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
          <v-select
            v-model="selectedUserIds"
            :options="userOptions"
            :reduce="opt => opt.value"
            label="text"
            multiple
            :close-on-select="false"
            deselect-from-dropdown
            placeholder="All users"
            class="lpr-user-select"
          >
            <template #option="option">
              <div class="lpr-opt">
                <img :src="option.avatar || DEFAULT_AVATAR" alt="" class="lpr-opt-avatar" />
                <span class="lpr-opt-name">{{ option.text }}</span>
                <span class="lpr-opt-count">{{ option.total }}</span>
              </div>
            </template>
            <template #selected-option="option">
              <span class="lpr-chip">
                <img :src="option.avatar || DEFAULT_AVATAR" alt="" />
                {{ option.text }}
              </span>
            </template>
            <template #no-options>No users</template>
          </v-select>
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
          <div class="lpr-kpi lpr-kpi--main">
            <span class="lpr-kpi-label">Leads taken</span>
            <strong class="lpr-kpi-value">{{ shownTotals.total }}</strong>
          </div>
          <div class="lpr-kpi">
            <span class="lpr-kpi-label">Users</span>
            <strong class="lpr-kpi-value">{{ filteredUsers.length }}</strong>
          </div>
          <div v-for="stage in stages" :key="`kpi_${stage.id}`" class="lpr-kpi">
            <span class="lpr-kpi-label">
              <span class="lpr-dot" :style="{ background: stage.color }" />
              {{ stage.name }}
            </span>
            <strong class="lpr-kpi-value">{{ shownTotals.stages[stage.id] || 0 }}</strong>
          </div>
        </div>

        <div v-if="!users.length" class="lpr-empty">No Lead Pool assignments in this date range.</div>

        <div v-else-if="!filteredUsers.length" class="lpr-empty">No Lead Pool assignments for the selected users.</div>

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
              <template v-for="row in filteredUsers" :key="row.user_id">
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
                    <span v-if="row.stages[stage.id]" class="lpr-count">{{ row.stages[stage.id] }}</span>
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
                            <span class="lpr-stage-pill">
                              <span class="lpr-dot" :style="{ background: lead.stage_color }" />
                              {{ lead.stage_name }}
                            </span>
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
import { ref, reactive, computed, onMounted } from 'vue'
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
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
// User select: pick one or more users; empty = everyone. Options are the users in the
// loaded report (searchable by name inside the select).
const selectedUserIds = ref([])

const userOptions = computed(() => users.value.map((row) => ({
  value: row.user_id,
  text: row.name,
  avatar: row.avatar,
  total: row.total,
})))

// The selection narrows the table AND the numbers on top.
const filteredUsers = computed(() => {
  if (!selectedUserIds.value.length) return users.value
  const picked = new Set(selectedUserIds.value)
  return users.value.filter((row) => picked.has(row.user_id))
})

