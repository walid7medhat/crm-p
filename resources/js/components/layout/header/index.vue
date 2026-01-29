<template>
  <aside class="sidebar" :class="{ 'sidebar-open': isMobileOpen }">
    <!-- Mobile Close -->
    <button type="button" class="sidebar-close-btn" @click="closeSidebar">
      <iconify-icon icon="radix-icons:cross-2" />
    </button>
    
    <div class="sidebar-toggle-container d-flex align-items-center justify-content-between">
      <!-- Logo -->
      <router-link to="/" class="sidebar-logo d-flex flex-wrap align-items-space-between gap-4">
        <img :src="logo" alt="Logo" class="light-logo" />
        <img :src="logo" alt="Logo" class="dark-logo" />
        <img :src="logo" alt="Logo" class="logo-icon" />
    
      </router-link>

      <button v-if="!isSidebarActive" type="button" class="sidebar-toggle" @click="toggleSidebarDesktop">
        <iconify-icon icon='heroicons:bars-3-solid'
          class="icon text-2xl"></iconify-icon>
      </button>
    </div>
    <button v-if="isSidebarActive" type="button" class="sidebar-toggle" @click="toggleSidebarDesktop">
      <iconify-icon icon='iconoir:arrow-right'
        class="icon text-2xl"></iconify-icon>
    </button>
    <!-- Menu -->
    <div class="sidebar-menu-area">
      <ul class="sidebar-menu">
        <li>
          <router-link to="/" :class="{ active: isActive('/') }">
            <!--<iconify-icon icon="solar:home-smile-angle-outline" class="menu-icon" />-->
            <img :src="dashboardIcon" class="imgicon"/>
            <span>Dashboard</span>
          </router-link>
        </li>
          <!-- <li>
          <router-link to="/leads" @click="() => goToRoute('/leads')" :class="{ 'active-page': isActive('/leads') }">
            <iconify-icon icon="material-symbols:map-outline" class="menu-icon" />
            <span>Lead</span>
          </router-link>
        </li>
         <li>
          <router-link to="/kanban" @click="() => goToRoute('/kanban')" :class="{ 'active-page': isActive('/kanban') }">
            <iconify-icon icon="material-symbols:map-outline" class="menu-icon" />
            <span>Kanban</span>
          </router-link>
        </li> -->
        <!-- Listings Dropdown -->
        <li v-if="filteredTableItems.length > 0" :class="{ 
          dropdown: true, 
          open: activeDropdown === 'table',
          'active-parent': isTableActive 
        }">
          <a href="javascript:void(0)" @click="toggleDropdown('table')" :class="{ active: isTableActive }">
            <!--<iconify-icon icon="mingcute:storage-line" class="menu-icon"></iconify-icon>-->
             <img :src="listingsIcon" class="imgicon"/>
            <span>Listings</span>
            <span class="dropdown-arrow" :class="{ rotated: activeDropdown === 'table' }"></span>
          </a>
          <transition @before-enter="beforeEnter" @enter="enter" @after-enter="afterEnter" @before-leave="beforeLeave"
            @leave="leave" @after-leave="afterLeave">
            <ul v-show="activeDropdown === 'table'" ref="tableMenu" class="sidebar-submenu">
              <li v-for="item in filteredTableItems" :key="item.path" :class="['nav-link', { 'active-page': isActive(item.path) }]">
                <router-link :to="item.path">
                  
                  <span class="menu-label">{{ item.label }}</span>
                  <span v-if="item.count > 0" class="menu-count">{{ item.count }}</span>
                  <span v-else-if="countsLoading" class="menu-count loading">...</span>
                </router-link>
              </li>
            </ul>
          </transition>
        </li>
         <!--features-->
          <li v-if="filteredProjectsItems.length > 0" :class="{ 
          dropdown: true, 
          open: activeDropdown === 'projects',
          'active-parent': isProjectsActive 
        }">
          <a href="javascript:void(0)" @click="toggleDropdown('projects')" :class="{ active: isProjectsActive }">
            <!--<iconify-icon icon="lucide:building" class="menu-icon"></iconify-icon>-->
            <img :src="propertyIcon" class="imgicon"/>
            <span>Projects</span>
            <span class="dropdown-arrow" :class="{ rotated: activeDropdown === 'projects' }"></span>
          </a>
          <transition @before-enter="beforeEnter" @enter="enter" @after-enter="afterEnter" @before-leave="beforeLeave"
            @leave="leave" @after-leave="afterLeave">
            <ul v-show="activeDropdown === 'projects'" ref="ProjectsMenu" class="sidebar-submenu">
              <li v-for="item in filteredProjectsItems" :key="item.path" :class="['nav-link', { 'active-page': isActive(item.path) }]">
                <router-link :to="item.path">
                  {{ item.label }}
                </router-link>
              </li>
            </ul>
          </transition>
        </li>
        <!-- Requests & Orders Dropdown -->
        <li v-if="filteredRequestsItems.length > 0" :class="{ 
          dropdown: true, 
          open: activeDropdown === 'requests',
          'active-parent': isRequestsActive 
        }">
          <a href="javascript:void(0)" @click="toggleDropdown('requests')" :class="{ active: isRequestsActive }">
            <!--<iconify-icon icon="lucide:shield-question" class="menu-icon"></iconify-icon>-->
             <img :src="requestsIcon" class="imgicon"/>
            <span>Requests</span>
            <span class="dropdown-arrow" :class="{ rotated: activeDropdown === 'requests' }"></span>
          </a>
          <transition @before-enter="beforeEnter" @enter="enter" @after-enter="afterEnter" @before-leave="beforeLeave"
            @leave="leave" @after-leave="afterLeave">
            <ul v-show="activeDropdown === 'requests'" ref="requestsMenu" class="sidebar-submenu">
              <li v-for="item in filteredRequestsItems" :key="item.path" :class="['nav-link', { 'active-page': isActive(item.path) }]">
                <router-link :to="item.path">
                  
                  <span class="menu-label">{{ item.label }}</span>
                  <span v-if="item.count > 0" class="menu-count">{{ item.count }}</span>
                  <span v-else-if="countsLoading" class="menu-count loading">...</span>
                </router-link>
              </li>
            </ul>
          </transition>
        </li>

        <!-- Developers Dropdown -->
         <li v-if="filteredDevelopersItems.length > 0" :class="{ 
          dropdown: true, 
          open: activeDropdown === 'developers',
          'active-parent': isDevelopersActive 
        }">
          <a href="javascript:void(0)" @click="toggleDropdown('developers')" :class="{ active: isDevelopersActive }">
            <iconify-icon icon="lucide:code" class="menu-icon"></iconify-icon>
            <span>Developers</span>
            <span class="dropdown-arrow" :class="{ rotated: activeDropdown === 'developers' }"></span>
          </a>
          <transition @before-enter="beforeEnter" @enter="enter" @after-enter="afterEnter" @before-leave="beforeLeave"
            @leave="leave" @after-leave="afterLeave">
            <ul v-show="activeDropdown === 'developers'" ref="developersMenu" class="sidebar-submenu">
              <li v-for="item in filteredDevelopersItems" :key="item.path" :class="['nav-link', { 'active-page': isActive(item.path) }]">
                <router-link :to="item.path">
                  {{ item.label }}
                </router-link>
              </li>
            </ul>
          </transition>
        </li> 

        <!-- Owners Dropdown -->
        <li v-if="filteredOwnersItems.length > 0" :class="{ 
          dropdown: true, 
          open: activeDropdown === 'owners',
          'active-parent': isOwnersActive 
        }">
          <a href="javascript:void(0)" @click="toggleDropdown('owners')" :class="{ active: isOwnersActive }">
            <!--<iconify-icon icon="lucide:users" class="menu-icon"></iconify-icon>-->
            <img :src="ownersIcon" class="imgicon"/>
            <span>Owners</span>
            <span class="dropdown-arrow" :class="{ rotated: activeDropdown === 'owners' }"></span>
          </a>
          <transition @before-enter="beforeEnter" @enter="enter" @after-enter="afterEnter" @before-leave="beforeLeave"
            @leave="leave" @after-leave="afterLeave">
            <ul v-show="activeDropdown === 'owners'" ref="ownersMenu" class="sidebar-submenu">
              <li v-for="item in filteredOwnersItems" :key="item.path" :class="['nav-link', { 'active-page': isActive(item.path) }]">
                <router-link :to="item.path">
                  {{ item.label }}
                </router-link>
              </li>
            </ul>
          </transition>
        </li>

        <!-- Property Types Dropdown -->
        <li v-if="filteredPropertyTypesItems.length > 0" :class="{ 
          dropdown: true, 
          open: activeDropdown === 'property_types',
          'active-parent': isPropertyTypesActive 
        }">
          <a href="javascript:void(0)" @click="toggleDropdown('property_types')" :class="{ active: isPropertyTypesActive }">
            <!--<iconify-icon icon="lucide:building" class="menu-icon"></iconify-icon>-->
            <img :src="propertyIcon" class="imgicon"/>
            <span>Property Types</span>
            <span class="dropdown-arrow" :class="{ rotated: activeDropdown === 'property_types' }"></span>
          </a>
          <transition @before-enter="beforeEnter" @enter="enter" @after-enter="afterEnter" @before-leave="beforeLeave"
            @leave="leave" @after-leave="afterLeave">
            <ul v-show="activeDropdown === 'property_types'" ref="PropertyTypesMenu" class="sidebar-submenu">
              <li v-for="item in filteredPropertyTypesItems" :key="item.path" :class="['nav-link', { 'active-page': isActive(item.path) }]">
                <router-link :to="item.path">
                  {{ item.label }}
                </router-link>
              </li>
            </ul>
          </transition>
        </li>
        <!--features-->
          <li v-if="filteredFeaturesItems.length > 0" :class="{ 
          dropdown: true, 
          open: activeDropdown === 'features',
          'active-parent': isFeaturesActive 
        }">
          <a href="javascript:void(0)" @click="toggleDropdown('features')" :class="{ active: isFeaturesActive }">
            <!--<iconify-icon icon="lucide:building" class="menu-icon"></iconify-icon>-->
            <img :src="propertyIcon" class="imgicon"/>
            <span>Features</span>
            <span class="dropdown-arrow" :class="{ rotated: activeDropdown === 'features' }"></span>
          </a>
          <transition @before-enter="beforeEnter" @enter="enter" @after-enter="afterEnter" @before-leave="beforeLeave"
            @leave="leave" @after-leave="afterLeave">
            <ul v-show="activeDropdown === 'features'" ref="FeaturesMenu" class="sidebar-submenu">
              <li v-for="item in filteredFeaturesItems" :key="item.path" :class="['nav-link', { 'active-page': isActive(item.path) }]">
                <router-link :to="item.path">
                  {{ item.label }}
                </router-link>
              </li>
            </ul>
          </transition>
        </li>
                      
        <!-- Unit Views Dropdown -->
        <li v-if="filteredUnitViewsItems.length > 0" :class="{ 
          dropdown: true, 
          open: activeDropdown === 'unit_views',
          'active-parent': isUnitViewsActive 
        }">
          <a href="javascript:void(0)" @click="toggleDropdown('unit_views')" :class="{ active: isUnitViewsActive }">
            <!--<iconify-icon icon="lucide:eye" class="menu-icon"></iconify-icon>-->
            <img :src="unitViewIcon" class="imgicon"/>

            <span>Unit Views</span>
            <span class="dropdown-arrow" :class="{ rotated: activeDropdown === 'unit_views' }"></span>
          </a>
          <transition @before-enter="beforeEnter" @enter="enter" @after-enter="afterEnter" @before-leave="beforeLeave"
            @leave="leave" @after-leave="afterLeave">
            <ul v-show="activeDropdown === 'unit_views'" ref="UnitViewsMenu" class="sidebar-submenu">
              <li v-for="item in filteredUnitViewsItems" :key="item.path" :class="['nav-link', { 'active-page': isActive(item.path) }]">
                <router-link :to="item.path">
                  {{ item.label }}
                </router-link>
              </li>
            </ul>
          </transition>
        </li>

        <!-- Layout Types Dropdown -->
        <li v-if="filteredLayoutTypesItems.length > 0" :class="{ 
          dropdown: true, 
          open: activeDropdown === 'layout_types',
          'active-parent': isLayoutTypesActive 
        }">
          <a href="javascript:void(0)" @click="toggleDropdown('layout_types')" :class="{ active: isLayoutTypesActive }">
            <!--<iconify-icon icon="lucide:grid-3x3" class="menu-icon"></iconify-icon>-->
            <img :src="layoutTypeIcon" class="imgicon"/>
            <span>Layout Types</span>
            <span class="dropdown-arrow" :class="{ rotated: activeDropdown === 'layout_types' }"></span>
          </a>
          <transition @before-enter="beforeEnter" @enter="enter" @after-enter="afterEnter" @before-leave="beforeLeave"
            @leave="leave" @after-leave="afterLeave">
            <ul v-show="activeDropdown === 'layout_types'" ref="LayoutTypesMenu" class="sidebar-submenu">
              <li v-for="item in filteredLayoutTypesItems" :key="item.path" :class="['nav-link', { 'active-page': isActive(item.path) }]">
                <router-link :to="item.path">
                  {{ item.label }}
                </router-link>
              </li>
            </ul>
          </transition>
        </li>
              
        <!-- Areas Dropdown -->
        <li v-if="filteredAreasItems.length > 0" :class="{ 
          dropdown: true, 
          open: activeDropdown === 'areas',
          'active-parent': isAreasActive 
        }">
          <a href="javascript:void(0)" @click="toggleDropdown('areas')" :class="{ active: isAreasActive }">
            <!--<iconify-icon icon="lucide:map-pinned"  class="menu-icon"></iconify-icon>-->
            <img :src="locationIcon" class="imgicon"/>
            <span>Areas</span>
            <span class="dropdown-arrow" :class="{ rotated: activeDropdown === 'areas' }"></span>
          </a>
          <transition @before-enter="beforeEnter" @enter="enter" @after-enter="afterEnter" @before-leave="beforeLeave"
            @leave="leave" @after-leave="afterLeave">
            <ul v-show="activeDropdown === 'areas'" ref="AreasMenu" class="sidebar-submenu">
              <li v-for="item in filteredAreasItems" :key="item.path" :class="['nav-link', { 'active-page': isActive(item.path) }]">
                <router-link :to="item.path">
                  {{ item.label }}
                </router-link>
              </li>
            </ul>
          </transition>
        </li>
          
        <!-- Users Dropdown -->
        <li v-if="filteredUsersItems.length > 0" :class="{ 
          dropdown: true, 
          open: activeDropdown === 'users',
          'active-parent': isUsersActive 
        }">
          <a href="javascript:void(0)" @click="toggleDropdown('users')" :class="{ active: isUsersActive }">
            <!--<iconify-icon icon="flowbite:users-group-outline" class="menu-icon"></iconify-icon>-->
            <img :src="agentsIcon" class="imgicon"/>
            <span>Agents</span>
            <span class="dropdown-arrow" :class="{ rotated: activeDropdown === 'users' }"></span>
          </a>
          <transition @before-enter="beforeEnter" @enter="enter" @after-enter="afterEnter" @before-leave="beforeLeave"
            @leave="leave" @after-leave="afterLeave">
            <ul v-show="activeDropdown === 'users'" ref="usersMenu" class="sidebar-submenu">
              <li v-for="item in filteredUsersItems" :key="item.path" :class="['nav-link', { 'active-page': isActive(item.path) }]">
                <router-link :to="item.path">
                  {{ item.label }}
                </router-link>
              </li>
            </ul>
          </transition>
        </li>

        <!-- Role & Access Dropdown -->
        <li v-if="filteredRolesItems.length > 0" :class="{ 
          dropdown: true, 
          open: activeDropdown === 'role',
          'active-parent': isRolesActive 
        }">
          <a href="javascript:void(0)" @click="toggleDropdown('role')" :class="{ active: isRolesActive }">
            <!--<i class="ri-user-settings-line text-xl me-14 d-flex w-auto"></i>-->
            <img :src="roleIcon" class="imgicon"/>
            <span>Role & Access</span>
            <span class="dropdown-arrow" :class="{ rotated: activeDropdown === 'role' }"></span>
          </a>
          <transition @before-enter="beforeEnter" @enter="enter" @after-enter="afterEnter" @before-leave="beforeLeave"
            @leave="leave" @after-leave="afterLeave">
            <ul v-show="activeDropdown === 'role'" ref="roleMenu" class="sidebar-submenu">
              <li v-for="item in filteredRolesItems" :key="item.path" :class="['nav-link', { 'active-page': isActive(item.path) }]">
                <router-link :to="item.path">
                  {{ item.label }}
                </router-link>
              </li>
            </ul>
          </transition>
        </li>
      </ul>
    </div>
  </aside>
