<template>
  <Teleport to="body">
    <button
      v-show="visible"
      type="button"
      class="chat-floating-btn"
      :class="{
        'chat-floating-btn--unread': unreadCount > 0,
        'chat-floating-btn--attention': attention,
      }"
      :aria-label="ariaLabel"
      :title="ariaLabel"
      @click="openChat"
    >
      <i class="ri-chat-3-fill" aria-hidden="true"></i>
      <span
        v-if="unreadCount > 0"
        class="chat-floating-badge"
        :class="{ 'chat-floating-badge--pulse': attention }"
        aria-hidden="true"
      >
        {{ badgeText }}
      </span>
    </button>
    <span class="chat-live-region" aria-live="polite">{{ liveStatus }}</span>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import api from '@/plugins/axios'
import Swal from 'sweetalert2'
import {
  messageAlertKey,
  isConversationOpen,
  isActivelyViewingConversation,
  claimIncomingSoundAcrossTabs,
  playIncomingChatSound,
  releaseIncomingChatSound,
} from './incomingChatAlert'

const props = defineProps({
  show: { type: Boolean, default: true },
  chatOpen: { type: Boolean, default: false },
})

const emit = defineEmits(['open'])

const visible = ref(false)
const unreadCount = ref(0)
const attention = ref(false)
const liveStatus = ref('')
const echoChannel = ref(null)
const deliveredIds = new Set()
let incomingHandler = null
let badgePulseTimer = null
let attentionFrame = null
let lastToastAt = 0
let originalTitle = null

const badgeText = computed(() => {
  const n = unreadCount.value
  return n > 99 ? '99+' : String(n)
})

const ariaLabel = computed(() => {
  const n = unreadCount.value
  if (n <= 0) return 'Open chat'
  if (n === 1) return 'Open chat, 1 unread message'
  const shown = n > 99 ? '99+' : String(n)
  return `Open chat, ${shown} unread messages`
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

function rememberDelivery(key) {
  if (!key || deliveredIds.has(key)) return false
  deliveredIds.add(key)
  if (deliveredIds.size > 200) {
    const first = deliveredIds.values().next().value
    deliveredIds.delete(first)
  }
  return true
}

function triggerAttention() {
  attention.value = false
  if (badgePulseTimer) clearTimeout(badgePulseTimer)
  if (attentionFrame) cancelAnimationFrame(attentionFrame)
  attentionFrame = requestAnimationFrame(() => {
    attentionFrame = null
    attention.value = true
    badgePulseTimer = setTimeout(() => {
      attention.value = false
      badgePulseTimer = null
    }, 3800)
  })
}

function announceToAssistiveTech(senderName) {
  const text = senderName ? `New chat message from ${senderName}` : 'New chat message'
  liveStatus.value = ''
  nextTick(() => {
    liveStatus.value = text
  })
}

function markBackgroundTab(count) {
  if (typeof document === 'undefined' || document.visibilityState !== 'hidden') return
  if (originalTitle == null) originalTitle = document.title
  if (!count) {
    document.title = 'New message'
    return
  }
  const n = count > 99 ? '99+' : String(count)
  document.title = `(${n}) New message`
}

function restoreTabTitle() {
  if (originalTitle == null || typeof document === 'undefined') return
  document.title = originalTitle
  originalTitle = null
}

function onVisibilityChange() {
  if (document.visibilityState === 'visible') restoreTabTitle()
}

function subscribeToNewMessages() {
  if (!window.Echo || echoChannel.value) return
  try {
    const userStr = localStorage.getItem('user')
    if (!userStr) return
    const user = JSON.parse(userStr)
    if (!user?.id) return
    const channel = window.Echo.private(`user.${user.id}`)
    incomingHandler = (e) => handleIncomingMessage(e, user.id)
    channel.listen('.message.sent', incomingHandler)
    echoChannel.value = channel
  } catch (_) {}
}

function handleIncomingMessage(eventPayload, currentUserId) {
  if (!eventPayload) return
  if (Number(eventPayload.sender_id) === Number(currentUserId)) return
  if (eventPayload.read_at) return

  const key = messageAlertKey(eventPayload)
  if (!rememberDelivery(key)) return

  const conversationOpen = isConversationOpen(eventPayload.conversation_id)
  const viewingThisThread = isActivelyViewingConversation(eventPayload.conversation_id)
  // An open thread is marked read by the existing chat window, so the badge
  // stays tied to messages the user has not actually opened.
  if (!conversationOpen) {
    unreadCount.value = Math.max(0, unreadCount.value) + 1
    triggerAttention()
    announceToAssistiveTech(eventPayload?.sender?.name)
  }

  notifyIncomingMessage(eventPayload, key, viewingThisThread)
}

function notifyIncomingMessage(eventPayload, key, viewingThisThread) {
  if (viewingThisThread) return

  const senderName = eventPayload?.sender?.name || 'New message'
  const text = (eventPayload?.message || '').trim()
  const tabVisible = typeof document === 'undefined' || document.visibilityState === 'visible'

  if (tabVisible && Date.now() - lastToastAt > 2500) {
    lastToastAt = Date.now()
    try {
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'info',
        title: senderName,
        text: text || 'New chat message',
        showConfirmButton: false,
        timer: 3200,
        timerProgressBar: true,
        backdrop: false,
      })
    } catch (_) {}
  }

  if (!tabVisible) {
    markBackgroundTab(unreadCount.value)
    if (typeof window !== 'undefined' && 'Notification' in window && Notification.permission === 'granted') {
      try {
        new Notification(senderName, {
          body: text || 'You have a new chat message.',
          tag: key,
          silent: true,
        })
      } catch (_) {}
    }
  }

  claimIncomingSoundAcrossTabs(key).then((claimed) => {
    if (claimed) playIncomingChatSound()
  }).catch(() => {})
}

