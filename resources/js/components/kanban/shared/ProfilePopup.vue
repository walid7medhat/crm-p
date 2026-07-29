<!-- components/ProfileModal.vue -->
<template>
  <Teleport to="body">
    <Transition name="profile-panel">
      <div v-if="modelValue" class="profile-panel-backdrop" @click="close">
        <div ref="profilePanel" class="profile-panel" @click.stop>
          <header class="profile-panel-header">
            <h2 class="profile-panel-title">Profile Details</h2>
            <button type="button" class="profile-panel-close" @click="close" aria-label="Close">
              <iconify-icon icon="lucide:x" class="icon"></iconify-icon>
            </button>
          </header>

          <div class="profile-panel-body">
            <div v-if="profileLoading" class="profile-panel-loading">
              <span class="profile-panel-spinner"></span>
              <span>Loading...</span>
            </div>
            <template v-else-if="userData">
              <div class="profile-summary-card">
                <div class="profile-summary-left">
                  <div class="profile-avatar-wrap">
                    <img v-if="userData.avatar" :src="userData.avatar" alt="Avatar" class="profile-summary-avatar">
                    <div v-else class="profile-summary-avatar profile-avatar-fallback d-flex align-items-center justify-content-center" :style="{ backgroundColor: getAvatarColor(userData.name) }">
                      <span class="avatar-initials">{{ getUserInitials(userData.name) }}</span>
                    </div>
                   
                  </div>
                  <div class="profile-summary-info">
                    <h6 class="profile-summary-name">{{ userData.name || 'User' }}</h6>
                    <p class="profile-summary-email">{{ userData.email || '—' }}</p>
                    <p class="profile-summary-role">{{ userData.role_name || 'User' }}</p>
                  </div>
                </div>
                <div class="profile-summary-right">
                  <div class="profile-status-row">
                    <span class="profile-status-dot" :class="userData.status === 'active' ? 'status-online' : 'status-offline'"></span>
                    <span class="profile-status-text">{{ userData.status === 'active' ? 'Online' : 'Offline' }}</span>
                    <button type="button" class="profile-more-btn" aria-label="Options" v-if="showEdit">
                      <iconify-icon icon="lucide:more-vertical" class="icon"></iconify-icon>
                    </button>
                  </div>
                  <p class="profile-last-active">Last Active : {{ lastActiveText }}</p>
                </div>
              </div>

              <section class="profile-section profile-section-contact">
                <div class="profile-section-head">
                  <h4 class="profile-section-title">Contact Information</h4>
                  <template v-if="showEdit && !isPersonalInfoEditing">
                    <button type="button" class="profile-edit-icon" aria-label="Edit" @click="startPersonalInfoEdit">
                      <iconify-icon icon="lucide:pencil" class="icon"></iconify-icon>
                    </button>
                  </template>
                  <div v-else-if="showEdit && isPersonalInfoEditing" class="profile-contact-actions">
                    <button type="button" class="profile-contact-btn profile-contact-cancel" @click="cancelPersonalInfoEdit">Cancel</button>
                    <button type="button" class="profile-contact-btn profile-contact-save" @click="savePersonalInfoEdit">Save</button>
                  </div>
                </div>
                <div class="profile-contact-grid profile-contact-two-cols">
                  <div class="profile-contact-item">
                    <span class="profile-contact-label">First Name</span>
                    <input
                      v-if="isPersonalInfoEditing"
                      v-model="personalInfoEdit.first_name"
                      type="text"
                      class="profile-contact-input"
                      placeholder="First Name"
                    >
                    <span v-else class="profile-contact-value">{{ firstName }}</span>
                  </div>
                  <div class="profile-contact-item">
                    <span class="profile-contact-label">Departments</span>
                    <span class="profile-contact-value profile-contact-readonly">{{ userData.role_name || '—' }}</span>
                  </div>
                  <div class="profile-contact-item">
                    <span class="profile-contact-label">Last Name</span>
                    <input
                      v-if="isPersonalInfoEditing"
                      v-model="personalInfoEdit.last_name"
                      type="text"
                      class="profile-contact-input"
                      placeholder="Last Name"
                    >
                    <span v-else class="profile-contact-value">{{ lastName }}</span>
                  </div>
                  <div class="profile-contact-item">
                    <span class="profile-contact-label">Notification Language</span>
                    <span class="profile-contact-value profile-contact-readonly">{{ notificationLanguage }}</span>
                  </div>
                  <div class="profile-contact-item">
                    <span class="profile-contact-label">Email</span>
                    <input
                      v-if="isPersonalInfoEditing"
                      v-model="personalInfoEdit.email"
                      type="email"
                      class="profile-contact-input"
                      placeholder="Email"
                    >
                    <span v-else class="profile-contact-value">{{ userData.email || '—' }}</span>
                  </div>
                  <div class="profile-contact-item">
                    <span class="profile-contact-label">Phone Number</span>
                    <input
                      v-if="isPersonalInfoEditing"
                      v-model="personalInfoEdit.phone"
                      type="tel"
                      class="profile-contact-input"
                      placeholder="Phone Number"
                    >
                    <span v-else class="profile-contact-value">{{ userData.phone || '—' }}</span>
                  </div>
                </div>
              </section>

              <section class="profile-section profile-section-team">
                <div class="profile-section-head">
                  <h4 class="profile-section-title"> Team</h4>
                  <span class="profile-section-badge"># TEAM</span>
                </div>
                <div class="profile-team-grid">
                  <div
                    v-for="member in visibleTeamMembers"
                    :key="member.id"
                    class="profile-team-card profile-team-pill"
                  >
                    <div class="profile-team-avatar-wrap">
                      <img v-if="member.avatar" :src="member.avatar" :alt="member.name" class="profile-team-avatar">
                      <span v-else class="profile-team-agent-icon">
                        <iconify-icon icon="lucide:user-round" class="icon"></iconify-icon>
                      </span>
                      <span
                        class="profile-team-status-dot"
                        :class="member.status === 'away' ? 'status-away' : member.online ? 'status-online' : 'status-offline'"
                      ></span>
                    </div>
                    <div class="profile-team-info">
                      <span class="profile-team-name">{{ teamMemberDisplayName(member) }}</span>
                      <span class="profile-team-role">{{ member.role_name || member.role || '—' }}</span>
                    </div>
                  </div>
                </div>
                <div v-if="teamMembersList.length > teamPageSize" class="profile-team-see-more-wrap">
                  <button
                    v-if="hasMoreTeamMembers"
                    type="button"
                    class="profile-show-all-team profile-see-more-btn"
                    @click="loadMoreTeamMembers"
                  >
                    Show All Team
                  </button>
                </div>
                <p v-if="teamMembersList.length === 0 && !profileLoading" class="profile-team-empty">No team members under you.</p>
              </section>

              <div class="profile-quick-menu">
                <button v-if="isOwnProfile" type="button" class="profile-quick-menu-item" @click="openThemeModal">
                  <iconify-icon icon="lucide:palette" class="profile-quick-menu-icon" />
                  <span class="profile-quick-menu-label">Visual theme</span>
                  <iconify-icon icon="lucide:chevron-right" class="profile-quick-menu-chevron" />
                </button>
                <router-link :to="`/users/${userData.id}`" class="profile-quick-menu-item" @click="close">
                  <iconify-icon icon="solar:user-linear" class="profile-quick-menu-icon" />
                  <span class="profile-quick-menu-label">{{ isOwnProfile ? 'My profile' : 'View full profile' }}</span>
                  <iconify-icon icon="lucide:chevron-right" class="profile-quick-menu-chevron" />
                </router-link>
                <button
                  v-if="showLogout || isOwnProfile"
                  type="button"
                  class="profile-quick-menu-item profile-quick-menu-item--logout"
                  @click="handleLogout"
                >
                  <iconify-icon icon="lucide:power" class="profile-quick-menu-icon" />
                  <span class="profile-quick-menu-label">Log out</span>
                  <iconify-icon icon="lucide:chevron-right" class="profile-quick-menu-chevron" />
                </button>
              </div>
            </template>

            <div v-else-if="!profileLoading && !userData" class="profile-error">
              <iconify-icon icon="lucide:alert-circle" class="icon"></iconify-icon>
              <span>{{ profileError || 'Failed to load profile data' }}</span>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <ProfileThemeModal v-model="showThemeModal" @saved="onThemeSaved" />
  </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/plugins/axios'
