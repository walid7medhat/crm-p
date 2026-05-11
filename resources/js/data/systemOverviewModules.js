/**
 * System Overview — reference content for internal documentation (Listings, Leads, Deals).
 * Endpoints reflect the app’s `routes/api.php` structure (prefix `/api`).
 */

export const moduleNav = [
  { id: 'listings', label: 'Listings', icon: 'lucide:building-2', color: 'amber' },
  { id: 'leads', label: 'Leads', icon: 'lucide:users', color: 'violet' },
  { id: 'deals', label: 'Deals', icon: 'lucide:handshake', color: 'emerald' },
]

export const demoSamples = {
  listing: { ref: '48291-3847AB', unit: '1204', area: 'Yas Island', price: '2,450,000 AED' },
  lead: { number: 'LD-2026-00412', name: 'Sara Al-Mansoori', stage: 'Qualification' },
  deal: { number: '48291-3847AB', type: 'Primary', stage: 'KYC / Documentation' },
}

function endpointsListings() {
  return [
    { method: 'GET', path: '/listings/properties', note: 'Paginated index (filters, cache)' },
    { method: 'GET', path: '/listings/properties/map', note: 'Map pins + coordinate resolution' },
    { method: 'POST', path: '/listings/properties', note: 'Create (multipart)' },
    { method: 'PUT', path: '/listings/properties/{id}', note: 'Update' },
    { method: 'GET', path: '/listings/matching', note: 'Smart match for lead context' },
    { method: 'POST', path: '/search-alerts', note: 'Saved search → notify' },
    { method: 'PATCH', path: '/listings/properties/{id}/approve', note: 'Manager approval' },
    { method: 'POST', path: '/listings/access-requests/{listing}/request', note: 'Owner / viewing request' },
  ]
}

function endpointsLeads() {
  return [
    { method: 'GET', path: '/leads', note: 'Index (grouped by stage in response)' },
    { method: 'POST', path: '/leads', note: 'Create' },
    { method: 'POST', path: '/leads/{lead}/change-stage', note: 'Move card' },
    { method: 'POST', path: '/leads/convert/to-deal', note: 'Convert → deal' },
    { method: 'GET', path: '/lead-assignment/settings', note: 'Assignment engine' },
    { method: 'POST', path: '/lead-assignment/run', note: 'Manual assign run' },
  ]
}

function endpointsDeals() {
  return [
    { method: 'GET', path: '/deals', note: 'Paginated deals + filters' },
    { method: 'GET', path: '/deals/grouped-by-stage', note: 'Kanban columns' },
    { method: 'POST', path: '/deals/check-stage-requirements', note: 'Gate before move' },
    { method: 'POST', path: '/deals/{id}/change-stage', note: 'Validated stage change' },
    { method: 'PUT', path: '/deals/{deal}', note: 'Full update + docs' },
    { method: 'POST', path: '/store/new', note: 'Lead → deal + parties (conversion)' },
  ]
}

