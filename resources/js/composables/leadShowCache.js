/**
 * Per-user in-memory SWR cache for GET /leads/{id}.
 * Never shared across users; invalidated on lead updates.
 */

import api from '@/plugins/axios'
import { dedupeRequest } from '@/composables/requestDedupe'

const cache = new Map()
/** Fresh enough to paint instantly without waiting on network. */
const TTL_MS = 60_000
/** Skip background revalidation when the entry is this fresh. */
const STALE_WHILE_REVALIDATE_MS = 2_500

function currentUserId() {
  try {
    const raw = localStorage.getItem('user')
    if (!raw) return 'anon'
    const id = Number(JSON.parse(raw)?.id)
    return Number.isFinite(id) && id > 0 ? String(id) : 'anon'
  } catch {
    return 'anon'
  }
}

function cacheKey(leadId) {
  return `${currentUserId()}:${Number(leadId)}`
}

/**
 * @returns {{ data: object, fetchedAt: number } | null}
 */
export function getCachedLead(leadId) {
  const key = cacheKey(leadId)
  const entry = cache.get(key)
  if (!entry) return null
  if (Date.now() - entry.fetchedAt > TTL_MS) {
    cache.delete(key)
    return null
  }
  return entry
}

/**
 * @param {number|string} leadId
 * @param {object} data
 * @param {{ fetchedAt?: number }} [options]
 */
export function setCachedLead(leadId, data, options = {}) {
  if (!leadId || !data || typeof data !== 'object') return
  const fetchedAt = Number(options.fetchedAt) || Date.now()
  const key = cacheKey(leadId)
  const existing = cache.get(key)
  // Never let an older in-flight GET overwrite a newer local edit / fresher response.
  if (existing && existing.fetchedAt > fetchedAt) {
    return
  }
  cache.set(key, {
    data: { ...data },
    fetchedAt,
  })
}

export function invalidateLeadCache(leadId = null) {
  if (leadId == null) {
    cache.clear()
    return
  }
  cache.delete(cacheKey(leadId))
}

/** Drop all entries for the signed-in user (logout / user switch). */
export function clearLeadCacheForCurrentUser() {
  const prefix = `${currentUserId()}:`
  for (const key of cache.keys()) {
    if (key.startsWith(prefix)) cache.delete(key)
  }
}

/**
 * Deduped GET /leads/{id}. Writes into the per-user cache on success.
 * @returns {Promise<object|null>}
 */
export function fetchLeadShow(leadId) {
  const id = Number(leadId)
  if (!id) return Promise.resolve(null)

  const key = `lead-show:${cacheKey(id)}`
  return dedupeRequest(key, async () => {
    const startedAt = Date.now()
    const response = await api.get(`/leads/${id}`)
    const fresh = response?.data?.data || null
    if (fresh) {
      setCachedLead(id, fresh, { fetchedAt: startedAt })
      // If a local edit landed while the GET was in flight, prefer that payload.
      const current = getCachedLead(id)
      return current?.data || fresh
    }
    return fresh
  })
}

export function shouldBackgroundRefresh(leadId) {
  const entry = getCachedLead(leadId)
  if (!entry) return true
  return Date.now() - entry.fetchedAt >= STALE_WHILE_REVALIDATE_MS
}
