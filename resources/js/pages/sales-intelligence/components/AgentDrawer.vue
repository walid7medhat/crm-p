<template>
  <Teleport to="body">
    <Transition name="si-drawer">
      <div v-if="open && agent" class="dr-back" @click.self="close">
        <aside class="dr" role="dialog" aria-modal="true">
          <header class="dr__head">
            <div class="dr__who">
              <img class="dr__avatar" :src="avatarUrl(agent.avatar)" alt="" />
              <div>
                <h6 class="ui-h-sub dr__name">{{ agent.name }}</h6>
                <p class="dr__email">{{ agent.email }}</p>
              </div>
            </div>
            <button type="button" class="dr__close" aria-label="Close" @click="close">×</button>
          </header>

          <div v-if="loading" class="dr__sk">
            <div class="dr__sk-line" />
            <div class="dr__sk-line" />
            <div class="dr__sk-line" />
          </div>

          <div v-else-if="preview" class="dr__body">
            <div class="dr__hero">
              <div>
                <p class="dr__label">Total score</p>
                <p class="dr__score" :class="{ 'dr__score--pulse': scorePulse }">
                  <SiAnimatedScore class="dr__score-num" :value="preview.total_score" />
                </p>
              </div>
              <span
                :key="String(preview.rank || '')"
                class="dr-tier dr-tier--live"
                :data-tier="String(preview.rank || '').toLowerCase()"
              >{{ tierLabel(preview.rank) }}</span>
            </div>

            <SiInsightWhy
              v-if="whyBullets.length"
              title="Why this changed"
              :bullets="whyBullets"
              :delta-line="whyDeltaLine"
              :factors="whyFactors"
              :default-open="!!whyDeltaLine"
              compact
            />

            <div class="dr__metrics">
              <div v-for="(v, k) in metricChips" :key="k" class="dr-chip">
                <span class="dr-chip__k">{{ k }}</span>
                <span class="dr-chip__v">{{ v }}</span>
              </div>
            </div>

            <div class="dr__charts">
              <div class="dr-chart">
                <p class="dr-chart__title">Factor radar</p>
                <ApexChart type="radar" height="260" :options="radarOptions" :series="radarSeries" />
              </div>
              <div class="dr-chart">
                <p class="dr-chart__title">Normalized factors</p>
                <ApexChart type="bar" height="260" :options="barOptions" :series="barSeries" />
              </div>
            </div>

            <div class="dr__insights">
              <p class="dr-chart__title">Performance insights</p>
              <ul class="dr-list">
                <li v-for="(line, i) in insightLines" :key="i">{{ line }}</li>
              </ul>
            </div>
          </div>

          <p v-else class="dr__empty">No preview data.</p>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import ApexChart from 'vue3-apexcharts'
import { avatarUrl, formatFactor, tierLabel } from '../utils'
import { previewDeltaBullets } from '@/composables/useSiPreviewDelta'
import SiInsightWhy from './SiInsightWhy.vue'
import SiAnimatedScore from './SiAnimatedScore.vue'

const props = defineProps({
  open: Boolean,
  agent: { type: Object, default: null },
  preview: { type: Object, default: null },
  /** Prior preview snapshot (client) for delta / causality copy */
  previousPreview: { type: Object, default: null },
  loading: Boolean,
})

const emit = defineEmits(['close'])

function close() {
  emit('close')
}

const metricChips = computed(() => {
  const m = props.preview?.metrics || {}
  const fmt = (n, suffix = '') => (n == null || n === '' ? '—' : `${Number(n).toLocaleString()}${suffix}`)
  return {
    Conversion: m.conversion_rate != null ? `${Math.round(Number(m.conversion_rate) * 10) / 10}%` : '—',
    'Response (h)': fmt(m.avg_response_time),
    Revenue: fmt(m.revenue, ''),
    Activities: fmt(m.activity_count),
    'Follow-up': m.follow_up_score != null ? `${Math.round(Number(m.follow_up_score))}%` : '—',
    'Close days': fmt(m.closing_speed),
  }
})

