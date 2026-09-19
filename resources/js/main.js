import { createApp, h, markRaw } from 'vue'
import App from './App.vue'
import router from './router.js'
import SearchableSelect from './components/ui/SearchableSelect.vue'
import 'vue-select/dist/vue-select.css'
import { Icon } from '@iconify/vue'
import Swal from 'sweetalert2'
import api, { getAppOrigin, getApiBaseUrl, resolveAuthToken } from './plugins/axios.js'

// CSS imports
import 'bootstrap/dist/css/bootstrap.min.css'
import 'bootstrap'

/* Intl phone: coords from library; CDN sprite survives CSP / quirks vs data-uri in vue-tel build */
import 'vue-tel-input/vue-tel-input.css'
import '../css/crm-phone-flags.css'

import 'vue-select/dist/vue-select.css'
import '../css/vue-select-overrides.css'
import '../css/form-placeholders.css'
import '../css/kanban-layout.css'
import '../css/crm-background.css'
import '../css/dashboard-home.css'
import '../css/analytics-premium.css'
import '../css/dashboard-light-shell.css'
import '../css/agent-performance.css'
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
import '../css/sweetalert-zindex.css'
import '../css/crm-toast.css'
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

Pusher.logToConsole = import.meta.env.DEV

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
  // ================= Live notifications (Pusher) =================
function getStoredUserId() {
  try {
    const raw = localStorage.getItem('user')
    if (!raw) return null
    const parsed = JSON.parse(raw)
    return parsed?.id || null
  } catch (_) {
    return null
  }
}

if (window.Echo) {
  const currentUserId = getStoredUserId()

  if (currentUserId) {
    window.Echo.private(`user.${currentUserId}`)
      .notification((notification) => {
        console.log('[Notification]', notification)

        // Toast it
        const type = String(notification.type || '').includes('status')
          ? (notification.status === 'approved' ? 'success' : 'error')
          : 'info'
        showNotificationDeferred(notification.message || 'New notification', type)

        // ===== التعديل هنا =====
        // لو نوع الإشعار leave_request_parent_status (يعني HR قبل أو رفض)
        if (notification.type === 'leave_request_parent_status') {
          // هنبعت إشعار مخصص عشان Vue component يعرف يحدث نفسه
          window.dispatchEvent(new CustomEvent('app-notification', { 
            detail: { 
              ...notification,
              // نحدد إنه parent status عشان نعرفه في الـ component
              isParentStatus: true 
            } 
          }))
        } else {
          // باقي الإشعارات زي ما هي
          window.dispatchEvent(new CustomEvent('app-notification', { detail: notification }))
        }
      })
  }
}
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
// ApexCharts is NOT registered globally — chart pages/components import
// vue3-apexcharts (or apexcharts) locally so login and non-chart routes
// do not pay the full chart library cost in the initial bundle.
app.use(router)

// Global properties
app.config.globalProperties.$apiBaseUrl = getApiBaseUrl()
app.config.globalProperties.$axios = api

// SweetAlert configuration (legacy mixin kept for any direct Toast.fire callers)
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

const CRM_TOAST_ICONS = {
  warning: `<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" stroke="none" d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line class="crm-glass-toast__mark" x1="12" y1="9" x2="12" y2="13"/><line class="crm-glass-toast__mark" x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
  error: `<svg viewBox="0 0 24 24" aria-hidden="true"><circle fill="currentColor" stroke="none" cx="12" cy="12" r="10"/><line class="crm-glass-toast__mark" x1="12" y1="8" x2="12" y2="12"/><line class="crm-glass-toast__mark" x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
  success: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`,
  info: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.22-8.56"/><path d="M21 3v5h-5"/><path d="M16 8a9 9 0 0 0-9 9"/></svg>`,
}

function escapeToastHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

/** Highlight times / key numbers inside toast copy (e.g. "4:44 PM"). */
function formatGlassToastMessage(message) {
  const escaped = escapeToastHtml(message)
  return escaped
    .replace(
      /(\d{1,2}:\d{2}\s*(?:AM|PM|am|pm)?)/g,
      '<b class="crm-glass-toast__highlight">$1</b>',
    )
    .replace(
      /(\(in\s+\d+\s+minutes?\))/gi,
      '<span class="crm-glass-toast__highlight">$1</span>',
    )
}

function closeGlassToast() {
  try {
    if (typeof Swal.close === 'function') {
      Swal.close()
    }
  } catch (_) {
    /* ignore */
  }

  document.querySelectorAll('.swal2-container').forEach((el) => {
    if (el.querySelector('.crm-glass-toast-popup, .crm-glass-toast')) {
      el.remove()
    }
  })

  document.body.classList.remove(
    'swal2-shown',
    'swal2-toast-shown',
    'swal2-height-auto',
    'swal2-no-backdrop',
  )
}

