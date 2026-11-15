<template>
  <Teleport to="body">
    <transition name="faa">
      <aside
        v-if="showAlert"
        class="faa"
        role="alert"
        aria-live="assertive"
      >
        <div class="faa__pulse" aria-hidden="true" />
        <div class="faa__body">
          <div class="faa__row">
            <span class="faa__dot" title="Foreign access" />
            <div class="faa__text">
              <div class="faa__kicker">Foreign Access Alert</div>
              <div class="faa__name">{{ latest.user_name || 'Employee' }}</div>
              <div class="faa__meta">
                {{ locationLabel }} · {{ latest.ip || '—' }}
              </div>
            </div>
          </div>
          <div class="faa__actions">
            <button type="button" class="faa__btn" @click="dismiss">Dismiss</button>
            <router-link
              class="faa__btn faa__btn--primary"
              :to="{ path: '/settings/foreign-access', query: { event: String(latest.id) } }"
              @click="dismiss"
            >
              Open event
            </router-link>
          </div>
        </div>
      </aside>
    </transition>
  </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { whenEchoReady } from '@/components/chat/incomingChatAlert'

const latest = ref(null)
const dismissedId = ref(null)
let channel = null
let echoOff = null
/** Avoid duplicate toasts for the same event id. */
const seenIds = new Set()

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
  && latest.value
  && latest.value.id
  && latest.value.id !== dismissedId.value
))

const locationLabel = computed(() => {
  const parts = [latest.value?.city, latest.value?.country || latest.value?.country_code]
    .filter(Boolean)
  return parts.join(', ') || 'Outside UAE'
})

function notify(payload) {
  try {
    const name = payload?.user_name || 'Employee'
    const where = [payload?.city, payload?.country || payload?.country_code].filter(Boolean).join(', ')
    const msg = `Foreign access: ${name}${where ? ` · ${where}` : ''}`
    if (typeof window.$showNotification === 'function') {
      window.$showNotification(msg, 'error')
    }
  } catch {
    /* ignore */
  }
}

function applyEvent(payload) {
  if (!payload?.id || seenIds.has(payload.id)) return
  seenIds.add(payload.id)
  latest.value = payload
  dismissedId.value = null
  notify(payload)
}

function dismiss() {
  if (latest.value?.id) dismissedId.value = latest.value.id
}

function subscribe() {
  echoOff = whenEchoReady(() => {
    if (!window.Echo || channel) return
    channel = window.Echo.private('foreign-access')
    channel.listen('.foreign.access', (event) => {
      applyEvent(event)
    })
  })
}

onMounted(() => {
  if (!isSuperAdmin.value) return
  subscribe()
})

onBeforeUnmount(() => {
  echoOff?.()
  if (channel && window.Echo) {
    try {
      window.Echo.leave('foreign-access')
    } catch {
      /* ignore */
    }
  }
  channel = null
})
</script>

<style scoped>
.faa {
  position: fixed;
  right: 18px;
  bottom: 168px;
  z-index: 12060;
  width: min(300px, calc(100vw - 24px));
  border-radius: 16px;
  padding: 12px 12px 10px;
  background: linear-gradient(160deg, rgba(48, 12, 12, 0.96), rgba(42, 18, 18, 0.92));
  border: 1px solid rgba(248, 113, 113, 0.35);
  box-shadow: 0 18px 40px rgba(0, 0, 0, 0.32);
  color: #f5f7fb;
  overflow: hidden;
  backdrop-filter: blur(10px);
}
.faa__pulse {
  position: absolute;
  inset: -40% auto auto -20%;
  width: 120px;
  height: 120px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(239, 68, 68, 0.32), transparent 70%);
  animation: faa-breathe 2.8s ease-in-out infinite;
  pointer-events: none;
}
.faa__body { position: relative; }
.faa__row { display: flex; gap: 10px; align-items: flex-start; }
.faa__dot {
  width: 10px;
  height: 10px;
  margin-top: 5px;
  border-radius: 50%;
  background: #ef4444;
  box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
  animation: faa-ping 1.8s ease-out infinite;
  flex: 0 0 auto;
}
.faa__kicker {
  font-size: 10px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  opacity: 0.7;
  color: #fca5a5;
}
.faa__name {
  font-size: 14px;
  font-weight: 650;
  line-height: 1.25;
}
.faa__meta {
  font-size: 11px;
  opacity: 0.72;
  margin-top: 2px;
}
.faa__actions {
  display: flex;
  gap: 6px;
  margin-top: 10px;
}
.faa__btn {
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
.faa__btn--primary {
  background: rgba(239, 68, 68, 0.22);
  border-color: rgba(239, 68, 68, 0.45);
}
.faa-enter-active,
.faa-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.faa-enter-from,
.faa-leave-to { opacity: 0; transform: translateY(10px); }

@keyframes faa-ping {
  0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.55); }
  70% { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
  100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}
@keyframes faa-breathe {
  0%, 100% { transform: scale(0.92); opacity: 0.7; }
  50% { transform: scale(1.05); opacity: 1; }
}

@media (max-width: 640px) {
  .faa { right: 12px; left: 12px; width: auto; bottom: 150px; }
}
</style>
