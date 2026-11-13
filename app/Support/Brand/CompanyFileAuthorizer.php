<?php

namespace App\Support\Brand;

use App\Models\Applicant;
use App\Models\CommentAttachment;
use App\Models\Deal;
use App\Models\DealCommentAttachment;
use App\Models\DealDocument;
use App\Models\DealProperty;
use App\Models\DocumentRequest;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\Listing;
use App\Models\ListingAdditionalDocument;
use App\Models\MarketingTask;
use App\Models\Owner;
use App\Models\OwnerAdditionalDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Record-level authorization for private company files.
 *
 * A signed URL alone is never sufficient — the current user must be allowed
 * to view the owning resource, and the path must be attached to that resource.
 */
class CompanyFileAuthorizer
{
    public const TYPE_DEAL = 'deal';

    public const TYPE_DEAL_DOCUMENT = 'deal_document';

    public const TYPE_DEAL_PROPERTY = 'deal_property';

    public const TYPE_DEAL_COMMENT_ATTACHMENT = 'deal_comment_attachment';

    public const TYPE_LEAD_COMMENT_ATTACHMENT = 'lead_comment_attachment';

    public const TYPE_LISTING = 'listing';

    public const TYPE_LISTING_ADDITIONAL_DOCUMENT = 'listing_additional_document';

    public const TYPE_OWNER = 'owner';

    public const TYPE_OWNER_ADDITIONAL_DOCUMENT = 'owner_additional_document';

    public const TYPE_EMPLOYEE_DOCUMENT = 'employee_document';

    public const TYPE_DOCUMENT_REQUEST = 'document_request';

    public const TYPE_LEAVE_REQUEST = 'leave_request';

    public const TYPE_APPLICANT = 'applicant';

    public const TYPE_MARKETING_TASK = 'marketing_task';

    public const TYPE_USER = 'user';

    /** @var list<string> */
    public const KNOWN_TYPES = [
        self::TYPE_DEAL,
        self::TYPE_DEAL_DOCUMENT,
        self::TYPE_DEAL_PROPERTY,
        self::TYPE_DEAL_COMMENT_ATTACHMENT,
        self::TYPE_LEAD_COMMENT_ATTACHMENT,
        self::TYPE_LISTING,
        self::TYPE_LISTING_ADDITIONAL_DOCUMENT,
        self::TYPE_OWNER,
        self::TYPE_OWNER_ADDITIONAL_DOCUMENT,
        self::TYPE_EMPLOYEE_DOCUMENT,
        self::TYPE_DOCUMENT_REQUEST,
        self::TYPE_LEAVE_REQUEST,
        self::TYPE_APPLICANT,
        self::TYPE_MARKETING_TASK,
        self::TYPE_USER,
    ];

    /**
     * Authorize the current (or given) user for a private file binding.
     * Cross-brand / missing → 404 (no existence leak). Unauthorized same brand → 403.
     */
    public function authorizeOrAbort(
        string $resource,
        int $resourceId,
        string $path,
        ?User $user = null
    ): void {
        $path = CompanyFilePolicy::normalize($path);
        $user = $user ?? Auth::user();

        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        if (! in_array($resource, self::KNOWN_TYPES, true)) {
            Log::warning('company_file.unknown_resource_type', [
                'resource' => $resource,
                'resource_id' => $resourceId,
            ]);
            abort(404);
        }

        if (! BrandIsolation::pathAllowedForCurrentBrand($path)) {
            abort(404);
        }

        $resolved = $this->resolve($resource, $resourceId);
        if ($resolved === null) {
            abort(404);
        }

        if (! $this->pathBelongsToResource($resource, $resolved, $path)) {
            abort(404);
        }

        if (! $this->userMayAccess($user, $resource, $resolved)) {
            // Same-brand denial — do not reveal file contents.
            abort(403, 'Forbidden');
        }
    }

