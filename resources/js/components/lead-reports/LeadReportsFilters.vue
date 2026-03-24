<template>
  <section class="lr-filters">
    <div class="lr-header-row">
      <div class="lr-filter-title">Search Filter</div>
      <div class="lr-reset-row">
        <button class="lr-link" @click="$emit('clear')">Clear</button>
        <button class="lr-link danger" @click="$emit('reset')">Reset Filter</button>
      </div>
    </div>
    <div class="lr-grid">
      <div class="lr-field">
        <label>Search</label>
        <div class="lr-input-wrap">
          <input
            :value="searchQuery"
            type="text"
            placeholder="Enter lead name etc..."
            @input="$emit('update:searchQuery', $event.target.value)"
          />
          <iconify-icon icon="lucide:search" />
        </div>
      </div>

      <div class="lr-field">
        <label>Select Branch</label>
        <v-select
          :model-value="branch"
          :options="branchOptions"
          :searchable="true"
          :clearable="false"
          :placeholder="'Not Selected'"
          class="lr-v-select"
          @update:model-value="$emit('update:branch', $event || '')"
        />
      </div>

      <div class="lr-field">
        <label>Date</label>
        <div class="lr-date-wrap">
          <button type="button" class="lr-date-btn" @click="showDateModal = true">
            <span>{{ dateRangeDisplay }}</span>
            <iconify-icon icon="lucide:calendar-days" />
          </button>
        </div>
      </div>

      <div class="lr-field">
        <label>Advanced Filter</label>
        <div class="lr-advanced-wrap">
          <v-select
            :model-value="stage"
            :options="stageOptions"
            :searchable="true"
            :clearable="true"
            :placeholder="'Search lead Stage, etc...'"
            class="lr-v-select"
            @update:model-value="$emit('update:stage', $event || '')"
          />
          <button type="button" class="lr-advanced-btn" title="Open advanced" @click="showAdvancedModal = true">
            <iconify-icon icon="lucide:sliders-horizontal" />
          </button>
        </div>
      </div>

      <button class="lr-search-btn" @click="$emit('submit')">Search</button>
    </div>
  </section>

  <div v-if="showAdvancedModal" class="lr-modal-backdrop" @click.self="showAdvancedModal = false">
    <div class="lr-advanced-modal">
      <div class="lr-advanced-left">
        <button
          v-for="chip in advancedLeftChips"
          :key="chip"
          type="button"
          class="lr-pill"
          :class="{ active: chip === tempDatePreset || chip === tempBranch }"
          @click="applyLeftChip(chip)"
        >
          {{ chip }}
        </button>
      </div>
      <div class="lr-advanced-right">
        <button type="button" class="lr-close-btn" @click="showAdvancedModal = false">
          <iconify-icon icon="lucide:x" />
        </button>

        <div class="lr-modal-field">
          <label>Select Deal Type</label>
          <v-select v-model="tempDealType" :options="dealTypeOptions" :searchable="true" :clearable="true" placeholder="Not Selected" class="lr-v-select" />
        </div>

        <div class="lr-modal-field">
          <label>Select Branch</label>
          <v-select v-model="tempBranch" :options="branchOptions" :searchable="true" :clearable="true" placeholder="Not Selected" class="lr-v-select" />
        </div>

        <div class="lr-modal-field">
          <label>Select Agents / Team</label>
          <v-select v-model="tempAgentTeam" :options="agentTeamOptions" :searchable="true" :clearable="true" placeholder="Not Selected" class="lr-v-select" />
        </div>

        <div class="lr-modal-field">
          <label>Select Stages</label>
          <v-select v-model="tempStage" :options="stageOptions" :searchable="true" :clearable="true" placeholder="Not Selected" class="lr-v-select" />
        </div>

        <div class="lr-modal-field">
          <label>Date</label>
          <button type="button" class="lr-date-btn" @click="showDateModal = true">
            <span>{{ tempDateRange || '--/--/--' }}</span>
            <iconify-icon icon="lucide:calendar-days" />
          </button>
        </div>

        <div class="lr-modal-actions">
          <button type="button" class="btn-cancel" @click="clearAdvanced">Clear</button>
          <button type="button" class="btn-apply" @click="applyAdvancedFilters">Search</button>
        </div>
      </div>
    </div>
  </div>

  <div v-if="showDateModal" class="lr-modal-backdrop" @click.self="showDateModal = false">
    <div class="lr-date-modal">
      <div class="lr-date-left">
        <button
          v-for="preset in datePresets"
          :key="preset"
          type="button"
          class="lr-date-preset"
          :class="{ active: selectedPreset === preset }"
          @click="selectPresetRange(preset)"
        >
          {{ preset }}
        </button>
      </div>

      <div class="lr-date-right">
        <div class="lr-calendar-head">
          <button type="button" @click="changeMonth(-1)"><iconify-icon icon="lucide:chevron-left" /></button>
          <div>{{ monthLabel }}</div>
          <button type="button" @click="changeMonth(1)"><iconify-icon icon="lucide:chevron-right" /></button>
        </div>

        <div class="lr-weekdays">
          <span v-for="d in weekDays" :key="d">{{ d }}</span>
        </div>

        <div class="lr-calendar-grid">
          <button
            v-for="cell in calendarCells"
            :key="cell.key"
            type="button"
            class="lr-day"
            :class="{
              muted: !cell.currentMonth,
              selected: isSelectedDate(cell.date),
              inrange: isInRange(cell.date)
            }"
            @click="pickDate(cell.date)"
          >
            {{ cell.day }}
          </button>
        </div>

        <div class="lr-date-actions large">
          <button type="button" class="btn-cancel" @click="showDateModal = false">Cancel</button>
          <button type="button" class="btn-apply" @click="applyDateRange">Apply</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
