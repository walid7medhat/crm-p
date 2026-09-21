import { createRouter, createWebHistory } from 'vue-router'
import { resetSidebarLayout } from './composables/useSidebar.js'
import { clearAuthToken } from './plugins/axios.js'
import { installNavProgress } from './composables/useNavProgress.js'

/**
 * Route components are lazy-loaded so the initial main bundle no longer pulls
 * every page (HR, kanban, listings, maps, etc.). Auth guards / meta below are
 * unchanged — they do not require synchronous component constructors.
 */
const baseRoutes = [
    { path: '/settings/deal-costs', component: () => import('./pages/settings/DealCostSettings.vue'), meta: { requiresAuth: true, requiresAdmin: true } },
    { path: '/settings/evaluations', component: () => import('./pages/settings/EvaluationSettings.vue'), meta: { requiresAuth: true, requiresSuperAdmin: true } },
    { path: '/settings/user-duplicates-report', component: () => import('./pages/settings/user-duplicates-report.vue'), meta: { requiresAuth: true, requiresAdmin: true } },
    { path: '/evaluations/:id', component: () => import('./pages/evaluations/FillEvaluation.vue'), meta: { requiresAuth: true } },
    { path: '/import-pitrix', component: () => import('./components/kanban/leadList/ImportPitrix.vue'), meta: { requiresAuth: true, requiresAdmin: true } },
    { path: '/sync-bitrix-leads', component: () => import('./components/kanban/leadList/SyncBitrixLeads.vue'), meta: { requiresAuth: true, requiresAdmin: true } },
    { path: '/sync-responsible', component: () => import('./components/kanban/leadList/SyncResponsible.vue'), meta: { requiresAuth: true, requiresAdmin: true } },
    { path: '/area-coordinates', component: () => import('./pages/areas/BulkAreaCoordinates.vue'), meta: { requiresAuth: true, requiresAdmin: true } },
      { path: '/logs', component: () => import('./pages/logs/index.vue'), meta: { requiresAuth: true, requiresSuperAdmin: true } },
      {
        path: '/system-overview',
        name: 'system-overview',
        component: () => import('./pages/system-overview/SystemOverview.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
      },

      { path: '/attendance-monthly-reports', component: () => import('./pages/hr/attendance-monthly-reports.vue'), meta: { requiresAuth: true, requiresSuperAdmin: true } },
  // Kanban Route — Leads tab requires the 'show-leads' permission (or admin)
  { path: '/kanban', component: () => import('./pages/kanban.vue'), meta: { requiresAuth: true, requiresPermission: 'show-leads' } },
  { path: '/kanban_deal', component: () => import('./pages/kanban_deal.vue'), meta: { requiresAuth: true, requiresPermission: 'show-leads'} },
  {
    path: '/project-map',
    name: 'project-map',
    component: () => import('./pages/dev/ProjectMapPage.vue'),
    meta: {
      layout: false,
      requiresAuth: true,
      requiresSuperAdmin: true,
    },
  },
    { path: '/settings/kanban', component: () => import('./components/kanban/KanbanSettings.vue') },
    { path: '/settings/lead-scoring', component: () => import('./components/kanban/LeadScoringSettings.vue'), meta: { requiresAuth: true, requiresAdmin: true } },
  { path: '/lead-scoring', redirect: '/settings/lead-scoring' },

  { path: '/settings/stage-visibility', component: () => import('./components/kanban/stage/StageVisibility.vue'), meta: { requiresAuth: true } },

  // Background picker: any user can choose their own; superadmin manages the pool (handled in-page).
  { path: '/settings/background', component: () => import('./pages/settings/background.vue'), meta: { requiresAuth: true } },

  { path: '/lead-reports', component: () => import('./pages/lead-reports.vue'), meta: { requiresAuth: true, requiresSuperAdmin: true } },
  { path: '/lead-source-report', component: () => import('./pages/lead-source-report.vue'), meta: { requiresAuth: true, requiresAdmin: true } },
  { path: '/agent-performance', component: () => import('./components/lead-reports/AgentPerformanceReport.vue'), meta: { requiresAuth: true, requiresSuperAdmin: true } },
  { path: '/sales-intelligence', component: () => import('./pages/sales-intelligence/index.vue'), meta: { requiresAuth: true } },
  // HR dashboard: `resources/js/pages/hr/index.vue`
  { path: '/hr', component: () => import('./pages/hr/index.vue'), meta: { requiresAuth: true, requiresAdmin: true, allowHr: true  } },
  { path: '/hr/employees/:id', component: () => import('./pages/hr/employees/EmployeeProfilePage.vue'), meta: { requiresAuth: true, requiresAdmin: true, allowHr: true  } },
  { path: '/hr/assets/:id', component: () => import('./pages/hr/assets/AssetDetailsPage.vue'), meta: { requiresAuth: true, requiresAdmin: true, allowHr: true  } },
  { path: '/suggestion', component: () => import('./pages/suggestions/index.vue'), meta: { requiresAuth: true } },
  { path: '/investment-analysis', component: () => import('./pages/dashboard/investment.vue'), meta: { requiresAuth: true, requiresSuperAdmin: true } },
  { path: '/settings/city-investments', component: () => import('./pages/dashboard/city-settings.vue'), meta: { requiresAuth: true, requiresSuperAdmin: true } },
  { path: '/settings/roi-calculator', component: () => import('./pages/settings/roi-calculator.vue'), meta: { requiresAuth: true } },
  { path: '/settings/roe-calculator', component: () => import('./pages/settings/roe-calculator.vue'), meta: { requiresAuth: true } },
   { path: '/home', component: () => import('./pages/dashboard/home-analytics.vue'), meta: { requiresAuth: true, dashboardHome: true, analyticsHome: true } },
   { path: '/', component: () => import('./pages/dashboard/home-analytics.vue'), meta: { requiresAuth: true, dashboardHome: true, analyticsHome: true } },

  { path: '/table-basic', component: () => import('./pages/table/table-basic.vue'), meta: { requiresAuth: true } },
  { path: '/table-data', component: () => import('./pages/table/table-data.vue'), meta: { requiresAuth: true } },
  { path: '/property-form', component: () => import('./pages/listings/property-form.vue'), meta: { requiresAuth: true } },
  { path: '/listings/overview', redirect: '/' },
  { path: '/my-listing', component: () => import('./pages/listings/my-listing.vue'), meta: { requiresAuth: true } },
  { path: '/archive', component: () => import('./pages/listings/archive.vue'), meta: { requiresAuth: true } },
  { path: '/alllisting', component: () => import('./components/alllisting/AllLsting.vue'), meta: { requiresAuth: true } },
  { path: '/notify-me', component: () => import('./pages/listings/notify-me.vue'), meta: { requiresAuth: true } },
  { path: '/properties-map', component: () => import('./pages/listings/property-map.vue'), meta: { requiresAuth: true } },
  { path: '/property-details/:id', name: 'property.show', component: () => import('./components/alllisting/PropertyShow.vue'), meta: { requiresAuth: true } },
  { path: '/properties/:id/edit', name: 'property.edit', component: () => import('./pages/listings/edit-form.vue'), meta: { requiresAuth: true } },
  { path: '/need-approve-requests', name: 'property.approve', component: () => import('./pages/listings/PendingApprovalsTable.vue'), meta: { requiresAuth: true } },

  { path: '/assign-role', component: () => import('./pages/roleAccess/assign-role.vue'), meta: { requiresAuth: true } },
  { path: '/role-access', component: () => import('./pages/roleAccess/role-access.vue'), meta: { requiresAuth: true } },

  { path: '/sign-in', component: () => import('./pages/authentication/sign-in.vue'), name: 'login', meta: { layout: false, requiresAuth: false } },
  { path: '/sign-up', component: () => import('./pages/authentication/sign-up.vue'), meta: { layout: false, requiresAuth: false } },
  { path: '/forgot-password', component: () => import('./pages/authentication/forgot-password.vue'), meta: { layout: false, requiresAuth: false } },
  { path: '/reset-password', name: 'reset-password', component: () => import('./pages/authentication/reset-password.vue'), meta: { layout: false, requiresAuth: false } },

  { path: '/developers', component: () => import('./pages/developers/developers-list.vue'), meta: { requiresAuth: true } },
  { path: '/add-developer', component: () => import('./pages/developers/add-developer.vue'), meta: { requiresAuth: true } },
  { path: '/developers/:id/edit', component: () => import('./pages/developers/edit-developer.vue'), meta: { requiresAuth: true } },
  { path: '/developers/:id', component: () => import('./pages/developers/view-developer.vue'), meta: { requiresAuth: true } },

  { path: '/owners', component: () => import('./pages/owners/owners-list.vue'), meta: { requiresAuth: true } },
  { path: '/add-owner', component: () => import('./pages/owners/form.vue'), meta: { requiresAuth: true } },
  { path: '/owners/:id/edit', component: () => import('./pages/owners/form.vue'), name: 'edit-owner', meta: { requiresAuth: true } },
  { path: '/owners/:id', component: () => import('./pages/owners/view-owner.vue'), meta: { requiresAuth: true } },

  { path: '/property_types', component: () => import('./pages/property_types/property_types-list.vue'), meta: { requiresAuth: true } },
  { path: '/add-property_type', component: () => import('./pages/property_types/form.vue'), meta: { requiresAuth: true } },
  { path: '/property_types/:id/edit', component: () => import('./pages/property_types/form.vue'), name: 'edit-property_type', meta: { requiresAuth: true } },

  { path: '/unit_views', component: () => import('./pages/unit_views/unit_views-list.vue'), meta: { requiresAuth: true } },
  { path: '/add-unit_view', component: () => import('./pages/unit_views/form.vue'), meta: { requiresAuth: true } },
  { path: '/unit_views/:id/edit', component: () => import('./pages/unit_views/form.vue'), name: 'edit-unit_view', meta: { requiresAuth: true } },

  { path: '/layout_types', component: () => import('./pages/layout_types/layout_types-list.vue'), meta: { requiresAuth: true } },
  { path: '/add-layout_type', component: () => import('./pages/layout_types/form.vue'), meta: { requiresAuth: true } },
  { path: '/layout_types/:id/edit', component: () => import('./pages/layout_types/form.vue'), name: 'edit-layout_type', meta: { requiresAuth: true } },
  


  { path: '/areas', component: () => import('./pages/areas/areas-list.vue'), meta: { requiresAuth: true } },
  { path: '/add-area', component: () => import('./pages/areas/form.vue'), meta: { requiresAuth: true } },
  { path: '/areas/:id/edit', component: () => import('./pages/areas/form.vue'), name: 'edit-area', meta: { requiresAuth: true } },

  { path: '/roles', component: () => import('./pages/roles/roles-list.vue'), meta: { requiresAuth: true } },
  { path: '/add-role', component: () => import('./pages/roles/form.vue'), meta: { requiresAuth: true } },
  { path: '/roles/:id/edit', component: () => import('./pages/roles/form.vue'), name: 'edit-role', meta: { requiresAuth: true } },
  { path: '/roles/:id', component: () => import('./pages/roles/details.vue'), name: 'list-role', meta: { requiresAuth: true } },

  { path: '/my-requests', component: () => import('./pages/listings/my_requests.vue'), meta: { requiresAuth: true } },
  { path: '/my-orders', component: () => import('./pages/listings/my_order.vue'), meta: { requiresAuth: true } },
  { path: '/my-viewings', component: () => import('./pages/listings/my_viewings.vue'), meta: { requiresAuth: true } },
  { path: '/all-requests', name: 'all-requests', component: () => import('./pages/listings/AllRequests.vue'), meta: { requiresAuth: true } },
  { path: '/hotDeal-requests',name:'hotDeal-requests', component: () => import('./pages/listings/hot_deal_requests.vue'), meta: { requiresAuth: true } },
  
  { path: '/users', component: () => import('./pages/users/UsersList.vue'), meta: { requiresAuth: true } },
  { path: '/users/:id', component: () => import('./pages/users/UserDetail.vue'), meta: { requiresAuth: true } },
  { path: '/add-user', component: () => import('./pages/users/UserForm.vue'), meta: { requiresAuth: true } },
  { path: '/users/:id/edit', component: () => import('./pages/users/UserForm.vue'), name: 'edit-user', meta: { requiresAuth: true } },
  { path: '/view-profile', component: () => import('./pages/users/view-profile.vue'), name: 'profile', meta: { requiresAuth: true } },
  
  { path: '/team-tree', name: 'TeamTree', component: () => import('./components/Table/TeamTree.vue'), meta: { requiresAuth: true } },
  { path: '/notifications', component: () => import('./components/allNotifications.vue'), name: 'notifications', meta: { requiresAuth: true } },
  { path: '/admin/chat', component: () => import('./pages/chat/AdminChatDashboard.vue'), name: 'admin-chat', meta: { requiresAuth: true } },

  // Email (SUPER_ADMIN only)
  { path: '/email', component: () => import('./pages/email/email.vue'), name: 'email', meta: { requiresAuth: true, requiresSuperAdmin: true } },
  { path: '/email/starred', component: () => import('./pages/email/StarredEmail.vue'), name: 'email-starred', meta: { requiresAuth: true, requiresSuperAdmin: true } },
  { path: '/email/view', component: () => import('./pages/email/VeiwDetails.vue'), name: 'email-view', meta: { requiresAuth: true, requiresSuperAdmin: true } },
  
     { path: '/features', component: () => import('./pages/features/features-list.vue'), meta: { requiresAuth: true } },
  { path: '/add-features', component: () => import('./pages/features/form.vue'), meta: { requiresAuth: true } },
  { path: '/features/:id/edit', component: () => import('./pages/features/form.vue'), name: 'edit-layout_type', meta: { requiresAuth: true } },
  {
  path: '/projects',
  component: () => import('./pages/projects/projects-list.vue'),
  meta: { requiresAuth: true }
},
{
  path: '/add-projects',
  component: () => import('./pages/projects/form.vue'),
  meta: { requiresAuth: true }
},
{
  path: '/projects/:id/edit',
  component: () => import('./pages/projects/form.vue'),
  name: 'edit-project',
  meta: { requiresAuth: true }
},
{
  path: '/projects/:id',
  component: () => import('./pages/projects/show.vue'),
  name: 'show-project',
  meta: { requiresAuth: true }
},
{
    path:'/projects/:id/floor-plans',
    component: () => import('./pages/projects/FloorPlans.vue'),
    name:'project-floorplan',
    meta:{ requiresAuth: true }
}
]

const routes = import.meta.env.PROD
  ? baseRoutes
  : [
      ...baseRoutes,
      {
        path: '/__dev__/project-map',
        name: 'dev-project-map',
        component: () => import('./pages/dev/ProjectMapPage.vue'),
        meta: {
          layout: false,
          requiresAuth: true,
          requiresSuperAdmin: true,
          devToolsOnly: true,
        },
      },
    ]

const isTokenValid = () => {
  const token = localStorage.getItem('token')
  
  if (!token) {
    console.log('No token found')
    return false
  }
  
  try {
    const parts = token.split('.')
    if (parts.length !== 3) {
      console.log('Invalid token format')
      return false
    }
    
    const payload = JSON.parse(atob(parts[1]))
    
    if (!payload.exp) {
      console.log('Token has no expiration')
      return true 
    }
    
    const expirationTime = payload.exp * 1000
    const isValid = Date.now() < expirationTime
    
    if (!isValid) {
      console.log('Token expired')
    }
    
    return isValid
  } catch (error) {
    console.warn('Token validation error:', error)
    return false
  }
}

const logout = () => {
  resetSidebarLayout()

  // Every token source axios/resolveAuthToken() knows how to read from —
  // token, access_token (localStorage + sessionStorage).
  clearAuthToken()

  localStorage.removeItem('searchFilters')
  localStorage.removeItem('listingSearchFilters')
  localStorage.removeItem('user')
  localStorage.removeItem('refreshToken')
  localStorage.removeItem('impersonator_token')
  localStorage.removeItem('impersonator_user')

  // Defensive: expire any auth cookie a server response may have set, since
  // resolveAuthToken() falls back to reading these.
  document.cookie = 'token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/'
  document.cookie = 'access_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/'

  // Per-user kanban board cache (leads.vue) — wipe every cached board so the
  // next person to sign in on this browser never even briefly sees it.
  Object.keys(localStorage)
    .filter((key) => key.startsWith('kanban_leads_stages_cache_v2'))
    .forEach((key) => localStorage.removeItem(key))

  window.location.href = '/sign-in'
}

/** Sidebar “CRM” block (Kanban, reports, lead tools, investments) — must match header isSuperAdmin */
const isSuperAdminFromStorage = () => {
  try {
    const raw = localStorage.getItem('user')
    if (!raw) return false
    const u = JSON.parse(raw)
    return Array.isArray(u.roles) && u.roles.includes('super_admin')
  } catch {
    return false
  }
}
const isAdminFromStorage = () => {
  try {
    const raw = localStorage.getItem('user')
    if (!raw) return false
    const u = JSON.parse(raw)
    return Array.isArray(u.roles) && (u.roles.includes('super_admin') || u.roles.includes('admin'))
  } catch {
    return false
  }
}
const isHrFromStorage = () => {
  try {
    const raw = localStorage.getItem('user')
    if (!raw) return false
    const u = JSON.parse(raw)
    return Array.isArray(u.roles) && u.roles.includes('hr')
  } catch {
    return false
  }
}
const hasPermissionFromStorage = (permission) => {
  try {
    const raw = localStorage.getItem('user')
    if (!raw) return false
    const u = JSON.parse(raw)
    return Array.isArray(u.permissions) && u.permissions.includes(permission)
  } catch {
    return false
  }
}

const router = createRouter({
  history: createWebHistory(),
  routes,

  scrollBehavior() {
  return { top: 0 }
}
})


router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token')
  const isValidToken = isTokenValid()

  if (to.path.startsWith('/__dev__/')) {
    if (import.meta.env.PROD) {
      next('/project-map')
      return
    }
  }

  if (token && !isValidToken) {
    logout()
    next('/sign-in')
    return
  }

  if (to.meta.requiresAuth && !isValidToken) {
    next('/sign-in')
    return
  }

  if (to.matched.some((r) => r.meta.requiresSuperAdmin) && !isSuperAdminFromStorage()) {
    next('/')
    return
  }
      if (
        to.matched.some((r) => r.meta.requiresAdmin) &&
        !isAdminFromStorage() &&
        !(to.matched.some((r) => r.meta.allowHr) && isHrFromStorage())
      ) {
          next('/')
          return
      }

  const requiredPermission = to.matched.find((r) => r.meta.requiresPermission)?.meta.requiresPermission
  if (requiredPermission && !isAdminFromStorage() && !hasPermissionFromStorage(requiredPermission)) {
    console.log(`Permission "${requiredPermission}" required — redirecting home`)
    next('/')
    return
  }

  if (to.path === '/sign-in' && isValidToken) {
    console.log('User authenticated, redirecting to home')
    next('/')
  } else if ((to.path === '/sign-up' || to.path === '/forgot-password' || to.path === '/reset-password') && isValidToken) {
    console.log('User authenticated, redirecting from auth pages to home')
    next('/')
  } else {
    console.log('Navigation allowed')
    next()
  }
})

installNavProgress(router)

export default router
