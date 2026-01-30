import { ref, onMounted } from 'vue'

const isSidebarActive = ref(false)

export function useSidebar() {
  function toggleSidebarDesktop() {
    isSidebarActive.value = !isSidebarActive.value
    document.querySelector('.sidebar')?.classList.toggle('active')
    document.querySelector('.dashboard-main')?.classList.toggle('active')
    // Persist state to localStorage
    localStorage.setItem('sidebarActive', isSidebarActive.value.toString())
  }

  // Restore sidebar state on mount
  onMounted(() => {
    const savedState = localStorage.getItem('sidebarActive')
    if (savedState === 'true') {
      isSidebarActive.value = true
      document.querySelector('.sidebar')?.classList.add('active')
      document.querySelector('.dashboard-main')?.classList.add('active')
    }
  })

  return { 
    isSidebarActive, 
    toggleSidebarDesktop 
  }
}
