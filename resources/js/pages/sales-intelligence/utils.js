export function avatarUrl(avatar) {
  if (!avatar) return '/storage/users/user.png'
  if (String(avatar).startsWith('http')) return avatar
  return `/storage/${String(avatar).replace(/^\//, '')}`
}

export function formatFactor(name) {
  if (!name) return ''
  return String(name).replace(/_/g, ' ')
}

export function tierLabel(tier) {
  const t = String(tier || '').toLowerCase()
  if (t === 'hot') return 'Hot'
  if (t === 'warm') return 'Warm'
  if (t === 'cold') return 'Cold'
  return '—'
}

const PREVIEW_SCORE_KEY = 'si_last_preview_score'

export function rememberScore(userId, score) {
  try {
    const raw = sessionStorage.getItem(PREVIEW_SCORE_KEY)
    const map = raw ? JSON.parse(raw) : {}
    map[String(userId)] = { score, at: Date.now() }
    sessionStorage.setItem(PREVIEW_SCORE_KEY, JSON.stringify(map))
  } catch {
    /* ignore */
  }
}

export function previousRememberedScore(userId) {
  try {
    const raw = sessionStorage.getItem(PREVIEW_SCORE_KEY)
    const map = raw ? JSON.parse(raw) : {}
    const prev = map[String(userId)]
    return prev && typeof prev.score === 'number' ? prev.score : null
  } catch {
    return null
  }
}
