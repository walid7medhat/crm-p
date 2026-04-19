<template>
  <section class="oc">
    <div class="oc__grid">
      <article
        v-for="card in cards"
        :key="card.key"
        class="oc-card"
        :class="{ 'oc-card--pulse': !loading }"
      >
        <div class="oc-card__top">
          <span class="oc-card__label">{{ card.label }}</span>
          <span class="oc-card__hint">{{ card.hint }}</span>
        </div>
        <div class="oc-card__value">
          <span class="oc-card__num">{{ displayValue(card) }}</span>
          <span v-if="card.suffix" class="oc-card__suffix">{{ card.suffix }}</span>
        </div>
        <div class="oc-card__spark">
          <ApexChart
            type="area"
            height="48"
            :options="sparkOptions(card)"
            :series="sparkSeries(card)"
          />
        </div>
      </article>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import ApexChart from 'vue3-apexcharts'

const props = defineProps({
  overview: { type: Object, default: () => ({}) },
  distributionsToday: { type: Number, default: 0 },
  loading: Boolean,
})

const animated = ref({})

function sparkFromValue(v) {
  const n = Number(v)
  if (Number.isNaN(n)) return [12, 14, 13, 15, 14]
  const base = Math.max(n * 0.88, 1)
  return [base, base * 0.95, base * 1.02, base * 0.97, n]
}

const cards = computed(() => {
  const o = props.overview || {}
  const agents = Number(o.agents_tracked) || 0
  const hot = Number(o.tiers?.hot) || 0
  const warm = Number(o.tiers?.warm) || 0
  const cold = Number(o.tiers?.cold) || 0
  const qualifyPct = agents > 0 ? Math.round(((hot + warm) / agents) * 1000) / 10 : null

  return [
    { key: 'agents', label: 'Total agents', hint: 'Tracked', value: agents, sparkKey: 'agents' },
    { key: 'avg', label: 'Average score', hint: 'Latest run', value: o.avg_score, suffix: '/100', sparkKey: 'avg' },
    { key: 'hot', label: 'Hot agents', hint: 'Score ≥ 80', value: hot, sparkKey: 'hot' },
    { key: 'dist', label: 'Distributions today', hint: 'From activity log', value: props.distributionsToday, sparkKey: 'dist' },
    {
      key: 'bench',
      label: 'Bench qualify',
      hint: '(Hot + warm) / team',
      value: qualifyPct,
      suffix: '%',
      sparkKey: 'bench',
    },
  ]
})

function displayValue(card) {
  if (props.loading) return '—'
  const v = card.value
  if (v === null || v === undefined || Number.isNaN(v)) return '—'
  const anim = animated.value[card.key]
  if (anim != null && typeof anim === 'number') return formatNum(anim, card.suffix === '%')
  return formatNum(v, card.suffix === '%')
}

function formatNum(v, isPct) {
  if (isPct) return String(Math.round(Number(v) * 10) / 10)
  if (Number.isInteger(Number(v))) return String(v)
  return Number(v).toFixed(1)
}

watch(
  () => [props.overview, props.distributionsToday, props.loading],
  () => {
    if (props.loading) return
    cards.value.forEach((card) => {
      const target = Number(card.value)
      if (Number.isNaN(target)) {
        animated.value[card.key] = null
        return
      }
      const from = animated.value[card.key] ?? target * 0.6
      animateTo(card.key, from, target, 520)
    })
  },
  { deep: true }
)

function animateTo(key, from, to, duration) {
  const start = performance.now()
  function frame(now) {
    const t = Math.min(1, (now - start) / duration)
    const ease = 1 - (1 - t) ** 3
    animated.value = { ...animated.value, [key]: from + (to - from) * ease }
    if (t < 1) requestAnimationFrame(frame)
  }
  requestAnimationFrame(frame)
}

function sparkSeries(card) {
  const v = props.loading ? 0 : Number(card.value) || 0
  return [{ name: card.label, data: sparkFromValue(v) }]
}

function sparkOptions(card) {
  const accent = card.key === 'hot' ? '#34d399' : card.key === 'warm' ? '#fbbf24' : '#818cf8'
  return {
    chart: {
      sparkline: { enabled: true },
      animations: { enabled: true, speed: 600 },
      toolbar: { show: false },
      fontFamily: 'Inter, system-ui, sans-serif',
    },
    stroke: { curve: 'smooth', width: 2, colors: [accent] },
    fill: {
      type: 'gradient',
      gradient: {
        shadeIntensity: 1,
        opacityFrom: 0.35,
        opacityTo: 0.02,
        colorStops: [
          { offset: 0, color: accent, opacity: 0.5 },
          { offset: 100, color: accent, opacity: 0 },
        ],
      },
    },
    colors: [accent],
    tooltip: { enabled: false },
    dataLabels: { enabled: false },
    grid: { show: false },
    xaxis: { labels: { show: false }, axisBorder: { show: false } },
    yaxis: { labels: { show: false } },
  }
}
</script>

<style scoped>
.oc__grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 6px;
}

@media (max-width: 1200px) {
  .oc__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 560px) {
  .oc__grid {
    grid-template-columns: 1fr;
  }
}

.oc-card {
  position: relative;
  border-radius: 8px;
  padding: 8px 8px 0;
  background: #fafafa;
  border: 1px solid #e5e7eb;
  transition: border-color 0.12s ease, background 0.12s ease;
}

.oc-card:hover {
  border-color: #d1d5db;
  background: #fff;
}

.oc-card__top {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 8px;
}

.oc-card__label {
  font-size: 11px;
  font-weight: 600;
  color: #4b5563;
}

.oc-card__hint {
  font-size: 9px;
  color: #9ca3af;
}

.oc-card__value {
  display: flex;
  align-items: baseline;
  gap: 4px;
  margin-top: 4px;
}

.oc-card__num {
  font-size: 18px;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: #111827;
}

.oc-card__suffix {
  font-size: 12px;
  color: #6b7280;
}

.oc-card__spark {
  margin-top: 4px;
  margin-left: -8px;
  margin-right: -8px;
  opacity: 0.95;
}
</style>
