<template>
  <div class="notifications-page">
    <section class="notifications-shell">
      <header class="notifications-header">
        <div>
          <h6 class="notifications-title mb-1">All Notifications</h6>
          <p class="notifications-subtitle mb-0">
            {{ unreadCount }} unread of {{ totalCount }} notifications
          </p>
        </div>
        <div class="notifications-header-actions">
          <button
            v-if="unreadCount > 0"
            type="button"
            class="btn-compact btn-compact-primary"
            @click="markAllAsRead"
          >
            Mark all read
          </button>
          <button
            type="button"
            class="btn-compact btn-compact-outline"
            @click="refreshNotifications"
          >
            <iconify-icon icon="solar:refresh-outline" />
            Refresh
          </button>
        </div>
      </header>

      <div class="notifications-toolbar">
        <div class="search-wrap">
          <iconify-icon icon="lucide:search" class="search-icon" />
          <input
            v-model="searchQuery"
            type="text"
            class="search-input"
            placeholder="Search by responsible person, action type, or keyword..."
          />
          <button
            v-if="searchQuery"
            type="button"
            class="search-clear"
            @click="searchQuery = ''"
          >
            <iconify-icon icon="lucide:x" />
          </button>
        </div>

        <div class="feed-toggle">
          <button
            type="button"
            class="feed-toggle-btn"
            :class="{ active: activeFeedTab === 'all' }"
            @click="activeFeedTab = 'all'"
          >
            All Notifications
          </button>
          <button
            type="button"
            class="feed-toggle-btn"
            :class="{ active: activeFeedTab === 'unread' }"
            @click="activeFeedTab = 'unread'"
          >
            Unread
          </button>
        </div>

        <div class="chip-row">
          <button
            v-for="chip in notificationTypeChips"
            :key="chip.id"
            type="button"
            class="chip-btn"
            :class="{ active: activeTypeFilter === chip.id }"
            @click="activeTypeFilter = chip.id"
          >
            {{ chip.label }}
          </button>
        </div>
      </div>

      <section class="notifications-content">
        <div v-if="loading" class="state-block">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="state-text mt-2">Loading notifications...</p>
        </div>

        <div v-else-if="filteredNotifications.length === 0" class="state-block">
          <iconify-icon icon="solar:bell-off-outline" class="state-icon"></iconify-icon>
          <h6 class="state-title mb-1">No matching notifications</h6>
          <p class="state-text mb-0">Try changing filters or search terms.</p>
        </div>

        <div v-else class="notifications-grid">
          <article
            v-for="notification in paginatedNotifications"
            :key="notification.id"
            class="notification-card"
            :class="{ unread: !notification.read_at }"
            @click="handleNotificationClick(notification)"
          >
            <div class="notification-avatar">
              <img
                v-if="getAvatarUrl(notification)"
                :src="getAvatarUrl(notification)"
                alt="Avatar"
              />
              <span v-else>{{ getInitials(getResponsibleName(notification)) }}</span>
            </div>
            <div class="notification-body">
              <div class="notification-row">
                <span v-if="getResponsibleName(notification)" class="notification-person highlight-person">{{ getResponsibleName(notification) }}</span>
                <span class="notification-time">{{ formatTime(notification.created_at) }}</span>
              </div>
              <p class="notification-message mb-2">{{ getMessage(notification) }}</p>
              <div class="notification-meta">
                <span class="type-pill">{{ getTypeLabel(getTypeKey(notification)) }}</span>
                <span v-if="!notification.read_at" class="unread-pill">Unread</span>
              </div>
            </div>
            <div class="notification-actions" @click.stop>
              <button
                v-if="!notification.read_at"
                type="button"
                class="icon-btn icon-btn-read"
                title="Mark as read"
                @click="markAsRead(notification.id)"
              >
                <iconify-icon icon="solar:check-read-outline"></iconify-icon>
              </button>
              <button
                type="button"
                class="icon-btn icon-btn-delete"
                title="Delete notification"
                @click="deleteNotification(notification.id)"
              >
                <iconify-icon icon="solar:trash-bin-trash-outline"></iconify-icon>
              </button>
            </div>
          </article>
        </div>
      </section>

      <div v-if="totalPages > 1" class="pagination-wrap">
        <button
          type="button"
          class="btn-compact btn-compact-outline"
          :disabled="uiPage <= 1 || loading"
          @click="changePage(uiPage - 1)"
        >
          Previous
        </button>

        <button
          v-for="page in paginationPages"
          :key="`page-${page}`"
          type="button"
          class="page-btn"
          :class="{ active: page === uiPage }"
          :disabled="loading"
          @click="changePage(page)"
        >
          {{ page }}
        </button>

        <button
          type="button"
          class="btn-compact btn-compact-outline"
          :disabled="uiPage >= totalPages || loading"
          @click="changePage(uiPage + 1)"
        >
          Next
        </button>
      </div>
    </section>
  </div>
