import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue'

export function isInsideHrSearchPopup(event) {
  return Boolean(
    event.target.closest?.(
      '.emp-search-popup, .date-time-picker-overlay, .date-time-picker-modal, .vs__dropdown-menu, .emp-search-select__menu'
    )
  )
}

export function useHrSearchPopupPortal(anchorRef, isOpen, { width = 720, gap = 10 } = {}) {
  const popupStyle = ref({})

  function positionPopup() {
    const el = anchorRef.value
    if (!el) return
    const rect = el.getBoundingClientRect()
    const popupWidth = Math.min(width, window.innerWidth - 32)
    let left = rect.right - popupWidth
    if (left < 16) left = 16
    if (left + popupWidth > window.innerWidth - 16) {
      left = Math.max(16, window.innerWidth - popupWidth - 16)
    }
    let top = rect.bottom + gap
    const viewportBottomPad = 16
    let maxHeight = window.innerHeight - top - viewportBottomPad
    if (maxHeight < 320 && rect.top > window.innerHeight - rect.bottom) {
      maxHeight = Math.max(280, rect.top - gap - viewportBottomPad)
      top = Math.max(16, rect.top - maxHeight - gap)
    }
    popupStyle.value = {
      position: 'fixed',
      top: `${top}px`,
      left: `${left}px`,
      width: `${popupWidth}px`,
      maxHeight: `${Math.max(280, maxHeight)}px`,
      zIndex: 100050,
    }
  }

  watch(isOpen, async (open) => {
    if (!open) return
    await nextTick()
    positionPopup()
  })

  onMounted(() => {
    window.addEventListener('resize', positionPopup)
    window.addEventListener('scroll', positionPopup, true)
  })

  onUnmounted(() => {
    window.removeEventListener('resize', positionPopup)
    window.removeEventListener('scroll', positionPopup, true)
  })

  return { popupStyle, positionPopup }
}