</template>

<script setup>
import { ref, computed, onMounted, getCurrentInstance, watch } from 'vue';
import { useRoute } from 'vue-router';
import api from '@/plugins/axios';

const logo = ref('/assets/images/LogoWhite.png');
const dashboardIcon=ref('/assets/icons/dashboard-icon.svg');
const listingsIcon=ref('/assets/icons/listings-icon.svg');
const requestsIcon=ref('/assets/icons/request-icon.svg');
const ownersIcon=ref('/assets/icons/owners-icon.svg');
const propertyIcon=ref('/assets/icons/property-icon-white.svg');
const unitViewIcon=ref('/assets/icons/unit-view-icon.svg');
const layoutTypeIcon=ref('/assets/icons/layout-icon.svg');
const locationIcon=ref('/assets/icons/area-icon.svg');
const agentsIcon=ref('/assets/icons/agents-icon.svg');
const roleIcon=ref('/assets/icons/role-icon.svg');

const route = useRoute();
const activeDropdown = ref(null);
const isMobileOpen = ref(false);
const countsLoading = ref(false);
const isSidebarActive = ref(false);
const { proxy } = getCurrentInstance();

const getUserFromStorage = () => {
  try {
    const userData = localStorage.getItem('user')
    return userData ? JSON.parse(userData) : null
  } catch (error) {
    console.error('Error getting user from storage:', error)
    return null
  }
}

