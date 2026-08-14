<template>
  <div v-if="visible" class="rec-create-job-overlay" @click.self="$emit('close')">
    <div class="rec-create-job-modal rec-history-modal">
      <div class="rec-create-job-modal__head">
        <h6>Scheduled History</h6>
        <button type="button" class="rec-create-job-modal__close" @click="$emit('close')">
          <iconify-icon icon="lucide:x" />
        </button>
      </div>
      <div class="rec-create-job-modal__body">
        <p v-if="!items.length" class="rec-interviews-empty">No scheduled interviews yet.</p>
        <article v-for="item in items" :key="item.id" class="rec-history-row">
          <div class="rec-interview-row__cell">
            <strong>{{ formatDate(item.scheduledAt) }}</strong>
            <span>Interview Date</span>
          </div>
          <div class="rec-interview-row__cell">
            <strong>{{ formatTimeRange(item) }}</strong>
            <span>Interview Time (Slot 1)</span>
          </div>
          <div class="rec-interview-row__cell">
            <strong>{{ item.type === 'in_person' ? 'Offline' : item.typeLabel }}</strong>
            <span>Interview Type</span>
          </div>
          <div class="rec-interview-row__cell">
            <strong>{{ item.interviewerName }}</strong>
            <span>Interviewer</span>
          </div>
          <div class="rec-interview-row__cell">
            <strong>{{ item.branch || item.location || '—' }}</strong>
            <span>Location</span>
          </div>
          <div class="rec-interview-row__actions">
            <button v-if="item.status === 'scheduled'" type="button" class="rec-interview-done" @click="$emit('mark-done', item)">
              <iconify-icon icon="lucide:clock" /> Mark Done
            </button>
            <span v-else class="rec-interview-completed">
              <iconify-icon icon="lucide:check-circle-2" /> Completed
            </span>
          </div>
        </article>
      </div>
    </div>
  </div>
</template>

<script setup>
import { formatAttendanceDate } from '@/services/leaveAttendanceApi'

defineProps({
  visible: Boolean,
  items: { type: Array, default: () => [] },
})
defineEmits(['close', 'mark-done'])

function formatDate(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return formatAttendanceDate(value)
  return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' })
}
function formatTimeRange(item) {
  const start = formatTime(item.scheduledAt)
  const end = formatTime(item.endTime)
  return end ? `${start} - ${end}` : start
}
function formatTime(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Dubai' })
}
</script>
