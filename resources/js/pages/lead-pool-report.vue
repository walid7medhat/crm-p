<template>
  <div class="dashboard-main-body lead-pool-report-page">
    <Breadcrumb title="Lead Pool Assignments" :breadcrumbs="[{ name: 'Lead Pool Assignments' }]" />

    <div class="lpr-shell">
      <div class="lpr-toolbar">
        <div class="lpr-filters">
          <input type="date" v-model="dateFrom" class="lpr-input" />
          <span class="lpr-sep">to</span>
          <input type="date" v-model="dateTo" class="lpr-input" />
          <button type="button" class="lpr-btn lpr-btn--ghost" @click="fetchReport" :disabled="loading">
            <iconify-icon icon="lucide:filter" width="16" height="16" />
            Apply
          </button>
          <button type="button" class="lpr-btn lpr-btn--ghost" @click="resetToThisMonth" :disabled="loading">
            This month
          </button>
        </div>
        <p class="lpr-hint">Leads users took from the Lead Pool (assigned to themselves) in this date range, and the stage each lead is in now.</p>
      </div>

      <div v-if="error" class="lpr-error">
        <iconify-icon icon="lucide:alert-circle" width="16" height="16" />
        <span>{{ error }}</span>
        <button type="button" class="lpr-link" @click="fetchReport">Retry</button>
      </div>

      <div v-if="loading" class="lpr-loading">Loading report...</div>

      <template v-else-if="!error">
        <div class="lpr-kpis">
          <div class="lpr-kpi">
            <span class="lpr-kpi-label">Leads taken</span>
            <strong class="lpr-kpi-value">{{ totals.total || 0 }}</strong>
          </div>
          <div class="lpr-kpi">
            <span class="lpr-kpi-label">Users</span>
            <strong class="lpr-kpi-value">{{ users.length }}</strong>
          </div>
          <div v-for="stage in stages" :key="`kpi_${stage.id}`" class="lpr-kpi lpr-kpi--stage">
            <span class="lpr-kpi-label">
              <span class="lpr-dot" :style="{ background: stage.color }" />
              {{ stage.name }}
            </span>
            <strong class="lpr-kpi-value">{{ totals.stages?.[stage.id] || 0 }}</strong>
          </div>
        </div>

        <div v-if="!users.length" class="lpr-empty">No Lead Pool assignments in this date range.</div>

        <div v-else class="lpr-table-wrap">
          <table class="lpr-table">
            <thead>
              <tr>
                <th class="lpr-th-user">User</th>
                <th class="lpr-num">Leads</th>
                <th v-for="stage in stages" :key="`th_${stage.id}`" class="lpr-num">
                  <span class="lpr-dot" :style="{ background: stage.color }" />
                  {{ stage.name }}
                </th>
                <th class="lpr-th-toggle"></th>
              </tr>
            </thead>
            <tbody>
              <template v-for="row in users" :key="row.user_id">
                <tr class="lpr-user-row" @click="toggleUser(row.user_id)">
                  <td>
                    <div class="lpr-user">
                      <img :src="row.avatar || DEFAULT_AVATAR" alt="" class="lpr-avatar" />
                      <span class="lpr-user-name">{{ row.name }}</span>
                      <span v-if="!row.active" class="lpr-badge">Inactive</span>
                    </div>
                  </td>
                  <td class="lpr-num"><strong>{{ row.total }}</strong></td>
                  <td v-for="stage in stages" :key="`td_${row.user_id}_${stage.id}`" class="lpr-num">
                    <span v-if="row.stages[stage.id]" class="lpr-count" :style="{ borderColor: stage.color }">{{ row.stages[stage.id] }}</span>
                    <span v-else class="lpr-zero">–</span>
                  </td>
                  <td class="lpr-th-toggle">
                    <iconify-icon :icon="expanded[row.user_id] ? 'lucide:chevron-up' : 'lucide:chevron-down'" width="16" height="16" />
                  </td>
                </tr>
                <tr v-if="expanded[row.user_id]" class="lpr-detail-row">
                  <td :colspan="stages.length + 3">
                    <table class="lpr-leads">
                      <thead>
                        <tr>
                          <th>Lead</th>
                          <th>Taken at</th>
                          <th>Current stage</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="lead in row.leads" :key="`${row.user_id}_${lead.id}_${lead.assigned_at}`">
                          <td>
                            <button
                              v-if="lead.stage_id !== 'deleted'"
                              type="button"
                              class="lpr-lead-link"
                              @click.stop="openLeadView(lead.id)"
                            >
                              {{ lead.lead_name || `Lead #${lead.id}` }}
                            </button>
                            <span v-else class="lpr-muted">{{ lead.lead_name || `Lead #${lead.id}` }}</span>
                            <span v-if="!lead.still_with_user && lead.stage_id !== 'deleted'" class="lpr-badge lpr-badge--moved" title="This lead is now with another user">Reassigned</span>
                          </td>
                          <td>{{ formatDateTime(lead.assigned_at) }}</td>
                          <td>
                            <span class="lpr-stage-pill" :style="{ background: lead.stage_color }">{{ lead.stage_name }}</span>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue'
import api from '@/plugins/axios'
import { openLeadView } from '@/composables/useLeadViewModal.js'

const DEFAULT_AVATAR = '/storage/users/user.png'

