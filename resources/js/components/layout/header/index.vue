<template>
  <Teleport to="body" :disabled="!isMobileViewport">
  <aside
    class="sidebar"
    :class="{
      active: isSidebarActive && !isMobileViewport,
      'sidebar--dashboard-home': isDashboardHome,
      'sidebar-open': isMobileMenuOpen,
      'sidebar--mobile-drawer': isMobileViewport || isMobileMenuOpen,
      'mobile-drawer-flyout-open': isMobileViewport && crmListingsExpanded && showCrmListingsDropdown,
    }"
    @mouseenter="!isMobileViewport && (sidebarHover = true)"
    @mouseleave="!isMobileViewport && (sidebarHover = false)"
  >
    <header v-if="isMobileViewport" class="mobile-drawer-header">
      <div class="mobile-drawer-header__brand">
        <span class="mobile-drawer-header__logo">Oia</span>
        <span class="mobile-drawer-header__title">Properties</span>
      </div>
      <button
        type="button"
        class="mobile-drawer-header__close"
        aria-label="Close menu"
        @click="handleMobileDrawerClose"
      >
        <iconify-icon icon="lucide:x" />
      </button>
    </header>
    <div
      v-if="!isMobileViewport"
      class="sidebar-brand"
      :class="{ 'sidebar-brand--collapsed': isSidebarActive }"
    >
      <div class="sidebar-brand__mark">
        <img
          :src="oiaBrandLogo"
          alt="Oia Properties"
          class="sidebar-brand__logo"
        />
      </div>
      <button
        type="button"
        class="sidebar-edge-toggle"
        :aria-label="isSidebarActive ? 'Open menu' : 'Close menu'"
        @pointerdown.stop
        @click.stop="handleSidebarToggleClick"
      >
        <iconify-icon :icon="isSidebarActive ? 'lucide:chevron-right' : 'lucide:chevron-left'" />
      </button>
    </div>
    <!-- Menu -->
    <div class="sidebar-menu-area" @click="onMobileSidebarNavClick">
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
              @mouseenter="prefetchRoute(isShowOnlyListing ? '/alllisting' : '/')"
              @click="navigate"
            >
              <img :src="dashboardIcon" class="imgicon" alt="" />
              <span>Dashboard</span>
            </a>
          </router-link>
        </li>

        <li
          
          :class="{
            dropdown: true,
            open: activeDropdown === 'crm',
            'dropdown-open': activeDropdown === 'crm',
            'active-parent': isSidebarModuleActive('crm'),
          }"
        >
          <a  v-if="!isHr" href="javascript:void(0)" @click.stop.prevent="handleCrmClick" :class="{ active: isSidebarModuleActive('crm') }">
            <img :src="crmIcon" class="imgicon" alt="" />
            <span>CRM</span>
            <span class="dropdown-arrow" :class="{ rotated: activeDropdown === 'crm' }" />
          </a>
          <ul v-if="!isHr" v-show="activeDropdown === 'crm'" class="sidebar-submenu sidebar-submenu--crm">
            <li v-if="canShowLeadsTab" :class="['nav-link', { 'active-page': isSidebarCrmSectionActive(CRM_SECTIONS.LEAD) }]">
              <a href="/kanban" class="sidebar-nav-link" @mouseenter="prefetchRoute('/kanban')" @click.prevent="goToCrmSection(CRM_SECTIONS.LEAD)">
                <img :src="leadsIcon" class="imgicon submenu-icon" alt="" />
                <span>Leads</span>
              </a>
            </li>
            <li v-if="canShowLeadsTab" :class="['nav-link', { 'active-page': isSidebarCrmSectionActive(CRM_SECTIONS.DEAL) }]">
              <a href="/kanban_deal" class="sidebar-nav-link" @mouseenter="prefetchRoute('/kanban_deal')" @click.prevent="goToCrmSection(CRM_SECTIONS.DEAL)">
                <img :src="dealsIcon" class="imgicon submenu-icon" alt="" />
                <span>Deals</span>
              </a>
            </li>
            <li
              v-if="showCrmListingsDropdown"
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
              <ul v-if="crmListingsExpanded && !isMobileViewport" class="sidebar-submenu sidebar-submenu--grouped sidebar-submenu--nested">
                <template v-for="section in listingsSidebarSections" :key="section.key">
                  <li v-if="section.key !== 'listings'" class="sidebar-submenu__heading sidebar-submenu__heading--with-icon">
                    <img v-if="section.iconSrc" :src="section.iconSrc" class="imgicon submenu-icon" alt="" />
                    <span>{{ section.title }}</span>
                  </li>
                  <li
                    v-for="item in section.items"
                    :key="`${section.key}-${item.path}`"
                    :class="['nav-link', { 'active-page': isSidebarSubItemActive(item.path) }]"
                  >
                    <a
                      href="#"
                      class="sidebar-nav-link"
                      :class="{ 'sidebar-nav-link--under-heading': section.key !== 'listings' }"
                      @mouseenter="prefetchRoute(item.path)"
                      @click.prevent="goToListingsItem(item.path)"
                    >
                      <img
                        v-if="section.key === 'listings' && section.iconSrc"
                        :src="section.iconSrc"
                        class="imgicon submenu-icon"
                        alt=""
                      />
                      <span class="menu-label">{{ item.label }}</span>
                      <span v-if="item.count > 0" class="menu-count">{{ item.count }}</span>
                      <span v-else-if="countsLoading && item.count !== undefined" class="menu-count loading">…</span>
                    </a>
                  </li>
                </template>
              </ul>
            </li>
            <li
              v-else-if="showCrmListingsFlat"
              :class="['nav-link', { 'active-page': isSidebarCrmSectionActive(CRM_SECTIONS.LISTINGS) }]"
            >
              <a href="#" class="sidebar-nav-link" @mouseenter="prefetchRoute(crmListingsFlatPath)" @click.prevent="goToCrmListingsFlat">
                <img :src="listingsIcon" class="imgicon submenu-icon" alt="" />
                <span>Listings</span>
              </a>
            </li>
          </ul>
        </li>

        <li
          v-if="!isHr"
          :class="{
            dropdown: true,
            open: activeDropdown === 'calculator',
            'dropdown-open': activeDropdown === 'calculator',
            'active-parent': isSidebarModuleActive('calculator'),
          }"
        >
          <a href="javascript:void(0)" @click.stop.prevent="toggleDropdown('calculator')" :class="{ active: isSidebarModuleActive('calculator') }">
            <img :src="calculatorIcon" class="imgicon" alt="" />
            <span>Calculators</span>
            <span class="dropdown-arrow" :class="{ rotated: activeDropdown === 'calculator' }" />
          </a>
          <ul v-show="activeDropdown === 'calculator'" class="sidebar-submenu sidebar-submenu--crm sidebar-submenu--calculator">
            <li
              v-for="item in calculatorMenuItems"
              :key="item.path"
              :class="['nav-link', { 'active-page': isSidebarSubItemActive(item.path) }]"
            >
              <router-link :to="item.path" custom v-slot="{ navigate, href }">
                <a
                  :href="href"
                  class="sidebar-nav-link sidebar-nav-link--calculator"
                  :title="item.name"
                  @mouseenter="prefetchRoute(item.path)"
                  @click="navigate"
                >
                  <iconify-icon :icon="item.icon" class="menu-icon submenu-icon" />
                  <span>{{ item.label }}</span>
                </a>
              </router-link>
            </li>
          </ul>
        </li>

        <li v-if="isSuperAdmin || user.id === 186 || isHr">
          <router-link to="/hr" custom v-slot="{ navigate, href }">
            <a
              :href="href"
              class="sidebar-nav-link sidebar-nav-link--hr"
              :class="{ active: isSidebarModuleActive('hr') }"
              @mouseenter="prefetchRoute('/hr')"
              @click="navigate"
            >
              <img :src="hrIcon" class="imgicon" alt="" />
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
                  <a
                    :href="href"
                    class="sidebar-nav-link"
                    @mouseenter="prefetchRoute(item.path)"
                    @click="navigate"
                  >{{ item.label }}</a>
                </router-link>
              </li>
            </ul>
          </transition>
        </li>

        <li v-if="!isShowOnlyListing">
          <router-link to="/suggestion" custom v-slot="{ navigate, href }">
            <a
              :href="href"
              class="sidebar-nav-link"
              :class="{ active: isSidebarSubItemActive('/suggestion') }"
              @mouseenter="prefetchRoute('/suggestion')"
              @click="navigate"
            >
              <img :src="suggestionIcon" class="imgicon" alt="" />
              <span>Suggestions</span>
            </a>
          </router-link>
        </li>

        <li
          v-if="settingsSidebarSections.length > 0"
          class="sidebar-menu__settings"
          :class="{ dropdown: true, open: activeDropdown === 'settings', 'active-parent': isSidebarModuleActive('settings') }"
        >
          <a href="javascript:void(0)" @click="toggleDropdown('settings')" :class="{ active: isSidebarModuleActive('settings') }">
            <img :src="roleIcon" class="imgicon" alt="" />
            <span>Settings</span>
            <span class="dropdown-arrow" :class="{ rotated: activeDropdown === 'settings' }" />
          </a>
          <transition @before-enter="beforeEnter" @enter="enter" @after-enter="afterEnter" @before-leave="beforeLeave" @leave="leave" @after-leave="afterLeave">
            <ul v-show="activeDropdown === 'settings'" class="sidebar-submenu sidebar-submenu--grouped">
              <template v-for="section in settingsSidebarSections" :key="section.key">
                <li class="sidebar-submenu__heading">{{ section.title }}</li>
                <li v-for="item in section.items" :key="`${section.key}-${item.path}`" :class="['nav-link', { 'active-page': isSidebarSubItemActive(item.path) }]">
                <router-link :to="item.path" custom v-slot="{ navigate, href }">
                  <a
                    :href="href"
                    class="sidebar-nav-link"
                    @mouseenter="prefetchRoute(item.path)"
                    @click="navigate"
                  >
                    <img v-if="item.iconSrc" :src="item.iconSrc" class="imgicon submenu-icon" alt="" />
                    <iconify-icon v-else-if="item.icon" :icon="item.icon" class="menu-icon submenu-icon" />
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

    <div v-if="!isMobileViewport && user" class="sidebar-user" :class="{ 'sidebar-user--collapsed': isSidebarActive }">
      <img
        v-if="user.avatar"
        :src="user.avatar"
        alt=""
        class="sidebar-user__avatar"
      />
      <span v-else class="sidebar-user__avatar sidebar-user__avatar--fallback">{{ sidebarUserInitial }}</span>
      <span v-show="!isSidebarActive" class="sidebar-user__meta">
        <span class="sidebar-user__label">Logged in</span>
        <span class="sidebar-user__name">{{ user.name }}</span>
      </span>
    </div>

    <!-- Mobile: Listings panel slides in front of the main menu -->
    <div
      v-if="isMobileViewport && crmListingsExpanded && showCrmListingsDropdown"
      class="mobile-drawer-flyout"
    >
      <div class="mobile-drawer-flyout__head">
        <button type="button" class="mobile-drawer-flyout__back" @click="crmListingsExpanded = false">
          <iconify-icon icon="lucide:chevron-left" />
          <span>Listings</span>
        </button>
      </div>
      <ul class="mobile-drawer-flyout__list">
        <template v-for="section in listingsSidebarSections" :key="`flyout-${section.key}`">
          <li v-if="section.key !== 'listings'" class="mobile-drawer-flyout__section">{{ section.title }}</li>
          <li
            v-for="item in section.items"
            :key="`flyout-${section.key}-${item.path}`"
            :class="{ 'is-active': isSidebarSubItemActive(item.path) }"
          >
            <a href="#" class="mobile-drawer-flyout__link" @click.prevent="goToListingsItem(item.path)">
              <span>{{ item.label }}</span>
              <span v-if="item.count > 0" class="mobile-drawer-flyout__count">{{ item.count }}</span>
              <span v-else-if="countsLoading && item.count !== undefined" class="mobile-drawer-flyout__count loading">…</span>
            </a>
          </li>
        </template>
      </ul>
    </div>
  </aside>
  </Teleport>

  <Teleport to="body">
    <div
      v-if="isMobileMenuOpen"
      class="mobile-sidebar-overlay"
      aria-hidden="true"
      @click="closeMobileMenu"
    />
  </Teleport>

  <Teleport to="body">
    <nav
      v-if="showMobileCoreDock"
      class="mobile-core-dock"
      aria-label="Quick navigation"
    >
      <router-link
        v-if="showMobileQuickLeads"
        to="/kanban"
        class="mobile-core-dock__btn"
        :class="{ 'is-active': isCoreDockLeadsActive }"
        @click="onCoreDockLeadsClick"
      >
        <iconify-icon icon="lucide:contact" class="mobile-core-dock__icon" />
        <span>Leads</span>
      </router-link>
      <span
        v-if="showMobileQuickLeads && showMobileQuickListings"
        class="mobile-core-dock__divider"
        aria-hidden="true"
      />
      <router-link
        v-if="showMobileQuickListings"
        :to="crmListingsFlatPath"
        class="mobile-core-dock__btn"
        :class="{ 'is-active': isCoreDockListingsActive }"
        @click="onCoreDockListingsClick"
      >
        <iconify-icon icon="lucide:building-2" class="mobile-core-dock__icon" />
        <span>Listings</span>
      </router-link>
    </nav>
  </Teleport>

  <Teleport to="body">
    <nav v-if="false" ref="mobileDockRef" class="mobile-sidebar-dock mobile-sidebar-dock--bayut" aria-label="Mobile menu">
      <span
        class="mobile-sidebar-dock__cursor"
        :class="{ 'mobile-sidebar-dock__cursor--ready': dockCursorReady }"
        :style="dockCursorStyle"
        aria-hidden="true"
      />
      <template v-for="(item, index) in mobileDockItems" :key="item.key || item.path">
        <button
          v-if="item.children || item.sections"
          type="button"
          :ref="(el) => setDockItemRef(el, index)"
          class="mobile-sidebar-dock__item mobile-sidebar-dock__btn"
          :class="{ 'is-active': isDockItemHighlighted(item, index), 'is-chat': item.path === '/admin/chat' }"
          :aria-label="item.label"
          :aria-current="isDockItemHighlighted(item, index) ? 'page' : undefined"
          @click="onDockButtonClick(item, index)"
        >
          <img v-if="item.iconSrc" :src="item.iconSrc" class="mobile-sidebar-dock__icon mobile-sidebar-dock__icon--img" alt="" />
          <iconify-icon v-else :icon="item.icon" class="mobile-sidebar-dock__icon" />
          <span class="mobile-sidebar-dock__label">{{ item.label }}</span>
        </button>
        <router-link
          v-else
          :to="item.path"
          :ref="(el) => setDockItemRef(el, index)"
          class="mobile-sidebar-dock__item"
          :class="{ 'is-active': isDockItemHighlighted(item, index), 'is-chat': item.path === '/admin/chat' }"
          :aria-label="item.label"
          :aria-current="isDockItemHighlighted(item, index) ? 'page' : undefined"
          @click="onDockLinkClick(index)"
        >
          <img v-if="item.iconSrc" :src="item.iconSrc" class="mobile-sidebar-dock__icon mobile-sidebar-dock__icon--img" alt="" />
          <iconify-icon v-else :icon="item.icon" class="mobile-sidebar-dock__icon" />
          <span class="mobile-sidebar-dock__label">{{ item.label }}</span>
        </router-link>
      </template>
    </nav>
  </Teleport>

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
          <router-link
            v-for="child in activeMobileDockGroup?.children || []"
            :key="`dock-flat-${child.path}`"
            :to="child.path"
            class="mobile-dock-sheet__item"
            :class="{ 'is-active': isDockActive(child.path) }"
            @click="closeMobileDockGroup"
          >
            <img v-if="child.iconSrc" :src="child.iconSrc" class="mobile-dock-sheet__item-icon mobile-dock-sheet__item-icon--img" alt="" />
            <iconify-icon v-else-if="child.icon" :icon="child.icon" class="mobile-dock-sheet__item-icon" />
            <span>{{ child.label }}</span>
            <span v-if="child.count > 0" class="mobile-dock-sheet__count">{{ child.count }}</span>
          </router-link>
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
                <img v-if="section.iconSrc" :src="section.iconSrc" class="mobile-dock-accordion__icon" alt="" />
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
                <template v-if="section.subsections?.length">
                  <template v-for="sub in section.subsections" :key="sub.key">
                    <div class="mobile-dock-sheet__heading">{{ sub.title }}</div>
                    <router-link
                      v-for="child in sub.items"
                      :key="`${sub.key}-${child.path}`"
                      :to="child.path"
                      class="mobile-dock-sheet__item"
                      :class="{ 'is-active': isDockActive(child.path) }"
                      @click="closeMobileDockGroup"
                    >
                      <span>{{ child.label }}</span>
                      <span v-if="child.count > 0" class="mobile-dock-sheet__count">{{ child.count }}</span>
                    </router-link>
                  </template>
                </template>
                <template v-else>
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
                </template>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, getCurrentInstance, watch, nextTick } from 'vue';
