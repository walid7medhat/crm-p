<template>
  <Teleport to="body">
    <transition name="lwa">
      <aside
        v-if="visible && presence?.is_online && presence?.monitored_user"
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
              <div class="lwa__name">{{ presence.monitored_user.name }}</div>
              <div class="lwa__meta">Online · {{ elapsedLabel }}</div>
            </div>
          </div>
          <div class="lwa__actions">
            <button
              type="button"
              class="lwa__btn"
              :title="muted ? 'Sound muted — click to enable' : 'Sound enabled — click to mute'"
              @click="toggleMute"
            >
              <iconify-icon :icon="muted ? 'lucide:volume-x' : 'lucide:volume-2'" width="14" height="14" />
              {{ muted ? 'Muted' : 'Sound' }}
            </button>
            <router-link class="lwa__btn lwa__btn--primary" to="/tracking">Timeline</router-link>
          </div>
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
} from '@/utils/liveWatchSiren'

const presence = ref(null)
const muted = ref(isLiveWatchSirenMuted())
const nowTick = ref(Date.now())
let tickTimer = null
let channel = null
let echoOff = null
let handledTransition = null

const visible = computed(() => {
  try {
    const raw = localStorage.getItem('user')
    if (!raw) return false
    const u = JSON.parse(raw)
    const roles = Array.isArray(u?.roles) ? u.roles : []
    return roles.includes('super_admin')
  } catch {
    return false
  }
})

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

function applyPresence(payload, { playSound = false } = {}) {
  if (!payload) return
  const nextOnline = payload.status === 'online' || payload.is_online === true
  const monitoredId = payload.monitored_user_id ?? payload.user_id ?? presence.value?.monitored_user_id
  const transitionId = payload.transition_id || null

  if (!nextOnline) {
    presence.value = {
      ...(presence.value || {}),
      is_online: false,
      transition_id: null,
      online_since: null,
      monitored_user_id: monitoredId,
      monitored_user: presence.value?.monitored_user || (payload.user_name
        ? { id: payload.user_id, name: payload.user_name }
        : null),
    }
    return
  }

  presence.value = {
    is_online: true,
    transition_id: transitionId,
    online_since: payload.online_since || presence.value?.online_since || new Date().toISOString(),
    monitored_user_id: monitoredId,
    monitored_user: presence.value?.monitored_user?.id === monitoredId
      ? presence.value.monitored_user
      : {
          id: payload.user_id || monitoredId,
          name: payload.user_name || presence.value?.monitored_user?.name || 'Monitored user',
        },
  }

  if (playSound && transitionId && transitionId !== handledTransition) {
    handledTransition = transitionId
    playLiveWatchSiren(transitionId)
  }
}

async function fetchPresence() {
  if (!visible.value) return
  try {
    const { data } = await api.get('/admin/live-watch/presence')
    const snap = data?.data
    if (!snap) return
    // Initial hydrate must NOT play the siren (refresh / reconnect).
    applyPresence({
      status: snap.is_online ? 'online' : 'offline',
      is_online: snap.is_online,
      user_id: snap.monitored_user_id,
      monitored_user_id: snap.monitored_user_id,
      transition_id: snap.transition_id,
      online_since: snap.online_since,
      user_name: snap.monitored_user?.name,
    }, { playSound: false })
    if (snap.monitored_user) {
      presence.value = { ...presence.value, monitored_user: snap.monitored_user }
    }
    if (snap.transition_id) {
      handledTransition = snap.transition_id
    }
  } catch {
    /* SA-only; ignore 403 for non-SA */
  }
}

function toggleMute() {
  muted.value = !muted.value
  setLiveWatchSirenMuted(muted.value)
  bindLiveWatchSirenUnlock()
}

function subscribe() {
  echoOff = whenEchoReady(() => {
    if (!window.Echo || channel) return
    channel = window.Echo.private('live-watch')
    channel.listen('.presence.changed', (event) => {
      applyPresence(event, { playSound: event?.status === 'online' })
    })
  })
}

onMounted(() => {
  if (!visible.value) return
  bindLiveWatchSirenUnlock()
  fetchPresence()
  subscribe()
  tickTimer = window.setInterval(() => {
    nowTick.value = Date.now()
  }, 1000)
})

onBeforeUnmount(() => {
  clearInterval(tickTimer)
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
  bottom: 88px;
  z-index: 1080;
  width: min(280px, calc(100vw - 24px));
  border-radius: 16px;
  padding: 12px 12px 10px;
  background: linear-gradient(160deg, rgba(12, 18, 28, 0.94), rgba(18, 28, 42, 0.9));
  border: 1px solid rgba(255, 255, 255, 0.1);
  box-shadow: 0 18px 40px rgba(0, 0, 0, 0.28);
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
  .lwa { right: 12px; left: 12px; width: auto; bottom: 76px; }
}
</style>
