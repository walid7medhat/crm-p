<template>
  <aside
    v-if="!isMobileViewport"
    class="sidebar"
    :class="{
      active: isSidebarActive,
      'sidebar--dashboard-home': isDashboardHome,
    }"
    @mouseenter="sidebarHover = true"
    @mouseleave="sidebarHover = false"
  >
    <div class="sidebar-toggle-container sidebar-header d-flex align-items-center" :class="{ 'sidebar-header--open': !isSidebarActive, 'sidebar-header--closed': isSidebarActive }">
      <div
        class="sidebar-toggle-wrap"
        @mouseenter="sidebarHeaderHover = true"
        @mouseleave="sidebarHeaderHover = false"
      >
        <button
          type="button"
          class="sidebar-toggle"
          :class="{ 'sidebar-toggle-with-label': !isSidebarActive || (isSidebarActive && (sidebarHeaderHover || sidebarHover)) }"
          :title="isSidebarActive ? 'Expand menu' : 'Oia Properties'"
          @click="handleSidebarToggleClick"
          aria-label="Toggle menu"
        >
          <iconify-icon icon="material-symbols:menu-rounded" class="sidebar-menu-icon" />
          <!-- "Oia Properties" when open; "Expand menu" with icon when collapsed + hover anywhere on sidebar -->
          <span
            v-show="!isSidebarActive || (isSidebarActive && (sidebarHeaderHover || sidebarHover))"
            class="sidebar-toggle-label"
          >{{ !isSidebarActive ? 'Oia Properties' : 'Expand menu' }}</span>
        </button>
      </div>
    </div>
    <!-- Menu -->
    <div class="sidebar-menu-area">
      <ul class="sidebar-menu">
        <li>
          <router-link
            :to="isShowOnlyListing ? '/alllisting' : '/'"
            custom
            v-slot="{ navigate, href }"
          >
            <a
              :href="href"
              class="sidebar-nav-link sidebar-nav-link--dashboard"
              :class="{ active: isSidebarModuleActive('dashboard') }"
              @click="navigate"
            >
              <img :src="dashboardIcon" class="imgicon" alt="" />
              <span>Dashboard</span>
            </a>
          </router-link>
        </li>

        <li
          v-if="isAdmin"
          :class="{
            dropdown: true,
            open: activeDropdown === 'crm',
            'dropdown-open': activeDropdown === 'crm',
            'active-parent': isSidebarModuleActive('crm'),
          }"
        >
          <a href="javascript:void(0)" @click.stop.prevent="handleCrmClick" :class="{ active: isSidebarModuleActive('crm') }">
            <iconify-icon icon="lucide:handshake" class="menu-icon" />
            <span>CRM</span>
            <span class="dropdown-arrow" :class="{ rotated: activeDropdown === 'crm' }" />
          </a>
          <ul v-show="activeDropdown === 'crm'" class="sidebar-submenu sidebar-submenu--crm">
            <li :class="['nav-link', { 'active-page': isSidebarCrmSectionActive(CRM_SECTIONS.LEAD) }]">
              <a href="/kanban" class="sidebar-nav-link" @click.prevent="goToCrmSection(CRM_SECTIONS.LEAD)">Lead</a>
            </li>
            <li :class="['nav-link', { 'active-page': isSidebarCrmSectionActive(CRM_SECTIONS.DEAL) }]">
              <a href="/kanban_deal" class="sidebar-nav-link" @click.prevent="goToCrmSection(CRM_SECTIONS.DEAL)">Deal</a>
            </li>
            <li
              v-if="listingsSidebarSections.length > 0 && !isShowOnlyListing"
              :class="{
                dropdown: true,
                'sidebar-submenu__nested': true,
                open: crmListingsExpanded,
                'dropdown-open': crmListingsExpanded,
                'active-parent': isSidebarCrmSectionActive(CRM_SECTIONS.LISTINGS),
              }"
            >
              <a href="javascript:void(0)" @click.stop.prevent="handleCrmListingsClick" :class="{ active: isSidebarCrmSectionActive(CRM_SECTIONS.LISTINGS) }">
                <img :src="listingsIcon" class="imgicon submenu-icon" alt="" />
                <span>Listings</span>
                <span class="dropdown-arrow dropdown-arrow--nested" :class="{ rotated: crmListingsExpanded }" />
              </a>
              <ul v-if="crmListingsExpanded" class="sidebar-submenu sidebar-submenu--grouped sidebar-submenu--nested">
                <template v-for="section in listingsSidebarSections" :key="section.key">
                  <li class="sidebar-submenu__heading">{{ section.title }}</li>
                  <li
                    v-for="item in section.items"
                    :key="`${section.key}-${item.path}`"
                    :class="['nav-link', { 'active-page': isSidebarSubItemActive(item.path) }]"
                  >
                    <a href="#" class="sidebar-nav-link" @click.prevent="goToListingsItem(item.path)">
                      <span class="menu-label">{{ item.label }}</span>
                      <span v-if="item.count > 0" class="menu-count">{{ item.count }}</span>
                      <span v-else-if="countsLoading && item.count !== undefined" class="menu-count loading">…</span>
                    </a>
                  </li>
                </template>
              </ul>
            </li>
          </ul>
        </li>

        <li v-if="isSuperAdmin || user.id === 186">
          <router-link to="/hr" custom v-slot="{ navigate, href }">
            <a
              :href="href"
              class="sidebar-nav-link sidebar-nav-link--hr"
              :class="{ active: isSidebarModuleActive('hr') }"
              @click="navigate"
            >
              <iconify-icon icon="lucide:users-round" class="menu-icon" />
              <span>HR</span>
            </a>
          </router-link>
        </li>

        <li
          v-if="filteredUsersItems.length > 0"
          :class="{ dropdown: true, open: activeDropdown === 'users', 'active-parent': isSidebarModuleActive('agents') }"
        >
          <a href="javascript:void(0)" @click="toggleDropdown('users')" :class="{ active: isSidebarModuleActive('agents') }">
            <img :src="agentsIcon" class="imgicon" alt="" />
            <span>Agents</span>
            <span class="dropdown-arrow" :class="{ rotated: activeDropdown === 'users' }" />
          </a>
          <transition @before-enter="beforeEnter" @enter="enter" @after-enter="afterEnter" @before-leave="beforeLeave" @leave="leave" @after-leave="afterLeave">
            <ul v-show="activeDropdown === 'users'" class="sidebar-submenu">
              <li v-for="item in filteredUsersItems" :key="item.path" :class="['nav-link', { 'active-page': isSidebarSubItemActive(item.path) }]">
                <router-link :to="item.path" custom v-slot="{ navigate, href }">
                  <a :href="href" class="sidebar-nav-link" @click="navigate">{{ item.label }}</a>
                </router-link>
              </li>
            </ul>
          </transition>
        </li>
        <li
          v-if="settingsSidebarSections.length > 0"
          class="sidebar-menu__settings"
          :class="{ dropdown: true, open: !isDashboardHome && activeDropdown === 'settings', 'active-parent': isSidebarModuleActive('settings') }"
        >
          <a href="javascript:void(0)" @click="toggleDropdown('settings')" :class="{ active: isSidebarModuleActive('settings') }">
            <iconify-icon icon="lucide:settings" class="menu-icon" />
            <span>Settings</span>
            <span class="dropdown-arrow" :class="{ rotated: activeDropdown === 'settings' }" />
          </a>
          <transition @before-enter="beforeEnter" @enter="enter" @after-enter="afterEnter" @before-leave="beforeLeave" @leave="leave" @after-leave="afterLeave">
            <ul v-show="!isDashboardHome && activeDropdown === 'settings'" class="sidebar-submenu sidebar-submenu--grouped">
              <template v-for="section in settingsSidebarSections" :key="section.key">
                <li class="sidebar-submenu__heading">{{ section.title }}</li>
                <li v-for="item in section.items" :key="`${section.key}-${item.path}`" :class="['nav-link', { 'active-page': isSidebarSubItemActive(item.path) }]">
                <router-link :to="item.path" custom v-slot="{ navigate, href }">
                  <a :href="href" class="sidebar-nav-link" @click="navigate">
                    <iconify-icon v-if="item.icon" :icon="item.icon" class="menu-icon submenu-icon" />
                    <span>{{ item.label }}</span>
                  </a>
                </router-link>
              </li>
              </template>
            </ul>
          </transition>
        </li>
      </ul>
    </div>
  </aside>

  <nav v-if="isMobileViewport" class="mobile-sidebar-dock" aria-label="Mobile menu">
    <template v-for="item in mobileDockItems" :key="item.key || item.path">
      <button
        v-if="item.children"
        type="button"
        class="mobile-sidebar-dock__item mobile-sidebar-dock__btn"
        :class="{ 'is-active': isDockGroupActive(item) || activeMobileDockGroup?.key === item.key, 'is-chat': item.path === '/admin/chat' }"
        @click="openMobileDockGroup(item)"
      >
        <iconify-icon :icon="item.icon" class="mobile-sidebar-dock__icon" />
        <span class="mobile-sidebar-dock__label">{{ item.label }}</span>
      </button>
      <router-link
        v-else
        :to="item.path"
        class="mobile-sidebar-dock__item"
        :class="{ 'is-active': isDockActive(item.path), 'is-chat': item.path === '/admin/chat' }"
      >
        <iconify-icon :icon="item.icon" class="mobile-sidebar-dock__icon" />
        <span class="mobile-sidebar-dock__label">{{ item.label }}</span>
      </router-link>
    </template>
  </nav>

  <Teleport to="body">
    <div
      v-if="showMobileDockSheet && activeMobileDockGroup"
      class="mobile-dock-sheet-overlay"
      @click.self="closeMobileDockGroup"
    >
      <div class="mobile-dock-sheet">
        <div class="mobile-dock-sheet__head">
          <button type="button" class="mobile-dock-sheet__close" @click="closeMobileDockGroup" aria-label="Close">
            <iconify-icon icon="lucide:x" />
          </button>
        </div>
        <div class="mobile-dock-sheet__list" :class="{ 'mobile-dock-sheet__list--inline-two': !activeMobileDockGroup?.sections?.length && (activeMobileDockGroup?.children?.length || 0) === 2 }">
          <template v-if="activeMobileDockGroup?.sections?.length">
            <div
              v-for="section in activeMobileDockGroup.sections"
              :key="section.key"
              class="mobile-dock-accordion"
            >
              <button
                type="button"
                class="mobile-dock-accordion__trigger"
                :aria-expanded="mobileDockExpandedSection === section.key"
                @click="toggleMobileDockSection(section.key)"
              >
                <span>{{ section.title }}</span>
                <iconify-icon
                  icon="lucide:chevron-down"
                  class="mobile-dock-accordion__chevron"
                  :class="{ 'is-open': mobileDockExpandedSection === section.key }"
                />
              </button>
              <div
                v-show="mobileDockExpandedSection === section.key"
                class="mobile-dock-accordion__panel"
              >
                <router-link
                  v-for="child in section.items"
                  :key="child.path"
                  :to="child.path"
                  class="mobile-dock-sheet__item"
                  :class="{ 'is-active': isDockActive(child.path) }"
                  @click="closeMobileDockGroup"
                >
                  <span>{{ child.label }}</span>
                  <span v-if="child.count > 0" class="mobile-dock-sheet__count">{{ child.count }}</span>
                </router-link>
              </div>
            </div>
          </template>
          <template v-else>
            <router-link
              v-for="child in activeMobileDockGroup.children"
              :key="child.path"
              :to="child.path"
              class="mobile-dock-sheet__item"
              :class="{ 'is-active': isDockActive(child.path) }"
              @click="closeMobileDockGroup"
            >
              <span>{{ child.label }}</span>
              <span v-if="child.count > 0" class="mobile-dock-sheet__count">{{ child.count }}</span>
            </router-link>
          </template>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, getCurrentInstance, watch, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/plugins/axios';
