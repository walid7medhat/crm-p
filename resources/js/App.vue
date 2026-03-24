<template>
  <div id="app">
    <Header v-if="showLayout" />
    <main :class="showLayout ? 'dashboard-main' : ''">
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
        const roles = Array.isArray(u?.roles) ? u.roles : []
        return roles.includes('super_admin') || roles.includes('admin')
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
}

#app main.dashboard-main {
  display: flex !important;
  flex-direction: column !important;
  flex-wrap: nowrap !important;
  position: relative;
  box-sizing: border-box;
  min-height: 100vh;
  padding-top: 0 !important;
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
  box-sizing: border-box;
  padding-top: 0 !important;
}
</style>