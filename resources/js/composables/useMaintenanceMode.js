import { computed, ref } from 'vue'
import api, { resolveAuthToken } from '@/plugins/axios'

const enabled = ref(false)
const scope = ref('all')
const branchUserIds = ref([])
const title = ref("We'll Be Back Soon")
const message = ref('')
const estimatedReturn = ref('')
const affectsMe = ref(false)
const bootstrapped = ref(false)

let pollTimer = null
let visibilityBound = false
let lastFetchAt = 0

// Echo pushes `.maintenance.updated` instantly; polling is only a fallback.
const POLL_MS = 120000
const POLL_WITH_PUSH_MS = 10 * 60_000
let echoBoundForMaintenance = false

function maintenancePushIsLive() {
  if (!echoBoundForMaintenance) return false
  try {
    const state = window.Echo?.connector?.pusher?.connection?.state
    return state ? state === 'connected' : true
  } catch {
    return true
  }
}
const VISIBLE_REFETCH_MIN_MS = 15000

function isStoredSuperAdmin() {
  try {
    const raw = localStorage.getItem('user')
    if (!raw) return false
    const user = JSON.parse(raw)
    const roles = user?.roles || []
    return Array.isArray(roles) && roles.includes('super_admin')
  } catch {
    return false
  }
}

function isSwitchedBySuperAdmin() {
  try {
    const raw = localStorage.getItem('impersonator_user')
    if (!raw) return false
    const user = JSON.parse(raw)
    const roles = user?.roles || []
    return Array.isArray(roles) && roles.includes('super_admin')
  } catch {
    return false
  }
}

function applyPayload(payload) {
  if (!payload || typeof payload !== 'object') return
  enabled.value = !!payload.enabled
  if (payload.scope != null) scope.value = String(payload.scope || 'all')
  if (Array.isArray(payload.branch_user_ids)) {
    branchUserIds.value = payload.branch_user_ids.map((id) => Number(id)).filter((id) => id > 0)
  }
  if (payload.title != null) title.value = String(payload.title || "We'll Be Back Soon")
  if (payload.message != null) message.value = String(payload.message || '')
  if (payload.estimated_return != null) estimatedReturn.value = String(payload.estimated_return || '')
  if (typeof payload.affects_me === 'boolean') {
    affectsMe.value = payload.affects_me
  } else if (!payload.enabled) {
    affectsMe.value = false
  } else if (payload.scope === 'branches' || payload.scope === 'users') {
    affectsMe.value = false
  } else if (payload.scope === 'all' || !payload.scope) {
    affectsMe.value = !!resolveAuthToken() && !isStoredSuperAdmin() && !isSwitchedBySuperAdmin()
  }
  bootstrapped.value = true
}

/**
 * Block CRM chrome only for authenticated users who are actually affected.
 * Anonymous / sign-in visitors keep the login form so Super Admin can recover.
 */
const shouldBlockUi = computed(() => {
  if (!enabled.value) return false
  if (!resolveAuthToken()) return false
  if (isStoredSuperAdmin() || isSwitchedBySuperAdmin()) return false
  return !!affectsMe.value
})

export function useMaintenanceMode() {
  return {
    enabled,
    scope,
    branchUserIds,
    title,
    message,
    estimatedReturn,
    affectsMe,
    bootstrapped,
    shouldBlockUi,
    applyPayload,
    isStoredSuperAdmin,
    fetchStatus,
    startMaintenanceWatch,
    stopMaintenanceWatch,
    applyFromApiError,
  }
}

export async function fetchStatus() {
  lastFetchAt = Date.now()
  try {
    const res = await api.get('/maintenance/status')
    applyPayload(res?.data?.data || res?.data)
  } catch {
    // ignore transient failures
  }
}

export function applyFromApiError(error) {
  const status = error?.response?.status
  const body = error?.response?.data
  if (status === 503 && (body?.maintenance === true || body?.data?.enabled === true)) {
    applyPayload({
      enabled: true,
      scope: body?.data?.scope ?? 'all',
      branch_user_ids: body?.data?.branch_user_ids ?? [],
      title: body?.data?.title ?? body?.title,
      message: body?.data?.message ?? body?.message,
      estimated_return: body?.data?.estimated_return,
      affects_me: true,
    })
    return true
  }
  return false
}

export function startMaintenanceWatch() {
  fetchStatus()
  if (pollTimer) clearInterval(pollTimer)
  pollTimer = setInterval(() => {
    if (document.visibilityState === 'hidden') return
    // With live push this is only a safety net.
    if (maintenancePushIsLive() && Date.now() - lastFetchAt < POLL_WITH_PUSH_MS) return
    fetchStatus()
  }, POLL_MS)

  if (!visibilityBound && typeof document !== 'undefined') {
    visibilityBound = true
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState !== 'visible') return
      const minGap = maintenancePushIsLive() ? POLL_MS : VISIBLE_REFETCH_MIN_MS
      if (Date.now() - lastFetchAt < minGap) return
      fetchStatus()
    })
  }

  const bindEcho = () => {
    if (echoBoundForMaintenance || !window.Echo || !resolveAuthToken()) return
    try {
      window.Echo.private('crm-campaigns')
        .listen('.maintenance.updated', () => {
          // Re-fetch so affects_me is computed server-side for this user.
          fetchStatus()
        })
      echoBoundForMaintenance = true
    } catch {
      // ignore
    }
  }
  bindEcho()
  window.addEventListener('echo-ready', bindEcho)
}

export function stopMaintenanceWatch() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}