import { useSidebar } from '@/composables/useSidebar.js';
import { useMobileNavigation } from '@/composables/useMobileNavigation.js';
import {
  buildListingsSidebarSections,
  buildSettingsSidebarSections,
  CRM_SECTIONS,
  getListingsEntryPath,
  resolveCrmSection,
} from '@/composables/useLayoutNavigation.js';
import { useLayoutActiveState } from '@/composables/useLayoutActiveState.js';

const logo = ref('/assets/images/LogoWhite.png');
const dashboardIcon=ref('/assets/icons/dashboard-icon.svg');
const listingsIcon=ref('/assets/icons/listings-icon.svg');
const requestsIcon=ref('/assets/icons/request-icon.svg');
const isMobileOpen = ref(false);
const ownersIcon=ref('/assets/icons/owners-icon.svg');
const propertyIcon=ref('/assets/icons/property-icon-white.svg');
const unitViewIcon=ref('/assets/icons/unit-view-icon.svg');
const layoutTypeIcon=ref('/assets/icons/layout-icon.svg');
const locationIcon=ref('/assets/icons/area-icon.svg');
const agentsIcon=ref('/assets/icons/agents-icon.svg');
const roleIcon=ref('/assets/icons/role-icon.svg');

const route = useRoute();
const router = useRouter();
const activeDropdown = ref(null);
const countsLoading = ref(false);
const { proxy } = getCurrentInstance();
const { isSidebarActive, toggleSidebarDesktop, expandSidebarDesktop } = useSidebar();
const {
  isMobileViewport,
  isMobileMenuOpen,
  closeMobileMenu,
  toggleMobileMenu,
  syncMobileViewport,
} = useMobileNavigation();

