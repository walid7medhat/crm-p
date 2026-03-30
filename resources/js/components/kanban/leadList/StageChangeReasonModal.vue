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

                                <select v-if="targetStageOrder === 4 || (isConversion && targetStageOrder === 6)" v-model="formData.lead_status" class="form-select">
                                    <option value="">Not Selected</option>
                                    <option value="cold">Cold Lead</option>
                                    <option value="warm">Warm Lead</option>
                                    <option value="hot">Hot Lead</option>
                                </select>

                                <select v-else-if="targetStageOrder === 9" v-model="formData.lead_status" class="form-select">
                                    <option value="">Select Status</option>
                                    <option value="no_answer">Lead Pool - No Answer</option>
                                    <option value="canceled">Lead Pool - Canceled</option>
                                </select>

                                <select v-else-if="targetStageOrder === 10" v-model="formData.lead_status" class="form-select">
                                    <option value="">Select Status</option>
                                    <option value="unqualified_not_interested">Unqualified - Not Interested</option>
                                    <option value="unqualified_wrong_contact">Unqualified - Wrong Contact Details</option>
                                    <option value="unqualified_job_seeker">Unqualified - Job Seeker</option>
                                    <option value="unqualified_other">Unqualified - Other</option>
                                </select>

                                <select v-else v-model="formData.lead_status" class="form-select">
                                    <option value="">Not Selected</option>
                                    <option value="cold">Cold</option>
                                    <option value="warm">Warm</option>
                                    <option value="hot">Hot</option>
                                </select>
                            </div>

                            <!-- Available Date -->
                            <div v-if="missingFields.includes('available_date')" class="form-group mb-3">
                                <label class="form-label">Available Date</label>
                                <input type="date" v-model="formData.available_date" class="form-control">
                            </div>

                            <!-- Branch -->
                            <div v-if="missingFields.includes('branch')" class="form-group mb-3">
                                <label class="form-label">Branch</label>
                                <select v-model="formData.branch" class="form-select">
                                    <option value="">Select Branch</option>
                                    <option value="Abu Dhabi">Abu Dhabi</option>
                                    <option value="Dubai">Dubai</option>
                                    <option value="Sharjah">Sharjah</option>
                                </select>
                            </div>

                            <!-- Lost Reason -->
                            <div v-if="missingFields.includes('why_lost_lead') || missingFields.includes('lost_reason')" class="form-group mb-3">
                                <label class="form-label">Lost Reason</label>
                                <select v-model="formData.lost_reason" class="form-select">
                                    <option value="">Select Lost Reason</option>
                                    <option value="lost_by_other_company">Lost by Other Company</option>
                                    <option value="lost_by_our_company">Lost by Our Company</option>
                                </select>
                            </div>
                        </div>
                        <!-- 🟦 Basic Info -->
                        <div 
                            v-if="['salutation','budget','area_id','property_type_id','bedrooms','purpose_buying'].some(f => missingFields.includes(f))" 
                            class="box-shadow"
                        >

                            <div v-if="missingFields.includes('salutation')" class="form-group mb-3">
                                <label class="form-label">Salutation</label>
                                <select v-model="formData.salutation" class="form-select">
                                    <option value="">Not Selected</option>
                                    <option value="Mr.">Mr.</option>
                                    <option value="Ms.">Ms.</option>
                                    <option value="Mrs.">Mrs.</option>
                                    <option value="Dr.">Dr.</option>
                                </select>
                            </div>

                            <div v-if="missingFields.includes('budget')" class="form-group mb-3">
                                <label class="form-label">Budget</label>
                                <input type="number" v-model="formData.budget" placeholder="Enter Budget" class="form-control">
                            </div>

                            <div v-if="missingFields.includes('area_id')" class="form-group mb-3">
                                <label class="form-label">Location / Area</label>
                                <select v-model="formData.area_id" class="form-select">
                                    <option value="">Not Selected</option>
                                    <option v-for="area in areas" :key="area.id" :value="area.id">
                                        {{ area.name }}
                                    </option>
                                </select>
                            </div>

                            <div v-if="missingFields.includes('property_type_id')" class="form-group mb-3">
                                <label class="form-label">Property Type</label>
                                <select v-model="formData.property_type_id" class="form-select">
                                    <option value="">Not Selected</option>
                                    <option v-for="type in propertyTypes" :key="type.id" :value="type.id">
                                        {{ type.name }}
                                    </option>
                                </select>
                            </div>

                            <div v-if="missingFields.includes('bedrooms')" class="form-group mb-3">
                                <label class="form-label">How Many Bedrooms?</label>
                                <select v-model="formData.bedrooms" class="form-select">
                                    <option value="">Select Bedrooms</option>
                                    <option value="Studio">Studio</option>
                                    <option v-for="n in 9" :key="n" :value="n">{{ n }}</option>
                                </select>
                            </div>

                            <div v-if="missingFields.includes('purpose_buying')" class="form-group mb-3">
                                <label class="form-label">Purpose Of Purchase</label>
                                <select v-model="formData.purpose_buying" class="form-select">
                                    <option value="">Not Selected</option>
                                    <option value="Investment">Investment</option>
                                    <option value="Residential">Residential</option>
                                    <option value="Commercial">Commercial</option>
                                </select>
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
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
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
    max-width: 600px;
}

.modal-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    margin: 0;
    font-size: 1.1rem !important; 
    font-weight: 600;
    color: #1f2937;
}

.btn-close {
    background: none;
    border: none;
    font-size: 1.25rem;
    cursor: pointer;
    color: #6b7280;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.reason-textarea {
    width: 100%;
    padding: 0.625rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.875rem;
    resize: vertical;
}

.reason-textarea:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
}

.form-group {
    margin-bottom: 1rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    font-size: 0.85rem;
    color: #374151;
}

.form-control,
.form-select {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.875rem;
    transition: border-color 0.15s ease-in-out;
}

.form-control:focus,
.form-select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
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
    padding: 0.5rem 1rem;
    border-radius: 50px;
    height: 44px;
    width: 90px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 14px;
}
.modal-footer{
        justify-content: center;

}
.btn-light {
    background-color: #F4F4F4;
    border: 1px solid #F4F4F4;
    color: #000;
}

.btn-light:hover {
    background-color: #e5e7eb;
}

.btn-primary {
    background-color: #000;
    border: 1px solid #000;
    color: #FFFFFF;
}

.btn-primary:hover {
    background-color: #F4F4F4;
    color: #000;
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
   
    background-color: #fff;
    border: 1px solid #F3F3F3;
    padding: 20px;
    margin: 10px 0px;
    border-radius: 13px;
    box-shadow: 0 2px 3px rgba(0, 0, 0, 0.06);
}
</style>