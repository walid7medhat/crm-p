<template>
  <div class="asp">
    <!-- Structure diagram -->
    <aside class="asp__diagram">
      <div class="asp__diagram-head">
        <Icon icon="lucide:git-branch" class="asp__diagram-icon" />
        <div>
          <h3>Score formula</h3>
          <p>How the 0–100 AI score is built from your weights</p>
        </div>
      </div>

      <div class="asp__tree">
        <div class="asp__tree-root">
          <span class="asp__tree-label">Overall AI Score</span>
          <span class="asp__tree-val">100 pts</span>
        </div>

        <div v-for="group in structureGroups" :key="group.key" class="asp__tree-branch">
          <div class="asp__tree-node" :style="{ '--branch-color': group.color }">
            <span class="asp__tree-dot" />
            <span class="asp__tree-label">{{ group.label }}</span>
            <span class="asp__tree-pct" :class="{ 'asp__tree-pct--warn': group.totalPct !== 100 && group.isWeightGroup }">
              {{ group.totalPct }}%
            </span>
          </div>
          <div v-if="group.children?.length" class="asp__tree-children">
            <div v-for="child in group.children" :key="child.key" class="asp__tree-leaf">
              <span>{{ child.label }}</span>
              <span class="asp__tree-leaf-pct">{{ child.pct }}%</span>
            </div>
          </div>
        </div>
      </div>

      <div v-if="weightWarnings.length" class="asp__warnings">
        <Icon icon="lucide:alert-triangle" />
        <ul>
          <li v-for="w in weightWarnings" :key="w">{{ w }}</li>
        </ul>
      </div>

      <div class="asp__defaults">
        <p class="asp__defaults-title">System defaults</p>
        <p class="asp__defaults-hint">Reset restores the auto-configured structure above. Recalculate after saving.</p>
      </div>
    </aside>

    <!-- Editable rules -->
    <div class="asp__editor">
      <header class="asp__editor-head">
        <div>
          <h3>Customize weights</h3>
          <p>Drag sliders or type values · changes apply after Save + Recalculate</p>
        </div>
        <div class="asp__editor-actions">
          <button type="button" class="asp__btn asp__btn--ghost" :disabled="saving" @click="$emit('reset')">
            <Icon icon="lucide:rotate-ccw" />
            Reset defaults
          </button>
          <button type="button" class="asp__btn asp__btn--primary" :disabled="saving || weightWarnings.length > 0" @click="$emit('save')">
            <Icon icon="lucide:save" />
            {{ saving ? 'Saving…' : 'Save rules' }}
          </button>
        </div>
      </header>

      <div v-for="section in editorSections" :key="section.key" class="asp__section">
        <header class="asp__section-head">
          <span class="asp__section-icon" :style="{ background: section.color }">
            <Icon :icon="section.icon" />
          </span>
          <div>
            <h4>{{ section.title }}</h4>
            <p>{{ section.hint }}</p>
          </div>
          <span v-if="section.isWeightGroup" class="asp__section-total" :class="{ 'asp__section-total--ok': sectionTotal(section.key) === 100 }">
            {{ sectionTotal(section.key) }}% total
          </span>
        </header>

        <div class="asp__rules-grid">
          <article v-for="rule in section.rules" :key="rule.id" class="asp__rule">
            <div class="asp__rule-top">
              <span class="asp__rule-name">{{ rule.label }}</span>
              <span class="asp__rule-val">{{ formatDisplay(rule) }}</span>
            </div>
            <p v-if="rule.description" class="asp__rule-desc">{{ rule.description }}</p>

            <template v-if="rule.rule_group === 'response_sla'">
              <div class="asp__sla-table">
                <div class="asp__sla-head">
                  <span>Within (min)</span>
                  <span>Score</span>
                </div>
                <div v-for="(row, i) in (rule.thresholds || [])" :key="i" class="asp__sla-row">
                  <span>{{ row.minutes >= 99999 ? '∞' : row.minutes }}</span>
                  <span>{{ row.score }}</span>
                </div>
              </div>
              <p class="asp__sla-note">SLA tiers are system-managed. Contact admin to change.</p>
            </template>

            <template v-else>
              <input
                v-model.number="rule.weight"
                type="range"
                class="asp__range"
                :min="0"
                :max="section.isWeightGroup ? 1 : 100"
                :step="section.isWeightGroup ? 0.01 : 1"
                :style="{ '--range-color': section.color }"
              />
              <input
                v-if="section.isWeightGroup"
                v-model.number="rule.weight"
                type="number"
                class="asp__num-input"
                min="0"
                max="1"
                step="0.01"
              />
              <input
                v-else
                v-model.number="rule.weight"
                type="number"
                class="asp__num-input"
                min="0"
                max="100"
                step="1"
              />
            </template>
          </article>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Icon } from '@iconify/vue'

const props = defineProps({
  rules: { type: Array, default: () => [] },
  saving: { type: Boolean, default: false },
})

defineEmits(['save', 'reset'])

