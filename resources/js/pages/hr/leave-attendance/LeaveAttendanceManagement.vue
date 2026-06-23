<template>
  <div class="emp-mgmt la-mgmt" :class="{ 'emp-mgmt--embedded': embedded }">
    <!-- KPIs -->
    <section class="emp-mgmt__stats la-mgmt__stats">
      <div class="emp-mgmt__stats-grid la-stats-grid--6">
        <article v-for="stat in kpiCards" :key="stat.key" class="emp-stat-card">
          <div>
            <p class="emp-stat-card__value">{{ stat.value }}</p>
            <p class="emp-stat-card__label">{{ stat.label }}</p>
          </div>
          <span class="emp-stat-card__icon" :style="{ background: stat.bgColor, color: stat.iconColor }">
            <iconify-icon :icon="stat.icon" />
          </span>
        </article>
      </div>
    </section>

    <!-- View tabs -->
    <div class="la-view-tabs">
      <button
        v-for="tab in viewTabs"
        :key="tab.id"
        type="button"
        class="la-view-tab"
        :class="{ 'is-active': activeView === tab.id }"
        @click="switchView(tab.id)"
      >
        <iconify-icon :icon="tab.icon" />
        {{ tab.label }}
      </button>
    </div>

    <!-- Toolbar -->
    <div class="emp-mgmt__toolbar">
      <div class="emp-mgmt__search-row">
        <div class="emp-mgmt__search">
          <iconify-icon icon="lucide:search" class="emp-mgmt__search-icon" />
          <input v-model="searchQuery" type="search" :placeholder="searchPlaceholder" autocomplete="off" />
        </div>
        <input
          v-if="activeView === 'records' || activeView === 'calendar'"
          v-model="selectedDate"
          type="date"
          class="la-date-input"
        />
        <button type="button" class="emp-mgmt__toolbar-btn" @click="showFilters = !showFilters">
          <iconify-icon icon="lucide:sliders-horizontal" />
          <span v-if="!isMobile">Filters{{ activeFilterCount ? ` (${activeFilterCount})` : '' }}</span>
        </button>
        <button v-if="!isMobile" type="button" class="emp-mgmt__toolbar-btn" @click="exportCurrent">
          <iconify-icon icon="lucide:download" />
          <span>Export</span>
        </button>
        <button v-if="!isMobile" type="button" class="emp-mgmt__toolbar-btn emp-mgmt__toolbar-btn--primary" @click="primaryAction">
          <iconify-icon icon="lucide:plus" />
          <span>{{ primaryActionLabel }}</span>
        </button>
      </div>
      <div class="emp-mgmt__chips">
        <button
          v-for="chip in quickChips"
          :key="chip.key + chip.value"
          type="button"
          class="emp-mgmt__chip"
          :class="{ 'is-active': filters[chip.key] === chip.value }"
          @click="toggleChip(chip.key, chip.value)"
        >
          {{ chip.label }}
        </button>
        <button v-if="hasActiveFilters" type="button" class="emp-mgmt__chip emp-mgmt__chip--clear" @click="onClearFilters">
          Clear all
        </button>
      </div>
      <div v-if="showFilters && !isMobile" class="emp-filter-desktop">
        <LeaveAttendanceFilterFields
          v-model="localFilters"
          :departments="departments"
          :leave-types="leaveTypes"
          :managers="agentManagers"
        />
        <div style="grid-column:1/-1;display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" class="emp-filter-sheet__clear" style="min-height:40px;padding:0 16px;border-radius:10px;" @click="onClearFilters">Clear</button>
          <button type="button" class="emp-filter-sheet__apply" style="min-height:40px;padding:0 20px;border-radius:10px;border:none;" @click="onApplyFilters">Apply</button>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div v-if="loading" class="emp-mgmt__grid">
      <div v-for="n in 6" :key="n" class="emp-skeleton" />
    </div>
    <div v-else-if="error" class="emp-error">
      <div class="emp-error__icon"><iconify-icon icon="lucide:alert-circle" /></div>
      <h3>Something went wrong</h3>
      <p>{{ error }}</p>
      <button type="button" class="emp-mgmt__toolbar-btn emp-mgmt__toolbar-btn--primary" @click="loadAll">Retry</button>
    </div>

    <!-- Attendance records -->
    <template v-else-if="activeView === 'records'">
      <div v-if="!filteredAttendance.length" class="emp-empty">
        <div class="emp-empty__icon"><iconify-icon icon="lucide:clipboard-list" /></div>
        <h3>No attendance records</h3>
        <p>No records match your search for {{ selectedDate }}.</p>
      </div>
      <div v-else class="emp-mgmt__grid">
        <AttendanceRecordCard
          v-for="row in filteredAttendance"
          :key="`att-${row.id}-${row.checkIn}`"
          :record="row"
          @edit="$emit('edit-attendance', row)"
          @history="$emit('view-history', row)"
        />
      </div>
    </template>

    <!-- Leave requests -->
    <template v-else-if="activeView === 'leave'">
      <div v-if="!filteredLeaves.length" class="emp-empty">
        <div class="emp-empty__icon"><iconify-icon icon="lucide:calendar-days" /></div>
        <h3>No leave requests</h3>
        <p>No requests match your filters.</p>
        <button type="button" class="emp-mgmt__toolbar-btn emp-mgmt__toolbar-btn--primary" @click="$emit('apply-leave')">Apply leave</button>
      </div>
      <div v-else class="emp-mgmt__grid">
        <LeaveRequestCard
          v-for="leave in filteredLeaves"
          :key="`leave-${leave.id}`"
          :leave="leave"
          @approve="onApproveLeave"
          @reject="onRejectLeave"
          @view="$emit('view-leave', leave)"
        />
      </div>
      <div v-if="leavePage < leaveLastPage" class="emp-load-more">
        <button type="button" :disabled="loadingMoreLeaves" @click="loadMoreLeaves">Load more leaves</button>
      </div>
    </template>

    <!-- Calendar -->
    <template v-else-if="activeView === 'calendar'">
      <div class="la-calendar">
        <div class="la-calendar__head">
          <button type="button" class="emp-mgmt__toolbar-btn" @click="shiftMonth(-1)"><iconify-icon icon="lucide:chevron-left" /></button>
          <strong>{{ calendarTitle }}</strong>
          <button type="button" class="emp-mgmt__toolbar-btn" @click="shiftMonth(1)"><iconify-icon icon="lucide:chevron-right" /></button>
        </div>
        <div class="la-calendar__weekdays">
          <span v-for="d in weekdays" :key="d">{{ d }}</span>
        </div>
        <div class="la-calendar__grid">
          <button
            v-for="cell in calendarCells"
            :key="cell.key"
            type="button"
            class="la-calendar__cell"
            :class="{
              'is-outside': !cell.inMonth,
              'is-today': cell.isToday,
              'is-selected': cell.date === selectedDate,
              'has-leave': cell.leaveCount > 0,
              'has-present': cell.presentCount > 0,
            }"
            @click="selectCalendarDay(cell.date)"
          >
            <span class="la-calendar__day">{{ cell.day }}</span>
            <span v-if="cell.leaveCount" class="la-calendar__dot la-calendar__dot--leave" />
            <span v-if="cell.presentCount" class="la-calendar__dot la-calendar__dot--present" />
          </button>
        </div>
        <div class="la-calendar__legend">
          <span><i class="la-calendar__dot la-calendar__dot--present" /> Attendance</span>
          <span><i class="la-calendar__dot la-calendar__dot--leave" /> Leave</span>
        </div>
        <div v-if="selectedDayLeaves.length" class="la-calendar__schedule">
          <h4>Leave on {{ formatDisplayDate(selectedDate) }}</h4>
          <ul>
            <li v-for="l in selectedDayLeaves" :key="l.id">{{ l.employeeName }} — {{ l.leaveType }}</li>
          </ul>
        </div>
      </div>
    </template>

    <!-- Analytics -->
    <template v-else-if="activeView === 'analytics'">
      <div class="la-analytics">
        <div class="la-analytics__row">
          <div class="la-chart-card">
            <h3>Today's attendance</h3>
            <div class="la-bar-chart">
              <div v-for="bar in attendanceTrend" :key="bar.label" class="la-bar-chart__item">
                <div class="la-bar-chart__bar" :style="{ height: barHeight(bar.value, attendanceTrend) + '%', background: bar.color }" />
                <span>{{ bar.label }}</span>
                <strong>{{ bar.value }}</strong>
              </div>
            </div>
          </div>
          <div class="la-chart-card">
            <h3>Leave statistics</h3>
            <div class="la-bar-chart">
              <div v-for="bar in leaveTrend" :key="bar.label" class="la-bar-chart__item">
                <div class="la-bar-chart__bar" :style="{ height: barHeight(bar.value, leaveTrend) + '%', background: bar.color }" />
                <span>{{ bar.label }}</span>
                <strong>{{ bar.value }}</strong>
              </div>
            </div>
          </div>
        </div>
        <div class="la-chart-card la-chart-card--wide">
          <h3>Department comparison (today)</h3>
          <div class="la-dept-bars">
            <div v-for="dept in departmentBreakdown" :key="dept.name" class="la-dept-bar">
              <span class="la-dept-bar__name">{{ dept.name }}</span>
              <div class="la-dept-bar__track">
                <span class="la-dept-bar__fill" :style="{ width: dept.percent + '%' }" />
              </div>
              <span class="la-dept-bar__count">{{ dept.present }}/{{ dept.total }}</span>
            </div>
          </div>
        </div>
      </div>
    </template>

    <button v-if="isMobile" type="button" class="emp-fab" @click="primaryAction">
      <iconify-icon icon="lucide:plus" />
    </button>

    <Teleport to="body">
      <div v-if="showFilters && isMobile" class="emp-filter-sheet" @click.self="showFilters = false">
        <div class="emp-filter-sheet__backdrop" @click="showFilters = false" />
        <div class="emp-filter-sheet__panel">
          <div class="emp-filter-sheet__handle" />
          <div class="emp-filter-sheet__head">
            <h3>Filters</h3>
            <button type="button" class="emp-mgmt__toolbar-btn" @click="showFilters = false"><iconify-icon icon="lucide:x" /></button>
          </div>
          <LeaveAttendanceFilterFields
            v-model="localFilters"
            :departments="departments"
            :leave-types="leaveTypes"
            :managers="agentManagers"
          />
          <div class="emp-filter-sheet__actions">
            <button type="button" class="emp-filter-sheet__clear" @click="onClearFilters">Clear all</button>
            <button type="button" class="emp-filter-sheet__apply" @click="onApplyFilters">Apply</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import Swal from 'sweetalert2'
