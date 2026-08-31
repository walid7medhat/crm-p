<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementView;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Notifications\AnnouncementNotification;
class AnnouncementController extends Controller
{
   
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            
            if ($user->hasRole('super_admin') || $user->hasRole('hr')) {
                $query = Announcement::with(['branch', 'department', 'creator']);
                
                if ($request->has('branch_id')) {
                    $query->where('branch_id', $request->branch_id);
                }
                
                if ($request->has('department_id')) {
                    $query->where('department_id', $request->department_id);
                }
                
                if ($request->has('search')) {
                    $query->where('title', 'like', '%' . $request->search . '%');
                }
                
                if ($request->has('status')) {
                    $today = now()->toDateString();
                    if ($request->status === 'active') {
                        $query->whereDate('start_date', '<=', $today)
                              ->where(function($q) use ($today) {
                                  $q->whereNull('end_date')
                                    ->orWhereDate('end_date', '>=', $today);
                              });
                    } elseif ($request->status === 'expired') {
                        $query->whereNotNull('end_date')
                              ->whereDate('end_date', '<', $today);
                    } elseif ($request->status === 'upcoming') {
                        $query->whereDate('start_date', '>', $today);
                    }
                }
                
                $announcements = $query->orderBy('created_at', 'desc')
                    ->paginate($request->per_page ?? 15);
                    
                return ApiResponse::success($announcements, 'Announcements retrieved successfully');
            } 
            else {
                $announcements = Announcement::forUser($user->id)
                    ->with(['branch', 'department', 'creator'])
                    ->orderBy('created_at', 'desc')
                    ->paginate($request->per_page ?? 15);
                
                foreach ($announcements as $announcement) {
                    $announcement->is_viewed = $announcement->isViewedByUser($user->id);
                }
                
                return ApiResponse::success($announcements, 'Announcements retrieved successfully');
            }
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve announcements: ' . $e->getMessage());
        }
    }
    
   
    public function show($id)
    {
        try {
            $user = Auth::user();
            $announcement = Announcement::with(['branch', 'department', 'creator'])
                ->findOrFail($id);
            
            if (!$user->hasRole('super_admin') && !$user->hasRole('hr')) {
                if (!$announcement->isTargetedUser($user->id)) {
                    return ApiResponse::error('Access denied', 403);
                }
            }
            
            $announcement->markAsViewed($user->id);
            $announcement->is_viewed = true;
            
            return ApiResponse::success($announcement, 'Announcement retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Announcement not found', 404);
        }
    }
    
   
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'time' => 'nullable|date_format:H:i',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'branch_id' => 'required|exists:company_branches,id',
            'department_id' => 'required|exists:departments,id',
            'send_now'=>'boolean',
        ]);

        try {
            DB::beginTransaction();

            $announcement = Announcement::create([
                'title' => $request->title,
                'description' => $request->description,
                'start_date' => $request->start_date,
                'time' => $request->time,
                'end_date' => $request->end_date,
                'branch_id' => $request->branch_id,
                'department_id' => $request->department_id,
                'created_by' => Auth::id(),
            ]);
              if ($request->send_now || $request->start_date <= now()->toDateString()) {
                    $users = $announcement->getTargetUsers();
                    foreach ($users as $user) {
                        $user->notify(new AnnouncementNotification($announcement));
                    }
                }
                    
            DB::commit();
            
            return ApiResponse::success(
                $announcement->load(['branch', 'department', 'creator']),
                'Announcement created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to create announcement: ' . $e->getMessage());
        }
    }
    
    
    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'sometimes|date',
            'time' => 'nullable|date_format:H:i',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'branch_id' => 'sometimes|required|exists:company_branches,id',
            'department_id' => 'sometimes|required|exists:departments,id',
        ]);
        
        try {
            $announcement = Announcement::findOrFail($id);
            $announcement->update($request->all());
            
            return ApiResponse::success(
                $announcement->load(['branch', 'department', 'creator']),
                'Announcement updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update announcement: ' . $e->getMessage());
        }
    }
    
    
    public function destroy($id)
    {
        try {
            $announcement = Announcement::findOrFail($id);
            $announcement->delete();
            
            return ApiResponse::success(null, 'Announcement deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete announcement: ' . $e->getMessage());
        }
    }
    
   
    public function getActiveAnnouncements()
    {
        try {
            $user = Auth::user();
            $today = now()->toDateString();
            
            $announcements = Announcement::forUser($user->id)
                ->whereDate('start_date', '<=', $today)
                ->where(function($q) use ($today) {
                    $q->whereNull('end_date')
                      ->orWhereDate('end_date', '>=', $today);
                })
                ->with(['branch', 'department'])
                ->orderBy('created_at', 'desc')
                ->get();
            
            foreach ($announcements as $announcement) {
                $announcement->is_viewed = $announcement->isViewedByUser($user->id);
            }
            
            return ApiResponse::success($announcements, 'Active announcements retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve announcements: ' . $e->getMessage());
        }
    }
    
    
    public function getUnreadAnnouncements()
    {
        try {
            $user = Auth::user();
            $today = now()->toDateString();
            
            $announcements = Announcement::forUser($user->id)
                ->whereDate('start_date', '<=', $today)
                ->where(function($q) use ($today) {
                    $q->whereNull('end_date')
                      ->orWhereDate('end_date', '>=', $today);
                })
                ->due()
                ->whereDoesntHave('views', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->orderBy('created_at', 'desc')
                ->get();

            return ApiResponse::success($announcements, 'Unread announcements retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve unread announcements: ' . $e->getMessage());
        }
    }
    
   
    public function markAllAsRead()
    {
        try {
            $user = Auth::user();
            $today = now()->toDateString();
            
            $announcements = Announcement::forUser($user->id)
                ->whereDate('start_date', '<=', $today)
                ->where(function($q) use ($today) {
                    $q->whereNull('end_date')
                      ->orWhereDate('end_date', '>=', $today);
                })
                ->whereDoesntHave('views', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->get();
            
            foreach ($announcements as $announcement) {
                $announcement->markAsViewed($user->id);
            }
            
            return ApiResponse::success(null, 'All announcements marked as read');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to mark announcements as read: ' . $e->getMessage());
        }
    }
    
  
    public function statistics()
    {
        try {
            $today = now()->toDateString();
            
            $stats = [
                'total' => Announcement::count(),
                'active' => Announcement::whereDate('start_date', '<=', $today)
                    ->where(function($q) use ($today) {
                        $q->whereNull('end_date')
                          ->orWhereDate('end_date', '>=', $today);
                    })->count(),
                'expired' => Announcement::whereNotNull('end_date')
                    ->whereDate('end_date', '<', $today)->count(),
                'upcoming' => Announcement::whereDate('start_date', '>', $today)->count(),
                'by_branch' => Announcement::select('company_branches.name', DB::raw('count(announcements.id) as count'))
                    ->leftJoin('company_branches', 'announcements.branch_id', '=', 'company_branches.id')
                    ->groupBy('company_branches.name')
                    ->get(),
                'by_department' => Announcement::select('departments.name', DB::raw('count(announcements.id) as count'))
                    ->leftJoin('departments', 'announcements.department_id', '=', 'departments.id')
                    ->groupBy('departments.name')
                    ->get(),
                'total_views' => AnnouncementView::count(),
            ];
            
            return ApiResponse::success($stats, 'Statistics retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve statistics: ' . $e->getMessage());
        }
    }
}