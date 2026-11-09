<template>
  <Teleport to="body">
    <div v-if="open" class="om-sheet-root" @keydown.esc="$emit('close')">
      <button type="button" class="om-sheet-backdrop" aria-label="Close filters" @click="$emit('close')" />
      <div class="om-sheet" role="dialog" aria-modal="true" :aria-label="sheetTitle">
        <div class="om-sheet-handle" />
        <header class="om-sheet-head">
          <h3>{{ sheetTitle }}</h3>
          <button type="button" class="om-icon-btn" aria-label="Close" @click="$emit('close')">
            <iconify-icon icon="lucide:x" width="18" height="18" />
          </button>
        </header>

        <div class="om-sheet-body">
          <!-- Dates -->
          <div v-show="panel === 'all' || panel === 'dates'" class="om-section">
            <div class="om-section-lab">
              Period
              <span class="om-period-pill">{{ periodPreview }}</span>
            </div>
            <div class="om-chips">
              <button
                type="button"
                class="om-chip"
                :class="{ 'is-on': activePreset === 'all' }"
                @click="clearDates"
              >All time</button>
              <button
                v-for="p in DATE_PRESETS"
                :key="p.key"
                type="button"
                class="om-chip"
                :class="{ 'is-on': activePreset === p.key }"
                @click="applyPreset(p.key)"
              >{{ p.label }}</button>
            </div>

            <div class="om-section-lab om-section-lab--sub">Custom range</div>
            <div class="om-pair">
              <label class="om-field">
                <span>From</span>
                <AdvancedDatePicker
                  v-model="local.date_from"
                  date-only
                  dob-layout
                  :block-future-dates="false"
                  placeholder="From"
                  class="om-date"
                  @update:model-value="onCustomDate"
                />
              </label>
              <label class="om-field">
                <span>To</span>
                <AdvancedDatePicker
                  v-model="local.date_to"
                  date-only
                  dob-layout
                  :block-future-dates="false"
                  placeholder="To"
                  class="om-date"
                  @update:model-value="onCustomDate"
                />
              </label>
            </div>
          </div>

          <!-- Sources -->
          <div v-show="panel === 'all' || panel === 'sources'" class="om-section">
            <div class="om-section-lab">
              Lead / deal source
              <button type="button" class="om-text-btn" @click="clearSources">All sources</button>
            </div>
            <p class="om-hint">Select one or more sources from your actual deal data.</p>
            <div v-if="!(options.sources || []).length" class="om-empty-soft">No sources recorded yet.</div>
            <div v-else class="om-source-list">
              <button
                v-for="s in options.sources"
                :key="s"
                type="button"
                class="om-source"
                :class="{ 'is-on': isSourceOn(s) }"
                @click="toggleSource(s)"
              >
                <span class="om-check" aria-hidden="true">
                  <iconify-icon v-if="isSourceOn(s)" icon="lucide:check" width="12" height="12" />
                </span>
                <span class="om-source-name">{{ s }}</span>
              </button>
            </div>
          </div>

          <!-- Rest of filters (full panel only) -->
          <template v-if="panel === 'all'">
            <div class="om-section">
              <div class="om-section-lab">Quick</div>
              <div class="om-chips">
                <button type="button" class="om-chip" :class="{ 'is-on': local.deal_status === 'closed' }" @click="local.deal_status = 'closed'">Closed</button>
                <button type="button" class="om-chip" :class="{ 'is-on': local.deal_status === 'all' }" @click="local.deal_status = 'all'">All deals</button>
                <button type="button" class="om-chip" :class="{ 'is-on': local.data_status === 'missing' }" @click="local.data_status = local.data_status === 'missing' ? 'all' : 'missing'">Missing data</button>
              </div>
            </div>

            <button type="button" class="om-advanced-toggle" @click="advanced = !advanced">
              <span>Advanced filters</span>
              <iconify-icon :icon="advanced ? 'lucide:chevron-up' : 'lucide:chevron-down'" width="16" height="16" />
            </button>

            <div v-show="advanced" class="om-advanced">
              <label class="om-field">
                <span>Agent</span>
                <select v-model="local.agent_id" class="om-select">
                  <option value="">All agents</option>
                  <option v-for="a in options.agents" :key="a.id" :value="String(a.id)">{{ a.name }}</option>
                </select>
              </label>
              <label class="om-field">
                <span>Client name or phone</span>
                <input v-model="local.client" type="search" class="om-input" placeholder="Name or phone" />
              </label>
              <label class="om-field">
                <span>Deal number</span>
                <input v-model="local.deal_number" type="search" class="om-input" placeholder="Deal number" />
              </label>
              <label class="om-field">
                <span>Branch</span>
                <select v-model="local.branch_id" class="om-select">
                  <option value="">All branches</option>
                  <option v-for="b in options.branches" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
                </select>
              </label>
              <label class="om-field">
                <span>Deal type</span>
                <select v-model="local.deal_type" class="om-select">
                  <option value="">All types</option>
                  <option value="sale">Sale</option>
                  <option v-for="t in options.deal_types" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
              </label>
              <div class="om-field">
                <span>Deal amount (AED)</span>
                <div class="om-pair">
                  <input v-model="local.min_amount" type="number" min="0" class="om-input" placeholder="Min" />
                  <input v-model="local.max_amount" type="number" min="0" class="om-input" placeholder="Max" />
                </div>
              </div>
              <div class="om-field">
                <span>Gross commission (AED)</span>
                <div class="om-pair">
                  <input v-model="local.min_commission" type="number" min="0" class="om-input" placeholder="Min" />
                  <input v-model="local.max_commission" type="number" min="0" class="om-input" placeholder="Max" />
                </div>
              </div>
              <label class="om-field">
                <span>Financial data</span>
                <select v-model="local.data_status" class="om-select">
                  <option value="all">All</option>
                  <option value="complete">Complete only</option>
                  <option value="missing">Missing data only</option>
                </select>
              </label>
            </div>
          </template>
        </div>

        <footer class="om-sheet-foot">
          <button type="button" class="om-btn om-btn--ghost" @click="onReset">Reset</button>
          <button type="button" class="om-btn om-btn--primary" @click="apply">Apply</button>
        </footer>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import AdvancedDatePicker from '@/components/shared/AdvancedDatePicker.vue'
