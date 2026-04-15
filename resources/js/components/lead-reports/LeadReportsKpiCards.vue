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
.lr-kpis { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin: 0 16px 12px; }
.lr-kpi { position: relative; text-align: left; border: 1px solid #ebeef3; background: #fff; border-radius: 10px; padding: 12px 11px; min-height: 112px; }
.lr-kpi-num { font-size: 27px; line-height: 1; font-weight: 700; color: #10152f; }
.lr-kpi-title { margin-top: 6px; font-size: 13px; color: #10152f; }
.lr-kpi-trend { margin-top: 6px; font-size: 11px; display: flex; gap: 4px; align-items: center; }
.lr-kpi-trend span { color: #9ca2ae; }
.lr-kpi-trend.up { color: #27ae60; }
.lr-kpi-trend.down { color: #d94f4f; }
.lr-kpi-icon { position: absolute; right: 10px; top: 10px; width: 30px; height: 30px; border-radius: 50%; background: #f3f6ff; color: #3b68ff; display: flex; align-items: center; justify-content: center; }
@media (max-width: 1320px) { .lr-kpis { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 768px) {
  .lr-kpis { grid-template-columns: repeat(2, 1fr); margin: 0 10px 10px; gap: 7px; }
  .lr-kpi { min-height: 95px; padding: 10px 9px; }
  .lr-kpi-num { font-size: 22px; }
  .lr-kpi-title { margin-top: 5px; font-size: 12px; }
  .lr-kpi-trend { margin-top: 5px; font-size: 10px; }
  .lr-kpi-icon { width: 26px; height: 26px; right: 8px; top: 8px; }
}
</style>
