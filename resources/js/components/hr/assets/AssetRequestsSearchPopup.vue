<template>
  <div class="emp-search-popup ast-req-search-popup" @click.stop>
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
          <span>User</span>
          <input
            :value="draft.search"
            type="text"
            placeholder="Search employee or ID"
            @input="patch('search', $event.target.value)"
          />
        </label>

        <label class="emp-search-field">
          <span>Applied Date</span>
          <HrFancyDateField v-model="draft.applied_date" placeholder="dd/mm/yyyy" />
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
          <span>Asset Item</span>
          <input
            :value="draft.asset_item"
            type="text"
            placeholder="Enter Asset Item"
            @input="patch('asset_item', $event.target.value)"
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
  departments: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'search', 'reset', 'update:search'])

const emptyDraft = () => ({
  search: '',
  department_id: '',
  asset_item: '',
  applied_date: '',
  status: '',
})

const draft = reactive(emptyDraft())

function syncFromProps() {
  draft.search = props.search || ''
  draft.department_id = props.filters.department_id || ''
  draft.asset_item = props.filters.asset_item || ''
  draft.applied_date = props.filters.applied_date || ''
  draft.status = props.filters.status || ''
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

const departmentOptions = computed(() =>
  props.departments.map((item) => ({ value: String(item.id), label: item.name }))
)

const statusOptions = [
  { value: 'approved', label: 'Approved' },
  { value: 'pending', label: 'Pending' },
  { value: 'rejected', label: 'Rejected' },
]

const sidebarChips = computed(() => [
  ...statusOptions.map((item) => ({
    key: `status-${item.value}`,
    type: 'status',
    value: item.value,
    label: item.label,
  })),
  ...departmentOptions.value.map((item) => ({
    key: `dept-${item.value}`,
    type: 'department',
    value: item.value,
    label: item.label,
  })),
])

function isChipActive(chip) {
  if (chip.type === 'status') return draft.status === chip.value
  if (chip.type === 'department') return String(draft.department_id) === String(chip.value)
  return false
}

function toggleChip(chip) {
  if (chip.type === 'status') {
    draft.status = draft.status === chip.value ? '' : chip.value
    return
  }
  if (chip.type === 'department') {
    draft.department_id = String(draft.department_id) === String(chip.value) ? '' : chip.value
  }
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
