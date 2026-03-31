<!-- StageChangeReasonModal.vue -->
<template>

    <div v-if="visible" class="stage-change-modal-overlay" @click.self="closeModal">
        <div class="stage-change-modal" :class="{ 'modal-wide': missingFields.length > 0 }">
            <div class="modal-header">
                <h5 class="modal-title">{{ isConversion ? 'Complete Lead Information' : `Move Lead to ${targetStageName}` }}</h5>
                <button type="button" class="btn-close" @click="closeModal"></button>
            </div>
            
            <div class="modal-body">
                <!-- Reason Section -->
                <div class="mb-4 box-shadow">
                    <label for="reason" class="form-label">
                        Please provide a reason for moving this lead <span class="text-danger">*</span>
                    </label>
                    <textarea
                        id="reason"
                        v-model="formData.reason"
                        rows="3"
                        class="form-control reason-textarea"
                        placeholder="Text Here"
                        required
                    ></textarea>
                </div>

                <!-- Dynamic Form Based on missingFields -->
                <div v-if="missingFields.length > 0" class="dynamic-form">
    <!-- 🟨 Status & Meta -->
                        <div 
                            v-if="['status_lead','available_date','branch','why_lost_lead','lost_reason'].some(f => missingFields.includes(f))" 
                            class="box-shadow"
                        >

                            <!-- Lead Status -->
                            <div v-if="missingFields.includes('status_lead')" class="form-group mb-3">
                                <label class="form-label">Lead Status</label>

                                <v-select
                                    v-if="targetStageOrder === 4 || (isConversion && targetStageOrder === 6)"
                                    v-model="formData.lead_status"
                                    :options="hotWarmLeadOptions"
                                    :reduce="opt => opt.value"
                                    label="text"
                                    placeholder="Not Selected"
                                    class="searchable-select"
                                />

                                <v-select
                                    v-else-if="targetStageOrder === 9"
                                    v-model="formData.lead_status"
                                    :options="leadPoolStatusOptions"
                                    :reduce="opt => opt.value"
                                    label="text"
                                    placeholder="Select Status"
                                    class="searchable-select"
                                />

                                <v-select
                                    v-else-if="targetStageOrder === 10"
                                    v-model="formData.lead_status"
                                    :options="unqualifiedStatusOptions"
                                    :reduce="opt => opt.value"
                                    label="text"
                                    placeholder="Select Status"
                                    class="searchable-select"
                                />

                                <v-select
                                    v-else
                                    v-model="formData.lead_status"
                                    :options="defaultLeadStatusOptions"
                                    :reduce="opt => opt.value"
                                    label="text"
                                    placeholder="Not Selected"
                                    class="searchable-select"
                                />
                            </div>

                            <!-- Available Date -->
                            <div v-if="missingFields.includes('available_date')" class="form-group mb-3">
                                <label class="form-label">Available Date</label>
                                <input type="date" v-model="formData.available_date" class="form-control">
                            </div>

                            <!-- Branch -->
                            <div v-if="missingFields.includes('branch')" class="form-group mb-3">
                                <label class="form-label">Branch</label>
                                <v-select
                                    v-model="formData.branch"
                                    :options="branchOptions"
                                    :reduce="opt => opt.value"
                                    label="text"
                                    placeholder="Select Branch"
                                    class="searchable-select"
                                />
                            </div>

                            <!-- Lost Reason -->
                            <div v-if="missingFields.includes('why_lost_lead') || missingFields.includes('lost_reason')" class="form-group mb-3">
                                <label class="form-label">Lost Reason</label>
                                <v-select
                                    v-model="formData.lost_reason"
                                    :options="lostReasonOptions"
                                    :reduce="opt => opt.value"
                                    label="text"
                                    placeholder="Select Lost Reason"
                                    class="searchable-select"
                                />
                            </div>
                        </div>
                        <!-- 🟦 Basic Info -->
                        <div 
                            v-if="['salutation','budget','area_id','property_type_id','bedrooms','purpose_buying'].some(f => missingFields.includes(f))" 
                            class="box-shadow"
                        >

                            <div v-if="missingFields.includes('salutation')" class="form-group mb-3">
                                <label class="form-label">Salutation</label>
                                <v-select
                                    v-model="formData.salutation"
                                    :options="salutationOptions"
                                    :reduce="opt => opt.value"
                                    label="text"
                                    placeholder="Not Selected"
                                    class="searchable-select"
                                />
                            </div>

                            <div v-if="missingFields.includes('budget')" class="form-group mb-3">
                                <label class="form-label">Budget (AED)</label>
                                <input type="number" v-model="formData.budget" placeholder="Enter Budget" class="form-control budget-input">
                            </div>

                            <div v-if="missingFields.includes('area_id')" class="form-group mb-3">
                                <label class="form-label">Location / Area</label>
                                <v-select
                                    v-model="formData.area_id"
                                    :options="areaOptions"
                                    :reduce="opt => opt.value"
                                    label="text"
                                    placeholder="Not Selected"
                                    class="searchable-select"
                                />
                            </div>

                            <div v-if="missingFields.includes('property_type_id')" class="form-group mb-3">
                                <label class="form-label">Property Type</label>
                                <v-select
                                    v-model="formData.property_type_id"
                                    :options="propertyTypeOptions"
                                    :reduce="opt => opt.value"
                                    label="text"
                                    placeholder="Not Selected"
                                    class="searchable-select"
                                />
                            </div>

                            <div v-if="missingFields.includes('bedrooms')" class="form-group mb-3">
                                <label class="form-label">How Many Bedrooms?</label>
                                <v-select
                                    v-model="formData.bedrooms"
                                    :options="bedroomOptions"
                                    :reduce="opt => opt.value"
                                    label="text"
                                    placeholder="Select Bedrooms"
                                    class="searchable-select"
                                />
                            </div>

                            <div v-if="missingFields.includes('purpose_buying')" class="form-group mb-3">
                                <label class="form-label">Purpose Of Purchase</label>
                                <v-select
                                    v-model="formData.purpose_buying"
                                    :options="purposeOptions"
                                    :reduce="opt => opt.value"
                                    label="text"
                                    placeholder="Not Selected"
                                    class="searchable-select"
                                />
                            </div>
                        </div>

                    

                    </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-light" @click="closeModal">Cancel</button>
                <button type="button" class="btn btn-primary" @click="handleSubmit" :disabled="isSubmitting">
                    <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-2"></span>
                    Submit
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import api from '@/plugins/axios'

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    leadId: {
        type: Number,
        default: null
    },
    targetStageId: {
        type: Number,
        default: null
    },
    targetStageName: {
        type: String,
        default: ''
    },
    targetStageOrder: {
        type: Number,
        default: null
    },
    missingFields: {
        type: Array,
        default: () => []
    },
    leadData: {
        type: Object,
        default: null
    },
    isConversion: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['update:modelValue', 'submit', 'closed'])

