<template>
    <!-- Modal mode -->
    <b-modal
        v-if="!asDropdown"
        id="lead-search-modal"
        v-model="show"
        hide-header
        hide-footer
        size="xl"
        centered
        body-class="p-0"
    >
        <div class="lead-search-container d-flex">
            <div class="sidebar-pills p-4 d-flex flex-column gap-3 border-end">
                <button
                    v-for="pill in sidebarPills"
                    :key="pill.id"
                    class="pill-btn"
                    :class="{ 'active': activePill === pill.id }"
                    @click="handleSidebarPillClick(pill)"
                >
                    <span>{{ pill.label }}</span>
                    <span v-if="pill.type === 'city'" class="pill-count">{{ pill.children.length }}</span>
                    <span v-if="pill.type === 'city' && isCitySelected(pill.id)" class="selected-indicator">✓</span>
                </button>
                <transition name="city-child-list">
                    <div
                        v-if="activeCityPill"
                        class="city-children-wrap"
                    >
                        <div class="city-children-title">Branches in {{ activeCityPill.label }}</div>
                        <button
                            v-for="child in activeCityPill.children"
                            :key="`city_child_${child.value}`"
                            type="button"
                            class="city-child-btn"
                            :class="{ active: isBranchSelected(child.value) }"
                            @click="selectCityBranch(activeCityPill, child)"
                        >
                            {{ child.text }}
                        </button>
                        <div v-if="activeCityPill.children.length === 0" class="city-children-empty">
                            No branches available
                        </div>
                    </div>
                </transition>
            </div>
            <div class="form-content-wrapper flex-grow-1 position-relative">
                <button class="close-btn" @click="show = false">
                    <iconify-icon icon="lucide:x"></iconify-icon>
                </button>
                <div class="row g-4">
                    <template v-for="field in visibleSearchFields" :key="field.id">
                        <div class="col-md-6 mt-3">
                            <label class="form-label-custom">{{ field.label }}</label>
                            <button
                                v-if="field.id === 'created_on'"
                                type="button"
                                class="custom-date-trigger"
                                @click="openDatePicker"
                            >
                                <span>{{ createdOnDisplay }}</span>
                                <iconify-icon icon="lucide:calendar-days" />
                            </button>
                            <b-form-input
                                v-else-if="field.type === 'text'"
                                v-model="form[field.formKey]"
                                :placeholder="field.placeholder"
                                class="custom-input"
                            />
                            <v-select
                                v-else-if="field.type === 'select' && field.id !== 'office'"
                                v-model="form[field.formKey]"
                                :options="field.options"
                                :reduce="opt => opt.value"
                                label="text"
                                :placeholder="field.placeholder || 'Select'"
                                :clearable="hasValue(form[field.formKey])"
                                append-to-body
                                class="custom-v-select"
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                            </v-select>
                            <v-select
                                v-if="field.id === 'source' && form.source === 'website'"
                                v-model="form.sourceWebsite"
                                :options="websiteSourceOptions"
                                :reduce="opt => opt.value"
                                label="text"
                                placeholder="Select Website"
                                :clearable="hasValue(form.sourceWebsite)"
                                append-to-body
                                class="custom-v-select mt-2"
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                            </v-select>
                            <!-- Multi-select for office/branch -->
                            <v-select
                                v-else-if="field.type === 'select' && field.id === 'office'"
                                v-model="form.office"
                                :options="field.options"
                                :reduce="opt => opt.value"
                                label="text"
                                :placeholder="field.placeholder || 'Select Branches'"
                                :clearable="form.office && form.office.length > 0"
                                append-to-body
                                multiple
                                class="custom-v-select office-multi-select"
                                @update:model-value="handleOfficeChange"
                                @click.stop
                                @mousedown.stop
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                            </v-select>
                        </div>
                    </template>
                </div>
                <div class="d-flex align-items-center justify-content-between mt-3 pt-4">
                    <div class="d-flex gap-4">
                        <a href="#" class="footer-link text-decoration-underline" @click.prevent="showFilterSettings = true">Add Field</a>
                        <a href="#" class="footer-link text-secondary" @click.prevent="restoreDefaultFields">Restore default fields</a>
                    </div>
                    <div class="d-flex gap-3">
                        <button class="btn-reset" @click="resetForm">Reset</button>
                        <button class="btn-search" @click="applySearch">Search</button>
                    </div>
                </div>
            </div>
        </div>
    </b-modal>

    <!-- Dropdown mode: panel under search input -->
    <div v-else class="lead-search-dropdown-panel">
        <div class="lead-search-container d-flex">
            <div class="sidebar-pills p-4 d-flex flex-column gap-3 border-end">
                <button
                    v-for="pill in sidebarPills"
                    :key="pill.id"
                    class="pill-btn"
                    :class="{ 'active': activePill === pill.id }"
                    @click="handleSidebarPillClick(pill)"
                >
                    <span>{{ pill.label }}</span>
                    <span v-if="pill.type === 'city'" class="pill-count">{{ pill.children.length }}</span>
                    <span v-if="pill.type === 'city' && isCitySelected(pill.id)" class="selected-indicator">✓</span>
                </button>
                <transition name="city-child-list">
                    <div
                        v-if="activeCityPill"
                        class="city-children-wrap"
                    >
                        <div class="city-children-title">Branches in {{ activeCityPill.label }}</div>
                        <button
                            v-for="child in activeCityPill.children"
                            :key="`city_child_dropdown_${child.value}`"
                            type="button"
                            class="city-child-btn"
                            :class="{ active: isBranchSelected(child.value) }"
                            @click="selectCityBranch(activeCityPill, child)"
                        >
                            {{ child.text }}
                        </button>
                        <div v-if="activeCityPill.children.length === 0" class="city-children-empty">
                            No branches available
                        </div>
                    </div>
                </transition>
            </div>
            <div class="form-content-wrapper flex-grow-1 position-relative">
                <button class="close-btn" @click="emit('update:modelValue', false)">
                    <iconify-icon icon="lucide:x"></iconify-icon>
                </button>
                <div class="row g-4">
                    <template v-for="field in visibleSearchFields" :key="field.id">
                        <div class="col-md-6 mt-3">
                            <label class="form-label-custom">{{ field.label }}</label>
                            <button
                                v-if="field.id === 'created_on'"
                                type="button"
                                class="custom-date-trigger"
                                @click="openDatePicker"
                            >
                                <span>{{ createdOnDisplay }}</span>
                                <iconify-icon icon="lucide:calendar-days" />
                            </button>
                            <b-form-input
                                v-else-if="field.type === 'text'"
                                v-model="form[field.formKey]"
                                :placeholder="field.placeholder"
                                class="custom-input"
                            />
                            <v-select
                                v-else-if="field.type === 'select' && field.id !== 'office'"
                                v-model="form[field.formKey]"
                                :options="field.options"
                                :reduce="opt => opt.value"
                                label="text"
                                :placeholder="field.placeholder || 'Select'"
                                :clearable="hasValue(form[field.formKey])"
                                append-to-body
                                class="custom-v-select"
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                            </v-select>
                            <v-select
                                v-if="field.id === 'source' && form.source === 'website'"
                                v-model="form.sourceWebsite"
                                :options="websiteSourceOptions"
                                :reduce="opt => opt.value"
                                label="text"
                                placeholder="Select Website"
                                :clearable="hasValue(form.sourceWebsite)"
                                append-to-body
                                class="custom-v-select mt-2"
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                            </v-select>
                            <!-- Multi-select for office/branch -->
                            <v-select
                                v-else-if="field.type === 'select' && field.id === 'office'"
                                v-model="form.office"
                                :options="field.options"
                                :reduce="opt => opt.value"
                                label="text"
                                :placeholder="field.placeholder || 'Select Branches'"
                                :clearable="form.office && form.office.length > 0"
                                append-to-body
                                multiple
                                class="custom-v-select office-multi-select"
                                @update:model-value="handleOfficeChange"
                                @click.stop
                                @mousedown.stop
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                            </v-select>
                        </div>
                    </template>
                </div>
                <div class="d-flex align-items-center justify-content-between mt-3 pt-4">
                    <div class="d-flex gap-4">
                        <a href="#" class="footer-link text-decoration-underline" @click.prevent="showFilterSettings = true">Add Field</a>
                        <a href="#" class="footer-link text-secondary" @click.prevent="restoreDefaultFields">Restore default fields</a>
                    </div>
                    <div class="d-flex gap-3">
                        <button class="btn-reset" @click="resetForm">Reset</button>
                        <button class="btn-search" @click="applySearch">Search</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <FilterFieldSettingsModal
        v-model="showFilterSettings"
        :initial-selected-lead-ids="selectedLeadFieldIds"
        @apply="onFilterApply"
    />

    <div v-if="showDateModal" class="lr-modal-backdrop" @click.stop>
        <div class="lr-date-modal">
            <div class="lr-date-left">
                <button
                    v-for="preset in datePresets"
                    :key="preset.value"
                    type="button"
                    class="lr-date-preset"
                    :class="{ active: selectedPreset === preset.value }"
                    @click="selectPresetRange(preset.value)"
                >
                    {{ preset.label }}
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
import { ref, watch, onMounted, computed, nextTick } from 'vue'
import { BModal, BFormInput } from 'bootstrap-vue-3'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import FilterFieldSettingsModal from './FilterFieldSettingsModal.vue'
import api from '@/plugins/axios'

