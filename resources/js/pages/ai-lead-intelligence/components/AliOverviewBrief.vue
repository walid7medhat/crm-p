<template>
  <section class="ali-brief ali-card">
    <header class="ali-brief__head">
      <span class="ali-badge">CRM Intelligence Brief</span>
      <p class="ali-brief__summary">
        <template v-if="summaryStatus === 'ready' && summary">{{ summary }}</template>
        <template v-else>Run Refresh Analysis to build a deterministic CRM intelligence brief from your leads.</template>
      </p>
    </header>
    <div class="ali-brief__cards">
      <article v-for="card in cards" :key="card.key" class="ali-metric" :class="`ali-metric--${card.icon || card.key}`">
        <div class="ali-metric__icon" aria-hidden="true"><Icon :icon="iconFor(card)" /></div>
        <div class="ali-metric__body">
          <span class="ali-metric__label">{{ card.label }}</span>
          <strong class="ali-metric__value">
            <template v-if="card.status === 'ready' && card.count != null">{{ card.count }}</template>
            <template v-else>—</template>
          </strong>
          <span class="ali-metric__hint">
            <template v-if="card.status === 'ready'">Active signals</template>
            <template v-else>Analysis not available yet</template>
          </span>
        </div>
      </article>
    </div>
  </section>
</template>

<script setup>
import { Icon } from '@iconify/vue'
defineProps({
  summary: { type: String, default: null },
  summaryStatus: { type: String, default: 'not_available' },
  cards: { type: Array, default: () => [] },
})
function iconFor(card) {
  const map = {
    priority: 'lucide:flame', high_priority: 'lucide:flame',
    risk: 'lucide:alert-triangle', at_risk: 'lucide:alert-triangle',
    property: 'lucide:home', property_opportunities: 'lucide:home',
    neglected: 'lucide:clock',
  }
  return map[card.icon] || map[card.key] || 'lucide:sparkles'
}
</script>
