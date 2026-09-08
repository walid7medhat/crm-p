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
  const isHr = computed(() => roles.value.includes('hr'))

  const scopeLabel = computed(() => {
    if (isAdmin.value) return 'Company analytics'
    if (isManager.value) return 'Team analytics'
    return 'My analytics'
  })

  // 'leads-show' is the single permission gating both the Leads and Deals kanban
  // (they're tabs of the same view — see kanban_deal.vue). HR is excluded outright,
  // regardless of any other role/permission overlap, matching the header nav.
  const canViewModule = (module) => {
    const map = {
      crm: () => !isHr.value && (isManager.value || hasPermission(user.value, 'leads-show')),
      listing: () =>
        !isHr.value && (
          isManager.value
          || roles.value.includes('only show listings')
          || hasPermission(user.value, 'listings-list')
          || hasPermission(user.value, 'listings-show')
          || user.value?.is_listing_team
          || hasPermission(user.value, 'leads-show')
        ),
      hr: () => isAdmin.value || hasPermission(user.value, 'hr-view') || isHr.value,
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
    isHr,
    scopeLabel,
    canViewModule,
    hasPermission: (p) => hasPermission(user.value, p),
  }
}
