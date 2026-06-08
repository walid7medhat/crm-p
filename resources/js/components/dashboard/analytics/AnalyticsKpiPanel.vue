<template>
  <article class="dh-panel dh-panel--kpi-full" :class="{ 'dh-panel--kpi-compact': compact }">
    <div class="dh-panel-head">
      <p class="dh-panel-title">{{ title }}</p>
    </div>
    <div v-if="loading" class="dh-analytics-kpi-grid" :class="{ 'dh-analytics-kpi-grid--compact': compact }">
      <div v-for="i in skeletonCount" :key="i" class="dh-status-cell">
        <div class="dh-skeleton" style="height: 10px; width: 70%" />
        <div class="dh-skeleton mt-1" style="height: 16px; width: 45%" />
      </div>
    </div>
    <div v-else class="dh-analytics-kpi-grid" :class="{ 'dh-analytics-kpi-grid--compact': compact }">
      <div v-for="kpi in kpis" :key="kpi.label" class="dh-status-cell">
        <span>{{ kpi.label }}</span>
        <strong>{{ kpi.value }}</strong>
      </div>
    </div>
  </article>
</template>

<script setup>
defineProps({
  title: { type: String, default: 'All Metrics' },
  kpis: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  skeletonCount: { type: Number, default: 12 },
  compact: { type: Boolean, default: false },
})
</script>