const toLocalDate = (d) => {
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

const today = new Date()
const dateFrom = ref(toLocalDate(new Date(today.getFullYear(), today.getMonth(), 1)))
const dateTo = ref(toLocalDate(today))

const stages = ref([])
const users = ref([])
const totals = ref({ total: 0, stages: {} })
const loading = ref(false)
const error = ref('')
const expanded = reactive({})

const toggleUser = (userId) => {
  expanded[userId] = !expanded[userId]
}

const formatDateTime = (iso) => {
  if (!iso) return '—'
  const d = new Date(iso)
  return d.toLocaleString([], { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })
}

const fetchReport = async () => {
  loading.value = true
  error.value = ''
  Object.keys(expanded).forEach((k) => delete expanded[k])
  try {
    const response = await api.get('/leads/reports/lead-pool-assignments', {
      params: {
        ...(dateFrom.value ? { date_from: dateFrom.value } : {}),
        ...(dateTo.value ? { date_to: dateTo.value } : {}),
      },
    })
    const data = response?.data?.data || {}
    stages.value = data.stages || []
    users.value = data.users || []
    totals.value = data.totals || { total: 0, stages: {} }
  } catch (err) {
    error.value = err?.response?.data?.message || 'Failed to load report'
  } finally {
    loading.value = false
  }
}

const resetToThisMonth = () => {
  const now = new Date()
  dateFrom.value = toLocalDate(new Date(now.getFullYear(), now.getMonth(), 1))
  dateTo.value = toLocalDate(now)
  fetchReport()
}

onMounted(fetchReport)
</script>

<style scoped>
.lead-pool-report-page.lead-pool-report-page {
  background: #ffffff !important;
  background-image: none !important;
  min-height: 100vh;
  padding: 12px;
}
.lpr-shell { border: 1px solid #d9deea; background: #ffffff !important; border-radius: 14px; padding: 16px; }

.lpr-toolbar { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }
.lpr-filters { display: flex; flex-direction: column; align-items: stretch; gap: 8px; }
.lpr-input { height: 38px; width: 100%; border: 1px solid #ebeef3; border-radius: 8px; padding: 0 10px; color: #10152f; background: #fff; box-sizing: border-box; }
.lpr-sep { color: #8390a7; font-size: 13px; }
.lpr-hint { margin: 0; color: #8390a7; font-size: 12px; }

.lpr-btn { display: flex; align-items: center; justify-content: center; gap: 6px; height: 38px; width: 100%; border-radius: 8px; padding: 0 14px; font-size: 13px; font-weight: 600; border: 1px solid transparent; cursor: pointer; box-sizing: border-box; }
.lpr-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.lpr-btn--ghost { background: #fff; border-color: #ebeef3; color: #10152f; }

.lpr-error { display: flex; align-items: center; gap: 8px; background: #fdecec; color: #b3261e; border: 1px solid #f5c2c0; border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; font-size: 13px; }
.lpr-link { border: none; background: transparent; color: #b3261e; text-decoration: underline; cursor: pointer; margin-left: auto; }
.lpr-loading, .lpr-empty { padding: 24px; text-align: center; color: #8390a7; }

.lpr-kpis { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; margin-bottom: 16px; }
.lpr-kpi { border: 1px solid #ebeef3; border-radius: 12px; padding: 10px 12px; display: flex; flex-direction: column; gap: 4px; }
.lpr-kpi-label { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: #8390a7; font-weight: 600; }
.lpr-kpi-value { font-size: 20px; color: #10152f; }

.lpr-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }

.lpr-table-wrap { overflow-x: auto; }
.lpr-table { width: 100%; border-collapse: collapse; min-width: 640px; }
.lpr-table th { text-align: left; font-size: 12px; color: #8390a7; text-transform: uppercase; padding: 8px 10px; border-bottom: 1px solid #ebeef3; white-space: nowrap; }
.lpr-table td { padding: 10px; border-bottom: 1px solid #f2f4f8; font-size: 13px; color: #10152f; vertical-align: middle; }
.lpr-num { text-align: center !important; }
.lpr-th-toggle { width: 32px; text-align: center; color: #8390a7; }

.lpr-user-row { cursor: pointer; }
.lpr-user-row:hover td { background: #f8f9fc; }
.lpr-user { display: flex; align-items: center; gap: 8px; min-width: 0; }
.lpr-avatar { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.lpr-user-name { font-weight: 600; white-space: nowrap; }

.lpr-count { display: inline-block; min-width: 28px; padding: 1px 8px; border: 2px solid; border-radius: 999px; font-weight: 700; }
.lpr-zero { color: #c3c9d6; }

.lpr-badge { font-size: 10px; font-weight: 700; color: #b3261e; background: #fdecec; border-radius: 999px; padding: 1px 7px; }
.lpr-badge--moved { color: #92400e; background: #fef3c7; margin-left: 6px; }

.lpr-detail-row > td { background: #fafbfd; padding: 8px 10px 12px 46px; }
.lpr-leads { width: 100%; border-collapse: collapse; }
.lpr-leads th { font-size: 11px; color: #8390a7; text-transform: uppercase; padding: 6px 8px; text-align: left; border-bottom: 1px solid #ebeef3; }
.lpr-leads td { font-size: 13px; padding: 7px 8px; border-bottom: 1px solid #f2f4f8; }
.lpr-lead-link { border: none; background: transparent; padding: 0; color: #3547ff; font-weight: 600; cursor: pointer; text-align: left; }
.lpr-lead-link:hover { text-decoration: underline; }
.lpr-muted { color: #8390a7; }
.lpr-stage-pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; color: #10152f; }

@media (min-width: 768px) {
  .lead-pool-report-page.lead-pool-report-page { padding: 20px; }
  .lpr-filters { flex-direction: row; align-items: center; }
  .lpr-input { width: 160px; }
  .lpr-btn { width: auto; }
  .lpr-sep { padding: 0 2px; }
}
</style>