const user = ref(getUserFromStorage());

const isAdmin = computed(() => {
  if (!user.value) return false;
  
  const isAdminUser = user.value.roles?.includes('super_admin') || 
                     user.value.roles?.includes('admin') ||
                     proxy.$hasPermission('admin');
  
  return isAdminUser;
});

const tableItems = computed(() => {
  const items = [
    { path: '/alllisting', label: 'All Listing', colorClass: 'text-warning-main w-auto', count: 0 },
    { path: '/property-form', label: 'Create Listing', colorClass: 'text-info-main w-auto', permission: 'listings-create', count: 0 },
  ]

  if (!isAdmin.value) {
    items.splice(1, 0, {
      path: '/my-listing',
      label: 'My Listing',
      colorClass: 'text-warning-main w-auto',
      permission: 'listings-list',
      count: 0
    })
  }

  return items
})

const requestsItems = computed(() => {
  if (isAdmin.value) {
    return [
      { path: '/all-requests', label: 'All Requests', colorClass: 'text-white w-auto', count: 0 }
    ]
  } else {
    return [
      { path: '/my-requests', label: 'Inbound Request', colorClass: 'text-white w-auto', count: 0 },
      { path: '/my-orders', label: 'Outbound Request', colorClass: 'text-white w-auto', count: 0 },
    ]
  }
});

