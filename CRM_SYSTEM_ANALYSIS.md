# CRM System Analysis

## Project Overview

This project is a **Laravel 11 + Vue 3 monolithic CRM** with:
- Backend API under `routes/api.php` (504 API routes currently registered).
- SPA frontend under `resources/js` (Vue Router + Axios).
- MySQL database (`oiapr_listing`) with 118 tables.
- JWT-based API authentication (`tymon/jwt-auth`) and RBAC via Spatie Permission.
- Real-time events/notifications through Laravel Echo + Pusher.

Primary business domains implemented:
- Leads pipeline (kanban, assignment, scoring/intelligence).
- Deals pipeline (primary/secondary/rental).
- Listings inventory and request workflows.
- User/role/permission management.
- HR modules (employees, attendance, leaves, recruitment, assets, announcements, document requests).
- Sales intelligence + AI sales intelligence.

---

## Full System Architecture

## Backend structure
- Core framework: `laravel/framework` v11.
- API layer:
  - Routes: `routes/api.php`.
  - Controllers: `app/Http/Controllers/Api/**`.
  - Validation: `app/Http/Requests/**`.
  - Resources/serialization: `app/Http/Resources/**`.
  - Middleware aliases in `bootstrap/app.php`:
    - `jwt.auth` -> `App\Http\Middleware\JwtAuthMiddleware`
    - `role`, `permission`, `role_or_permission` (Spatie).
- Domain layer:
  - Models: `app/Models/**` (99 models).
  - Services: `app/Services/**` (lead assignment, mobile kanban, AI scoring, geocoding, etc.).
  - Jobs + scheduler in `bootstrap/app.php` (lead scoring/assignment/escalation, attendance sync, notifications).
- Real-time:
  - Echo auth route in `routes/web.php` (`/broadcasting/auth`, `auth:api`).
  - Channels (user/lead/listing/assignment channels).

## Frontend structure
- SPA entry: `resources/js/main.js`.
- Router: `resources/js/router.js` (route guards based on JWT + role checks from local storage).
- API client: `resources/js/plugins/axios.js`.
  - Auto injects `Authorization: Bearer <token>`.
  - Handles 401 -> clears session + redirects to `/sign-in`.
- UI modules/pages:
  - Dashboard, Kanban, Listings, Users, Roles, Settings.
  - HR (`resources/js/pages/hr/**`).
  - Sales intelligence (`resources/js/pages/sales-intelligence/**`).

## Frontend <-> Backend Communication
- Base URL from `VITE_API_BASE_URL` (`resources/js/plugins/axios.js`).
- Communication style:
  - JSON REST for most endpoints.
  - Multipart/form-data for uploads (listings/deals/docs/images).
  - JWT in Authorization header.
  - API responses mostly wrapped via `ApiResponse`:
    - Success: `{ status: true, message, data, meta? }`
    - Error: `{ status: false, message, errors? }`
- Real-time update channel via Echo/Pusher for notifications and lead/kanban updates.

---

## Backend Technology and Database Structure

## Technology stack
- PHP `^8.3`.
- Laravel `^11.31`.
- MySQL 8.
- JWT auth (`tymon/jwt-auth`).
- RBAC (`spatie/laravel-permission`).
- Activity logs (`spatie/laravel-activitylog`).
- Vue 3 + Vite + Axios frontend.

## Database structure summary
- Database: `oiapr_listing`.
- Tables: 118.
- Core auth/RBAC tables:
  - `users`, `roles`, `permissions`, `model_has_roles`, `role_has_permissions`.
- CRM core:
  - Leads: `leads`, `lead_activities`, `lead_comments`, `lead_histories`, `lead_assignment_*`, `stages`.
  - Deals: `deals`, `deal_activities`, `deal_comments`, `deal_parties`, `deal_properties`, `deal_documents`.
  - Listings: `listings`, `listing_access_requests`, `listing_comments`, `listing_additional_documents`, `property_offers`.
  - Master data: `areas`, `property_types`, `owners`, `developers`, `projects`, `features`, `unit_views`, `layout_types`, `sources`.

