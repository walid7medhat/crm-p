<template>
  <div class="container py-4 b24-page">
    <div class="card shadow-sm b24-card">
      <div class="card-header b24-card__header text-white d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <i class="ri-cloud-line fs-4"></i>
          <h5 class="mb-0">Bitrix24 Queue Monitor</h5>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span v-if="isPolling" class="badge bg-light text-primary b24-live">
            <span class="b24-live__dot"></span> Live
          </span>
          <span class="badge" :class="badgeClass">{{ statusLabel }}</span>
        </div>
      </div>

      <div class="card-body b24-card__body">

        <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
          <button
            type="button"
            class="btn btn-primary"
            :disabled="loading || isRunning"
            @click="startQueue"
          >
            <span v-if="loading">{{ canResume ? 'Resuming...' : 'Starting...' }}</span>
            <span v-else-if="isRunning">Sync in progress</span>
            <span v-else-if="queue.status === 'done'">Restart sync (fresh)</span>
            <span v-else-if="canResume">Resume sync (cursor {{ queue.cursor }})</span>
            <span v-else>Start Queue Sync</span>
          </button>

          <button
            v-if="isRunning"
            type="button"
            class="btn btn-outline-danger"
            :disabled="canceling"
            @click="cancelQueue"
          >
            {{ canceling ? 'Cancelling...' : 'Cancel sync' }}
          </button>

          <button
            v-if="!isRunning && queue.status !== 'idle'"
            type="button"
            class="btn btn-outline-secondary btn-sm"
            :disabled="resetting"
            @click="resetQueue"
          >
            {{ resetting ? 'Resetting...' : 'Reset state' }}
          </button>

          <div class="form-check ms-auto">
            <input
              id="skip-existing"
              class="form-check-input"
              type="checkbox"
              v-model="skipExisting"
              :disabled="isRunning"
            />
            <label class="form-check-label small" for="skip-existing">
              Skip already-imported leads (faster)
            </label>
          </div>
        </div>

        <div class="progress mb-2" style="height: 10px;">
          <div
            class="progress-bar"
            :class="progressBarClass"
            :style="{ width: Math.min(100, queue.progress) + '%' }"
            role="progressbar"
            :aria-valuenow="queue.progress"
          ></div>
        </div>
        <small class="text-muted d-block">
          {{ queue.processed.toLocaleString() }} / {{ queue.total ? queue.total.toLocaleString() : '—' }} leads processed ({{ queue.progress }}%)
          <span v-if="isRunning && queue.leads_per_sec > 0" class="ms-2">
            · {{ queue.leads_per_sec }} leads/sec
            <span v-if="queue.eta_seconds"> · ~{{ formatEta(queue.eta_seconds) }} left</span>
          </span>
          <span v-if="isRunning && lastFetchedAt" class="ms-2 text-primary">
            · updated {{ secondsSinceFetch }}s ago
          </span>
        </small>

        <div class="row g-2 mt-3">
          <div class="col-6 col-md-3">
            <div class="stat-tile" :class="{ 'stat-tile--pulse': statPulse.new }">
              <div class="stat-tile__label">New</div>
              <div class="stat-tile__value text-success">{{ queue.new.toLocaleString() }}</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="stat-tile" :class="{ 'stat-tile--pulse': statPulse.existing }">
              <div class="stat-tile__label">Already existed</div>
              <div class="stat-tile__value text-secondary">{{ queue.existing.toLocaleString() }}</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="stat-tile" :class="{ 'stat-tile--pulse': statPulse.errors }">
              <div class="stat-tile__label">Errors</div>
              <div class="stat-tile__value text-danger">{{ queue.errors.toLocaleString() }}</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="stat-tile" :class="{ 'stat-tile--pulse': statPulse.processed }">
              <div class="stat-tile__label">Processed</div>
              <div class="stat-tile__value">{{ queue.processed.toLocaleString() }}</div>
            </div>
          </div>
        </div>

        <div v-if="queue.started_at" class="text-muted small mt-3">
          Started: {{ formatDate(queue.started_at) }}
          <span v-if="queue.finished_at"> · Finished: {{ formatDate(queue.finished_at) }}</span>
        </div>

        <div v-if="actionMessage" class="alert alert-info mt-3 mb-0 py-2 px-3" style="font-size: 13px;">
          {{ actionMessage }}
        </div>

        <div v-if="queue.last_error" class="alert alert-danger mt-3 mb-0 py-2 px-3" style="font-size: 13px;">
          <i class="ri-error-warning-line me-1"></i>
          {{ queue.last_error }}
        </div>

        <details class="mt-3">
          <summary class="text-muted small" style="cursor: pointer;">How does this work?</summary>
          <p class="small text-muted mt-2 mb-0">
            Run a queue worker in a terminal:
            <code>php artisan queue:work database --queue=default,bitrix24 --timeout=900</code>.
            Counters refresh every second while sync is running (every ~5 leads during import). Check
            <strong>Skip already-imported</strong> for fastest import of 160k+ leads.
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
const resetting = ref(false)
const skipExisting = ref(true)
const actionMessage = ref('')
const isPolling = ref(false)
const lastFetchedAt = ref(null)
const tickNow = ref(Date.now())

