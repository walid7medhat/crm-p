<template>
  <div class="dashboard-main-body">
    <div class="chat-admin-header mb-4">
      <h4 class="chat-admin-title">All Chats</h4>
      <p class="chat-admin-desc">View all conversations. Search by name below to filter.</p>
    </div>
    <div class="card">
      <div class="card-body">
        <div class="chat-admin-search-row mb-3">
          <label class="form-label small mb-1">Search by name</label>
          <input
            v-model.trim="searchQuery"
            type="text"
            class="form-control form-control-sm chat-admin-search-input"
            placeholder="Type name or email to search..."
          />
        </div>
        <div v-if="loading" class="text-center py-5 text-muted">Loading conversations...</div>
        <div v-else-if="!(conversations && conversations.length)" class="text-center py-5 text-muted">No conversations found.</div>
        <div v-else class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th>ID</th>
                <th>Participants</th>
                <th>Listing</th>
                <th>Last message</th>
                <th>Updated</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in conversations" :key="c.id">
                <td>{{ c.id }}</td>
                <td>
                  <span v-if="c.other_user" class="badge bg-light text-dark">{{ c.other_user.name }} ({{ c.other_user.id }})</span>
                  <span v-else class="text-muted">—</span>
                </td>
                <td>
                  <span v-if="c.listing" class="small">#{{ c.listing.id }} {{ c.listing.reference_number || '' }}</span>
                  <span v-else class="text-muted">—</span>
                </td>
                <td class="small text-muted">{{ c.last_message?.message || '—' }}</td>
                <td class="small">{{ formatDate(c.updated_at) }}</td>
                <td>
                  <button class="btn btn-sm btn-outline-primary" @click="openConversation(c)">Open</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="meta.last_page > 1" class="d-flex justify-content-between align-items-center mt-3">
          <button
            class="btn btn-sm btn-outline-secondary"
            :disabled="meta.current_page <= 1"
            @click="fetchConversations(meta.current_page - 1)"
          >
            Previous
          </button>
          <span class="small text-muted">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
          <button
            class="btn btn-sm btn-outline-secondary"
            :disabled="meta.current_page >= meta.last_page"
            @click="fetchConversations(meta.current_page + 1)"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="selectedConversation" class="chat-admin-modal-overlay" @click.self="selectedConversation = null">
        <div class="chat-admin-modal">
          <div class="chat-admin-modal-header">
            <h6 class="mb-0">Conversation #{{ selectedConversation.id }}</h6>
            <button type="button" class="btn-close btn-sm" aria-label="Close" @click="selectedConversation = null"></button>
          </div>
          <div class="chat-admin-modal-body">
            <ChatWindow
              v-if="selectedConversation"
              :conversation-id="selectedConversation.id"
              :messages="adminMessages"
              :other-user="selectedConversation.other_user"
              :current-user-id="currentUserId"
              :current-user-avatar="currentUserAvatar"
              :current-user-name="currentUserName"
              online-status=""
              @send="handleSend"
              @load-more="loadMoreAdminMessages"
            />
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import api from '@/plugins/axios'
import ChatWindow from '@/components/chat/ChatWindow.vue'

const allConversations = ref([])
const loading = ref(false)
const searchQuery = ref('')
const meta = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
const selectedConversation = ref(null)
const adminMessages = ref([])
const adminMessagesPage = ref(1)
const adminMessagesLastPage = ref(1)
const currentUserId = ref(null)
const currentUserAvatar = ref('')
const currentUserName = ref('')

const conversations = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return allConversations.value
  return allConversations.value.filter((c) => {
    const name = (c.other_user?.name || '').toLowerCase()
    const email = (c.other_user?.email || '').toLowerCase()
    return name.includes(q) || email.includes(q)
  })
})

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString()
}

async function fetchConversations(page = 1) {
  loading.value = true
  try {
    const res = await api.get('/chat/admin/conversations', {
      params: { page, per_page: 200 }
    })
    allConversations.value = res.data?.data ?? []
    meta.value = res.data?.meta ?? meta.value
  } catch (e) {
    console.error('Admin conversations', e)
    allConversations.value = []
  } finally {
    loading.value = false
  }
}

function openConversation(conv) {
  selectedConversation.value = conv
  adminMessages.value = []
  adminMessagesPage.value = 1
  adminMessagesLastPage.value = 1
  if (conv.id) loadAdminMessages(conv.id)
}

async function loadAdminMessages(conversationId, page = 1) {
  try {
    const res = await api.get(`/chat/messages/${conversationId}`, { params: { page } })
    const list = res.data?.data ?? []
    const resMeta = res.data?.meta ?? {}
    if (page === 1) adminMessages.value = list
    else adminMessages.value = [...list, ...adminMessages.value]
    adminMessagesPage.value = resMeta.current_page ?? page
    adminMessagesLastPage.value = resMeta.last_page ?? 1
  } catch (e) {
    console.error('Load admin messages', e)
  }
}

function loadMoreAdminMessages() {
  if (!selectedConversation.value?.id || adminMessagesPage.value >= adminMessagesLastPage.value) return
  loadAdminMessages(selectedConversation.value.id, adminMessagesPage.value + 1)
}

async function handleSend(payload) {
  try {
    const res = await api.post('/chat/send', payload)
    if (res.data?.success && res.data?.message) {
      adminMessages.value = [...adminMessages.value, res.data.message]
    }
  } catch (e) {
    console.error('Send message', e)
  }
}

watch(selectedConversation, (v) => {
  if (!v) adminMessages.value = []
})

function loadUser() {
  try {
    const userStr = localStorage.getItem('user')
    if (userStr) {
      const user = JSON.parse(userStr)
      currentUserId.value = user.id
      currentUserName.value = user.name || user.email || ''
      currentUserAvatar.value = user.avatar_url || (user.avatar ? (import.meta.env.VITE_APP_URL || '') + '/storage/' + user.avatar : '') || ''
    }
  } catch (_) {}
}

onMounted(() => {
  loadUser()
  fetchConversations()
})
</script>

<style scoped>
.chat-admin-header {
  padding: 12px 16px;
  background: #1e293b;
  border-radius: 10px;
}
.chat-admin-title {
  font-size: 1.1rem;
  font-weight: 600;
  color: #ffffff;
  margin: 0 0 4px 0;
}
.chat-admin-desc {
  font-size: 0.85rem;
  color: #ffffff;
  margin: 0;
  opacity: 0.9;
}
.chat-admin-search-row {
  max-width: 320px;
}
.chat-admin-search-input {
  border-radius: 8px;
}
.chat-admin-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}
.chat-admin-modal {
  width: 480px;
  max-width: 95vw;
  height: 600px;
  max-height: 85vh;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.chat-admin-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-bottom: 1px solid #eee;
}
.chat-admin-modal-body {
  flex: 1;
  overflow: hidden;
}
</style>