const props = defineProps({
    modelValue: Boolean,
    asDropdown: { type: Boolean, default: false },
    initialActivePill: { type: String, default: undefined },
    hasActiveFilters: { type: Boolean, default: true },
    currentQuery: { type: Object, default: null },
    showTeamFilter: { type: Boolean, default: false }
})

const emit = defineEmits(['update:modelValue', 'search'])

const show = ref(props.modelValue)
const showFilterSettings = ref(false)
const showDateModal = ref(false)
const selectedLeadFieldIds = ref(['first_name', 'lead_name', 'created_on', 'work_phone', 'responsible_person', 'lead_branch_source','office', 'stage', 'email', 'bedrooms','source','team'])
const activePill = ref(props.initialActivePill || 'leads-in-progress')
const teamOptions = ref([{ value: null, text: 'Select Team' }])
const officeOptions = ref([{ value: null, text: 'Select Office' }])
const allResponsiblePersons = ref([])
const allTeams = ref([])
const selectedOffice = ref(null)
const selectedPillType = ref(null)

const normalizeOfficeId = (value) => {
    if (value === null || value === undefined || value === '') return null
    const numeric = Number(value)
    return Number.isNaN(numeric) ? String(value) : numeric
}

const normalizeOfficeSelection = (value) => {
    const values = Array.isArray(value) ? value : [value]
    const normalized = values
        .flatMap((item) => {
            if (typeof item === 'string' && item.includes(',')) {
                return item.split(',').map(part => part.trim()).filter(Boolean)
            }
            return [item]
        })
        .map(normalizeOfficeId)
        .filter(item => item !== null)

    return [...new Set(normalized)]
}

// Helper functions for branch selection
const isBranchSelected = (branchValue) => {
    if (!form.value.office) return false
    const normalizedBranch = normalizeOfficeId(branchValue)
    const selected = normalizeOfficeSelection(form.value.office)
    if (Array.isArray(form.value.office)) {
        return selected.includes(normalizedBranch)
    }
    return normalizeOfficeId(form.value.office) === normalizedBranch
}

const isCitySelected = (cityId) => {
    if (!form.value.office || !Array.isArray(form.value.office)) return false
    const cityPill = sidebarPills.value.find(p => p.id === cityId)
    if (!cityPill) return false
    const selected = normalizeOfficeSelection(form.value.office)
    return cityPill.children.some(child => selected.includes(normalizeOfficeId(child.value)))
}

// Handle office change to update filters
const handleOfficeChange = async (newOffice) => {
    console.log('Office changed to:', newOffice)
    const normalizedOffices = normalizeOfficeSelection(newOffice)
    form.value.office = normalizedOffices
    
    // Update selectedOffice for filtering
    if (normalizedOffices.length) {
        selectedOffice.value = [...normalizedOffices]
    } else {
        selectedOffice.value = null
    }
    
    // Clear responsible and team when office changes
    if (form.value.responsible) {
        form.value.responsible = ''
    }
    if (form.value.team) {
        form.value.team = ''
    }
    
    // Fetch filtered data based on selected offices
    await Promise.all([
        fetchResponsiblePersonsWithFilter(),
        fetchTeamsWithFilter()
    ])
}

watch(() => props.modelValue, (val) => {
    show.value = val
})

watch(() => props.modelValue, (val) => {
    if (val) {
        nextTick(() => {
            if (!props.hasActiveFilters) resetFormValues()
            else syncFormFromQuery(props.currentQuery)
        })
    }
}, { immediate: true })

const queryToFormKeys = {
    lead_name: 'leadName',
    first_name: 'firstName',
    responsible_person_id: 'responsible',
    created_at: 'createdOn',
    created_from: 'createdFrom',    
    created_to: 'createdTo',
    lead_branch_source: 'branchSource',
    closed: 'Converted',
    work_phone: 'workPhone',
    stage_id: 'stage',
    email: 'email',
    bedrooms: 'bedrooms',
    search: 'search',
    source: 'source',
    source_website: 'sourceWebsite',
    team_id: 'team',
    office_branch: 'office',
}

