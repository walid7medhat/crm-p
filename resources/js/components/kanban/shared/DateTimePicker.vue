<template>
    <Teleport to="body">
    <div v-if="show" class="date-time-picker-overlay" @click.self="handleCancel">
        <div class="date-time-picker-modal" :class="{ 'is-dob-layout': dateOnly && dobLayout }" @click.stop>
            <!-- Month / Year (calendar nav) -->
            <!-- DOB mode: dropdowns for Month, Day, Year (fast jump to any birthday) -->
            <div v-if="dateOnly && dobLayout" class="dob-selectors">
                <div class="dob-select-field">
                    <span class="dob-select-field-label">Month</span>
                    <v-select
                        v-model="dobMonthOneBased"
                        :options="dobMonthChoices"
                        :reduce="reduceDobOption"
                        label="label"
                        placeholder="Month"
                        :clearable="false"
                        :searchable="false"
                        class="dob-v-select"
                        aria-label="Month"
                    >
                        <template #open-indicator="{ attributes }">
                            <span v-bind="attributes" class="dob-vs-open">
                                <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon" />
                            </span>
                        </template>
                    </v-select>
                </div>
                <div class="dob-select-field">
                    <span class="dob-select-field-label">Day</span>
                    <v-select
                        :key="`dob-day-${currentDate.getFullYear()}-${currentDate.getMonth()}`"
                        v-model="dobSelDayOneBased"
                        :options="dobDayChoices"
                        :reduce="reduceDobOption"
                        label="label"
                        placeholder="Day"
                        :clearable="false"
                        :searchable="false"
                        class="dob-v-select"
                        aria-label="Day"
                    >
                        <template #open-indicator="{ attributes }">
                            <span v-bind="attributes" class="dob-vs-open">
                                <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon" />
                            </span>
                        </template>
                    </v-select>
                </div>
                <div class="dob-select-field">
                    <span class="dob-select-field-label">Year</span>
                    <v-select
                        v-model="dobSelYear"
                        :options="dobYearChoices"
                        :reduce="reduceDobOption"
                        label="label"
                        placeholder="Year"
                        :clearable="false"
                        :searchable="false"
                        class="dob-v-select"
                        aria-label="Year"
                    >
                        <template #open-indicator="{ attributes }">
                            <span v-bind="attributes" class="dob-vs-open">
                                <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon" />
                            </span>
                        </template>
                    </v-select>
                </div>
                <p class="dob-helper">Or pick a day in the calendar.</p>
            </div>

            <div v-else class="picker-header">
                <button type="button" class="nav-arrow" @click="previousMonth">
                    <iconify-icon icon="lucide:chevron-left" class="arrow-icon"></iconify-icon>
                </button>
                <div class="picker-month-year">
                    <label class="picker-nav-select">
                        <select :value="currentDate.getMonth()" @change="setHeaderMonth(Number($event.target.value))">
                            <option v-for="(label, idx) in dobMonthLabels" :key="label" :value="idx">{{ label }}</option>
                        </select>
                        <iconify-icon icon="lucide:chevron-down" />
                    </label>
                    <label class="picker-nav-select picker-nav-select--year">
                        <select :value="currentDate.getFullYear()" @change="setHeaderYear(Number($event.target.value))">
                            <option v-for="y in headerYearOptions" :key="y" :value="y">{{ y }}</option>
                        </select>
                        <iconify-icon icon="lucide:chevron-down" />
                    </label>
                </div>
                <button type="button" class="nav-arrow" @click="nextMonth">
                    <iconify-icon icon="lucide:chevron-right" class="arrow-icon"></iconify-icon>
                </button>
            </div>

            <!-- Calendar Grid -->
            <div class="calendar-container" :class="{ 'is-dob-compact': dateOnly && dobLayout }">
                <!-- Weekday Headers -->
                <div class="weekday-headers">
                    <div class="weekday-header" v-for="day in weekdays" :key="day">{{ day }}</div>
                </div>

                <!-- Calendar Days -->
                <div class="calendar-grid">
                    <div 
                        v-for="(day, index) in calendarDays" 
                        :key="index"
                        class="calendar-day"
                        :class="{
                            'other-month': day.otherMonth,
                            'selected': day.selected,
                            'today': day.isToday,
                            'is-future-dob': day.isFutureDobBlocked
                        }"
                        role="gridcell"
                        @click="selectDate(day.date, day)"
                    >
                        {{ day.day }}
                    </div>
                </div>
            </div>

            <!-- Time Picker -->
            <div v-if="!dateOnly" class="time-picker-container">
                <div class="time-dropdown-wrapper">
                    <select v-model="selectedHour" class="time-dropdown">
                        <option v-for="h in hours" :key="h" :value="h">{{ String(h).padStart(2, '0') }}</option>
                    </select>
                    <iconify-icon icon="lucide:chevron-down" class="dropdown-chevron"></iconify-icon>
                </div>
                <span class="time-separator">:</span>
                <div class="time-dropdown-wrapper">
                    <select v-model="selectedMinute" class="time-dropdown">
                        <option v-for="m in minutes" :key="m" :value="m">{{ String(m).padStart(2, '0') }}</option>
                    </select>
                    <iconify-icon icon="lucide:chevron-down" class="dropdown-chevron"></iconify-icon>
                </div>
                <div class="time-dropdown-wrapper">
                    <select v-model="selectedAmPm" class="time-dropdown">
                        <option value="AM">AM</option>
                        <option value="PM">PM</option>
                    </select>
                    <iconify-icon icon="lucide:chevron-down" class="dropdown-chevron"></iconify-icon>
                </div>
            </div>

            <!-- Separator Line -->
            <div class="separator-line"></div>

            <!-- Action Buttons -->
            <div class="picker-actions">
                <button class="btn-cancel-picker" @click="handleCancel">Cancel</button>
                <button class="btn-apply-picker" @click="handleApply">Apply</button>
            </div>
        </div>
    </div>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { Modal } from 'bootstrap'