const fetchAllCounts = async () => {
  try {
    countsLoading.value = true;
    const response = await api.get('/sidebar/counts');
    
    if (response.data.success) {
      const counts = response.data.data;
      
      tableItems.value.forEach(item => {
        if (item.path === '/alllisting') item.count = counts.listings.all || 0;
        if (item.path === '/my-listing') item.count = counts.listings.my || 0;
        if (item.path === '/archive') item.count = counts.listings.archive || 0;
      });
      
      if (isAdmin.value) {
        const totalRequests =  (counts.orders.all || 0);
        requestsItems.value.forEach(item => {
          if (item.path === '/all-requests') item.count = totalRequests;
        });
      } else {
        requestsItems.value.forEach(item => {
          if (item.path === '/my-requests') item.count = counts.requests.all || 0;
          if (item.path === '/my-orders') item.count = counts.orders.all || 0;
        });
      }
    }
  } catch (error) {
    console.error('Error fetching sidebar counts:', error);
  } finally {
    countsLoading.value = false;
  }
};

// Computed properties
const filteredTableItems = computed(() => {
  return tableItems.value.filter(item => {
    if (!item.permission) return true;
    return proxy.$hasPermission(item.permission); 
  });
});

const filteredRequestsItems = computed(() => {
  return requestsItems.value.filter(item => {
    if (!item.permission) return true;
    return proxy.$hasPermission(item.permission); 
  });
});