const queue = reactive({
  status: 'idle',
  progress: 0,
  processed: 0,
  total: 0,
  new: 0,
  existing: 0,
  errors: 0,
  cursor: 0,
  last_error: null,
  started_at: null,
  finished_at: null,
  updated_at: null,
  leads_per_sec: 0,
  eta_seconds: null,
})

const statPulse = reactive({
  processed: false,
  new: false,
  existing: false,
  errors: false,
})

let pulseTimers = {}

const isRunning = computed(() => queue.status === 'running')

const canResume = computed(() =>
  ['cancelled', 'failed', 'paused'].includes(queue.status)
)

const statusLabel = computed(() => {
  const labels = {
    idle: 'Idle',
    running: 'Running',
    done: 'Done',
    failed: 'Failed',
    cancelled: 'Cancelled',
    paused: 'Paused',
  }
  return labels[queue.status] || String(queue.status)
})

const badgeClass = computed(() => ({
  'bg-secondary': queue.status === 'idle',
  'bg-primary':   queue.status === 'running',
  'bg-success':   queue.status === 'done',
  'bg-danger':    queue.status === 'failed',
  'bg-warning':   queue.status === 'cancelled' || queue.status === 'paused',
  'text-dark':    queue.status === 'cancelled' || queue.status === 'paused',
}))

const progressBarClass = computed(() => ({
  'bg-success':     queue.status === 'done',
  'bg-danger':      queue.status === 'failed',
  'bg-primary':     queue.status === 'running',
  'progress-bar-striped progress-bar-animated': queue.status === 'running',
}))

let pollInterval = null
let tickInterval = null
const POLL_MS = 1000

const secondsSinceFetch = computed(() => {
  if (!lastFetchedAt.value) return null
  return Math.max(0, Math.floor((tickNow.value - lastFetchedAt.value) / 1000))
})

const apiErrorMessage = (err, fallback) => {
  const body = err?.response?.data
  if (typeof body?.message === 'string') return body.message
  if (typeof body?.errors === 'string') return body.errors
  return err?.message || fallback
}

/**
 * Unwrap Laravel ApiResponse { status: true, message, data: { status: 'running', ... } }.
 * Never confuse API success flag (boolean) with sync status (string).
 */
const parseQueuePayload = (response) => {
  const body = response?.data ?? response
  if (!body || typeof body !== 'object') return {}

  if (body.data && typeof body.data === 'object' && typeof body.data.status === 'string') {
    return body.data
  }

  if (typeof body.status === 'string') {
    return body
  }

  return {}
}

const pulseStat = (key) => {
  statPulse[key] = true
  if (pulseTimers[key]) clearTimeout(pulseTimers[key])
  pulseTimers[key] = setTimeout(() => {
    statPulse[key] = false
  }, 600)
}

const applyQueuePayload = (q) => {
  const prev = {
    processed: queue.processed,
    new: queue.new,
    existing: queue.existing,
    errors: queue.errors,
  }

  queue.status         = typeof q.status === 'string' ? q.status : 'idle'
  queue.progress       = Number(q.progress) || 0
  queue.processed      = Number(q.processed) || 0
  queue.total          = Number(q.total) || 0
  queue.new            = Number(q.new_count) || 0
  queue.existing       = Number(q.existing_count) || 0
  queue.errors         = Number(q.error_count) || 0
  queue.cursor         = Number(q.cursor) || 0
  queue.last_error     = q.last_error || null
  queue.started_at     = q.started_at || null
  queue.finished_at    = q.finished_at || null
  queue.updated_at     = q.updated_at || null
  queue.leads_per_sec  = Number(q.leads_per_sec) || 0
  queue.eta_seconds    = q.eta_seconds != null ? Number(q.eta_seconds) : null

  if (prev.processed !== queue.processed) pulseStat('processed')
  if (prev.new !== queue.new) pulseStat('new')
  if (prev.existing !== queue.existing) pulseStat('existing')
  if (prev.errors !== queue.errors) pulseStat('errors')
}

