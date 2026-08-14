<template>
  <div class="emp-search-popup rec-search-popup rec-applicants-search-popup" @click.stop>
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
        <h6 class="rec-applicants-search-title">Search Applicants</h6>
        <label class="emp-search-field">
          <span>Search Candidates</span>
          <EmpSearchSelect
            :model-value="draft.candidate"
            :options="candidateOptions"
            placeholder="Search Candidates"
            @update:model-value="patch('candidate', $event)"
          />
        </label>
        <label class="emp-search-field">
          <span>Applied Date</span>
          <HrFancyDateField v-model="draft.applied_date" placeholder="dd/mm/yyyy" />
        </label>
        <label class="emp-search-field">
          <span>Stage</span>
          <EmpSearchSelect
            :model-value="draft.decision"
            :options="stageOptions"
            placeholder="Select Stage"
            @update:model-value="patch('decision', $event)"
          />
        </label>
        <label class="emp-search-field">
          <span>Interview Status</span>
          <EmpSearchSelect
            :model-value="draft.interview_status"
            :options="interviewStatusOptions"
            placeholder="Select Interview Status"
            @update:model-value="patch('interview_status', $event)"
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
    toggle() { this.open = !this.open },
    select(value) { this.$emit('update:modelValue', value); this.open = false },
    onDocClick(event) { if (!this.$el?.contains(event.target)) this.open = false },
  },
  mounted() { document.addEventListener('click', this.onDocClick) },
  unmounted() { document.removeEventListener('click', this.onDocClick) },
  template: `
    <div class="emp-search-select" @click.stop="toggle">
      <span :class="{ 'is-placeholder': !selectedLabel }">{{ selectedLabel || placeholder }}</span>
      <iconify-icon icon="lucide:chevrons-up-down" />
      <div v-if="open" class="emp-search-select__menu" @click.stop>
        <button v-for="opt in options" :key="opt.value" type="button" :class="{ 'is-selected': String(opt.value) === String(modelValue) }" @click="select(opt.value)">
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
  applicants: { type: Array, default: () => [] },
})
const emit = defineEmits(['close', 'search', 'reset', 'update:search'])

const emptyDraft = () => ({
  candidate: '',
  applied_date: '',
  decision: '',
  interview_status: '',
  date_preset: '',
  search: '',
})
const draft = reactive(emptyDraft())

function syncFromProps() {
  draft.candidate = props.filters.candidate || ''
  draft.applied_date = props.filters.applied_date || ''
  draft.decision = props.filters.decision || ''
  draft.interview_status = props.filters.interview_status || ''
  draft.date_preset = props.filters.date_preset || ''
  draft.search = props.search || ''
}
syncFromProps()
watch(() => props.search, (value) => { draft.search = value || '' })

const candidateOptions = computed(() => {
  const names = [...new Set(props.applicants.map((item) => item.name).filter(Boolean))]
  return names.map((name) => ({ value: name, label: name }))
})
const stageOptions = [
  { value: 'selected', label: 'Selected' },
  { value: 'maybe', label: 'May be' },
  { value: 'rejected', label: 'Rejected' },
]
const interviewStatusOptions = [
  { value: 'Not Scheduled', label: 'Not Scheduled' },
  { value: 'Scheduled', label: 'Scheduled' },
  { value: 'Completed', label: 'Completed' },
]
const sidebarChips = [
  { key: 'selected', type: 'decision', value: 'selected', label: 'Selected' },
  { key: 'maybe', type: 'decision', value: 'maybe', label: 'May be' },
  { key: 'rejected', type: 'decision', value: 'rejected', label: 'Rejected' },
  { key: 'today', type: 'date_preset', value: 'today', label: 'Today' },
  { key: 'yesterday', type: 'date_preset', value: 'yesterday', label: 'Yesterday' },
  { key: 'this_week', type: 'date_preset', value: 'this_week', label: 'This Week' },
  { key: 'this_month', type: 'date_preset', value: 'this_month', label: 'This Month' },
]

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
