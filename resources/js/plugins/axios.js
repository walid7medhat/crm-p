import axios from 'axios'
import Swal from 'sweetalert2'

const getApiBaseUrl = () =>
  (typeof window !== 'undefined' && window.__API_BASE_URL__) ||
  import.meta.env.VITE_API_BASE_URL ||
  'http://127.0.0.1:8001/api'

const resolveAuthToken = () => {
  let token =
    localStorage.getItem('token') ||
    localStorage.getItem('access_token') ||
    sessionStorage.getItem('token')

  if (!token && typeof document !== 'undefined') {
    const cookies = document.cookie.split('; ')
    const tokenCookie = cookies.find((row) => row.startsWith('token='))
    const accessTokenCookie = cookies.find((row) => row.startsWith('access_token='))
    token = tokenCookie?.split('=')[1] || accessTokenCookie?.split('=')[1]
  }

  return token || null
}

let handlingUnauthorized = false

async function handleUnauthorized(error) {
  if (handlingUnauthorized) return Promise.reject(error)
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

const api = axios.create({
  baseURL: getApiBaseUrl(),
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = resolveAuthToken()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      return handleUnauthorized(error)
    }
    return Promise.reject(error)
  }
)

export function getApiErrorMessage(error, fallback = 'Request failed') {
  return (
    error?.response?.data?.message ||
    error?.message ||
    fallback
  )
}

export default api
