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
    currentQuery: { type: Object, default: null }
})

const emit = defineEmits(['update:modelValue', 'search'])

const show = ref(props.modelValue)
const showFilterSettings = ref(false)
const selectedLeadFieldIds = ref(['first_name', 'lead_name', 'closed', 'work_phone', 'responsible_person', 'lead_branch_source', 'stage', 'email', 'bedrooms'])

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
    lead_branch_source: 'branchSource',
    closed: 'closed',
    work_phone: 'workPhone',
    stage_id: 'stage',
    email: 'email',
    bedrooms: 'bedrooms',
    search: 'search'
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
        stageChangedBy: '',
        branchSource: '',
        closed: '',
        workPhone: '',
        stage: '',
        email: '',
        bedrooms: '',
        leadName: ''
    }
    Object.keys(queryToFormKeys).forEach(qKey => {
        const formKey = queryToFormKeys[qKey]
        if (query[qKey] !== undefined && query[qKey] !== '') {
            next[formKey] = query[qKey]
        }
    })
    form.value = next
}

watch(show, (val) => {
    emit('update:modelValue', val)
    if (val) {
        if (props.initialActivePill) activePill.value = props.initialActivePill
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

const activePill = ref('leads-in-progress')

const sidebarPills = [
    { id: 'closed-leads', label: 'Closed Leads' },
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
    leadName: ''
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

const searchFieldsConfig = [
    { id: 'first_name', label: 'First Name', formKey: 'firstName', queryKey: 'first_name', type: 'text', placeholder: 'Enter First Name' },
    { id: 'lead_name', label: 'Lead Name', formKey: 'leadName', queryKey: 'lead_name', type: 'text', placeholder: 'Enter Lead Name' },
    { id: 'closed', label: 'Closed', formKey: 'closed', queryKey: 'closed', type: 'select', options: yesNoOptions },
    { id: 'work_phone', label: 'Work Phone', formKey: 'workPhone', queryKey: 'work_phone', type: 'text', placeholder: 'Enter Work Phone' },
    { id: 'responsible_person', label: 'Responsible Person', formKey: 'responsible', queryKey: 'responsible_person_id', type: 'select', options: [] },
    { id: 'lead_branch_source', label: 'Lead Branch Source', formKey: 'branchSource', queryKey: 'lead_branch_source', type: 'select', options: [] },
    { id: 'stage', label: 'Stage', formKey: 'stage', queryKey: 'stage_id', type: 'select', options: [] },
    { id: 'email', label: 'Email', formKey: 'email', queryKey: 'email', type: 'text', placeholder: 'Enter Email' },
    { id: 'bedrooms', label: 'Bedrooms', formKey: 'bedrooms', queryKey: 'bedrooms', type: 'select', options: bedroomsOptions }
]

const visibleSearchFields = computed(() => {
    return searchFieldsConfig
        .filter(f => selectedLeadFieldIds.value.includes(f.id))
        .map(f => ({
            ...f,
            options: f.formKey === 'responsible' ? personOptions.value : (f.formKey === 'branchSource' ? branchSourceOptions.value : (f.formKey === 'stage' ? stageOptions.value : (f.options || []))),
            placeholder: f.placeholder || (f.type === 'select' ? 'Select' : '')
        }))
})

const defaultLeadFieldIds = searchFieldsConfig.map(f => f.id)

function restoreDefaultFields() {
    selectedLeadFieldIds.value = [...defaultLeadFieldIds]
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
    const query = {
        lead_name: form.value.leadName || undefined,
        first_name: form.value.firstName || undefined,
        responsible_person_id: form.value.responsible ?? undefined,
        created_at: form.value.createdOn || undefined,
        lead_branch_source: form.value.branchSource || undefined,
        closed: form.value.closed ?? undefined,
        work_phone: form.value.workPhone || undefined,
        stage_id: form.value.stage ?? undefined,
        email: form.value.email || undefined,
        bedrooms: form.value.bedrooms ?? undefined,
        search: form.value.search || undefined,
    }
    Object.keys(query).forEach(k => { if (query[k] === '' || query[k] === undefined) delete query[k] })

    const activeFilters = []
    const visibleFields = searchFieldsConfig.filter(f => selectedLeadFieldIds.value.includes(f.id))
    visibleFields.forEach(field => {
        const raw = form.value[field.formKey]
        if (!hasValue(raw)) return
        const displayValue = getDisplayValue(
            { ...field, options: field.formKey === 'responsible' ? personOptions.value : (field.formKey === 'branchSource' ? branchSourceOptions.value : (field.formKey === 'stage' ? stageOptions.value : (field.options || []))) },
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

    const pill = sidebarPills.find(p => p.id === activePill.value)
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

function resetFormValues() {
    form.value = {
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
        bedrooms: ''
    }
}

const resetForm = () => {
    resetFormValues()
    show.value = false
    emit('search', { query: null, activePill: null, activeFilters: [] })
}

onMounted(() => {
    fetchResponsiblePersons()
    fetchBranchSources()
    fetchStages()
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