const emit = defineEmits([
  'update:searchQuery',
  'update:branch',
  'update:stage',
  'update:dateRange',
  'openAdvanced',
  'submit',
  'clear',
  'reset'
])

const datePresets = ['Today', 'Yesterday', 'This Week', 'Last Week', 'This Month', 'Last Month', 'Last Year', 'Custom Date']
const showDateModal = ref(false)
const showAdvancedModal = ref(false)
const selectedPreset = ref('')
const startDate = ref(null)
const endDate = ref(null)
const calendarMonth = ref(new Date())
const tempDealType = ref('')
const tempAgentTeam = ref('')
const tempDatePreset = ref('')
const tempDateRange = ref('')
const tempBranch = ref('')
const tempStage = ref('')
const dealTypeOptions = ['Primary Off Plan', 'Secondary', 'Rental']
const agentTeamOptions = ['All Team', 'Abu Dhabi Team', 'Dubai Team']
const weekDays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']

const props = defineProps({
  searchQuery: { type: String, default: '' },
  branch: { type: String, default: 'All Team' },
  stage: { type: String, default: '' },
  dateRange: { type: String, default: 'Last Month' },
  branchOptions: { type: Array, default: () => [] },
  stageOptions: { type: Array, default: () => [] }
})

const dateRangeDisplay = computed(() => props.dateRange || '--/--/--')
const monthLabel = computed(() => calendarMonth.value.toLocaleString('en-US', { month: 'long', year: 'numeric' }))
const advancedLeftChips = computed(() => [...props.branchOptions, ...datePresets.filter((item) => item !== 'Custom Date')])

const startOfDay = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate())
const formatYmd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const sameDay = (a, b) => a && b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate()
const inRange = (d, a, b) => a && b && startOfDay(d) >= startOfDay(a) && startOfDay(d) <= startOfDay(b)

const calendarCells = computed(() => {
  const y = calendarMonth.value.getFullYear()
  const m = calendarMonth.value.getMonth()
  const first = new Date(y, m, 1)
  const offset = first.getDay()
  const daysInMonth = new Date(y, m + 1, 0).getDate()
  const prevDays = new Date(y, m, 0).getDate()
  const cells = []

  for (let i = offset - 1; i >= 0; i -= 1) {
    const day = prevDays - i
    const date = new Date(y, m - 1, day)
    cells.push({ key: `p-${day}`, day, date, currentMonth: false })
  }
  for (let day = 1; day <= daysInMonth; day += 1) {
    const date = new Date(y, m, day)
    cells.push({ key: `c-${day}`, day, date, currentMonth: true })
  }
  while (cells.length < 42) {
    const day = cells.length - (offset + daysInMonth) + 1
    const date = new Date(y, m + 1, day)
    cells.push({ key: `n-${day}`, day, date, currentMonth: false })
  }
  return cells
})

const selectPresetRange = (preset) => {
  selectedPreset.value = preset
  tempDatePreset.value = preset
  const today = new Date()
  const y = today.getFullYear()
  const m = today.getMonth()

  if (preset === 'Custom Date') return
  if (preset === 'Today') {
    startDate.value = startOfDay(today)
    endDate.value = startOfDay(today)
  } else if (preset === 'Yesterday') {
    const d = new Date(y, m, today.getDate() - 1)
    startDate.value = startOfDay(d)
    endDate.value = startOfDay(d)
  } else if (preset === 'This Week') {
    const s = new Date(y, m, today.getDate() - today.getDay())
    const e = new Date(s.getFullYear(), s.getMonth(), s.getDate() + 6)
    startDate.value = startOfDay(s)
    endDate.value = startOfDay(e)
  } else if (preset === 'Last Week') {
    const end = new Date(y, m, today.getDate() - today.getDay() - 1)
    const start = new Date(end.getFullYear(), end.getMonth(), end.getDate() - 6)
    startDate.value = startOfDay(start)
    endDate.value = startOfDay(end)
  } else if (preset === 'This Month') {
    startDate.value = new Date(y, m, 1)
    endDate.value = new Date(y, m + 1, 0)
  } else if (preset === 'Last Month') {
    startDate.value = new Date(y, m - 1, 1)
    endDate.value = new Date(y, m, 0)
  } else if (preset === 'Last Year') {
    startDate.value = new Date(y - 1, 0, 1)
    endDate.value = new Date(y - 1, 11, 31)
  }
  calendarMonth.value = new Date(startDate.value.getFullYear(), startDate.value.getMonth(), 1)
}

