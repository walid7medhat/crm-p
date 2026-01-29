<template>
  <div class="navbar-header">
    <div class="row align-items-center justify-content-between">
      <div class="col-auto">
        <div class="d-flex flex-wrap align-items-center gap-4">

          <button type="button" @click="toggleSidebarMobile" class="sidebar-mobile-toggle">
            <iconify-icon icon="heroicons:bars-3-solid" class="icon"></iconify-icon>
          </button>
        </div>
      </div>
      <div class="col-auto">
        <div class="d-flex flex-wrap align-items-center gap-3">
          <!-- زر Create Property - يظهر فقط في صفحات العقارات -->
          <router-link 
            v-if="showCreatePropertyButton"
            to="/property-form" 
            class="btn btn-primary btn-sm create-property-btn d-flex align-items-center gap-2"
          >
            <i class="ri-add-line"></i>
            Create Property
          </router-link>

          <!-- Notification dropdown -->
          <NotificationBell 
            ref="notificationBell"
            :sound-enabled="soundEnabled"
            :browser-notifications-enabled="browserNotificationsEnabled"
            @toggle="handleNotificationToggle"
          />

          <!-- Profile dropdown -->
          <div class="dropdown" ref="profileDropdown">
            <button 
              class="d-flex justify-content-center align-items-center rounded-circle" 
              type="button"
              @click.stop="toggleProfileDropdown"
            >
              <img 
                v-if="user && user.avatar" 
                :src="user.avatar" 
                alt="User Avatar" 
                class="w-40-px h-40-px object-fit-cover rounded-circle"
              >
              <img 
                v-else 
                :src="userPlaceholder" 
                alt="User Avatar" 
                class="w-40-px h-40-px object-fit-cover rounded-circle"
              >
            </button>
            <div 
              class="dropdown-menu to-top dropdown-menu-sm"
              :class="{ 'show': isProfileDropdownOpen }"
            >
              <div
                class="py-12 px-16 radius-8 bg-primary-50 mb-16 d-flex align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-3">
                  <img 
                    v-if="user && user.avatar" 
                    :src="user.avatar" 
                    alt="User Avatar" 
                    class="w-40-px h-40-px object-fit-cover rounded-circle"
                  >
                  <img 
                    v-else 
                    :src="userPlaceholder" 
                    alt="User Avatar" 
                    class="w-40-px h-40-px object-fit-cover rounded-circle"
                  >
                  <div>
                    <h6 class="text-lg text-primary-light fw-semibold mb-2">
                      {{ user ? user.name : 'User' }}
                    </h6>
                    <span class="text-secondary-light fw-medium text-sm">
                      {{ user && user.role_name  ? user.role_name : 'User' }}
                    </span>
                  </div>
                </div>
                <button 
                  type="button" 
                  class="hover-text-danger"
                  @click.stop="closeProfileDropdown"
                >
                  <iconify-icon icon="radix-icons:cross-1" class="icon text-xl"></iconify-icon>
                </button>
              </div>
              <ul class="to-top-list">
                <li>
                  <router-link
                    class="dropdown-item text-black px-0 py-8 hover-bg-transparent hover-text-primary d-flex align-items-center gap-3"
                    to="/view-profile"
                    @click="closeProfileDropdown">
                    <iconify-icon icon="solar:user-linear" class="icon text-xl"></iconify-icon> 
                    My Profile
                  </router-link>
                </li>
                <li>
                  <a
                    class="dropdown-item text-black px-0 py-8 hover-bg-transparent hover-text-danger d-flex align-items-center gap-3"
                    href="javascript:void(0)"
                    @click="logout"
                  >
                    <iconify-icon icon="lucide:power" class="icon text-xl"></iconify-icon> 
                    Log out
                  </a>
                </li>
              </ul>
            </div>
          </div><!-- Profile dropdown end -->
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, onUnmounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useTheme } from '@/composables/useTheme.js';
import NotificationBell from '@/components/NotificationBell.vue';
import userPlaceholder from '@/assets/images/user.png';

const { theme, toggleTheme } = useTheme();
const isSidebarActive = ref(false);
const router = useRouter();
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

// حالة dropdowns
const isProfileDropdownOpen = ref(false);

// مراجع للـ dropdowns
const notificationBell = ref(null);
const profileDropdown = ref(null);

// إدارة dropdown الملف الشخصي
function toggleProfileDropdown() {
  // إغلاق dropdown الإشعارات إذا كان مفتوحًا
  if (notificationBell.value && notificationBell.value.showDropdown) {
    notificationBell.value.closeNotifications();
  }
  
  isProfileDropdownOpen.value = !isProfileDropdownOpen.value;
}

function closeProfileDropdown() {
  isProfileDropdownOpen.value = false;
}

function handleNotificationToggle() {
  // إغلاق dropdown الملف الشخصي إذا كان مفتوحًا
  if (isProfileDropdownOpen.value) {
    closeProfileDropdown();
  }
}

// إعداد listener للنقر خارج dropdowns
function setupClickOutsideListener() {
  document.addEventListener('click', handleClickOutside);
}

function handleClickOutside(event) {
  // التحقق إذا كان النقر خارج dropdown الإشعارات
  if (notificationBell.value && 
      notificationBell.value.notificationDropdown && 
      !notificationBell.value.notificationDropdown.contains(event.target) &&
      notificationBell.value.showDropdown) {
    notificationBell.value.closeNotifications();
  }
  
  // التحقق إذا كان النقر خارج dropdown الملف الشخصي
  if (profileDropdown.value && !profileDropdown.value.contains(event.target) && isProfileDropdownOpen.value) {
    closeProfileDropdown();
  }
}

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

function toggleSidebarDesktop() {
  isSidebarActive.value = !isSidebarActive.value;
  document.querySelector('.sidebar')?.classList.toggle('active');
  document.querySelector('.dashboard-main')?.classList.toggle('active');
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
</script>

<style scoped>
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
  z-index: 1000;
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
</style>