<template>
  <article class="dh-panel dh-panel--heatmap">
    <div class="dh-panel-head">
      <p class="dh-panel-title">Listing Performance Heatmap</p>
      <span class="dh-chart-period dh-chart-period--static">By views</span>
    </div>
    <div v-if="loading" class="dh-heatmap-grid dh-skeleton" style="min-height: 140px" />
    <div v-else-if="items?.length" class="dh-heatmap-grid">
      <div
        v-for="(item, idx) in items"
        :key="item.id || idx"
        class="dh-heatmap-cell"
        :style="{ '--heat': heatLevel(item.views) }"
      >
        <span class="dh-heatmap-title">{{ item.title }}</span>
        <strong>{{ item.views }} views</strong>
        <span class="dh-heatmap-status">{{ item.status }}</span>
      </div>
    </div>
    <div v-else class="dh-empty dh-empty--compact">
      <p class="dh-empty-text">No listing activity in this period.</p>
    </div>
  </article>
</template>

<script setup>
const props = defineProps({
  items: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

function heatLevel(views) {
  const max = Math.max(...(props.items || []).map((i) => Number(i.views) || 0), 1)
  return Math.max(0.15, (Number(views) || 0) / max)
}
</script>
