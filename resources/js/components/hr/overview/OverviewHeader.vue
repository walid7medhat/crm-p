<template>
  <div class="overview-header">
    <h6 class="overview-title">Overview</h6>
    <div class="overview-filters">
      <button
        type="button"
        class="overview-pill"
        :class="{ active: selectedKey === 'all' }"
        @click="$emit('select', 'all')"
      >
        All Employees
      </button>
      <button
        v-for="employee in quickEmployees"
        :key="employee.id"
        type="button"
        class="overview-pill"
        :class="{ active: selectedKey === String(employee.id) }"
        @click="$emit('select', String(employee.id))"
      >
        {{ employee.name }}
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  quickEmployees: {
    type: Array,
    default: () => [],
  },
  selectedKey: {
    type: String,
    default: 'all',
  },
})

defineEmits(['select'])
</script>

<style scoped>
.overview-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.overview-title {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
  color: #111827;
}
.overview-filters {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.overview-pill {
  border: 1px solid #e5e7eb;
  background: #ffffff;
  color: #4b5563;
  border-radius: 999px;
  padding: 5px 10px;
  font-size: 11px;
  font-weight: 500;
  transition: all 0.2s ease;
}
.overview-pill.active {
  border-color: #facc15;
  color: #111827;
  background: #fffbeb;
}
</style>
