<template>
  <div class="container py-4">

    <div class="card shadow-sm">
      <div class="card-header bg-dark text-white">
        <h5 class="mb-0">Bitrix24 Queue Monitor</h5>
      </div>

      <div class="card-body">

        <!-- Start Button -->
        <button class="btn btn-primary" :disabled="loading" @click="startQueue">
          <span v-if="loading">Starting...</span>
          <span v-else>Start Queue Sync</span>
        </button>

        <hr />

        <!-- Status -->
        <div>
          <p>Status: <b>{{ queue.status }}</b></p>
          <p>Progress: <b>{{ queue.progress }}%</b></p>
          <p>Processed: <b>{{ queue.processed }}</b></p>
          <p>Total: <b>{{ queue.total }}</b></p>
          <p>New: <b class="text-success">{{ queue.new }}</b></p>
          <p>Existing: <b class="text-secondary">{{ queue.existing }}</b></p>
          <p>Errors: <b class="text-danger">{{ queue.errors }}</b></p>
        </div>

        <!-- Progress Bar -->
        <div class="progress mt-3" style="height: 10px;">
          <div class="progress-bar bg-success" :style="{ width: queue.progress + '%' }"></div>
        </div>

      </div>
    </div>

  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import axios from 'axios'

const loading = ref(false)

const queue = reactive({
  jobId: null,
  status: 'idle',
  progress: 0,
  processed: 0,
  total: 0,
  new: 0,
  existing: 0,
  errors: 0
})

let interval = null

// ▶ Start Queue
const startQueue = async () => {
  loading.value = true

  try {
    const { data } = await axios.post('/api/leads/bitrix24/start-queue')

    queue.status = 'running'
    queue.jobId = data?.data?.job_id || null

    startPolling()

  } catch (e) {
    queue.status = 'error'
  } finally {
    loading.value = false
  }
}

// ▶ Poll status
const startPolling = () => {
  if (interval) clearInterval(interval)

  interval = setInterval(async () => {
    try {
      const { data } = await axios.get('/api/bitrix24/queue-status')

      const q = data.data

      queue.status = q.status
      queue.progress = q.progress
      queue.processed = q.processed
      queue.total = q.total
      queue.new = q.new_count
      queue.existing = q.existing_count
      queue.errors = q.error_count

      if (q.status === 'done' || q.status === 'failed') {
        clearInterval(interval)
      }

    } catch (e) {
      console.log(e)
    }

  }, 2000)
}
</script>

<style scoped>
.card {
  border-radius: 12px;
}
</style>