import ProfileThemeModal from '@/components/shared/ProfileThemeModal.vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  userId: {
    type: [Number, String],
    required: true
  },
  showEdit: {
    type: Boolean,
    default: false
  },
  showLogout: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'logout'])

const router = useRouter()
const userData = ref(null)
const profileLoading = ref(false)
const profileError = ref('')
const profilePanel = ref(null)
const avatarInput = ref(null)
const showThemeModal = ref(false)

function getCurrentUserId() {
  try {
    const raw = localStorage.getItem('user')
    if (!raw) return null
    const id = Number(JSON.parse(raw)?.id)
    return Number.isFinite(id) ? id : null
  } catch {
    return null
  }
}

const isOwnProfile = computed(() => {
  const currentId = getCurrentUserId()
  const viewedId = Number(props.userId)
  return !!currentId && !!viewedId && currentId === viewedId
})

// Edit states
const isPersonalInfoEditing = ref(false)
const personalInfoEdit = ref({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
})

// Team states
const fetchedTeamMembers = ref([])
const teamPageSize = 6
const visibleTeamCount = ref(teamPageSize)

// Helper functions
function getUserInitials(name) {
  if (!name) return 'U'
  return name.split(' ')
    .map(word => word[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
}

function getAvatarColor(name) {
  if (!name) return '#6366f1'
  const colors = ['#6366f1', '#8b5cf6', '#ec4899', '#f43f5e', '#ef4444', '#f97316', '#f59e0b', '#eab308', '#84cc16', '#10b981', '#14b8a6', '#06b6d4', '#0ea5e9', '#3b82f6']
  let hash = 0
  for (let i = 0; i < name.length; i++) {
    hash = ((hash << 5) - hash) + name.charCodeAt(i)
    hash |= 0
  }
  return colors[Math.abs(hash) % colors.length]
}

const firstName = computed(() => {
  if (!userData.value?.name) return '—'
  const parts = (userData.value.name || '').trim().split(/\s+/)
  return parts[0] || '—'
})

const lastName = computed(() => {
  if (!userData.value?.name) return '—'
  const parts = (userData.value.name || '').trim().split(/\s+/)
  return parts.length > 1 ? parts.slice(1).join(' ') : '—'
})

const notificationLanguage = computed(() => {
  return userData.value?.notification_language || userData.value?.locale || 'English'
})

const lastActiveText = computed(() => {
  const raw = userData.value?.last_login_at || userData.value?.last_active
  if (!raw) return '—'
  const d = new Date(raw)
  const now = new Date()
  const diffMs = now - d
  const diffMins = Math.floor(diffMs / 60000)
  if (diffMins < 1) return 'Just now'
  if (diffMins < 60) return `${diffMins} min ago`
  const diffHours = Math.floor(diffMins / 60)
  if (diffHours < 24) return `${diffHours}h ago`
  const diffDays = Math.floor(diffHours / 24)
  return `${diffDays}d ago`
})

const teamMembersList = computed(() => fetchedTeamMembers.value)
const visibleTeamMembers = computed(() => teamMembersList.value.slice(0, visibleTeamCount.value))
const hasMoreTeamMembers = computed(() => teamMembersList.value.length > visibleTeamCount.value)

function teamMemberDisplayName(member) {
  if (member.first_name != null || member.last_name != null) {
    return [member.first_name, member.last_name].filter(Boolean).join(' ').trim() || member.name || '—'
  }
  return member.name || '—'
}

function loadMoreTeamMembers() {
  visibleTeamCount.value += teamPageSize
}

function normalizeUserPayload(raw) {
  if (!raw || typeof raw !== 'object') return null
  if (raw.data && typeof raw.data === 'object' && (raw.data.id != null || raw.data.name)) {
    return raw.data
  }
  if (raw.id != null || raw.name) return raw
  return null
}

// Fetch functions
async function fetchUserFromAPI() {
  const userId = Number(props.userId)
  if (!userId || Number.isNaN(userId)) {
    profileError.value = 'Invalid user id'
    userData.value = null
    return
  }
  profileLoading.value = true
  profileError.value = ''
  try {
    const response = await api.get(`/users/${userId}`)
    const payload = normalizeUserPayload(response.data?.data)
    if (response.data?.status !== false && payload) {
      userData.value = payload
    } else {
      userData.value = null
      profileError.value = response.data?.message || 'Failed to load profile data'
    }
  } catch (error) {
    console.error('Failed to fetch user:', error)
    userData.value = null
    profileError.value = error?.response?.data?.message || 'Failed to load profile data'
  } finally {
    profileLoading.value = false
  }
}

async function fetchTeamMembers() {
  const userId = Number(props.userId)
  if (!userId || Number.isNaN(userId)) return
  try {
    const teamRes = await api.get(`/users/${userId}/team-members/recursive`).catch(() => ({ data: { data: [] } }))
    const list = teamRes.data?.data
    if (Array.isArray(list)) {
      fetchedTeamMembers.value = list.map((m) => ({
        id: m.id,
        name: m.name,
        first_name: m.first_name,
        last_name: m.last_name,
        email: m.email,
        phone: m.phone,
        avatar: m.avatar,
        role_name: m.role_name,
        role: m.role_name,
        status: m.status,
        online: m.status === 'active',
        created_at: m.created_at,
      }))
    }
  } catch (error) {
    console.error('Failed to fetch team:', error)
    fetchedTeamMembers.value = []
  }
}

async function fetchProfileAndTeam() {
  await Promise.all([fetchUserFromAPI(), fetchTeamMembers()])
}

// Edit functions (only if showEdit is true)
function startPersonalInfoEdit() {
  if (!props.showEdit) return
  const u = userData.value
  const parts = (u?.name || '').trim().split(/\s+/)
  personalInfoEdit.value = {
    first_name: parts[0] || '',
    last_name: parts.length > 1 ? parts.slice(1).join(' ') : '',
    email: u?.email || '',
    phone: u?.phone || '',
  }
  isPersonalInfoEditing.value = true
}

function cancelPersonalInfoEdit() {
  isPersonalInfoEditing.value = false
}

function savePersonalInfoEdit() {
  if (!props.showEdit) return
  const u = userData.value
  if (!u) return
  const first = (personalInfoEdit.value.first_name || '').trim()
  const last = (personalInfoEdit.value.last_name || '').trim()
  const fullName = [first, last].filter(Boolean).join(' ') || u.name
  userData.value = {
    ...u,
    name: fullName,
    email: (personalInfoEdit.value.email || '').trim() || u.email,
    phone: (personalInfoEdit.value.phone || '').trim() || u.phone,
  }
  isPersonalInfoEditing.value = false
}

function onAvatarChange(event) {
  if (!props.showEdit) return
  const file = event.target?.files?.[0]
  if (!file || !file.type.startsWith('image/')) return
  const reader = new FileReader()
  reader.onload = () => {
    const u = userData.value
    if (!u) return
    userData.value = { ...u, avatar: reader.result }
  }
  reader.readAsDataURL(file)
  event.target.value = ''
}

function close() {
  userData.value = null
  profileError.value = ''
  fetchedTeamMembers.value = []
  emit('update:modelValue', false)
}

function openThemeModal() {
  showThemeModal.value = true
}

function onThemeSaved() {
  // Background applied globally via useBackground in ProfileThemeModal
}

function handleLogout() {
  emit('logout')
  close()
}

// Watch for modal open / user change (immediate: panel is often mounted via v-if already open)
watch(
  () => [props.modelValue, props.userId],
  ([isOpen, userId]) => {
    if (!isOpen) {
      userData.value = null
      profileError.value = ''
      profileLoading.value = false
      fetchedTeamMembers.value = []
      return
    }
    if (userId) {
      visibleTeamCount.value = teamPageSize
      fetchProfileAndTeam()
    }
  },
  { immediate: true },
)
</script>

<style scoped>
/* جميع الستايلات السابقة */
.profile-panel-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  z-index: 50000;
  display: flex;
  justify-content: flex-end;
  font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, sans-serif;
}

.profile-panel {
  width: 713px;
  max-width: 95vw;
  height: 100%;
  background: #ffffff;
  box-shadow: -8px 0 32px rgba(0, 0, 0, 0.15);
  border-radius: 12px 0 0 12px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.profile-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid #e5e7eb;
  flex-shrink: 0;
  background: #fff;
}

.profile-panel-title {
  margin: 0 !important;
  font-size: 18px !important;
  font-weight: 700 !important;
  color: #111827 !important;
  letter-spacing: -0.01em !important;
  line-height: 1.3 !important;
}

.profile-panel-close {
  width: 36px;
  height: 36px;
  border: none;
  background: transparent;
  color: #6b7280;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: color 0.2s, background 0.2s;
}

.profile-panel-close:hover {
  color: #111827;
  background: #f3f4f6;
}

.profile-panel-close .icon {
  font-size: 20px;
}

.profile-panel-body {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
}

.profile-panel-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 32px;
  color: #6b7280;
  font-size: 14px;
}

