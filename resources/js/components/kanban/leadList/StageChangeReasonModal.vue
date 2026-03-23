<!-- components/leads/StageChangeReasonModal.vue -->
<template>
    <div class="modal fade" id="stageChangeReasonModal" tabindex="-1" aria-labelledby="stageChangeReasonModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg stage-reason-dialog">
            <div class="modal-content stage-reason-modal">
                <div class="modal-header stage-reason-header">
                    <h6 class="modal-title stage-reason-title mb-0" id="stageChangeReasonModalLabel">
                        Change Stage Reason
                    </h6>
                    <button type="button" class="btn-close stage-reason-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body stage-reason-body">
                    <form id="stageChangeReasonForm" @submit.prevent="submitReason">
                        <div class="mb-2">
                            <label for="reason" class="form-label compact-label mb-2">
                                Please provide a reason for moving this lead <span class="text-danger">*</span>
                            </label>
                            <textarea
                                id="reason"
                                v-model="reason"
                                rows="3"
                                class="form-control compact-control reason-textarea"
                                placeholder="Enter reason for stage change..."
                                required
                            ></textarea>
                        </div>

                        <div class="mb-3" v-if="error">
                            <div class="alert alert-danger py-2 compact-alert">
                                {{ error }}
                            </div>
                        </div>

                        <section
                            v-if="props.missingFields.includes('area_id') || props.missingFields.includes('property_type_id') || props.missingFields.includes('lead_source') || props.missingFields.includes('purpose_buying')"
                            class="required-section"
                        >
                            <div class="required-header">
                                <div class="required-title">Required Selects</div>
                                <div class="required-subtitle">Please select these values</div>
                            </div>

                            <div class="required-selects-grid">
                                <div class="info-group compact-group" v-if="props.missingFields.includes('area_id')">
                                    <label class="form-label compact-label mb-1">Area</label>
                                    <v-select
                                        v-model="form.area_id"
                                        :options="areas"
                                        :reduce="area => area.id"
                                        :disabled="isLoadingAreas"
                                        label="name"
                                        placeholder="Select area"
                                        class="compact-v-select"
                                    >
                                        <template #open-indicator="{ attributes }">
                                            <i v-bind="attributes" class="ri-arrow-down-s-line dropdown-icon"></i>
                                        </template>
                                        <template #option="option">
                                            <div class="location-option">
                                                <i class="ri-map-pin-line location-option-icon"></i>
                                                <div class="location-option-text">
                                                    <span class="location-option-name">{{ locationFirstLine(option) }}</span>
                                                    <span class="location-option-subtitle">{{ locationSecondLine(option) }}</span>
                                                </div>
                                            </div>
                                        </template>
                                        <template #selected-option="option">
                                            <div v-if="option" class="location-selected">
                                                <span class="location-selected-name">{{ locationFirstLine(option) }}</span>
                                                <span class="location-selected-subtitle">{{ locationSecondLine(option) }}</span>
                                            </div>
                                        </template>
                                        <template #no-options>
                                            <div class="text-center p-2">
                                                {{ isLoadingAreas ? 'Loading areas...' : 'No areas found' }}
                                            </div>
                                        </template>
                                    </v-select>
                                </div>

                                <div class="info-group compact-group" v-if="props.missingFields.includes('property_type_id')">
                                    <label class="form-label compact-label mb-1">Property Type</label>
                                    <v-select
                                        v-model="form.property_type_id"
                                        :options="propertyTypeOptions"
                                        :reduce="item => item.id"
                                        label="text"
                                        placeholder="Select Property Type"
                                        class="compact-v-select"
                                    />
                                </div>

                                <div class="info-group compact-group" v-if="props.missingFields.includes('lead_source')">
                                    <label class="form-label compact-label mb-1">Source</label>
                                    <v-select
                                        v-model="form.lead_source"
                                        :options="sourceOptions"
                                        :reduce="option => option.value"
                                        label="text"
                                        placeholder="Select Source"
                                        class="compact-v-select"
                                    />
                                </div>

                                <div class="info-group compact-group" v-if="props.missingFields.includes('purpose_buying')">
                                    <label class="form-label compact-label mb-1">Purpose Of Purchase</label>
                                    <v-select
                                        v-model="form.purpose_buying"
                                        :options="purposeOptions"
                                        :reduce="option => option.value"
                                        label="text"
                                        placeholder="Select Purpose"
                                        class="compact-v-select"
                                    />
                                </div>
                            </div>
                        </section>

                        <section
                            v-if="props.missingFields.includes('budget') || props.missingFields.includes('bedrooms')"
                            class="required-section required-section-secondary"
                        >
                            <div class="required-header">
                                <div class="required-title">Additional Required</div>
                                <div class="required-subtitle">Complete before submitting</div>
                            </div>

                            <div class="required-grid">
                                <div class="info-group compact-group" v-if="props.missingFields.includes('budget')">
                                    <label class="form-label compact-label mb-1">Budget</label>
                                    <b-form-input v-model="form.budget" type="number" placeholder="Enter Budget" class="compact-control compact-input" />
                                </div>

                                <div class="info-group compact-group" v-if="props.missingFields.includes('bedrooms')">
                                    <label class="form-label compact-label mb-1">Bedrooms</label>
                                    <v-select
                                        v-model="form.bedrooms"
                                        :options="bedroomOptions"
                                        :reduce="option => option.value"
                                        label="text"
                                        placeholder="Select Bedrooms"
                                        class="compact-v-select"
                                    />
                                </div>
                            </div>
                        </section>
                    </form>
                </div>

                <div class="modal-footer stage-reason-footer justify-content-center gap-2">
                    <button
                        type="button"
                        class="stage-btn stage-btn-cancel"
                        data-bs-dismiss="modal"
                        @click="cancel"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="stage-btn stage-btn-submit"
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
import { BFormInput } from 'bootstrap-vue-3'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import api from '@/plugins/axios'

