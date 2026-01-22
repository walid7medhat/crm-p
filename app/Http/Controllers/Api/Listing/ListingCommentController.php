<?php
// app/Http/Controllers/Api/ListingCommentController.php

namespace App\Http\Controllers\Api\Listing;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ListingCommentController extends Controller
{
    public function index($listingId)
    {
        $comments = ListingComment::with(['user', 'replies.user'])
            ->where('listing_id', $listingId)
            ->approved()
            ->mainComments()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $comments
        ]);
    }

    public function store(Request $request, $listingId)
    {
        $request->validate([
            'comment' => 'required|string|min:3|max:1000',
            'rating' => 'nullable|integer|min:1|max:5',
            'parent_id' => 'nullable|exists:listing_comments,id'
        ]);

        $listing = Listing::findOrFail($listingId);

        $comment = ListingComment::create([
            'listing_id' => $listingId,
            'user_id' => Auth::id(),
            'comment' => $request->comment,
            'rating' => $request->rating,
            'parent_id' => $request->parent_id,
            'is_approved' => true
        ]);

        $comment->load('user');

        return response()->json([
            'status' => true,
            'message' => 'Comment added successfully',
            'data' => $comment
        ], 201);
    }

    public function update(Request $request, $commentId)
    {
        $comment = ListingComment::where('user_id', Auth::id())->findOrFail($commentId);

        $request->validate([
            'comment' => 'required|string|min:3|max:1000',
            'rating' => 'nullable|integer|min:1|max:5'
        ]);

        $comment->update([
            'comment' => $request->comment,
            'rating' => $request->rating
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Comment updated successfully',
            'data' => $comment
        ]);
    }

    public function destroy($commentId)
    {
        $comment = ListingComment::where('user_id', Auth::id())->findOrFail($commentId);
        $comment->delete();

        return response()->json([
            'status' => true,
            'message' => 'Comment deleted successfully'
        ]);
    }

    public function getStats($listingId)
    {
        $stats = ListingComment::where('listing_id', $listingId)
            ->approved()
            ->selectRaw('COUNT(*) as total_comments, AVG(rating) as average_rating')
            ->first();

        $ratingDistribution = ListingComment::where('listing_id', $listingId)
            ->approved()
            ->whereNotNull('rating')
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->orderBy('rating', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => [
                'total_comments' => $stats->total_comments ?? 0,
                'average_rating' => round($stats->average_rating ?? 0, 1),
                'rating_distribution' => $ratingDistribution
            ]
        ]);
    }
}