import vSelect from 'vue-select'

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    modelValue: {
        type: Date,
        default: null
    },
    dateOnly: {
        type: Boolean,
        default: false
    },
    /** Month / Day / Year dropdowns + compact calendar. */
    dobLayout: {
        type: Boolean,
        default: false
    },
    /** Block dates after today (default true when dobLayout). Set false for schedules / handover. */
    blockFutureDates: {
        type: Boolean,
        default: undefined
    }
})

const shouldBlockFuture = computed(() => {
    if (props.blockFutureDates !== undefined) return props.blockFutureDates
    return props.dobLayout && props.dateOnly
})

const maxSelectableDate = computed(() => {
    if (!shouldBlockFuture.value) return null
    const t = new Date()
    return new Date(t.getFullYear(), t.getMonth(), t.getDate())
})

const dobMonthLabels = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
]

const dobMonthChoices = computed(() =>
    dobMonthLabels.map((label, idx) => ({ value: idx + 1, label }))
)

const dobYearOptions = computed(() => {
    const todayY = new Date().getFullYear()
    const maxY = shouldBlockFuture.value ? todayY : todayY + 50
    const minY = shouldBlockFuture.value ? todayY - 110 : todayY - 30
    const list = []
    for (let y = maxY; y >= minY; y--) list.push(y)
    return list
})

function reduceDobOption(o) {
    return o.value
}

const emit = defineEmits(['update:modelValue', 'update:show', 'apply', 'cancel'])

const weekdays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']
const hours = Array.from({ length: 12 }, (_, i) => i + 1)
const minutes = Array.from({ length: 60 }, (_, i) => i)

const currentDate = ref(new Date())
const selectedDate = ref(null)
const selectedHour = ref(12)
const selectedMinute = ref(0)
const selectedAmPm = ref('PM')

