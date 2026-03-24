<template>
  <Teleport to="body">
    <div
      v-show="show"
      class="chat-popup-overlay"
      :style="show ? { display: 'flex' } : {}"
      @click.self="close"
    >
      <div class="chat-popup">
        <div class="popup-header">
          <div class="popup-header-left">
            <span class="popup-icon"><i class="ri-chat-3-fill"></i></span>
            <h6 class="popup-title">Messages</h6>
          </div>
          <button type="button" class="popup-close" aria-label="Close" @click="close">
            <i class="ri-close-line"></i>
          </button>
        </div>
        <div class="popup-body">
          <div v-if="showConversationList" class="chat-list-wrap">
            <div class="chat-search-wrap">
              <i class="ri-search-line chat-search-icon"></i>
              <input
                v-model.trim="chatSearchQuery"
                type="text"
                class="chat-search-input"
                placeholder="Search chats or agents by name/email..."
              />
            </div>
            <div v-if="chatSearchQuery && chatSearchQuery.length >= 2" class="chat-agent-search-results">
              <div class="chat-agent-search-title">
                System agents
                <span v-if="searchingAgents" class="chat-agent-search-loading">Searching...</span>
              </div>
              <button
                v-for="agent in agentSearchResults"
                :key="`agent-${agent.id}`"
                type="button"
                class="chat-agent-result-item"
                @click="startChatWithAgent(agent)"
              >
                <span class="chat-agent-result-name">{{ agent.name }}</span>
                <span class="chat-agent-result-email">{{ agent.email }}</span>
              </button>
              <div v-if="!searchingAgents && agentSearchResults.length === 0" class="chat-agent-search-empty">
                No matching agents found.
              </div>
            </div>
            <ConversationList
              :conversations="filteredConversations"
              :selected-id="activeConversationId"
              :loading="loadingConversations"
              @select="openConversation"
            />
          </div>
          <ChatWindow
            v-else-if="showChatWindow"
            :conversation-id="activeConversationId"
            :messages="messages"
            :other-user="activeOtherUser"
            :current-user-id="currentUserId"
            :current-user-avatar="currentUserAvatar"
            :current-user-name="currentUserName"
            :online-status="onlineStatus"
            :typing-user="typingUser"
            @send="handleSend"
            @load-more="loadMoreMessages"
            @typing="emitTyping"
          />
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import api from '@/plugins/axios'
import ConversationList from './ConversationList.vue'
import ChatWindow from './ChatWindow.vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  startWithAgent: { type: Object, default: null },
  startWithListingId: { type: Number, default: null },
})

const emit = defineEmits(['close'])

const conversations = ref([])
const loadingConversations = ref(false)
const activeConversationId = ref(null)
const activeConversation = ref(null)
const messages = ref([])
const loadingMessages = ref(false)
const currentUserId = ref(null)
const currentUserAvatar = ref('')
const currentUserName = ref('')
const onlineStatus = ref('')
const typingUser = ref('')
const typingTimeout = ref(null)
const echoChannel = ref(null)
const messagesPage = ref(1)
const messagesLastPage = ref(1)
const startWithAgentFailed = ref(false)
const chatSearchQuery = ref('')
const agentSearchResults = ref([])
const searchingAgents = ref(false)
let agentSearchDebounceTimer = null

const filteredConversations = computed(() => {
  const q = chatSearchQuery.value.toLowerCase()
  if (!q) return conversations.value
  return conversations.value.filter((c) => {
    const name = (c.other_user?.name || '').toLowerCase()
    const email = (c.other_user?.email || '').toLowerCase()
    return name.includes(q) || email.includes(q)
  })
})

const activeOtherUser = computed(() => {
  return activeConversation.value?.other_user || props.startWithAgent || null
})

const showConversationList = computed(() => {
  if (activeConversationId.value) return false
  return !props.startWithAgent || startWithAgentFailed.value
})

const showChatWindow = computed(() => {
  return !!activeConversationId.value || (!!props.startWithAgent && !startWithAgentFailed.value)
})

watch(() => props.show, (visible) => {
  if (visible) {
    startWithAgentFailed.value = false
    chatSearchQuery.value = ''
    agentSearchResults.value = []
    loadUser()
    const isSelf = currentUserId.value != null && props.startWithAgent && Number(props.startWithAgent.id) === Number(currentUserId.value)
    if (isSelf) {
      // Current user is the agent — show inbox instead of "chat with yourself"
      loadConversations()
      activeConversationId.value = null
      activeConversation.value = null
      messages.value = []
      startWithAgentFailed.value = true
    } else if (props.startWithAgent && props.startWithListingId != null) {
      startConversationWithAgent(props.startWithAgent.id, props.startWithListingId)
    } else if (props.startWithAgent) {
      startConversationWithAgent(props.startWithAgent.id, null)
    } else {
      loadConversations()
      activeConversationId.value = null
      activeConversation.value = null
      messages.value = []
    }
  } else {
    unsubscribeEcho()
    if (agentSearchDebounceTimer) {
      clearTimeout(agentSearchDebounceTimer)
      agentSearchDebounceTimer = null
    }
  }
})