.profile-panel-spinner {
  width: 24px;
  height: 24px;
  border: 2px solid #e5e7eb;
  border-top-color: #2563eb;
  border-radius: 50%;
  animation: profile-spin 0.8s linear infinite;
}

@keyframes profile-spin {
  to { transform: rotate(360deg); }
}

.profile-summary-card {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 20px;
  background: #f3f4f6;
  border-radius: 12px;
  margin-bottom: 28px;
  border: 1px solid #e5e7eb;
}

.profile-summary-left {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.profile-avatar-wrap {
  position: relative;
  flex-shrink: 0;
}

.profile-summary-avatar {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  object-fit: cover;
}

.profile-avatar-fallback {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  display: flex;
  align-items: center;
  justify-content: center;
}

.avatar-initials {
  font-size: 20px;
  font-weight: 600;
  color: white;
  text-transform: uppercase;
}

.profile-avatar-camera {
  position: absolute;
  right: -1px;
  bottom: -1px;
  width: 28px;
  height: 28px;
  background: #22c55e;
  color: #fff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid #f3f4f6;
  cursor: pointer;
}

.profile-avatar-file-input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}

.profile-summary-info {
  min-width: 0;
}

.profile-summary-name {
  margin: 0 0 4px;
  font-size: 17px;
  font-weight: 700;
  color: #111827;
  line-height: 1.3;
}

