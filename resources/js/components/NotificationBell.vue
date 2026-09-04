<template>
  <div class="notification-wrapper">
    <button
      ref="bellBtn"
      class="notification-bell-btn"
      type="button"
      aria-label="Open notifications"
      @click.stop="toggleNotifications"
    >
      <iconify-icon icon="lucide:bell" class="notification-bell-icon" />
      <span v-if="unreadCount > 0" class="notification-badge">{{ formatUnreadCount }}</span>
    </button>

    <Teleport to="body">
      <section
        v-if="showDropdown"
        ref="dropdownPortal"
        class="notification-dropdown"
        :style="dropdownPosition"
        @click.stop
      >
        <header class="dropdown-head">
          <div>
            <h6 class="dropdown-title mb-0">Notifications</h6>
            <p class="dropdown-sub mb-0">{{ unreadCount }} unread</p>
          </div>
          <button class="icon-btn" type="button" @click="showDropdown = false">
            <iconify-icon icon="lucide:x" />
          </button>
        </header>

        <div class="dropdown-list scroll-sm">
          <div v-if="notifications.length === 0" class="empty-state">No notifications yet</div>
          <article
            v-for="notification in notifications.slice(0, 10)"
            :key="notification.id"
            class="dropdown-item"
            :class="{ unread: !notification.read_at }"
            @click="handleNotificationClick(notification)"
          >
            <p class="item-message mb-1">{{ getMessage(notification) }}</p>
            <span class="item-time">{{ formatTime(notification.created_at) }}</span>
          </article>
        </div>

        <footer class="dropdown-foot">
          <router-link to="/notifications" class="foot-link" @click="showDropdown = false">
            Open notifications page
          </router-link>
        </footer>
      </section>
    </Teleport>
  </div>
</template>

<script>
import api from '@/plugins/axios'
import { openLeadFromNotification } from '@/composables/useLeadViewModal.js'

function getApiBaseUrl() {
  const base =
    (api.defaults && api.defaults.baseURL) ||
    (typeof window !== 'undefined' && window.__API_BASE_URL__) ||
    import.meta.env.VITE_API_BASE_URL ||
    import.meta.env.VITE_API_URL ||
    'http://127.0.0.1:8001/api'
  return String(base).replace(/\/$/, '')
}

