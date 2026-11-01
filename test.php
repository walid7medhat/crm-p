<?php

/**
 * Match Studio test data — LOCAL DEV ONLY, command line only.
 *
 *   php test.php                 Give 4 leads of user #25 requirements copied from active listings, then match them
 *   php test.php --user=59 --leads=3
 *   php test.php --notify        Also send the agent one match notification (needs `php artisan queue:work`)
 *   php test.php --phone=+971521152141   Put this WhatsApp number on the seeded leads (works on an existing run too)
 *   php test.php --check         Only check the setup (table, routes, command), change nothing
 *   php test.php --new-listing   NEW PROPERTY MATCH: add a copy of a listing that fits a test client, run the
 *                                real matching + notification, and show who got alerted
 *   php test.php --price-drop    PRICE DROP: lower a matched listing's price 6% and run the same flow
 *   php test.php --restore       Put the leads back exactly as they were, delete test listings and matches, undo price drops
 *
 * The original lead values are saved to storage/app/match-studio-test-backup.json before anything changes.
 * Do not commit this file.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Lead;
use App\Models\LeadListingMatch;
use App\Models\Listing;
use App\Models\User;
use App\Services\Matching\LeadMatcher;
use App\Services\Matching\MatchNotifier;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

$opts = getopt('', ['user::', 'leads::', 'phone::', 'notify', 'check', 'restore', 'new-listing', 'price-drop']);
$userId = (int) ($opts['user'] ?? 25);
$leadCount = max(1, min(10, (int) ($opts['leads'] ?? 4)));
$backupFile = storage_path('app/match-studio-test-backup.json');
$fields = ['area_id', 'property_type_id', 'bedrooms', 'budget', 'budget_from', 'budget_to', 'lead_type', 'property_status', 'last_engagement_at', 'whatsapp_number'];

$ok = fn ($msg) => print("  \033[32m✔\033[0m {$msg}\n");
$bad = fn ($msg) => print("  \033[31m✘\033[0m {$msg}\n");
$line = fn ($msg = '') => print($msg."\n");

/** Put the test WhatsApp number on the seeded leads, keeping each lead's own number in the backup. */
$applyPhone = function (string $phone) use ($backupFile, $ok) {
    $backup = json_decode(file_get_contents($backupFile), true);
    foreach (array_keys($backup['leads']) as $id) {
        if (! array_key_exists('whatsapp_number', $backup['leads'][$id])) {
            $backup['leads'][$id]['whatsapp_number'] = DB::table('leads')->where('id', $id)->value('whatsapp_number');
        }
        DB::table('leads')->where('id', $id)->update(['whatsapp_number' => $phone]);
        $ok("Lead #{$id} WhatsApp → {$phone}");
    }
    file_put_contents($backupFile, json_encode($backup, JSON_PRETTY_PRINT));
};

// ------------------------------------------------------------------ checks
$line("\nMatch Studio setup");
$tableOk = Schema::hasTable('lead_listing_matches');
$tableOk ? $ok('lead_listing_matches table exists') : $bad('lead_listing_matches table missing — run: php artisan migrate');
$routes = collect(Route::getRoutes())->map(fn ($r) => $r->uri())->filter(fn ($u) => str_contains($u, 'matches'))->values();
$routes->count() >= 5 ? $ok('API routes: '.$routes->implode(', ')) : $bad('API routes missing');
array_key_exists('matches:scan-quiet', Artisan::all()) ? $ok('matches:scan-quiet command registered') : $bad('matches:scan-quiet command missing');
$ok('queue connection: '.config('queue.default').(config('queue.default') === 'sync' ? '' : ' (run `php artisan queue:work` for live notifications)'));
$matcher = app(LeadMatcher::class);
$ok('working stages: '.DB::table('stages')->whereIn('id', $matcher->workingStageIds())->pluck('name')->implode(', '));
$ok('active listings: '.Listing::query()->activeUnit()->count());

if (! $tableOk || isset($opts['check'])) {
    $line();
    exit($tableOk ? 0 : 1);
}

