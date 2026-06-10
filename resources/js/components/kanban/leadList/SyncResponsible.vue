<template>
  <div class="container py-4 b24-page">
    <div class="card shadow-sm b24-card">
      <div class="card-header b24-card__header text-white d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <i class="ri-user-shared-line fs-4"></i>
          <h5 class="mb-0">Bitrix24 Responsible Sync</h5>
        </div>
        <span class="badge" :class="badgeClass">{{ statusLabel }}</span>
      </div>

      <div class="card-body b24-card__body">
        <p class="text-muted small mb-3">
          Pulls the current assignee (ASSIGNED_BY_ID) from Bitrix24 and updates each lead's
          responsible person locally.
        </p>

        <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
          <button class="btn btn-primary" :disabled="loading || isRunning" @click="start(false)">
            <span v-if="loading">Starting...</span>
            <span v-else-if="isRunning">Sync in progress</span>
            <span v-else>Run responsible sync</span>
          </button>
          <button class="btn btn-outline-secondary" :disabled="loading || isRunning" @click="start(true)">
            Dry run
          </button>
          <button v-if="isRunning" class="btn btn-outline-danger" :disabled="canceling" @click="cancel">
            {{ canceling ? 'Cancelling...' : 'Cancel' }}
          </button>
        </div>

        <div class="progress mb-2" style="height: 10px;">
          <div class="progress-bar" :class="progressBarClass" :style="{ width: state.progress + '%' }"></div>
        </div>
        <small class="text-muted">{{ state.scanned }} / {{ state.total || '—' }} scanned ({{ state.progress }}%)</small>

        <div class="row g-2 mt-3">
          <div class="col-6 col-md-3">
            <div class="stat-tile"><div class="stat-tile__label">Updated</div><div class="stat-tile__value text-success">{{ state.updated }}</div></div>
          </div>
          <div class="col-6 col-md-3">
            <div class="stat-tile"><div class="stat-tile__label">Scanned</div><div class="stat-tile__value">{{ state.scanned }}</div></div>
          </div>
          <div class="col-6 col-md-3">
            <div class="stat-tile"><div class="stat-tile__label">Unmapped users</div><div class="stat-tile__value text-warning">{{ state.unmapped }}</div></div>
          </div>
          <div class="col-6 col-md-3">
            <div class="stat-tile"><div class="stat-tile__label">Not in local DB</div><div class="stat-tile__value text-secondary">{{ state.no_local }}</div></div>
          </div>
        </div>

        <div v-if="completionSummary" class="alert mt-3 mb-0 py-2 px-3" :class="completionAlertClass" style="font-size: 13px;">
          <i class="ri-information-line me-1"></i>{{ completionSummary }}
        </div>

        <div v-if="state.last_error" class="alert alert-danger mt-3 mb-0 py-2 px-3" style="font-size: 13px;">
          <i class="ri-error-warning-line me-1"></i>{{ state.last_error }}
        </div>

        <div class="event-feed mt-3">
          <div class="event-feed__head d-flex align-items-center justify-content-between">
            <span class="text-muted small text-uppercase">Live activity</span>
            <span class="text-muted small" v-if="isRunning">updating…</span>
          </div>
          <ul class="event-feed__list">
            <li v-for="(ev, i) in state.events" :key="i" class="event-feed__item" :class="`event-feed__item--${ev.type}`">
              <span class="event-feed__icon"><i :class="eventIcon(ev.type)"></i></span>
              <span class="event-feed__msg" :title="ev.message">{{ ev.message }}</span>
              <span class="event-feed__time">{{ formatTime(ev.at) }}</span>
            </li>
            <li v-if="!state.events.length" class="event-feed__empty text-muted small">No activity yet.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const loading = ref(false)
const canceling = ref(false)
const completionSummary = ref('')
const lastTerminal = ref(null)

const state = reactive({
  status: 'idle', progress: 0, total: 0, scanned: 0, updated: 0, unmapped: 0, no_local: 0,
  last_error: null, started_at: null, finished_at: null, events: [],
})

const isRunning = computed(() => state.status === 'running')
const statusLabel = computed(() => ({ idle: 'Idle', running: 'Running', done: 'Done', failed: 'Failed', cancelled: 'Cancelled' }[state.status] || state.status))
const badgeClass = computed(() => ({
  'bg-secondary': state.status === 'idle', 'bg-primary': state.status === 'running',
  'bg-success': state.status === 'done', 'bg-danger': state.status === 'failed',
  'bg-warning text-dark': state.status === 'cancelled',
}))
const progressBarClass = computed(() => ({
  'bg-success': state.status === 'done', 'bg-danger': state.status === 'failed', 'bg-primary': state.status === 'running',
  'progress-bar-striped progress-bar-animated': state.status === 'running',
}))
const completionAlertClass = computed(() => ({
  'alert-success': lastTerminal.value === 'done', 'alert-warning': lastTerminal.value === 'cancelled', 'alert-danger': lastTerminal.value === 'failed',
}))

