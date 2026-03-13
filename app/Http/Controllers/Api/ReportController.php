<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use App\Models\Lead;
use App\Models\Stage;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * الحصول على تقرير شامل لجميع المستخدمين
     */
    public function userReport(Request $request)
    {
        try {
            $user = auth()->user();
            
            // التحقق من الصلاحيات (admin فقط)
            if (!$user->hasRole(['admin', 'super_admin'])) {
                return ApiResponse::error('Unauthorized - Only admins can access reports', 403);
            }

            // فلترة حسب التاريخ
            $dateFilter = $this->buildDateFilter($request);
            
            // الحصول على جميع المستخدمين مع إحصائياتهم
            $users = User::with(['roles'])
                ->withCount([
                    'assignedLeads' => function ($query) use ($dateFilter) {
                        // $query->whereNull('deleted_at');
                        if ($dateFilter) {
                            $query->whereDate('created_at', '>=', $dateFilter['from'])
                                  ->whereDate('created_at', '<=', $dateFilter['to']);
                        }
                    },
                    'createdLeads' => function ($query) use ($dateFilter) {
                        // $query->whereNull('deleted_at');
                        if ($dateFilter) {
                            $query->whereDate('created_at', '>=', $dateFilter['from'])
                                  ->whereDate('created_at', '<=', $dateFilter['to']);
                        }
                    }
                ])
                       ->having('assigned_leads_count', '>', 0) 
            ->orHaving('created_leads_count', '>', 0) 
                ->get();

            // إحصائيات إضافية لكل مستخدم
            $reportData = $users->map(function ($user) use ($request, $dateFilter) {
                return [
                    'user' => new UserResource($user),
                    'statistics' => $this->getUserStatistics($user->id, $request, $dateFilter),
                ];
            });

            // إحصائيات عامة
            $summary = $this->getSummaryStatistics($request, $dateFilter);

            return ApiResponse::success([
                'users_report' => $reportData,
                'summary' => $summary,
                'filters' => [
                    'month' => $request->month,
                    'year' => $request->year,
                    'date_from' => $request->date_from,
                    'date_to' => $request->date_to,
                ]
            ], 'User report retrieved successfully');

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to generate report: ' . $e->getMessage());
        }
    }

    /**
     * تقرير مفصل لمستخدم معين
     */
    public function singleUserReport(Request $request, $userId)
    {
        try {
            $user = auth()->user();
            
            // التحقق من الصلاحيات
            if (!$user->hasRole(['admin', 'super_admin']) && $user->id != $userId) {
                return ApiResponse::error('Unauthorized', 403);
            }

            $targetUser = User::findOrFail($userId);
            
            // فلترة حسب التاريخ
            $dateFilter = $this->buildDateFilter($request);

            $statistics = $this->getUserStatistics($userId, $request, $dateFilter);

            return ApiResponse::success([
                'user' => new UserResource($targetUser),
                'statistics' => $statistics,
                'filters' => [
                    'month' => $request->month,
                    'year' => $request->year,
                    'date_from' => $request->date_from,
                    'date_to' => $request->date_to,
                ]
            ], 'User report retrieved successfully');

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to generate report: ' . $e->getMessage());
        }
    }

    /**
     * الحصول على إحصائيات مستخدم معين
     */
    private function getUserStatistics($userId, Request $request, $dateFilter = null)
    {
        // عدد الليدات المسندة للمستخدم
        $assignedLeadsQuery = Lead::where('responsible_person_id', $userId);
            // ->whereNull('deleted_at');

        // عدد الليدات المضافة بواسطة المستخدم
        $createdLeadsQuery = Lead::where('added_by', $userId);
            // ->whereNull('deleted_at');

        // تطبيق فلترة التاريخ
        if ($dateFilter) {
            $assignedLeadsQuery->whereDate('created_at', '>=', $dateFilter['from'])
                              ->whereDate('created_at', '<=', $dateFilter['to']);
            $createdLeadsQuery->whereDate('created_at', '>=', $dateFilter['from'])
                             ->whereDate('created_at', '<=', $dateFilter['to']);
        }

        // تطبيق فلترة الشهر والسنة إذا وجدت
        if ($request->filled('month') && $request->filled('year')) {
            $assignedLeadsQuery->whereMonth('created_at', $request->month)
                              ->whereYear('created_at', $request->year);
            $createdLeadsQuery->whereMonth('created_at', $request->month)
                             ->whereYear('created_at', $request->year);
        }

        // الحصول على جميع المراحل
        $stages = Stage::where('stage_type','lead')->orderBy('order')->get();

        // توزيع الليدات حسب المرحلة (لليدات المسندة)
        $leadsByStage = [];
        foreach ($stages as $stage) {
            $count = Lead::where('responsible_person_id', $userId)
                ->where('stage_id', $stage->id);
                // ->whereNull('deleted_at');

            if ($dateFilter) {
                $count->whereDate('created_at', '>=', $dateFilter['from'])
                      ->whereDate('created_at', '<=', $dateFilter['to']);
            }

            if ($request->filled('month') && $request->filled('year')) {
                $count->whereMonth('created_at', $request->month)
                      ->whereYear('created_at', $request->year);
            }

            $leadsByStage[] = [
                'stage_id' => $stage->id,
                'stage_name' => $stage->name,
                'stage_color' => $stage->color,
                'count' => $count->count(),
            ];
        }

        // إحصائيات إضافية
        $totalAssigned = $assignedLeadsQuery->count();
        $totalCreated = $createdLeadsQuery->count();

        // الليدات المحولة (closed)
        $closed = Stage::where('stage_type', 'lead')
                ->where('name', 'like', '%Converted%')
                ->orderBy('order', 'desc')
                ->first();
        $convertedLeads = Lead::where('responsible_person_id', $userId)
            ->where('stage_id', $closed->id);
            // ->whereNull('deleted_at');

        if ($dateFilter) {
            $convertedLeads->whereDate('created_at', '>=', $dateFilter['from'])
                          ->whereDate('created_at', '<=', $dateFilter['to']);
        }

        // الليدات النشطة (غير محولة)
        $activeLeads = $totalAssigned - $convertedLeads->count();

        // آخر 10 ليدات
        $recentLeads = Lead::where('responsible_person_id', $userId)
            ->with(['stage', 'addedBy'])
            // ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return [
            'total_assigned_leads' => $totalAssigned,
            'total_created_leads' => $totalCreated,
            'active_leads' => $activeLeads,
            'converted_leads' => $convertedLeads->count(),
            'leads_by_stage' => $leadsByStage,
            'recent_leads' => $recentLeads,
        ];
    }

    /**
     * إحصائيات عامة
     */
    private function getSummaryStatistics(Request $request, $dateFilter = null)
    {
        $totalLeadsQuery = Lead::query();
            // whereNull('deleted_at');
        $totalAssignedQuery = Lead::whereNotNull('responsible_person_id');
        // ->whereNull('deleted_at');
             $closed = Stage::where('stage_type', 'lead')
                ->where('name', 'like', '%Converted%')
                ->orderBy('order', 'desc')
                ->first();
        $totalConvertedQuery = Lead::where('stage_id', $closed->id);
        // ->whereNull('deleted_at');

        if ($dateFilter) {
            $totalLeadsQuery->whereDate('created_at', '>=', $dateFilter['from'])
                           ->whereDate('created_at', '<=', $dateFilter['to']);
            $totalAssignedQuery->whereDate('created_at', '>=', $dateFilter['from'])
                              ->whereDate('created_at', '<=', $dateFilter['to']);
            $totalConvertedQuery->whereDate('created_at', '>=', $dateFilter['from'])
                               ->whereDate('created_at', '<=', $dateFilter['to']);
        }

        // توزيع الليدات حسب المرحلة (عام)
        $stages = Stage::where('stage_type','lead')->orderBy('order')->get();
        $leadsByStageOverall = [];
        foreach ($stages as $stage) {
            $count = Lead::where('stage_id', $stage->id);
            // ->whereNull('deleted_at');
            if ($dateFilter) {
                $count->whereDate('created_at', '>=', $dateFilter['from'])
                      ->whereDate('created_at', '<=', $dateFilter['to']);
            }
            $leadsByStageOverall[] = [
                'stage_name' => $stage->name,
                'count' => $count->count(),
                'stage_color'=>$stage->color
            ];
        }

        return [
            'total_leads' => $totalLeadsQuery->count(),
            'total_assigned_leads' => $totalAssignedQuery->count(),
            'total_converted_leads' => $totalConvertedQuery->count(),
            'conversion_rate' => $totalLeadsQuery->count() > 0 
                ? round(($totalConvertedQuery->count() / $totalLeadsQuery->count()) * 100, 2) 
                : 0,
            'leads_by_stage_overall' => $leadsByStageOverall,
        ];
    }

    /**
     * بناء فلتر التاريخ
     */
    private function buildDateFilter(Request $request)
    {
        if ($request->filled('date_from') && $request->filled('date_to')) {
            return [
                'from' => Carbon::parse($request->date_from)->startOfDay(),
                'to' => Carbon::parse($request->date_to)->endOfDay(),
            ];
        }

        if ($request->filled('month') && $request->filled('year')) {
            return [
                'from' => Carbon::createFromDate($request->year, $request->month, 1)->startOfMonth(),
                'to' => Carbon::createFromDate($request->year, $request->month, 1)->endOfMonth(),
            ];
        }

        if ($request->filled('year') && !$request->filled('month')) {
            return [
                'from' => Carbon::createFromDate($request->year, 1, 1)->startOfYear(),
                'to' => Carbon::createFromDate($request->year, 12, 31)->endOfYear(),
            ];
        }

        return null;
    }

    /**
     * الحصول على قائمة الأشهر لاستخدامها في الفلتر
     */
    public function getMonthOptions()
    {
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[] = [
                'value' => $i,
                'label' => Carbon::create()->month($i)->format('F')
            ];
        }
        return $months;
    }

    /**
     * الحصول على قائمة السنوات المتوفرة
     */
    public function getYearOptions()
    {
        $years = Lead::selectRaw('YEAR(created_at) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        return array_map(function ($year) {
            return ['value' => $year, 'label' => $year];
        }, $years);
    }
}