const radarSeries = computed(() => {
  const rows = props.preview?.breakdown || []
  const vals = rows.map((r) => Math.max(0, Math.min(100, Number(r.normalized) || 0)))
  return [{ name: 'Score', data: vals }]
})

const radarOptions = computed(() => ({
  chart: { toolbar: { show: false }, fontFamily: 'Inter, system-ui, sans-serif', foreColor: '#6b7280' },
  plotOptions: { radar: { polygons: { strokeColors: '#e5e7eb', fill: { colors: ['#f9fafb'] } } } },
  colors: ['#6366f1'],
  stroke: { width: 2 },
  fill: { opacity: 0.2 },
  markers: { size: 3 },
  xaxis: { categories: (props.preview?.breakdown || []).map((r) => formatFactor(r.factor)) },
  yaxis: { show: false, min: 0, max: 100 },
  dataLabels: { enabled: true, style: { fontSize: '10px', colors: ['#374151'] } },
}))

const barSeries = computed(() => [
  {
    name: 'Normalized',
    data: (props.preview?.breakdown || []).map((r) => Math.round(Number(r.normalized) || 0)),
  },
])

const barOptions = computed(() => ({
  chart: { toolbar: { show: false }, fontFamily: 'Inter, system-ui, sans-serif', foreColor: '#6b7280' },
  plotOptions: { bar: { borderRadius: 8, horizontal: true } },
  colors: ['#6366f1'],
  grid: { borderColor: '#f3f4f6' },
  xaxis: { max: 100 },
  yaxis: { categories: (props.preview?.breakdown || []).map((r) => formatFactor(r.factor)) },
  dataLabels: { enabled: false },
}))

const insightLines = computed(() => {
  const p = props.preview
  if (!p?.breakdown?.length) return ['Not enough data yet.']
  const top = [...p.breakdown].sort((a, b) => Number(b.normalized) - Number(a.normalized))[0]
  const low = [...p.breakdown].sort((a, b) => Number(a.normalized) - Number(b.normalized))[0]
  const lines = []
  if (top) lines.push(`Strongest lever: ${formatFactor(top.factor)} (${Number(top.normalized).toFixed(0)}).`)
  if (low && low !== top) lines.push(`Watch: ${formatFactor(low.factor)} is dragging composite score.`)
  lines.push(`Server rank band: ${tierLabel(p.rank)} · composite ${Number(p.total_score).toFixed(1)}.`)
  return lines
})

const whyBullets = computed(() => previewDeltaBullets(props.previousPreview, props.preview))

const whyDeltaLine = computed(() => {
  const cur = props.preview?.total_score
  const prev = props.previousPreview?.total_score
  if (cur == null || prev == null) return ''
  const d = Math.round((Number(cur) - Number(prev)) * 10) / 10
  if (d === 0) return ''
  return `${d > 0 ? '+' : ''}${d} pts`
})

const whyFactors = computed(() => {
  const rows = props.preview?.breakdown || []
  if (!Array.isArray(rows) || !rows.length) return []
  return [...rows]
    .sort((a, b) => Number(b.normalized || 0) * Number(b.weight || 0) - Number(a.normalized || 0) * Number(a.weight || 0))
    .slice(0, 3)
    .map((r) => formatFactor(r.factor))
})

const scorePulse = ref(false)
watch(
  () => props.preview?.total_score,
  (nv, ov) => {
    if (ov == null || nv == null || Number(nv) === Number(ov)) return
    scorePulse.value = true
    window.setTimeout(() => {
      scorePulse.value = false
    }, 600)
  }
)

watch(
  () => props.open,
  (v) => {
    if (v) document.body.classList.add('si-drawer-lock')
    else document.body.classList.remove('si-drawer-lock')
  }
)
</script>

<style scoped>
.dr-back {
  position: fixed;
  inset: 0;
  z-index: 2000;
  background: rgba(17, 24, 39, 0.25);
  backdrop-filter: blur(4px);
  display: flex;
  justify-content: flex-end;
}

.dr {
  width: min(440px, 100%);
  height: 100%;
  background: #fff;
  border-left: 1px solid #e5e7eb;
  box-shadow: -12px 0 40px rgba(15, 23, 42, 0.12);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.dr__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 14px 10px;
  border-bottom: 1px solid #f3f4f6;
}