const roleItems = [
  { path: '/roles', label: 'Role & Access', colorClass: 'text-white w-auto', permission: 'roles-list' },
  { path: '/add-role', label: 'Add Role', colorClass: 'text-warning-main w-auto', permission: 'roles-create' },
]

const developersItems = [
  { path: '/developers', label: 'Developers List', colorClass: 'text-white w-auto', permission: 'developers-list' },
  { path: '/add-developer', label: 'Add Developer', colorClass: 'text-white w-auto', permission: 'developers-create' },
]

const ownersItems = [
  { path: '/owners', label: 'Owners List', colorClass: 'text-white w-auto', permission: 'owners-list' },
  { path: '/add-owner', label: 'Add Owner', colorClass: 'text-white w-auto', permission: 'owners-create' },
]

const propertyTypesItems = [
  { path: '/property_types', label: 'Property Types List', colorClass: 'text-white w-auto', permission: 'property_types-list' },
  { path: '/add-property_type', label: 'Add Property Type', colorClass: 'text-white w-auto', permission: 'property_types-create' },
]
const featuresItems = [
  { path: '/features', label: 'Features List', colorClass: 'text-white w-auto', permission: 'features-list' },
  { path: '/add-features', label: 'Add Feature', colorClass: 'text-white w-auto', permission: 'features-create' },
]
const projectsItems = [
  { path: '/projects', label: 'Projects List', colorClass: 'text-white w-auto', permission: 'projects-list' },
  { path: '/add-projects', label: 'Add Project', colorClass: 'text-white w-auto', permission: 'projects-create' },
]