watch(chatSearchQuery, (q) => {
  const query = (q || '').trim()
  if (agentSearchDebounceTimer) {
    clearTimeout(agentSearchDebounceTimer)
    agentSearchDebounceTimer = null
  }

  if (query.length < 2) {
    agentSearchResults.value = []
    searchingAgents.value = false
    return
  }

  agentSearchDebounceTimer = setTimeout(() => {
    searchAgents(query)
  }, 250)
})

watch(activeConversationId, (id) => {
  if (id) {
    loadMessages(id)
    markRead(id)
    subscribeConversation(id)
  } else {
    messages.value = []
    unsubscribeEcho()
  }
})

function loadUser() {
  try {
    const userStr = localStorage.getItem('user')
    if (userStr) {
      const user = JSON.parse(userStr)
      currentUserId.value = user.id
      currentUserName.value = user.name || user.email || ''
      currentUserAvatar.value = user.avatar_url || (user.avatar ? import.meta.env.VITE_APP_URL + '/storage/' + user.avatar : '') || ''
    }
  } catch (e) {
    console.error('Chat load user', e)
  }
}

async function loadConversations() {
  loadingConversations.value = true
  try {
    const res = await api.get('/chat/conversations')
    if (res.data?.data) conversations.value = res.data.data
    else conversations.value = []
  } catch (e) {
    console.error('Load conversations', e)
    conversations.value = []
  } finally {
    loadingConversations.value = false
  }
}

function normalizeUsersPayload(res) {
  const raw = res?.data?.data
  if (Array.isArray(raw)) return raw
  if (Array.isArray(raw?.data)) return raw.data
  if (Array.isArray(res?.data)) return res.data
  return []
}

async function searchAgents(query) {
  searchingAgents.value = true
  try {
    const res = await api.get('/users', {
      params: { search: query }
    })
    const list = normalizeUsersPayload(res)
    agentSearchResults.value = list
      .map((u) => ({
        id: Number(u.id),
        name: u.name || 'Unknown',
        email: u.email || '',
        avatar: u.avatar_url || u.avatar || null
      }))
      .filter((u) => Number.isInteger(u.id) && u.id > 0 && u.id !== Number(currentUserId.value))
      .slice(0, 8)
  } catch (e) {
    console.error('Search agents', e)
    agentSearchResults.value = []
  } finally {
    searchingAgents.value = false
  }
}

async function startConversationWithAgent(agentId, listingId) {
  const agentIdNum = agentId != null ? Number(agentId) : NaN
  if (!Number.isInteger(agentIdNum) || agentIdNum < 1) {
    console.error('Start conversation: invalid agent_id', agentId)
    return
  }
  try {
    const payload = { agent_id: agentIdNum }
    const listingIdNum = listingId != null && listingId !== '' ? Number(listingId) : NaN
    if (Number.isInteger(listingIdNum) && listingIdNum > 0) {
      payload.listing_id = listingIdNum
    }
    const res = await api.post('/chat/start', payload)
    if (res.data?.success && res.data?.conversation) {
      const c = res.data.conversation
      activeConversationId.value = c.id
      activeConversation.value = c
      conversations.value = [c, ...conversations.value.filter(x => x.id !== c.id)]
    }
  } catch (e) {
    const msg = e.response?.data?.message || e.message
    const errors = e.response?.data?.errors
    console.error('Start conversation', msg, errors || e)
    if (errors) console.error('Validation errors', errors)
    startWithAgentFailed.value = true
    loadConversations()
    activeConversationId.value = null
    activeConversation.value = null
    messages.value = []
  }
}

function startChatWithAgent(agent) {
  if (!agent?.id) return
  chatSearchQuery.value = ''
  agentSearchResults.value = []
  startConversationWithAgent(agent.id, null)
}

function openConversation(conv) {
  activeConversationId.value = conv.id
  activeConversation.value = conv
}

async function loadMessages(conversationId, page = 1) {
  if (page === 1) {
    loadingMessages.value = true
    messages.value = []
    messagesPage.value = 1
    messagesLastPage.value = 1
  }
  try {
    const res = await api.get(`/chat/messages/${conversationId}`, { params: { page } })
    const list = res.data?.data || []
    const meta = res.data?.meta || {}
    if (page === 1) messages.value = list
    else messages.value = [...list, ...messages.value]
    messagesPage.value = meta.current_page || page
    messagesLastPage.value = meta.last_page || 1
  } catch (e) {
    console.error('Load messages', e)
  } finally {
    loadingMessages.value = false
  }
}

function loadMoreMessages() {
  if (loadingMessages.value || !activeConversationId.value) return
  if (messagesPage.value >= messagesLastPage.value) return
  loadMessages(activeConversationId.value, messagesPage.value + 1)
}

