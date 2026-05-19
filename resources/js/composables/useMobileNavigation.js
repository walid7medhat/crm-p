import { ref } from 'vue';

export const MOBILE_LAYOUT_MAX_WIDTH = 768;

const isMobileViewport = ref(false);
const isMobileMenuOpen = ref(false);
let listenersAttached = false;

export function syncMobileViewport() {
  if (typeof window === 'undefined') return;
  const mobile = window.matchMedia(`(max-width: ${MOBILE_LAYOUT_MAX_WIDTH}px)`).matches;
  isMobileViewport.value = mobile;
  if (!mobile) {
    closeMobileMenu();
  }
}

export function openMobileMenu() {
  if (!isMobileViewport.value) return;
  isMobileMenuOpen.value = true;
  document.body.classList.add('overlay-active', 'mobile-nav-open');
  document.querySelector('aside.sidebar')?.classList.add('sidebar-open');
}

export function closeMobileMenu() {
  isMobileMenuOpen.value = false;
  document.body.classList.remove('overlay-active', 'mobile-nav-open');
  document.querySelector('aside.sidebar')?.classList.remove('sidebar-open');
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
    isMobileMenuOpen,
    openMobileMenu,
    closeMobileMenu,
    toggleMobileMenu,
    syncMobileViewport,
  };
}