const sidebarHeaderHover = ref(false);
const sidebarHover = ref(false);

const handleSidebarToggleClick = () => {
  toggleSidebarDesktop();
};

const closeSidebar = () => {
  if (isMobileViewport.value) {
    closeMobileMenu();
    return;
  }
  isMobileOpen.value = false;
  document.body.classList.remove('overlay-active');
  document.querySelector('aside.sidebar')?.classList.remove('sidebar-open');
};

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

const isShowOnlyListing = computed(() => {
  if (!user.value) return false;
  
  const isAdminUser = user.value.roles?.includes('only show listings');
  
  return isAdminUser;
});

const isCustomAdmin = computed(() => {
  if (!user.value) return false;
   const userId = Number(user.value.id);
  const isAdminUser = user.value.roles?.includes('super_admin') || 
                     (user.value.roles?.includes('admin') && ( userId==30 || userId==33));
  
  return isAdminUser;
});

const isSuperAdmin = computed(() => {
  return user.value?.roles?.includes('super_admin') ?? false;
});

const tableItems = computed(() => {
  const items = [
    { path: '/alllisting', label: 'All Listing', colorClass: 'text-warning-main w-auto', count: 0,permission: 'listings-list' },
    { path: '/property-form', label: 'Create Listing', colorClass: 'text-info-main w-auto', permission: 'listings-create', count: 0 },
    { path: '/notify-me', label: 'Notify me', colorClass: 'text-info-main w-auto', count: 0 ,permission: 'listings-list'},
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
      { path: '/all-requests', label: 'All Requests', colorClass: 'text-white w-auto', count: 0 },
            { path: '/my-viewings', label: 'Viewings', colorClass: 'text-white w-auto', count: 0, permission: 'listings-list' },

    ]
  } else {
    const items = [
      { path: '/my-requests', label: 'Inbound Request', colorClass: 'text-white w-auto', count: 0 ,permission: 'listings-list'},
      { path: '/my-orders', label: 'Outbound Request', colorClass: 'text-white w-auto', count: 0,permission: 'listings-list' },
      { path: '/my-viewings', label: 'Viewings', colorClass: 'text-white w-auto', count: 0, permission: 'listings-list' },
    ];
    
    // Only show hot deal requests for listing team members
    if (user.value?.is_listing_team &&  (user.value.roles?.includes('super_admin') ||  user.value.roles?.includes('admin') ||  user.value.roles?.includes('team_lead') ||  user.value.roles?.includes('manager'))) {
      items.push({ path: '/hotDeal-requests', label: 'Hot Deal Requests', colorClass: 'text-white w-auto', count: 0 });
    }
    
    if (user.value?.is_listing_team &&  (user.value.roles?.includes('super_admin') ||  user.value.roles?.includes('manager'))) {
      items.push({ path: '/need-approve-requests', label: 'Need Approval Listings', colorClass: 'text-white w-auto', count: 0 });
    }
    
    return items;
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
           if (item.path === '/hotDeal-requests') item.count = counts.hot_deals.all || 0;
           if (item.path === '/need-approve-requests') item.count = counts.needapprove.all || 0;
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
//   { path: '/add-projects', label: 'Add Project', colorClass: 'text-white w-auto', permission: 'projects-create' },
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

const {
  isDashboardHome,
  isSidebarModuleActive,
  isSidebarCrmSectionActive,
  isSidebarSubItemActive,
  isMobileDockItemActive,
  rememberListingsPath,
  rememberCrmSection,
} = useLayoutActiveState();

const crmListingsExpanded = ref(false);

const listingsOverviewPath = computed(() =>
  isShowOnlyListing.value ? '/alllisting' : '/alllisting',
);

const listingsSidebarSections = computed(() =>
  buildListingsSidebarSections({
    listings: filteredTableItems.value,
    projects: filteredProjectsItems.value,
    requests: filteredRequestsItems.value,
    developers: filteredDevelopersItems.value,
    owners: filteredOwnersItems.value,
    property_types: filteredPropertyTypesItems.value,
    features: filteredFeaturesItems.value,
    unit_views: filteredUnitViewsItems.value,
    layout_types: filteredLayoutTypesItems.value,
    areas: filteredAreasItems.value,
  }),
);

const mainMenuItems = computed(() => {
  const items = [];

  if (isSuperAdmin.value) {
    items.push({ path: '/lead-reports', label: 'Lead Reports', icon: 'lucide:bar-chart-2' });
    items.push({ path: '/sales-intelligence', label: 'Sales Intelligence', icon: 'lucide:sparkles' });
    items.push({ path: '/investment-analysis', label: 'Investment Analysis', icon: 'lucide:line-chart' });
    items.push({ path: '/settings/city-investments', label: 'City Investments', icon: 'lucide:landmark' });
  }

  if (isAdmin.value) {
    items.push({ path: '/settings/lead-scoring', label: 'Lead Scoring', icon: 'lucide:target' });
  }

  return items;
});

const filteredMainMenuItems = computed(() => mainMenuItems.value.filter((item) => !!item.path));

const settingsSidebarSections = computed(() => {
  const system = [];
  if (isAdmin.value) {
    system.push({ path: '/system-overview', label: 'System Map', icon: 'lucide:layout-dashboard' });
    system.push({ path: '/import-pitrix', label: 'Import Leads', icon: 'lucide:cloud-download' });
    system.push({ path: '/sync-bitrix-leads', label: 'Sync Leads', icon: 'lucide:refresh-cw' });
    system.push({ path: '/sync-responsible', label: 'Sync Responsible', icon: 'lucide:user-check' });
  }
  if (isSuperAdmin.value) {
    system.push({ path: '/logs', label: 'Logs', icon: 'lucide:scroll-text' });
    system.push({ path: '/attendance-monthly-reports', label: 'Reports', icon: 'lucide:bar-chart-3' });
    system.push({ path: '/settings/background', label: 'Background', icon: 'lucide:image' });
      
  }

  const chat = isCustomAdmin.value
    ? [{ path: '/admin/chat', label: 'All Chats', icon: 'ri-chat-3-line' }]
    : [];

  const other = !isShowOnlyListing.value
    ? [
        { path: '/suggestion', label: 'Suggestions', icon: 'lucide:lightbulb' },
       
      ]
    : [];

  const tools = isAdmin.value
    ? [{ path: '/settings/roi-calculator', label: 'ROI Calculator', icon: 'lucide:calculator' }]
    : [];

  return buildSettingsSidebarSections({
    system,
    roles: filteredRolesItems.value,
    tools,
    insights: filteredMainMenuItems.value,
    chat,
    other,
  });
});

const allListingsMenuPaths = computed(() =>
  listingsSidebarSections.value.flatMap((s) => s.items.map((i) => i.path)),
);

const allSettingsMenuPaths = computed(() =>
  settingsSidebarSections.value.flatMap((s) => s.items.map((i) => i.path)),
);

function syncViewport() {
  syncMobileViewport();
  if (!isMobileViewport.value) {
    closeMobileDockGroup();
  }
}

const showMobileDockSheet = ref(false);
const activeMobileDockGroup = ref(null);
const mobileDockExpandedSection = ref(null);

const mobileDockItems = computed(() => {
  const listingChildren = listingsSidebarSections.value
    .flatMap((s) => s.items)
    .slice(0, 8)
    .map((it) => ({ path: it.path, label: it.label, count: it.count || 0 }));

  const settingsChildren = settingsSidebarSections.value
    .flatMap((s) => s.items)
    .slice(0, 6)
    .map((it) => ({ path: it.path, label: it.label }));

  const items = [
    { path: isShowOnlyListing.value ? '/alllisting' : '/', label: 'Home', icon: 'solar:home-smile-angle-outline' },
  ];

  if (isAdmin.value) {
    const crmSections = [
      {
        key: 'crm-core',
        title: 'CRM',
        items: [
          { path: '/kanban', label: 'Lead' },
          { path: '/kanban_deal', label: 'Deal' },
        ],
      },
    ];
    if (listingsSidebarSections.value.length && !isShowOnlyListing.value) {
      crmSections.push(
        ...listingsSidebarSections.value.map((section) => ({
          key: `crm-${section.key}`,
          title: section.title,
          items: section.items.map((it) => ({
            path: it.path,
            label: it.label,
            count: it.count || 0,
          })),
        })),
      );
    }
    items.push({
      key: 'group-crm',
      label: 'CRM',
      icon: 'lucide:handshake',
      sections: crmSections,
    });
  }
  if (isSuperAdmin.value || user.value?.id === 186) {
    items.push({ path: '/hr', label: 'HR', icon: 'lucide:users-round' });
  }
  if (filteredUsersItems.value.length) {
    items.push({
      key: 'group-agents',
      label: 'Agents',
      icon: 'lucide:user-round',
      children: filteredUsersItems.value.map((it) => ({ path: it.path, label: it.label })),
    });
  }
  if (settingsSidebarSections.value.length) {
    items.push({
      key: 'group-settings',
      label: 'Settings',
      icon: 'lucide:settings',
      children: settingsChildren,
      sections: settingsSidebarSections.value.map((section) => ({
        key: section.key,
        title: section.title,
        items: section.items.map((it) => ({
          path: it.path,
          label: it.label,
        })),
      })),
    });
  }

  return items;
});

function isDockActive(path) {
  return isMobileDockItemActive(path);
}

function isDockGroupActive(group) {
  if (isDashboardHome.value || !group?.children?.length) return false;
  return group.children.some((child) => isDockActive(child.path));
}

async function openMobileDockGroup(group) {
  if (group?.key === 'group-crm') {
    openCrmDropdown();
  }
  activeMobileDockGroup.value = group;
  mobileDockExpandedSection.value = group?.sections?.[0]?.key ?? null;
  showMobileDockSheet.value = true;
}

function closeMobileDockGroup() {
  showMobileDockSheet.value = false;
  activeMobileDockGroup.value = null;
  mobileDockExpandedSection.value = null;
}

function toggleMobileDockSection(sectionKey) {
  mobileDockExpandedSection.value =
    mobileDockExpandedSection.value === sectionKey ? null : sectionKey;
}


// Methods
const toggleDropdown = (name) => {
  activeDropdown.value = activeDropdown.value === name ? null : name;
  localStorage.setItem('activeDropdown', activeDropdown.value || '');
};

const openCrmDropdown = () => {
  activeDropdown.value = 'crm';
  localStorage.setItem('activeDropdown', 'crm');
};

const closeCrmDropdown = () => {
  activeDropdown.value = null;
  localStorage.removeItem('activeDropdown');
  crmListingsExpanded.value = false;
};

async function goToCrmSection(section) {
  rememberCrmSection(section);
  openCrmDropdown();
  crmListingsExpanded.value = false;

  if (section === CRM_SECTIONS.LEAD) {
    localStorage.setItem('kanban_active_tab', 'leads');
    if (route.path !== '/kanban') {
      await router.push('/kanban');
    }
    window.dispatchEvent(new CustomEvent('kanban-tab-change', { detail: 'leads' }));
    return;
  }

  if (section === CRM_SECTIONS.DEAL) {
    localStorage.setItem('kanban_active_tab', 'deals');
    if (route.path !== '/kanban_deal') {
      await router.push('/kanban_deal');
    }
    window.dispatchEvent(new CustomEvent('kanban-tab-change', { detail: 'deals' }));
    const dealType = localStorage.getItem('kanban_deal_type') || 'primary';
    window.dispatchEvent(new CustomEvent('kanban-deal-type-change', { detail: dealType }));
  }
}

async function goToListingsItem(path) {
  rememberCrmSection(CRM_SECTIONS.LISTINGS);
  rememberListingsPath(path);
  openCrmDropdown();
  if (route.path !== path) {
    await router.push(path);
  }
}

const handleCrmClick = () => {
  expandSidebarDesktop();
  if (activeDropdown.value === 'crm') {
    closeCrmDropdown();
    return;
  }
  openCrmDropdown();
  crmListingsExpanded.value = false;
};

const handleCrmListingsClick = () => {
  crmListingsExpanded.value = !crmListingsExpanded.value;
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

function syncSidebarDropdownFromRoute() {
  if (isDashboardHome.value) {
    if (activeDropdown.value !== 'crm') {
      activeDropdown.value = null;
      localStorage.removeItem('activeDropdown');
    }
    return;
  }

  const crmSection = resolveCrmSection(route.path);
  if (crmSection) {
    openCrmDropdown();
    crmListingsExpanded.value = false;
    if (crmSection === CRM_SECTIONS.LISTINGS) {
      rememberListingsPath(route.path);
    }
    rememberCrmSection(crmSection);
    return;
  }
  if (allSettingsMenuPaths.value.some((p) => isSidebarSubItemActive(p))) {
    activeDropdown.value = 'settings';
    localStorage.setItem('activeDropdown', 'settings');
    return;
  }
  if (filteredUsersItems.value.some((item) => isSidebarSubItemActive(item.path))) {
    activeDropdown.value = 'users';
    localStorage.setItem('activeDropdown', 'users');
    return;
  }

  activeDropdown.value = null;
  localStorage.removeItem('activeDropdown');
}

watch(() => route.path, () => {
  closeMobileDockGroup();
  closeMobileMenu();
  syncSidebarDropdownFromRoute();
});

watch(isDashboardHome, (onHome) => {
  if (!onHome) return;
  // Keep CRM/Agents dropdown open if user explicitly opened it from dashboard
  if (activeDropdown.value === 'crm' || activeDropdown.value === 'users') return;
  activeDropdown.value = null;
  localStorage.removeItem('activeDropdown');
});

onMounted(() => {
  syncViewport();
  window.addEventListener('resize', syncViewport);
  if (localStorage.getItem('activeDropdown') === 'listings') {
    localStorage.setItem('activeDropdown', 'crm');
  }
  syncSidebarDropdownFromRoute();
  fetchAllCounts();
  setInterval(fetchAllCounts, 60000);
});

onUnmounted(() => {
  window.removeEventListener('resize', syncViewport);
});

</script>
<style scoped>
/* 1. Default / open sidebar: same as header bar (light transparent glass) */
.sidebar {
  display: flex;
  flex-direction: column;
  background: var(--gradient-crm-glass) !important;
  backdrop-filter: blur(24px);
  -webkit-backdrop-filter: blur(24px);
  border-color: rgba(255, 255, 255, 0.12) !important;
  z-index: 99 !important;
  position: fixed;
}

.sidebar-menu-area {
  position: relative;
  z-index: 1201;
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}

.sidebar-menu {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}

.sidebar-submenu {
  position: relative;
  z-index: 1202;
}

/* Direct CRM submenu only — do not force nested Listings submenu open */
.sidebar:not(.active) .sidebar-menu > li.dropdown.open > .sidebar-submenu--crm,
.sidebar:not(.active) .sidebar-menu > li.dropdown.dropdown-open > .sidebar-submenu--crm {
  display: block !important;
  visibility: visible !important;
}

.sidebar-submenu__nested .sidebar-submenu--nested {
  display: none !important;
}

.sidebar-submenu__nested.open .sidebar-submenu--nested,
.sidebar-submenu__nested.dropdown-open .sidebar-submenu--nested {
  display: block !important;
  visibility: visible !important;
}

.sidebar-header {
  padding: 0.65rem 0.75rem;
  min-height: 4rem;
  box-sizing: border-box;
  justify-content: flex-start;
  background: transparent;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
.sidebar-menu li a {
    padding: 0.45rem 0.5rem !important;
}
.sidebar-menu li a span,
.sidebar-submenu li a span {
  font-size: 0.8125rem;
  line-height: 1.3;
}
/* 2. Darker only on hover when collapsed (.sidebar.active = collapsed) */
.sidebar.active:hover {
  width: auto;
   background: var(--gradient-crm-glass) !important;
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  /*border-color: rgba(255, 255, 255, 0.12);*/
  z-index: 1300 !important;
}
@media (max-width: 991px) {
  .sidebar.sidebar-open {
    background: var(--gradient-crm-glass) !important;
    backdrop-filter: blur(16px) !important;
    -webkit-backdrop-filter: blur(16px) !important;
            z-index: 100 !important;

  }
}

@media (max-width: 768px) {
  .mobile-sidebar-dock {
    position: fixed;
    z-index: 1200;
    display: flex;
    align-items: center;
    overflow-x: auto;
    overflow-y: visible;
    -webkit-overflow-scrolling: touch;
  }

  .mobile-sidebar-dock::-webkit-scrollbar {
    display: none;
  }

  .mobile-sidebar-dock__item {
    flex: 0 0 auto;
    min-width: 52px;
    height: 42px;
    border-radius: 12px;
    color: rgba(255, 255, 255, 0.9);
    text-decoration: none;
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
    padding: 4px 8px;
  }

  .mobile-sidebar-dock__item.is-active {
    background: #ffffff;
    color: #0b0736;
    box-shadow: 0 2px 10px rgba(11, 7, 54, 0.22);
  }

  .mobile-sidebar-dock__item.is-active .mobile-sidebar-dock__icon,
  .mobile-sidebar-dock__item.is-active .mobile-sidebar-dock__label {
    color: #0b0736;
  }

  .mobile-sidebar-dock__item.is-active :deep(iconify-icon),
  .mobile-sidebar-dock__item.is-active :deep(svg) {
    color: #0b0736 !important;
  }

  .mobile-sidebar-dock__icon {
    font-size: 14px;
    line-height: 1;
    color: inherit;
  }

  .mobile-sidebar-dock__label {
    font-size: 8px;
    font-weight: 700;
    line-height: 1.1;
    white-space: nowrap;
    color: inherit;
  }

  .mobile-sidebar-dock__item.is-chat .mobile-sidebar-dock__icon {
    font-size: 10px;
  }

  .mobile-sidebar-dock__btn {
    border: none;
    background: transparent;
  }

  .mobile-sidebar-dock__group {
    position: relative;
    flex: 0 0 auto;
  }

  .mobile-dock-inline-submenu {
    position: absolute;
    bottom: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%);
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 0;
    background: transparent;
    border: none;
    box-shadow: none;
    z-index: 1205;
    white-space: nowrap;
  }

  .mobile-dock-inline-submenu__item {
    text-decoration: none;
    color: #ffffff;
    font-size: 9px;
    font-weight: 600;
    line-height: 1;
    padding: 0;
    background: transparent;
    border: none;
  }

  .mobile-dock-inline-submenu__item.is-active {
    color: #f5b749;
    text-decoration: underline;
    text-underline-offset: 2px;
  }

  .mobile-dock-sheet-overlay {
    position: fixed;
    inset: 0;
    z-index: 2200;
    background: rgba(15, 23, 42, 0.42);
    display: flex;
    align-items: flex-end;
    justify-content: center;
  }

  .mobile-dock-sheet {
    width: 100%;
    background: #fff;
    border-radius: 20px 20px 0 0;
    padding: 8px 12px calc(10px + env(safe-area-inset-bottom, 0px));
    box-shadow: 0 -8px 30px rgba(15, 23, 42, 0.18);
    max-height: min(44vh, 360px);
    overflow: auto;
  }


  .mobile-dock-sheet__head {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    margin-bottom: 4px;
  }

  .mobile-dock-sheet__close {
    width: 28px;
    height: 28px;
    border: none;
    border-radius: 50%;
    background: #f1f5f9;
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    z-index: 2202;
  }

  .mobile-dock-sheet__list {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .mobile-dock-sheet__list--inline-two {
    flex-direction: row;
    gap: 8px;
  }


  .mobile-dock-sheet__item {
    text-decoration: none;
    color: #0f172a;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 7px 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 11px;
    font-weight: 600;
  }

  .mobile-dock-sheet__list--inline-two .mobile-dock-sheet__item {
    flex: 1 1 0;
    justify-content: center;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }


  .mobile-dock-sheet__item.is-active {
    border-color: #f59e0b;
    box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.22);
  }

  .mobile-dock-sheet__count {
    min-width: 22px;
    text-align: center;
    font-size: 9px;
    font-weight: 700;
    background: #fff;
    border: 1px solid #dbe1ea;
    border-radius: 999px;
    padding: 2px 6px;
    color: #334155;
  }
}
@media (min-width: 1200px) {
  .sidebar.active:hover {
    inset-inline-start: 0;
    width: 11.75rem;
  }
}
@media (min-width: 1400px) {
  .sidebar.active:hover {
    width: 13rem;
  }
}
@media (min-width: 1650px) {
  .sidebar.active:hover {
    width: 14rem;
  }
}

/* Menu icon: white on dark header */
.sidebar-toggle-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.sidebar-toggle {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 36px;
  width: 36px;
  padding: 0;
  background: transparent;
  border: none;
  cursor: pointer;
  flex-shrink: 0;
}
.sidebar-toggle-with-label {
  width: auto;
  gap: 0.375rem;
  justify-content: flex-start;
}
/* Same place, same style as menu items but smaller and not bold */
.sidebar-toggle-label {
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 400;
  color: #ffffff;
  white-space: nowrap;
}
.sidebar-menu-icon {
  font-size: 1.5rem;
  color: #ffffff !important;
  width: 1.5rem;
  height: 1.5rem;
}
.sidebar-toggle:hover .sidebar-menu-icon {
  color: rgba(255, 255, 255, 0.9) !important;
}
[data-theme="dark"] .sidebar-menu-icon {
  color: #ffffff !important;
}
[data-theme="dark"] .sidebar-toggle:hover .sidebar-menu-icon {
  color: rgba(255, 255, 255, 0.9) !important;
}
/* Ensure Iconify icon inherits color (SVG fill) */
.sidebar-menu-icon :deep(svg),
.sidebar-menu-icon :deep(path) {
  fill: currentColor;
}

.sidebar-menu .dropdown.active-parent > a {
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.18) 0%, rgba(255, 255, 255, 0.08) 100%);
  border: 1px solid rgba(255, 255, 255, 0.15);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  border-radius: 10px;
}

.sidebar-menu .nav-link.active-page a {
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.14) 0%, rgba(255, 255, 255, 0.06) 100%);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 8px;
}

