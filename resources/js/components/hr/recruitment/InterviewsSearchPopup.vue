<template>
  <div class="emp-search-popup rec-search-popup rec-interviews-search-popup" @click.stop>
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
          <span>Candidate Name</span>
          <EmpSearchSelect
            :model-value="draft.candidate"
            :options="candidateOptions"
            placeholder="Search Candidate"
            @update:model-value="patch('candidate', $event)"
          />
        </label>

        <label class="emp-search-field">
          <span>Opening</span>
          <EmpSearchSelect
            :model-value="draft.job_title"
            :options="jobOptions"
            placeholder="Search Opening"
            @update:model-value="patch('job_title', $event)"
          />
        </label>

        <label class="emp-search-field">
          <span>Branch</span>
          <EmpSearchSelect
            :model-value="draft.branch"
            :options="branchOptions"
            placeholder="Select Branch"
            @update:model-value="patch('branch', $event)"
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
  interviews: { type: Array, default: () => [] },
  branches: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'search', 'reset', 'update:search'])

const emptyDraft = () => ({
  candidate: '',
  job_title: '',
  branch: '',
  status: '',
  search: '',
})

const draft = reactive(emptyDraft())

function syncFromProps() {
  draft.candidate = props.filters.candidate || ''
  draft.job_title = props.filters.job_title || ''
  draft.branch = props.filters.branch || ''
  draft.status = props.filters.status || ''
  draft.search = props.search || ''
}

syncFromProps()

watch(() => props.search, (value) => {
  draft.search = value || ''
})

const candidateOptions = computed(() => {
  const names = [...new Set(props.interviews.map((item) => item.applicantName).filter((name) => name && name !== '—'))]
  return names.map((name) => ({ value: name, label: name }))
})
const jobOptions = computed(() => {
  const titles = [...new Set(props.interviews.map((item) => item.jobTitle).filter((title) => title && title !== '—'))]
  return titles.map((title) => ({ value: title, label: title }))
})
const branchOptions = computed(() => {
  const fromProps = props.branches.map((item) => ({ value: item.name, label: item.name }))
  if (fromProps.length) return fromProps
  const names = [...new Set(props.interviews.map((item) => item.branch).filter((name) => name && name !== '—'))]
  return names.map((name) => ({ value: name, label: name }))
})
const statusOptions = [
  { value: 'scheduled', label: 'Pending' },
  { value: 'completed', label: 'Completed' },
]
const sidebarChips = computed(() => {
  const branchChips = branchOptions.value.slice(0, 4).map((item) => ({
    key: `branch-${item.value}`,
    type: 'branch',
    value: item.value,
    label: item.label,
  }))
  return [
    ...branchChips,
    { key: 'status-scheduled', type: 'status', value: 'scheduled', label: 'Pending' },
    { key: 'status-completed', type: 'status', value: 'completed', label: 'Completed' },
  ]
})

function isChipActive(chip) {
  return String(draft[chip.type] || '') === String(chip.value)
}

function toggleChip(chip) {
  draft[chip.type] = String(draft[chip.type]) === String(chip.value) ? '' : chip.value
}

function patch(key, value) {
  draft[key] = value
  if (key === 'candidate') emit('update:search', value)
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
