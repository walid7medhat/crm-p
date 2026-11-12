/**
 * Live Watch client: heartbeat for the monitored user + navigation/modal tracking.
 * Fire-and-forget; never blocks route or lead opening.
 */
import api, { resolveAuthToken } from '@/plugins/axios'
import { showLeadViewModal, leadViewModalId } from '@/composables/useLeadViewModal'
import { showDealViewModal, dealViewPayload } from '@/composables/useDealViewModal'
import { watch } from 'vue'

const SESSION_KEY = 'live_watch_session_id'
let started = false
let heartbeatTimer = null
let intervalMs = 30_000
let heartbeatEnabled = false
let visibilityHandler = null
let routerUnsub = null
let lastPath = null
let lastLeadId = null
let lastDealId = null
let leadWatchStop = null
let dealWatchStop = null
let configPollTimer = null

function isAuthenticated() {
  try {
    return Boolean(resolveAuthToken())
  } catch {
    return false
  }
}

function isImpersonating() {
  try {
    return Boolean(localStorage.getItem('impersonator_token'))
  } catch {
    return false
  }
}

function sessionId() {
  try {
    let id = sessionStorage.getItem(SESSION_KEY)
    if (!id) {
      id = (crypto?.randomUUID?.() || `s-${Date.now()}-${Math.random().toString(16).slice(2)}`).slice(0, 64)
      sessionStorage.setItem(SESSION_KEY, id)
    }
    return id
  } catch {
    return `s-${Date.now()}`
  }
}

function deviceLabel() {
  try {
    return (navigator.userAgent || 'browser').slice(0, 64)
  } catch {
    return 'browser'
  }
}

function postNav(payload) {
  if (!isAuthenticated() || isImpersonating() || !heartbeatEnabled) return
  const body = {
    session_id: sessionId(),
    client_event_id: payload.client_event_id
      || `${payload.event_type}-${payload.action}-${payload.entity_id || ''}-${Date.now()}`,
    ...payload,
  }
  // Non-blocking; ignore failures.
  api.post('/auth/live-watch/navigation', body).catch(() => {})
}

function moduleFromPath(path) {
  if (!path) return 'app'
  if (path === '/' || path.startsWith('/home')) return 'dashboard'
  if (path.startsWith('/kanban_deal') || path.includes('deal')) return 'deals'
  if (path.startsWith('/kanban') || path.includes('lead')) return 'leads'
  if (path.startsWith('/marketing')) return 'marketing'
  if (path.startsWith('/hr')) return 'hr'
  if (path.startsWith('/alllisting') || path.startsWith('/property') || path.startsWith('/my-listing')) return 'listings'
  if (path.startsWith('/tracking') || path.startsWith('/settings') || path.startsWith('/logs')) return 'settings'
  return 'app'
}

async function refreshConfig() {
  if (!isAuthenticated() || isImpersonating()) {
    heartbeatEnabled = false
    return
  }
  try {
    const { data } = await api.get('/auth/live-watch/client-config')
    const cfg = data?.data || {}
    heartbeatEnabled = Boolean(cfg.heartbeat_enabled)
    const seconds = Number(cfg.heartbeat_interval_seconds)
    if (Number.isFinite(seconds) && seconds >= 15) {
      intervalMs = seconds * 1000
    }
  } catch {
    heartbeatEnabled = false
  }
}

async function sendHeartbeat() {
  if (!heartbeatEnabled || !isAuthenticated() || isImpersonating()) return
  if (typeof document !== 'undefined' && document.visibilityState === 'hidden') return
  try {
    await api.post('/auth/live-watch/heartbeat', {
      session_id: sessionId(),
      device_label: deviceLabel(),
    })
  } catch {
    /* soft-fail */
  }
}

function scheduleHeartbeat() {
  clearInterval(heartbeatTimer)
  heartbeatTimer = null
  if (!started || !heartbeatEnabled) return
  sendHeartbeat()
  heartbeatTimer = window.setInterval(sendHeartbeat, intervalMs)
}

