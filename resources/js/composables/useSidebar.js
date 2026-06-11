import { ref, onMounted } from 'vue'

const isSidebarActive = ref(false)

/** Remove sidebar layout classes so auth pages are full-width after logout. */
export function resetSidebarLayout() {
  isSidebarActive.value = false
  localStorage.removeItem('sidebarActive')

  document.querySelectorAll('.sidebar').forEach((el) => {
    el.classList.remove('active', 'sidebar-open')
  })
  document.querySelectorAll('.dashboard-main').forEach((el) => {
    el.classList.remove('active')
  })
  document.body.classList.remove('mobile-nav-open')
}

function applySidebarLayoutFromStorage() {
  const token = localStorage.getItem('token')
  if (!token) {
    resetSidebarLayout()
    return
  }

  const savedState = localStorage.getItem('sidebarActive')
  if (savedState === 'true') {
    isSidebarActive.value = true
    document.querySelector('.sidebar')?.classList.add('active')
    document.querySelector('.dashboard-main')?.classList.add('active')
  } else {
    isSidebarActive.value = false
    document.querySelector('.sidebar')?.classList.remove('active')
    document.querySelector('.dashboard-main')?.classList.remove('active')
  }
}

export function useSidebar() {
  function toggleSidebarDesktop() {
    isSidebarActive.value = !isSidebarActive.value
    document.querySelector('.sidebar')?.classList.toggle('active')
    document.querySelector('.dashboard-main')?.classList.toggle('active')
    localStorage.setItem('sidebarActive', isSidebarActive.value.toString())
  }

  /** Ensure the sidebar is in its expanded (full-width) state — `active` class means collapsed. */
  function expandSidebarDesktop() {
    if (!isSidebarActive.value) return
    isSidebarActive.value = false
    document.querySelector('.sidebar')?.classList.remove('active')
    document.querySelector('.dashboard-main')?.classList.remove('active')
    localStorage.setItem('sidebarActive', 'false')
  }

  onMounted(() => {
    applySidebarLayoutFromStorage()
  })

  return {
    isSidebarActive,
    toggleSidebarDesktop,
    expandSidebarDesktop,
    resetSidebarLayout,
  }
}
