<!-- components/Deals/ConvertLeadModal.vue -->
<template>
    <div ref="modalRootRef" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered convert-lead-dialog">
            <div class="modal-content convert-lead-content">
                <div class="modal-header convert-lead-header">
                    <h6 class="modal-title mb-0">Select Converted Lead Type</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body convert-lead-body">
                    <div class="options-container">
                        <label class="deal-type-option" :class="{ selected: form.deal_type === 'primary' }">
                            <div class="option-icon">
                                <img :src="primaryIcon" alt="Primary / Off Plan" width="32" height="32">
                            </div>
                            <input type="radio" name="dealType" value="primary" v-model="form.deal_type" class="deal-type-radio">
                            <span class="deal-type-label">Primary</span>
                            <span class="selected-mark" :class="{ show: form.deal_type === 'primary' }">
                                <img :src="checkIcon" alt="✓" >
                            </span>
                        </label>

                        <label class="deal-type-option" :class="{ selected: form.deal_type === 'secondary' }">
                            <div class="option-icon">
                                <img :src="secondaryIcon" alt="Secondary" width="32" height="32">
                            </div>
                            <input type="radio" name="dealType" value="secondary" v-model="form.deal_type" class="deal-type-radio">
                            <span class="deal-type-label">Secondary</span>
                            <span class="selected-mark" :class="{ show: form.deal_type === 'secondary' }">
                                <img :src="checkIcon" alt="✓" >
                            </span>
                        </label>

                        <label class="deal-type-option" :class="{ selected: form.deal_type === 'rental' }">
                            <div class="option-icon">
                                <img :src="rentalIcon" alt="Rental" width="32" height="32">
                            </div>
                            <input type="radio" name="dealType" value="rental" v-model="form.deal_type" class="deal-type-radio">
                            <span class="deal-type-label">Rental</span>
                            <span class="selected-mark" :class="{ show: form.deal_type === 'rental' }">
                                <img :src="checkIcon" alt="✓" >
                            </span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer convert-lead-footer">
                    <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button
                        type="button"
                        class="btn btn-add-deal"
                        @click="submitConversion"
                        :disabled="!form.deal_type || loading"
                    >
                        <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
                        Add Deal
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, nextTick, onMounted, onUnmounted } from 'vue'
import api from '@/plugins/axios'
import Swal from 'sweetalert2'
import * as bootstrap from 'bootstrap'

// Import images from public folder (no import needed, just use direct path)
const primaryIcon = '/assets/images/deal-types/primary.svg'
const secondaryIcon = '/assets/images/deal-types/secondary.svg'
const rentalIcon = '/assets/images/deal-types/rental.svg'
const checkIcon = '/assets/images/deal-types/check.svg'

const props = defineProps({
    leadId: {
        type: [Number, String],
        default: null
    },
    leadData: {
        type: Object,
        default: null
    }
})

const emit = defineEmits(['converted', 'closed'])

const loading = ref(false)
const modalInstance = ref(null)
const modalRootRef = ref(null)

const form = ref({
    lead_id: props.leadId,
    deal_type: ''
})

watch(() => props.leadId, (newId) => {
    form.value.lead_id = newId
})

const resolveLeadId = (explicitLeadId = null, explicitLeadData = null) => {
    const candidate =
        explicitLeadId
        ?? explicitLeadData?.id
        ?? explicitLeadData?.lead_id
        ?? explicitLeadData?.lead?.id
        ?? explicitLeadData?.lead?.lead_id
        ?? props.leadId
        ?? form.value.lead_id
        ?? props.leadData?.id
        ?? props.leadData?.lead_id
        ?? props.leadData?.lead?.id
        ?? props.leadData?.lead?.lead_id
        ?? null

    const numeric = Number(candidate)
    if (!Number.isNaN(numeric) && numeric > 0) return numeric
    return candidate
}

const show = (leadId = null, leadData = null) => {
    form.value.lead_id = resolveLeadId(leadId, leadData)
    nextTick(() => {
        const modalEl = modalRootRef.value
        if (modalEl) {
            if (!modalInstance.value) {
                modalInstance.value = new bootstrap.Modal(modalEl)
            }
            modalInstance.value.show()
        }
    })
}

const hide = () => {
    if (modalInstance.value) {
        modalInstance.value.hide()
    }
}

const onHidden = () => {
    emit('closed')
    form.value.deal_type = ''
}

onMounted(() => {
    const modalEl = modalRootRef.value
    if (!modalEl) return
    modalEl.addEventListener('hidden.bs.modal', onHidden)
})

onUnmounted(() => {
    const modalEl = modalRootRef.value
    if (modalEl) {
        modalEl.removeEventListener('hidden.bs.modal', onHidden)
    }
    if (modalInstance.value) {
        modalInstance.value.dispose()
        modalInstance.value = null
    }
})