.profile-summary-email,
.profile-summary-role {
  margin: 0 !important;
  font-size: 13px !important;
  color: #6b7280 !important;
  line-height: 1.45 !important;
}

.profile-summary-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
  flex-shrink: 0;
}

.profile-status-row {
  display: flex;
  align-items: center;
  gap: 5px;
}

.profile-status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #9ca3af;
}

.profile-status-dot.status-offline {
  background: #ef4444;
}

.profile-status-dot.status-online {
  background: #22c55e;
}

.profile-status-text {
  font-size: 14px;
  color: #6b7280;
  font-weight: 500;
}

.profile-last-active {
  margin: 0;
  font-size: 14px;
  color: #9ca3af;
}

.profile-section {
  margin-bottom: 0;
}

.profile-section-contact {
  margin-bottom: 28px;
  padding: 20px 22px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
}

.profile-section-team {
  padding: 20px 22px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
}

.profile-section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.profile-section-title {
  margin: 0 !important;
  font-size: 14px !important;
  font-weight: 600 !important;
  color: #111827 !important;
}

.profile-section-badge {
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  background: #e5e7eb;
  padding: 4px 8px;
  border-radius: 4px;
}

.profile-edit-icon {
  width: 36px;
  height: 36px;
  border: none;
  background: transparent;
  color: #6b7280;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.profile-edit-icon:hover {
  background: #f3f4f6;
  color: #111827;
}

