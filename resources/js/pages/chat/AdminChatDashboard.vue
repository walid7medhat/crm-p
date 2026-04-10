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
          <input v-model="dateFilter" type="date" class="chat-filter-date" />
        </div>

        <div v-if="loading" class="text-center py-5 text-muted">Loading conversations...</div>
        <div v-else-if="!filteredConversations.length" class="text-center py-5 text-muted">No conversations found.</div>
        <div v-else class="table-responsive chat-table-wrap">
          <table class="table align-middle chat-table">
            <thead>
              <tr>
                <th>From / To</th>
                <th>Property</th>
                <th>Last Message</th>
                <th>Date / Time</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in pagedConversations" :key="c.id">
                <td>
                  <div class="chat-participants">
                    <div class="participant-row">
                      <img :src="getFromParticipant(c).avatar" class="participant-avatar" alt="" />
                      <div>
                        <div class="participant-label">From</div>
                        <div class="participant-name">{{ getFromParticipant(c).name }}</div>
                      </div>
                    </div>
                    <div class="participant-row">
                      <img :src="getToParticipant(c).avatar" class="participant-avatar" alt="" />
                      <div>
                        <div class="participant-label">To</div>
                        <div class="participant-name">{{ getToParticipant(c).name }}</div>
                      </div>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="property-cell">
                    <div class="property-title">{{ getPropertyName(c) }}</div>
                    <a
                      v-if="getPropertyLink(c)"
                      :href="getPropertyLink(c)"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="property-link"
                    >
                      Open Property
                    </a>
                    <div v-else class="property-meta">General Chat</div>
                  </div>
                </td>
                <td>
                  <span class="message-preview">{{ c.last_message?.message || '—' }}</span>
                </td>
                <td class="small">{{ formatDate(c.updated_at) }}</td>
                <td>
                  <div class="action-buttons">
                    <button class="btn btn-sm btn-primary-soft" @click="openConversation(c)">
                      <i class="bx bx-message-square-detail"></i>
                      Open Chat
                    </button>

                    <button
                      v-if="isSuperAdmin"
                      class="btn btn-sm btn-outline-danger"
                      @click="confirmDeleteConversation(c)"
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
import Swal from 'sweetalert2'

const allConversations = ref([])
const loading = ref(false)
const searchQuery = ref('')
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

const isSuperAdmin = computed(() => currentUserRoles.value.includes('super_admin'))
const isNormalAdmin = computed(() => currentUserRoles.value.includes('admin') && !isSuperAdmin.value)
const conversationToDelete = ref(null) 
const showDeleteModal = ref(false) 

const filteredConversations = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  return allConversations.value.filter((c) => {
    const property = getPropertyName(c).toLowerCase()
    const fromName = (getFromParticipant(c).name || '').toLowerCase()
    const toName = (getToParticipant(c).name || '').toLowerCase()
    const message = (c.last_message?.message || '').toLowerCase()
    const matchesQuery = !q || property.includes(q) || fromName.includes(q) || toName.includes(q) || message.includes(q)
    const matchesDate = !dateFilter.value || (c.updated_at && c.updated_at.slice(0, 10) === dateFilter.value)
    return matchesQuery && matchesDate
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

function getPropertyLink(conversation) {
  const id = conversation?.listing?.id
  if (!id) return ''
  const base = (import.meta.env.VITE_APP_URL || window.location.origin || '').replace(/\/$/, '')
  return `${base}/property-details/${id}`
}

function getFromParticipant(conversation) {
  const senderId = conversation?.last_message?.sender_id
  const sender = (conversation?.participants || []).find(p => Number(p.id) === Number(senderId))
  const fromName = sender?.name || conversation?.last_message?.sender_name || '—'
  const fromAvatar = sender?.avatar || conversation?.last_message?.sender_avatar || '/assets/images/user.png'
  return {
    name: fromName,
    avatar: fromAvatar
  }
}

function getToParticipant(conversation) {
  const senderId = conversation?.last_message?.sender_id
  const participants = conversation?.participants || []
  let receiver = participants.find(p => Number(p.id) !== Number(senderId)) || participants[0]

  // Fallback for environments where backend still returns old shape (no participants array)
  if (
    !receiver &&
    conversation?.other_user &&
    (!senderId || Number(conversation.other_user.id) !== Number(senderId))
  ) {
    receiver = {
      id: conversation.other_user.id,
      name: conversation.other_user.name,
      avatar: conversation.other_user.avatar
    }
  }

  return {
    name: receiver?.name || '—',
    avatar: receiver?.avatar || '/assets/images/user.png'
  }
}

async function fetchConversations(page = 1) {
  loading.value = true
  try {
    // const endpoint = isSuperAdmin.value ? '/chat/admin/conversations' : '/chat/conversations'
    const endpoint = '/chat/admin/conversations' 
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

function confirmDeleteConversation(conv) {
  if (!isSuperAdmin.value) return

  Swal.fire({
    title: `Delete conversation #${conv.id}?`,
    text: "This action cannot be undone.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete it!',
    cancelButtonText: 'Cancel',
    reverseButtons: true,
  }).then(async (result) => {
    if (result.isConfirmed) {
      try {
        const res = await api.delete(`/chat/admin/conversations/${conv.id}`)
        if (res.data?.success) {
          allConversations.value = allConversations.value.filter(item => item.id !== conv.id)
          if (selectedConversation.value?.id === conv.id) selectedConversation.value = null
          Swal.fire('Deleted!', 'The conversation has been deleted.', 'success')
        } else {
          Swal.fire('Failed!', res.data?.message || 'Could not delete conversation.', 'error')
        }
      } catch (e) {
        console.error(e)
        Swal.fire('Error!', 'Something went wrong while deleting.', 'error')
      }
    }
  })
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
  grid-template-columns: 1fr 180px;
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
.property-link {
  display: inline-block;
  margin-top: 4px;
  font-size: 0.75rem;
  color: #2563eb;
  text-decoration: underline;
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
.chat-participants {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.participant-row {
  display: flex;
  align-items: center;
  gap: 8px;
}
.participant-avatar {
  width: 28px;
  height: 28px;
  border-radius: 999px;
  object-fit: cover;
  border: 1px solid #e2e8f0;
}
.participant-label {
  font-size: 0.68rem;
  color: #64748b;
  line-height: 1.1;
}
.participant-name {
  font-size: 0.8rem;
  color: #0f172a;
  font-weight: 600;
  line-height: 1.1;
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

@media (max-width: 768px) {
  .action-buttons {
    gap: 4px;
  }

  .action-buttons .btn {
    padding: 3px 8px;
    font-size: 0.68rem;
    line-height: 1.1;
    border-radius: 8px;
  }

  .action-buttons .btn i {
    font-size: 0.75rem;
    margin-right: 2px;
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
