<template>
  <div id="app">
    <AppLoader :show="isAppLoading" @hidden="onLoaderHidden" />
    <Header v-if="showLayout && !isAppLoading" />
    <main :class="showLayout ? 'dashboard-main' : 'auth-page-main'">
      <Navbar v-if="showLayout && !isAppLoading" />
      <div
        :class="[
          showLayout ? 'dashboard-main-router' : '',
          { 'dashboard-main-router--loading': isAppLoading && showLayout },
          { 'dashboard-main-router--home': showLayout && isDashboardHome },
        ]"
      >
        <router-view />
      </div>
      <Footer v-if="showLayout" />
    </main>
    <ChatFloatingButton
      v-if="showLayout && canUseChat"
      :show="!chatOpen"
      :chat-open="chatOpen"
      @open="openChatInbox"
    />
    <ChatPopup
      v-if="canUseChat"
      :show="chatOpen"
      :start-with-agent="chatAgent"
      :start-with-listing-id="chatListingId"
      :start-with-context="chatContext"
      @close="closeChat"
    />
    <ViewLeadModal
      v-if="showLayout"
      v-model="showLeadViewModal"
      :leadId="leadViewModalId"
      @lead-updated="notifyLeadViewUpdated"
    />
  </div>
</template>

<script>
import { computed, ref, onMounted, onUnmounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import Header from './components/layout/header/index.vue'
import Navbar from './components/layout/navbar/index.vue'
import Footer from './components/layout/footer/index.vue'
import ChatPopup from './components/chat/ChatPopup.vue'
import ChatFloatingButton from './components/chat/ChatFloatingButton.vue'
import AppLoader from './components/layout/AppLoader.vue'
import ViewLeadModal from './components/kanban/viewLead/ViewLeadModal.vue'
import { useAppLoader } from './composables/useAppLoader.js'
import { resetSidebarLayout } from './composables/useSidebar.js'
import { useBackground } from './composables/useBackground.js'
import { useLeadViewModal } from './composables/useLeadViewModal.js'

export default {
  name: 'App',
  components: {
    AppLoader,
    Header,
    Navbar,
    Footer,
    ChatPopup,
    ChatFloatingButton,
    ViewLeadModal,
  },
  setup() {
    const route = useRoute()
    const { isAppLoading, onLoaderHidden } = useAppLoader()
    const { loadFromCache: loadBackgroundFromCache } = useBackground()
    const {
      showLeadViewModal,
      leadViewModalId,
      openLeadView,
      notifyLeadViewUpdated,
    } = useLeadViewModal()
    const showLayout = computed(() => route.meta.layout !== false)
    const isDashboardHome = computed(() => !!route.meta?.dashboardHome)
    const chatOpen = ref(false)
    const chatAgent = ref(null)
    const chatListingId = ref(null)
    const chatContext = ref(null)
    const canUseChat = computed(() => {
      try {
        const raw = localStorage.getItem('user')
        if (!raw) return false
        const u = JSON.parse(raw)
        console.log("is_listing_team"+ u?.is_listing_team);
        const roles = Array.isArray(u?.roles) ? u.roles : []
          return true
        // return roles.includes('super_admin') || roles.includes('admin') || u?.is_listing_team
      
      } catch {
        return false
      }
    })

    function openPropertyChat(agent, listingId, context = null) {
      if (!canUseChat.value) return
      chatAgent.value = agent
      chatListingId.value = listingId != null ? Number(listingId) : null
      chatContext.value = context || null
      chatOpen.value = true
    }

    function openChatInbox() {
      if (!canUseChat.value) return
      chatAgent.value = null
      chatListingId.value = null
      chatContext.value = null
      chatOpen.value = true
    }

    function closeChat() {
      chatOpen.value = false
    }

    function syncVideoBgClass() {
      document.body.classList.toggle('app-has-video-bg', showLayout.value)
    }

    onMounted(() => {
      window.__openPropertyChat = openPropertyChat
      window.__openLeadView = openLeadView
      loadBackgroundFromCache()
      syncVideoBgClass()
      if (!showLayout.value) {
        resetSidebarLayout()
      }
    })
    onUnmounted(() => {
      window.__openPropertyChat = null
      window.__openLeadView = null
      document.body.classList.remove('app-has-video-bg')
    })

    watch(showLayout, (visible) => {
      syncVideoBgClass()
      if (!visible) {
        resetSidebarLayout()
      }
    })

    return {
      isAppLoading,
      onLoaderHidden,
      showLayout,
      isDashboardHome,
      chatOpen,
      chatAgent,
      chatListingId,
      chatContext,
      canUseChat,
      openChatInbox,
      closeChat,
      showLeadViewModal,
      leadViewModalId,
      notifyLeadViewUpdated,
    }
  }
}
</script>

<!-- Global layout: keeps every routed page below the glass top navbar (sidebar is separate). -->
<style>
#app {
  --app-topbar-height: 2.75rem;
  --app-header-below-gap: 0.5rem;
  overflow-x: hidden;
  max-width: 100vw;
  min-height: 100vh;
  min-height: 100dvh;
}

