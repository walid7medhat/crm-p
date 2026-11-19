/**
 * Shared Lead Pool assignment-status fetch.
 * Kanban shell + Lead Pool tab both need this — dedupe + short TTL avoids double GETs.
 */

import api from '@/plugins/axios'
import { dedupeRequest } from '@/composables/requestDedupe'

const TTL_MS = 8_000
let cached = null
let cachedAt = 0
let cachedUserId = null

function currentUserId() {
  try {
    const raw = localStorage.getItem('user')
    if (!raw) return null
    const id = Number(JSON.parse(raw)?.id)
    return Number.isFinite(id) && id > 0 ? id : null
  } catch {
    return null
  }
}

/**
 * @param {{ force?: boolean }} [options]
 * @returns {Promise<object|null>}
 */
export function fetchLeadPoolAssignmentStatus({ force = false } = {}) {
  const userId = currentUserId()
  if (!userId) return Promise.resolve(null)

  if (
    !force
    && cached
    && cachedUserId === userId
    && Date.now() - cachedAt < TTL_MS
  ) {
    return Promise.resolve(cached)
  }

  return dedupeRequest(`lead-pool-assignment-status:${userId}`, async () => {
    const response = await api.get('/lead-pool/assignment-status')
    const data = response?.data?.data ?? null
    cached = data
    cachedAt = Date.now()
    cachedUserId = userId
    return data
  })
}

export function invalidateLeadPoolAssignmentStatus() {
  cached = null
  cachedAt = 0
  cachedUserId = null
}