async function markRead(conversationId) {
  try {
    await api.post('/chat/read', { conversation_id: conversationId })
  } catch (_) {}
}

async function handleSend(payload) {
  try {
    const res = await api.post('/chat/send', payload)
    if (res.data?.success && res.data?.message) {
      messages.value = [...messages.value, res.data.message]
    }
  } catch (e) {
    console.error('Send message', e)
  }
}

function emitTyping() {
  typingUser.value = 'You'
  if (typingTimeout.value) clearTimeout(typingTimeout.value)
  typingTimeout.value = setTimeout(() => { typingUser.value = '' }, 2000)
}

function subscribeConversation(conversationId) {
  unsubscribeEcho()
  if (!window.Echo || !currentUserId.value) return
  try {
    const channel = window.Echo.private(`user.${currentUserId.value}`)
    channel.listen('.message.sent', (e) => {
      if (e.conversation_id === conversationId && e.sender_id !== currentUserId.value) {
        messages.value = [...messages.value, {
          id: e.id,
          conversation_id: e.conversation_id,
          sender_id: e.sender_id,
          sender: e.sender,
          message: e.message,
          read_at: e.read_at,
          created_at: e.created_at,
          is_mine: false,
        }]
        markRead(conversationId)
      }
    })
    echoChannel.value = channel
  } catch (e) {
    console.warn('Echo subscribe', e)
  }
}

function unsubscribeEcho() {
  if (echoChannel.value) {
    try {
      echoChannel.value.stopListening('.message.sent')
    } catch (_) {}
    echoChannel.value = null
  }
}

function close() {
  emit('close')
}
</script>

<style scoped>
.chat-popup-overlay {
  position: fixed;
  inset: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0, 0, 0, 0.25);
  backdrop-filter: blur(2px);
  display: flex;
  align-items: flex-end;
  justify-content: flex-end;
  z-index: 99999;
  pointer-events: auto;
  padding: 0 24px 24px 0;
  box-sizing: border-box;
}
.chat-popup {
  width: 420px;
  min-width: 360px;
  max-width: min(480px, calc(100vw - 48px));
  height: 580px;
  min-height: 420px;
  max-height: calc(100vh - 100px);
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12), 0 2px 8px rgba(0, 0, 0, 0.08);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid rgba(0, 0, 0, 0.06);
  animation: chat-popup-in 0.2s ease-out;
}
@keyframes chat-popup-in {
  from {
    opacity: 0;
    transform: translateY(12px) scale(0.98);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}
.popup-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
  border-bottom: 1px solid #e2e8f0;
  flex-shrink: 0;
}
.popup-header-left {
  display: flex;
  align-items: center;
  gap: 10px;
}
.popup-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
}
.popup-title {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: #1e293b;
  letter-spacing: -0.01em;
}
.popup-close {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: transparent;
  border: none;
  font-size: 20px;
  color: #64748b;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.15s, color 0.15s;
}
.popup-close:hover {
  background: #f1f5f9;
  color: #1e293b;
}
.popup-body {
  flex: 1;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  background: #fff;
}
.chat-list-wrap {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}
.chat-search-wrap {
  flex-shrink: 0;
  position: relative;
  padding: 10px 12px;
  border-bottom: 1px solid #e2e8f0;
  background: #fff;
}
.chat-search-input {
  width: 100%;
  padding: 8px 12px 8px 34px;
  border: 1px solid #e2e8f0;
  border-radius: 20px;
  font-size: 13px;
  outline: none;
  transition: border-color 0.15s;
}
.chat-search-input:focus {
  border-color: #0d6efd;
  box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.1);
}
.chat-search-icon {
  position: absolute;
  left: 22px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 16px;
  color: #94a3b8;
  pointer-events: none;
}
.chat-list-wrap :deep(.conversation-list) {
  flex: 1;
  min-height: 0;
  max-height: none;
}
.chat-agent-search-results {
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
  padding: 8px 12px;
  max-height: 190px;
  overflow-y: auto;
}
.chat-agent-search-title {
  font-size: 12px;
  font-weight: 600;
  color: #334155;
  margin-bottom: 6px;
  display: flex;
  justify-content: space-between;
}
.chat-agent-search-loading {
  font-size: 11px;
  color: #64748b;
  font-weight: 500;
}
.chat-agent-result-item {
  width: 100%;
  text-align: left;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #fff;
  padding: 8px 10px;
  margin-bottom: 6px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.chat-agent-result-item:hover {
  border-color: #bfdbfe;
  background: #eff6ff;
}
.chat-agent-result-name {
  font-size: 13px;
  color: #0f172a;
  font-weight: 600;
}
.chat-agent-result-email {
  font-size: 11px;
  color: #64748b;
}
.chat-agent-search-empty {
  font-size: 12px;
  color: #64748b;
  padding: 4px 0 2px;
}
</style>
