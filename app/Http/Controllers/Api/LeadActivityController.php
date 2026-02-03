<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lead\StoreActivityRequest;
use App\Http\Requests\Lead\UpdateActivityRequest;
use App\Http\Requests\Lead\StoreCommentRequest;
use App\Http\Requests\Lead\UpdateCommentRequest;
use App\Http\Resources\Lead\ActivityResource;
use App\Http\Resources\Lead\CommentResource;
use App\Http\Resources\Lead\CommentAttachmentResource;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadComment;
use App\Models\User;
use App\Models\CommentAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Notifications\CommentMentionNotification;
use Notification;
use Carbon\Carbon;
use App\Http\Resources\User\MentionAgentResource;
use App\Helpers\ApiResponse;

class LeadActivityController extends Controller
{
    // ==================== ACTIVITY METHODS ====================
    
    /**
     * Get all activities for a specific lead
     */
    public function getLeadActivities($leadId)
    {
        $lead = Lead::findOrFail($leadId);
        
        $activities = $lead->activities()
            ->with('user')
            ->orderBy('reminder_date', 'desc')
            ->paginate(20);
            
        return ActivityResource::collection($activities);
    }
    
    /**
     * Create new activity
     */
    public function storeActivity(StoreActivityRequest $request)
    {
        try {
             $activity = LeadActivity::create([
                'lead_id' => $request->lead_id,
                'user_id' => auth()->id(),
                'title' => $request->title,
                'reminder_date' => $request->reminder_date,
                'reminders' => $request->reminders, // [15, 60, 1440]
                'is_completed' => false,
            ]);
    
            $activity->calculateNextReminder();
            $activity->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Activity created successfully',
                'data' => new ActivityResource($activity->load('user'))
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create activity',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update activity
     */
    public function updateActivity(UpdateActivityRequest $request, $id)
    {
        $activity = LeadActivity::findOrFail($id);
        
        // Check if user owns this activity
        if ($activity->user_id != auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this activity'
            ], 403);
        }
        
        try {
           $activity->update($request->validated());
            
            $activity->calculateNextReminder();
            $activity->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Activity updated successfully',
                'data' => new ActivityResource($activity->load('user'))
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update activity',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Toggle activity completion status
     */
    public function toggleActivityCompletion($id)
    {
        $activity = LeadActivity::findOrFail($id);
        
        $activity->update([
            'is_completed' => !$activity->is_completed
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Activity status updated successfully',
            'data' => new ActivityResource($activity->load('user'))
        ]);
    }
    
    /**
     * Delete activity
     */
    public function destroyActivity($id)
    {
        $activity = LeadActivity::findOrFail($id);
        
        // Check if user owns this activity
        if ($activity->user_id != auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this activity'
            ], 403);
        }
        
        $activity->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Activity deleted successfully'
        ]);
    }
    
    /**
     * Get user's upcoming activities
     */
    public function getUpcomingActivities()
    {
        $activities = LeadActivity::with(['lead', 'user'])
            ->where('user_id', auth()->id())
            ->upcoming()
            ->orderBy('reminder_date', 'asc')
            ->paginate(20);
            
        return ActivityResource::collection($activities);
    }
    
    /**
     * Get user's overdue activities
     */
    public function getOverdueActivities()
    {
        $activities = LeadActivity::with(['lead', 'user'])
            ->where('user_id', auth()->id())
            ->overdue()
            ->orderBy('reminder_date', 'asc')
            ->paginate(20);
            
        return ActivityResource::collection($activities);
    }
    
    /**
     * Get user's completed activities
     */
    public function getCompletedActivities()
    {
        $activities = LeadActivity::with(['lead', 'user'])
            ->where('user_id', auth()->id())
            ->completed()
            ->orderBy('updated_at', 'desc')
            ->paginate(20);
            
        return ActivityResource::collection($activities);
    }
    
    // ==================== COMMENT METHODS ====================
    
    /**
     * Get all comments for a specific lead
     */
    public function getLeadComments($leadId)
    {
        $lead = Lead::findOrFail($leadId);
        
        $comments = $lead->comments()
            ->with(['user', 'attachments', 'mentionedUsers'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            
        return CommentResource::collection($comments);
    }
    
    /**
     * Create new comment
     */
    public function storeComment(StoreCommentRequest $request)
    {
        DB::beginTransaction();
        
        try {
            // Create comment
            $comment = LeadComment::create([
                'lead_id' => $request->lead_id,
                'user_id' => auth()->id(),
                'comment' => $request->comment,
            ]);
            
            // Handle attachments if present
            if ($request->hasFile('attachments')) {
                $attachmentsData = [];
                
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('comments/attachments', 'public');
                    
                    $attachmentsData[] = [
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'file_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ];
                }
                
                if (!empty($attachmentsData)) {
                    $comment->addAttachments($attachmentsData);
                }
            }
            
            // Handle mentions if present
            if ($request->has('mentioned_users') && is_array($request->mentioned_users)) {
                $comment->addMentions($request->mentioned_users);
                
                // TODO: Send notifications to mentioned users
                Notification::send($comment->mentionedUsers, new CommentMentionNotification($comment));
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Comment added successfully',
                'data' => new CommentResource($comment->load(['user', 'attachments', 'mentionedUsers']))
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            dd($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add comment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update comment
     */
    public function updateComment(UpdateCommentRequest $request, $id)
    {
        $comment = LeadComment::findOrFail($id);
        
        // Check if user owns this comment
        if ($comment->user_id != auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this comment'
            ], 403);
        }
        
        DB::beginTransaction();
        
        try {
            $comment->update([
                'comment' => $request->comment,
            ]);
            
            // Update mentions
            if ($request->has('mentioned_users')) {
                // Remove existing mentions
                $comment->mentions()->delete();
                
                // Add new mentions
                if (is_array($request->mentioned_users) && !empty($request->mentioned_users)) {
                    $comment->addMentions($request->mentioned_users);
                }
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Comment updated successfully',
                'data' => new CommentResource($comment->load(['user', 'attachments', 'mentionedUsers']))
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update comment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete comment
     */
    public function destroyComment($id)
    {
        $comment = LeadComment::findOrFail($id);
        
        // Check if user owns this comment
        if ($comment->user_id != auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this comment'
            ], 403);
        }
        
        DB::beginTransaction();
        
        try {
            // Delete attachments from storage
            foreach ($comment->attachments as $attachment) {
                Storage::disk('public')->delete($attachment->file_path);
            }
            
            $comment->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete comment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Upload additional attachments to comment
     */
    public function addCommentAttachments(Request $request, $commentId)
    {
        $request->validate([
            'attachments' => 'required|array|max:5',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt',
        ]);
        
        $comment = LeadComment::findOrFail($commentId);
        
        // Check if user owns this comment
        if ($comment->user_id != auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to add attachments to this comment'
            ], 403);
        }
        
        try {
            $attachmentsData = [];
            
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('comments/attachments', 'public');
                
                $attachmentsData[] = [
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ];
            }
            
            if (!empty($attachmentsData)) {
                $comment->addAttachments($attachmentsData);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Attachments uploaded successfully',
                'data' => CommentAttachmentResource::collection($comment->attachments()->latest()->get())
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload attachments',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete comment attachment
     */
    public function destroyCommentAttachment($commentId, $attachmentId)
    {
        $comment = LeadComment::findOrFail($commentId);
        $attachment = CommentAttachment::findOrFail($attachmentId);
        
        // Verify attachment belongs to comment
        if ($attachment->comment_id != $comment->id) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found for this comment'
            ], 404);
        }
        
        // Check if user owns this comment
        if ($comment->user_id != auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this attachment'
            ], 403);
        }
        
        try {
            // Delete file from storage
            Storage::disk('public')->delete($attachment->file_path);
            
            $attachment->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Attachment deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attachment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function get_mentions(Request $request){
        $query = User::query();
          if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }
                        $users = $query->orderBy('created_at','desc')->where('id','!=',auth()->user()->id)->get();

         return ApiResponse::success(
                MentionAgentResource::collection($users),
                'Users retrieved successfully'
            );
    }
}