<template>
  <div class="dashboard-main-body hr-screen">
    <div class="hr-frame">
      <div class="hr-topbar">
        <div class="hr-topbar-tabs">
          <button
            v-for="tab in headerTabs"
            :key="tab"
            type="button"
            class="hr-tab"
            :class="{ active: tab === activeTab }"
            @click="activeTab = tab"
          >
            {{ tab }}
            <iconify-icon v-if="tab !== 'Overview'" icon="lucide:chevron-down" class="hr-tab-chevron" />
          </button>
        </div>
        <div class="hr-topbar-actions">
          <button type="button" class="hr-generate-btn">
            Generate Leave
            <iconify-icon icon="lucide:plus" />
          </button>
          <button type="button" class="hr-icon-btn"><iconify-icon icon="lucide:more-vertical" /></button>
          <button type="button" class="hr-icon-btn"><iconify-icon icon="lucide:settings" /></button>
        </div>
      </div>

      <div class="hr-content-card" v-if="activeTab === 'Leave / Attendance'">
        <div class="hr-content-shell" :class="{ 'hr-content-shell--team': hrSectionTab === 'team' }">
          <div class="hr-content-head">
            <h6 class="hr-heading">Manage Attendance</h6>
            <div class="hr-head-actions">
              <div class="hr-date-filter">
                <label for="hr-attendance-date">Date</label>
                <input
                  id="hr-attendance-date"
                  v-model="dateFilter"
                  type="date"
                  class="form-control form-control-sm hr-date-input"
                  @change="onAttendanceDateChange"
                />
              </div>
              <div class="hr-search-wrap">
                <iconify-icon icon="lucide:plus" />
                <input
                  v-model="searchKeyword"
                  type="text"
                  class="hr-search-input"
                  placeholder="Filter and search Attendance"
                />
                <iconify-icon icon="lucide:search" />
              </div>
              <button type="button" class="hr-export-btn" @click="exportAttendance">
                Export Excel
                <iconify-icon icon="lucide:file-down" />
              </button>
            </div>
          </div>

          <div class="hr-inner-tabs">
            <button type="button" class="hr-inner-tab" :class="{ active: hrSectionTab === 'attendance' }" @click="hrSectionTab = 'attendance'">
              Attendance
            </button>
            <button type="button" class="hr-inner-tab" :class="{ active: hrSectionTab === 'team' }" @click="hrSectionTab = 'team'">
              TEAM VIEW
            </button>
          </div>

          <template v-if="hrSectionTab === 'attendance'">
          <div class="hr-summary-row">
            <div class="hr-stat-card">
              <span>Total Employees</span>
              <strong>{{ summary.total_employees }}</strong>
            </div>
            <div class="hr-stat-card present">
              <span>Present</span>
              <strong>{{ summary.present_today }}</strong>
            </div>
            <div class="hr-stat-card absent">
              <span>Absent</span>
              <strong>{{ summary.absent_today }}</strong>
            </div>
            <div class="hr-stat-card late">
              <span>Late</span>
              <strong>{{ summary.late_today }}</strong>
            </div>
            <div class="hr-chart-card">
              <ApexCharts type="donut" height="90" :options="chartOptions" :series="chartSeries" />
            </div>
          </div>

          <div class="hr-table-wrap">
            <table class="table hr-table align-middle mb-0">
              <thead>
                <tr>
                  <th class="checkbox-col"><input type="checkbox" /></th>
                  <th>Date</th>
                  <th>EMP ID</th>
                  <th>Employee Name</th>
                  <th>Status</th>
                  <th>Check In &amp; Check Out</th>
                  <th>Action</th>
                </tr>
              </thead>

              <tbody v-if="loading">
                <tr v-for="i in 10" :key="`sk-${i}`">
                  <td colspan="7"><div class="hr-skeleton"></div></td>
                </tr>
              </tbody>

              <tbody v-else-if="filteredRows.length === 0">
                <tr>
                  <td colspan="7">
                    <div class="hr-empty">
                      <div class="hr-empty-title">No attendance records found</div>
                      <div class="hr-empty-text">Try another date or filter keyword.</div>
                    </div>
                  </td>
                </tr>
              </tbody>

              <tbody v-else>
                <tr v-for="row in pagedRows" :key="`${row.employee_id}-${row.date}`">
                  <td class="checkbox-col"><input type="checkbox" /></td>
                  <td>{{ formatDate(row.date) }}</td>
                  <td class="emp-id">#EMP{{ formatEmpId(row.employee_id) }}</td>
                  <td>
                    <div class="employee-cell">
                      <span class="avatar-circle">{{ initials(row.employee_name) }}</span>
                      <span>{{ row.employee_name }}</span>
                    </div>
                  </td>
                  <td><span class="status-badge" :class="`status-${row.status}`">{{ row.status }}</span></td>
                  <td>
                    <div class="check-flow">
                      <span class="check-time">{{ formatTime(row.check_in) }}</span>
                      <span class="check-duration-wrap">
                        <span class="dur-dot"></span>
                        <span class="dur-line"></span>
                        <span class="dur-text">{{ formatDuration(row.check_in, row.check_out) }}</span>
                        <span class="dur-line"></span>
                        <span class="dur-dot"></span>
                      </span>
                      <span class="check-time">{{ formatTime(row.check_out) }}</span>
                    </div>
                  </td>
                  <td>
                    <button type="button" class="row-action-btn" @click="openEdit(row)">
                      <iconify-icon icon="lucide:more-vertical" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="hr-footer">
            <span>Showing {{ startEntry }} to {{ endEntry }} of {{ filteredRows.length }} Entries</span>
            <div class="hr-pagination">
              <button type="button" class="page-btn" :disabled="page === 1" @click="page = Math.max(1, page - 1)">Previous</button>
              <button type="button" class="page-number active">1</button>
              <button type="button" class="page-number">2</button>
              <button type="button" class="page-number">3</button>
              <span class="page-dots">...</span>
              <button type="button" class="page-number">10</button>
              <button type="button" class="page-btn" :disabled="page >= totalPages" @click="page = Math.min(totalPages, page + 1)">Next</button>
            </div>
          </div>
          </template>

          <template v-else>
            <div class="team-view-controls">
              <div class="team-control">
                <label>Search</label>
                <input v-model="teamSearch" type="text" class="form-control form-control-sm" placeholder="Search by name or ID" />
              </div>
              <div class="team-control">
                <label>Team Filter</label>
                <select v-model="teamFilter" class="form-select form-select-sm">
                  <option v-for="option in teamOptions" :key="option" :value="option">
                    {{ option === 'all' ? 'All Teams' : option }}
                  </option>
                </select>
              </div>
              <div class="team-control">
                <label>Status</label>
                <select v-model="treeStatusFilter" class="form-select form-select-sm">
                  <option value="all">All Status</option>
                  <option value="present">Present</option>
                  <option value="late">Late</option>
                  <option value="absent">Absent</option>
                </select>
              </div>
            </div>

            <template v-if="hrDebugUi">
              <div class="hr-pipeline-debug font-monospace small p-2 mb-2 bg-warning bg-opacity-25 rounded text-start">
                <div>attendance: {{ attendance.length }} | tree roots: {{ hrAttendanceTeamTree.length }} | agents: {{ mergedData.length }}</div>
              </div>
            </template>

            <div class="card border-0 shadow-sm hr-team-tree-card mt-2" v-if="!loading && !loadingAgents">
              <div class="card-body p-0 hr-team-tree-card-body">
                <div class="team-tree-container hr-team-tree-container">
                  <HrTeamTreePanel :roots="hrAttendanceTeamTree" :team-filter="teamFilter" />
                </div>
              </div>
            </div>
          </template>

          <div v-if="error" class="alert alert-danger mt-3 mb-0 py-2">{{ error }}</div>
        </div>
      </div>

      <div v-else class="hr-content-card">
        <div class="hr-content-shell hr-empty-tab"></div>
      </div>
    </div>

    <div v-if="editingRow" class="edit-overlay" @click.self="editingRow = null">
      <div class="edit-modal">
        <div class="edit-modal-head">
          <h6>Edit Attendance</h6>
          <button type="button" class="row-action-btn" @click="editingRow = null">
            <iconify-icon icon="lucide:x" />
          </button>
        </div>
        <div class="edit-modal-body">
          <p><strong>Employee:</strong> {{ editingRow.employee_name }}</p>
          <p><strong>Date:</strong> {{ formatDate(editingRow.date) }}</p>
          <p><strong>Check In:</strong> {{ formatTime(editingRow.check_in) }}</p>
          <p><strong>Check Out:</strong> {{ formatTime(editingRow.check_out) }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import ApexCharts from 'vue3-apexcharts'
import api from '@/plugins/axios'
import HrTeamTreePanel from '@/components/hr/HrTeamTreePanel.vue'
import { hrPipelineDebugEnabled, useHrDashboard } from '@/composables/useHrDashboard'

const {
  loading,
  error,
  dateFilter,
  employees,
  attendance,
  mergedData,
  filteredMergedEmployees,
  summary,
  chartSeries,
  loadAttendance,
  loadAgentData,
  loadingAgents,
  teamSearch,
  teamFilter,
  treeStatusFilter,
  groupedTeams,
  hrAttendanceTeamTree,
  teamOptions,
} = useHrDashboard()

const route = useRoute()
/** True in Vite dev, when `VITE_HR_PIPELINE_DEBUG=1` (rebuild), or `?hr_debug=1` in the URL. */
const hrDebugUi = computed(() => {
  void route.fullPath
  return hrPipelineDebugEnabled()
})

const headerTabs = ['Overview', 'Employees', 'Payroll', 'Leave / Attendance', 'Career', 'Assets']
const activeTab = ref('Leave / Attendance')
const searchKeyword = ref('')
const page = ref(1)
const perPage = 10
const editingRow = ref(null)
const hrSectionTab = ref('attendance')
const filteredRows = computed(() => {
  const keyword = searchKeyword.value.trim().toLowerCase()
  if (!keyword) return employees.value
  return employees.value.filter((row) => {
    const name = String(row.employee_name || '').toLowerCase()
    const status = String(row.status || '').toLowerCase()
    const id = String(row.employee_id || '').toLowerCase()
    return name.includes(keyword) || status.includes(keyword) || id.includes(keyword)
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredRows.value.length / perPage)))
const pagedRows = computed(() => {
  const start = (page.value - 1) * perPage
  return filteredRows.value.slice(start, start + perPage)
})
const startEntry = computed(() => (filteredRows.value.length ? (page.value - 1) * perPage + 1 : 0))
const endEntry = computed(() => Math.min(page.value * perPage, filteredRows.value.length))

const chartOptions = computed(() => ({
  chart: { toolbar: { show: false } },
  labels: ['Present', 'Absent', 'Late'],
  colors: ['#16a34a', '#dc2626', '#f59e0b'],
  legend: { show: false },
  stroke: { width: 0 },
  dataLabels: { enabled: false },
}))

function initials(name) {
  if (!name) return 'U'
  const parts = String(name).trim().split(/\s+/).slice(0, 2)
  return parts.map((p) => p.charAt(0).toUpperCase()).join('') || 'U'
}

function formatEmpId(value) {
  const num = Number(value)
  if (Number.isNaN(num) || num <= 0) return String(value || '0001')
  return String(num).padStart(4, '0')
}

function formatDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return String(value)
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatTime(value) {
  if (!value) return '--'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return String(value)
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

function formatDuration(checkIn, checkOut) {
  if (!checkIn || !checkOut) return '--'
  const inDate = new Date(checkIn)
  const outDate = new Date(checkOut)
  if (Number.isNaN(inDate.getTime()) || Number.isNaN(outDate.getTime())) return '--'
  let diff = Math.round((outDate.getTime() - inDate.getTime()) / 60000)
  if (diff < 0) diff = 0
  const hours = Math.floor(diff / 60)
  const minutes = diff % 60
  return `${hours}h ${minutes}m`
}

function openEdit(row) {
  editingRow.value = row
}

async function onAttendanceDateChange() {
  page.value = 1
  await loadAttendance()
}

function exportAttendance() {
  if (!filteredRows.value.length) {
    if (window.$showNotification) window.$showNotification('No attendance data to export', 'warning')
    return
  }

  const headers = ['Date', 'EMP ID', 'Employee Name', 'Status', 'Check In', 'Check Out']
  const rows = filteredRows.value.map((row) => [
    formatDate(row.date),
    `EMP${formatEmpId(row.employee_id)}`,
    row.employee_name || '',
    row.status || '',
    formatTime(row.check_in),
    formatTime(row.check_out),
  ])

  const csv = [headers, ...rows]
    .map((line) => line.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(','))
    .join('\n')

  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `attendance-${new Date().toISOString().slice(0, 10)}.csv`
  link.click()
  URL.revokeObjectURL(link.href)
}

onMounted(async () => {
  console.log('BASE URL:', api.defaults.baseURL)
  const d = new Date()
  dateFilter.value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
  await Promise.all([loadAttendance(), loadAgentData()])
})
</script>

<style scoped>
.hr-screen { padding-top: 8px; }
.hr-frame {
  background: linear-gradient(180deg, #1136c7 0%, #0a29a2 100%);
  border-radius: 18px;
  border: 1px solid #3657d7;
  padding: 14px;
  box-shadow: 0 14px 32px rgba(16, 32, 97, 0.2);
}
.hr-topbar {
  background: #fff;
  border-radius: 14px;
  min-height: 62px;
  padding: 8px 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.hr-topbar-tabs { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.hr-tab {
  border: none;
  background: transparent;
  padding: 9px 12px;
  font-size: 13px;
  color: #4b5563;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  gap: 3px;
}
.hr-tab.active {
  color: #111827;
  font-weight: 600;
  border-bottom: 2px solid #f5c543;
  border-radius: 0;
}
.hr-tab-chevron { font-size: 12px; color: #9ca3af; }
.hr-topbar-actions { display: flex; align-items: center; gap: 8px; }
.hr-generate-btn {
  border: none;
  background: #0d1f77;
  color: #fff;
  border-radius: 24px;
  padding: 10px 16px;
  font-size: 13px;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.hr-icon-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 1px solid #e5e7eb;
  background: #fff;
  color: #6b7280;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.hr-content-card {
  margin-top: 12px;
  border: 1px solid rgba(189, 203, 255, 0.55);
  border-radius: 14px;
  padding: 12px;
}
.hr-content-shell {
  background: #fff;
  border: 1px solid #d6dff8;
  border-radius: 12px;
  padding: 14px;
}
.hr-content-shell--team .hr-content-head {
  padding-bottom: 0;
  margin-bottom: 0;
}
.hr-content-shell--team .hr-heading {
  font-size: 15px;
  font-weight: 600;
}
.hr-content-shell--team .hr-inner-tabs {
  margin-top: 4px;
}
.hr-content-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.hr-heading {
  margin: 0;
  font-size: 22px;
  font-weight: 500;
  color: #374151;
}
.hr-head-actions { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
.hr-date-filter {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 150px;
}
.hr-date-filter label {
  margin: 0;
  font-size: 11px;
  color: #6b7280;
  font-weight: 500;
}
.hr-date-input {
  border-radius: 8px;
  border: 1px solid #eceff5;
  font-size: 12px;
}
.hr-search-wrap {
  min-width: 360px;
  display: flex;
  align-items: center;
  gap: 8px;
  background: #fff;
  border: 1px solid #eceff5;
  border-radius: 22px;
  padding: 9px 12px;
  color: #9ca3af;
}
.hr-search-input {
  border: none;
  outline: none;
  width: 100%;
  font-size: 12px;
  color: #4b5563;
}
.hr-export-btn {
  border: 1px solid #eceff5;
  background: #fff;
  border-radius: 22px;
  padding: 9px 14px;
  color: #111827;
  font-size: 13px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.hr-inner-tabs {
  margin-top: 12px;
  display: flex;
  gap: 8px;
}
.hr-inner-tab {
  border: 1px solid #e5eaf3;
  background: #fff;
  border-radius: 10px;
  padding: 6px 12px;
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
}
.hr-inner-tab.active {
  background: #eef4ff;
  color: #1d4ed8;
  border-color: #cfdcff;
}

.hr-summary-row {
  margin-top: 12px;
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 8px;
}
.hr-stat-card {
  background: #f8fafc;
  border: 1px solid #edf2fb;
  border-radius: 12px;
  padding: 10px 12px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.hr-stat-card span { font-size: 11px; color: #64748b; }
.hr-stat-card strong { font-size: 20px; font-weight: 700; color: #111827; }
.hr-stat-card.present strong { color: #15803d; }
.hr-stat-card.absent strong { color: #b91c1c; }
.hr-stat-card.late strong { color: #b45309; }
.hr-chart-card {
  border: 1px solid #edf2fb;
  border-radius: 12px;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
}

.hr-table-wrap {
  margin-top: 12px;
  border: 1px solid #edf1f8;
  border-radius: 12px;
  overflow: hidden;
}
.hr-table thead th {
  background: #fafbfe;
  border-bottom: 1px solid #edf1f8;
  font-size: 12px;
  font-weight: 500;
  color: #6b7280;
  padding: 12px 10px;
  white-space: nowrap;
}
.hr-table tbody td {
  border-bottom: 1px solid #edf1f8;
  font-size: 13px;
  color: #374151;
  padding: 12px 10px;
}
.checkbox-col { width: 38px; text-align: center; }
.emp-id { color: #9ca3af; letter-spacing: 0.02em; }
.employee-cell {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-weight: 500;
}
.avatar-circle {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #e0ecff;
  color: #2f65f6;
  font-size: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
}
.status-badge {
  text-transform: capitalize;
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
}
.status-present { background: #dcfce7; color: #166534; }
.status-absent { background: #fee2e2; color: #991b1b; }
.status-late { background: #ffedd5; color: #9a3412; }
.row-action-btn {
  border: none;
  background: transparent;
  color: #6b7280;
}
.check-flow {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  white-space: nowrap;
}
.check-time {
  color: #111827;
  font-weight: 500;
  letter-spacing: 0.01em;
}
.check-duration-wrap {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.dur-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #9ca3af;
}
.dur-line {
  width: 20px;
  height: 1px;
  background: #cfd4dc;
}
.dur-text {
  color: #d69a22;
  font-size: 13px;
  font-weight: 500;
}
.hr-table tbody tr { transition: background-color .18s ease; }
.hr-table tbody tr:hover { background: #f8fbff; }

.hr-footer {
  margin-top: 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  font-size: 12px;
  color: #9ca3af;
}
.hr-pagination { display: flex; align-items: center; gap: 6px; }
.page-btn, .page-number {
  border: 1px solid #eceff5;
  background: #fff;
  color: #4b5563;
  border-radius: 18px;
  padding: 7px 12px;
  font-size: 12px;
}
.page-number {
  width: 32px;
  height: 32px;
  padding: 0;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}
.page-number.active { background: #f2f4f8; border-color: #f2f4f8; }
.page-dots { color: #9ca3af; font-size: 12px; padding: 0 4px; }

.hr-empty { padding: 28px 12px; text-align: center; }
.hr-empty-title { color: #334155; font-size: 13px; font-weight: 600; }
.hr-empty-text { color: #94a3b8; font-size: 12px; margin-top: 4px; }

.hr-skeleton {
  height: 24px;
  border-radius: 8px;
  background: linear-gradient(90deg, #f5f7fb 25%, #e9edf5 37%, #f5f7fb 63%);
  background-size: 400px 100%;
  animation: hrShimmer 1.1s infinite linear;
}
@keyframes hrShimmer { 0% { background-position: -400px 0; } 100% { background-position: 400px 0; } }

.hr-empty-tab {
  min-height: 620px;
}
.team-view-controls {
  margin-top: 8px;
  margin-bottom: 16px;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}
.team-control label {
  display: block;
  margin-bottom: 6px;
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
}
.hr-team-tree-card {
  background: #fff;
  border-radius: 14px;
  overflow: hidden;
  border: 1px solid #dfe4f1;
  margin-top: 6px;
}
.hr-team-tree-card-body {
  display: flex;
  flex-direction: column;
  min-height: 520px;
  max-height: 74vh;
}
.hr-team-tree-container {
  position: relative;
  width: 100%;
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: #f4f4f5;
}

.edit-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.35);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
}
.edit-modal {
  width: min(420px, 95vw);
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  box-shadow: 0 20px 35px rgba(15, 23, 42, 0.15);
}
.edit-modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 14px;
  border-bottom: 1px solid #edf1f8;
}
.edit-modal-head h6 {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
}
.edit-modal-body {
  padding: 14px;
  font-size: 13px;
  color: #374151;
}

@media (max-width: 1200px) {
  .hr-summary-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .hr-search-wrap { min-width: 260px; }
}
@media (max-width: 900px) {
  .hr-content-head { flex-direction: column; align-items: stretch; }
  .hr-head-actions { width: 100%; flex-direction: column; align-items: stretch; }
  .hr-search-wrap { min-width: 0; width: 100%; }
  .hr-footer { flex-direction: column; align-items: flex-start; }
  .team-view-controls { grid-template-columns: 1fr; }
}
</style>

