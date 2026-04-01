/**
 * Thousand separators for budget fields (e.g. 1,000,000).
 * Stored values are plain numbers; display strings include commas.
 */

export function formatBudgetThousands(n) {
  if (n == null || n === '') return ''
  const num = typeof n === 'number' ? n : Number(String(n).replace(/\D/g, ''))
  if (!Number.isFinite(num)) return ''
  return num.toLocaleString('en-US')
}

/**
 * @param {string} raw - User input from a text field
 * @returns {{ numeric: number|null, display: string }}
 */
export function parseBudgetThousandsInput(raw) {
  const digits = String(raw ?? '').replace(/\D/g, '')
  if (digits === '') return { numeric: null, display: '' }
  const n = Number(digits)
  if (!Number.isFinite(n)) return { numeric: null, display: '' }
  return { numeric: n, display: n.toLocaleString('en-US') }
}

/** Display line for lead cards / detail (range or legacy single budget). */
export function formatLeadBudgetRange(lead) {
  if (!lead) return ''
  const hasRange = lead.budget_from != null || lead.budget_to != null
  if (hasRange) {
    const from =
      lead.budget_from != null && lead.budget_from !== ''
        ? formatBudgetThousands(lead.budget_from)
        : '—'
    const to =
      lead.budget_to != null && lead.budget_to !== '' ? formatBudgetThousands(lead.budget_to) : '—'
    return `${from} – ${to}`
  }
  if (lead.budget != null && lead.budget !== '') {
    return formatBudgetThousands(lead.budget)
  }
  return ''
}
