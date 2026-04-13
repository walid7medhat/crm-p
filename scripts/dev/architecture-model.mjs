/**
 * Logical module / domain model + roadmap graph data for the dev project map.
 */
import fs from 'fs'
import path from 'path'

const IGNORE_DIRS = new Set(['node_modules', '.git', 'dist', 'build', 'vendor'])

export const DOMAIN_LABELS = {
  kanban: 'Kanban & leads',
  listings: 'Listings & search',
  auth: 'Authentication',
  chat: 'Chat',
  dashboard: 'Dashboard & AI home',
  lead_tools: 'Lead tools & reports',
  users: 'Users & roles',
  email: 'Email',
  properties: 'Developers / owners / catalog',
  settings: 'Settings & config',
  ui: 'UI kit & charts',
  shared: 'Shared layout & cross-cutting',
  dev: 'Dev tools',
  other: 'Other',
}

const DOMAIN_ORDER = Object.keys(DOMAIN_LABELS)

/** Pretty title from file name (primary UI label). */
export function humanizeFileName(filename) {
  const base = filename.replace(/\.(vue|js|ts|tsx)$/i, '')
  const spaced = base
    .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
    .replace(/[-_]+/g, ' ')
    .trim()
  return spaced.replace(/\b\w/g, (c) => c.toUpperCase()) || base
}

export function inferDomain(relPath) {
  const p = relPath.replace(/\\/g, '/').toLowerCase()

  if (p.includes('/dev/') || p.includes('project-map')) return 'dev'
  /** Mobile Kanban REST (Laravel) — PHP paths + route map */
  if (
    p.includes('/services/mobile') ||
    p.includes('app/http/controllers/api/mobile') ||
    p.includes('helpers/mobileapiresponse') ||
    p.includes('config/mobile-api') ||
    p.includes('http/resources/mobile') ||
    p === 'routes/api.php' ||
    p.endsWith('/routes/api.php')
  ) {
    return 'kanban'
  }
  if (p.includes('lead-reports') || p.includes('lead-scoring') || p.includes('/settings/lead-scoring') || p.includes('investment-analysis') || p.includes('city-investments'))
    return 'lead_tools'
  if (p.includes('/kanban') || p.includes('kanban_deal') || p.includes('import-pitrix') || p.includes('stage-visibility')) return 'kanban'
  if (p.includes('/listings/') || p.includes('alllisting') || p.includes('property-details') || p.includes('properties/') || p.includes('notify-me') || p.includes('searchbar'))
    return 'listings'
  if (p.includes('/authentication/') || p.includes('sign-in') || p.includes('sign-up') || p.includes('forgot-password') || p.includes('reset-password'))
    return 'auth'
  if (p.includes('/chat/') || p.startsWith('components/chat/')) return 'chat'
  if (p.includes('/dashboard/') || p === 'pages/dashboard/ai.vue' || p.endsWith('/crm.vue')) return 'dashboard'
  if (p.includes('/email/')) return 'email'
  if (p.includes('/users/') || p.includes('/roles/') || p.includes('roleaccess') || p.includes('assign-role')) return 'users'
  if (p.includes('/developers/') || p.includes('/owners/') || p.includes('/property_types/') || p.includes('/unit_views/') || p.includes('/layout_types/') || p.includes('/areas/') || p.includes('/features/') || p.includes('/projects/'))
    return 'properties'
  if (p.includes('/settings/') || p.includes('city-settings') || p.includes('currencies') || p.includes('payment-gateway')) return 'settings'
  if (p.includes('/uicomponent/') || p.includes('/chart/') || p.includes('uicomponent')) return 'ui'
  if (p.includes('components/layout') || p.includes('components/allnotifications') || p.includes('app.vue') || p.includes('main.js'))
    return 'shared'

  if (p.startsWith('pages/')) {
    const seg = p.split('/')[1] || ''
    if (['kanban', 'listings', 'authentication', 'chat', 'dashboard', 'email', 'users'].includes(seg)) {
      return inferDomain(`pages/${seg}/x`)
    }
  }
  if (p.startsWith('components/')) {
    const seg = p.split('/')[1] || ''
    if (['kanban', 'alllisting', 'listings'].includes(seg)) return seg === 'alllisting' ? 'listings' : 'kanban'
    if (seg === 'layout' || seg === 'allnotifications') return 'shared'
  }

  return 'other'
}

export function isSharedPath(relPath) {
  const p = relPath.replace(/\\/g, '/').toLowerCase()
  return (
    p.includes('components/layout') ||
    p.includes('components/allnotifications') ||
    p.includes('/assets/') ||
    p.includes('app.vue')
  )
}