import { MOBILE_LAYOUT_MAX_WIDTH } from '@/composables/useMobileNavigation'
import { useLeaveAttendanceManagement } from '@/composables/useLeaveAttendanceManagement'
import {
  approveLeaveParent,
  approveLeaveHr,
  rejectLeaveParent,
  rejectLeaveHr,
  exportCsv,
} from '@/services/leaveAttendanceApi'
import AttendanceRecordCard from '@/components/hr/leave-attendance/AttendanceRecordCard.vue'
import LeaveRequestCard from '@/components/hr/leave-attendance/LeaveRequestCard.vue'
import LeaveAttendanceFilterFields from '@/components/hr/leave-attendance/LeaveAttendanceFilterFields.vue'

const props = defineProps({
  embedded: { type: Boolean, default: true },
  initialView: { type: String, default: 'records' },
})

const emit = defineEmits(['apply-leave', 'create-attendance', 'edit-attendance', 'view-history', 'view-leave'])

const showFilters = ref(false)
const isMobile = ref(false)
const localFilters = ref({})
const loadingMoreLeaves = ref(false)

const {
  loading,
  error,
  searchQuery,
  filters,
  selectedDate,
  activeView,
  leaveTypes,
  departments,
  agents,
  leavePage,
  leaveLastPage,
  calendarMonth,
  activeFilterCount,
  hasActiveFilters,
  kpiCards,
  filteredAttendance,
  filteredLeaves,
  leaveTrend,
  attendanceTrend,
  loadAll,
  loadLeaves,
  loadAnalytics,
  clearFilters,
} = useLeaveAttendanceManagement()

