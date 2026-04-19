<template>
  <div class="navbar-header">
    <div class="navbar-header-toolbar">
      <div class="navbar-header-left">
        <div class="d-flex flex-wrap align-items-center gap-2 gap-md-3">
          <button type="button" v-if="showBackButton"  class="sidebar-toggle back-button" @click="goBack" title="Go back" aria-label="Go back">
            <iconify-icon icon="lucide:arrow-left" class="icon navbar-header-back-icon text-white"></iconify-icon>
          </button>
          <button type="button" @click="toggleSidebarMobile" class="sidebar-mobile-toggle">
            <iconify-icon icon="heroicons:bars-3-solid" class="icon navbar-header-menu-icon"></iconify-icon>
          </button>
        </div>
      </div>

      <div class="navbar-header-right">
        <router-link 
          v-if="showCreatePropertyButton"
          to="/property-form" 
          class="btn btn-primary btn-sm create-property-btn navbar-create-listing d-flex align-items-center gap-1"
        >
          <i class="ri-add-line"></i>
          <span class="d-none d-sm-inline">Create Listing</span>
        </router-link>
        <NotificationBell 
          ref="notificationBell"
          :sound-enabled="soundEnabled"
          :browser-notifications-enabled="browserNotificationsEnabled"
          @toggle="handleNotificationToggle"
        />
        <div class="profile-trigger-wrap" ref="profileDropdown">
          <button 
            class="profile-avatar-btn rounded-circle" 
            type="button"
            @click.stop="openProfilePanel"
          >
            <img 
              v-if="user && user.avatar" 
              :src="user.avatar" 
              alt="User Avatar" 
              class="navbar-profile-img object-fit-cover rounded-circle"
            >
            <img 
              v-else 
              :src="userPlaceholder" 
              alt="User Avatar" 
              class="navbar-profile-img object-fit-cover rounded-circle"
            >
          </button>
        </div>
      </div>

      <!-- BIG Profile Details modal (like the picture) -->
      <Teleport to="body">
            <Transition name="profile-panel">
              <div v-if="isProfilePanelOpen" class="profile-panel-backdrop" @click="closeProfilePanel">
                <div ref="profilePanel" class="profile-panel" @click.stop>
                  <header class="profile-panel-header">
                    <h6 class="ui-h-section profile-panel-title">Profile Details</h6>
                    <button type="button" class="profile-panel-close" @click="closeProfilePanel" aria-label="Close">
                      <iconify-icon icon="lucide:x" class="icon"></iconify-icon>
                    </button>
                  </header>

                  <div class="profile-panel-body">
                    <div v-if="profileLoading" class="profile-panel-loading">
                      <span class="profile-panel-spinner"></span>
                      <span>Loading...</span>
                    </div>
                    <template v-else>
                    <div class="profile-summary-card">
                      <div class="profile-summary-left">
                        <div class="profile-avatar-wrap">
                          <img v-if="user && user.avatar" :src="user.avatar" alt="Avatar" class="profile-summary-avatar">
                          <img v-else :src="userPlaceholder" alt="Avatar" class="profile-summary-avatar">
                          <label class="profile-avatar-camera">
                            <input
                              ref="avatarInput"
                              type="file"
                              accept="image/*"
                              class="profile-avatar-file-input"
                              @change="onAvatarChange"
                            >
                            <iconify-icon icon="solar:camera-outline" class="icon"></iconify-icon>
                          </label>
                        </div>
                        <div class="profile-summary-info">
                          <h6 class="profile-summary-name">{{ user ? user.name : 'User' }}</h6>
                          <p class="profile-summary-email">{{ user && user.email ? user.email : '—' }}</p>
                          <p class="profile-summary-role">{{ user && user.role_name ? user.role_name : 'User' }}</p>
                        </div>
                      </div>
                      <div class="profile-summary-right">
                        <div class="profile-status-row">
                          <span class="profile-status-dot" :class="user && user.status === 'active' ? 'status-online' : 'status-offline'"></span>
                          <span class="profile-status-text">{{ user && user.status === 'active' ? 'Online' : 'Offline' }}</span>
                          <button type="button" class="profile-more-btn" aria-label="Options">
                            <iconify-icon icon="lucide:more-vertical" class="icon"></iconify-icon>
                          </button>
                        </div>
                        <p class="profile-last-active">Last Active : {{ lastActiveText }}</p>
                      </div>
                    </div>

                    <section class="profile-section profile-section-contact">
                      <div class="profile-section-head">
                        <h6 class="ui-h-mini profile-section-title">Contact Information</h6>
                        <template v-if="!isPersonalInfoEditing">
                          <button type="button" class="profile-edit-icon" aria-label="Edit" @click="startPersonalInfoEdit">
                            <iconify-icon icon="lucide:pencil" class="icon"></iconify-icon>
                          </button>
                        </template>
                        <div v-else class="profile-contact-actions">
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
                          <span class="profile-contact-value profile-contact-readonly">{{ user && user.role_name ? user.role_name : '—' }}</span>
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
                          <span v-else class="profile-contact-value">{{ user && user.email ? user.email : '—' }}</span>
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
                          <span v-else class="profile-contact-value">{{ user && user.phone ? user.phone : '—' }}</span>
                        </div>
                      </div>
                    </section>
                    <section class="profile-section profile-section-team">
                      <div class="profile-section-head">
                        <h6 class="ui-h-mini profile-section-title">Your Team</h6>
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

                    <div class="profile-panel-actions">
                      <router-link to="/view-profile" class="profile-action-link" @click="closeProfilePanel">
                        <iconify-icon icon="solar:user-linear" class="icon"></iconify-icon>
                        My Profile
                      </router-link>
                      <button type="button" class="profile-action-link profile-action-logout" @click="logout">
                        <iconify-icon icon="lucide:power" class="icon"></iconify-icon>
                        Log out
                      </button>
                    </div>
                    </template>
                  </div>
                </div>
              </div>
            </Transition>
          </Teleport>
    </div>
  </div>
