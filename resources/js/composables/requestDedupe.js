/**
 * Reuse an in-flight promise for identical keys so multiple components
 * requesting the same endpoint do not fan out duplicate HTTP calls.
 *
 * Keys must include user/permission context when the response is user-scoped.
 */

const inflight = new Map()

/**
 * @template T
 * @param {string} key
 * @param {() => Promise<T>} factory
 * @returns {Promise<T>}
 */
export function dedupeRequest(key, factory) {
  if (!key) return factory()
  const existing = inflight.get(key)
  if (existing) return existing

  const promise = Promise.resolve()
    .then(factory)
    .finally(() => {
      if (inflight.get(key) === promise) {
        inflight.delete(key)
      }
    })

  inflight.set(key, promise)
  return promise
}

export function clearDedupe(keyPrefix = null) {
  if (keyPrefix == null) {
    inflight.clear()
    return
  }
  for (const key of inflight.keys()) {
    if (String(key).startsWith(String(keyPrefix))) {
      inflight.delete(key)
    }
  }
}