activeView.value = props.initialView === 'leave' ? 'leave' : props.initialView === 'attendance' ? 'records' : props.initialView

const viewTabs = [
  { id: 'records', label: 'Attendance', icon: 'lucide:clipboard-check' },
  { id: 'leave', label: 'Leave', icon: 'lucide:calendar-days' },
  { id: 'calendar', label: 'Calendar', icon: 'lucide:calendar' },
  { id: 'analytics', label: 'Analytics', icon: 'lucide:bar-chart-3' },
]

const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

const quickChips = [
  { key: 'attendance_status', value: 'present', label: 'Present' },
  { key: 'attendance_status', value: 'late', label: 'Late' },
  { key: 'attendance_status', value: 'absent', label: 'Absent' },
]

const agentManagers = computed(() =>
  agents.value.filter((a) => a.roles?.some?.((r) => ['manager', 'team_lead', 'hr', 'admin', 'super_admin'].includes(r.name || r)) || a.parent_id == null).slice(0, 50)
)

const searchPlaceholder = computed(() =>
  activeView.value === 'leave'
    ? 'Search employee, leave type…'
    : 'Search name, ID, department, team…'
)

const primaryActionLabel = computed(() =>
  activeView.value === 'leave' ? 'Apply Leave' : 'Add Attendance'
)