const dobDaysInMonth = computed(() => {
    const y = currentDate.value.getFullYear()
    const m = currentDate.value.getMonth()
    const maxD = new Date(y, m + 1, 0).getDate()
    return Array.from({ length: maxD }, (_, i) => i + 1)
})

const dobDayChoices = computed(() =>
    dobDaysInMonth.value.map((d) => ({ value: d, label: String(d) }))
)

const dobYearChoices = computed(() =>
    dobYearOptions.value.map((y) => ({ value: y, label: String(y) }))
)

/** Sync selected date into viewed month/year, clamp invalid day / future dates. */
function normalizeDobSelection() {
    if (!props.dobLayout || !props.dateOnly) return
    const y = currentDate.value.getFullYear()
    const m = currentDate.value.getMonth()
    const maxD = new Date(y, m + 1, 0).getDate()
    let d = selectedDate.value ? Math.min(selectedDate.value.getDate(), maxD) : 1
    let cand = new Date(y, m, d)
    const maxC = maxSelectableDate.value
    if (maxC && compareCalendarOnly(cand, maxC) > 0) {
        cand = new Date(maxC)
        currentDate.value = new Date(cand.getFullYear(), cand.getMonth(), 1)
    }
    selectedDate.value = cand
}

const dobMonthOneBased = computed({
    get() {
        return currentDate.value.getMonth() + 1
    },
    set(m) {
        const y = currentDate.value.getFullYear()
        currentDate.value = new Date(y, m - 1, 1)
        normalizeDobSelection()
    }
})

const dobSelYear = computed({
    get() {
        return currentDate.value.getFullYear()
    },
    set(y) {
        const m = currentDate.value.getMonth()
        currentDate.value = new Date(y, m, 1)
        normalizeDobSelection()
    }
})

const dobSelDayOneBased = computed({
    get() {
        if (!selectedDate.value) return 1
        const y = currentDate.value.getFullYear()
        const m = currentDate.value.getMonth()
        if (
            selectedDate.value.getFullYear() === y &&
            selectedDate.value.getMonth() === m
        ) {
            return selectedDate.value.getDate()
        }
        const maxD = new Date(y, m + 1, 0).getDate()
        return Math.min(selectedDate.value.getDate(), maxD)
    },
    set(d) {
        const y = currentDate.value.getFullYear()
        const m = currentDate.value.getMonth()
        let cand = new Date(y, m, d)
        const maxC = maxSelectableDate.value
        if (maxC && compareCalendarOnly(cand, maxC) > 0) {
            cand = new Date(maxC)
        }
        selectedDate.value = cand
        currentDate.value = new Date(cand.getFullYear(), cand.getMonth(), 1)
    }
})

function compareCalendarOnly(a, b) {
    const da = new Date(a.getFullYear(), a.getMonth(), a.getDate()).getTime()
    const db = new Date(b.getFullYear(), b.getMonth(), b.getDate()).getTime()
    return da === db ? 0 : da < db ? -1 : 1
}

// Initialize with modelValue or current date
watch(() => props.modelValue, (newValue) => {
    if (newValue) {
        const date = new Date(newValue)
        selectedDate.value = new Date(date.getFullYear(), date.getMonth(), date.getDate())
        currentDate.value = new Date(date.getFullYear(), date.getMonth(), 1)
        
        const hours24 = date.getHours()
        selectedHour.value = hours24 === 0 ? 12 : (hours24 > 12 ? hours24 - 12 : hours24)
        selectedMinute.value = date.getMinutes()
        selectedAmPm.value = hours24 >= 12 ? 'PM' : 'AM'
    } else {
        const now = new Date()
        selectedDate.value = new Date(now.getFullYear(), now.getMonth(), now.getDate())
        currentDate.value = new Date(now.getFullYear(), now.getMonth(), 1)
        selectedHour.value = now.getHours() === 0 ? 12 : (now.getHours() > 12 ? now.getHours() - 12 : now.getHours())
        selectedMinute.value = now.getMinutes()
        selectedAmPm.value = now.getHours() >= 12 ? 'PM' : 'AM'
    }
    if (props.dateOnly && props.dobLayout) {
        normalizeDobSelection()
    }
}, { immediate: true })

