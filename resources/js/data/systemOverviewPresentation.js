/**
 * Presentation-layer content: KPIs, flows, intelligence engines, cross-module edges.
 */

export const intelligenceEngines = [
  {
    id: 'scoring',
    title: 'AI Lead Scoring',
    subtitle: 'Priority & intent',
    icon: 'lucide:brain-circuit',
    accent: '#8b5cf6',
    feeds: ['leads'],
    summary: 'Weighted score, hot/warm/cold priority, optional OpenAI intent, next-best-action.',
  },
  {
    id: 'assignment',
    title: 'Auto Assignment',
    subtitle: 'Routing engine',
    icon: 'lucide:git-branch-plus',
    accent: '#0ea5e9',
    feeds: ['leads'],
    summary: 'Realtime / simple / scheduled modes; attendance & performance weights; assignment logs.',
  },
  {
    id: 'matching',
    title: 'Matching Engine',
    subtitle: 'Listing ↔ Lead',
    icon: 'lucide:link-2',
    accent: '#f59e0b',
    feeds: ['listings', 'leads'],
    summary: 'Smart listing match with relaxed price bands; search alerts on new inventory.',
  },
  {
    id: 'stageValidation',
    title: 'Stage Validation',
    subtitle: 'Compliance gates',
    icon: 'lucide:shield-check',
    accent: '#10b981',
    feeds: ['deals'],
    summary: 'Required fields & docs per deal stage; listing_id-aware seller/landlord rules.',
  },
]

/** Edges for relationship diagram (investor-facing labels) */
export const crossModuleEdges = [
  { from: 'intelligence', to: 'leads', label: 'Score & assign' },
  { from: 'intelligence', to: 'deals', label: 'Validate stages' },
  { from: 'listings', to: 'leads', label: 'Match & alerts' },
  { from: 'leads', to: 'deals', label: 'Convert' },
  { from: 'listings', to: 'deals', label: 'listing_id' },
]

export const pipelineStages = [
  {
    id: 'listings',
    label: 'Listings',
    tagline: 'Inventory & discovery',
    icon: 'lucide:building-2',
    tone: 'amber',
  },
  {
    id: 'leads',
    label: 'Leads',
    tagline: 'Pipeline & qualification',
    icon: 'lucide:users',
    tone: 'violet',
  },
  {
    id: 'deals',
    label: 'Deals',
    tagline: 'Transactions',
    icon: 'lucide:handshake',
    tone: 'emerald',
  },
]

/** Mock KPIs — presentation only */
export const moduleKpis = {
  listings: [
    { label: 'Active units', value: '2.4k', delta: '+12%', up: true },
    { label: 'Approval queue', value: '48', delta: '−6%', up: false },
    { label: 'Map coverage', value: '94%', delta: '+2%', up: true },
  ],
  leads: [
    { label: 'Open leads', value: '1.1k', delta: '+8%', up: true },
    { label: 'Avg. score', value: '72', delta: '+4pts', up: true },
    { label: 'Auto-routed', value: '61%', delta: '+5%', up: true },
  ],
  deals: [
    { label: 'Pipeline value', value: 'AED 420M', delta: '+14%', up: true },
    { label: 'In progress', value: '312', delta: '+3%', up: true },
    { label: 'Stage pass rate', value: '88%', delta: '±0%', up: null },
  ],
}

/** 3–4 step micro-flow per module (for chips / diagram) */
export const moduleMicroFlow = {
  listings: [
    { key: 'capture', label: 'Capture' },
    { key: 'approve', label: 'Approve' },
    { key: 'publish', label: 'Publish' },
    { key: 'match', label: 'Match' },
  ],
  leads: [
    { key: 'ingest', label: 'Ingest' },
    { key: 'score', label: 'Score' },
    { key: 'assign', label: 'Assign' },
    { key: 'convert', label: 'Convert' },
  ],
  deals: [
    { key: 'open', label: 'Open' },
    { key: 'kyc', label: 'KYC' },
    { key: 'validate', label: 'Validate' },
    { key: 'close', label: 'Close' },
  ],
}

export const actionChips = {
  listings: ['Create', 'Edit', 'Approve', 'Archive', 'Hot deal'],
  leads: ['Create', 'Edit', 'Move', 'Assign', 'Convert'],
  deals: ['Edit', 'Stage', 'Assign', 'Documents', 'Win/Lost'],
}

export const moduleDependencies = {
  listings: [
    { target: 'leads', relation: 'Feeds matching & search alerts' },
    { target: 'deals', relation: 'listing_id on transactions' },
  ],
  leads: [
    { target: 'listings', relation: 'Property match API' },
    { target: 'deals', relation: 'One-way conversion' },
    { target: 'intelligence', relation: 'Scoring & assignment' },
  ],
  deals: [
    { target: 'leads', relation: 'lead_id / history merge' },
    { target: 'listings', relation: 'Inventory link & validation' },
    { target: 'intelligence', relation: 'Stage gates' },
  ],
}
