import { ref, shallowRef, watch, onMounted, onUnmounted } from 'vue'
import fallback from '../projectMapData.json'

/**
 * Origin of the Vite dev server (from the @vite/client script tag), or '' if unknown.
 * Replaces 0.0.0.0 with 127.0.0.1 — browsers may fail or mis-resolve fetches to 0.0.0.0.
 */
export function getViteDevOrigin() {
  if (typeof document === 'undefined') return ''
  const el = document.querySelector('script[src*="@vite/client"]')
  if (el?.src) {
    try {
      const u = new URL(el.src)
      if (u.hostname === '0.0.0.0') {
        u.hostname = '127.0.0.1'
      }
      return u.origin
    } catch {
      return ''
    }
  }
  return ''
}

/**
 * Live data from `/@project-map/data` when the Vite dev server + plugin are active;
 * falls back to bundled `projectMapData.json` (e.g. production build or Laravel-only).
 */
export function useProjectMapState() {
  const data = shallowRef(fallback)
  const loading = ref(false)
  const query = ref('')
  const liveSync = ref(true)
  const streamConnected = ref(false)
  const error = ref(null)

  let eventSource = null
  let pollTimer = null
  /** Catches missed FS events (some renames) when live sync is on */
  let safetyPollTimer = null

  function disconnectStream() {
    try {
      eventSource?.close()
    } catch {
      /* ignore */
    }
    eventSource = null
    streamConnected.value = false
  }

  function clearPoll() {
    if (pollTimer) {
      clearInterval(pollTimer)
      pollTimer = null
    }
  }

  function clearSafetyPoll() {
    if (safetyPollTimer) {
      clearInterval(safetyPollTimer)
      safetyPollTimer = null
    }
  }

  function startSafetyPoll() {
    clearSafetyPoll()
    if (!import.meta.env.DEV || !liveSync.value) return
    safetyPollTimer = setInterval(() => {
      refresh()
    }, 15000)
  }

  async function refresh() {
    if (!import.meta.env.DEV) {
      data.value = fallback
      return
    }
    loading.value = true
    error.value = null
    const base = getViteDevOrigin()
    const url = base ? `${base}/@project-map/data` : '/@project-map/data'
    try {
      const r = await fetch(`${url}?t=${Date.now()}`, {
        credentials: 'omit',
        mode: 'cors',
        cache: 'no-store',
      })
      if (r.ok) {
        data.value = await r.json()
      }
    } catch (e) {
      error.value = String(e?.message || e)
    } finally {
      loading.value = false
    }
  }

  function connectStream() {
    disconnectStream()
    clearPoll()
    clearSafetyPoll()
    if (!import.meta.env.DEV || !liveSync.value) return
    const base = getViteDevOrigin()
    if (!base) {
      pollTimer = setInterval(refresh, 6000)
      return
    }
    try {
      eventSource = new EventSource(`${base}/@project-map/stream`)
      eventSource.onmessage = (ev) => {
        streamConnected.value = true
        try {
          const p = JSON.parse(ev.data)
          if (p?.type === 'update' || p?.type === 'connected') {
            if (p?.type === 'update') refresh()
          }
        } catch {
          refresh()
        }
      }
      eventSource.onerror = () => {
        streamConnected.value = false
        disconnectStream()
        if (liveSync.value && !pollTimer) {
          pollTimer = setInterval(refresh, 5000)
        }
      }
    } catch {
      pollTimer = setInterval(refresh, 6000)
    }
  }

  onMounted(() => {
    refresh().finally(() => {
      if (liveSync.value) {
        connectStream()
        startSafetyPoll()
      }
    })
  })

  watch(liveSync, (on) => {
    disconnectStream()
    clearPoll()
    clearSafetyPoll()
    if (on) {
      connectStream()
      startSafetyPoll()
    }
  })

  onUnmounted(() => {
    disconnectStream()
    clearPoll()
    clearSafetyPoll()
  })

  return {
    data,
    loading,
    query,
    liveSync,
    streamConnected,
    error,
    refresh,
    getViteDevOrigin,
  }
}
