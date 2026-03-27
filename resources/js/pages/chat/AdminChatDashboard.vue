<template>
  <div class="dashboard-main-body chat-admin-page">
    <div class="chat-admin-topbar">
      <div>
        <h6 class="chat-page-title">Chat Management</h6>
        <p class="chat-page-subtitle">Monitor conversations, review activity, and manage chat lifecycle.</p>
      </div>
      <span class="chat-role-pill" :class="{ 'is-super': isSuperAdmin }">
        {{ isSuperAdmin ? 'Super Admin' : 'Admin' }}
      </span>
    </div>

    <div class="chat-card">
      <div class="chat-card-body">
        <div class="chat-filters">
          <div class="chat-search-wrap">
            <i class="bx bx-search"></i>
            <input
              v-model.trim="searchQuery"
              type="text"
              class="chat-search-input"
              placeholder="Search by property, user, or message..."
            />
          </div>

          <select v-model="statusFilter" class="chat-filter-select">
            <option value="all">All status</option>
            <option value="open">Open</option>
            <option value="closed">Closed</option>
          </select>

          <input v-model="dateFilter" type="date" class="chat-filter-date" />
        </div>

        <div v-if="loading" class="text-center py-5 text-muted">Loading conversations...</div>
        <div v-else-if="!filteredConversations.length" class="text-center py-5 text-muted">No conversations found.</div>
        <div v-else class="table-responsive chat-table-wrap">
          <table class="table align-middle chat-table">
            <thead>
              <tr>
                <th>Property Name</th>
                <th>User Name</th>
                <th>Last Message</th>
                <th>Date / Time</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in pagedConversations" :key="c.id">
                <td>
                  <div class="property-cell">
                    <div class="property-title">{{ getPropertyName(c) }}</div>
                    <div class="property-meta" v-if="c.listing?.reference_number">
                      Ref: {{ c.listing.reference_number }}
                    </div>
                  </div>
                </td>
                <td>
                  <div class="user-cell">
                    <div class="user-name">{{ c.other_user?.name || '—' }}</div>
                    <div class="user-email">{{ c.other_user?.email || '' }}</div>
                  </div>
                </td>
                <td>
                  <span class="message-preview">{{ c.last_message?.message || '—' }}</span>
                </td>
                <td class="small">{{ formatDate(c.updated_at) }}</td>
                <td>
                  <span class="status-badge" :class="getConversationStatus(c) === 'closed' ? 'is-closed' : 'is-open'">
                    {{ getConversationStatus(c) === 'closed' ? 'Closed' : 'Open' }}
                  </span>
                </td>
                <td>
                  <div class="action-buttons">
                    <button class="btn btn-sm btn-primary-soft" @click="openConversation(c)">
                      <i class="bx bx-message-square-detail"></i>
                      Open Chat
                    </button>

                    <button
                      v-if="isSuperAdmin"
                      class="btn btn-sm btn-outline-warning"
                      @click="toggleConversationStatus(c)"
                    >
                      <i :class="getConversationStatus(c) === 'closed' ? 'bx bx-lock-open-alt' : 'bx bx-lock-alt'"></i>
                      {{ getConversationStatus(c) === 'closed' ? 'Reopen' : 'Close' }}
                    </button>

                    <button
                      v-if="isSuperAdmin"
                      class="btn btn-sm btn-outline-danger"
                      @click="deleteConversation(c)"
                    >
                      <i class="bx bx-trash"></i>
                      Delete
                    </button>
                  </div>
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
const statusFilter = ref('all')
const dateFilter = ref('')
const meta = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
const selectedConversation = ref(null)
const adminMessages = ref([])
const adminMessagesPage = ref(1)
const adminMessagesLastPage = ref(1)
const currentUserId = ref(null)
const currentUserAvatar = ref('')
const currentUserName = ref('')
const currentUserRoles = ref([])
const localStatusMap = ref({})

const isSuperAdmin = computed(() => currentUserRoles.value.includes('super_admin'))
const isNormalAdmin = computed(() => currentUserRoles.value.includes('admin') && !isSuperAdmin.value)

const filteredConversations = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  return allConversations.value.filter((c) => {
    const property = getPropertyName(c).toLowerCase()
    const name = (c.other_user?.name || '').toLowerCase()
    const email = (c.other_user?.email || '').toLowerCase()
    const message = (c.last_message?.message || '').toLowerCase()
    const status = getConversationStatus(c)
    const matchesQuery = !q || property.includes(q) || name.includes(q) || email.includes(q) || message.includes(q)
    const matchesStatus = statusFilter.value === 'all' || status === statusFilter.value
    const matchesDate = !dateFilter.value || (c.updated_at && c.updated_at.slice(0, 10) === dateFilter.value)
    return matchesQuery && matchesStatus && matchesDate
  })
})

const pagedConversations = computed(() => filteredConversations.value)

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString()
}

function getPropertyName(conversation) {
  return (
    conversation?.listing?.title ||
    conversation?.listing?.location ||
    conversation?.listing?.reference_number ||
    (conversation?.listing?.id ? `Property #${conversation.listing.id}` : 'General Chat')
  )
}

