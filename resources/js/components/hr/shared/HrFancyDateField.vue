<template>
  <div class="emp-search-date hr-fancy-date" @click.stop="open">
    <input :value="displayValue" type="text" readonly :placeholder="placeholder" />
    <span class="emp-search-date__icon" aria-hidden="true">
      <iconify-icon icon="lucide:calendar" />
    </span>
    <DateTimePicker
      :show="openPicker"
      :model-value="parsedValue"
      :date-only="true"
      :block-future-dates="blockFuture"
      @update:show="openPicker = $event"
      @apply="onApply"
    />
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import DateTimePicker from '@/components/kanban/shared/DateTimePicker.vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: 'dd/mm/yyyy' },
  blockFuture: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const openPicker = ref(false)

const parsedValue = computed(() => {
  if (!props.modelValue) return null
  const d = new Date(`${props.modelValue}T00:00:00`)
  return Number.isNaN(d.getTime()) ? null : d
})

const displayValue = computed(() => {
  if (!props.modelValue) return ''
  const d = parsedValue.value
  if (!d) return props.modelValue
  const dd = String(d.getDate()).padStart(2, '0')
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  return `${dd}/${mm}/${d.getFullYear()}`
})

function open() {
  openPicker.value = true
}

function onApply(date) {
  if (!date) {
    emit('update:modelValue', '')
    return
  }
  const d = date instanceof Date ? date : new Date(date)
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  emit('update:modelValue', `${y}-${m}-${day}`)
}
</script>