function syncFormFromQuery(query) {
    if (!query || typeof query !== 'object' || Object.keys(query).length === 0) {
        resetFormValues()
        return
    }
    const next = {
        search: '',
        id: '',
        firstName: '',
        responsible: '',
        createdOn: '',
        createdFrom: '',   
        createdTo: '', 
        stageChangedBy: '',
        branchSource: '',
        closed: '',
        workPhone: '',
        stage: '',
        email: '',
        bedrooms: '',
        leadName: '',
        sourceWebsite: '',
        team: '',
        office: []
    }
    Object.keys(queryToFormKeys).forEach(qKey => {
        const formKey = queryToFormKeys[qKey]
        if (query[qKey] !== undefined && query[qKey] !== '') {
            if (formKey === 'office' && query[qKey]) {
                next[formKey] = normalizeOfficeSelection(query[qKey])
            } else {
                next[formKey] = query[qKey]
            }
        }
    })
    if (next.source === 'allproperties' || next.source === 'oiaproperties') {
        next.sourceWebsite = next.source
        next.source = 'website'
    }
    form.value = next
}

watch(() => props.initialActivePill, (newVal) => {
    console.log('initialActivePill changed:', newVal)
    if (newVal && show.value) {
        activePill.value = newVal
    }
}, { immediate: true })

watch(show, (val) => {
    emit('update:modelValue', val)
    if (val) {
        console.log('Modal opening with initialActivePill:', props.initialActivePill) 
        if (props.initialActivePill) {
            activePill.value = props.initialActivePill
            console.log('Setting activePill to:', props.initialActivePill)
        }
        if (!props.hasActiveFilters) resetFormValues()
        else syncFormFromQuery(props.currentQuery)
    }
})

watch(() => props.hasActiveFilters, (val) => {
    if (!val && show.value) resetFormValues()
})

watch(() => props.currentQuery, (query) => {
    if (show.value && props.hasActiveFilters) syncFormFromQuery(query)
}, { deep: true })

const normalizeCityText = (value) => String(value || '').toLowerCase().replace(/\s+/g, ' ').trim()

const detectCityKeyFromOffice = (office) => {
    const probes = [
        office?.city,
        office?.city_name,
        office?.branch_source,
        office?.branchSource,
        office?.parent_name,
        office?.parent?.name,
        office?.region,
        office?.office_city,
        office?.text,
        office?.name,
    ]
        .map(normalizeCityText)
        .filter(Boolean)

    const all = probes.join(' | ')
    if (all.includes('dubai')) return 'dubai'
    if (all.includes('abu dhabi') || all.includes('abudhabi') || all.includes('abu-dhabi')) return 'abu-dhabi'
    return ''
}

const cityBranchGroups = computed(() => {
    const options = (officeOptions.value || []).filter(o => o && o.value != null)
    const groups = {
        dubai: { id: 'dubai', label: 'Dubai', children: [] },
        'abu-dhabi': { id: 'abu-dhabi', label: 'Abu Dhabi', children: [] },
    }

    options.forEach((office) => {
        const cityKey = office.cityKey || detectCityKeyFromOffice(office.raw || office)
        if (cityKey === 'dubai') groups.dubai.children.push(office)
        else if (cityKey === 'abu-dhabi') groups['abu-dhabi'].children.push(office)
    })

    return Object.values(groups)
})

const sidebarPills = computed(() => {
    const base = [
        { id: 'closed-leads', label: 'Converted Leads', type: 'default' },
        { id: 'leads-in-progress', label: 'Leads In Progress', type: 'default' },
        { id: 'my-leads', label: 'My Leads', type: 'default' },
    ]

    const cityPills = cityBranchGroups.value.map(group => ({
        id: group.id,
        label: group.label,
        type: 'city',
        children: group.children,
    }))

    return [...base, ...cityPills]
})

const activeCityPill = computed(() => {
    return sidebarPills.value.find(p => p.id === activePill.value && p.type === 'city') || null
})

const form = ref({
    search: '',
    id: '',
    firstName: '',
    responsible: '',
    createdOn: '',
    stageChangedBy: '',
    branchSource: '',
    closed: '',
    workPhone: '',
    stage: '',
    email: '',
    bedrooms: '',
    leadName: '',
    source: '',
    createdFrom: '',    
    createdTo: '',  
    team: '',
    office: []
})

const responsiblePersons = ref([])
const branchSourceOptions = ref([{ value: null, text: 'Select Branch Source' }])
const stageOptions = ref([{ value: null, text: 'Select Stage' }])

const personOptions = computed(() => {
    const opts = [{ value: null, text: 'Select Person' }]
    
    let filteredPersons = [...allResponsiblePersons.value]
    
    filteredPersons.forEach(p => {
        opts.push({ 
            value: p.id, 
            text: p.name || `User ${p.id}`,
        })
    })
    
    return opts
})

const computedTeamOptions = computed(() => {
    const opts = [{ value: null, text: 'Select Team' }]
    
    let filteredTeams = [...allTeams.value]
    
    filteredTeams.forEach(team => {
        opts.push({ value: team.id, text: team.name })
    })
    
    return opts
})

const dateOptions = [
    { value: null, text: 'Any Date' }
]

const yesNoOptions = [
    { value: null, text: 'Any' },
    { value: 1, text: 'Yes' },
    { value: 0, text: 'No' }
]

const bedroomsOptions = [
    { value: null, text: 'Any' },
    { value: 1, text: '1' },
    { value: 2, text: '2' },
    { value: 3, text: '3' },
    { value: 4, text: '4+' }
]

const createdOnOptions = [
    { value: null, text: 'Any Date' },
    { value: 'today', text: 'Today' },
    { value: 'yesterday', text: 'Yesterday' },
    { value: 'this_week', text: 'This Week' },
    { value: 'this_month', text: 'This Month' },
    { value: 'current_quarter', text: 'Current Quarter' },
    { value: 'last_7_days', text: 'Last 7 Days' },
    { value: 'last_30_days', text: 'Last 30 Days' },
    { value: 'last_60_days', text: 'Last 60 Days' },
    { value: 'last_90_days', text: 'Last 90 Days' },
    { value: 'last_week', text: 'Last Week' },
    { value: 'last_month', text: 'Last Month' },
    { value: 'custom_date', text: 'Custom Date' }
]