function walkFiles(rootDir, testFile, out) {
  let entries
  try {
    entries = fs.readdirSync(rootDir, { withFileTypes: true })
  } catch {
    return
  }
  for (const ent of entries) {
    const full = path.join(rootDir, ent.name)
    if (ent.isDirectory()) {
      if (IGNORE_DIRS.has(ent.name) || ent.name.startsWith('.')) continue
      walkFiles(full, testFile, out)
    } else if (testFile(ent.name, full)) {
      out.push(full)
    }
  }
}

function makeItem(rel, jsRoot) {
  const base = path.basename(rel)
  const label = humanizeFileName(base)
  return {
    id: rel.replace(/[^\w/-]+/g, '_'),
    rel,
    label,
    file: base,
    shared: isSharedPath(rel),
  }
}

function emptyDomains() {
  const m = {}
  for (const id of DOMAIN_ORDER) {
    m[id] = {
      id,
      title: DOMAIN_LABELS[id],
      pages: [],
      components: [],
      composables: [],
      services: [],
    }
  }
  return m
}

/**
 * Scan resources/js into logical domains with categorized file lists.
 */
export function buildModuleArchitecture(jsRoot) {
  const domains = emptyDomains()
  const pages = []
  const components = []
  const composables = []
  const services = []

  walkFiles(
    path.join(jsRoot, 'pages'),
    (name) => /\.(vue)$/i.test(name),
    pages,
  )
  walkFiles(
    path.join(jsRoot, 'components'),
    (name) => /\.vue$/i.test(name),
    components,
  )
  walkFiles(
    jsRoot,
    (name, full) => {
      if (!/\.(js|ts)$/i.test(name)) return false
      return full.replace(/\\/g, '/').includes('/composables/')
    },
    composables,
  )
  walkFiles(
    jsRoot,
    (name, full) => {
      if (!/\.(js|ts)$/i.test(name)) return false
      const rel = full.replace(/\\/g, '/').toLowerCase()
      return rel.includes('/services/') || /api\.js$/i.test(name) || /service\.js$/i.test(name)
    },
    services,
  )

  for (const full of pages) {
    const rel = path.relative(jsRoot, full).replace(/\\/g, '/')
    const domain = inferDomain(rel)
    domains[domain].pages.push({ ...makeItem(rel, jsRoot), isEntry: true })
  }
  for (const full of components) {
    const rel = path.relative(jsRoot, full).replace(/\\/g, '/')
    const domain = inferDomain(rel)
    domains[domain].components.push(makeItem(rel, jsRoot))
  }
  for (const full of composables) {
    const rel = path.relative(jsRoot, full).replace(/\\/g, '/')
    const domain = inferDomain(rel)
    domains[domain].composables.push(makeItem(rel, jsRoot))
  }
  for (const full of services) {
    const rel = path.relative(jsRoot, full).replace(/\\/g, '/')
    const domain = inferDomain(rel)
    domains[domain].services.push(makeItem(rel, jsRoot))
  }

  const list = DOMAIN_ORDER.map((id) => domains[id]).filter(
    (d) => d.pages.length + d.components.length + d.composables.length + d.services.length > 0,
  )

  return { domains: list, domainOrder: DOMAIN_ORDER }
}

/** Attach API call rows to domain by source file. */
export function attachApisToDomains(domainsArr, apiCalls) {
  const byId = Object.fromEntries(domainsArr.map((d) => [d.id, { ...d, apiSamples: [] }]))
  for (const row of apiCalls.slice(0, 500)) {
    const domain = inferDomain(row.file)
    if (byId[domain] && byId[domain].apiSamples.length < 22) {
      byId[domain].apiSamples.push({
        method: row.method,
        url: row.url,
        file: row.file,
        line: row.line,
      })
    }
  }
  return domainsArr.map((d) => byId[d.id])
}

