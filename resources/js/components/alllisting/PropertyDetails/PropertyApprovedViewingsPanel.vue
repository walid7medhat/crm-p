<template>
  <div class="approved-viewings-panel" :class="panelClasses">
    <div class="approved-viewings-panel__head">
      <div class="approved-viewings-panel__title-row">
        <i class="ri-calendar-check-line"></i>
        <span class="approved-viewings-panel__title">Viewings</span>
        <span v-if="viewings.length" class="approved-viewings-panel__count">{{ viewings.length }}</span>
      </div>
      <p class="approved-viewings-panel__hint">Scheduled visits for this property</p>
    </div>

    <div v-if="loading" class="approved-viewings-panel__state">
      <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
    </div>

    <div v-else-if="!viewings.length" class="approved-viewings-panel__state approved-viewings-panel__state--empty">
      <i class="ri-calendar-line"></i>
      <span>No viewings yet</span>
    </div>

    <ul v-else class="approved-viewings-panel__list">
      <li
        v-for="viewing in viewings"
        :key="viewing.id"
        class="approved-viewings-panel__item"
      >
        <img
          :src="viewing.requested_by?.avatar || defaultAvatar"
          class="approved-viewings-panel__avatar"
          alt=""
        >
        <div class="approved-viewings-panel__body">
          <span class="approved-viewings-panel__name">{{ viewing.requested_by?.name || 'Agent' }}</span>
          <span class="approved-viewings-panel__time">
            <i class="ri-time-line"></i>
            {{ displayWhen(viewing) }}
          </span>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  viewings: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  mobile: { type: Boolean, default: false },
  underAgent: { type: Boolean, default: false },
})

const panelClasses = computed(() => ({
  'approved-viewings-panel--mobile': props.mobile,
  'approved-viewings-panel--under-agent': props.underAgent,
}))

const defaultAvatar = 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'

function displayWhen(viewing) {
  const date = viewing.formatted_date
  const time = viewing.formatted_time
  if (date && time) return `${date} · ${time}`
  if (date) return date
  if (time) return time
  return 'Date not set'
}
</script>

<style scoped>
.approved-viewings-panel {
  background: #fff;
  border: 1px solid #e8edf3;
  border-radius: 12px;
  padding: 10px 12px;
  margin-top: 0;
}

.approved-viewings-panel--mobile {
  margin-bottom: 12px;
}

.approved-viewings-panel--under-agent {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.approved-viewings-panel__head {
  margin-bottom: 8px;
}

.approved-viewings-panel__title-row {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #0b0736;
  font-size: 12px;
  font-weight: 600;
}

.approved-viewings-panel__title-row i {
  font-size: 14px;
  color: #733e87;
}

.approved-viewings-panel__count {
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  border-radius: 999px;
  background: #733e87;
  color: #fff;
  font-size: 9px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.approved-viewings-panel__hint {
  margin: 2px 0 0;
  font-size: 9px;
  color: #94a3b8;
  line-height: 1.3;
}

.approved-viewings-panel__state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 12px 6px;
  color: #94a3b8;
  font-size: 11px;
}

.approved-viewings-panel__state--empty {
  flex-direction: column;
  gap: 4px;
}

.approved-viewings-panel__state--empty i {
  font-size: 18px;
  opacity: 0.6;
}

.approved-viewings-panel__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-height: 132px;
  overflow-y: auto;
  padding-right: 2px;
}

.approved-viewings-panel__list::-webkit-scrollbar {
  width: 3px;
}

.approved-viewings-panel__list::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}

.approved-viewings-panel__item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 8px;
  border-radius: 8px;
  background: #f8fafc;
  border: 1px solid #eef2f7;
}

.approved-viewings-panel__avatar {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
  border: 1px solid #e2e8f0;
}

.approved-viewings-panel__body {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.approved-viewings-panel__name {
  font-size: 11px;
  font-weight: 600;
  color: #1e293b;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.approved-viewings-panel__time {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 10px;
  color: #64748b;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.approved-viewings-panel__time i {
  font-size: 10px;
  color: #733e87;
  flex-shrink: 0;
}
</style>
