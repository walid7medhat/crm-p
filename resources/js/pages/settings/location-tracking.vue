<template>
  <div class="dashboard-main-body loc-page">
    <div class="loc-shell">
      <div class="loc-top">
        <div>
          <div class="loc-title">Employee location tracking</div>
          <p class="loc-hint">
            Super Admin only · Tied to CRM online presence (not re-login) ·
            Foreground only — no background GPS ·
            Live = captured within {{ freshMinutes }} min while online
          </p>
        </div>
        <div class="loc-actions">
          <button type="button" class="loc-btn" :disabled="loading" @click="fetchOnline">
            <iconify-icon icon="lucide:refresh-cw" width="14" height="14" />
            Refresh
          </button>
        </div>
      </div>

      <div v-if="error" class="loc-error">
        <span>{{ error }}</span>
        <button type="button" class="loc-link" @click="fetchOnline">Retry</button>
      </div>

      <div v-if="loading" class="loc-loading">Loading online employees…</div>

      <template v-else-if="!error">
        <div class="loc-metrics">
          <div class="loc-metric">
            <span class="loc-metric__n">{{ users.length }}</span>
            <span class="loc-metric__l">Online now</span>
          </div>
          <div class="loc-metric loc-metric--live">
            <span class="loc-metric__n">{{ liveCount }}</span>
            <span class="loc-metric__l">Live location</span>
          </div>
          <div class="loc-metric">
            <span class="loc-metric__n">{{ lastKnownCount }}</span>
            <span class="loc-metric__l">Last known only</span>
          </div>
        </div>

        <div class="loc-label">Currently online</div>
        <div v-if="!users.length" class="loc-empty">No employees online in the last 15 minutes.</div>
        <div v-else class="loc-table-wrap">
          <table class="loc-table">
            <thead>
              <tr>
                <th>Employee</th>
                <th>Status</th>
                <th>Location</th>
                <th>Captured</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in users"
                :key="row.id"
                :class="{ 'is-selected': selectedUserId === row.id }"
              >
                <td>
                  <div class="loc-name">{{ row.name }}</div>
                  <div class="loc-dim">{{ row.email }}</div>
                </td>
                <td>
                  <span class="loc-tag" :class="statusClass(row)">{{ statusLabel(row) }}</span>
                </td>
                <td>
                  <template v-if="row.has_device_location">
                    <div class="loc-mono">{{ formatCoords(row) }}</div>
                    <div v-if="row.address" class="loc-dim loc-clip" :title="row.address">{{ row.address }}</div>
                  </template>
                  <span v-else class="loc-dim">No device fix</span>
                </td>
                <td class="loc-mono">{{ formatWhen(row.captured_at) }}</td>
                <td>
                  <button type="button" class="loc-btn loc-btn--secondary" @click="openHistory(row)">
                    History
                  </button>
                  <a
                    v-if="row.has_device_location"
                    class="loc-btn loc-btn--secondary"
                    :href="mapsUrl(row)"
                    target="_blank"
                    rel="noopener noreferrer"
                  >Map</a>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="selectedUserId" class="loc-history">
          <div class="loc-label">
            History · {{ selectedName }}
            <span class="loc-label__meta">chronological · append-only</span>
          </div>

          <div class="loc-filters">
            <label class="loc-filter">
              <span>From</span>
              <input v-model="from" type="datetime-local" class="loc-input" />
            </label>
            <label class="loc-filter">
              <span>To</span>
              <input v-model="to" type="datetime-local" class="loc-input" />
            </label>
            <button type="button" class="loc-btn" :disabled="historyLoading" @click="fetchHistory">
              Apply
            </button>
            <button type="button" class="loc-btn loc-btn--secondary" @click="clearHistory">
              Close
            </button>
          </div>

          <div v-if="historyError" class="loc-error">{{ historyError }}</div>
          <div v-if="historyLoading" class="loc-loading">Loading history…</div>
          <div v-else-if="!historyRows.length" class="loc-empty">No history rows for this range.</div>
          <div v-else class="loc-table-wrap">
            <table class="loc-table">
              <thead>
                <tr>
                  <th>Captured</th>
                  <th>Coords</th>
                  <th>Accuracy</th>
                  <th>Address</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="point in historyRows" :key="point.id">
                  <td class="loc-mono">{{ formatWhen(point.captured_at) }}</td>
                  <td class="loc-mono">{{ formatCoords(point) }}</td>
                  <td>{{ point.accuracy != null ? `${point.accuracy} m` : '—' }}</td>
                  <td class="loc-clip" :title="point.address || ''">{{ point.address || '—' }}</td>
                  <td>
                    <a
                      class="loc-link-quiet"
                      :href="mapsUrl(point)"
                      target="_blank"
                      rel="noopener noreferrer"
                    >Map</a>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-if="historyTotal > historyRows.length" class="loc-hint loc-hint--block">
            Showing {{ historyRows.length }} of {{ historyTotal }}. Narrow the date range for older points.
          </p>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/plugins/axios'

