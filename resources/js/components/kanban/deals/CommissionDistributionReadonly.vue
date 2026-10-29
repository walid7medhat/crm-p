<template>
  <div v-if="hasData" class="df-ro">
    <div class="df-ro__kpis">
      <div class="df-ro__kpi">
        <span class="df-ro__label">Gross</span>
        <strong>{{ formatMoney(dist.gross_amount) }} <small>AED</small></strong>
      </div>
      <div v-if="agencyEnabled" class="df-ro__kpi df-ro__kpi--agency">
        <span class="df-ro__label">Agency</span>
        <strong>{{ formatMoney(dist.external_agency?.amount) }} <small>AED</small></strong>
        <em>{{ dist.external_agency?.name || 'External' }} · {{ Number(dist.external_agency?.split_percent || 0) }}%</em>
      </div>
      <div class="df-ro__kpi df-ro__kpi--oia">
        <span class="df-ro__label">OIA retained</span>
        <strong>{{ formatMoney(dist.oia_retained_amount) }} <small>AED</small></strong>
      </div>
    </div>

    <div v-if="agents.length" class="df-ro__agents">
      <div
        v-for="(agent, idx) in agents"
        :key="'ra-' + idx"
        class="df-ro__agent"
      >
        <span class="df-ro__avatar">{{ initial(agent) }}</span>
        <div class="df-ro__agent-meta">
          <strong>{{ agent.name || ('Agent #' + (agent.user_id || idx + 1)) }}</strong>
          <span>{{ Number(agent.split_percent || 0) }}% · {{ formatMoney(agent.amount) }} AED</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  distribution: { type: Object, default: null },
})

const dist = computed(() => props.distribution || {})
const agencyEnabled = computed(() => !!dist.value.external_agency?.enabled)
const agents = computed(() => Array.isArray(dist.value.agents) ? dist.value.agents : [])
const hasData = computed(() => {
  if (!props.distribution || typeof props.distribution !== 'object') return false
  return agencyEnabled.value || agents.value.length > 0 || Number(dist.value.gross_amount) > 0
})

function formatMoney(n) {
  const v = Math.round((Number(n) || 0) * 100) / 100
  return v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function initial(agent) {
  return String(agent?.name || '?').trim().charAt(0).toUpperCase() || '?'
}
</script>

<style scoped>
.df-ro {
  margin-top: 0.85rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.df-ro__kpis {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.55rem;
}

.df-ro__kpi {
  border: 1px solid var(--color-primary-border);
  border-radius: 12px;
  padding: 0.7rem 0.85rem;
  background: var(--color-primary-soft);
}

.df-ro__kpi--agency {
  background: rgba(var(--color-primary-rgb), 0.06);
  border-color: var(--color-primary-muted);
}

.df-ro__kpi--oia {
  background: var(--color-primary-soft);
  border-color: var(--color-primary-border);
}

.df-ro__label {
  display: block;
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-secondary);
  margin-bottom: 0.2rem;
}

.df-ro__kpi strong {
  font-size: 0.95rem;
  color: var(--color-text-primary);
}

.df-ro__kpi small {
  font-size: 0.7rem;
  color: var(--color-text-muted);
  font-weight: 650;
}

.df-ro__kpi em {
  display: block;
  margin-top: 0.15rem;
  font-style: normal;
  font-size: 0.72rem;
  color: var(--color-text-secondary);
}

.df-ro__agents {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.5rem;
}

.df-ro__agent {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  border: 1px solid var(--color-primary-border);
  border-radius: 12px;
  padding: 0.55rem 0.7rem;
  background: var(--color-surface);
}

.df-ro__avatar {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  background: var(--gradient-primary);
  color: var(--color-on-primary);
  font-size: 0.72rem;
  font-weight: 700;
  flex-shrink: 0;
}

.df-ro__agent-meta {
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.df-ro__agent-meta strong {
  font-size: 0.82rem;
  color: var(--color-text-primary);
  line-height: 1.2;
}

.df-ro__agent-meta span {
  font-size: 0.72rem;
  color: var(--color-text-secondary);
}

@media (max-width: 768px) {
  .df-ro__kpis,
  .df-ro__agents {
    grid-template-columns: 1fr;
  }
}
</style>
