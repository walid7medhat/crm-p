<?php

namespace App\Http\Controllers\Api\Deal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Deal\UpdateDealRequest;
use App\Http\Resources\Deal\DealResource;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Stage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DealController extends Controller
{
    /**
     * 1. جلب كل الصفقات مع فلترة متقدمة
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = Deal::with([
            'lead',
            'stage',
            'propertyType',
            'project',
            'area',
            'developer',
            'responsiblePerson',
            'parties'
        ]);

        // --- تطبيق فلترة حسب صلاحيات المستخدم ---
        if ($user->hasAnyRole(['manager', 'team_lead', 'admin'])) {
            $subordinatesIds = $user->getAllSubordinatesIds();
            $query->where(function($q) use ($subordinatesIds, $user) {
                $q->whereIn('responsible_person_id', array_merge($subordinatesIds, [$user->id]));
            });
        } elseif (!$user->hasAnyRole(['super_admin'])) {
            $query->where(function($q) use ($user) {
                $q->where('responsible_person_id', $user->id);
            });
        }

        // --- تطبيق الفلاتر من الـrequest ---
        if ($request->filled('deal_type')) $query->where('deal_type', $request->deal_type);
        if ($request->filled('stage_id')) $query->where('stage_id', $request->stage_id);
        if ($request->filled('stage_ids')) {
            $stageIds = explode(',', $request->stage_ids);
            $query->whereIn('stage_id', $stageIds);
        }
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('responsible_id')) $query->where('responsible_person_id', $request->responsible_id);
        if ($request->filled('project_id')) $query->where('project_id', $request->project_id);
        if ($request->filled('area_id')) $query->where('area_id', $request->area_id);
        if ($request->filled('developer_id')) $query->where('developer_id', $request->developer_id);
        if ($request->filled('from_date')) $query->whereDate('created_at', '>=', $request->from_date);
        if ($request->filled('to_date')) $query->whereDate('created_at', '<=', $request->to_date);

        // بحث نصي
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('deal_number', 'like', "%{$search}%")
                  ->orWhere('deal_name', 'like', "%{$search}%")
                  ->orWhere('unit_no', 'like', "%{$search}%")
                  ->orWhereHas('lead', function($leadQuery) use ($search) {
                      $leadQuery->where('lead_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        // ترتيب
        $orderBy = $request->get('order_by', 'created_at');
        $orderDir = $request->get('order_dir', 'desc');
        $query->orderBy($orderBy, $orderDir);

        $perPage = $request->get('per_page', 15);
        $deals = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => DealResource::collection($deals),
            'meta' => [
                'total' => $deals->total(),
                'per_page' => $deals->perPage(),
                'current_page' => $deals->currentPage(),
                'last_page' => $deals->lastPage(),
            ]
        ]);
    }

    /**
     * 2. جلب صفقة واحدة بالتفصيل
     */
    public function show(Deal $deal)
    {
        $user = auth()->user();
        
        // التحقق من الصلاحية
        if (!$user->hasAnyRole(['super_admin'])) {
            $canAccess = false;
            
            if ($user->hasAnyRole(['manager', 'team_lead', 'admin'])) {
                $subordinatesIds = $user->getAllSubordinatesIds();
                $canAccess = in_array($deal->responsible_person_id, array_merge($subordinatesIds, [$user->id]));
            } else {
                $canAccess = $deal->responsible_person_id == $user->id;
            }
            
            if (!$canAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
        }

        $deal->load([
            'lead',
            'stage',
            'propertyType',
            'project',
            'area',
            'developer',
            'responsiblePerson',
            'parties',
            'documents'
        ]);

        return response()->json([
            'success' => true,
            'data' => new DealResource($deal)
        ]);
    }

    /**
     * 3. تحديث صفقة
     */
    public function update(UpdateDealRequest $request, Deal $deal)
    {
        $user = auth()->user();
        
        // التحقق من الصلاحية
        if (!$user->hasAnyRole(['super_admin'])) {
            $canUpdate = false;
            
            if ($user->hasAnyRole(['manager', 'team_lead', 'admin'])) {
                $subordinatesIds = $user->getAllSubordinatesIds();
                $canUpdate = in_array($deal->responsible_person_id, array_merge($subordinatesIds, [$user->id]));
            } else {
                $canUpdate = $deal->responsible_person_id == $user->id;
            }
            
            if (!$canUpdate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
        }

        try {
            $deal->update(array_merge(
                $request->validated(),
                ['updated_by' => auth()->id()]
            ));

            return response()->json([
                'success' => true,
                'message' => 'Deal updated successfully',
                'data' => new DealResource($deal->fresh(['stage', 'responsiblePerson']))
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 4. جلب الصفقات مجمعة حسب المرحلة (للكانبان)
     */
    public function getDealsGroupedByStage(Request $request)
    {
        $user = auth()->user();
        
        $stages = Stage::where('stage_type', 'deal')
           ->when($request->deal_type,function($q) use($request){
               $q->where('deal_type',$request->deal_type);
           })
            ->orderBy('deal_type')
            ->orderBy('order')
            ->get();

        $result = [];

        foreach ($stages as $stage) {
            $query = Deal::where('stage_id', $stage->id)
                ->with(['lead', 'propertyType', 'project', 'area', 'developer', 'responsiblePerson']);

            // صلاحيات
            if ($user->hasAnyRole(['manager', 'team_lead', 'admin'])) {
                $subordinatesIds = $user->getAllSubordinatesIds();
                $query->whereIn('responsible_person_id', array_merge($subordinatesIds, [$user->id]));
            } elseif (!$user->hasAnyRole(['super_admin'])) {
                $query->where('responsible_person_id', $user->id);
            }

            // فلاتر
            if ($request->filled('deal_type')) {
                $query->where('deal_type', $request->deal_type);
            }

            $deals = $query->get();

            $result[] = [
                'stage_id' => $stage->id,
                'stage_name' => $stage->name,
                'stage_color' => $stage->color,
                'deal_type' => $stage->deal_type,
                'deals_count' => $deals->count(),
                'deals' => DealResource::collection($deals)
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }
}