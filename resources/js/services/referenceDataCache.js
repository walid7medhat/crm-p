/**
 * Shared reference-data cache + in-flight dedupe for dropdown catalogs.
 * Keys must include query shape so has_listings / search variants never collide.
 * Do not store user-private lead payloads here.
 */
import api from '@/plugins/axios'

const store = new Map()
const inflight = new Map()

const DEFAULT_TTL_MS = 10 * 60 * 1000

function cacheGet(key) {
  const hit = store.get(key)
  if (!hit) return null
  if (Date.now() - hit.at > hit.ttl) {
    store.delete(key)
    return null
  }
  return hit.data
}

function cacheSet(key, data, ttl = DEFAULT_TTL_MS) {
  store.set(key, { data, at: Date.now(), ttl })
  return data
}

export function invalidateReferenceData(prefix = null) {
  if (!prefix) {
    store.clear()
    inflight.clear()
    return
  }
  for (const key of [...store.keys()]) {
    if (key.startsWith(prefix)) store.delete(key)
  }
  for (const key of [...inflight.keys()]) {
    if (key.startsWith(prefix)) inflight.delete(key)
  }
}

async function load(key, loader, ttl = DEFAULT_TTL_MS) {
  const cached = cacheGet(key)
  if (cached != null) return cached
  if (inflight.has(key)) return inflight.get(key)

  const promise = Promise.resolve()
    .then(loader)
    .then((data) => cacheSet(key, data, ttl))
    .finally(() => {
      inflight.delete(key)
    })

  inflight.set(key, promise)
  return promise
}

function unwrapList(res) {
  let data = res?.data?.data
  if (!Array.isArray(data)) {
    data = Array.isArray(res?.data) ? res.data : []
  }
  return data
}

/** Areas catalog. Pass { has_listings: true } for the listings-scoped list. */
export function fetchAreasReference(params = {}) {
  const key = 'areas:' + JSON.stringify(params || {})
  return load(key, async () => {
    const res = await api.get('/listings/areas', { params })
    return unwrapList(res)
  })
}

export function fetchPropertyTypesReference(params = {}) {
  const key = 'property-types:' + JSON.stringify(params || {})
  return load(key, async () => {
    const res = await api.get('/listings/property-types', { params })
    return unwrapList(res)
  })
}