function fireGlassToast(message, type = 'info') {
  const toastType = ['success', 'error', 'warning', 'info'].includes(type) ? type : 'info'
  const iconHtml = CRM_TOAST_ICONS[toastType] || CRM_TOAST_ICONS.info
  const filledIcon = toastType === 'warning' || toastType === 'error'
  const duration = toastType === 'error' || toastType === 'warning' ? 5500 : 3500

  // Replace any existing toast so loading/new actions never leave a stuck one
  closeGlassToast()

  let fallbackTimer = null

  Swal.fire({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    showCloseButton: false,
    allowOutsideClick: false,
    allowEscapeKey: true,
    timer: duration,
    timerProgressBar: true,
    background: 'transparent',
    customClass: {
      popup: 'crm-glass-toast-popup',
      timerProgressBar: 'crm-glass-toast-progress',
      htmlContainer: 'crm-glass-toast-html',
    },
    html: `
      <div class="crm-glass-toast crm-glass-toast--${toastType}">
        <div class="crm-glass-toast__icon${filledIcon ? ' crm-glass-toast__icon--filled' : ''}">${iconHtml}</div>
        <div class="crm-glass-toast__body">
          <p class="crm-glass-toast__title">${formatGlassToastMessage(message)}</p>
        </div>
        <button type="button" class="crm-glass-toast__close" aria-label="Dismiss" data-crm-toast-close>
          <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>
    `,
    didOpen: (popup) => {
      popup.style.pointerEvents = 'all'

      const forceClose = (event) => {
        event?.preventDefault?.()
        event?.stopPropagation?.()
        if (fallbackTimer) {
          clearTimeout(fallbackTimer)
          fallbackTimer = null
        }
        closeGlassToast()
      }

      const closeBtn = popup.querySelector('[data-crm-toast-close]')
      if (closeBtn) {
        closeBtn.style.pointerEvents = 'all'
        // mousedown is more reliable than click when toast opens under the cursor
        closeBtn.addEventListener('mousedown', forceClose, true)
        closeBtn.addEventListener('click', forceClose, true)
      }

      // Hard auto-dismiss fallback if SweetAlert's timer is paused/stuck
      fallbackTimer = window.setTimeout(() => {
        closeGlassToast()
      }, duration + 250)
    },
    willClose: () => {
      if (fallbackTimer) {
        clearTimeout(fallbackTimer)
        fallbackTimer = null
      }
    },
  })
}

// Global notification – always defer so SweetAlert2 never runs in same turn as a closing Bootstrap modal (avoids focus-trap stack overflow)
function showNotificationDeferred(message, type = 'info') {
  const msg = typeof message === 'string' ? message : String(message)
  const delay = 80
  setTimeout(() => {
    try {
      fireGlassToast(msg, type)
    } catch (e) {
      console.warn('Toast fire failed:', e)
      try {
        Toast.fire({
          icon: ['success', 'error', 'warning', 'info'].includes(type) ? type : 'info',
          title: msg,
        })
      } catch (_) {
        /* ignore */
      }
    }
  }, delay)
}
app.config.globalProperties.$showNotification = showNotificationDeferred
window.$showNotification = showNotificationDeferred
app.config.globalProperties.$hideNotification = closeGlassToast
window.$hideNotification = closeGlassToast

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
// vue-select (v-select, used with append-to-body across ~20 forms/modals) computes
// its dropdown menu's position once when it opens and never re-tracks the trigger
// afterwards, so scrolling any ancestor container (a modal body, the page, a
// scrollable panel, ...) leaves the options list visually detached from its select.
// There's no cheap way to keep every independent instance repositioned live, so
// close the open dropdown instead — but ignore scrolls that happen *inside* the
// dropdown's own option list, since scrolling through a long list is normal.
window.addEventListener('scroll', (event) => {
  const openSelect = document.querySelector('.v-select.vs--open')
  if (!openSelect) return
  const menu = document.querySelector('.vs__dropdown-menu')
  if (menu && (event.target === menu || menu.contains(event.target))) return
  const searchInput = openSelect.querySelector('.vs__search')
  if (searchInput) searchInput.blur()
}, true)

// in main.js, after app creation, before app.mount
window.addEventListener('unhandledrejection', (event) => {
  if (String(event.reason?.message || event.reason).includes('Element not found')) {
    // Known benign ApexCharts race when a chart's container unmounts mid-update
    event.preventDefault()
  }
})
// Mount app
app.mount('#app')