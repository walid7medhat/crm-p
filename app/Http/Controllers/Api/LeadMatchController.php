<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadComment;
use App\Models\LeadListingMatch;
use App\Services\Matching\LeadMatcher;
use App\Services\Matching\MatchPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Match Studio: the agent's clients with homes waiting, one lead's matches,
 * shortlist/skip decisions, and sending offers on WhatsApp.
 */
class LeadMatchController extends Controller
{
    /** How long an on-read refresh of a lead's matches stays good. */
    private const FRESH_MINUTES = 30;

    private const QUEUE_LIMIT = 60;

    public function __construct(private LeadMatcher $matcher, private MatchPresenter $presenter)
    {
    }

    /** GET /lead-matches: the signed-in agent's own clients with homes waiting (the dock's Matches panel). */
    public function queue(Request $request)
    {
        $user = $request->user();
        $working = $this->matcher->workingStageIds();

        $rows = LeadListingMatch::query()
            ->where('status', LeadListingMatch::STATUS_NEW)
            ->whereHas('lead', fn ($q) => $q->where('responsible_person_id', $user->id)
                ->whereNull('archived_at')
                ->whereIn('stage_id', $working))
            ->with($this->listingRelations())
            ->orderByDesc('score')
            ->limit(1000)
            ->get()
            ->filter(fn ($row) => $row->listing && $row->listing->isActiveUnit());

        $byLead = $rows->groupBy('lead_id');
        $leads = Lead::query()->with('stage:id,name')->whereIn('id', $byLead->keys())->get()->keyBy('id');

        $clients = $byLead->map(function (Collection $leadRows, $leadId) use ($leads) {
            $lead = $leads->get($leadId);
            if (! $lead) {
                return null;
            }

            return $this->queueRow($lead, $leadRows);
        })->filter()->sortBy([
            fn ($a, $b) => $this->bucketRank($a['bucket']) <=> $this->bucketRank($b['bucket']),
            fn ($a, $b) => $b['best_score'] <=> $a['best_score'],
        ])->values()->take(self::QUEUE_LIMIT);

        return ApiResponse::success([
            'summary' => [
                'clients_waiting' => $clients->count(),
                'homes_waiting' => $clients->sum('matches_count'),
                'best_score' => (int) ($clients->max('best_score') ?? 0),
            ],
            'clients' => $clients,
        ]);
    }

    /** GET /leads/{lead}/matches: what fits this client now, refreshed at most every 30 minutes. */
    public function forLead(Request $request, Lead $lead)
    {
        if (! $request->user()->canViewLead($lead)) {
            return ApiResponse::error('You cannot view this lead.', 403);
        }

        $freshKey = $this->freshKey($lead->id);
        if (in_array((int) $lead->stage_id, $this->matcher->workingStageIds(), true) && ! Cache::has($freshKey)) {
            $this->matcher->refreshLead($lead);
            Cache::put($freshKey, true, now()->addMinutes(self::FRESH_MINUTES));
        }

        $rows = LeadListingMatch::query()
            ->where('lead_id', $lead->id)
            ->with($this->listingRelations())
            ->orderByDesc('score')
            ->get()
            ->filter(fn ($row) => $row->listing);

        $open = $rows->filter(fn ($row) => in_array($row->status, [LeadListingMatch::STATUS_NEW, LeadListingMatch::STATUS_SHORTLISTED], true)
            && $row->listing->isActiveUnit());
        $history = $rows->filter(fn ($row) => in_array($row->status, [LeadListingMatch::STATUS_SENT, LeadListingMatch::STATUS_SKIPPED], true))
            ->sortByDesc(fn ($row) => $row->sent_at ?? $row->decided_at)
            ->take(20);

        $lead->loadMissing('stage:id,name');

        return ApiResponse::success([
            'lead' => $this->presenter->lead($lead),
            'has_requirements' => $this->matcher->requirementsFor($lead) !== null,
            'why_now' => $this->whyNow($lead, $open),
            'matches' => $open->values()->map(fn ($row) => $this->presenter->match($row)),
            'history' => $history->values()->map(fn ($row) => $this->presenter->match($row)),
            'counts' => [
                'new' => $open->where('status', LeadListingMatch::STATUS_NEW)->count(),
                'shortlisted' => $open->where('status', LeadListingMatch::STATUS_SHORTLISTED)->count(),
                'sent' => $rows->where('status', LeadListingMatch::STATUS_SENT)->count(),
            ],
        ]);
    }

