<template>
  <section class="lp">
    <header class="lp__head">
      <div>
        <h6 class="lp__title">Live agent preview</h6>
        <p class="lp__sub">Debounced preview-score · reflects rule edits</p>
      </div>
      <button type="button" class="lp__ghost" :disabled="!previewUserId" @click="emit('refresh')">Refresh</button>
    </header>

    <div class="lp__controls">
      <label class="lp-field">
        <span>Search</span>
        <input v-model="q" type="search" class="lp-input" placeholder="Filter agents…" @input="onQ" />
      </label>
      <label class="lp-field">
        <span>Agent</span>
        <select v-model="previewUserId" class="lp-input" @change="emit('user-change', previewUserId)">
          <option value="">Select…</option>
          <option v-for="a in filteredAgents" :key="a.id" :value="String(a.id)">{{ a.name }}</option>
        </select>
      </label>
    </div>

    <div class="lp__panel">
      <div v-if="loading" class="lp__sk">
        <div class="lp__sk-b" />
        <div class="lp__sk-b" />
      </div>
      <template v-else-if="preview">
        <div class="lp__row">
          <div>
            <p class="lp__label">Total score</p>
            <p class="lp__score">{{ Math.round(preview.total_score ?? 0) }}</p>
          </div>
          <div class="lp__delta" :class="deltaClass">
            <span class="lp__dlabel">vs last preview</span>
            <span class="lp__dval">{{ deltaText }}</span>
          </div>
          <span class="lp-badge" :data-tier="String(preview.rank || '').toLowerCase()">{{ tierLabel(preview.rank) }}</span>
        </div>
        <div class="lp__bars">
          <div v-for="(row, i) in preview.breakdown" :key="i" class="lp-bar">
            <div class="lp-bar__top">
              <span>{{ formatFactor(row.factor) }}</span>
              <span class="lp-bar__n">{{ Math.round(Number(row.normalized) || 0) }}</span>
            </div>
            <div class="lp-bar__track"><i :style="{ width: (Number(row.normalized) || 0) + '%' }" /></div>
          </div>
        </div>
      </template>
      <p v-else class="lp__empty">Select an agent to preview scoring with the current rule draft.</p>
      <p v-if="error" class="lp__err">{{ error }}</p>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { formatFactor, tierLabel, rememberScore, previousRememberedScore } from '../utils'

const props = defineProps({
  agents: { type: Array, default: () => [] },
  preview: { type: Object, default: null },
  loading: Boolean,
  error: { type: String, default: '' },
  modelValue: { type: [String, Number], default: '' },
})

const emit = defineEmits(['update:modelValue', 'user-change', 'refresh'])

const previewUserId = computed({
  get: () => (props.modelValue === '' || props.modelValue == null ? '' : String(props.modelValue)),
  set: (v) => emit('update:modelValue', v),
})

const q = ref('')
const qDebounced = ref('')
let t = null
function onQ() {
  clearTimeout(t)
  t = setTimeout(() => {
    qDebounced.value = q.value
  }, 240)
}

const filteredAgents = computed(() => {
  const s = (qDebounced.value || '').toLowerCase().trim()
  if (!s) return props.agents
  return props.agents.filter((a) => (a.name || '').toLowerCase().includes(s) || String(a.email || '').toLowerCase().includes(s))
})

const deltaText = computed(() => {
  if (!props.preview || !previewUserId.value) return '—'
  const cur = Number(props.preview.total_score)
  const prev = previousRememberedScore(previewUserId.value)
  if (prev == null) return 'baseline'
  const d = Math.round((cur - prev) * 10) / 10
  if (d === 0) return '0'
  return (d > 0 ? '+' : '') + d
})

const deltaClass = computed(() => {
  const t = deltaText.value
  if (t === '—' || t === 'baseline') return ''
  if (t.startsWith('+')) return 'lp__delta--up'
  if (t.startsWith('-')) return 'lp__delta--down'
  return ''
})

watch(
  () => props.preview?.total_score,
  (sc) => {
    if (sc != null && previewUserId.value) rememberScore(previewUserId.value, Number(sc))
  }
)
</script>

<style scoped>
.lp__head {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 12px;
  margin-bottom: 12px;
}

.lp__title {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  color: #111827;
}

.lp__sub {
  margin: 4px 0 0;
  font-size: 12px;
  color: #6b7280;
}

.lp__ghost {
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  background: #fff;
  color: #374151;
  font-size: 12px;
  font-weight: 600;
  padding: 8px 12px;
  cursor: pointer;
}

.lp__ghost:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.lp__controls {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-bottom: 12px;
}

@media (max-width: 640px) {
  .lp__controls {
    grid-template-columns: 1fr;
  }
}

.lp-field span {
  display: block;
  font-size: 11px;
  color: #6b7280;
  margin-bottom: 4px;
}

.lp-input {
  width: 100%;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  background: #fff;
  color: #111827;
  padding: 10px 12px;
  font-size: 13px;
}

.lp__panel {
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  background: #f9fafb;
  padding: 12px 12px 10px;
  min-height: 200px;
}

.lp__row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}

.lp__label {
  margin: 0;
  font-size: 12px;
  color: #6b7280;
}

.lp__score {
  margin: 2px 0 0;
  font-size: 28px;
  font-weight: 700;
  color: #111827;
}

.lp__delta {
  text-align: right;
}

.lp__dlabel {
  display: block;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #9ca3af;
}

.lp__dval {
  font-size: 14px;
  font-weight: 700;
  color: #374151;
}

.lp__delta--up .lp__dval {
  color: #059669;
}

.lp__delta--down .lp__dval {
  color: #dc2626;
}

.lp-badge {
  align-self: center;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 8px 12px;
  border-radius: 999px;
  border: 1px solid #e5e7eb;
  background: #fff;
}

.lp-badge[data-tier='hot'] {
  color: #047857;
  border-color: #a7f3d0;
  background: #ecfdf5;
}

.lp-badge[data-tier='warm'] {
  color: #b45309;
  border-color: #fde68a;
  background: #fffbeb;
}

.lp-badge[data-tier='cold'] {
  color: #b91c1c;
  border-color: #fecaca;
  background: #fef2f2;
}

.lp__bars {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.lp-bar__top {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #6b7280;
}

.lp-bar__n {
  font-variant-numeric: tabular-nums;
  color: #9ca3af;
}

.lp-bar__track {
  height: 6px;
  border-radius: 999px;
  background: #e5e7eb;
  overflow: hidden;
  margin-top: 4px;
}

.lp-bar__track > i {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, #6366f1, #38bdf8);
  transition: width 0.35s ease;
}

.lp__empty {
  margin: 0;
  font-size: 13px;
  color: #9ca3af;
}

.lp__err {
  margin: 10px 0 0;
  font-size: 12px;
  color: #b91c1c;
}

.lp__sk {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.lp__sk-b {
  height: 48px;
  border-radius: 12px;
  background: #e5e7eb;
  animation: lp-pulse 0.9s ease-in-out infinite alternate;
}

@keyframes lp-pulse {
  to {
    opacity: 0.55;
  }
}
</style>