    /**
     * @return array{resource: string, resource_id: int}|null
     */
    public function resolveBindingFromPath(string $path): ?array
    {
        $path = CompanyFilePolicy::normalize($path);
        if ($path === '' || CompanyFilePolicy::isPublicAllowlisted($path)) {
            return null;
        }

        $doc = DealDocument::query()->where('file_path', $path)->first();
        if ($doc) {
            return ['resource' => self::TYPE_DEAL_DOCUMENT, 'resource_id' => (int) $doc->id];
        }

        $dealAtt = DealCommentAttachment::query()->where('file_path', $path)->first();
        if ($dealAtt) {
            return ['resource' => self::TYPE_DEAL_COMMENT_ATTACHMENT, 'resource_id' => (int) $dealAtt->id];
        }

        $leadAtt = CommentAttachment::query()->where('file_path', $path)->first();
        if ($leadAtt) {
            return ['resource' => self::TYPE_LEAD_COMMENT_ATTACHMENT, 'resource_id' => (int) $leadAtt->id];
        }

        $listingExtra = ListingAdditionalDocument::query()->where('path', $path)->first();
        if ($listingExtra) {
            return ['resource' => self::TYPE_LISTING_ADDITIONAL_DOCUMENT, 'resource_id' => (int) $listingExtra->id];
        }

        $ownerExtra = OwnerAdditionalDocument::query()->where('path', $path)->first();
        if ($ownerExtra) {
            return ['resource' => self::TYPE_OWNER_ADDITIONAL_DOCUMENT, 'resource_id' => (int) $ownerExtra->id];
        }

        $empDoc = EmployeeDocument::query()->where('file_path', $path)->first();
        if ($empDoc) {
            return ['resource' => self::TYPE_EMPLOYEE_DOCUMENT, 'resource_id' => (int) $empDoc->id];
        }

        $docReq = DocumentRequest::query()->where('file_path', $path)->first();
        if ($docReq) {
            return ['resource' => self::TYPE_DOCUMENT_REQUEST, 'resource_id' => (int) $docReq->id];
        }

        $leave = LeaveRequest::query()->where('attachment', $path)->first();
        if ($leave) {
            return ['resource' => self::TYPE_LEAVE_REQUEST, 'resource_id' => (int) $leave->id];
        }

        $applicant = Applicant::query()
            ->where('resume_path', $path)
            ->orWhere('cover_letter_path', $path)
            ->first();
        if ($applicant) {
            return ['resource' => self::TYPE_APPLICANT, 'resource_id' => (int) $applicant->id];
        }

        $task = MarketingTask::query()->where('reference_image', $path)->first();
        if ($task) {
            return ['resource' => self::TYPE_MARKETING_TASK, 'resource_id' => (int) $task->id];
        }

        $listing = Listing::query()
            ->where('spa_document_path', $path)
            ->orWhere('desk_document_path', $path)
            ->orWhere('other_document_path', $path)
            ->first();
        if ($listing) {
            return ['resource' => self::TYPE_LISTING, 'resource_id' => (int) $listing->id];
        }

        $owner = Owner::query()
            ->where('id_front_path', $path)
            ->orWhere('id_back_path', $path)
            ->orWhere('visa_copy_path', $path)
            ->orWhere('passport_copy_path', $path)
            ->orWhere('avatar_path', $path)
            ->first();
        if ($owner) {
            return ['resource' => self::TYPE_OWNER, 'resource_id' => (int) $owner->id];
        }

        $user = User::query()->where('avatar', $path)->first();
        if ($user) {
            return ['resource' => self::TYPE_USER, 'resource_id' => (int) $user->id];
        }

        // Path-encoded deal id: deals/{id}/… (optional luxvant/ prefix).
        // Property JSON files also live under this prefix; TYPE_DEAL + dealOwnsPath covers them.
        $logical = $path;
        foreach (['luxvant/', 'oia/'] as $prefix) {
            if (str_starts_with($logical, $prefix)) {
                $logical = substr($logical, strlen($prefix));
                break;
            }
        }
        if (preg_match('#^deals/(\d+)/#', $logical, $m)) {
            $dealId = (int) $m[1];
            if (Deal::query()->whereKey($dealId)->exists()) {
                return ['resource' => self::TYPE_DEAL, 'resource_id' => $dealId];
            }
        }

        // Attendance: EmployeeAttendanceController stores under attendance/{userId}/ but
        // attendances.attachment does not exist in the live schema / Attendance::$fillable.
        // Fail closed — do not invent ownership. Required schema change:
        //   ALTER attendances ADD attachment VARCHAR NULL; then add TYPE_ATTENDANCE
        //   bound to Attendance id with userMayAccess via attendance.user_id.
        return null;
    }

    private function resolve(string $resource, int $resourceId): ?Model
    {
        return match ($resource) {
            self::TYPE_DEAL => Deal::query()->find($resourceId),
            self::TYPE_DEAL_DOCUMENT => DealDocument::query()->with('deal')->find($resourceId),
            self::TYPE_DEAL_PROPERTY => DealProperty::query()->with('deal')->find($resourceId),
            self::TYPE_DEAL_COMMENT_ATTACHMENT => DealCommentAttachment::query()->with('comment.deal')->find($resourceId),
            self::TYPE_LEAD_COMMENT_ATTACHMENT => CommentAttachment::query()->with('comment.lead')->find($resourceId),
            self::TYPE_LISTING => Listing::query()->find($resourceId),
            self::TYPE_LISTING_ADDITIONAL_DOCUMENT => ListingAdditionalDocument::query()->with('listing')->find($resourceId),
            self::TYPE_OWNER => Owner::query()->find($resourceId),
            self::TYPE_OWNER_ADDITIONAL_DOCUMENT => OwnerAdditionalDocument::query()->with('owner')->find($resourceId),
            self::TYPE_EMPLOYEE_DOCUMENT => EmployeeDocument::query()->with('employeeProfile.user')->find($resourceId),
            self::TYPE_DOCUMENT_REQUEST => DocumentRequest::query()->find($resourceId),
            self::TYPE_LEAVE_REQUEST => LeaveRequest::query()->find($resourceId),
            self::TYPE_APPLICANT => Applicant::query()->find($resourceId),
            self::TYPE_MARKETING_TASK => MarketingTask::query()->find($resourceId),
            self::TYPE_USER => User::query()->find($resourceId),
            default => null,
        };
    }

