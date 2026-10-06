import axios from 'axios'
import Swal from 'sweetalert2'
import { waitForLeadBoardPriority } from '../composables/leadBoardPriority.js'

export function getApiBaseUrl() {
  return (
    (typeof window !== 'undefined' && window.__API_BASE_URL__) ||
    import.meta.env.VITE_API_BASE_URL ||
    (typeof window !== 'undefined'
      ? `${window.location.origin}/api`
      : 'http://127.0.0.1:8000/api')
  )
}

export function getAppOrigin() {
  const base = getApiBaseUrl()
  return base.replace(/\/api\/?$/, '')
}

export function resolveAuthToken() {
  let token =
    localStorage.getItem('token') ||
    localStorage.getItem('access_token') ||
    sessionStorage.getItem('token')

  if (!token && typeof document !== 'undefined') {
    const cookies = document.cookie.split('; ')
    const tokenCookie = cookies.find((row) => row.startsWith('token='))
    const accessTokenCookie = cookies.find((row) => row.startsWith('access_token='))
    const raw = tokenCookie?.split('=').slice(1).join('=') || accessTokenCookie?.split('=').slice(1).join('=')
    token = raw ? decodeURIComponent(raw) : null
  }

  return token?.trim() || null
}

export function setAuthToken(token) {
  if (!token) return
  const value = String(token).trim()
  localStorage.setItem('token', value)
  sessionStorage.setItem('token', value)
}

export function clearAuthToken() {
  localStorage.removeItem('token')
  localStorage.removeItem('access_token')
  sessionStorage.removeItem('token')
}

let handlingUnauthorized = false

function requestHadAuthHeader(config) {
  const headers = config?.headers || {}
  const auth = headers.Authorization || headers.authorization || headers.get?.('Authorization')
  return Boolean(auth && String(auth).startsWith('Bearer '))
}

function isAuthEndpoint(url = '') {
  const path = String(url)
  return (
    path.includes('/auth/login') ||
    path.includes('/auth/register') ||
    path.includes('/auth/forgot') ||
    path.includes('/auth/reset')
  )
}

function isPublicAuthPage() {
  if (typeof window === 'undefined') return false
  const path = window.location.pathname || ''
  return (
    path.startsWith('/sign-in') ||
    path.startsWith('/sign-up') ||
    path.startsWith('/forgot-password') ||
    path.startsWith('/reset-password')
  )
}

async function handleUnauthorized(error) {
  if (handlingUnauthorized || isPublicAuthPage()) {
    return Promise.reject(error)
  }
  handlingUnauthorized = true

  const message = error?.response?.data?.message || 'Your session has expired. Please login again.'

  try {
    await Swal.fire({
      icon: 'warning',
      title: 'Session Expired',
      text: message,
      confirmButtonText: 'Login',
      allowOutsideClick: false,
    })
    const { resetSidebarLayout } = await import('../composables/useSidebar.js')
    resetSidebarLayout()
    localStorage.clear()
    sessionStorage.clear()
    window.location.href = '/sign-in'
  } finally {
    handlingUnauthorized = false
  }

  return Promise.reject(error)
}

let handlingInactive = false

/**
 * The account was set inactive / blocked: log out now. Used by the live
 * `.account.deactivated` event (main.js) and by the API's 403 "Account inactive".
 */
export async function forceInactiveLogout(message) {
  if (handlingInactive || isPublicAuthPage()) return
  handlingInactive = true
  // Drop the token first so nothing else keeps calling the API behind the popup.
  clearAuthToken()
  try {
    await Swal.fire({
      icon: 'warning',
      title: 'Account Inactive',
      text: message || 'Your account has been deactivated. Please contact your administrator.',
      confirmButtonText: 'OK',
      allowOutsideClick: false,
    })
  } finally {
    try {
      const { resetSidebarLayout } = await import('../composables/useSidebar.js')
      resetSidebarLayout()
    } catch {
      // ignore
    }
    localStorage.clear()
    sessionStorage.clear()
    window.location.href = '/sign-in'
  }
}

const INTERCEPTOR_FLAG = '__crmAuthInterceptorsAttached'

function attachAuthInterceptor(client) {
  if (client[INTERCEPTOR_FLAG]) return
  client[INTERCEPTOR_FLAG] = true

  client.interceptors.request.use(async (config) => {
    const url = String(config?.url || '')
    if (!url.includes('stages-with-leads')) {
      await waitForLeadBoardPriority()
    }
    const token = resolveAuthToken()
    if (token) {
      config.headers = config.headers || {}
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  })

  client.interceptors.response.use(
    (response) => response,
    (error) => {
      const status = error.response?.status
      const url = error.config?.url || ''

      // JwtAuthMiddleware: account was set inactive while logged in.
      if (status === 403 && String(error?.response?.data?.message || '') === 'Account inactive') {
        forceInactiveLogout()
        return Promise.reject(error)
      }

      if (status === 401 && !isAuthEndpoint(url) && !isPublicAuthPage()) {
        const sentToken = requestHadAuthHeader(error.config)
        const storedToken = resolveAuthToken()
        const serverMessage = String(error?.response?.data?.message || '').toLowerCase()

        // Only force re-login when a token was sent but rejected (expired/invalid).
        if (sentToken && storedToken && !serverMessage.includes('not provided')) {
          return handleUnauthorized(error)
        }
      }

      return Promise.reject(error)
    }
  )
}

const api = axios.create({
  baseURL: getApiBaseUrl(),
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

attachAuthInterceptor(api)

// Keep the default axios singleton in sync for legacy `import axios from 'axios'`.
axios.defaults.baseURL = getApiBaseUrl()
axios.defaults.headers.common['Accept'] = 'application/json'
axios.defaults.headers.common['Content-Type'] = 'application/json'
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
attachAuthInterceptor(axios)

export function getApiErrorMessage(error, fallback = 'Request failed') {
  return error?.response?.data?.message || error?.message || fallback
}

export default api