const visible = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
})

const isSubmitting = ref(false)
const areas = ref([])
const propertyTypes = ref([])

const showFields = computed(() => {
    return [3, 4, 5, 7, 8, 9, 10].includes(props.targetStageOrder)
})

const hotWarmLeadOptions = [
    { value: 'cold', text: 'Cold Lead' },
    { value: 'warm', text: 'Warm Lead' },
    { value: 'hot', text: 'Hot Lead' }
]

const leadPoolStatusOptions = [
    { value: 'no_answer', text: 'Lead Pool - No Answer' },
    { value: 'canceled', text: 'Lead Pool - Canceled' }
]

const unqualifiedStatusOptions = [
    { value: 'unqualified_not_interested', text: 'Unqualified - Not Interested' },
    { value: 'unqualified_wrong_contact', text: 'Unqualified - Wrong Contact Details' },
    { value: 'unqualified_job_seeker', text: 'Unqualified - Job Seeker' },
    { value: 'unqualified_other', text: 'Unqualified - Other' }
]

const defaultLeadStatusOptions = [
    { value: 'cold', text: 'Cold' },
    { value: 'warm', text: 'Warm' },
    { value: 'hot', text: 'Hot' }
]

const branchOptions = [
    { value: 'Abu Dhabi', text: 'Abu Dhabi' },
    { value: 'Dubai', text: 'Dubai' },
    { value: 'Sharjah', text: 'Sharjah' }
]

