<template>
    <div class="history-search-form">
        <!-- Event Type (custom searchable dropdown – no v-select) -->
        <div class="filter-row">
            <label class="filter-label">Event Type</label>
            <div class="filter-input-wrap custom-select-wrap" ref="eventTypeWrapRef">
                <button
                    type="button"
                    class="custom-select-trigger filter-select-advanced"
                    :class="{ open: openEventType }"
                    @mousedown.prevent
                    @click="toggleEventType"
                >
                    <span class="custom-select-label">{{ eventTypeLabel }}</span>
                    <iconify-icon icon="lucide:chevrons-up-down" class="custom-select-chevron"></iconify-icon>
                </button>
                <Teleport to="body">
                    <div
                        v-if="openEventType"
                        class="custom-select-dropdown"
                        :style="eventTypeDropdownStyle"
                        @click.stop
                        @mousedown.stop
                    >
                <input
                        ref="eventTypeSearchRef"
                        v-model="eventTypeQuery"
                        type="text"
                        class="custom-select-search"
                        placeholder="Search..."
                        autocomplete="off"
                        @keydown.enter.prevent="pickFirstEventType"
                    />
                        <ul class="custom-select-list">
                            <li
                                v-for="opt in filteredEventTypeOptions"
                                :key="opt.value"
                                class="custom-select-option"
                                :class="{ selected: localAction === opt.value }"
                                @click="localAction = opt.value; openEventType = false"
                            >
                                {{ opt.label }}
                            </li>
                            <li v-if="filteredEventTypeOptions.length === 0" class="custom-select-option muted">No matches</li>
                        </ul>
                    </div>
                </Teleport>
            </div>
        </div>

        <!-- Created By (custom searchable dropdown) -->
        <div class="filter-row">
            <label class="filter-label">Created By</label>
            <div class="filter-input-wrap custom-select-wrap" ref="createdByWrapRef">
                <button
                    type="button"
                    class="custom-select-trigger filter-select-advanced"
                    :class="{ open: openCreatedBy }"
                    @mousedown.prevent
                    @click="toggleCreatedBy"
                >
                    <span class="custom-select-label">{{ createdByLabel }}</span>
                    <iconify-icon icon="lucide:chevrons-up-down" class="custom-select-chevron"></iconify-icon>
                </button>
                <Teleport to="body">
                    <div
                        v-if="openCreatedBy"
                        class="custom-select-dropdown"
                        :style="createdByDropdownStyle"
                        @click.stop
                        @mousedown.stop
                    >
                        <input
                            ref="createdBySearchRef"
                            v-model="createdByQuery"
                            type="text"
                            class="custom-select-search"
                            placeholder="Search..."
                            autocomplete="off"
                            @keydown.enter.prevent="pickFirstCreatedBy"
                        />
                        <ul class="custom-select-list">
                            <li
                                v-for="opt in filteredUserOptions"
                                :key="opt.value"
                                class="custom-select-option"
                                :class="{ selected: localUser === opt.value }"
                                @click="localUser = opt.value; openCreatedBy = false"
                            >
                                {{ opt.label }}
                            </li>
                            <li v-if="filteredUserOptions.length === 0" class="custom-select-option muted">No matches</li>
                        </ul>
                    </div>
                </Teleport>
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
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'

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
    },
    /** When set, replaces the default lead event-type list (e.g. deal history). */
    eventTypeOptions: {
        type: Array,
        default: null
    }
})

const emit = defineEmits(['search', 'close'])

const localSearch = ref(props.initialSearch)
const localAction = ref(props.initialAction)
const localUser = ref(props.initialUser || '')
const defaultLeadEventTypeOptions = [
    { label: 'View', value: 'view' },
    { label: 'Status Changed', value: 'stage_changed' },
    { label: 'Revert', value: 'revert' },
    { label: 'Responsible Person Changed', value: 'assigned' },
    { label: 'Lead Updated', value: 'updated' },
    { label: 'Lead Created', value: 'created' }
]
const resolvedEventTypeOptions = computed(() =>
    Array.isArray(props.eventTypeOptions) && props.eventTypeOptions.length
        ? props.eventTypeOptions
        : defaultLeadEventTypeOptions
)
const userOptions = computed(() =>
    (props.users || []).map((u) => ({ label: u.name || 'Unknown', value: String(u.id) }))
)
const dateFrom = ref(props.initialDateFrom || '')
const dateTo = ref(props.initialDateTo || '')
const showDateDropdown = ref(false)
const dateWrapRef = ref(null)

const openEventType = ref(false)
const openCreatedBy = ref(false)
const eventTypeQuery = ref('')
const createdByQuery = ref('')
const eventTypeWrapRef = ref(null)
const createdByWrapRef = ref(null)
const eventTypeSearchRef = ref(null)
const createdBySearchRef = ref(null)

const eventTypeDropdownStyle = ref({})
const createdByDropdownStyle = ref({})

function updateDropdownPosition(wrapRef, styleRef) {
    if (!wrapRef?.value) return
    const rect = wrapRef.value.getBoundingClientRect()
    styleRef.value = {
        position: 'fixed',
        top: `${rect.bottom + 4}px`,
        left: `${rect.left}px`,
        width: `${rect.width}px`,
        minWidth: `${rect.width}px`,
        zIndex: 10600
    }
}