.sidebar-menu .dropdown.active-parent .menu-icon,
.sidebar-menu .nav-link.active-page .menu-icon {
  color: #fff;
}

.sidebar-menu li a.active {
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.18) 0%, rgba(255, 255, 255, 0.08) 100%);
  border: 1px solid rgba(255, 255, 255, 0.15);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  border-radius: 10px;
  padding: 8px 10px;
}



.menu-label {
  flex: 1;
}

.menu-count {
  background: #ffff;
  color: rgb(95, 95, 95);
  border-radius: 10px;
  padding: 1px 6px;
  font-size: 0.6875rem;
  font-weight: 600;
  min-width: 20px;
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

/* Menu links: visible on both transparent (open) and dark (collapsed hover) */
.sidebar-menu li a,
.sidebar-submenu li a {
  display: flex;
  align-items: center;
  gap: 5px;
  width: 100%;
  padding: 8px 10px;
  margin-bottom: 2px;
  border-radius: 10px;
  color: rgba(255, 255, 255, 0.95);
  transition: background 0.15s ease, color 0.15s ease;
}
.sidebar-menu li a:hover,
.sidebar-submenu li a:hover {
  color: #fff;
}
.sidebar-menu li a.active,
.sidebar-menu li a.sidebar-nav-link.active {
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.18) 0%, rgba(255, 255, 255, 0.08) 100%);
  border: 1px solid rgba(255, 255, 255, 0.15);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  border-radius: 10px;
  padding: 8px 10px;
  color: #fff;
}

