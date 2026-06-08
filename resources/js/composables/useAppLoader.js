import { ref, nextTick, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  isKanbanRoute,
  resetKanbanReady,
  waitForKanbanReady,
} from './useKanbanReady.js'

/** Set to false to disable splash + navigation loader entirely */
const APP_LOADER_ENABLED = false

const MIN_DISPLAY_MS = 900
const NAV_MIN_DISPLAY_MS = 650
const KANBAN_MIN_DISPLAY_MS = 400
const KANBAN_NAV_MIN_DISPLAY_MS = 300
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
  const isAppLoading = ref(APP_LOADER_ENABLED)

  function onLoaderHidden() {
    if (!isAppLoading.value) {
      document.body.classList.remove('app-loader-active')
    }
  }

  if (!APP_LOADER_ENABLED) {
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

  function resolveNavMinDisplayMs(route, overrideMs) {
    if (overrideMs != null) return overrideMs
    const path = route?.path ?? ''
    return isKanbanRoute(path) ? KANBAN_NAV_MIN_DISPLAY_MS : NAV_MIN_DISPLAY_MS
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
    await runLoader({ minDisplayMs: MIN_DISPLAY_MS })
    initialBootstrapDone = true

    router.beforeEach((to, from, next) => {
      if (!initialBootstrapDone) {
        next()
        return
      }

      if (shouldUseNavLoader(to, from)) {
        if (isKanbanRoute(to.path)) {
          resetKanbanReady()
        }
        isAppLoading.value = true
        document.body.classList.add('app-loader-active')
      }

      next()
    })

    router.afterEach(async (to, from) => {
      if (!initialBootstrapDone) return
      if (!shouldUseNavLoader(to, from)) return
      await runLoader({ minDisplayMs: resolveNavMinDisplayMs(to), route: to })
    })
  })

  return {
    isAppLoading,
    onLoaderHidden,
  }
}
