<template>
  <div
    class="advanced-date-picker"
    :class="{ 'advanced-date-picker--dob': dobLayout, 'advanced-date-picker--compact': compact }"
  >
    <button
      type="button"
      class="advanced-date-trigger d-flex align-items-center gap-2"
      :class="{ 'is-invalid': invalid, 'is-disabled': disabled }"
      :disabled="disabled"
      @click.stop="openPicker"
    >
      <iconify-icon icon="lucide:calendar" class="advanced-date-icon" />
      <span class="advanced-date-text" :class="{ 'is-empty': isDisplayEmpty }">{{ displayText }}</span>
    </button>

    <DateTimePicker
      :show="showPicker"
      :model-value="pickerDate"
      :date-only="dateOnly"
      :dob-layout="dobLayout"
      :block-future-dates="blockFutureDates"
      @update:show="showPicker = $event"
      @apply="onApply"
      @cancel="onCancel"
    />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import DateTimePicker from '@/components/kanban/shared/DateTimePicker.vue'
import {
  parseToDate,
  toDateOnlyApiString,
  formatDateOnlyLong,
  formatReminderStyle,
} from '@/composables/useAdvancedDateModel'

const props = defineProps({
  /** YYYY-MM-DD string, ISO string, Date, or empty */
  modelValue: { type: [String, Date], default: null },
  /** When true, hide time controls and emit YYYY-MM-DD (DOB / date filters). */
  dateOnly: { type: Boolean, default: true },
  /** Rich DOB UX: Month / Day / Year dropdowns + calendar; future dates blocked by default. */
  dobLayout: { type: Boolean, default: false },
  /** When false with dobLayout, allows future dates (e.g. handover / installments). Default: true if dobLayout. */
  blockFutureDates: { type: Boolean, default: undefined },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  invalid: { type: Boolean, default: false },
  /** Smaller trigger text (e.g. payment breakdown quick modal). */
  compact: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const showPicker = ref(false)

const pickerDate = computed(() => {
  const d = parseToDate(props.modelValue)
  return d || new Date()
})

const emptyLabel = computed(
  () => props.placeholder || (props.dateOnly ? 'Select date' : 'Select date and time'),
)

const isDisplayEmpty = computed(() => {
  const v = props.modelValue
  if (v == null || v === '') return true
  if (v instanceof Date) return Number.isNaN(v.getTime())
  return !parseToDate(v)
})

const displayText = computed(() => {
  if (props.dateOnly) {
    return formatDateOnlyLong(props.modelValue, emptyLabel.value)
  }
  return formatReminderStyle(props.modelValue, emptyLabel.value)
})

function openPicker() {
  if (props.disabled) return
  showPicker.value = true
}

function onApply(date) {
  if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
    showPicker.value = false
    return
  }
  if (props.dateOnly) {
    emit('update:modelValue', toDateOnlyApiString(date))
  } else {
    emit('update:modelValue', date.toISOString())
  }
  showPicker.value = false
}

function onCancel() {
  showPicker.value = false
}
</script>

<style scoped>
.advanced-date-picker {
  width: 100%;
}

.advanced-date-trigger {
  width: 100%;
  min-height: 38px;
  background: transparent;
  border: 1px solid rgba(237, 237, 237, 1);
  padding: 8px 12px;
  border-radius: 15px;
  cursor: pointer;
  transition: all 0.2s;
  text-align: left;
}

.advanced-date-trigger:hover:not(:disabled) {
  background: #f8fafc;
}

.advanced-date-trigger.is-disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.advanced-date-icon {
  font-size: 16px;
  color: #64748b;
  flex-shrink: 0;
}

.advanced-date-text {
  font-size: 13px;
  font-weight: 400;
  color: #0f172a;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.advanced-date-text.is-empty {
  font-size: 11px;
  font-weight: 400;
  color: #94a3b8;
}

.advanced-date-trigger.is-invalid {
  border-color: #dc3545;
}

.advanced-date-picker--dob .advanced-date-text {
  font-size: 12px;
}

.advanced-date-picker--dob .advanced-date-icon {
  font-size: 14px;
}

.advanced-date-picker--dob .advanced-date-trigger {
  min-height: 38px;
  padding: 8px 12px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  background: linear-gradient(to bottom, #ffffff, #fafbfc);
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.advanced-date-picker--dob .advanced-date-trigger:hover:not(:disabled) {
  border-color: #cbd5e1;
  background: #fff;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
}

.advanced-date-picker--dob .advanced-date-trigger:focus-visible {
  outline: none;
  border-color: #1a2f5b;
  box-shadow: 0 0 0 3px rgba(26, 47, 91, 0.15);
}

.advanced-date-picker--dob .advanced-date-trigger.is-invalid {
  border-color: #dc3545;
  box-shadow: 0 0 0 1px rgba(220, 53, 69, 0.2);
}

.advanced-date-picker--dob .advanced-date-icon {
  color: #1a2f5b;
}

.advanced-date-picker--dob .advanced-date-text {
  font-weight: 500;
}

.advanced-date-picker--compact .advanced-date-text.is-empty {
  font-size: 10px;
  font-weight: 400;
  color: #94a3b8;
}

.advanced-date-picker--compact .advanced-date-text:not(.is-empty) {
  font-size: 11px;
}

.advanced-date-picker--compact.advanced-date-picker--dob .advanced-date-trigger {
  min-height: 32px;
  padding: 4px 8px;
  border-radius: 8px;
}

.advanced-date-picker--compact.advanced-date-picker--dob .advanced-date-text.is-empty {
  font-size: 10px;
  font-weight: 400;
  color: #94a3b8;
}

.advanced-date-picker--compact.advanced-date-picker--dob .advanced-date-text:not(.is-empty) {
  font-size: 11px;
  font-weight: 500;
}

.advanced-date-picker--compact .advanced-date-icon {
  font-size: 12px;
}
:deep(.vs__dropdown-menu, .flatpickr-calendar, [data-popper-placement] ){
    z-index: 45004 !important;
        max-width: 100px !important;

}
.vs__dropdown-menu, .flatpickr-calendar, [data-popper-placement] {
    z-index: 45004 !important;
        max-width: 100px !important;

}
</style>