## Core model relationships
- `Lead` belongs to `Stage`, `User` (responsible/creator), `Area`, `PropertyType`; has many `LeadActivity`, `LeadComment`, `LeadHistory`.
- `Deal` belongs to `Lead`, `Stage`, `Listing`, `User` (responsible/creator/updater); has many parties/properties/comments/activities/documents.
- `Listing` belongs to `PropertyType`, `Area`, `Project`, `Owner`, `Developer`, `User` (agent/added_by); has many comments/access requests/offers/internal updates.
- `User` self-hierarchy via `parent_id` and `children()`, plus roles/permissions.

---

## Authentication Mechanism

## Login endpoint
- **Endpoint:** `POST /api/auth/login`
- **Validation (`LoginRequest`):**
  - `email` required.
  - `password` required.
  - `latitude`, `longitude` required in non-local env, optional in local.

## Token type
- **JWT Bearer token** (`auth guard = api`, driver `jwt` in `config/auth.php`).
- Token issued in `AuthController@login` via `auth()->login($user)`.

## Refresh token availability
- No backend refresh-token endpoint found (`/auth/refresh` not implemented).
- No JWT refresh flow implemented in API routes or backend controller.
- Frontend clears `refreshToken` key from localStorage, but it is not backed by server API.

## User session handling
- Stateless API session with JWT.
- Frontend stores token in localStorage/sessionStorage (`setAuthToken`).
- Axios adds Bearer header automatically.
- On token invalid/expired (401), frontend forces re-login.
- Logout endpoint: `POST /api/auth/logout` (invalidates token via `auth()->logout()`).

## Example login response
```json
{
  "status": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "User Name",
      "roles": ["super_admin"],
      "permissions": ["leads-list", "leads-edit"]
    },
    "token": "jwt_token_here"
  }
}
```

---

## User Roles and Permissions

RBAC is implemented with Spatie Permission:
- Role APIs: `/api/roles`
- Permission catalog API: `GET /api/permissions`
- User permissions API: `GET /api/users/{user}/permissions`

Observed core roles in app logic:
- `super_admin`
- `admin`
- `manager`
- `team_lead`
- `sales`
- (legacy/other role names also appear in code paths)

Permission checks are enforced:
- At route/controller middleware level (`permission:*`).
- In business logic (`$user->can(...)`, `canViewLead`, hierarchy checks).

---

## Available APIs (Inventory)

Route inventory generated from Laravel route list:
- **Total API routes:** 504
- Largest route groups:
  - `listings` (110)
  - `leads` (52)
  - `deals` (30)
  - `users` (20)
  - `attendance` (16)
  - `leaves` (16)
  - `recruitment` (15)

## Key auth & identity endpoints
- `POST /api/auth/login`
- `POST /api/auth/register`
- `POST /api/auth/logout`
- `POST /api/auth/forgot-password`
- `POST /api/auth/reset-password`
- `GET /api/profile`
- `PUT /api/profile`
- `GET /api/users`
- `GET /api/users/{user}/permissions`
- `GET /api/permissions`

## CRM core endpoints (high-value)
- Leads:
  - `GET /api/leads`
  - `POST /api/leads`
  - `GET /api/leads/{lead}`
  - `PUT/PATCH /api/leads/{lead}`
  - `POST /api/leads/{lead}/change-stage`
  - `POST /api/leads/{lead}/assign-responsible-person`
  - `POST /api/leads/convert/to-deal`
  - `GET /api/leads/{leadId}/activities`
  - `GET /api/leads/{leadId}/comments`
- Deals:
  - `GET /api/deals`
  - `GET /api/deals/grouped-by-stage`
  - `GET /api/deals/{deal}`
  - `PUT /api/deals/{deal}`
  - `POST /api/deals/{id}/change-stage`
  - `POST /api/deals/check-stage-requirements`