</template>

<script>
export default {
  name: 'AllNotifications',
  data() {
    return {
      notifications: [],
      loading: false,
      loadingMore: false,
      currentPage: 1,
      lastPage: 1,
      hasMore: false,
      uiPage: 1,
      pageSize: 12,
      unreadCount: 0,
      totalCount: 0,
      searchQuery: '',
      activeFeedTab: 'all',
      activeTypeFilter: 'all',
      apiBaseUrl:
        (typeof window !== 'undefined' && window.__API_BASE_URL__) ||
        import.meta.env.VITE_API_URL ||
        import.meta.env.VITE_API_BASE_URL ||
        'http://127.0.0.1:8001/api',
    }
  },
  mounted() {
    this.fetchNotifications()
  },
  computed: {
    notificationTypeChips() {
      const unique = new Map()
      this.notifications.forEach((n) => {
        const key = this.getTypeKey(n)
        if (!unique.has(key)) unique.set(key, this.getTypeLabel(key))
      })
      const chips = [{ id: 'all', label: 'All Types' }]
      unique.forEach((label, id) => chips.push({ id, label }))
      return chips.slice(0, 8)
    },
    filteredNotifications() {
      let list = Array.isArray(this.notifications) ? [...this.notifications] : []

      if (this.activeFeedTab === 'unread') {
        list = list.filter((n) => !n.read_at)
      }
      if (this.activeTypeFilter !== 'all') {
        list = list.filter((n) => this.getTypeKey(n) === this.activeTypeFilter)
      }

      const query = String(this.searchQuery || '').trim().toLowerCase()
      if (!query) return list

      return list.filter((n) => {
        const text = [
          this.getMessage(n),
          this.getResponsibleName(n),
          this.getTypeLabel(this.getTypeKey(n)),
        ]
          .join(' ')
          .toLowerCase()
        return text.includes(query)
      })
    },
    totalPages() {
      const total = this.filteredNotifications.length
      return Math.max(1, Math.ceil(total / this.pageSize))
    },
    paginatedNotifications() {
      const start = (this.uiPage - 1) * this.pageSize
      const end = start + this.pageSize
      return this.filteredNotifications.slice(start, end)
    },
    paginationPages() {
      const total = this.totalPages || 1
      const current = this.uiPage || 1
      const start = Math.max(1, current - 2)
      const end = Math.min(total, start + 4)
      const pages = []
      for (let i = start; i <= end; i += 1) pages.push(i)
      return pages
    },
  },
  watch: {
    searchQuery() {
      this.uiPage = 1
    },
    activeFeedTab() {
      this.uiPage = 1
    },
    activeTypeFilter() {
      this.uiPage = 1
    },
    notifications() {
      if (this.uiPage > this.totalPages) {
        this.uiPage = this.totalPages
      }
    },
  },
  methods: {
    async fetchNotifications(page = 1) {
      try {
        this.loading = page === 1
        const token = localStorage.getItem('token') || sessionStorage.getItem('token')
        const response = await fetch(`${this.apiBaseUrl}/auth/notifications?page=${page}`, {
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
          },
        })

        if (!response.ok) {
          this.$showNotification?.('Failed to load notifications', 'error')
          return
        }

        const data = await response.json()
        const notificationsData = Array.isArray(data?.data)
          ? data.data
          : Array.isArray(data)
            ? data
            : Array.isArray(data?.notifications)
              ? data.notifications
              : []

        this.notifications = notificationsData

        const localUnread = this.notifications.filter((n) => !n.read_at).length
        this.currentPage = data.meta?.current_page || page
        this.lastPage = data.meta?.last_page || 1
        this.hasMore = this.currentPage < this.lastPage
        this.unreadCount = data.meta?.unread_count ?? localUnread
        this.totalCount = data.meta?.total ?? this.notifications.length
      } catch (error) {
        this.$showNotification?.('Network error while loading notifications', 'error')
      } finally {
        this.loading = false
      }
    },

    changePage(page) {
      if (page < 1 || page > this.totalPages || page === this.uiPage) return
      this.uiPage = page
    },

    async refreshNotifications() {
      await this.fetchNotifications(1)
      this.$showNotification?.('Notifications refreshed', 'success')
    },

    async markAsRead(id) {
      try {
        const token = localStorage.getItem('token') || sessionStorage.getItem('token')
        const response = await fetch(`${this.apiBaseUrl}/auth/notifications/${id}/read`, {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
          },
        })

        if (!response.ok) {
          this.$showNotification?.('Failed to mark notification as read', 'error')
          return
        }

        const notification = this.notifications.find((n) => n.id === id)
        if (notification && !notification.read_at) {
          notification.read_at = new Date().toISOString()
          this.unreadCount = Math.max(0, this.unreadCount - 1)
        }
      } catch {
        this.$showNotification?.('Error marking notification as read', 'error')
      }
    },

    async markAllAsRead() {
      try {
        const token = localStorage.getItem('token') || sessionStorage.getItem('token')
        const response = await fetch(`${this.apiBaseUrl}/auth/notifications/read-all`, {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
          },
        })
        if (!response.ok) {
          this.$showNotification?.('Failed to mark all notifications as read', 'error')
          return
        }
        this.notifications.forEach((n) => {
          n.read_at = n.read_at || new Date().toISOString()
        })
        this.unreadCount = 0
      } catch {
        this.$showNotification?.('Error marking all notifications as read', 'error')
      }
    },

    async deleteNotification(id) {
      if (!confirm('Are you sure you want to delete this notification?')) return
      try {
        const token = localStorage.getItem('token') || sessionStorage.getItem('token')
        const response = await fetch(`${this.apiBaseUrl}/auth/notifications/${id}`, {
          method: 'DELETE',
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
          },
        })
        if (!response.ok) {
          this.$showNotification?.('Failed to delete notification', 'error')
          return
        }
        const notification = this.notifications.find((n) => n.id === id)
        if (notification && !notification.read_at) {
          this.unreadCount = Math.max(0, this.unreadCount - 1)
        }
        this.notifications = this.notifications.filter((n) => n.id !== id)
        this.totalCount = Math.max(0, this.totalCount - 1)
      } catch {
        this.$showNotification?.('Error deleting notification', 'error')
      }
    },

    handleNotificationClick(notification) {
      if (!notification.read_at) this.markAsRead(notification.id)
      const type = notification.type || notification.data?.notification_type

      if (['request', 'approved', 'rejected'].includes(type)) {
        this.$router.push('/my-requests')
      } else if (type === 'new_sales_agent') {
        this.$router.push('/users')
      } else if (type === 'request_cancelled' && notification.data?.property_id) {
        this.$router.push(`/property-details/${notification.data.property_id}`)
      } else if (['property_assigned', 'property_unassigned'].includes(type) && notification.data?.property_id) {
        this.$router.push(`/property-details/${notification.data.property_id}`)
      } else if (notification.data?.listing_id && type !== 'App\\Notifications\\HotDealRequestNotification') {
        this.$router.push(`/property-details/${notification.data.listing_id}`)
      } else if (notification.data?.property_id) {
        this.$router.push(`/property-details/${notification.data.property_id}`)
      } else if (type === 'App\\Notifications\\LeadUpdatedNotification') {
        this.$router.push('/kanban')
      } else if (type === 'App\\Notifications\\DealUpdatedNotificatio') {
        this.$router.push('/kanban')
      } else if (type === 'App\\Notifications\\NewListingMatchedNotification') {
        this.$router.push(`/property-details/${notification.data.listing_id}`)
      } else if (type === 'App\\Notifications\\HotDealRequestNotification') {
        this.$router.push('/hotDeal-requests')
      }
    },

    getMessage(notification) {
      return (
        notification?.data?.message ||
        notification?.data?.title ||
        notification?.data?.body ||
        'Notification update'
      )
    },
    getTypeKey(notification) {
      return notification?.data?.notification_type || notification?.type || 'general'
    },
    getTypeLabel(type) {
      const raw = String(type || 'general')
        .replace(/^App\\Notifications\\/, '')
        .replace(/([a-z])([A-Z])/g, '$1 $2')
        .replace(/_/g, ' ')
        .replace(/notification/gi, '')
        .trim()
      if (!raw) return 'General'
      return raw
        .split(' ')
        .filter(Boolean)
        .slice(0, 3)
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase())
        .join(' ')
    },
    getResponsibleName(notification) {
      const data = notification?.data || {}
      return (
        data.responsible_person_name ||
        data.responsible_person ||
        data.user_name ||
        data.assigned_to_name ||
        data.agent_name ||
        ''
      )
    },
    getAvatarUrl(notification) {
      const data = notification?.data || {}
      return data.avatar || data.user_avatar || data.responsible_person_avatar || ''
    },
    getInitials(name) {
      const text = String(name || '').trim()
      if (!text) return 'SY'
      return text
        .split(' ')
        .slice(0, 2)
        .map((w) => w.charAt(0).toUpperCase())
        .join('')
    },
    getNotificationTitle(notification) {
      const type = this.getTypeKey(notification)
      const titles = {
        new_sales_agent: 'New Sales Agent',
        request: 'Property Request',
        approved: 'Request Approved',
        rejected: 'Request Rejected',
        request_cancelled: 'Request Cancelled',
        property_assigned: 'Property Assigned',
        property_unassigned: 'Property Unassigned',
      }
      return titles[type] || 'Notification'
    },
    formatTime(timestamp) {
      if (!timestamp) return 'Unknown time'
      try {
        const notificationTime = new Date(timestamp)
        const now = new Date()
        const diff = now - notificationTime
        const mins = Math.floor(diff / 60000)
        const hours = Math.floor(diff / 3600000)
        const days = Math.floor(diff / 86400000)

        if (mins < 1) return 'Just now'
        if (mins < 60) return `${mins} min ago`
        if (hours < 24) return `${hours} hr ago`
        if (days < 7) return `${days} day${days === 1 ? '' : 's'} ago`
        return notificationTime.toLocaleDateString('en-US', {
          year: 'numeric',
          month: 'short',
          day: 'numeric',
        })
      } catch {
        return 'Unknown time'
      }
    },
  },
}
</script>