    private function pathBelongsToResource(string $resource, Model $model, string $path): bool
    {
        $path = CompanyFilePolicy::normalize($path);

        return match ($resource) {
            self::TYPE_DEAL => $model instanceof Deal && $this->dealOwnsPath($model, $path),
            self::TYPE_DEAL_DOCUMENT => CompanyFilePolicy::normalize((string) $model->file_path) === $path,
            self::TYPE_DEAL_PROPERTY => $model instanceof DealProperty && $this->dealPropertyOwnsPath($model, $path),
            self::TYPE_DEAL_COMMENT_ATTACHMENT,
            self::TYPE_LEAD_COMMENT_ATTACHMENT => CompanyFilePolicy::normalize((string) $model->file_path) === $path,
            self::TYPE_LISTING => in_array($path, array_filter([
                CompanyFilePolicy::normalize($model->spa_document_path ?? null),
                CompanyFilePolicy::normalize($model->desk_document_path ?? null),
                CompanyFilePolicy::normalize($model->other_document_path ?? null),
            ]), true),
            self::TYPE_LISTING_ADDITIONAL_DOCUMENT => CompanyFilePolicy::normalize((string) $model->path) === $path,
            self::TYPE_OWNER => in_array($path, array_filter([
                CompanyFilePolicy::normalize($model->id_front_path ?? null),
                CompanyFilePolicy::normalize($model->id_back_path ?? null),
                CompanyFilePolicy::normalize($model->visa_copy_path ?? null),
                CompanyFilePolicy::normalize($model->passport_copy_path ?? null),
                CompanyFilePolicy::normalize($model->avatar_path ?? null),
            ]), true),
            self::TYPE_OWNER_ADDITIONAL_DOCUMENT => CompanyFilePolicy::normalize((string) $model->path) === $path,
            self::TYPE_EMPLOYEE_DOCUMENT => CompanyFilePolicy::normalize((string) $model->file_path) === $path,
            self::TYPE_DOCUMENT_REQUEST => CompanyFilePolicy::normalize((string) $model->file_path) === $path,
            self::TYPE_LEAVE_REQUEST => CompanyFilePolicy::normalize((string) ($model->attachment ?? '')) === $path,
            self::TYPE_APPLICANT => in_array($path, array_filter([
                CompanyFilePolicy::normalize($model->resume_path ?? null),
                CompanyFilePolicy::normalize($model->cover_letter_path ?? null),
            ]), true),
            self::TYPE_MARKETING_TASK => CompanyFilePolicy::normalize((string) ($model->reference_image ?? '')) === $path,
            self::TYPE_USER => CompanyFilePolicy::normalize((string) ($model->getRawOriginal('avatar') ?? $model->avatar ?? '')) === $path
                || CompanyFilePolicy::normalize((string) ($model->avatar ?? '')) === $path,
            default => false,
        };
    }

