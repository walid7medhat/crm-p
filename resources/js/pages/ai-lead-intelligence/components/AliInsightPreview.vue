<template>
  <section class="ali-insight ali-card" aria-labelledby="ali-insight-title">
    <header class="ali-insight__head">
      <span class="ali-badge">✨ AI Insight</span>
      <span class="ali-insight__tag">Analysis preview</span>
    </header>
    <h2 id="ali-insight-title" class="sr-only">AI Insight Preview</h2>
    <p class="ali-insight__text">
      <template v-if="text">{{ text }}</template>
      <template v-else>CRM intelligence summary will appear here after Refresh Analysis completes. Phase 4 will add AI-generated conclusions.</template>
    </p>
    <p v-if="disclaimer" class="ali-insight__disclaimer">{{ disclaimer }}</p>
  </section>
</template>

<script setup>
defineProps({
  status: { type: String, default: 'preview' },
  text: { type: String, default: null },
  disclaimer: { type: String, default: '' },
})
</script>
