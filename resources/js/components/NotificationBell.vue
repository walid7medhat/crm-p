<template>
  <div class="notification-wrapper position-relative">
    <!-- Notification Bell Button -->
    <button
      class="has-indicator w-40-px h-40-px bg-neutral-200 rounded-circle d-flex justify-content-center align-items-center position-relative border-0"
      type="button"
      @click.stop="toggleNotifications"
    >
      <iconify-icon icon="iconoir:bell" class="text-primary-light text-xl"></iconify-icon>
      <span v-if="unreadCount > 0" class="notification-badge">
        {{ unreadCount > 9 ? '9+' : unreadCount }}
      </span>
    </button>

    <!-- Notification Dropdown -->
    <div 
      v-if="showDropdown" 
      class="notification-dropdown dropdown-menu to-top dropdown-menu-lg p-0 show"
      @click.stop
    >
      <div class="m-16 py-12 px-16 radius-8 bg-primary-50 mb-16 d-flex align-items-center justify-content-between gap-2">
        <div>
          <h6 class="text-lg text-primary-light fw-semibold mb-0">Notifications</h6>
        </div>
        <span class="text-primary-600 fw-semibold text-lg w-40-px h-40-px rounded-circle bg-base d-flex justify-content-center align-items-center">
          {{ unreadCount }}
        </span>
      </div>

      <div class="max-h-400-px overflow-y-auto scroll-sm pe-4">
        <div v-if="notifications.length === 0" class="text-center py-16">
          <iconify-icon icon="solar:bell-off-outline" class="text-3xl text-muted mb-8"></iconify-icon>
          <p class="text-muted">No notifications</p>
        </div>
        
        <div v-else>
          <div 
            v-for="notification in notifications" 
            :key="notification.id"
            class="notification-item p-12 mb-8 radius-8 cursor-pointer"
            :class="{ 
              'bg-primary-50': !notification.read_at,
              'border border-primary': !notification.read_at
            }"
            @click="handleNotificationClick(notification)"
          >
            <div class="d-flex align-items-start gap-12">
              <div class="flex-grow-1">
                <p class="text-sm fw-medium mb-4">{{ notification.data.message }}</p>
                <span class="text-xs text-muted">
                  {{ formatTime(notification.created_at) }}
                </span>
              </div>
              <div v-if="!notification.read_at" class="unread-indicator"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="text-center py-12 px-16 border-top">
        <button 
          v-if="notifications.length > 0 && unreadCount > 0"
          @click="markAllAsRead" 
          class="btn btn-sm btn-outline-primary me-8"
        >
          Mark All Read
        </button>
        <router-link 
          to="/notifications" 
          class="text-primary-600 fw-semibold text-md"
          @click="showDropdown = false"
        >
          See All Notifications
        </router-link>
      </div>
    </div>
  </div>
</template>

<script>
const notificationSound = '/assets/notification-sound.mp3';

