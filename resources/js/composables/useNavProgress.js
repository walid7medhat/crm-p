import { ref } from 'vue'

/**
 * Thin top-of-viewport bar while Vue Router resolves lazy chunks.
 * Gives immediate click feedback before the destination page mounts.
 */
const isNavigating = ref(false)
let hideTimer = null
let wired = false

function clearHideTimer() {
  if (hideTimer != null) {
    window.clearTimeout(hideTimer)
    hideTimer = null
  }
}

export function startNavProgress() {
  clearHideTimer()
  isNavigating.value = true
}

export function finishNavProgress() {
  clearHideTimer()
  // Brief linger so short navigations still flash visibly
  hideTimer = window.setTimeout(() => {
    isNavigating.value = false
    hideTimer = null
  }, 120)
}

export function cancelNavProgress() {
  clearHideTimer()
  isNavigating.value = false
}

/**
 * Attach once to the app router (idempotent).
 */
export function installNavProgress(router) {
  if (wired || !router) return
  wired = true

  router.beforeEach((to, from) => {
    if (to.path !== from.path) {
      startNavProgress()
    }
  })

  router.afterEach(() => {
    finishNavProgress()
  })

  router.onError(() => {
    cancelNavProgress()
  })
}

export function useNavProgress() {
  return {
    isNavigating,
    startNavProgress,
    finishNavProgress,
    cancelNavProgress,
  }
}
