<template>
  <div class="dashboard-main-body lw-page">
    <div class="lw-shell">
      <div class="lw-top">
        <div>
          <div class="lw-title">Super Admin Live Watch</div>
          <p class="lw-hint">
            Monitor exactly one CRM user · presence via authenticated heartbeats ·
            timeline separates page views from business actions · retention
            {{ retentionDays }} days · Super Admin only
          </p>
        </div>
        <div class="lw-actions">
          <button type="button" class="lw-btn" :disabled="loading" @click="refreshAll">
            <iconify-icon icon="lucide:refresh-cw" width="14" height="14" />
            Refresh
          </button>
        </div>
      </div>

      <div v-if="error" class="lw-error">
        <span>{{ error }}</span>
        <button type="button" class="lw-link" @click="refreshAll">Retry</button>
      </div>

      <div class="lw-config">
        <div class="lw-field">
          <label>Monitored user</label>
          <div class="lw-user-pick">
            <input
              v-model="userSearch"
              type="search"
              placeholder="Search name, email, or ID"
              @input="onSearchUsers"
            />
            <select v-model="selectedUserId">
              <option :value="null">— None —</option>
              <option
                v-for="u in userOptions"
                :key="u.id"
                :value="u.id"
              >
                {{ u.name }} ({{ u.email }})
              </option>
            </select>
          </div>
        </div>
        <div class="lw-field lw-field--sm">
          <label>Heartbeat (sec)</label>
          <input v-model.number="heartbeatSeconds" type="number" min="15" max="120" />
        </div>
        <div class="lw-field lw-field--sm">
          <label>Offline timeout (sec)</label>
          <input v-model.number="offlineTimeout" type="number" min="45" max="600" />
        </div>
        <button type="button" class="lw-btn lw-btn--primary" :disabled="saving" @click="saveSettings">
          {{ saving ? 'Saving…' : 'Save' }}
        </button>
      </div>

      <div class="lw-metrics">
        <div class="lw-metric">
          <span class="lw-metric__n">{{ presence?.monitored_user?.name || '—' }}</span>
          <span class="lw-metric__l">Target</span>
        </div>
        <div class="lw-metric" :class="{ 'is-online': presence?.is_online }">
          <span class="lw-metric__n">{{ presence?.is_online ? 'Online' : 'Offline' }}</span>
          <span class="lw-metric__l">Presence · {{ presence?.active_sessions || 0 }} session(s)</span>
        </div>
        <div class="lw-metric">
          <span class="lw-metric__n">{{ elapsed }}</span>
          <span class="lw-metric__l">Online duration</span>
        </div>
      </div>

      <div class="lw-filters">
        <input v-model="filters.date_from" type="date" @change="fetchTimeline" />
        <input v-model="filters.date_to" type="date" @change="fetchTimeline" />
        <select v-model="filters.event_kind" @change="fetchTimeline">
          <option value="">All kinds</option>
          <option value="navigation">Navigation / page views</option>
          <option value="business">Business actions</option>
        </select>
        <select v-model="filters.module" @change="fetchTimeline">
          <option value="">All modules</option>
          <option v-for="m in modules" :key="m" :value="m">{{ m }}</option>
        </select>
        <input
          v-model="filters.event_type"
          type="search"
          placeholder="Event type"
          @change="fetchTimeline"
        />
      </div>

      <div class="lw-label">
        Activity timeline
        <span class="lw-label__meta">live · newest first</span>
      </div>

      <div v-if="loadingTimeline" class="lw-loading">Loading timeline…</div>
      <div v-else-if="!events.length" class="lw-empty">No events for the current filters.</div>
      <ul v-else class="lw-timeline">
        <li v-for="ev in events" :key="ev.id" class="lw-item" :class="`is-${ev.event_kind}`">
          <div class="lw-item__icon">
            <iconify-icon :icon="iconFor(ev)" width="16" height="16" />
          </div>
          <div class="lw-item__body">
            <div class="lw-item__top">
              <span class="lw-pill" :class="ev.event_kind">{{ ev.event_kind }}</span>
              <span class="lw-item__type">{{ ev.event_type }}</span>
              <span class="lw-item__time">{{ formatWhen(ev.occurred_at) }}</span>
            </div>
            <div class="lw-item__desc">
              <strong>{{ ev.module || 'app' }}</strong>
              · {{ ev.action }}
              <template v-if="ev.entity_type && ev.entity_id">
                ·
                <a v-if="entityLink(ev)" :href="entityLink(ev)" class="lw-entity" @click.prevent="openEntity(ev)">
                  {{ ev.entity_type }} #{{ ev.entity_id }}
                </a>
                <span v-else>{{ ev.entity_type }} #{{ ev.entity_id }}</span>
              </template>
            </div>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/plugins/axios'
