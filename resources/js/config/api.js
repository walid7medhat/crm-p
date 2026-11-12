// resources/js/config/api.js — endpoint paths resolved at call time (same-origin).
import { getApiBaseUrl } from './apiBaseUrl.js'

function apiUrl(path) {
  const base = String(getApiBaseUrl() || '/api').replace(/\/$/, '')
  const suffix = path.startsWith('/') ? path : `/${path}`
  return `${base}${suffix}`
}

export const API_ENDPOINTS = {
  // Auth
  get LOGIN() {
    return apiUrl('/auth/login')
  },
  get PROFILE() {
    return apiUrl('/auth/profile')
  },
  get PROFILE_ATTENDANCE_HISTORY() {
    return apiUrl('/profile/attendance-history')
  },

  // Developers
  get DEVELOPERS() {
    return apiUrl('/listings/developers')
  },
  DEVELOPER_BY_ID: (id) => apiUrl(`/listings/developers/${id}`),

  // Owners
  get OWNERS() {
    return apiUrl('/listings/owners')
  },
  OWNER_BY_ID: (id) => apiUrl(`/listings/owners/${id}`),

  // Property_types
  get PROPERTY_TYPES() {
    return apiUrl('/listings/property-types')
  },
  PROPERTY_TYPE_BY_ID: (id) => apiUrl(`/listings/property-types/${id}`),

  // unit_views
  get UNIT_VIEWS() {
    return apiUrl('/listings/unit_views')
  },
  UNIT_VIEW_BY_ID: (id) => apiUrl(`/listings/unit_views/${id}`),

  // layout_types
  get LAYOUT_TYPES() {
    return apiUrl('/listings/layout_types')
  },
  LAYOUT_TYPE_BY_ID: (id) => apiUrl(`/listings/layout_types/${id}`),

  // Areas
  get AREAS() {
    return apiUrl('/listings/areas')
  },
  AREA_BY_ID: (id) => apiUrl(`/listings/areas/${id}`),

  get ROLES() {
    return apiUrl('/roles')
  },
  ROLE_BY_ID: (id) => apiUrl(`/roles/${id}`),
  get PERMISSIONS() {
    return apiUrl('/permissions')
  },
  ASSIGN_PERMISSIONS: (id) => apiUrl(`/roles/${id}/permissions`),

  get USERS() {
    return apiUrl('/users')
  },
  USER_PERMISSIONS: (id) => apiUrl(`/users/${id}/permissions`),
  USER_BY_ID: (id) => apiUrl(`/users/${id}`),
  get USER_MANAGERS() {
    return apiUrl('/users/managers/available')
  },
  get USER_STATISTICS() {
    return apiUrl('/users/statistics')
  },
  USER_TEAM_MEMBERS: (userId) => apiUrl(`/users/${userId}/team-members`),
  USER_ATTENDANCE_HISTORY: (userId) => apiUrl(`/users/${userId}/attendance-history`),
  get TEAM_HIERARCHY() {
    return apiUrl('/team/hierarchy')
  },
  get TEAM_REASSIGN() {
    return apiUrl('/team/reassign')
  },
  get USERS_WITH_CHILDREN() {
    return apiUrl('/users/with-children')
  },
  USER_STATUS: (id) => apiUrl(`/users/${id}/status`),
  USER_BIOMETRIC: (id) => apiUrl(`/users/${id}/biometric-code`),
  USER_IMPERSONATE: (id) => apiUrl(`/users/${id}/impersonate`),
  USER_ABSENT_EMAIL: (id) => apiUrl(`/users/${id}/absent-email`),

  // features
  get FEATURES() {
    return apiUrl('/listings/features')
  },
  Feature_BY_ID: (id) => apiUrl(`/listings/features/${id}`),

  get PROJECTS() {
    return apiUrl('/listings/projects')
  },
  PROJECT_BY_ID: (id) => apiUrl(`/listings/projects/${id}`),
  get PROJECT_DEVELOPERS() {
    return apiUrl('/listings/developers')
  },
  PROJECT_FEATURES: (id) => apiUrl('/listings/features'),

  get DEAL_COST_SETTINGS() {
    return apiUrl('/settings/deal-costs')
  },
  get DEAL_COST_SETTINGS_UPDATE() {
    return apiUrl('/settings/deal-costs')
  },
  DEAL_COST_SETTING_BY_KEY: (key) => apiUrl(`/settings/deal-costs/${key}`),
}

export { getApiBaseUrl }
export default getApiBaseUrl
