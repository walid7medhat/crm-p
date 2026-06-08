<template>
  <div class="container py-4 b24-page">
    <div class="card shadow-sm b24-card">
      <div class="card-header b24-card__header text-white d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <i class="ri-refresh-line fs-4"></i>
          <h5 class="mb-0">Bitrix24 Lead Sync</h5>
        </div>
        <span class="badge" :class="badgeClass">{{ statusLabel }}</span>
      </div>

      <div class="card-body b24-card__body">

        <!-- Controls -->
        <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
          <button class="btn btn-primary" :disabled="loading || isRunning" @click="startSync">
            <span v-if="loading">Starting...</span>
            <span v-else-if="isRunning">Sync in progress</span>
            <span v-else>Run lead sync</span>
          </button>

          <button v-if="isRunning" class="btn btn-outline-danger" :disabled="canceling" @click="cancelSync">
            {{ canceling ? 'Cancelling...' : 'Cancel sync' }}
          </button>

          <div class="d-flex align-items-center gap-2">
            <label class="form-label small mb-0 text-muted" for="b24-limit">Limit</label>
            <input
              id="b24-limit"
              v-model.number="limit"
              type="number"
              min="0"
              class="form-control form-control-sm"
              style="width: 96px;"
              :disabled="isRunning"
              placeholder="0 = all"
            />
          </div>

          <div class="form-check">
            <input id="sl-fast" class="form-check-input" type="checkbox" v-model="fastMode" :disabled="isRunning" />
            <label class="form-check-label small" for="sl-fast">Fast mode (no comments/timeline)</label>
          </div>

          <div class="form-check ms-auto">
            <input id="sl-skip-existing" class="form-check-input" type="checkbox" v-model="skipExisting" :disabled="isRunning" />
            <label class="form-check-label small" for="sl-skip-existing">Skip already-imported leads</label>
          </div>
        </div>

        <!-- Progress bar -->
        <div class="progress mb-2" style="height: 10px;">
          <div
            class="progress-bar"
            :class="progressBarClass"
            :style="{ width: state.progress + '%' }"
            role="progressbar"
            :aria-valuenow="state.progress"
          ></div>
        </div>
        <small class="text-muted">
          {{ state.processed }} / {{ state.total || '—' }} leads processed ({{ state.progress }}%)
        </small>

        <!-- Stats -->
        <div class="row g-2 mt-3">
          <div class="col-6 col-md-3">
            <div class="stat-tile">
              <div class="stat-tile__label">Created</div>
              <div class="stat-tile__value text-success">{{ state.created }}</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="stat-tile">
              <div class="stat-tile__label">Updated</div>
              <div class="stat-tile__value text-primary">{{ state.updated }}</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="stat-tile">
              <div class="stat-tile__label">Skipped</div>
              <div class="stat-tile__value text-secondary">{{ state.skipped }}</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="stat-tile">
              <div class="stat-tile__label">Errors</div>
              <div class="stat-tile__value text-danger">{{ state.errors }}</div>
            </div>
          </div>
        </div>

        <!-- Change breakdown: what was updated -->
        <p class="text-muted small text-uppercase mt-3 mb-1">What changed (on updates)</p>
        <div class="row g-2">
          <div class="col-6 col-md">
            <div class="stat-tile stat-tile--mini">
              <div class="stat-tile__label">Stage</div>
              <div class="stat-tile__value stat-tile__value--mini">{{ state.stage_changed }}</div>
            </div>
          </div>
          <div class="col-6 col-md">
            <div class="stat-tile stat-tile--mini">
              <div class="stat-tile__label">Status</div>
              <div class="stat-tile__value stat-tile__value--mini">{{ state.status_changed }}</div>
            </div>
          </div>
          <div class="col-6 col-md">
            <div class="stat-tile stat-tile--mini">
              <div class="stat-tile__label">Owner</div>
              <div class="stat-tile__value stat-tile__value--mini">{{ state.owner_changed }}</div>
            </div>
          </div>
          <div class="col-6 col-md">
            <div class="stat-tile stat-tile--mini">
              <div class="stat-tile__label">Source</div>
              <div class="stat-tile__value stat-tile__value--mini">{{ state.source_changed }}</div>
            </div>
          </div>
          <div class="col-6 col-md">
            <div class="stat-tile stat-tile--mini">
              <div class="stat-tile__label">Activity person</div>
              <div class="stat-tile__value stat-tile__value--mini">{{ state.activity_changed }}</div>
            </div>
          </div>
        </div>

        <!-- Completion banner ("call me what happened") -->
        <div v-if="completionSummary" class="alert mt-3 mb-0 py-2 px-3" :class="completionAlertClass" style="font-size: 13px;">
          <i class="ri-information-line me-1"></i>
          {{ completionSummary }}
        </div>

        <!-- Timing -->
        <div v-if="state.started_at" class="text-muted small mt-3">
          Started: {{ formatDate(state.started_at) }}
          <span v-if="state.finished_at"> · Finished: {{ formatDate(state.finished_at) }}</span>
        </div>

        <!-- Error -->
        <div v-if="state.last_error" class="alert alert-danger mt-3 mb-0 py-2 px-3" style="font-size: 13px;">
          <i class="ri-error-warning-line me-1"></i>
          {{ state.last_error }}
        </div>

        <!-- Live event feed -->
        <div class="event-feed mt-3">
          <div class="event-feed__head d-flex align-items-center justify-content-between">
            <span class="text-muted small text-uppercase">Live activity</span>
            <span class="text-muted small" v-if="isRunning">updating…</span>
          </div>
          <ul class="event-feed__list">
            <li v-for="(ev, i) in state.events" :key="i" class="event-feed__item" :class="`event-feed__item--${ev.type}`">
              <span class="event-feed__icon">
                <i :class="eventIcon(ev.type)"></i>
              </span>
              <span v-if="ev.b24" class="event-feed__b24" title="Bitrix24 ID">#{{ ev.b24 }}</span>
              <span class="event-feed__msg" :title="ev.message">{{ ev.message }}</span>
              <span class="event-feed__time">{{ formatTime(ev.at) }}</span>
            </li>
            <li v-if="!state.events.length" class="event-feed__empty text-muted small">
              No activity yet — run the sync to see created / updated leads here.
            </li>
          </ul>
        </div>

        <details class="mt-3">
          <summary class="text-muted small" style="cursor: pointer;">How does this work?</summary>
          <p class="small text-muted mt-2 mb-0">
            Runs the <code>bitrix24:sync-leads</code> command on the queue. For each Bitrix24 lead it
            creates a new lead or updates an existing one — syncing stage (Bitrix "Future Prospected"
            maps to <code>qualified</code>), source, the activity person, comments and activities.
            This panel polls every 2 seconds and shows the created / updated feed and counts. You can
            also run the command on the server (cPanel terminal/cron) and watch the same progress here.
          </p>
        </details>

      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const loading = ref(false)
