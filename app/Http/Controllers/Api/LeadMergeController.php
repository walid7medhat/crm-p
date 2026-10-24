<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\Lead;
use App\Services\LeadMergeService;
use App\Helpers\ApiResponse;   
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Lead\LeadResource;
use App\Models\User;
use Illuminate\Support\Arr;
class LeadMergeController extends Controller
{
    public function __construct(private LeadMergeService $svc) {}
    public const RESTRICTED_KEYS = [
        'id', 'lead_name', 'salutation', 'first_name', 'last_name',
        'work_phone', 'work_phone_2', 'email', 'secondary_email',
        'area', 'area_id', 'property_type', 'property_type_id', 'bedrooms',
        'budget_from', 'budget_to', 'lead_type', 'property_status', 'purpose_buying',
        'status_lead', 'extra_client_requirements', 'whatsapp_qualification',
        'can_edit', 'is_merged', 'merged_at',
    ];
    private function guard(Lead $lead): void
    {
        $u = auth()->user();
        abort_unless($u->hasAnyRole(config('lead_merge.merge_roles')), 403, 'You are not allowed to merge leads.');
        abort_unless($u->canViewLead($lead), 403, 'You are not authorized to view this lead.');
    }

    private function mask(?string $v, Lead $default): ?string
    {
        if (! $v) return $v;
        $u = auth()->user();
        $full = $u->hasAnyRole(['super_admin', 'admin', 'branch_admin'])
            || (int) $default->responsible_person_id === (int) $u->id;
        return $full ? $v : mb_substr($v, 0, 3).str_repeat('★', max(0, mb_strlen($v) - 3));
    }

    private function slim(Lead $l): array
    {
        return [
            'id' => $l->id, 'lead_name' => $l->lead_name, 'work_phone' => $l->work_phone, 'email' => $l->email,
            'stage_id' => $l->stage_id, 'stage_name' => $l->stage?->name,
            'is_pool' => (int) $l->stage_id === (int) config('lead_merge.lead_pool_stage_id'),
            'property_type' => $l->propertyType?->name, 'area' => $l->area?->name, 'bedrooms' => $l->bedrooms,
            'responsible_name' => $l->responsiblePerson?->name,
            'created_at' => $l->created_at,
        ];
    }

    /** Step 1 list */
        public function candidates(Lead $lead)
        {
            $this->guard($lead);
            $pool = (int) config('lead_merge.lead_pool_stage_id');

            $all = $this->svc->group($lead, auth()->user());

            // Lead Pool leads are merged automatically (daily) and never picked manually.
            $leads = $all->reject(fn ($l) => (int) $l->stage_id === $pool)
                ->map(fn ($l) => $this->slim($l))
                ->values();

            return ApiResponse::success([
                'leads'      => $leads,
                'pool_count' => $all->count() - $leads->count(),
            ], 'OK');
        }

    /** Step 2 preview (read-only) */
    public function preview(Request $r)
    {
        $data = $r->validate(['default_lead_id' => 'required|integer']);
        $default = Lead::findOrFail($data['default_lead_id']);
        $this->guard($default);

        $group = $this->svc->group($default, auth()->user());
        ['eligible' => $e, 'ineligible' => $i] = $this->svc->classify($default, $group);

        return ApiResponse::success([
            'default'    => $this->slim($default->loadMissing(['stage:id,name', 'responsiblePerson:id,name', 'propertyType:id,name', 'area:id,name'])),
            'eligible'   => $e->map(fn ($l) => $this->slim($l))->values(),
            'ineligible' => $i->map(fn ($x) => $this->slim($x['lead']) + ['reason' => $x['reason']])->values(),
        ], 'OK');
    }

    public function confirm(Request $r)
    {
        $data = $r->validate([
            'default_lead_id' => 'required|integer',
            'lead_ids'        => 'required|array|min:1',
            'lead_ids.*'      => 'integer',
        ]);
        $default = Lead::findOrFail($data['default_lead_id']);
        $this->guard($default);

        try {
            $res = $this->svc->merge($default->id, $data['lead_ids'], auth()->user());
        } catch (ValidationException $e) {
            return ApiResponse::error(collect($e->errors())->flatten()->first(), 422);
        }

        return ApiResponse::success([
            'default_lead_id' => $res['default']->id, 'merged_ids' => $res['merged_ids'], 'skipped' => $res['skipped'],
        ], 'Leads merged successfully');
    }

    /** Merged Leads section */
            /** Merged Leads section: lead name + responsible person ONLY */
        public function mergedLeads(Lead $lead)
        {
            abort_unless(auth()->user()->canViewLead($lead), 403, 'You are not authorized to view this lead.');

            $rows = $lead->mergedLeads()
                ->with('responsiblePerson:id,name,display_name,avatar')
                ->orderByDesc('merged_at')->get()
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'lead_name' => $m->lead_name,
                    'responsible_person' => $m->responsiblePerson ? [
                        'id' => $m->responsiblePerson->id,
                        'name' => User::resolveDisplayName($m->responsiblePerson),
                        'avatar' => $m->responsiblePerson->avatar_url,
                    ] : null,
                ]);

            return ApiResponse::success($rows, 'OK');
        }

        /** Read-only info for ONE merged lead: whitelist, authorized through the default lead */
        public function restricted(Lead $lead, int $mergedId)
        {
            abort_unless(auth()->user()->canViewLead($lead), 403, 'You are not authorized to view this lead.');

            $m = $lead->mergedLeads()->with(['propertyType:id,name', 'area'])->findOrFail($mergedId);

            $data = Arr::only((new LeadResource($m))->resolve(), self::RESTRICTED_KEYS);
            foreach (['work_phone', 'work_phone_2', 'email', 'secondary_email'] as $k) {
                if (isset($data[$k])) $data[$k] = $this->mask($data[$k], $lead);
            }
            $data['can_edit'] = false;
            $data['is_merged'] = true;
            $data['merged_at'] = $m->merged_at;

            return ApiResponse::success($data, 'OK');
        }

 
}