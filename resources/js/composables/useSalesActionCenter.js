import { ref } from 'vue'

export const salesActionCenterOpen = ref(false)
export const salesActionAnchor = ref(null)

let openedAt = 0

function rectOf(event) {
  const el = event?.currentTarget
  if (!el?.getBoundingClientRect) return null
  const rect = el.getBoundingClientRect()
  return {
    left: rect.left,
    top: rect.top,
    right: rect.right,
    bottom: rect.bottom,
    width: rect.width,
    height: rect.height,
  }
}

export function openSalesActionCenter(event) {
  const next = rectOf(event)
  if (next) salesActionAnchor.value = next

  if (salesActionCenterOpen.value) {
    if (Date.now() - openedAt < 400) return
    salesActionCenterOpen.value = false
    return
  }

  openedAt = Date.now()
  salesActionCenterOpen.value = true
}

export function closeSalesActionCenter() {
  salesActionCenterOpen.value = false
}
