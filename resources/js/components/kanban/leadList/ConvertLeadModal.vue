<!-- components/Deals/ConvertLeadModal.vue -->
<template>
    <Teleport to="body">
        <div
            v-if="visible"
            class="convert-lead-overlay"
            role="dialog"
            aria-modal="true"
            aria-label="Select converted lead type"
            @click.self="hide"
        >
            <div class="convert-lead-dialog">
                <div class="convert-lead-content">
                    <div class="convert-lead-header">
                        <h6 class="convert-lead-title">Select Converted Lead Type</h6>
                        <button type="button" class="convert-lead-close" aria-label="Close" @click="hide">
                            <iconify-icon icon="lucide:x" />
                        </button>
                    </div>
                    <div class="convert-lead-body">
                        <div class="options-container">
                            <button
                                type="button"
                                class="deal-type-option"
                                :class="{ selected: form.deal_type === 'primary' }"
                                @click.stop="selectDealType('primary')"
                            >
                                <div class="option-icon">
                                    <img :src="primaryIcon" alt="Primary / Off Plan" width="32" height="32">
                                </div>
                                <span class="deal-type-label">Primary</span>
                                <span class="selected-mark" :class="{ show: form.deal_type === 'primary' }">
                                    <img :src="checkIcon" alt="Selected">
                                </span>
                            </button>

                            <button
                                type="button"
                                class="deal-type-option"
                                :class="{ selected: form.deal_type === 'secondary' }"
                                @click.stop="selectDealType('secondary')"
                            >
                                <div class="option-icon">
                                    <img :src="secondaryIcon" alt="Secondary" width="32" height="32">
                                </div>
                                <span class="deal-type-label">Secondary</span>
                                <span class="selected-mark" :class="{ show: form.deal_type === 'secondary' }">
                                    <img :src="checkIcon" alt="Selected">
                                </span>
                            </button>

                            <button
                                type="button"
                                class="deal-type-option"
                                :class="{ selected: form.deal_type === 'rental' }"
                                @click.stop="selectDealType('rental')"
                            >
                                <div class="option-icon">
                                    <img :src="rentalIcon" alt="Rental" width="32" height="32">
                                </div>
                                <span class="deal-type-label">Rental</span>
                                <span class="selected-mark" :class="{ show: form.deal_type === 'rental' }">
                                    <img :src="checkIcon" alt="Selected">
                                </span>
                            </button>
                        </div>
                    </div>
                    <div class="convert-lead-footer">
                        <button type="button" class="btn-cancel" @click="hide">Cancel</button>
                        <button
                            type="button"
                            class="btn-add-deal"
                            @click.stop="submitConversion"
                            :disabled="!form.deal_type || loading"
                        >
                            <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
                            Add Deal
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue'
import api from '@/plugins/axios'
import Swal from 'sweetalert2'

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

const visible = ref(false)
const loading = ref(false)

const form = ref({
    lead_id: props.leadId,
    deal_type: ''
})

watch(() => props.leadId, (newId) => {
    form.value.lead_id = newId
})

function selectDealType(type) {
    form.value.deal_type = type
}

function onEscapeKey(event) {
    if (event.key === 'Escape' && visible.value) {
        hide()
    }
}

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

function cleanupBootstrapBackdrops() {
    document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove())
    document.body.classList.remove('modal-open')
    document.body.style.removeProperty('overflow')
    document.body.style.removeProperty('padding-right')
}

const show = (leadId = null, leadData = null) => {
    cleanupBootstrapBackdrops()
    form.value.lead_id = resolveLeadId(leadId, leadData)
    form.value.deal_type = ''
    visible.value = true
    document.body.style.overflow = 'hidden'
}

const hide = () => {
    if (!visible.value) return
    visible.value = false
    document.body.style.overflow = ''
    emit('closed')
    form.value.deal_type = ''
}

onMounted(() => {
    document.addEventListener('keydown', onEscapeKey)
})

