/**
 * Foreground location tracking tied to CRM online presence — not to login.
 *
 * Presence in this CRM = authenticated API activity refreshing last_login_at
 * (JwtAuthMiddleware, ~5 min) and "online" within a 15-minute window.
 *
 * This composable captures when the already-authenticated CRM becomes active
 * (layout shown / tab visible again) and polls while visible. It does not run
 * when the tab/PWA is hidden, suspended, or closed.
 *
 * Phase 2.5: single-flight, skip unchanged coords, client cool-down, 429 backoff.
 */
import api, { resolveAuthToken } from '@/plugins/axios'
import { captureDeviceLocation } from '@/utils/deviceLocation.mjs'

const DEFAULT_INTERVAL_MS = 180_000
const DEFAULT_ONLINE_WINDOW_MS = 15 * 60_000
/** Client-side minimum gap between POSTs (aligned with server write_cooldown). */
const MIN_POST_GAP_MS = 45_000
/** Skip POST when movement is below this (metres). */
const MIN_MOVE_METERS = 50

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
/** Last successfully posted coordinates. */
let lastPostedCoords = null
/** Do not POST until this timestamp (429 / Retry-After). */
let backoffUntil = 0
let backoffMs = 0

function isAuthenticated() {
  try {
    return Boolean(resolveAuthToken())
  } catch {
    return false
  }
}

/** Super admin "switch account" session — never send the device location. */
function isImpersonating() {
  try {
    return Boolean(localStorage.getItem('impersonator_token'))
  } catch {
    return false
  }
}

function haversineMeters(a, b) {
  if (!a || !b) return Infinity
  const toRad = (d) => (d * Math.PI) / 180
  const R = 6371000
  const dLat = toRad(b.latitude - a.latitude)
  const dLng = toRad(b.longitude - a.longitude)
  const lat1 = toRad(a.latitude)
  const lat2 = toRad(b.latitude)
  const h =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2
  return 2 * R * Math.asin(Math.min(1, Math.sqrt(h)))
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

function shouldSkipUnchanged(coords, sessionStart) {
  if (sessionStart) return false
  if (!lastPostedCoords || lastPostedAt == null) return false
  const moved = haversineMeters(lastPostedCoords, coords)
  if (moved >= MIN_MOVE_METERS) return false
  return Date.now() - lastPostedAt < Math.max(intervalMs, MIN_POST_GAP_MS)
}

function applyBackoffFromError(error) {
  const status = error?.response?.status
  if (status !== 429) return
  const retryAfter = Number(error?.response?.headers?.['retry-after'])
  const fromHeader = Number.isFinite(retryAfter) && retryAfter > 0 ? retryAfter * 1000 : 0
  backoffMs = fromHeader || Math.min(Math.max(backoffMs * 2 || 15_000, 15_000), 120_000)
  backoffUntil = Date.now() + backoffMs
}

async function postFix(coords, { sessionStart = false } = {}) {
  // Re-check: a capture started before "switch account" may resolve after it.
  if (!coords || !isAuthenticated() || isImpersonating()) return
  if (Date.now() < backoffUntil) return
  if (!sessionStart && lastPostedAt != null && Date.now() - lastPostedAt < MIN_POST_GAP_MS) {
    return
  }
  if (shouldSkipUnchanged(coords, sessionStart)) return

  try {
    await api.post('/auth/location', {
      latitude: coords.latitude,
      longitude: coords.longitude,
      accuracy: coords.accuracy,
      captured_at: coords.capturedAt,
      session_start: sessionStart || undefined,
    })
    lastPostedAt = Date.now()
    lastPostedCoords = {
      latitude: Number(coords.latitude),
      longitude: Number(coords.longitude),
    }
    backoffMs = 0
    backoffUntil = 0
  } catch (error) {
    applyBackoffFromError(error)
    /* Soft-fail: offline / denied / validation / 429 must not break the CRM. */
  }
}

async function captureAndSend({ sessionStart = false, force = false } = {}) {
  if (inFlight || document.hidden || !isAuthenticated() || isImpersonating()) return
  if (!navigator?.geolocation || window.isSecureContext === false) return
  if (!force && Date.now() < backoffUntil) return
  if (
    !force &&
    !sessionStart &&
    lastPostedAt != null &&
    Date.now() - lastPostedAt < MIN_POST_GAP_MS
  ) {
    return
  }

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
  if (!isAuthenticated() || isImpersonating()) return

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
    // Avoid hammering /auth/location when showLayout re-triggers start.
    const recentlyPosted =
      lastPostedAt != null && Date.now() - lastPostedAt < MIN_POST_GAP_MS
    if (!recentlyPosted) {
      captureAndSend({ sessionStart: shouldMarkSessionStart() })
    }
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
  // Keep lastPostedAt / lastPostedCoords so a quick logout/login in the same tab
  // still respects cool-down; cleared on full page unload naturally.
}

export function pauseLocationTracking() {
  clearTimer()
}