- Listings:
  - `GET /api/listings/properties`
  - `POST /api/listings/properties`
  - `GET /api/listings/properties/{property}`
  - `PUT/PATCH /api/listings/properties/{property}`
  - `GET /api/listings/properties/map`
  - `GET /api/listings/matching`
  - `GET /api/listings/pending-approvals`
  - `PATCH /api/listings/properties/{listing}/approve`
  - `POST /api/listings/access-requests/{listing}/request`

> Full route inventory is defined in `routes/api.php` and validated against `php artisan route:list --path=api --except-vendor`.

---

## CRM Modules Analysis

## 1) Leads
- **Purpose:** Lead intake, qualification, assignment, stage progression, conversion to deals.
- **DB entities:** `leads`, `lead_activities`, `lead_comments`, `lead_histories`, `lead_participants`, `lead_observers`, `lead_assignment_logs`, `lead_scoring_settings`.
- **Fields (core):**
  - Identity: `lead_name`, `lead_number`, `work_phone`, `email`.
  - Pipeline: `stage_id`, `responsible_person_id`, `last_stage_change_at`.
  - Intelligence: `score`, `priority`, `intent`, `next_action`, `score_breakdown`.
  - Conversion: `converted_to_deal_id`, `converted_at`.
- **Relationships:** Lead -> Stage/User/Area/PropertyType; has many activities/comments/history.
- **Permissions:** `leads-list`, `leads-create`, `leads-edit`, `leads-delete` (plus hierarchy checks).
- **API endpoints:** `/api/leads*`, `/api/lead-assignment*`, `/api/scoring-settings*`.

## 2) Deals (Opportunities equivalent)
- **Purpose:** Sales transaction lifecycle after lead qualification.
- **DB entities:** `deals`, `deal_activities`, `deal_comments`, `deal_parties`, `deal_documents`, `deal_properties`.
- **Fields (core):**
  - `deal_number`, `deal_type` (primary/secondary/rental), `stage_id`, `status`.
  - Financial: `deal_total_amount`, `deal_commission`, `agent_share`, `company_share`.
  - Links: `lead_id`, `listing_id`, `responsible_person_id`.
- **Relationships:** Deal belongs to Lead/Stage/Listing/User; has many parties/properties/activities/comments.
- **Permissions:** deal operations are role/permission + hierarchy controlled.
- **API endpoints:** `/api/deals*`.

## 3) Listings
- **Purpose:** Property inventory management and request workflows.
- **DB entities:** `listings`, `listing_access_requests`, `listing_comments`, `listing_additional_documents`, `property_offers`, `hot_deal_requests`.
- **Fields (core):**
  - Inventory: `reference_number`, `unit_number`, `listing_status`, `status`, `approved`.
  - Commercial: `price`, `owner_id`, `agent_id`, `developer_id`.
  - Geo/meta: `area_id`, `project_id`, `latitude`, `longitude`.
- **Relationships:** Listing belongs to owner/agent/area/project/property type/developer; has many requests/comments/offers.
- **Permissions:** listing edit/approval/access-request flow depends on role + ownership + listing-team logic.
- **API endpoints:** `/api/listings/*`, `/api/search-alerts*`.

## 4) Activities (Tasks equivalent)
- **Purpose:** Follow-up reminders and action tracking.
- **DB entities:** `lead_activities`, `deal_activities`.
- **Fields:** `title`, `reminder_date`, `is_completed`, `reminders`, `next_reminder_at`.
- **Relationships:** belongs to lead/deal and user.
- **APIs:** `/api/leads/{id}/activities`, `/api/deals/{id}/activities`, activity CRUD/toggle endpoints.

## 5) Contacts
- **Status:** No dedicated `contacts` module/table found.
- **Current implementation:** contact-like data is embedded in:
  - `leads` (person/contact fields).
  - `deal_parties` (buyers/sellers/tenants/landlords/clients).
  - `owners` (property owners).

## 6) Accounts
- **Status:** No dedicated `accounts` module/table found.
- **Current implementation:** company/account context exists as plain fields (`company_name` in leads, developer/project/owner entities) but no separate account domain.

