<?php

/**
 * Match Studio test data — LOCAL DEV ONLY, command line only.
 *
 *   php test.php                 Give 4 leads of user #25 requirements copied from active listings, then match them
 *   php test.php --user=59 --leads=3
 *   php test.php --notify        Also send the agent one match notification (needs `php artisan queue:work`)
 *   php test.php --check         Only check the setup (table, routes, command), change nothing
 *   php test.php --restore       Put the leads back exactly as they were and delete their test matches
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

$opts = getopt('', ['user::', 'leads::', 'notify', 'check', 'restore']);
$userId = (int) ($opts['user'] ?? 25);
$leadCount = max(1, min(10, (int) ($opts['leads'] ?? 4)));
$backupFile = storage_path('app/match-studio-test-backup.json');
$fields = ['area_id', 'property_type_id', 'bedrooms', 'budget', 'budget_from', 'budget_to', 'lead_type', 'property_status', 'last_engagement_at'];

$ok = fn ($msg) => print("  \033[32m✔\033[0m {$msg}\n");
$bad = fn ($msg) => print("  \033[31m✘\033[0m {$msg}\n");
$line = fn ($msg = '') => print($msg."\n");

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

// ------------------------------------------------------------------ seed
$agent = User::find($userId);
if (! $agent) {
    $bad("User #{$userId} not found.");
    exit(1);
}
if (is_file($backupFile)) {
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

$base = rtrim(config('app.frontend_url', config('app.url')), '/');
$line("\nNow sign in as {$agent->name} ({$agent->email}) — or impersonate them — and open:");
$line("  {$base}/matches/mine          My Matches + Start review");
$line("  {$base}/matches/team          Team board (managers / admins)");
$line("  {$base}/kanban?lead={$leads->first()->id}   Lead modal → Matches tab");
$line("\nWhen you're done:  php test.php --restore\n");
