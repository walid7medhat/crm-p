<template>
  <section class="sr">
    <header class="sr__head">
      <div>
        <h6 class="sr__title">Scoring rules</h6>
        <p class="sr__sub">Weights & thresholds · preview updates in rail</p>
      </div>
      <span class="sr__pill">Preview-only</span>
    </header>

    <div v-if="impactUserLabel" class="sr__band" :class="{ 'sr__band--load': livePreviewLoading }">
      <div class="sr__band-row">
        <span class="sr__band-k">Live impact</span>
        <span class="sr__band-n">{{ impactUserLabel }}</span>
      </div>
      <template v-if="livePreviewLoading">
        <p class="sr__band-meta">Recalculating…</p>
      </template>
      <template v-else-if="livePreview">
        <div class="sr__band-row sr__band-row--nums">
          <span class="sr__band-sc">{{ Math.round(Number(livePreview.total_score) || 0) }}</span>
          <span v-if="impactPrevScore != null" class="sr__band-d">{{ impactDeltaLabel }}</span>
          <span class="sr__band-rk">{{ tierShort(livePreview.rank) }}</span>
        </div>
        <p class="sr__band-hint">Factor cards show normalized contribution for this agent (draft rules).</p>
      </template>
      <p v-else class="sr__band-meta">Pick an agent in Insights → Live preview to attach this overlay.</p>
    </div>

    <SiInsightWhy
      v-if="rulesWhyBullets.length"
      title="Why the preview moved"
      :bullets="rulesWhyBullets"
      :delta-line="rulesWhyDelta"
      compact
    />

    <div v-if="loading" class="sr__skeleton">
      <div v-for="n in 4" :key="n" class="sr-sk" />
    </div>

    <div v-else class="sr__panels">
      <div v-for="group in groupedRules" :key="group.title" class="sr-group">
        <header class="sr-group__head">
          <span class="sr-group__title">{{ group.title }}</span>
          <span class="sr-group__hint">{{ group.rules.length }} factors</span>
        </header>
        <div class="sr-group__grid">
          <article v-for="r in group.rules" :key="r.id" class="sr-card" :class="{ 'sr-card--hit': isHotFactor(r) }">
            <div class="sr-card__top">
              <span class="sr-card__name">{{ formatFactor(r.factor_name) }}</span>
              <button
                type="button"
                class="sr-dir"
                :class="{ 'sr-dir--low': r.direction === 'lower_better' }"
                @click="toggleDir(r.id)"
              >
                {{ r.direction === 'lower_better' ? 'Lower better' : 'Higher better' }}
              </button>
            </div>

            <div class="sr-causal" role="group" :aria-label="`Causal link for ${formatFactor(r.factor_name)}`">
              <span class="sr-causal__src">{{ formatFactor(r.factor_name) }}</span>
              <span class="sr-causal__arrow" aria-hidden="true">→</span>
              <span class="sr-causal__metric">{{ causalMetricTitle(r.factor_name) }}</span>
              <span class="sr-causal__fx" :data-up="causalIsUpGood(r)">{{ causalEffectHint(r) }}</span>
            </div>

            <label class="sr-slider">
              <div class="sr-slider__row">
                <span>Weight</span>
                <span class="sr-slider__val">{{ weightPct(r.weight) }}%</span>
              </div>
              <input
                v-model.number="r.weight"
                type="range"
                min="0"
                max="1"
                step="0.01"
                class="sr-range"
                @input="emitChange"
              />
            </label>

            <div class="sr-thresholds">
              <label class="sr-num">
                <span>Low</span>
                <input v-model.number="r.low_value" type="number" step="any" class="sr-input" @input="emitChange" />
              </label>
              <label class="sr-num">
                <span>Medium</span>
                <input v-model.number="r.medium_value" type="number" step="any" class="sr-input" @input="emitChange" />
              </label>
              <label class="sr-num">
                <span>High</span>
                <input v-model.number="r.high_value" type="number" step="any" class="sr-input" @input="emitChange" />
              </label>
            </div>

            <div v-if="livePreview && breakdownFor(r)" class="sr-impact">
              <div class="sr-impact__row">
                <span>Engine norm</span>
                <span>{{ Math.round(Number(breakdownFor(r).normalized) || 0) }}</span>
              </div>
              <div class="sr-impact__track">
                <div
                  class="sr-impact__bar"
                  :style="{ width: Math.min(100, Math.max(0, Number(breakdownFor(r).normalized) || 0)) + '%' }"
                />
              </div>
              <p v-if="metricChip(r)" class="sr-impact__raw">{{ metricChip(r) }}</p>
            </div>
          </article>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { formatFactor, previousRememberedScore } from '../utils'
import { previewDeltaBullets } from '@/composables/useSiPreviewDelta'
import SiInsightWhy from './SiInsightWhy.vue'

const FACTOR_METRIC_KEY = {
  conversion_rate: 'conversion_rate',
  response_speed: 'avg_response_time',
  revenue_performance: 'revenue',
  activity_level: 'activity_count',
  follow_up_discipline: 'follow_up_score',
  closing_efficiency: 'closing_speed',
}