watch(() => props.show, (newValue) => {
    if (newValue) {
        document.body.classList.add('date-time-picker-open')
        suspendBootstrapModalFocusTraps()
    } else {
        document.body.classList.remove('date-time-picker-open')
        restoreBootstrapModalFocusTraps()
    }

    if (!newValue || !props.modelValue) return
    const date = new Date(props.modelValue)
    selectedDate.value = new Date(date.getFullYear(), date.getMonth(), date.getDate())
    currentDate.value = new Date(date.getFullYear(), date.getMonth(), 1)

    const hours24 = date.getHours()
    selectedHour.value = hours24 === 0 ? 12 : (hours24 > 12 ? hours24 - 12 : hours24)
    selectedMinute.value = date.getMinutes()
    selectedAmPm.value = hours24 >= 12 ? 'PM' : 'AM'
    if (props.dateOnly && props.dobLayout) {
        normalizeDobSelection()
    }
})

const currentMonthYear = computed(() => {
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
    return `${months[currentDate.value.getMonth()]} ${currentDate.value.getFullYear()}`
})

const calendarDays = computed(() => {
    const year = currentDate.value.getFullYear()
    const month = currentDate.value.getMonth()
    
    // First day of the month
    const firstDay = new Date(year, month, 1)
    const firstDayOfWeek = firstDay.getDay()
    
    // Last day of the month
    const lastDay = new Date(year, month + 1, 0)
    const daysInMonth = lastDay.getDate()
    
    // Previous month's last days
    const prevMonthLastDay = new Date(year, month, 0).getDate()
    
    const days = []
    
    // Previous month days
    const maxSel = props.dobLayout && props.dateOnly ? maxSelectableDate.value : null
    for (let i = firstDayOfWeek - 1; i >= 0; i--) {
        const day = prevMonthLastDay - i
        const date = new Date(year, month - 1, day)
        const isFutureDobBlocked =
            maxSel &&
            compareCalendarOnly(date, maxSel) > 0
        days.push({
            day,
            date,
            otherMonth: true,
            selected: false,
            isToday: false,
            isFutureDobBlocked: !!isFutureDobBlocked
        })
    }
    
    // Current month days
    const today = new Date()
    for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(year, month, day)
        const isSelected = selectedDate.value && 
            date.getDate() === selectedDate.value.getDate() &&
            date.getMonth() === selectedDate.value.getMonth() &&
            date.getFullYear() === selectedDate.value.getFullYear()
        
        const isToday = date.getDate() === today.getDate() &&
            date.getMonth() === today.getMonth() &&
            date.getFullYear() === today.getFullYear()
        
        const isFutureDobBlocked =
            maxSel &&
            compareCalendarOnly(date, maxSel) > 0

        days.push({
            day,
            date,
            otherMonth: false,
            selected: isSelected,
            isToday,
            isFutureDobBlocked: !!isFutureDobBlocked
        })
    }
    
    // Next month days to fill the grid
    const remainingDays = 42 - days.length // 6 rows * 7 days
    for (let day = 1; day <= remainingDays; day++) {
        const date = new Date(year, month + 1, day)
        const isFutureDobBlockedNext =
            maxSel &&
            compareCalendarOnly(date, maxSel) > 0
        days.push({
            day,
            date,
            otherMonth: true,
            selected: false,
            isToday: false,
            isFutureDobBlocked: !!isFutureDobBlockedNext
        })
    }
    
    return days
})

const previousMonth = () => {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() - 1, 1)
}

const nextMonth = () => {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 1)
}

const headerYearOptions = computed(() => {
    const todayY = new Date().getFullYear()
    const maxY = shouldBlockFuture.value ? todayY : todayY + 20
    const minY = shouldBlockFuture.value ? todayY - 110 : todayY - 40
    const list = []
    for (let y = maxY; y >= minY; y--) list.push(y)
    return list
})

