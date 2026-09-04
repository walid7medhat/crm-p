<template>
  <div v-if="visible && row" class="edit-overlay" @click.self="handlers.closeLeaveDetails">
    <div class="leave-detail-modal">
      <button type="button" class="employee-filter-close" @click="handlers.closeLeaveDetails"><iconify-icon icon="lucide:x" /></button>
      <h6>Leave Details</h6>
      <div class="leave-detail-grid">
        <p><span>Employee</span><strong>{{ row.employeeName }}</strong></p><p><span>Designation</span><strong>{{ row.designation }}</strong></p>
        <p><span>Start Date</span><strong>{{ row.startDate }}<span v-if="compOffWeekday" class="leave-detail-weekday"> ({{ compOffWeekday }})</span></strong></p><p><span>End Date</span><strong>{{ row.endDate }}</strong></p>
        <p><span>Leave Days</span><strong>{{ row.days }} Day(s)</strong></p><p><span>Status</span><strong :class="`leave-txt-${row.status.toLowerCase()}`">{{ row.status }}</strong></p>
        <p><span>Leave Type</span><strong>{{ row.leaveType }}</strong></p><p><span>Applied On</span><strong>{{ row.appliedDate }}</strong></p>
      </div>
      <div class="leave-detail-reason"><span>Leave Reason</span><p>{{ row.reason }}</p></div>
      <div class="leave-detail-actions">
        <button type="button" class="leave-approve-btn" @click="handlers.openApproveLeaveModal(row)">Approve Leave</button>
        <button type="button" class="leave-reject-btn" @click="handlers.openRejectLeaveModal(row)">Reject Leave</button>
      </div>
    </div>
  </div>
</template>
<script setup>
import { computed } from 'vue'

const props = defineProps({ visible: Boolean, row: Object, handlers: Object })

const compOffWeekday = computed(() => {
  if (props.row?.leaveType !== 'Compensation Off' || !props.row?.startDate) return ''
  const [y, m, d] = String(props.row.startDate).split('-').map(Number)
  if (!y || !m || !d) return ''
  return new Date(y, m - 1, d).toLocaleDateString('en-US', { weekday: 'long' })
})
</script>
