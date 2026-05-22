<template>
  <div class="container py-4 b24-page">
    <div class="card shadow-sm b24-card">
      <div class="card-header b24-card__header text-white d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <i class="ri-cloud-line fs-4"></i>
          <h5 class="mb-0">Bitrix24 Queue Monitor</h5>
        </div>
        <span class="badge" :class="badgeClass">{{ statusLabel }}</span>
      </div>

      <div class="card-body b24-card__body">

        <!-- Controls -->
        <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
          <button
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
            class="btn btn-outline-danger"
            :disabled="canceling"
            @click="cancelQueue"
          >
            {{ canceling ? 'Cancelling...' : 'Cancel sync' }}
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

        <!-- Progress bar -->
        <div class="progress mb-2" style="height: 10px;">
          <div
            class="progress-bar"
            :class="progressBarClass"
            :style="{ width: queue.progress + '%' }"
            role="progressbar"
            :aria-valuenow="queue.progress"
          ></div>
        </div>
        <small class="text-muted">
          {{ queue.processed }} / {{ queue.total || '—' }} leads processed ({{ queue.progress }}%)
        </small>

        <!-- Stats -->
        <div class="row g-2 mt-3">
          <div class="col-6 col-md-3">
            <div class="stat-tile">
              <div class="stat-tile__label">New</div>
              <div class="stat-tile__value text-success">{{ queue.new }}</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="stat-tile">
              <div class="stat-tile__label">Already existed</div>
              <div class="stat-tile__value text-secondary">{{ queue.existing }}</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="stat-tile">
              <div class="stat-tile__label">Errors</div>
              <div class="stat-tile__value text-danger">{{ queue.errors }}</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="stat-tile">
              <div class="stat-tile__label">Cursor</div>
              <div class="stat-tile__value">{{ queue.cursor }}</div>
            </div>
          </div>
        </div>

        <!-- Timing -->
        <div v-if="queue.started_at" class="text-muted small mt-3">
          Started: {{ formatDate(queue.started_at) }}
          <span v-if="queue.finished_at"> · Finished: {{ formatDate(queue.finished_at) }}</span>
        </div>

        <!-- Error -->
        <div v-if="queue.last_error" class="alert alert-danger mt-3 mb-0 py-2 px-3" style="font-size: 13px;">
          <i class="ri-error-warning-line me-1"></i>
          {{ queue.last_error }}
        </div>

        <!-- How it works -->
        <details class="mt-3">
          <summary class="text-muted small" style="cursor: pointer;">How does this work?</summary>
          <p class="small text-muted mt-2 mb-0">
            The job processes Bitrix24 in chunks of ~50 leads (one Bitrix24 page per run).
            After each chunk it saves progress, then dispatches the next chunk as a fresh
            queue job — so the worker timeout never expires mid-sync, and progress survives
            a worker restart. The UI here polls every 2 seconds and stops when the job
            reports <code>done</code>, <code>failed</code>, or <code>cancelled</code>.
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
})

const isRunning = computed(() => queue.status === 'running')

// Cancelled / failed / paused → "Resume sync" picks up from saved cursor.
// done → "Restart sync (fresh)" resets to 0. idle → first run from 0.
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
  return labels[queue.status] || queue.status
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

const formatDate = (iso) => {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleString()
  } catch {
    return iso
  }
}

const fetchStatus = async () => {
  try {
    const { data } = await axios.get('/api/bitrix24/queue-status')
    const q = data?.data ?? data
    queue.status      = q.status || 'idle'
    queue.progress    = q.progress || 0
    queue.processed   = q.processed || 0
    queue.total       = q.total || 0
    queue.new         = q.new_count || 0
    queue.existing    = q.existing_count || 0
    queue.errors      = q.error_count || 0
    queue.cursor      = q.cursor || 0
    queue.last_error  = q.last_error || null
    queue.started_at  = q.started_at || null
    queue.finished_at = q.finished_at || null

    // Stop polling once the job reaches a terminal state.
    if (['done', 'failed', 'cancelled', 'idle'].includes(queue.status)) {
      stopPolling()
    }
  } catch (e) {
    console.error('queue-status fetch failed', e)
  }
}

const startPolling = () => {
  stopPolling()
  pollInterval = setInterval(fetchStatus, 2000)
}

const stopPolling = () => {
  if (pollInterval) clearInterval(pollInterval)
  pollInterval = null
}

const startQueue = async () => {
  if (isRunning.value) return
  loading.value = true
  try {
    await axios.post('/api/leads/bitrix24/start-queue', {
      skip_existing: skipExisting.value,
    })
    // Optimistic UI; the next poll will reflect the real state.
    queue.status = 'running'
    queue.progress = 0
    queue.processed = 0
    queue.new = 0
    queue.existing = 0
    queue.errors = 0
    queue.last_error = null
    startPolling()
    await fetchStatus()
  } catch (e) {
    queue.last_error = e.response?.data?.message || e.message || 'Failed to start sync'
  } finally {
    loading.value = false
  }
}

const cancelQueue = async () => {
  if (!isRunning.value) return
  canceling.value = true
  try {
    await axios.post('/api/leads/bitrix24/cancel-queue')
    await fetchStatus()
  } catch (e) {
    queue.last_error = e.response?.data?.message || e.message || 'Failed to cancel sync'
  } finally {
    canceling.value = false
  }
}

onMounted(async () => {
  await fetchStatus()
  if (isRunning.value) startPolling()
})

onUnmounted(stopPolling)
</script>

<style scoped>
.b24-page {
  /* soft tinted backdrop so the card "lifts" off the page */
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

/* slim progress bar pops on the lighter card body */
:deep(.progress) {
  background-color: #e2e8f0;
  border-radius: 999px;
  overflow: hidden;
}
:deep(.progress-bar) {
  border-radius: 999px;
}
</style>
