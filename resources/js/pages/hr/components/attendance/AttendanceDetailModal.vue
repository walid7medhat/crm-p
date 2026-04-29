<template>
  <div v-if="visible && state.selectedAttendanceRow" class="edit-overlay" @click.self="handlers.closeAttendanceDetailModal">
    <div class="attendance-detail-modal">
      <button type="button" class="employee-filter-close" @click="handlers.closeAttendanceDetailModal"><iconify-icon icon="lucide:x" /></button>
      <button type="button" class="attendance-detail-edit-link" @click="handlers.switchAttendanceDetailToEdit"><iconify-icon icon="lucide:pencil" /><span>Edit</span></button>
      <div class="attendance-detail-hero"><div class="attendance-detail-icon"><iconify-icon icon="lucide:calendar-check-2" /></div><h6>{{ state.attendanceDetailMode === 'edit' ? 'Edit Attendance' : 'Attendance Details' }}</h6><p>View the complete attendance information for the selected date. This includes check-in, check-out, working hours, and status.</p></div>
      <div class="attendance-detail-grid-card"><div class="attendance-detail-grid">
        <p><span>Employee ID</span><strong>#EMP{{ helpers.formatEmpId(state.selectedAttendanceRow.employee_id) }}</strong></p>
        <p><span>Employee Name</span><strong>{{ state.selectedAttendanceRow.employee_name || '--' }}</strong></p>
        <p v-if="state.attendanceDetailMode === 'view'"><span>Date</span><strong>{{ helpers.formatDate(state.selectedAttendanceRow.date) }}</strong></p>
        <div v-else class="attendance-detail-field"><span>Date</span><input :value="helpers.formatDateDisplay(state.attendanceEditForm.date)" type="text" placeholder="dd/mm/yyyy" readonly @click="handlers.openDatePicker('attendanceEditForm.date')" /></div>
        <p v-if="state.attendanceDetailMode === 'view'"><span>Check in &amp; Check out</span><strong>{{ helpers.formatTime(state.selectedAttendanceRow.check_in) }} - {{ helpers.formatTime(state.selectedAttendanceRow.check_out) }}</strong></p>
        <div v-else class="attendance-detail-field attendance-time-row"><span>Check in &amp; Check out</span><div class="attendance-time-grid"><input v-model="state.attendanceEditForm.checkIn" type="time" /><input v-model="state.attendanceEditForm.checkOut" type="time" /></div></div>
        <p><span>Hours</span><strong>{{ state.attendanceDetailMode === 'view' ? helpers.formatDuration(state.selectedAttendanceRow.check_in, state.selectedAttendanceRow.check_out) : state.attendanceEditDuration }}</strong></p>
        <p v-if="state.attendanceDetailMode === 'view'"><span>Status</span><strong class="attendance-status-text" :class="`status-${String(state.selectedAttendanceRow.status || '').toLowerCase().replace(/\s+/g, '-')}`">{{ state.selectedAttendanceRow.status || '--' }}</strong></p>
        <div v-else class="attendance-detail-field"><span>Status</span><SearchableSelect v-model="state.attendanceEditForm.status" :options="options.attendanceStatusOptions" placeholder="Select Status" /></div>
        <p><span>Break</span><strong>{{ state.attendanceDetailMode === 'view' ? helpers.formatBreakDisplay(state.selectedAttendanceRow) : state.attendanceEditForm.breakLabel || '--' }}</strong></p>
        <p><span>Overtime (OT)</span><strong>{{ state.attendanceDetailMode === 'view' ? helpers.formatOtDisplay(state.selectedAttendanceRow) : state.attendanceEditForm.otLabel || '--' }}</strong></p>
        <p><span>Attachments</span><strong>--</strong></p><p><span>Image</span><strong>No Media</strong></p>
        <p class="full"><span>Description</span><strong>{{ state.attendanceDetailMode === 'view' ? '--' : (state.attendanceEditForm.description || '--') }}</strong></p>
      </div></div>
      <div v-if="state.attendanceDetailMode === 'edit'" class="attendance-detail-actions"><button type="button" class="employee-filter-btn ghost" @click="handlers.closeAttendanceDetailModal">Cancel</button><button type="button" class="employee-filter-btn primary" @click="handlers.saveAttendanceEdit">Save</button></div>
    </div>
  </div>
</template>
<script setup>
import SearchableSelect from '@/components/ui/SearchableSelect.vue'
defineProps({ visible: Boolean, state: Object, options: Object, handlers: Object, helpers: Object })
</script>
