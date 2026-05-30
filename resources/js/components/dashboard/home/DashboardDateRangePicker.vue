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
      <div class="dh-date-range-fields">
        <div class="dh-date-range-field">
          <label class="dh-date-range-label">From</label>
          <AdvancedDatePicker
            v-model="draftFrom"
            date-only
            placeholder="Select start date"
            :block-future-dates="false"
          />
        </div>
        <div class="dh-date-range-field">
          <label class="dh-date-range-label">To</label>
          <AdvancedDatePicker
            v-model="draftTo"
            date-only
            placeholder="Select end date"
            :block-future-dates="false"
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
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import AdvancedDatePicker from '@/components/shared/AdvancedDatePicker.vue'
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

function onApply() {
  const from = parseToDate(draftFrom.value)
  const to = parseToDate(draftTo.value)
  if (!from || !to) {
    error.value = 'Please select both start and end dates.'
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
  if (e.target.closest?.('.date-time-picker-overlay')) return
  if (e.target.closest?.('.flatpickr-calendar')) return
  onCancel()
}

onMounted(() => {
  document.addEventListener('click', onClickOutside, true)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onClickOutside, true)
})
</script>
