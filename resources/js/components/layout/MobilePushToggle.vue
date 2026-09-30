<template>
  <div v-if="visible && !autoOnly" class="mobile-push" :class="{ 'mobile-push--compact': compact }">
    <button
      v-if="compact"
      type="button"
      class="mobile-push__icon"
      :disabled="busy"
      :aria-label="iconLabel"
      :title="iconLabel"
      @click="enable('user')"
    >
      <iconify-icon :icon="iconName" />
    </button>
    <template v-else>
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
      <p v-else-if="status === 'failed'" class="mobile-push__status">
        Could not enable mobile notifications. Tap again.
      </p>
      <button
        v-else
        type="button"
        class="mobile-push__button"
        :disabled="busy"
        @click="enable('user')"
      >
        {{ busy ? 'Enabling…' : 'Enable Mobile Notifications' }}
      </button>
    </template>
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

function canAutoOffer() {
  if (typeof Notification === 'undefined') return false
  if (Notification.permission === 'denied') return false
  if (isIos() && !isStandalone()) return false
  return true
}

let autoOfferClaimed = false

function claimAutoOffer() {
  if (autoOfferClaimed) return false
  autoOfferClaimed = true
  return true
}

export default {
  name: 'MobilePushToggle',
  props: {
    compact: { type: Boolean, default: false },
    autoOnly: { type: Boolean, default: false },
  },
  data() {
    return {
      visible: false,
      busy: false,
      status: 'idle',
      publicKey: null,
    }
  },
  computed: {
    iconName() {
      return this.status === 'enabled' ? 'lucide:bell-ring' : 'lucide:bell-plus'
    },
    iconLabel() {
      if (this.busy) return 'Enabling mobile notifications'
      if (this.status === 'enabled') return 'Mobile Notifications Enabled'
      if (this.status === 'denied') return 'Notifications are blocked in this browser. Allow them in the browser settings, then try again.'
      if (this.status === 'ios') return 'On iPhone, add Alt CRM to your Home Screen, open it from that icon, then enable notifications.'
      if (this.status === 'unconfigured') return 'Mobile notifications are not configured on the server yet.'
      if (this.status === 'failed') return 'Could not enable mobile notifications. Tap again.'
      return 'Enable Mobile Notifications'
    },
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
        if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
          if (claimAutoOffer()) await this.enable('auto')
          else if (data.subscribed) this.status = 'enabled'
          return
        }
        if (canAutoOffer() && claimAutoOffer()) {
          await this.enable('auto')
        }
      } catch (_) {
        this.visible = false
      }
    },
    async enable(source) {
      const fromUser = source !== 'auto'
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
        if (permission === 'denied') {
          this.status = 'denied'
          return
        }
        if (permission !== 'granted') {
          this.status = 'idle'
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
        if (typeof navigator.setAppBadge === 'function') {
          await navigator.setAppBadge(0).catch(() => {})
        }
      } catch (error) {
        const blocked = error?.name === 'NotAllowedError' || error?.name === 'InvalidStateError'
        this.status = !fromUser && blocked ? 'idle' : 'failed'
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

.mobile-push--compact {
  margin: 0;
  flex-shrink: 0;
}

.mobile-push__icon {
  width: 36px;
  height: 36px;
  min-width: 36px;
  border-radius: 50%;
  border: 1px solid #e8eaef;
  background: #f4f5f7;
  color: #1a1528;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  font-size: 18px;
  line-height: 0;
  cursor: pointer;
}

.mobile-push__icon:disabled {
  opacity: 0.7;
}
</style>
