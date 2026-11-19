/**
 * Shared push-subscriptions/config fetch — App, navbar, bell, and home each mount
 * MobilePushToggle; one in-flight + short TTL avoids 3–4 identical GETs.
 */

import api from '@/plugins/axios'
import { dedupeRequest } from '@/composables/requestDedupe'

const TTL_MS = 60_000
let cached = null
let cachedAt = 0

/**
 * @param {{ force?: boolean }} [options]
 * @returns {Promise<object|null>}
 */
export function fetchPushSubscriptionConfig({ force = false } = {}) {
  if (!force && cached && Date.now() - cachedAt < TTL_MS) {
    return Promise.resolve(cached)
  }

  return dedupeRequest('push-subscriptions-config', async () => {
    const response = await api.get('/push-subscriptions/config')
    const data = response?.data?.data ?? null
    cached = data
    cachedAt = Date.now()
    return data
  })
}