    /** POST /lead-matches/{match}/decision {action: shortlist|skip|reset} */
    public function decide(Request $request, LeadListingMatch $match)
    {
        $data = $request->validate(['action' => 'required|in:shortlist,skip,reset']);
        $lead = $match->lead;
        if (! $lead || ! $request->user()->canViewLead($lead)) {
            return ApiResponse::error('You cannot change matches for this lead.', 403);
        }
        if ($match->status === LeadListingMatch::STATUS_SENT) {
            return ApiResponse::error('This home was already sent to the client.', 422);
        }

        $status = match ($data['action']) {
            'shortlist' => LeadListingMatch::STATUS_SHORTLISTED,
            'skip' => LeadListingMatch::STATUS_SKIPPED,
            default => LeadListingMatch::STATUS_NEW,
        };
        $match->update([
            'status' => $status,
            'decided_by' => $data['action'] === 'reset' ? null : $request->user()->id,
            'decided_at' => $data['action'] === 'reset' ? null : now(),
        ]);
        // Skips re-rank this lead's other suggestions on the next load.
        Cache::forget($this->freshKey($lead->id));

        return ApiResponse::success([
            'id' => $match->id,
            'status' => $match->status,
            'learned' => $data['action'] === 'skip' ? $this->matcher->learnNote($match, $lead) : null,
        ]);
    }

