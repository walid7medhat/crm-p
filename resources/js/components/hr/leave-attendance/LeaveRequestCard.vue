<template>
  <article class="emp-card la-leave-card">
    <div class="emp-card__head">
      <img :src="leave.avatar" :alt="leave.employeeName" class="emp-card__avatar" loading="lazy" />
      <div class="emp-card__meta">
        <p class="emp-card__name">{{ leave.employeeName }}</p>
        <p class="emp-card__code">{{ leave.empCode }}</p>
      </div>
      <span class="emp-card__badge" :class="statusClass">{{ leave.statusLabel }}</span>
    </div>
    <div class="la-leave-card__body">
      <p class="emp-card__role">{{ leave.leaveType }}</p>
      <div class="la-leave-card__dates">
        <span><iconify-icon icon="lucide:calendar" /> {{ formatDate(leave.startDate) }} → {{ formatDate(leave.endDate) }}</span>
        <span><iconify-icon icon="lucide:timer" /> {{ leave.duration }} day(s)</span>
      </div>
    </div>
    <div class="emp-card__actions la-leave-card__actions" @click.stop>
      <button v-if="leave.canApproveParent || leave.canApproveHr" type="button" class="emp-card__action la-action--approve" @click="$emit('approve', leave)">
        <iconify-icon icon="lucide:check" /> Approve
      </button>
      <button v-if="leave.canReject" type="button" class="emp-card__action la-action--reject" @click="$emit('reject', leave)">
        <iconify-icon icon="lucide:x" /> Reject
      </button>
      <button type="button" class="emp-card__action" @click="$emit('view', leave)">
        <iconify-icon icon="lucide:eye" /> View
      </button>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  leave: { type: Object, required: true },
})

defineEmits(['approve', 'reject', 'view'])

const statusClass = computed(() => {
  const s = props.leave.status
  if (s === 'approved') return 'emp-card__badge--active'
  if (s === 'rejected') return 'emp-card__badge--terminated'
  if (s?.includes('pending')) return 'emp-card__badge--on_leave'
  return 'emp-card__badge--inactive'
})

function formatDate(v) {
  if (!v) return '—'
  const d = new Date(v)
  if (Number.isNaN(d.getTime())) return v
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })
}
</script>

<style scoped>
.la-leave-card__body { margin-top: -4px; }
.la-leave-card__dates {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 12px;
  color: #64748b;
}
.la-leave-card__dates span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.la-leave-card__actions .la-action--approve {
  background: #ecfdf5;
  border-color: #86efac;
  color: #15803d;
}
.la-leave-card__actions .la-action--reject {
  background: #fef2f2;
  border-color: #fecaca;
  color: #dc2626;
}
</style>