const pickDate = (date) => {
  if (!startDate.value || (startDate.value && endDate.value)) {
    startDate.value = startOfDay(date)
    endDate.value = null
    selectedPreset.value = 'Custom Date'
    return
  }
  if (startOfDay(date) < startOfDay(startDate.value)) {
    endDate.value = startDate.value
    startDate.value = startOfDay(date)
  } else {
    endDate.value = startOfDay(date)
  }
}

const isSelectedDate = (date) => sameDay(date, startDate.value) || sameDay(date, endDate.value)
const isInRange = (date) => inRange(date, startDate.value, endDate.value)

const changeMonth = (delta) => {
  calendarMonth.value = new Date(calendarMonth.value.getFullYear(), calendarMonth.value.getMonth() + delta, 1)
}

const applyDateRange = () => {
  if (startDate.value && endDate.value) {
    const value = `${formatYmd(startDate.value)} to ${formatYmd(endDate.value)}`
    tempDateRange.value = value
    emit('update:dateRange', value)
  } else if (selectedPreset.value && selectedPreset.value !== 'Custom Date') {
    tempDateRange.value = selectedPreset.value
    emit('update:dateRange', selectedPreset.value)
  }
  showDateModal.value = false
}

const applyLeftChip = (chip) => {
  if (props.branchOptions.includes(chip)) {
    tempBranch.value = chip
  } else {
    tempDatePreset.value = chip
    selectPresetRange(chip)
  }
}

const clearAdvanced = () => {
  tempDealType.value = ''
  tempAgentTeam.value = ''
  tempBranch.value = ''
  tempStage.value = ''
  tempDatePreset.value = ''
  tempDateRange.value = ''
}

const applyAdvancedFilters = () => {
  emit('update:branch', tempBranch.value || props.branch)
  emit('update:stage', tempStage.value || '')
  if (tempDateRange.value) emit('update:dateRange', tempDateRange.value)
  emit('submit')
  showAdvancedModal.value = false
}
</script>

