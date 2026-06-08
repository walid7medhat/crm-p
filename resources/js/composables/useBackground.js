import { ref } from 'vue'
import { normalizePublicStorageUrl } from '@/composables/usePublicStorageUrl'

/**
 * Drives the app-wide background image.
 *
 * The app shell already renders a fixed `.crm-bg-image` layer (see
 * resources/views/welcome.blade.php) with a hardcoded default image. We simply
 * override that element's background-image with the user's chosen background.
 * Clearing it reverts to the CSS default (the "current"/default background).
 *
 * The effective URL is sent by the API on the user object (`background_url`); we
 * mirror it into a cache key so the background paints instantly on reload.
 */
const CACHE_KEY = 'app_background_url'

// Shared across every component that calls useBackground().
const backgroundUrl = ref(null)

function bgElement() {
  if (typeof document === 'undefined') return null
  return document.querySelector('.crm-bg-image')
}

function readCachedUrl() {
  try {
    const direct = localStorage.getItem(CACHE_KEY)
    if (direct) return direct

    const raw = localStorage.getItem('user')
    if (!raw) return null
    const u = JSON.parse(raw)
    return u?.background_url || null
  } catch {
    return null
  }
}

export function useBackground() {
  // Paint the shell layer with `url`, or revert to the CSS default when null.
  function applyBackground(url) {
    const normalized = url ? normalizePublicStorageUrl(url) : null
    backgroundUrl.value = normalized

    const el = bgElement()
    if (el) {
      el.style.backgroundImage = normalized ? `url("${normalized}")` : ''
    }

    try {
      if (normalized) {
        localStorage.setItem(CACHE_KEY, normalized)
      } else {
        localStorage.removeItem(CACHE_KEY)
      }
    } catch {
      /* ignore storage errors (private mode, quota) */
    }
  }

  // Paint whatever we already know about (cache) — call this on app boot.
  function loadFromCache() {
    applyBackground(readCachedUrl())
  }

  // After the API returns a fresh user object, sync background + cached user.
  function syncFromUser(user) {
    if (!user) return
    try {
      const raw = localStorage.getItem('user')
      const stored = raw ? JSON.parse(raw) : {}
      stored.background_id = user.background_id ?? null
      stored.background_url = user.background_url ?? null
      localStorage.setItem('user', JSON.stringify(stored))
    } catch {
      /* ignore */
    }
    applyBackground(user.background_url || null)
  }

  return { backgroundUrl, applyBackground, loadFromCache, syncFromUser }
}