function unsubscribeEcho() {
  if (echoChannel.value && incomingHandler) {
    try {
      echoChannel.value.stopListening('.message.sent', incomingHandler)
    } catch (_) {}
  }
  incomingHandler = null
  echoChannel.value = null
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
  document.addEventListener('visibilitychange', onVisibilityChange)
  if (visible.value) {
    fetchUnreadCount()
    subscribeToNewMessages()
    pollInterval = setInterval(fetchUnreadCount, 60000)
  }
})

onUnmounted(() => {
  unsubscribeEcho()
  document.removeEventListener('visibilitychange', onVisibilityChange)
  restoreTabTitle()
  if (pollInterval) clearInterval(pollInterval)
  releaseIncomingChatSound()
  if (attentionFrame) cancelAnimationFrame(attentionFrame)
  attentionFrame = null
  if (badgePulseTimer) clearTimeout(badgePulseTimer)
  badgePulseTimer = null
  attention.value = false
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
.chat-floating-btn--unread {
  box-shadow:
    0 4px 16px rgba(13, 110, 253, 0.4),
    0 0 0 3px rgba(220, 53, 69, 0.55);
}
.chat-floating-btn--attention {
  animation: chat-btn-glow 1.15s ease-in-out 3;
  transition: transform 0.2s;
}
.chat-floating-btn--attention::before {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: 50%;
  pointer-events: none;
  animation: chat-btn-ring 1.15s ease-out 3;
}
.chat-floating-btn:hover {
  transform: scale(1.05);
  box-shadow: 0 6px 20px rgba(13, 110, 253, 0.5);
}
.chat-floating-btn--unread:hover {
  box-shadow:
    0 6px 20px rgba(13, 110, 253, 0.5),
    0 0 0 3px rgba(220, 53, 69, 0.6);
}
.chat-floating-btn:active {
  transform: scale(0.98);
}
.chat-floating-btn i {
  font-size: 26px;
  position: relative;
  z-index: 1;
}

@media (max-width: 768px) {
  .chat-floating-btn {
    bottom: calc(76px + env(safe-area-inset-bottom, 0px));
    right: 16px;
    width: 52px;
    height: 52px;
    z-index: 10050;
  }

  .chat-floating-btn i {
    font-size: 23px;
  }

  .chat-floating-btn--attention::before {
    animation-name: chat-btn-ring-mobile;
  }
}
.chat-floating-badge {
  position: absolute;
  top: -3px;
  right: -3px;
  min-width: 22px;
  height: 22px;
  padding: 0 5px;
  border-radius: 11px;
  background: #dc3545;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  line-height: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid #fff;
  box-shadow: 0 2px 6px rgba(220, 53, 69, 0.45);
  z-index: 2;
  pointer-events: none;
}

.chat-floating-badge--pulse {
  animation: chat-badge-pulse 0.7s ease-in-out 3;
}

.chat-live-region {
  position: fixed;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

@keyframes chat-badge-pulse {
  0% { transform: scale(1); }
  40% { transform: scale(1.16); }
  100% { transform: scale(1); }
}

@keyframes chat-btn-glow {
  0%, 100% {
    box-shadow:
      0 4px 16px rgba(13, 110, 253, 0.4),
      0 0 0 3px rgba(220, 53, 69, 0.4);
  }
  50% {
    box-shadow:
      0 8px 24px rgba(220, 53, 69, 0.45),
      0 0 0 7px rgba(220, 53, 69, 0.2);
  }
}

@keyframes chat-btn-ring {
  0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.45); opacity: 0.85; }
  100% { box-shadow: 0 0 0 14px rgba(220, 53, 69, 0); opacity: 0; }
}

@keyframes chat-btn-ring-mobile {
  0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); opacity: 0.7; }
  100% { box-shadow: 0 0 0 8px rgba(220, 53, 69, 0); opacity: 0; }
}

@media (max-width: 768px) {
  .chat-floating-badge {
    top: -2px;
    right: -2px;
    min-width: 18px;
    height: 18px;
    padding: 0 4px;
    font-size: 10px;
    border-width: 1.5px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .chat-floating-btn--attention,
  .chat-floating-btn--attention::before,
  .chat-floating-badge--pulse {
    animation: none;
  }
}
</style>