import { useSidebarCounts } from '@/composables/useSidebarCounts.js';
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
import { useRoutePrefetch } from '@/composables/useRoutePrefetch.js';
import { startNavProgress } from '@/composables/useNavProgress.js';

const logo = ref('/assets/images/LogoWhite.png');
const oiaBrandLogo = ref('/assets/images/auth/oia-properties-logo.svg');
const dashboardIcon = ref('/assets/icons/dashboard-icon.svg?v=2');
const leadsIcon = ref('/assets/icons/leads-icon.svg?v=2');
const dealsIcon = ref('/assets/icons/deals-icon.svg?v=2');
const listingsIcon = ref('/assets/icons/listings-icon.svg?v=2');
const requestsIcon = ref('/assets/icons/request-icon.svg?v=2');
const isMobileOpen = ref(false);
const crmIcon = ref('/assets/icons/kanban-icon.svg?v=2');
const ownersIcon = ref('/assets/icons/owners-icon.svg?v=2');
const propertyIcon = ref('/assets/icons/property-icon.svg?v=2');
const unitViewIcon = ref('/assets/icons/unit-view-icon.svg?v=2');
const layoutTypeIcon = ref('/assets/icons/layout-icon.svg?v=2');
const locationIcon = ref('/assets/icons/area-icon.svg?v=2');
const agentsIcon = ref('/assets/icons/agents-icon.svg?v=2');
const roleIcon = ref('/assets/icons/role-icon.svg?v=2');
const hrIcon = ref('/assets/icons/hr-icon.svg?v=2');
const calculatorIcon = ref('/assets/icons/insights-icon.svg?v=2');
const projectsIcon = ref('/assets/icons/projects-icon.svg?v=2');
const featuresIcon = ref('/assets/icons/features-icon.svg?v=2');
const developerIcon = ref('/assets/icons/developer-icon.svg?v=2');
const allChatsIcon = ref('/assets/icons/all-chats-icon.svg?v=2');
const suggestionIcon = ref('/assets/icons/suggestion-icon.svg?v=2');
const insightsIcon = ref('/assets/icons/insights-icon.svg?v=2');

