<template>
  <div class="ex-filters">
    <div class="ex-filters__pills">
      <button
        v-for="p in presets"
        :key="p.id"
        type="button"
        class="ex-pill"
        :class="{ 'ex-pill--active': period === p.id }"
        @click="$emit('period', p.id)"
      >
        {{ p.label }}
      </button>
    </div>
    <div class="ex-filters__right">
      <DashboardDateRangePicker
        v-model:date-from="localFrom"
        v-model:date-to="localTo"
        :label="customLabel"
        picker-class="ex-filter-date"
        @apply="applyCustom"
      />
      <button type="button" class="ex-btn ex-btn--icon" :disabled="loading" @click="$emit('refresh')">
        <iconify-icon icon="lucide:refresh-cw" :class="{ 'ex-spin': loading }" width="14" height="14" />
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import DashboardDateRangePicker from '@/components/dashboard/home/DashboardDateRangePicker.vue'

const props = defineProps({
  period: { type: String, default: 'monthly' },
  dateFrom: { type: String, default: '' },
  dateTo: { type: String, default: '' },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['period', 'custom', 'refresh'])

const presets = [
  { id: 'today', label: 'Today' },
  { id: 'weekly', label: 'Weekly' },
  { id: 'monthly', label: 'Monthly' },
  { id: 'yearly', label: 'Yearly' },
]

const localFrom = ref(props.dateFrom)
const localTo = ref(props.dateTo)

watch(() => props.dateFrom, (v) => { localFrom.value = v })
watch(() => props.dateTo, (v) => { localTo.value = v })

const customLabel = computed(() => {
  if (props.period === 'custom' && localFrom.value && localTo.value) {
    return `${localFrom.value} → ${localTo.value}`
  }
  return 'Custom'
})

function applyCustom() {
  emit('custom', { from: localFrom.value, to: localTo.value })
}
</script>
