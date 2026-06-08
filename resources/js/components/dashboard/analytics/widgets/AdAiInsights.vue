<template>
  <AdPanel title="AI Insights">
    <ul class="ex-insight-list">
      <li
        v-for="(item, idx) in insights"
        :key="idx"
        class="ex-insight"
        :class="`ex-insight--${item.tone || 'neutral'}`"
      >
        <iconify-icon :icon="iconFor(item.tone)" width="14" height="14" />
        <span>{{ item.text }}</span>
      </li>
      <li v-if="!insights?.length" class="ex-insight ex-insight--neutral">
        <iconify-icon icon="lucide:sparkles" width="14" height="14" />
        <span>Insights will appear when data is available.</span>
      </li>
    </ul>
  </AdPanel>
</template>

<script setup>
import AdPanel from './AdPanel.vue'

defineProps({
  insights: { type: Array, default: () => [] },
})

function iconFor(tone) {
  if (tone === 'positive') return 'lucide:check-circle'
  if (tone === 'warning') return 'lucide:alert-triangle'
  return 'lucide:info'
}
</script>
