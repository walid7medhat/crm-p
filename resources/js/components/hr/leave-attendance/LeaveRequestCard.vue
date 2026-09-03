<template>
  <article class="la-leave-card">
    <div class="la-leave-card__head">
      <img
        :src="leave.avatar"
        :alt="leave.employeeName"
        class="la-leave-card__avatar"
        loading="lazy"
        @error="onAvatarError"
      />
      <div class="la-leave-card__meta">
        <p class="la-leave-card__name">{{ leave.employeeName }}</p>
        <p class="la-leave-card__code">{{ formattedEmpCode }}</p>
      </div>
      <span class="la-leave-card__status" :class="statusClass">
        <iconify-icon :icon="statusIcon" />
        {{ statusText }}
      </span>
    </div>

    <div class="la-leave-card__body">
      <div class="la-leave-card__row">
        <span class="la-leave-card__icon-box">
          <iconify-icon icon="lucide:alarm-clock" />
        </span>
        <div class="la-leave-card__row-text">
          <span class="la-leave-card__label">Leave Type</span>
          <p class="la-leave-card__value">{{ leave.leaveType }}</p>
        </div>
      </div>

      <div class="la-leave-card__row">
        <span class="la-leave-card__icon-box">
          <iconify-icon icon="lucide:calendar" />
        </span>
        <div class="la-leave-card__row-text">
          <span class="la-leave-card__label">Dates</span>
          <p class="la-leave-card__value">
            {{ formatDate(leave.startDate) }} - {{ formatDate(leave.endDate) }}
          </p>
        </div>
      </div>

      <div class="la-leave-card__row">
        <span class="la-leave-card__icon-box">
          <iconify-icon icon="lucide:clock" />
        </span>
        <div class="la-leave-card__row-text">
          <span class="la-leave-card__label">Duration</span>
          <p class="la-leave-card__value">{{ durationText }}</p>
        </div>
      </div>
    </div>

    <div class="la-leave-card__actions" @click.stop>
      <template v-if="hasApproveReject">
        <button
          v-if="leave.canApproveParent || leave.canApproveHr"
          type="button"
          class="la-leave-card__btn la-leave-card__btn--approve"
          @click="$emit('approve', leave)"
        >
          <iconify-icon icon="lucide:circle-check" />
          Approve
        </button>

        <button
          v-if="leave.canReject"
          type="button"
          class="la-leave-card__btn la-leave-card__btn--reject"
          @click="$emit('reject', leave)"
        >
          <iconify-icon icon="lucide:ban" />
          Reject
        </button>

        <button
          type="button"
          class="la-leave-card__btn la-leave-card__btn--view"
          @click="$emit('view', leave)"
        >
          View
          <iconify-icon icon="lucide:eye" />
        </button>
      </template>

      <button
        v-else
        type="button"
        class="la-leave-card__btn la-leave-card__btn--view la-leave-card__btn--full"
        @click="$emit('view', leave)"
      >
        View Details
        <iconify-icon icon="lucide:eye" />
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
  if (s === 'approved') return 'is-approved'
  if (s === 'rejected') return 'is-rejected'
  if (s?.includes('pending')) return 'is-pending'
  return 'is-pending'
})

const statusIcon = computed(() => {
  const s = props.leave.status
  if (s === 'approved') return 'lucide:circle-check'
  if (s === 'rejected') return 'lucide:ban'
  return 'lucide:clock'
})

const statusText = computed(() => {
  const s = props.leave.status
  if (s === 'approved') return 'Approved'
  if (s === 'rejected') return 'Rejected'
  if (s === 'pending_parent' || s === 'pending_hr' || s?.includes('pending')) return 'Pending'
  return props.leave.statusLabel || 'Pending'
})