/* Auth pages: fill viewport and center content vertically */
#app main.auth-page-main {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  min-height: 100dvh;
  height: 100vh;
  height: 100dvh;
  padding: 0;
  margin: 0;
  overflow: hidden;
}

#app main.auth-page-main > * {
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  min-height: 0;
  width: 100%;
  height: 100%;
}

html:has(#app main.auth-page-main),
html:has(#app main.auth-page-main) body {
  overflow: hidden;
  height: 100%;
  max-height: 100dvh;
}

/* Auth screens: never show app sidebar / mobile dock (e.g. after logout) */
html:has(#app main.auth-page-main) .sidebar,
html:has(#app main.auth-page-main) .mobile-sidebar-dock,
html:has(#app main.auth-page-main) .mobile-core-dock,
html:has(#app main.auth-page-main) .mobile-dock-sheet-overlay,
html:has(#app main.auth-page-main) .mobile-sidebar-overlay {
  display: none !important;
  visibility: hidden !important;
  pointer-events: none !important;
}

html:has(#app main.auth-page-main) #app main.dashboard-main,
html:has(#app main.auth-page-main) .dashboard-main.active {
  margin-inline-start: 0 !important;
}

#app main.dashboard-main {
  display: flex !important;
  flex-direction: column !important;
  flex-wrap: nowrap !important;
  position: relative;
  box-sizing: border-box;
  min-height: 100vh;
  padding-top: 0 !important;
  overflow-x: hidden;
  max-width: 100vw;
}

#app main.dashboard-main > .dashboard-main-router {
  flex: 1 1 auto;
  min-height: 0;
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  padding-top: var(--app-header-below-gap, 0.5rem) !important;
  overflow-x: hidden;
  overflow-y: visible;
  display: flex;
  flex-direction: column;
}

#app main.dashboard-main > .dashboard-main-router:has(.property-form-page),
#app main.dashboard-main > .dashboard-main-router:has(.property-show-page),
#app main.dashboard-main > .dashboard-main-router:has(.property-show-inner),
#app main.dashboard-main > .dashboard-main-router:has(.project-show-page) {
  flex: 0 0 auto;
  display: block;
  min-height: auto;
  height: auto;
  max-height: none;
  overflow: visible;
}

#app main.dashboard-main:has(.project-show-page),
#app main.dashboard-main:has(.property-show-page),
#app main.dashboard-main:has(.property-show-inner),
#app main.dashboard-main:has(.property-form-page) {
  height: auto;
  max-height: none;
  min-height: 100dvh;
  overflow-x: hidden;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
}

/* Route mounts behind loader; keep layout stable without flashing old page */
#app main.dashboard-main > .dashboard-main-router--loading {
  visibility: hidden;
  pointer-events: none;
}

body.mobile-nav-open {
  overflow: hidden;
}

@media (max-width: 768px) {
  #app {
    --app-topbar-height: 3.25rem;
    --app-header-below-gap: 0.5rem;
  }

  #app main.dashboard-main > .dashboard-main-router {
    padding-bottom: calc(88px + env(safe-area-inset-bottom, 0px));
    overflow-x: hidden;
  }
}
</style>