# Legacy sensitive files under public storage — read-only inventory

**Scope:** `storage/app/public` (and historic `/storage` URLs).
**Date:** 2026-11-13
**Branch:** `deploy/luxvant-release`
**Action taken:** none (no moves, renames, or URL breaks).

This inventory classifies **path categories** used by the CRM. Live disk volume counts vary by environment; do not treat this as a production file listing.

## Classification legend

| Class | Meaning |
|---|---|
| Intentionally public | Allowlisted marketing/branding assets; keep `/storage` URLs |
| Sensitive + currently public | Company documents that historically lived on the public disk |
| Unknown / owner decision | Ambiguous or schema-unreliable associations |

## Category table

| Category (relative prefix) | Classification | Owning record | Notes |
|---|---|---|---|
| `listings/gallery`, `listings/hero`, `listings/floor_plans` | Intentionally public | Listing / gallery rows | Keep public |
| `projects/` | Intentionally public | Project images | Keep public |
| `avatars/`, `users/avatars` | Intentionally public | User | Keep public (PII-light) |
| `developers/avatars`, `images/features`, `backgrounds/`, `system-campaigns/`, `Setting/`, `watermark/` | Intentionally public | Branding / CMS | Keep public |
| `deals/{id}/…` | Sensitive + currently public (legacy) | Deal / DealProperty JSON / DealDocument | New uploads → `company` disk |
| `comments/attachments/…` | Sensitive + currently public (legacy) | Lead or Deal comment attachment | Flat dir; **no path→owner without DB** |
| `owners/documents`, `owners/additional_documents`, `owners/avatars` | Sensitive + currently public (legacy) | Owner columns / OwnerAdditionalDocument | Path alone insufficient |
| `listings/documents`, `listings/additional_documents` | Sensitive + currently public (legacy) | Listing columns / ListingAdditionalDocument | Path alone insufficient |
| `employees/{userId}/…` | Sensitive + currently public (legacy) | EmployeeDocument | Path encodes user id |
| `attendance/{userId}/…` | Unknown / owner decision | **No DB owner** — `attendances` has no `attachment` column; `Attendance::$fillable` omits it | Controller still uploads to `attendance/{user_id}/` but path is not persisted. **Fail closed** until schema adds `attendances.attachment` (+ resource type) |
| `leave_attachments/{userId}/…` | Sensitive + currently public (legacy) | LeaveRequest.attachment | |
| `document_requests/{userId}/…` | Sensitive + currently public (legacy) | DocumentRequest | |
| `applicants/resumes/{jobId}/…` | Sensitive + currently public (legacy) | Applicant | |
| `marketing/references/…` | Sensitive + currently public (legacy) | MarketingTask.reference_image | Path alone insufficient |
| `offer-bases/…` | Sensitive (often `local` disk) | Listing id in path | Separate from BrandIsolation disk |

## Proposed migration plan (separate change — not executed)

1. **Inventory production** with a dry-run command: list sensitive prefixes on `public` disk, join to DB by path, report orphans.
2. **Copy** (not move) matched files to `storage/app/company` under the same relative path (Luxvant: ensure `luxvant/` prefix).
3. **Dual-read window:** `CompanyFileService::urlForLegacyPath` already prefers company disk when present; OIA keeps public URL if file still on public disk.
4. **Cutover per category** after API consumers use signed+JWT serve for sensitive URLs.
5. **Delete public copies** only after monitoring shows zero public hits for that prefix.
6. **Rollback:** restore public copies from backup / re-copy from company disk; revert URL generation to `asset('storage/…')` for OIA.

### Compatibility / rollback notes

- Never rewrite existing OIA public URLs in place without a dual-read window.
- Luxvant new uploads already go to private disk; legacy Luxvant public copies (if any) follow the same copy-then-cutover plan.
- Orphans (file on disk, no DB row) require owner decision before delete.

## Blocked for path-only private serve

- `attendance/*` until attachment column/model association is confirmed in all environments.
- Any sensitive file whose DB association cannot be resolved: **do not** issue a private URL (fail closed).
