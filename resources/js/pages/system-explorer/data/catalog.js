import { CATALOG_VERSION, CATALOG_GENERATED_AT, CATALOG_SOURCE, STACK } from './meta.js'
import { modules } from './modules.js'
import { features } from './features.js'
import { workflows } from './workflows.js'
import { tables, erMermaid } from './database.js'
import { integrations } from './integrations.js'
import { troubleshooting } from './troubleshooting.js'
import { glossary } from './glossary.js'
import { frontendLayer, backendLayer, architectureMermaid } from './layers.js'
import { ui } from './ui.js'

function loc(obj, lang) {
  if (obj == null) return ''
  if (typeof obj === 'string') return obj
  return obj[lang] || obj.en || ''
}

/** Flat search index for client-side filtering */
export function buildSearchIndex(lang = 'en') {
  const rows = []

  for (const m of modules) {
    rows.push({
      id: `mod:${m.id}`,
      type: 'module',
      section: 'modules',
      targetId: m.id,
      title: loc(m.name, lang),
      haystack: [loc(m.name, lang), loc(m.purpose, lang), m.id, ...(m.tables || []), ...(m.vueRoutes || [])].join(' ').toLowerCase(),
    })
  }

  for (const f of features) {
    const steps = (f.howItWorks || []).map((s) => `${s.path || ''} ${loc(s, lang)}`).join(' ')
    rows.push({
      id: `feat:${f.id}`,
      type: 'feature',
      section: 'business',
      targetId: f.id,
      title: loc(f.name, lang),
      haystack: [loc(f.name, lang), loc(f.simple, lang), steps, ...(f.filesFirst || [])].join(' ').toLowerCase(),
    })
  }

  for (const t of tables) {
    rows.push({
      id: `tbl:${t.id}`,
      type: 'table',
      section: 'database',
      targetId: t.id,
      title: t.id,
      haystack: [t.id, t.model, loc(t.purpose, lang), ...(t.relations || []).map((r) => r.to)].join(' ').toLowerCase(),
    })
  }

  for (const i of integrations) {
    rows.push({
      id: `int:${i.id}`,
      type: 'integration',
      section: 'integrations',
      targetId: i.id,
      title: loc(i.name, lang),
      haystack: [loc(i.name, lang), loc(i.purpose, lang), ...(i.files || []), ...(i.routes || [])].join(' ').toLowerCase(),
    })
  }

  for (const w of workflows) {
    rows.push({
      id: `wf:${w.id}`,
      type: 'workflow',
      section: 'workflows',
      targetId: w.id,
      title: loc(w.name, lang),
      haystack: [loc(w.name, lang), loc(w.summary, lang)].join(' ').toLowerCase(),
    })
  }

  for (const g of glossary) {
    rows.push({
      id: `gl:${g.id}`,
      type: 'glossary',
      section: 'glossary',
      targetId: g.id,
      title: loc(g.term, lang),
      haystack: [loc(g.term, lang), loc(g.def, lang)].join(' ').toLowerCase(),
    })
  }

  for (const ts of troubleshooting) {
    rows.push({
      id: `ts:${ts.id}`,
      type: 'troubleshoot',
      section: 'troubleshoot',
      targetId: ts.id,
      title: loc(ts.title, lang),
      haystack: [loc(ts.title, lang), ...(ts.files || [])].join(' ').toLowerCase(),
    })
  }

  // File paths from modules/features
  const fileSet = new Set()
  for (const m of modules) {
    for (const f of m.frontend || []) fileSet.add(f.path)
    for (const s of m.services || []) fileSet.add(s)
    for (const mo of m.models || []) fileSet.add(mo)
  }
  for (const f of features) {
    for (const p of f.filesFirst || []) fileSet.add(p)
  }
  for (const path of fileSet) {
    if (!path || path.endsWith('/')) continue
    rows.push({
      id: `file:${path}`,
      type: 'file',
      section: 'files',
      targetId: path,
      title: path,
      haystack: path.toLowerCase(),
    })
  }

  return rows
}

export function coverageStats() {
  const all = [...modules, ...features, ...tables, ...integrations, ...workflows, ...troubleshooting]
  const verified = all.filter((x) => x.status === 'verified').length
  const partial = all.filter((x) => x.status === 'partial').length
  const needs = all.filter((x) => x.status === 'needs_verification').length
  return { total: all.length, verified, partial, needs, modules: modules.length, features: features.length }
}

export const catalog = {
  version: CATALOG_VERSION,
  generatedAt: CATALOG_GENERATED_AT,
  source: CATALOG_SOURCE,
  stack: STACK,
  modules,
  features,
  workflows,
  tables,
  erMermaid,
  integrations,
  troubleshooting,
  glossary,
  frontendLayer,
  backendLayer,
  architectureMermaid,
  ui,
}

export { loc }