export default {
  name: 'NotificationBell',
  data() {
    return {
      notifications: [],
      unreadCount: 0,
      showDropdown: false,
      userId: null,
      dropdownPosition: {},
    }
  },
  computed: {
    formatUnreadCount() {
      if (this.unreadCount > 99) return '99+'
      if (this.unreadCount > 9) return '9+'
      return this.unreadCount
    },
  },
  watch: {
    showDropdown(val) {
      if (val) {
        this.$nextTick(() => {
          this.positionDropdown()
          document.addEventListener('click', this.handleClickOutside, true)
          window.addEventListener('resize', this.positionDropdown)
        })
      } else {
        document.removeEventListener('click', this.handleClickOutside, true)
        window.removeEventListener('resize', this.positionDropdown)
      }
    },
  },
  mounted() {
    const user = localStorage.getItem('user')
    this.userId = user ? JSON.parse(user).id : null
    this.fetchNotifications()
  },
  beforeUnmount() {
    document.removeEventListener('click', this.handleClickOutside, true)
    window.removeEventListener('resize', this.positionDropdown)
  },
  methods: {
    positionDropdown() {
      const btn = this.$refs.bellBtn
      if (!btn) return
      const rect = btn.getBoundingClientRect()
      const width = Math.min(420, window.innerWidth - 16)
      this.dropdownPosition = {
        position: 'fixed',
        top: `${Math.round(rect.bottom + 8)}px`,
        left: `${Math.round(Math.max(8, rect.right - width))}px`,
        width: `${width}px`,
        zIndex: 10050,
      }
    },
    toggleNotifications() {
      this.showDropdown = !this.showDropdown
      if (this.showDropdown) this.fetchNotifications()
    },
    handleClickOutside(event) {
      if (!this.showDropdown) return
      const path = typeof event.composedPath === 'function' ? event.composedPath() : [event.target]
      if (path.includes(this.$refs.bellBtn) || path.includes(this.$refs.dropdownPortal)) return
      this.showDropdown = false
    },
    async fetchNotifications() {
      try {
        const token = localStorage.getItem('token') || sessionStorage.getItem('token')
        const response = await fetch(`${getApiBaseUrl()}/auth/notifications`, {
          headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
        })
        if (!response.ok) return
        const data = await response.json()
        this.notifications = data.data || []
        this.unreadCount = this.notifications.filter((n) => !n.read_at).length
      } catch (e) {
        console.error('Notification fetch failed', e)
      }
    },
    getMessage(notification) {
      return notification?.data?.message || 'New notification'
    },
    async markAsRead(id) {
      try {
        const token = localStorage.getItem('token') || sessionStorage.getItem('token')
        const response = await fetch(`${getApiBaseUrl()}/auth/notifications/${id}/read`, {
          method: 'POST',
          headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
        })
        if (response.ok) {
          const n = this.notifications.find((i) => i.id === id)
          if (n && !n.read_at) {
            n.read_at = new Date().toISOString()
            this.unreadCount = Math.max(0, this.unreadCount - 1)
          }
        }
      } catch {}
    },
    handleNotificationClick(notification) {
      if (!notification.read_at) this.markAsRead(notification.id)
      this.showDropdown = false

      if (openLeadFromNotification(notification)) {
        return
      }

      const type = notification.type || notification?.data?.notification_type

      if (type === 'App\\Notifications\\BirthdayColleagueNotification') {
        const d = notification.data || {}
        if (typeof window !== 'undefined' && typeof window.__openPropertyChat === 'function') {
          window.__openPropertyChat(
            { id: d.birthday_user_id, name: d.birthday_user_name, avatar: d.birthday_user_avatar },
            null,
            null,
          )
        }
        return
      }

      if (type === 'App\\Notifications\\EvaluationCompletedHrNotification') {
        const employeeId = notification?.data?.employee_id
        if (employeeId) this.$router.push(`/hr/employees/${employeeId}`)
        return
      }

      if (['request', 'approved', 'rejected'].includes(type)) {
        this.$router.push('/my-requests')
      } else if (notification?.data?.property_id) {
        this.$router.push(`/property-details/${notification.data.property_id}`)
      } else if (notification?.data?.listing_id) {
        this.$router.push(`/property-details/${notification.data.listing_id}`)
      } else if (type === 'App\\Notifications\\HotDealRequestNotification') {
        this.$router.push('/hotDeal-requests')
      }
    },
    formatTime(timestamp) {
      if (!timestamp) return 'Unknown time'
      const date = new Date(timestamp)
      const diff = Date.now() - date.getTime()
      const mins = Math.floor(diff / 60000)
      const hours = Math.floor(diff / 3600000)
      const days = Math.floor(diff / 86400000)
      if (mins < 1) return 'Just now'
      if (mins < 60) return `${mins} min ago`
      if (hours < 24) return `${hours} hr ago`
      if (days < 7) return `${days} days ago`
      return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
    },
  },
}
</script>

<style scoped>
.notification-wrapper {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  position: relative;
  z-index: 3;
}

.notification-bell-btn {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: 1px solid #dbe2ea;
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
  box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  line-height: 0;
}

.notification-bell-icon {
  width: 16px;
  height: 16px;
  font-size: 16px;
  color: #334155;
  display: block;
}

.notification-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  min-width: 15px;
  height: 15px;
  border-radius: 999px;
  background: #ef4444;
  color: #fff;
  font-size: 9px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 2px solid #fff;
  padding: 0 4px;
}

.notification-dropdown {
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  background: #fff;
  box-shadow: 0 20px 42px rgba(15, 23, 42, 0.16);
  overflow: hidden;
}

.dropdown-head {
  padding: 12px 14px;
  border-bottom: 1px solid #eef2f7;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.dropdown-title {
  font-size: 13px;
  font-weight: 700;
}

.dropdown-sub {
  font-size: 11px;
  color: #64748b;
}

.icon-btn {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  border: 1px solid #e2e8f0;
  background: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 0;
  padding: 0;
}

.dropdown-list {
  max-height: 340px;
  overflow-y: auto;
  padding: 10px;
}

.dropdown-item {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 10px;
  margin-bottom: 8px;
  cursor: pointer;
  overflow: hidden;
}

.dropdown-item.unread {
  background: #f8fbff;
  border-color: #bfdbfe;
}

.item-message {
  font-size: 11px;
  color: #334155;
  white-space: normal;
    word-break: break-word;
}

.item-time {
  font-size: 10px;
  color: #94a3b8;
}

.dropdown-foot {
  border-top: 1px solid #eef2f7;
  padding: 10px 14px;
  text-align: center;
}

.foot-link {
  font-size: 12px;
  color: #2563eb;
  font-weight: 600;
  text-decoration: none;
}

.empty-state {
  padding: 22px 10px;
  text-align: center;
  font-size: 12px;
  color: #64748b;
}
</style>