<style scoped>
.notifications-page {
  width: 100%;
  padding: 14px;
}

.notifications-shell {
  width: 100%;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 16px 36px rgba(15, 23, 42, 0.08);
  padding: 14px;
}

.notifications-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.notifications-title {
  font-size: 15px;
  font-weight: 600;
  color: #0f172a;
}

.notifications-subtitle {
  font-size: 12px;
  color: #64748b;
}

.notifications-header-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.btn-compact {
  border-radius: 10px;
  border: 1px solid transparent;
  height: 34px;
  padding: 0 12px;
  font-size: 12px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

.btn-compact-primary {
  background: #0f172a;
  color: #fff;
}

.btn-compact-outline {
  background: #fff;
  color: #334155;
  border-color: #e2e8f0;
}

.notifications-toolbar {
  display: grid;
  gap: 10px;
  margin-bottom: 12px;
}

.search-wrap {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  display: flex;
  align-items: center;
  gap: 8px;
  height: 38px;
  padding: 0 10px;
  transition: border-color .2s, box-shadow .2s;
}

.search-wrap:focus-within {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
}

.search-icon,
.search-clear {
  color: #94a3b8;
}

.search-input {
  flex: 1;
  border: none;
  outline: none;
  background: transparent;
  font-size: 12px;
}

.search-clear {
  border: none;
  background: transparent;
  width: 22px;
  height: 22px;
}

.feed-toggle {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.feed-toggle-btn {
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  background: #fff;
  color: #334155;
  height: 30px;
  font-size: 11px;
  padding: 0 12px;
}

.feed-toggle-btn.active {
  background: #0f172a;
  border-color: #0f172a;
  color: #fff;
}

.chip-row {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 2px;
}

.chip-btn {
  white-space: nowrap;
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  background: #f8fafc;
  color: #475569;
  font-size: 10px;
  padding: 5px 10px;
}

.chip-btn.active {
  background: #e2e8f0;
  color: #0f172a;
}

.notifications-content {
  min-height: 280px;
}

.notifications-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
  gap: 10px;
}

.notification-card {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #fff;
  padding: 10px;
  display: grid;
  grid-template-columns: auto 1fr auto;
  gap: 10px;
  cursor: pointer;
  transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
}

.notification-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08);
  border-color: #cbd5e1;
}