function getConversationStatus(conversation) {
  if (conversation?.status === 'closed' || conversation?.is_closed === true) return 'closed'
  const local = localStatusMap.value[conversation.id]
  return local || 'open'
}

function persistLocalStatuses() {
  localStorage.setItem('chat-admin-local-statuses', JSON.stringify(localStatusMap.value))
}

async function fetchConversations(page = 1) {
  loading.value = true
  try {
    const endpoint = isSuperAdmin.value ? '/chat/admin/conversations' : '/chat/conversations'
    const res = await api.get(endpoint, {
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

function toggleConversationStatus(conv) {
  if (!isSuperAdmin.value) return
  const current = getConversationStatus(conv)
  localStatusMap.value[conv.id] = current === 'closed' ? 'open' : 'closed'
  persistLocalStatuses()
}

function deleteConversation(conv) {
  if (!isSuperAdmin.value) return
  const ok = window.confirm(`Delete conversation #${conv.id}? This action cannot be undone.`)
  if (!ok) return
  allConversations.value = allConversations.value.filter((item) => item.id !== conv.id)
  if (selectedConversation.value?.id === conv.id) {
    selectedConversation.value = null
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
      currentUserRoles.value = Array.isArray(user.roles) ? user.roles : []
      currentUserId.value = user.id
      currentUserName.value = user.name || user.email || ''
      currentUserAvatar.value = user.avatar || (user.avatar ? (import.meta.env.VITE_APP_URL || '') + '/storage/' + user.avatar : '') || ''
    }
  } catch (_) {}

  try {
    const statuses = localStorage.getItem('chat-admin-local-statuses')
    localStatusMap.value = statuses ? JSON.parse(statuses) : {}
  } catch (_) {
    localStatusMap.value = {}
  }
}

onMounted(() => {
  loadUser()
  fetchConversations()
})
</script>

<style scoped>
.chat-admin-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.chat-admin-topbar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}
.chat-page-title {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
  color: #ffffff;
  letter-spacing: 0.2px;
  line-height: 1.2;
}
.chat-page-subtitle {
  margin: 4px 0 0;
  font-size: 0.8rem;
  color: #ffffff;
  opacity: 0.9;
}
.chat-role-pill {
  font-size: 0.72rem;
  line-height: 1;
  padding: 8px 10px;
  border-radius: 999px;
  background: #e2e8f0;
  color: #334155;
  font-weight: 600;
}
.chat-role-pill.is-super {
  background: #dbeafe;
  color: #1d4ed8;
}
.chat-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  box-shadow: 0 8px 30px rgba(15, 23, 42, 0.05);
}
.chat-card-body {
  padding: 16px;
}
.chat-filters {
  display: grid;
  grid-template-columns: 1fr 160px 160px;
  gap: 10px;
  margin-bottom: 14px;
}
.chat-search-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 0 10px;
  background: #fff;
}
.chat-search-wrap i {
  color: #94a3b8;
  font-size: 18px;
}
.chat-search-input {
  width: 100%;
  border: 0;
  outline: 0;
  height: 38px;
  font-size: 0.85rem;
}
.chat-filter-select,
.chat-filter-date {
  height: 38px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  padding: 0 10px;
  font-size: 0.84rem;
  color: #334155;
  background: #fff;
}
.chat-table-wrap {
  border: 1px solid #edf2f7;
  border-radius: 12px;
  overflow: auto;
}
.chat-table thead th {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #64748b;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}
.chat-table tbody tr {
  transition: background-color 0.18s ease;
}
.chat-table tbody tr:hover {
  background: #f8fafc;
}
.chat-table td,
.chat-table th {
  padding: 12px 14px;
  vertical-align: middle;
}
.property-title {
  font-size: 0.87rem;
  font-weight: 600;
  color: #0f172a;
}
.property-meta,
.user-email {
  font-size: 0.75rem;
  color: #64748b;
}
.user-name {
  font-size: 0.84rem;
  font-weight: 500;
  color: #0f172a;
}
.message-preview {
  display: inline-block;
  max-width: 280px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-size: 0.82rem;
  color: #475569;
}
.status-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 72px;
  height: 26px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
}
.status-badge.is-open {
  color: #166534;
  background: #dcfce7;
}
.status-badge.is-closed {
  color: #92400e;
  background: #fef3c7;
}
.action-buttons {
  display: flex;
  justify-content: flex-end;
  gap: 6px;
  flex-wrap: wrap;
}
.btn-primary-soft {
  color: #1d4ed8;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
}
.btn-primary-soft:hover {
  background: #dbeafe;
  color: #1e40af;
}
.chat-admin-modal {
  width: 540px;
  max-width: 96vw;
  height: 640px;
  max-height: 88vh;
}

@media (max-width: 900px) {
  .chat-filters {
    grid-template-columns: 1fr;
  }
  .message-preview {
    max-width: 180px;
  }
  .chat-admin-topbar {
    flex-direction: column;
    align-items: flex-start;
  }
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