const sourceOptions = ref([
    { value: null, text: 'Select Source' },
    { value: 'Lead Form', text: 'Meta' },
    { value: 'website', text: 'Website' }
])
const websiteSourceOptions = ref([
    { value: null, text: 'Select Website' },
    { value: 'Allproperties.ae', text: 'Allproperties.ae' },
    { value: 'Oiaproperties.com', text: 'Oiaproperties.com' }
])
const weekDays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']
const selectedPreset = ref('')
const startDate = ref(null)
const endDate = ref(null)
const calendarMonth = ref(new Date())
const datePresets = [
    { value: 'today', label: 'Today' },
    { value: 'yesterday', label: 'Yesterday' },
    { value: 'this_week', label: 'This Week' },
    { value: 'last_week', label: 'Last Week' },
    { value: 'this_month', label: 'This Month' },
    { value: 'last_month', label: 'Last Month' },
    { value: 'last_year', label: 'Last Year' },
    { value: 'custom_date', label: 'Custom Date' },
]

const getUserFromStorage = () => {
    try {
        const userData = localStorage.getItem('user')
        return userData ? JSON.parse(userData) : null
    } catch (error) {
        console.error('Error getting user from storage:', error)
        return null
    }
}

const user = ref(getUserFromStorage())

const updateUserFromStorage = () => {
    try {
        const userData = localStorage.getItem('user')
        user.value = userData ? JSON.parse(userData) : null
    } catch (error) {
        console.error('Error getting user from storage:', error)
        user.value = null
    }
}

const appliedSearchParams = ref(null)

const isAdminOrSuperAdmin = computed(() => {
    if (!user.value) return false
    
    const isAdminUser = user.value.roles?.includes('super_admin') || 
                       user.value.roles?.includes('admin') || user.value.roles?.includes('manager')
    
    return isAdminUser
})

const searchFieldsConfig = computed(() => {
    const fields = [
        { id: 'first_name', label: 'First Name', formKey: 'firstName', queryKey: 'first_name', type: 'text', placeholder: 'Enter First Name' },
        { id: 'lead_name', label: 'Lead Name', formKey: 'leadName', queryKey: 'lead_name', type: 'text', placeholder: 'Enter Lead Name' },
        { id: 'closed', label: 'Converted', formKey: 'closed', queryKey: 'closed', type: 'select', options: yesNoOptions },
        { id: 'created_on', label: 'Created On', formKey: 'createdOn', queryKey: 'created_at', type: 'select', options: createdOnOptions },
        { id: 'work_phone', label: 'Phone', formKey: 'workPhone', queryKey: 'work_phone', type: 'text', placeholder: 'Enter Work Phone' },
        { id: 'responsible_person', label: 'Responsible Person', formKey: 'responsible', queryKey: 'responsible_person_id', type: 'select', options: [] },
        { id: 'email', label: 'Email', formKey: 'email', queryKey: 'email', type: 'text', placeholder: 'Enter Email' },
    ]
    
    if (isAdminOrSuperAdmin.value) {
        fields.push({ id: 'team', label: 'Team', formKey: 'team', queryKey: 'team_id', type: 'select', options: [] })
    }
    
    fields.push(
        { id: 'stage', label: 'Stage', formKey: 'stage', queryKey: 'stage_id', type: 'select', options: [] },
        { id: 'bedrooms', label: 'Bedrooms', formKey: 'bedrooms', queryKey: 'bedrooms', type: 'select', options: bedroomsOptions },
        { id: 'lead_branch_source', label: 'Lead Branch Source', formKey: 'branchSource', queryKey: 'lead_branch_source', type: 'select', options: [] },
        { id: 'office', label: 'Branch', formKey: 'office', queryKey: 'office_branch', type: 'select', options: officeOptions.value, multiple: true },
        { id: 'source', label: 'Source', formKey: 'source', queryKey: 'source', type: 'select', options: [] }
    )
    
    return fields
})

const monthLabel = computed(() => calendarMonth.value.toLocaleString('en-US', { month: 'long', year: 'numeric' }))
const createdOnDisplay = computed(() => {
    if (form.value.createdOn === 'custom_date' && form.value.createdFrom && form.value.createdTo) {
        return `${form.value.createdFrom} to ${form.value.createdTo}`
    }
    const preset = createdOnOptions.find(opt => opt.value === form.value.createdOn)
    return preset?.text || 'Select Date'
})

const visibleSearchFields = computed(() => {
    return searchFieldsConfig.value
        .filter(f => selectedLeadFieldIds.value.includes(f.id))
        .map(f => ({
            ...f,
            options:
                f.formKey === 'responsible' ? personOptions.value :
                f.formKey === 'branchSource' ? branchSourceOptions.value :
                f.formKey === 'stage' ? stageOptions.value :
                f.formKey === 'source' ? sourceOptions.value :
                f.formKey === 'createdOn' ? createdOnOptions :
                f.formKey === 'team' ? computedTeamOptions.value :
                (f.options || []),
            placeholder: f.placeholder || (f.type === 'select' ? 'Select' : '')
        }))
})

const defaultLeadFieldIds = computed(() =>
    searchFieldsConfig.value.map(f => f.id)
)

function restoreDefaultFields() {
    selectedLeadFieldIds.value = [...defaultLeadFieldIds.value]
}

function onFilterApply(payload) {
    if (payload && Array.isArray(payload.leads)) {
        selectedLeadFieldIds.value = payload.leads.length ? payload.leads : [...defaultLeadFieldIds]
    }
}

function hasValue(val) {
    if (Array.isArray(val)) return val.length > 0
    return val !== null && val !== undefined && val !== ''
}

function getDisplayValue(field, rawValue) {
    if (rawValue === null || rawValue === undefined || rawValue === '') return null
    if (Array.isArray(rawValue)) {
        if (field.type === 'select') {
            const opts = field.options || []
            const selectedTexts = rawValue.map(val => {
                const opt = opts.find(o => o.value === val)
                return opt ? opt.text : String(val)
            })
            return selectedTexts.join(', ')
        }
        return rawValue.join(', ')
    }
    if (field.type === 'select') {
        const opts = field.formKey === 'responsible' ? personOptions.value : (field.formKey === 'branchSource' ? branchSourceOptions.value : (field.formKey === 'stage' ? stageOptions.value : (field.options || [])))
        const opt = opts.find(o => o.value === rawValue)
        return opt ? opt.text : String(rawValue)
    }
    return String(rawValue)
}