const route = useRoute();
const router = useRouter();
const { prefetchRoute, prefetchRoutes } = useRoutePrefetch(router);
const activeDropdown = ref(null);
const {
  counts: sidebarCounts,
  loading: countsLoading,
  fetchCounts,
  startPolling,
  stopPolling,
} = useSidebarCounts();
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
const sidebarUserInitial = computed(() => {
  const name = String(user.value?.name || '').trim();
  const parts = name.split(/\s+/).filter(Boolean);
  if (!parts.length) return 'U';
  return parts.slice(0, 2).map((part) => part[0]).join('').toUpperCase();
});

const isAdmin = computed(() => {
  if (!user.value) return false;
  
  const isAdminUser = user.value.roles?.includes('super_admin') || 
                     user.value.roles?.includes('admin') ||
                     proxy.$hasPermission('admin');
  
  return isAdminUser;
});

const canShowLeadsTab = computed(() => {
  if (!user.value) return false;
  return isAdmin.value || proxy.$hasPermission('show-leads');
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
const isHr =computed(() => {
  return user.value?.roles?.includes('hr') ?? false;
});
const tableItems = computed(() => {
  const c = sidebarCounts.value;
  const items = [
    { path: '/alllisting', label: 'All Listing', colorClass: 'text-warning-main w-auto', count: c?.listings?.all || 0,permission: 'listings-list' },
    { path: '/property-form', label: 'Create Listing', colorClass: 'text-info-main w-auto', permission: 'listings-create', count: 0 },
    { path: '/notify-me', label: 'Notify me', colorClass: 'text-info-main w-auto', count: 0 ,permission: 'listings-list'},
  ]

  if (!isAdmin.value) {
    items.splice(1, 0, {
      path: '/my-listing',
      label: 'My Listing',
      colorClass: 'text-warning-main w-auto',
      permission: 'listings-list',
      count: c?.listings?.my || 0
    })
  }

  return items
})

const requestsItems = computed(() => {
  const c = sidebarCounts.value;
  if (isAdmin.value) {
    return [
      { path: '/all-requests', label: 'All Requests', colorClass: 'text-white w-auto', count: c?.orders?.all || 0 },
            { path: '/my-viewings', label: 'Viewings', colorClass: 'text-white w-auto', count: 0, permission: 'listings-list' },

    ]
  } else {
    const items = [
      { path: '/my-requests', label: 'Inbound Request', colorClass: 'text-white w-auto', count: c?.requests?.all || 0 ,permission: 'listings-list'},
      { path: '/my-orders', label: 'Outbound Request', colorClass: 'text-white w-auto', count: c?.orders?.all || 0,permission: 'listings-list' },
      { path: '/my-viewings', label: 'Viewings', colorClass: 'text-white w-auto', count: 0, permission: 'listings-list' },
    ];
    
    // Only show hot deal requests for listing team members
    if (user.value?.is_listing_team &&  (user.value.roles?.includes('super_admin') ||  user.value.roles?.includes('admin') ||  user.value.roles?.includes('team_lead') ||  user.value.roles?.includes('manager'))) {
      items.push({ path: '/hotDeal-requests', label: 'Hot Deal Requests', colorClass: 'text-white w-auto', count: c?.hot_deals?.all || 0 });
    }
    
    if (user.value?.is_listing_team &&  (user.value.roles?.includes('super_admin') || user.value.roles?.includes('team_lead') ||  user.value.roles?.includes('manager'))) {
      items.push({ path: '/need-approve-requests', label: 'Need Approval Listings', colorClass: 'text-white w-auto', count: c?.needapprove?.all || 0 });
    }
    
    return items;
  }
});

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
  }).map((section) => ({
    ...section,
    iconSrc: listingsSectionIcon(section.iconKey || section.key),
  })),
);

