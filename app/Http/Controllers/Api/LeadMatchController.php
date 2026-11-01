<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadComment;
use App\Models\LeadListingMatch;
use App\Models\User;
use App\Services\Matching\LeadMatcher;
use App\Services\Matching\MatchPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Match Studio: the agent's queue of clients with homes waiting, one lead's matches,
 * shortlist/skip decisions, sending on WhatsApp, and the managers' team board.
 */
class LeadMatchController extends Controller
{
    /** How long an on-read refresh of a lead's matches stays good. */
    private const FRESH_MINUTES = 30;

    private const QUEUE_LIMIT = 60;

    private const TEAM_ROLES = ['super_admin', 'admin', 'manager', 'team_lead', 'branch_admin'];

    public function __construct(private LeadMatcher $matcher, private MatchPresenter $presenter)
    {
    }

    /** GET /lead-matches: the signed-in agent's own clients with homes waiting, plus who they sent to today. */
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

        $sentToday = LeadListingMatch::query()
            ->where('status', LeadListingMatch::STATUS_SENT)
            ->where('decided_by', $user->id)
            ->where('sent_at', '>=', now()->startOfDay())
            ->select('lead_id', DB::raw('count(*) as homes'))
            ->groupBy('lead_id')
            ->get();
        $sentLeads = Lead::query()->whereIn('id', $sentToday->pluck('lead_id'))->get(['id', 'lead_name', 'first_name'])->keyBy('id');

        return ApiResponse::success([
            'summary' => [
                'clients_waiting' => $clients->count(),
                'homes_waiting' => $clients->sum('matches_count'),
                'sent_today' => $sentToday->count(),
                'best_score' => (int) ($clients->max('best_score') ?? 0),
                'minutes' => max(2, $clients->count() * 2),
            ],
            'clients' => $clients,
            'sent_today' => $sentToday->map(fn ($row) => [
                'lead_id' => (int) $row->lead_id,
                'name' => $sentLeads->get($row->lead_id)?->lead_name,
                'initials' => $this->presenter->initials($sentLeads->get($row->lead_id)?->lead_name),
                'homes' => (int) $row->homes,
            ])->values(),
            'can_view_team' => $user->hasAnyRole(self::TEAM_ROLES),
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

    /** GET /lead-matches/team: agents in the viewer's scope, ranked by homes sent in the last 7 days. */
    public function team(Request $request)
    {
        $user = $request->user();
        if (! $user->hasAnyRole(self::TEAM_ROLES)) {
            return ApiResponse::error('Only managers can see the team board.', 403);
        }
        $limit = max(1, min(50, (int) $request->input('limit', 5)));
        $scopeIds = $user->hasRole('super_admin') ? null : array_map('intval', $user->leadScopeUserIds());
        $working = $this->matcher->workingStageIds();

        $base = fn () => LeadListingMatch::query()
            ->join('leads', 'leads.id', '=', 'lead_listing_matches.lead_id')
            ->whereNull('leads.archived_at')
            ->where('leads.responsible_person_id', '!=', 1)
            ->when($scopeIds !== null, fn ($q) => $q->whereIn('leads.responsible_person_id', $scopeIds ?: [-1]));

        $waiting = $base()
            ->where('lead_listing_matches.status', LeadListingMatch::STATUS_NEW)
            ->whereIn('leads.stage_id', $working)
            ->groupBy('leads.responsible_person_id')
            ->get([
                'leads.responsible_person_id as agent_id',
                DB::raw('count(*) as homes'),
                DB::raw('count(distinct lead_listing_matches.lead_id) as clients'),
            ])->keyBy('agent_id');

        $sent = $base()
            ->where('lead_listing_matches.status', LeadListingMatch::STATUS_SENT)
            ->where('lead_listing_matches.sent_at', '>=', now()->subDays(7))
            ->groupBy('leads.responsible_person_id')
            ->get([
                'leads.responsible_person_id as agent_id',
                DB::raw('count(*) as homes'),
                DB::raw('count(distinct lead_listing_matches.lead_id) as clients'),
            ])->keyBy('agent_id');

        $agentIds = $waiting->keys()->merge($sent->keys())->unique()->map(fn ($id) => (int) $id);
        $users = User::query()->whereIn('id', $agentIds)->get(['id', 'name'])->keyBy('id');

        $agents = $agentIds->map(fn ($id) => [
            'id' => $id,
            'name' => $users->get($id)?->name ?? 'Agent #'.$id,
            'initials' => $this->presenter->initials($users->get($id)?->name),
            'is_you' => $id === (int) $user->id,
            'waiting_homes' => (int) ($waiting->get($id)?->homes ?? 0),
            'waiting_clients' => (int) ($waiting->get($id)?->clients ?? 0),
            'sent_homes' => (int) ($sent->get($id)?->homes ?? 0),
            'reached_clients' => (int) ($sent->get($id)?->clients ?? 0),
        ])->sortBy([
            fn ($a, $b) => $b['reached_clients'] <=> $a['reached_clients'],
            fn ($a, $b) => $b['sent_homes'] <=> $a['sent_homes'],
        ])->values();

        $top = $agents->take($limit)->map(function ($agent) use ($base, $working) {
            $agent['leads'] = $base()
                ->where('leads.responsible_person_id', $agent['id'])
                ->where('lead_listing_matches.status', LeadListingMatch::STATUS_NEW)
                ->whereIn('leads.stage_id', $working)
                ->groupBy('lead_listing_matches.lead_id', 'leads.lead_name')
                ->orderByDesc(DB::raw('max(lead_listing_matches.score)'))
                ->limit(5)
                ->get([
                    'lead_listing_matches.lead_id',
                    'leads.lead_name',
                    DB::raw('count(*) as homes'),
                    DB::raw('max(lead_listing_matches.score) as best'),
                ])->map(fn ($row) => [
                    'lead_id' => (int) $row->lead_id,
                    'name' => $row->lead_name,
                    'initials' => $this->presenter->initials($row->lead_name),
                    'homes' => (int) $row->homes,
                    'best' => (int) $row->best,
                ])->values();

            return $agent;
        });

        return ApiResponse::success([
            'agents' => $top,
            'total_agents' => $agents->count(),
            'unsent_alert' => $agents->filter(fn ($a) => $a['waiting_homes'] >= 5)->values()->map(fn ($a) => [
                'name' => $a['name'],
                'homes' => $a['waiting_homes'],
            ]),
        ]);
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
            ],
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
