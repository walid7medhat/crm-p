<?php

namespace App\Http\Resources\Listing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\ListingAccessRequest;
use App\Models\User;

class ListingGridResource extends JsonResource
{
    /** Per-request memo: "{listingId}:{userId}" => approved request_type list. */
    protected static array $accessRequestCache = [];

    /** Per-request memo for role checks on the authenticated user. */
    protected static ?array $viewerFlags = null;

    public static function resetRequestCaches(): void
    {
        self::$accessRequestCache = [];
        self::$viewerFlags = null;
    }

    protected function viewerFlags(): array
    {
        if (self::$viewerFlags !== null) {
            return self::$viewerFlags;
        }

        $user = auth()->user();
        if (! $user) {
            return self::$viewerFlags = [
                'user' => null,
                'is_super_admin' => false,
                'is_admin' => false,
                'is_listing_manager' => false,
                'is_user_30' => false,
            ];
        }

        return self::$viewerFlags = [
            'user' => $user,
            'is_super_admin' => $user->hasRole('super_admin'),
            'is_admin' => $user->hasRole('admin'),
            'is_listing_manager' => $user->hasRole('manager') && (int) $user->listing_team === 1,
            'is_user_30' => (int) $user->id === 30,
        ];
    }

    protected function resolveCanEditPaymentBreakdown(Request $request): bool
    {
        $flags = $this->viewerFlags();
        $user = $flags['user'] ?? ($request->user() ?? auth()->user());
        if (! $user) {
            return false;
        }
        if ($flags['is_super_admin'] || $flags['is_admin']) {
            return true;
        }

        return (int) $this->agent_id === (int) $user->id;
    }

    protected function hasApprovedAccess(?int $userId, string $requestType): bool
    {
        if (! $userId) {
            return false;
        }
        $key = $this->id . ':' . $userId;
        if (! array_key_exists($key, self::$accessRequestCache)) {
            self::$accessRequestCache[$key] = $this->relationLoaded('accessRequests')
                ? $this->accessRequests
                    ->where('requested_by', $userId)
                    ->where('status', 'approved')
                    ->pluck('request_type')
                    ->all()
                : ListingAccessRequest::query()
                    ->where('listing_id', $this->id)
                    ->where('requested_by', $userId)
                    ->where('status', 'approved')
                    ->pluck('request_type')
                    ->all();
        }

        return in_array($requestType, self::$accessRequestCache[$key], true);
    }

    protected function resolveAreaLabel(): ?string
    {
        if ($this->relationLoaded('area') && $this->area) {
            return $this->area->title;
        }
        if ($this->relationLoaded('old_area') && $this->old_area) {
            return $this->old_area->title ?? $this->old_area->name;
        }

        return $this->area?->title ?? $this->old_area?->title ?? null;
    }

    protected function resolveTotalImages(): int
    {
        if (isset($this->gallery_images_count)) {
            return ((int) $this->gallery_images_count) + 1;
        }
        if ($this->relationLoaded('galleryImages')) {
            return $this->galleryImages->count() + 1;
        }

        return 1;
    }

    /**
     * Slim project payload for listing cards.
     * Avoids loading project galleries / features / ADGM area trees (those belong on detail).
     */
    protected function resolveProjectSummary(): ?array
    {
        if (! $this->relationLoaded('project') || ! $this->project) {
            return null;
        }

        $project = $this->project;
        $developer = $project->relationLoaded('developer') ? $project->developer : null;

        return [
            'id' => $project->id,
            'title' => $project->title,
            'name' => $project->title,
            'about' => $project->about,
            'area_id' => $project->area_id,
            'project_id' => $project->id,
            'developer' => $project->developer_id,
            'developer_name' => $developer?->name,
            'developerData' => $developer ? [
                'id' => $developer->id,
                'name' => $developer->name,
                'avatar' => $developer->avatar_path ? asset('storage/' . $developer->avatar_path) : null,
                'noc_fees_ready' => $developer->noc_fees_ready,
                'noc_fees_off_plan' => $developer->noc_fees_off_plan,
            ] : null,
            // Intentionally omitted for grid performance:
            // features, gallery_images, floor_plan_images, area ADGM helpers
            'features' => [],
            'gallery_images' => [],
            'floor_plan_images' => [],
            'image' => null,
            'image2' => null,
            'area' => null,
        ];
    }

