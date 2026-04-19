/**
 * Client-only "why changed" copy from two preview payloads (rules / rail / drawer).
 */

import { formatFactor } from '@/pages/sales-intelligence/utils'

function breakdownMap(preview) {
  const rows = preview?.breakdown
  if (!Array.isArray(rows)) return new Map()
  const m = new Map()
  for (const r of rows) {
    const k = String(r.factor || '')
    if (k) m.set(k, Number(r.normalized) || 0)
  }
  return m
}

/**
 * @param {object|null} prev
 * @param {object|null} cur
 * @returns {string[]}
 */
export function previewDeltaBullets(prev, cur) {
  if (!cur) return []
  const lines = []
  if (prev && cur.total_score != null && prev.total_score != null) {
    const d = Math.round((Number(cur.total_score) - Number(prev.total_score)) * 10) / 10
    if (d !== 0) {
      lines.push(
        `Composite moved ${d > 0 ? '+' : ''}${d} pts vs the last preview snapshot (draft rules / same agent).`
      )
    }
  }
  const rk = String(cur.rank || '').toLowerCase()
  const prk = String(prev?.rank || '').toLowerCase()
  if (prev && rk && prk && rk !== prk) {
    lines.push(`Tier band shifted ${prk} → ${rk} because the weighted blend crossed the engine cutoffs.`)
  }
  const A = breakdownMap(prev)
  const B = breakdownMap(cur)
  const shifts = []
  for (const [factor, bn] of B) {
    const an = A.get(factor)
    if (an == null) continue
    const d = Math.round((bn - an) * 10) / 10
    if (Math.abs(d) >= 2) shifts.push({ factor, d })
  }
  shifts.sort((a, b) => Math.abs(b.d) - Math.abs(a.d))
  for (const { factor, d } of shifts.slice(0, 3)) {
    lines.push(`${formatFactor(factor)} normalized ${d > 0 ? '+' : ''}${d} pts — directly feeds the composite.`)
  }
  if (!lines.length && Array.isArray(cur.breakdown) && cur.breakdown.length) {
    const top = [...cur.breakdown].sort((a, b) => Number(b.normalized || 0) - Number(a.normalized || 0))[0]
    if (top) {
      lines.push(
        `Largest contributor right now: ${formatFactor(top.factor)} (${Math.round(Number(top.normalized) || 0)}).`
      )
    }
  }
  return lines.slice(0, 6)
}

/**
 * @param {object} result distribution API result (client shape)
 * @returns {string[]}
 */
export function distributionResultBullets(result) {
  if (!result || typeof result !== 'object') return []
  const lines = []
  const method = result.method || result.meta?.method || 'route'
  lines.push(`Engine picked ${method} using server caps, scores, and lead context.`)
  const name = result.assigned_to?.name
  if (name) {
    lines.push(`Assigned: ${name} · composite at run ${result.score_at_assignment ?? '—'}.`)
  }
  if (result.dry_run === true || result.meta?.dry_run) {
    lines.push('Dry run — no lead mutation; validates routing before live send.')
  }
  return lines
}
