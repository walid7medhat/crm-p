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
          <span class="emp-search-field__label">Department</span>
          <EmpSearchSelect
            :model-value="draft.department_id"
            :options="departmentOptions"
            placeholder="Select Department"
            @update:model-value="patch('department_id', $event)"
          />
        </div>

        <div class="emp-search-field">
          <span class="emp-search-field__label">Designation</span>
          <EmpSearchSelect
            :model-value="draft.designation_id"
            :options="designationOptions"
            placeholder="Select Designation"
            @update:model-value="patch('designation_id', $event)"
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
          <EmpSearchSelect
            :model-value="draft.status"
            :options="statusOptions"
            placeholder="Select Status"
            @update:model-value="patch('status', $event)"
          />
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

const EmpSearchSelect = {
  props: {
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Select' },
  },
  emits: ['update:modelValue'],
  data() {
    return { open: false }
  },
  computed: {
    selectedLabel() {
      const match = this.options.find((opt) => String(opt.value) === String(this.modelValue))
      return match?.label || ''
    },
  },
  methods: {
    toggle() {
      this.open = !this.open
    },
    select(value) {
      this.$emit('update:modelValue', value)
      this.open = false
    },
    onDocClick(event) {
      if (!this.$el?.contains(event.target)) this.open = false
    },
  },
  mounted() {
    document.addEventListener('click', this.onDocClick)
  },
  unmounted() {
    document.removeEventListener('click', this.onDocClick)
  },
  template: `
    <div class="emp-search-select" @click.stop="toggle">
      <span :class="{ 'is-placeholder': !selectedLabel }">{{ selectedLabel || placeholder }}</span>
      <iconify-icon icon="lucide:chevrons-up-down" />
      <div v-if="open" class="emp-search-select__menu" @click.stop>
        <button
          v-for="opt in options"
          :key="opt.value"
          type="button"
          :class="{ 'is-selected': String(opt.value) === String(modelValue) }"
          @click="select(opt.value)"
        >
          <span>{{ opt.label }}</span>
          <iconify-icon v-if="String(opt.value) === String(modelValue)" icon="lucide:check" />
        </button>
      </div>
    </div>
  `,
}

const props = defineProps({
  name: { type: String, default: '' },
  filters: { type: Object, default: () => ({}) },
  departments: { type: Array, default: () => [] },
  designations: { type: Array, default: () => [] },
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
const designationOptions = computed(() => uniqueOptions(props.designations))
const statusOptions = [
  { value: 'active', label: 'Active' },
  { value: 'in_active', label: 'In Active' },
]
const sidebarChips = computed(() => [
  ...departmentOptions.value.map((item) => ({
    key: `dept-${item.value}`,
    type: 'department',
    value: item.value,
    label: item.label,
  })),
  { key: 'status-active', type: 'status', value: 'active', label: 'Active' },
  { key: 'status-inactive', type: 'status', value: 'in_active', label: 'In Active' },
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