function listingsSectionIcon(key) {
  const map = {
    listings: listingsIcon.value,
    projects: projectsIcon.value,
    requests: requestsIcon.value,
    developers: developerIcon.value,
    owners: ownersIcon.value,
    property_types: propertyIcon.value,
    features: featuresIcon.value,
    unit_views: unitViewIcon.value,
    layout_types: layoutTypeIcon.value,
    areas: locationIcon.value,
  };
  return map[key] || listingsIcon.value;
}

/** Listings nested submenu — all admins (inventory links under CRM → Listings) */
const isListingsDropdownAdmin = computed(() => isAdmin.value && !isShowOnlyListing.value);

const showCrmListingsDropdown = computed(() =>
  isListingsDropdownAdmin.value &&
  listingsSidebarSections.value.length > 0 &&
  !isShowOnlyListing.value,
);

const showCrmListingsFlat = computed(() =>
  
  !isListingsDropdownAdmin.value &&
  !isShowOnlyListing.value,
);


const crmListingsFlatPath = computed(() => getListingsEntryPath('/alllisting'));
const mainMenuItems = computed(() => {
  const items = [];

  if (isSuperAdmin.value) {
    items.push({ path: '/lead-reports', label: 'Lead Reports', iconSrc: insightsIcon.value });
  }

  if (isSuperAdmin.value) {
    items.push({ path: '/sales-intelligence', label: 'AI Sales Intelligence', iconSrc: insightsIcon.value });
    items.push({ path: '/investment-analysis', label: 'Investment Analysis', iconSrc: insightsIcon.value });
    items.push({ path: '/settings/city-investments', label: 'City Investments', iconSrc: projectsIcon.value });
  }

  if (isAdmin.value) {
    items.push({ path: '/lead-source-report', label: 'Leads by Source', iconSrc: insightsIcon.value });
    items.push({ path: '/settings/lead-scoring', label: 'Lead Scoring', iconSrc: leadsIcon.value });
  }

  return items;
});

const filteredMainMenuItems = computed(() => mainMenuItems.value.filter((item) => !!item.path));

const settingsSidebarSections = computed(() => {
  const system = [];
  if (isAdmin.value) {
    system.push({ path: '/system-overview', label: 'System Map', iconSrc: dashboardIcon.value });
  }
  if (isSuperAdmin.value) {
    system.push({ path: '/logs', label: 'Logs', icon: 'lucide:scroll-text' });
    system.push({ path: '/agent-performance', label: 'Agent Performance', iconSrc: agentsIcon.value });
    system.push({ path: '/import-pitrix', label: 'Import Leads', iconSrc: leadsIcon.value });
    system.push({ path: '/sync-bitrix-leads', label: 'Sync Leads', iconSrc: leadsIcon.value });
    system.push({ path: '/sync-responsible', label: 'Sync Responsible', iconSrc: agentsIcon.value });
    system.push({ path: '/settings/background', label: 'Background', icon: 'lucide:image' });
    system.push({ path: '/settings/deal-costs', label: 'Deal Costs', iconSrc: dealsIcon.value });
    system.push({ path: '/settings/user-duplicates-report', label: 'Non-OIA & Duplicate Users', iconSrc: agentsIcon.value });
  }

  const chat = isCustomAdmin.value
    ? [{ path: '/admin/chat', label: 'All Chats', iconSrc: allChatsIcon.value }]
    : [];

  // Suggestions moved to its own top-level sidebar item (see the standalone <li> right
  // before the Settings dropdown) — for a plain sales user it used to be the only entry
  // here, leaving a one-item "Settings" dropdown; promoting it out lets Settings itself
  // disappear for them (settingsSidebarSections.length > 0 gate below) when nothing else remains.
  const roles = (filteredRolesItems.value || []).map((item) => ({
    ...item,
    iconSrc: item.iconSrc || roleIcon.value,
  }));

  return buildSettingsSidebarSections({
    system,
    roles,
    tools: [],
    insights: filteredMainMenuItems.value,
    chat,
  });
});

const calculatorMenuItems = computed(() => [
  {
    path: '/settings/roi-calculator',
    label: 'ROI',
    name: 'Return on Investment',
    icon: 'lucide:percent',
  },
  {
    path: '/settings/roe-calculator',
    label: 'ROE',
    name: 'Return on Equity',
    icon: 'lucide:trending-up',
  },
]);

const allCalculatorMenuPaths = computed(() => calculatorMenuItems.value.map((i) => i.path));

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
  const moreChildren = [];
  const moreSections = [];

  if (calculatorMenuItems.value.length) {
    moreChildren.push(...calculatorMenuItems.value.map((it) => ({
      path: it.path,
      label: `${it.label} · ${it.name}`,
      icon: it.icon,
    })));
  }

  if (isSuperAdmin.value || isHr.value || user.value?.id === 186) {
    moreChildren.push({ path: '/hr', label: 'HR', iconSrc: hrIcon.value });
  }

  if (filteredUsersItems.value.length) {
    moreChildren.push(...filteredUsersItems.value.map((it) => ({ path: it.path, label: it.label })));
  }

  if (settingsSidebarSections.value.length) {
    moreSections.push(...settingsSidebarSections.value.map((section) => ({
      key: section.key,
      title: section.title,
      items: section.items.map((it) => ({ path: it.path, label: it.label })),
    })));
  }

  if (isAdmin.value && listingsSidebarSections.value.length && isListingsDropdownAdmin.value) {
    moreSections.push({
      key: 'more-listings',
      title: 'Listings',
      iconSrc: listingsIcon.value,
      subsections: listingsSidebarSections.value.map((section) => ({
        key: section.key,
        title: section.title,
        items: section.items.map((it) => ({
          path: it.path,
          label: it.label,
          count: it.count || 0,
        })),
      })),
    });
  }

  const items = [
    {
      path: isShowOnlyListing.value ? '/alllisting' : '/',
      label: 'Home',
      iconSrc: dashboardIcon.value,
    },
  ];

  if (isAdmin.value || isShowOnlyListing.value) {
    items.push({
      path: isShowOnlyListing.value ? '/alllisting' : getListingsEntryPath(),
      label: 'Listings',
      iconSrc: listingsIcon.value,
    });
  }

  if (!isShowOnlyListing.value && !isHr.value && canShowLeadsTab.value) {
    items.push({ path: '/kanban', label: 'Leads', iconSrc: leadsIcon.value });

  }
  if (!isShowOnlyListing.value && !isHr.value && canShowLeadsTab.value) {
    items.push({ path: '/kanban_deal', label: 'Deals', iconSrc: dealsIcon.value });
  }

  if (moreChildren.length || moreSections.length) {
    items.push({
      key: 'group-more',
      label: 'More',
      iconSrc: featuresIcon.value,
      children: moreChildren,
      sections: moreSections,
    });
  }

  return items;
});

