<?php

namespace App\Services\Listing;

use App\Models\Area;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\ListingNeedsApproval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Who approves (and rejects) a listing:
 *  - super_admin / admin: every listing.
 *  - Dubai listings (area inside the Dubai city): the branch_admin of the Dubai branch —
 *    NOT the listing-team managers / team leads.
 *  - every other listing: the listing team, as before (manager, listing_team user,
 *    team_lead with is_listing_team).
 */
class ListingApprovalPolicy
{
    public function isGlobalApprover(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->hasRole('admin');
    }

    /** branch_admin whose branch (the node right under super admin) is "Dubai". */
    public function isDubaiApprover(User $user): bool
    {
        if (! $user->hasRole('branch_admin')) {
            return false;
        }

        $branch = $user->getBranchNode();

        return $branch && strcasecmp(trim((string) $branch->name), 'dubai') === 0;
    }

    /**
     * The Dubai branch admin may also edit Dubai listings (the ones they approve).
     * Unit number and owner stay hidden from them — ListingRequest keeps the saved
     * values on their edits.
     */
    public function canEditAsDubaiApprover(User $user, Listing $listing): bool
    {
        return $listing->isInDubai() && $this->isDubaiApprover($user);
    }

    /** The pre-existing listing-team rule, now only for non-Dubai listings. */
    public function isListingTeamApprover(User $user): bool
    {
        return $user->hasRole('manager') || (bool) $user->listing_team
            || ($user->hasRole('team_lead') && $user->is_listing_team);
    }

    /** May approve at least some listings (pending-approvals page / counts). */
    public function canApproveAny(User $user): bool
    {
        return $this->isGlobalApprover($user) || $this->isDubaiApprover($user) || $this->isListingTeamApprover($user);
    }

    public function canApprove(User $user, Listing $listing): bool
    {
        if ($this->isGlobalApprover($user)) {
            return true;
        }

        return $listing->isInDubai()
            ? $this->isDubaiApprover($user)
            : $this->isListingTeamApprover($user);
    }

    /**
     * Narrow a pending-approval query to the listings $user may approve.
     * Team leads keep their existing restriction to their own two-level hierarchy.
     */
    public function restrictToApprovable(Builder $query, User $user): Builder
    {
        if ($this->isGlobalApprover($user)) {
            return $query;
        }

        $dubaiApprover = $this->isDubaiApprover($user);
        $teamApprover = $this->isListingTeamApprover($user);
        if (! $dubaiApprover && ! $teamApprover) {
            return $query->whereRaw('1 = 0');
        }

        $dubaiAreaIds = Area::dubaiAreaIds();

        return $query->where(function (Builder $scope) use ($user, $dubaiApprover, $teamApprover, $dubaiAreaIds) {
            if ($dubaiApprover) {
                $scope->orWhereIn('area_id', $dubaiAreaIds ?: [0]);
            }

            if ($teamApprover) {
                $scope->orWhere(function (Builder $outsideDubai) use ($user, $dubaiAreaIds) {
                    $outsideDubai->where(function (Builder $q) use ($dubaiAreaIds) {
                        $q->whereNull('area_id')->orWhereNotIn('area_id', $dubaiAreaIds ?: [0]);
                    });

                    if ($user->hasRole('team_lead') && $user->is_listing_team && ! $user->hasRole('manager')) {
                        $outsideDubai->whereIn('agent_id', $this->teamLeadHierarchyIds($user));
                    }
                });
            }
        });
    }

    /** Active Dubai-branch branch_admins. */
    public function dubaiApprovers(): Collection
    {
        // Only the current company's admins (OIA Dubai admin never gets Luxvant listings).
        return \App\Support\Brand\BrandIsolation::constrainUsers(User::role('branch_admin'))
            ->where('status', 'active')
            ->get()
            ->filter(fn (User $user) => $this->isDubaiApprover($user))
            ->values();
    }

    /**
     * Tell whoever approves this listing that it is waiting. Returns the notified
     * user ids (for logging).
     */
    public function notifyApprovers(Listing $listing): array
    {
        if ($listing->isInDubai() && \App\Support\Brand\BrandIsolation::trustedKey() === 'luxvant') {
            // Luxvant has no Dubai branch admin: its Dubai listings are approved by
            // Luxvant admins (or super_admin) only, so they get the notification.
            $recipients = \App\Support\Brand\BrandIsolation::constrainUsers(User::role('admin'))
                ->where('status', 'active')
                ->get();
        } elseif ($listing->isInDubai()) {
            $recipients = $this->dubaiApprovers();
        } else {
            $recipients = \App\Support\Brand\BrandIsolation::constrainUsers(User::role('manager'))
                ->where('listing_team', 1)
                ->where('status', 'active')
                ->get();

            $teamLead = $listing->agent?->parent;
            if ($teamLead && \App\Support\Brand\BrandIsolation::userBelongsToCurrentBrand($teamLead) && $teamLead->hasRole('team_lead') && $teamLead->is_listing_team && $teamLead->status === 'active') {
                $recipients->push($teamLead);
            }
        }

        $recipients = $recipients->unique('id');
        foreach ($recipients as $recipient) {
            $recipient->notify(new ListingNeedsApproval($listing));
        }

        return $recipients->pluck('id')->all();
    }

    /** Self + direct reports + their reports (what team leads have always been limited to). */
    private function teamLeadHierarchyIds(User $user): array
    {
        $childIds = User::where('parent_id', $user->id)->pluck('id');
        $grandchildIds = $childIds->isEmpty() ? collect() : User::whereIn('parent_id', $childIds)->pluck('id');

        return $childIds->merge($grandchildIds)->push($user->id)->unique()->values()->all();
    }
}
