<template>
  <section class="ap-breakdown">
    <header class="ap-breakdown__head">
      <h3 class="ap-section-title">Performance Breakdown</h3>
      <p v-if="agentName" class="ap-section-sub">Metrics for {{ agentName }}</p>
      <p v-else class="ap-section-sub">Top performer metrics (by commission)</p>
    </header>
    <ul class="ap-breakdown__list">
      <li v-for="item in items" :key="item.key" class="ap-breakdown__item">
        <div class="ap-breakdown__row">
          <span class="ap-breakdown__icon">
            <iconify-icon :icon="item.icon" width="15" height="15" />
          </span>
          <div class="ap-breakdown__text">
            <span class="ap-breakdown__label">{{ item.label }}</span>
            <span class="ap-breakdown__value">{{ item.display }}</span>
          </div>
          <span class="ap-breakdown__pct">{{ item.percent }}%</span>
        </div>
        <div class="ap-breakdown__bar">
          <div class="ap-breakdown__fill" :style="{ width: `${item.percent}%` }" />
        </div>
      </li>
    </ul>
  </section>
</template>

<script setup>
defineProps({
  items: { type: Array, default: () => [] },
  agentName: { type: String, default: '' },
})
</script>
