<template>
  <Teleport to="body">
    <transition name="lwa">
      <aside
        v-if="showAlert"
        class="lwa"
        role="status"
        aria-live="polite"
      >
        <div class="lwa__pulse" aria-hidden="true" />
        <div class="lwa__body">
          <div class="lwa__row">
            <span class="lwa__dot" title="Online" />
            <div class="lwa__text">
              <div class="lwa__kicker">Live Watch</div>
              <div class="lwa__name">{{ displayName }}</div>
              <div class="lwa__meta">Online · {{ elapsedLabel }}</div>
            </div>
          </div>
          <div class="lwa__actions">
            <button
              type="button"
              class="lwa__btn"
              :class="{ 'lwa__btn--warn': muted || soundBlocked }"
              :title="soundButtonTitle"
              @click="toggleMute"
            >
              <iconify-icon :icon="muted ? 'lucide:volume-x' : 'lucide:volume-2'" width="14" height="14" />
              {{ soundButtonLabel }}
            </button>
            <router-link class="lwa__btn lwa__btn--primary" to="/tracking">Timeline</router-link>
          </div>
          <p v-if="!muted && soundBlocked" class="lwa__hint">
            Click Sound once to allow the alarm in this browser.
          </p>
        </div>
      </aside>
    </transition>
  </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import api from '@/plugins/axios'
import { whenEchoReady } from '@/components/chat/incomingChatAlert'
import {
  bindLiveWatchSirenUnlock,
  isLiveWatchSirenMuted,
  playLiveWatchSiren,
  setLiveWatchSirenMuted,
  unlockLiveWatchSiren,
} from '@/utils/liveWatchSiren'

const POLL_MS = 12_000

const presence = ref(null)
const muted = ref(isLiveWatchSirenMuted())
const soundBlocked = ref(false)
const nowTick = ref(Date.now())
let tickTimer = null
let pollTimer = null
let channel = null
let echoOff = null
/** Last transition we already alerted for (siren + toast). */
let alertedTransition = null
/** Previous online flag for poll-based offline → online detection. */
let wasOnline = false

const isSuperAdmin = computed(() => {
  try {
    const raw = localStorage.getItem('user')
    if (!raw) return false
    const u = JSON.parse(raw)
    const roles = Array.isArray(u?.roles) ? u.roles : []
    return roles.some((r) => (typeof r === 'string' ? r : r?.name) === 'super_admin')
      || String(u?.role_name || '').toLowerCase().includes('super admin')
      || String(u?.role_name || '').toLowerCase() === 'super_admin'
  } catch {
    return false
  }
})

const showAlert = computed(() => (
  isSuperAdmin.value
  && Boolean(presence.value?.is_online)
  && Boolean(presence.value?.monitored_user_id || presence.value?.monitored_user)
))

const displayName = computed(() => (
  presence.value?.monitored_user?.name
  || presence.value?.user_name
  || 'Monitored user'
))

const elapsedLabel = computed(() => {
  const since = presence.value?.online_since
  if (!since) return 'just now'
  const ms = Math.max(0, nowTick.value - new Date(since).getTime())
  const sec = Math.floor(ms / 1000)
  if (sec < 60) return `${sec}s`
  const min = Math.floor(sec / 60)
  if (min < 60) return `${min}m ${sec % 60}s`
  const hr = Math.floor(min / 60)
  return `${hr}h ${min % 60}m`
})

const soundButtonLabel = computed(() => {
  if (muted.value) return 'Muted'
  if (soundBlocked.value) return 'Enable sound'
  return 'Sound on'
})

const soundButtonTitle = computed(() => {
  if (muted.value) return 'Sound muted — click to enable'
  if (soundBlocked.value) return 'Browser blocked autoplay — click to enable the alarm'
  return 'Sound enabled — click to mute'
})

function notifyOnline(name) {
  try {
    const msg = `${name || 'Monitored user'} is online`
    if (typeof window.$showNotification === 'function') {
      window.$showNotification(msg, 'success')
    }
  } catch {
    /* ignore */
  }
}

async function maybeAlertOnline(transitionId, name, { playSound }) {
  if (!transitionId || transitionId === alertedTransition) return
  alertedTransition = transitionId
  notifyOnline(name)
  if (!playSound || muted.value) return

  bindLiveWatchSirenUnlock()
  const played = await playLiveWatchSiren(transitionId)
  soundBlocked.value = !played && !isLiveWatchSirenMuted()
}

function applyPresence(payload, { playSound = false, fromHydrate = false } = {}) {
  if (!payload) return

  const nextOnline = payload.status === 'online' || payload.is_online === true
  const monitoredId = payload.monitored_user_id ?? payload.user_id ?? presence.value?.monitored_user_id
  const transitionId = payload.transition_id || null
  const name = payload.user_name
    || payload.monitored_user?.name
    || presence.value?.monitored_user?.name

  if (!nextOnline) {
    wasOnline = false
    presence.value = {
      ...(presence.value || {}),
      is_online: false,
      transition_id: null,
      online_since: null,
      monitored_user_id: monitoredId || null,
      monitored_user: presence.value?.monitored_user
        || (name ? { id: payload.user_id || monitoredId, name } : null),
    }
    return
  }

  const becameOnline = !wasOnline
  wasOnline = true

  presence.value = {
    is_online: true,
    transition_id: transitionId,
    online_since: payload.online_since || presence.value?.online_since || new Date().toISOString(),
    monitored_user_id: monitoredId,
    monitored_user: presence.value?.monitored_user?.id === monitoredId
      ? presence.value.monitored_user
      : {
          id: payload.user_id || monitoredId,
          name: name || 'Monitored user',
        },
  }

  // Hydrate while already online: show card, never siren.
  // Live transition (Echo or poll): siren + toast once per transition_id.
  if (!fromHydrate && becameOnline) {
    maybeAlertOnline(transitionId, name, { playSound })
  } else if (fromHydrate && transitionId) {
    alertedTransition = transitionId
  }
}