const props = defineProps({
    leadId: { type: [Number, String], required: true },
    targetStageId: { type: [Number, String], required: true },
    targetStageName: { type: String, default: '' },
    leadData: { type: Object, default: () => ({}) },
    missingFields: { type: Array, default: () => [] }
})

const emit = defineEmits(['submit', 'cancel', 'closed'])
const reason = ref('')
const submitting = ref(false)
const error = ref(null)
let modalInstance = null

const form = reactive({
    area_id: null,
    property_type_id: null,
    budget: null,
    lead_source: null,
    purpose_buying: null,
    bedrooms: null
})

const areas = ref([])
const propertyTypeOptions = ref([])
const sourceOptions = ref([])
const isLoadingAreas = ref(false)
const isLoadingSources = ref(false)

const purposeOptions = ref([
    { value: 'Self Use', text: 'Self Use' },
    { value: 'Investment', text: 'Investment' },
    { value: 'Rental Income', text: 'Rental Income' },
    { value: 'Future Residence', text: 'Future Residence' },
    { value: 'Business Use', text: 'Business Use' },
    { value: 'Commercial Investment', text: 'Commercial Investment' },
    { value: 'Holiday / Weekend Home', text: 'Holiday / Weekend Home' },
    { value: 'Land Banking', text: 'Land Banking' },
    { value: 'Resale', text: 'Resale' },
    { value: 'Portfolio Expansion', text: 'Portfolio Expansion' }
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
    { value: 9, text: '9' }
])

const locationFirstLine = (area) => area?.name || ''
const locationSecondLine = (area) => area?.parent || ''

const fetchSources = async () => {
    try {
        isLoadingSources.value = true
        const response = await api.get('/sources')
        const data = response.data?.data || response.data || []
        sourceOptions.value = data.map((source) => ({
            value: source.name,
            text: source.name
        }))
    } catch (err) {
        console.error('Error fetching sources:', err)
    } finally {
        isLoadingSources.value = false
    }
}

const fetchAreas = async () => {
    try {
        isLoadingAreas.value = true
        const response = await api.get('/listings/areas/?has_listings=true')
        const areasData = response.data?.data || response.data || []
        areas.value = areasData.map((area) => ({
            id: area.id,
            name: area.name || area.title,
            parent: area.area_parents_title || null
        }))
    } catch (err) {
        console.error('Error fetching areas:', err.response || err)
    } finally {
        isLoadingAreas.value = false
    }
}

const fetchPropertyTypes = async () => {
    try {
        const response = await api.get('/listings/property-types')
        const data = response.data?.data || response.data || []
        propertyTypeOptions.value = data.map((item) => ({
            id: item.id,
            value: item.id,
            text: item.name
        }))
    } catch (err) {
        console.error('Property types error:', err)
    }
}

const show = () => {
    reason.value = ''
    error.value = null
    submitting.value = false

    if (props.leadData) {
        form.area_id = props.leadData.area_id ?? null
        form.property_type_id = props.leadData.property_type_id ?? null
        form.budget = props.leadData.budget ?? null
        form.lead_source = props.leadData.lead_source ?? null
        form.purpose_buying = props.leadData.purpose_buying ?? null
        form.bedrooms = props.leadData.bedrooms ?? null
    }

    const modalElement = document.getElementById('stageChangeReasonModal')
    if (!modalElement) return

    if (modalInstance) modalInstance.dispose()
    modalInstance = new bootstrap.Modal(modalElement, { backdrop: 'static', keyboard: false })
    modalInstance.show()

    modalElement.addEventListener('shown.bs.modal', () => {
        const textarea = document.getElementById('reason')
        if (textarea) textarea.focus()
    }, { once: true })

    modalElement.addEventListener('hidden.bs.modal', () => {
        emit('closed')
    }, { once: true })
}

const hide = () => {
    if (modalInstance) modalInstance.hide()
}

const submitReason = async () => {
    if (!reason.value?.trim()) {
        error.value = 'Reason is required'
        return
    }

    submitting.value = true
    error.value = null
    try {
        const payload = {
            leadId: props.leadId,
            targetStageId: props.targetStageId,
            reason: reason.value.trim()
        }
        props.missingFields.forEach((field) => {
            if (form[field] !== null && form[field] !== undefined) {
                payload[field] = form[field]
            }
        })
        await emit('submit', payload)
        hide()
    } catch (err) {
        error.value = err.message || 'Failed to submit reason'
    } finally {
        submitting.value = false
    }
}