const lostReasonOptions = [
    { value: 'lost_by_other_company', text: 'Lost by Other Company' },
    { value: 'lost_by_our_company', text: 'Lost by Our Company' }
]

const salutationOptions = [
    { value: 'Mr.', text: 'Mr.' },
    { value: 'Ms.', text: 'Ms.' },
    { value: 'Mrs.', text: 'Mrs.' },
    { value: 'Dr.', text: 'Dr.' }
]

const purposeOptions = [
    { value: 'Investment', text: 'Investment' },
    { value: 'Residential', text: 'Residential' },
    { value: 'Commercial', text: 'Commercial' }
]

const bedroomOptions = computed(() => {
    const base = [{ value: 'Studio', text: 'Studio' }]
    const nums = Array.from({ length: 9 }, (_, i) => ({ value: i + 1, text: String(i + 1) }))
    return [...base, ...nums]
})

const areaOptions = computed(() => (areas.value || []).map(area => ({
    value: area.id,
    text: area.name
})))

const propertyTypeOptions = computed(() => (propertyTypes.value || []).map(type => ({
    value: type.id,
    text: type.name
})))

const formData = ref({
    reason: '',
    salutation: '',
    budget: '',
    currency: 'AED',
    area_id: '',
    property_type_id: '',
    bedrooms: '',
    purpose_buying: '',
    lead_source: '',
    lead_status: '',
    available_date: '',
    branch: '',
    lost_reason: ''
})

const loadLookupData = async () => {
    try {
        const [areasRes, typesRes] = await Promise.all([
            api.get('/listings/areas'),
            api.get('/listings/property-types')
        ])
        areas.value = areasRes.data.data || []
        propertyTypes.value = typesRes.data.data || []
    } catch (error) {
        console.error('Error loading lookup data:', error)
    }
}

const closeModal = () => {
    visible.value = false
    resetForm()
    emit('closed')
}

const resetForm = () => {
    formData.value = {
        reason: '',
        salutation: '',
        budget: '',
        currency: 'AED',
        area_id: '',
        property_type_id: '',
        bedrooms: '',
        purpose_buying: '',
        lead_source: '',
        lead_status: '',
        available_date: '',
        branch: '',
        lost_reason: ''
    }
    isSubmitting.value = false
}

const handleSubmit = async () => {
    // Validate reason
    if (!formData.value.reason.trim()) {
        $showNotification('Please provide a reason', 'warning')
        return
    }
    
    // Validate based on stage and missing fields
    for (const field of props.missingFields) {
        if (field === 'salutation' && !formData.value.salutation) {
            $showNotification('Please select salutation', 'warning')
            return
        }
        if (field === 'budget' && !formData.value.budget) {
            $showNotification('Please enter budget', 'warning')
            return
        }
        if (field === 'area_id' && !formData.value.area_id) {
            $showNotification('Please select area', 'warning')
            return
        }
        if (field === 'property_type_id' && !formData.value.property_type_id) {
            $showNotification('Please select property type', 'warning')
            return
        }
        if (field === 'bedrooms' && !formData.value.bedrooms) {
            $showNotification('Please select bedrooms', 'warning')
            return
        }
        if (field === 'purpose_buying' && !formData.value.purpose_buying) {
            $showNotification('Please select purpose', 'warning')
            return
        }
        if (field === 'status_lead') {
            // التحقق حسب المرحلة
            const targetOrder = props.targetStageOrder
            
            if (targetOrder === 4 || (props.isConversion && targetOrder === 6)) {
                if (!formData.value.lead_status) {
                    $showNotification('Please select lead status (cold/warm/hot)', 'warning')
                    return
                }
            } else if (targetOrder === 9) {
                if (!formData.value.lead_status) {
                    $showNotification('Please select lead pool status', 'warning')
                    return
                }
                // تحويل القيمة للصيغة الصحيحة للباك اند
                if (formData.value.lead_status === 'no_answer' || formData.value.lead_status === 'canceled') {
                    // هذه القيم صحيحة
                }
            } else if (targetOrder === 10) {
                if (!formData.value.lead_status) {
                    $showNotification('Please select unqualified status', 'warning')
                    return
                }
            }
        }
        if (field === 'available_date' && !formData.value.available_date) {
            $showNotification('Please select available date', 'warning')
            return
        }
        if (field === 'branch' && !formData.value.branch) {
            $showNotification('Please select branch', 'warning')
            return
        }
        if ((field === 'why_lost_lead' || field === 'lost_reason') && !formData.value.lost_reason) {
            $showNotification('Please select lost reason', 'warning')
            return
        }
    }
    
    isSubmitting.value = true
         let bedroomsValue = formData.value.bedrooms
        if (bedroomsValue === 'Studio' || bedroomsValue === 'studio') {
            bedroomsValue = 0
        }
    try {
        const submitData = {
            leadId: props.leadId,
            targetStageId: props.targetStageId,
            reason: formData.value.reason,
            salutation: formData.value.salutation,
            budget: formData.value.budget,
            area_id: formData.value.area_id,
            property_type_id: formData.value.property_type_id,
            bedrooms: bedroomsValue,
            purpose_buying: formData.value.purpose_buying,
            lead_source: formData.value.lead_source,
            lead_status: formData.value.lead_status, // سيرسل حسب المرحلة
            available_date: formData.value.available_date,
            branch: formData.value.branch,
            lost_reason: formData.value.lost_reason
        }
        
        // Remove empty fields
        Object.keys(submitData).forEach(key => {
            if (submitData[key] === null || submitData[key] === undefined || submitData[key] === '') {
                delete submitData[key]
            }
        })
        
        emit('submit', submitData)
        closeModal()
    } catch (error) {
        console.error('Error submitting:', error)
    } finally {
        isSubmitting.value = false
    }
}

