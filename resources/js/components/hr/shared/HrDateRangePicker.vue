<template>
  <div class="hr-range-picker" @click.stop>
    <aside class="hr-range-picker__presets">
      <button
        v-for="preset in presets"
        :key="preset.id"
        type="button"
        class="hr-range-picker__preset"
        :class="{ 'is-active': activePreset === preset.id }"
        @click="applyPreset(preset.id)"
      >
        {{ preset.label }}
      </button>
    </aside>

    <div class="hr-range-picker__calendar">
      <div class="hr-range-picker__nav">
        <button type="button" aria-label="Previous month" @click="shiftMonth(-1)">
          <iconify-icon icon="lucide:chevron-left" />
        </button>
        <strong>{{ monthLabel }}</strong>
        <button type="button" aria-label="Next month" @click="shiftMonth(1)">
          <iconify-icon icon="lucide:chevron-right" />
        </button>
      </div>
      <div class="hr-range-picker__weekdays">
        <span v-for="day in weekdays" :key="day">{{ day }}</span>
      </div>
      <div class="hr-range-picker__grid">
        <button
          v-for="cell in cells"
          :key="cell.key"
          type="button"
          class="hr-range-picker__day"
          :class="{
            'is-outside': cell.outside,
            'is-today': cell.isToday,
            'is-start': cell.isStart,
            'is-end': cell.isEnd,
            'is-in-range': cell.inRange,
          }"
          @click="selectDay(cell.date)"
        >
          {{ cell.date.getDate() }}
        </button>
      </div>
      <div class="hr-range-picker__footer">
        <button type="button" class="hr-range-picker__cancel" @click="$emit('cancel')">Cancel</button>
        <button type="button" class="hr-range-picker__apply" @click="onApply">Apply</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  startDate: { type: String, default: '' },
  endDate: { type: String, default: '' },
})

const emit = defineEmits(['apply', 'cancel'])

const weekdays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']
const presets = [
  { id: 'today', label: 'Today' },
  { id: 'yesterday', label: 'Yesterday' },
  { id: 'this_week', label: 'This Week' },
  { id: 'last_week', label: 'Last Week' },
  { id: 'this_month', label: 'This Month' },
  { id: 'last_month', label: 'Last Month' },
  { id: 'last_year', label: 'Last Year' },
  { id: 'custom', label: 'Custom Date' },
]

const viewDate = ref(parseYmd(props.startDate) || startOfDay(new Date()))
const draftStart = ref(parseYmd(props.startDate))
const draftEnd = ref(parseYmd(props.endDate) || parseYmd(props.startDate))
const pickingEnd = ref(false)
const activePreset = ref(props.startDate || props.endDate ? 'custom' : '')

watch(
  () => [props.startDate, props.endDate],
  () => {
    draftStart.value = parseYmd(props.startDate)
    draftEnd.value = parseYmd(props.endDate) || parseYmd(props.startDate)
    if (draftStart.value) viewDate.value = new Date(draftStart.value)
  },
)

const monthLabel = computed(() =>
  viewDate.value.toLocaleDateString('en-US', { month: 'long', year: 'numeric' }),
)

const cells = computed(() => {
  const year = viewDate.value.getFullYear()
  const month = viewDate.value.getMonth()
  const first = new Date(year, month, 1)
  const startWeekday = first.getDay()
  const daysInMonth = new Date(year, month + 1, 0).getDate()
  const prevMonthDays = new Date(year, month, 0).getDate()
  const today = startOfDay(new Date())
  const start = draftStart.value ? startOfDay(draftStart.value) : null
  const end = draftEnd.value ? startOfDay(draftEnd.value) : start
  const list = []

  for (let i = startWeekday - 1; i >= 0; i -= 1) {
    list.push(makeCell(new Date(year, month - 1, prevMonthDays - i), true, today, start, end))
  }
  for (let day = 1; day <= daysInMonth; day += 1) {
    list.push(makeCell(new Date(year, month, day), false, today, start, end))
  }
  const remainder = list.length % 7 === 0 ? 0 : 7 - (list.length % 7)
  for (let day = 1; day <= remainder; day += 1) {
    list.push(makeCell(new Date(year, month + 1, day), true, today, start, end))
  }
  return list
})

function makeCell(date, outside, today, start, end) {
  const day = startOfDay(date)
  const isStart = !!(start && day.getTime() === start.getTime())
  const isEnd = !!(end && day.getTime() === end.getTime())
  const inRange = !!(start && end && day > start && day < end)
  return {
    key: `${toYmd(day)}-${outside ? 'o' : 'i'}`,
    date: day,
    outside,
    isToday: day.getTime() === today.getTime(),
    isStart,
    isEnd,
    inRange,
  }
}

function startOfDay(value) {
  const date = new Date(value)
  date.setHours(0, 0, 0, 0)
  return date
}

function parseYmd(value) {
  if (!value) return null
  const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (!match) return null
  return startOfDay(new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])))
}

function toYmd(date) {
  if (!date) return ''
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

function shiftMonth(delta) {
  viewDate.value = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth() + delta, 1)
}

function selectDay(date) {
  activePreset.value = 'custom'
  const day = startOfDay(date)
  if (!draftStart.value || (draftStart.value && draftEnd.value && !pickingEnd.value)) {
    draftStart.value = day
    draftEnd.value = null
    pickingEnd.value = true
    return
  }
  if (day < draftStart.value) {
    draftEnd.value = draftStart.value
    draftStart.value = day
  } else {
    draftEnd.value = day
  }
  pickingEnd.value = false
}

function applyPreset(id) {
  activePreset.value = id
  if (id === 'custom') {
    pickingEnd.value = !draftEnd.value
    return
  }
  const today = startOfDay(new Date())
  let start = today
  let end = today
  if (id === 'yesterday') {
    start = startOfDay(new Date(today.getFullYear(), today.getMonth(), today.getDate() - 1))
    end = start
  } else if (id === 'this_week') {
    start = startOfDay(new Date(today.getFullYear(), today.getMonth(), today.getDate() - today.getDay()))
    end = startOfDay(new Date(start.getFullYear(), start.getMonth(), start.getDate() + 6))
  } else if (id === 'last_week') {
    const thisSunday = startOfDay(new Date(today.getFullYear(), today.getMonth(), today.getDate() - today.getDay()))
    start = startOfDay(new Date(thisSunday.getFullYear(), thisSunday.getMonth(), thisSunday.getDate() - 7))
    end = startOfDay(new Date(start.getFullYear(), start.getMonth(), start.getDate() + 6))
  } else if (id === 'this_month') {
    start = new Date(today.getFullYear(), today.getMonth(), 1)
    end = new Date(today.getFullYear(), today.getMonth() + 1, 0)
  } else if (id === 'last_month') {
    start = new Date(today.getFullYear(), today.getMonth() - 1, 1)
    end = new Date(today.getFullYear(), today.getMonth(), 0)
  } else if (id === 'last_year') {
    start = new Date(today.getFullYear() - 1, 0, 1)
    end = new Date(today.getFullYear() - 1, 11, 31)
  }
  draftStart.value = startOfDay(start)
  draftEnd.value = startOfDay(end)
  pickingEnd.value = false
  viewDate.value = new Date(draftStart.value)
}

function onApply() {
  const start = draftStart.value || draftEnd.value
  const end = draftEnd.value || draftStart.value
  emit('apply', {
    start: start ? toYmd(start) : '',
    end: end ? toYmd(end) : '',
  })
}
</script>
