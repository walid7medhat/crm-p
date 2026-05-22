<template>
  <div class="container py-4">
    <div class="card shadow-sm">
      <div class="card-header bg-dark text-white d-flex align-items-center justify-content-between">
        <h5 class="mb-0">Bitrix24 Queue Monitor</h5>
        <span class="badge" :class="badgeClass">{{ statusLabel }}</span>
      </div>

      <div class="card-body">

        <!-- Controls -->
        <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
          <button
            class="btn btn-primary"
            :disabled="loading || isRunning"
            @click="startQueue"
          >
            <span v-if="loading">Starting...</span>
            <span v-else-if="isRunning">Sync in progress</span>
            <span v-else-if="queue.status === 'done'">Restart sync</span>
            <span v-else-if="queue.status === 'failed' || queue.status === 'cancelled'">Retry sync</span>
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
.card {
  border-radius: 12px;
}

.stat-tile {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 10px 12px;
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
</style>
