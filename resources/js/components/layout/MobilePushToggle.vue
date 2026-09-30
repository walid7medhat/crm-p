<template>
  <div v-if="visible" class="mobile-push">
    <p v-if="status === 'enabled'" class="mobile-push__status">Mobile Notifications Enabled</p>
    <p v-else-if="status === 'denied'" class="mobile-push__status">
      Notifications are blocked in this browser. Allow them in the browser settings, then try again.
    </p>
    <p v-else-if="status === 'ios'" class="mobile-push__status">
      On iPhone, add Alt CRM to your Home Screen, open it from that icon, then enable notifications.
    </p>
    <p v-else-if="status === 'unconfigured'" class="mobile-push__status">
      Mobile notifications are not configured on the server yet.
    </p>
    <button
      v-else
      type="button"
      class="mobile-push__button"
      :disabled="busy"
      @click="enable"
    >
      {{ busy ? 'Enabling…' : 'Enable Mobile Notifications' }}
    </button>
  </div>
</template>

<script>
import api from '@/plugins/axios'

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
  const raw = window.atob(base64)
  const output = new Uint8Array(raw.length)
  for (let i = 0; i < raw.length; i += 1) output[i] = raw.charCodeAt(i)
  return output
}

function isIos() {
  const ua = window.navigator.userAgent || ''
  return /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
}

function isStandalone() {
  return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true
}

export default {
  name: 'MobilePushToggle',
  data() {
    return {
      visible: false,
      busy: false,
      status: 'idle',
      publicKey: null,
    }
  },
  mounted() {
    this.load()
  },
  methods: {
    async load() {
      try {
        const response = await api.get('/push-subscriptions/config')
        const data = response.data?.data
        if (!data?.eligible) return
        this.visible = true
        this.publicKey = data.public_key || null
        if (!data.configured || !this.publicKey) {
          this.status = 'unconfigured'
          return
        }
        if (isIos() && !isStandalone()) {
          this.status = 'ios'
          return
        }
        if (typeof Notification !== 'undefined' && Notification.permission === 'denied') {
          this.status = 'denied'
          return
        }
        if (data.subscribed && typeof Notification !== 'undefined' && Notification.permission === 'granted') {
          this.status = 'enabled'
        }
      } catch (_) {
        this.visible = false
      }
    },
    async enable() {
      if (this.busy || this.status === 'denied' || this.status === 'ios' || this.status === 'unconfigured') return
      if (!('serviceWorker' in navigator) || !('PushManager' in window) || !this.publicKey) {
        this.status = 'unconfigured'
        return
      }
      this.busy = true
      try {
        if (Notification.permission === 'denied') {
          this.status = 'denied'
          return
        }
        const permission = Notification.permission === 'granted'
          ? 'granted'
          : await Notification.requestPermission()
        if (permission !== 'granted') {
          this.status = 'denied'
          return
        }
        const registration = await navigator.serviceWorker.register('/sw.js')
        await navigator.serviceWorker.ready
        const subscription = await registration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8Array(this.publicKey),
        })
        await api.post('/push-subscriptions', subscription.toJSON())
        this.status = 'enabled'
      } catch (_) {
        this.status = 'idle'
      } finally {
        this.busy = false
      }
    },
  },
}
</script>

<style scoped>
.mobile-push {
  margin-bottom: 8px;
}

.mobile-push__button {
  width: 100%;
  border: 1px solid #bfdbfe;
  background: #eff6ff;
  color: #1d4ed8;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  padding: 8px 10px;
}

.mobile-push__button:disabled {
  opacity: 0.7;
}

.mobile-push__status {
  margin: 0;
  font-size: 11px;
  line-height: 1.4;
  color: #475569;
}
</style>
