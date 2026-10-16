/** Safari paints WebM transparency as black. It can play the HEVC clip with a clear background. */
export function transparentVideoSrc(src) {
  if (!src || typeof navigator === 'undefined') return src || ''
  if (!/\.webm(\?.*)?$/i.test(src)) return src
  const ua = navigator.userAgent || ''
  const safari = /Safari/i.test(ua) && !/Chrome|Chromium|Edg|OPR|Android/i.test(ua)
  return safari ? src.replace(/\.webm(\?.*)?$/i, '.mov$1') : src
}

export function prefersReducedMotion() {
  return typeof window !== 'undefined'
    && typeof window.matchMedia === 'function'
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches
}
