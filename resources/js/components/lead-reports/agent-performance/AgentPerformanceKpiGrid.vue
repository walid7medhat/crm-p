<template>
  <section class="ap-kpi-grid">
    <article v-for="card in cards" :key="card.key" class="ap-kpi-card" :class="card.accent ? 'ap-kpi-card--accent' : ''">
      <div class="ap-kpi-card__icon" :class="`ap-kpi-card__icon--${card.tone}`">
        <iconify-icon :icon="card.icon" width="18" height="18" />
      </div>
      <p class="ap-kpi-card__label">{{ card.label }}</p>
      <p class="ap-kpi-card__value">{{ card.value }}</p>
      <p v-if="card.sub" class="ap-kpi-card__sub">{{ card.sub }}</p>
    </article>
  </section>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  summary: { type: Object, default: null },
  avgLeadScore: { type: [Number, null], default: null },
  avgDealValue: { type: [Number, null], default: null },
  avgCommission: { type: [Number, null], default: null },
  formatMoney: { type: Function, required: true },
})

const cards = computed(() => [
  {
    key: 'agents',
    label: 'Active Agents',
    value: props.summary?.agents_count ?? '—',
    sub: 'With converted deals',
    icon: 'lucide:users',
    tone: 'blue',
  },
  {
    key: 'deals',
    label: 'Converted Deals',
    value: props.summary?.deals_count ?? '—',
    sub: 'In selected period',
    icon: 'lucide:handshake',
    tone: 'green',
  },
  {
    key: 'value',
    label: 'Total Deal Value',
    value: props.formatMoney(props.summary?.total_amount),
    sub: props.avgDealValue != null ? `Avg ${props.formatMoney(props.avgDealValue)} / deal` : '',
    icon: 'lucide:building-2',
    tone: 'navy',
  },
  {
    key: 'commission',
    label: 'Total Commission',
    value: props.formatMoney(props.summary?.total_commission),
    sub: props.avgCommission != null ? `Avg ${props.formatMoney(props.avgCommission)} / deal` : '',
    icon: 'lucide:coins',
    tone: 'gold',
    accent: true,
  },
  {
    key: 'score',
    label: 'Avg Lead Score',
    value: props.avgLeadScore != null ? props.avgLeadScore : '—',
    sub: 'Lead intelligence score (0–100)',
    icon: 'lucide:target',
    tone: 'purple',
  },
])
</script>