const unitViewsItems = [
  { path: '/unit_views', label: 'Unit Views List', colorClass: 'text-white w-auto', permission: 'unit_views-list' },
  { path: '/add-unit_view', label: 'Add Unit View', colorClass: 'text-white w-auto', permission: 'unit_views-create' },
]

const LayoutTypesItems = [
  { path: '/layout_types', label: 'Layout Types List', colorClass: 'text-white w-auto', permission: 'layout_types-list' },
  { path: '/add-layout_type', label: 'Add Layout Type', colorClass: 'text-white w-auto', permission: 'layout_types-create' },
]

const AreasItems = [
  { path: '/areas', label: 'Areas List', colorClass: 'text-white w-auto', permission: 'areas-list' },
  { path: '/add-area', label: 'Add Area', colorClass: 'text-white w-auto', permission: 'areas-create' },
]

const UsersItems = [
  { path: '/users', label: 'Agents List', colorClass: 'text-white w-auto', permission: 'users-list' },
  { path: '/add-user', label: 'Add Agent', colorClass: 'text-white w-auto', permission: 'users-create' },
]

const filteredRolesItems = computed(() => {
  return roleItems.filter(item => {
    if (!item.permission) return true;
    return proxy.$hasPermission(item.permission); 
  });
});

const filteredDevelopersItems = computed(() => {
  return developersItems.filter(item => {
    if (!item.permission) return true;
    return proxy.$hasPermission(item.permission); 
  });
});

const filteredOwnersItems = computed(() => {
  return ownersItems.filter(item => {
    if (!item.permission) return true;
    return proxy.$hasPermission(item.permission); 
  });
});

