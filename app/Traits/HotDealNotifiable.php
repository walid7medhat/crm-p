<?php

namespace App\Traits;

use App\Notifications\HotDealRequestNotification;
use App\Notifications\HotDealStatusNotification;
use App\Models\User;
use App\Models\Listing;

trait HotDealNotifiable
{
    /**
     * Get the manager and team lead hierarchy for approval
     */
           protected function getApproversForHotDeal(Listing $listing)
        {
            $approvers = [];
            $agent = $listing->agent;
        
            if (!$agent) {
                return collect();
            }
        
            $current = $agent;
        
            if ($agent->is_listing_team) {
        
                while ($current && $current->parent_id) {
                    $current = $current->parent;
        
                    if (!$current) break;
        
                    if ($current->hasRole('team_lead') || $current->hasRole('manager')) {
                        $approvers[] = $current;
                    }
        
                    if ($current->hasRole('manager')) {
                        break;
                    }
                }
        
            } 
            else {
                $approvers = User::role('manager')
                    ->get()
                    ->filter(fn ($user) => $user->is_listing_team)
                    ->values();
            }
        
            return collect($approvers)->unique('id')->values();
        }
    /**
     * Send notification to approvers
     */
    protected function notifyApprovers(Listing $listing, $requester)
    {
        $approvers = $this->getApproversForHotDeal($listing);
        
        if (empty($approvers)) {
            \Log::warning('No approvers found for hot deal request', [
                'listing_id' => $listing->id,
                'agent_id' => $listing->agent_id
            ]);
            return false;
        }
        
        foreach ($approvers as $approver) {
            try {
                $approver->notify(new HotDealRequestNotification($listing, $requester));
                \Log::info('Hot deal request notification sent', [
                    'listing_id' => $listing->id,
                    'approver_id' => $approver->id,
                    'approver_role' => $approver->roles->pluck('name')->first()
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to send hot deal notification', [
                    'approver_id' => $approver->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return true;
    }
    
    /**
     * Check if user can approve hot deals
     */
    public function canApproveHotDeal(Listing $listing)
    {
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }
        
        // Super admin and admin can always approve
        if ($user->hasRole('super_admin') || $user->hasRole('admin')) {
            return true;
        }
        
        // Check if user is manager or team lead in the hierarchy
        $approvers = $this->getApproversForHotDeal($listing);
        
        foreach ($approvers as $approver) {
            if ($approver->id === $user->id) {
                return true;
            }
        }
        
        return false;
    }
}