import { openLeadView } from '@/composables/useLeadViewModal'
import { openDealView } from '@/composables/useDealViewModal'
import { whenEchoReady } from '@/components/chat/incomingChatAlert'

const router = useRouter()
const loading = ref(false)
const loadingTimeline = ref(false)
const saving = ref(false)
const error = ref('')
const presence = ref(null)
const events = ref([])
const userOptions = ref([])
const userSearch = ref('')
const selectedUserId = ref(null)
const heartbeatSeconds = ref(30)
const offlineTimeout = ref(90)
const retentionDays = 60
const nowTick = ref(Date.now())
let tickTimer = null
let searchTimer = null
let echoOff = null
let channel = null

const filters = reactive({
  date_from: '',
  date_to: '',
  event_kind: '',
  module: '',
  event_type: '',
})

const modules = ['auth', 'dashboard', 'leads', 'deals', 'listings', 'marketing', 'hr', 'settings', 'app']

const elapsed = computed(() => {
  if (!presence.value?.is_online || !presence.value?.online_since) return '—'
  const ms = Math.max(0, nowTick.value - new Date(presence.value.online_since).getTime())
  const sec = Math.floor(ms / 1000)
  const min = Math.floor(sec / 60)
  const hr = Math.floor(min / 60)
  if (hr > 0) return `${hr}h ${min % 60}m`
  if (min > 0) return `${min}m ${sec % 60}s`
  return `${sec}s`
})

function formatWhen(iso) {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleString()
  } catch {
    return iso
  }
}

function iconFor(ev) {
  if (ev.event_kind === 'business') return 'lucide:briefcase'
  if (ev.event_type?.includes('lead')) return 'lucide:user-round'
  if (ev.event_type?.includes('deal')) return 'lucide:handshake'
  if (ev.event_type?.includes('listing')) return 'lucide:building-2'
  if (ev.event_type?.includes('login')) return 'lucide:log-in'
  if (ev.event_type?.includes('logout')) return 'lucide:log-out'
  return 'lucide:navigation'
}

function entityLink(ev) {
  if (ev.entity_type === 'lead') return `#lead-${ev.entity_id}`
  if (ev.entity_type === 'deal') return `#deal-${ev.entity_id}`
  if (ev.entity_type === 'listing' || ev.entity_type === 'Listing') {
    return `/property-details/${ev.entity_id}`
  }
  return null
}

function openEntity(ev) {
  if (ev.entity_type === 'lead') {
    openLeadView(ev.entity_id)
    return
  }
  if (ev.entity_type === 'deal') {
    openDealView(ev.entity_id)
    return
  }
  if (ev.entity_type === 'listing' || ev.entity_type === 'Listing') {
    router.push(`/property-details/${ev.entity_id}`)
  }
}

async function fetchPresence() {
  const { data } = await api.get('/admin/live-watch/presence')
  presence.value = data?.data || null
  selectedUserId.value = presence.value?.monitored_user_id ?? null
  heartbeatSeconds.value = presence.value?.heartbeat_interval_seconds || 30
  offlineTimeout.value = presence.value?.offline_timeout_seconds || 90
  if (presence.value?.monitored_user) {
    const u = presence.value.monitored_user
    if (!userOptions.value.find((x) => x.id === u.id)) {
      userOptions.value = [u, ...userOptions.value]
    }
  }
}

async function fetchTimeline() {
  loadingTimeline.value = true
  try {
    const params = {}
    Object.entries(filters).forEach(([k, v]) => {
      if (v) params[k] = v
    })
    const { data } = await api.get('/admin/live-watch/timeline', { params })
    events.value = data?.data?.items || []
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load timeline'
  } finally {
    loadingTimeline.value = false
  }
}

async function searchUsers(q = '') {
  const { data } = await api.get('/admin/live-watch/users', { params: { search: q } })
  userOptions.value = data?.data || []
}

function onSearchUsers() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => searchUsers(userSearch.value), 250)
}

async function saveSettings() {
  saving.value = true
  error.value = ''
  try {
    const { data } = await api.put('/admin/live-watch/settings', {
      monitored_user_id: selectedUserId.value,
      heartbeat_interval_seconds: heartbeatSeconds.value,
      offline_timeout_seconds: offlineTimeout.value,
    })
    presence.value = data?.data || null
    await fetchTimeline()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to save settings'
  } finally {
    saving.value = false
  }
}

async function refreshAll() {
  loading.value = true
  error.value = ''
  try {
    await Promise.all([fetchPresence(), fetchTimeline(), searchUsers(userSearch.value)])
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load Live Watch'
  } finally {
    loading.value = false
  }
}

