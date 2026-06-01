<template>
  <div ref="rootRef" class="dh-date-range" :class="pickerClass">
    <button
      type="button"
      class="dh-date-picker"
      :class="{ 'dh-date-picker--icon-only': iconOnly }"
      :aria-expanded="open"
      :aria-label="iconOnly ? (label || 'Select date range') : undefined"
      aria-haspopup="dialog"
      @click="toggleOpen"
    >
      <iconify-icon icon="lucide:calendar" class="dh-date-picker-icon" width="16" height="16" />
      <span v-if="!iconOnly">{{ label }}</span>
      <iconify-icon v-if="!iconOnly" icon="lucide:chevrons-up-down" width="14" height="14" />
    </button>

    <div v-if="open" class="dh-date-range-popover" role="dialog" aria-label="Select date range">
      <p class="dh-date-range-title">Custom date range</p>

      <div class="dh-date-range-presets" role="group" aria-label="Quick ranges">
        <button
          v-for="preset in presets"
          :key="preset.id"
          type="button"
          class="dh-date-range-preset"
          @click="applyPreset(preset)"
        >
          {{ preset.label }}
        </button>
      </div>

      <div class="dh-date-range-fields">
        <div class="dh-date-range-field">
          <div class="dh-date-range-field-head">
            <span class="dh-date-range-label">From</span>
            <span v-if="fromPreview" class="dh-date-range-preview">{{ fromPreview }}</span>
          </div>
          <DateYmdSelect
            id-prefix="dh-from"
            v-model="draftFrom"
            :invalid="!!error && !draftFrom"
          />
        </div>
        <div class="dh-date-range-field">
          <div class="dh-date-range-field-head">
            <span class="dh-date-range-label">To</span>
            <span v-if="toPreview" class="dh-date-range-preview">{{ toPreview }}</span>
          </div>
          <DateYmdSelect
            id-prefix="dh-to"
            v-model="draftTo"
            :invalid="!!error && !draftTo"
          />
        </div>
      </div>

      <p v-if="error" class="dh-date-range-error">{{ error }}</p>

      <div class="dh-date-range-actions">
        <button type="button" class="dh-date-range-btn dh-date-range-btn--ghost" @click="onCancel">
          Cancel
        </button>
        <button type="button" class="dh-date-range-btn dh-date-range-btn--primary" @click="onApply">
          Apply
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed, onMounted, onBeforeUnmount } from 'vue'
import DateYmdSelect from '@/components/dashboard/home/DateYmdSelect.vue'
import { parseToDate, toDateOnlyApiString } from '@/composables/useAdvancedDateModel.js'

const props = defineProps({
  dateFrom: { type: String, default: '' },
  dateTo: { type: String, default: '' },
  label: { type: String, default: '' },
  pickerClass: { type: String, default: '' },
  iconOnly: { type: Boolean, default: false },
})

const emit = defineEmits(['update:dateFrom', 'update:dateTo', 'apply'])

const rootRef = ref(null)
const open = ref(false)
const draftFrom = ref('')
const draftTo = ref('')
const error = ref('')

const presets = [
  { id: '7d', label: 'Last 7 days', days: 7 },
  { id: '30d', label: 'Last 30 days', days: 30 },
  { id: '3m', label: 'Last 3 months', months: 3 },
  { id: 'year', label: 'This year', type: 'year' },
  { id: 'month', label: 'This month', type: 'month' },
]

function formatPreview(ymd) {
  const d = parseToDate(ymd)
  if (!d) return ''
  return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

const fromPreview = computed(() => formatPreview(draftFrom.value))
const toPreview = computed(() => formatPreview(draftTo.value))

function syncDraft() {
  draftFrom.value = props.dateFrom || ''
  draftTo.value = props.dateTo || ''
  error.value = ''
}

watch(
  () => [props.dateFrom, props.dateTo],
  () => {
    if (!open.value) syncDraft()
  },
  { immediate: true }
)

function toggleOpen() {
  open.value = !open.value
  if (open.value) syncDraft()
}

function onCancel() {
  syncDraft()
  open.value = false
}

function applyPreset(preset) {
  const end = new Date()
  const start = new Date()
  if (preset.days) {
    start.setDate(end.getDate() - (preset.days - 1))
  } else if (preset.months) {
    start.setMonth(end.getMonth() - preset.months)
    start.setDate(start.getDate() + 1)
  } else if (preset.type === 'year') {
    start.setMonth(0, 1)
  } else if (preset.type === 'month') {
    start.setDate(1)
  }
  draftFrom.value = toDateOnlyApiString(start)
  draftTo.value = toDateOnlyApiString(end)
  error.value = ''
}

function onApply() {
  const from = parseToDate(draftFrom.value)
  const to = parseToDate(draftTo.value)
  if (!from || !to) {
    error.value = 'Please select day, month, and year for both dates.'
    return
  }
  if (from.getTime() > to.getTime()) {
    error.value = 'Start date must be before end date.'
    return
  }
  error.value = ''
  emit('update:dateFrom', toDateOnlyApiString(from))
  emit('update:dateTo', toDateOnlyApiString(to))
  emit('apply')
  open.value = false
}

function onClickOutside(e) {
  if (!open.value || !rootRef.value) return
  if (rootRef.value.contains(e.target)) return
  onCancel()
}

onMounted(() => {
  document.addEventListener('click', onClickOutside, true)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onClickOutside, true)
})
</script>
