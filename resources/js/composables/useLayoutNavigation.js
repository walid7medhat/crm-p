import { computed } from 'vue';

/** Route prefixes grouped by main sidebar module */
export const LAYOUT_MODULES = {
  DASHBOARD: 'dashboard',
  CRM: 'crm',
  HR: 'hr',
  AGENTS: 'agents',
  LISTINGS: 'listings',
  SETTINGS: 'settings',
};

export const CRM_PREFIXES = ['/kanban', '/kanban_deal'];

export const CRM_SECTIONS = {
  LEAD: 'lead',
  DEAL: 'deal',
  LISTINGS: 'listings',
};

export const KANBAN_ACTIVE_TAB_KEY = 'kanban_active_tab';
export const CRM_SECTION_KEY = 'crm_active_section';
export const DEAL_TYPE_KEY = 'kanban_deal_type';
export const LAST_LISTINGS_PATH_KEY = 'layout_last_listings_path';

const ALL_LISTINGS_MATCH_PATHS = [
  '/alllisting',
  '/my-listing',
  '/archive',
  '/property-details',
  '/properties',
  '/properties-map',
  '/property-form',
  '/notify-me',
  '/my-requests',
  '/my-orders',
  '/all-requests',
  '/hotDeal-requests',
  '/need-approve-requests',
  '/my-viewings',
  '/developers',
  '/add-developer',
  '/owners',
  '/property_types',
  '/add-property_type',
  '/unit_views',
  '/add-unit_view',
  '/layout_types',
  '/add-layout_type',
  '/areas',
  '/add-area',
  '/features',
  '/add-features',
  '/projects',
  '/add-projects',
];

const HR_PREFIXES = ['/hr'];

const AGENTS_PREFIXES = ['/users', '/add-user', '/team-tree', '/view-profile', '/invited'];

const LISTINGS_PREFIXES = [
  '/alllisting',
  '/my-listing',
  '/property-form',
  '/notify-me',
  '/archive',
  '/properties-map',
  '/property-details',
  '/properties',
  '/my-requests',
  '/my-orders',
  '/all-requests',
  '/hotDeal-requests',
  '/need-approve-requests',
  '/my-viewings',
  '/developers',
  '/add-developer',
  '/owners',
  '/property_types',
  '/add-property_type',
  '/unit_views',
  '/add-unit_view',
  '/layout_types',
  '/add-layout_type',
  '/areas',
  '/add-area',
  '/features',
  '/add-features',
  '/projects',
  '/add-projects',
];

const SETTINGS_PREFIXES = [
  '/logs',
  '/system-overview',
  '/attendance-monthly-reports',
  '/roles',
  '/add-role',
  '/lead-reports',
  '/sales-intelligence',
  '/investment-analysis',
  '/settings',
  '/admin/chat',
  '/suggestion',
  '/import-pitrix',
  '/area-coordinates',
  '/project-map',
  '/email',
];

function pathMatches(path, prefixes) {
  return prefixes.some((prefix) => path === prefix || path.startsWith(`${prefix}/`));
}

export function isDashboardRoute(path) {
  return path === '/' || path === '/home' || path === '';
}

export function resolveCrmSection(path) {
  if (pathMatches(path, LISTINGS_PREFIXES)) return CRM_SECTIONS.LISTINGS;
  if (path === '/kanban_deal' || path.startsWith('/kanban_deal/')) return CRM_SECTIONS.DEAL;
  if (path === '/kanban' || path.startsWith('/kanban/')) return CRM_SECTIONS.LEAD;
  return null;
}

export function rememberCrmSection(section) {
  if (!section || !Object.values(CRM_SECTIONS).includes(section)) return;
  try {
    localStorage.setItem(CRM_SECTION_KEY, section);
  } catch {
    /* ignore */
  }
}

export function getRememberedCrmSection() {
  try {
    const stored = localStorage.getItem(CRM_SECTION_KEY);
    if (stored && Object.values(CRM_SECTIONS).includes(stored)) return stored;
  } catch {
    /* ignore */
  }
  return CRM_SECTIONS.LEAD;
}

export function getCrmSectionEntryPath(section, ctx = {}) {
  if (section === CRM_SECTIONS.DEAL) return '/kanban_deal';
  if (section === CRM_SECTIONS.LISTINGS) {
    const fallback =
      ctx.isAdmin || ctx.hasPermission?.('listings-list')
        ? '/alllisting'
        : '/my-listing';
    return getListingsEntryPath(fallback);
  }
  return '/kanban';
}

