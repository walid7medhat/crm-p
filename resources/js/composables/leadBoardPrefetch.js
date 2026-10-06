import api from '@/plugins/axios'

let pending = null

function isPlainLeadBoardParams(params) {
  if (!params || typeof params !== 'object') return false
  const keys = Object.keys(params).filter((key) => {
    const value = params[key]
    return value != null && value !== ''
  })
  return keys.length === 1 && keys[0] === 'per_page' && Number(params.per_page) === 15
}

/** Start the default Lead board request before the rest of the page. */
export function prefetchLeadBoard() {
  if (!pending) {
    pending = api.get('/stages/kanban/stages-with-leads', { params: { per_page: 15 } })
  }
  return pending
}

/**
 * Use the early request only for the unfiltered first load.
 * A search or shortcut fetch keeps its own request.
 */
export function takeLeadBoardPrefetch(params) {
  if (!pending) return null
  const promise = pending
  pending = null
  return isPlainLeadBoardParams(params) ? promise : null
}