const calendarTitle = computed(() =>
  calendarMonth.value.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' })
)

const calendarCells = computed(() => {
  const year = calendarMonth.value.getFullYear()
  const month = calendarMonth.value.getMonth()
  const first = new Date(year, month, 1)
  const startPad = first.getDay()
  const daysInMonth = new Date(year, month + 1, 0).getDate()
  const cells = []
  const today = new Date().toISOString().slice(0, 10)

  for (let i = 0; i < startPad; i++) {
    const d = new Date(year, month, -startPad + i + 1)
    cells.push(makeCell(d, false, today))
  }
  for (let day = 1; day <= daysInMonth; day++) {
    const d = new Date(year, month, day)
    cells.push(makeCell(d, true, today))
  }
  while (cells.length % 7 !== 0) {
    const d = new Date(year, month + 1, cells.length - startPad - daysInMonth + 1)
    cells.push(makeCell(d, false, today))
  }
  return cells
})

function makeCell(date, inMonth, today) {
  const dateStr = date.toLocaleDateString('en-CA')
  const leaveCount = filteredLeaves.value.filter(
    (l) => l.status === 'approved' && l.startDate <= dateStr && l.endDate >= dateStr
  ).length
  const presentCount = dateStr === selectedDate.value
    ? filteredAttendance.value.filter((r) => r.status === 'present' || r.status === 'late').length
    : 0
  return {
    key: dateStr,
    date: dateStr,
    day: date.getDate(),
    inMonth,
    isToday: dateStr === today,
    leaveCount,
    presentCount,
  }
}

const selectedDayLeaves = computed(() =>
  filteredLeaves.value.filter(
    (l) => l.status === 'approved' && l.startDate <= selectedDate.value && l.endDate >= selectedDate.value
  )
)

const departmentBreakdown = computed(() => {
  const map = {}
  filteredAttendance.value.forEach((r) => {
    const dept = r.department || 'Unassigned'
    if (!map[dept]) map[dept] = { name: dept, total: 0, present: 0 }
    map[dept].total += 1
    if (r.status === 'present' || r.status === 'late') map[dept].present += 1
  })
  return Object.values(map).map((d) => ({
    ...d,
    percent: d.total ? Math.round((d.present / d.total) * 100) : 0,
  }))
})