</template>

<script setup>
import { useSidebar } from '@/composables/useSidebar.js';
import { ref, onMounted, computed, onUnmounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useTheme } from '@/composables/useTheme.js';
import NotificationBell from '@/components/NotificationBell.vue';
const userPlaceholder = '/assets/images/user.png';
const { isMobileOpen, openMobileSidebar } = useSidebar();
import api from '@/plugins/axios';

const { theme, toggleTheme } = useTheme();
const router = useRouter();

function goBack() {
  router.back();
}
const route = useRoute();
const user = ref(null);

// إعدادات الإشعارات
const soundEnabled = ref(true);
const browserNotificationsEnabled = ref(true);

// computed property للتحقق من الصلاحيات وعرض الزر
const showCreatePropertyButton = computed(() => {
  const allowedRoutes = [
    '/property-form',
    '/my-listing', 
    '/archive',
    '/alllisting',
    '/property-details',
    '/properties'
  ];
  
  return allowedRoutes.some(allowedRoute => 
    route.path.startsWith(allowedRoute.replace('/:id', '').replace('/:id?', ''))
  );
});

// BIG Profile Details panel (slide-in from right)
const isProfilePanelOpen = ref(false);
const profilePanel = ref(null);

const notificationBell = ref(null);
const profileDropdown = ref(null);

function openProfilePanel() {
  if (notificationBell.value && notificationBell.value.showDropdown) {
    notificationBell.value.closeNotifications();
  }
  if (isProfilePanelOpen.value) {
    closeProfilePanel();
    return;
  }
  isProfilePanelOpen.value = true;
}

function closeProfilePanel() {
  isProfilePanelOpen.value = false;
}

function handleNotificationToggle() {
  if (isProfilePanelOpen.value) {
    closeProfilePanel();
  }
}

