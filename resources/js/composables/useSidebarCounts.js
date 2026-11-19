import { ref } from 'vue'
import api from '@/plugins/axios'

/**
 * Shared sidebar/listing badge counts for Header + Navbar.
 * One in-flight request, optional soft client freshness, single poll timer.
 */
const counts = ref(null)
const loading = ref(false)
const lastError = ref(null)

let inflight = null
let lastFetchedAt = 0
let pollTimer = null
let pollSubscribers = 0
let pollInFlight = false

const POLL_MS = 60000
/** Skip a brand-new HTTP call if we already fetched this recently (mount race). */
const CLIENT_FRESH_MS = 5000

async function fetchCounts({ force = false } = {}) {
  if (inflight) {
    return inflight
  }

  if (
    !force &&
    counts.value &&
    lastFetchedAt > 0 &&
    Date.now() - lastFetchedAt < CLIENT_FRESH_MS
  ) {
    return counts.value
  }

  inflight = (async () => {
    loading.value = true
    lastError.value = null
    try {
      const response = await api.get('/sidebar/counts')
      if (response.data?.success) {
        counts.value = response.data.data || null
        lastFetchedAt = Date.now()
      }
      return counts.value
    } catch (error) {
      lastError.value = error
      console.error('Error fetching sidebar counts:', error)
      throw error
    } finally {
      loading.value = false
      inflight = null
    }
  })()

  return inflight
}

// ---- Push (Echo) --------------------------------------------------------------------
// The server broadcasts `.sidebar.counts.changed` on crm-campaigns whenever a listing /
// access request / hot deal change can move a badge. While that push is live, the timer
// is only a slow safety net; without it we fall back to the old 60s poll.
const PUSH_SAFETY_MS = 10 * 60_000
// Spread refetches so ~100 open tabs don't all hit the server in the same second.
const PUSH_JITTER_MIN_MS = 1500
const PUSH_JITTER_MAX_MS = 7000

let echoBound = false
let echoReadyHandler = null
let pushTimer = null
let dirtyWhileHidden = false

function isHidden() {
  return typeof document !== 'undefined' && document.visibilityState === 'hidden'
}

function pushIsLive() {
  if (!echoBound) return false
  try {
    const state = window.Echo?.connector?.pusher?.connection?.state
    return state ? state === 'connected' : true
  } catch {
    return true
  }
}

function staleAfterMs() {
  return pushIsLive() ? PUSH_SAFETY_MS : POLL_MS
}

function onCountsPushed() {
  if (isHidden()) {
    dirtyWhileHidden = true
    return
  }
  if (pushTimer) return // one refetch covers a burst of events
  const delay = PUSH_JITTER_MIN_MS + Math.random() * (PUSH_JITTER_MAX_MS - PUSH_JITTER_MIN_MS)
  pushTimer = window.setTimeout(() => {
    pushTimer = null
    pollTick({ force: true })
  }, delay)
}

function bindEcho() {
  if (echoBound || !window.Echo || pollTimer == null) return
  try {
    window.Echo.private('crm-campaigns').listen('.sidebar.counts.changed', onCountsPushed)
    echoBound = true
  } catch {
    echoBound = false
  }
}

function unbindEcho() {
  if (echoReadyHandler) {
    window.removeEventListener('echo-ready', echoReadyHandler)
    echoReadyHandler = null
  }
  if (echoBound) {
    try {
      window.Echo?.private('crm-campaigns').stopListening('.sidebar.counts.changed', onCountsPushed)
    } catch {
      /* ignore */
    }
  }
  echoBound = false
  clearTimeout(pushTimer)
  pushTimer = null
}

// ---- Safety poll ----------------------------------------------------------------------

async function pollTick({ force = false } = {}) {
  if (pollInFlight) {
    return
  }
  // Background tabs don't need live badges; catch up on visibilitychange.
  if (isHidden()) {
    return
  }
  // Timer fires every minute, but with live push only refetch when the safety window passed.
  if (!force && Date.now() - lastFetchedAt < staleAfterMs()) {
    return
  }
  pollInFlight = true
  try {
    await fetchCounts({ force: true })
  } catch {
    /* logged in fetchCounts */
  } finally {
    pollInFlight = false
  }
}

function onVisibilityChange() {
  if (document.visibilityState !== 'visible' || pollTimer == null) {
    return
  }
  if (dirtyWhileHidden) {
    dirtyWhileHidden = false
    pollTick({ force: true })
    return
  }
  pollTick()
}

function startPolling() {
  pollSubscribers += 1
  if (pollTimer != null) {
    return
  }
  pollTimer = window.setInterval(() => pollTick(), POLL_MS)
  document.addEventListener('visibilitychange', onVisibilityChange)
  if (window.Echo) {
    bindEcho()
  } else {
    echoReadyHandler = () => bindEcho()
    window.addEventListener('echo-ready', echoReadyHandler)
  }
}

function stopPolling() {
  pollSubscribers = Math.max(0, pollSubscribers - 1)
  if (pollSubscribers === 0 && pollTimer != null) {
    window.clearInterval(pollTimer)
    pollTimer = null
    document.removeEventListener('visibilitychange', onVisibilityChange)
    unbindEcho()
  }
}

/** Clear module state (e.g. before logout navigation without full reload). */
function resetSidebarCounts() {
  counts.value = null
  loading.value = false
  lastError.value = null
  lastFetchedAt = 0
  inflight = null
}

export function useSidebarCounts() {
  return {
    counts,
    loading,
    lastError,
    fetchCounts,
    startPolling,
    stopPolling,
    resetSidebarCounts,
  }
}
