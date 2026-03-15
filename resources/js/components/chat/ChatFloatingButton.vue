<template>
  <Teleport to="body">
    <button
      v-show="visible"
      type="button"
      class="chat-floating-btn"
      aria-label="Open chat"
      @click="openChat"
    >
      <i class="ri-chat-3-fill"></i>
      <span v-if="unreadCount > 0" class="chat-floating-badge">{{ badgeText }}</span>
    </button>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import api from '@/plugins/axios'

const props = defineProps({
  show: { type: Boolean, default: true },
  chatOpen: { type: Boolean, default: false },
})

const emit = defineEmits(['open'])

const visible = ref(false)
const unreadCount = ref(0)
const echoChannel = ref(null)

const badgeText = computed(() => {
  const n = unreadCount.value
  return n > 99 ? '99+' : String(n)
})

async function fetchUnreadCount() {
  try {
    const res = await api.get('/chat/unread-count')
    if (res.data?.success && typeof res.data.count === 'number') {
      unreadCount.value = res.data.count
    }
  } catch (_) {
    unreadCount.value = 0
  }
}

function subscribeToNewMessages() {
  if (!window.Echo) return
  try {
    const userStr = localStorage.getItem('user')
    if (!userStr) return
    const user = JSON.parse(userStr)
    if (!user?.id) return
    const channel = window.Echo.private(`user.${user.id}`)
    channel.listen('.message.sent', (e) => {
      if (e.sender_id !== user.id) {
        unreadCount.value = Math.max(0, unreadCount.value) + 1
      }
    })
    echoChannel.value = channel
  } catch (_) {}
}

function unsubscribeEcho() {
  if (echoChannel.value) {
    try {
      echoChannel.value.stopListening('.message.sent')
    } catch (_) {}
    echoChannel.value = null
  }
}

function openChat() {
  emit('open')
}

function checkVisible() {
  visible.value = !!localStorage.getItem('token') && props.show
}

let pollInterval = null

onMounted(() => {
  checkVisible()
  if (visible.value) {
    fetchUnreadCount()
    subscribeToNewMessages()
    pollInterval = setInterval(fetchUnreadCount, 60000)
  }
})

onUnmounted(() => {
  unsubscribeEcho()
  if (pollInterval) clearInterval(pollInterval)
})

watch(() => props.show, (v) => {
  if (v && localStorage.getItem('token')) {
    visible.value = true
    fetchUnreadCount()
    if (!echoChannel.value) subscribeToNewMessages()
    if (!pollInterval) pollInterval = setInterval(fetchUnreadCount, 60000)
  } else {
    visible.value = false
  }
})

watch(() => props.chatOpen, (isOpen) => {
  if (!isOpen && visible.value) fetchUnreadCount()
})
</script>

<style scoped>
.chat-floating-btn {
  position: fixed;
  bottom: 24px;
  right: 24px;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
  color: #fff;
  border: none;
  box-shadow: 0 4px 16px rgba(13, 110, 253, 0.4);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 99998;
  transition: transform 0.2s, box-shadow 0.2s;
}
.chat-floating-btn:hover {
  transform: scale(1.05);
  box-shadow: 0 6px 20px rgba(13, 110, 253, 0.5);
}
.chat-floating-btn:active {
  transform: scale(0.98);
}
.chat-floating-btn i {
  font-size: 26px;
}
.chat-floating-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  border-radius: 10px;
  background: #dc3545;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 6px rgba(220, 53, 69, 0.4);
}
</style>