function applySearch() {
    let createdFrom = undefined
    let createdTo = undefined
    let createdAt = undefined
    let branchSource = form.value.branchSource || undefined
    let queryOfficeBranch = undefined
    let responsiblePersonId = form.value.responsible ?? undefined
    let closed = form.value.closed ?? undefined
    let teamId = form.value.team ?? undefined
    
    let officeBranches = form.value.office && form.value.office.length ? form.value.office : undefined
    
    switch (activePill.value) {
        case 'dubai':
            const dubaiOption = branchSourceOptions.value.find(opt =>
                opt.text?.toLowerCase().includes('dubai')
            )
            branchSource = dubaiOption?.value
            break
            
        case 'abu-dhabi':
            const abuDhabiOption = branchSourceOptions.value.find(opt =>
                opt.text?.toLowerCase().includes('abu dhabi')
            )
            branchSource = abuDhabiOption?.value
            break
            
        case 'my-leads':
            const user = JSON.parse(localStorage.getItem('user') || '{}')
            responsiblePersonId = user.id
            break
            
        case 'closed-leads':
            closed = 1 
            break
    }
    
    if (form.value.createdOn) {
        const today = new Date()
        today.setHours(0, 0, 0, 0)
        
        switch (form.value.createdOn) {
            case 'today':
                createdAt = today.toISOString().split('T')[0]
                break
                
            case 'yesterday':
                const yesterday = new Date(today)
                yesterday.setDate(yesterday.getDate() - 1)
                createdAt = yesterday.toISOString().split('T')[0]
                break
                
            case 'tomorrow':
                const tomorrow = new Date(today)
                tomorrow.setDate(tomorrow.getDate() + 1)
                createdAt = tomorrow.toISOString().split('T')[0]
                break
                
            case 'this_week':
                const startOfWeek = new Date(today)
                startOfWeek.setDate(today.getDate() - today.getDay())
                const endOfWeek = new Date(startOfWeek)
                endOfWeek.setDate(startOfWeek.getDate() + 6)
                createdFrom = startOfWeek.toISOString().split('T')[0]
                createdTo = endOfWeek.toISOString().split('T')[0]
                break
                
            case 'this_month':
                createdFrom = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0]
                createdTo = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0]
                break
                
            case 'current_quarter':
                const quarter = Math.floor(today.getMonth() / 3)
                createdFrom = new Date(today.getFullYear(), quarter * 3, 1).toISOString().split('T')[0]
                createdTo = new Date(today.getFullYear(), (quarter + 1) * 3, 0).toISOString().split('T')[0]
                break
                
            case 'last_7_days':
                createdTo = today.toISOString().split('T')[0]
                const last7Days = new Date(today)
                last7Days.setDate(last7Days.getDate() - 7)
                createdFrom = last7Days.toISOString().split('T')[0]
                break
                
            case 'last_30_days':
                createdTo = today.toISOString().split('T')[0]
                const last30Days = new Date(today)
                last30Days.setDate(last30Days.getDate() - 30)
                createdFrom = last30Days.toISOString().split('T')[0]
                break
                
            case 'last_60_days':
                createdTo = today.toISOString().split('T')[0]
                const last60Days = new Date(today)
                last60Days.setDate(last60Days.getDate() - 60)
                createdFrom = last60Days.toISOString().split('T')[0]
                break
                
            case 'last_90_days':
                createdTo = today.toISOString().split('T')[0]
                const last90Days = new Date(today)
                last90Days.setDate(last90Days.getDate() - 90)
                createdFrom = last90Days.toISOString().split('T')[0]
                break
                
            case 'last_week':
                const lastWeek = new Date(today)
                lastWeek.setDate(lastWeek.getDate() - 7)
                const startOfLastWeek = new Date(lastWeek)
                startOfLastWeek.setDate(lastWeek.getDate() - lastWeek.getDay())
                const endOfLastWeek = new Date(startOfLastWeek)
                endOfLastWeek.setDate(startOfLastWeek.getDate() + 6)
                createdFrom = startOfLastWeek.toISOString().split('T')[0]
                createdTo = endOfLastWeek.toISOString().split('T')[0]
                break
                
            case 'last_month':
                createdFrom = new Date(today.getFullYear(), today.getMonth() - 1, 1).toISOString().split('T')[0]
                createdTo = new Date(today.getFullYear(), today.getMonth(), 0).toISOString().split('T')[0]
                break
                
            case 'next_week':
                const nextWeek = new Date(today)
                nextWeek.setDate(nextWeek.getDate() + 7)
                const startOfNextWeek = new Date(nextWeek)
                startOfNextWeek.setDate(nextWeek.getDate() - nextWeek.getDay())
                const endOfNextWeek = new Date(startOfNextWeek)
                endOfNextWeek.setDate(startOfNextWeek.getDate() + 6)
                createdFrom = startOfNextWeek.toISOString().split('T')[0]
                createdTo = endOfNextWeek.toISOString().split('T')[0]
                break
                
            case 'next_month':
                createdFrom = new Date(today.getFullYear(), today.getMonth() + 1, 1).toISOString().split('T')[0]
                createdTo = new Date(today.getFullYear(), today.getMonth() + 2, 0).toISOString().split('T')[0]
                break
                
            case 'exact_date':
                if (form.value.exactDate) {
                    createdAt = form.value.exactDate
                }
                break
            case 'custom_date':
                if (form.value.createdFrom) {
                    createdFrom = form.value.createdFrom
                }
                if (form.value.createdTo) {
                    createdTo = form.value.createdTo
                }
                createdAt = undefined
                break
        }
    }

    const query = {
        lead_name: form.value.leadName || undefined,
        first_name: form.value.firstName || undefined,
        responsible_person_id: responsiblePersonId,
        lead_branch_source: branchSource,
        closed: closed,
        work_phone: form.value.workPhone || undefined,
        stage_id: form.value.stage ?? undefined,
        email: form.value.email || undefined,
        bedrooms: form.value.bedrooms ?? undefined,
        search: form.value.search || undefined,
        source: form.value.source === 'website'
            ? (form.value.sourceWebsite || 'website')
            : (form.value.source || undefined),
        source_website: form.value.source === 'website' ? (form.value.sourceWebsite || undefined) : undefined,
        created_from: createdFrom || undefined,  
        created_to: createdTo || undefined,     
        created_at: createdAt || undefined,   
        team_id: teamId || undefined,
        office_branch: officeBranches || undefined
    }
    
    Object.keys(query).forEach(k => { 
        if (query[k] === '' || query[k] === undefined || (Array.isArray(query[k]) && query[k].length === 0)) delete query[k] 
    })
    
    console.log('Search Query:', query)

    const activeFilters = []
    const visibleFields = searchFieldsConfig.value.filter(f => selectedLeadFieldIds.value.includes(f.id))
    
    visibleFields.forEach(field => {
        const raw = form.value[field.formKey]
        if (!hasValue(raw)) return
        
        const displayValue = getDisplayValue(
            { 
                ...field, 
                options: 
                    field.formKey === 'responsible' ? personOptions.value : 
                    field.formKey === 'branchSource' ? branchSourceOptions.value : 
                    field.formKey === 'stage' ? stageOptions.value : 
                    field.formKey === 'source' ? sourceOptions.value :
                    field.formKey === 'team' ? teamOptions.value : 
                    (field.options || [])
            },
            raw
        )
        
        if (displayValue) {
            activeFilters.push({
                id: field.id,
                queryKey: field.queryKey,
                label: field.label,
                value: displayValue
            })
        }
    })
    
    const pill = sidebarPills.value.find(p => p.id === activePill.value)
    const pillData = pill ? { id: pill.id, label: pill.label } : null
    
    emit('search', { query, activePill: pillData, activeFilters })
    show.value = false
}