async function fetchPresence({ fromHydrate = false } = {}) {
  if (!isSuperAdmin.value) return
  try {
    const { data } = await api.get('/admin/live-watch/presence')
    const snap = data?.data
    if (!snap) return

    applyPresence({
      status: snap.is_online ? 'online' : 'offline',
      is_online: Boolean(snap.is_online),
      user_id: snap.monitored_user_id,
      monitored_user_id: snap.monitored_user_id,
      transition_id: snap.transition_id,
      online_since: snap.online_since,
      user_name: snap.monitored_user?.name,
      monitored_user: snap.monitored_user,
    }, {
      // Poll detects genuine offline → online even when Echo/Pusher is down.
      playSound: !fromHydrate,
      fromHydrate,
    })

    if (snap.monitored_user) {
      presence.value = { ...(presence.value || {}), monitored_user: snap.monitored_user }
    }
  } catch {
    /* SA-only; ignore 403 */
  }
}

async function toggleMute() {
  muted.value = !muted.value
  setLiveWatchSirenMuted(muted.value)
  if (!muted.value) {
    const ok = await unlockLiveWatchSiren()
    soundBlocked.value = !ok
  } else {
    soundBlocked.value = false
  }
}

function subscribe() {
  echoOff = whenEchoReady(() => {
    if (!window.Echo || channel) return
    channel = window.Echo.private('live-watch')
    channel.listen('.presence.changed', (event) => {
      applyPresence(event, { playSound: event?.status === 'online', fromHydrate: false })
    })
  })
}

onMounted(() => {
  if (!isSuperAdmin.value) return
  bindLiveWatchSirenUnlock()
  fetchPresence({ fromHydrate: true })
  subscribe()
  pollTimer = window.setInterval(() => {
    if (document.visibilityState === 'hidden') return
    fetchPresence({ fromHydrate: false })
  }, POLL_MS)
  tickTimer = window.setInterval(() => {
    nowTick.value = Date.now()
  }, 1000)
})

onBeforeUnmount(() => {
  clearInterval(tickTimer)
  clearInterval(pollTimer)
  echoOff?.()
  if (channel && window.Echo) {
    try {
      window.Echo.leave('live-watch')
    } catch {
      /* ignore */
    }
  }
  channel = null
})
</script>

<style scoped>
.lwa {
  position: fixed;
  right: 18px;
  bottom: 96px;
  z-index: 12050;
  width: min(300px, calc(100vw - 24px));
  border-radius: 16px;
  padding: 12px 12px 10px;
  background: linear-gradient(160deg, rgba(12, 18, 28, 0.96), rgba(18, 28, 42, 0.92));
  border: 1px solid rgba(255, 255, 255, 0.12);
  box-shadow: 0 18px 40px rgba(0, 0, 0, 0.32);
  color: #f5f7fb;
  overflow: hidden;
  backdrop-filter: blur(10px);
}
.lwa__pulse {
  position: absolute;
  inset: -40% auto auto -20%;
  width: 120px;
  height: 120px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(56, 212, 140, 0.28), transparent 70%);
  animation: lwa-breathe 2.8s ease-in-out infinite;
  pointer-events: none;
}
.lwa__body { position: relative; }
.lwa__row { display: flex; gap: 10px; align-items: flex-start; }
.lwa__dot {
  width: 10px;
  height: 10px;
  margin-top: 5px;
  border-radius: 50%;
  background: #3ddc97;
  box-shadow: 0 0 0 0 rgba(61, 220, 151, 0.7);
  animation: lwa-ping 1.8s ease-out infinite;
  flex: 0 0 auto;
}
.lwa__kicker {
  font-size: 10px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  opacity: 0.65;
}
.lwa__name {
  font-size: 14px;
  font-weight: 650;
  line-height: 1.25;
}
.lwa__meta {
  font-size: 11px;
  opacity: 0.72;
  margin-top: 2px;
}
.lwa__actions {
  display: flex;
  gap: 6px;
  margin-top: 10px;
}
.lwa__btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  background: rgba(255, 255, 255, 0.06);
  color: inherit;
  font-size: 11px;
  padding: 5px 10px;
  text-decoration: none;
  cursor: pointer;
}
.lwa__btn--primary {
  background: rgba(61, 220, 151, 0.18);
  border-color: rgba(61, 220, 151, 0.35);
}
.lwa__btn--warn {
  background: rgba(251, 191, 36, 0.18);
  border-color: rgba(251, 191, 36, 0.4);
}
.lwa__hint {
  margin: 8px 0 0;
  font-size: 11px;
  opacity: 0.75;
  line-height: 1.35;
}
.lwa-enter-active,
.lwa-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.lwa-enter-from,
.lwa-leave-to { opacity: 0; transform: translateY(10px); }

@keyframes lwa-ping {
  0% { box-shadow: 0 0 0 0 rgba(61, 220, 151, 0.55); }
  70% { box-shadow: 0 0 0 8px rgba(61, 220, 151, 0); }
  100% { box-shadow: 0 0 0 0 rgba(61, 220, 151, 0); }
}
@keyframes lwa-breathe {
  0%, 100% { transform: scale(0.92); opacity: 0.7; }
  50% { transform: scale(1.05); opacity: 1; }
}

@media (max-width: 640px) {
  .lwa { right: 12px; left: 12px; width: auto; bottom: 84px; }
}
</style>
