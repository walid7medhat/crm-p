import { ref, nextTick, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  isKanbanRoute,
  resetKanbanReady,
  waitForKanbanReady,
} from './useKanbanReady.js'

/** Boot splash stays off so the app itself opens immediately. */
const APP_SPLASH_ENABLED = false
/** Page changes that actually take time show the logo loader. */
const NAV_LOADER_ENABLED = true
const NAV_SHOW_AFTER_MS = 140
const NAV_MIN_VISIBLE_MS = 380

const MIN_DISPLAY_MS = 900
const KANBAN_MIN_DISPLAY_MS = 400
const MAX_WAIT_MS = 8000

function wait(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

function waitForPaint() {
  return new Promise((resolve) => {
    requestAnimationFrame(() => requestAnimationFrame(resolve))
  })
}

function withTimeout(promise, ms) {
  return Promise.race([promise, wait(ms)])
}

function hasAuthenticatedLayout(route) {
  return route?.meta?.layout !== false
}

function shouldUseNavLoader(to, from) {
  if (!localStorage.getItem('token')) return false
  if (!hasAuthenticatedLayout(to) || !hasAuthenticatedLayout(from)) return false
  if (to.path === from.path) return false
  return true
}

async function prepareRoute(route) {
  await nextTick()
  if (route && isKanbanRoute(route.path)) {
    await waitForKanbanReady()
  }
  await withTimeout(waitForPaint(), MAX_WAIT_MS)
  await waitForPaint()
}

/**
 * Initial splash + loader on sidebar / in-app navigation until the route is painted.
 */
export function useAppLoader() {
  const isAppLoading = ref(false)

  function onLoaderHidden() {
    if (!isAppLoading.value) {
      document.body.classList.remove('app-loader-active')
    }
  }

  if (!APP_SPLASH_ENABLED && !NAV_LOADER_ENABLED) {
    if (typeof document !== 'undefined') {
      document.body.classList.remove('app-loader-active')
    }
    return {
      isAppLoading,
      onLoaderHidden,
    }
  }

  const router = useRouter()
  let initialBootstrapDone = false
  let activeLoadId = 0

  function resolveMinDisplayMs(route, overrideMs) {
    if (overrideMs != null) return overrideMs
    const path = route?.path ?? router.currentRoute.value?.path ?? ''
    return isKanbanRoute(path) ? KANBAN_MIN_DISPLAY_MS : MIN_DISPLAY_MS
  }

  async function runLoader({ minDisplayMs, route } = {}) {
    const loadId = ++activeLoadId
    const startedAt = performance.now()
    const effectiveMinMs = resolveMinDisplayMs(route ?? router.currentRoute.value, minDisplayMs)

    isAppLoading.value = true
    document.body.classList.add('app-loader-active')

    try {
      await prepareRoute(route ?? router.currentRoute.value)
    } catch {
      /* always dismiss */
    }

    if (loadId !== activeLoadId) return

    const elapsed = performance.now() - startedAt
    const remaining = Math.max(0, effectiveMinMs - elapsed)
    if (remaining > 0) {
      await wait(remaining)
    }

    if (loadId !== activeLoadId) return
    isAppLoading.value = false
  }

  onMounted(async () => {
    await router.isReady()
    if (APP_SPLASH_ENABLED) {
      await runLoader({ minDisplayMs: MIN_DISPLAY_MS })
    }
    initialBootstrapDone = true

    if (!NAV_LOADER_ENABLED) return

    let navTimer = 0
    let navShownAt = 0

    router.beforeEach((to, from, next) => {
      if (!initialBootstrapDone) {
        next()
        return
      }

      window.clearTimeout(navTimer)
      if (shouldUseNavLoader(to, from)) {
        if (isKanbanRoute(to.path)) {
          resetKanbanReady()
        }
        navTimer = window.setTimeout(() => {
          navShownAt = performance.now()
          isAppLoading.value = true
          document.body.classList.add('app-loader-active')
        }, NAV_SHOW_AFTER_MS)
      }

      next()
    })

    router.afterEach(async (to, from) => {
      if (!initialBootstrapDone) return
      window.clearTimeout(navTimer)
      if (!shouldUseNavLoader(to, from) || !isAppLoading.value) return

      try {
        await prepareRoute(to)
      } catch {
        /* always dismiss */
      }

      const remaining = Math.max(0, NAV_MIN_VISIBLE_MS - (performance.now() - navShownAt))
      if (remaining > 0) await wait(remaining)
      isAppLoading.value = false
    })
  })

  return {
    isAppLoading,
    onLoaderHidden,
  }
}
