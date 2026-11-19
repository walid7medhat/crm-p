import api from '@/plugins/axios'

let pending = null
let settledResponse = null
let requestId = 0

function isPlainLeadBoardParams(params) {
  if (!params || typeof params !== 'object') return false
  const keys = Object.keys(params).filter((key) => {
    const value = params[key]
    return value != null && value !== ''
  })
  // Default first board page: per_page=15, optional page=1 only.
  // leads.vue always sends page:1 — treating that as a mismatch caused a second full board GET.
  if (!keys.includes('per_page') || Number(params.per_page) !== 15) return false
  for (const key of keys) {
    if (key === 'per_page') continue
    if (key === 'page' && Number(params.page) === 1) continue
    return false
  }
  return true
}

function takeEarlyBrowserPrefetch() {
  if (typeof window === 'undefined') return null
  const early = window.__leadBoardEarlyPrefetch
  const settled = window.__leadBoardEarlyResponse || null
  window.__leadBoardEarlyPrefetch = null
  window.__leadBoardEarlyResponse = null
  if (settled) settledResponse = settled
  if (early && typeof early.then === 'function') return early
  if (settled) return Promise.resolve(settled)
  return null
}

/** Start the default Lead board request before the rest of the page. */
export function prefetchLeadBoard() {
  if (!pending) {
    const id = ++requestId
    settledResponse = null
    const early = takeEarlyBrowserPrefetch()
    const request = early || api.get('/stages/kanban/stages-with-leads', { params: { per_page: 15 } })
    pending = Promise.resolve(request)
      .then((response) => {
        if (id === requestId) settledResponse = response
        return response
      })
    pending.catch(() => {})
  }
  return pending
}

/**
 * Already-resolved default board response, still owned by the in-flight prefetch.
 * Does not start or consume the request — takeLeadBoardPrefetch() still does that.
 */
export function peekSettledLeadBoardPrefetch(params) {
  if (!pending || settledResponse == null) return null
  return isPlainLeadBoardParams(params) ? settledResponse : null
}

/** In-flight default board request. Does not consume it. */
export function peekLeadBoardPrefetchPromise(params) {
  if (!pending || !isPlainLeadBoardParams(params)) return null
  return pending
}

/**
 * Use the early request only for the unfiltered first load.
 * A search or shortcut fetch keeps its own request.
 */
export function takeLeadBoardPrefetch(params) {
  if (!pending) return null
  // Do not clear pending on param mismatch — a filtered fetch must not
  // discard the shared default-board prefetch for a later consumer.
  if (!isPlainLeadBoardParams(params)) return null
  const promise = pending
  pending = null
  return promise
}
