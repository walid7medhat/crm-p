<template>
  <div class="stats-grid">
    <article v-for="stat in stats" :key="stat.key" class="stat-card">
      <div>
        <p class="stat-value">{{ stat.value }}</p>
        <p class="stat-label">{{ stat.label }}</p>
      </div>
      <span class="stat-icon" :style="{ background: stat.bgColor, color: stat.iconColor }">
        <iconify-icon :icon="stat.icon" />
      </span>
    </article>
  </div>
</template>

<script setup>
defineProps({
  stats: {
    type: Array,
    default: () => [],
  },
})
</script>

<style scoped>
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
}
.stat-card {
  background: #ffffff;
  border: 1px solid #eef2f7;
  border-radius: 10px;
  padding: 8px 10px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 72px;
}
.stat-value {
  margin: 0;
  font-size: 21px;
  line-height: 1;
  font-weight: 700;
  color: #111827;
}
.stat-label {
  margin: 4px 0 0;
  font-size: 12px;
  font-weight: 500;
  color: #111827;
}
.stat-icon {
  width: 38px;
  height: 38px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
}

@media (max-width: 1024px) {
  .stats-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