watch(visible, (newVal) => {
    if (newVal) {
        loadLookupData()
        if (props.leadData) {
            formData.value.salutation = props.leadData.salutation || ''
            formData.value.budget = props.leadData.budget || ''
            formData.value.currency = props.leadData.currency || 'AED'
            formData.value.area_id = props.leadData.area_id || ''
            formData.value.property_type_id = props.leadData.property_type_id || ''
            formData.value.bedrooms = props.leadData.bedrooms || ''
            formData.value.purpose_buying = props.leadData.purpose_buying || ''
            formData.value.lead_source = props.leadData.lead_source || ''
            formData.value.lead_status = props.leadData.lead_status || ''
        }
    }
})

defineExpose({
    show: () => { visible.value = true },
    hide: () => { visible.value = false }
})
</script>

<style scoped>

/* نفس الـ styles الموجود مع إضافة الـ reason-textarea */
.stage-change-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000  !important;
    pointer-events: auto !important;
}

.stage-change-modal {
    background: #ffffff;
    border-radius: 10px;
    width: 90%;
    max-width: 560px;
    max-height: 92vh;
    overflow-y: auto;
    border: 1px solid #e8edf3;
    box-shadow: 0 18px 55px rgba(15, 23, 42, 0.18);
    pointer-events: auto !important;
    position: relative;
    z-index: 2001 !important;
}

.stage-change-modal * {
    pointer-events: auto !important;
}

/* إزالة أي تأثيرات قد تمنع الكتابة */
.form-control,
.form-select,
.reason-textarea {
    pointer-events: auto !important;
    background: white !important;
    user-select: text !important;
}

.stage-change-modal.modal-wide {
    max-width: 760px;
}

.modal-header {
    padding: 0.7rem 1rem;
    border-bottom: 1px solid #edf1f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(180deg, #fcfdff 0%, #ffffff 100%);
}

.modal-title {
    margin: 0;
    font-size: 0.95rem !important;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: 0.1px;
}

.btn-close {
    background: none;
    border: none;
    font-size: 1rem;
    cursor: pointer;
    color: #6b7280;
}

.modal-body {
    padding: 0.8rem 1rem;
    background: #fbfcfe;
}

.modal-footer {
    padding: 0.7rem 1rem;
    border-top: 1px solid #edf1f6;
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    background: #ffffff;
}

.reason-textarea {
    width: 100%;
    padding: 0.5rem 0.625rem;
    border: 1px solid #d6dee8;
    border-radius: 7px;
    font-size: 0.75rem;
    min-height: 70px;
    resize: vertical;
    color: #0f172a;
    background: #ffffff;
}