let pollInterval = null
const formatTime = (iso) => { try { return new Date(iso).toLocaleTimeString() } catch { return '' } }
const eventIcon = (t) => ({ updated: 'ri-refresh-line', info: 'ri-information-line', error: 'ri-error-warning-line' }[t] || 'ri-circle-line')

const applyState = (q) => {
  const prev = state.status
  Object.assign(state, {
    status: q.status || 'idle', progress: q.progress || 0, total: q.total || 0,
    scanned: q.scanned || 0, updated: q.updated || 0, unmapped: q.unmapped || 0, no_local: q.no_local || 0,
    last_error: q.last_error || null, started_at: q.started_at || null, finished_at: q.finished_at || null,
    events: Array.isArray(q.events) ? q.events : [],
  })
  if (['done', 'failed', 'cancelled'].includes(state.status)) {
    if (prev === 'running') {
      lastTerminal.value = state.status
      completionSummary.value = `Responsible sync ${state.status}: ${state.updated} updated, ${state.scanned} scanned, ${state.unmapped} unmapped.`
      try { window.$showNotification?.(completionSummary.value, state.status === 'failed' ? 'error' : 'success') } catch {}
    }
    stopPolling()
  }
}

const fetchProgress = async () => {
  try { const { data } = await axios.get('/api/bitrix24/sync-responsible/progress'); applyState(data?.data ?? data) }
  catch (e) { console.error('responsible progress failed', e) }
}
const startPolling = () => { stopPolling(); pollInterval = setInterval(fetchProgress, 2000) }
const stopPolling = () => { if (pollInterval) clearInterval(pollInterval); pollInterval = null }

const start = async (dryRun) => {
  if (isRunning.value) return
  loading.value = true
  completionSummary.value = ''
  lastTerminal.value = null
  try {
    await axios.post('/api/leads/bitrix24/sync-responsible/start', { dry_run: dryRun })
    state.status = 'running'
    startPolling()
    await fetchProgress()
  } catch (e) {
    state.last_error = e.response?.data?.message || e.message || 'Failed to start'
  } finally {
    loading.value = false
  }
}

const cancel = async () => {
  if (!isRunning.value) return
  canceling.value = true
  try { await axios.post('/api/leads/bitrix24/sync-responsible/cancel'); await fetchProgress() }
  catch (e) { state.last_error = e.response?.data?.message || e.message }
  finally { canceling.value = false }
}

onMounted(async () => { await fetchProgress(); if (isRunning.value) startPolling() })
onUnmounted(stopPolling)
</script>

<style scoped>
.b24-page { background: linear-gradient(180deg, #eef2ff 0%, #f5f7fb 60%, #f8fafc 100%); border-radius: 16px; padding-top: 1.5rem !important; padding-bottom: 1.5rem !important; }
.b24-card { border: none; border-radius: 14px; overflow: hidden; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08); }
.b24-card__header { background: linear-gradient(135deg, #0ea5e9 0%, #1d4ed8 60%, #1e1b4b 100%); padding: 14px 18px; }
.b24-card__header h5 { font-weight: 700; }
.b24-card__body { background: #fff; padding: 22px; }
.stat-tile { background: linear-gradient(180deg, #fff 0%, #f8fafc 100%); border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; }
.stat-tile__label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; }
.stat-tile__value { font-size: 22px; font-weight: 700; font-variant-numeric: tabular-nums; }
:deep(.progress) { background-color: #e2e8f0; border-radius: 999px; overflow: hidden; }
:deep(.progress-bar) { border-radius: 999px; }
.event-feed { border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; overflow: hidden; }
.event-feed__head { padding: 8px 12px; border-bottom: 1px solid #eef2f7; background: #f8fafc; }
.event-feed__list { list-style: none; margin: 0; padding: 0; max-height: 280px; overflow-y: auto; }
.event-feed__item { display: flex; align-items: center; gap: 8px; padding: 7px 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
.event-feed__item:last-child { border-bottom: none; }
.event-feed__icon { flex: 0 0 auto; width: 18px; text-align: center; }
.event-feed__msg { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.event-feed__time { flex: 0 0 auto; color: #94a3b8; font-size: 11px; }
.event-feed__item--updated .event-feed__icon { color: #2563eb; }
.event-feed__item--error .event-feed__icon { color: #dc2626; }
.event-feed__item--info .event-feed__icon { color: #64748b; }
.event-feed__empty { padding: 14px 12px; }
</style>
