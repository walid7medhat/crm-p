<template>
  <div class="emp-search-popup rec-search-popup" @click.stop>
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
          <span>Search Job Title</span>
          <EmpSearchSelect
            :model-value="draft.title"
            :options="jobTitleOptions"
            placeholder="Search Job Title"
            @update:model-value="patch('title', $event)"
          />
        </label>

        <label class="emp-search-field">
          <span>Posted Date</span>
          <HrFancyDateField v-model="draft.posted_date" placeholder="dd/mm/yyyy" />
        </label>

        <label class="emp-search-field">
          <span>Closing Date</span>
          <HrFancyDateField v-model="draft.closing_date" placeholder="dd/mm/yyyy" />
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
          <span>Type</span>
          <EmpSearchSelect
            :model-value="draft.type"
            :options="typeOptions"
            placeholder="Select Type"
            @update:model-value="patch('type', $event)"
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
  jobs: { type: Array, default: () => [] },
  departments: { type: Array, default: () => [] },
  branches: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'search', 'reset', 'update:search'])

const emptyDraft = () => ({
  title: '',
  posted_date: '',
  closing_date: '',
  department_id: '',
  type: '',
  branch_id: '',
})

const draft = reactive(emptyDraft())

function syncFromProps() {
  draft.title = props.search || props.filters.title || ''
  draft.posted_date = props.filters.posted_date || ''
  draft.closing_date = props.filters.closing_date || ''
  draft.department_id = props.filters.department_id || ''
  draft.type = props.filters.type || props.filters.job_type || props.filters.status || ''
  draft.branch_id = props.filters.branch_id || ''
}

syncFromProps()

watch(
  () => props.search,
  (value) => {
    draft.title = value || ''
  },
)

const jobTitleOptions = computed(() => {
  const titles = [...new Set(props.jobs.map((job) => job.title).filter(Boolean))]
  return titles.map((title) => ({ value: title, label: title }))
})
const departmentOptions = computed(() =>
  props.departments.map((item) => ({ value: String(item.id), label: item.name }))
)
const typeOptions = [
  { value: 'full_time', label: 'Full-time' },
  { value: 'part_time', label: 'Part-time' },
  { value: 'closed', label: 'Closed' },
]
const sidebarChips = computed(() => {
  const branchChips = props.branches.slice(0, 4).map((item) => ({
    key: `branch-${item.id}`,
    type: 'branch_id',
    value: String(item.id),
    label: item.name,
  }))
  const deptChips = departmentOptions.value.slice(0, 6).map((item) => ({
    key: `dept-${item.value}`,
    type: 'department_id',
    value: item.value,
    label: item.label,
  }))
  return [...branchChips, ...deptChips]
})

function isChipActive(chip) {
  return String(draft[chip.type] || '') === String(chip.value)
}

function toggleChip(chip) {
  draft[chip.type] = String(draft[chip.type]) === String(chip.value) ? '' : chip.value
}

function patch(key, value) {
  draft[key] = value
  if (key === 'title') emit('update:search', value)
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
