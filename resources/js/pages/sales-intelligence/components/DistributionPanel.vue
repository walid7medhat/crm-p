<template>
  <section class="dp">
    <header class="dp__head">
      <div>
        <h6 class="dp__title">Distribution simulator</h6>
        <p class="dp__sub">Distribute API · dry-run default · server cap {{ serverMax }}</p>
      </div>
    </header>

    <div class="dp__controls">
      <SiCombobox
        v-model="mode"
        history-key="si:dist-mode"
        label="Mode"
        :options="modeOptions"
        label-key="label"
        value-key="value"
        placeholder="Server default"
        :clearable="true"
        hint="Overrides server distribution_mode for this run."
      />
      <SiLeadCombobox v-model="leadId" label="Lead" history-key="si:dist-lead" :error="leadError" />
      <SiCombobox
        v-model="manualUserId"
        history-key="si:dist-manual-agent"
        label="Manual agent"
        :options="manualAgentOptions"
        label-key="label"
        value-key="value"
        placeholder="When mode is Manual"
        :clearable="true"
        :filterable="true"
        hint="Pick an eligible sales user."
      />
      <label class="dp-field dp-toggle">
        <input v-model="dryRun" type="checkbox" />
        <span>Dry run</span>
      </label>
      <label class="dp-field dp-range-wrap">
        <span>Cap preview (UI)</span>
        <input v-model.number="simMax" type="range" min="1" max="80" class="dp-range" />
        <span class="dp-cap">{{ simMax }} / agent / day</span>
      </label>
      <div class="dp-field dp-actions">
        <button type="button" class="dp-btn" :disabled="simulating || !leadId" @click="run">
          {{ simulating ? 'Running…' : 'Run' }}
        </button>
        <button type="button" class="dp-btn dp-btn--ghost" :disabled="simulating" @click="resetForm">Reset</button>
      </div>
    </div>

    <div v-if="recommendedUserId && recommendedLabel" class="dp-assist">
      <iconify-icon icon="lucide:sparkles" class="dp-assist__ic" aria-hidden="true" />
      <div class="dp-assist__txt">
        <span class="dp-assist__k">Assist</span>
        <span class="dp-assist__v">Default pick: {{ recommendedLabel }} — {{ recommendedReason }}</span>
      </div>
      <button type="button" class="dp-assist__btn" @click="applyRecommended">Use in manual</button>
    </div>

    <div v-if="result" class="dp__flow">
      <div class="dp-node dp-node--lead">
        <span class="dp-node__k">Lead</span>
        <span class="dp-node__v">#{{ leadId }}</span>
      </div>
      <div class="dp-arrow">
        <span>{{ result.method || 'route' }}</span>
      </div>
      <div class="dp-node dp-node--agent" :class="{ 'dp-node--pulse': assignPulse }">
        <span class="dp-node__k">Assigned</span>
        <span class="dp-node__v">{{ result.assigned_to?.name || '—' }}</span>
        <span class="dp-node__m">Score {{ result.score_at_assignment ?? '—' }}</span>
      </div>
    </div>

    <SiInsightWhy
      v-if="result && resultWhyBullets.length"
      title="Why this assignment"
      :bullets="resultWhyBullets"
      compact
    />

    <p v-if="error" class="dp__err">{{ error }}</p>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { salesIntelligenceApi } from '@/services/salesIntelligenceApi'
import { distributionResultBullets } from '@/composables/useSiPreviewDelta'
import SiCombobox from './SiCombobox.vue'
import SiLeadCombobox from './SiLeadCombobox.vue'
import SiInsightWhy from './SiInsightWhy.vue'

const props = defineProps({
  serverMaxLeads: { type: Number, default: 15 },
  externalLeadId: { type: Number, default: null },
  /** Active sales users for manual assignment combobox */
  agents: { type: Array, default: () => [] },
  /** Heuristic default agent (client-only) */
  recommendedUserId: { type: [Number, String], default: null },
  recommendedLabel: { type: String, default: '' },
  recommendedReason: { type: String, default: 'Highest composite in loaded pool.' },
})

const modeOptions = [
  { value: '', label: 'Server default' },
  { value: 'weighted', label: 'Weighted' },
  { value: 'round_robin', label: 'Round robin' },
  { value: 'performance_first', label: 'Performance first' },
  { value: 'manual', label: 'Manual' },
  { value: 'hybrid', label: 'Hybrid' },
]

const mode = ref('')
watch(mode, (v) => {
  if (v === null || v === undefined) mode.value = ''
})

const leadId = ref(null)
const manualUserId = ref(null)

const manualAgentOptions = computed(() =>
  (props.agents || []).map((a) => ({
    value: a.id,
    label: `${a.name}${a.email ? ` · ${a.email}` : ''}`,
  }))
)

const dryRun = ref(true)
const simMax = ref(15)
const simulating = ref(false)
const result = ref(null)
const error = ref('')
const leadError = ref('')

const serverMax = ref(props.serverMaxLeads)
const assignPulse = ref(false)

const resultWhyBullets = computed(() => {
  if (!result.value) return []
  return distributionResultBullets({ ...result.value, dry_run: dryRun.value })
})

function applyRecommended() {
  if (props.recommendedUserId == null) return
  manualUserId.value = Number(props.recommendedUserId)
  if (!mode.value) mode.value = 'manual'
}