    public function toArray(Request $request): array
    {
        $flags = $this->viewerFlags();
        $user = $flags['user'];
        $isPrivilegedViewer = $user && (
            $flags['is_super_admin']
            || (int) $this->agent_id === (int) $user->id
            || $flags['is_listing_manager']
            || $flags['is_user_30']
        );
        $canSeeUnitNumber = $isPrivilegedViewer
            || ($user && $this->hasApprovedAccess($user->id, ListingAccessRequest::TYPE_UNIT_NUMBER));
        $canSeeOwnerData = $isPrivilegedViewer
            || ($user && $this->hasApprovedAccess($user->id, ListingAccessRequest::TYPE_OWNER_DATA));

        $project = $this->relationLoaded('project') ? $this->project : null;

        return [
            'id' => $this->id,
            'title' => $project?->title,
            'approved' => (bool) $this->approved,
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'approved_by' => $this->whenLoaded('approvedBy', function () {
                return [
                    'id' => $this->approvedBy->id,
                    'name' => User::resolveDisplayName($this->approvedBy),
                ];
            }),
            'approval_status' => $this->approved ? 'approved' : 'pending',
            'rejection_reason' => $this->rejection_reason,
            'rejected_by' => $this->rejected_by,
            'rejected_by_name' => $this->relationLoaded('rejectedBy')
                ? User::resolveDisplayName($this->rejectedBy)
                : null,
            'is_active' => (bool) $this->is_active,
            'is_archived' => (bool) $this->is_archived,
            'is_hot_deal' => $this->is_hot_deal == 'Yes' && $this->hot_deal_approved_by && $this->hot_deal_approved_at
                ? $this->is_hot_deal
                : 'No',
            'sold_by' => $this->sold_by,
            'property_type_id' => $this->property_type_id,
            'developer_id' => $project?->developer_id,
            'project_id' => $this->project_id,
            'project_name' => $project?->title,
            'occupancy_status' => $this->occupancy_status,
            'reference_number' => $this->reference_number,
            'status' => $this->status,
            'unit_number' => $canSeeUnitNumber ? $this->unit_number : null,
            'size_sqft' => $this->size_sqft,
            'size_sqmt' => $this->size_sqmt,
            'number_of_bedrooms' => $this->number_of_bedrooms,
            'number_of_bathrooms' => $this->number_of_bathrooms,
            'price' => $this->price,
            'furnished_status' => $this->furnished_status,
            'listing_status' => $this->listing_status,
            'completion_status' => $this->completion_status,
            'original_price' => $this->original_price,
            'selling_price' => $this->selling_price ?? $this->price,
            'payment_breakdown' => $this->payment_breakdown,
            'assignment_expense_lines' => $this->assignment_expense_lines,
            'has_payment_breakdown' => $this->hasPaymentBreakdown(),
            'noc_percentage' => $this->noc_percentage,
            'handover_date' => $this->handover_date?->format('Y-m-d'),
            'payment_plan' => $this->payment_plan,
            'can_edit_payment_breakdown' => $this->resolveCanEditPaymentBreakdown($request),
            'main_image' => $this->hero_image_path
                ? route('image.watermark', ['path' => $this->hero_image_path])
                : null,
            'total_images' => $this->resolveTotalImages(),
            'property_type' => $this->propertyType?->name,
            'area' => $this->resolveAreaLabel(),
            'agent' => $this->whenLoaded('agent', function () {
                return [
                    'id' => $this->agent->id,
                    'name' => User::resolveDisplayName($this->agent),
                    'email' => $this->agent->email,
                    'avatar' => $this->avatar ?: null,
                ];
            }),
            'owner' => $this->whenLoaded('owner', fn () => $canSeeOwnerData ? new OwnerResource($this->owner) : null),
            'canShowOwner' => $canSeeOwnerData,
            'canShowUnitNumber' => $canSeeUnitNumber,
            'created_at' => $this->created_at?->format('M d, Y'),
            'project' => $this->resolveProjectSummary(),
        ];
    }
}
