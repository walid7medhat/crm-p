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
  gap: 16px;
}
.stat-card {
  background: #ffffff;
  border: 1.5px solid #c8eaf8;
  border-radius: 14px;
  padding: 20px 22px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 108px;
  box-shadow:
    0 0 0 1px rgba(125, 211, 252, 0.28),
    0 10px 24px rgba(32, 19, 68, 0.08);
}
.stat-value {
  margin: 0;
  font-size: 32px;
  line-height: 1;
  font-weight: 700;
  letter-spacing: -0.04em;
  color: #111827;
}
.stat-label {
  margin: 8px 0 0;
  font-size: 13px;
  font-weight: 500;
  color: #6b7280;
}
.stat-icon {
  width: 52px;
  height: 52px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  flex-shrink: 0;
}

@media (max-width: 1100px) {
  .stats-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
  .stat-card {
    min-height: 92px;
    padding: 16px 18px;
  }
  .stat-value {
    font-size: 26px;
  }
}
</style>