.reason-textarea:focus {
    outline: none;
    border-color: #0ea5e9;
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.12);
}

.form-group {
    margin-bottom: 0.55rem;
}

.form-label {
    display: block;
    margin-bottom: 0.25rem;
    font-weight: 600;
    font-size: 0.74rem;
    color: #1f2937;
}

.form-control,
.form-select {
    width: 100%;
    padding: 0.4rem 0.55rem;
    border: 1px solid #d6dee8;
    border-radius: 7px;
    font-size: 0.75rem;
    transition: border-color 0.15s ease-in-out;
    min-height: 34px;
    color: #0f172a;
    background: #ffffff;
}

.form-control::placeholder {
    font-size: 0.75rem;
    color: #9ca3af;
}

.form-control:focus,
.form-select:focus {
    outline: none;
    border-color: #0ea5e9;
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.12);
}

.input-group {
    display: flex;
    gap: 0.5rem;
}

.input-group .form-control {
    flex: 1;
}

.input-group .form-select {
    width: auto;
    min-width: 80px;
}

.text-danger {
    color: #ef4444;
}

.btn {
    padding: 0.25rem 0.7rem;
    border-radius: 50px;
    height: 36px;
    width: 84px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 12px;
    border: 1px solid transparent;
}
.modal-footer{
        justify-content: center;

}
.btn-light {
    background-color: #f3f5f8;
    border-color: #e3e8ef;
    color: #0f172a;
}

.btn-light:hover {
    background-color: #e7ebf1;
}

.btn-primary {
    background-color: #0b1220;
    border-color: #0b1220;
    color: #FFFFFF;
}

.btn-primary:hover {
    background-color: #111827;
    border-color: #111827;
    color: #fff;
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.spinner-border {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    border: 0.2em solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: spinner-border 0.75s linear infinite;
}

@keyframes spinner-border {
    to {
        transform: rotate(360deg);
    }
}
.box-shadow {
   
    background-color: #ffffff;
    border: 1px solid #edf1f6;
    padding: 10px;
    margin: 6px 0px;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(15, 23, 42, 0.05);
}

/* Two fields per line in dynamic form */
.dynamic-form .box-shadow {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px 10px;
}

.dynamic-form .box-shadow .form-group {
    margin-bottom: 0 !important;
}

:deep(.searchable-select .vs__dropdown-toggle) {
    min-height: 34px;
    border: 1px solid #d6dee8;
    border-radius: 7px;
    padding: 0 8px;
    background: #fff;
}

:deep(.searchable-select .vs__selected-options) {
    align-items: center;
    min-height: 32px;
}

:deep(.searchable-select .vs__selected),
:deep(.searchable-select .vs__search),
:deep(.searchable-select .vs__search::placeholder) {
    font-size: 0.75rem;
    color: #111827;
}

:deep(.searchable-select .vs__actions) {
    padding: 0 4px 0 8px;
}

:deep(.searchable-select .vs__dropdown-menu) {
    font-size: 0.75rem;
    z-index: 3000;
    border: 1px solid #e2e8f0;
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.14);
    border-radius: 8px;
}

:deep(.searchable-select .vs__dropdown-option) {
    color: #111827 !important;
    background: #fff !important;
}

:deep(.searchable-select .vs__dropdown-option--highlight) {
    color: #111827 !important;
    background: #f3f4f6 !important;
}

:deep(.searchable-select .vs__dropdown-option--selected) {
    color: #111827 !important;
    background: #e5e7eb !important;
}

.budget-input::placeholder {
    font-size: 0.62rem !important;
    color: #9ca3af !important;
}

/* Smooth, clean scroll for long content */
.stage-change-modal::-webkit-scrollbar {
    width: 8px;
}

.stage-change-modal::-webkit-scrollbar-thumb {
    background: #cfd8e3;
    border-radius: 999px;
}

@media (max-width: 768px) {
    .stage-change-modal,
    .stage-change-modal.modal-wide {
        max-width: 96vw;
    }

    .dynamic-form .box-shadow {
        grid-template-columns: 1fr;
    }
}
</style>