const formatDate = (iso) => {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleString()
  } catch {
    return iso
  }
}

const formatEta = (seconds) => {
  const s = Number(seconds) || 0
  if (s < 60) return `${s}s`
  if (s < 3600) return `${Math.floor(s / 60)}m`
  return `${Math.floor(s / 3600)}h ${Math.floor((s % 3600) / 60)}m`
}

const fetchStatus = async () => {
  try {
    const response = await axios.get('/api/bitrix24/queue-status', {
      params: { _: Date.now() },
      headers: { 'Cache-Control': 'no-cache' },
    })
    applyQueuePayload(parseQueuePayload(response))
    lastFetchedAt.value = Date.now()

    if (queue.status === 'running') {
      startPolling()
    } else {
      stopPolling()
    }
  } catch (e) {
    console.error('queue-status fetch failed', e)
    queue.last_error = apiErrorMessage(e, 'Could not load sync status')
  }
}

const startPolling = () => {
  stopPolling()
  isPolling.value = true
  pollInterval = setInterval(fetchStatus, POLL_MS)
  if (!tickInterval) {
    tickInterval = setInterval(() => {
      tickNow.value = Date.now()
    }, 1000)
  }
}

const stopPolling = () => {
  isPolling.value = false
  if (pollInterval) {
    clearInterval(pollInterval)
    pollInterval = null
  }
}

const startQueue = async () => {
  if (isRunning.value || loading.value) return
  loading.value = true
  actionMessage.value = ''
  queue.last_error = null

  try {
    const response = await axios.post('/api/leads/bitrix24/start-queue', {
      skip_existing: skipExisting.value,
    })

    applyQueuePayload(parseQueuePayload(response))
    if (queue.status !== 'running') {
      queue.status = 'running'
    }

    actionMessage.value = response?.data?.message || 'Sync queued — waiting for worker...'
    startPolling()
    await fetchStatus()
  } catch (e) {
    const msg = apiErrorMessage(e, 'Failed to start sync')
    queue.last_error = msg
    if (e?.response?.status === 409) {
      queue.last_error = msg + ' Click "Cancel sync" or "Reset state" first.'
    }
  } finally {
    loading.value = false
  }
}

const cancelQueue = async () => {
  if (!isRunning.value || canceling.value) return
  canceling.value = true
  actionMessage.value = ''
  try {
    await axios.post('/api/leads/bitrix24/cancel-queue')
    actionMessage.value = 'Cancellation requested.'
    await fetchStatus()
  } catch (e) {
    queue.last_error = apiErrorMessage(e, 'Failed to cancel sync')
  } finally {
    canceling.value = false
  }
}

const resetQueue = async () => {
  if (resetting.value) return
  resetting.value = true
  actionMessage.value = ''
  try {
    await axios.post('/api/leads/bitrix24/reset-queue')
    actionMessage.value = 'Sync state reset. You can start again.'
    await fetchStatus()
  } catch (e) {
    queue.last_error = apiErrorMessage(e, 'Failed to reset sync')
  } finally {
    resetting.value = false
  }
}

onMounted(async () => {
  await fetchStatus()
})

onUnmounted(() => {
  stopPolling()
  if (tickInterval) {
    clearInterval(tickInterval)
    tickInterval = null
  }
  Object.values(pulseTimers).forEach((t) => clearTimeout(t))
})
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

.b24-card__header h5 {
  font-weight: 700;
  letter-spacing: 0.01em;
}

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

.stat-tile:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
}

.stat-tile__label {
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #64748b;
}

.stat-tile__value {
  font-size: 22px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.stat-tile--pulse {
  border-color: #38bdf8;
  box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.25);
}

.b24-live {
  font-size: 11px;
  font-weight: 600;
}

.b24-live__dot {
  display: inline-block;
  width: 6px;
  height: 6px;
  margin-right: 4px;
  border-radius: 50%;
  background: #0ea5e9;
  animation: b24-pulse 1.2s ease-in-out infinite;
}

@keyframes b24-pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.4; transform: scale(0.85); }
}

:deep(.progress) {
  background-color: #e2e8f0;
  border-radius: 999px;
  overflow: hidden;
}
:deep(.progress-bar) {
  border-radius: 999px;
}
</style>
