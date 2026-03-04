import { createRouter, createWebHistory } from 'vue-router'

// DashBoard
import Ai from './pages/dashboard/ai.vue'
import Crm from './pages/dashboard/crm.vue'
import AllLsting from './components/alllisting/AllLsting.vue'
import PropertyShow from './components/alllisting/PropertyShow.vue'
import PropertyForm from './pages/listings/property-form.vue'
import EditPropertyForm from './pages/listings/edit-form.vue'
import MyListing from './pages/listings/my-listing.vue'
import Archive from './pages/listings/archive.vue'

// Table
import BasicTable from './pages/table/table-basic.vue'
import DataTable from './pages/table/table-data.vue'

// Users
import ViewProfile from './pages/users/view-profile.vue'
import UsersList from './pages/users/UsersList.vue'
import UserForm from './pages/users/UserForm.vue'
import UserDetail from './pages/users/UserDetail.vue'
import TeamTree from './components/Table/TeamTree.vue'
import InvitedUsers from './pages/users/invitedUsers.vue'

// Role and Permission
import AssignRole from './pages/roleAccess/assign-role.vue'
import RoleAccess from './pages/roleAccess/role-access.vue'

// authentication
import SignIn from './pages/authentication/sign-in.vue'
import SignUp from './pages/authentication/sign-up.vue'
import ForgotPassword from './pages/authentication/forgot-password.vue'

import DevelopersList from './pages/developers/developers-list.vue'
import AddDeveloper from './pages/developers/add-developer.vue'
import EditDeveloper from './pages/developers/edit-developer.vue'
import ViewDeveloper from './pages/developers/view-developer.vue'

import OwnersList from './pages/owners/owners-list.vue'
import OwnerForm from './pages/owners/form.vue'
import ViewOwner from './pages/owners/view-owner.vue'

import PropertyTypesList from './pages/property_types/property_types-list.vue'
import PropertyTypeForm from './pages/property_types/form.vue'

import UnitViewsList from './pages/unit_views/unit_views-list.vue'
import UnitViewForm from './pages/unit_views/form.vue'

import LayoutTypesList from './pages/layout_types/layout_types-list.vue'
import LayoutTypeForm from './pages/layout_types/form.vue'

import AreasList from './pages/areas/areas-list.vue'
import AreaForm from './pages/areas/form.vue'

import RolesList from './pages/roles/roles-list.vue'
import RoleForm from './pages/roles/form.vue'
import RoleDetails from './pages/roles/details.vue'

import MyRequests from './pages/listings/my_requests.vue'
import MyOrders from './pages/listings/my_order.vue'
import AllRequests from './pages/listings/AllRequests.vue'

import allNotifications from './components/allNotifications.vue'

// Kanban
import Kanban from './pages/kanban.vue'
import kanban_deal from './pages/kanban_deal.vue'

// Reports
import Reports from './pages/reports/index.vue'

// Suggestions
import Suggestions from './pages/suggestions/index.vue'

import FeaturesList from './pages/features/features-list.vue'
import FeatureForm from './pages/features/form.vue'

