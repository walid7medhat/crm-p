<!-- components/Deals/ConvertLeadModal.vue -->
<template>
    <div class="modal fade" id="convertLeadModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered convert-lead-dialog">
            <div class="modal-content convert-lead-content">
                <div class="modal-header convert-lead-header">
                    <h6 class="modal-title mb-0">Select Converted Lead Type</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body convert-lead-body">
                    <div class="d-flex flex-column gap-3">
                        <label class="deal-type-option" :class="{ selected: form.deal_type === 'primary' }">
                            <input type="radio" name="dealType" value="primary" v-model="form.deal_type" class="deal-type-radio">
                            <span class="deal-type-label">Primary / Off Plan</span>
                            <span class="selected-mark" :class="{ show: form.deal_type === 'primary' }">
                                <iconify-icon icon="lucide:check"></iconify-icon>
                            </span>
                        </label>

                        <label class="deal-type-option" :class="{ selected: form.deal_type === 'secondary' }">
                            <input type="radio" name="dealType" value="secondary" v-model="form.deal_type" class="deal-type-radio">
                            <span class="deal-type-label">Secondary</span>
                            <span class="selected-mark" :class="{ show: form.deal_type === 'secondary' }">
                                <iconify-icon icon="lucide:check"></iconify-icon>
                            </span>
                        </label>

                        <label class="deal-type-option" :class="{ selected: form.deal_type === 'rental' }">
                            <input type="radio" name="dealType" value="rental" v-model="form.deal_type" class="deal-type-radio">
                            <span class="deal-type-label">Rental</span>
                            <span class="selected-mark" :class="{ show: form.deal_type === 'rental' }">
                                <iconify-icon icon="lucide:check"></iconify-icon>
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
                        AddDeal
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue'
import api from '@/plugins/axios'
import Swal from 'sweetalert2'
import * as bootstrap from 'bootstrap'

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

const form = ref({
    lead_id: props.leadId,
    deal_type: ''
})

watch(() => props.leadId, (newId) => {
    form.value.lead_id = newId
})

const show = () => {
    nextTick(() => {
        const modalEl = document.getElementById('convertLeadModal')
        if (modalEl) {
            modalInstance.value = new bootstrap.Modal(modalEl)
            modalInstance.value.show()
            
            modalEl.addEventListener('hidden.bs.modal', () => {
                emit('closed')
                form.value.deal_type = '' // Reset on close
            })
        }
    })
}

const hide = () => {
    if (modalInstance.value) {
        modalInstance.value.hide()
    }
}

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
        const response = await api.post('/leads/convert/to-deal', {
            lead_id: props.leadId,
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
        Swal.fire({
            icon: 'error',
            title: 'Conversion failed',
            text: error.response?.data?.message || 'An error occurred',
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
    border-radius: 16px;
    border: 2px solid #2a9ef0;
    overflow: hidden;
}

.convert-lead-header {
    padding: 24px 44px 18px;
    border-bottom: 1px solid #eceff4;
}

.convert-lead-header .modal-title {
    font-size: 44px;
    font-weight: 600;
    line-height: 1.2;
    color: #111827;
}

.convert-lead-header .btn-close {
    opacity: 1;
    transform: scale(1.35);
}

.convert-lead-body {
    padding: 28px 44px;
}

.deal-type-option {
    border: 2px solid #eceff4;
    border-radius: 22px;
    min-height: 56px;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #fff;
}

.deal-type-option.selected {
    background: #00073a;
    border-color: #00073a;
}

.deal-type-radio {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.deal-type-label {
    font-size: 20px !important;
    font-weight: 500;
    color: #1f2937;
}

.deal-type-option.selected .deal-type-label {
    color: #fff;
    font-weight: 600;
}

.selected-mark {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f5ab00;
    color: #fff;
    font-size: 16px;
    opacity: 0;
    transform: scale(0.85);
    transition: all 0.2s ease;
}

.selected-mark.show {
    opacity: 1;
    transform: scale(1);
}

.convert-lead-footer {
    border-top: 1px solid #eceff4;
    padding: 22px 44px 28px;
    justify-content: flex-end;
    gap: 18px;
}

.btn-cancel,
.btn-add-deal {
    min-width: 127px;
    height: 46px;
    border-radius: 999px;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    line-height: 1;
    font-size: 12px;
    font-weight: 500;
    padding: 0 14px;
}

.btn-cancel {
    background: #efeff2;
    color: #111827;
}

.btn-add-deal {
    background: #000;
    color: #fff;
}

.btn-add-deal:disabled {
    opacity: 0.65;
}

@media (max-width: 1200px) {
    .convert-lead-dialog {
        max-width: 95vw;
    }

    .convert-lead-header .modal-title {
        font-size: 34px;
    }

    .deal-type-label {
        font-size: 20px;
    }

    .btn-cancel,
    .btn-add-deal {
        min-width: 120px;
        height: 40px;
        font-size: 12px;
    }
}

@media (max-width: 768px) {
    .convert-lead-header,
    .convert-lead-body,
    .convert-lead-footer {
        padding-left: 20px;
        padding-right: 20px;
    }

    .convert-lead-header .modal-title {
        font-size: 24px;
    }

    .deal-type-option {
        min-height: 52px;
        border-radius: 14px;
        padding: 10px 14px;
    }

    .deal-type-label {
        font-size: 20px;
    }

    .selected-mark {
        width: 28px;
        height: 28px;
        font-size: 14px;
    }

    .btn-cancel,
    .btn-add-deal {
        min-width: 104px;
        height: 36px;
        font-size: 11px;
    }
}
</style>