const eventTypeLabel = computed(() => {
    if (!localAction.value) return 'Not Specified'
    const o = resolvedEventTypeOptions.value.find((opt) => opt.value === localAction.value)
    return o ? o.label : 'Not Specified'
})
const createdByLabel = computed(() => {
    if (!localUser.value) return 'Not Specified'
    const o = userOptions.value.find((opt) => opt.value === localUser.value)
    return o ? o.label : 'Not Specified'
})

const filteredEventTypeOptions = computed(() => {
    const q = (eventTypeQuery.value || '').toLowerCase().trim()
    const list = [{ label: 'Not Specified', value: '' }, ...resolvedEventTypeOptions.value]
    if (!q) return list
    return list.filter((o) => o.label.toLowerCase().includes(q))
})
const filteredUserOptions = computed(() => {
    const q = (createdByQuery.value || '').toLowerCase().trim()
    const list = [{ label: 'Not Specified', value: '' }, ...userOptions.value]
    if (!q) return list
    return list.filter((o) => o.label.toLowerCase().includes(q))
})

function toggleEventType() {
    openCreatedBy.value = false
    openEventType.value = !openEventType.value
}
function toggleCreatedBy() {
    openEventType.value = false
    openCreatedBy.value = !openCreatedBy.value
}

function pickFirstEventType() {
    const first = filteredEventTypeOptions.value[0]
    if (first) {
        localAction.value = first.value
        openEventType.value = false
    }
}
function pickFirstCreatedBy() {
    const first = filteredUserOptions.value[0]
    if (first) {
        localUser.value = first.value
        openCreatedBy.value = false
    }
}

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
    const target = e.target
    const insideAnySelect = target?.closest?.('.custom-select-dropdown')
    if (showDateDropdown.value && dateWrapRef.value && !dateWrapRef.value.contains(target)) {
        showDateDropdown.value = false
    }
    if (openEventType.value && !insideAnySelect && eventTypeWrapRef.value && !eventTypeWrapRef.value.contains(target)) {
        openEventType.value = false
    }
    if (openCreatedBy.value && !insideAnySelect && createdByWrapRef.value && !createdByWrapRef.value.contains(target)) {
        openCreatedBy.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})
onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})

watch(openEventType, (isOpen) => {
    if (isOpen) {
        eventTypeQuery.value = ''
        updateDropdownPosition(eventTypeWrapRef, eventTypeDropdownStyle)
        nextTick(() => {
            requestAnimationFrame(() => {
                eventTypeSearchRef.value?.focus()
            })
        })
    }
})
watch(openCreatedBy, (isOpen) => {
    if (isOpen) {
        createdByQuery.value = ''
        updateDropdownPosition(createdByWrapRef, createdByDropdownStyle)
        nextTick(() => {
            requestAnimationFrame(() => {
                createdBySearchRef.value?.focus()
            })
        })
    }
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

/* Advanced select style – background, subtle border */
.filter-select-advanced {
    background: #F1F5F9 !important;
    border-color: #E2E8F0 !important;
    color: #1e293b;
    font-weight: 500;
}

.filter-select-advanced:hover {
    background: #E2E8F0 !important;
    border-color: #CBD5E1 !important;
}

.filter-select-advanced:focus {
    background: #F8FAFC !important;
    border-color: #94A3B8 !important;
    box-shadow: 0 0 0 2px rgba(148, 163, 184, 0.25);
}

/* Custom searchable dropdown (replaces v-select so it opens reliably) */
.custom-select-wrap {
    position: relative;
}
.custom-select-trigger {
    width: 100%;
    min-height: 40px;
    padding: 8px 36px 8px 12px;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    background: #F1F5F9;
    color: #1e293b;
    font-weight: 500;
    font-size: 14px;
    text-align: left;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-family: inherit;
}
.custom-select-trigger:hover {
    background: #E2E8F0;
    border-color: #CBD5E1;
}
.custom-select-trigger.open {
    border-color: #94A3B8;
    box-shadow: 0 0 0 2px rgba(148, 163, 184, 0.25);
}
.custom-select-label {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.custom-select-chevron {
    flex-shrink: 0;
    color: #64748B;
    font-size: 16px;
}
.custom-select-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    margin-top: 4px;
    padding: 8px;
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    z-index: 1000;
}
.custom-select-search {
    width: 100%;
    padding: 8px 10px;
    margin-bottom: 6px;
    font-size: 14px;
    border: 1px solid #E5E7EB;
    border-radius: 6px;
    font-family: inherit;
    pointer-events: auto;
    position: relative;
    z-index: 1;
}
.custom-select-search:focus {
    outline: none;
    border-color: #94A3B8;
}
.custom-select-list {
    list-style: none;
    margin: 0;
    padding: 0;
    max-height: 200px;
    overflow-y: auto;
}
.custom-select-option {
    padding: 8px 10px;
    font-size: 14px;
    color: #374151;
    cursor: pointer;
    border-radius: 6px;
}
.custom-select-option:hover {
    background: #F1F5F9;
}
.custom-select-option.selected {
    background: #E2E8F0;
    font-weight: 500;
}
.custom-select-option.muted {
    color: #9CA3AF;
    cursor: default;
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

</style>
