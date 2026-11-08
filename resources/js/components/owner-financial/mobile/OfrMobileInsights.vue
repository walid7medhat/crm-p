<template>
  <section class="om-insights">
    <button type="button" class="om-insights-toggle" @click="expanded = !expanded">
      <span>
        <iconify-icon icon="lucide:chart-column" width="15" height="15" />
        Insights
      </span>
      <iconify-icon :icon="expanded ? 'lucide:chevron-up' : 'lucide:chevron-down'" width="16" height="16" />
    </button>

    <div v-if="expanded" class="om-insights-body">
      <div v-if="loading" class="om-insights-loading"><span class="om-spinner" /> Loading insights…</div>
      <div v-else-if="error" class="om-insights-err">
        {{ error }}
        <button type="button" class="om-link" @click="$emit('retry')">Retry</button>
      </div>
      <template v-else-if="insights">
        <div class="om-tabs">
          <button
            v-for="t in TABS"
            :key="t.key"
            type="button"
            class="om-tab"
            :class="{ 'is-on': tab === t.key }"
            @click="tab = t.key"
          >{{ t.label }}</button>
        </div>

        <div v-if="!activeRows.length" class="om-empty">No data for this view.</div>
        <ul v-else class="om-bars">
          <li v-for="row in activeRows" :key="row.key" class="om-bar-row">
            <div class="om-bar-head">
              <span class="om-bar-lab">{{ row.label }}</span>
              <strong class="om-bar-val">
                <template v-if="tab === 'by_source'">{{ aedShort(row.gross_commission) }}</template>
                <template v-else>{{ aedShort(row.net_revenue) }}</template>
              </strong>
            </div>
            <div class="om-bar-track">
              <div class="om-bar-fill" :style="{ width: barPct(row) + '%' }" />
            </div>
            <div class="om-bar-meta">
              <template v-if="tab === 'by_source'">
                {{ row.deals }} deals
                · Value {{ aedShort(row.deal_amount) }}
                · {{ Number(row.share_pct || 0).toFixed(1) }}% of commission
              </template>
              <template v-else>
                {{ row.deals }} deals · Gross {{ aedShort(row.gross_commission) }}
              </template>
            </div>
          </li>
        </ul>
      </template>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { aedShort } from '@/components/owner-financial/format.js'

const props = defineProps({
  insights: { type: Object, default: null },
  loading: Boolean,
  error: { type: String, default: '' },
  forceOpen: Boolean,
  preferredTab: { type: String, default: '' },
})

const emit = defineEmits(['open', 'retry'])

const TABS = [
  { key: 'by_source', label: 'Sources' },
  { key: 'by_month', label: 'Monthly' },
  { key: 'by_deal_type', label: 'Type' },
  { key: 'by_agent', label: 'Agents' },
  { key: 'by_branch', label: 'Branches' },
]

const expanded = ref(false)
const tab = ref('by_source')

watch(() => props.forceOpen, (v) => {
  if (v) {
    expanded.value = true
    if (props.preferredTab) tab.value = props.preferredTab
  }
})

watch(expanded, (v) => {
  if (v) emit('open')
})

watch(() => props.preferredTab, (v) => {
  if (v) tab.value = v
})

const activeRows = computed(() => props.insights?.[tab.value] || [])

const maxVal = computed(() => {
  const key = tab.value === 'by_source' ? 'gross_commission' : 'net_revenue'
  const vals = activeRows.value.map((r) => Math.abs(Number(r[key]) || 0))
  return Math.max(1, ...vals)
})

function barPct(row) {
  const key = tab.value === 'by_source' ? 'gross_commission' : 'net_revenue'
  return Math.max(2, Math.round((Math.abs(Number(row[key]) || 0) / maxVal.value) * 100))
}

defineExpose({
  open(preferred) {
    if (preferred) tab.value = preferred
    expanded.value = true
  },
})
</script>

<style scoped>
.om-insights {
  background: #fff;
  border: 1px solid var(--ofr-border, #e7dcec);
  border-radius: 12px;
  overflow: hidden;
}
.om-insights-toggle {
  width: 100%; display: flex; align-items: center; justify-content: space-between;
  border: none; background: transparent; padding: 11px 12px;
  font-size: var(--om-fs-title, 11px) !important; font-weight: 650 !important;
  color: var(--ofr-ink, #3f2148); cursor: pointer;
}
.om-insights-toggle span { display: inline-flex; align-items: center; gap: 6px; }
.om-insights-body { padding: 0 12px 12px; }
.om-insights-loading, .om-insights-err, .om-empty {
  display: flex; align-items: center; gap: 8px;
  font-size: var(--om-fs-body, 12px); color: var(--ofr-text-2, #6b6678); padding: 8px 0;
}
.om-insights-err { color: #b3261e; }
.om-link { margin-left: auto; border: none; background: transparent; color: #b3261e; text-decoration: underline; font-weight: 600; cursor: pointer; }
.om-spinner {
  width: 14px; height: 14px; border-radius: 50%;
  border: 2px solid var(--ofr-border, #e7dcec); border-top-color: var(--ofr-p, #733E87);
  animation: om-spin .8s linear infinite;
}
@keyframes om-spin { to { transform: rotate(360deg); } }
.om-tabs {
  display: flex; gap: 4px; overflow-x: auto; padding-bottom: 8px;
  -webkit-overflow-scrolling: touch; scrollbar-width: none;
}
.om-tabs::-webkit-scrollbar { display: none; }
.om-tab {
  flex-shrink: 0; height: 28px; padding: 0 10px; border-radius: 999px;
  border: 1px solid var(--ofr-border, #e7dcec); background: #fff;
  font-size: var(--om-fs-body, 12px); font-weight: 600; color: var(--ofr-text-2, #6b6678); cursor: pointer;
}
.om-tab.is-on { background: var(--ofr-p, #733E87); border-color: var(--ofr-p, #733E87); color: #fff; }
.om-bars { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.om-bar-head { display: flex; justify-content: space-between; gap: 8px; margin-bottom: 4px; }
.om-bar-lab {
  font-size: var(--om-fs-title, 11px) !important; font-weight: 650; color: var(--ofr-text, #1e1b2e);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0;
}
.om-bar-val {
  font-size: var(--om-fs-body, 12px) !important; font-weight: 700; color: var(--ofr-ink, #3f2148);
  font-variant-numeric: tabular-nums; white-space: nowrap;
}
.om-bar-track {
  height: 6px; border-radius: 999px; background: var(--ofr-soft, #f7f2f8); overflow: hidden;
}
.om-bar-fill {
  height: 100%; border-radius: 999px;
  background: linear-gradient(90deg, #733E87, #9a5fb0);
  transition: width .25s ease;
}
.om-bar-meta {
  margin-top: 3px; font-size: var(--om-fs-body, 12px) !important;
  color: var(--ofr-text-3, #8b8798); line-height: 1.35;
}
</style>
