/**
 * Prefetch lazy route chunks so sidebar clicks do not wait on first download.
 */

const prefetched = new Set()

function unwrapComponent(comp) {
  if (!comp) return null
  if (typeof comp === 'function') return comp
  // vue-router async component shape: { default: () => import(...) }
  if (typeof comp === 'object' && typeof comp.default === 'function') {
    return comp.default
  }
  return null
}

/**
 * Kick off dynamic import() for every matched route record (no-op if already loaded).
 */
export function prefetchRoute(router, path) {
  if (!router || !path || typeof path !== 'string') return
  if (path.startsWith('javascript') || path === '#' || path.startsWith('#')) return

  const key = path.split('?')[0]
  if (prefetched.has(key)) return
  prefetched.add(key)

  try {
    const resolved = router.resolve(key)
    const records = resolved?.matched || []
    for (const record of records) {
      const components = record.components || {}
      for (const name of Object.keys(components)) {
        const loader = unwrapComponent(components[name])
        if (typeof loader === 'function') {
          Promise.resolve()
            .then(() => loader())
            .catch(() => {
              // Allow retry on next hover if the chunk failed (offline, etc.)
              prefetched.delete(key)
            })
        }
      }
    }
  } catch {
    prefetched.delete(key)
  }
}

export function prefetchRoutes(router, paths = []) {
  for (const path of paths) {
    prefetchRoute(router, path)
  }
}

export function useRoutePrefetch(router) {
  return {
    prefetchRoute: (path) => prefetchRoute(router, path),
    prefetchRoutes: (paths) => prefetchRoutes(router, paths),
  }
}
