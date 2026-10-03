import { ref } from 'vue';

export const MOBILE_LAYOUT_MAX_WIDTH = 768;
/** Bottom navigation only. Does not switch page layouts into the phone shell. */
export const COMPACT_NAV_MAX_WIDTH = 1024;

const isMobileViewport = ref(false);
const isCompactNav = ref(false);
const isMobileMenuOpen = ref(false);
let listenersAttached = false;

export function isMobileLayout() {
  if (typeof window === 'undefined') return false;
  return window.matchMedia(`(max-width: ${MOBILE_LAYOUT_MAX_WIDTH}px)`).matches;
}

export function isCompactNavLayout() {
  if (typeof window === 'undefined') return false;
  return window.matchMedia(`(max-width: ${COMPACT_NAV_MAX_WIDTH}px)`).matches;
}

export function syncMobileViewport() {
  if (typeof window === 'undefined') return;
  const mobile = isMobileLayout();
  const compact = isCompactNavLayout();
  isMobileViewport.value = mobile;
  isCompactNav.value = compact;
  if (!compact) {
    closeMobileMenu();
  }
}

export function openMobileMenu() {
  const mobile = isMobileLayout();
  const compact = isCompactNavLayout();
  isMobileViewport.value = mobile;
  isCompactNav.value = compact;
  if (!compact) return;
  isMobileMenuOpen.value = true;
  document.body.classList.add('overlay-active', 'mobile-nav-open');
  const sidebar = document.querySelector('aside.sidebar');
  sidebar?.classList.add('sidebar-open');
  if (mobile) {
    sidebar?.classList.add('sidebar--mobile-drawer');
  }
}

export function closeMobileMenu() {
  isMobileMenuOpen.value = false;
  document.body.classList.remove('overlay-active', 'mobile-nav-open');
  const sidebar = document.querySelector('aside.sidebar');
  sidebar?.classList.remove('sidebar-open');
  if (!isMobileLayout()) {
    sidebar?.classList.remove('sidebar--mobile-drawer');
  }
}

export function toggleMobileMenu() {
  if (isMobileMenuOpen.value) {
    closeMobileMenu();
  } else {
    openMobileMenu();
  }
}

function attachViewportListeners() {
  if (listenersAttached || typeof window === 'undefined') return;
  listenersAttached = true;
  syncMobileViewport();
  window.addEventListener('resize', syncMobileViewport, { passive: true });
}

export function useMobileNavigation() {
  attachViewportListeners();
  return {
    isMobileViewport,
    isCompactNav,
    isMobileMenuOpen,
    openMobileMenu,
    closeMobileMenu,
    toggleMobileMenu,
    syncMobileViewport,
    isMobileLayout,
    isCompactNavLayout,
  };
}
