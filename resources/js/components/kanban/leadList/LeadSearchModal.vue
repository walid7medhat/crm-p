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
                    @click="activePill = pill.id"
                >
                    {{ pill.label }}
                </button>
            </div>
            <div class="form-content-wrapper flex-grow-1 position-relative">
                <button class="close-btn" @click="show = false">
                    <iconify-icon icon="lucide:x"></iconify-icon>
                </button>
                <div class="row g-4">
                    <template v-for="field in visibleSearchFields" :key="field.id">
                        <div class="col-md-6 mt-3">
                            <label class="form-label-custom">{{ field.label }}</label>
                            <b-form-input
                                v-if="field.type === 'text'"
                                v-model="form[field.formKey]"
                                :placeholder="field.placeholder"
                                class="custom-input"
                            />
                            <v-select
                                v-else-if="field.type === 'select'"
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
                    @click="activePill = pill.id"
                >
                    {{ pill.label }}
                </button>
            </div>
            <div class="form-content-wrapper flex-grow-1 position-relative">
                <button class="close-btn" @click="emit('update:modelValue', false)">
                    <iconify-icon icon="lucide:x"></iconify-icon>
                </button>
                <div class="row g-4">
                    <template v-for="field in visibleSearchFields" :key="field.id">
                        <div class="col-md-6 mt-3">
                            <label class="form-label-custom">{{ field.label }}</label>
                            <b-form-input
                                v-if="field.type === 'text'"
                                v-model="form[field.formKey]"
                                :placeholder="field.placeholder"
                                class="custom-input"
                            />
                            <v-select
                                v-else-if="field.type === 'select'"
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
                        </div>
                    </template>
                   <!-- Custom Date Range -->
                    <div v-if="showCustomDateRange" class="row mt-4 pt-2 ">
                        <div class="col-12">
                            <label class="form-label-custom fw-bold mb-3">Custom Date Range</label>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">From Date</label>
                            <b-form-input
                                v-model="form.createdFrom"
                                type="date"
                                class="custom-input"
                            />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">To Date</label>
                            <b-form-input
                                v-model="form.createdTo"
                                type="date"
                                class="custom-input"
                            />
                        </div>
                    </div>
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
    /** When true, render as dropdown panel instead of modal */
    asDropdown: { type: Boolean, default: false },
    /** When provided, this pill is selected when the modal opens */
    initialActivePill: { type: String, default: undefined },
    /** When false, form is reset when modal opens (e.g. filters were cleared in parent) */
    hasActiveFilters: { type: Boolean, default: true },
    /** Current search query from parent; form is synced from this when modal opens or when it changes */
    currentQuery: { type: Object, default: null },
     showTeamFilter: { type: Boolean, default: false }
})

const emit = defineEmits(['update:modelValue', 'search'])

const show = ref(props.modelValue)
const showFilterSettings = ref(false)
const selectedLeadFieldIds = ref(['first_name', 'lead_name', 'created_on', 'work_phone', 'responsible_person', 'lead_branch_source', 'stage', 'email', 'bedrooms','source','team'])
// const activePill = ref('leads-in-progress')
const activePill = ref(props.initialActivePill || 'leads-in-progress')
const teamOptions = ref([{ value: null, text: 'Select Team' }])


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
    team_id: 'team'
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
          team: ''
    }
    Object.keys(queryToFormKeys).forEach(qKey => {
        const formKey = queryToFormKeys[qKey]
        if (query[qKey] !== undefined && query[qKey] !== '') {
            next[formKey] = query[qKey]
        }
    })
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


const sidebarPills = [
    { id: 'closed-leads', label: 'Converted Leads' },
    { id: 'leads-in-progress', label: 'Leads In Progress' },
    { id: 'my-leads', label: 'My Leads' },
    { id: 'dubai', label: 'Dubai' },
    { id: 'abu-dhabi', label: 'Abu Dhabi' }
]

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
      team: ''
})

const responsiblePersons = ref([])
const branchSourceOptions = ref([{ value: null, text: 'Select Branch Source' }])
const stageOptions = ref([{ value: null, text: 'Select Stage' }])