const filteredPropertyTypesItems = computed(() => {
  return propertyTypesItems.filter(item => {
    if (!item.permission) return true;
    return proxy.$hasPermission(item.permission); 
  });
});
const filteredFeaturesItems = computed(() => {
  return featuresItems.filter(item => {
    if (!item.permission) return true;
    return proxy.$hasPermission(item.permission); 
  });
});
const filteredProjectsItems = computed(() => {
  return projectsItems.filter(item => {
    if (!item.permission) return true;
    return proxy.$hasPermission(item.permission); 
  });
});

const filteredUnitViewsItems = computed(() => {
  return unitViewsItems.filter(item => {
    if (!item.permission) return true;
    return proxy.$hasPermission(item.permission); 
  });
});

const filteredLayoutTypesItems = computed(() => {
  return LayoutTypesItems.filter(item => {
    if (!item.permission) return true;
    return proxy.$hasPermission(item.permission); 
  });
});

const filteredAreasItems = computed(() => {
  return AreasItems.filter(item => {
    if (!item.permission) return true;
    return proxy.$hasPermission(item.permission); 
  });
});

const filteredUsersItems = computed(() => {
  return UsersItems.filter(item => {
    if (!item.permission) return true;
    return proxy.$hasPermission(item.permission); 
  });
});

const isTableActive = computed(() => 
  filteredTableItems.value.some(item => isActive(item.path))
);

const isRequestsActive = computed(() => 
  filteredRequestsItems.value.some(item => isActive(item.path))
);

const isDevelopersActive = computed(() => 
  filteredDevelopersItems.value.some(item => isActive(item.path))
);

const isOwnersActive = computed(() => 
  filteredOwnersItems.value.some(item => isActive(item.path))
);

const isPropertyTypesActive = computed(() => 
  filteredPropertyTypesItems.value.some(item => isActive(item.path))
);
const isFeaturesActive = computed(() => 
  filteredFeaturesItems.value.some(item => isActive(item.path))
);
const isProjectsActive = computed(() => 
  filteredProjectsItems.value.some(item => isActive(item.path))
);

const isUnitViewsActive = computed(() => 
  filteredUnitViewsItems.value.some(item => isActive(item.path))
);

const isLayoutTypesActive = computed(() => 
  filteredLayoutTypesItems.value.some(item => isActive(item.path))
);

const isAreasActive = computed(() => 
  filteredAreasItems.value.some(item => isActive(item.path))
);

const isUsersActive = computed(() => 
  filteredUsersItems.value.some(item => isActive(item.path))
);

const isRolesActive = computed(() => 
  filteredRolesItems.value.some(item => isActive(item.path))
);

// Methods
const toggleDropdown = (name) => {
  activeDropdown.value = activeDropdown.value === name ? null : name;
  localStorage.setItem('activeDropdown', activeDropdown.value || '');
};

const closeSidebar = () => {
  isMobileOpen.value = false;
  document.body.classList.remove('overlay-active');
  const asideEl = document.querySelector('aside.sidebar');
  if (asideEl) {
    asideEl.classList.remove('sidebar-open');
  }
};

function toggleSidebarDesktop() {
  isSidebarActive.value = !isSidebarActive.value;
  document.querySelector('.sidebar')?.classList.toggle('active');
  document.querySelector('.dashboard-main')?.classList.toggle('active');
}

function toggleSidebarMobile() {
  isMobileOpen.value = true;
  document.querySelector('.sidebar')?.classList.add('sidebar-open');
  document.body.classList.add('overlay-active');
}

const isActive = (path) => {
  if (path === '/') {
    return route.path === '/';
  }
  return route.path === path || route.path.startsWith(path + '/');
};

// Animation functions (تبقى كما هي)
function beforeEnter(el) {
  el.style.height = '0px';
  el.style.opacity = '0';
  el.style.overflow = 'hidden';
}

function enter(el) {
  el.style.transition = 'height 0.7s ease';
  el.style.height = el.scrollHeight + 'px';
  el.style.opacity = '1';
}

function afterEnter(el) {
  el.style.height = 'auto';
  el.style.overflow = '';
  el.style.transition = '';
}

function beforeLeave(el) {
  el.style.height = el.scrollHeight + 'px';
  el.style.opacity = '1';
  el.style.overflow = 'hidden';
}

