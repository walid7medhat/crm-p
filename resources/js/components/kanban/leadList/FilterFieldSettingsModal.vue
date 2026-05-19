<template>
    <b-modal
        id="filter-field-settings-modal"
        v-model="show"
        title="Filter Field Settings"
        centered
        size="lg"
        hide-footer
        hide-header
        body-class="p-4"
    >
        <div class="modal-body-content p-3">
            <!-- Custom Header -->
            <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom-light">
                <h5 class="modal-title-custom mb-0">Filter Field Settings</h5>
                <button class="close-btn-custom" @click="show = false">
                    <iconify-icon icon="lucide:x" width="20" height="20"></iconify-icon>
                </button>
            </div>
            
            <!-- Tabs -->
            <div class="d-flex gap-2 mb-4">
                <button 
                    class="tab-btn" 
                    :class="{ active: activeTabs.includes('leads') }"
                    @click="toggleTab('leads')"
                >
                    Leads
                    <span v-if="activeTabs.includes('leads')" class="check-badge">
                        <iconify-icon icon="lucide:check" width="10"></iconify-icon>
                    </span>
                </button>
            </div>

            <!-- Leads Section -->
            <div v-show="activeTabs.includes('leads')" class="settings-section mb-3 mt-3">
                <div class="section-header mb-3 border-bottom">
                    <b-form-checkbox v-model="allLeads" class="main-checkbox">
                        <span class="section-title">Leads</span>
                    </b-form-checkbox>
                </div>
                <div class="settings-subsections">
                    <div
                        v-for="section in groupedLeadSections"
                        :key="section.id"
                        class="settings-subsection"
                    >
                        <div class="settings-subsection-head">
                            <b-form-checkbox :model-value="isSectionChecked(section.id)" @update:model-value="setSectionChecked(section.id, $event)">
                                <span class="settings-subsection-title">{{ section.title }}</span>
                            </b-form-checkbox>
                        </div>
                        <div class="fields-grid">
                            <div v-for="field in section.fields" :key="field.id" class="field-item">
                                <b-form-checkbox v-model="field.checked">
                                    <span class="field-checkbox">{{ field.label }}</span>
                                </b-form-checkbox>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="d-flex align-items-center justify-content-between mt-4">
                <a href="#" class="text-muted text-decoration-none fs-14" @click.prevent="resetToDefaultFields">Default Fields</a>
                <div class="d-flex gap-2">
                    <button class="btn btn-cancel rounded-pill" @click="show = false">Cancel</button>
                    <button class="btn btn-apply rounded-pill" @click="applySettings">Apply</button>
                </div>
            </div>
        </div>
    </b-modal>
</template>

<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { BModal, BFormCheckbox } from 'bootstrap-vue-3'

const props = defineProps({
    modelValue: Boolean,
    initialSelectedLeadIds: {
        type: Array,
        default: undefined
    }
})

const emit = defineEmits(['update:modelValue', 'apply'])
const STORAGE_KEY = 'selectedLeadFields'

const show = ref(props.modelValue)
const activeTabs = ref(['leads'])

// تعريف الحقول مع قيم افتراضية
const getDefaultLeadFields = () => [
    { id: 'lead_name', label: 'Lead Name', checked: true },
    { id: 'first_name', label: 'Client Name', checked: true },
    { id: 'created_on', label: 'Created On', checked: true },
    { id: 'assigned_on', label: 'Assign On', checked: true },
    { id: 'work_phone', label: 'Phone', checked: true },
    { id: 'responsible_person', label: 'Responsible Person', checked: true },
    { id: 'source', label: 'Source', checked: true },
    { id: 'lead_branch_source', label: 'Lead Branch Source', checked: true },
    { id: 'email', label: 'Email', checked: true },
    { id: 'bedrooms', label: 'Bedrooms', checked: false },
    { id: 'office', label: 'Branch', checked: true },
    { id: 'team', label: 'Team', checked: true },
    { id: 'location', label: 'Location / Area', checked: false },
    { id: 'quality_status', label: 'Quality Status', checked: true },
    { id: 'property_type', label: 'Property Type', checked: false },
    { id: 'lead_type', label: 'Lead Type', checked: false },
    { id: 'property_status', label: 'Property Status', checked: false },
    { id: 'budget_from', label: 'Budget (AED)', checked: false },
    { id: 'purpose_purchase', label: 'Purpose of Purchase', checked: false },
    { id: 'stage', label: 'Stage', checked: true },
    { id: 'interaction_result', label: 'Call Result', checked: true }
]

const leadFields = ref(getDefaultLeadFields())

const leadFieldSections = [
    { id: 'lead-core', title: 'Lead Information', fieldIds: ['first_name', 'lead_name', 'work_phone', 'email', 'created_on', 'assigned_on'] },
    { id: 'assignment', title: 'Assignment', fieldIds: ['responsible_person', 'office', 'team'] },
    { id: 'source', title: 'Source', fieldIds: ['source', 'lead_branch_source'] },
    { id: 'qualification', title: 'Qualification', fieldIds: ['quality_status', 'lead_type', 'property_status', 'stage', 'interaction_result'] },
    { id: 'client-req', title: 'Client Requirement', fieldIds: ['location', 'property_type', 'bedrooms', 'budget_from', 'purpose_purchase'] }
]