const personOptions = computed(() => {
    const opts = [{ value: null, text: 'Select Person' }]
    responsiblePersons.value.forEach(p => {
        opts.push({ value: p.id, text: p.name || `User ${p.id}` })
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
    // { value: 'tomorrow', text: 'Tomorrow' },
    { value: 'this_week', text: 'This Week' },
    { value: 'this_month', text: 'This Month' },
    { value: 'current_quarter', text: 'Current Quarter' },
    { value: 'last_7_days', text: 'Last 7 Days' },
    { value: 'last_30_days', text: 'Last 30 Days' },
    { value: 'last_60_days', text: 'Last 60 Days' },
    { value: 'last_90_days', text: 'Last 90 Days' },
    { value: 'last_week', text: 'Last Week' },
    { value: 'last_month', text: 'Last Month' },
    // { value: 'next_week', text: 'Next Week' },
    // { value: 'next_month', text: 'Next Month' },
    // { value: 'exact_date', text: 'Exact Date' }
    { value: 'custom_date', text: 'Custom Date' }
]

const sourceOptions = ref([{ value: null, text: 'Select Source' }])
// const searchFieldsConfig = [
//     { id: 'first_name', label: 'First Name', formKey: 'firstName', queryKey: 'first_name', type: 'text', placeholder: 'Enter First Name' },
//     { id: 'lead_name', label: 'Lead Name', formKey: 'leadName', queryKey: 'lead_name', type: 'text', placeholder: 'Enter Lead Name' },
//     // { id: 'closed', label: 'Converted', formKey: 'closed', queryKey: 'closed', type: 'select', options: yesNoOptions },
//      { id: 'created_on', label: 'Created On', formKey: 'createdOn', queryKey: 'created_at', type: 'select', options: createdOnOptions },
//     { id: 'work_phone', label: 'Work Phone', formKey: 'workPhone', queryKey: 'work_phone', type: 'text', placeholder: 'Enter Work Phone' },
//     { id: 'responsible_person', label: 'Responsible Person', formKey: 'responsible', queryKey: 'responsible_person_id', type: 'select', options: [] },
//     { id: 'email', label: 'Email', formKey: 'email', queryKey: 'email', type: 'text', placeholder: 'Enter Email' },
//     { id: 'stage', label: 'Stage', formKey: 'stage', queryKey: 'stage_id', type: 'select', options: [] },
//     { id: 'lead_branch_source', label: 'Lead Branch Source', formKey: 'branchSource', queryKey: 'lead_branch_source', type: 'select', options: [] },
//     { id: 'bedrooms', label: 'Bedrooms', formKey: 'bedrooms', queryKey: 'bedrooms', type: 'select', options: bedroomsOptions },
//     { id: 'source', label: 'Source', formKey: 'source', queryKey: 'source', type: 'select', options: [] },

       
// ]
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
        console.log('User updated from storage:', user.value)
    } catch (error) {
        console.error('Error getting user from storage:', error)
        user.value = null
    }
}
// Applied search params (from search modal, not from URL)
const appliedSearchParams = ref(null)

// Check if user is admin or super_admin (same pattern as header/index.vue)
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
        { id: 'work_phone', label: 'Work Phone', formKey: 'workPhone', queryKey: 'work_phone', type: 'text', placeholder: 'Enter Work Phone' },
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
        { id: 'source', label: 'Source', formKey: 'source', queryKey: 'source', type: 'select', options: [] }
    )
    
    console.log(isAdminOrSuperAdmin.value)
    return fields
})

