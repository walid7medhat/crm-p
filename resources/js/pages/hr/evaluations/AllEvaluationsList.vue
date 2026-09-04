<template>
  <div class="aev-page">
    <div class="aev-shell">
      <div class="aev-top">
        <div class="aev-top__left">
          <h6 class="aev-title">All Evaluations</h6>
          <p class="aev-sub">Every employee evaluation across the company</p>
        </div>
        <label class="aev-search">
          <iconify-icon icon="lucide:search" />
          <input v-model="search" type="text" placeholder="Search by employee or evaluator…" />
        </label>
      </div>

      <div class="aev-filters">
        <button
          v-for="opt in statusOptions"
          :key="opt.value"
          type="button"
          class="aev-chip"
          :class="{ 'is-active': statusFilter === opt.value }"
          @click="statusFilter = opt.value"
        >
          {{ opt.label }}
        </button>
      </div>

      <div v-if="loading" class="aev-empty">Loading…</div>
      <div v-else-if="!filteredEvaluations.length" class="aev-empty">
        {{ search ? 'No matching evaluations.' : 'No evaluations yet.' }}
      </div>
      <div v-else class="aev-table-wrap">
        <table class="aev-table">
          <thead>
            <tr>
              <th>Employee</th>
              <th>Milestone</th>
              <th>Evaluator</th>
              <th>Status</th>
              <th>Submitted</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ev in filteredEvaluations" :key="ev.id">
              <td>
                <router-link :to="`/hr/employees/${ev.user_id}`" class="aev-employee">
                  <img :src="ev.user?.avatar || fallbackAvatar" :alt="ev.user?.name" />
                  <span>{{ ev.user?.display_name || ev.user?.name || '—' }}</span>
                </router-link>
              </td>
              <td>{{ ev.milestone_months }}-month</td>
              <td>{{ ev.evaluator?.display_name || ev.evaluator?.name || '—' }}</td>
              <td>
                <span class="aev-badge" :class="ev.status === 'submitted' ? 'is-on' : 'is-off'">
                  {{ ev.status === 'submitted' ? 'Submitted' : 'Pending' }}
                </span>
              </td>
              <td>{{ formatDate(ev.submitted_at) }}</td>
              <td class="aev-actions">
                <a v-if="ev.pdf_url" :href="ev.pdf_url" target="_blank" rel="noopener" class="aev-btn aev-btn--primary">
                  <iconify-icon icon="lucide:file-text" />
                  PDF
                </a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/plugins/axios'

const evaluations = ref([])
const loading = ref(false)
const search = ref('')
const statusFilter = ref('all')
const fallbackAvatar = '/assets/images/user.png'

const statusOptions = [
  { value: 'all', label: 'All' },
  { value: 'submitted', label: 'Submitted' },
  { value: 'pending', label: 'Pending' },
]

const filteredEvaluations = computed(() => {
  const q = search.value.trim().toLowerCase()
  return evaluations.value.filter((ev) => {
    if (statusFilter.value !== 'all' && ev.status !== statusFilter.value) return false
    if (!q) return true
    return [ev.user?.name, ev.user?.display_name, ev.evaluator?.name, ev.evaluator?.display_name]
      .some((v) => String(v || '').toLowerCase().includes(q))
  })
})

function formatDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '—'
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/evaluations')
    evaluations.value = data?.data || []
  } catch {
    evaluations.value = []
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.aev-page {
  --navy: #0b0736;
  --purple: #733e87;
  --border: #ece8f3;
  --muted: #6b7280;
  padding: 0;
  font-size: 13px;
  color: #111827;
}
.aev-shell {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 14px;
  box-shadow: 0 8px 24px rgba(11, 7, 54, 0.06);
}
.aev-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 12px;
  padding-bottom: 12px;
  border-bottom: 1px solid #f3f0f7;
}
.aev-title {
  margin: 0 !important;
  font-size: 15px !important;
  font-weight: 700 !important;
  color: var(--navy) !important;
  line-height: 1.3 !important;
}
.aev-sub {
  margin: 2px 0 0 !important;
  font-size: 12px !important;
  color: var(--muted) !important;
}
.aev-search {
  display: flex;
  align-items: center;
  gap: 8px;
  height: 36px;
  padding: 0 12px;
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  background: #faf8fc;
  color: #9ca3af;
  min-width: 260px;
}
.aev-search input {
  border: none !important;
  outline: none !important;
  box-shadow: none !important;
  background: transparent !important;
  width: 100%;
  font-size: 12px !important;
  color: #111827 !important;
  padding: 0 !important;
}
.aev-filters {
  display: flex;
  gap: 6px;
  margin-bottom: 12px;
}
.aev-chip {
  height: 30px;
  padding: 0 12px;
  border-radius: 999px;
  border: 1px solid #e5e7eb;
  background: #fff;
  color: #4b5563;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}
.aev-chip.is-active {
  background: var(--navy);
  border-color: var(--navy);
  color: #fff;
}
.aev-empty {
  text-align: center;
  color: #9ca3af;
  padding: 40px 12px;
  font-size: 12px !important;
}
.aev-table-wrap {
  overflow-x: auto;
}
.aev-table {
  width: 100%;
  border-collapse: collapse;
}
.aev-table th {
  text-align: left;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--muted);
  padding: 8px 10px;
  border-bottom: 1px solid var(--border);
  white-space: nowrap;
}
.aev-table td {
  padding: 10px;
  border-bottom: 1px solid #f5f3f8;
  font-size: 12px;
  vertical-align: middle;
  white-space: nowrap;
}
.aev-employee {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  text-decoration: none;
  color: var(--navy);
  font-weight: 600;
}
.aev-employee img {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
}
.aev-employee:hover {
  text-decoration: underline;
}
.aev-badge {
  font-size: 10px !important;
  font-weight: 700;
  padding: 3px 9px;
  border-radius: 999px;
}
.aev-badge.is-on {
  background: #eef2ff;
  color: #3730a3;
}
.aev-badge.is-off {
  background: #fef3c7;
  color: #92400e;
}
.aev-actions {
  text-align: right;
}
.aev-btn {
  height: 28px;
  padding: 0 10px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  color: #111827;
  font-size: 11px !important;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  text-decoration: none;
}
.aev-btn--primary {
  background: var(--navy);
  border-color: var(--navy);
  color: #fff;
}
</style>
