import { computed, ref } from 'vue'

export const salesActionCenterOpen = ref(false)
export const salesActionAnchor = ref(null)

let openedAt = 0

/** Any signed-in CRM user can open Next Actions, including admin and super admin. */
export const canUseNextActions = computed(() => {
  try {
    return !!localStorage.getItem('token') && !!localStorage.getItem('user')
  } catch {
    return false
  }
})

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
  if (!canUseNextActions.value) return

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