const loading = ref(false)
const error = ref('')
const users = ref([])
const freshMinutes = ref(10)

const selectedUserId = ref(null)
const selectedName = ref('')
const from = ref('')
const to = ref('')
const historyLoading = ref(false)
const historyError = ref('')
const historyRows = ref([])
const historyTotal = ref(0)

const liveCount = computed(() => users.value.filter((u) => u.location_live).length)
const lastKnownCount = computed(() =>
  users.value.filter((u) => u.has_device_location && !u.location_live).length
)

function statusLabel(row) {
  if (row.location_live) return 'Live'
  if (row.has_device_location) return 'Last known'
  return 'No GPS'
}

function statusClass(row) {
  if (row.location_live) return 'loc-tag--live'
  if (row.has_device_location) return 'loc-tag--known'
  return 'loc-tag--none'
}

function formatCoords(row) {
  if (row.latitude == null || row.longitude == null) return '—'
  return `${Number(row.latitude).toFixed(5)}, ${Number(row.longitude).toFixed(5)}`
}

function formatWhen(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return date.toLocaleString()
}

function mapsUrl(row) {
  return `https://www.google.com/maps?q=${encodeURIComponent(`${row.latitude},${row.longitude}`)}`
}

async function fetchOnline() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/admin/location/online')
    const payload = data?.data || {}
    users.value = payload.users || []
    freshMinutes.value = payload.fresh_minutes || 10
  } catch (err) {
    error.value = err?.response?.status === 403
      ? 'Forbidden — Super Admin only'
      : (err?.response?.data?.message || 'Failed to load online locations')
    users.value = []
  } finally {
    loading.value = false
  }
}

async function openHistory(row) {
  selectedUserId.value = row.id
  selectedName.value = row.name
  from.value = ''
  to.value = ''
  await fetchHistory()
}

function clearHistory() {
  selectedUserId.value = null
  selectedName.value = ''
  historyRows.value = []
  historyTotal.value = 0
  historyError.value = ''
}

async function fetchHistory() {
  if (!selectedUserId.value) return
  historyLoading.value = true
  historyError.value = ''
  try {
    const params = { per_page: 100 }
    if (from.value) params.from = new Date(from.value).toISOString()
    if (to.value) params.to = new Date(to.value).toISOString()
    const { data } = await api.get(`/admin/location/users/${selectedUserId.value}`, { params })
    const payload = data?.data || {}
    historyRows.value = payload.history || []
    historyTotal.value = payload.pagination?.total || historyRows.value.length
  } catch (err) {
    historyError.value = err?.response?.data?.message || 'Failed to load history'
    historyRows.value = []
  } finally {
    historyLoading.value = false
  }
}

onMounted(fetchOnline)
</script>