const showCustomDateRange = computed(() => {
    return form.value.createdOn === 'custom_date'  
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
                f.formKey === 'team' ? teamOptions.value :
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
    return val !== null && val !== undefined && val !== ''
}

function getDisplayValue(field, rawValue) {
    if (rawValue === null || rawValue === undefined || rawValue === '') return null
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
    let responsiblePersonId = form.value.responsible ?? undefined
    let closed = form.value.closed ?? undefined
    let teamId = form.value.team ?? undefined
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
        today.setHours(0, 0, 0, 0) // بداية اليوم
        
        switch (form.value.createdOn) {
            case 'today':
                createdAt = today.toISOString().split('T')[0] // YYYY-MM-DD
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
                startOfWeek.setDate(today.getDate() - today.getDay()) // الأحد
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
        // created_at: form.value.createdOn || undefined,
        work_phone: form.value.workPhone || undefined,
        stage_id: form.value.stage ?? undefined,
        email: form.value.email || undefined,
        bedrooms: form.value.bedrooms ?? undefined,
        search: form.value.search || undefined,
       source: form.value.source || undefined ,
        created_from: createdFrom  || undefined,  
        created_to: createdTo || undefined,     
        created_at: createdAt  || undefined,   
         team_id: teamId || undefined 
    }
    Object.keys(query).forEach(k => { if (query[k] === '' || query[k] === undefined) delete query[k] })
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
console.log(activePill);
    const pill = sidebarPills.find(p => p.id === activePill.value)
    console.log(pill.id);
    emit('search', { query, activePill: pill ? { id: pill.id, label: pill.label } : null, activeFilters })
    show.value = false
}

async function fetchResponsiblePersons() {
    try {
        const res = await api.get('/available-responsible-persons')
        if (res.data?.data) responsiblePersons.value = res.data.data
    } catch (_) {}
}

async function fetchBranchSources() {
    try {
        const res = await api.get('/get/lead/branch_source')
        const data = res.data?.data
        if (Array.isArray(data) && data.length) {
            branchSourceOptions.value = [
                { value: null, text: 'Select Branch Source' },
                ...data.map(b => ({ value: b.id, text: b.name }))
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
    try {
        const res = await api.get('/sources')
        const data = res.data?.data
        if (Array.isArray(data) && data.length) {
            sourceOptions.value = [
                { value: null, text: 'Select Source' },
                ...data.map(s => ({ value: s.name, text: s.name }))
            ]
        }
    } catch (error) {
        console.error('Error fetching sources:', error)
    }
}
async function fetchTeams() {
    try {
        const res = await api.get('/teams-with-leads') // You'll need to create this endpoint
        const data = res.data?.data
        if (Array.isArray(data) && data.length) {
            teamOptions.value = [
                { value: null, text: 'Select Team' },
                ...data.map(team => ({ value: team.id, text: team.name }))
            ]
        }
    } catch (error) {
        console.error('Error fetching teams:', error)
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
         team: ''
    }
}

const resetForm = () => {
    resetFormValues()
    show.value = false
    emit('search', { query: null, activePill: null, activeFilters: [] })
}
watch(() => form.value.createdOn, (newVal, oldVal) => {
    if (oldVal === 'custom_date' && newVal !== 'custom_date') {
        form.value.createdFrom = ''
        form.value.createdTo = ''
    }
})
onMounted(() => {
      updateUserFromStorage() 
    fetchResponsiblePersons()
    fetchBranchSources()
    fetchStages()
    fetchSources()
        fetchTeams()
})
</script>

<style scoped>
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
}

.pill-btn.active {
    background: #01062C;
    color: #fff;
    border-color: #01062C;
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

/* Custom v-select styles (same as CreateLeadModal) */
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

/* :deep(.custom-v-select .vs__clear) {
    display: none !important;
}

:deep(.custom-v-select:has(.vs__selected) .vs__clear) {
    display: block !important;
} */

:deep(.custom-v-select .vs__open-indicator-icon) {
    font-size: 16px;
    color: #64748B;
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
    /* text-decoration: underline; */
    color: #3B82F6;
    font-weight: 500;
}

.btn-reset {
    background: #F4F4F4;
    border: none;
    padding: 10px 25px;
    border-radius: 100px;
    font-size: 14px;
    color: #01062C;
}

.btn-search {
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
    /* Appended v-select dropdown (append-to-body) must sit above modal */
    .vs__dropdown-menu {
        z-index: 9999 !important;
    }
    /* Highlight color for dropdown options when appended to body */
    .vs__dropdown-option--highlight {
        background: #FAA300 !important;
        color: #fff !important;
    }
    .vs__dropdown-option--selected {
        background: #FAA300 !important;
        color: #fff !important;
    }
</style>

