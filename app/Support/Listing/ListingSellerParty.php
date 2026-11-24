<?php

namespace App\Support\Listing;

use App\Helpers\ImageHelper;
use App\Models\Deal;
use App\Models\DealDocument;
use App\Models\DealParty;
use App\Models\DealProperty;
use App\Models\Listing;
use App\Models\ListingAccessRequest;
use App\Models\Owner;
use App\Models\User;
use App\Support\Brand\BrandIsolation;
use App\Support\Brand\BrandStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Listing party captured when an OIA agent closes the listing:
 *  - "Sold Out" → the SELLER   (given to the secondary deal on that unit)
 *  - "Rented"   → the LANDLORD (given to the rental deal on that unit)
 *
 * The party is a normal deal_parties row (party_type seller / landlord) with listing_id set
 * and deal_id NULL; its files are deal_documents rows with the same listing_id. When the
 * matching deal is created on the listing, the SAME rows get deal_id (+ deal_property_id) —
 * nothing is copied. reconcile() keeps that link in step with the deal's properties.
 *
 * Visibility: a listing party is hidden from the deal's sales agent. Only admin /
 * super_admin / accountant and the listing side (OwnerVisibility + approved owner-data
 * access request) may see or edit it.
 */
class ListingSellerParty
{
    public const FIELDS = [
        'first_name', 'last_name', 'date_of_birth', 'phone', 'email',
        'nationality', 'residency_status', 'city', 'country', 'language',
    ];

    public const DOCUMENT_TYPES = ['national_id', 'passport', 'visa', 'title_deed'];

    /** party_type => deal type that gets it + listing status it comes from. */
    public const TYPES = [
        'seller' => ['deal_type' => 'secondary', 'listing_status' => 'converted'],
        'landlord' => ['deal_type' => 'rental', 'listing_status' => 'rented'],
    ];

    /** Roles that always see listing parties on deals. */
    public const SEE_ALL_ROLES = ['admin', 'super_admin', 'accountant'];

    /** @var array<string, bool> "userId|listingId" => can see */
    private static array $canSeeMemo = [];

