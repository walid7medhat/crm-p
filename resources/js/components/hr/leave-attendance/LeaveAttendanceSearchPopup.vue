<template>
  <div class="emp-search-popup la-search-popup" @click.stop>
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
          <span>Search</span>
          <input
            :value="draft.search"
            type="text"
            :placeholder="searchPlaceholder"
            @input="patch('search', $event.target.value)"
          />
        </label>

        <label class="emp-search-field">
          <span>Date from</span>
          <HrFancyDateField v-model="draft.start_date" placeholder="dd/mm/yyyy" />
        </label>

        <label class="emp-search-field">
          <span>Date to</span>
          <HrFancyDateField v-model="draft.end_date" placeholder="dd/mm/yyyy" />
        </label>

        <label class="emp-search-field">
          <span>Department</span>
          <EmpSearchSelect
            :model-value="draft.department"
            :options="departmentOptions"
            placeholder="Select Department"
            @update:model-value="patch('department', $event)"
          />
        </label>

        <label v-if="mode === 'leave'" class="emp-search-field">
          <span>Leave type</span>
          <EmpSearchSelect
            :model-value="draft.leave_type_id"
            :options="leaveTypeOptions"
            placeholder="Select Leave Type"
            @update:model-value="patch('leave_type_id', $event)"
          />
        </label>

        <label v-else class="emp-search-field">
          <span>Attendance status</span>
          <EmpSearchSelect
            :model-value="draft.attendance_status"
            :options="statusOptions"
            placeholder="Select Status"
            @update:model-value="patch('attendance_status', $event)"
          />
        </label>

        <label class="emp-search-field">
          <span>Manager / Team</span>
          <EmpSearchSelect
            :model-value="draft.manager_id"
            :options="managerOptions"
            placeholder="Select Manager"
            @update:model-value="patch('manager_id', $event)"
          />
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
  mode: { type: String, default: 'leave' },
  search: { type: String, default: '' },
  filters: { type: Object, default: () => ({}) },
  departments: { type: Array, default: () => [] },
  leaveTypes: { type: Array, default: () => [] },
  managers: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'search', 'reset', 'update:search'])

const emptyDraft = () => ({
  search: '',
  start_date: '',
  end_date: '',
  department: '',
  attendance_status: '',
  leave_type_id: '',
  manager_id: '',
})

const draft = reactive(emptyDraft())

function syncFromProps() {
  draft.search = props.search || ''
  draft.start_date = props.filters.start_date || ''
  draft.end_date = props.filters.end_date || ''
  draft.department = props.filters.department || ''
  draft.attendance_status = props.filters.attendance_status || ''
  draft.leave_type_id = props.filters.leave_type_id || ''
  draft.manager_id = props.filters.manager_id || ''
}

syncFromProps()

watch(
  () => props.search,
  (value) => {
    draft.search = value || ''
  },
)

const searchPlaceholder = computed(() =>
  props.mode === 'leave' ? 'Search employee, leave type...' : 'Search name, ID, department...'
)
const departmentOptions = computed(() =>
  props.departments.map((item) => ({ value: item.name || String(item.id), label: item.name }))
)
const leaveTypeOptions = computed(() =>
  props.leaveTypes.map((item) => ({ value: String(item.id), label: item.name }))
)
const managerOptions = computed(() =>
  props.managers.map((item) => ({ value: String(item.id), label: item.name }))
)
const statusOptions = [
  { value: 'present', label: 'Present' },
  { value: 'late', label: 'Late' },
  { value: 'absent', label: 'Absent' },
]
const sidebarChips = computed(() => {
  if (props.mode === 'leave') {
    return leaveTypeOptions.value.map((item) => ({
      key: `leave-${item.value}`,
      type: 'leave_type_id',
      value: item.value,
      label: item.label,
    }))
  }
  return [
    ...departmentOptions.value.map((item) => ({
      key: `dept-${item.value}`,
      type: 'department',
      value: item.value,
      label: item.label,
    })),
    ...statusOptions.map((item) => ({
      key: `status-${item.value}`,
      type: 'attendance_status',
      value: item.value,
      label: item.label,
    })),
  ]
})

function isChipActive(chip) {
  return String(draft[chip.type]) === String(chip.value)
}

function toggleChip(chip) {
  draft[chip.type] = String(draft[chip.type]) === String(chip.value) ? '' : chip.value
}

function patch(key, value) {
  draft[key] = value
  if (key === 'search') emit('update:search', value)
}

function onReset() {
  Object.assign(draft, emptyDraft())
  emit('update:search', '')
  emit('reset')
}

function onSearch() {
  emit('search', { ...draft })
}
</script>
