import { computed, ref } from 'vue'

export const salesActionCenterOpen = ref(false)
export const salesActionAnchor = ref(null)

let openedAt = 0

/** Sales agents and managers keep Next Actions. Admin and super admin do not. */
export const canUseNextActions = computed(() => {
  try {
    const user = JSON.parse(localStorage.getItem('user') || '{}')
    const roles = Array.isArray(user.roles) ? user.roles : []
    if (roles.includes('super_admin') || roles.includes('admin')) return false
    return true
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