const submitConversion = async () => {
    if (!form.value.deal_type) {
        Swal.fire({
            icon: 'warning',
            title: 'Please select a deal type',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        })
        return
    }

    loading.value = true
    
    try {
        const resolvedLeadId = resolveLeadId()
        if (!resolvedLeadId) {
            Swal.fire({
                icon: 'error',
                title: 'Conversion failed',
                text: 'Lead ID is missing. Please reopen the convert modal and try again.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3500
            })
            loading.value = false
            return
        }

        const response = await api.post('/leads/convert/to-deal', {
            lead_id: resolvedLeadId,
            leadId: resolvedLeadId,
            id: resolvedLeadId,
            deal_type: form.value.deal_type
        })
        
        if (response.data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Lead converted successfully!',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            })
            
            emit('converted', response.data.data)
            hide()
        }
    } catch (error) {
        const backendDebug = error?.response?.data?.debug?.payload
            ? ` | payload: ${JSON.stringify(error.response.data.debug.payload)}`
            : ''
        Swal.fire({
            icon: 'error',
            title: 'Conversion failed',
            text: (error.response?.data?.message || 'An error occurred') + backendDebug,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        })
    } finally {
        loading.value = false
    }
}

defineExpose({
    show,
    hide
})
</script>

<style scoped>
.convert-lead-dialog {
    max-width: 760px;
}

.convert-lead-content {
    border-radius: 24px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.01);
}

.convert-lead-header {
    padding: 28px 32px 20px;
    border-bottom: 1px solid #f0f0f0;
    background: white;
}

.convert-lead-header .modal-title {
    font-size: 22px !important;
    font-weight: 600;
    line-height: 1.3;
    color: #111827;
    margin: 0;
}

.convert-lead-header .btn-close {
    opacity: 1;
    transform: scale(1.2);
    filter: brightness(0.6);
}

.convert-lead-body {
    padding: 24px 32px;
    background: white;
}

.options-container {
    display: flex;
    flex-direction: row;
    gap: 16px;
    justify-content: space-between;
}

.deal-type-option {
    flex: 1;
    border: 1.5px solid #e5e7eb;
    border-radius: 16px;
    padding: 20px 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #fff;
    position: relative;
    gap: 12px;
}

.deal-type-option:hover {
    border-color: #FAA300;
    background: #fef9e6;
    transform: translateY(-2px);
}

.deal-type-option.selected {
    background: #01062C;
    border-color: #01062C;
}

.option-icon {
    width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.option-icon img {
    filter: brightness(0) saturate(100%) invert(67%) sepia(71%) saturate(1235%) hue-rotate(1deg) brightness(102%) contrast(103%);
    transition: all 0.2s ease;
}

.deal-type-option.selected .option-icon img {
    filter: brightness(0) saturate(100%) invert(67%) sepia(71%) saturate(1235%) hue-rotate(1deg) brightness(102%) contrast(103%);
}

.deal-type-radio {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.deal-type-label {
    font-size: 15px;
    font-weight: 500;
    color: #000;
    text-align: center;
}

.deal-type-option.selected .deal-type-label {
    color: #fff;
}

.selected-mark {
   position: absolute;
    top: -10px;
    right: -2px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    /* background: #FAA300;
    color: #fff; */
   
}

.selected-mark img {
    /* filter: brightness(0) invert(1);
    width: 16px;
    height: 16px; */
}

.selected-mark.show {
    opacity: 1;
    transform: scale(1);
}

.deal-type-option.selected .selected-mark {
    /* background: #FAA300; */
}

.convert-lead-footer {
    border-top: 1px solid #f0f0f0;
    padding: 20px 32px 28px;
    justify-content: flex-end;
    gap: 12px;
    background: white;
}

.btn-cancel,
.btn-add-deal {
    min-width: 110px;
    height: 44px;
    border-radius: 12px;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    line-height: 1;
    font-size: 14px;
    font-weight: 600;
    padding: 0 24px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-cancel {
    background: #F4F4F4;
    color: #000000;
}

.btn-cancel:hover {
    background: #e5e7eb;
}

.btn-add-deal {
    background: #000000;
    color: #fff;
}

.btn-add-deal:hover:not(:disabled) {
    background: #FAA300;
    color: #000000;
    transform: translateY(-1px);
}

.btn-add-deal:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .convert-lead-header,
    .convert-lead-body,
    .convert-lead-footer {
        padding-left: 20px;
        padding-right: 20px;
    }

    .convert-lead-header {
        padding-top: 20px;
        padding-bottom: 16px;
    }

    .convert-lead-header .modal-title {
        font-size: 18px !important;
    }

    .convert-lead-body {
        padding: 20px;
    }

    .options-container {
        gap: 12px;
    }

    .deal-type-option {
        padding: 16px 12px;
        gap: 10px;
    }

    .option-icon {
        width: 48px;
        height: 48px;
    }

    .option-icon img {
        width: 28px;
        height: 28px;
    }

    .deal-type-label {
        font-size: 13px;
    }

    .selected-mark {
        width: 24px;
        height: 24px;
        top: 8px;
        right: 8px;
    }

    .selected-mark img {
        width: 14px;
        height: 14px;
    }

    .btn-cancel,
    .btn-add-deal {
        min-width: 90px;
        height: 40px;
        font-size: 13px;
        padding: 0 18px;
    }
}
</style>