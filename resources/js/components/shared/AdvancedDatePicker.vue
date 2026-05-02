<template>
  <div class="advanced-date-picker">
    <button
      type="button"
      class="advanced-date-trigger d-flex align-items-center gap-2"
      :class="{ 'is-invalid': invalid, 'is-disabled': disabled }"
      :disabled="disabled"
      @click.stop="openPicker"
    >
      <iconify-icon icon="lucide:calendar" class="advanced-date-icon" />
      <span class="advanced-date-text">{{ displayText }}</span>
    </button>

    <DateTimePicker
      :show="showPicker"
      :model-value="pickerDate"
      :date-only="dateOnly"
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
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  invalid: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const showPicker = ref(false)

const pickerDate = computed(() => {
  const d = parseToDate(props.modelValue)
  return d || new Date()
})

const displayText = computed(() => {
  const empty =
    props.placeholder ||
    (props.dateOnly ? 'Select date' : 'Select date and time')
  if (props.dateOnly) {
    return formatDateOnlyLong(props.modelValue, empty)
  }
  return formatReminderStyle(props.modelValue, empty)
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

.advanced-date-trigger.is-invalid {
  border-color: #dc3545;
}
</style>