    private function userMayAccess(User $user, string $resource, Model $model): bool
    {
        return match ($resource) {
            self::TYPE_DEAL => $model instanceof Deal && $user->canViewDeal($model),
            self::TYPE_DEAL_DOCUMENT => ($model->deal instanceof Deal) && $user->canViewDeal($model->deal),
            self::TYPE_DEAL_PROPERTY => ($model->deal instanceof Deal) && $user->canViewDeal($model->deal),
            self::TYPE_DEAL_COMMENT_ATTACHMENT => ($model->comment?->deal instanceof Deal)
                && $user->canViewDeal($model->comment->deal),
            self::TYPE_LEAD_COMMENT_ATTACHMENT => ($model->comment?->lead)
                && $user->canViewLead($model->comment->lead),
            self::TYPE_LISTING => $model instanceof Listing && $this->canViewListing($user, $model),
            self::TYPE_LISTING_ADDITIONAL_DOCUMENT => ($model->listing instanceof Listing)
                && $this->canViewListing($user, $model->listing),
            self::TYPE_OWNER => $model instanceof Owner && $this->canViewOwner($user, $model),
            self::TYPE_OWNER_ADDITIONAL_DOCUMENT => ($model->owner instanceof Owner)
                && $this->canViewOwner($user, $model->owner),
            self::TYPE_EMPLOYEE_DOCUMENT => $this->canViewEmployeeSubject($user, $this->employeeDocumentUser($model)),
            self::TYPE_DOCUMENT_REQUEST => $this->canViewEmployeeSubject($user, User::query()->find($model->user_id)),
            self::TYPE_LEAVE_REQUEST => $this->canViewEmployeeSubject($user, User::query()->find($model->user_id)),
            self::TYPE_APPLICANT => $user->hasAnyRole(['super_admin', 'hr', 'admin', 'branch_admin']),
            self::TYPE_MARKETING_TASK => $user->hasAnyRole(['super_admin', 'admin', 'branch_admin', 'marketing', 'manager'])
                || (int) ($model->assigned_to ?? 0) === (int) $user->id
                || (int) ($model->designed_by ?? 0) === (int) $user->id
                || (int) ($model->created_by ?? 0) === (int) $user->id,
            self::TYPE_USER => $model instanceof User
                && BrandIsolation::userBelongsToCurrentBrand($model)
                && (
                    (int) $user->id === (int) $model->id
                    || $user->hasAnyRole(['super_admin', 'admin', 'hr', 'branch_admin'])
                ),
            default => false,
        };
    }

    private function canViewListing(User $user, Listing $listing): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin', 'branch_admin'])) {
            return true;
        }
        if ((int) $listing->agent_id === (int) $user->id) {
            return true;
        }
        if ($user->hasRole('manager') || $user->hasRole('team_lead')) {
            return in_array((int) $listing->agent_id, $user->getAllSubordinatesIds(), true);
        }

        // Listing team / sales with listing permission often browse published inventory.
        return $user->can('listings-view') || $user->can('listings-index');
    }

    private function canViewOwner(User $user, Owner $owner): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin', 'branch_admin'])) {
            return true;
        }
        if ((int) ($owner->added_by ?? 0) === (int) $user->id) {
            return true;
        }

        return (bool) ($user->listing_team ?? false) || $user->hasRole('manager');
    }

    private function canViewEmployeeSubject(User $viewer, ?User $subject): bool
    {
        if (! $subject || ! BrandIsolation::userBelongsToCurrentBrand($subject)) {
            return false;
        }
        if ($viewer->hasAnyRole(['super_admin', 'hr', 'admin', 'branch_admin'])) {
            return true;
        }
        if ((int) $viewer->id === (int) $subject->id) {
            return true;
        }
        if ($viewer->isManagerOrTeamLead()) {
            return in_array((int) $subject->id, $viewer->getAllSubordinatesIds(), true);
        }

        return false;
    }

    private function employeeDocumentUser(Model $doc): ?User
    {
        if (method_exists($doc, 'employeeProfile') && $doc->relationLoaded('employeeProfile')) {
            return $doc->employeeProfile?->user;
        }
        if (isset($doc->employee_profile_id)) {
            $profile = $doc->employeeProfile()->with('user')->first();

            return $profile?->user;
        }

        return null;
    }

    private function dealOwnsPath(Deal $deal, string $path): bool
    {
        if (DealDocument::query()->where('deal_id', $deal->id)->where('file_path', $path)->exists()) {
            return true;
        }

        foreach ($deal->properties ?? $deal->properties()->get() as $property) {
            if ($this->dealPropertyOwnsPath($property, $path)) {
                return true;
            }
        }

        $logical = $path;
        foreach (['luxvant/', 'oia/'] as $prefix) {
            if (str_starts_with($logical, $prefix)) {
                $logical = substr($logical, strlen($prefix));
                break;
            }
        }

        return (bool) preg_match('#^deals/'.preg_quote((string) $deal->id, '#').'/#', $logical);
    }

    private function dealPropertyOwnsPath(DealProperty $property, string $path): bool
    {
        $fields = [
            'payment_proof', 'spa_document', 'eoi_documents', 'booking_documents',
            'mou_documents', 'noc_documents', 'title_deed_documents',
            'contract_document', 'ejari_document',
        ];

        foreach ($fields as $field) {
            $docs = $property->{$field} ?? null;
            if (! is_array($docs)) {
                if (is_string($docs) && $docs !== '') {
                    $decoded = json_decode($docs, true);
                    $docs = is_array($decoded) ? $decoded : [];
                } else {
                    $docs = [];
                }
            }
            foreach ($docs as $doc) {
                if (! is_array($doc)) {
                    continue;
                }
                $docPath = CompanyFilePolicy::normalize((string) ($doc['path'] ?? $doc['file_path'] ?? ''));
                if ($docPath !== '' && $docPath === $path) {
                    return true;
                }
            }
        }

        return false;
    }

}
