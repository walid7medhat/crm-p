import { createApp } from 'vue'
import App from './App.vue'
import router from './router.js'
import VueApexCharts from "vue3-apexcharts"
import { Icon } from '@iconify/vue'
import Swal from 'sweetalert2'
import axios from 'axios'

// CSS imports
import 'bootstrap/dist/css/bootstrap.min.css'
import 'bootstrap'
import 'vue-slick-carousel/dist/vue-slick-carousel.css'
import 'vue-slick-carousel/dist/vue-slick-carousel-theme.css'


const addCSS = (url) => {
  const link = document.createElement('link')
  link.rel = 'stylesheet'
  link.href = url
  document.head.appendChild(link)
}
addCSS('/assets/css/style14.css')
addCSS('https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css')


// Setup Axios
axios.defaults.baseURL = 'http://127.0.0.1:8001'
axios.defaults.headers.common['Accept'] = 'application/json'
axios.defaults.headers.common['Content-Type'] = 'application/json'

// Request interceptor for JWT token
axios.interceptors.request.use(
  config => {
    // Get token from various sources
    let token = localStorage.getItem('token') || 
                localStorage.getItem('access_token') ||
                sessionStorage.getItem('token')
console.log(token);
    // Check cookies
    if (!token) {
      const cookies = document.cookie.split('; ')
      const tokenCookie = cookies.find(row => row.startsWith('token='))
      const accessTokenCookie = cookies.find(row => row.startsWith('access_token='))
      
      token = tokenCookie?.split('=')[1] || accessTokenCookie?.split('=')[1]
    }

    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }

    return config
  },
  error => {
    console.error('Axios Request Error:', error)
    return Promise.reject(error)
  }
)

// Response interceptor
axios.interceptors.response.use(
  response => {
    return response
  },
  error => {
    console.error('Axios Response Error:', error.response?.status, error.message)

    if (error.response?.status === 401) {
      Swal.fire({
        icon: 'warning',
        title: 'Session Expired',
        text: 'Your session has expired. Please login again.',
        confirmButtonText: 'Login',
        allowOutsideClick: false
      }).then(() => {
        localStorage.clear()
        sessionStorage.clear()
        window.location.href = '/sign-in'
      })
    }

    return Promise.reject(error)
  }
)

// Make axios globally available
window.axios = axios

// Pusher and Echo initialization
import Echo from 'laravel-echo'
import Pusher from 'pusher-js/dist/web/pusher'

Pusher.logToConsole = true

window.Pusher = Pusher

// Get base URL without /api for broadcasting auth
const getBaseUrl = () => {
    const apiUrl = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8001/api';
    // Remove /api from the end if present
    return apiUrl.replace(/\/api\/?$/, '');
};

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true,
    authEndpoint: `${getBaseUrl()}/broadcasting/auth`,
    auth: {
        headers: {
            Authorization: `Bearer ${localStorage.getItem('token')}`,
            Accept: 'application/json'
        }
    }
});


const app = createApp(App)
app.config.devtools = true

app.config.errorHandler = (err, instance, info) => {
  console.error('[Vue Error]', info, err)
  if (instance && instance.type) {
    console.error('[Vue Error] Component:', instance.type.__name || instance.type.name || instance.type)
  }
}

// Global components
app.component('iconify-icon', Icon)

// Plugins
app.use(router)
app.use(VueApexCharts)

// Global properties
app.config.globalProperties.$apiBaseUrl = 'http://127.0.0.1:8000/api'
app.config.globalProperties.$axios = axios

// SweetAlert configuration
const Toast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 3000,
  timerProgressBar: true,
  background: '#fff',
  color: '#333',
  didOpen: (toast) => {
    toast.addEventListener('mouseenter', Swal.stopTimer)
    toast.addEventListener('mouseleave', Swal.resumeTimer)
  }
})

// Global notification – always defer so SweetAlert2 never runs in same turn as a closing Bootstrap modal (avoids focus-trap stack overflow)
function showNotificationDeferred(message, type = 'info') {
  const msg = typeof message === 'string' ? message : String(message)
  const icon = ['success', 'error', 'warning', 'info'].includes(type) ? type : 'info'
  setTimeout(() => {
    Toast.fire({ icon, title: msg })
  }, 150)
}
app.config.globalProperties.$showNotification = showNotificationDeferred
window.$showNotification = showNotificationDeferred

// Global confirmation function
import showConfirmation from './composables/useConfirmation'
app.config.globalProperties.$showConfirmation = showConfirmation
window.$showConfirmation = showConfirmation

// Permission check function
app.config.globalProperties.$hasPermission = function(permission) {
  try {
    const userData = localStorage.getItem('user')
    if (!userData) return false
    const user = JSON.parse(userData)
    const permissions = user.permissions || []
    return permissions.includes(permission)
  } catch (error) {
    console.error("Error checking permissions:", error)
    return false
  }
}

app.config.globalProperties.$swal = Swal

// Time formatting mixin
app.mixin({
  methods: {
    $formatDubaiTime(timestamp) {
      if (!timestamp) return 'unknown time';
      
      try {
        const date = new Date(timestamp);
        const dubaiTime = new Date(date.toLocaleString("en-US", {timeZone: "Asia/Dubai"}));
        const now = new Date();
        
        const diff = now - dubaiTime;
        const mins = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);

        if (mins < 1) return 'Just now';
        if (mins < 60) return `${mins} minutes ago`;
        if (hours < 24) return `${hours} hours ago`;
        if (days < 7) return `${days} days ago`;
        
        return dubaiTime.toLocaleDateString('en-US', {
          year: 'numeric',
          month: 'short',
          day: 'numeric'
        });
      } catch (error) {
        return 'unknown time';
      }
    },
    
    $formatDubaiDateTime(timestamp) {
      if (!timestamp) return 'unknown time';
      
      try {
        const date = new Date(timestamp);
        return date.toLocaleString('en-US', {
          timeZone: 'Asia/Dubai',
          year: 'numeric',
          month: 'short',
          day: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        });
      } catch (error) {
        return 'unknown time';
      }
    }
  }
})

// Mount app
app.mount('#app')