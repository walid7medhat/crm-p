<!-- components/leads/StageChangeReasonModal.vue -->
<template>
    <div class="modal fade" id="stageChangeReasonModal" tabindex="-1" aria-labelledby="stageChangeReasonModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title text-xl mb-0" id="stageChangeReasonModalLabel">
                        Change Stage Reason
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="stageChangeReasonForm" @submit.prevent="submitReason">
                        <!-- Reason -->
                        <div class="mb-3">
                            <label for="reason" class="form-label fw-semibold text-primary-light text-sm mb-8">
                                Please provide a reason for moving this lead <span class="text-danger">*</span>
                            </label>
                            <textarea 
                                class="form-control" 
                                id="reason" 
                                v-model="reason" 
                                rows="4" 
                                placeholder="Enter reason for stage change..."
                                required
                            ></textarea>
                        </div>
                        
                        <div class="mb-3" v-if="error">
                            <div class="alert alert-danger py-2">
                                {{ error }}
                            </div>
                        </div>

                        <!-- Area -->
                        <div class="info-group mb-3" v-if="props.missingFields.includes('area_id')">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Area</label>
                                  <v-select
                                        v-model="form.area_id"
                                        :options="areas"
                                        :reduce="area => area.id"
                                        :disabled="isLoadingAreas"
                                        label="name"
                                        placeholder="Select area"
                                        class="custom-v-select"
                                    >
                                        <template #open-indicator="{ attributes }">
                                            <i v-bind="attributes" class="ri-arrow-down-s-line dropdown-icon"></i>
                                        </template>
                                
                                        <template #option="option">
                                            <div class="location-option">
                                                <i class="ri-map-pin-line location-option-icon"></i>
                                                <div class="location-option-text">
                                                    <span class="location-option-name">
                                                        {{ locationFirstLine(option) }}
                                                    </span>
                                                    <span class="location-option-subtitle">
                                                        {{ locationSecondLine(option) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </template>
                                
                                        <template #selected-option="option">
                                            <div v-if="option" class="location-selected">
                                                <span class="location-selected-name">
                                                    {{ locationFirstLine(option) }}
                                                </span>
                                                <span class="location-selected-subtitle">
                                                    {{ locationSecondLine(option) }}
                                                </span>
                                            </div>
                                        </template>
                                
                                        <template #no-options>
                                            <div class="text-center p-2">
                                                {{ isLoadingAreas ? 'Loading areas...' : 'No areas found' }}
                                            </div>
                                        </template>
                                    </v-select>
                        </div>
                        
                        <!-- Property Type -->
                        <div class="info-group mb-3" v-if="props.missingFields.includes('property_type_id')">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Property Type</label>
                            <v-select
                                v-model="form.property_type_id"
                                :options="propertyTypeOptions"
                                :reduce="item => item.id"
                                label="text"
                                placeholder="Select Property Type"
                                class="custom-v-select"
                            />
                        </div>
                        
                        <!-- Budget -->
                        <div class="info-group mb-3" v-if="props.missingFields.includes('budget')">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Budget</label>
                            <b-form-input v-model="form.budget" type="number" placeholder="Enter Budget" class="custom-input" />
                        </div>
                        
                        <!-- Lead Source -->
                        <div class="info-group mb-3" v-if="props.missingFields.includes('lead_source')">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Source</label>
                            <v-select
                                v-model="form.lead_source"
                                :options="sourceOptions"
                                :reduce="option => option.value"
                                label="text"
                                placeholder="Select Source"
                                class="custom-v-select"
                            />
                        </div>
                        
                        <!-- Purpose Buying -->
                        <div class="info-group mb-3" v-if="props.missingFields.includes('purpose_buying')">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Purpose Of Purchase</label>
                            <v-select
                                v-model="form.purpose_buying"
                                :options="purposeOptions"
                                :reduce="option => option.value"
                                label="text"
                                placeholder="Select Purpose"
                                class="custom-v-select"
                            />
                        </div>
                        
                        <!-- Bedrooms -->
                        <div class="info-group mb-3" v-if="props.missingFields.includes('bedrooms')">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Bedrooms</label>
                            <v-select
                                v-model="form.bedrooms"
                                :options="bedroomOptions"
                                :reduce="option => option.value"
                                label="text"
                                placeholder="Select Bedrooms"
                                class="custom-v-select"
                            />
                        </div>
                    </form>
                </div>
                
                <div class="modal-footer justify-content-center gap-3">
                    <button 
                        type="button"
                        class="border border-danger-600 bg-hover-danger-200 text-danger-600 text-md px-50 py-11 radius-8"
                        data-bs-dismiss="modal"
                        @click="cancel"
                    >
                        Cancel
                    </button>
                    <button 
                        type="button" 
                        class="btn btn-primary border border-primary-600 text-md px-28 py-12 radius-8"
                        @click="submitReason"
                        :disabled="submitting"
                    >
                        <span v-if="submitting" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        {{ submitting ? 'Submitting...' : 'Submit' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import * as bootstrap from 'bootstrap'
import { BModal, BFormInput, BFormSelect, BFormTextarea } from 'bootstrap-vue-3'

import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import api from '@/plugins/axios'

// تعريف props
const props = defineProps({
    leadId: { type: [Number, String], required: true },
    targetStageId: { type: [Number, String], required: true },
    targetStageName: { type: String, default: '' },
    leadData: { type: Object, default: () => ({}) },
    missingFields: { type: Array, default: () => [] }
})

// تعريف emits
const emit = defineEmits(['submit', 'cancel', 'closed'])

// ================ المتغيرات الأساسية ================
const reason = ref('')
const submitting = ref(false)
const error = ref(null)
let modalInstance = null

// ================ متغيرات الفورم ================
const form = reactive({
    area_id: null,
    property_type_id: null,
    budget: null,
    lead_source: null,
    purpose_buying: null,
    bedrooms: null
})

// ================ متغيرات الـ API ================
const sources = ref([])
const missingFieldsForLead = ref([])
const areas = ref([])
const propertyTypeOptions = ref([])
const sourceOptions = ref([])

const isLoadingAreas = ref(false)
const isLoadingPropertyTypes = ref(false)
const isLoadingSources = ref(false)

// ================ الـ Options ================
const purposeOptions = ref([
    { value: "Self Use", text: "Self Use" },
    { value: "Investment", text: "Investment" },
    { value: "Rental Income", text: "Rental Income" },
    { value: "Future Residence", text: "Future Residence" },
    { value: "Business Use", text: "Business Use" },
    { value: "Commercial Investment", text: "Commercial Investment" },
    { value: "Holiday / Weekend Home", text: "Holiday / Weekend Home" },
    { value: "Land Banking", text: "Land Banking" },
    { value: "Resale", text: "Resale" },
    { value: "Portfolio Expansion", text: "Portfolio Expansion" }
])

const bedroomOptions = ref([
    { value: '0', text: 'Studio' },
    { value: 1, text: '1' },
    { value: 2, text: '2' },
    { value: 3, text: '3' },
    { value: 4, text: '4' },
    { value: 5, text: '5' },
    { value: 6, text: '6' },
    { value: 7, text: '7' },
    { value: 8, text: '8' },
    { value: 9, text: '9' },
])

// ================ دوال مساعدة ================
const locationFirstLine = (area) => {
    return area?.name || ''
}

const locationSecondLine = (area) => {
    return area?.parent || ''
}

// ================ دوال جلب البيانات ================
const fetchSources = async () => {
    try {
        isLoadingSources.value = true
        const response = await api.get('/sources')
        if (response.data && (response.data.data || response.data)) {
            const data = response.data.data || response.data
            sourceOptions.value = [
                // { value: null, text: 'Select Source' },
                ...data.map(source => ({
                    value: source.name,
                    text: source.name
                }))
            ]
        }
    } catch (error) {
        console.error('Error fetching sources:', error)
    } finally {
        isLoadingSources.value = false
    }
}

const fetchAreas = async () => {
    try {
        isLoadingAreas.value = true;

        const response = await api.get("/listings/areas/?has_listings=true");
        const areasData = response.data.data || response.data;

        areas.value = areasData.map(area => ({
            id: area.id,
            name: area.name || area.title,
            parent: area.area_parents_title || null
        }));

    } catch (error) {
        console.error("❌ Error fetching areas:", error.response || error);
    } finally {
        isLoadingAreas.value = false;
    }
};

const fetchPropertyTypes = async () => {
    try {
        const res = await api.get('/listings/property-types')
        const data = res.data.data || res.data

        propertyTypeOptions.value = data.map(item => ({
            id:item.id,
            value: item.id,
            text: item.name
        }))
    } catch (e) {
        console.error('Property types error', e)
    }
}

// ================ دوال المودال ================
const show = () => {
    reason.value = ''
    error.value = null
    submitting.value = false

    // تعبئة القيم الحالية للـ lead
    if (props.leadData) {
        form.area_id = props.leadData.area_id ?? null
        form.property_type_id = props.leadData.property_type_id ?? null
        form.budget = props.leadData.budget ?? null
        form.lead_source = props.leadData.lead_source ?? null
        form.purpose_buying = props.leadData.purpose_buying ?? null
        form.bedrooms = props.leadData.bedrooms ?? null
    }

    // إظهار المودال
    const modalElement = document.getElementById('stageChangeReasonModal')
    if (modalElement) {
        // إزالة أي مودال سابق
        if (modalInstance) {
            modalInstance.dispose()
        }
        
        modalInstance = new bootstrap.Modal(modalElement, {
            backdrop: 'static',
            keyboard: false
        })
        
        modalInstance.show()

        // Focus على textarea بعد ظهور المودال
        modalElement.addEventListener('shown.bs.modal', () => {
            const textarea = document.getElementById('reason')
            if (textarea) textarea.focus()
        }, { once: true })
        
        // تنظيف عند إغلاق المودال
        modalElement.addEventListener('hidden.bs.modal', () => {
            emit('closed')
        }, { once: true })
    }
}

// إخفاء المودال
const hide = () => {
    if (modalInstance) {
        modalInstance.hide()
    }
}

// تقديم السبب
const submitReason = async () => {
    if (!reason.value?.trim()) {
        error.value = 'Reason is required'
        return
    }

    submitting.value = true
    error.value = null

    try {
        // تجهيز البيانات للإرسال
        const payload = { 
            leadId: props.leadId, 
            targetStageId: props.targetStageId, 
            reason: reason.value.trim() 
        }
        
        // إضافة الحقول المطلوبة فقط
        props.missingFields.forEach(field => {
            if (form[field] !== null && form[field] !== undefined) {
                payload[field] = form[field]
            }
        })

        console.log('Emitting payload from modal:', payload) // للتجربة

        // إرسال البيانات
        await emit('submit', payload)
        hide()
    } catch (err) {
        error.value = err.message || 'Failed to submit reason'
    } finally {
        submitting.value = false
    }
}

// إلغاء
const cancel = () => {
    emit('cancel')
    hide()
}

// جلب البيانات عند تحميل المكون
onMounted(() => {
    fetchSources()
    fetchAreas()
    fetchPropertyTypes()
})

// تعريف الدوال المتاحة للـ template
defineExpose({ 
    show,
    // للاختبار
    form,
    reason,
    areas,
    propertyTypeOptions,
    sourceOptions,
    purposeOptions,
    bedroomOptions,
    isLoadingAreas,
    isLoadingPropertyTypes,
    isLoadingSources,
    locationFirstLine,
    locationSecondLine
})
</script>
<style scoped>
        
    /* Location: same height & padding as Price/Size range */
:deep(.location-select .vs__dropdown-toggle) {
  min-height: 30px !important;
  padding: 0px 8px !important;
  align-items: center !important;
  font-size: 0.65rem !important;
}

:deep(.location-select .vs__selected) {
  padding: 0 !important;
  margin: 0 !important;
  display: flex !important;
  align-items: center !important;
}

:deep(.location-select .vs__placeholder) {
  margin: 0 !important;
  position: static !important;
  width: 100%;
  text-align: center;
}

:deep(.location-select .vs__selected) {
  width: 100%;
  text-align: center;
}

:deep(.location-select .vs__selected .location-selected) {
  text-align: left;
}

/* Location selected value in 2 lines (when using selected-option slot) */
.location-selected {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  line-height: 1.2;
}

.location-selected-name {
  font-weight: 600;
  font-size: 0.75rem;
  color: #01062d;
}

.location-selected-subtitle {
  font-size: 0.7rem;
  color: #64748b;
}

/* Location dropdown options: 2 lines with icon (like image) */
.location-option {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 4px 0;
  min-height: 40px;
}

.location-option-icon {
  font-size: 1.1rem;
  color: #64748b;
  flex-shrink: 0;
  margin-top: 2px;
}

.location-option-text {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.location-option-name {
  font-weight: 600;
  font-size: 0.75rem;
  color: #01062d;
  line-height: 1.2;
}

.location-option-subtitle {
  font-size: 0.65rem;
  color: #64748b;
  line-height: 1.2;
}

/* Location dropdown list: wider */
:deep(.location-select + .vs__dropdown-menu),
:deep(.location-select .vs__dropdown-menu) {
  min-width: 320px !important;
  width: 100% !important;
  max-width: 400px;
}

/* All top-row inputs: same height, padding, font as Price/Size range */
.unified-input-inline {
     min-height: 30px !important;
    padding: 0px !important;
    font-size: .65rem !important;
}

:deep(.unified-input-inline.vs--single .vs__dropdown-toggle),
:deep(.form-group-inline .custom-select.vs--single .vs__dropdown-toggle) {
  min-height: 30px !important;
  padding: 2px 8px !important;
  font-size: 0.65rem !important;
}

.main-search-row-single :deep(.vs__selected),
.main-search-row-single :deep(.vs__search),
.main-search-row-single :deep(.vs__placeholder) {
  font-size: 0.65rem !important;
}

.unified-btn-inline {
  min-height: 30px !important;
  padding: 2px 8px !important;
  font-size: 0.65rem !important;
}
</style>