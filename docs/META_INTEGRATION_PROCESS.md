# Meta (Facebook) Integration – Process Overview

This document describes how Meta (Facebook Lead Ads) was linked to your CRM so you can connect forms and see them in the Integration tab.

---

## 1. Database (Laravel)

**Migration: `integrations` table**

- **File:** `database/migrations/2026_02_17_120000_create_integrations_table.php`
- **Purpose:** Store each connected Meta form.
- **Columns:**
  - `user_id` – who connected it
  - `form_id` – Meta form ID
  - `form_name` – form name from Meta
  - `meta_account_id` – Facebook Page ID
  - `access_token` – encrypted (Laravel `encrypted` cast)
  - `meta_app_id` – optional
  - `platform` – e.g. `meta`
  - `active` – on/off
  - `created_at` / `updated_at`

**Run once:**  
`php artisan migrate`

---

## 2. Backend API (Laravel)

**Model**

- **File:** `app/Models/Integration.php`
- **Role:** Eloquent model for `integrations` table; `access_token` is cast to `encrypted` and hidden in JSON.

**Controller**

- **File:** `app/Http/Controllers/Api/IntegrationController.php`
- **Actions:**

| Method | Route | Purpose |
|--------|--------|--------|
| GET    | `GET /api/integrations` | List current user’s integrations (for the Integration table). |
| POST   | `POST /api/integrations/meta/forms` or `POST /api/integrations/meta` | Fetch Meta forms: sends `access_token` + `meta_account_id` (+ optional `cursor`) to Meta Graph API and returns forms. |
| POST   | `POST /api/integrations` | Save a new integration (form_id, form_name, meta_account_id, access_token, optional meta_app_id). |
| PATCH  | `PATCH /api/integrations/{id}/toggle-active` | Toggle integration active. |
| DELETE | `DELETE /api/integrations/{id}` | Remove an integration. |

**Meta API used**

- **Endpoint:** `https://graph.facebook.com/v17.0/{page_id}/leadgen_forms?access_token=...`
- **Important:** `meta_account_id` must be a **Facebook Page ID** (numeric), not an Ad Account ID (`act_xxx`).
- Pagination is supported via `cursor` / `after` when Meta returns multiple pages.

**Routes**

- **File:** `routes/api.php` (inside `jwt.auth` middleware)
- All integration routes are under the same auth group so only logged-in users can call them.

---

## 3. Frontend (Vue) – Where You “Link” Meta

**Where you connect Meta**

1. Open **Kanban** (e.g. `/kanban`).
2. Switch to the **Integration** tab (“CRM Forms”).
3. Click **Create Integration** (or “Add CRM Form”).
4. In the modal, open the **“Facebook Lead Ads”** tab.
5. Enter:
   - **Meta App ID** (optional for fetch)
   - **Meta App Secret** (optional)
   - **Access Token** (required – from Meta)
   - **Meta Account / Page ID** (required – your **Page ID**, not Ad Account).
6. Click **“Fetch forms”** → the app calls `POST /api/integrations/meta/forms` (or `/api/integrations/meta`) with token + page ID; backend calls Meta and returns the list.
7. In the list, **select one form** and click **“Connect”** → the app calls `POST /api/integrations`; backend saves one row in `integrations` (form_id, form_name, meta_account_id, access_token, etc.).
8. Modal closes and the Integration table refreshes (via `integrationRef.loadIntegrations()`).

**Files involved**

- **FacebookLeadAdsTab.vue** – Form with token/page ID, “Fetch forms”, list of forms, “Connect” button. Calls:
  - `POST /integrations/meta/forms` (or `/integrations/meta`) to fetch forms.
  - `POST /integrations` to save the selected form.
- **CreateIntegrationModal.vue** – Contains the tabs; listens for `@connected` from Facebook Lead Ads tab, then emits `integration-created` and closes.
- **Integration.vue** – Main Integration/CRM Forms page: loads list with `GET /integrations`, shows table, toggle active (PATCH), delete (DELETE). Exposes `loadIntegrations()` for refresh.
- **Kanban.vue** – Shows Integration tab, opens Create Integration modal; on `integration-created` calls `integrationRef.loadIntegrations()` and shows success message.

**Fixes applied along the way**

- **Pagination param fix:** “Fetch forms” was passing the click event as the pagination `cursor`. Fixed by calling `fetchForms()` with no args on button click and only sending `cursor` when it’s a string (for “Load more forms”).
- **Wrong URL 404:** Some requests went to `/api/integrations/meta` instead of `/api/integrations/meta/forms`. Added an alias route so `POST /api/integrations/meta` uses the same controller action.
- **Integrations table missing:** User ran `php artisan migrate` to create the `integrations` table.
- **Encrypted cast:** Model uses `'encrypted'` cast for `access_token` (string) so the token is stored encrypted.

---

## 4. End-to-End Flow (How Meta Is “Linked”)

```
You (in UI)                          Frontend (Vue)                    Backend (Laravel)                 Meta
─────────────────────────────────────────────────────────────────────────────────────────────────────────────
1. Open Integration tab              Integration.vue loads             GET /api/integrations             –
   and Create Integration            list (empty or existing)          → IntegrationController::index
                                                                        → reads `integrations` table

2. Go to “Facebook Lead Ads”          FacebookLeadAdsTab                –
   tab, enter Token + Page ID        shows form

3. Click “Fetch forms”                POST /integrations/meta/forms     IntegrationController::            GET .../page_id/leadgen_forms
                                      body: access_token,                fetchMetaForms                    → returns list of forms
                                       meta_account_id                   → Http::get(Meta API)
                                                                         → returns { forms, next_cursor }

4. See list of forms                 Renders list, you select one      –
   and select one

5. Click “Connect”                    POST /integrations                IntegrationController::store       –
                                      body: form_id, form_name,          → Integration::create(...)
                                       meta_account_id, access_token     → saves to `integrations` table

6. Modal closes, list refreshes       emit('connected')                 –
                                      → CreateIntegrationModal
                                      → Kanban: loadIntegrations()       GET /api/integrations
                                                                        → updated list (including new row)
```

So “linking Meta” = **saving one row per form** in `integrations` (with encrypted token and page ID), after fetching the form list from Meta’s API using that token and page ID.

---

## 5. Optional: .env

In `.env` / `.env.example` you can add (optional; token is often entered in the UI):

```env
META_APP_ID=
META_APP_SECRET=
```

---

## 6. Quick Checklist

- [ ] Migration run: `php artisan migrate` (creates `integrations` table).
- [ ] User is logged in (JWT) when opening Integration / Create Integration.
- [ ] Access Token is valid and has lead/forms permissions for the Page.
- [ ] Meta Account / Page ID is the **Page ID**, not Ad Account ID.
- [ ] “Fetch forms” calls `POST /api/integrations/meta/forms` or `POST /api/integrations/meta`; “Connect” calls `POST /api/integrations`.

If you want, we can add a one-page “User guide: How to connect a Meta form” (screens and steps only) in the same doc or a separate file.