function isDockActive(path) {
  return isMobileDockItemActive(path);
}

function isDockGroupActive(group) {
  if (isDashboardHome.value) return false;
  if (group?.children?.some((child) => isDockActive(child.path))) return true;
  if (group?.sections?.length) {
    return group.sections.some((section) => {
      if (section.subsections?.length) {
        return section.subsections.some((sub) =>
          sub.items?.some((child) => isDockActive(child.path)),
        );
      }
      return section.items?.some((child) => isDockActive(child.path));
    });
  }
  return false;
}

const mobileDockRef = ref(null);
const dockItemRefs = ref([]);
const dockCursorReady = ref(false);
const dockCursorStyle = ref({
  transform: 'translateX(0px)',
  width: '0px',
  opacity: '0',
});

function setDockItemRef(el, index) {
  if (!el) {
    dockItemRefs.value[index] = null;
    return;
  }
  dockItemRefs.value[index] = el.$el ?? el;
}

const manualDockIndex = ref(null);

const activeDockIndex = computed(() => {
  const items = mobileDockItems.value;

  // Dashboard home: always highlight Dashboard tab
  if (isDashboardHome.value) {
    const homeIdx = items.findIndex((it) => it.path === '/' || it.path === '/home');
    if (homeIdx >= 0) return homeIdx;
  }

  const dashboardIdx = items.findIndex((it) => it.path === '/alllisting');
  if (dashboardIdx >= 0 && isDockActive('/alllisting') && !isDockGroupActive(items.find((it) => it.key === 'group-crm'))) {
    return dashboardIdx;
  }

  for (let i = 0; i < items.length; i++) {
    const item = items[i];
    if (item.children || item.sections) {
      if (activeMobileDockGroup.value?.key === item.key) return i;
      if (isDockGroupActive(item)) return i;
    } else if (isDockActive(item.path)) {
      return i;
    }
  }
  return 0;
});

const highlightedDockIndex = computed(() => {
  if (manualDockIndex.value !== null) return manualDockIndex.value;
  return activeDockIndex.value;
});

function isDockItemHighlighted(item, index) {
  return highlightedDockIndex.value === index;
}

function setDockHighlight(index) {
  manualDockIndex.value = index;
  moveDockCursorToIndex(index);
}

function moveDockCursorToIndex(index) {
  requestAnimationFrame(() => {
    const nav = mobileDockRef.value;
    const el = dockItemRefs.value[index];
    if (!nav || !el) return;
    const navRect = nav.getBoundingClientRect();
    const elRect = el.getBoundingClientRect();
    const insetX = 5;
    const pillWidth = Math.max(42, elRect.width - insetX * 2);
    const left = elRect.left - navRect.left + insetX;
    dockCursorStyle.value = {
      transform: `translateX(${left}px)`,
      width: `${pillWidth}px`,
      opacity: '1',
    };
    dockCursorReady.value = true;
  });
}

function onDockButtonClick(item, index) {
  setDockHighlight(index);
  openMobileDockGroup(item, index);
}

function updateDockCursor() {
  moveDockCursorToIndex(highlightedDockIndex.value);
}

function onDockLinkClick(index) {
  closeMobileDockGroup();
  setDockHighlight(index);
}

let dockResizeObserver = null;

function attachDockObservers() {
  updateDockCursor();
  if (typeof ResizeObserver !== 'undefined' && mobileDockRef.value && !dockResizeObserver) {
    dockResizeObserver = new ResizeObserver(() => updateDockCursor());
    dockResizeObserver.observe(mobileDockRef.value);
    dockItemRefs.value.forEach((el) => {
      if (el) dockResizeObserver.observe(el);
    });
  }
}

function detachDockObservers() {
  dockResizeObserver?.disconnect();
  dockResizeObserver = null;
}

watch([mobileDockItems, isMobileViewport], () => {
  dockItemRefs.value = [];
  nextTick(attachDockObservers);
});

watch(activeDockIndex, () => {
  if (manualDockIndex.value === null) {
    nextTick(updateDockCursor);
  }
});

watch(() => route.path, () => {
  manualDockIndex.value = null;
  closeMobileDockGroup();
  closeMobileMenu();
  syncSidebarDropdownFromRoute();
  nextTick(updateDockCursor);
});

async function openMobileDockGroup(group, index) {
  if (group?.key === 'group-crm') {
    openCrmDropdown();
  }
  activeMobileDockGroup.value = group;
  mobileDockExpandedSection.value = group?.sections?.[0]?.key ?? null;
  showMobileDockSheet.value = true;
  moveDockCursorToIndex(index);
  await nextTick();
  updateDockCursor();
}

function closeMobileDockGroup() {
  showMobileDockSheet.value = false;
  activeMobileDockGroup.value = null;
  mobileDockExpandedSection.value = null;
  manualDockIndex.value = null;
  nextTick(updateDockCursor);
}

function toggleMobileDockSection(sectionKey) {
  mobileDockExpandedSection.value =
    mobileDockExpandedSection.value === sectionKey ? null : sectionKey;
}


// Methods
const toggleDropdown = (name) => {
  activeDropdown.value = activeDropdown.value === name ? null : name;
  localStorage.setItem('activeDropdown', activeDropdown.value || '');
  if (activeDropdown.value === 'calculator') {
    prefetchRoutes(allCalculatorMenuPaths.value);
  } else if (activeDropdown.value === 'users') {
    prefetchRoutes(filteredUsersItems.value.map((i) => i.path));
  } else if (activeDropdown.value === 'settings') {
    prefetchRoutes(allSettingsMenuPaths.value);
  }
};

const openCrmDropdown = () => {
  activeDropdown.value = 'crm';
  localStorage.setItem('activeDropdown', 'crm');
  // Prefetch only the two CRM boards — avoid downloading every listings chunk
  // while the user is still deciding / navigating.
  prefetchRoutes(['/kanban', '/kanban_deal']);
};

const closeCrmDropdown = () => {
  activeDropdown.value = null;
  localStorage.removeItem('activeDropdown');
  crmListingsExpanded.value = false;
};

async function goToCrmSection(section) {
  rememberCrmSection(section);
  activeDropdown.value = 'crm';
  localStorage.setItem('activeDropdown', 'crm');
  crmListingsExpanded.value = false;
  if (isMobileViewport.value) closeMobileMenu();

  if (section === CRM_SECTIONS.LEAD) {
    localStorage.setItem('kanban_active_tab', 'leads');
    if (route.path !== '/kanban') {
      await Promise.all([
        import('@/pages/kanban.vue'),
        import('@/components/kanban/leadList/leads.vue'),
      ]);
      router.push('/kanban');
    }
    window.dispatchEvent(new CustomEvent('kanban-tab-change', { detail: 'leads' }));
    return;
  }

  if (section === CRM_SECTIONS.DEAL) {
    localStorage.setItem('kanban_active_tab', 'deals');
    if (route.path !== '/kanban_deal') {
      startNavProgress();
      router.push('/kanban_deal');
    }
    window.dispatchEvent(new CustomEvent('kanban-tab-change', { detail: 'deals' }));
    const dealType = localStorage.getItem('kanban_deal_type') || 'primary';
    window.dispatchEvent(new CustomEvent('kanban-deal-type-change', { detail: dealType }));
  }
}

