# Lead Search Optimization Report — Phase 2

**Date:** 2026-11-12  
**Scope:** Phase 2 only (lead search). No production deploy. No production migration.  
**MySQL:** 8.0.40 (`ngram_token_size=2`, `innodb_ft_min_token_size=3`)

---

## 1. Original search implementation

### Shared applicator
`app/Services/LeadTextSearch.php` is used by:

| Surface | Entry | Options |
|---------|-------|---------|
| Kanban board | `StageController::getStagesWithLeads` | lean, relations, admin, comments=false |
| Kanban load-more | `StageController` more-leads | same |
| Lead Pool | `LeadController::index` | lean, relations, admin by role |
| Smart Search / Cmd+K | `SmartSearchService` | lean + role-gated admin; email equality path separate |
| Clients autocomplete | `LeadController::getClientsList` | **was** ad-hoc LIKE; **now** LeadTextSearch lean |

### Supported behaviours (preserved)

| Kind | Gate | Fields / behaviour |
|------|------|--------------------|
| Phone-shaped | ≥4 digits, only `[\d\s+\-()]` | `work_phone_digits` exact/prefix **plus** legacy `%digits%` on `work_phone`, `work_phone_2`, `whatsapp_number`, `lead_number`, `source_client_phone`; UAE 0↔971 variants |
| Email-shaped | contains `@`, length ≥3 | `email`, `secondary_email`, `source_client_email` |
| Admin text | length ≥2 | Identity: name/number/email/source (+ phones in legacy LIKE path); optional relations (responsible user, property type, stage, integration); website/portal/whatsapp source expansions; non-lean extra CRM fields |
| Non-admin text | length ≥2 | Historically: `lead_name` (optional), `first_name`, `lead_source` (+ optional propertyType/comments when not lean) |
| Visibility | unchanged | Applied by callers (kanban scope, pool branch scope, SmartSearch `constrainLeads`, clients `added_by`/`responsible_person_id`) |

### Frontend (before → after)

| Surface | Debounce | Abort / stale |
|---------|----------|---------------|
| Navbar kanban search | 120 → **220 ms** | fingerprint dedupe; live filter immediate; board fetch via `fetchLeads(true, …)` |
| Smart Search | 160 → **200 ms** | AbortController + request key |
| `leads.vue` secondary | 300 → **200 ms** | AbortController on board fetch |
| Lead Pool | relies on navbar | AbortController |

Removed noisy `console.log` on every navbar keystroke.

---

## 2. Root cause of slow searches

1. **Leading-wildcard multi-column OR**  
   `lead_name LIKE '%term%' OR first_name LIKE …` cannot use BTREE indexes (`leads_lead_name_index`, etc.).
2. **Visibility OR + text OR** compounds scans under branch/role scopes.
3. **Historical evidence** (larger local corpus): kanban `search=ahmed` **~3.6–4.0 s** avg (`LEAD_SEARCH_OPTIMIZATION_REPORT.md` / Phase 0). Current local DB (~29–36 leads) does **not** reproduce that latency.

Phone search was already largely fixed via `work_phone_digits`.

---

## 3. Strategy evaluation (FULLTEXT vs alternatives)

| Approach | Preserves `%term%` contains? | Notes |
|----------|------------------------------|-------|
| More BTREE indexes | **No** | Already present; useless for leading `%` |
| Prefix-only `term%` | **No** | Faster but drops mid-string (e.g. `hass` → Hassan) |
| Standard FULLTEXT + `term*` | **Partial** | Prefix of tokens; weak mid-token / Arabic |
| **Ngram FULLTEXT** (`WITH PARSER ngram`) | **Yes** (2+ chars) | Verified: `ahme`, `hass`, Arabic `محمد` |
| External search engine | Yes | Out of scope; needs Redis/ops approval |

**Decision:** Hybrid  
- Identity contains → **ngram FULLTEXT** when indexes exist (`LEAD_SEARCH_USE_FULLTEXT`, default true)  
- Else → legacy LIKE  
- Phone → unchanged digits + legacy secondary  
- Email → equality + prefix + contains  
- Non-admin → FULLTEXT **AND** historical column LIKE filter (so email-only hits do not leak)  
- Relations / non-lean fields → unchanged LIKE / subqueries  

