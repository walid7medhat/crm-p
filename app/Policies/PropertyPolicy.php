<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\ListingAccessRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PropertyPolicy
{
    /**
     * Determine if the user can update the property.
     */
    public function update(User $user, Listing $property): bool
    {
        return $user->id === $property->added_by  || 
               ($property->agent_id && $user->id === $property->agent_id) ; 
    }

    /**
     * Determine if the user can delete the property.
     */
    public function delete(User $user, Listing $property): bool
    {
        return $user->id === $property->added_by  || 
               ($property->agent_id && $user->id === $property->agent_id) ;
    }

    public function requestUnitNumber(User $user, Listing $property): bool
{
    if (!auth()->check()) {
        return false;
    }

    if ($user->id === $property->added_by_id || 
        ($property->agent_id && $user->id === $property->agent_id)) {
        return false;
    }

    return !ListingAccessRequest::where('user_id', $user->id)
        ->where('listing_id', $property->id)
        ->where('request_type', 'unit_number')
        ->whereIn('status', ['pending', 'approved'])
        ->exists();
}

public function requestOwnerInfo(User $user, Listing $property): bool
{
    if (!auth()->check()) {
        return false;
    }

    if ($user->id === $property->added_by_id || 
        ($property->agent_id && $user->id === $property->agent_id)) {
        return false;
    }

    return !ListingAccessRequest::where('user_id', $user->id)
            ->where('listing_id', $property->id)
            ->where('request_type', 'owner_data')
            ->whereIn('status', ['pending', 'approved'])
            ->exists();
    }
}