onUnmounted(() => {
    document.removeEventListener('keydown', onEscapeKey)
    document.body.style.overflow = ''
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

            const createdDeal = {
                ...(response.data.data || {}),
                deal_type: response.data.data?.deal_type ?? form.value.deal_type,
            }
            emit('converted', createdDeal)
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
.convert-lead-overlay {
    position: fixed;
    inset: 0;
    z-index: 101800;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(0, 0, 0, 0.65);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    pointer-events: auto;
    animation: convertLeadFadeIn 0.2s ease;
}

@keyframes convertLeadFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.convert-lead-dialog {
    width: min(760px, 100%);
    max-height: calc(100vh - 40px);
    pointer-events: auto;
    animation: convertLeadSlideIn 0.22s cubic-bezier(0.22, 1, 0.36, 1);
}

@keyframes convertLeadSlideIn {
    from {
        opacity: 0;
        transform: translateY(12px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.convert-lead-content {
    border-radius: 24px;
    border: 1px solid #e5e7eb;
    overflow: visible;
    background: #fff;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.12), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    pointer-events: auto;
}

.convert-lead-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 28px 32px 20px;
    border-bottom: 1px solid #f0f0f0;
    background: white;
}

.convert-lead-title {
    margin: 0;
    font-size: 22px !important;
    font-weight: 600;
    line-height: 1.3;
    color: #111827;
}

.convert-lead-close {
    width: 36px;
    height: 36px;
    border: 1px solid #e2e8f0;
    border-radius: 50%;
    background: #fff;
    color: #334155;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    cursor: pointer;
    flex-shrink: 0;
}

.convert-lead-body {
    padding: 24px 32px;
    background: white;
    pointer-events: auto;
}

.options-container {
    display: flex;
    flex-direction: row;
    gap: 16px;
    justify-content: space-between;
    pointer-events: auto;
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
    transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
    background: #fff;
    position: relative;
    gap: 12px;
    appearance: none;
    -webkit-appearance: none;
    font: inherit;
    text-align: center;
    pointer-events: auto;
    touch-action: manipulation;
    -webkit-tap-highlight-color: transparent;
}

.deal-type-option:not(.selected):hover {
    border-color: #d1d5db;
    background: #f9fafb;
}

.deal-type-option.selected {
    background: #0B0736;
    border-color: #0B0736;
}

.option-icon {
    width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    pointer-events: none;
}

.option-icon img {
    filter: brightness(0) saturate(100%) invert(67%) sepia(71%) saturate(1235%) hue-rotate(1deg) brightness(102%) contrast(103%);
}

.deal-type-label {
    font-size: 15px;
    font-weight: 500;
    color: #000;
    text-align: center;
    pointer-events: none;
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
    pointer-events: none;
    transition: opacity 0.15s ease;
}

.selected-mark.show {
    opacity: 1;
}

.convert-lead-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    border-top: 1px solid #f0f0f0;
    padding: 20px 32px 28px;
    background: white;
    pointer-events: auto;
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
    font-size: 14px;
    font-weight: 600;
    padding: 0 24px;
    cursor: pointer;
    transition: all 0.2s ease;
    pointer-events: auto;
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
    background: #733E87;
    color: #fff;
}

.btn-add-deal:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .convert-lead-overlay {
        align-items: flex-end;
        padding: 0;
    }

    .convert-lead-dialog {
        width: 100%;
        max-height: 90vh;
    }

    .convert-lead-content {
        border-radius: 20px 20px 0 0;
    }

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

    .convert-lead-title {
        font-size: 18px !important;
    }

    .options-container {
        gap: 10px;
    }

    .deal-type-option {
        padding: 14px 10px;
        gap: 8px;
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

    .btn-cancel,
    .btn-add-deal {
        min-width: 90px;
        height: 40px;
        font-size: 13px;
        padding: 0 18px;
    }
}
</style>
