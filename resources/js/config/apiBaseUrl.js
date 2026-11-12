/**
 * Runtime API base URL for multi-host white-label (OIA + Luxvant).
 *
 * Browser (production / same-app hosts): always same-origin `/api`
 * so each brand host calls its own /api path (no cross-host CORS).
 * Never bake APP_URL / VITE absolute production hosts into the client bundle.
 *
 * Local Vite DEV only: honor VITE_API_BASE_URL / VITE_API_URL when set
 * (e.g. Vite on :5173 → Laravel on :8000).
 */

export function getApiBaseUrl() {
  if (typeof window !== 'undefined') {
    const injected = window.__API_BASE_URL__
    if (typeof injected === 'string' && injected.trim() !== '') {
      return injected.trim().replace(/\/$/, '') || '/api'
    }

    // Vite HMR / split-origin local only — never use baked absolute hosts in prod builds.
    if (import.meta.env.DEV) {
      const vite =
        import.meta.env.VITE_API_BASE_URL || import.meta.env.VITE_API_URL || ''
      if (typeof vite === 'string' && vite.trim() !== '') {
        return vite.trim().replace(/\/$/, '')
      }
    }

    return '/api'
  }

  const vite =
    import.meta.env.VITE_API_BASE_URL || import.meta.env.VITE_API_URL || ''
  if (typeof vite === 'string' && vite.trim() !== '') {
    return vite.trim().replace(/\/$/, '')
  }

  return 'http://127.0.0.1:8000/api'
}

export function getAppOrigin() {
  if (typeof window !== 'undefined' && window.location?.origin) {
    return window.location.origin
  }
  return getApiBaseUrl().replace(/\/api\/?$/, '')
}

export default getApiBaseUrl
