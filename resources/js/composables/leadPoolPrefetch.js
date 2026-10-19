import api from '@/plugins/axios'

const CACHE_KEY = 'crm.leadPool.firstPage'
const MAX_AGE_MS = 3 * 60 * 1000

export const LEAD_POOL_FIRST_PAGE = {
  stage_id: 10,
  paginate: 1,
  page: 1,
  per_page: 24,
}

let pending = null
let settledBody = null
let settledAt = 0
let requestSeq = 0

export function isDefaultLeadPoolParams(params) {
  if (!params || typeof params !== 'object') return false
  const extra = Object.keys(params).filter((key) => {
    if (['stage_id', 'paginate', 'page', 'per_page'].includes(key)) return false
    const value = params[key]
    return value != null && value !== ''
  })
  if (extra.length) return false
  return Number(params.stage_id) === 10
    && String(params.paginate) === '1'
    && Number(params.page) === 1
    && Number(params.per_page) === LEAD_POOL_FIRST_PAGE.per_page
}

function remember(body) {
  settledBody = body
  settledAt = Date.now()
  try {
    sessionStorage.setItem(CACHE_KEY, JSON.stringify({ at: Date.now(), body }))
  } catch {
    /* private mode / quota */
  }
}

/** First page already in memory or from the last visit this session. */
export function readLeadPoolCache() {
  if (settledBody?.data) return settledBody
  try {
    const raw = sessionStorage.getItem(CACHE_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw)
    if (!parsed?.body?.data || !Array.isArray(parsed.body.data)) return null
    if (Date.now() - Number(parsed.at) > MAX_AGE_MS) return null
    settledBody = parsed.body
    settledAt = Number(parsed.at) || Date.now()
    return settledBody
  } catch {
    return null
  }
}

/**
 * One shared request for the default Lead Pool page.
 * Started when the Lead Pool tab is selected, so that tab can reuse the
 * in-flight result instead of waiting on a second request.
 */
export function prefetchLeadPool({ force = false } = {}) {
  if (!force && pending) return pending
  if (!force && settledBody?.data?.length && Date.now() - settledAt < 20000) {
    return Promise.resolve({ data: settledBody })
  }
  const seq = ++requestSeq
  const request = api.get('/leads', { params: { ...LEAD_POOL_FIRST_PAGE } })
    .then((response) => {
      if (seq === requestSeq && response?.data?.data) remember(response.data)
      return response
    })
    .finally(() => {
      if (pending === request) pending = null
    })
  pending = request
  return pending
}

export function rememberLeadPoolResponse(body) {
  if (body?.data) remember(body)
}
