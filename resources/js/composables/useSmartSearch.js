import { ref } from 'vue'
import router from '@/router.js'

export const smartSearchOpen = ref(false)
export const smartSearchQuery = ref('')
export const smartSearchLoading = ref(false)
export const smartSearchAnchorEl = ref(null)

let lastFocus = null
let listening = false
let outsideListening = false
let navApi = null
let closing = false

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

function isSearchUi(target) {
  if (!target || typeof target.closest !== 'function') return false
  // Backdrop is intentionally NOT protected — clicking it must close.
  if (target.closest('[data-smart-search-backdrop]')) return false
  return !!(
    target.closest('[data-smart-search-panel]')
    || target.closest('[data-smart-search-input]')
    || target.closest('[data-smart-search-shell]')
  )
}

function setBodyOpenClass(on) {
  if (typeof document === 'undefined') return
  document.body.classList.toggle('oia-smart-search-open', !!on)
}

export function openSmartSearch() {
  if (!isSmartSearchAllowed() || closing) return
  if (!smartSearchOpen.value) {
    const active = typeof document !== 'undefined' ? document.activeElement : null
    lastFocus = isSearchUi(active) ? null : active
    smartSearchQuery.value = ''
  }
  smartSearchOpen.value = true
  setBodyOpenClass(true)
  bindOutsideClose()
}

export function closeSmartSearch() {
  if (!smartSearchOpen.value) return
  closing = true
  smartSearchOpen.value = false
  smartSearchQuery.value = ''
  smartSearchLoading.value = false
  setBodyOpenClass(false)
  unbindOutsideClose()

  const input = typeof document !== 'undefined'
    ? document.querySelector('[data-smart-search-input]')
    : null
  if (input && typeof input.blur === 'function') input.blur()

  const el = lastFocus
  lastFocus = null
  if (
    el
    && el !== input
    && typeof el.focus === 'function'
    && document.contains(el)
    && !isSearchUi(el)
  ) {
    el.focus()
  }

  // Allow focus handlers again on the next tick.
  requestAnimationFrame(() => {
    closing = false
  })
}

function onOutsidePointerDown(event) {
  if (!smartSearchOpen.value) return
  if (isSearchUi(event.target)) return
  closeSmartSearch()
}

function bindOutsideClose() {
  if (outsideListening || typeof window === 'undefined') return
  outsideListening = true
  // Capture so we close even if something stops bubbling.
  window.addEventListener('pointerdown', onOutsidePointerDown, true)
}

function unbindOutsideClose() {
  if (!outsideListening || typeof window === 'undefined') return
  window.removeEventListener('pointerdown', onOutsidePointerDown, true)
  outsideListening = false
}

function isTypingTarget(target) {
  if (!target || typeof target.closest !== 'function') return false
  if (isSearchUi(target)) return false
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
    closeSmartSearch()
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
    unbindOutsideClose()
    stopRouteGuard?.()
    listening = false
  }
}