.profile-contact-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px 32px;
}

.profile-contact-two-cols {
  grid-template-columns: 1fr 1fr;
  gap: 18px 32px;
}

.profile-contact-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.profile-contact-label {
  font-size: 12px;
  font-weight: 500;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.profile-contact-value {
  font-size: 15px;
  color: #111827;
  font-weight: 500;
  line-height: 1.45;
}

.profile-contact-readonly {
  color: #374151;
}

.profile-contact-input {
  width: 100%;
  font-size: 15px;
  color: #111827;
  font-weight: 500;
  line-height: 1.45;
  padding: 8px 10px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background: #fff;
  outline: none;
}

.profile-contact-input:focus {
  border-color: #60a5fa;
}

.profile-contact-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.profile-contact-btn {
  padding: 6px 14px;
  font-size: 13px;
  font-weight: 600;
  border-radius: 8px;
  cursor: pointer;
  border: none;
  transition: background 0.2s, color 0.2s;
}

.profile-contact-cancel {
  background: #f3f4f6;
  color: #6b7280;
}

.profile-contact-cancel:hover {
  background: #e5e7eb;
  color: #111827;
}

.profile-contact-save {
  background: #2563eb;
  color: #fff;
}

.profile-contact-save:hover {
  background: #1d4ed8;
}

.profile-team-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px 12px;
  margin-bottom: 20px;
}