watch(
  () => result.value,
  (v) => {
    if (!v) return
    assignPulse.value = true
    window.setTimeout(() => {
      assignPulse.value = false
    }, 700)
  }
)

watch(
  () => props.serverMaxLeads,
  (v) => {
    serverMax.value = v
    simMax.value = v
  }
)

watch(
  () => props.externalLeadId,
  (v) => {
    if (v != null && !Number.isNaN(Number(v))) leadId.value = Number(v)
  }
)

function resetForm() {
  result.value = null
  error.value = ''
  leadError.value = ''
  manualUserId.value = null
}

async function run() {
  error.value = ''
  leadError.value = ''
  result.value = null
  if (!leadId.value) {
    leadError.value = 'Select a lead'
    return
  }
  if (mode.value === 'manual' && !manualUserId.value) {
    error.value = 'Manual mode requires an agent'
    return
  }
  simulating.value = true
  try {
    const body = { lead_id: leadId.value, dry_run: dryRun.value }
    if (mode.value) body.mode = mode.value
    if (manualUserId.value) body.manual_user_id = manualUserId.value
    const data = await salesIntelligenceApi.distribute(body)
    result.value = data
  } catch (e) {
    error.value = e?.message || 'Failed'
  } finally {
    simulating.value = false
  }
}
</script>

<style scoped>
.dp__head {
  margin-bottom: 8px;
}

.dp__title {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
  color: #111827;
}

.dp__sub {
  margin: 2px 0 0;
  font-size: 11px;
  color: #6b7280;
}

.dp__controls {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 8px;
  align-items: end;
}

.dp-field span {
  display: block;
  font-size: 10px;
  color: #6b7280;
  margin-bottom: 4px;
}

.dp-toggle {
  display: flex;
  flex-direction: row !important;
  align-items: center;
  gap: 8px;
  padding-top: 14px;
  font-size: 12px;
  color: #374151;
}

.dp-range-wrap {
  padding-top: 4px;
}

.dp-range {
  width: 100%;
  accent-color: #6366f1;
}

.dp-cap {
  display: block;
  margin-top: 2px;
  font-size: 10px;
  color: #9ca3af;
}

.dp-actions {
  display: flex;
  flex-direction: row;
  gap: 8px;
  align-items: center;
  padding-top: 14px;
}

.dp-btn {
  border: none;
  border-radius: 8px;
  padding: 8px 14px;
  font-weight: 600;
  font-size: 12px;
  cursor: pointer;
  color: #fff;
  background: #111827;
  transition: opacity 0.15s ease;
}

.dp-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.dp-btn--ghost {
  background: #fff;
  color: #374151;
  border: 1px solid #e5e7eb;
}

.dp-btn--ghost:hover:not(:disabled) {
  background: #f9fafb;
}

.dp__flow {
  margin-top: 12px;
  display: flex;
  flex-wrap: wrap;
  align-items: stretch;
  gap: 8px;
}

.dp-node {
  flex: 1;
  min-width: 120px;
  border-radius: 8px;
  padding: 8px 10px;
  border: 1px solid #e5e7eb;
  background: #fafafa;
}

.dp-node--lead {
  border-color: #e0f2fe;
  background: #f0f9ff;
}

.dp-node--agent {
  border-color: #e0e7ff;
  background: #fafaff;
}

.dp-node__k {
  display: block;
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #6b7280;
}

.dp-node__v {
  display: block;
  margin-top: 2px;
  font-size: 13px;
  font-weight: 700;
  color: #111827;
}

.dp-node__m {
  display: block;
  margin-top: 4px;
  font-size: 11px;
  color: #6b7280;
}

.dp-arrow {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 88px;
  font-size: 9px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #4f46e5;
  background: #fff;
  border-radius: 999px;
  border: 1px dashed #e5e7eb;
}

.dp__err {
  margin-top: 8px;
  font-size: 11px;
  color: #b91c1c;
}

.dp-assist {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  margin-top: 10px;
  padding: 8px 10px;
  border-radius: 8px;
  border: 1px solid #e0e7ff;
  background: #fafaff;
}

.dp-assist__ic {
  font-size: 16px;
  color: #4f46e5;
  flex-shrink: 0;
}

.dp-assist__txt {
  flex: 1;
  min-width: 0;
}

.dp-assist__k {
  display: block;
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #9ca3af;
}

.dp-assist__v {
  font-size: 11px;
  color: #374151;
  line-height: 1.35;
}

.dp-assist__btn {
  flex-shrink: 0;
  border: 1px solid #e5e7eb;
  background: #fff;
  border-radius: 8px;
  padding: 6px 10px;
  font-size: 11px;
  font-weight: 600;
  color: #111827;
  cursor: pointer;
  transition: background 0.16s ease;
}

.dp-assist__btn:hover {
  background: #f3f4f6;
}

.dp-node--pulse {
  animation: dp-assign-pulse 0.7s ease-out;
}

@keyframes dp-assign-pulse {
  0% {
    box-shadow: 0 0 0 0 rgba(79, 70, 229, 0.35);
  }
  100% {
    box-shadow: 0 0 0 10px rgba(79, 70, 229, 0);
  }
}
</style>
