import { onBeforeUnmount, onMounted, ref } from 'vue'

const MQ = '(max-width: 1099px)'
const RECENT_KEY = 'ofr_recent_searches'
const RECENT_MAX = 5

/** Match the page's existing mobile/desktop split at 1099px. */
export function useOfrMobile() {
  const isMobile = ref(typeof window !== 'undefined' ? window.matchMedia(MQ).matches : false)
  let mql = null

  const onChange = (e) => { isMobile.value = e.matches }

  onMounted(() => {
    mql = window.matchMedia(MQ)
    isMobile.value = mql.matches
    mql.addEventListener('change', onChange)
  })

  onBeforeUnmount(() => {
    mql?.removeEventListener('change', onChange)
  })

  return { isMobile }
}

export function loadRecentSearches() {
  try {
    const raw = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]')
    return Array.isArray(raw) ? raw.filter((s) => typeof s === 'string' && s.trim()).slice(0, RECENT_MAX) : []
  } catch {
    return []
  }
}

export function pushRecentSearch(query) {
  const q = String(query || '').trim()
  if (!q) return loadRecentSearches()
  const next = [q, ...loadRecentSearches().filter((s) => s.toLowerCase() !== q.toLowerCase())].slice(0, RECENT_MAX)
  try { localStorage.setItem(RECENT_KEY, JSON.stringify(next)) } catch { /* ignore */ }
  return next
}

/** Date helpers in local calendar (report uses UAE day boundaries server-side). */
function pad(n) {
  return String(n).padStart(2, '0')
}

export function toIsoDate(d) {
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

export function startOfDay(d = new Date()) {
  return new Date(d.getFullYear(), d.getMonth(), d.getDate())
}

export const DATE_PRESETS = Object.freeze([
  { key: 'today', label: 'Today' },
  { key: 'yesterday', label: 'Yesterday' },
  { key: 'week', label: 'This week' },
  { key: 'month', label: 'This month' },
  { key: 'last_month', label: 'Last month' },
  { key: 'quarter', label: 'This quarter' },
  { key: 'year', label: 'This year' },
])

export function datePresetRange(preset) {
  const today = startOfDay()
  const end = toIsoDate(today)

  if (preset === 'today') {
    return { date_from: end, date_to: end }
  }

  if (preset === 'yesterday') {
    const y = new Date(today)
    y.setDate(today.getDate() - 1)
    const iso = toIsoDate(y)
    return { date_from: iso, date_to: iso }
  }

  if (preset === 'week') {
    const day = today.getDay() // 0 Sun
    const mondayOffset = day === 0 ? -6 : 1 - day
    const from = new Date(today)
    from.setDate(today.getDate() + mondayOffset)
    return { date_from: toIsoDate(from), date_to: end }
  }

  if (preset === 'month') {
    return { date_from: toIsoDate(new Date(today.getFullYear(), today.getMonth(), 1)), date_to: end }
  }

  if (preset === 'last_month') {
    const from = new Date(today.getFullYear(), today.getMonth() - 1, 1)
    const to = new Date(today.getFullYear(), today.getMonth(), 0)
    return { date_from: toIsoDate(from), date_to: toIsoDate(to) }
  }

  if (preset === 'quarter') {
    const q = Math.floor(today.getMonth() / 3)
    return { date_from: toIsoDate(new Date(today.getFullYear(), q * 3, 1)), date_to: end }
  }

  if (preset === 'year') {
    return { date_from: toIsoDate(new Date(today.getFullYear(), 0, 1)), date_to: end }
  }

  return { date_from: '', date_to: '' }
}

/** Which preset matches the current from/to, or 'custom' / 'all'. */
export function detectDatePreset(dateFrom, dateTo) {
  const from = String(dateFrom || '')
  const to = String(dateTo || '')
  if (!from && !to) return 'all'
  for (const p of DATE_PRESETS) {
    const range = datePresetRange(p.key)
    if (range.date_from === from && range.date_to === to) return p.key
  }
  return 'custom'
}

function formatShort(iso) {
  if (!iso) return ''
  const d = new Date(`${String(iso).slice(0, 10)}T00:00:00`)
  if (Number.isNaN(d.getTime())) return String(iso)
  return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

/** Compact period label for the mobile header chip. */
export function formatPeriodLabel(dateFrom, dateTo) {
  const preset = detectDatePreset(dateFrom, dateTo)
  if (preset === 'all') return 'All time'
  const named = DATE_PRESETS.find((p) => p.key === preset)
  if (named) return named.label
  const from = formatShort(dateFrom)
  const to = formatShort(dateTo)
  if (from && to && from === to) return from
  if (from && to) return `${from} – ${to}`
  if (from) return `From ${from}`
  if (to) return `Until ${to}`
  return 'Custom'
}

export function formatSourceLabel(sources) {
  const list = Array.isArray(sources) ? sources.filter(Boolean) : []
  if (!list.length) return 'All sources'
  if (list.length === 1) return list[0]
  return `${list.length} sources`
}
