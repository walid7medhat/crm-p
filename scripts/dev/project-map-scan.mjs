/**
 * Shared project scan for CLI (build-project-map.mjs) and Vite dev plugin.
 */
import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import {
  buildModuleArchitecture,
  attachApisToDomains,
  buildRoadmapGraph,
  buildRouteFlowForVue,
} from './architecture-model.mjs'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

const IGNORE_DIRS = new Set(['node_modules', '.git', 'dist', 'build', 'vendor'])
const EXT_OK = /\.(vue|js|ts)$/i

export function getRepoRoot(fromDir = __dirname) {
  return path.join(fromDir, '../..')
}

function walkTree(dir, relBase, depth, maxDepth, stats, isRoot = false) {
  if (depth > maxDepth) return null
  const name = isRoot ? 'resources/js' : path.basename(dir)
  const node = { name, type: 'dir', children: [] }
  let entries
  try {
    entries = fs.readdirSync(dir, { withFileTypes: true })
  } catch {
    return null
  }
  for (const ent of entries.sort((a, b) => a.name.localeCompare(b.name))) {
    if (ent.name.startsWith('.')) continue
    const full = path.join(dir, ent.name)
    if (ent.isDirectory()) {
      if (IGNORE_DIRS.has(ent.name)) continue
      const sub = walkTree(full, relBase, depth + 1, maxDepth, stats, false)
      if (sub && (sub.children?.length || sub.type === 'file')) node.children.push(sub)
    } else if (EXT_OK.test(ent.name)) {
      stats.files += 1
      node.children.push({ type: 'file', name: ent.name })
    }
  }
  return node
}

