import { onBeforeUnmount, onMounted, ref, watch } from 'vue'

/**
 * Horizontal card deck. A swipe changes the card. A still tap does not.
 * The click that follows a swipe is swallowed so it cannot open the card.
 */
export function useSwipeDeck(countOf) {
  const index = ref(0)
  const dragging = ref(false)
  const dragX = ref(0)
  const reducedMotion = ref(false)

  let moved = false
  let ignoreClick = false
  let startX = 0
  let startScroll = 0
  let pointerId = null
  let clickGuard = null
  let guardTimer = null
  let motionQuery = null

  function count() {
    const value = Number(typeof countOf === 'function' ? countOf() : countOf)
    return Number.isFinite(value) && value > 0 ? value : 0
  }

  function go(next) {
    const total = count()
    index.value = total ? Math.max(0, Math.min(total - 1, next)) : 0
  }

  function clearGuard() {
    if (clickGuard) {
      window.removeEventListener('click', clickGuard, true)
      clickGuard = null
    }
    if (guardTimer) {
      clearTimeout(guardTimer)
      guardTimer = null
    }
  }

  function armClickGuard() {
    clearGuard()
    ignoreClick = true
    clickGuard = (event) => {
      event.preventDefault()
      event.stopPropagation()
      clearGuard()
    }
    window.addEventListener('click', clickGuard, true)
    guardTimer = window.setTimeout(() => {
      ignoreClick = false
      clearGuard()
    }, 400)
  }

  function onPointerdown(event) {
    if (event.pointerType === 'mouse' && event.button !== 0) return
    moved = false
    ignoreClick = false
    clearGuard()
    dragging.value = true
    dragX.value = 0
    startX = event.clientX
    startScroll = event.currentTarget?.scrollTop || 0
    pointerId = event.pointerId
    event.currentTarget?.setPointerCapture?.(event.pointerId)
  }

  function onPointermove(event) {
    if (!dragging.value || event.pointerId !== pointerId) return
    const delta = event.clientX - startX
    dragX.value = reducedMotion.value ? 0 : delta
    if (Math.abs(delta) > 10) moved = true
  }

  function onPointerup(event) {
    if (!dragging.value || (pointerId != null && event.pointerId !== pointerId)) return
    const delta = event.clientX - startX
    const scrolled = Math.abs((event.currentTarget?.scrollTop || 0) - startScroll) > 4
    dragging.value = false
    dragX.value = 0
    pointerId = null
    if (!reducedMotion.value && Math.abs(delta) > 48) {
      go(index.value + (delta < 0 ? 1 : -1))
      moved = true
    }
    if (moved || Math.abs(delta) > 10 || scrolled) armClickGuard()
    ignoreClick = false
  }

  function onPointercancel(event) {
    onPointerup(event)
  }

  function consumeClick() {
    if (moved || ignoreClick) {
      moved = false
      return true
    }
    return false
  }

  function onMotionChange() {
    reducedMotion.value = !!motionQuery?.matches
  }

  watch(() => count(), () => go(index.value))

  onMounted(() => {
    motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)')
    onMotionChange()
    motionQuery.addEventListener?.('change', onMotionChange)
  })

  onBeforeUnmount(() => {
    clearGuard()
    motionQuery?.removeEventListener?.('change', onMotionChange)
  })

  return {
    index,
    dragging,
    dragX,
    reducedMotion,
    go,
    consumeClick,
    pointer: {
      onPointerdown,
      onPointermove,
      onPointerup,
      onPointercancel,
    },
  }
}