function leave(el) {
  el.style.transition = 'height 0.7s ease';
  requestAnimationFrame(() => {
    el.style.height = '0px';
    el.style.opacity = '0';
  });
}

function afterLeave(el) {
  el.style.height = '';
  el.style.opacity = '';
  el.style.transition = '';
  el.style.overflow = '';
}

// Watch for route changes
watch(() => route.path, (newPath) => {
  const menus = {
    table: filteredTableItems.value,
    requests: filteredRequestsItems.value,
    developers: filteredDevelopersItems.value,
    owners: filteredOwnersItems.value,
    property_types: filteredPropertyTypesItems.value,
    features: filteredFeaturesItems.value,
    projects: filteredProjectsItems.value,
    unit_views: filteredUnitViewsItems.value,
    layout_types: filteredLayoutTypesItems.value,
    areas: filteredAreasItems.value,
    users: filteredUsersItems.value,
    role: filteredRolesItems.value
  };

  for (const [menuName, items] of Object.entries(menus)) {
    if (items.some(item => isActive(item.path))) {
      activeDropdown.value = menuName;
      localStorage.setItem('activeDropdown', menuName);
      break;
    }
  }
});

onMounted(() => {
  const savedDropdown = localStorage.getItem('activeDropdown');
  if (savedDropdown) {
    activeDropdown.value = savedDropdown;
  }

  const menus = {
    table: filteredTableItems.value,
    requests: filteredRequestsItems.value,
    developers: filteredDevelopersItems.value,
    owners: filteredOwnersItems.value,
    property_types: filteredPropertyTypesItems.value,
    features: filteredFeaturesItems.value,
    unit_views: filteredUnitViewsItems.value,
    layout_types: filteredLayoutTypesItems.value,
    areas: filteredAreasItems.value,
    users: filteredUsersItems.value,
    role: filteredRolesItems.value
  };

  for (const [menuName, items] of Object.entries(menus)) {
    if (items.some(item => isActive(item.path))) {
      activeDropdown.value = menuName;
      break;
    }
  }

  fetchAllCounts();

  setInterval(fetchAllCounts, 60000);
});
</script>
<style scoped>
.sidebar-toggle {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 40px;
}

/* .sidebar-menu .dropdown.active-parent > a {
  background-color: rgba(59, 130, 246, 0.1);
  color: #3b82f6;
  border-right: 3px solid #3b82f6;
} */

/* .sidebar-menu .nav-link.active-page a {
  background-color: rgba(59, 130, 246, 0.1);
  color: #3b82f6;
  border-right: 3px solid #3b82f6;
} */
/* 
.sidebar-menu .dropdown.active-parent .menu-icon,
.sidebar-menu .nav-link.active-page .menu-icon {
  color: #3b82f6;
} */

.sidebar-menu li a.active {
   background: linear-gradient(135deg, 
        rgba(255, 255, 255, 0.12) 0%,   
        rgba(255, 255, 255, 0.5) 100%    
    );
    
    border: 1px solid rgba(255, 255, 255, 0.1); 
    
    box-shadow: 
        0 4px 20px rgba(0, 0, 0, 0.1),
        0 0 40px rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(40px); 
    
    border-radius: 16px;
    
    padding: 15px;
    

    
    background-clip: padding-box;
}



.menu-label {
  flex: 1;
}

.menu-count {
  background: #ffff;
  color: rgb(95, 95, 95);
  border-radius: 12px;
  padding: 2px 8px;
  font-size: 0.75rem;
  font-weight: 600;
  min-width: 24px;
  text-align: center;
  margin-left: auto;
}

.menu-count.loading {
  background: #9ca3af;
  animation: pulse 1.5s infinite;
}

.sidebar-submenu .menu-count {
  /* background: #6b7280; */
  font-size: 0.7rem;
  padding: 1px 6px;
}

.sidebar-menu li a,
.sidebar-submenu li a {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
}

.nav-link a {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}
.sidebar-logo
{
  justify-content: space-between;
  border:none;
}
.sidebar-menu li a .menu-icon{
    margin-inline-end: 0rem !important;
}
</style>