const formattedEmpCode = computed(() => {
  const code = props.leave.empCode
  if (!code || code === '—') return 'EMP - —'
  const raw = String(code).replace(/^EMP\s*-?\s*/i, '').replace(/^#/, '').trim()
  return `EMP - ${raw}`
})

const hasApproveReject = computed(
  () => props.leave.canApproveParent || props.leave.canApproveHr || props.leave.canReject,
)

const durationText = computed(() => {
  const v = props.leave.duration
  if (v === null || v === undefined || v === '' || v === '—') return '—'
  const n = Number(v)
  if (Number.isFinite(n)) return `${n} Day${n === 1 ? '' : 's'}`
  return `${String(v)} Days`
})

function formatDate(v) {
  if (!v) return '—'
  const d = new Date(v)
  if (Number.isNaN(d.getTime())) return v
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function onAvatarError(event) {
  const el = event?.target
  if (!el || el.dataset.avatarFallback === '1') return
  el.dataset.avatarFallback = '1'
  const name = el.alt || 'E'
  el.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=733e87&color=fff`
}
</script>

<style scoped>
.la-leave-card {
  background: #fff;
  border: 1px solid #e8edf3;
  border-radius: 16px;
  padding: 18px 18px 16px;
  box-shadow: 0 4px 18px rgba(15, 23, 42, 0.05);
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.la-leave-card__head {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding-bottom: 14px;
  border-bottom: 1px solid #f1f5f9;
}

.la-leave-card__avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid #f1f5f9;
  flex-shrink: 0;
}

.la-leave-card__meta {
  min-width: 0;
  flex: 1;
  padding-top: 2px;
}

.la-leave-card__name {
  margin: 0;
  font-size: 14px !important;
  font-weight: 700;
  color: #0b0736;
  line-height: 1.3;
  word-break: break-word;
}

.la-leave-card__code {
  margin: 3px 0 0;
  font-size: 12px;
  color: #94a3b8;
  font-weight: 500;
}

.la-leave-card__status {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  border-radius: 999px;
  padding: 5px 12px;
  font-size: 11px;
  font-weight: 600;
  flex-shrink: 0;
  white-space: nowrap;
}

.la-leave-card__status iconify-icon {
  font-size: 14px;
}

.la-leave-card__status.is-pending {
  background: #fff7ed;
  color: #c2410c;
}

.la-leave-card__status.is-pending iconify-icon {
  color: #f59e0b;
}

.la-leave-card__status.is-approved {
  background: #ecfdf5;
  color: #15803d;
}

.la-leave-card__status.is-approved iconify-icon {
  color: #16a34a;
}

.la-leave-card__status.is-rejected {
  background: #fef2f2;
  color: #dc2626;
}

.la-leave-card__status.is-rejected iconify-icon {
  color: #ef4444;
}

.la-leave-card__body {
  display: grid;
  gap: 14px;
}

.la-leave-card__row {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.la-leave-card__icon-box {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: #f3e8ff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.la-leave-card__icon-box iconify-icon {
  font-size: 18px;
  color: #7c3aed;
}

.la-leave-card__row-text {
  min-width: 0;
  flex: 1;
  padding-top: 1px;
}

.la-leave-card__label {
  display: block;
  font-size: 11px;
  font-weight: 500;
  color: #94a3b8;
  margin-bottom: 3px;
}

.la-leave-card__value {
  margin: 0;
  font-size: 13px;
  font-weight: 700;
  color: #0b0736;
  line-height: 1.35;
  word-break: break-word;
}

.la-leave-card__actions {
  display: flex;
  gap: 8px;
  align-items: stretch;
  padding-top: 4px;
}

.la-leave-card__btn {
  flex: 1;
  min-height: 40px;
  border-radius: 999px;
  border: 1px solid transparent;
  font-size: 12px;
  font-weight: 600;
  color: #0b0736;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  cursor: pointer;
  padding: 0 14px;
  background: #fff;
  transition: opacity 0.15s ease;
}

.la-leave-card__btn:hover {
  opacity: 0.88;
}

.la-leave-card__btn iconify-icon {
  font-size: 16px;
}

.la-leave-card__btn--approve {
  background: #ecfdf5;
  border-color: #86efac;
  color: #15803d;
}

.la-leave-card__btn--approve iconify-icon {
  color: #16a34a;
}

.la-leave-card__btn--reject {
  background: #fef2f2;
  border-color: #fecaca;
  color: #dc2626;
}

.la-leave-card__btn--reject iconify-icon {
  color: #ef4444;
}

.la-leave-card__btn--view {
  background: #f8fafc;
  border-color: #e2e8f0;
  color: #0b0736;
}

.la-leave-card__btn--view iconify-icon {
  color: #f59e0b;
}

.la-leave-card__btn--full {
  flex: 1;
  width: 100%;
}
</style>