    /**
     * POST /leads/{lead}/matches/send {match_ids: []}
     * Marks the homes as sent, logs the message on the lead's timeline, and returns the
     * WhatsApp text and link for the agent to send from their own WhatsApp.
     */
    public function send(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'match_ids' => 'required|array|min:1|max:10',
            'match_ids.*' => 'integer',
        ]);
        $user = $request->user();
        if (! $user->canViewLead($lead)) {
            return ApiResponse::error('You cannot send matches for this lead.', 403);
        }

        $rows = LeadListingMatch::query()
            ->where('lead_id', $lead->id)
            ->whereIn('id', $data['match_ids'])
            ->whereIn('status', [LeadListingMatch::STATUS_NEW, LeadListingMatch::STATUS_SHORTLISTED])
            ->with($this->listingRelations())
            ->orderByDesc('score')
            ->get()
            ->filter(fn ($row) => $row->listing);
        if ($rows->isEmpty()) {
            return ApiResponse::error('None of these homes can be sent. Refresh and try again.', 422);
        }

        $message = $this->presenter->whatsappMessage($lead, $rows, $user->name);
        $phone = $this->presenter->phoneDigits($lead->whatsapp_number ?: $lead->work_phone);

        DB::transaction(function () use ($rows, $user, $lead, $message) {
            LeadListingMatch::query()->whereIn('id', $rows->pluck('id'))->update([
                'status' => LeadListingMatch::STATUS_SENT,
                'sent_at' => now(),
                'decided_by' => $user->id,
                'decided_at' => now(),
            ]);
            LeadComment::create([
                'lead_id' => $lead->id,
                'user_id' => $user->id,
                'comment' => "Sent {$rows->count()} matching ".($rows->count() === 1 ? 'home' : 'homes')." on WhatsApp from Match Studio:\n\n".$message,
            ]);
        });

        return ApiResponse::success([
            'sent_count' => $rows->count(),
            'message' => $message,
            'phone' => $phone,
            'whatsapp_url' => $phone ? 'https://wa.me/'.$phone.'?text='.rawurlencode($message) : null,
        ], 'Logged on the lead');
    }

    /**
     * POST /lead-matches/{match}/offer {offer_number?}
     * Called once the browser has built the sales offer PDF (the property page's own
     * "Generate Offer", which saves the offer record). Marks the home sent, logs it on
     * the lead, and hands back the WhatsApp message and link.
     */
    public function offer(Request $request, LeadListingMatch $match)
    {
        $data = $request->validate(['offer_number' => 'nullable|string|max:40']);
        $user = $request->user();
        $lead = $match->lead;
        if (! $lead || ! $user->canViewLead($lead)) {
            return ApiResponse::error('You cannot send offers for this lead.', 403);
        }
        $match->load($this->listingRelations());
        if (! $match->listing) {
            return ApiResponse::error('This listing is no longer available.', 422);
        }

        $offerNumber = $data['offer_number'] ?? null;
        $phone = $this->presenter->phoneDigits($lead->whatsapp_number ?: $lead->work_phone);
        $message = $this->presenter->offerMessage($lead, $match, $user, $offerNumber);
        $what = $offerNumber ? "sales offer {$offerNumber}" : 'a sales offer';

        DB::transaction(function () use ($match, $lead, $user, $message, $what) {
            $match->update([
                'status' => LeadListingMatch::STATUS_SENT,
                'sent_at' => now(),
                'decided_by' => $user->id,
                'decided_at' => now(),
            ]);
            LeadComment::create([
                'lead_id' => $lead->id,
                'user_id' => $user->id,
                'comment' => "Sent {$what} (PDF) on WhatsApp from Match Studio:\n\n".$message,
            ]);
        });

        return ApiResponse::success([
            'match_id' => $match->id,
            'listing_id' => $match->listing_id,
            'offer_number' => $offerNumber,
            'message' => $message,
            'phone' => $phone,
            'whatsapp_url' => $phone ? 'https://wa.me/'.$phone.'?text='.rawurlencode($message) : null,
        ], 'Logged on the lead');
    }

    private function queueRow(Lead $lead, Collection $rows): array
    {
        $bucket = $rows->contains('bucket', LeadListingMatch::BUCKET_PRICE_DROP)
            ? LeadListingMatch::BUCKET_PRICE_DROP
            : ($rows->contains('bucket', LeadListingMatch::BUCKET_NEW) ? LeadListingMatch::BUCKET_NEW : LeadListingMatch::BUCKET_REVIVE);

        return [
            'lead' => [
                'id' => $lead->id,
                'name' => $lead->lead_name,
                'first_name' => $this->presenter->firstName($lead),
                'initials' => $this->presenter->initials($lead->lead_name),
                'stage' => $lead->stage?->name,
                'whatsapp' => $this->presenter->phoneDigits($lead->whatsapp_number ?: $lead->work_phone),
            ],
            // The home the row's one-tap "Send offer" sends.
            'best_match_id' => $rows->sortByDesc('score')->first()?->id,
            'best_listing_id' => $rows->sortByDesc('score')->first()?->listing_id,
            'bucket' => $bucket,
            'reason' => $this->whyNow($lead, $rows),
            'matches_count' => $rows->count(),
            'best_score' => (int) $rows->max('score'),
            'thumbs' => $rows->take(3)->map(fn ($row) => $this->presenter->listing($row->listing)['main_image'])->values(),
        ];
    }

    /** One line on why this client is worth a message today. */
    private function whyNow(Lead $lead, Collection $rows): ?string
    {
        if ($rows->isEmpty()) {
            return null;
        }
        $count = $rows->count();
        $homes = $count === 1 ? '1 home fits' : "{$count} homes fit";

        $drop = $rows->first(fn ($row) => $row->bucket === LeadListingMatch::BUCKET_PRICE_DROP && $row->previous_price);
        if ($drop) {
            $pct = (int) round((1 - (float) $drop->matched_price / (float) $drop->previous_price) * 100);
            $place = $drop->listing?->project?->title ?: $drop->listing?->area?->name ?: 'A listing';

            return "{$place} dropped {$pct}%. {$homes}.";
        }

        $quiet = $this->matcher->quietDays($lead);
        if ($quiet >= LeadMatcher::QUIET_DAYS) {
            return "Quiet for {$quiet} days. {$homes}.";
        }
        if ($lead->created_at && $lead->created_at->greaterThan(now()->subDays(2))) {
            return "New lead. {$homes} from your stock.";
        }

        return "{$homes} what they asked for.";
    }

    private function bucketRank(string $bucket): int
    {
        return match ($bucket) {
            LeadListingMatch::BUCKET_PRICE_DROP => 0,
            LeadListingMatch::BUCKET_NEW => 1,
            default => 2,
        };
    }

    private function listingRelations(): array
    {
        return [
            'listing' => fn ($q) => $q->with([
                'area:id,name,parent_id,type',
                'project:id,title,area_id',
                'propertyType:id,name',
                'firstGalleryImage',
            ]),
        ];
    }

    private function freshKey(int $leadId): string
    {
        return 'lead-matches:fresh:'.$leadId;
    }
}