Standard FULLTEXT alone was **rejected** because it would not preserve contains semantics.

---

## 4. Exact SQL before and after

### Before (admin lean identity)

```sql
WHERE (
  lead_name LIKE '%ahme%'
  OR lead_number LIKE '%ahme%'
  OR first_name LIKE '%ahme%'
  OR last_name LIKE '%ahme%'
  OR email LIKE '%ahme%'
  OR work_phone LIKE '%ahme%'
  OR work_phone_2 LIKE '%ahme%'
  OR lead_source LIKE '%ahme%'
  -- optional responsible_person_id IN (users LIKE ...)
)
```

EXPLAIN (local): `type=ALL`, `key=NULL`.

### After (admin lean identity, FULLTEXT on)

```sql
WHERE (
  leads.id IN (
    SELECT id FROM leads
    WHERE MATCH(lead_name) AGAINST (? IN BOOLEAN MODE)
  )
  OR leads.id IN (
    SELECT id FROM leads
    WHERE MATCH(first_name, last_name, email, lead_source, lead_number)
          AGAINST (? IN BOOLEAN MODE)
  )
  -- optional responsible_person_id IN (...)
)
```

EXPLAIN (local, subquery rows): `type=fulltext`, keys `leads_lead_name_ngram_ft` / `leads_identity_ngram_ft`.

### Why two indexes

`lead_name` is `utf8mb4_general_ci`; other identity columns are `utf8mb4_unicode_ci`. MySQL forbids mixed collations in one FULLTEXT index. Split avoids rewriting `lead_name` collation.

A single `OR MATCH(...) OR MATCH(...)` in one WHERE caused `type=ALL` on this server; **separate `whereIn` subqueries** restore `type=fulltext` per branch.

---

## 5. Indexes introduced and why

Migration: `database/migrations/2026_11_12_210000_add_leads_identity_ngram_fulltext_index.php`

| Index | Columns | Why |
|-------|---------|-----|
| `leads_lead_name_ngram_ft` | `lead_name` | Ngram contains on display name (general_ci) |
| `leads_identity_ngram_ft` | `first_name`, `last_name`, `email`, `lead_source`, `lead_number` | Ngram contains on remaining identity fields (unicode_ci) |

**Not duplicated:** existing BTREE name/email/phone indexes kept (still used for equality/prefix/filters).  
**Rollback:** `php artisan migrate:rollback --path=database/migrations/2026_11_12_210000_add_leads_identity_ngram_fulltext_index.php` (drops both FULLTEXT indexes).

**Local status:** migration **ran on the local/dev database** for verification only. **Do not run on production** until staging EXPLAIN + timing + correctness sign-off.

---

## 6. Files changed

| File | Change |
|------|--------|
| `app/Services/LeadTextSearch.php` | Hybrid FULLTEXT/LIKE, email path, FT detection, txn-safe test fallback |
| `config/lead_search.php` | **New** feature flags / index names / MATCH groups |
| `database/migrations/2026_11_12_210000_add_leads_identity_ngram_fulltext_index.php` | **New** ngram FULLTEXT indexes |
| `app/Http/Controllers/Api/LeadController.php` | `getClientsList` → LeadTextSearch |
| `resources/js/components/layout/navbar/index.vue` | 220 ms debounce; remove debug log |
| `resources/js/components/search/SmartSearchDialog.vue` | 200 ms debounce |
| `resources/js/components/kanban/leadList/leads.vue` | 200 ms secondary debounce |
| `.env.example` | `LEAD_SEARCH_USE_FULLTEXT` docs |
| `tests/Feature/Lead/LeadTextSearchFulltextTest.php` | **New** correctness / SQL tests |
| `docs/LEAD_SEARCH_OPTIMIZATION_REPORT.md` | This report |

---

## 7. Tests performed

```bash
php artisan test --filter='LeadPhoneSearchTest|LeadTextSearchFulltextTest'
# 8 passed (62 assertions)

npm run build
# success
```