## 7) Opportunities
- Implemented as **Deals module** (`deals` pipeline).

## 8) Additional modules found
- User & access management (`users`, `roles`, `permissions`, invitations).
- Dashboard analytics.
- Suggestions.
- Chat/messaging.
- Integrations (Meta/Facebook webhooks, website leads, Bitrix24 sync).
- HR suite: employees, attendance, leaves, recruitment, assets, announcements, document requests.
- Sales Intelligence + AI Sales Intelligence.

---

## Required Endpoints Requested in Task

## Which endpoint returns list of modules?
- **No backend API endpoint currently returns a canonical "module list".**
- Module navigation is assembled frontend-side in:
  - `resources/js/composables/useLayoutNavigation.js`
  - `resources/js/data/systemOverviewModules.js`

## Which endpoint returns user permissions?
- `GET /api/users/{user}/permissions` (effective user permissions).
- Supporting global catalog: `GET /api/permissions`.

## Which endpoint handles login?
- `POST /api/auth/login`.

## Which APIs are needed for mobile application?
Already present (minimum):
- Auth:
  - `POST /api/auth/login`
  - `POST /api/auth/logout`
  - `GET /api/profile`
- Mobile-specific CRM:
  - `GET /api/v1/mobile/kanban`
  - `POST /api/v1/mobile/leads/{lead}/move`
- Supporting:
  - `GET /api/available-responsible-persons`
  - `GET /api/stages*` (if mobile needs standalone stage metadata)
  - notifications/profile endpoints as needed.

---

## API Parameter Notes (selected core endpoints)

- `POST /api/auth/login`:
  - Required: `email`, `password`
  - Required in non-local env: `latitude`, `longitude`
- `POST /api/leads` (`LeadRequest`):
  - Required: `lead_name`, `first_name`, `stage_id`, `work_phone`, `lead_source`, `responsible_person_id`
- `POST /api/listings/properties` (`ListingRequest`):
  - Required: `unit_number`, `price`, `property_type_id`, `project_id`, `owner_id`, `agent_id`
  - Conditional: gallery/floor-plan rules on publish.
- `PUT /api/deals/{deal}` (`UpdateDealRequest`):
  - Optional but validated fields for stage/status/amount/responsible/listing/properties/docs.

---

## Mobile App Readiness Summary

## 1) What we need to build the mobile app
- Stable auth/session model for mobile (JWT lifecycle design).
- Mobile-first API contract for:
  - lead list/kanban,
  - lead details,
  - lead comments/activities CRUD,
  - notifications,
  - profile/settings.
- Pagination/filter/search strategy for low bandwidth.
- Clear offline/poor-network behavior strategy (sync/retry/conflict).

## 2) Recommended mobile architecture
- **Client:** feature-based architecture (Auth, Leads, Deals, Listings, Profile).
- **Data layer:** repository pattern + DTO mapping to API responses.
- **Networking:** one HTTP client with auth interceptor and standardized error mapping.
- **State:** normalized store per module (stages/leads/comments).
- **Sync strategy:** optimistic updates for stage moves using idempotency/conflict checks (`expected_updated_at`, `Idempotency-Key` pattern already exists in mobile lead move service).

## 3) Missing APIs for strong mobile support
- No endpoint to return dynamic module/menu capabilities in one payload.
- No refresh token endpoint/flow (token expiry UX risk on mobile).
- No dedicated compact endpoints for:
  - lead detail bundle (lead + comments + activities + permissions in one call),
  - notifications unread counters + batched mark/read by type.
- Contacts/accounts are not first-class modules (if mobile scope includes them, new APIs/domain needed).

---

## Final Assessment

This CRM is production-scale and feature-rich, with strong domain coverage around **Leads, Deals (Opportunities), Listings, and HR extensions**.  
For mobile, the project already includes initial mobile APIs (`/api/v1/mobile/kanban`, `/api/v1/mobile/leads/{lead}/move`), but additional API consolidation and token lifecycle improvements are recommended before full mobile rollout.

