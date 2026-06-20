<?php

namespace App\Http\Controllers\Api\Listing;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\InternalUpdate;
use Illuminate\Http\Request;

class InternalUpdateController extends Controller
{
    public function index(Listing $listing)
    {
        $user = auth()->user();
        
        if (!$this->canViewUpdates($user, $listing)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        return response()->json([
            'status' => true,
            'data' => $listing->internalUpdates()->with('user')->latest()->get()
        ]);
    }

    public function store(Request $request, Listing $listing)
    {
        $user = auth()->user();
        
        if (!$this->canAddUpdate($user, $listing)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $request->validate([
            'content' => 'required|string|max:5000'
        ]);
        
        $update = $listing->internalUpdates()->create([
            'user_id' => $user->id,
            'content' => $request->content,
        ]);
        
        return response()->json([
            'status' => true,
            'message' => 'Update added successfully',
            'data' => $update->load('user')
        ]);
    }

    public function destroy(Listing $listing, InternalUpdate $update)
    {
        $user = auth()->user();
        
        // فقط صاحب التحديث أو admin يمكنه الحذف
        if ($update->user_id != $user->id && !$user->hasRole('super_admin') && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $update->delete();
        
        return response()->json([
            'status' => true,
            'message' => 'Update deleted successfully'
        ]);
    }

    private function canViewUpdates($user, $listing)
    {
        if (!$user) return false;
        
        return $user->hasRole('super_admin') ||
               $user->hasRole('admin') ||
               ($user->hasRole('manager') && $user->listing_team == 1) ||
               $listing->agent_id == $user->id ||
               $user->id == 30;
    }

    private function canAddUpdate($user, $listing)
    {
        if (!$user) return false;
        
        return $listing->agent_id == $user->id ||
               $user->hasRole('super_admin') ||
               $user->hasRole('admin');
    }
}