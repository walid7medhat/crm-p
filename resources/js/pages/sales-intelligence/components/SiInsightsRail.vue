<template>
  <aside class="rail">
    <span class="rail__h">Insights</span>

    <div class="rail__card">
      <span class="rail__k">Top performer</span>
      <template v-if="top">
        <p class="rail__name">{{ top.name }}</p>
        <p class="rail__meta">Score {{ scoreTxt(top.score) }} · {{ tierLabel(top.rank) }}</p>
      </template>
      <p v-else class="rail__muted">Open Agents tab to load rankings.</p>
    </div>

    <div class="rail__card">
      <span class="rail__k">Needs attention</span>
      <template v-if="worst">
        <p class="rail__name">{{ worst.name }}</p>
        <p class="rail__meta">Score {{ scoreTxt(worst.score) }} · {{ tierLabel(worst.rank) }}</p>
      </template>
      <p v-else class="rail__muted">—</p>
    </div>

    <div class="rail__card">
      <span class="rail__k">Today</span>
      <p class="rail__stat">{{ distributionsToday }} <span class="rail__u">distributions</span></p>
      <p class="rail__meta">Avg score {{ overview?.avg_score ?? '—' }}</p>
    </div>

    <div class="rail__card">
      <span class="rail__k">Live preview</span>
      <SiCombobox
        v-model="liveUserId"
        history-key="si:rail-live-agent"
        :options="previewAgentOptions"
        label-key="label"
        value-key="value"
        placeholder="Select agent…"
        :clearable="true"
        :filterable="true"
      />
      <div v-if="previewLoading" class="rail__sk" />
      <template v-else-if="preview">
        <p class="rail__big">{{ Math.round(preview.total_score ?? 0) }}</p>
        <p class="rail__meta">{{ tierLabel(preview.rank) }} · {{ deltaText }}</p>
      </template>
      <p v-else class="rail__muted">Select an agent</p>
    </div>

    <div class="rail__card rail__card--accent">
      <span class="rail__k">AI hint</span>
      <p class="rail__hint">{{ aiHint }}</p>
      <button type="button" class="rail__link" @click="$emit('go-ai')">Open AI mode →</button>
    </div>
  </aside>
</template>

<script setup>
import { computed, watch } from 'vue'
import { tierLabel, previousRememberedScore, rememberScore } from '../utils'
import SiCombobox from './SiCombobox.vue'

const props = defineProps({
  agents: { type: Array, default: () => [] },
  overview: { type: Object, default: () => ({}) },
  distributionsToday: { type: Number, default: 0 },
  preview: { type: Object, default: null },
  previewLoading: Boolean,
})

const emit = defineEmits(['go-ai'])

const liveUserId = defineModel('liveUserId', { type: String, default: '' })

const ranked = computed(() =>
  [...(props.agents || [])]
    .filter((a) => a.score != null && !Number.isNaN(Number(a.score)))
    .sort((a, b) => Number(b.score) - Number(a.score))
)

const top = computed(() => ranked.value[0] || null)
const worst = computed(() => ranked.value[ranked.value.length - 1] || null)

const agentChoices = computed(() => (props.agents || []).slice(0, 40))

const previewAgentOptions = computed(() => [
  { value: '', label: '— None —' },
  ...agentChoices.value.map((a) => ({ value: String(a.id), label: a.name })),
])

watch(
  () => liveUserId.value,
  (v) => {
    if (v === null || v === undefined) liveUserId.value = ''
  }
)

function scoreTxt(s) {
  if (s == null) return '—'
  return Math.round(Number(s))
}

const deltaText = computed(() => {
  if (!props.preview || !liveUserId.value) return ''
  const cur = Number(props.preview.total_score)
  const prev = previousRememberedScore(liveUserId.value)
  if (prev == null) return 'baseline'
  const d = Math.round((cur - prev) * 10) / 10
  return d === 0 ? 'no change' : `${d > 0 ? '+' : ''}${d} vs last`
})

watch(
  () => props.preview?.total_score,
  (sc) => {
    if (sc != null && liveUserId.value) rememberScore(liveUserId.value, Number(sc))
  }
)

const aiHint = computed(() => {
  const h = props.overview?.tiers?.hot ?? 0
  const w = props.overview?.tiers?.warm ?? 0
  return `${h} hot · ${w} warm agents in pool. Tune rules to shift balance.`
})
</script>

<style scoped>
.rail {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.rail__h {
  display: block;
  margin: 0 0 2px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #6b7280;
}

.rail__card {
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  padding: 8px;
  transition: border-color 0.12s ease;
}

.rail__card:hover {
  border-color: #d1d5db;
}

.rail__card--accent {
  background: #fafafa;
  border-color: #e5e7eb;
}

.rail__k {
  display: block;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #9ca3af;
  margin-bottom: 6px;
}

.rail__name {
  margin: 0;
  font-size: 12px;
  font-weight: 600;
  color: #111827;
}

.rail__meta {
  margin: 2px 0 0;
  font-size: 11px;
  color: #6b7280;
}

.rail__muted {
  margin: 0;
  font-size: 11px;
  color: #9ca3af;
}

.rail__stat {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
  color: #111827;
}

.rail__u {
  font-size: 11px;
  font-weight: 500;
  color: #6b7280;
}

.rail__big {
  margin: 4px 0 0;
  font-size: 20px;
  font-weight: 700;
  color: #111827;
}

.rail__hint {
  margin: 0 0 6px;
  font-size: 11px;
  line-height: 1.4;
  color: #4b5563;
}

.rail__link {
  border: none;
  background: transparent;
  padding: 0;
  font-size: 11px;
  font-weight: 600;
  color: #4f46e5;
  cursor: pointer;
}

.rail__link:hover {
  text-decoration: underline;
}

.rail__sk {
  height: 36px;
  border-radius: 8px;
  background: #f3f4f6;
  margin-top: 6px;
  animation: rail-p 0.8s ease-in-out infinite alternate;
}

@keyframes rail-p {
  to {
    opacity: 0.65;
  }
}
</style>