export const modulesContent = {
  listings: {
    title: 'Listings',
    shortTitle: 'Listing Module',
    icon: 'lucide:building-2',
    badges: ['Approval', 'Automation', 'Maps'],
    overview:
      'Central inventory of properties (sale/rent): units tied to projects, areas, owners, and agents. Powers discovery (search, map), governance (draft/publish/approve), and collaboration (access requests, hot deals, comments).',
    features: [
      'CRUD listings with hero image, gallery (10+ on publish for non-plots), floor plans from project or uploads, SPA/desk/other docs.',
      'Unique unit number per area + listing_status; auto reference_number; plot types relax bedroom/gallery rules.',
      'Public catalog visibility gated by is_active, approved, archived, status (non-admin users see approved pipeline listings only).',
      'My listings scope for agents + two-level reporting chain.',
      'Property map: joins areas hierarchy + projects; coordinates via ListingMapCoordinateResolver (Nominatim geocode fallback, rate-limited).',
      'Smart matching: relaxing price bands and filters for lead/property alignment.',
      'Access requests: owner_data, unit_number, viewing — agent/delegate notifications, checkAccess, respond, convert, review.',
      'Hot deal requests: listing-team sales may require approval; managers approve → is_hot_deal + stamps.',
      'Search alerts: saved filters + job to email on first match.',
      'Statistics, batch listing approval, sold/rented/converted tracking, property offers generation.',
    ],
    workflows: [
      { title: 'Create & publish', body: 'Draft or publish; publish validates gallery/floor plans; new rows start unapproved until manager approves for org-wide list.' },
      { title: 'Approve', body: 'Listing-team manager or super_admin approves → visible in default index; notifications to creator and agent.' },
      { title: 'Request access', body: 'Agent requests owner phone or full data or viewing; handler approves; requester sees scoped fields.' },
      { title: 'Mark sold / rented', body: 'Status moves to converted/rented; optional external company + agent contact fields.' },
    ],
    uiActions: [
      'Create / edit / delete (permission + ownership rules).',
      'Assign agent, toggle archive, set hero, upload & remove gallery/floor plans/docs.',
      'Move on map, filter search, save “notify me” alert, request hot deal, respond to access requests.',
    ],
    dataFields: [
      { field: 'reference_number', type: 'string', desc: 'Auto-generated unique ref' },
      { field: 'title, unit_number', type: 'string', desc: 'Unit + display title' },
      { field: 'listing_status', type: 'sale | rent', desc: 'Sale vs rental inventory' },
      { field: 'status', type: 'string', desc: 'draft, published, converted, …' },
      { field: 'approved', type: 'bool', desc: 'Manager approval gate' },
      { field: 'price, area_id, project_id', type: 'money / FK', desc: 'Pricing & geography' },
      { field: 'agent_id, owner_id', type: 'FK', desc: 'Commercial parties' },
      { field: 'is_hot_deal', type: 'Yes | No', desc: 'Promotion flag + approval trail' },
    ],
    endpoints: endpointsListings(),
    specialLogic: [
      'Listing visibility stack for non-admins: active + approved + not archived + excludes draft/converted/rented as configured in index.',
      'JSON additional_features filter (maid, storage, …) in listing queries.',
      'HotDealNotifiable trait for approver pings.',
    ],
    highlights: ['Manager approval queue', 'OSM Nominatim map fallback', 'Listing-linked seller hiding in deals validation'],
  },
  leads: {
    title: 'Leads',
    shortTitle: 'Lead Module',
    icon: 'lucide:users',
    badges: ['AI', 'Automation', 'Kanban'],
    overview:
      'Top-of-funnel CRM: contacts linked to pipeline stages and a responsible person. Supports hierarchy visibility, intelligence scoring, auto-assignment, activities/comments, and conversion to deals.',
    features: [
      'Kanban stages (stage_type lead); change stage with history + broadcasts.',
      'Mandatory phone format; referral source unlocks referee fields.',
      'Lead intelligence job: weighted score, priority (hot/warm/cold), intent (keywords + optional OpenAI), next_action, score_breakdown JSON.',
      'Auto-assignment (queue): LeadAssignmentService with modes (realtime, simple, scheduled), logs, performance & attendance weights.',
      'Duplicate API: same work_phone as weak duplicate signal.',
      'Participants & observers on the lead record.',
      'Index visibility: super_admin all; managers team subtree; sales own + added.',
      'Revert to stage 1 after configurable hours in stage order 2 (KanbanSetting).',
    ],
    workflows: [
      { title: 'Create lead', body: 'Validated store → intelligence job + auto-assign job if enabled → LeadUpdated broadcast.' },
      { title: 'Move card', body: 'changeStage updates last_stage_change_at; history + Pusher.' },
      { title: 'Assign', body: 'Managers assign responsible person within subtree; clears revert marker where implemented.' },
      { title: 'Convert', body: 'POST /leads/convert/to-deal creates Deal + parties; lead gets converted_to_deal_id.' },
    ],
    uiActions: [
      'Create / edit / delete (Spatie permissions leads-*).',
      'Drag/move stages, assign responsible, add comments & activities with reminders.',
      'Import spreadsheet, view duplicate modal, open lead reports (super_admin dashboards).',
    ],
    dataFields: [
      { field: 'lead_number', type: 'string', desc: 'Unique identifier' },
      { field: 'stage_id', type: 'FK', desc: 'Kanban column' },
      { field: 'responsible_person_id', type: 'FK', desc: 'Owning agent/manager' },
      { field: 'lead_source', type: 'string', desc: 'Channel + referral extras' },
      { field: 'score, priority, intent', type: 'int / string', desc: 'Intelligence outputs' },
      { field: 'converted_to_deal_id', type: 'FK nullable', desc: 'After conversion' },
    ],
    endpoints: endpointsLeads(),
    specialLogic: [
      'ProcessLeadIntelligenceJob throttles rescoring via last_scored_at window.',
      'ProcessLeadAutoAssignmentJob respects LeadAssignmentSetting (disabled / simple / realtime).',
      'canViewLead gates show/update; observers not in canViewLead — product gap.',
    ],
    highlights: ['OpenAI intent when API key configured', 'Self-learning / SLA migrations in assignment engine'],
  },
  deals: {
    title: 'Deals',
    shortTitle: 'Deal Module',
    icon: 'lucide:handshake',
    badges: ['Stage gates', 'Compliance'],
    overview:
      'Post-qualification pipeline for primary, secondary, and rental transactions: financials, property linkage (including listing_id), parties (buyer/seller/tenant/landlord), documents, and strict stage validation before advancing.',
    features: [
      'Deal types: primary | secondary | rental with separate deal-stage rows (stage_type deal + deal_type).',
      'Creation from lead conversion (lead_id unique when present); optional rich store with multipart documents.',
      'DealStageValidator + service: required fields per stage; listing_id filters out seller (secondary) or landlord (rental) requirements when inventory carries that side.',
      'Parties with primary role per type; documents categorized by party/property.',
      'Activities & comments with attachments and mentions; history via lead_histories with deal_id.',
      'Visibility: visibleFor scope — managers see subtree responsibles; sales see own deals; hard-coded user exceptions in scope (legacy).',
    ],
    workflows: [
      { title: 'Convert from lead', body: 'Authorization check → deal stage → DealParty buyer/tenant → history + broadcast.' },
      { title: 'Advance stage', body: 'check-stage-requirements → change-stage or update-and-change-stage after filling KYC/financial fields.' },
      { title: 'Assign', body: 'Managers assign responsible person; DealUpdated assigned event.' },
      { title: 'Lost', body: 'lost_reason captured on updates / partial updates.' },
    ],
    uiActions: [
      'Kanban drag with modal for required fields (CompleteStageFields pattern).',
      'Upload documents (WebP compression for images), partial save per party buckets.',
      'Search deals (global query includes parties and linked lead).',
    ],
    dataFields: [
      { field: 'deal_number', type: 'string', desc: 'Often derived from lead' },
      { field: 'deal_type', type: 'enum', desc: 'primary | secondary | rental' },
      { field: 'stage_id', type: 'FK', desc: 'Deal pipeline' },
      { field: 'status', type: 'enum', desc: 'draft … cancelled' },
      { field: 'listing_id', type: 'FK nullable', desc: 'Inventory link' },
      { field: 'deal_total_amount', type: 'decimal', desc: 'Transaction value' },
      { field: 'responsible_person_id', type: 'FK', desc: 'Deal owner' },
    ],
    endpoints: endpointsDeals(),
    specialLogic: [
      'Soft deletes on deals.',
      'assignResponsiblePerson may attempt last_stage_change_at — verify fillable alignment in model.',
      'History merges lead_id rows for unified timeline when deal originated from lead.',
    ],
    highlights: ['Listing-aware stage validation', 'Merged audit timeline (lead + deal)'],
  },
}
