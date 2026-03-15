<template>
  <div id="app">
    <Header v-if="showLayout" />
    <main :class="showLayout ? 'dashboard-main' : ''">
      <Navbar v-if="showLayout" />
      <router-view />
      <Footer v-if="showLayout" />
    </main>
    <ChatFloatingButton
      v-if="showLayout"
      :show="!chatOpen"
      :chat-open="chatOpen"
      @open="openChatInbox"
    />
    <ChatPopup
      :show="chatOpen"
      :start-with-agent="chatAgent"
      :start-with-listing-id="chatListingId"
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

    function openPropertyChat(agent, listingId) {
      chatAgent.value = agent
      chatListingId.value = listingId != null ? Number(listingId) : null
      chatOpen.value = true
    }

    function openChatInbox() {
      chatAgent.value = null
      chatListingId.value = null
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
      openChatInbox,
      closeChat,
    }
  }
}
</script>