<template>
  <article class="la-attendance-row" :class="`la-attendance-row--${record.status}`">
    <div class="la-attendance-row__employee">
      <img
        :src="record.avatar"
        :alt="record.name"
        class="la-attendance-row__avatar"
        loading="lazy"
        @error="onAvatarError"
      />
      <div class="la-attendance-row__identity">
        <p class="la-attendance-row__name">{{ record.name }}</p>
        <span class="la-attendance-row__meta">{{ record.department }}</span>
      </div>
    </div>

    <span class="la-attendance-row__cell la-attendance-row__dept">{{ record.department }}</span>

    <span class="la-attendance-row__cell">
      <span class="la-attendance-row__status" :class="statusClass">{{ statusLabel }}</span>
    </span>

    <span class="la-attendance-row__cell la-attendance-row__time">{{ formatTime(record.checkIn) }}</span>
    <span class="la-attendance-row__cell la-attendance-row__time">{{ formatTime(record.checkOut) }}</span>
    <span class="la-attendance-row__cell la-attendance-row__num">{{ record.workingHours || '—' }}</span>
    <span class="la-attendance-row__cell la-attendance-row__num">{{ record.overtimeHours || '—' }}</span>

    <div class="la-attendance-row__times-mobile">
      <span><em>In</em> {{ formatTime(record.checkIn) }}</span>
      <span><em>Out</em> {{ formatTime(record.checkOut) }}</span>
      <span><em>Hrs</em> {{ record.workingHours || '—' }}</span>
      <span><em>OT</em> {{ record.overtimeHours || '—' }}</span>
    </div>

    <div class="la-attendance-row__actions" @click.stop>
      <button type="button" class="la-attendance-row__action" title="Edit" @click="$emit('edit', record.raw || record)">
        <iconify-icon icon="lucide:pencil" />
      </button>
      <button type="button" class="la-attendance-row__action" title="History" @click="$emit('history', record.raw || record)">
        <iconify-icon icon="lucide:history" />
      </button>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  record: { type: Object, required: true },
})

defineEmits(['edit', 'history'])

const statusLabel = computed(() => {
  const map = { present: 'Present', late: 'Late', absent: 'Absent', on_leave: 'On Leave' }
  return map[props.record.status] || props.record.status || '—'
})

const statusClass = computed(() => {
  const s = props.record.status
  if (s === 'present') return 'is-present'
  if (s === 'late') return 'is-late'
  if (s === 'absent') return 'is-absent'
  return 'is-other'
})

function formatTime(t) {
  if (!t) return '—'
  const s = String(t)
  const m = s.match(/(\d{1,2}:\d{2})/)
  return m ? m[1] : s.slice(0, 5)
}

function onAvatarError(event) {
  const el = event?.target
  if (!el || el.dataset.avatarFallback === '1') return
  el.dataset.avatarFallback = '1'
  const name = el.alt || 'E'
  el.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=733e87&color=fff`
}
</script>