async function goToListingsItem(path) {
  rememberCrmSection(CRM_SECTIONS.LISTINGS);
  rememberListingsPath(path);
  activeDropdown.value = 'crm';
  localStorage.setItem('activeDropdown', 'crm');
  if (isMobileViewport.value) closeMobileMenu();
  if (route.path !== path) {
    startNavProgress();
    router.push(path);
  }
}

async function goToCrmListingsFlat() {
  rememberCrmSection(CRM_SECTIONS.LISTINGS);
  rememberListingsPath(crmListingsFlatPath.value);
  activeDropdown.value = 'crm';
  localStorage.setItem('activeDropdown', 'crm');
  crmListingsExpanded.value = false;
  if (route.path !== crmListingsFlatPath.value) {
    startNavProgress();
    router.push(crmListingsFlatPath.value);
  }
}

const showMobileQuickLeads = computed(() => isAdmin.value && !isShowOnlyListing.value);
const showMobileQuickListings = computed(() => isAdmin.value || isShowOnlyListing.value);
const showMobileSidebarQuickbar = computed(() => showMobileQuickLeads.value || showMobileQuickListings.value);
const isPropertyShowPage = computed(() => route.path.startsWith('/property-details'));
const showMobileCoreDock = computed(() => showMobileSidebarQuickbar.value && !isPropertyShowPage.value);

const isCoreDockLeadsActive = computed(() => {
  const p = route.path;
  return p === '/kanban' || (p.startsWith('/kanban/') && !p.startsWith('/kanban_deal'));
});

const isCoreDockListingsActive = computed(() => {
  if (route.path.startsWith('/property-details')) return true;
  return resolveCrmSection(route.path) === CRM_SECTIONS.LISTINGS;
});

function onCoreDockLeadsClick() {
  rememberCrmSection(CRM_SECTIONS.LEAD);
  localStorage.setItem('kanban_active_tab', 'leads');
  window.dispatchEvent(new CustomEvent('kanban-tab-change', { detail: 'leads' }));
}

function onCoreDockListingsClick() {
  rememberCrmSection(CRM_SECTIONS.LISTINGS);
  rememberListingsPath(crmListingsFlatPath.value);
}

function onMobileSidebarNavClick(event) {
  if (!isMobileViewport.value) return;
  const link = event.target.closest('a.sidebar-nav-link, .sidebar-menu a[href]');
  if (!link) return;
  const href = link.getAttribute('href') || '';
  if (href.startsWith('javascript') || href === '#') return;
  closeMobileMenu();
}

async function mobileQuickGoLeads() {
  closeMobileMenu();
  await goToCrmSection(CRM_SECTIONS.LEAD);
}

async function mobileQuickGoListings() {
  closeMobileMenu();
  await goToCrmListingsFlat();
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
  if (isMobileViewport.value) {
    crmListingsExpanded.value = !crmListingsExpanded.value;
    return;
  }
  crmListingsExpanded.value = !crmListingsExpanded.value;
};

const handleMobileDrawerClose = () => {
  if (isMobileViewport.value && crmListingsExpanded.value && showCrmListingsDropdown.value) {
    crmListingsExpanded.value = false;
    return;
  }
  closeMobileMenu();
};

// Animation functions (تبقى كما هي)
function beforeEnter(el) {
  el.style.height = '0px';
  el.style.opacity = '0';
  el.style.overflow = 'hidden';
}

function enter(el) {
  el.style.transition = 'height 0.18s ease';
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
  el.style.transition = 'height 0.15s ease';
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
    if (!['crm', 'calculator', 'settings', 'users'].includes(activeDropdown.value)) {
      activeDropdown.value = null;
      localStorage.removeItem('activeDropdown');
    }
    return;
  }

  const crmSection = resolveCrmSection(route.path);
  if (crmSection) {
    openCrmDropdown();
    crmListingsExpanded.value =
      showCrmListingsDropdown.value &&
      crmSection === CRM_SECTIONS.LISTINGS &&
      !isMobileViewport.value;
    if (crmSection === CRM_SECTIONS.LISTINGS) {
      rememberListingsPath(route.path);
    }
    rememberCrmSection(crmSection);
    return;
  }
  if (allCalculatorMenuPaths.value.some((p) => isSidebarSubItemActive(p))) {
    activeDropdown.value = 'calculator';
    localStorage.setItem('activeDropdown', 'calculator');
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

watch(isDashboardHome, (onHome) => {
  if (!onHome) return;
  // Keep dropdown open if user explicitly opened it from dashboard
  if (['crm', 'calculator', 'settings', 'users'].includes(activeDropdown.value)) return;
  activeDropdown.value = null;
  localStorage.removeItem('activeDropdown');
});

watch(isMobileMenuOpen, (open) => {
  if (!open) crmListingsExpanded.value = false;
});

onMounted(() => {
  syncViewport();
  window.addEventListener('resize', syncViewport);
  window.addEventListener('resize', updateDockCursor, { passive: true });
  if (localStorage.getItem('activeDropdown') === 'listings') {
    localStorage.setItem('activeDropdown', 'crm');
  }
  syncSidebarDropdownFromRoute();
  fetchCounts().catch(() => {});
  startPolling();
  nextTick(attachDockObservers);

  // Warm primary CRM boards after first paint — one at a time so we don't
  // saturate the network before the user clicks.
  const warm = () => prefetchRoute('/kanban');
  const warmMore = () => {
    prefetchRoute('/kanban_deal');
    prefetchRoute('/alllisting');
  };
  if (typeof window.requestIdleCallback === 'function') {
    window.requestIdleCallback(warm, { timeout: 3000 });
    window.requestIdleCallback(warmMore, { timeout: 6000 });
  } else {
    window.setTimeout(warm, 1500);
    window.setTimeout(warmMore, 3500);
  }
});

onUnmounted(() => {
  stopPolling();
  window.removeEventListener('resize', syncViewport);
  window.removeEventListener('resize', updateDockCursor);
  detachDockObservers();
});

</script>
<style scoped>
/* 1. Default / open sidebar: frosted so system background shows */
.sidebar {
  display: flex;
  flex-direction: column;
  background: rgba(255, 255, 255, 0.72) !important;
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-color: rgba(232, 234, 239, 0.8) !important;
  z-index: 1100 !important;
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
  list-style: none;
}

.sidebar-menu,
.sidebar-submenu,
.sidebar-menu li,
.sidebar-submenu li {
  /* display:flex on the <ul> suppresses the native bullet in Chrome, but not reliably in
     iOS Safari — there it can still render a marker dot next to the icon on every row,
     which looks like a second icon. */
  list-style: none;
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
  padding: 0.25rem 0;
  min-height: 40px;
  box-sizing: border-box;
  justify-content: stretch;
  align-items: center;
  background: transparent;
  border-bottom: 1px solid #eef0f4;
}

aside.sidebar {
  display: flex;
  flex-direction: column;
  background: #ffffff !important;
  border-radius: 28px !important;
  overflow: visible !important;
  box-shadow: 0 12px 32px rgba(76, 29, 149, 0.08) !important;
}

.sidebar-brand {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  padding: 14px 16px 6px;
  min-height: 0;
}

.sidebar-brand__mark {
  width: 86px;
  overflow: hidden;
  display: flex;
  justify-content: center;
}

.sidebar-brand__logo {
  width: 86px;
  height: auto;
  display: block;
}

.sidebar-brand--collapsed {
  min-height: 0;
  padding: 16px 0 10px;
}

.sidebar-brand--collapsed .sidebar-brand__mark {
  width: 28px;
  height: 26px;
  flex: 0 0 28px;
  overflow: hidden;
  background: url('/assets/images/auth/oia-properties-logo.svg') center top / 49px auto no-repeat;
}

.sidebar-brand--collapsed .sidebar-brand__logo {
  display: none;
}

.sidebar.active:hover .sidebar-brand--collapsed .sidebar-brand__mark {
  width: 86px;
  height: auto;
  flex: 0 0 auto;
  background: none;
}

.sidebar.active:hover .sidebar-brand--collapsed .sidebar-brand__logo {
  display: block;
  width: 86px;
  height: auto;
  max-width: none;
}

.sidebar-edge-toggle {
  position: absolute;
  top: 18px;
  inset-inline-end: -12px;
  transform: none;
  z-index: 40;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  border: 1px solid #ece7f6;
  background: #fff;
  color: #5b21b6;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(76, 29, 149, 0.16);
  padding: 0;
  pointer-events: auto;
}

.sidebar-edge-toggle iconify-icon {
  font-size: 14px;
  display: inline-flex;
  pointer-events: none;
}

.sidebar-edge-toggle:hover {
  color: #6d28d9;
  border-color: #ddd6fe;
}

.sidebar-menu-area {
  flex: 1 1 auto;
  height: auto !important;
  min-height: 0;
}

.sidebar-user {
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 8px 12px 14px;
  padding: 8px 10px;
  border-radius: 16px;
  background: #f8f6fc;
}

.sidebar-user--collapsed {
  justify-content: center;
  margin-inline: 6px;
  padding: 8px 0;
  background: transparent;
}

.sidebar-user__avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
}