const groupedLeadSections = computed(() =>
    leadFieldSections
        .map(section => ({
            ...section,
            fields: section.fieldIds
                .map(id => leadFields.value.find(field => field.id === id))
                .filter(Boolean)
        }))
        .filter(section => section.fields.length > 0)
)

const isSectionChecked = (sectionId) => {
    const section = groupedLeadSections.value.find(s => s.id === sectionId)
    if (!section || !section.fields.length) return false
    return section.fields.every(field => field.checked)
}

const setSectionChecked = (sectionId, checked) => {
    const section = groupedLeadSections.value.find(s => s.id === sectionId)
    if (!section) return
    section.fields.forEach(field => {
        field.checked = !!checked
    })
}

const defaultFieldIds = [
    'first_name', 'lead_name', 'created_on', 'assigned_on', 'work_phone',
    'responsible_person', 'office', 'email', 'source', 'lead_branch_source', 
    'team', 'stage', 'quality_status', 'interaction_result'
]

// ✅ دالة لاستعادة الحقول من localStorage
const restoreFieldsFromStorage = () => {
    try {
        const savedFields = localStorage.getItem('selectedLeadFields')
        if (savedFields) {
            const savedIds = JSON.parse(savedFields)
            if (Array.isArray(savedIds) && savedIds.length) {
                leadFields.value = leadFields.value.map(field => ({
                    ...field,
                    checked: savedIds.includes(field.id)
                }))
                console.log('Restored fields from localStorage:', savedIds)
                return true
            }
        }
        return false
    } catch (error) {
        console.error('Error restoring fields from storage:', error)
        return false
    }
}

// ✅ دالة لتحميل الحقول الأولية
const loadInitialFields = () => {
    // أولاً: حاول استعادة من localStorage
    const hasSavedFields = restoreFieldsFromStorage()
    
    // ثانياً: إذا لم توجد حقول محفوظة واستخدمنا initialSelectedLeadIds
    if (!hasSavedFields && props.initialSelectedLeadIds && Array.isArray(props.initialSelectedLeadIds)) {
        const ids = [...props.initialSelectedLeadIds]
        leadFields.value = leadFields.value.map(field => ({
            ...field,
            checked: ids.includes(field.id)
        }))
        console.log('Loaded from initialSelectedLeadIds:', ids)
    }
}

const getAllFieldIds = computed(() => {
    return leadFields.value.map(f => f.id)
})

const saveFieldsToLocalStorage = () => {
    const selectedLeads = leadFields.value.filter(f => f.checked).map(f => f.id)
    console.log('Saving to localStorage:', selectedLeads)
    localStorage.setItem(STORAGE_KEY, JSON.stringify(selectedLeads))
    return selectedLeads
}

const applySettings = () => {
    const selectedLeads = saveFieldsToLocalStorage()
    emit('apply', { leads: selectedLeads })
    show.value = false
}

const resetToDefaultFields = () => {
    const defaultIds = [...defaultFieldIds]
    leadFields.value = leadFields.value.map(field => ({
        ...field,
        checked: defaultIds.includes(field.id)
    }))
    localStorage.setItem(STORAGE_KEY, JSON.stringify(defaultIds))
    emit('apply', { leads: defaultIds })
}

const loadFromLocalStorage = () => {
    try {
        const savedFields = localStorage.getItem(STORAGE_KEY)
        if (savedFields) {
            const parsed = JSON.parse(savedFields)
            if (Array.isArray(parsed) && parsed.length) {
                leadFields.value = leadFields.value.map(field => ({
                    ...field,
                    checked: parsed.includes(field.id)
                }))
                console.log('Loaded from localStorage in settings modal:', parsed)
                return
            }
        }
        if (Array.isArray(props.initialSelectedLeadIds) && props.initialSelectedLeadIds.length) {
            const selectedSet = new Set(props.initialSelectedLeadIds)
            leadFields.value = leadFields.value.map(field => ({
                ...field,
                checked: selectedSet.has(field.id)
            }))
        }
    } catch (error) {
        console.error('Error loading from localStorage:', error)
    }
}

const toggleTab = (tab) => {
    const index = activeTabs.value.indexOf(tab)
    if (index > -1) {
        activeTabs.value.splice(index, 1)
    } else {
        activeTabs.value.push(tab)
    }
}

const allLeads = computed({
    get: () => leadFields.value.every(f => f.checked),
    set: (val) => leadFields.value.forEach(f => f.checked = val)
})

// مراقبة فتح الموديل
watch(() => props.modelValue, (val) => {
    show.value = val
    if (val) {
        loadFromLocalStorage()
    }
})