function barHeight(value, list) {
  const max = Math.max(...list.map((b) => b.value), 1)
  return Math.max(8, (value / max) * 100)
}

function syncMobile() {
  isMobile.value = window.innerWidth <= MOBILE_LAYOUT_MAX_WIDTH
}

function switchView(id) {
  activeView.value = id
  if (id === 'analytics') loadAnalytics()
}

function shiftMonth(delta) {
  const d = new Date(calendarMonth.value)
  d.setMonth(d.getMonth() + delta)
  calendarMonth.value = d
}

function selectCalendarDay(date) {
  selectedDate.value = date
  activeView.value = 'records'
  loadAll()
}

function formatDisplayDate(d) {
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function toggleChip(key, value) {
  filters.value[key] = filters.value[key] === value ? '' : value
  if (key === 'attendance_status') loadAll()
}

function onApplyFilters() {
  filters.value = { ...localFilters.value }
  showFilters.value = false
  loadAll()
}

function onClearFilters() {
  localFilters.value = {
    department: '', team: '', attendance_status: '', leave_type_id: '', manager_id: '', start_date: '', end_date: '',
  }
  clearFilters()
  showFilters.value = false
}

function primaryAction() {
  if (activeView.value === 'leave') emit('apply-leave')
  else emit('create-attendance')
}

async function loadMoreLeaves() {
  loadingMoreLeaves.value = true
  try {
    await loadLeaves(false)
  } finally {
    loadingMoreLeaves.value = false
  }
}

async function onApproveLeave(leave) {
  try {
    if (leave.canApproveParent) await approveLeaveParent(leave.id)
    else if (leave.canApproveHr) await approveLeaveHr(leave.id)
    Swal.fire({ icon: 'success', title: 'Approved', timer: 1800, showConfirmButton: false, toast: true, position: 'top-end' })
    await loadAll()
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed', text: e?.response?.data?.message || e?.message })
  }
}

async function onRejectLeave(leave) {
  const { value: reason, isConfirmed } = await Swal.fire({
    title: 'Reject leave?',
    input: 'textarea',
    inputPlaceholder: 'Reason (optional)',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
  })
  if (!isConfirmed) return
  try {
    if (leave.canApproveParent) await rejectLeaveParent(leave.id, reason || '')
    else await rejectLeaveHr(leave.id, reason || '')
    Swal.fire({ icon: 'success', title: 'Rejected', timer: 1800, showConfirmButton: false, toast: true, position: 'top-end' })
    await loadAll()
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed', text: e?.response?.data?.message || e?.message })
  }
}

function exportCurrent() {
  if (activeView.value === 'leave') {
    exportCsv('leave-requests.csv', filteredLeaves.value, [
      { label: 'Employee', value: (r) => r.employeeName },
      { label: 'Type', value: (r) => r.leaveType },
      { label: 'Start', value: (r) => r.startDate },
      { label: 'End', value: (r) => r.endDate },
      { label: 'Days', value: (r) => r.duration },
      { label: 'Status', value: (r) => r.statusLabel },
    ])
  } else {
    exportCsv(`attendance-${selectedDate.value}.csv`, filteredAttendance.value, [
      { label: 'Employee', value: (r) => r.name },
      { label: 'Department', value: (r) => r.department },
      { label: 'Check In', value: (r) => r.checkIn },
      { label: 'Check Out', value: (r) => r.checkOut },
      { label: 'Hours', value: (r) => r.workingHours },
      { label: 'Status', value: (r) => r.status },
    ])
  }
}

watch(() => props.initialView, (v) => {
  if (v === 'leave') activeView.value = 'leave'
  else if (v === 'attendance') activeView.value = 'records'
})

onMounted(() => {
  localFilters.value = { ...filters.value }
  syncMobile()
  window.addEventListener('resize', syncMobile, { passive: true })
})

onUnmounted(() => {
  window.removeEventListener('resize', syncMobile)
})
</script>

<style>
@import '../../../../css/hr-employees.css';
@import '../../../../css/hr-leave-attendance.css';
</style>
