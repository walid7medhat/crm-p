<template>
  <div class="dashboard-main-body fam-page">
    <div class="fam-shell">
      <div class="fam-top">
        <div>
          <div class="fam-title">Foreign Access Monitor</div>
          <p class="fam-hint">
            Super Admin only · IP geolocation alerts for CRM access outside the UAE ·
            Monitoring only — sessions are never blocked
          </p>
        </div>
        <div class="fam-actions">
          <button type="button" class="fam-btn" :disabled="loading" @click="fetchEvents">
            <iconify-icon icon="lucide:refresh-cw" width="14" height="14" />
            Refresh
          </button>
        </div>
      </div>

      <div v-if="error" class="fam-error">
        <span>{{ error }}</span>
        <button type="button" class="fam-link" @click="fetchEvents">Retry</button>
      </div>

      <div class="fam-metrics">
        <div class="fam-metric fam-metric--alert">
          <span class="fam-metric__n">{{ openCount }}</span>
          <span class="fam-metric__l">Open alerts</span>
        </div>
        <div class="fam-metric">
          <span class="fam-metric__n">{{ pagination.total }}</span>
          <span class="fam-metric__l">Matching rows</span>
        </div>
      </div>

      <div class="fam-filters">
        <label class="fam-filter">
          <span>Employee ID</span>
          <input v-model="filters.user_id" type="number" min="1" class="fam-input" placeholder="User ID" />
        </label>
        <label class="fam-filter">
          <span>Country</span>
          <input v-model="filters.country" type="text" class="fam-input" placeholder="e.g. Egypt" />
        </label>
        <label class="fam-filter">
          <span>From</span>
          <input v-model="filters.from" type="datetime-local" class="fam-input" />
        </label>
        <label class="fam-filter">
          <span>To</span>
          <input v-model="filters.to" type="datetime-local" class="fam-input" />
        </label>
        <label class="fam-filter">
          <span>Status</span>
          <select v-model="filters.status" class="fam-input">
            <option value="">All</option>
            <option value="new">New</option>
            <option value="reviewed">Reviewed</option>
          </select>
        </label>
        <button type="button" class="fam-btn" :disabled="loading" @click="applyFilters">Apply</button>
        <button type="button" class="fam-btn fam-btn--secondary" @click="resetFilters">Reset</button>
      </div>

      <div v-if="loading" class="fam-loading">Loading foreign access events…</div>
      <div v-else-if="!error && !events.length" class="fam-empty">No foreign access events for these filters.</div>
      <div v-else-if="!error" class="fam-table-wrap">
        <table class="fam-table">
          <thead>
            <tr>
              <th></th>
              <th>Employee</th>
              <th>Country</th>
              <th>City</th>
              <th>IP</th>
              <th>Browser / Device</th>
              <th>Accessed</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in events"
              :id="`fam-event-${row.id}`"
              :key="row.id"
              :class="{ 'is-highlight': highlightId === row.id, 'is-new': row.status === 'new' }"
            >
              <td>
                <span class="fam-alert-dot" title="Foreign access" />
              </td>
              <td>
                <div class="fam-name">{{ row.user_name || `User #${row.user_id}` }}</div>
                <div class="fam-dim">#{{ row.user_id }} · {{ row.user_email || '—' }}</div>
              </td>
              <td>
                <div class="fam-country">
                  <span class="fam-flag" aria-hidden="true">{{ countryFlag(row.country_code) }}</span>
                  <span>{{ row.country || row.country_code || '—' }}</span>
                </div>
                <div class="fam-dim">{{ reasonLabel(row.detection_reason) }}</div>
              </td>
              <td>{{ row.city || '—' }}</td>
              <td class="fam-mono">{{ row.ip || '—' }}</td>
              <td>
                <div>{{ row.device || '—' }}</div>
                <div v-if="row.session_key" class="fam-dim fam-clip" :title="row.session_key">
                  Session {{ shortKey(row.session_key) }}
                </div>
              </td>
              <td class="fam-mono">{{ formatWhen(row.occurred_at) }}</td>
              <td>
                <span class="fam-tag" :class="row.status === 'reviewed' ? 'fam-tag--ok' : 'fam-tag--new'">
                  {{ row.status === 'reviewed' ? 'Reviewed' : 'New' }}
                </span>
              </td>
              <td>
                <button
                  v-if="row.status !== 'reviewed'"
                  type="button"
                  class="fam-btn fam-btn--secondary"
                  :disabled="reviewingId === row.id"
                  @click="markReviewed(row)"
                >
                  Mark reviewed
                </button>
                <span v-else class="fam-dim">Done</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="pagination.last_page > 1" class="fam-pager">
        <button
          type="button"
          class="fam-btn fam-btn--secondary"
          :disabled="loading || pagination.current_page <= 1"
          @click="goPage(pagination.current_page - 1)"
        >
          Prev
        </button>
        <span class="fam-dim">Page {{ pagination.current_page }} / {{ pagination.last_page }}</span>
        <button
          type="button"
          class="fam-btn fam-btn--secondary"
          :disabled="loading || pagination.current_page >= pagination.last_page"
          @click="goPage(pagination.current_page + 1)"
        >
          Next
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/plugins/axios'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const error = ref('')
const events = ref([])
const openCount = ref(0)
const reviewingId = ref(null)
const highlightId = ref(null)
const page = ref(1)