const canceling = ref(false)
const skipExisting = ref(false)
const fastMode = ref(false)
const limit = ref(0)
const completionSummary = ref('')
const lastTerminalStatus = ref(null)

const state = reactive({
  status: 'idle',
  progress: 0,
  processed: 0,
  total: 0,
  created: 0,
  updated: 0,
  skipped: 0,
  errors: 0,
  stage_changed: 0,
  status_changed: 0,
  owner_changed: 0,
  source_changed: 0,
  activity_changed: 0,
  last_error: null,
  started_at: null,
  finished_at: null,
  events: [],
})

const isRunning = computed(() => state.status === 'running')

const statusLabel = computed(() => ({
  idle: 'Idle', running: 'Running', done: 'Done', failed: 'Failed', cancelled: 'Cancelled',
}[state.status] || state.status))

const badgeClass = computed(() => ({
  'bg-secondary': state.status === 'idle',
  'bg-primary':   state.status === 'running',
  'bg-success':   state.status === 'done',
  'bg-danger':    state.status === 'failed',
  'bg-warning text-dark': state.status === 'cancelled',
}))

const progressBarClass = computed(() => ({
  'bg-success': state.status === 'done',
  'bg-danger':  state.status === 'failed',
  'bg-primary': state.status === 'running',
  'progress-bar-striped progress-bar-animated': state.status === 'running',
}))

const completionAlertClass = computed(() => ({
  'alert-success': lastTerminalStatus.value === 'done',
  'alert-warning': lastTerminalStatus.value === 'cancelled',
  'alert-danger':  lastTerminalStatus.value === 'failed',
}))

let pollInterval = null

const formatDate = (iso) => { try { return new Date(iso).toLocaleString() } catch { return iso } }
const formatTime = (iso) => { try { return new Date(iso).toLocaleTimeString() } catch { return '' } }

const eventIcon = (type) => ({
  created: 'ri-add-circle-line',
  updated: 'ri-refresh-line',
  error:   'ri-error-warning-line',
  info:    'ri-information-line',
}[type] || 'ri-circle-line')

const notify = (summary) => {
  completionSummary.value = summary
  // Best-effort: also surface a global toast if the app provides one.
  try { window.$showNotification?.(summary, lastTerminalStatus.value === 'failed' ? 'error' : 'success') } catch {}
  // Best-effort browser notification ("call me what happened").
  try {
    if (typeof Notification !== 'undefined') {
      if (Notification.permission === 'granted') new Notification('Bitrix24 Lead Sync', { body: summary })
      else if (Notification.permission !== 'denied') Notification.requestPermission()
    }
  } catch {}
}

