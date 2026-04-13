// resources/js/config/api.js
const API_BASE_URL = 'https://listings.oiaproperties.com/api';

export const API_ENDPOINTS = {
  // Auth
  LOGIN: `${API_BASE_URL}/auth/login`,
  PROFILE: `${API_BASE_URL}/auth/profile`,
  
  // Developers
  DEVELOPERS: `${API_BASE_URL}/listings/developers`,
  DEVELOPER_BY_ID: (id) => `${API_BASE_URL}/listings/developers/${id}`,

  // Owners
  OWNERS: `${API_BASE_URL}/listings/owners`,
  OWNER_BY_ID: (id) => `${API_BASE_URL}/listings/owners/${id}`,

   // Property_types
  PROPERTY_TYPES: `${API_BASE_URL}/listings/property-types`,
  PROPERTY_TYPE_BY_ID : (id) => `${API_BASE_URL}/listings/property-types/${id}`,

     // unit_views
  UNIT_VIEWS: `${API_BASE_URL}/listings/unit_views`,
  UNIT_VIEW_BY_ID: (id) => `${API_BASE_URL}/listings/unit_views/${id}`,

   // layout_types
  LAYOUT_TYPES: `${API_BASE_URL}/listings/layout_types`,
  LAYOUT_TYPE_BY_ID: (id) => `${API_BASE_URL}/listings/layout_types/${id}`,
  
 
  // Areas
  AREAS: `${API_BASE_URL}/listings/areas`,
  AREA_BY_ID: (id) => `${API_BASE_URL}/listings/areas/${id}`,
  
  ROLES: `${API_BASE_URL}/roles`,
  ROLE_BY_ID: (id) => `${API_BASE_URL}/roles/${id}`,
  PERMISSIONS: `${API_BASE_URL}/permissions`,
  ASSIGN_PERMISSIONS: (id) => `${API_BASE_URL}/roles/${id}/permissions`,

  
    USERS: `${API_BASE_URL}/users`,
      USER_PERMISSIONS: (id) => `${API_BASE_URL}/users/${id}/permissions`,
  USER_BY_ID: (id) => `${API_BASE_URL}/users/${id}`,
    USER_MANAGERS: `${API_BASE_URL}/users/managers/available`,
  USER_STATISTICS: `${API_BASE_URL}/users/statistics`,
  USER_TEAM_MEMBERS: (userId) => `${API_BASE_URL}/users/${userId}/team-members`,
     TEAM_HIERARCHY: `${API_BASE_URL}/team/hierarchy`,
    USERS_WITH_CHILDREN: `${API_BASE_URL}/users/with-children`,
    USER_STATUS: (id) => `${API_BASE_URL}/users/${id}/status`,
    USER_BIOMETRIC: (id) => `${API_BASE_URL}/users/${id}/biometric-code`,

    
     // features
  FEATURES: `${API_BASE_URL}/listings/features`,
  Feature_BY_ID: (id) => `${API_BASE_URL}/listings/features/${id}`,
  
  PROJECTS: `${API_BASE_URL}/listings/projects`,
  PROJECT_BY_ID: (id) => `${API_BASE_URL}/listings/projects/${id}`,
    PROJECT_DEVELOPERS: `${API_BASE_URL}/listings/developers`,
  PROJECT_FEATURES: (id) => `${API_BASE_URL}/listings/features`,

};

export default API_BASE_URL;