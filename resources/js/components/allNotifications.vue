<template>
  <div class="container-fluid py-16">
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between mb-16">
          <div>
            <h1 class="h2 fw-bold text-primary-light mb-4">All Notifications</h1>
            <p class="text-muted mb-0">
              {{ unreadCount }} unread of {{ totalCount }} notifications
            </p>
          </div>
          <div class="d-flex gap-8">
            <button 
              v-if="unreadCount > 0"
              @click="markAllAsRead" 
              class="btn btn-primary"
            >
              Mark All as Read
            </button>
            <button 
              @click="refreshNotifications" 
              class="btn btn-outline-primary"
            >
              <iconify-icon icon="solar:refresh-outline" class="me-8"></iconify-icon>
              Refresh
            </button>
          </div>
        </div>

        <!-- Notifications List -->
        <div class="card">
          <div class="card-body p-0">
            <div v-if="loading" class="text-center py-32">
              <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
              </div>
              <p class="text-muted mt-8">Loading notifications...</p>
            </div>

            <div v-else-if="notifications.length === 0" class="text-center py-32">
              <iconify-icon icon="solar:bell-off-outline" class="text-3xl text-muted mb-8"></iconify-icon>
              <h5 class="text-muted mb-8">No notifications yet</h5>
              <p class="text-muted">You'll see notifications here when you receive them.</p>
            </div>

            <div v-else class="notifications-list">
              <div 
                v-for="notification in notifications" 
                :key="notification.id"
                class="notification-item p-16 border-bottom cursor-pointer"
                :class="{ 
                  'bg-primary-50': !notification.read_at,
                  'border-start border-primary border-3': !notification.read_at
                }"
                @click="handleNotificationClick(notification)"
              >
                <div class="d-flex align-items-start gap-12">
                  <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-8 mb-8">
                      <h6 class="mb-0 fw-semibold">{{ getNotificationTitle(notification) }}</h6>
                      <span 
                        v-if="!notification.read_at" 
                        class="badge bg-primary rounded-pill"
                      >
                        New
                      </span>
                    </div>
                    <p class="text-md mb-8">{{ notification.data.message }}</p>
                    <div class="d-flex align-items-center gap-16">
                      <span class="text-xs text-muted">
                        <iconify-icon icon="solar:clock-circle-outline" class="me-4"></iconify-icon>
                        {{ formatTime(notification.created_at) }}
                      </span>
                      <!-- <span class="text-xs text-muted">
                        <iconify-icon icon="solar:user-rounded-outline" class="me-4"></iconify-icon>
                        {{ getNotificationType(notification) }}
                      </span> -->
                    </div>
                  </div>
                  <div class="d-flex flex-column align-items-end gap-8">
                    <button 
                      v-if="!notification.read_at"
                      @click.stop="markAsRead(notification.id)" 
                      class="btn btn-sm btn-outline-primary"
                      title="Mark as read"
                    >
                      <iconify-icon icon="solar:check-read-outline"></iconify-icon>
                    </button>
                    <button 
                      @click.stop="deleteNotification(notification.id)" 
                      class="btn btn-sm btn-outline-danger"
                      title="Delete notification"
                    >
                      <iconify-icon icon="solar:trash-bin-trash-outline"></iconify-icon>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Load More Button -->
        <div v-if="hasMore" class="text-center mt-16">
          <button 
            @click="loadMore" 
            class="btn btn-outline-primary"
            :disabled="loadingMore"
          >
            <span v-if="loadingMore" class="spinner-border spinner-border-sm me-8"></span>
            Load More Notifications
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
export default {
  name: "AllNotifications",
  data() {
    return {
      notifications: [],
      loading: false,
      loadingMore: false,
      currentPage: 1,
      lastPage: 1,
      hasMore: false,
      unreadCount: 0,
      totalCount: 0,
      apiBaseUrl: import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api'
    };
  },
  mounted() {
    this.fetchNotifications();
  },
  methods: {
    async fetchNotifications(page = 1) {
      try {
        this.loading = true;
        const token = localStorage.getItem('token') || sessionStorage.getItem('token');
        
        console.log('🔑 Token:', token ? 'Available' : 'Missing');
        console.log('🌐 Fetching from:', `${this.apiBaseUrl}/auth/notifications?page=${page}`);
        
        const response = await fetch(`${this.apiBaseUrl}/auth/notifications?page=${page}`, {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
          }
        });
        
        console.log('📡 Response status:', response.status);
        
        if (response.ok) {
          const data = await response.json();
          console.log('📊 Full API Response:', data);
          
          let notificationsData = [];
          let unreadCount = 0;
          let totalCount = 0;
          
          if (data.data) {
            notificationsData = data.data;
            console.log('📝 Found notifications in data.data:', notificationsData.length);
          } else if (Array.isArray(data)) {
            notificationsData = data;
            console.log('📝 Found notifications as direct array:', notificationsData.length);
          } else if (data.notifications) {
            notificationsData = data.notifications;
            console.log('📝 Found notifications in data.notifications:', notificationsData.length);
          }
          
          unreadCount = notificationsData.filter(n => !n.read_at).length;
          totalCount = notificationsData.length;
          
          console.log('🔢 Unread count:', unreadCount);
          console.log('📊 Total count:', totalCount);
          
          if (page === 1) {
            this.notifications = notificationsData;
          } else {
            this.notifications = [...this.notifications, ...notificationsData];
          }
          
          this.currentPage = data.meta?.current_page || page;
          this.lastPage = data.meta?.last_page || 1;
          this.hasMore = this.currentPage < this.lastPage;
          this.unreadCount = data.meta?.unread_count || unreadCount;
          this.totalCount = data.meta?.total || totalCount;
          
          console.log('✅ Final notifications:', this.notifications.length);
          console.log('📄 Pagination - Current:', this.currentPage, 'Last:', this.lastPage, 'HasMore:', this.hasMore);
          
          // إظهار notification عند تحميل البيانات
          if (notificationsData.length > 0) {
            this.$showNotification(`Loaded ${notificationsData.length} notifications`, 'success');
          }
          
        } else {
          console.error('❌ API Error - Status:', response.status);
          const errorText = await response.text();
          console.error('❌ API Error - Response:', errorText);
          this.$showNotification('Failed to load notifications', 'error');
        }
      } catch (error) {
        console.error('❌ Failed to fetch notifications:', error);
        this.$showNotification('Network error while loading notifications', 'error');
      } finally {
        this.loading = false;
      }
    },

    async loadMore() {
      if (this.loadingMore || !this.hasMore) return;
      
      this.loadingMore = true;
      await this.fetchNotifications(this.currentPage + 1);
      this.loadingMore = false;
    },

    async refreshNotifications() {
      await this.fetchNotifications(1);
      this.$showNotification('Notifications refreshed', 'success');
    },

    async markAsRead(id) {
      try {
        const token = localStorage.getItem('token') || sessionStorage.getItem('token');
        console.log('📝 Marking as read:', id);
        
        const response = await fetch(`${this.apiBaseUrl}/auth/notifications/${id}/read`, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
          }
        });

        console.log('✅ Mark as read response:', response.status);
        
        if (response.ok) {
          const notification = this.notifications.find(n => n.id === id);
          if (notification && !notification.read_at) {
            notification.read_at = new Date().toISOString();
            this.unreadCount--;
            console.log('✅ Notification marked as read in UI');
            this.$showNotification('Notification marked as read', 'success');
          }
        } else {
          console.error('❌ Failed to mark as read');
          this.$showNotification('Failed to mark notification as read', 'error');
        }
      } catch (error) {
        console.error('❌ Error marking as read:', error);
        this.$showNotification('Error marking notification as read', 'error');
      }
    },

    async markAllAsRead() {
      try {
        const token = localStorage.getItem('token') || sessionStorage.getItem('token');
        console.log('📝 Marking all as read');
        
        const response = await fetch(`${this.apiBaseUrl}/auth/notifications/read-all`, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
          }
        });

        console.log('✅ Mark all as read response:', response.status);
        
        if (response.ok) {
          this.notifications.forEach(notification => {
            if (!notification.read_at) {
              notification.read_at = new Date().toISOString();
            }
          });
          
          this.unreadCount = 0;
          console.log('✅ All notifications marked as read in UI');
          this.$showNotification('All notifications marked as read', 'success');
        } else {
          console.error('❌ Failed to mark all as read');
          this.$showNotification('Failed to mark all notifications as read', 'error');
        }
      } catch (error) {
        console.error('❌ Error marking all as read:', error);
        this.$showNotification('Error marking all notifications as read', 'error');
      }
    },

    async deleteNotification(id) {
      if (!confirm('Are you sure you want to delete this notification?')) {
        return;
      }

      try {
        const token = localStorage.getItem('token') || sessionStorage.getItem('token');
        console.log('🗑️ Deleting notification:', id);
        
        const response = await fetch(`${this.apiBaseUrl}/auth/notifications/${id}`, {
          method: 'DELETE',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
          }
        });

        console.log('✅ Delete response:', response.status);
        
        if (response.ok) {
          const notification = this.notifications.find(n => n.id === id);
          if (notification && !notification.read_at) {
            this.unreadCount--;
          }
          
          this.notifications = this.notifications.filter(n => n.id !== id);
          this.totalCount--;
          console.log('✅ Notification deleted from UI');
          this.$showNotification('Notification deleted', 'success');
        } else {
          console.error('❌ Failed to delete notification');
          this.$showNotification('Failed to delete notification', 'error');
        }
      } catch (error) {
        console.error('❌ Error deleting notification:', error);
        this.$showNotification('Error deleting notification', 'error');
      }
    },

    handleNotificationClick(notification) {
    console.log('🖱️ Notification clicked:', notification);
    
    // mark as read إذا كانت unread
    if (!notification.read_at) {
        this.markAsRead(notification.id);
    }

    this.showDropdown = false;
    
    const type = notification.data.notification_type || notification.type;
    console.log('🔗 Notification type:', type);
    
    if (['request', 'approved', 'rejected'].includes(type)) {
        this.$router.push('/my-requests');
    } else if (type === 'new_sales_agent') {
        this.$router.push('/users');
    } else if (type === 'request_cancelled' && notification.data.property_id) {
        this.$router.push(`/property-details/${notification.data.property_id}`);
    } else if (['property_assigned', 'property_unassigned'].includes(type) && notification.data.property_id) {
        this.$router.push(`/property-details/${notification.data.property_id}`);
    } else if (notification.data.listing_id) {
        this.$router.push(`/property-details/${notification.data.listing_id}`);
    } else if (notification.data.property_id) {
        this.$router.push(`/property-details/${notification.data.property_id}`);
    } else {
        console.log('📍 No specific action for notification type:', type);
    }
},
  getNotificationTitle(notification) {
    const type = notification.data?.notification_type || notification.type;
    
    const titles = {
        'new_sales_agent': 'New Sales Agent',
        'request': 'Property Request',
        'approved': 'Request Approved',
        'rejected': 'Request Rejected',
        'request_cancelled': 'Request Cancelled',
        'property_assigned': 'Property Assigned',
        'property_unassigned': 'Property Unassigned'
    };
    
    return titles[type] || 'Notification';
},

    getNotificationType(notification) {
      const type = notification.data?.notification_type || notification.type;
      return type ? type.replace('_', ' ').toUpperCase() : 'GENERAL';
    },

  formatTime(timestamp) {
  if (!timestamp) return 'Unknown time';
  
  try {
    // استخدم الـ created_at مباشرة من الـ notification object
    const notificationTime = new Date(timestamp);
    console.log(notificationTime);
    const now = new Date();
    
    const diff = now - notificationTime;
    const mins = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);

    console.log('🕒 Notification Time Debug:', {
      notificationTime: notificationTime.toString(),
      now: now.toString(),
      diffMinutes: mins
    });

    if (mins < 1) return 'Just now';
    if (mins < 60) return `${mins} min ago`;
    if (hours < 24) return `${hours} hr ago`;
    if (days < 7) return `${days} day${days === 1 ? '' : 's'} ago`;
    
    return notificationTime.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
    
  } catch (error) {
    console.error('Error formatting time:', error);
    return 'Unknown time';
  }
}
  }
}
</script>
<style scoped>
.notification-item {
  transition: all 0.2s ease;
}

.notification-item:hover {
  background-color: #f8fafc !important;
}

.cursor-pointer {
  cursor: pointer;
}

.border-3 {
  border-width: 3px !important;
}
</style>