async function handleSidebarPillClick(pill) {
    console.log('Sidebar pill clicked:', pill)
    activePill.value = pill.id
    
    if (pill.type === 'city') {
        // City pill now only opens its branch list; selection happens per branch button.
        // Keep current selected branches unchanged so users can mix multiple branches easily.
        const normalizedOffices = normalizeOfficeSelection(form.value.office)
        form.value.office = normalizedOffices
        selectedOffice.value = normalizedOffices.length ? [...normalizedOffices] : null
        selectedPillType.value = pill.id

        // Refresh dependent dropdowns based on current office selection
        await Promise.all([
            fetchResponsiblePersonsWithFilter(),
            fetchTeamsWithFilter()
        ])
    } else {
        // For non-city pills, clear office selection
        form.value.office = []
        selectedOffice.value = null
        selectedPillType.value = null
        
        // Clear responsible and team
        form.value.responsible = ''
        form.value.team = ''
        
        // Fetch filtered data
        await Promise.all([
            fetchResponsiblePersonsWithFilter(),
            fetchTeamsWithFilter()
        ])
    }
}

async function selectCityBranch(cityPill, child) {
    console.log('City branch selected:', cityPill, child)
    activePill.value = cityPill.id
    
    // Toggle selection for multi-select
    const branchId = normalizeOfficeId(child.value)
    const offices = normalizeOfficeSelection(form.value.office)
    
    const index = offices.indexOf(branchId)
    if (index === -1) {
        offices.push(branchId)
    } else {
        offices.splice(index, 1)
    }
    form.value.office = offices
    
    // Update selectedOffice and trigger filtering
    if (offices.length) {
        selectedOffice.value = [...offices]
    } else {
        selectedOffice.value = null
    }
    selectedPillType.value = cityPill.id
    
    // Clear responsible and team
    form.value.responsible = ''
    form.value.team = ''
    
    // Fetch filtered data
    await Promise.all([
        fetchResponsiblePersonsWithFilter(),
        fetchTeamsWithFilter()
    ])
}

async function fetchResponsiblePersonsWithFilter() {
    try {
        const params = {}
        
        // Add office filter if selected (now supports multiple offices)
        if (selectedOffice.value && Array.isArray(selectedOffice.value) && selectedOffice.value.length) {
            params.office_ids = selectedOffice.value.join(',')
        } else if (selectedOffice.value && !Array.isArray(selectedOffice.value)) {
            params.office_id = selectedOffice.value
        }
        
        // Add pill filter if needed
        if (selectedPillType.value) {
            params.pill_type = selectedPillType.value
        }
        
        const res = await api.get('/available-responsible-persons', { params })
        if (res.data.data) {
            allResponsiblePersons.value = res.data.data.map(person => ({
                ...person,
            }))
        } else {
            allResponsiblePersons.value = []
        }
    } catch (error) {
        console.error('Error fetching responsible persons with filter:', error)
        allResponsiblePersons.value = []
    }
}

async function fetchBranchSources() {
    try {
        const res = await api.get('/get/lead/branch_source')
        const data = res.data?.data
        if (Array.isArray(data) && data.length) {
            branchSourceOptions.value = [
                { value: null, text: 'Select Branch Source' },
                ...data.map(b => ({ value: b.name, text: b.name }))
            ]
        }
    } catch (_) {}
}

async function fetchStages() {
    try {
        const res = await api.get('/stages')
        const raw = res.data?.data
        const data = Array.isArray(raw?.data) ? raw.data : (Array.isArray(raw) ? raw : [])
        if (data.length) {
            stageOptions.value = [
                { value: null, text: 'Select Stage' },
                ...data.map(s => ({ value: s.id, text: s.name }))
            ]
        }
    } catch (_) {}
}

async function fetchSources() {
    // Source options are fixed by requirement:
    // Meta or Website, with a second selector for website source.
}

async function fetchTeams() {
    try {
        const res = await api.get('/teams-with-leads')
        const data = res.data?.data
        if (Array.isArray(data) && data.length) {
            allTeams.value = data.map(team => ({
                id: team.id,
                name: team.name,
                office_id: team.office_id || null,
                city: team.city || null
            }))
        }
    } catch (error) {
        console.error('Error fetching teams:', error)
    }
}

async function fetchTeamsWithFilter() {
    try {
        const params = {}
        
        // Add office filter if selected (now supports multiple offices)
        if (selectedOffice.value && Array.isArray(selectedOffice.value) && selectedOffice.value.length) {
            params.office_ids = selectedOffice.value.join(',')
        } else if (selectedOffice.value && !Array.isArray(selectedOffice.value)) {
            params.office_id = selectedOffice.value
        }
        
        // Add pill filter if needed
        if (selectedPillType.value) {
            params.pill_type = selectedPillType.value
        }
        
        const res = await api.get('/teams-with-leads', { params })
        const data = res.data?.data
        if (Array.isArray(data) && data.length) {
            allTeams.value = data.map(team => ({
                id: team.id,
                name: team.name,
                office_id: team.office_id || null,
                city: team.city || null,
                admin_parent_id: team.admin_parent_id || null
            }))
        } else {
            allTeams.value = []
        }
    } catch (error) {
        console.error('Error fetching teams with filter:', error)
        allTeams.value = []
    }
}

async function fetchOffices() {
    try {
        const res = await api.get('/get-offices')
        const data = res.data?.data
        if (Array.isArray(data) && data.length) {
            officeOptions.value = [
                { value: null, text: 'Select Office' },
                ...data.map(office => ({
                    value: office.id,
                    text: office.name,
                    cityKey: detectCityKeyFromOffice(office),
                    raw: office,
                }))
            ]
        }
    } catch (error) {
        console.error('Error fetching offices:', error)
        try {
            const res2 = await api.get('/users', {
                params: {
                    role: 'admin',
                    has_parent: true
                }
            })
            const admins = res2.data?.data
            if (Array.isArray(admins) && admins.length) {
                officeOptions.value = [
                    { value: null, text: 'Select Office' },
                    ...admins.map(admin => ({
                        value: admin.id,
                        text: admin.name,
                        cityKey: detectCityKeyFromOffice(admin),
                        raw: admin,
                    }))
                ]
            }
        } catch (err) {
            console.error('Error fetching admin users:', err)
        }
    }
}

