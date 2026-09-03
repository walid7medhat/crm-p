<template>
  <div class="emp-search-popup" @click.stop>
    <button type="button" class="emp-search-popup__close" aria-label="Close" @click="$emit('close')">
      <iconify-icon icon="lucide:x" />
    </button>

    <div class="emp-search-popup__layout">
      <aside class="emp-search-popup__chips">
        <button
          v-for="chip in sidebarChips"
          :key="chip.key"
          type="button"
          class="emp-search-popup__chip"
          :class="{ 'is-active': isChipActive(chip) }"
          @click="toggleChip(chip)"
        >
          {{ chip.label }}
        </button>
      </aside>

      <div class="emp-search-popup__form">
        <div class="emp-search-field">
          <span class="emp-search-field__label">Employee Name</span>
          <input
            :value="draft.name"
            type="text"
            placeholder="Enter Employee Name"
            @input="patch('name', $event.target.value)"
          />
        </div>

        <div class="emp-search-field">
          <span class="emp-search-field__label">Joining Date</span>
          <HrFancyDateField v-model="draft.joining_date" placeholder="dd/mm/yyyy" />
        </div>

        <div class="emp-search-field">
          <span class="emp-search-field__label">Visa Validity</span>
          <HrFancyDateField v-model="draft.visa_validity" placeholder="dd/mm/yyyy" />
        </div>

        <div class="emp-search-field">
          <span class="emp-search-field__label">Employee Status</span>
          <select
            class="emp-search-native"
            :class="{ 'is-placeholder': !draft.status }"
            :value="draft.status"
            @change="patch('status', $event.target.value)"
          >
            <option value="">Select Status</option>
            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
        </div>
      </div>
    </div>

    <div class="emp-search-popup__actions">
      <button type="button" class="emp-search-popup__reset" @click="onReset">Reset</button>
      <button type="button" class="emp-search-popup__search" @click="onSearch">Search</button>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, watch } from 'vue'
import HrFancyDateField from '@/components/hr/shared/HrFancyDateField.vue'

const props = defineProps({
  name: { type: String, default: '' },
  filters: { type: Object, default: () => ({}) },
  departments: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'search', 'reset', 'update:name'])

const emptyDraft = () => ({
  name: '',
  department_id: '',
  designation_id: '',
  joining_date: '',
  visa_validity: '',
  status: '',
})

const draft = reactive(emptyDraft())

function syncFromProps() {
  draft.name = props.name || ''
  draft.department_id = props.filters.department_id || ''
  draft.designation_id = props.filters.designation_id || ''
  draft.joining_date = props.filters.joining_date || props.filters.joining_date_from || ''
  draft.visa_validity = props.filters.visa_validity || ''
  draft.status = props.filters.status || ''
}

syncFromProps()

watch(
  () => props.name,
  (value) => {
    draft.name = value || ''
  },
)

function uniqueOptions(items) {
  const seen = new Set()
  const out = []
  for (const item of items || []) {
    const label = String(item?.name ?? item?.label ?? '').trim()
    const key = label.toLowerCase() || `id:${item?.id ?? item?.value}`
    if (!label || seen.has(key)) continue
    seen.add(key)
    out.push({ value: String(item.id ?? item.value), label })
  }
  return out
}

const departmentOptions = computed(() => uniqueOptions(props.departments))
const statusOptions = [
  { value: 'active', label: 'Active' },
  { value: 'in_active', label: 'In Active' },
  { value: 'blocked', label: 'Blocked' },
]
const sidebarChips = computed(() => [
  ...departmentOptions.value.map((item) => ({
    key: `dept-${item.value}`,
    type: 'department',
    value: item.value,
    label: item.label,
  })),
  ...statusOptions.map((item) => ({
    key: `status-${item.value}`,
    type: 'status',
    value: item.value,
    label: item.label,
  })),
])

function isChipActive(chip) {
  if (chip.type === 'department') return String(draft.department_id) === String(chip.value)
  return String(draft.status) === String(chip.value)
}

function toggleChip(chip) {
  if (chip.type === 'department') {
    draft.department_id = String(draft.department_id) === String(chip.value) ? '' : chip.value
    return
  }
  draft.status = String(draft.status) === String(chip.value) ? '' : chip.value
}

function patch(key, value) {
  draft[key] = value
  if (key === 'name') emit('update:name', value)
}

function onReset() {
  Object.assign(draft, emptyDraft())
  emit('update:name', '')
  emit('reset')
}

function onSearch() {
  emit('search', { ...draft })
}
</script>