// ------------------------------------------------------------------ restore
if (isset($opts['restore'])) {
    if (! is_file($backupFile)) {
        $bad('Nothing to restore (no backup file).');
        exit(1);
    }
    $backup = json_decode(file_get_contents($backupFile), true);
    foreach ($backup['created_listings'] ?? [] as $id) {
        // Its matches go with it (cascade).
        DB::table('listings')->where('id', $id)->delete();
        $ok("Test listing #{$id} deleted");
    }
    foreach ($backup['prices'] ?? [] as $id => $price) {
        DB::table('listings')->where('id', $id)->update(['price' => $price]);
        $ok("Listing #{$id} price put back to AED ".number_format((float) $price));
    }
    foreach ($backup['leads'] as $id => $values) {
        DB::table('leads')->where('id', $id)->update($values);
        LeadListingMatch::where('lead_id', $id)->delete();
        cache()->forget('lead-matches:fresh:'.$id);
        $ok("Lead #{$id} restored, its matches deleted");
    }
    $ok('Comments logged by "Send on WhatsApp" while testing stay on the leads; delete them by hand if needed.');
    unlink($backupFile);
    $line("\nDone.\n");
    exit(0);
}

// ------------------------------------------------------------------ new listing / price drop
if (isset($opts['new-listing']) || isset($opts['price-drop'])) {
    if (! is_file($backupFile)) {
        $bad('Seed test clients first: php test.php');
        exit(1);
    }
    $backup = json_decode(file_get_contents($backupFile), true);
    $leadIds = array_map('intval', array_keys($backup['leads']));

    // The best match of the seeded clients is the listing we copy or discount.
    $match = LeadListingMatch::whereIn('lead_id', $leadIds)->orderByDesc('score')->with('listing', 'lead')->first();
    if (! $match || ! $match->listing) {
        $bad('The test clients have no matches to start from. Run `php test.php --restore` then `php test.php`.');
        exit(1);
    }
    $template = $match->listing;
    $agentName = User::find($match->lead->responsible_person_id)?->name ?? 'the agent';
    $notificationsBefore = DB::table('notifications')->where('type', App\Notifications\LeadMatchesNotification::class)->max('created_at');

    if (isset($opts['new-listing'])) {
        $line("\nNew property match test");
        // A fresh copy of a listing that already fits, 3% cheaper so it stays in budget.
        // Saved through Eloquent, so the real "listing created" observer fires.
        $copy = $template->replicate(['reference_number']);
        $copy->reference_number = null;
        $copy->price = round((float) $template->price * 0.97);
        $copy->created_at = now();
        $copy->save();
        $backup['created_listings'][] = $copy->id;
        file_put_contents($backupFile, json_encode($backup, JSON_PRETTY_PRINT));
        $ok("Created listing #{$copy->id} ({$copy->reference_number}), a copy of #{$template->id} at AED ".number_format((float) $copy->price));
        $watched = $copy;
    } else {
        $line("\nPrice drop test");
        $backup['prices'][$template->id] ??= (float) $template->price;
        file_put_contents($backupFile, json_encode($backup, JSON_PRETTY_PRINT));
        // Mark it handled first: a price drop brings a home back even after it was sent or skipped.
        $match->update(['status' => LeadListingMatch::STATUS_SKIPPED]);
        $newPrice = round((float) $template->price * 0.94);
        $template->update(['price' => $newPrice]);
        $ok("Listing #{$template->id} price AED ".number_format((float) $backup['prices'][$template->id]).' → AED '.number_format($newPrice));
        $watched = $template;
    }

    // What a queue worker would do: run the matching job and send the notifications now.
    $line('  Running the queue (matching + notifications)…');
    Artisan::call('queue:work', ['--stop-when-empty' => true, '--tries' => 1]);

    $rows = LeadListingMatch::where('listing_id', $watched->id)->with('lead:id,lead_name,responsible_person_id')->orderByDesc('score')->get();
    $rows->isEmpty()
        ? $bad('No client matched this listing.')
        : $ok($rows->count().' client(s) matched:');
    foreach ($rows as $row) {
        $line("     {$row->score}%  {$row->lead?->lead_name}  [{$row->status} · {$row->bucket}]");
    }
    $sent = DB::table('notifications')
        ->where('type', App\Notifications\LeadMatchesNotification::class)
        ->when($notificationsBefore, fn ($q) => $q->where('created_at', '>', $notificationsBefore))
        ->get(['notifiable_id', 'data']);
    $sent->isEmpty()
        ? $bad('No notification was sent (already notified for these clients, or no agent on the lead).')
        : $ok($sent->count().' notification(s) sent:');
    foreach ($sent as $n) {
        $line('     → user #'.$n->notifiable_id.': '.(json_decode($n->data, true)['message'] ?? ''));
    }
    $line("\nSigned in as {$agentName}? The bell shows it now. With Pusher connected you also get the gold toast.");
    $line("Undo with: php test.php --restore\n");
    exit(0);
}