const GROUP_ORDER = ['Outcomes', 'Responsiveness', 'Activity', 'Discipline', 'Other']

const FACTOR_GROUP = {
  conversion_rate: 'Outcomes',
  revenue_performance: 'Outcomes',
  closing_efficiency: 'Outcomes',
  response_speed: 'Responsiveness',
  activity_level: 'Activity',
  follow_up_discipline: 'Discipline',
}

const rules = defineModel('rules', { type: Array, default: () => [] })

const props = defineProps({
  loading: Boolean,
  livePreview: { type: Object, default: null },
  livePreviewLoading: Boolean,
  impactUserId: { type: String, default: '' },
  impactUserLabel: { type: String, default: '' },
})

const emit = defineEmits(['rules-changed'])

const rulesPrevSnap = ref(null)
watch(
  () => props.livePreview,
  (_cur, prev) => {
    if (prev == null) return
    try {
      rulesPrevSnap.value = JSON.parse(JSON.stringify(prev))
    } catch {
      rulesPrevSnap.value = prev
    }
  }
)

const rulesWhyBullets = computed(() => previewDeltaBullets(rulesPrevSnap.value, props.livePreview))

const rulesWhyDelta = computed(() => {
  const cur = props.livePreview?.total_score
  const prev = rulesPrevSnap.value?.total_score
  if (cur == null || prev == null) return ''
  const d = Math.round((Number(cur) - Number(prev)) * 10) / 10
  if (d === 0) return ''
  return `${d > 0 ? '+' : ''}${d} pts`
})

const impactPrevScore = computed(() => {
  if (!props.impactUserId) return null
  return previousRememberedScore(props.impactUserId)
})

const impactDeltaLabel = computed(() => {
  if (!props.livePreview || impactPrevScore.value == null) return ''
  const d = Math.round((Number(props.livePreview.total_score) - impactPrevScore.value) * 10) / 10
  if (d === 0) return '±0 vs last'
  return `${d > 0 ? '+' : ''}${d} vs last`
})

function tierShort(rank) {
  const t = String(rank || '').toLowerCase()
  if (t === 'hot') return 'HOT'
  if (t === 'warm') return 'WARM'
  if (t === 'cold') return 'COLD'
  return '—'
}

function breakdownFor(rule) {
  const rows = props.livePreview?.breakdown || []
  return rows.find((b) => b.factor === rule.factor_name) || null
}

function isHotFactor(rule) {
  const b = breakdownFor(rule)
  return b != null && Number(b.normalized) >= 72
}

function metricChip(rule) {
  const m = props.livePreview?.metrics
  if (!m) return ''
  const k = FACTOR_METRIC_KEY[rule.factor_name]
  if (!k || m[k] === '' || m[k] == null) return ''
  const v = m[k]
  const t = typeof v === 'number' ? (Number.isInteger(v) ? String(v) : String(Math.round(v * 10) / 10)) : String(v)
  return `Live metric · ${k.replace(/_/g, ' ')}: ${t}`
}

const METRIC_LABEL = {
  conversion_rate: 'Conversion %',
  avg_response_time: 'Response time',
  revenue: 'Revenue',
  activity_count: 'Activities',
  follow_up_score: 'Follow-up',
  closing_speed: 'Close speed',
}

function causalMetricTitle(factorName) {
  const k = FACTOR_METRIC_KEY[factorName]
  if (!k) return 'Composite driver'
  return METRIC_LABEL[k] || k.replace(/_/g, ' ')
}

function causalIsUpGood(rule) {
  return rule.direction !== 'lower_better'
}

function causalEffectHint(rule) {
  const up = causalIsUpGood(rule)
  if (up) return 'Raw ↑ → norm tends ↑'
  return 'Raw ↓ → norm tends ↑'
}

const groupedRules = computed(() => {
  const buckets = {}
  for (const title of GROUP_ORDER) buckets[title] = []
  for (const r of rules.value || []) {
    const cat = FACTOR_GROUP[r.factor_name] || 'Other'
    if (!buckets[cat]) buckets[cat] = []
    buckets[cat].push(r)
  }
  return GROUP_ORDER.filter((t) => buckets[t]?.length).map((title) => ({
    title,
    rules: buckets[title],
  }))
})

function emitChange() {
  emit('rules-changed')
}

function weightPct(w) {
  return Math.round(Number(w || 0) * 1000) / 10
}

function toggleDir(ruleId) {
  const row = rules.value.find((x) => x.id === ruleId)
  if (!row) return
  row.direction = row.direction === 'lower_better' ? 'higher_better' : 'lower_better'
  emitChange()
}
</script>

<style scoped>
.sr__head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 8px;
}

.sr__title {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
  color: #111827;
}

.sr__sub {
  margin: 2px 0 0;
  font-size: 11px;
  color: #6b7280;
}

