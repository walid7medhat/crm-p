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

const CRM_PREFIXES = ['/kanban', '/kanban_deal'];

const HR_PREFIXES = ['/hr'];

const AGENTS_PREFIXES = ['/users', '/add-user', '/team-tree', '/view-profile', '/invited'];

export const LISTINGS_OVERVIEW_PATH = '/listings/overview';

const LISTINGS_PREFIXES = [
  LISTINGS_OVERVIEW_PATH,
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

export function resolveActiveModule(path) {
  if (path === '/' || path === '/home' || path === '') return LAYOUT_MODULES.DASHBOARD;
  if (pathMatches(path, CRM_PREFIXES)) return LAYOUT_MODULES.CRM;
  if (pathMatches(path, HR_PREFIXES)) return LAYOUT_MODULES.HR;
  if (pathMatches(path, AGENTS_PREFIXES)) return LAYOUT_MODULES.AGENTS;
  if (pathMatches(path, SETTINGS_PREFIXES)) return LAYOUT_MODULES.SETTINGS;
  if (pathMatches(path, LISTINGS_PREFIXES)) return LAYOUT_MODULES.LISTINGS;
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
 * Header sub-navigation tabs per active sidebar module.
 * `ctx` supplies permission flags from the layout shell.
 */
export function buildHeaderTabs(module, ctx = {}) {
  const { isAdmin, isSuperAdmin, isCustomAdmin, isShowOnlyListing, hasPermission } = ctx;

  if (module === LAYOUT_MODULES.CRM) {
    return [
      { id: 'leads', label: 'Leads', type: 'event' },
      { id: 'lead-pool', label: 'Lead Pool', type: 'event' },
      { id: 'deals', label: 'Deals', type: 'event' },
    ];
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

  if (module === LAYOUT_MODULES.LISTINGS && !isShowOnlyListing) {
    const mainPath =
      isAdmin || (hasPermission && hasPermission('listings-list'))
        ? '/alllisting'
        : '/my-listing';
    const tabs = [
      {
        id: 'overview',
        label: 'Overview',
        type: 'route',
        path: LISTINGS_OVERVIEW_PATH,
        matchPaths: [LISTINGS_OVERVIEW_PATH],
      },
      {
        id: 'main',
        label: 'Main Listings',
        type: 'route',
        path: mainPath,
        matchPaths: ['/alllisting', '/my-listing', '/archive', '/property-details', '/properties', '/properties-map'],
      },
    ];
    if (!hasPermission || hasPermission('listings-create')) {
      tabs.push({
        id: 'create',
        label: 'Create Listing',
        type: 'route',
        path: '/property-form',
        matchPaths: ['/property-form', '/properties'],
      });
    }
    tabs.push({
      id: 'notify',
      label: 'Notify Me',
      type: 'route',
      path: '/notify-me',
      matchPaths: ['/notify-me'],
    });
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

/**
 * Top header shortcut row (CRM, HRM, Listings) — dashboard / home layout.
 */
export function buildTopModuleNav(ctx = {}) {
  const {
    isAdmin,
    isSuperAdmin,
    isShowOnlyListing,
    hasPermission,
    userId,
    canAccessListings,
  } = ctx;

  const items = [];

  if (isAdmin) {
    items.push({
      id: 'crm',
      label: 'CRM',
      path: '/kanban',
      matchModules: [LAYOUT_MODULES.DASHBOARD, LAYOUT_MODULES.CRM],
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

  if (canAccessListings && !isShowOnlyListing) {
    const listingsPath =
      isAdmin || (hasPermission && hasPermission('listings-list'))
        ? LISTINGS_OVERVIEW_PATH
        : '/my-listing';
    items.push({
      id: 'listings',
      label: 'Listings',
      path: listingsPath,
      matchModules: [LAYOUT_MODULES.LISTINGS],
    });
  }

  return items;
}

export function isTopModuleNavActive(currentPath, activeModule, item) {
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