// ------------------------------------------------------------------ seed
$agent = User::find($userId);
if (! $agent) {
    $bad("User #{$userId} not found.");
    exit(1);
}
if (is_file($backupFile)) {
    if (! empty($opts['phone'])) {
        $line("\nTest run already in place: only setting the WhatsApp number");
        $applyPhone($opts['phone']);
        $line("\nOpen the dock's Matches panel, pick a client, and press Send offer in Match Studio.\n");
        exit(0);
    }
    $bad('A previous test run is still in place. Run `php test.php --restore` first.');
    exit(1);
}

$leads = Lead::query()
    ->whereNull('archived_at')
    ->where('responsible_person_id', $userId)
    ->whereIn('stage_id', $matcher->workingStageIds())
    ->latest('id')
    ->limit($leadCount)
    ->get();
if ($leads->isEmpty()) {
    $bad("User #{$userId} has no leads in a working stage.");
    exit(1);
}

$listings = Listing::query()
    ->activeUnit()
    ->whereNotNull('area_id')
    ->whereNotNull('number_of_bedrooms')
    ->where('price', '>', 0)
    ->latest('id')
    ->limit($leadCount)
    ->get();
if ($listings->isEmpty()) {
    $bad('No active listings with area, bedrooms and price.');
    exit(1);
}

$backup = ['user_id' => $userId, 'leads' => []];
foreach ($leads as $lead) {
    $backup['leads'][$lead->id] = collect($fields)->mapWithKeys(fn ($f) => [$f => $lead->getRawOriginal($f)])->all();
}
file_put_contents($backupFile, json_encode($backup, JSON_PRETTY_PRINT));

$line("\nSeeding {$leads->count()} leads of {$agent->name} (#{$userId})");

// Each lead copies one listing, with a twist so every kind of card shows up.
$scenarios = ['exact', 'over_budget', 'quiet', 'one_more_bedroom'];
foreach ($leads->values() as $i => $lead) {
    $listing = $listings[$i % $listings->count()];
    $scenario = $scenarios[$i % count($scenarios)];
    $beds = (int) $listing->number_of_bedrooms;

    $values = [
        'area_id' => $listing->area_id,
        'property_type_id' => $listing->property_type_id,
        'bedrooms' => $scenario === 'one_more_bedroom' ? $beds + 1 : $beds,
        'budget_from' => null,
        'budget_to' => round($listing->price * ($scenario === 'over_budget' ? 0.97 : 1.05)),
        'lead_type' => str_contains(strtolower((string) $listing->listing_status), 'rent') ? 'rent' : 'sale',
        'property_status' => null,
    ];
    if ($scenario === 'quiet') {
        $values['last_engagement_at'] = now()->subDays(LeadMatcher::QUIET_DAYS + 30);
    }
    // Straight to the row: no model events, so nothing else (scoring, assignment) reacts.
    DB::table('leads')->where('id', $lead->id)->update($values);
    $lead->refresh();

    $rows = $matcher->refreshLead($lead);
    cache()->forget('lead-matches:fresh:'.$lead->id);
    $all = LeadListingMatch::where('lead_id', $lead->id)->orderByDesc('score')->get();
    $line("\n  Lead #{$lead->id} {$lead->lead_name}  [{$scenario}]  → {$all->count()} matches");
    foreach ($all->take(3) as $m) {
        $gaps = implode(', ', $m->reasons['gaps'] ?? []);
        $line("     {$m->score}%  listing #{$m->listing_id}  ".implode(' · ', $m->reasons['fits'] ?? []).($gaps ? "  (gaps: {$gaps})" : ''));
    }

    // Show a price-drop card on the first lead.
    if ($i === 0 && ($top = $all->first())) {
        $top->update([
            'bucket' => LeadListingMatch::BUCKET_PRICE_DROP,
            'previous_price' => round((float) $top->matched_price * 1.06),
        ]);
        $line('     (marked as a 6% price drop so the Price drops section shows)');
    }

    if ($i === 0 && isset($opts['notify'])) {
        app(MatchNotifier::class)->leadMatches($lead, $all);
        $ok('Notification queued for '.$agent->name);
    }
}

if (! empty($opts['phone'])) {
    $applyPhone($opts['phone']);
}

$base = rtrim(config('app.frontend_url', config('app.url')), '/');
$line("\nNow sign in as {$agent->name} ({$agent->email}) — or impersonate them — and open:");
$line("  The floating dock → Matches (sparkle button): clients with homes waiting → Match Studio");
$line("  Bell / toast match alert → Match Studio for that client, then the lead when you close it");
$line("\nWhen you're done:  php test.php --restore\n");