.sr__pill {
  font-size: 10px;
  font-weight: 600;
  padding: 4px 8px;
  border-radius: 999px;
  color: #4338ca;
  border: 1px solid #e0e7ff;
  background: #eef2ff;
}

.sr__band {
  margin-bottom: 10px;
  padding: 8px 10px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fafafa;
}

.sr__band--load {
  opacity: 0.85;
}

.sr__band-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  flex-wrap: wrap;
}

.sr__band-row--nums {
  margin-top: 4px;
}

.sr__band-k {
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #9ca3af;
}

.sr__band-n {
  font-size: 12px;
  font-weight: 600;
  color: #111827;
}

.sr__band-sc {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
  font-variant-numeric: tabular-nums;
}

.sr__band-d {
  font-size: 11px;
  font-weight: 600;
  color: #4f46e5;
}

.sr__band-rk {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.04em;
  color: #6b7280;
}

.sr__band-meta {
  margin: 4px 0 0;
  font-size: 11px;
  color: #6b7280;
}

.sr__band-hint {
  margin: 6px 0 0;
  font-size: 10px;
  color: #9ca3af;
  line-height: 1.35;
}

.sr__panels {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.sr-group__head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 6px;
  padding-bottom: 4px;
  border-bottom: 1px solid #e5e7eb;
}

.sr-group__title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #6b7280;
}

.sr-group__hint {
  font-size: 10px;
  color: #9ca3af;
}

.sr-group__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 8px;
}

.sr-card {
  border-radius: 8px;
  padding: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  transition: border-color 0.12s ease, background 0.12s ease;
}

.sr-card:hover {
  border-color: #d1d5db;
  background: #fafafa;
}

.sr-card--hit {
  border-color: #c7d2fe;
  box-shadow: inset 0 0 0 1px #e0e7ff;
}

.sr-impact {
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px dashed #e5e7eb;
}

.sr-impact__row {
  display: flex;
  justify-content: space-between;
  font-size: 10px;
  font-weight: 600;
  color: #6b7280;
  margin-bottom: 4px;
}

.sr-impact__track {
  height: 4px;
  border-radius: 999px;
  background: #f3f4f6;
  overflow: hidden;
}

.sr-impact__bar {
  height: 100%;
  border-radius: 999px;
  background: #111827;
  transition: width var(--si-ease, 0.16s ease);
}

.sr-impact__raw {
  margin: 4px 0 0;
  font-size: 10px;
  color: #9ca3af;
  line-height: 1.3;
}

.sr-causal {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 8px;
  padding: 6px 8px;
  border-radius: 6px;
  background: #f9fafb;
  border: 1px dashed #e5e7eb;
  font-size: 10px;
  color: #6b7280;
}

.sr-causal__src {
  font-weight: 600;
  color: #374151;
}

.sr-causal__arrow {
  color: #9ca3af;
  font-weight: 700;
}

.sr-causal__metric {
  font-weight: 600;
  color: #111827;
}

.sr-causal__fx {
  margin-left: auto;
  font-weight: 600;
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #047857;
}

.sr-causal__fx[data-up='false'] {
  color: #0369a1;
}

.sr-card__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 6px;
}

.sr-card__name {
  margin: 0;
  font-size: 12px;
  font-weight: 600;
  color: #111827;
}

.sr-dir {
  border-radius: 999px;
  border: 1px solid #e5e7eb;
  background: #f9fafb;
  color: #4b5563;
  font-size: 10px;
  font-weight: 600;
  padding: 4px 8px;
  cursor: pointer;
  flex-shrink: 0;
  transition: background 0.12s ease;
}

.sr-dir:hover {
  background: #f3f4f6;
}

.sr-dir--low {
  border-color: #bae6fd;
  color: #0369a1;
}

.sr-slider__row {
  display: flex;
  justify-content: space-between;
  font-size: 10px;
  color: #6b7280;
  margin-bottom: 2px;
}

.sr-slider__val {
  font-variant-numeric: tabular-nums;
  color: #111827;
}

.sr-range {
  width: 100%;
  accent-color: #6366f1;
}

.sr-thresholds {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 6px;
  margin-top: 6px;
}

.sr-num span {
  display: block;
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #9ca3af;
  margin-bottom: 2px;
}

.sr-input {
  width: 100%;
  border-radius: 6px;
  border: 1px solid #e5e7eb;
  background: #fff;
  color: #111827;
  padding: 6px 8px;
  font-size: 12px;
}

.sr-input:focus {
  outline: none;
  border-color: #c7d2fe;
  box-shadow: var(--si-focus-ring, 0 0 0 2px #fff, 0 0 0 4px #d4d4d4);
}

.sr__skeleton {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.sr-sk {
  height: 96px;
  border-radius: 8px;
  background: linear-gradient(110deg, #f3f4f6 8%, #e5e7eb 18%, #f3f4f6 33%);
  background-size: 200% 100%;
  animation: sr-shimmer 1.2s ease-in-out infinite;
}

@keyframes sr-shimmer {
  to {
    background-position-x: -200%;
  }
}
</style>
