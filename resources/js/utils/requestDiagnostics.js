/**
 * Dev-only API request diagnostics. Enable with:
 *   localStorage.setItem('crm_net_diag', '1')
 * or Vite DEV mode + localStorage flag.
 * Never logs tokens, Authorization headers, or response bodies.
 */

const counts = new Map()
const recent = []
const MAX_RECENT = 80

function enabled() {
  if (typeof window === 'undefined') return false
  try {
    const flag = localStorage.getItem('crm_net_diag')
    if (flag === '1') return true
    if (flag === 'dev' && import.meta.env?.DEV) return true
  } catch {
    /* private mode */
  }
  return false
}

function fingerprint(config) {
  const method = String(config?.method || 'get').toUpperCase()
  const url = String(config?.url || '')
  const params = config?.params ? JSON.stringify(config.params) : ''
  return `${method} ${url}?${params}`
}

export function attachRequestDiagnostics(client) {
  if (!client || client.__crmNetDiagAttached) return
  client.__crmNetDiagAttached = true

  client.interceptors.request.use((config) => {
    if (!enabled()) return config
    config.__crmDiagStarted = performance.now()
    const key = fingerprint(config)
    counts.set(key, (counts.get(key) || 0) + 1)
    return config
  })

  client.interceptors.response.use(
    (response) => {
      if (!enabled()) return response
      record(response.config, response.status)
      return response
    },
    (error) => {
      if (enabled()) {
        record(error.config, error.response?.status || 0)
      }
      return Promise.reject(error)
    }
  )
}

function record(config, status) {
  if (!config) return
  const key = fingerprint(config)
  const started = config.__crmDiagStarted
  const durationMs = started != null ? Math.round(performance.now() - started) : null
  const entry = {
    method: String(config.method || 'get').toUpperCase(),
    url: String(config.url || ''),
    status,
    durationMs,
    duplicateCount: counts.get(key) || 1,
    at: new Date().toISOString(),
  }
  recent.push(entry)
  if (recent.length > MAX_RECENT) recent.shift()
  // eslint-disable-next-line no-console
  console.debug('[crm-net]', entry.method, entry.url, entry.status, `${entry.durationMs}ms`, `n=${entry.duplicateCount}`)
}

export function getRequestDiagnosticsSnapshot() {
  return {
    recent: [...recent],
    counts: Object.fromEntries(counts),
  }
}

if (typeof window !== 'undefined') {
  window.__crmNetDiag = getRequestDiagnosticsSnapshot
}