const applyState = (q) => {
  const prevStatus = state.status
  Object.assign(state, {
    status: q.status || 'idle',
    progress: q.progress || 0,
    processed: q.processed || 0,
    total: q.total || 0,
    created: q.created || 0,
    updated: q.updated || 0,
    skipped: q.skipped || 0,
    errors: q.errors || 0,
    stage_changed: q.stage_changed || 0,
    status_changed: q.status_changed || 0,
    owner_changed: q.owner_changed || 0,
    source_changed: q.source_changed || 0,
    activity_changed: q.activity_changed || 0,
    last_error: q.last_error || null,
    started_at: q.started_at || null,
    finished_at: q.finished_at || null,
    events: Array.isArray(q.events) ? q.events : [],
  })

  const terminal = ['done', 'failed', 'cancelled']
  if (terminal.includes(state.status)) {
    if (prevStatus === 'running' || (lastTerminalStatus.value === null && state.processed > 0)) {
      lastTerminalStatus.value = state.status
      notify(
        `Sync ${state.status}: ${state.created} created, ${state.updated} updated, ` +
        `${state.skipped} skipped, ${state.errors} errors ` +
        `(stage ${state.stage_changed} · source ${state.source_changed} · activity ${state.activity_changed}).`
      )
    }
    stopPolling()
  }
}

const fetchProgress = async () => {
  try {
    const { data } = await axios.get('/api/bitrix24/sync-leads/progress')
    applyState(data?.data ?? data)
  } catch (e) {
    console.error('sync-leads progress fetch failed', e)
  }
}

const startPolling = () => { stopPolling(); pollInterval = setInterval(fetchProgress, 2000) }
const stopPolling = () => { if (pollInterval) clearInterval(pollInterval); pollInterval = null }

const startSync = async () => {
  if (isRunning.value) return
  loading.value = true
  completionSummary.value = ''
  lastTerminalStatus.value = null
  try {
    await axios.post('/api/leads/bitrix24/sync-leads/start', {
      skip_existing: skipExisting.value,
      fast: fastMode.value,
      limit: Number(limit.value) || 0,
    })
    state.status = 'running'
    state.last_error = null
    startPolling()
    await fetchProgress()
  } catch (e) {
    state.last_error = e.response?.data?.message || e.message || 'Failed to start sync'
  } finally {
    loading.value = false
  }
}

const cancelSync = async () => {
  if (!isRunning.value) return
  canceling.value = true
  try {
    await axios.post('/api/leads/bitrix24/sync-leads/cancel')
    await fetchProgress()
  } catch (e) {
    state.last_error = e.response?.data?.message || e.message || 'Failed to cancel sync'
  } finally {
    canceling.value = false
  }
}

onMounted(async () => {
  await fetchProgress()
  if (isRunning.value) startPolling()
})

onUnmounted(stopPolling)
</script>

<style scoped>
.b24-page {
  background: linear-gradient(180deg, #eef2ff 0%, #f5f7fb 60%, #f8fafc 100%);
  border-radius: 16px;
  padding-top: 1.5rem !important;
  padding-bottom: 1.5rem !important;
}

.b24-card {
  border: none;
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
}

.b24-card__header {
  background: linear-gradient(135deg, #0ea5e9 0%, #1d4ed8 60%, #1e1b4b 100%);
  border-bottom: 1px solid rgba(255, 255, 255, 0.12);
  padding: 14px 18px;
}

.b24-card__header h5 { font-weight: 700; letter-spacing: 0.01em; }

.b24-card__body {
  background: #ffffff;
  background-image:
    radial-gradient(at 0% 0%, rgba(14, 165, 233, 0.06), transparent 40%),
    radial-gradient(at 100% 0%, rgba(30, 64, 175, 0.05), transparent 40%);
  padding: 22px 22px 18px;
}

.stat-tile {
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 10px 12px;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.stat-tile:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06); }
.stat-tile--mini { padding: 6px 10px; }

.stat-tile__label {
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #64748b;
}

.stat-tile__value { font-size: 22px; font-weight: 700; font-variant-numeric: tabular-nums; }
.stat-tile__value--mini { font-size: 16px; }

:deep(.progress) { background-color: #e2e8f0; border-radius: 999px; overflow: hidden; }
:deep(.progress-bar) { border-radius: 999px; }

.event-feed {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #ffffff;
  overflow: hidden;
}
.event-feed__head { padding: 8px 12px; border-bottom: 1px solid #eef2f7; background: #f8fafc; }
.event-feed__list { list-style: none; margin: 0; padding: 0; max-height: 280px; overflow-y: auto; }
.event-feed__item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 7px 12px;
  border-bottom: 1px solid #f1f5f9;
  font-size: 13px;
}
.event-feed__item:last-child { border-bottom: none; }
.event-feed__icon { flex: 0 0 auto; width: 18px; text-align: center; }
.event-feed__b24 {
  flex: 0 0 auto;
  font-size: 10px;
  font-weight: 700;
  color: #1d4ed8;
  background: #eff6ff;
  border: 1px solid #dbeafe;
  border-radius: 6px;
  padding: 1px 5px;
  font-variant-numeric: tabular-nums;
}
.event-feed__msg { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.event-feed__time { flex: 0 0 auto; color: #94a3b8; font-size: 11px; font-variant-numeric: tabular-nums; }
.event-feed__item--created .event-feed__icon { color: #16a34a; }
.event-feed__item--updated .event-feed__icon { color: #2563eb; }
.event-feed__item--error  .event-feed__icon { color: #dc2626; }
.event-feed__item--info   .event-feed__icon { color: #64748b; }
.event-feed__empty { padding: 14px 12px; }
</style>
