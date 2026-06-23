<template>
  <article class="emp-card la-record-card">
    <div class="emp-card__head">
      <img :src="record.avatar" :alt="record.name" class="emp-card__avatar" loading="lazy" />
      <div class="emp-card__meta">
        <h3 class="emp-card__name">{{ record.name }}</h3>
        <p class="emp-card__code">{{ record.department }}</p>
      </div>
      <span class="emp-card__badge" :class="statusClass">{{ record.status }}</span>
    </div>
    <div class="la-record-card__times">
      <div><label>In</label><strong>{{ formatTime(record.checkIn) }}</strong></div>
      <div><label>Out</label><strong>{{ formatTime(record.checkOut) }}</strong></div>
      <div><label>Hours</label><strong>{{ record.workingHours }}</strong></div>
      <div><label>OT</label><strong>{{ record.overtimeHours }}</strong></div>
    </div>
    <div class="emp-card__actions" @click.stop>
      <button type="button" class="emp-card__action" @click="$emit('edit', record.raw || record)">
        <iconify-icon icon="lucide:pencil" /> Edit
      </button>
      <button type="button" class="emp-card__action" @click="$emit('history', record.raw || record)">
        <iconify-icon icon="lucide:history" /> History
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

const statusClass = computed(() => {
  const s = props.record.status
  if (s === 'present') return 'emp-card__badge--active'
  if (s === 'late') return 'emp-card__badge--on_leave'
  return 'emp-card__badge--inactive'
})

function formatTime(t) {
  if (!t) return '—'
  const s = String(t)
  const m = s.match(/(\d{1,2}:\d{2})/)
  return m ? m[1] : s.slice(0, 5)
}
</script>

<style scoped>
.la-record-card__times {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
  padding: 8px 10px;
  background: #f8fafc;
  border-radius: 10px;
  border: 1px solid #eef2f7;
}
.la-record-card__times label {
  display: block;
  font-size: 9px;
  font-weight: 600;
  color: #94a3b8;
  text-transform: uppercase;
  margin-bottom: 2px;
}
.la-record-card__times strong {
  font-size: 12px;
  color: #0b0736;
}
</style>
