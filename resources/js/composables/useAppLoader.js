import { ref, nextTick, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  isKanbanRoute,
  resetKanbanReady,
  waitForKanbanReady,
} from './useKanbanReady.js'

const MIN_DISPLAY_MS = 900
const NAV_MIN_DISPLAY_MS = 650
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
  const isAppLoading = ref(true)
  const router = useRouter()
  let initialBootstrapDone = false
  let activeLoadId = 0

  async function runLoader({ minDisplayMs = MIN_DISPLAY_MS, route } = {}) {
    const loadId = ++activeLoadId
    const startedAt = performance.now()

    isAppLoading.value = true
    document.body.classList.add('app-loader-active')

    try {
      await prepareRoute(route ?? router.currentRoute.value)
    } catch {
      /* always dismiss */
    }

    if (loadId !== activeLoadId) return

    const elapsed = performance.now() - startedAt
    const remaining = Math.max(0, minDisplayMs - elapsed)
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
      await runLoader({ minDisplayMs: NAV_MIN_DISPLAY_MS, route: to })
    })
  })

  function onLoaderHidden() {
    if (!isAppLoading.value) {
      document.body.classList.remove('app-loader-active')
    }
  }

  return {
    isAppLoading,
    onLoaderHidden,
  }
}
