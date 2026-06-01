<template>
  <div class="dh-ymd-select" :class="{ 'dh-ymd-select--invalid': invalid }">
    <div class="dh-ymd-select__field">
      <label class="dh-ymd-select__label" :for="idPrefix + '-day'">Day</label>
      <select
        :id="idPrefix + '-day'"
        v-model.number="selDay"
        class="dh-ymd-select__control"
        :disabled="disabled || !selMonth || !selYear"
        @change="emitFromParts"
      >
        <option :value="0" disabled>Day</option>
        <option v-for="d in dayOptions" :key="d" :value="d">{{ d }}</option>
      </select>
    </div>
    <div class="dh-ymd-select__field">
      <label class="dh-ymd-select__label" :for="idPrefix + '-month'">Month</label>
      <select
        :id="idPrefix + '-month'"
        v-model.number="selMonth"
        class="dh-ymd-select__control"
        :disabled="disabled"
        @change="onMonthChange"
      >
        <option :value="0" disabled>Month</option>
        <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
      </select>
    </div>
    <div class="dh-ymd-select__field">
      <label class="dh-ymd-select__label" :for="idPrefix + '-year'">Year</label>
      <select
        :id="idPrefix + '-year'"
        v-model.number="selYear"
        class="dh-ymd-select__control"
        :disabled="disabled"
        @change="onYearChange"
      >
        <option :value="0" disabled>Year</option>
        <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
      </select>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { parseToDate, toDateOnlyApiString } from '@/composables/useAdvancedDateModel.js'

const MONTHS = [
  { value: 1, label: 'January' },
  { value: 2, label: 'February' },
  { value: 3, label: 'March' },
  { value: 4, label: 'April' },
  { value: 5, label: 'May' },
  { value: 6, label: 'June' },
  { value: 7, label: 'July' },
  { value: 8, label: 'August' },
  { value: 9, label: 'September' },
  { value: 10, label: 'October' },
  { value: 11, label: 'November' },
  { value: 12, label: 'December' },
]

const props = defineProps({
  modelValue: { type: String, default: '' },
  idPrefix: { type: String, default: 'ymd' },
  disabled: { type: Boolean, default: false },
  invalid: { type: Boolean, default: false },
  minYear: { type: Number, default: 2015 },
  maxYear: { type: Number, default: () => new Date().getFullYear() + 1 },
})

const emit = defineEmits(['update:modelValue'])

const selDay = ref(0)
const selMonth = ref(0)
const selYear = ref(0)

const monthOptions = MONTHS

const yearOptions = computed(() => {
  const max = props.maxYear
  const min = props.minYear
  const list = []
  for (let y = max; y >= min; y--) list.push(y)
  return list
})

const dayOptions = computed(() => {
  if (!selYear.value || !selMonth.value) return []
  const max = new Date(selYear.value, selMonth.value, 0).getDate()
  return Array.from({ length: max }, (_, i) => i + 1)
})

function syncFromModel(value) {
  const d = parseToDate(value)
  if (!d) {
    selDay.value = 0
    selMonth.value = 0
    selYear.value = 0
    return
  }
  selYear.value = d.getFullYear()
  selMonth.value = d.getMonth() + 1
  selDay.value = d.getDate()
}

function clampDay() {
  const max = dayOptions.value.length
  if (max && selDay.value > max) selDay.value = max
}

function onMonthChange() {
  clampDay()
  emitFromParts()
}

function onYearChange() {
  clampDay()
  emitFromParts()
}

function emitFromParts() {
  if (!selDay.value || !selMonth.value || !selYear.value) {
    emit('update:modelValue', '')
    return
  }
  const d = new Date(selYear.value, selMonth.value - 1, selDay.value)
  emit('update:modelValue', toDateOnlyApiString(d))
}

watch(
  () => props.modelValue,
  (v) => syncFromModel(v),
  { immediate: true }
)
</script>
