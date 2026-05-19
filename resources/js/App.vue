<template>
  <div id="app">
    <Header v-if="showLayout" />
    <main :class="showLayout ? 'dashboard-main' : 'auth-page-main'">
      <Navbar v-if="showLayout" />
      <!-- In-flow spacer: reserves height so pages never sit under the absolute navbar -->
      <div
        v-if="showLayout"
        class="app-navbar-spacer"
        aria-hidden="true"
      />
      <div :class="showLayout ? 'dashboard-main-router' : ''">
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
  </div>
</template>

<script>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import Header from './components/layout/header/index.vue'
import Navbar from './components/layout/navbar/index.vue'
import Footer from './components/layout/footer/index.vue'
import ChatPopup from './components/chat/ChatPopup.vue'
import ChatFloatingButton from './components/chat/ChatFloatingButton.vue'

export default {
  name: 'App',
  components: {
    Header,
    Navbar,
    Footer,
    ChatPopup,
    ChatFloatingButton,
  },
  setup() {
    const route = useRoute()
    const showLayout = computed(() => route.meta.layout !== false)
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

    onMounted(() => {
      window.__openPropertyChat = openPropertyChat
    })
    onUnmounted(() => {
      window.__openPropertyChat = null
    })

    return {
      showLayout,
      chatOpen,
      chatAgent,
      chatListingId,
      chatContext,
      canUseChat,
      openChatInbox,
      closeChat,
    }
  }
}
</script>

<!-- Global layout: keeps every routed page below the glass top navbar (sidebar is separate). -->
<style>
#app {
  --app-topbar-height: 3.25rem;
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

/* Must match .navbar-header height; reserves space so content never slides under the bar */
#app main.dashboard-main > .app-navbar-spacer {
  flex: 0 0 auto !important;
  width: 100% !important;
  height: calc(var(--app-topbar-height) + env(safe-area-inset-top, 0px)) !important;
  min-height: calc(var(--app-topbar-height) + env(safe-area-inset-top, 0px)) !important;
  pointer-events: none;
  box-sizing: border-box;
}

#app main.dashboard-main > .dashboard-main-router {
  flex: 1 1 auto;
  min-height: 0;
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  padding-top: 0 !important;
  overflow-x: hidden;
  /* Lets routed pages (e.g. property map) use height: 100% / flex to fill below navbar */
  display: flex;
  flex-direction: column;
}

body.mobile-nav-open {
  overflow: hidden;
  touch-action: none;
}

@media (max-width: 768px) {
  #app main.dashboard-main > .app-navbar-spacer {
    height: calc(var(--app-topbar-height, 5.5rem) + env(safe-area-inset-top, 0px)) !important;
    min-height: calc(var(--app-topbar-height, 5.5rem) + env(safe-area-inset-top, 0px)) !important;
  }

  #app main.dashboard-main > .dashboard-main-router {
    padding-bottom: calc(76px + env(safe-area-inset-bottom, 0px));
  }
}
</style>