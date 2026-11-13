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
  } else if (payload.scope === 'all') {
    affectsMe.value = !!resolveAuthToken() && !isStoredSuperAdmin()
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
  if (isStoredSuperAdmin()) return false
  if (scope.value === 'branches') return !!affectsMe.value
  return true
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
    fetchStatus()
  }, 30000)

  if (!visibilityBound && typeof document !== 'undefined') {
    visibilityBound = true
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') fetchStatus()
    })
  }

  const bindEcho = () => {
    if (!window.Echo || !resolveAuthToken()) return
    try {
      window.Echo.private('crm-campaigns')
        .listen('.maintenance.updated', () => {
          // Re-fetch so affects_me is computed server-side for this user.
          fetchStatus()
        })
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
