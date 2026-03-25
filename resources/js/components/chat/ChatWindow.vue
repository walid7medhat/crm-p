<template>
  <div class="chat-window">
    <div v-if="otherUser" class="chat-window-header">
      <div class="header-user">
        <img v-if="otherUser.avatar" :src="otherUser.avatar" :alt="otherUser.name" class="header-avatar" />
        <div v-else class="header-avatar-placeholder">{{ (otherUser.name || '?')[0] }}</div>
        <div class="header-info">
          <span class="header-name">{{ otherUser.name }}</span>
          <span class="header-status">{{ onlineStatus }}</span>
        </div>
      </div>
    </div>
    <div v-if="propertyContext" class="property-context-card">
      <div class="property-context-title">{{ propertyContext.title }}</div>
      <div v-if="propertyContext.reference" class="property-context-meta">Reference: {{ propertyContext.reference }}</div>
      <div v-if="propertyContext.location" class="property-context-meta">{{ propertyContext.location }}</div>
      <div v-if="propertyContext.price" class="property-context-meta">Price: {{ propertyContext.price }}</div>
      <a v-if="propertyContext.link" :href="propertyContext.link" target="_blank" rel="noopener" class="property-context-link">
        Open Property
      </a>
    </div>
    <div ref="messagesContainerRef" class="chat-messages" @scroll="onScroll">
      <div v-if="loadingMore" class="loading-more">Loading...</div>
      <div v-for="msg in sortedMessages" :key="msg.id" class="message-row">
        <MessageBubble
          :message="msg"
          :sender="msg.sender"
          :is-mine="msg.sender_id === currentUserId"
          :current-user-avatar="currentUserAvatar"
          :current-user-name="currentUserName"
        />
      </div>
      <div v-if="typingUser" class="typing-indicator">
        <span class="typing-dots">{{ typingUser }} is typing...</span>
      </div>
      <div ref="bottomRef" class="messages-bottom"></div>
    </div>
    <div class="chat-input-area">
      <textarea
        v-model="draft"
        placeholder="Type a message..."
        rows="1"
        class="chat-input"
        @keydown.enter.exact.prevent="sendMessage"
        @input="onTyping"
      />
      <button type="button" class="send-btn" :disabled="sending || !draft.trim()" @click="sendMessage">
        <i class="ri-send-plane-fill"></i>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import MessageBubble from './MessageBubble.vue'
import api from '@/plugins/axios'

const props = defineProps({
  conversationId: { type: Number, default: null },
  messages: { type: Array, default: () => [] },
  otherUser: { type: Object, default: null },
  propertyContext: { type: Object, default: null },
  currentUserId: { type: Number, default: null },
  currentUserAvatar: { type: String, default: '' },
  currentUserName: { type: String, default: '' },
  onlineStatus: { type: String, default: '' },
  typingUser: { type: String, default: '' },
})

const emit = defineEmits(['send', 'load-more', 'typing'])

const draft = ref('')
const sending = ref(false)
const messagesContainerRef = ref(null)
const bottomRef = ref(null)
const loadingMore = ref(false)
const lastScrollHeight = ref(0)

const sortedMessages = computed(() => {
  const list = (props.messages || []).filter((m) => {
    const text = (m?.message || '').trim()
    if (!text) return true
    const isLegacyPropertyContext =
      /^\[Property(?::\d+)?\]/i.test(text) ||
      /^\[PropertyContext(?::\d+)?\]/i.test(text) ||
      text.includes('Property inquiry')
    return !isLegacyPropertyContext
  })
  return [...list].sort((a, b) => new Date(a.created_at) - new Date(b.created_at))
})

watch(() => props.messages?.length, () => {
  nextTick(() => scrollToBottom())
}, { flush: 'post' })

watch(() => props.conversationId, () => {
  draft.value = ''
})

function scrollToBottom() {
  nextTick(() => {
    const el = messagesContainerRef.value
    if (el) el.scrollTop = el.scrollHeight
  })
}

function onScroll() {
  const el = messagesContainerRef.value
  if (!el || loadingMore.value) return
  if (el.scrollTop < 80) {
    emit('load-more')
  }
}

function onTyping() {
  emit('typing')
}

async function sendMessage() {
  const text = draft.value?.trim()
  if (!text || sending.value || !props.conversationId) return
  sending.value = true
  try {
    emit('send', { conversation_id: props.conversationId, message: text })
    draft.value = ''
  } finally {
    sending.value = false
  }
}
</script>

<style scoped>
.chat-window {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 320px;
  background: #fff;
}
.chat-window-header {
  padding: 12px 16px;
  border-bottom: 1px solid #eee;
  background: #f8f9fa;
}
.header-user {
  display: flex;
  align-items: center;
  gap: 10px;
}
.header-avatar, .header-avatar-placeholder {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
}
.header-avatar-placeholder {
  background: #0d6efd;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
}
.header-name { font-weight: 600; font-size: 15px; color: #1a1a1a; }
.header-status { font-size: 12px; color: #666; display: block; }
.chat-messages {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  background: #f8fafc;
}
.property-context-card {
  margin: 10px 12px 0;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #f8fbff;
  padding: 10px 12px;
}
.property-context-title {
  font-size: 13px;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 4px;
}
.property-context-meta {
  font-size: 12px;
  color: #475569;
  line-height: 1.4;
}
.property-context-link {
  display: inline-flex;
  margin-top: 7px;
  font-size: 12px;
  color: #0d6efd;
  text-decoration: underline;
}
.loading-more { text-align: center; padding: 8px; font-size: 12px; color: #666; }
.message-row { margin-bottom: 6px; }
.typing-indicator { padding: 4px 0; font-size: 12px; color: #666; font-style: italic; }
.typing-dots::after { content: ''; animation: dots 1.5s infinite; }
@keyframes dots { 0%, 20% { content: '.'; } 40% { content: '..'; } 60%, 100% { content: '...'; } }
.messages-bottom { height: 8px; }
.chat-input-area {
  display: flex;
  align-items: flex-end;
  gap: 10px;
  padding: 14px 16px;
  border-top: 1px solid #e2e8f0;
  background: #fff;
}
.chat-input {
  flex: 1;
  min-width: 0;
  border: 1px solid #e2e8f0;
  border-radius: 22px;
  padding: 12px 18px;
  font-size: 14px;
  line-height: 1.45;
  resize: none;
  max-height: 120px;
  font-family: inherit;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.chat-input:focus {
  outline: none;
  border-color: #0d6efd;
  box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.15);
}
.send-btn {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #0d6efd;
  color: #fff;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  flex-shrink: 0;
}
.send-btn:disabled {
  background: #ccc;
  cursor: not-allowed;
}
.send-btn:not(:disabled):hover {
  background: #0b5ed7;
}
</style>
