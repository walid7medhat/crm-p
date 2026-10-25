import { ref } from 'vue'
import router from '@/router.js'

export const smartSearchOpen = ref(false)
export const smartSearchQuery = ref('')
export const smartSearchLoading = ref(false)
export const smartSearchAnchorEl = ref(null)

let lastFocus = null
let listening = false
let navApi = null

export function isMacPlatform() {
  if (typeof navigator === 'undefined') return false
  return /Mac|iPhone|iPad/i.test(navigator.platform || navigator.userAgent || '')
}

export function isSmartSearchAllowed() {
  return !!router?.currentRoute?.value?.meta?.dashboardHome
}

export function setSmartSearchAnchor(el) {
  smartSearchAnchorEl.value = el || null
}

export function registerSmartSearchNav(api) {
  navApi = api || null
  return () => {
    if (navApi === api) navApi = null
  }
}

export function smartSearchMove(step) {
  navApi?.move?.(step)
}

export function smartSearchActivate() {
  navApi?.activate?.()
}

export function openSmartSearch() {
  if (!isSmartSearchAllowed()) return
  if (!smartSearchOpen.value) {
    lastFocus = typeof document !== 'undefined' ? document.activeElement : null
    smartSearchQuery.value = ''
  }
  smartSearchOpen.value = true
}

export function closeSmartSearch() {
  if (!smartSearchOpen.value) return
  smartSearchOpen.value = false
  smartSearchQuery.value = ''
  smartSearchLoading.value = false
  const el = lastFocus
  lastFocus = null
  if (el && typeof el.focus === 'function' && document.contains(el)) {
    el.focus()
  }
}

function isTypingTarget(target) {
  if (!target || typeof target.closest !== 'function') return false
  if (target.closest('[data-smart-search-root]')) return false
  if (target.closest('[data-smart-search-input]')) return false
  const tag = target.tagName
  if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return true
  return !!target.isContentEditable
}

function incompatibleDialogOpen() {
  if (typeof document === 'undefined') return false
  return !!document.querySelector('.modal.show, .swal2-container.swal2-shown')
}

function onShortcut(event) {
  if (event.repeat) return
  if (!isSmartSearchAllowed()) return
  const key = String(event.key || '').toLowerCase()
  if (!(event.metaKey || event.ctrlKey) || key !== 'k' || event.altKey) return
  if (smartSearchOpen.value) {
    event.preventDefault()
    return
  }
  if (isTypingTarget(event.target) || incompatibleDialogOpen()) return
  event.preventDefault()
  openSmartSearch()
  requestAnimationFrame(() => {
    document.querySelector('[data-smart-search-input]')?.focus?.()
  })
}

export function bindSmartSearchShortcut() {
  if (listening || typeof window === 'undefined') return () => {}
  listening = true
  window.addEventListener('keydown', onShortcut)
  const stopRouteGuard = router.afterEach(() => {
    if (!isSmartSearchAllowed() && smartSearchOpen.value) {
      closeSmartSearch()
    }
  })
  return () => {
    window.removeEventListener('keydown', onShortcut)
    stopRouteGuard?.()
    listening = false
  }
}
