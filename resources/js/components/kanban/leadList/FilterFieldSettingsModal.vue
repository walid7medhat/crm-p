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
                <!--<button -->
                <!--    class="tab-btn" -->
                <!--    :class="{ active: activeTabs.includes('activity') }"-->
                <!--    @click="toggleTab('activity')">-->
                <!---->
                <!--    Activity-->
                <!--    <span v-if="activeTabs.includes('activity')" class="check-badge">-->
                <!--        <iconify-icon icon="lucide:check" width="10"></iconify-icon>-->
                <!--    </span>-->
                <!--</button>-->
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

            <!-- Activity Section -->
            <div v-show="activeTabs.includes('activity')" class="settings-section activity-section mb-4">
                <div class="section-header mb-3 border-bottom">
                    <b-form-checkbox v-model="allActivity" class="main-checkbox">
                        <span class="section-title">Activity</span>
                    </b-form-checkbox>
                </div>
                <div class="fields-grid">
                    <div v-for="field in activityFields" :key="field.id" class="field-item">
                        <b-form-checkbox v-model="field.checked">
                            <span class="field-checkbox">{{ field.label }}</span>
                        </b-form-checkbox>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="d-flex align-items-center justify-content-between mt-4">
                <a href="#" class="text-muted text-decoration-none fs-14" @click.prevent>Default Fields</a>
                <div class="d-flex gap-2">
                    <button class="btn btn-cancel rounded-pill" @click="show = false">Cancel</button>
                    <button class="btn btn-apply rounded-pill" @click="applySettings">Apply</button>
                </div>
            </div>
        </div>
    </b-modal>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { BModal, BFormCheckbox } from 'bootstrap-vue-3'

const props = defineProps({
    modelValue: Boolean,
    /** When provided, lead field checkboxes are synced to this list when modal opens */
    initialSelectedLeadIds: {
        type: Array,
        default: undefined
    }
})

const emit = defineEmits(['update:modelValue', 'apply'])

const show = ref(props.modelValue)
const activeTabs = ref(['leads'])

const leadFields = ref([
    { id: 'first_name', label: 'First Name', checked: true },
    { id: 'lead_name', label: 'Lead Name', checked: true },
    { id: 'closed', label: 'Converted', checked: false },
    { id: 'created_on', label: 'Created On', checked: true },
    { id: 'work_phone', label: 'Phone', checked: true },
    { id: 'responsible_person', label: 'Responsible Person', checked: true },
    { id: 'source', label: 'Source', checked: true },
    { id: 'lead_branch_source', label: 'Lead Branch Source', checked: true },
    { id: 'stage', label: 'Stage', checked: true },
    { id: 'email', label: 'Email', checked: true },
    { id: 'bedrooms', label: 'Bedrooms', checked: false },
    { id: 'office', label: 'Branch', checked: true },
    { id: 'team', label: 'Team', checked: true },
    { id: 'location', label: 'Location / Area', checked: false },
    { id: 'interaction_result', label: 'Call Result', checked: false },
    { id: 'property_type', label: 'Property Type', checked: false },
    { id: 'lead_type', label: 'Lead Type', checked: false },
    { id: 'property_status', label: 'Property Status', checked: false },
    { id: 'budget_from', label: 'Budget (AED)', checked: false }
])

const leadFieldSections = [
    { id: 'lead-core', title: 'Lead Information', fieldIds: ['first_name', 'lead_name', 'work_phone', 'email', 'created_on', 'closed'] },
    { id: 'assignment', title: 'Assignment', fieldIds: ['responsible_person', 'lead_branch_source', 'office', 'team', 'stage', 'source'] },
    { id: 'source', title: 'Source & Follow-up', fieldIds: ['interaction_result'] },
    { id: 'client-req', title: 'Client Requirement', fieldIds: ['location', 'property_type', 'lead_type', 'property_status', 'bedrooms', 'budget_from'] }
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
    'first_name', 'lead_name', 'created_on', 'work_phone', 
    'responsible_person', 'lead_branch_source', 'office', 'stage', 
    'email', 'source', 'team'
]

const restoreDefaultFields = () => {
    leadFields.value.forEach(field => {
        field.checked = defaultFieldIds.includes(field.id)
    })
}

watch(() => props.modelValue, (val) => {
    show.value = val
})

watch(show, (val) => {
    emit('update:modelValue', val)
    if (val && props.initialSelectedLeadIds && Array.isArray(props.initialSelectedLeadIds)) {
        const ids = [...props.initialSelectedLeadIds]
        if (ids.includes('budget_to') && !ids.includes('budget_from')) {
            ids.push('budget_from')
        }
        leadFields.value.forEach(f => {
            f.checked = ids.includes(f.id)
        })
    }
})

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

const applySettings = () => {
    const selectedLeads = leadFields.value.filter(f => f.checked).map(f => f.id)
    emit('apply', { leads: selectedLeads })
    show.value = false
}
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
    box-shadow: 0px 0px 5px 4px #FAA30026 !important;
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
    color: #01062C;
    font-family: 'Montserrat', sans-serif;
}

.close-btn-custom {
    background: transparent;
    border: none;
    color: #000;
    cursor: pointer;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
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
    background: #01062C;
    color: #fff;
    border-color: #01062C;
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
    color: #01062C;
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
    color: #01062C;
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
    color: #01062C;
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