watch(() => props.initialSelectedLeadIds, (ids) => {
    if (!show.value || !Array.isArray(ids)) return
    const selectedSet = new Set(ids)
    leadFields.value = leadFields.value.map(field => ({
        ...field,
        checked: selectedSet.has(field.id)
    }))
}, { deep: true })

watch(show, (val) => {
    emit('update:modelValue', val)
})

// تحميل أولي للمكون
onMounted(() => {
    loadInitialFields()
})
</script>

<style scoped>
/* Hide the theme's custom dot */
:deep(.form-check-input::before) {
    display: none !important;
}

/* Apply standard checkmark and orange theme */
:deep(.form-check-input:checked) {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 10 3 3 6-6'/%3e%3c/svg%3e") !important;
    background-color: #F59E0B !important;
    border-color: #F59E0B !important;
    background-size: 18px 18px !important;
    background-position: center !important;
    background-repeat: no-repeat !important;
    box-shadow: 0px 0px 5px 4px #733E8726 !important;
    border-radius: 4px !important;
}

.modal-body-content,
.modal-body-content * {
    font-family: 'Montserrat', sans-serif !important;
    font-weight: 500;
    font-size: 14px;
}

.modal-body-content {
    font-family: 'Montserrat', sans-serif;
    font-weight: 500;
    font-size: 14px;
}

.modal-title-custom {
    font-size: 14px !important;
    font-weight: 500 !important;
    color: #0B0736;
    font-family: 'Montserrat', sans-serif;
}

.close-btn-custom {
    position: absolute;
    top: 8px;
    right: -61px;
    width: 83px;
    height: 49px;
    color: rgb(255, 255, 255);
    font-size: 18px;
    line-height: 1;
    box-shadow: rgba(15, 23, 42, 0.2) 0px 8px 16px;
    z-index: -1;
    display: flex;
    justify-content: center;
    align-items: center;
    border-width: 1px;
    border-style: solid;
    border-color: rgba(115, 62, 135, 0.75);
    border-image: initial;
    border-radius: 999px;
    background: var(--gradient-crm, linear-gradient(135deg, #0b0736 0%, #733e87 100%));
    padding: 0px;
    transition: filter 0.2s;;
}

.border-bottom-light {
    border-bottom: 1px solid #F1F5F9 !important;
}

.tab-btn {
    border: 1px solid #E2E8F0;
    background: #fff;
    border-radius: 100px;
    padding: 3px 15px;
    font-size: 13px;
    color: #666666;
    position: relative;
    font-weight: 400;
    transition: all 0.2s;
    font-family: 'Montserrat', sans-serif;
    display: flex;
    align-items: center;
    justify-content: center;
}

.tab-btn.active {
    background: #0B0736;
    color: #fff;
    border-color: #0B0736;
    font-weight: 500;
}

.check-badge {
    position: absolute;
    top: -6px;
    right: -2px;
    background: #F59E0B;
    color: white;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1.5px solid #fff;
    z-index: 2;
}

.settings-section {
    background: #fff;
    border: 1px solid #F1F5F9;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 1px 1px 5px 5px #00000005;
}

.section-title {
    font-size: 13px;
    font-weight: 500;
    color: #0B0736;
    margin-left: 8px;
    margin-bottom: 2px;
}

.fields-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px 24px;
}

.settings-subsections {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.settings-subsection {
    border: 1px solid #F1F5F9;
    border-radius: 12px;
    padding: 12px 14px;
    background: #fff;
}

.settings-subsection-head {
    border-bottom: 1px solid #F1F5F9;
    margin-bottom: 10px;
    padding-bottom: 8px;
}

.settings-subsection-title {
    font-size: 12px;
    font-weight: 600;
    color: #0B0736;
}

.field-item {
    font-size: 14px;
}

:deep(.form-check-input) {
    width: 20px;
    height: 20px;
    margin-top: 0.15em;
    cursor: pointer;
    border-radius: 6px !important;
    border: 1.5px solid #E2E8F0;
}

:deep(.form-check-input:checked) {
    background-color: #F59E0B;
    border-color: #F59E0B;
}

:deep(.form-check-label) {
    cursor: pointer;
    padding-left: 8px;
    font-size: 14px;
    color: #64748B;
    font-weight: 500;
}

.fs-14 {
    font-size: 14px;
}

.btn-cancel {
    background: #F4F4F4;
    border: none;
    padding: 10px 25px;
    border-radius: 100px;
    font-size: 14px;
    color: #0B0736;
}

.btn-apply {
    background: #000;
    border: none;
    padding: 10px 25px;
    border-radius: 100px;
    font-size: 14px;
    color: #fff;
}

.text-muted {
    color: #979797 !important;
    font-family: Montserrat;
    font-weight: 400;
    font-style: Medium;
    font-size: 13px;
    margin-left: 5px;
}

.field-checkbox {
    font-size: 13px !important;
    font-weight: 400 !important;
    color: #666666 !important;
    font-family: 'Montserrat', sans-serif !important;
}

:deep(.form-check .form-check-input) {
    margin-top: 3px !important;
}

.section-header {
    padding-bottom: 10px !important;
}
</style>