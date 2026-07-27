import { createApp, h, markRaw } from 'vue'
import App from './App.vue'
import router from './router.js'
import SearchableSelect from './components/ui/SearchableSelect.vue'
import 'vue-select/dist/vue-select.css'
import VueApexCharts from "vue3-apexcharts"
import { Icon } from '@iconify/vue'
import Swal from 'sweetalert2'
import api, { getAppOrigin, getApiBaseUrl, resolveAuthToken } from './plugins/axios.js'

// CSS imports
import 'bootstrap/dist/css/bootstrap.min.css'
import 'bootstrap'

/* Intl phone: coords from library; CDN sprite survives CSP / quirks vs data-uri in vue-tel build */
import 'vue-tel-input/vue-tel-input.css'
import '../css/crm-phone-flags.css'
import 'vue-slick-carousel/dist/vue-slick-carousel.css'
import 'vue-slick-carousel/dist/vue-slick-carousel-theme.css'

import 'vue-select/dist/vue-select.css'
import '../css/vue-select-overrides.css'
import '../css/form-placeholders.css'
import '../css/kanban-layout.css'
import '../css/crm-background.css'
import '../css/dashboard-home.css'
import '../css/analytics-premium.css'
import '../css/dashboard-ai.css'
import '../css/roi-calculator.css'
import '../css/background-settings.css'
import '../css/mobile-layout.css'
import '../css/mobile-listings.css'
import '../css/mobile-property-show.css'
import '../css/mobile-sidebar.css'
import '../css/mobile-header-select.css'
import '../css/mobile-hr.css'
import '../css/view-profile.css'
import '../css/project-page.css'
import { syncMobileViewport } from './composables/useMobileNavigation.js'
import { initLeadViewModal } from '@/composables/useLeadViewModal.js'

syncMobileViewport()
initLeadViewModal(router, router.currentRoute)

const addCSS = (url) => {
  const link = document.createElement('link')
  link.rel = 'stylesheet'
  link.href = url
  document.head.appendChild(link)
}
addCSS('/assets/css/style14.css')
addCSS('https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css')

// Single shared API client (token + /api base URL) for the whole app
window.axios = api

const initialToken = resolveAuthToken()

// Pusher and Echo initialization
import Echo from 'laravel-echo'
import Pusher from 'pusher-js/dist/web/pusher'

Pusher.logToConsole = true

window.Pusher = Pusher

if (initialToken && import.meta.env.VITE_PUSHER_APP_KEY) {
  window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true,
    authEndpoint: `${getAppOrigin()}/broadcasting/auth`,
    auth: {
      headers: {
        Authorization: `Bearer ${initialToken}`,
        Accept: 'application/json',
      },
    },
  })
}


const app = createApp(App)
app.component('SearchableSelect', SearchableSelect)
app.config.devtools = true

app.config.errorHandler = (err, instance, info) => {
  console.error('[Vue Error]', info, err)
  if (instance && instance.type) {
    console.error('[Vue Error] Component:', instance.type.__name || instance.type.name || instance.type)
  }
}

// Global components – wrap Icon so we pass a plain (markRaw) props object to avoid Iconify's mergeCustomisations + Vue reactivity causing "Maximum call stack size exceeded"
app.component('iconify-icon', {
  name: 'IconifyIconSafe',
  props: {
    icon: { type: [String, Object], default: '' },
    width: { type: [String, Number], default: undefined },
    height: { type: [String, Number], default: undefined },
    color: { type: String, default: undefined },
    flip: { type: String, default: undefined },
    rotate: { type: [String, Number], default: undefined },
    inline: { type: Boolean, default: undefined },
    mode: { type: String, default: undefined },
    ariaLabel: { type: String, default: undefined },
    ariaHidden: { type: [Boolean, String], default: undefined },
    class: { type: [String, Object, Array], default: undefined },
    style: { type: [String, Object], default: undefined }
  },
  setup (props) {
    return () => {
      const icon = props.icon != null ? String(props.icon) : ''
      const plain = { icon }
      if (props.width !== undefined) plain.width = props.width
      if (props.height !== undefined) plain.height = props.height
      if (props.color !== undefined) plain.color = props.color
      if (props.flip !== undefined) plain.flip = props.flip
      if (props.rotate !== undefined) plain.rotate = props.rotate
      if (props.inline !== undefined) plain.inline = props.inline
      if (props.mode !== undefined) plain.mode = props.mode
      if (props.ariaLabel !== undefined) plain.ariaLabel = props.ariaLabel
      if (props.ariaHidden !== undefined) plain.ariaHidden = props.ariaHidden
      if (props.class !== undefined) plain.class = props.class
      if (props.style !== undefined) plain.style = props.style
      return h(Icon, markRaw(plain))
    }
  }
})

// Plugins
app.use(router)
app.use(VueApexCharts)

// Global properties
app.config.globalProperties.$apiBaseUrl = getApiBaseUrl()
app.config.globalProperties.$axios = api

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