function setHeaderMonth(monthIndex) {
    currentDate.value = new Date(currentDate.value.getFullYear(), monthIndex, 1)
}

function setHeaderYear(year) {
    currentDate.value = new Date(year, currentDate.value.getMonth(), 1)
}

const selectDate = (date, dayCell) => {
    if (props.dateOnly && props.dobLayout && dayCell?.isFutureDobBlocked) {
        return
    }
    selectedDate.value = new Date(date.getFullYear(), date.getMonth(), date.getDate())

    // If selected date is from another month, switch to that month
    if (date.getMonth() !== currentDate.value.getMonth() || date.getFullYear() !== currentDate.value.getFullYear()) {
        currentDate.value = new Date(date.getFullYear(), date.getMonth(), 1)
    }
    if (props.dateOnly && props.dobLayout) {
        normalizeDobSelection()
    }
}

const handleApply = () => {
    if (!selectedDate.value) {
        selectedDate.value = new Date()
    }

    if (props.dateOnly && props.dobLayout && maxSelectableDate.value) {
        if (compareCalendarOnly(selectedDate.value, maxSelectableDate.value) > 0) {
            selectedDate.value = new Date(maxSelectableDate.value)
        }
    }
    
    // Convert 12-hour format to 24-hour format
    let hours24 = selectedHour.value
    if (selectedAmPm.value === 'PM' && hours24 !== 12) {
        hours24 += 12
    } else if (selectedAmPm.value === 'AM' && hours24 === 12) {
        hours24 = 0
    }
    if (props.dateOnly) {
        hours24 = 0
        selectedMinute.value = 0
    }
    
    const finalDate = new Date(
        selectedDate.value.getFullYear(),
        selectedDate.value.getMonth(),
        selectedDate.value.getDate(),
        hours24,
        selectedMinute.value,
        0
    )
    
    emit('update:modelValue', finalDate)
    emit('apply', finalDate)
    emit('update:show', false)
}

const handleCancel = () => {
    emit('update:show', false)
    emit('cancel')
}

const modalFocusRestore = []

/** Bootstrap modals trap focus — teleported date picker v-selects blur and close instantly without this. */
function suspendBootstrapModalFocusTraps() {
    document.querySelectorAll('.modal.show').forEach((el) => {
        const inst = Modal.getInstance(el)
        if (!inst?._config || inst._config.focus === false) return
        modalFocusRestore.push({ inst, focus: inst._config.focus })
        inst._config.focus = false
    })
}

function restoreBootstrapModalFocusTraps() {
    modalFocusRestore.forEach(({ inst, focus }) => {
        if (inst?._config) inst._config.focus = focus
    })
    modalFocusRestore.length = 0
}

onBeforeUnmount(() => {
    document.body.classList.remove('date-time-picker-open')
    restoreBootstrapModalFocusTraps()
})
</script>

<style scoped>
.date-time-picker-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    /* Above bootstrap modals/backdrops (style.css ~100700) and layout chrome (~50000) */
    z-index: 101000;
    --vs-dropdown-z-index: 101050;
    backdrop-filter: blur(2px);
}

.date-time-picker-modal {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0px 10px 40px rgba(0, 0, 0, 0.2);
    padding: 24px 20px;
    min-width: 340px;
    max-width: 400px;
    width: 100%;
    overflow: visible;
    position: relative;
    z-index: 1;
}

.date-time-picker-modal.is-dob-layout {
    min-width: 320px;
    max-width: 380px;
    padding: 16px 16px 14px;
}

.dob-selectors {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid #f1f5f9;
}

.dob-helper {
    grid-column: 1 / -1;
    margin: 0;
    padding-top: 4px;
    font-size: 11px;
    line-height: 1.35;
    color: #94a3b8;
}

.dob-select-field {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
    position: relative;
    z-index: 2;
}