export function getCrmEntryPath(ctx = {}) {
  return getCrmSectionEntryPath(getRememberedCrmSection(), ctx);
}

export function getListingsEntryPath(fallback = '/alllisting') {
  try {
    const stored = localStorage.getItem(LAST_LISTINGS_PATH_KEY);
    if (stored && pathMatches(stored, LISTINGS_PREFIXES)) return stored;
  } catch {
    /* ignore */
  }
  return fallback;
}

export function rememberListingsPath(path) {
  if (!path || !pathMatches(path, LISTINGS_PREFIXES)) return;
  try {
    localStorage.setItem(LAST_LISTINGS_PATH_KEY, path);
  } catch {
    /* ignore */
  }
}

export function resolveActiveModule(path) {
  if (isDashboardRoute(path)) return LAYOUT_MODULES.DASHBOARD;
  if (pathMatches(path, CRM_PREFIXES) || pathMatches(path, LISTINGS_PREFIXES)) {
    return LAYOUT_MODULES.CRM;
  }
  if (pathMatches(path, HR_PREFIXES)) return LAYOUT_MODULES.HR;
  if (pathMatches(path, AGENTS_PREFIXES)) return LAYOUT_MODULES.AGENTS;
  if (pathMatches(path, SETTINGS_PREFIXES)) return LAYOUT_MODULES.SETTINGS;
  return null;
}

export function isPathActive(currentPath, targetPath) {
  if (targetPath === '/') return currentPath === '/' || currentPath === '/home';
  return currentPath === targetPath || currentPath.startsWith(`${targetPath}/`);
}

export function isTabActive(currentPath, tab) {
  const paths = tab.matchPaths || [tab.path];
  return paths.some((p) => isPathActive(currentPath, p));
}

/**
 * Header sub-navigation tabs — context-aware per CRM section or module.
 */
export function buildHeaderTabs(module, ctx = {}, crmSection = null) {
  const { isAdmin, isSuperAdmin, isCustomAdmin, isShowOnlyListing, hasPermission } = ctx;

  if (module === LAYOUT_MODULES.CRM && crmSection) {
    return buildCrmSectionHeaderTabs(crmSection, ctx);
  }

  if (module === LAYOUT_MODULES.AGENTS) {
    const tabs = [
      {
        id: 'list',
        label: 'Agents List',
        type: 'route',
        path: '/users',
        matchPaths: ['/users', '/view-profile', '/team-tree', '/invited'],
      },
    ];
    if (!hasPermission || hasPermission('users-create')) {
      tabs.push({
        id: 'create',
        label: 'Add Agent',
        type: 'route',
        path: '/add-user',
        matchPaths: ['/add-user'],
      });
    }
    return tabs;
  }

  if (module === LAYOUT_MODULES.SETTINGS) {
    const tabs = [];

    if (isAdmin) {
      tabs.push({
        id: 'system',
        label: 'System & Logs',
        type: 'route',
        path: '/system-overview',
        matchPaths: ['/system-overview', '/logs', '/attendance-monthly-reports', '/project-map', '/import-pitrix', '/area-coordinates'],
      });
    }

    if (!hasPermission || hasPermission('roles-list')) {
      tabs.push({
        id: 'roles',
        label: 'Roles & Access',
        type: 'route',
        path: '/roles',
        matchPaths: ['/roles', '/add-role', '/assign-role', '/role-access'],
      });
    }

    const insightPaths = [
      '/lead-reports',
      '/sales-intelligence',
      '/investment-analysis',
      '/settings/city-investments',
      '/settings/roi-calculator',
      '/settings/lead-scoring',
      '/settings/kanban',
      '/settings/stage-visibility',
    ];
    if (isAdmin || isSuperAdmin) {
      tabs.push({
        id: 'insights',
        label: 'Insights',
        type: 'route',
        path: isSuperAdmin ? '/lead-reports' : '/settings/lead-scoring',
        matchPaths: insightPaths,
      });
    }

    if (isCustomAdmin) {
      tabs.push({
        id: 'chat',
        label: 'Chat',
        type: 'route',
        path: '/admin/chat',
        matchPaths: ['/admin/chat'],
      });
    }

    tabs.push({
      id: 'suggestions',
      label: 'Suggestions',
      type: 'route',
      path: '/suggestion',
      matchPaths: ['/suggestion'],
    });

    return tabs;
  }

  return [];
}

