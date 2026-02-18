<template>
    <div class="history-search-form">
        <!-- Type (keyword search) -->
        <div class="filter-row">
            <label class="filter-label">Type</label>
            <div class="filter-input-wrap">
                <input
                    type="text"
                    class="filter-input"
                    v-model="localSearch"
                    placeholder="Not Specified"
                />
            </div>
        </div>

        <!-- Event Type -->
        <div class="filter-row">
            <label class="filter-label">Event Type</label>
            <div class="filter-input-wrap">
                <select class="filter-input filter-select" v-model="localAction">
                    <option value="">Not Specified</option>
                    <option value="view">View</option>
                    <option value="stage_changed">Status Changed</option>
                    <option value="revert">Revert</option>
                    <option value="assigned">Responsible Person Changed</option>
                    <option value="updated">Lead Updated</option>
                    <option value="created">Lead Created</option>
                </select>
                <iconify-icon icon="lucide:chevrons-up-down" class="input-chevron"></iconify-icon>
            </div>
        </div>

        <!-- Created By (searchable dropdown: type to search) -->
        <div class="filter-row">
            <label class="filter-label">Created By</label>
            <div class="filter-input-wrap created-by-select-wrap">
                <v-select
                    v-model="localUser"
                    :options="userOptions"
                    :reduce="opt => opt.value"
                    label="label"
                    placeholder="Text here"
                    :clearable="true"
                    class="history-v-select"
                >
                    <template #open-indicator="{ attributes }">
                        <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevrons-up-down" class="vs-open-icon"></iconify-icon>
                        </span>
                    </template>
                </v-select>
            </div>
        </div>

        <!-- Date -->
        <div class="filter-row">
            <label class="filter-label">Date</label>
            <div class="filter-input-wrap filter-date-wrap" ref="dateWrapRef">
                <input
                    type="text"
                    class="filter-input filter-date-display"
                    readonly
                    :value="dateDisplayText"
                    placeholder="Any Date"
                    @click="showDateDropdown = !showDateDropdown"
                />
                <iconify-icon icon="lucide:chevrons-up-down" class="input-chevron"></iconify-icon>
                <div v-if="showDateDropdown" class="date-dropdown" @click.stop>
                    <div class="date-dropdown-row">
                        <span class="date-dropdown-label">From</span>
                        <input type="date" class="filter-input date-inline" v-model="dateFrom" />
                    </div>
                    <div class="date-dropdown-row">
                        <span class="date-dropdown-label">To</span>
                        <input type="date" class="filter-input date-inline" v-model="dateTo" />
                    </div>
                    <button type="button" class="date-dropdown-close" @click="showDateDropdown = false">Done</button>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="filter-actions">
            <button type="button" class="btn-reset" @click="resetFilters">Reset</button>
            <button type="button" class="btn-search" @click="applySearch">Search</button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'

const props = defineProps({
    initialSearch: {
        type: String,
        default: ''
    },
    initialAction: {
        type: String,
        default: ''
    },
    initialUser: {
        type: String,
        default: ''
    },
    initialDateFrom: {
        type: String,
        default: ''
    },
    initialDateTo: {
        type: String,
        default: ''
    },
    users: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['search', 'close'])

const localSearch = ref(props.initialSearch)
const localAction = ref(props.initialAction)
const localUser = ref(props.initialUser || '')
const userOptions = computed(() =>
    (props.users || []).map((u) => ({ label: u.name || 'Unknown', value: String(u.id) }))
)
const dateFrom = ref(props.initialDateFrom || '')
const dateTo = ref(props.initialDateTo || '')
const showDateDropdown = ref(false)
const dateWrapRef = ref(null)

function formatDateForDisplay(isoDate) {
    if (!isoDate) return ''
    const d = new Date(isoDate + 'T12:00:00')
    if (isNaN(d.getTime())) return isoDate
    const day = d.getDate()
    const month = d.toLocaleDateString('en-GB', { month: 'short' })
    const year = d.getFullYear()
    return `${day} ${month} ${year}`
}

const dateDisplayText = computed(() => {
    if (dateFrom.value && dateTo.value) {
        return `${formatDateForDisplay(dateFrom.value)} – ${formatDateForDisplay(dateTo.value)}`
    }
    if (dateFrom.value) return formatDateForDisplay(dateFrom.value)
    if (dateTo.value) return formatDateForDisplay(dateTo.value)
    return ''
})

function handleClickOutside(e) {
    if (showDateDropdown.value && dateWrapRef.value && !dateWrapRef.value.contains(e.target)) {
        showDateDropdown.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})
onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})