function resetFormValues() {
    form.value = {
        search: '',
        id: '',
        firstName: '',
        responsible: '',
        createdOn: '',
        createdFrom: '',     
        createdTo: '',  
        created_from: '',     
        created_to: '',  
        stageChangedBy: '',
        branchSource: '',
        closed: '',
        workPhone: '',
        stage: '',
        email: '',
        bedrooms: '',
        source: '',
        sourceWebsite: '',
        team: '',
        office: []
    }
    selectedOffice.value = null
    selectedPillType.value = null
}

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

function openDatePicker() {
    if (form.value.createdOn && form.value.createdOn !== 'custom_date') {
        selectedPreset.value = form.value.createdOn
        selectPresetRange(form.value.createdOn)
    } else if (form.value.createdOn === 'custom_date' && form.value.createdFrom && form.value.createdTo) {
        selectedPreset.value = 'custom_date'
        startDate.value = startOfDay(new Date(form.value.createdFrom))
        endDate.value = startOfDay(new Date(form.value.createdTo))
        calendarMonth.value = new Date(startDate.value.getFullYear(), startDate.value.getMonth(), 1)
    }
    showDateModal.value = true
}

function selectPresetRange(preset) {
    selectedPreset.value = preset
    const today = new Date()
    const y = today.getFullYear()
    const m = today.getMonth()

    if (preset === 'custom_date') return
    if (preset === 'today') {
        startDate.value = startOfDay(today)
        endDate.value = startOfDay(today)
    } else if (preset === 'yesterday') {
        const d = new Date(y, m, today.getDate() - 1)
        startDate.value = startOfDay(d)
        endDate.value = startOfDay(d)
    } else if (preset === 'this_week') {
        const s = new Date(y, m, today.getDate() - today.getDay())
        const e = new Date(s.getFullYear(), s.getMonth(), s.getDate() + 6)
        startDate.value = startOfDay(s)
        endDate.value = startOfDay(e)
    } else if (preset === 'last_week') {
        const end = new Date(y, m, today.getDate() - today.getDay() - 1)
        const start = new Date(end.getFullYear(), end.getMonth(), end.getDate() - 6)
        startDate.value = startOfDay(start)
        endDate.value = startOfDay(end)
    } else if (preset === 'this_month') {
        startDate.value = new Date(y, m, 1)
        endDate.value = new Date(y, m + 1, 0)
    } else if (preset === 'last_month') {
        startDate.value = new Date(y, m - 1, 1)
        endDate.value = new Date(y, m, 0)
    } else if (preset === 'last_year') {
        startDate.value = new Date(y - 1, 0, 1)
        endDate.value = new Date(y - 1, 11, 31)
    }
    calendarMonth.value = new Date(startDate.value.getFullYear(), startDate.value.getMonth(), 1)
}

function pickDate(date) {
    if (!startDate.value || (startDate.value && endDate.value)) {
        startDate.value = startOfDay(date)
        endDate.value = null
        selectedPreset.value = 'custom_date'
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

function changeMonth(delta) {
    calendarMonth.value = new Date(calendarMonth.value.getFullYear(), calendarMonth.value.getMonth() + delta, 1)
}

function applyDateRange() {
    if (selectedPreset.value && selectedPreset.value !== 'custom_date') {
        form.value.createdOn = selectedPreset.value
        form.value.createdFrom = ''
        form.value.createdTo = ''
    } else if (startDate.value && endDate.value) {
        form.value.createdOn = 'custom_date'
        form.value.createdFrom = formatYmd(startDate.value)
        form.value.createdTo = formatYmd(endDate.value)
    }
    showDateModal.value = false
    applySearch()
}

const resetForm = () => {
    resetFormValues()
    show.value = false
    emit('search', { query: null, activePill: null, activeFilters: [] })
}

watch(officeOptions, (newOptions) => {
    if (form.value.office && form.value.office.length && newOptions.length) {
        const normalizedSelection = normalizeOfficeSelection(form.value.office)
        const validOffices = normalizedSelection.filter(officeId =>
            newOptions.some(opt => normalizeOfficeId(opt.value) === officeId)
        )
        if (validOffices.length !== normalizedSelection.length) {
            form.value.office = validOffices
        } else {
            form.value.office = normalizedSelection
        }
        selectedOffice.value = [...form.value.office]
    }
}, { deep: true })

watch(() => form.value.createdOn, (newVal, oldVal) => {
    if (oldVal === 'custom_date' && newVal !== 'custom_date') {
        form.value.createdFrom = ''
        form.value.createdTo = ''
    }
})

watch(() => form.value.source, (newVal) => {
    if (newVal !== 'website') {
        form.value.sourceWebsite = ''
    }
})

onMounted(async () => {
    updateUserFromStorage() 
    
    await Promise.all([
        fetchResponsiblePersonsWithFilter(),
        fetchBranchSources(),
        fetchStages(),
        fetchSources(),
        fetchTeamsWithFilter(),
        fetchOffices()
    ])
    
    console.log('Initial data loaded')
})
</script>

<style scoped>
/* Add selected indicator style */
.selected-indicator {
    margin-left: 4px;
    font-size: 12px;
}

/* Keep all existing styles from your original code */
.lead-search-dropdown-panel {
    width: 1140px;
    max-width: calc(100vw - 32px);
    min-height: 507px;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12);
    background: #fff;
    overflow: hidden;
}

.lead-search-container {
    min-height: 507px;
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
}

.sidebar-pills {
    min-width: 221px;
    background: #F8FAFC;
    padding: 25px !important;
}

.pill-btn {
    border: none;
    background: #fff;
    border-radius: 100px;
    font-size: 13px;
    color: #475569;
    padding: 1px 10px;
    text-align: center;
    transition: all 0.2s;
    border: 1px solid #E2E8F0;
    width: fit-content;
    text-wrap: nowrap;
    display: inline-flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.pill-btn.active {
    background: #01062C;
    color: #fff;
    border-color: #01062C;
}

.pill-count {
    min-width: 20px;
    height: 20px;
    border-radius: 999px;
    background: #e2e8f0;
    color: #334155;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 6px;
}

.pill-btn.active .pill-count {
    background: rgba(255, 255, 255, 0.18);
    color: #ffffff;
}

.city-children-wrap {
    margin-top: -6px;
    padding: 8px 8px 2px 8px;
    border-radius: 12px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.city-children-title {
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    padding: 0 4px;
}

.city-child-btn {
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #334155;
    border-radius: 10px;
    padding: 6px 10px;
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    transition: all 0.16s ease;
}

.city-child-btn:hover {
    background: #eef2ff;
    border-color: #c7d2fe;
}

.city-child-btn.active {
    background: #eaf3ff;
    border-color: #bfdbfe;
    color: #1d4ed8;
}

.city-children-empty {
    padding: 4px 8px 8px;
    font-size: 11px;
    color: #94a3b8;
}

.city-child-list-enter-active,
.city-child-list-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.city-child-list-enter-from,
.city-child-list-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

.form-content-wrapper {
    padding: 30px 20px !important;
}

.close-btn {
    position: absolute;
    top: 0px;
    right: 10px;
    border: none;
    background: transparent;
    font-size: 22px;
    color: #000000;
    cursor: pointer;
    z-index: 10;
}

.form-label-custom {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: #000000;
    margin-bottom: 2px;
}

.custom-input {
    height: 42px !important;
    border-radius: 10px !important;
    border: 1px solid #E2E8F0 !important;
    font-size: 13px !important;
    color: #64748B !important;
    font-family: 'Montserrat';
}

.custom-input::placeholder {
    color: #64748B !important;
    opacity: 1;
    font-size: 13px !important;
    font-family: 'Montserrat';
}

.custom-date-trigger {
    width: 100%;
    height: 42px;
    border-radius: 10px;
    border: 1px solid #E2E8F0;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 12px;
    font-size: 13px;
    color: #64748B;
    font-family: 'Montserrat';
}

.custom-date-trigger:hover {
    border-color: #cbd5e1;
}

.lr-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(2, 6, 23, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 4000;
    padding: 12px;
}

.lr-date-modal {
    width: min(860px, 96vw);
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 25px 80px rgba(2, 6, 23, 0.25);
    display: grid;
    grid-template-columns: 220px 1fr;
    overflow: hidden;
}

.lr-date-left {
    background: #f8fafc;
    border-right: 1px solid #e2e8f0;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.lr-date-preset {
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 10px;
    padding: 7px 10px;
    font-size: 12px;
    color: #334155;
    text-align: left;
    transition: all .15s ease;
}

.lr-date-preset.active {
    background: #01062C;
    border-color: #01062C;
    color: #fff;
}

.lr-date-right {
    padding: 14px;
}

.lr-calendar-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    font-weight: 700;
    color: #0f172a;
}

.lr-calendar-head button {
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 9px;
    width: 30px;
    height: 30px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    line-height: 1;
}

.lr-calendar-head button iconify-icon {
    font-size: 16px;
    line-height: 1;
}

.lr-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 6px;
    margin-bottom: 6px;
}

.lr-weekdays span {
    text-align: center;
    font-size: 11px;
    color: #64748b;
    font-weight: 700;
}

.lr-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 6px;
}