.notification-card.unread {
  background: #f8fbff;
  border-color: #bfdbfe;
}

.notification-avatar {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: #dbeafe;
  color: #1e3a8a;
  font-size: 11px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.notification-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.notification-body {
  min-width: 0;
}

.notification-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 8px;
}

.notification-person {
  font-size: 11px;
  font-weight: 700;
  color: #0f172a;
}

.highlight-person {
  background: #e0ecff;
  color: #1e40af;
  border: 1px solid #bfdbfe;
  border-radius: 999px;
  padding: 2px 8px;
  display: inline-flex;
  align-items: center;
  line-height: 1.2;
}

.notification-time {
  font-size: 10px;
  color: #94a3b8;
}

.notification-heading {
  font-size: 12px;
  font-weight: 600;
  color: #1e293b;
}

.notification-message {
  font-size: 11px;
  color: #475569;
  line-height: 1.45;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  overflow: hidden;
}

.notification-meta {
  display: flex;
  gap: 6px;
  align-items: center;
  justify-content: space-between;
}

.type-pill {
  border-radius: 999px;
  background: #f1f5f9;
  color: #475569;
  font-size: 10px;
  padding: 3px 7px;
}

.unread-pill {
  color: #1d4ed8;
  font-size: 10px;
  font-weight: 600;
}

.notification-actions {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.icon-btn {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.icon-btn-read {
  color: #2563eb;
}

.icon-btn-delete {
  color: #dc2626;
}

.state-block {
  min-height: 260px;
  display: grid;
  place-items: center;
  text-align: center;
  color: #64748b;
}

.state-title {
  font-size: 13px;
  font-weight: 600;
  color: #334155;
}

.state-text {
  font-size: 12px;
}

.state-icon {
  font-size: 28px;
  margin-bottom: 6px;
}

.pagination-wrap {
  margin-top: 12px;
  text-align: center;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.page-btn {
  min-width: 32px;
  height: 32px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
  color: #334155;
  font-size: 12px;
  font-weight: 600;
}

.page-btn.active {
  background: #0f172a;
  border-color: #0f172a;
  color: #fff;
}

@media (max-width: 768px) {
  .notifications-page {
    padding: 8px;
  }

  .notifications-shell {
    padding: 10px;
  }

  .notifications-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .notifications-grid {
    grid-template-columns: 1fr;
  }
}
</style>