<style scoped>
.loc-page.loc-page {
  background: #fff !important;
  min-height: 100vh;
  padding: 10px 12px;
  font-size: 12px;
  color: #2a3142;
}
.loc-shell {
  border: 1px solid #e3e7ef;
  border-radius: 10px;
  padding: 12px;
  background: #fff;
}
.loc-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 10px;
}
.loc-actions { display: flex; flex-wrap: wrap; gap: 6px; }
.loc-title {
  font-size: 13px;
  font-weight: 650;
  color: #151a2b;
  margin-bottom: 2px;
}
.loc-hint {
  margin: 0;
  font-size: 11px;
  color: #8b93a7;
  line-height: 1.35;
}
.loc-hint--block {
  margin-top: 10px;
  padding: 8px 10px;
  border: 1px dashed #e3e7ef;
  border-radius: 8px;
  background: #fafbfd;
}
.loc-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  height: 28px;
  padding: 0 10px;
  border-radius: 6px;
  border: 1px solid #e3e7ef;
  background: #fff;
  color: #2a3142;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
}
.loc-btn--secondary { background: #f5f7fb; border-color: #d8dee9; }
.loc-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.loc-error {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #fdecec;
  color: #b3261e;
  border: 1px solid #f5c2c0;
  border-radius: 8px;
  padding: 8px 10px;
  margin-bottom: 10px;
}
.loc-link {
  border: none;
  background: transparent;
  color: #b3261e;
  text-decoration: underline;
  cursor: pointer;
  margin-left: auto;
  font-size: 11px;
}
.loc-link-quiet {
  color: #1d4ed8;
  font-size: 11px;
  text-decoration: none;
}
.loc-loading, .loc-empty {
  padding: 12px 4px;
  color: #8b93a7;
}
.loc-metrics {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 14px;
}
.loc-metric {
  border: 1px solid #e8ecf3;
  border-radius: 8px;
  padding: 6px 10px;
  min-width: 72px;
  background: #fafbfd;
}
.loc-metric--live { border-color: #b7e4c7; background: #f3fbf6; }
.loc-metric__n {
  display: block;
  font-size: 14px;
  font-weight: 700;
  color: #151a2b;
}
.loc-metric__l {
  display: block;
  font-size: 10px;
  color: #8b93a7;
  margin-top: 1px;
}
.loc-label {
  display: flex;
  align-items: baseline;
  gap: 8px;
  font-size: 11px;
  font-weight: 700;
  color: #5b6478;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  margin: 14px 0 6px;
}
.loc-label__meta {
  font-weight: 500;
  text-transform: none;
  color: #8b93a7;
}
.loc-table-wrap { overflow-x: auto; }
.loc-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 640px;
}
.loc-table th {
  text-align: left;
  font-size: 10px;
  font-weight: 600;
  color: #8b93a7;
  text-transform: uppercase;
  padding: 5px 6px;
  border-bottom: 1px solid #e8ecf3;
}
.loc-table td {
  padding: 7px 6px;
  border-bottom: 1px solid #f1f3f7;
  vertical-align: top;
}
.loc-table tr.is-selected { background: #f5f8ff; }
.loc-name { font-weight: 600; color: #151a2b; }
.loc-dim { color: #8b93a7; font-size: 10px; }
.loc-mono {
  font-variant-numeric: tabular-nums;
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 10px;
}
.loc-clip {
  max-width: 220px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.loc-tag {
  display: inline-block;
  padding: 1px 6px;
  border-radius: 4px;
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
}
.loc-tag--live { background: #e6f7ee; color: #0f9d58; }
.loc-tag--known { background: #fff4e5; color: #b45309; }
.loc-tag--none { background: #f1f3f7; color: #8b93a7; }
.loc-history { margin-top: 8px; }
.loc-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: end;
  margin-bottom: 10px;
}
.loc-filter {
  display: flex;
  flex-direction: column;
  gap: 3px;
  font-size: 10px;
  color: #8b93a7;
}
.loc-input {
  height: 28px;
  border: 1px solid #e3e7ef;
  border-radius: 6px;
  padding: 0 8px;
  font-size: 11px;
  color: #2a3142;
}
</style>