.lr-day {
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 10px;
    min-height: 34px;
    font-size: 12px;
    color: #334155;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    padding: 0;
}

.lr-day.muted {
    opacity: .45;
}

.lr-day.selected {
    background: #01062C;
    border-color: #01062C;
    color: #fff;
}

.lr-day.inrange:not(.selected) {
    background: #eff6ff;
    border-color: #bfdbfe;
    color: #1d4ed8;
}

.lr-date-actions.large {
    margin-top: 12px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Custom v-select styles */
:deep(.custom-v-select) {
    font-family: 'Montserrat';
}

:deep(.custom-v-select .vs__dropdown-toggle) {
    height: 42px;
    border-radius: 10px;
    border: 1px solid #E2E8F0;
    background: #fff;
    padding: 0 8px;
}

:deep(.custom-v-select .vs__selected-options) {
    flex-wrap: nowrap;
    overflow: hidden;
    max-width: calc(100% - 30px);
}

:deep(.custom-v-select .vs__selected) {
    font-size: 13px;
    color: #64748B;
    margin: 0;
    padding: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
    max-width: 100%;
    line-height: 40px;
}

/* Branch multi-select: show all selected options clearly */
:deep(.office-multi-select .vs__selected-options) {
    flex-wrap: wrap !important;
    overflow: visible !important;
    max-width: calc(100% - 30px);
    gap: 4px;
    padding-top: 4px;
    padding-bottom: 4px;
}

:deep(.office-multi-select .vs__selected) {
    display: inline-flex !important;
    align-items: center;
    line-height: 1.2 !important;
    margin: 0 !important;
    padding: 2px 8px !important;
    border-radius: 999px;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    color: #1e3a8a;
    font-size: 12px;
    white-space: nowrap;
}

:deep(.office-multi-select .vs__search) {
    min-width: 80px;
    line-height: 1.4;
}

:deep(.custom-v-select .vs__search) {
    font-size: 13px;
    color: #64748B;
    margin: 0;
    padding: 0;
}

:deep(.custom-v-select .vs__search::placeholder) {
    color: #64748B;
}

:deep(.custom-v-select .vs__actions) {
    padding: 0 8px;
}

:deep(.custom-v-select .vs__open-indicator-icon) {
    font-size: 15px;
    color: #cfdbec;
}

:deep(.custom-v-select svg) {
    vertical-align: middle !important;
}

:deep(.custom-v-select .vs__dropdown-menu) {
    border: 1px solid #E2E8F0;
    box-shadow: 0px 10px 30px rgba(0, 0, 0, 0.1);
    padding: 0;
    margin-top: 5px;
    z-index: 1100;
}

:deep(.custom-v-select .vs__dropdown-option) {
    padding: 5px 10px;
    font-size: 14px;
    color: #475569;
    transition: all 0.2s;
}

:deep(.custom-v-select .vs__dropdown-option--highlight) {
    background: #FAA300 !important;
    color: #fff !important;
}

:deep(.custom-v-select .vs__dropdown-option--selected) {
    background: #FAA300 !important;
    color: #fff !important;
}

.footer-link {
    font-size: 14px;
    color: #3B82F6;
    font-weight: 500;
}

.btn-reset,.btn-cancel {
    background: #F4F4F4;
    border: none;
    padding: 10px 25px;
    border-radius: 100px;
    font-size: 14px;
    color: #01062C;
}

.btn-search,.btn-apply {
    background: #000;
    border: none;
    padding: 10px 25px;
    border-radius: 100px;
    font-size: 14px;
    color: #fff;
}

</style>
<style>
    .modal-dialog {
        z-index: 1060 !important;
    }
    .vs__dropdown-menu {
        z-index: 9999 !important;
    }
    .vs__dropdown-option--highlight {
        background: #FAA300 !important;
        color: #fff !important;
    }
    .vs__dropdown-option--selected {
        background: #FAA300 !important;
        color: #fff !important;
    }
</style>