.profile-team-pill {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  text-align: left;
  min-width: 0;
}

.profile-team-avatar-wrap {
  position: relative;
  width: 44px;
  height: 44px;
  flex-shrink: 0;
}

.profile-team-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  object-fit: cover;
}

.profile-team-agent-icon {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: #e5e7eb;
  color: #6b7280;
  display: flex;
  align-items: center;
  justify-content: center;
}

.profile-team-status-dot {
  position: absolute;
  right: 0;
  bottom: 0;
  width: 10px;
  height: 10px;
  border: 2px solid #ffffff;
  border-radius: 50%;
}

.profile-team-status-dot.status-online {
  background: #22c55e;
}

.profile-team-status-dot.status-offline {
  background: #ef4444;
}

.profile-team-status-dot.status-away {
  background: #38bdf8;
}

.profile-team-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  flex: 1;
}

.profile-team-pill .profile-team-name {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.profile-team-role {
  margin: 0;
  font-size: 12px;
  color: #6b7280;
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.profile-team-see-more-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 4px;
}

.profile-see-more-btn {
  font-size: 14px;
  font-weight: 600;
  color: #2563eb;
  background: none;
  border: none;
  cursor: pointer;
  padding: 6px 0;
  text-align: left;
}

.profile-see-more-btn:hover {
  text-decoration: underline;
}

.profile-quick-menu {
  margin-top: 10px;
  padding: 4px;
  border-radius: 12px;
  background: #fff;
  border: 1px solid #e8ecf1;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
}

.profile-quick-menu > :not(:first-child) {
  border-top: 1px solid #f1f5f9;
}

.profile-quick-menu-item {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: none;
  border-radius: 8px;
  background: transparent;
  cursor: pointer;
  text-align: left;
  text-decoration: none;
  color: inherit;
  transition: background 0.15s ease;
}

.profile-quick-menu-item:hover {
  background: #f8fafc;
}

.profile-quick-menu-item--logout:hover .profile-quick-menu-label,
.profile-quick-menu-item--logout:hover .profile-quick-menu-icon {
  color: #dc2626;
}

.profile-quick-menu-icon {
  font-size: 16px;
  color: #475569;
  flex-shrink: 0;
}

.profile-quick-menu-label {
  flex: 1;
  font-size: 12px;
  font-weight: 500;
  color: #1e293b;
}

.profile-quick-menu-chevron {
  font-size: 14px;
  color: #94a3b8;
  flex-shrink: 0;
}

.profile-panel-enter-active,
.profile-panel-leave-active {
  transition: opacity 0.25s ease;
}

.profile-panel-enter-active .profile-panel,
.profile-panel-leave-active .profile-panel {
  transition: transform 0.25s ease;
}

.profile-panel-enter-from,
.profile-panel-leave-to {
  opacity: 0;
}

.profile-panel-enter-from .profile-panel,
.profile-panel-leave-to .profile-panel {
  transform: translateX(100%);
}

.profile-error {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 32px;
  color: #ef4444;
  font-size: 14px;
}

@media (max-width: 768px) {
  .profile-panel-backdrop {
    justify-content: center;
    padding: 0 12px;
  }

  .profile-panel {
    width: 100%;
    max-width: 100%;
  }
}

@media (max-width: 480px) {
  .profile-panel {
    width: 100%;
    max-width: 100%;
    border-radius: 0;
  }
  
  .profile-contact-grid {
    grid-template-columns: 1fr;
    gap: 12px;
  }
  
  .profile-summary-card {
    flex-direction: column;
  }
  
  .profile-summary-right {
    align-items: flex-start;
  }
}
</style>