// Read-only display helpers for deal financials ("3715000.00" → "3,715,000.00 AED").

const isBlank = (v) => v === null || v === undefined || String(v).trim() === ''

export function formatDealAmount(value, currency = 'AED') {
  if (isBlank(value)) return '----'
  const n = Number(value)
  if (!Number.isFinite(n)) return String(value)
  return `${n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency || 'AED'}`
}

export function formatDealPercent(value) {
  if (isBlank(value)) return '----'
  const n = Number(value)
  if (!Number.isFinite(n)) return String(value)
  return `${n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} %`
}