<style scoped>
.lr-filters { border: 1px solid #ebeef3; border-radius: 14px; background: #fff; margin: 12px 16px; padding: 12px 14px; }
.lr-header-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.lr-filter-title { font-size: 13px; font-weight: 600; color: #10152f; }
.lr-grid { display: grid; gap: 10px; grid-template-columns: 1.2fr 1.15fr 1.1fr 1.3fr auto; align-items: end; }
.lr-field label { display: block; font-size: 11px; margin-bottom: 5px; color: #10152f; font-weight: 600; }
.lr-input-wrap { height: 40px; border: 1px solid #e4e8f0; border-radius: 10px; background: #fff; display: flex; align-items: center; padding: 0 11px; color: #6f7282; }
.lr-input-wrap input { border: none; width: 100%; outline: none; font-size: 12px; color: #4f5669; }
.lr-input-wrap input::placeholder { color: #a2a8b6; }
.lr-input-wrap iconify-icon { color: #9aa0ad; font-size: 14px; }

.lr-date-wrap { position: relative; z-index: 120; }
.lr-date-btn { width: 100%; height: 40px; border: 1px solid #e4e8f0; border-radius: 10px; background: #fff; color: #7c8393; font-size: 12px; padding: 0 11px; display: flex; align-items: center; justify-content: space-between; }
.lr-date-actions { margin-top: 10px; display: flex; justify-content: flex-end; gap: 8px; }
.btn-cancel, .btn-apply { height: 38px; border-radius: 999px; font-size: 30px; padding: 0 20px; font-size: 12px; }
.btn-cancel { border: 1px solid #e4e8f0; background: #fff; color: #61697a; }
.btn-apply { border: 1px solid #020b38; background: #020b38; color: #fff; }

.lr-advanced-wrap { display: grid; grid-template-columns: 1fr 34px; gap: 6px; align-items: center; position: relative; z-index: 110; }
.lr-advanced-btn { height: 40px; border: 1px solid #e4e8f0; background: #fff; border-radius: 10px; color: #7f8799; display: grid; place-items: center; }
.lr-search-btn { height: 40px; border: none; border-radius: 22px; background: #020b38; color: #fff; padding: 0 20px; font-size: 12px; font-weight: 600; }
.lr-reset-row { display: flex; justify-content: flex-end; gap: 10px; }
.lr-link { border: none; background: transparent; color: #9ca2ae; font-size: 12px; padding: 0; }
.lr-link.danger { color: #df525c; font-weight: 500; }

:deep(.lr-v-select) { width: 100%; }
:deep(.lr-v-select .vs__dropdown-toggle) {
  min-height: 40px;
  border-radius: 10px;
  border: 1px solid #e4e8f0;
  padding: 0 6px;
}
:deep(.lr-v-select .vs__selected) { color: #4f5669; font-size: 12px; margin: 0; }
:deep(.lr-v-select .vs__search) { font-size: 12px; color: #4f5669; }
:deep(.lr-v-select .vs__search::placeholder) { color: #a2a8b6; }
:deep(.lr-v-select .vs__open-indicator) { fill: #9aa0ad; }
:deep(.lr-v-select .vs__clear) { fill: #9aa0ad; }
:deep(.lr-v-select .vs__dropdown-menu) {
  border: 1px solid #eceff5;
  border-radius: 10px;
  box-shadow: 0 10px 18px rgba(12, 20, 45, 0.12);
  font-size: 12px;
  z-index: 240;
}

.lr-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(12, 20, 45, 0.24);
  backdrop-filter: blur(2px);
  display: grid;
  place-items: center;
  z-index: 1200;
}
.lr-advanced-modal {
  width: min(92vw, 1120px);
  border-radius: 14px;
  background: #fff;
  border: 1px solid #eceff5;
  box-shadow: 0 16px 34px rgba(12, 20, 45, 0.2);
  display: grid;
  grid-template-columns: 220px 1fr;
  overflow: hidden;
}
.lr-advanced-left {
  border-right: 1px solid #f0f2f6;
  padding: 14px 10px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.lr-pill {
  height: 36px;
  border-radius: 999px;
  border: 1px solid #d8deea;
  background: #fff;
  color: #697183;
  font-size: 28px;
  font-size: 12px;
  text-align: left;
  padding: 0 14px;
}
.lr-pill.active {
  background: #020b38;
  border-color: #020b38;
  color: #fff;
}
.lr-advanced-right {
  padding: 14px;
  position: relative;
}
.lr-close-btn {
  position: absolute;
  right: 10px;
  top: 10px;
  border: none;
  background: transparent;
  color: #4e5668;
}
.lr-modal-field { margin-bottom: 10px; }
.lr-modal-field label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: #10152f;
  margin-bottom: 6px;
}
.lr-modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 16px;
}

.lr-date-modal {
  width: min(92vw, 700px);
  background: #fff;
  border: 1px solid #eceff5;
  border-radius: 16px;
  box-shadow: 0 16px 34px rgba(12, 20, 45, 0.2);
  display: grid;
  grid-template-columns: 190px 1fr;
  overflow: hidden;
}
.lr-date-left {
  border-right: 1px solid #eceff5;
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.lr-date-preset {
  height: 44px;
  border-radius: 999px;
  border: 1px solid #dce1ec;
  background: #fff;
  color: #10152f;
  font-size: 13px;
  text-align: left;
  padding: 0 18px;
}
.lr-date-preset.active {
  background: #020b38;
  border-color: #020b38;
  color: #fff;
}
.lr-date-right {
  padding: 14px 16px;
}
.lr-calendar-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}
.lr-calendar-head button {
  border: none;
  background: transparent;
  color: #6f7688;
}
.lr-calendar-head div {
  font-size: 36px;
  font-size: 16px;
  color: #08113a;
  font-weight: 700;
}
.lr-weekdays {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  margin-bottom: 8px;
}
.lr-weekdays span {
  text-align: center;
  font-size: 12px;
  color: #686f80;
  padding: 4px 0;
}
.lr-calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 6px;
}
.lr-day {
  height: 42px;
  border: none;
  background: transparent;
  border-radius: 999px;
  font-size: 14px;
  color: #171f40;
}
.lr-day.muted { color: #c2c7d2; }
.lr-day.inrange { background: #f9f2df; border-radius: 10px; }
.lr-day.selected { background: #f7a600; color: #fff; font-weight: 700; }
.lr-date-actions.large {
  margin-top: 12px;
  border-top: 1px solid #eceff5;
  padding-top: 12px;
}

@media (max-width: 1200px) {
  .lr-grid { grid-template-columns: 1fr 1fr; }
  .lr-advanced-modal { grid-template-columns: 1fr; }
  .lr-advanced-left { border-right: none; border-bottom: 1px solid #eceff5; }
  .lr-date-modal { grid-template-columns: 1fr; }
  .lr-date-left { border-right: none; border-bottom: 1px solid #eceff5; }
}
</style>
