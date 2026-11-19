import { reactive } from 'vue'
import api from '@/plugins/axios'
import { normalizePublicStorageUrl } from '@/composables/usePublicStorageUrl'

/**
 * Private company files are served by GET /api/company-files/serve (signed URL + jwt.auth).
 * <img>, <iframe>, <a target=_blank> can't send the JWT header, so loading that URL directly
 * showed {"status":false,"message":"Unauthorized"}. These helpers fetch the file through the
 * API client (which adds the token; the signed URL is requested unchanged so the signature
 * stays valid) and hand back a same-origin blob: URL that any element can display.
 */

const PROTECTED_RE = /\/company-files\/serve(?:[/?#]|$)/

/** url → Promise<blob url>; shared so a thumbnail and its preview fetch once. */
const cache = new Map()

export function isProtectedFileUrl(url) {
  return typeof url === 'string' && PROTECTED_RE.test(url)
}

/**
 * Best URL for a stored document object: the server-issued url first (signed for private
 * files), then file_url / path rewritten to /storage (legacy public files). A bare private
 * path must never win — /storage/<private path> 404s into the SPA and opens the home page.
 */
export function pickDocumentUrl(doc) {
  if (!doc) return null
  if (typeof doc === 'string') return normalizePublicStorageUrl(doc)

  const url = doc.url || null
  if (url && (url.startsWith('blob:') || url.startsWith('data:') || isProtectedFileUrl(url))) return url
  if (url && /^https?:\/\//i.test(url)) return normalizePublicStorageUrl(url)

  for (const key of ['url', 'file_url', 'path', 'file_path', 'link']) {
    const v = doc[key]
    if (v && typeof v === 'string') {
      const n = normalizePublicStorageUrl(v)
      if (n) return n
    }
  }
  return null
}

/** Resolve to something an <img>/<iframe>/new tab can load. */
export function resolveFileUrl(url) {
  if (!url) return Promise.resolve(null)
  if (!isProtectedFileUrl(url)) return Promise.resolve(url)
  if (!cache.has(url)) {
    const p = api
      .get(url, { responseType: 'blob' })
      .then((res) => URL.createObjectURL(res.data))
      .catch((err) => {
        cache.delete(url)
        throw err
      })
    cache.set(url, p)
  }
  return cache.get(url)
}

/** Open in a new tab (window opened synchronously so popup blockers allow it). */
export async function openFileInNewTab(url) {
  if (!url) return
  if (!isProtectedFileUrl(url)) {
    window.open(url, '_blank', 'noopener')
    return
  }
  const win = window.open('', '_blank')
  try {
    const resolved = await resolveFileUrl(url)
    if (win) win.location.href = resolved
    else window.open(resolved, '_blank')
  } catch (err) {
    win?.close()
    window.$showNotification?.('Could not open this file.', 'error')
    throw err
  }
}

/** Download with the original file name. */
export async function downloadFile(url, filename = '') {
  if (!url) return
  const resolved = await resolveFileUrl(url)
  const a = document.createElement('a')
  a.href = resolved
  if (filename) a.download = filename
  a.target = '_blank'
  document.body.appendChild(a)
  a.click()
  a.remove()
}

/**
 * Reactive resolver for templates: src(url) returns the displayable URL, or null while a
 * protected file is still loading (the component re-renders when it arrives).
 */
export function useAuthorizedFileUrls() {
  const resolved = reactive({})
  const pending = new Set()

  function src(url) {
    if (!url) return null
    if (!isProtectedFileUrl(url)) return url
    if (resolved[url] !== undefined) return resolved[url]
    if (!pending.has(url)) {
      pending.add(url)
      resolveFileUrl(url)
        .then((r) => { resolved[url] = r })
        .catch(() => { resolved[url] = null })
        .finally(() => pending.delete(url))
    }
    return null
  }

  /** True once a protected file failed to load (expired link / no access). */
  function failed(url) {
    return isProtectedFileUrl(url) && resolved[url] === null
  }

  return { src, failed }
}
