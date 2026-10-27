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

    /** Exact roles with elevated merged-lead visibility (not a broad permission). */
    public const ELEVATED_VIEW_ROLES = ['super_admin', 'admin', 'branch_admin'];

    /**
     * Non-elevated detail: basic lead info + client requirements only.
     * No stage, comments, activities, histories, or merge metadata.
     */
    public const BASIC_KEYS = [
        'id', 'lead_name', 'salutation', 'first_name', 'last_name',
        'work_phone', 'work_phone_2', 'email', 'secondary_email',
        'area', 'area_id', 'property_type', 'property_type_id', 'bedrooms',
        'budget_from', 'budget_to', 'lead_type', 'property_status', 'purpose_buying',
        'status_lead', 'extra_client_requirements', 'whatsapp_qualification',
        'can_edit', 'is_merged',
    ];

    /** @deprecated Use BASIC_KEYS; kept for existing test references during transition. */
    public const RESTRICTED_KEYS = self::BASIC_KEYS;

    private function isElevatedViewer(?User $u = null): bool
    {
        $user = $u ?? auth()->user();

        return (bool) $user?->hasAnyRole(self::ELEVATED_VIEW_ROLES);
    }

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
        $full = $this->isElevatedViewer($u)
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

    /** Related merged leads for View Lead (authorized through the primary lead). */
        public function mergedLeads(Lead $lead)
        {
            abort_unless(auth()->user()->canViewLead($lead), 403, 'You are not authorized to view this lead.');

            $elevated = $this->isElevatedViewer();

            $query = $lead->mergedLeads()->orderByDesc('merged_at');
            if ($elevated) {
                $query->with([
                    'responsiblePerson:id,name,display_name,avatar',
                    'stage:id,name',
                ]);
            }

            $rows = $query->get()->map(function ($m) use ($lead, $elevated) {
                if (! $elevated) {
                    return [
                        'id' => $m->id,
                        'lead_name' => $m->lead_name,
                    ];
                }

                $originalStageId = $m->merged_original_stage_id ?: $m->stage_id;
                $originalStageName = $m->stage_id === $originalStageId
                    ? $m->stage?->name
                    : (\App\Models\Stage::query()->where('id', $originalStageId)->value('name') ?? $m->stage?->name);

                return [
                    'id' => $m->id,
                    'lead_name' => $m->lead_name,
                    'work_phone' => $this->mask($m->work_phone, $lead),
                    'email' => $this->mask($m->email, $lead),
                    'stage_id' => $m->stage_id,
                    'stage_name' => $originalStageName,
                    'original_stage_id' => $originalStageId,
                    'created_at' => $m->created_at,
                    'merged_at' => $m->merged_at,
                    'merge_type' => $m->merge_type,
                    'responsible_person' => $m->responsiblePerson ? [
                        'id' => $m->responsiblePerson->id,
                        'name' => User::resolveDisplayName($m->responsiblePerson),
                        'avatar' => $m->responsiblePerson->avatar_url,
                    ] : null,
                ];
            });

            return ApiResponse::success($rows->values(), 'OK', 200, [
                'elevated_merged_view' => $elevated,
            ]);
        }

        /** Read-only info for ONE merged lead: whitelist, authorized through the default lead */
        public function restricted(Lead $lead, int $mergedId)
        {
            abort_unless(auth()->user()->canViewLead($lead), 403, 'You are not authorized to view this lead.');

            $elevated = $this->isElevatedViewer();

            $with = ['propertyType:id,name', 'area'];
            if ($elevated) {
                $with = array_merge($with, [
                    'stage:id,name,order,color',
                    'responsiblePerson:id,name,display_name,avatar',
                    'comments' => fn ($q) => $q->latest('id')->limit(50),
                    'activities' => fn ($q) => $q->latest('id')->limit(50),
                    'histories' => fn ($q) => $q->latest('id')->limit(50),
                ]);
            }

            $m = $lead->mergedLeads()->with($with)->findOrFail($mergedId);

            $data = Arr::only((new LeadResource($m))->resolve(), self::BASIC_KEYS);
            foreach (['work_phone', 'work_phone_2', 'email', 'secondary_email'] as $k) {
                if (isset($data[$k])) {
                    $data[$k] = $this->mask($data[$k], $lead);
                }
            }
            $data['can_edit'] = false;
            $data['is_merged'] = true;
            $data['elevated_merged_view'] = $elevated;

            if ($elevated) {
                $originalStageId = $m->merged_original_stage_id ?: $m->stage_id;
                $data['merged_at'] = $m->merged_at;
                $data['merge_type'] = $m->merge_type;
                $data['merged_original_stage_id'] = $m->merged_original_stage_id;
                $data['stage_id'] = $m->stage_id;
                $data['stage'] = $m->stage ? [
                    'id' => $m->stage->id,
                    'name' => $m->stage->name,
                    'order' => $m->stage->order,
                    'color' => $m->stage->color,
                ] : null;
                $data['stage_name'] = $m->stage?->name;
                $data['original_stage_id'] = $originalStageId;
                $data['original_stage_name'] = $m->stage_id === $originalStageId
                    ? $m->stage?->name
                    : (\App\Models\Stage::query()->where('id', $originalStageId)->value('name') ?? $m->stage?->name);
                $data['responsible_person'] = $m->responsiblePerson ? [
                    'id' => $m->responsiblePerson->id,
                    'name' => User::resolveDisplayName($m->responsiblePerson),
                    'avatar' => $m->responsiblePerson->avatar_url,
                ] : null;
                $data['comments'] = $m->comments->map(fn ($c) => [
                    'id' => $c->id,
                    'comment' => $c->comment,
                    'user_id' => $c->user_id,
                    'created_at' => $c->created_at,
                ])->values();
                $data['activities'] = $m->activities->map(fn ($a) => [
                    'id' => $a->id,
                    'title' => $a->title ?? $a->activity_title ?? null,
                    'type' => $a->type ?? $a->activity_type ?? null,
                    'created_at' => $a->created_at,
                ])->values();
                $data['histories'] = $m->histories->map(fn ($h) => [
                    'id' => $h->id,
                    'changes' => $h->changes,
                    'user_id' => $h->user_id,
                    'created_at' => $h->created_at,
                ])->values();
            }

            return ApiResponse::success($data, 'OK');
        }


}