const lastActiveText = computed(() => {
  const raw = user.value?.last_login_at || user.value?.last_active;
  if (!raw) return '—';
  const d = new Date(raw);
  const now = new Date();
  const diffMs = now - d;
  const diffMins = Math.floor(diffMs / 60000);
  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins} min ago`;
  const diffHours = Math.floor(diffMins / 60);
  if (diffHours < 24) return `${diffHours}h ago`;
  const diffDays = Math.floor(diffHours / 24);
  return `${diffDays}d ago`;
});

const memberSinceFormatted = computed(() => {
  const raw = user.value?.created_at || user.value?.member_since || user.value?.created_at;
  if (!raw) return '—';
  const d = new Date(raw);
  if (isNaN(d.getTime())) return raw;
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  const h = String(d.getHours()).padStart(2, '0');
  const min = String(d.getMinutes()).padStart(2, '0');
  return `${y}-${m}-${day} ${h}:${min}`;
});

const firstName = computed(() => {
  const u = user.value;
  if (!u?.name) return '—';
  const parts = (u.name || '').trim().split(/\s+/);
  return parts[0] || '—';
});
const lastName = computed(() => {
  const u = user.value;
  if (!u?.name) return '—';
  const parts = (u.name || '').trim().split(/\s+/);
  return parts.length > 1 ? parts.slice(1).join(' ') : '—';
});
const notificationLanguage = computed(() => {
  const u = user.value;
  return u?.notification_language || u?.locale || 'English';
});

// Personal Info edit mode (First Name, Last Name, Email, Phone)
const isPersonalInfoEditing = ref(false);
const personalInfoEdit = ref({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
});

function startPersonalInfoEdit() {
  const u = user.value;
  const parts = (u?.name || '').trim().split(/\s+/);
  personalInfoEdit.value = {
    first_name: parts[0] || '',
    last_name: parts.length > 1 ? parts.slice(1).join(' ') : '',
    email: u?.email || '',
    phone: u?.phone || '',
  };
  isPersonalInfoEditing.value = true;
}

function cancelPersonalInfoEdit() {
  isPersonalInfoEditing.value = false;
}

function savePersonalInfoEdit() {
  const u = user.value;
  if (!u) return;
  const first = (personalInfoEdit.value.first_name || '').trim();
  const last = (personalInfoEdit.value.last_name || '').trim();
  const fullName = [first, last].filter(Boolean).join(' ') || u.name;
  user.value = {
    ...u,
    name: fullName,
    email: (personalInfoEdit.value.email || '').trim() || u.email,
    phone: (personalInfoEdit.value.phone || '').trim() || u.phone,
  };
  try {
    localStorage.setItem('user', JSON.stringify(user.value));
  } catch (e) {
    console.warn('Could not persist user to localStorage', e);
  }
  isPersonalInfoEditing.value = false;
}

function teamMemberDisplayName(member) {
  if (member.first_name != null || member.last_name != null) {
    return [member.first_name, member.last_name].filter(Boolean).join(' ').trim() || member.name || '—';
  }
  return member.name || '—';
}

const avatarInput = ref(null);

function onAvatarChange(event) {
  const file = event.target?.files?.[0];
  if (!file || !file.type.startsWith('image/')) return;
  const reader = new FileReader();
  reader.onload = () => {
    const u = user.value;
    if (!u) return;
    user.value = { ...u, avatar: reader.result };
    try {
      localStorage.setItem('user', JSON.stringify(user.value));
    } catch (e) {
      console.warn('Could not persist user to localStorage', e);
    }
  };
  reader.readAsDataURL(file);
  event.target.value = '';
}

// Fetched from API: current user profile + team members (who is under this user by role)
const profileLoading = ref(false);
const fetchedTeamMembers = ref([]);

async function fetchProfileAndTeam() {
  const currentUser = user.value;
  if (!currentUser?.id) return;
  profileLoading.value = true;
  fetchedTeamMembers.value = [];
  try {
    const [profileRes, teamRes] = await Promise.all([
      api.get(`/users/${currentUser.id}`),
      api.get(`/users/${currentUser.id}/team-members/recursive`).catch(() => ({ data: { data: [] } })),
    ]);
    if (profileRes.data?.data) {
      const apiUser = profileRes.data.data;
      user.value = { ...currentUser, ...apiUser, name: apiUser.name ?? currentUser.name, email: apiUser.email ?? currentUser.email, phone: apiUser.phone ?? currentUser.phone, role_name: apiUser.role_name ?? currentUser.role_name, status: apiUser.status ?? currentUser.status, created_at: apiUser.created_at ?? currentUser.created_at, avatar: apiUser.avatar ?? currentUser.avatar };
    }
    const list = teamRes.data?.data;
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
      }));
    }
  } catch (e) {
    console.warn('Profile/team fetch failed:', e);
  } finally {
    profileLoading.value = false;
  }
}

const teamMembersList = computed(() => {
  if (fetchedTeamMembers.value.length > 0) return fetchedTeamMembers.value;
  const list = user.value?.team_members || [];
  if (Array.isArray(list) && list.length) return list;
  return [];
});

const teamPageSize = 6;
const visibleTeamCount = ref(teamPageSize);

const visibleTeamMembers = computed(() => teamMembersList.value.slice(0, visibleTeamCount.value));

const hasMoreTeamMembers = computed(() => teamMembersList.value.length > visibleTeamCount.value);

function loadMoreTeamMembers() {
  visibleTeamCount.value += teamPageSize;
}

function showAllTeamMembers() {
  visibleTeamCount.value = teamMembersList.value.length;
}

// إعداد listener للنقر خارج dropdowns
function setupClickOutsideListener() {
  document.addEventListener('click', handleClickOutside);
}

function handleClickOutside(event) {
  // Notification dropdown: handled inside NotificationBell (Teleport + refs); avoid broken notificationDropdown ref here.

  if (isProfilePanelOpen.value && profilePanel.value && !profilePanel.value.contains(event.target) && profileDropdown.value && !profileDropdown.value.contains(event.target)) {
    closeProfilePanel();
  }
}

watch(isProfilePanelOpen, (open) => {
  if (open && user.value?.id) {
    visibleTeamCount.value = teamPageSize;
    fetchProfileAndTeam();
  }
});

// تحميل بيانات المستخدم والإعدادات
onMounted(() => {
  loadUserData();
  loadNotificationSettings();
  setupClickOutsideListener();
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});

function loadUserData() {
  const userData = localStorage.getItem('user');
  if (userData) {
    user.value = JSON.parse(userData);
  }
}

function loadNotificationSettings() {
  const soundSetting = localStorage.getItem('notification_sound');
  const browserSetting = localStorage.getItem('browser_notifications');
  
  if (soundSetting !== null) {
    soundEnabled.value = JSON.parse(soundSetting);
  }
  
  if (browserSetting !== null) {
    browserNotificationsEnabled.value = JSON.parse(browserSetting);
  }
}

function toggleSound() {
  localStorage.setItem('notification_sound', soundEnabled.value);
  console.log('🔊 Sound setting:', soundEnabled.value ? 'Enabled' : 'Disabled');
}

function toggleBrowserNotifications() {
  if (browserNotificationsEnabled.value) {
    if ('Notification' in window && Notification.permission === 'default') {
      Notification.requestPermission().then(permission => {
        if (permission === 'granted') {
          localStorage.setItem('browser_notifications', 'true');
          console.log('📱 Browser notifications enabled');
        } else {
          browserNotificationsEnabled.value = false;
          console.log('📱 Browser notifications permission denied');
        }
      });
    } else if (Notification.permission === 'denied') {
      browserNotificationsEnabled.value = false;
      console.log('📱 Browser notifications blocked by user');
    } else {
      localStorage.setItem('browser_notifications', 'true');
      console.log('📱 Browser notifications enabled');
    }
  } else {
    localStorage.setItem('browser_notifications', 'false');
    console.log('📱 Browser notifications disabled');
  }
}

function toggleSidebarMobile() {
  document.querySelector('.sidebar')?.classList.add('sidebar-open');
  document.body.classList.add('overlay-active');
}

function logout() {
  localStorage.removeItem('token');
  localStorage.removeItem('user');
  sessionStorage.removeItem('token');
  router.push('/sign-in');
}

const showBackButton = computed(() => {
  const hiddenRoutes = ['/kanban', '/kanban_deal'];

  return !hiddenRoutes.some(r => route.path.startsWith(r));
});
</script>

<style scoped>
/* Glass bar: z-index 1000 = below sidebar (1100+), below modals (global) */
.navbar-header {
  background: rgba(255, 255, 255, 0.1) !important;
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border-bottom: 1px solid rgba(255, 255, 255, 0.15);
  height: var(--app-topbar-height, 3.25rem);
  min-height: var(--app-topbar-height, 3.25rem);
  padding: 0.25rem 0.75rem;
  box-sizing: border-box;
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  /* Header stays below sidebar */
  z-index: 99 !important;
  pointer-events: auto;
  display: flex;
  align-items: center;
}

.navbar-header-toolbar {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: center;
  width: 100%;
  gap: 0.5rem;
  min-height: 0;
  flex: 1 1 auto;
}

.navbar-header-left {
  justify-self: start;
  min-width: 0;
}

.navbar-header-right {
  justify-self: end;
  min-width: 0;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: nowrap;
  gap: 0.375rem;
  position: relative;
  z-index: 2;
  pointer-events: auto;
}

.navbar-header-back-icon {
  font-size: 1.125rem !important;
  width: 1.125rem;
  height: 1.125rem;
}

.navbar-header-menu-icon {
  font-size: 1.05rem !important;
  width: 1.125rem;
  height: 1.125rem;
}

.navbar-profile-img {
  width: 2rem;
  height: 2rem;
}

.navbar-create-listing {
  padding: 0.5rem 0.75rem !important;
  min-height: 40px;
  font-size: 0.75rem !important;
  line-height: 1.2;
}

.sidebar-toggle {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 36px;
}

.sidebar-mobile-toggle.menu-btn-with-label {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.sidebar-mobile-toggle .menu-btn-label {
  max-width: 0;
  overflow: hidden;
  opacity: 0;
  white-space: nowrap;
  transition: max-width 0.2s ease, opacity 0.2s ease;
  font-size: 0.875rem;
  font-weight: 500;
}

.sidebar-mobile-toggle:hover .menu-btn-label {
  max-width: 200px;
  opacity: 1;
}

.w-40-px {
  width: 40px;
}

.h-40-px {
  height: 40px;
}

.dropdown-menu.show {
  display: block;
  position: absolute;
  top: 100%;
  right: 0;
  left: auto;
  margin-top: 0.5rem;
  z-index: 10050 !important;
}

.hover-text-danger:hover {
  color: #ef4444 !important;
}

.create-property-btn {
  padding: 8px 16px;
  font-size: 14px;
  font-weight: 500;
  border-radius: 6px;
  text-decoration: none;
  transition: all 0.3s ease;
}

.create-property-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* Profile trigger */
.profile-trigger-wrap {
  position: relative;
  z-index: 1;
}

.profile-avatar-btn {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 0;
  border: none;
  background: transparent;
  cursor: pointer;
}

/* BIG Profile Details panel – match design image (font sizes, spacing, colors) */
.profile-panel-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  /* Below Bootstrap modals (100600+); above notification portal (10050) */
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

/* Summary card – like design image */
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

.profile-avatar-camera .icon {
  font-size: 20px;
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
  letter-spacing: -0.01em;
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

.profile-more-btn {
  width: 32px;
  height: 32px;
  border: none;
  background: transparent;
  color: #6b7280;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.profile-more-btn:hover {
  background: #e5e7eb;
  color: #111827;
}

.profile-more-btn .icon {
  font-size: 20px;
}

.profile-last-active {
  margin: 0;
  font-size: 14px;
  color: #9ca3af;
}

/* Sections – base */
.profile-section {
  margin-bottom: 0;
}

.profile-section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

/* Contact Information – no blue borders */
.profile-section-contact {
  margin-bottom: 28px;
  padding: 20px 22px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
}

.profile-section-contact .profile-section-head {
  margin-bottom: 18px;
}

/* Your Team – no blue borders */
.profile-section-team {
  padding: 20px 22px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
}

.profile-section-team .profile-section-head {
  margin-bottom: 16px;
}

.profile-section-title {
  margin: 0 !important;
  font-size: 14px !important;
  font-weight: 600 !important;
  color: #111827 !important;
  letter-spacing: -0.01em !important;
  line-height: 1.3 !important;
}

.profile-section-badge {
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  background: #e5e7eb;
  padding: 4px 8px;
  border-radius: 4px;
  letter-spacing: 0.02em;
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

.profile-edit-icon .icon {
  font-size: 20px;
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

.profile-contact-readonly {
  color: #374151;
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
  line-height: 1.3;
}

.profile-contact-value {
  font-size: 15px;
  color: #111827;
  font-weight: 500;
  line-height: 1.45;
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
  transition: border-color 0.2s;
}

.profile-contact-input:focus {
  border-color: #60a5fa;
}

.profile-contact-input::placeholder {
  color: #9ca3af;
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

.profile-phone-link {
  color: #2563eb;
  text-decoration: none;
  font-size: 15px;
  font-weight: 500;
}

.profile-phone-link:hover {
  text-decoration: underline;
}

/* Your Team – 3-column grid, oval pill cards, avatar + name + role, status dot */
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

.profile-team-agent-icon .icon {
  font-size: 20px;
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

.profile-show-all-team {
  font-size: 14px;
  font-weight: 600;
  color: #2563eb;
  text-decoration: none;
}

.profile-show-all-team:hover {
  text-decoration: underline;
}

.profile-team-empty {
  margin: 0;
  font-size: 14px;
  color: #6b7280;
  padding: 12px 0;
}

/* Footer actions – My Profile / Log out */
.profile-panel-actions {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding-top: 14px;
  border-top: 1px solid #e5e7eb;
  margin-top: 6px;
}

.profile-action-link {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  font-size: 14px;
  font-weight: 500;
  color: #111827;
  text-decoration: none;
  border: none;
  background: none;
  cursor: pointer;
  text-align: left;
  border-radius: 8px;
  transition: background 0.2s, color 0.2s;
}

.profile-action-link:hover {
  background: #f3f4f6;
  color: #2563eb;
}

.profile-action-link .icon {
  font-size: 20px;
  flex-shrink: 0;
}

.profile-action-logout:hover {
  color: #dc2626;
}

/* Panel transition */
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

@media (max-width: 768px) {
  .sidebar-mobile-toggle {
    display: none !important;
  }
}
</style>