export function buildCrmSectionHeaderTabs(section, ctx = {}) {
  const { isAdmin, isShowOnlyListing, hasPermission } = ctx;

  if (section === CRM_SECTIONS.LEAD) {
    return [
      { id: 'leads', label: 'Lead', type: 'event' },
      { id: 'lead-pool', label: 'Lead Pool', type: 'event' },
    ];
  }

  if (section === CRM_SECTIONS.DEAL) {
    return [
      { id: 'primary', label: 'Primary', type: 'deal-type' },
      { id: 'secondary', label: 'Secondary', type: 'deal-type' },
      { id: 'rental', label: 'Rental', type: 'deal-type' },
    ];
  }

  if (section === CRM_SECTIONS.LISTINGS && !isShowOnlyListing) {
    const mainPath =
      isAdmin || (hasPermission && hasPermission('listings-list'))
        ? '/alllisting'
        : '/my-listing';
    return [
      {
        id: 'listings',
        label: 'Listings',
        type: 'route',
        path: mainPath,
        matchPaths: ALL_LISTINGS_MATCH_PATHS,
      },
    ];
  }

  return [];
}

/**
 * Top header shortcut row (CRM, HRM) — dashboard / home layout only.
 */
export function buildTopModuleNav(ctx = {}) {
  const {
    isAdmin,
    isSuperAdmin,
    userId,
  } = ctx;

  const items = [];

  if (isAdmin) {
    items.push({
      id: 'crm',
      label: 'CRM',
      path: getCrmEntryPath(ctx),
      matchModules: [LAYOUT_MODULES.CRM],
    });
  }

  if (isSuperAdmin || userId === 186) {
    items.push({
      id: 'hr',
      label: 'HRM',
      path: '/hr',
      matchModules: [LAYOUT_MODULES.HR],
    });
  }

  return items;
}

export function isTopModuleNavActive(currentPath, activeModule, item) {
  if (isDashboardRoute(currentPath) || activeModule === LAYOUT_MODULES.DASHBOARD) {
    return false;
  }
  if (item.matchModules?.length && item.matchModules.includes(activeModule)) {
    return true;
  }
  const paths = item.matchPaths || (item.path ? [item.path] : []);
  return paths.some((p) => isPathActive(currentPath, p));
}

/**
 * Merge property/inventory items into the Listings sidebar dropdown (ordered sections).
 */
export function buildListingsSidebarSections(sections) {
  const order = [
    { key: 'listings', title: 'Listings' },
    { key: 'projects', title: 'Projects' },
    { key: 'requests', title: 'Requests' },
    { key: 'developers', title: 'Developers' },
    { key: 'owners', title: 'Owners' },
    { key: 'property_types', title: 'Property Types' },
    { key: 'features', title: 'Features' },
    { key: 'unit_views', title: 'Unit Views' },
    { key: 'layout_types', title: 'Layout Types' },
    { key: 'areas', title: 'Areas' },
  ];

  return order
    .map(({ key, title }) => {
      const items = sections[key] || [];
      if (!items.length) return null;
      return { key, title, items };
    })
    .filter(Boolean);
}

export function buildSettingsSidebarSections(sections) {
  const order = [
    { key: 'system', title: 'System' },
    { key: 'roles', title: 'Roles & Access' },
    { key: 'tools', title: 'Investment Tools' },
    { key: 'insights', title: 'Insights' },
    { key: 'chat', title: 'Chat' },
    { key: 'other', title: 'More' },
  ];

  return order
    .map(({ key, title }) => {
      const items = sections[key] || [];
      if (!items.length) return null;
      return { key, title, items };
    })
    .filter(Boolean);
}

export function useLayoutNavigation(route, ctxRef) {
  const activeModule = computed(() => resolveActiveModule(route.path));

  const headerTabs = computed(() =>
    buildHeaderTabs(activeModule.value, ctxRef?.value ?? ctxRef ?? {}),
  );

  return {
    activeModule,
    headerTabs,
    LAYOUT_MODULES,
  };
}
