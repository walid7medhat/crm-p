/**
 * Foreground location tracking tied to CRM online presence — not to login.
 *
 * Presence in this CRM = authenticated API activity refreshing last_login_at
 * (JwtAuthMiddleware, ~5 min) and "online" within a 15-minute window.
 *
 * This composable captures when the already-authenticated CRM becomes active
 * (layout shown / tab visible again) and polls while visible. It does not run
 * when the tab/PWA is hidden, suspended, or closed.
 */
import api, { resolveAuthToken } from '@/plugins/axios'
import { captureDeviceLocation } from '@/utils/deviceLocation.mjs'

const DEFAULT_INTERVAL_MS = 180_000
const DEFAULT_ONLINE_WINDOW_MS = 15 * 60_000

let started = false
let timerId = null
let inFlight = false
let intervalMs = DEFAULT_INTERVAL_MS
let onlineWindowMs = DEFAULT_ONLINE_WINDOW_MS
let visibilityHandler = null
let settingsPromise = null
/** Timestamp when the document last became hidden (ms), or null if visible. */
let hiddenAt = null
/** Last successful location POST (ms). Used to detect offline → online gaps. */
let lastPostedAt = null

function isAuthenticated() {
  try {
    return Boolean(resolveAuthToken())
  } catch {
    return false
  }
}

async function loadSettings() {
  if (settingsPromise) return settingsPromise
  settingsPromise = (async () => {
    try {
      const { data } = await api.get('/auth/location-settings')
      const seconds = Number(data?.data?.interval_seconds)
      if (Number.isFinite(seconds) && seconds >= 60) {
        intervalMs = seconds * 1000
      }
      const windowMinutes = Number(data?.data?.online_window_minutes)
      if (Number.isFinite(windowMinutes) && windowMinutes >= 1) {
        onlineWindowMs = windowMinutes * 60_000
      }
    } catch {
      intervalMs = DEFAULT_INTERVAL_MS
      onlineWindowMs = DEFAULT_ONLINE_WINDOW_MS
    }
  })()
  return settingsPromise
}

/**
 * True when this capture should mark a new online session:
 * first post in this browser session, or gap since last post ≥ online window,
 * or the tab was hidden longer than the online window.
 */
function shouldMarkSessionStart() {
  if (lastPostedAt == null) return true
  const now = Date.now()
  if (now - lastPostedAt >= onlineWindowMs) return true
  if (hiddenAt != null && now - hiddenAt >= onlineWindowMs) return true
  return false
}

async function postFix(coords, { sessionStart = false } = {}) {
  if (!coords || !isAuthenticated()) return
  try {
    await api.post('/auth/location', {
      latitude: coords.latitude,
      longitude: coords.longitude,
      accuracy: coords.accuracy,
      captured_at: coords.capturedAt,
      session_start: sessionStart || undefined,
    })
    lastPostedAt = Date.now()
  } catch {
    /* Soft-fail: offline / denied / validation must not break the CRM. */
  }
}

async function captureAndSend({ sessionStart = false } = {}) {
  if (inFlight || document.hidden || !isAuthenticated()) return
  if (!navigator?.geolocation || window.isSecureContext === false) return

  const markStart = sessionStart || shouldMarkSessionStart()
  inFlight = true
  try {
    const coords = await captureDeviceLocation(navigator.geolocation, {
      maxWait: 8000,
    })
    await postFix(coords, { sessionStart: markStart })
  } catch {
    /* Permission denied / timeout — leave previous server location alone. */
  } finally {
    inFlight = false
  }
}

function clearTimer() {
  if (timerId != null) {
    clearInterval(timerId)
    timerId = null
  }
}

function armTimer() {
  clearTimer()
  if (document.hidden || !isAuthenticated()) return
  timerId = window.setInterval(() => {
    captureAndSend()
  }, intervalMs)
}

/**
 * Start (or resume) tracking for an authenticated CRM session.
 * Fires immediately when the app is visible so already-logged-in users
 * get a point when they become active — no second login required.
 */
export async function startLocationTracking() {
  if (typeof window === 'undefined') return
  if (!isAuthenticated()) return

  await loadSettings()

  if (!started) {
    started = true
    hiddenAt = document.hidden ? Date.now() : null
    visibilityHandler = () => {
      if (document.hidden) {
        hiddenAt = Date.now()
        clearTimer()
        return
      }
      // Tab/PWA visible again → treat as potential offline→online transition.
      const wasAwayLong = hiddenAt != null && Date.now() - hiddenAt >= onlineWindowMs
      hiddenAt = null
      captureAndSend({ sessionStart: wasAwayLong || shouldMarkSessionStart() })
      armTimer()
    }
    document.addEventListener('visibilitychange', visibilityHandler)
  }

  if (!document.hidden) {
    // Capture now: page restore, reconnect, or first paint of an existing JWT session.
    captureAndSend({ sessionStart: shouldMarkSessionStart() })
    armTimer()
  }
}

export function stopLocationTracking() {
  clearTimer()
  if (visibilityHandler) {
    document.removeEventListener('visibilitychange', visibilityHandler)
    visibilityHandler = null
  }
  started = false
  settingsPromise = null
  inFlight = false
  hiddenAt = null
  // Keep lastPostedAt so a quick logout/login in the same tab still uses
  // the server online-window rule; cleared on full page unload naturally.
}

export function pauseLocationTracking() {
  clearTimer()
}