watch(() => props.initialSearch, (val) => { localSearch.value = val })
watch(() => props.initialAction, (val) => { localAction.value = val })
watch(() => props.initialUser, (val) => { localUser.value = val || '' })
watch(() => props.initialDateFrom, (val) => { dateFrom.value = val || '' })
watch(() => props.initialDateTo, (val) => { dateTo.value = val || '' })

const resetFilters = () => {
    localSearch.value = ''
    localAction.value = ''
    localUser.value = ''
    dateFrom.value = ''
    dateTo.value = ''
}

const applySearch = () => {
    showDateDropdown.value = false
    emit('search', {
        search: localSearch.value,
        action: localAction.value,
        user: localUser.value,
        dateFrom: dateFrom.value,
        dateTo: dateTo.value
    })
}
</script>

<style scoped>
.history-search-form {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    padding: 0;
}

.filter-row {
    margin-bottom: 14px;
}

.filter-row:last-of-type {
    margin-bottom: 18px;
}

.filter-label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
}

.filter-input-wrap {
    position: relative;
    width: 100%;
}

.filter-input {
    width: 100%;
    height: 40px;
    padding: 8px 36px 8px 12px;
    border: 1px solid #E5E7EB;
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
    color: #374151;
    background: #fff;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.filter-input::placeholder {
    color: #9CA3AF;
}

.filter-input:focus {
    outline: none;
    border-color: #D1D5DB;
    box-shadow: 0 0 0 2px rgba(229, 231, 235, 0.5);
}

.filter-date-display {
    cursor: pointer;
    color: #374151;
}

.filter-select {
    appearance: none;
    cursor: pointer;
}

.input-chevron {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #6B7280;
    font-size: 14px;
    pointer-events: none;
}

.filter-date-wrap {
    position: relative;
}

.date-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    margin-top: 4px;
    padding: 12px;
    background: #fff;
    border: 1px solid #E5E7EB;
    border-radius: 8px;
    box-shadow: 0 8px 20px -4px rgba(0, 0, 0, 0.08);
    z-index: 20;
}

.date-dropdown-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.date-dropdown-row:last-of-type {
    margin-bottom: 0;
}

.date-dropdown-label {
    font-size: 13px;
    font-weight: 500;
    color: #6B7280;
    min-width: 40px;
}

.date-inline {
    flex: 1;
    min-height: 36px;
    padding: 6px 10px;
    font-size: 14px;
    color: #374151;
    border: 1px solid #E5E7EB;
    border-radius: 8px;
}

.date-inline::-webkit-calendar-picker-indicator {
    cursor: pointer;
    opacity: 0.6;
}

.date-dropdown-close {
    width: 100%;
    margin-top: 10px;
    padding: 8px;
    background: #F3F4F6;
    border: 1px solid #E5E7EB;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    color: #374151;
    cursor: pointer;
}

.date-dropdown-close:hover {
    background: #f1f5f9;
}

.filter-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
}

.btn-reset {
    padding: 8px 16px;
    background: #F3F4F6;
    border: 1px solid #E5E7EB;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    font-family: inherit;
    color: #374151;
    cursor: pointer;
    transition: background 0.2s, border-color 0.2s;
}

.btn-reset:hover {
    background: #E5E7EB;
    border-color: #D1D5DB;
}

.btn-search {
    padding: 8px 22px;
    background: #1e3a5f;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    font-family: inherit;
    color: #fff;
    cursor: pointer;
    transition: background 0.2s ease;
}

.btn-search:hover {
    background: #172554;
}

/* Searchable Created By – v-select */
.created-by-select-wrap {
    padding: 0;
}

:deep(.history-v-select) {
    font-size: 14px;
}

:deep(.history-v-select .vs__dropdown-toggle) {
    min-height: 40px;
    padding: 8px 36px 8px 12px;
    border: 1px solid #E5E7EB;
    border-radius: 8px;
    background: #fff;
}

:deep(.history-v-select .vs__search) {
    margin: 0;
    padding: 0;
    border: none;
}

:deep(.history-v-select .vs__search::placeholder) {
    color: #9CA3AF;
}

:deep(.history-v-select .vs__selected) {
    margin: 0;
    padding: 0;
    color: #374151;
}

:deep(.history-v-select .vs__actions) {
    padding: 0 14px 0 0;
}

:deep(.history-v-select .vs__open-indicator) {
    fill: #64748b;
}

:deep(.history-v-select .vs__dropdown-menu) {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
}

:deep(.history-v-select .vs__dropdown-option--highlight) {
    background: #f1f5f9;
    color: #1e293b;
}

:deep(.history-v-select .vs__clear) {
    fill: #64748b;
}

.vs-open-icon {
    font-size: 16px;
    color: #64748b;
}
</style>