/* Main dashboard: only Dashboard may appear active in the sidebar */
.sidebar--dashboard-home .sidebar-menu > li > a.sidebar-nav-link.active:not(.sidebar-nav-link--dashboard),
.sidebar--dashboard-home .sidebar-menu .dropdown.active-parent > a,
.sidebar--dashboard-home .sidebar-menu .nav-link.active-page a {
  background: transparent !important;
  border-color: transparent !important;
  box-shadow: none !important;
  color: rgba(255, 255, 255, 0.95) !important;
}

/* Keep CRM submenu visible when user opens it from dashboard */
.sidebar--dashboard-home .sidebar-menu li.dropdown.open > .sidebar-submenu--crm,
.sidebar--dashboard-home .sidebar-menu li.dropdown.dropdown-open > .sidebar-submenu--crm {
  display: block !important;
  visibility: visible !important;
}

.sidebar-submenu--crm {
  padding-top: 4px;
  display: block;
  overflow: visible;
}

.sidebar-menu li.dropdown > a {
  cursor: pointer;
  user-select: none;
}

.sidebar-submenu__nested > a {
  padding-left: 12px;
}

.sidebar-submenu--nested {
  margin-left: 8px;
  padding-left: 4px;
  border-left: 1px solid rgba(255, 255, 255, 0.12);
}