function subscribe() {
  echoOff = whenEchoReady(() => {
    if (!window.Echo || channel) return
    channel = window.Echo.private('live-watch')
    channel.listen('.presence.changed', async () => {
      try {
        await fetchPresence()
      } catch {
        /* ignore */
      }
    })
    channel.listen('.timeline.event', (event) => {
      if (!event?.id) return
      if (filters.event_kind && event.event_kind !== filters.event_kind) return
      if (filters.module && event.module !== filters.module) return
      if (filters.event_type && event.event_type !== filters.event_type) return
      if (events.value.some((x) => x.id === event.id)) return
      events.value = [event, ...events.value].slice(0, 200)
    })
  })
}

onMounted(() => {
  refreshAll()
  subscribe()
  tickTimer = setInterval(() => {
    nowTick.value = Date.now()
  }, 1000)
})

onBeforeUnmount(() => {
  clearInterval(tickTimer)
  clearTimeout(searchTimer)
  echoOff?.()
  if (channel && window.Echo) {
    try {
      window.Echo.leave('live-watch')
    } catch {
      /* ignore */
    }
  }
})
</script>

<style scoped>
.lw-page { padding-bottom: 40px; }
.lw-shell { max-width: 1100px; margin: 0 auto; }
.lw-top { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; margin-bottom: 18px; }
.lw-title { font-size: 1.35rem; font-weight: 700; }
.lw-hint { margin: 4px 0 0; font-size: 12px; opacity: 0.7; max-width: 640px; }
.lw-actions { display: flex; gap: 8px; }
.lw-btn {
  display: inline-flex; align-items: center; gap: 6px;
  border-radius: 10px; border: 1px solid rgba(0,0,0,.1);
  background: #fff; padding: 8px 12px; font-size: 12px; cursor: pointer;
}
.lw-btn--primary { background: #111827; color: #fff; border-color: #111827; }
.lw-error {
  background: #fef2f2; color: #991b1b; border-radius: 10px; padding: 10px 12px;
  display: flex; justify-content: space-between; gap: 12px; margin-bottom: 12px;
}
.lw-link { background: none; border: 0; color: inherit; text-decoration: underline; cursor: pointer; }
.lw-config {
  display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;
  padding: 14px; border-radius: 14px; background: rgba(255,255,255,.72);
  border: 1px solid rgba(0,0,0,.06); margin-bottom: 14px;
}
.lw-field { display: flex; flex-direction: column; gap: 4px; min-width: 220px; flex: 1; }
.lw-field--sm { min-width: 120px; flex: 0 0 140px; }
.lw-field label { font-size: 11px; opacity: .7; text-transform: uppercase; letter-spacing: .04em; }
.lw-field input, .lw-field select, .lw-user-pick input, .lw-filters input, .lw-filters select {
  border: 1px solid rgba(0,0,0,.12); border-radius: 8px; padding: 7px 9px; font-size: 13px; background: #fff;
}
.lw-user-pick { display: flex; flex-direction: column; gap: 6px; }
.lw-metrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-bottom: 14px; }
.lw-metric {
  border-radius: 14px; padding: 14px; background: rgba(255,255,255,.75);
  border: 1px solid rgba(0,0,0,.06);
}
.lw-metric.is-online { border-color: rgba(16,185,129,.35); background: rgba(16,185,129,.08); }
.lw-metric__n { display: block; font-size: 1.05rem; font-weight: 700; }
.lw-metric__l { font-size: 11px; opacity: .65; }
.lw-filters { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.lw-label { font-size: 12px; font-weight: 650; margin: 8px 0; display: flex; gap: 8px; align-items: baseline; }
.lw-label__meta { font-weight: 400; opacity: .55; }
.lw-loading, .lw-empty { padding: 24px; opacity: .65; text-align: center; }
.lw-timeline { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.lw-item {
  display: flex; gap: 10px; padding: 10px 12px; border-radius: 12px;
  background: rgba(255,255,255,.78); border: 1px solid rgba(0,0,0,.05);
}
.lw-item.is-navigation { border-left: 3px solid #60a5fa; }
.lw-item.is-business { border-left: 3px solid #34d399; }
.lw-item__icon {
  width: 28px; height: 28px; border-radius: 8px; display: grid; place-items: center;
  background: rgba(0,0,0,.04); flex: 0 0 auto;
}
.lw-item__top { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.lw-pill {
  font-size: 10px; text-transform: uppercase; letter-spacing: .04em;
  padding: 2px 6px; border-radius: 999px; background: #e5e7eb;
}
.lw-pill.business { background: #d1fae5; color: #065f46; }
.lw-pill.navigation { background: #dbeafe; color: #1e3a8a; }
.lw-item__type { font-weight: 600; font-size: 13px; }
.lw-item__time { margin-left: auto; font-size: 11px; opacity: .6; font-variant-numeric: tabular-nums; }
.lw-item__desc { font-size: 12px; opacity: .85; margin-top: 2px; }
.lw-entity { color: inherit; text-decoration: underline; }

@media (max-width: 768px) {
  .lw-metrics { grid-template-columns: 1fr; }
  .lw-top { flex-direction: column; }
}
</style>
