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
use App\Exports\LeadsBySourceReportExport;
use Maatwebsite\Excel\Facades\Excel;

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
     * Stage IDs currently covered by the leads-by-source report.
     */
    private const LEADS_BY_SOURCE_STAGE_IDS = [6, 8];

    /**
     * تقرير مصادر الليدات لمراحل محددة: عدد الليدات لكل lead_source.
     */
    public function leadsBySourceReport(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$user->hasRole(['admin', 'super_admin'])) {
                return ApiResponse::error('Unauthorized - Only admins can access reports', 403);
            }

            $report = $this->buildLeadsBySourceReport($request);

            return ApiResponse::success([
                'report' => $report,
                'filters' => [
                    'month' => $request->month,
                    'year' => $request->year,
                    'date_from' => $request->date_from,
                    'date_to' => $request->date_to,
                ],
            ], 'Leads by source report retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to generate report: ' . $e->getMessage());
        }
    }

    /**
     * Lead Pool self-assignments in a date range (by when the lead was taken —
     * lead_pool_assignments.assigned_at): per user, how many leads they took from the
     * Lead Pool and which stage each of those leads is in NOW, plus the leads themselves.
     * super_admin only (for now).
     */
    public function leadPoolAssignmentsReport(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user->hasRole('super_admin')) {
                return ApiResponse::error('Unauthorized - Only super admins can access this report', 403);
            }

            $from = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : null;
            $to = $request->filled('date_to') ? Carbon::parse($request->date_to)->endOfDay() : null;

            $rows = DB::table('lead_pool_assignments as a')
                ->join('users as u', 'u.id', '=', 'a.user_id')
                // Deleted (archived) leads still count — they were taken; shown as "Deleted".
                ->leftJoin('leads as l', 'l.id', '=', 'a.lead_id')
                ->leftJoin('stages as s', 's.id', '=', 'l.stage_id')
                ->when($from, fn ($q) => $q->where('a.assigned_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('a.assigned_at', '<=', $to))
                ->orderBy('a.assigned_at', 'desc')
                ->get([
                    'a.user_id', 'a.lead_id', 'a.assigned_at',
                    'u.name as user_name', 'u.display_name as user_display_name', 'u.avatar as user_avatar', 'u.status as user_status',
                    'l.lead_name', 'l.deleted_at as lead_deleted_at', 'l.responsible_person_id',
                    's.id as stage_id', 's.name as stage_name', 's.color as stage_color', 's.order as stage_order',
                ]);

            $stageOf = function ($row) {
                if (! $row->lead_name && ! $row->stage_id) {
                    return ['id' => 'deleted', 'name' => 'Deleted', 'color' => '#94a3b8', 'order' => 999];
                }
                if ($row->lead_deleted_at) {
                    return ['id' => 'deleted', 'name' => 'Deleted', 'color' => '#94a3b8', 'order' => 999];
                }

                return [
                    'id' => $row->stage_id ?? 'none',
                    'name' => $row->stage_name ?? 'No stage',
                    'color' => $row->stage_color ?: '#cbd5e1',
                    'order' => (int) ($row->stage_order ?? 998),
                ];
            };

            $stages = [];
            $users = [];
            foreach ($rows as $row) {
                $stage = $stageOf($row);
                $stages[$stage['id']] = $stage;

                $uid = (int) $row->user_id;
                $users[$uid] ??= [
                    'user_id' => $uid,
                    'name' => $row->user_display_name ?: $row->user_name,
                    'avatar' => $row->user_avatar ? asset('storage/' . $row->user_avatar) : null,
                    'active' => $row->user_status === 'active',
                    'total' => 0,
                    'stages' => [],
                    'leads' => [],
                ];
                $users[$uid]['total']++;
                $users[$uid]['stages'][$stage['id']] = ($users[$uid]['stages'][$stage['id']] ?? 0) + 1;
                $users[$uid]['leads'][] = [
                    'id' => (int) $row->lead_id,
                    'lead_name' => $row->lead_name,
                    'assigned_at' => Carbon::parse($row->assigned_at)->toISOString(),
                    'stage_id' => $stage['id'],
                    'stage_name' => $stage['name'],
                    'stage_color' => $stage['color'],
                    // Lead may have moved on to someone else since it was taken.
                    'still_with_user' => (int) $row->responsible_person_id === $uid,
                ];
            }

            $stages = collect($stages)->sortBy('order')->values()->all();
            $users = collect($users)->sortByDesc('total')->values()->all();

            $totals = ['total' => count($rows), 'stages' => []];
            foreach ($users as $u) {
                foreach ($u['stages'] as $stageId => $count) {
                    $totals['stages'][$stageId] = ($totals['stages'][$stageId] ?? 0) + $count;
                }
            }

            return ApiResponse::success([
                'stages' => $stages,
                'users' => $users,
                'totals' => $totals,
                'filters' => ['date_from' => $request->date_from, 'date_to' => $request->date_to],
            ], 'Lead pool assignments report retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to generate report: ' . $e->getMessage());
        }
    }

    /**
     * نفس تقرير المصادر لكن كملف اكسل قابل للتحميل.
     */
    public function leadsBySourceReportExport(Request $request)
    {
        $user = auth()->user();

        if (!$user->hasRole(['admin', 'super_admin'])) {
            return ApiResponse::error('Unauthorized - Only admins can access reports', 403);
        }

        $report = $this->buildLeadsBySourceReport($request);

        return Excel::download(
            new LeadsBySourceReportExport($report),
            'leads-by-source-report.xlsx'
        );
    }

    /**
     * يبني بيانات تقرير المصادر لمراحل self::LEADS_BY_SOURCE_STAGE_IDS.
     */
    private function buildLeadsBySourceReport(Request $request): array
    {
        $dateFilter = $this->buildDateFilter($request);
        $stages = Stage::whereIn('id', self::LEADS_BY_SOURCE_STAGE_IDS)->orderBy('order')->get();

        $report = [];

        foreach ($stages as $stage) {
            $sourcesQuery = Lead::where('stage_id', $stage->id);

            if ($dateFilter) {
                $sourcesQuery->whereDate('created_at', '>=', $dateFilter['from'])
                             ->whereDate('created_at', '<=', $dateFilter['to']);
            }

            $sources = $sourcesQuery
                ->selectRaw("COALESCE(NULLIF(lead_source, ''), 'Unknown') as source, COUNT(*) as count")
                ->groupBy('source')
                ->orderByDesc('count')
                ->get();

            $report[] = [
                'stage_id' => $stage->id,
                'stage_name' => $stage->name,
                'stage_color' => $stage->color,
                'total' => $sources->sum('count'),
                'sources' => $sources->map(fn ($row) => [
                    'source' => $row->source,
                    'count' => (int) $row->count,
                ])->values(),
            ];
        }

        return $report;
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