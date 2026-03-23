<template>
  <div class="lr-kpis">
    <button v-for="card in cards" :key="card.key" class="lr-kpi" @click="$emit('selectStage', card)">
      <div class="lr-kpi-num">{{ card.value }}</div>
      <div class="lr-kpi-title">{{ card.title }}</div>
      <div class="lr-kpi-trend" :class="{ up: card.positive, down: !card.positive }">
        <iconify-icon :icon="card.positive ? 'lucide:trending-up' : 'lucide:trending-down'" />
        {{ card.delta }} <span>{{ card.trend }}</span>
      </div>
      <div class="lr-kpi-icon"><iconify-icon :icon="card.icon" /></div>
    </button>
  </div>
</template>

<script setup>
defineProps({ cards: { type: Array, default: () => [] } })
defineEmits(['selectStage'])
</script>

<style scoped>
.lr-kpis { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin: 0 16px 14px; }
.lr-kpi { position: relative; text-align: left; border: 1px solid #ebeef3; background: #fff; border-radius: 12px; padding: 16px 14px; }
.lr-kpi-num { font-size: 34px; line-height: 1; font-weight: 700; color: #10152f; }
.lr-kpi-title { margin-top: 8px; font-size: 16px; color: #10152f; }
.lr-kpi-trend { margin-top: 8px; font-size: 12px; display: flex; gap: 4px; align-items: center; }
.lr-kpi-trend span { color: #9ca2ae; }
.lr-kpi-trend.up { color: #27ae60; }
.lr-kpi-trend.down { color: #d94f4f; }
.lr-kpi-icon { position: absolute; right: 12px; top: 12px; width: 34px; height: 34px; border-radius: 50%; background: #f3f6ff; color: #3b68ff; display: flex; align-items: center; justify-content: center; }
@media (max-width: 1320px) { .lr-kpis { grid-template-columns: repeat(3, 1fr); } }
</style>