const shownTotals = computed(() => {
  if (!selectedUserIds.value.length) {
    return { total: totals.value.total || 0, stages: totals.value.stages || {} }
  }
  const result = { total: 0, stages: {} }
  filteredUsers.value.forEach((row) => {
    result.total += row.total
    Object.entries(row.stages || {}).forEach(([stageId, count]) => {
      result.stages[stageId] = (result.stages[stageId] || 0) + count
    })
  })
  return result
})

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
    // Keep only picked users that still exist in the new date range.
    const ids = new Set(users.value.map((row) => row.user_id))
    selectedUserIds.value = selectedUserIds.value.filter((id) => ids.has(id))
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
/* CRM purple theme (board tabs / Create button #733E87) + greys. Stage colors only as dots. */
.lead-pool-report-page.lead-pool-report-page {
  --lpr-primary: #733e87;
  --lpr-primary-soft: #f4eef7;
  --lpr-primary-line: #e3d6ea;
  --lpr-text: #1e1b2e;
  --lpr-muted: #8b8798;
  --lpr-line: #ece9f1;
  background: #ffffff !important;
  background-image: none !important;
  min-height: 100vh;
  padding: 12px;
}
.lpr-shell { border: 1px solid var(--lpr-line); background: #ffffff !important; border-radius: 14px; padding: 16px; }

.lpr-toolbar { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }
.lpr-filters { display: flex; flex-direction: column; align-items: stretch; gap: 8px; }
.lpr-input { height: 38px; width: 100%; border: 1px solid var(--lpr-line); border-radius: 8px; padding: 0 10px; color: var(--lpr-text); background: #fff; box-sizing: border-box; }
.lpr-input:focus { outline: none; border-color: var(--lpr-primary); }
.lpr-sep { color: var(--lpr-muted); font-size: 13px; }
.lpr-hint { margin: 0; color: var(--lpr-muted); font-size: 12px; }

.lpr-btn { display: flex; align-items: center; justify-content: center; gap: 6px; height: 38px; width: 100%; border-radius: 8px; padding: 0 14px; font-size: 13px; font-weight: 600; border: 1px solid transparent; cursor: pointer; box-sizing: border-box; }
.lpr-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.lpr-btn--ghost { background: #fff; border-color: var(--lpr-primary-line); color: var(--lpr-primary); }
.lpr-btn--ghost:hover:not(:disabled) { background: var(--lpr-primary-soft); }

.lpr-error { display: flex; align-items: center; gap: 8px; background: #fdecec; color: #b3261e; border: 1px solid #f5c2c0; border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; font-size: 13px; }
.lpr-link { border: none; background: transparent; color: #b3261e; text-decoration: underline; cursor: pointer; margin-left: auto; }
.lpr-loading, .lpr-empty { padding: 24px; text-align: center; color: var(--lpr-muted); }

/* User select */
.lpr-user-select { width: 100%; min-width: 0; }
.lpr-user-select :deep(.vs__dropdown-toggle) { min-height: 38px; border: 1px solid var(--lpr-line); border-radius: 8px; background: #fff; }
.lpr-user-select.vs--open :deep(.vs__dropdown-toggle) { border-color: var(--lpr-primary); }
.lpr-user-select :deep(.vs__dropdown-option--highlight) { background: var(--lpr-primary-soft); color: var(--lpr-text); }
.lpr-user-select :deep(.vs__dropdown-option--selected) { font-weight: 600; }
.lpr-user-select :deep(.vs__selected) { background: transparent; border: none; padding: 0; margin: 2px 2px 2px 0; }
.lpr-chip { display: inline-flex; align-items: center; gap: 5px; padding: 2px 8px 2px 3px; border-radius: 999px; background: var(--lpr-primary-soft); color: var(--lpr-primary); font-size: 12px; font-weight: 600; }
.lpr-chip img { width: 18px; height: 18px; border-radius: 50%; object-fit: cover; }
.lpr-opt { display: flex; align-items: center; gap: 8px; }
.lpr-opt-avatar { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.lpr-opt-name { flex: 1 1 auto; min-width: 0; }
.lpr-opt-count { font-size: 11px; font-weight: 700; color: var(--lpr-muted); }

.lpr-kpis { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; margin-bottom: 16px; }
.lpr-kpi { border: 1px solid var(--lpr-line); border-radius: 12px; padding: 10px 12px; display: flex; flex-direction: column; gap: 4px; background: #fff; }
.lpr-kpi--main { background: var(--lpr-primary); border-color: var(--lpr-primary); }
.lpr-kpi--main .lpr-kpi-label { color: #eadff0; }
.lpr-kpi--main .lpr-kpi-value { color: #fff; }
.lpr-kpi-label { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--lpr-muted); font-weight: 600; }
.lpr-kpi-value { font-size: 20px; color: var(--lpr-text); }

.lpr-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }

.lpr-table-wrap { overflow-x: auto; }
.lpr-table { width: 100%; border-collapse: collapse; min-width: 640px; }
.lpr-table th { text-align: left; font-size: 12px; color: var(--lpr-muted); text-transform: uppercase; padding: 8px 10px; border-bottom: 1px solid var(--lpr-line); white-space: nowrap; }
.lpr-table td { padding: 10px; border-bottom: 1px solid #f4f2f7; font-size: 13px; color: var(--lpr-text); vertical-align: middle; }
.lpr-num { text-align: center !important; }
.lpr-th-toggle { width: 32px; text-align: center; color: var(--lpr-muted); }

.lpr-user-row { cursor: pointer; }
.lpr-user-row:hover td { background: #faf8fc; }
.lpr-user { display: flex; align-items: center; gap: 8px; min-width: 0; }
.lpr-avatar { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.lpr-user-name { font-weight: 600; white-space: nowrap; }

.lpr-count { display: inline-block; min-width: 28px; padding: 2px 8px; border-radius: 999px; background: var(--lpr-primary-soft); color: var(--lpr-primary); font-weight: 700; }
.lpr-zero { color: #cfcbd8; }

/* Small grey tags — no extra colors. */
.lpr-badge { font-size: 10px; font-weight: 700; color: #6b6678; background: #f4f2f7; border: 1px solid var(--lpr-line); border-radius: 999px; padding: 1px 7px; }
.lpr-badge--moved { margin-left: 6px; }

.lpr-detail-row > td { background: #fbfafc; padding: 8px 10px 12px 46px; }
.lpr-leads { width: 100%; border-collapse: collapse; }
.lpr-leads th { font-size: 11px; color: var(--lpr-muted); text-transform: uppercase; padding: 6px 8px; text-align: left; border-bottom: 1px solid var(--lpr-line); }
.lpr-leads td { font-size: 13px; padding: 7px 8px; border-bottom: 1px solid #f4f2f7; }
.lpr-lead-link { border: none; background: transparent; padding: 0; color: var(--lpr-primary); font-weight: 600; cursor: pointer; text-align: left; }
.lpr-lead-link:hover { text-decoration: underline; }
.lpr-muted { color: var(--lpr-muted); }
/* Stage color only as a small dot; the label stays dark on light grey (always readable). */
.lpr-stage-pill { display: inline-flex; align-items: center; gap: 6px; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; color: var(--lpr-text); background: #f4f2f7; }

@media (min-width: 768px) {
  .lead-pool-report-page.lead-pool-report-page { padding: 20px; }
  .lpr-filters { flex-direction: row; align-items: center; }
  .lpr-input { width: 160px; }
  .lpr-user-select { margin-left: auto; width: 320px; }
  .lpr-btn { width: auto; }
  .lpr-sep { padding: 0 2px; }
}
</style>