const SECTION_META = {
  overall: { title: 'Overall composition', hint: 'How each area contributes to the final 0–100 score', icon: 'lucide:pie-chart', color: '#6366f1', isWeightGroup: true },
  behavior: { title: 'Behavior sub-score', hint: 'Weights inside the behavior composite (must sum to 100%)', icon: 'lucide:activity', color: '#8b5cf6', isWeightGroup: true },
  status: { title: 'Status thresholds', hint: 'Minimum overall score for each status label', icon: 'lucide:signal', color: '#10b981', isWeightGroup: false },
  risk: { title: 'Risk thresholds', hint: 'Risk score cutoffs for Low / Medium / High', icon: 'lucide:shield-alert', color: '#f59e0b', isWeightGroup: false },
}

const GROUP_COLORS = {
  overall: '#6366f1',
  behavior: '#8b5cf6',
  status: '#10b981',
  risk: '#f59e0b',
}

function rulesInGroup(key) {
  return (props.rules || []).filter((r) => r.rule_group === key)
}

function sectionTotal(key) {
  const rules = rulesInGroup(key)
  if (!rules.length) return 0
  const meta = SECTION_META[key]
  if (!meta?.isWeightGroup) return 0
  return Math.round(rules.reduce((s, r) => s + Number(r.weight || 0), 0) * 100)
}

const editorSections = computed(() =>
  Object.entries(SECTION_META).map(([key, meta]) => ({
    key,
    ...meta,
    rules: rulesInGroup(key),
  })).filter((s) => s.rules.length)
)

const structureGroups = computed(() => {
  const groups = ['overall', 'behavior', 'status', 'risk'].map((key) => {
    const rules = rulesInGroup(key)
    const meta = SECTION_META[key]
    const isWeightGroup = meta?.isWeightGroup ?? false
    const totalPct = isWeightGroup ? sectionTotal(key) : 0
    return {
      key,
      label: meta?.title || key,
      color: GROUP_COLORS[key] || '#6366f1',
      isWeightGroup,
      totalPct,
      children: isWeightGroup
        ? rules.map((r) => ({
            key: r.factor_key,
            label: r.label,
            pct: Math.round(Number(r.weight || 0) * 100),
          }))
        : rules.map((r) => ({
            key: r.factor_key,
            label: r.label,
            pct: Math.round(Number(r.weight || 0)),
          })),
    }
  })
  return groups.filter((g) => g.children.length)
})

const weightWarnings = computed(() => {
  const warnings = []
  const overall = sectionTotal('overall')
  const behavior = sectionTotal('behavior')
  if (overall > 0 && overall !== 100) {
    warnings.push(`Overall weights sum to ${overall}% — should be 100%`)
  }
  if (behavior > 0 && behavior !== 100) {
    warnings.push(`Behavior weights sum to ${behavior}% — should be 100%`)
  }
  return warnings
})

function formatDisplay(rule) {
  if (rule.rule_group === 'overall' || rule.rule_group === 'behavior') {
    return `${Math.round(Number(rule.weight || 0) * 100)}%`
  }
  return String(Math.round(Number(rule.weight || 0)))
}
</script>

<style scoped>
/* Override global CRM heading scales */
.asp :is(h1, h2, h3, h4, h5, h6) {
  font-weight: 600 !important;
  line-height: 1.3 !important;
  letter-spacing: normal !important;
  text-transform: none !important;
}

.asp {
  display: grid;
  grid-template-columns: minmax(260px, 320px) minmax(0, 1fr);
  gap: 1.25rem;
  align-items: start;
}

@media (max-width: 960px) {
  .asp { grid-template-columns: 1fr; }
}

.asp__diagram {
  position: sticky;
  top: 1rem;
  background: linear-gradient(145deg, #1e1b4b 0%, #312e81 50%, #3730a3 100%);
  border-radius: 16px;
  padding: 1.25rem;
  color: #e0e7ff;
  box-shadow: 0 8px 32px rgba(49, 46, 129, 0.35);
}

.asp__diagram-head {
  display: flex;
  gap: 0.75rem;
  margin-bottom: 1.25rem;
}

.asp__diagram-icon {
  font-size: 1.5rem;
  color: #a5b4fc;
  flex-shrink: 0;
}

.asp__diagram-head h3 {
  margin: 0 !important;
  font-size: 0.8125rem !important;
  font-weight: 600 !important;
  color: #ffffff !important;
}

.asp__diagram-head p {
  margin: 0.2rem 0 0;
  font-size: 0.75rem;
  color: #a5b4fc;
  opacity: 0.85;
}

.asp__tree-root {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.6rem 0.75rem;
  background: rgba(255, 255, 255, 0.12);
  border-radius: 10px;
  margin-bottom: 0.75rem;
  font-weight: 600;
  font-size: 0.85rem;
}

.asp__tree-val {
  font-size: 0.75rem;
  color: #c7d2fe;
  font-weight: 500;
}

.asp__tree-branch {
  margin-bottom: 0.65rem;
  padding-left: 0.5rem;
  border-left: 2px solid rgba(165, 180, 252, 0.3);
  margin-left: 0.5rem;
}

.asp__tree-node {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.8rem;
  font-weight: 600;
  margin-bottom: 0.35rem;
}

.asp__tree-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--branch-color, #818cf8);
  flex-shrink: 0;
}

