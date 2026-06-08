import { computed } from 'vue'

function readUser() {
  try {
    return JSON.parse(localStorage.getItem('user') || 'null')
  } catch {
    return null
  }
}

function hasPermission(user, permission) {
  const permissions = user?.permissions || []
  return permissions.includes(permission)
}

export function useDashboardPermissions() {
  const user = computed(() => readUser())

  const roles = computed(() => {
    const r = user.value?.roles
    return Array.isArray(r) ? r : []
  })

  const isSuperAdmin = computed(() => roles.value.includes('super_admin'))
  const isAdmin = computed(() => isSuperAdmin.value || roles.value.includes('admin'))
  const isManager = computed(() => isAdmin.value || roles.value.includes('manager'))
  const isAgent = computed(() => !isManager.value)

  const scopeLabel = computed(() => {
    if (isAdmin.value) return 'Company analytics'
    if (isManager.value) return 'Team analytics'
    return 'My analytics'
  })

  const canViewModule = (module) => {
    const map = {
      crm: () => isManager.value || hasPermission(user.value, 'leads-list') || hasPermission(user.value, 'leads-show'),
      listing: () =>
        isManager.value
        || hasPermission(user.value, 'listings-list')
        || hasPermission(user.value, 'listings-show')
        || user.value?.is_listing_team
        || hasPermission(user.value, 'leads-list')
        || hasPermission(user.value, 'leads-show'),
      hr: () => isAdmin.value || hasPermission(user.value, 'hr-view') || roles.value.includes('hr'),
      finance: () => isAdmin.value || hasPermission(user.value, 'finance-view'),
      support: () => isManager.value || isAdmin.value,
      ai: () => true,
    }
    return map[module]?.() ?? isAdmin.value
  }

  return {
    user,
    roles,
    isSuperAdmin,
    isAdmin,
    isManager,
    isAgent,
    scopeLabel,
    canViewModule,
    hasPermission: (p) => hasPermission(user.value, p),
  }
}