const cancel = () => {
    emit('cancel')
    hide()
}

onMounted(() => {
    fetchSources()
    fetchAreas()
    fetchPropertyTypes()
})

defineExpose({
    show,
    form,
    reason,
    areas,
    propertyTypeOptions,
    sourceOptions,
    purposeOptions,
    bedroomOptions,
    isLoadingAreas,
    isLoadingSources,
    locationFirstLine,
    locationSecondLine
})
</script>

<style scoped>
.stage-reason-modal {
  border: 0;
  border-radius: 14px;
  box-shadow: 0 16px 44px rgba(0, 0, 0, 0.22);
  overflow: hidden;
}

:global(#stageChangeReasonModal .modal-content) {
  animation: none !important;
}

.stage-reason-header {
  border-bottom: 1px solid #eceff5;
  padding: 9px 12px;
  background: linear-gradient(180deg, #fbfcff 0%, #f7f9ff 100%);
}

.stage-reason-title {
  font-size: 15px;
  font-weight: 700;
  color: #01062c;
}

.stage-reason-close { transform: scale(0.78); }

.stage-reason-body { padding: 10px 12px 6px; }

.compact-label {
  font-size: 12px !important;
  font-weight: 600 !important;
  color: #1f2a44;
}

.compact-control {
  min-height: 34px;
  border-radius: 8px;
  border: 1px solid #d9dfeb;
  font-size: 13px !important;
  color: #1d2a40;
  box-shadow: none;
}

.compact-control:focus {
  border-color: #6e86ff;
  box-shadow: 0 0 0 3px rgba(110, 134, 255, 0.12);
}

.reason-textarea { resize: none; }
.compact-alert { font-size: 13px; border-radius: 7px; }

.required-section {
  margin-top: 8px;
  background: #f8faff;
  border: 1px solid #e8edf8;
  border-radius: 10px;
  padding: 8px;
}
.required-section-secondary { background: #fbfcff; }

.required-header { margin-bottom: 6px; }
.required-title {
  font-size: 12px;
  font-weight: 700;
  color: #1f2a44;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}
.required-subtitle { font-size: 11px; color: #67748b; }

.required-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 6px 8px;
}
.required-selects-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 6px 8px;
}
.compact-group { margin-bottom: 0 !important; }

:deep(.compact-v-select .vs__dropdown-toggle) {
  min-height: 34px !important;
  border-radius: 8px !important;
  border: 1px solid #d9dfeb !important;
  padding: 0 7px !important;
  font-size: 13px !important;
}
:deep(.compact-v-select .vs__selected),
:deep(.compact-v-select .vs__search),
:deep(.compact-v-select .vs__placeholder) {
  font-size: 12px !important;
  color: #1d2a40 !important;
}
:deep(.compact-v-select.vs--open .vs__dropdown-toggle) {
  border-color: #6e86ff !important;
  box-shadow: 0 0 0 3px rgba(110, 134, 255, 0.12) !important;
}
:deep(.compact-v-select .vs__dropdown-menu) {
  font-size: 12px !important;
  border-radius: 8px !important;
}

.location-selected {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  line-height: 1.2;
}
.location-selected-name {
  font-weight: 600;
  font-size: 11px;
  color: #01062d;
}
.location-selected-subtitle {
  font-size: 10px;
  color: #64748b;
}

.location-option {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 4px 0;
  min-height: 40px;
}
.location-option-icon {
  font-size: 1rem;
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
  font-size: 11px;
  color: #01062d;
  line-height: 1.2;
}
.location-option-subtitle {
  font-size: 10px;
  color: #64748b;
  line-height: 1.2;
}

.compact-input { padding: 6px 10px; font-size: 13px !important; }
.compact-input::placeholder { font-size: 12px !important; color: #8b97aa; }

.stage-reason-footer {
  border-top: 1px solid #eceff5;
  padding: 8px 12px 10px;
  background: #fbfcff;
  display: flex;
  align-items: center;
  justify-content: center;
}
.stage-btn {
  min-width: 96px;
  height: 33px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  transition: all 0.18s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
  padding: 0 14px;
  text-align: center;
  vertical-align: middle;
}
.stage-btn-cancel {
  border: 1px solid #f0b5be;
  color: #d0455b;
  background: #fff;
}
.stage-btn-cancel:hover { background: #fff5f7; }
.stage-btn-submit {
  border: 1px solid #de9f1f;
  color: #fff;
  background: linear-gradient(180deg, #ffbf3f 0%, #f2a907 100%);
  box-shadow: 0 6px 14px rgba(242, 169, 7, 0.24);
}
.stage-btn-submit:hover { transform: translateY(-1px); }
.stage-btn-submit:disabled { opacity: 0.7; cursor: not-allowed; }

.stage-btn :deep(.spinner-border-sm) {
  width: 12px;
  height: 12px;
  border-width: 2px;
}

@media (max-width: 767px) {
  .required-grid { grid-template-columns: 1fr; }
}
</style>