export function parseRouterRoutes(routerPath) {
  const text = fs.readFileSync(routerPath, 'utf8')
  const routes = []
  const chunks = text.split(/\{\s*path:\s*/)
  for (let i = 1; i < chunks.length; i++) {
    const chunk = chunks[i]
    const pathM = chunk.match(/^['"]([^'"]+)['"]/)
    if (!pathM) continue
    const p = pathM[1]
    const compM = chunk.match(/component:\s*([A-Za-z0-9_]+)/)
    const nameM = chunk.match(/name:\s*['"]([^'"]+)['"]/)
    const redM = chunk.match(/redirect:\s*['"]([^'"]+)['"]/)
    const metaM = chunk.match(/requiresSuperAdmin:\s*true/)
    const metaAdmin = chunk.match(/requiresAdmin:\s*true/)
    routes.push({
      path: p,
      component: compM ? compM[1] : null,
      name: nameM ? nameM[1] : null,
      redirect: redM ? redM[1] : null,
      requiresSuperAdmin: !!metaM,
      requiresAdmin: !!metaAdmin,
    })
  }
  return routes
}

/** Nested segments for route explorer (VS Code–style path tree). */
export function buildRouteHierarchy(flatRoutes) {
  const root = { segment: '', fullPath: '/', children: [], routes: [] }
  for (const r of flatRoutes) {
    const raw = r.path === '/' ? [] : r.path.replace(/^\//, '').split('/').filter(Boolean)
    if (raw.length === 0) {
      root.routes.push({ ...r, kind: r.redirect ? 'redirect' : 'route' })
      continue
    }
    let node = root
    let acc = ''
    for (let i = 0; i < raw.length; i++) {
      const seg = raw[i]
      acc += `/${seg}`
      let next = node.children.find((c) => c.segment === seg)
      if (!next) {
        next = { segment: seg, fullPath: acc, children: [], routes: [] }
        node.children.push(next)
      }
      node = next
    }
    node.routes.push({ ...r, kind: r.redirect && !r.component ? 'redirect' : 'route' })
  }
  return root
}

function scanApiCalls(jsRoot) {
  const apiCalls = []
  function walk(dir) {
    let entries
    try {
      entries = fs.readdirSync(dir, { withFileTypes: true })
    } catch {
      return
    }
    for (const ent of entries) {
      const full = path.join(dir, ent.name)
      if (ent.isDirectory()) {
        if (IGNORE_DIRS.has(ent.name) || ent.name.startsWith('.')) continue
        walk(full)
      } else if (EXT_OK.test(ent.name)) {
        let content
        try {
          content = fs.readFileSync(full, 'utf8')
        } catch {
          continue
        }
        const rel = path.relative(jsRoot, full).replace(/\\/g, '/')
        const lines = content.split('\n')
        lines.forEach((line, idx) => {
          const m =
            line.match(/axios\.(get|post|put|patch|delete)\s*\(\s*[`'"]([^`'"]+)[`'"]/) ||
            line.match(/axios\.(get|post|put|patch|delete)\s*\(\s*`([^`]+)`/) ||
            line.match(/\$fetch\s*\(\s*[`'"]([^`'"]+)[`'"]/)
          if (m) {
            const method = m[1] && ['get', 'post', 'put', 'patch', 'delete'].includes(m[1]) ? m[1] : 'get'
            const url = m[2] || m[1]
            if (url && url.length < 200) {
              apiCalls.push({ file: rel, line: idx + 1, method, url: url.slice(0, 160) })
            }
          }
        })
      }
    }
  }
  walk(jsRoot)
  return apiCalls
}

/** Group API rows by top-level module (e.g. components/kanban, pages/listings). */
export function groupApiByModule(apiCalls, jsRootRel = '') {
  const groups = {}
  for (const row of apiCalls) {
    const parts = row.file.split('/')
    const mod = parts.length >= 2 ? `${parts[0]}/${parts[1]}` : parts[0] || 'root'
    if (!groups[mod]) groups[mod] = []
    groups[mod].push(row)
  }
  return groups
}

function extractImportsForFile(jsRoot, fileRel) {
  const full = path.join(jsRoot, fileRel)
  if (!fs.existsSync(full)) return []
  const content = fs.readFileSync(full, 'utf8')
  const imports = []
  const re = /import\s+(?:(?:\{[^}]*\}|\*\s+as\s+\w+|\w+)(?:\s*,\s*)?)+\s+from\s+['"]([^'"]+)['"]/g
  let m
  while ((m = re.exec(content)) !== null) {
    imports.push(m[1])
  }
  return [...new Set(imports)].slice(0, 120)
}

function findImporters(jsRoot, targetPartial, limit = 50) {
  const importers = []
  function walk(dir) {
    let entries
    try {
      entries = fs.readdirSync(dir, { withFileTypes: true })
    } catch {
      return
    }
    for (const ent of entries) {
      const full = path.join(dir, ent.name)
      if (ent.isDirectory()) {
        if (IGNORE_DIRS.has(ent.name)) continue
        walk(full)
      } else if (/\.(vue|js)$/i.test(ent.name)) {
        const content = fs.readFileSync(full, 'utf8')
        if (content.includes(targetPartial)) {
          importers.push(path.relative(jsRoot, full).replace(/\\/g, '/'))
          if (importers.length >= limit) return
        }
      }
    }
  }
  walk(jsRoot)
  return importers
}

/** Edges: .vue file → imported .vue path (resolved relative). */
export function scanVueComponentEdges(jsRoot, maxEdges = 400) {
  const edges = []
  const nodes = new Set()
  const repoRoot = path.join(jsRoot, '..', '..')

  function resolveImport(fromFile, spec) {
    if (!spec.endsWith('.vue') && !spec.includes('.vue?')) return null
    const clean = spec.replace(/\?.*$/, '').replace(/^@\//, '')
    const dir = path.dirname(fromFile)
    let resolved =
      clean.startsWith('resources/') || clean.startsWith('js/')
        ? path.join(repoRoot, clean.replace(/^js\//, 'resources/js/'))
        : path.resolve(dir, clean)
    if (!resolved.endsWith('.vue')) resolved += '.vue'
    const rel = path.relative(jsRoot, resolved).replace(/\\/g, '/')
    if (!rel.startsWith('..') && fs.existsSync(path.join(jsRoot, rel))) return rel
    const aliasPath = path.join(jsRoot, clean.replace(/^\//, ''))
    if (fs.existsSync(aliasPath)) return path.relative(jsRoot, aliasPath).replace(/\\/g, '/')
    return null
  }

  function walk(dir) {
    let entries
    try {
      entries = fs.readdirSync(dir, { withFileTypes: true })
    } catch {
      return
    }
    for (const ent of entries) {
      const full = path.join(dir, ent.name)
      if (ent.isDirectory()) {
        if (IGNORE_DIRS.has(ent.name)) continue
        walk(full)
      } else if (ent.name.endsWith('.vue')) {
        const fromRel = path.relative(jsRoot, full).replace(/\\/g, '/')
        nodes.add(fromRel)
        let content
        try {
          content = fs.readFileSync(full, 'utf8')
        } catch {
          continue
        }
        const re = /import\s+[\w{},\s*]+\s+from\s+['"]([^'"]+)['"]/g
        let m
        while ((m = re.exec(content)) !== null && edges.length < maxEdges) {
          const to = resolveImport(full, m[1])
          if (to) {
            nodes.add(to)
            edges.push({ from: fromRel, to, kind: 'import' })
          }
        }
      }
    }
  }
  walk(jsRoot)
  return { nodes: [...nodes].sort(), edges }
}

export function buildDataFlowGraph() {
  return {
    nodes: [
      { id: 'lead', label: 'Lead / task (Kanban card)', group: 'entity' },
      { id: 'requirement', label: 'Client requirement (extra rows)', group: 'entity' },
      { id: 'meta', label: 'qualification_meta (priority pointer)', group: 'meta' },
      { id: 'property', label: 'Property / listing', group: 'entity' },
      { id: 'user', label: 'User / roles', group: 'entity' },
    ],
    edges: [
      { from: 'lead', to: 'requirement', label: 'extra_client_requirements[]' },
      { from: 'meta', to: 'requirement', label: 'source → priority id' },
      { from: 'lead', to: 'meta', label: 'embedded in same array' },
      { from: 'lead', to: 'property', label: 'listing / project refs' },
      { from: 'user', to: 'lead', label: 'assignment / ownership' },
    ],
  }
}

function scanLaravelMobileApiRoutes(repoRoot) {
  const apiPhp = path.join(repoRoot, 'routes/api.php')
  if (!fs.existsSync(apiPhp)) return []
  const text = fs.readFileSync(apiPhp, 'utf8')
  if (!text.includes('v1/mobile') || !text.includes('MobileKanbanController')) return []
  const lines = text.split('\n')
  const rows = []
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i]
    if (!line.includes('MobileKanbanController') && !line.includes('MobileLeadMoveController')) continue
    const m = line.match(/Route::(get|post|put|patch|delete)\(\s*['"]([^'"]+)['"]/)
    if (!m) continue
    const method = m[1].toUpperCase()
    const suffix = m[2].replace(/^\//, '')
    const fullPath = `/api/v1/mobile/${suffix}`.replace(/\/+/g, '/')
    rows.push({
      file: 'routes/api.php',
      line: i + 1,
      method,
      url: fullPath,
      source: 'laravel-mobile',
    })
  }
  return rows
}

function businessHighlights() {
  return {
    mobileApi: [
      'Laravel mobile layer: GET /api/v1/mobile/kanban — one-shot Kanban (stages, leads_by_stage, settings, assignable_users)',
      'POST /api/v1/mobile/leads/{lead}/move — idempotent move (Idempotency-Key), optional expected_updated_at for 409 conflicts',
      'PHP: routes/api.php (prefix v1/mobile), app/Http/Controllers/Api/Mobile/, app/Services/Mobile/, app/Helpers/MobileApiResponse.php',
      'Realtime: LeadUpdated broadcast still uses event name lead.updated; payload adds canonical_event + lead_mobile for clients',
    ],
    priorityRequirement: [
      'resources/js/components/kanban/leadList/leads.vue — getQualificationSourceId, getPriorityRequirement, getPriorityBedrooms',
      'Qualification meta: extra_client_requirements entry with _kind === "qualification_meta" and source → priority extra id or "primary"',
      'Plot/land: isNoBedroomPropertyType / PLOT_TYPE_IDS — bedrooms hidden when priority req is residential/commercial plots',
    ],
    bedrooms: [
      'leads.vue — shouldShowPriorityBedrooms, shouldHideBedroomsDueToPlotPriority, hasDynamicFieldValue(bedrooms)',
      'Kanban card: dedicated row uses getPriorityBedrooms; dynamic fallback uses task.bedrooms unless plot priority suppresses',
    ],
    dynamicFields: [
      'leads.vue — enabledFieldsForColumn, hasDynamicFieldValue, getDynamicFieldDisplay, cardBehavior / qualifiedFieldKeys',
    ],
    entities: [
      'Lead / task — kanban card payload (task), extra_client_requirements, property_type, bedrooms',
      'Client requirement — extra rows on lead; priority selected via qualification_meta.source',
    ],
  }
}

/**
 * @param {string} repoRoot - Absolute path to repository root
 */
export function scanProjectMap(repoRoot) {
  const jsRoot = path.join(repoRoot, 'resources/js')
  const stats = { files: 0 }
  const tree = walkTree(jsRoot, jsRoot, 0, 14, stats, true)
  const routes = parseRouterRoutes(path.join(jsRoot, 'router.js'))
  const routeHierarchy = buildRouteHierarchy(routes)
  const apiCalls = scanApiCalls(jsRoot)
  const laravelMobileRoutes = scanLaravelMobileApiRoutes(repoRoot)
  const apiCallsMerged = [...laravelMobileRoutes, ...apiCalls]
  const apiByModule = groupApiByModule(apiCallsMerged)
  const vueGraph = scanVueComponentEdges(jsRoot)

  const leadsImports = extractImportsForFile(jsRoot, 'components/kanban/leadList/leads.vue')
  const importersLeads = findImporters(jsRoot, 'kanban/leadList/leads.vue')

  const highlights = businessHighlights()
  const dataFlowGraph = buildDataFlowGraph()

  const arch = buildModuleArchitecture(jsRoot)
  const architectureDomains = attachApisToDomains(arch.domains, apiCallsMerged)
  const roadmapGraph = buildRoadmapGraph(vueGraph, routes, apiCallsMerged, dataFlowGraph, 72)
  const routeFlowGraph = buildRouteFlowForVue(routes)

  return {
    generatedAt: new Date().toISOString(),
    root: 'resources/js',
    stats: {
      jsVueFilesApprox: stats.files,
      routesCount: routes.length,
      apiCallRows: apiCallsMerged.length,
      laravelMobileRoutes: laravelMobileRoutes.length,
      vueNodes: vueGraph.nodes.length,
      vueEdges: vueGraph.edges.length,
    },
    tree,
    routes,
    routeHierarchy,
    apiCalls: apiCallsMerged.slice(0, 1200),
    apiByModule,
    architecture: { domains: architectureDomains },
    roadmapGraph,
    routeFlowGraph,
    componentGraph: {
      hub: 'components/kanban/leadList/leads.vue',
      importsFromHub: leadsImports,
      sampleImporters: importersLeads,
      vueImportGraph: vueGraph,
    },
    dataFlow: highlights.entities,
    dataFlowGraph,
    businessLogic: highlights,
  }
}

export function writeProjectMapJson(repoRoot, outPath) {
  const payload = scanProjectMap(repoRoot)
  fs.mkdirSync(path.dirname(outPath), { recursive: true })
  fs.writeFileSync(outPath, JSON.stringify(payload, null, 2), 'utf8')
  return payload
}