const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 50,
  total: 0,
})

const filters = ref({
  user_id: '',
  country: '',
  from: '',
  to: '',
  status: '',
})

function countryFlag(code) {
  if (!code || String(code).length !== 2) return '🌐'
  const cc = String(code).toUpperCase()
  return String.fromCodePoint(...[...cc].map((c) => 127397 + c.charCodeAt(0)))
}

function reasonLabel(reason) {
  if (reason === 'foreign_login') return 'Foreign login'
  if (reason === 'country_change') return 'Country change'
  if (reason === 'foreign_ip') return 'Foreign IP'
  return reason || '—'
}

function formatWhen(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return date.toLocaleString()
}

function shortKey(key) {
  if (!key) return ''
  return key.length > 10 ? `${key.slice(0, 8)}…` : key
}

function buildParams() {
  const params = { per_page: 50, page: page.value }
  if (filters.value.user_id) params.user_id = Number(filters.value.user_id)
  if (filters.value.country) params.country = filters.value.country
  if (filters.value.status) params.status = filters.value.status
  if (filters.value.from) params.from = new Date(filters.value.from).toISOString()
  if (filters.value.to) params.to = new Date(filters.value.to).toISOString()
  return params
}

async function fetchEvents() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/admin/foreign-access', { params: buildParams() })
    const payload = data?.data || {}
    events.value = payload.events || []
    openCount.value = payload.open_count ?? 0
    pagination.value = payload.pagination || pagination.value
  } catch (err) {
    error.value = err?.response?.status === 403
      ? 'Forbidden — Super Admin only'
      : (err?.response?.data?.message || 'Failed to load foreign access events')
    events.value = []
  } finally {
    loading.value = false
    maybeHighlight()
  }
}

function applyFilters() {
  page.value = 1
  fetchEvents()
}

function resetFilters() {
  filters.value = { user_id: '', country: '', from: '', to: '', status: '' }
  page.value = 1
  fetchEvents()
}

function goPage(next) {
  page.value = next
  fetchEvents()
}

async function markReviewed(row) {
  reviewingId.value = row.id
  try {
    const { data } = await api.post(`/admin/foreign-access/${row.id}/review`)
    const updated = data?.data
    if (updated) {
      events.value = events.value.map((e) => (e.id === row.id ? { ...e, ...updated } : e))
      if (openCount.value > 0) openCount.value -= 1
    } else {
      await fetchEvents()
    }
  } catch (err) {
    error.value = err?.response?.data?.message || 'Failed to mark as reviewed'
  } finally {
    reviewingId.value = null
  }
}

