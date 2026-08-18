<template>
  <section class="ap-score-panel">
    <div class="ap-score-panel__ring-wrap">
      <svg class="ap-score-ring" viewBox="0 0 120 120" aria-hidden="true">
        <circle class="ap-score-ring__track" cx="60" cy="60" r="52" />
        <circle
          class="ap-score-ring__progress"
          :class="`ap-score-ring__progress--${level.tone}`"
          cx="60"
          cy="60"
          r="52"
          :stroke-dasharray="circumference"
          :stroke-dashoffset="dashOffset"
        />
      </svg>
      <div class="ap-score-panel__center">
        <span class="ap-score-panel__number">{{ displayScore }}</span>
        <span class="ap-score-panel__of">/ 100</span>
      </div>
    </div>

    <div class="ap-score-panel__info">
      <p class="ap-score-panel__eyebrow">Average Lead Score</p>
      <h2 class="ap-score-panel__level" :class="`ap-score-panel__level--${level.tone}`">
        {{ level.label }}
      </h2>
      <p class="ap-score-panel__desc">
        <template v-if="agentName">
          Lead quality score for <strong>{{ agentName }}</strong>, based on the intelligence scoring of converted leads.
        </template>
        <template v-else>
          Team-wide average lead quality score across all agents with converted deals in this period.
        </template>
      </p>
      <div v-if="agentName" class="ap-score-panel__meta">
        <span><iconify-icon icon="lucide:handshake" width="13" height="13" /> {{ agentDeals }} deals</span>
        <span><iconify-icon icon="lucide:coins" width="13" height="13" /> {{ agentCommission }}</span>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  score: { type: [Number, null], default: null },
  agentName: { type: String, default: '' },
  agentDeals: { type: [Number, String], default: 0 },
  agentCommission: { type: String, default: '' },
  scoreLevel: { type: Function, required: true },
})

const circumference = 2 * Math.PI * 52

const level = computed(() => props.scoreLevel(props.score))

const displayScore = computed(() => (props.score != null ? props.score : '—'))

const dashOffset = computed(() => {
  if (props.score == null) return circumference
  const pct = Math.min(100, Math.max(0, props.score)) / 100
  return circumference * (1 - pct)
})
</script>
