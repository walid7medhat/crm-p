<template>
  <section class="ap-ranking">
    <header class="ap-ranking__head">
      <div>
        <h3 class="ap-section-title">Agent Ranking</h3>
        <p class="ap-section-sub">Sorted by total commission</p>
      </div>
      <span class="ap-ranking__badge">{{ agents.length }} agents</span>
    </header>

    <ul v-if="agents.length" class="ap-ranking__list">
      <li
        v-for="(agent, idx) in agents"
        :key="agent.agent_id"
        class="ap-ranking__item"
        :class="{ 'ap-ranking__item--top': idx === 0, 'ap-ranking__item--podium': idx < 3 }"
      >
        <span class="ap-ranking__rank">
          <iconify-icon v-if="idx === 0" icon="lucide:crown" width="14" height="14" />
          <template v-else>{{ idx + 1 }}</template>
        </span>

        <div class="ap-ranking__avatar">{{ initials(agent.agent_name) }}</div>

        <div class="ap-ranking__info">
          <p class="ap-ranking__name">{{ agent.agent_name }}</p>
          <p v-if="agent.agent_email" class="ap-ranking__email">{{ agent.agent_email }}</p>
        </div>

        <div class="ap-ranking__metrics">
          <div class="ap-ranking__metric">
            <span class="ap-ranking__metric-label">Deals</span>
            <span class="ap-ranking__metric-value">{{ agent.converted_count }}</span>
          </div>
          <div class="ap-ranking__metric">
            <span class="ap-ranking__metric-label">Avg Score</span>
            <span class="ap-ranking__metric-value">
              <span class="score-pill" :class="scoreClass(agent.avg_lead_score)">
                {{ agent.avg_lead_score ?? '—' }}
              </span>
            </span>
          </div>
          <div class="ap-ranking__metric ap-ranking__metric--hide-mobile">
            <span class="ap-ranking__metric-label">Deal Value</span>
            <span class="ap-ranking__metric-value">{{ formatMoney(agent.total_amount) }}</span>
          </div>
          <div class="ap-ranking__metric ap-ranking__metric--accent">
            <span class="ap-ranking__metric-label">Commission</span>
            <span class="ap-ranking__metric-value">{{ formatMoney(agent.total_commission) }}</span>
          </div>
        </div>
      </li>
    </ul>
  </section>
</template>

<script setup>
defineProps({
  agents: { type: Array, default: () => [] },
  formatMoney: { type: Function, required: true },
  scoreClass: { type: Function, required: true },
})

function initials(name = '') {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase())
    .join('') || '?'
}
</script>
