<?php

namespace App\Http\Resources\Deal;

use App\Http\Resources\User\UserResource;
use App\Models\Deal;
use App\Models\LeadHistory;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class DealResource extends JsonResource
{
    /** @var array<int, LeadHistory|null> deal_id => latest assigned history */
    protected static array $assignmentHistoryByDealId = [];

    protected static bool $collectionPrimed = false;

    /**
     * Batch-load latest "assigned" histories for a deal collection.
     * Mirrors Deal::histories() semantics (deal_id OR lead_id) without per-deal queries.
     *
     * @param  iterable<int, Deal>  $deals
     */
    public static function primeForCollection(iterable $deals): void
    {
        $collection = $deals instanceof Collection ? $deals : collect($deals);
        static::clearCollectionPrime();

        if ($collection->isEmpty()) {
            return;
        }

        static::$collectionPrimed = true;

        $dealIds = $collection->pluck('id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $leadIds = $collection->pluck('lead_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        foreach ($dealIds as $dealId) {
            static::$assignmentHistoryByDealId[$dealId] = null;
        }

        if ($dealIds === []) {
            return;
        }

        $histories = LeadHistory::query()
            ->with([
                'user:id,name,display_name,avatar,email,parent_id,status,background_id',
                'user.background',
                'user.parent:id,name,display_name,avatar,parent_id,status,background_id',
                'user.parent.roles:id,name',
                'user.parent.background',
                'user.roles:id,name',
                'user.employeeProfile.companyBranch:id,name',
                'user.employeeProfile.designation:id,name',
                'user.employeeProfile.department:id,name',
            ])
            ->where('changes->action', 'assigned')
            ->where(function ($q) use ($dealIds, $leadIds) {
                $q->whereIn('deal_id', $dealIds);
                if ($leadIds !== []) {
                    $q->orWhereIn('lead_id', $leadIds);
                }
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        foreach ($collection as $deal) {
            $dealId = (int) $deal->id;
            $leadId = $deal->lead_id ? (int) $deal->lead_id : null;

            // Same selection order as histories()->...->orderBy(created_at desc)->first():
            // prefer deal_id match, else lead_id match (when lead_id is set).
            $match = $histories->first(function (LeadHistory $history) use ($dealId, $leadId) {
                if ((int) $history->deal_id === $dealId) {
                    return true;
                }

                return $leadId !== null && (int) $history->lead_id === $leadId;
            });

            static::$assignmentHistoryByDealId[$dealId] = $match;
        }
    }

    public static function clearCollectionPrime(): void
    {
        static::$assignmentHistoryByDealId = [];
        static::$collectionPrimed = false;
    }

    public static function isCollectionPrimed(): bool
    {
        return static::$collectionPrimed;
    }

    protected function resolveAssignmentHistory(): ?LeadHistory
    {
        $dealId = (int) $this->id;

        if (static::$collectionPrimed && array_key_exists($dealId, static::$assignmentHistoryByDealId)) {
            return static::$assignmentHistoryByDealId[$dealId];
        }

        return $this->histories()
            ->where('changes->action', 'assigned')
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function toArray($request)
    {
        $assignmentHistory = $this->resolveAssignmentHistory();

        if ($assignmentHistory && $assignmentHistory->user) {
            $assignedBy = $assignmentHistory->user;
        } else {
            $assignedBy = $this->addedBy;
        }

        return [
            'id' => $this->id,
            'deal_number' => $this->deal_number,
            'deal_type' => $this->deal_type,
            'status' => $this->status,
            'source' => $this->source,
            'deal_name' => $this->deal_name,
            'listing_id' => $this->listing_id,

            // Financial
            'deal_total_amount' => $this->deal_total_amount,
            'currency' => $this->currency,
            'deal_commission' => $this->deal_commission,
            'agent_share' => $this->agent_share,
            'company_share' => $this->company_share,

            // Relationships
            'lead_id' => $this->lead_id,
            'lead' => $this->whenLoaded('lead', fn () => [
                'id' => $this->lead->id,
                'lead_name' => $this->lead->lead_name,
                'name' => $this->lead->lead_name,
                'email' => $this->lead->email,
                'phone' => $this->lead->phone ?? $this->lead->work_phone,
                'work_phone' => $this->lead->work_phone,
            ]),

            'stage' => [
                'id' => $this->stage->id,
                'name' => $this->stage->name,
                'color' => $this->stage->color,
                'order' => $this->stage->order,
            ],

            'listing' => $this->listing ? [
                'id' => $this->listing->id,
                'name' => $this->listing->area?->area_title,
                'agent' => $this->listing->agent->name,
            ] : null,

            'area' => [
                'id' => $this->area?->id,
                'name' => $this->area?->name,
            ],

            // ========== MULTI PROPERTIES ==========
            'properties' => $this->whenLoaded('properties', function () use ($request) {
                return $this->properties->map(function ($property) use ($request) {
                    $listing = $property->listing; // belongsTo, eager-loaded when relation is requested
                    return [
                        'id' => $property->id,
                        'sort_order' => $property->sort_order,
                        'unit_no' => $property->unit_no,
                        'property_type_id' => $property->property_type_id,
                        'property_type_name' => $property->propertyType?->name,
                        'bedrooms' => $property->bedrooms,
                        'unit_size' => $property->unit_size,
                        'area_id' => $property->area_id,
                        'area_name' => $property->area?->area_title,
                        'project_id' => $property->project_id,
                        // Linked listing — surfaced so the view-deal screen can render the
                        // "Linked Listing" card and the deal forms can hydrate the picker.
                        'listing_id' => $property->listing_id,
                        'listing' => $listing ? [
                            'id' => $listing->id,
                            'reference_number' => $listing->reference_number,
                            'unit_number' => $listing->unit_number,
                            'status' => $listing->status,
                            'number_of_bedrooms' => $listing->number_of_bedrooms,
                            'size_sqft' => $listing->size_sqft,
                            'property_type' => $listing->propertyType ? [
                                'id' => $listing->propertyType->id,
                                'name' => $listing->propertyType->name,
                            ] : null,
                        ] : null,
                        'developer_id' => $property->developer_id,
                        'developer_name' => $property->developer_name,
                        'developer_phone' => $property->developer_phone,
                        'budget_from' => $property->budget_from,
                        'budget_to' => $property->budget_to,
                        'purchase_price' => $property->purchase_price,
                        'rental_price' => $property->rental_price,
                        'commission' => $property->commission,

                        // ✅ Debug: نجيب البيانات الخام الأول
                        'payment_proof_raw' => $property->payment_proof,
                        'spa_document_raw' => $property->spa_document,

                        'payment_proof' => (new PropertyDocumentResource($property->payment_proof, 'payment_proof'))->resolve($request),
                        'spa_document' => (new PropertyDocumentResource($property->spa_document, 'spa'))->resolve($request),

                        'display_name' => $property->display_name,
                        'budget_range' => $property->budget_range,
                             // ✅ EOI Documents
                        'eoi_documents_raw' => $property->eoi_documents,
                        'eoi_documents' => (new PropertyDocumentResource($property->eoi_documents, 'eoi'))->resolve($request),

                        // ✅ Booking Documents
                        'booking_documents_raw' => $property->booking_documents,
                        'booking_documents' => (new PropertyDocumentResource($property->booking_documents, 'booking'))->resolve($request),

                        // ✅ MOU Documents (secondary order 3+)
                        'mou_documents_raw' => $property->mou_documents,
                        'mou_documents' => (new PropertyDocumentResource($property->mou_documents, 'mou'))->resolve($request),

                        // ✅ NOC Documents (secondary order 4+)
                        'noc_documents_raw' => $property->noc_documents,
                        'noc_documents' => (new PropertyDocumentResource($property->noc_documents, 'noc'))->resolve($request),
                    ];
                });
            }),

            'developer_name' => $this->developer_name,
            'developer_phone' => $this->developer_phone,
            'developer' => [
                'id' => $this->developer?->id,
                'name' => $this->developer?->name,
            ],

            'responsible_person_id' => $this->responsible_person_id,
            'responsible_person' => new UserResource($this->responsiblePerson),

            'buyer_name' => (function () {
                $buyer = $this->parties
                    ->where('party_type', 'buyer')
                    ->where('party_role', 'primary')
                    ->first();
                return $buyer ? trim($buyer->first_name.' '.$buyer->last_name) : null;
            })(),

            'parties' => DealPartyResource::collection($this->whenLoaded('parties')),
            'documents' => DealDocumentResource::collection($this->whenLoaded('documents')),
            'parent' => new UserResource($assignedBy),
            'assigned_at' => $assignmentHistory ? $assignmentHistory->created_at : $this->created_at,
            'lost_reason' => $this->lost_reason,

            // Timestamps
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'converted_at' => $this->lead?->converted_at?->format('Y-m-d H:i:s'),
             'eoi_date' => $this->eoi_date?->toDateTimeString(),
            'booking_date' => $this->booking_date?->toDateTimeString(),
            'spa_date' => $this->spa_date?->toDateTimeString(),
            'security_deposit_date' => $this->security_deposit_date?->toDateTimeString(),
            'mou_date' => $this->mou_date?->toDateTimeString(),
            'noc_date' => $this->noc_date?->toDateTimeString(),
            'won_date' => $this->won_date?->toDateTimeString(),

        ];
    }

    /**
     * Parse JSON field to array
     */
    private function parseJsonField($value)
    {
        // لو فاضي
        if (empty($value)) {
            return [];
        }

        // لو已经是 array
        if (is_array($value)) {
            return $value;
        }

        // لو string - نحاول decode JSON
        if (is_string($value)) {
            // أولاً: لو كانت JSON string
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            // ثانياً: لو كانت serialized string
            $unserialized = @unserialize($value);
            if ($unserialized !== false && is_array($unserialized)) {
                return $unserialized;
            }
        }

        return [];
    }
}
