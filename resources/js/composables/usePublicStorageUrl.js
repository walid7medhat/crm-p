/**
 * Laravel public-disk files are often returned as full URLs built with APP_URL,
 * which may not match the host where the SPA runs (e.g. API vs MAMP). For any
 * path that lives under /storage/, rewrite to the current origin so img, iframe,
 * and new-tab View use the same host as the CRM.
 */
export function normalizePublicStorageUrl(input) {
  if (input == null || input === '') return null
  const s = String(input).trim()
  if (s.startsWith('blob:') || s.startsWith('data:')) return s

  const marker = '/storage/'
  const lower = s.toLowerCase()
  const idx = lower.indexOf(marker)

  let relativePath = ''

  if (idx !== -1) {
    relativePath = s.slice(idx + marker.length)
  } else if (/^https?:\/\//i.test(s)) {
    return s
  } else {
    relativePath = s.replace(/^\/+/, '').replace(/^storage\//i, '')
  }

  relativePath = (relativePath || '').split('#')[0]
  if (!relativePath) return null

  const tail = relativePath.replace(/^\/+/, '')
  const origin = typeof window !== 'undefined' && window.location?.origin
  if (!origin) return `/storage/${tail}`

  return `${origin}/storage/${tail}`
}
