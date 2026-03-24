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
            return $approvers;
        }
        
        // Find the team lead and manager in the hierarchy
        $current = $agent;
        
        while ($current && $current->parent_id) {
            $current = $current->parent;
            
            // Check if this user has role manager or team_lead
            if ($current && ($current->hasRole('manager') || $current->hasRole('team_lead'))) {
                $approvers[] = $current;
                
                // If we found a manager, we can stop (or continue to find all)
                if ($current->hasRole('manager')) {
                    break;
                }
            }
        }
        
        // Also check if the agent's parent is a manager/team lead
        if ($agent->parent && ($agent->parent->hasRole('manager') || $agent->parent->hasRole('team_lead'))) {
            $approvers[] = $agent->parent;
        }
        
        return array_unique($approvers, SORT_REGULAR);
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