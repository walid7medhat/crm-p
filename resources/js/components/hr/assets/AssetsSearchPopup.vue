<template>
  <div class="emp-search-popup ast-search-popup" @click.stop>
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
        <label class="emp-search-field">
          <span>Asset Name / ID</span>
          <input
            :value="draft.search"
            type="text"
            placeholder="Search name, ID or serial"
            @input="patch('search', $event.target.value)"
          />
        </label>

        <label class="emp-search-field">
          <span>Assigned Employee</span>
          <EmpSearchSelect
            :model-value="draft.user_id"
            :options="employeeOptions"
            placeholder="Select Employee"
            @update:model-value="patch('user_id', $event)"
          />
        </label>

        <label class="emp-search-field">
          <span>Asset Type</span>
          <EmpSearchSelect
            :model-value="draft.asset_type_id"
            :options="typeOptions"
            placeholder="Select Type"
            @update:model-value="patch('asset_type_id', $event)"
          />
        </label>

        <label class="emp-search-field">
          <span>Department</span>
          <EmpSearchSelect
            :model-value="draft.department_id"
            :options="departmentOptions"
            placeholder="Select Department"
            @update:model-value="patch('department_id', $event)"
          />
        </label>

        <label class="emp-search-field">
          <span>Status</span>
          <EmpSearchSelect
            :model-value="draft.status"
            :options="statusOptions"
            placeholder="Select Status"
            @update:model-value="patch('status', $event)"
          />
        </label>

        <label class="emp-search-field">
          <span>Purchase Date</span>
          <HrFancyDateField v-model="draft.purchase_date_from" placeholder="dd/mm/yyyy" />
        </label>
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
  search: { type: String, default: '' },
  filters: { type: Object, default: () => ({}) },
  assetTypes: { type: Array, default: () => [] },
  departments: { type: Array, default: () => [] },
  employees: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'search', 'reset', 'update:search'])

const statusOptions = [
  { value: 'available', label: 'Available' },
  { value: 'assigned', label: 'Assigned' },
  { value: 'maintenance', label: 'Under Maintenance' },
  { value: 'disposed', label: 'Lost / Disposed' },
]

const emptyDraft = () => ({
  search: '',
  asset_type_id: '',
  status: '',
  department_id: '',
  user_id: '',
  purchase_date_from: '',
})

const draft = reactive(emptyDraft())

function syncFromProps() {
  draft.search = props.search || ''
  draft.asset_type_id = props.filters.asset_type_id || ''
  draft.status = props.filters.status || ''
  draft.department_id = props.filters.department_id || ''
  draft.user_id = props.filters.user_id || ''
  draft.purchase_date_from = props.filters.purchase_date_from || ''
}

syncFromProps()

watch(
  () => props.search,
  (value) => {
    draft.search = value || ''
  },
)

function patch(key, value) {
  draft[key] = value
  if (key === 'search') emit('update:search', value)
}

const typeOptions = computed(() =>
  props.assetTypes.map((item) => ({ value: String(item.id), label: item.name })),
)
const departmentOptions = computed(() =>
  props.departments.map((item) => ({ value: String(item.id), label: item.name })),
)
const employeeOptions = computed(() =>
  props.employees.map((item) => ({ value: String(item.id), label: item.name })),
)

const sidebarChips = computed(() => [
  ...statusOptions.map((item) => ({
    key: `status-${item.value}`,
    type: 'status',
    value: item.value,
    label: item.label,
  })),
  ...typeOptions.value.slice(0, 6).map((item) => ({
    key: `type-${item.value}`,
    type: 'asset_type_id',
    value: item.value,
    label: item.label,
  })),
])

function isChipActive(chip) {
  return String(draft[chip.type] || '') === String(chip.value)
}

function toggleChip(chip) {
  draft[chip.type] = String(draft[chip.type]) === String(chip.value) ? '' : chip.value
}

function onSearch() {
  emit('search', { ...draft })
}

function onReset() {
  Object.assign(draft, emptyDraft())
  emit('update:search', '')
  emit('reset')
}
</script>
