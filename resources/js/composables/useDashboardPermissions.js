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
  // Sales agent embedded in the listing team — same definition as header/index.vue.
  const isSalesInListingTeam = computed(() => {
    const hasSalesRole = roles.value.includes('sales') || roles.value.includes('Sales')
    const isInListingTeam = user.value?.is_listing_team === true || user.value?.is_listing_team === 1
    return hasSalesRole && isInListingTeam
  })

  const scopeLabel = computed(() => {
    if (isAdmin.value) return 'Company analytics'
    if (isManager.value) return 'Team analytics'
    return 'My analytics'
  })

  // 'show-leads' gates both the Leads and Deals kanban — same permission the router/header nav
  // use (there is no separate 'show-deals' permission; deals access mirrors leads access
  // exactly). Only admins bypass the permission outright (matching header/index.vue's
  // canShowLeadsTab) — NOT isManager, since that would let a listing-team manager through
  // regardless of the hierarchy-aware permission grant, defeating the whole point of it.
  // HR is excluded from 'crm'/'listing' outright, regardless of any other role/permission
  // overlap, matching the header nav.
  const canViewModule = (module) => {
    const map = {
      crm: () => !isHr.value && (isAdmin.value || hasPermission(user.value, 'show-leads')),
      deals: () => !isHr.value && (isAdmin.value || hasPermission(user.value, 'show-leads')),
      listing: () =>
        !isHr.value && (
          isManager.value
          || roles.value.includes('only show listings')
          || hasPermission(user.value, 'listings-list')
          || hasPermission(user.value, 'listings-show')
          || user.value?.is_listing_team
          || hasPermission(user.value, 'show-leads')
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
    isSalesInListingTeam,
    scopeLabel,
    canViewModule,
    hasPermission: (p) => hasPermission(user.value, p),
  }
}
