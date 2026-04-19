<template>
  <div class="si-cb" :class="{ 'si-cb--invalid': !!error }">
    <span v-if="label" class="si-cb__lbl">{{ label }}</span>
    <VueSelect
      class="si-cb__vs"
      :model-value="modelValue"
      :options="mergedOptions"
      :label="labelKey"
      :reduce="reduceFn"
      :placeholder="placeholder"
      :clearable="clearable"
      :filterable="filterable"
      :disabled="disabled"
      :loading="loading"
      :append-to-body="appendToBody"
      :close-on-select="closeOnSelect"
      :deselect-from-dropdown="false"
      :filter="comboFilter"
      @update:model-value="$emit('update:modelValue', $event)"
      @search="onVsSearch"
      @option:selected="onOptionSelected"
    >
      <template #no-options="{ search }">
        <div class="si-cb__empty">
          <p class="si-cb__empty-t">{{ emptyTitle(search) }}</p>
          <p v-if="emptyHint" class="si-cb__empty-h">{{ emptyHint }}</p>
        </div>
      </template>
    </VueSelect>
    <div v-if="hint && !error" class="si-cb__hint">{{ hint }}</div>
    <div v-if="error" class="si-cb__err" role="alert">{{ error }}</div>
    <div v-if="showReset && hasValue" class="si-cb__actions">
      <button type="button" class="si-cb__reset" @click="onReset">Clear</button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import VueSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import { rankByFuzzy } from '@/composables/siFuzzy'
import { getSiComboboxRecents, pushSiComboboxRecent } from '@/composables/siComboboxHistory'

const props = defineProps({
  modelValue: { type: [String, Number, Object, Boolean, null], default: null },
  options: { type: Array, default: () => [] },
  labelKey: { type: String, default: 'label' },
  valueKey: { type: String, default: 'value' },
  placeholder: { type: String, default: '' },
  label: { type: String, default: '' },
  hint: { type: String, default: '' },
  error: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  clearable: { type: Boolean, default: true },
  filterable: { type: Boolean, default: true },
  appendToBody: { type: Boolean, default: true },
  closeOnSelect: { type: Boolean, default: true },
  showReset: { type: Boolean, default: false },
  /** Persist recent picks (local) when set */
  historyKey: { type: String, default: '' },
  emptyHint: { type: String, default: 'Narrow search or clear filters.' },
})

const emit = defineEmits(['update:modelValue', 'search'])

function reduceFn(opt) {
  if (opt == null) return null
  if (typeof opt === 'object' && props.valueKey in opt) return opt[props.valueKey]
  return opt
}

function optionHaystack(opt) {
  if (opt == null) return ''
  if (typeof opt === 'object') {
    const lab = opt[props.labelKey]
    const v = opt[props.valueKey]
    return `${lab ?? ''} ${v ?? ''}`
  }
  return String(opt)
}

const mergedOptions = computed(() => {
  const base = props.options || []
  if (!props.historyKey) return base
  const raws = getSiComboboxRecents(props.historyKey)
  const recObjs = raws.map((r) => ({
    [props.labelKey]: r.label,
    [props.valueKey]: r.value,
    __recent: true,
  }))
  const vals = new Set(recObjs.map((o) => String(o[props.valueKey])))
  const tail = base.filter((o) => {
    if (o == null) return true
    if (typeof o === 'object' && props.valueKey in o) return !vals.has(String(o[props.valueKey]))
    return !vals.has(String(o))
  })
  return [...recObjs, ...tail]
})

function comboFilter(options, search) {
  const s = (search || '').trim()
  const opts = [...options]
  if (!s) return opts
  return rankByFuzzy(opts, (o) => optionHaystack(o), s)
}

function onVsSearch(q, ld) {
  emit('search', q, ld)
}

function onOptionSelected(opt) {
  if (!props.historyKey || opt == null) return
  const val = reduceFn(opt)
  const label =
    typeof opt === 'object' && opt != null && props.labelKey in opt ? String(opt[props.labelKey] ?? val) : String(val ?? '')
  pushSiComboboxRecent(props.historyKey, { value: val, label })
}

const hasValue = computed(() => {
  const v = props.modelValue
  if (v === null || v === undefined) return false
  if (typeof v === 'string') return v.length > 0
  return true
})

function onReset() {
  emit('update:modelValue', null)
}

function emptyTitle(search) {
  const s = (search || '').trim()
  return s ? `No matches for “${s}”` : 'No options'
}
</script>

<style scoped>
.si-cb {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.si-cb__lbl {
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6b7280;
}

.si-cb__hint {
  font-size: 10px;
  color: #9ca3af;
  line-height: 1.3;
}

.si-cb__err {
  font-size: 10px;
  color: #b91c1c;
  line-height: 1.3;
}

.si-cb--invalid :deep(.vs__dropdown-toggle) {
  border-color: #fca5a5;
}

.si-cb__actions {
  display: flex;
  justify-content: flex-end;
}

.si-cb__reset {
  border: none;
  background: transparent;
  color: #6b7280;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  padding: 2px 0;
  text-decoration: underline;
  text-underline-offset: 2px;
}

.si-cb__reset:hover {
  color: #111827;
}

.si-cb__reset:focus-visible {
  outline: none;
  box-shadow: var(--si-focus-ring, 0 0 0 2px #fff, 0 0 0 4px #d4d4d4);
  border-radius: 4px;
}

.si-cb__empty {
  padding: 10px 12px;
}

.si-cb__empty-t {
  margin: 0;
  font-size: 12px;
  font-weight: 600;
  color: #374151;
}

.si-cb__empty-h {
  margin: 4px 0 0;
  font-size: 11px;
  color: #9ca3af;
  line-height: 1.35;
}

.si-cb :deep(.vs__dropdown-toggle) {
  min-height: 32px;
  padding: 2px 8px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  font-size: 12px;
  transition:
    border-color var(--si-ease, 0.16s ease),
    box-shadow var(--si-ease, 0.16s ease);
}

.si-cb :deep(.vs__dropdown-toggle:focus-within) {
  border-color: #a3a3a3;
  box-shadow: var(--si-focus-ring, 0 0 0 2px #fff, 0 0 0 4px #d4d4d4);
}

.si-cb :deep(.vs__selected-options) {
  padding: 0;
}

.si-cb :deep(.vs__search) {
  margin: 0;
  padding: 2px 0;
  font-size: 12px;
}

.si-cb :deep(.vs__actions) {
  padding-top: 0;
}

.si-cb :deep(.vs__dropdown-menu) {
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
  font-size: 12px;
  max-height: 240px;
}

.si-cb :deep(.vs__dropdown-option) {
  padding: 6px 10px;
}

.si-cb :deep(.vs__dropdown-option--highlight) {
  background: #f3f4f6;
  color: #111827;
}

.si-cb :deep(.vs__clear) {
  fill: #9ca3af;
}

.si-cb :deep(.vs--loading .vs__spinner) {
  border-color: #e5e7eb;
  border-right-color: #111827;
}
</style>