.sidebar-user__avatar--fallback {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #ede9fe;
  color: #6d28d9;
  font-size: 12px;
  font-weight: 700;
}

.sidebar-user__meta {
  display: flex;
  flex-direction: column;
  min-width: 0;
  line-height: 1.2;
}

.sidebar-user__label {
  font-size: 11px;
  color: #94a3b8;
}

.sidebar-user__name {
  font-size: 13px;
  font-weight: 700;
  color: #1e1b4b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

aside.sidebar .sidebar-menu > li > a.active,
aside.sidebar .sidebar-menu .nav-link.active-page > a {
  position: relative;
  color: #6d28d9 !important;
}

aside.sidebar .sidebar-menu > li > a.active span,
aside.sidebar .sidebar-menu .nav-link.active-page > a span {
  color: #6d28d9 !important;
  font-weight: 600;
}

aside.sidebar .sidebar-menu > li > a.active::before,
aside.sidebar .sidebar-menu .nav-link.active-page > a::before {
  content: '';
  position: absolute;
  inset-inline-start: 0;
  top: 8px;
  bottom: 8px;
  width: 3px;
  border-radius: 0 4px 4px 0;
  background: #6d28d9;
}
.sidebar-menu li a {
    padding: 0.28rem 0.4rem !important;
    min-height: 34px;
    box-sizing: border-box;
    align-items: center;
    margin-bottom: 1px;
}
.sidebar-menu > li > a span {
  font-size: 0.8125rem;
  line-height: 1.25;
  font-weight: 500;
}
.sidebar-submenu li a span,
.sidebar-submenu .menu-label {
  font-size: 0.75rem;
  line-height: 1.25;
  font-weight: 500;
}
.sidebar:not(.active) .sidebar-submenu,
.sidebar.active:hover .sidebar-submenu {
  padding-inline-start: 14px;
}
/* Hovering a closed menu opens it so the labels can be read. */
.sidebar.active:hover {
  width: 11.75rem !important;
  min-width: 11.75rem !important;
  max-width: none !important;
  background: #ffffff !important;
  z-index: 1100 !important;
}
@media (max-width: 991px) {
  .sidebar.sidebar-open:not(.sidebar--mobile-drawer) {
    background: #ffffff !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    z-index: 100 !important;
  }
}

@media (max-width: 768px) {
  .sidebar {
    z-index: 12050 !important;
    background: #ffffff !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    pointer-events: auto !important;
    touch-action: auto !important;
  }

  .sidebar .sidebar-menu li a,
  .sidebar .sidebar-nav-link {
    pointer-events: auto !important;
    cursor: pointer;
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
    z-index: 10100;
    background: rgba(15, 23, 42, 0.42);
    display: flex;
    align-items: flex-end;
    justify-content: center;
    padding-bottom: calc(68px + env(safe-area-inset-bottom, 0px));
    box-sizing: border-box;
  }

  .mobile-dock-sheet {
    width: 100%;
    background: #fff;
    border-radius: 20px 20px 0 0;
    padding: 8px 12px calc(10px + env(safe-area-inset-bottom, 0px));
    box-shadow: 0 -8px 30px rgba(15, 23, 42, 0.18);
    max-height: min(44vh, 360px);
    overflow: auto;
    position: relative;
    z-index: 10101;
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
    gap: 8px;
    font-size: 11px;
    font-weight: 600;
  }

  .mobile-dock-sheet__item-icon {
    flex-shrink: 0;
    font-size: 15px;
    color: #5b3d8f;
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

  .mobile-dock-sheet__heading {
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #64748b;
    padding: 8px 4px 2px;
  }
}
@media (min-width: 1200px) {
  .sidebar.active:hover {
    inset-inline-start: 0;
    width: 11.75rem !important;
    min-width: 11.75rem !important;
  }
}
@media (min-width: 1400px) {
  .sidebar.active:hover {
    width: 11.75rem !important;
    min-width: 11.75rem !important;
  }
}
@media (min-width: 1650px) {
  .sidebar.active:hover {
    width: 11.75rem !important;
    min-width: 11.75rem !important;
  }
}

/* Menu icon: white on dark header */
.sidebar-toggle-wrap {
  position: relative;
  display: flex;
  align-items: center;
  width: 100%;
}

.sidebar-toggle {
  display: flex;
  flex-direction: row;
  flex-wrap: nowrap;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-width: 0;
  height: 40px;
  min-height: 40px;
  max-height: 40px;
  padding: 0.35rem 0.4rem;
  box-sizing: border-box;
  background: transparent;
  border: none;
  cursor: pointer;
  flex-shrink: 0;
  gap: 8px;
  line-height: 1;
  transform: none !important;
}
.sidebar-toggle-with-label {
  justify-content: flex-start;
}
/* Same place, same style as menu items but smaller and not bold */
.sidebar-toggle-label {
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 700;
  color: #1a1528;
  white-space: nowrap;
  line-height: 1.25;
}
.sidebar-menu-icon {
  font-size: 1.25rem;
  color: #4b4568 !important;
  width: 1.25rem;
  height: 1.25rem;
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}
.sidebar-toggle:hover .sidebar-menu-icon {
  color: #6b21a8 !important;
}
[data-theme="dark"] .sidebar-menu-icon {
  color: #4b4568 !important;
}
[data-theme="dark"] .sidebar-toggle:hover .sidebar-menu-icon {
  color: #6b21a8 !important;
}
/* Ensure Iconify icon inherits color (SVG fill) */
.sidebar-menu-icon :deep(svg),
.sidebar-menu-icon :deep(path) {
  fill: currentColor;
}

.sidebar-menu .dropdown.active-parent > a {
  background: transparent;
  border: 1px solid transparent;
  box-shadow: none;
  border-radius: 10px;
  color: #4b4568;
}

.sidebar-menu .nav-link.active-page a {
  background: transparent;
  border: 1px solid transparent;
  border-radius: 8px;
  box-shadow: none;
}

.sidebar-menu .dropdown.active-parent .menu-icon,
.sidebar-menu .nav-link.active-page .menu-icon {
  color: #6b7280;
}

.sidebar-menu li a.active {
  background: transparent;
  border: 1px solid transparent;
  box-shadow: none;
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

/* Menu links: dark text on light sidebar */
.sidebar-menu li a,
.sidebar-submenu li a {
  display: flex;
  flex-direction: row;
  flex-wrap: nowrap;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 8px 10px;
  margin-bottom: 2px;
  border-radius: 10px;
  color: #4b4568;
  transition: background 0.15s ease, color 0.15s ease;
}
.sidebar-menu li a:hover,
.sidebar-submenu li a:hover {
  color: #4b4568;
  background: rgba(15, 9, 57, 0.04);
}
.sidebar-menu li a.active,
.sidebar-menu li a.sidebar-nav-link.active {
  background: transparent;
  border: 1px solid transparent;
  box-shadow: none;
  border-radius: 10px;
  padding: 8px 10px;
  color: #4b4568;
}

/* Main dashboard: only Dashboard may appear active in the sidebar */
.sidebar--dashboard-home .sidebar-menu > li > a.sidebar-nav-link.active:not(.sidebar-nav-link--dashboard),
.sidebar--dashboard-home .sidebar-menu .dropdown.active-parent > a,
.sidebar--dashboard-home .sidebar-menu .nav-link.active-page a {
  background: transparent !important;
  border-color: transparent !important;
  box-shadow: none !important;
  color: #4b4568 !important;
}

.sidebar--dashboard-home {
  background: rgba(255, 255, 255, 0.72) !important;
  backdrop-filter: blur(12px) !important;
  -webkit-backdrop-filter: blur(12px) !important;
  border-color: rgba(232, 234, 239, 0.8) !important;
}

.sidebar--dashboard-home.active:hover {
  background: rgba(255, 255, 255, 0.88) !important;
}

.sidebar--dashboard-home .sidebar-header {
  padding: 0.25rem 0;
  min-height: 40px;
  border-bottom: 1px solid #eef0f4;
}

.sidebar--dashboard-home .sidebar-toggle,
.sidebar--dashboard-home .sidebar-menu-icon,
.sidebar--dashboard-home .sidebar-toggle-label,
.sidebar--dashboard-home .sidebar-menu li a,
.sidebar--dashboard-home .sidebar-submenu li a,
.sidebar--dashboard-home .menu-icon {
  color: #4b4568 !important;
}

.sidebar--dashboard-home .sidebar-menu li a:hover {
  background: #f4f5f7 !important;
  color: #6b21a8 !important;
}

.sidebar--dashboard-home .sidebar-menu li a {
  padding: 0.35rem 0.4rem !important;
  margin-bottom: 1px;
  border-radius: 10px;
  min-height: 40px;
}

.sidebar--dashboard-home .sidebar-menu > li > a.sidebar-nav-link--dashboard.active {
  background: transparent !important;
  border: 1px solid transparent !important;
  box-shadow: none !important;
  color: #4b4568 !important;
}

.sidebar--dashboard-home .sidebar-menu li a span,
.sidebar--dashboard-home .sidebar-submenu li a span {
  font-size: 0.75rem;
  line-height: 1.25;
  color: inherit !important;
}

.sidebar--dashboard-home .menu-icon,
.sidebar--dashboard-home .imgicon {
  width: 18px;
  height: 18px;
}

.sidebar--dashboard-home .imgicon {
  filter: brightness(0) saturate(100%) invert(28%) sepia(8%) saturate(900%) hue-rotate(210deg) brightness(95%);
  opacity: 0.9;
}

.sidebar--dashboard-home .sidebar-menu li a.active .imgicon,
.sidebar--dashboard-home .sidebar-menu li a:hover .imgicon {
  filter: brightness(0) saturate(100%) invert(28%) sepia(8%) saturate(900%) hue-rotate(210deg) brightness(95%);
  opacity: 1;
}

/* Keep CRM submenu visible when user opens it from dashboard */
.sidebar--dashboard-home .sidebar-menu li.dropdown.open > .sidebar-submenu--crm,
.sidebar--dashboard-home .sidebar-menu li.dropdown.dropdown-open > .sidebar-submenu--crm {
  display: block !important;
  visibility: visible !important;
}

.sidebar-submenu--calculator {
  padding-top: 4px;
}

.sidebar-nav-link--calculator {
  align-items: center !important;
}

/* Keep Calculator submenu visible when user opens it from dashboard */
.sidebar--dashboard-home .sidebar-menu li.dropdown.open > .sidebar-submenu--calculator,
.sidebar--dashboard-home .sidebar-menu li.dropdown.dropdown-open > .sidebar-submenu--calculator {
  display: block !important;
  visibility: visible !important;
}

/* Keep Settings submenu visible when user opens it from dashboard */
.sidebar--dashboard-home .sidebar-menu li.dropdown.open > .sidebar-submenu--grouped,
.sidebar--dashboard-home .sidebar-menu li.sidebar-menu__settings.open > .sidebar-submenu--grouped {
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
  padding-left: 0.35rem !important;
}

.sidebar-submenu--nested {
  margin-left: 0 !important;
  padding-left: 0 !important;
  border-left: none !important;
}

.dropdown-arrow--nested {
  margin-left: auto;
}


/* Icons and dropdown arrow visible on light sidebar */
.sidebar-menu .nav-link.active-page a,
.sidebar-submenu .nav-link.active-page a {
  background: transparent;
  filter: none;
  border-radius: 10px;
  color: #4b4568;
  box-shadow: none;
  border: 1px solid transparent;
}
.sidebar .menu-icon {
  color: #6b7280 !important;
  font-size: 1.125rem !important;
  width: 1.125rem;
  height: 1.125rem;
  flex-shrink: 0;
}
.sidebar .imgicon {
  opacity: 0.9;
  width: 1.25rem;
  height: auto;
  flex-shrink: 0;
  filter: brightness(0) saturate(100%) invert(28%) sepia(8%) saturate(900%) hue-rotate(210deg) brightness(95%);
}
.sidebar .dropdown-arrow {
  border-left-color: #9ca3af;
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
  color: #9ca3af;
  pointer-events: none;
}

.sidebar-submenu__heading--with-icon {
  display: flex;
  align-items: center;
  gap: 8px;
  text-transform: none;
  letter-spacing: 0;
  font-size: 11px;
  font-weight: 600;
  color: #6b7280;
}

.sidebar-submenu__heading--with-icon .imgicon {
  width: 1rem;
  height: 1rem;
  object-fit: contain;
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
  color: #4b4568 !important;
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

  .mobile-dock-accordion__icon {
    width: 18px;
    height: 18px;
    object-fit: contain;
    flex-shrink: 0;
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