import ProjectsTable from './pages/projects/projects-list.vue'
import ProjectForm from './pages/projects/form.vue'
import ProjectDetails from './pages/projects/show.vue'
import FloorPlans from './pages/projects/FloorPlans.vue'
const routes = [
  // Kanban Route
  { path: '/kanban', component: Kanban },
  { path: '/kanban_deal', component: kanban_deal },
  { path: '/reports', component: Reports, meta: { requiresAuth: true } },
  { path: '/suggestion', component: Suggestions, meta: { requiresAuth: true } },
  { path: '/', component: Ai, meta: { requiresAuth: true } },

  { path: '/table-basic', component: BasicTable, meta: { requiresAuth: true } },
  { path: '/table-data', component: DataTable, meta: { requiresAuth: true } },
  { path: '/property-form', component: PropertyForm, meta: { requiresAuth: true } },
  { path: '/my-listing', component: MyListing, meta: { requiresAuth: true } },
  { path: '/archive', component: Archive, meta: { requiresAuth: true } },
  { path: '/alllisting', component: AllLsting, meta: { requiresAuth: true } },
  { path: '/property-details/:id', name: 'property.show', component: PropertyShow, meta: { requiresAuth: true } },
  { path: '/properties/:id/edit', name: 'property.edit', component: EditPropertyForm, meta: { requiresAuth: true } },

  { path: '/assign-role', component: AssignRole, meta: { requiresAuth: true } },
  { path: '/role-access', component: RoleAccess, meta: { requiresAuth: true } },

  { path: '/sign-in', component: SignIn, name: 'login', meta: { layout: false, requiresAuth: false } },
  { path: '/sign-up', component: SignUp, meta: { layout: false, requiresAuth: false } },
  { path: '/forgot-password', component: ForgotPassword, meta: { layout: false, requiresAuth: false } },

  { path: '/developers', component: DevelopersList, meta: { requiresAuth: true } },
  { path: '/add-developer', component: AddDeveloper, meta: { requiresAuth: true } },
  { path: '/developers/:id/edit', component: EditDeveloper, meta: { requiresAuth: true } },
  { path: '/developers/:id', component: ViewDeveloper, meta: { requiresAuth: true } },

  { path: '/owners', component: OwnersList, meta: { requiresAuth: true } },
  { path: '/add-owner', component: OwnerForm, meta: { requiresAuth: true } },
  { path: '/owners/:id/edit', component: OwnerForm, name: 'edit-owner', meta: { requiresAuth: true } },
  { path: '/owners/:id', component: ViewOwner, meta: { requiresAuth: true } },

  { path: '/property_types', component: PropertyTypesList, meta: { requiresAuth: true } },
  { path: '/add-property_type', component: PropertyTypeForm, meta: { requiresAuth: true } },
  { path: '/property_types/:id/edit', component: PropertyTypeForm, name: 'edit-property_type', meta: { requiresAuth: true } },

  { path: '/unit_views', component: UnitViewsList, meta: { requiresAuth: true } },
  { path: '/add-unit_view', component: UnitViewForm, meta: { requiresAuth: true } },
  { path: '/unit_views/:id/edit', component: UnitViewForm, name: 'edit-unit_view', meta: { requiresAuth: true } },

  { path: '/layout_types', component: LayoutTypesList, meta: { requiresAuth: true } },
  { path: '/add-layout_type', component: LayoutTypeForm, meta: { requiresAuth: true } },
  { path: '/layout_types/:id/edit', component: LayoutTypeForm, name: 'edit-layout_type', meta: { requiresAuth: true } },
  


  { path: '/areas', component: AreasList, meta: { requiresAuth: true } },
  { path: '/add-area', component: AreaForm, meta: { requiresAuth: true } },
  { path: '/areas/:id/edit', component: AreaForm, name: 'edit-area', meta: { requiresAuth: true } },

  { path: '/roles', component: RolesList, meta: { requiresAuth: true } },
  { path: '/add-role', component: RoleForm, meta: { requiresAuth: true } },
  { path: '/roles/:id/edit', component: RoleForm, name: 'edit-role', meta: { requiresAuth: true } },
  { path: '/roles/:id', component: RoleDetails, name: 'list-role', meta: { requiresAuth: true } },

  { path: '/my-requests', component: MyRequests, meta: { requiresAuth: true } },
  { path: '/my-orders', component: MyOrders, meta: { requiresAuth: true } },
  { path: '/all-requests', name: 'all-requests', component: AllRequests, meta: { requiresAuth: true } },
  
  { path: '/users', component: UsersList, meta: { requiresAuth: true } },
  { path: '/users/:id', component: UserDetail, meta: { requiresAuth: true } },
  { path: '/add-user', component: UserForm, meta: { requiresAuth: true } },
  { path: '/users/:id/edit', component: UserForm, name: 'edit-user', meta: { requiresAuth: true } },
  { path: '/view-profile', component: ViewProfile, name: 'profile', meta: { requiresAuth: true } },
  
  { path: '/team-tree', name: 'TeamTree', component: TeamTree, meta: { requiresAuth: true } },
  { path: '/notifications', component: allNotifications, name: 'notifications', meta: { requiresAuth: true } },
  
  
     { path: '/features', component: FeaturesList, meta: { requiresAuth: true } },
  { path: '/add-features', component: FeatureForm, meta: { requiresAuth: true } },
  { path: '/features/:id/edit', component: FeatureForm, name: 'edit-layout_type', meta: { requiresAuth: true } },
  {
  path: '/projects',
  component: ProjectsTable,
  meta: { requiresAuth: true }
},
{
  path: '/add-projects',
  component: ProjectForm,
  meta: { requiresAuth: true }
},
{
  path: '/projects/:id/edit',
  component: ProjectForm,
  name: 'edit-project',
  meta: { requiresAuth: true }
},
{
  path: '/projects/:id',
  component: ProjectDetails,
  name: 'show-project',
  meta: { requiresAuth: true }
},
{
    path:'/projects/:id/floor-plans',
    component:FloorPlans,
    name:'project-floorplan',
    meta:{ requiresAuth: true }
}
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
  localStorage.removeItem('token')
  localStorage.removeItem('user')
  localStorage.removeItem('refreshToken')
  
  window.location.href = '/sign-in'
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

  console.log(`Navigation to: ${to.path}, Token valid: ${isValidToken}`)

  if (token && !isValidToken) {
    console.log('Token exists but is invalid, logging out...')
    logout()
    next('/sign-in')
    return
  }

  if (to.meta.requiresAuth && !isValidToken) {
    console.log('Auth required, redirecting to sign-in')
    next('/sign-in')
  } else if (to.path === '/sign-in' && isValidToken) {
    console.log('User authenticated, redirecting to home')
    next('/')
  } else if ((to.path === '/sign-up' || to.path === '/forgot-password') && isValidToken) {
    console.log('User authenticated, redirecting from auth pages to home')
    next('/')
  } else {
    console.log('Navigation allowed')
    next()
  }
})

export default router