import {
  DATE_PRESETS,
  datePresetRange,
  detectDatePreset,
  formatPeriodLabel,
} from './useOfrMobile.js'

const props = defineProps({
  open: Boolean,
  /** 'all' | 'dates' | 'sources' — focused sheet from the quick bar */
  focus: { type: String, default: 'all' },
  draft: { type: Object, required: true },
  options: { type: Object, required: true },
})

const emit = defineEmits(['close', 'apply', 'reset', 'quick-apply'])

const local = reactive({})
const advanced = ref(false)
const activePreset = ref('all')
const panel = ref('all')

const sheetTitle = computed(() => {
  if (panel.value === 'dates') return 'Date period'
  if (panel.value === 'sources') return 'Sources'
  return 'Filters'
})

const periodPreview = computed(() => formatPeriodLabel(local.date_from, local.date_to))

watch(() => props.open, (open) => {
  if (!open) return
  Object.assign(local, {
    ...props.draft,
    source: [...(props.draft.source || [])],
  })
  activePreset.value = detectDatePreset(local.date_from, local.date_to)
  panel.value = props.focus === 'dates' || props.focus === 'sources' ? props.focus : 'all'
  if (panel.value === 'all') advanced.value = false
}, { immediate: true })

watch(() => props.focus, (f) => {
  if (props.open && (f === 'dates' || f === 'sources' || f === 'all')) panel.value = f
})

function applyPreset(key) {
  activePreset.value = key
  Object.assign(local, datePresetRange(key))
  if (panel.value === 'dates') {
    emit('quick-apply', payload())
    emit('close')
  }
}

function clearDates() {
  activePreset.value = 'all'
  local.date_from = ''
  local.date_to = ''
  if (panel.value === 'dates') {
    emit('quick-apply', payload())
    emit('close')
  }
}

function onCustomDate() {
  activePreset.value = detectDatePreset(local.date_from, local.date_to)
}

function isSourceOn(s) {
  return (local.source || []).includes(s)
}

function toggleSource(s) {
  const cur = [...(local.source || [])]
  const i = cur.indexOf(s)
  if (i >= 0) cur.splice(i, 1)
  else cur.push(s)
  local.source = cur
}

function clearSources() {
  local.source = []
}

function payload() {
  return { ...local, source: [...(local.source || [])] }
}

function apply() {
  emit('apply', payload())
}

function onReset() {
  if (panel.value === 'dates') {
    clearDates()
    return
  }
  if (panel.value === 'sources') {
    clearSources()
    emit('apply', payload())
    return
  }
  emit('reset')
}
</script>

