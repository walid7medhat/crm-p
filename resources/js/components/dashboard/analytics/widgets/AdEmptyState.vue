<template>
  <div class="ex-empty">
    <div class="ex-empty__icon">
      <iconify-icon :icon="icon" width="22" height="22" />
    </div>
    <p class="ex-empty__title">{{ title }}</p>
    <p v-if="description" class="ex-empty__sub">{{ description }}</p>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, default: 'No data yet' },
  description: { type: String, default: '' },
  icon: { type: String, default: 'lucide:bar-chart-2' },
})
</script>

<style scoped>
.ex-empty__sub { font-size: 11px; color: var(--ex-muted); margin: 0; }
</style>