.dob-select-field:has(.vs--open) {
    z-index: 20;
}

.dob-select-field-label {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #64748b;
}

.dob-vs-open {
    display: inline-flex;
    align-items: center;
    cursor: pointer;
}

/* Compact searchable vue-select (DOB row) */
:deep(.dob-v-select.v-select .vs__dropdown-toggle) {
    min-height: 30px !important;
    padding: 0 4px !important;
    border-radius: 10px !important;
    border: 1px solid #e2e8f0 !important;
    background: #fafbfc !important;
}

:deep(.dob-v-select.v-select:not(.vs--open) .vs__dropdown-toggle:hover) {
    border-color: #cbd5e1 !important;
    background: #fff !important;
}

:deep(.dob-v-select.v-select.vs--open .vs__dropdown-toggle) {
    border-color: #1a2f5b !important;
    box-shadow: 0 0 0 2px rgba(26, 47, 91, 0.12);
    background: #fff !important;
}

:deep(.dob-v-select .vs__selected-options) {
    font-size: 11px !important;
}

:deep(.dob-v-select .vs__selected),
:deep(.dob-v-select .vs__search),
:deep(.dob-v-select input.vs__search) {
    font-size: 11px !important;
    font-weight: 500 !important;
    line-height: 1.2 !important;
}

:deep(.dob-v-select .vs__search::placeholder),
:deep(.dob-v-select input.vs__search::placeholder) {
    font-size: 10px !important;
    color: #94a3b8 !important;
    font-weight: 400 !important;
}

:deep(.dob-v-select .vs__dropdown-menu) {
    z-index: 100 !important;
    max-height: 220px !important;
    overflow-y: auto !important;
}

:deep(.dob-v-select .vs__dropdown-menu .vs__dropdown-option) {
    font-size: 11px !important;
    padding: 6px 10px !important;
}

:deep(.dob-v-select .vs__open-indicator),
:deep(.dob-v-select .vs__open-indicator .vs__open-indicator-icon) {
    transform: scale(0.92);
}

.date-time-picker-modal.is-dob-layout .calendar-container.is-dob-compact {
    margin-bottom: 8px;
}

.date-time-picker-modal.is-dob-layout .is-dob-compact .weekday-headers {
    gap: 2px;
    margin-bottom: 4px;
}

.date-time-picker-modal.is-dob-layout .is-dob-compact .weekday-header {
    padding: 2px 1px;
    font-size: 10px;
    font-weight: 600;
}

.date-time-picker-modal.is-dob-layout .is-dob-compact .calendar-grid {
    gap: 3px;
}

.date-time-picker-modal.is-dob-layout .is-dob-compact .calendar-day {
    aspect-ratio: unset;
    min-height: 0;
    height: 30px;
    max-height: 30px;
    font-size: 12px;
    border-radius: 8px;
}

.date-time-picker-modal.is-dob-layout .is-dob-compact .calendar-day.selected {
    border-radius: 50%;
}

.date-time-picker-modal.is-dob-layout .separator-line {
    margin-bottom: 10px;
}

.date-time-picker-modal.is-dob-layout .picker-actions {
    padding-top: 0;
}

.date-time-picker-modal.is-dob-layout .btn-cancel-picker,
.date-time-picker-modal.is-dob-layout .btn-apply-picker {
    padding: 7px 16px;
    font-size: 13px;
}

/* Header */
.picker-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    padding: 0 4px;
}

.nav-arrow {
    background: transparent;
    border: none;
    padding: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: background 0.2s;
    color: #64748B;
}

.nav-arrow:hover {
    background: #F1F5F9;
}

.arrow-icon {
    font-size: 20px;
    color: #64748B;
}

.month-year-text {
    font-size: 16px;
    font-weight: 600;
    color: #0B0736;
    text-align: center;
    flex: 1;
    letter-spacing: -0.2px;
}

.picker-month-year {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    flex: 1;
}

.picker-nav-select {
    position: relative;
    display: inline-flex;
    align-items: center;
}