function leaveBeacon() {
  if (!heartbeatEnabled || !isAuthenticated()) return
  try {
    const token = resolveAuthToken()
    if (!token) return
    const body = JSON.stringify({ session_id: sessionId() })
    const url = `${window.location.origin}/api/auth/live-watch/leave`
    if (navigator.sendBeacon) {
      const blob = new Blob([body], { type: 'application/json' })
      // sendBeacon cannot set Authorization; fall back to fetch keepalive.
    }
    fetch(url, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token}`,
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body,
      keepalive: true,
    }).catch(() => {})
  } catch {
    /* ignore */
  }
}

function trackRoute(to, from) {
  if (!heartbeatEnabled) return
  const toPath = to?.path || ''
  const fromPath = from?.path || ''

  if (fromPath && fromPath !== toPath) {
    postNav({
      action: 'leave',
      event_type: 'route.leave',
      module: moduleFromPath(fromPath),
      path: fromPath,
      client_event_id: `route-leave-${fromPath}-${Date.now()}`,
    })
  }

  if (toPath && toPath !== lastPath) {
    lastPath = toPath
    postNav({
      action: 'enter',
      event_type: 'route.enter',
      module: moduleFromPath(toPath),
      path: toPath,
      client_event_id: `route-enter-${toPath}-${Date.now()}`,
    })
  }

  // Listing detail route
  if (to?.name === 'property.show' || toPath.startsWith('/property-details/')) {
    const id = Number(to?.params?.id)
    if (id) {
      postNav({
        action: 'open',
        event_type: 'listing.open',
        module: 'listings',
        entity_type: 'listing',
        entity_id: id,
        path: toPath,
        client_event_id: `listing-open-${id}-${Date.now()}`,
      })
    }
  }
}

function bindModalWatches() {
  leadWatchStop?.()
  dealWatchStop?.()

  leadWatchStop = watch(
    () => [showLeadViewModal.value, leadViewModalId.value],
    ([open, id], [prevOpen, prevId]) => {
      if (!heartbeatEnabled) return
      if (open && id && (!prevOpen || Number(prevId) !== Number(id))) {
        lastLeadId = Number(id)
        postNav({
          action: 'open',
          event_type: 'lead.open',
          module: 'leads',
          entity_type: 'lead',
          entity_id: Number(id),
          client_event_id: `lead-open-${id}-${Date.now()}`,
        })
      } else if (!open && prevOpen && lastLeadId) {
        postNav({
          action: 'close',
          event_type: 'lead.close',
          module: 'leads',
          entity_type: 'lead',
          entity_id: lastLeadId,
          client_event_id: `lead-close-${lastLeadId}-${Date.now()}`,
        })
        lastLeadId = null
      }
    },
  )

  dealWatchStop = watch(
    () => [showDealViewModal.value, dealViewPayload.value?.id ?? null],
    ([open, id], [prevOpen, prevId]) => {
      if (!heartbeatEnabled) return
      if (open && id && (!prevOpen || Number(prevId) !== Number(id))) {
        lastDealId = Number(id)
        postNav({
          action: 'open',
          event_type: 'deal.open',
          module: 'deals',
          entity_type: 'deal',
          entity_id: Number(id),
          client_event_id: `deal-open-${id}-${Date.now()}`,
        })
      } else if (!open && prevOpen && lastDealId) {
        postNav({
          action: 'close',
          event_type: 'deal.close',
          module: 'deals',
          entity_type: 'deal',
          entity_id: lastDealId,
          client_event_id: `deal-close-${lastDealId}-${Date.now()}`,
        })
        lastDealId = null
      }
    },
  )
}

export async function startLiveWatchClient(router) {
  if (started) return
  started = true

  await refreshConfig()
  scheduleHeartbeat()
  bindModalWatches()

  visibilityHandler = () => {
    if (document.visibilityState === 'visible') {
      sendHeartbeat()
    }
  }
  document.addEventListener('visibilitychange', visibilityHandler)
  window.addEventListener('pagehide', leaveBeacon)

  if (router) {
    routerUnsub = router.afterEach((to, from) => {
      // Defer so navigation paint is never blocked.
      queueMicrotask(() => trackRoute(to, from))
    })
  }

  // Config may change when SA selects this user — light poll.
  configPollTimer = window.setInterval(async () => {
    const before = heartbeatEnabled
    await refreshConfig()
    if (before !== heartbeatEnabled) scheduleHeartbeat()
  }, 60_000)
}

export function stopLiveWatchClient() {
  if (!started) return
  leaveBeacon()
  started = false
  heartbeatEnabled = false
  clearInterval(heartbeatTimer)
  heartbeatTimer = null
  clearInterval(configPollTimer)
  configPollTimer = null
  if (visibilityHandler) {
    document.removeEventListener('visibilitychange', visibilityHandler)
    visibilityHandler = null
  }
  window.removeEventListener('pagehide', leaveBeacon)
  routerUnsub?.()
  routerUnsub = null
  leadWatchStop?.()
  dealWatchStop?.()
  leadWatchStop = null
  dealWatchStop = null
}