function maybeHighlight() {
  const raw = route.query.event
  const id = raw ? Number(raw) : null
  if (!id) return
  highlightId.value = id
  requestAnimationFrame(() => {
    document.getElementById(`fam-event-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  })
  // Clear query after focus so refresh stays clean.
  if (route.query.event) {
    const nextQuery = { ...route.query }
    delete nextQuery.event
    router.replace({ query: nextQuery })
  }
}

watch(() => route.query.event, () => maybeHighlight())

onMounted(fetchEvents)
</script>

<style scoped>
.fam-page.fam-page {
  background: #fff !important;
  min-height: 100vh;
  padding: 10px 12px;
  font-size: 12px;
  color: #2a3142;
}
.fam-shell {
  border: 1px solid #e3e7ef;
  border-radius: 10px;
  padding: 12px;
  background: #fff;
}
.fam-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 10px;
}
.fam-actions { display: flex; flex-wrap: wrap; gap: 6px; }
.fam-title {
  font-size: 13px;
  font-weight: 650;
  color: #151a2b;
  margin-bottom: 2px;
}
.fam-hint {
  margin: 0;
  font-size: 11px;
  color: #8b93a7;
  line-height: 1.35;
}
.fam-btn {
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
.fam-btn--secondary { background: #f5f7fb; border-color: #d8dee9; }
.fam-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.fam-error {
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
.fam-link {
  border: none;
  background: transparent;
  color: #b3261e;
  text-decoration: underline;
  cursor: pointer;
  margin-left: auto;
  font-size: 11px;
}
.fam-loading, .fam-empty {
  padding: 12px 4px;
  color: #8b93a7;
}
.fam-metrics {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 12px;
}
.fam-metric {
  border: 1px solid #e8ecf3;
  border-radius: 8px;
  padding: 6px 10px;
  min-width: 72px;
  background: #fafbfd;
}
.fam-metric--alert { border-color: #f5c2c0; background: #fff5f5; }
.fam-metric__n {
  display: block;
  font-size: 14px;
  font-weight: 700;
  color: #151a2b;
}
.fam-metric__l {
  display: block;
  font-size: 10px;
  color: #8b93a7;
  margin-top: 1px;
}
.fam-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: end;
  margin-bottom: 12px;
}
.fam-filter {
  display: flex;
  flex-direction: column;
  gap: 3px;
  font-size: 10px;
  color: #8b93a7;
}
.fam-input {
  height: 28px;
  border: 1px solid #e3e7ef;
  border-radius: 6px;
  padding: 0 8px;
  font-size: 11px;
  color: #2a3142;
  min-width: 120px;
  background: #fff;
}
.fam-table-wrap { overflow-x: auto; }
.fam-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 860px;
}
.fam-table th {
  text-align: left;
  font-size: 10px;
  font-weight: 600;
  color: #8b93a7;
  text-transform: uppercase;
  padding: 5px 6px;
  border-bottom: 1px solid #e8ecf3;
}
.fam-table td {
  padding: 7px 6px;
  border-bottom: 1px solid #f1f3f7;
  vertical-align: top;
}
.fam-table tr.is-new { background: #fff8f8; }
.fam-table tr.is-highlight {
  outline: 2px solid #ef4444;
  outline-offset: -2px;
  background: #fff1f1;
}
.fam-alert-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #ef4444;
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15);
  margin-top: 4px;
}
.fam-name { font-weight: 600; color: #151a2b; }
.fam-dim { color: #8b93a7; font-size: 10px; }
.fam-mono {
  font-variant-numeric: tabular-nums;
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 10px;
}
.fam-clip {
  max-width: 140px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.fam-country {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.fam-flag { font-size: 14px; line-height: 1; }
.fam-tag {
  display: inline-block;
  padding: 1px 6px;
  border-radius: 4px;
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
}
.fam-tag--new { background: #fdecec; color: #b3261e; }
.fam-tag--ok { background: #e6f7ee; color: #0f9d58; }
.fam-pager {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 12px;
}
</style>