.picker-nav-select select {
    appearance: none;
    -webkit-appearance: none;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    border-radius: 10px;
    padding: 7px 28px 7px 12px;
    font-size: 13px;
    font-weight: 600;
    color: #0b0736;
    cursor: pointer;
    min-width: 118px;
}

.picker-nav-select--year select {
    min-width: 88px;
}

.picker-nav-select select:focus {
    outline: none;
    border-color: #f99f1c;
    box-shadow: 0 0 0 3px rgba(249, 159, 28, 0.16);
    background: #fff;
}

.picker-nav-select iconify-icon {
    position: absolute;
    right: 8px;
    pointer-events: none;
    font-size: 14px;
    color: #94a3b8;
}

/* Calendar */
.calendar-container {
    margin-bottom: 20px;
}

.weekday-headers {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
    margin-bottom: 8px;
}

.weekday-header {
    text-align: center;
    font-size: 12px;
    font-weight: 400;
    color: #475569;
    padding: 8px 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
}

.calendar-day {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 400;
    color: #0B0736;
    cursor: pointer;
    border-radius: 50%;
    transition: all 0.2s;
    position: relative;
    min-height: 36px;
}

.calendar-day:hover:not(.selected) {
    background: #F1F5F9;
    border-radius: 50%;
}

.calendar-day.other-month {
    color: #CBD5E1;
}

.calendar-day.selected {
    background: #733E87;
    color: #fff;
    font-weight: 500;
    border-radius: 50%;
}

.calendar-day.selected:hover {
    background: #733E87;
    opacity: 0.9;
}

.calendar-day.today:not(.selected) {
    font-weight: 600;
    color: #0B0736;
}

.calendar-day.is-future-dob {
    color: #e2e8f0 !important;
    cursor: not-allowed;
    pointer-events: none;
}

.calendar-day.is-future-dob.other-month {
    color: #f1f5f9 !important;
}

/* Time Picker */
.time-picker-container {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-bottom: 16px;
    padding: 0 8px;
}

.time-dropdown-wrapper {
    position: relative;
    display: inline-block;
}

.time-dropdown {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 8px 32px 8px 12px;
    font-size: 14px;
    font-weight: 400;
    color: #0B0736;
    cursor: pointer;
    min-width: 60px;
    text-align: center;
    transition: all 0.2s;
}

.time-dropdown:hover {
    border-color: #CBD5E1;
}

.time-dropdown:focus {
    outline: none;
    border-color: #733E87;
    box-shadow: 0 0 0 3px rgba(250, 163, 0, 0.1);
}

.dropdown-chevron {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 16px;
    color: #64748B;
    pointer-events: none;
}

.time-separator {
    font-size: 18px;
    font-weight: 500;
    color: #0B0736;
    padding: 0 4px;
}

/* Separator Line */
.separator-line {
    height: 1px;
    background: repeating-linear-gradient(
        to right,
        #A6C1FF 0px,
        #A6C1FF 3px,
        transparent 3px,
        transparent 6px
    );
    margin-bottom: 16px;
    border: none;
}

/* Action Buttons */
.picker-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
}

.btn-cancel-picker {
    background: #F4F4F4;
    border: none;
    padding: 8px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 400;
    color: #0B0736;
    cursor: pointer;
    transition: all 0.2s;
    min-width: 70px;
}

.btn-cancel-picker:hover {
    background: #E2E8F0;
}

.btn-apply-picker {
    background: #1A2F5B;
    border: none;
    padding: 8px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 400;
    color: #fff;
    cursor: pointer;
    transition: all 0.2s;
    min-width: 70px;
}

.btn-apply-picker:hover {
    background: #152547;
}
</style>

<!-- Fallback when any portaled vue-select is used inside the picker -->
<style>
body.date-time-picker-open .vs__dropdown-menu {
    z-index: 101050 !important;
    max-height: 220px !important;
    overflow-y: auto !important;
}
</style>
