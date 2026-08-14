<template>
  <div class="emp-mgmt la-mgmt" :class="{ 'emp-mgmt--embedded': embedded }">
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

    <div v-if="loading" class="la-attendance-table-wrap la-attendance-table-wrap--loading">
      <div v-for="n in 8" :key="n" class="la-attendance-table__skeleton" />
    </div>
    <div v-else-if="error" class="emp-error">
      <div class="emp-error__icon"><iconify-icon icon="lucide:alert-circle" /></div>
      <h6>Something went wrong</h6>
      <p>{{ error }}</p>
      <button type="button" class="emp-mgmt__toolbar-btn emp-mgmt__toolbar-btn--primary" @click="loadAll">Retry</button>
    </div>

    <AttendanceTable
      v-else-if="activeView === 'records'"
      :records="pagedAttendance"
      v-model:page="attendancePage"
      v-model:per-page="attendancePerPage"
      v-model:selected-ids="selectedAttendanceIds"
      v-model:search-query="searchQuery"
      :filters="filters"
      :departments="departments"
      :managers="agentManagers"
      :total="filteredAttendance.length"
      :total-pages="attendanceTotalPages"
      :start-entry="attendanceStartEntry"
      :end-entry="attendanceEndEntry"
      :pagination-items="attendancePaginationItems"
      @export="exportCurrent"
      @apply-filters="onPopupSearch"
      @clear-filters="onClearFilters"
      @edit="$emit('edit-attendance', $event)"
      @history="$emit('view-history', $event)"
    />

    <LeaveTable
      v-else
      :records="pagedLeaves"
      v-model:page="leaveTablePage"
      v-model:per-page="leavePerPage"
      v-model:selected-ids="selectedLeaveIds"
      v-model:search-query="searchQuery"
      :filters="filters"
      :departments="departments"
      :leave-types="leaveTypes"
      :managers="agentManagers"
      :total="filteredLeaves.length"
      :total-pages="leaveTotalPages"
      :start-entry="leaveStartEntry"
      :end-entry="leaveEndEntry"
      :pagination-items="leavePaginationItems"
      @export="exportCurrent"
      @apply-filters="onPopupSearch"
      @clear-filters="onClearFilters"
      @approve="onApproveLeave"
      @reject="onRejectLeave"
      @view="$emit('view-leave', $event)"
    />

    <button v-if="isMobile && activeView === 'leave'" type="button" class="emp-fab" @click="primaryAction">
      <iconify-icon icon="lucide:plus" />
    </button>

    <Teleport to="body">
      <div v-if="rejectLeave" class="edit-overlay" @click.self="closeRejectModal">
        <div class="la-reject-modal">
          <button type="button" class="la-reject-modal__close" aria-label="Close" @click="closeRejectModal">
            <iconify-icon icon="lucide:x" />
          </button>
          <div class="la-reject-modal__icon">
            <iconify-icon icon="lucide:ban" />
          </div>
          <h6>Reject leave request?</h6>
          <p v-if="rejectLeave.employeeName">{{ rejectLeave.employeeName }} · {{ rejectLeave.leaveType }}</p>
          <label class="la-reject-modal__field">
            <span>Reason</span>
            <textarea v-model="rejectReason" rows="4" placeholder="Enter a reason (optional)" />
          </label>
          <div class="la-reject-modal__actions">
            <button type="button" class="la-reject-modal__cancel" @click="closeRejectModal">Cancel</button>
            <button type="button" class="la-reject-modal__confirm" @click="confirmRejectLeave">Reject</button>
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
import AttendanceTable from '@/components/hr/leave-attendance/AttendanceTable.vue'
import LeaveTable from '@/components/hr/leave-attendance/LeaveTable.vue'

const props = defineProps({
  embedded: { type: Boolean, default: true },
  initialView: { type: String, default: 'records' },
})

const emit = defineEmits(['apply-leave', 'create-attendance', 'edit-attendance', 'view-history', 'view-leave'])

const isMobile = ref(false)
const rejectLeave = ref(null)
const rejectReason = ref('')
const selectedAttendanceIds = ref([])
const selectedLeaveIds = ref([])

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
  filteredAttendance,
  filteredLeaves,
  attendancePage,
  attendancePerPage,
  attendanceTotalPages,
  attendanceStartEntry,
  attendanceEndEntry,
  attendancePaginationItems,
  pagedAttendance,
  leaveTablePage,
  leavePerPage,
  leaveTotalPages,
  leaveStartEntry,
  leaveEndEntry,
  leavePaginationItems,
  pagedLeaves,
  loadAll,
  clearFilters,
} = useLeaveAttendanceManagement()

watch(filteredAttendance, () => {
  attendancePage.value = 1
  selectedAttendanceIds.value = []
})

watch(attendanceTotalPages, (tp) => {
  if (attendancePage.value > tp) attendancePage.value = tp
})

watch(filteredLeaves, () => {
  leaveTablePage.value = 1
  selectedLeaveIds.value = []
})

watch(leaveTotalPages, (tp) => {
  if (leaveTablePage.value > tp) leaveTablePage.value = tp
})

watch(searchQuery, () => {
  attendancePage.value = 1
  leaveTablePage.value = 1
})

activeView.value = props.initialView === 'leave' ? 'leave' : props.initialView === 'attendance' ? 'records' : props.initialView

const viewTabs = [
  { id: 'records', label: 'Attendance', icon: 'lucide:clipboard-check' },
  { id: 'leave', label: 'Leave', icon: 'lucide:calendar-days' },
]

const agentManagers = computed(() =>
  agents.value.filter((a) => a.roles?.some?.((r) => ['manager', 'team_lead', 'hr', 'admin', 'super_admin'].includes(r.name || r)) || a.parent_id == null).slice(0, 50)
)

function syncMobile() {
  isMobile.value = window.innerWidth <= MOBILE_LAYOUT_MAX_WIDTH
}

function switchView(id) {
  activeView.value = id
}

function onPopupSearch(payload) {
  searchQuery.value = payload.search || ''
  filters.value = {
    ...filters.value,
    department: payload.department || '',
    attendance_status: payload.attendance_status || '',
    leave_type_id: payload.leave_type_id || '',
    manager_id: payload.manager_id || '',
    start_date: payload.start_date || '',
    end_date: payload.end_date || '',
  }
  loadAll()
}

function onClearFilters() {
  searchQuery.value = ''
  clearFilters()
}

function primaryAction() {
  emit('apply-leave')
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

function onRejectLeave(leave) {
  rejectLeave.value = leave
  rejectReason.value = ''
}

function closeRejectModal() {
  rejectLeave.value = null
  rejectReason.value = ''
}

async function confirmRejectLeave() {
  const leave = rejectLeave.value
  if (!leave) return
  try {
    if (leave.canApproveParent) await rejectLeaveParent(leave.id, rejectReason.value || '')
    else await rejectLeaveHr(leave.id, rejectReason.value || '')
    closeRejectModal()
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