export default {
  name: "NotificationBell",
  props: {
    soundEnabled: {
      type: Boolean,
      default: true
    },
    browserNotificationsEnabled: {
      type: Boolean,
      default: true
    }
  },
  data() {
    return {
      notifications: [],
      unreadCount: 0,
      showDropdown: false,
      userId: null,
      pusherInitialized: false,
      apiBaseUrl: (typeof window !== 'undefined' && window.__API_BASE_URL__) || import.meta.env.VITE_API_URL || import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8001/api',
      notificationSound
    };
  },
  mounted() {
    console.log('🚀 NotificationBell component mounted');
    
    const user = localStorage.getItem("user");
    this.userId = user ? JSON.parse(user).id : null;
    
    this.fetchNotifications();
    this.initializePusher();
    
    // طلب إذن الإشعارات إذا مسموح
    if ('Notification' in window && Notification.permission === 'default' && this.browserNotificationsEnabled) {
      Notification.requestPermission().then(permission => {
        console.log('📱 Notification permission:', permission);
      });
    }
    
    // إضافة event listener للنقر خارج الـ dropdown
    document.addEventListener('click', this.handleClickOutside);
  },
  beforeUnmount() {
    if (window.Echo && this.userId) {
      console.log('🧹 Cleaning up Pusher connection');
      window.Echo.leave(`App.Models.User.${this.userId}`);
    }
    // إزالة event listener
    document.removeEventListener('click', this.handleClickOutside);
  },
  methods: {
    toggleNotifications() {
      console.log('🔔 Toggle notifications, current state:', this.showDropdown);
      this.showDropdown = !this.showDropdown;
      // إرسال event للمكون الرئيسي
      this.$emit('toggle', this.showDropdown);
    },
    
    closeNotifications() {
      this.showDropdown = false;
    },

    handleClickOutside(event) {
      // التحقق إذا كان النقر خارج مكون الـ notification
      const notificationElement = this.$el;
      if (notificationElement && !notificationElement.contains(event.target)) {
        this.closeNotifications();
      }
    },

    async fetchNotifications() {
      try {
        console.log('📨 Fetching notifications from API...');
        const token = localStorage.getItem('token') || sessionStorage.getItem('token');
        console.log('🔑 Token available:', !!token);
        
        const response = await fetch(`${this.apiBaseUrl}/auth/notifications`, {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
          }
        });
        
        console.log('📡 API Response status:', response.status);
        
        if (response.ok) {
          const data = await response.json();
          console.log('📊 API Response data:', data);
          this.notifications = data.data || [];
          this.updateUnreadCount();
          console.log('✅ Notifications loaded:', this.notifications.length);
        } else {
          console.error('❌ API Error:', response.status, response.statusText);
        }
      } catch (error) {
        console.error('❌ Failed to fetch notifications:', error);
      }
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
            if (this.$showNotification) {
              this.$showNotification('Notification marked as read', 'success');
            }
          }
        } else {
          console.error('❌ Failed to mark as read');
          if (this.$showNotification) {
            this.$showNotification('Failed to mark notification as read', 'error');
          }
        }
      } catch (error) {
        console.error('❌ Error marking as read:', error);
        if (this.$showNotification) {
          this.$showNotification('Error marking notification as read', 'error');
        }
      }
    },

    async markAllAsRead() {
      try {
        console.log('📝 Marking all notifications as read');
        const token = localStorage.getItem('token') || sessionStorage.getItem('token');
        
        const response = await fetch(`${this.apiBaseUrl}/auth/notifications/read-all`, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
          }
        });

        if (response.ok) {
          this.notifications.forEach(notification => {
            if (!notification.read_at) {
              notification.read_at = new Date().toISOString();
            }
          });
          
          this.unreadCount = 0;
          console.log('✅ All notifications marked as read');
        } else {
          console.error('❌ Failed to mark all as read:', response.status);
        }
      } catch (error) {
        console.error('❌ Error marking all as read:', error);
      }
    },

    initializePusher() {
      if (!window.Echo) {
        console.error('❌ Echo is not available');
        return;
      }

      if (!this.userId) {
        console.error('❌ User ID is not available');
        return;
      }

      console.log('🔔 Initializing Pusher for user:', this.userId);
      
      try {
        window.Echo.leave(`App.Models.User.${this.userId}`);
        console.log('✅ Left previous channel');
      } catch (error) {
        console.log('ℹ️ No previous channel to leave');
      }
      
      const channelName = `App.Models.User.${this.userId}`;
      console.log('🎯 Subscribing to channel:', channelName);
      
      const channel = window.Echo.private(channelName);
      
      channel.subscribed(() => {
        console.log('✅ Successfully subscribed to channel:', channelName);
        this.pusherInitialized = true;
      });

      channel.error((error) => {
        console.error('❌ Channel subscription error:', error);
      });

      channel.listen('.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated', (event) => {
        console.log('🎉 BROADCAST NOTIFICATION EVENT:', event);
        this.handleNotificationEvent(event);
      });

      channel.listen('.BroadcastNotificationCreated', (event) => {
        console.log('🎉 CUSTOM BROADCAST EVENT:', event);
        this.handleNotificationEvent(event);
      });

      channel.listenToAll((eventName, data) => {
        console.log('🔍 ALL EVENTS - Name:', eventName, 'Data:', data);
      });

      console.log('✅ All event listeners registered');
    },

    handleNotificationEvent(event) {
      console.log('🔄 Processing notification event:', event);
      
      let notificationData = null;
      
      if (event.notification) {
          notificationData = event.notification;
      } else if (event.data) {
          notificationData = event.data;
      } else {
          notificationData = event;
      }
      
      console.log('📦 Extracted notification data:', notificationData);
      
      // معالجة إشعار تعيين الـ property
      if (notificationData && notificationData.data?.notification_type === 'property_assigned') {
          console.log('🎯 Property assignment notification detected');
          
          this.addNotification({
              id: notificationData.id || `property_assign_${Date.now()}`,
              type: 'App\\Notifications\\PropertyAssignedNotification',
              data: notificationData.data || notificationData,
              user_id: this.userId,
              read_at: null,
              created_at: notificationData.created_at || new Date().toISOString()
          });
      } 
      else if (notificationData && notificationData.data?.notification_type === 'property_unassigned') {
          console.log('🎯 Property unassignment notification detected');
          
          this.addNotification({
              id: notificationData.id || `property_unassign_${Date.now()}`,
              type: 'App\\Notifications\\PropertyUnassignedNotification',
              data: notificationData.data || notificationData,
              user_id: this.userId,
              read_at: null,
              created_at: notificationData.created_at || new Date().toISOString()
          });
      }
      else if (notificationData) {
          this.addNotification({
              id: notificationData.id || Date.now(),
              type: notificationData.type || 'general',
              data: notificationData.data || notificationData,
              user_id: this.userId,
              read_at: null,
              created_at: notificationData.created_at || new Date().toISOString()
          });
      }
    },
    
    addNotification(notificationData) {
      console.log('➕ ADDING NOTIFICATION:', notificationData);
      
      if (!notificationData.data) {
        console.error('❌ Notification data is missing');
        return;
      }

      const newNotification = {
        id: notificationData.id,
        type: notificationData.type,
        data: notificationData.data,
        read_at: notificationData.read_at,
        created_at: notificationData.created_at
      };

      console.log('📝 New notification object:', newNotification);

      this.notifications.unshift(newNotification);
      
      if (!newNotification.read_at) {
        this.unreadCount++;
      }
      
      console.log('✅ Notification added successfully');
      console.log('📊 Total notifications:', this.notifications.length);
      console.log('🔢 Unread count:', this.unreadCount);

      if (!newNotification.read_at) {
        this.playSound();
        this.showBrowserNotification(newNotification.data.message || 'New notification');
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
      } else if (type === 'App\\Notifications\\LeadUpdatedNotification') {
          this.$router.push('/kanban');
      } else if (type === 'App\\Notifications\\DealUpdatedNotificatio') {
          this.$router.push('/kanban');
      } else {
          console.log('📍 No specific action for notification type:', type);
      }
    },
    
    updateUnreadCount() {
      this.unreadCount = this.notifications.filter(n => !n.read_at).length;
      console.log('🔢 Unread count updated:', this.unreadCount);
    },

    playSound() {
      if (!this.soundEnabled) {
        console.log('🔇 Sound disabled by user');
        return;
      }
      
      try {
        console.log('🔊 Attempting to play notification sound');
        const audio = new Audio(notificationSound);
        audio.volume = 0.3;
        
        const playPromise = audio.play();
        
        if (playPromise !== undefined) {
          playPromise
            .then(() => {
              console.log('✅ Notification sound played successfully');
            })
            .catch(error => {
              console.error('🔇 Audio play failed:', error);
            });
        }
      } catch (error) {
        console.error('🔇 Sound error:', error);
      }
    },

    showBrowserNotification(message) {
      if (!this.browserNotificationsEnabled) {
        console.log('📱 Browser notifications disabled by user');
        return;
      }
      
      console.log('📱 Attempting to show browser notification:', message);
      
      if (Notification.permission === 'granted') {
        new Notification('New Notification', {
          body: message,
          icon: '/favicon.ico'
        });
        console.log('✅ Browser notification shown');
      } else if (Notification.permission === 'default') {
        Notification.requestPermission().then(permission => {
          console.log('📱 Notification permission result:', permission);
          if (permission === 'granted') {
            new Notification('New Notification', {
              body: message,
              icon: '/favicon.ico'
            });
          }
        });
      } else {
        console.log('📱 Notification permission denied');
      }
    },

    formatTime(timestamp) {
      if (!timestamp) return 'Unknown time';
      
      try {
        const notificationTime = new Date(timestamp);
        const now = new Date();
        
        const diff = now - notificationTime;
        const mins = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);

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
.notification-wrapper {
  display: inline-block;
}

.notification-badge {
  position: absolute;
  top: -5px;
  right: -5px;
  background: #ef4444;
  color: white;
  border-radius: 50%;
  min-width: 18px;
  height: 18px;
  font-size: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid white;
  padding: 0 4px;
}

.notification-dropdown {
  position: absolute;
  right: 0;
  top: 100%;
  width: 400px;
  z-index: 1050;
  margin-top: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: white;
}

.notification-item {
  transition: all 0.2s ease;
  border: 1px solid transparent;
}

.notification-item:hover {
  border-color: #3b82f6;
  background-color: #f8fafc !important;
}

.unread-indicator {
  width: 8px;
  height: 8px;
  background: #3b82f6;
  border-radius: 50%;
  margin-top: 4px;
  flex-shrink: 0;
}

.scroll-sm::-webkit-scrollbar {
  width: 4px;
}

.scroll-sm::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 2px;
}

.scroll-sm::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}
</style>