.dr__who {
  display: flex;
  gap: 12px;
  min-width: 0;
}

.dr__avatar {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  object-fit: cover;
  border: 1px solid #e5e7eb;
}

.dr__name {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: #111827;
}

.dr__email {
  margin: 2px 0 0;
  font-size: 12px;
  color: #6b7280;
  word-break: break-all;
}

.dr__close {
  border: none;
  background: #f3f4f6;
  color: #374151;
  width: 34px;
  height: 34px;
  border-radius: 8px;
  font-size: 20px;
  line-height: 1;
  cursor: pointer;
}

.dr__close:hover {
  background: #e5e7eb;
}

.dr__body {
  padding: 16px 18px 24px;
  overflow: auto;
  flex: 1;
}

.dr__hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}

.dr__label {
  margin: 0;
  font-size: 11px;
  color: #6b7280;
}

.dr__score {
  margin: 4px 0 0;
  font-size: 28px;
  font-weight: 700;
  letter-spacing: -0.03em;
  color: #111827;
  transition: transform 0.35s ease, color 0.35s ease;
}

.dr__score--pulse {
  color: #4f46e5;
  transform: scale(1.02);
}

.dr__score :deep(.si-as) {
  font-size: inherit;
  font-weight: inherit;
}

.dr-tier--live {
  transition:
    background 0.45s ease,
    border-color 0.45s ease,
    color 0.45s ease,
    transform 0.35s ease;
  animation: si-tier-pop 0.55s ease-out;
}

@keyframes si-tier-pop {
  0% {
    transform: scale(0.98);
  }
  40% {
    transform: scale(1.03);
  }
  100% {
    transform: scale(1);
  }
}

.dr-tier {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  padding: 8px 12px;
  border-radius: 999px;
  border: 1px solid #e5e7eb;
  background: #fff;
}

.dr-tier[data-tier='hot'] {
  color: #047857;
  border-color: #a7f3d0;
  background: #ecfdf5;
}

.dr-tier[data-tier='warm'] {
  color: #b45309;
  border-color: #fde68a;
  background: #fffbeb;
}

.dr-tier[data-tier='cold'] {
  color: #b91c1c;
  border-color: #fecaca;
  background: #fef2f2;
}

.dr__metrics {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
  margin-bottom: 16px;
}

.dr-chip {
  border-radius: 10px;
  padding: 8px 10px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
}

.dr-chip__k {
  display: block;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #9ca3af;
}

.dr-chip__v {
  font-size: 12px;
  font-weight: 600;
  color: #111827;
}

.dr__charts {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.dr-chart {
  border-radius: 10px;
  padding: 8px 6px 2px;
  background: #fff;
  border: 1px solid #e5e7eb;
}

.dr-chart__title {
  margin: 0 0 4px 8px;
  font-size: 11px;
  font-weight: 600;
  color: #6b7280;
}

.dr__insights {
  margin-top: 16px;
}

.dr-list {
  margin: 8px 0 0;
  padding-left: 18px;
  color: #4b5563;
  font-size: 12px;
  line-height: 1.5;
}

.dr__empty {
  padding: 20px;
  color: #9ca3af;
  font-size: 12px;
}

.dr__sk {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.dr__sk-line {
  height: 14px;
  border-radius: 8px;
  background: #f3f4f6;
  animation: pulse 1s ease-in-out infinite alternate;
}

@keyframes pulse {
  to {
    opacity: 0.5;
  }
}

.si-drawer-enter-active,
.si-drawer-leave-active {
  transition: opacity 0.22s ease;
}

.si-drawer-enter-active .dr,
.si-drawer-leave-active .dr {
  transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}

.si-drawer-enter-from,
.si-drawer-leave-to {
  opacity: 0;
}

.si-drawer-enter-from .dr,
.si-drawer-leave-to .dr {
  transform: translateX(24px);
}
</style>

<style>
body.si-drawer-lock {
  overflow: hidden;
}
</style>