<style scoped>
.om-sheet-root {
  position: fixed; inset: 0; z-index: 13000;
  display: flex; align-items: flex-end; justify-content: center;
}
.om-sheet-backdrop {
  position: absolute; inset: 0; border: none; background: rgba(30, 27, 46, 0.45);
  backdrop-filter: blur(2px);
}
.om-sheet {
  position: relative; width: 100%; max-width: 560px; max-height: min(92vh, 720px);
  background: #fff; border-radius: 18px 18px 0 0;
  display: flex; flex-direction: column;
  box-shadow: 0 -12px 40px rgba(115, 62, 135, 0.18);
  animation: om-up .22s ease;
}
@keyframes om-up {
  from { transform: translateY(24px); opacity: .6; }
  to { transform: none; opacity: 1; }
}
.om-sheet-handle {
  width: 36px; height: 4px; border-radius: 999px; background: #e7dcec;
  margin: 8px auto 0;
}
.om-sheet-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 14px 8px;
}
.om-sheet-head h3 {
  margin: 0 !important;
  font-size: 11px !important;
  font-weight: 650 !important;
  color: #3f2148 !important;
  letter-spacing: 0 !important;
  text-transform: none !important;
  line-height: 1.25 !important;
}
.om-icon-btn {
  width: 36px; height: 36px; border: none; border-radius: 10px;
  background: #f7f2f8; color: #733E87; display: grid; place-items: center; cursor: pointer;
}
.om-sheet-body {
  flex: 1; overflow: auto; padding: 4px 14px 12px;
  -webkit-overflow-scrolling: touch;
}
.om-section { margin-bottom: 14px; }
.om-section-lab {
  display: flex; align-items: center; justify-content: space-between; gap: 8px;
  font-size: 10.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
  color: #8b8798; margin-bottom: 8px;
}
.om-section-lab--sub { margin-top: 10px; text-transform: none; letter-spacing: 0; font-size: 11.5px; }
.om-period-pill {
  font-size: 10.5px; font-weight: 700; letter-spacing: 0; text-transform: none;
  color: #733E87; background: #f7f2f8; border: 1px solid #e7dcec;
  border-radius: 999px; padding: 2px 8px; max-width: 60%;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.om-text-btn {
  border: none; background: transparent; color: #733E87;
  font-size: 11px; font-weight: 700; letter-spacing: 0; text-transform: none; cursor: pointer;
}
.om-hint { margin: -2px 0 8px; font-size: 11.5px; color: #8b8798; }
.om-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
.om-chip {
  height: 32px; padding: 0 12px; border-radius: 999px;
  border: 1px solid #e7dcec; background: #fff; color: #6b6678;
  font-size: 12px; font-weight: 600; cursor: pointer;
}
.om-chip.is-on {
  background: #733E87; border-color: #733E87; color: #fff;
}
.om-pair { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.om-source-list { display: flex; flex-direction: column; gap: 6px; }
.om-source {
  width: 100%; display: flex; align-items: center; gap: 10px;
  border: 1px solid #e7dcec; background: #fff; border-radius: 11px;
  padding: 10px 12px; text-align: left; font: inherit; color: inherit; cursor: pointer;
}
.om-source.is-on {
  border-color: #733E87; background: #f7f2f8;
}
.om-check {
  width: 20px; height: 20px; border-radius: 6px; flex-shrink: 0;
  border: 1.5px solid #d4c0de; display: grid; place-items: center;
  color: #fff; background: #fff;
}
.om-source.is-on .om-check {
  background: #733E87; border-color: #733E87;
}
.om-source-name { font-size: 13px; font-weight: 650; color: #1e1b2e; }
.om-empty-soft {
  padding: 12px; border-radius: 10px; background: #f7f2f8; color: #8b8798; font-size: 12.5px;
}
.om-advanced-toggle {
  width: 100%; display: flex; align-items: center; justify-content: space-between;
  border: none; background: #f7f2f8; border-radius: 10px; padding: 10px 12px;
  font-size: 13px; font-weight: 700; color: #3f2148; cursor: pointer; margin-bottom: 10px;
}
.om-advanced { display: flex; flex-direction: column; gap: 10px; }
.om-field { display: flex; flex-direction: column; gap: 5px; margin: 0; }
.om-field > span { font-size: 11.5px; font-weight: 600; color: #6b6678; }
.om-input, .om-select {
  height: 40px; width: 100%; box-sizing: border-box;
  border: 1px solid #e7dcec; border-radius: 10px; padding: 0 10px;
  font-size: 13px; color: #1e1b2e; background: #fff;
}
.om-input:focus, .om-select:focus {
  outline: none; border-color: #733E87; box-shadow: 0 0 0 3px rgba(115, 62, 135, 0.14);
}
.om-date :deep(.advanced-date-trigger) {
  min-height: 40px !important; border: 1px solid #e7dcec !important; border-radius: 10px !important;
}
.om-sheet-foot {
  display: grid; grid-template-columns: 1fr 1.4fr; gap: 8px;
  padding: 10px 14px calc(16px + env(safe-area-inset-bottom, 0px));
  border-top: 1px solid #e7dcec; background: #fff;
  flex-shrink: 0;
  position: relative;
  z-index: 2;
}
.om-btn {
  height: 44px; border-radius: 12px; font-size: 14px; font-weight: 700; cursor: pointer;
  border: 1px solid transparent; display: inline-flex; align-items: center; justify-content: center;
}
.om-btn--ghost { background: #fff; color: #733E87; border-color: #e7dcec; }
.om-btn--primary {
  background: linear-gradient(135deg, #733E87, #9a5fb0); color: #fff;
  box-shadow: 0 4px 12px rgba(115, 62, 135, 0.25);
}
</style>