    /** Listing party type a deal of this type gets (seller / landlord), or null. */
    public static function partyTypeForDeal(?string $dealType): ?string
    {
        foreach (self::TYPES as $type => $cfg) {
            if ($cfg['deal_type'] === $dealType) {
                return $type;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Sold out / rented (listing side)
    // ------------------------------------------------------------------

    /**
     * Save the seller / landlord (and uploaded documents) of a listing being marked sold out
     * / rented. $details null → taken from the listing's owner.
     *
     * @param  array<int, array{document_type: string, file: UploadedFile}>  $documents
     */
    public static function saveForListing(Listing $listing, ?array $details, array $documents = [], string $type = 'seller'): ?DealParty
    {
        $data = $details !== null ? self::cleanDetails($details) : null;
        $fromOwner = $data === null || ($data['first_name'] ?? '') === '';
        if ($fromOwner) {
            $data = self::detailsFromOwner(self::partyOwner($listing, $type));
        }
        if (! $data || ($data['first_name'] ?? '') === '') {
            return null; // deal_parties.first_name is required; nothing to save
        }

        // Reuse the listing's free row; a row already linked to a deal stays with it.
        $party = DealParty::query()
            ->where('listing_id', $listing->id)
            ->where('party_type', $type)
            ->whereNull('deal_id')
            ->latest('id')
            ->first();

        if ($party) {
            $party->update($data);
        } else {
            $party = DealParty::create([
                ...$data,
                'deal_id' => null,
                'listing_id' => $listing->id,
                'party_type' => $type,
                'party_role' => 'primary',
            ]);
        }

        foreach ($documents as $doc) {
            $file = $doc['file'] ?? null;
            $docType = $doc['document_type'] ?? null;
            if ($file instanceof UploadedFile && $file->isValid() && $docType) {
                self::storeUploadedDocument($listing, $party, $docType, $file);
            }
        }

        if ($fromOwner && empty($documents) && ! $party->documents()->exists()) {
            self::copyOwnerDocuments($listing, $party, self::partyOwner($listing, $type));
        }

        return $party;
    }

    /** Remove the listing's free party (not linked to a deal) and its files — on revert. */
    public static function deleteUnlinkedForListing(Listing $listing, string $type = 'seller'): void
    {
        $parties = DealParty::query()
            ->where('listing_id', $listing->id)
            ->where('party_type', $type)
            ->whereNull('deal_id')
            ->get();

        foreach ($parties as $party) {
            foreach ($party->documents()->whereNull('deal_id')->get() as $doc) {
                self::deleteDocumentFile($doc);
                $doc->delete();
            }
            $party->delete();
        }
    }

    /**
     * The listing's seller / landlord row — creates it from the owner for listings closed
     * before this feature existed. Returns null when there's nothing to build it from.
     */
    public static function ensureFor(Listing $listing, string $type = 'seller'): ?DealParty
    {
        $party = DealParty::query()
            ->where('listing_id', $listing->id)
            ->where('party_type', $type)
            ->orderByRaw('deal_id is null desc')
            ->latest('id')
            ->first();

        return $party ?? self::saveForListing($listing, null, [], $type);
    }

    // ------------------------------------------------------------------
    // Deal side
    // ------------------------------------------------------------------

    /**
     * Link / unlink listing parties so they match the deal's current properties and type
     * (secondary → sellers of sold-out units, rental → landlords of rented units).
     * Call after anything that creates, edits, removes or reorders deal properties, or
     * changes the deal type.
     */
    public static function reconcile(Deal $deal): void
    {
        try {
            $properties = $deal->properties()->get(['id', 'deal_id', 'listing_id', 'sort_order']);
            $type = self::partyTypeForDeal($deal->deal_type);

            /** @var array<int, DealProperty|null> $wanted listing_id => property */
            $wanted = [];
            if ($type) {
                foreach ($properties as $property) {
                    if ($property->listing_id && ! array_key_exists((int) $property->listing_id, $wanted)) {
                        $wanted[(int) $property->listing_id] = $property;
                    }
                }
                if ($deal->listing_id && ! array_key_exists((int) $deal->listing_id, $wanted)) {
                    $wanted[(int) $deal->listing_id] = null;
                }
            }

            $linked = DealParty::query()
                ->where('deal_id', $deal->id)
                ->whereIn('party_type', array_keys(self::TYPES))
                ->whereNotNull('listing_id')
                ->get();

            $toLink = $wanted;
            foreach ($linked as $party) {
                $listingId = (int) $party->listing_id;
                if ($party->party_type !== $type
                    || ! array_key_exists($listingId, $wanted)
                    || ! array_key_exists($listingId, $toLink)) {
                    // Unit removed / changed, deal type changed, or a duplicate link.
                    self::unlink($party);
                    continue;
                }
                $propertyId = $wanted[$listingId]?->id;
                if ((int) $party->deal_property_id !== (int) $propertyId) {
                    $party->update(['deal_property_id' => $propertyId]);
                }
                unset($toLink[$listingId]);
            }

            foreach ($toLink as $listingId => $property) {
                $listing = Listing::query()->withoutGlobalScope('branchView')->find($listingId);
                if (! $listing || $listing->status !== self::TYPES[$type]['listing_status']) {
                    continue;
                }
                $party = self::ensureFor($listing, $type);
                if (! $party) {
                    continue;
                }
                if ($party->deal_id && (int) $party->deal_id !== (int) $deal->id) {
                    // Already on another deal; take it over only if that deal was archived.
                    $other = Deal::withTrashed()->withoutGlobalScope('branchView')->find($party->deal_id);
                    if ($other && ! $other->trashed()) {
                        continue;
                    }
                }
                self::link($party, $deal, $property);
            }

            if ($type) {
                self::syncRoles($deal, $properties, $type);
            }
        } catch (\Throwable $e) {
            // Never break a deal save because of the listing party link.
            Log::error('ListingSellerParty::reconcile failed', [
                'deal_id' => $deal->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function link(DealParty $party, Deal $deal, ?DealProperty $property): void
    {
        $party->update([
            'deal_id' => $deal->id,
            'deal_property_id' => $property?->id,
        ]);

        DealDocument::query()
            ->where('deal_party_id', $party->id)
            ->update(['deal_id' => $deal->id, 'listing_id' => $party->listing_id]);
    }

    private static function unlink(DealParty $party): void
    {
        $party->update([
            'deal_id' => null,
            'deal_property_id' => null,
            'party_role' => 'primary',
        ]);

        DealDocument::query()
            ->where('deal_party_id', $party->id)
            ->update(['deal_id' => null, 'listing_id' => $party->listing_id]);
    }

    /** Main property's party is primary, the other listing parties of that type are secondary. */
    private static function syncRoles(Deal $deal, Collection $properties, string $type): void
    {
        $sortById = $properties->pluck('sort_order', 'id');

        $parties = DealParty::query()
            ->where('deal_id', $deal->id)
            ->where('party_type', $type)
            ->whereNotNull('listing_id')
            ->get()
            ->sortBy(fn (DealParty $p) => [
                $p->deal_property_id ? (int) ($sortById[$p->deal_property_id] ?? PHP_INT_MAX) : PHP_INT_MAX,
                (int) $p->id,
            ])
            ->values();

        foreach ($parties as $i => $party) {
            $role = $i === 0 ? 'primary' : 'secondary';
            if ($party->party_role !== $role) {
                $party->update(['party_role' => $role]);
            }
        }
    }

    // ------------------------------------------------------------------
    // Visibility
    // ------------------------------------------------------------------

    public static function canSee(?User $user, DealParty $party): bool
    {
        if (! $party->isListingSeller()) {
            return true;
        }

        return self::canSeeListingSeller($user, (int) $party->listing_id);
    }

    public static function canSeeDocument(?User $user, DealDocument $document): bool
    {
        if ($document->deal_party_id) {
            $party = $document->relationLoaded('party')
                ? $document->party
                : DealParty::query()->find($document->deal_party_id, ['id', 'listing_id', 'party_type']);
            if ($party) {
                return self::canSee($user, $party);
            }
        }

        // A listing party file that is not on a deal belongs to the listing side only.
        if ($document->listing_id && ! $document->deal_id) {
            return self::canSeeListingSeller($user, (int) $document->listing_id);
        }

        return true;
    }

    /** Can the viewer see the seller / landlord saved on this listing? */
    public static function canSeeListingSeller(?User $user, int $listingId): bool
    {
        if (! $user || ! $listingId) {
            return false;
        }

        $key = $user->id.'|'.$listingId;
        if (array_key_exists($key, self::$canSeeMemo)) {
            return self::$canSeeMemo[$key];
        }

        if ($user->hasAnyRole(self::SEE_ALL_ROLES)) {
            return self::$canSeeMemo[$key] = true;
        }

        $agentId = Listing::query()->withoutGlobalScope('branchView')->whereKey($listingId)->value('agent_id');
        if (OwnerVisibility::canSeeListingOf($user, $agentId)) {
            return self::$canSeeMemo[$key] = true;
        }

        return self::$canSeeMemo[$key] = ListingAccessRequest::query()
            ->where('listing_id', $listingId)
            ->where('requested_by', $user->id)
            ->where('status', 'approved')
            ->where('request_type', ListingAccessRequest::TYPE_OWNER_DATA)
            ->exists();
    }

    /** Ids of the deal's parties the viewer must not see. */
    public static function hiddenPartyIds(iterable $parties, ?User $user): array
    {
        $hidden = [];
        foreach ($parties as $party) {
            if (! self::canSee($user, $party)) {
                $hidden[] = (int) $party->id;
            }
        }

        return $hidden;
    }

    /**
     * False when the deal has a listing party of this type (seller / landlord) the viewer
     * can't see — they may not edit that party type.
     */
    public static function mayEditSellers(Deal $deal, ?User $user, string $type = 'seller'): bool
    {
        $listingIds = DealParty::query()
            ->where('deal_id', $deal->id)
            ->where('party_type', $type)
            ->whereNotNull('listing_id')
            ->pluck('listing_id');

        foreach ($listingIds as $listingId) {
            if (! self::canSeeListingSeller($user, (int) $listingId)) {
                return false;
            }
        }

        return true;
    }

    public static function resetMemo(): void
    {
        self::$canSeeMemo = [];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private static function cleanDetails(array $details): array
    {
        $data = [];
        foreach (self::FIELDS as $field) {
            $value = $details[$field] ?? ($field === 'date_of_birth' ? ($details['dob'] ?? null) : null);
            $value = is_string($value) ? trim($value) : $value;
            $data[$field] = ($value === '' ? null : $value);
        }

        if ($data['date_of_birth']) {
            try {
                $data['date_of_birth'] = \Carbon\Carbon::parse($data['date_of_birth'])->format('Y-m-d');
            } catch (\Throwable) {
                $data['date_of_birth'] = null;
            }
        }
        $data['first_name'] = (string) ($data['first_name'] ?? '');

        return $data;
    }

    /** Owner to build the party from: the landlord is the current owner. */
    private static function partyOwner(Listing $listing, string $type): ?Owner
    {
        if ($type === 'landlord') {
            return $listing->owner_id ? Owner::query()->find($listing->owner_id) : null;
        }

        return self::sellerOwner($listing);
    }

    /**
     * The owner who sold. The "new owner" sold-out flow swaps owner_id to the buyer first
     * (previous_owner_id = seller); an agent reassignment also sets previous_owner_id but
     * to a clone of the same person — so the previous owner only wins when it's a
     * different person.
     */
    private static function sellerOwner(Listing $listing): ?Owner
    {
        $current = $listing->owner_id ? Owner::query()->find($listing->owner_id) : null;
        $previous = $listing->previous_owner_id ? Owner::query()->find($listing->previous_owner_id) : null;

        if ($previous && $current && ! self::samePerson($previous, $current)) {
            return $previous;
        }

        return $current ?? $previous;
    }

    private static function samePerson(Owner $a, Owner $b): bool
    {
        $norm = fn ($v) => Str::lower(preg_replace('/\s+/', '', (string) $v));

        return ($norm($a->phone_number) !== '' && $norm($a->phone_number) === $norm($b->phone_number))
            || ($norm($a->first_name.$a->last_name) === $norm($b->first_name.$b->last_name));
    }

    private static function detailsFromOwner(?Owner $owner): ?array
    {
        if (! $owner) {
            return null;
        }

        return [
            'first_name' => (string) $owner->first_name,
            'last_name' => $owner->last_name,
            'date_of_birth' => null,
            'phone' => $owner->phone_number,
            'email' => $owner->email,
            'nationality' => $owner->nationality,
            'residency_status' => $owner->residency_status,
            'city' => null,
            'country' => null,
            'language' => null,
        ];
    }

    private static function documentDirectory(Listing $listing, string $type): string
    {
        // listings/documents/* is sensitive (private disk) and uses the Luxvant storage split.
        return "listings/documents/{$listing->id}/{$type}";
    }

    private static function storeUploadedDocument(Listing $listing, DealParty $party, string $docType, UploadedFile $file): void
    {
        $directory = self::documentDirectory($listing, $party->party_type);
        $mime = $file->getMimeType();

        if (in_array($mime, ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'], true)) {
            $result = ImageHelper::compressAndConvertToWebP($file, $directory, [
                'quality' => 80,
                'max_width' => 1920,
                'max_height' => 1080,
                'watermark' => ['enabled' => false],
            ]);
            $path = $result['path'];
            $size = $result['compressed_size'] ?? $file->getSize();
        } else {
            $path = BrandIsolation::storeInDirectory($file, $directory);
            $size = $file->getSize();
        }

        DealDocument::create([
            'deal_id' => $party->deal_id,
            'listing_id' => $listing->id,
            'deal_party_id' => $party->id,
            'document_category' => $party->party_type,
            'document_type' => $docType,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => (int) $size,
            'mime_type' => $mime,
            'uploaded_by' => auth()->id() ?? $listing->agent_id,
        ]);
    }

    /**
     * Copy (not share) the owner's ID / passport / visa files, so deleting a deal document
     * never removes the owner's file and each path keeps a single owning record.
     */
    private static function copyOwnerDocuments(Listing $listing, DealParty $party, ?Owner $owner): void
    {
        if (! $owner) {
            return;
        }

        $map = [
            'id_front_path' => 'national_id',
            'id_back_path' => 'national_id',
            'passport_copy_path' => 'passport',
            'visa_copy_path' => 'visa',
        ];

        $uploader = auth()->id() ?? $listing->agent_id;
        if (! $uploader) {
            return; // deal_documents.uploaded_by is required
        }

        $logicalDir = self::documentDirectory($listing, $party->party_type);

        foreach ($map as $column => $docType) {
            $source = $owner->{$column} ?? null;
            if (! $source) {
                continue;
            }

            try {
                $sourceDisk = BrandStorage::diskHolding($source);
                if (! $sourceDisk) {
                    continue;
                }

                $directory = BrandIsolation::ensureCompanyStoragePath($logicalDir);
                $targetDisk = BrandStorage::diskForNewUpload($logicalDir);
                $target = $directory.'/'.Str::random(32).'.'.pathinfo($source, PATHINFO_EXTENSION);

                $contents = Storage::disk($sourceDisk)->get($source);
                if ($contents === null) {
                    continue;
                }
                Storage::disk($targetDisk)->put($target, $contents);

                DealDocument::create([
                    'deal_id' => $party->deal_id,
                    'listing_id' => $listing->id,
                    'deal_party_id' => $party->id,
                    'document_category' => $party->party_type,
                    'document_type' => $docType,
                    'file_name' => basename($source),
                    'file_path' => $target,
                    'file_size' => strlen($contents),
                    'mime_type' => Storage::disk($targetDisk)->mimeType($target) ?: 'application/octet-stream',
                    'uploaded_by' => $uploader,
                ]);
            } catch (\Throwable $e) {
                Log::warning('ListingSellerParty: owner document copy failed', [
                    'listing_id' => $listing->id,
                    'column' => $column,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private static function deleteDocumentFile(DealDocument $doc): void
    {
        try {
            if ($doc->file_path && BrandStorage::diskHolding($doc->file_path) !== null) {
                BrandIsolation::deletePublicPath($doc->file_path);
            }
        } catch (\Throwable $e) {
            Log::warning('ListingSellerParty: file delete failed', ['doc_id' => $doc->id, 'error' => $e->getMessage()]);
        }
    }
}
