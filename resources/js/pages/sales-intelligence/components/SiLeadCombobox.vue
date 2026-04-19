<template>
  <div class="si-lc">
    <span v-if="label" class="si-lc__lbl">{{ label }}</span>
    <VueSelect
      class="si-lc__vs"
      :model-value="selectedOption"
      :options="options"
      label="label"
      :reduce="(o) => o.id"
      :placeholder="placeholder"
      :filterable="true"
      :clearable="clearable"
      :disabled="disabled"
      :loading="loading"
      :append-to-body="true"
      :filter="leadComboFilter"
      @update:model-value="onPick"
      @search="onSearch"
      @option:selected="onOptionSelected"
    >
      <template #no-options="{ search }">
        <div class="si-lc__empty">
          <p class="si-lc__empty-t">{{ search?.trim() ? `No leads for “${search.trim()}”` : 'Start typing' }}</p>
          <p class="si-lc__empty-h">Search by name, company, or lead #. Recent picks appear when the field is empty.</p>
        </div>
      </template>
    </VueSelect>
    <div v-if="error" class="si-lc__err" role="alert">{{ error }}</div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import VueSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import api from '@/plugins/axios'
import { rankByFuzzy } from '@/composables/siFuzzy'
import { getSiComboboxRecents, pushSiComboboxRecent } from '@/composables/siComboboxHistory'
import { flattenLeadsFromStages, unwrapLeadsResponse } from '../siSearchUtils'

const props = defineProps({
  modelValue: { type: [Number, null], default: null },
  label: { type: String, default: 'Lead' },
  placeholder: { type: String, default: 'Search by name or number…' },
  disabled: { type: Boolean, default: false },
  clearable: { type: Boolean, default: true },
  error: { type: String, default: '' },
  historyKey: { type: String, default: 'si:lead-combobox' },
})

const emit = defineEmits(['update:modelValue'])

const options = ref([])
const loading = ref(false)
let timer = null

const selectedOption = computed(() => {
  const id = props.modelValue
  if (id == null) return null
  return options.value.find((o) => o.id === id) || { id, label: `Lead #${id}`, subtitle: '' }
})

function recentsToOptions() {
  return getSiComboboxRecents(props.historyKey)
    .map((r) => {
      const id = Number(r.value)
      if (!Number.isFinite(id)) return null
      return { id, label: r.label, subtitle: '', __recent: true }
    })
    .filter(Boolean)
}

function leadComboFilter(opts, search) {
  const s = (search || '').trim()
  if (!s) return [...opts]
  return rankByFuzzy(opts, (o) => `${o.label || ''} ${o.subtitle || ''} ${o.id}`, s)
}

watch(
  () => props.modelValue,
  (id) => {
    if (id == null) return
    if (!options.value.some((o) => o.id === id)) {
      options.value = [{ id, label: `Lead #${id}`, subtitle: '' }, ...options.value].slice(0, 20)
    }
  }
)

function onPick(id) {
  emit('update:modelValue', id == null ? null : Number(id))
}

function onOptionSelected(opt) {
  if (opt == null || opt.id == null) return
  pushSiComboboxRecent(props.historyKey, { value: opt.id, label: opt.label || `Lead #${opt.id}` })
}

function onSearch(search, loadingFn) {
  const q = (search || '').trim()
  clearTimeout(timer)
  if (q.length < 1) {
    options.value = recentsToOptions()
    return
  }
  timer = setTimeout(() => run(q, loadingFn), 280)
}

onMounted(() => {
  if (!options.value.length) options.value = recentsToOptions()
})

async function run(q, loadingFn) {
  loading.value = true
  if (typeof loadingFn === 'function') loadingFn(true)
  try {
    const leadRes = await api.get('/leads', { params: { search: q, per_page: 40 } })
    const raw = unwrapLeadsResponse(leadRes)
    const flat = flattenLeadsFromStages(raw)
    const low = q.toLowerCase()
    const mapped = flat
      .filter((l) => `${l.label} ${l.subtitle}`.toLowerCase().includes(low))
      .map((l) => ({
        id: l.id,
        label: `${l.label}${l.subtitle ? ` · ${l.subtitle}` : ''}`,
        subtitle: l.subtitle,
      }))
    options.value = rankByFuzzy(mapped, (l) => `${l.label} ${l.subtitle || ''}`, q).slice(0, 25)
  } catch {
    options.value = []
  } finally {
    loading.value = false
    if (typeof loadingFn === 'function') loadingFn(false)
  }
}
</script>

<style scoped>
.si-lc {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.si-lc__lbl {
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6b7280;
}

.si-lc__err {
  font-size: 10px;
  color: #b91c1c;
}

.si-lc__empty {
  padding: 10px 12px;
}

.si-lc__empty-t {
  margin: 0;
  font-size: 12px;
  font-weight: 600;
  color: #374151;
}

.si-lc__empty-h {
  margin: 4px 0 0;
  font-size: 11px;
  color: #9ca3af;
  line-height: 1.35;
}

.si-lc :deep(.vs__dropdown-toggle) {
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

.si-lc :deep(.vs__dropdown-toggle:focus-within) {
  border-color: #a3a3a3;
  box-shadow: var(--si-focus-ring, 0 0 0 2px #fff, 0 0 0 4px #d4d4d4);
}

.si-lc :deep(.vs__dropdown-menu) {
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  font-size: 12px;
  max-height: 260px;
}

.si-lc :deep(.vs__dropdown-option) {
  padding: 6px 10px;
}

.si-lc :deep(.vs__dropdown-option--highlight) {
  background: #f3f4f6;
  color: #111827;
}

.si-lc :deep(.vs--loading .vs__spinner) {
  border-color: #e5e7eb;
  border-right-color: #111827;
}
</style>