/** Route → route redirect chains for “route flow” view. */
export function buildRouteFlowModel(routes) {
  const nodes = []
  const edges = []
  const seen = new Set()
  const pathSet = new Set((routes || []).map((r) => r.path))

  for (const r of routes || []) {
    const id = `route:${r.path}`
    if (seen.has(id)) continue
    seen.add(id)
    const tail = r.path === '/' ? 'home' : r.path.replace(/^\//, '').split('/').pop() || r.path
    const label = humanizeFileName(tail)
    nodes.push({
      id,
      path: r.path,
      label,
      sub: r.component || r.redirect || '—',
      component: r.component,
      redirect: r.redirect,
      requiresSuperAdmin: r.requiresSuperAdmin,
    })
  }

  for (const r of routes || []) {
    if (r.redirect && pathSet.has(r.redirect)) {
      edges.push({
        id: `e:${r.path}->${r.redirect}`,
        source: `route:${r.path}`,
        target: `route:${r.redirect}`,
        label: 'redirect',
        animated: true,
      })
    }
  }

  return { nodes, edges }
}

/** Vue Flow–ready route flow (positions + node shape). */
export function buildRouteFlowForVue(routes) {
  const rf = buildRouteFlowModel(routes)
  const flowNodes = rf.nodes.map((n, i) => ({
    id: n.id,
    type: 'roadmap',
    position: { x: (i % 5) * 220, y: Math.floor(i / 5) * 100 },
    data: {
      group: 'route',
      label: n.label,
      detail: n.path,
      rel: n.sub,
      meta: n.component || n.redirect || '',
    },
  }))
  const flowEdges = rf.edges.map((e) => ({
    id: e.id,
    source: e.source,
    target: e.target,
    label: e.label,
    animated: !!e.animated,
  }))
  return { nodes: flowNodes, edges: flowEdges }
}

function layoutGrid(n, rowW = 200, colH = 90) {
  return n.map((node, i) => ({
    ...node,
    position: { x: (i % 6) * rowW, y: Math.floor(i / 6) * colH },
  }))
}

/**
 * Combined roadmap graph: entities, routes (sample), vue imports (sample), APIs (sample).
 */
export function buildRoadmapGraph(vueGraph, routes, apiCalls, entityGraph, maxNodes = 70) {
  const rawNodes = []
  const rawEdges = []
  const ids = new Set()
  let nid = 0

  const addNode = (group, label, extra = {}) => {
    if (rawNodes.length >= maxNodes) return null
    const id = extra.id || `n${nid++}`
    if (ids.has(id)) return id
    ids.add(id)
    rawNodes.push({
      id,
      type: 'roadmap',
      position: { x: 0, y: 0 },
      data: {
        group,
        label,
        detail: extra.detail || '',
        rel: extra.rel || '',
      },
    })
    return id
  }

  for (const n of entityGraph.nodes || []) {
    addNode('entity', n.label, { id: `ent:${n.id}`, detail: String(n.group || '') })
  }
  for (const e of entityGraph.edges || []) {
    rawEdges.push({
      id: `ee:${e.from}-${e.to}`,
      source: `ent:${e.from}`,
      target: `ent:${e.to}`,
      label: e.label,
    })
  }

  const routeSample = (routes || []).filter((r) => r.component && r.path !== '/').slice(0, 14)
  for (const r of routeSample) {
    const label = humanizeFileName(r.component || r.path)
    addNode('page', label, {
      id: `pg:${r.path}`,
      detail: r.path,
      rel: r.component || '',
    })
  }

  const seenEdge = new Set()
  for (const e of (vueGraph.edges || []).slice(0, 100)) {
    const fromId = `f:${e.from}`
    const toId = `f:${e.to}`
    addNode('component', humanizeFileName(path.basename(e.from)), {
      id: fromId,
      detail: e.from,
      rel: e.from,
    })
    addNode('component', humanizeFileName(path.basename(e.to)), {
      id: toId,
      detail: e.to,
      rel: e.to,
    })
    if (rawNodes.length >= maxNodes) break
    const ek = `${fromId}->${toId}`
    if (seenEdge.has(ek) || rawEdges.length > 120) continue
    seenEdge.add(ek)
    rawEdges.push({
      id: `im:${ek}`,
      source: fromId,
      target: toId,
      label: 'imports',
    })
  }

  const apiSample = (apiCalls || []).slice(0, 10)
  for (const a of apiSample) {
    if (rawNodes.length >= maxNodes) break
    const shortUrl = a.url.length > 48 ? `${a.url.slice(0, 48)}…` : a.url
    addNode('api', `${a.method} · ${shortUrl}`, {
      id: `api:${a.file}:${a.line}:${nid}`,
      detail: a.url,
      rel: `${a.file}:${a.line}`,
    })
  }

  const nodeIds = new Set(rawNodes.map((n) => n.id))
  const edges = rawEdges.filter((e) => nodeIds.has(e.source) && nodeIds.has(e.target))

  const nodes = layoutGrid(rawNodes)
  return { nodes, edges }
}
