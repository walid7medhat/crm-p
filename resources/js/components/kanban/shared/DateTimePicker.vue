<template>
    <div v-if="show" class="date-time-picker-overlay" @click.self="handleCancel">
        <div class="date-time-picker-modal" @click.stop>
            <!-- Month/Year Header -->
            <div class="picker-header">
                <button class="nav-arrow" @click="previousMonth">
                    <iconify-icon icon="lucide:chevron-left" class="arrow-icon"></iconify-icon>
                </button>
                <div class="month-year-text">{{ currentMonthYear }}</div>
                <button class="nav-arrow" @click="nextMonth">
                    <iconify-icon icon="lucide:chevron-right" class="arrow-icon"></iconify-icon>
                </button>
            </div>

            <!-- Calendar Grid -->
            <div class="calendar-container">
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
                            'today': day.isToday
                        }"
                        @click="selectDate(day.date)"
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
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'

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
    }
})

const emit = defineEmits(['update:modelValue', 'update:show', 'apply', 'cancel'])

const weekdays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']
const hours = Array.from({ length: 12 }, (_, i) => i + 1)
const minutes = Array.from({ length: 60 }, (_, i) => i)

const currentDate = ref(new Date())
const selectedDate = ref(null)
const selectedHour = ref(12)
const selectedMinute = ref(0)
const selectedAmPm = ref('PM')

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
}, { immediate: true })

watch(() => props.show, (newValue) => {
    if (newValue && props.modelValue) {
        const date = new Date(props.modelValue)
        selectedDate.value = new Date(date.getFullYear(), date.getMonth(), date.getDate())
        currentDate.value = new Date(date.getFullYear(), date.getMonth(), 1)
        
        const hours24 = date.getHours()
        selectedHour.value = hours24 === 0 ? 12 : (hours24 > 12 ? hours24 - 12 : hours24)
        selectedMinute.value = date.getMinutes()
        selectedAmPm.value = hours24 >= 12 ? 'PM' : 'AM'
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
    for (let i = firstDayOfWeek - 1; i >= 0; i--) {
        const day = prevMonthLastDay - i
        const date = new Date(year, month - 1, day)
        days.push({
            day,
            date,
            otherMonth: true,
            selected: false,
            isToday: false
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
        
        days.push({
            day,
            date,
            otherMonth: false,
            selected: isSelected,
            isToday
        })
    }
    
    // Next month days to fill the grid
    const remainingDays = 42 - days.length // 6 rows * 7 days
    for (let day = 1; day <= remainingDays; day++) {
        const date = new Date(year, month + 1, day)
        days.push({
            day,
            date,
            otherMonth: true,
            selected: false,
            isToday: false
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

const selectDate = (date) => {
    selectedDate.value = new Date(date.getFullYear(), date.getMonth(), date.getDate())
    
    // If selected date is from another month, switch to that month
    if (date.getMonth() !== currentDate.value.getMonth() || date.getFullYear() !== currentDate.value.getFullYear()) {
        currentDate.value = new Date(date.getFullYear(), date.getMonth(), 1)
    }
}

const handleApply = () => {
    if (!selectedDate.value) {
        selectedDate.value = new Date()
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
    z-index: 10000;
    backdrop-filter: blur(2px);
}

.date-time-picker-modal {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0px 10px 40px rgba(0, 0, 0, 0.2);
    padding: 24px 20px;
    min-width: 320px;
    max-width: 360px;
    width: 100%;
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
    color: #01062C;
    text-align: center;
    flex: 1;
    letter-spacing: -0.2px;
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
    color: #01062C;
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
    background: #FAA300;
    color: #fff;
    font-weight: 500;
    border-radius: 50%;
}

.calendar-day.selected:hover {
    background: #FAA300;
    opacity: 0.9;
}

.calendar-day.today:not(.selected) {
    font-weight: 600;
    color: #01062C;
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
    color: #01062C;
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
    border-color: #FAA300;
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
    color: #01062C;
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
    color: #01062C;
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
.advanced-date-trigger{
    border: none important;
    border-radius: 12px !important;
}
</style>