.dropdown-arrow--nested {
  margin-left: auto;
}


/* Icons and dropdown arrow visible on dark sidebar */
.sidebar-menu .nav-link.active-page a,
.sidebar-submenu .nav-link.active-page a {
  background: rgba(255, 255, 255, 0.1);
  filter: brightness(1.05);
  border-radius: 10px;
  color: #fff;
}
.sidebar .menu-icon {
  color: rgba(255, 255, 255, 0.9) !important;
  font-size: 1.125rem !important;
  width: 1.125rem;
  height: 1.125rem;
  flex-shrink: 0;
}
.sidebar .imgicon {
  opacity: 0.95;
  width: 1.25rem;
  height: auto;
  flex-shrink: 0;
}
.sidebar .dropdown-arrow {
  border-left-color: rgba(255, 255, 255, 0.9);
}

.nav-link a {
  display: flex;
  align-items: center;
  gap: 5px;
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

.sidebar-menu__settings {
  margin-top: auto;
}

.sidebar-submenu--grouped {
  padding-top: 2px;
}

.sidebar-submenu__heading {
  list-style: none;
  padding: 8px 12px 4px;
  margin: 0;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.45);
  pointer-events: none;
}

.sidebar-submenu__heading:first-child {
  padding-top: 4px;
}

.submenu-icon {
  font-size: 1rem;
  margin-inline-end: 6px;
  flex-shrink: 0;
}