Coverage includes:

- Phone legacy parity + digits index EXPLAIN  
- FULLTEXT vs LIKE identity parity (committed inserts; English partials + Arabic)  
- Non-admin must not return email-only FULLTEXT hits  
- LIKE fallback when `LEAD_SEARCH_USE_FULLTEXT=false`  
- MATCH SQL when FULLTEXT enabled outside a transaction  

---

## 8. Before / after benchmarks

### Local tiny DB (~29–36 leads) — shape evidence only

| Query | LIKE EXPLAIN | FULLTEXT EXPLAIN |
|-------|--------------|------------------|
| Identity `ahme` | `type=ALL` | subquery `type=fulltext` on both indexes |
| Phone `0501234567` | digits index (unchanged) | unchanged phone path |

Wall times on this dataset are sub‑10 ms for both paths and **are not production-representative**.

### Historical (pre-Phase-2, larger local corpus)

| Call | avg | p95 |
|------|----:|----:|
| kanban `search=ahmed` | **3623–3984 ms** | **3820–4778 ms** |

### Required next measurement (staging / production-sized clone)

Measure with realistic lead volume:

- Common / rare names, partials, Arabic + English  
- Phones with/without country code  
- Emails, short terms (2–3 chars)  
- p50 / p95 / p99, SQL time, query count, payload size, correctness vs LIKE golden set  

**Target:** typical search API &lt; 300 ms where infrastructure permits.

---

## 9. Search correctness comparisons

| Case | Result |
|------|--------|
| `ahme` / `Ahmed` / `hass` mid-token | FULLTEXT ⊇ LIKE identity set (test) |
| Arabic `محمد` | Matched under ngram (test fixture) |
| Non-admin + email-only token | **Excluded** (column filter) |
| Phone frozen cases | Unchanged (`LeadPhoneSearchTest`) |
| `LEAD_SEARCH_USE_FULLTEXT=false` | Legacy LIKE SQL |

**Known nuance:** Admin lean FULLTEXT path no longer ORs `work_phone` / `work_phone_2` for **non-phone-shaped** text. Pure digit searches (≥4) still use the phone path. Toggle LIKE via env if a rare alphanumeric phone-text case is required.

**InnoDB note:** FULLTEXT is not visible for rows inserted inside an open transaction until commit. HTTP search is unaffected; unit tests using `DatabaseTransactions` automatically fall back to LIKE.

---

## 10. Deployment and rollback

### Deploy (staging first)

1. Backup / snapshot DB.  
2. On **staging clone**:  
   `php artisan migrate --path=database/migrations/2026_11_12_210000_add_leads_identity_ngram_fulltext_index.php`  
3. Expect table rebuild time proportional to `leads` size (maintenance window on large tables).  
4. `php artisan config:clear` (or cache rebuild) so `lead_search` config loads.  
5. Run `LeadPhoneSearchTest` + `LeadTextSearchFulltextTest` + manual kanban/Smart Search checks.  
6. Capture EXPLAIN + p95 on production-like data.  
7. Only then schedule production migrate with approval.

### Feature flag

- `LEAD_SEARCH_USE_FULLTEXT=true` (default): use ngram when indexes exist  
- `LEAD_SEARCH_USE_FULLTEXT=false`: force legacy LIKE without dropping indexes  

### Rollback

1. Set `LEAD_SEARCH_USE_FULLTEXT=false` (immediate behavioural rollback).  
2. Optionally:  
   `php artisan migrate:rollback --path=database/migrations/2026_11_12_210000_add_leads_identity_ngram_fulltext_index.php`  
3. Redeploy previous app build if code rollback is required.

---

## 11. Remaining bottlenecks (out of Phase 2)

- Lead open fan-out / assignment history / `visibleEngagement` (Phase 3)  
- Kanban N-stage ID queries  
- Studio / SAC residual cost (partially addressed in Phase 1)  
- Deals list payload / map pins  
- File cache + database queue under concurrency  
- Production-sized search p95 still **unverified** on staging clone  

---

**STOP:** Phase 2 complete. Awaiting approval before Phase 3 (Lead Opening).
