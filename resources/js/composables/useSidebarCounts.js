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

async function pollTick() {
  if (pollInFlight) {
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

function startPolling() {
  pollSubscribers += 1
  if (pollTimer != null) {
    return
  }
  pollTimer = window.setInterval(pollTick, POLL_MS)
}

function stopPolling() {
  pollSubscribers = Math.max(0, pollSubscribers - 1)
  if (pollSubscribers === 0 && pollTimer != null) {
    window.clearInterval(pollTimer)
    pollTimer = null
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
