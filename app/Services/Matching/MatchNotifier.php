<?php

namespace App\Services\Matching;

use App\Models\Lead;
use App\Models\LeadListingMatch;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\LeadMatchesNotification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Tells the responsible agent about matches they haven't seen. Rows are stamped
 * notified_at so the same match never alerts twice, and alerts are grouped per agent
 * so one new listing that fits ten clients sends one notification, not ten.
 */
class MatchNotifier
{
    /** Leads owned by the system user (#1) have no agent to tell. */
    private const UNASSIGNED_USER_ID = 1;

    /** New matches for one lead (new lead, or its requirements changed). */
    public function leadMatches(Lead $lead, Collection $rows): void
    {
        $rows = $rows->filter(fn (LeadListingMatch $row) => $row->notified_at === null)->values();
        $agent = $this->agentFor($lead);
        if ($rows->isEmpty() || ! $agent) {
            return;
        }

        // The matcher hands back a plain collection; load() needs an Eloquent one.
        $rows = (new EloquentCollection($rows->all()))->load('listing.project:id,title', 'listing.area:id,name');
        $best = $rows->sortByDesc('score')->first();
        $drop = $rows->firstWhere('bucket', LeadListingMatch::BUCKET_PRICE_DROP);
        $name = $lead->lead_name ?: 'your client';

        $message = $drop && $rows->count() === 1
            ? 'Price dropped on '.$this->place($drop).'. It now fits '.$name.'.'
            : ($rows->count() === 1 ? '1 of your listings fits ' : $rows->count().' of your listings fit ').$name
                .' (best match '.$best->score.'%).';

        $agent->notify(new LeadMatchesNotification([
            'message' => $message,
            'lead_id' => $lead->id,
            'matches_count' => $rows->count(),
            'best_score' => $best->score,
            'bucket' => $drop ? LeadListingMatch::BUCKET_PRICE_DROP : $best->bucket,
        ]));

        $this->stamp($rows);
    }

    /** A listing went live or dropped its price and fits several leads, maybe across agents. */
    public function listingMatches(Listing $listing, Collection $rows, bool $priceDropped): void
    {
        $rows = $rows->filter(fn (LeadListingMatch $row) => $row->notified_at === null && $row->lead);

        foreach ($rows->groupBy(fn ($row) => (int) $row->lead->responsible_person_id) as $agentId => $agentRows) {
            if ($agentRows->count() === 1) {
                $this->leadMatches($agentRows->first()->lead, $agentRows);
                continue;
            }
            $agent = $agentId !== self::UNASSIGNED_USER_ID ? User::query()->find($agentId) : null;
            if (! $agent) {
                continue;
            }

            $place = $this->place($agentRows->first());
            $agent->notify(new LeadMatchesNotification([
                'message' => $priceDropped
                    ? "Price dropped on {$place}. It now fits {$agentRows->count()} of your clients."
                    : "New listing {$place} fits {$agentRows->count()} of your clients.",
                'listing_match_id' => $listing->id,
                'matches_count' => $agentRows->count(),
                'best_score' => (int) $agentRows->max('score'),
                'bucket' => $priceDropped ? LeadListingMatch::BUCKET_PRICE_DROP : LeadListingMatch::BUCKET_NEW,
            ]));
            $this->stamp($agentRows);
        }
    }

    /** Morning digest: quiet clients that got new matches overnight. */
    public function quietDigest(User $agent, Collection $rows): void
    {
        $rows = $rows->filter(fn (LeadListingMatch $row) => $row->notified_at === null);
        if ($rows->isEmpty()) {
            return;
        }

        $clients = $rows->pluck('lead_id')->unique()->count();
        $agent->notify(new LeadMatchesNotification([
            'message' => ($clients === 1 ? '1 quiet client has' : "{$clients} quiet clients have")
                .' new matches. No contact for '.LeadMatcher::QUIET_DAYS.'+ days.',
            'matches_count' => $rows->count(),
            'clients_count' => $clients,
            'bucket' => LeadListingMatch::BUCKET_REVIVE,
        ]));
        $this->stamp($rows);
    }

    private function agentFor(Lead $lead): ?User
    {
        $id = (int) $lead->responsible_person_id;

        return $id && $id !== self::UNASSIGNED_USER_ID ? User::query()->find($id) : null;
    }

    private function place(LeadListingMatch $row): string
    {
        $listing = $row->listing;
        $place = implode(', ', array_filter([$listing?->project?->title, $listing?->area?->name]));
        $beds = $listing?->number_of_bedrooms;
        $bedsLabel = $beds === null ? '' : ($beds === 0 ? 'Studio ' : $beds.'BR ');

        return trim($bedsLabel.($place ?: 'a listing'));
    }

    private function stamp(Collection $rows): void
    {
        LeadListingMatch::query()->whereIn('id', $rows->pluck('id')->all())->update(['notified_at' => now()]);
    }
}