.asp__tree-pct {
  margin-left: auto;
  font-size: 0.72rem;
  font-weight: 700;
  color: #a5b4fc;
  background: rgba(255, 255, 255, 0.1);
  padding: 0.1rem 0.45rem;
  border-radius: 999px;
}

.asp__tree-pct--warn {
  background: rgba(251, 191, 36, 0.25);
  color: #fcd34d;
}

.asp__tree-children {
  padding-left: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.asp__tree-leaf {
  display: flex;
  justify-content: space-between;
  font-size: 0.72rem;
  color: #c7d2fe;
  opacity: 0.9;
}

.asp__tree-leaf-pct {
  color: #a5b4fc;
  font-weight: 600;
}

.asp__warnings {
  display: flex;
  gap: 0.5rem;
  margin-top: 1rem;
  padding: 0.65rem 0.75rem;
  background: rgba(251, 191, 36, 0.15);
  border-radius: 8px;
  font-size: 0.72rem;
  color: #fcd34d;
}

.asp__warnings ul {
  margin: 0;
  padding-left: 1rem;
}

.asp__defaults {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid rgba(255, 255, 255, 0.12);
}

.asp__defaults-title {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 600;
  color: #c7d2fe;
}

.asp__defaults-hint {
  margin: 0.25rem 0 0;
  font-size: 0.68rem;
  color: #a5b4fc;
  opacity: 0.8;
  line-height: 1.4;
}

.asp__editor {
  background: #fff;
  border-radius: 16px;
  border: 1px solid #e5e7eb;
  padding: 1.25rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.asp__editor-head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.25rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #f3f4f6;
}

.asp__editor-head h3 {
  margin: 0 !important;
  font-size: 0.875rem !important;
  font-weight: 600 !important;
  color: #111827 !important;
}

.asp__editor-head p {
  margin: 0.2rem 0 0;
  font-size: 0.78rem;
  color: #6b7280;
}

.asp__editor-actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.asp__btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.5rem 1rem;
  border-radius: 10px;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.15s ease;
}

.asp__btn--ghost {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  color: #374151;
}

.asp__btn--ghost:hover:not(:disabled) {
  background: #f3f4f6;
}

.asp__btn--primary {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  box-shadow: 0 2px 8px rgba(99, 102, 241, 0.35);
}

.asp__btn--primary:hover:not(:disabled) {
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.45);
  transform: translateY(-1px);
}

.asp__btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
  transform: none;
}

.asp__section {
  margin-bottom: 1.5rem;
}

.asp__section-head {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.85rem;
}

.asp__section-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: grid;
  place-items: center;
  color: #fff;
  font-size: 1rem;
  flex-shrink: 0;
}

.asp__section-head h4 {
  margin: 0 !important;
  font-size: 0.8125rem !important;
  font-weight: 600 !important;
  color: #111827 !important;
}

.asp__section-head p {
  margin: 0.1rem 0 0;
  font-size: 0.72rem;
  color: #9ca3af;
}

.asp__section-total {
  margin-left: auto;
  font-size: 0.72rem;
  font-weight: 700;
  padding: 0.25rem 0.6rem;
  border-radius: 999px;
  background: #fef3c7;
  color: #b45309;
}

.asp__section-total--ok {
  background: #d1fae5;
  color: #047857;
}

.asp__rules-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 0.75rem;
}

.asp__rule {
  background: #f9fafb;
  border: 1px solid #f3f4f6;
  border-radius: 12px;
  padding: 0.85rem;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.asp__rule:hover {
  border-color: #e5e7eb;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.asp__rule-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.25rem;
}

.asp__rule-name {
  font-size: 0.82rem;
  font-weight: 600;
  color: #111827;
}

.asp__rule-val {
  font-size: 0.85rem;
  font-weight: 700;
  color: #6366f1;
  font-variant-numeric: tabular-nums;
}

.asp__rule-desc {
  margin: 0 0 0.5rem;
  font-size: 0.68rem;
  color: #9ca3af;
  line-height: 1.35;
}

.asp__range {
  width: 100%;
  height: 6px;
  accent-color: var(--range-color, #6366f1);
  margin-bottom: 0.35rem;
}

.asp__num-input {
  width: 100%;
  padding: 0.35rem 0.5rem;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 0.8rem;
  text-align: center;
  background: #fff;
}

.asp__sla-table {
  font-size: 0.72rem;
  margin-top: 0.35rem;
}

.asp__sla-head,
.asp__sla-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
  padding: 0.25rem 0;
}

.asp__sla-head {
  font-weight: 700;
  color: #6b7280;
  border-bottom: 1px solid #e5e7eb;
}

.asp__sla-note {
  margin: 0.5rem 0 0;
  font-size: 0.65rem;
  color: #9ca3af;
  font-style: italic;
}
</style>
