import { computed } from 'vue';
import { useRoute } from 'vue-router';
import {
  LAYOUT_MODULES,
  CRM_SECTIONS,
  resolveActiveModule,
  resolveCrmSection,
  isDashboardRoute,
  isPathActive,
  isTopModuleNavActive,
  getCrmEntryPath,
  rememberListingsPath,
  rememberCrmSection,
} from './useLayoutNavigation.js';

/**
 * Single source of truth for sidebar + header active/highlight state.
 */
export function useLayoutActiveState() {
  const route = useRoute();

  const isDashboardHome = computed(
    () => !!route.meta?.dashboardHome || isDashboardRoute(route.path),
  );

  const activeLayoutModule = computed(() => {
    if (isDashboardHome.value) return LAYOUT_MODULES.DASHBOARD;
    return resolveActiveModule(route.path);
  });

  const activeCrmSection = computed(() => {
    if (isDashboardHome.value) return null;
    return resolveCrmSection(route.path);
  });

  const crmEntryPath = computed(() => {
    void route.path;
    return getCrmEntryPath();
  });

  function isSidebarModuleActive(module) {
    if (isDashboardHome.value) {
      return module === LAYOUT_MODULES.DASHBOARD;
    }
    return activeLayoutModule.value === module;
  }

  function isSidebarCrmSectionActive(section) {
    if (isDashboardHome.value) return false;
    return activeCrmSection.value === section;
  }

  function isSidebarSubItemActive(path) {
    if (isDashboardHome.value) return false;
    return isPathActive(route.path, path);
  }

  function isTopModuleItemActive(item) {
    return isTopModuleNavActive(route.path, activeLayoutModule.value, item);
  }

  function isMobileDockItemActive(path) {
    if (path === '/' || path === '/home') return isDashboardHome.value;
    if (path === '/alllisting' || path === '/my-listing' || path === '/archive') {
      if (isDashboardHome.value) return false;
      if (route.path.startsWith('/property-details/')) return true;
      return isPathActive(route.path, path);
    }
    if (isDashboardHome.value) return false;
    return isPathActive(route.path, path);
  }

  return {
    isDashboardHome,
    activeLayoutModule,
    activeCrmSection,
    crmEntryPath,
    isSidebarModuleActive,
    isSidebarCrmSectionActive,
    isSidebarSubItemActive,
    isTopModuleItemActive,
    isMobileDockItemActive,
    rememberListingsPath,
    rememberCrmSection,
    CRM_SECTIONS,
  };
}
