const money = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

/** "AED 1,234,567.00" (null → "—"). */
export function aed(value) {
  if (value === null || value === undefined || value === '') return '—'
  const n = Number(value)
  return Number.isFinite(n) ? `AED ${money.format(n)}` : '—'
}

/** Number only, for table cells under an "(AED)" header. */
export function amount(value) {
  if (value === null || value === undefined || value === '') return '—'
  const n = Number(value)
  return Number.isFinite(n) ? money.format(n) : '—'
}

/** "AED 50,000" — no decimals when the amount is whole, else 2. Null → "—". */
export function aedShort(value) {
  if (value === null || value === undefined || value === '') return '—'
  const n = Number(value)
  if (!Number.isFinite(n)) return '—'
  const whole = Math.abs(n - Math.round(n)) < 0.005
  return `AED ${n.toLocaleString('en-US', { minimumFractionDigits: whole ? 0 : 2, maximumFractionDigits: whole ? 0 : 2 })}`
}

/** "AED 1.25M" / "AED 450K" for tight spots (donut centre). */
export function compact(value) {
  const n = Number(value)
  if (!Number.isFinite(n)) return '—'
  return `AED ${new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 2 }).format(n)}`
}

export function formatDate(iso) {
  if (!iso) return '—'
  const d = new Date(`${String(iso).slice(0, 10)}T00:00:00`)
  return Number.isNaN(d.getTime())
    ? '—'
    : d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
