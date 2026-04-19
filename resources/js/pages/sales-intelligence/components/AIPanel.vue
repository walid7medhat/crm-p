<template>
  <section class="ai">
    <header class="ai__head">
      <div>
        <h6 class="ai__title">AI mode</h6>
        <p class="ai__sub">Server mode from settings · suggestions via ai/suggest</p>
      </div>
    </header>

    <div class="ai__modes" role="tablist" aria-label="AI mode (read-only)">
      <div
        v-for="m in modes"
        :key="m.id"
        class="ai-mode"
        :class="{ 'ai-mode--on': serverMode === m.id, 'ai-mode--dim': serverMode !== m.id }"
      >
        <span class="ai-mode__dot" />
        <span class="ai-mode__name">{{ m.label }}</span>
        <span class="ai-mode__desc">{{ m.desc }}</span>
      </div>
    </div>

    <div class="ai__card">
      <SiLeadCombobox v-model="leadId" label="Lead" history-key="si:ai-lead" placeholder="Search lead…" />
      <div class="ai__row">
        <button type="button" class="ai-btn" :disabled="loading || !leadId" @click="run">Suggest</button>
        <button type="button" class="ai-btn ai-btn--ghost" :disabled="loading" @click="resetAi">Clear</button>
      </div>

      <div v-if="loading" class="ai__sk">
        <div class="ai__sk-line" />
        <div class="ai__sk-line" />
      </div>

      <div v-else-if="suggestion" class="ai__result">
        <div class="ai__hero">
          <div>
            <p class="ai__label">Suggested agent</p>
            <p class="ai__agent">{{ suggestion.suggested_agent?.name || '—' }}</p>
            <p class="ai__email">{{ suggestion.suggested_agent?.email || '' }}</p>
          </div>
          <div class="ai__prob">
            <p class="ai__label">Close probability</p>
            <p class="ai__pct">{{ suggestion.close_probability ?? '—' }}%</p>
          </div>
        </div>
        <div class="ai__bullets">
          <p class="ai__label">Reasoning</p>
          <ul>
            <li v-for="(b, i) in bullets" :key="i">{{ b }}</li>
          </ul>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { salesIntelligenceApi } from '@/services/salesIntelligenceApi'
import SiLeadCombobox from './SiLeadCombobox.vue'

const props = defineProps({
  serverMode: { type: String, default: 'hybrid' },
})

const modes = [
  { id: 'rules_only', label: 'Rules only', desc: 'Deterministic routing' },
  { id: 'ai_assisted', label: 'AI assisted', desc: 'Heuristic boosters' },
  { id: 'hybrid', label: 'Hybrid', desc: 'Balanced (default)' },
]

const leadId = ref(null)
const loading = ref(false)
const suggestion = ref(null)

const bullets = computed(() => {
  const r = suggestion.value?.rationale
  if (!r || typeof r !== 'object') return []
  const lines = []
  if (r.lead_source) lines.push(`Lead source context: ${r.lead_source}.`)
  if (r.budget != null && r.budget !== '') lines.push(`Budget signal considered (${r.budget}).`)
  if (r.top_match_boost != null) lines.push(`Match boost factor: ${Number(r.top_match_boost).toFixed(2)}.`)
  if (!lines.length) lines.push('Heuristic model scored eligible agents with attendance & load caps.')
  return lines
})

async function run() {
  loading.value = true
  suggestion.value = null
  try {
    suggestion.value = await salesIntelligenceApi.aiSuggest({ lead_id: leadId.value })
  } finally {
    loading.value = false
  }
}

function resetAi() {
  leadId.value = null
  suggestion.value = null
}
</script>

<style scoped>
.ai__head {
  margin-bottom: 8px;
}

.ai__title {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
  color: #111827;
}

.ai__sub {
  margin: 2px 0 0;
  font-size: 11px;
  color: #6b7280;
}

.ai__modes {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
  margin-bottom: 10px;
}

@media (max-width: 720px) {
  .ai__modes {
    grid-template-columns: 1fr;
  }
}

.ai-mode {
  border-radius: 8px;
  padding: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  transition: border-color 0.12s ease, opacity 0.12s ease;
}

.ai-mode--on {
  border-color: #d1d5db;
  background: #fafafa;
}

.ai-mode--dim {
  opacity: 0.55;
}

.ai-mode__dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 999px;
  background: #6366f1;
  margin-bottom: 6px;
}

.ai-mode__name {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: #111827;
}

.ai-mode__desc {
  display: block;
  margin-top: 2px;
  font-size: 11px;
  color: #6b7280;
}

.ai__card {
  border-radius: 8px;
  padding: 8px;
  border: 1px solid #e5e7eb;
  background: #fafafa;
}

.ai__row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 8px;
  align-items: center;
}

.ai-btn {
  border: none;
  border-radius: 8px;
  padding: 8px 14px;
  font-weight: 600;
  font-size: 12px;
  cursor: pointer;
  color: #fff;
  background: #111827;
  transition: opacity 0.12s ease;
}

.ai-btn--ghost {
  background: #fff;
  color: #374151;
  border: 1px solid #e5e7eb;
}

.ai-btn--ghost:hover:not(:disabled) {
  background: #f9fafb;
}

.ai-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.ai__hero {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 8px;
  margin-top: 8px;
}

.ai__label {
  margin: 0;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #6b7280;
}

.ai__agent {
  margin: 2px 0 0;
  font-size: 14px;
  font-weight: 700;
  color: #111827;
}

.ai__email {
  margin: 2px 0 0;
  font-size: 11px;
  color: #6b7280;
}

.ai__pct {
  margin: 2px 0 0;
  font-size: 20px;
  font-weight: 700;
  color: #059669;
}

.ai__bullets ul {
  margin: 4px 0 0;
  padding-left: 16px;
  color: #374151;
  font-size: 12px;
  line-height: 1.45;
}

.ai__sk {
  margin-top: 8px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.ai__sk-line {
  height: 12px;
  border-radius: 8px;
  background: #e5e7eb;
  animation: ai-pulse 0.9s ease-in-out infinite alternate;
}

@keyframes ai-pulse {
  to {
    opacity: 0.55;
  }
}
</style>