.sidebar-item-all-chats a,
.sidebar-item-all-chats a span {
  font-size: 0.8rem !important;
  color: #ffffff !important;
}
.sidebar-item-all-chats .menu-icon {
  font-size: 1rem !important;
}

.mobile-nav-overlay {
  position: fixed;
  inset: 0;
  z-index: 2350;
  background: rgba(11, 7, 54, 0.48);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
}

@media (max-width: 768px) {
  .mobile-sidebar-dock {
    height: 58px;
    padding: 6px 10px;
    background: var(--gradient-crm-glass, linear-gradient(135deg, rgba(11, 7, 54, 0.92) 0%, rgba(115, 62, 135, 0.82) 100%));
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.14);
  }

  .mobile-sidebar-dock__item,
  .mobile-sidebar-dock__btn {
    min-width: 56px;
    min-height: 44px;
    padding: 6px 10px;
  }

  .mobile-dock-sheet {
    max-height: min(72vh, 520px);
  }

  .mobile-dock-sheet__close {
    min-width: 44px;
    min-height: 44px;
  }

  .mobile-dock-accordion {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
  }

  .mobile-dock-accordion__trigger {
    width: 100%;
    min-height: 44px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 10px 12px;
    border: none;
    background: #f8fafc;
    color: #0b0736;
    font-size: 12px;
    font-weight: 700;
    text-align: left;
    cursor: pointer;
  }

  .mobile-dock-accordion__chevron {
    transition: transform 0.2s ease;
    flex-shrink: 0;
  }

  .mobile-dock-accordion__chevron.is-open {
    transform: rotate(180deg);
  }

  .mobile-dock-accordion__panel {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 6px 8px 8px;
    max-height: 40vh;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
  }

  .mobile-dock-sheet__item {
    min-height: 44px;
    padding: 10px 12px;
    font-size: 12px;
  }
}

</style>