<!-- components/Deals/ConvertLeadModal.vue -->
<template>
    <div class="modal fade" id="convertLeadModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title text-xl mb-0">Select Coverted Lead Type</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  
                    
                    <!-- Deal Type Selection -->
                    <div class="d-flex flex-column gap-3">
                        <label 
                            class="deal-type-option p-3 border rounded-3 d-flex align-items-center gap-3 cursor-pointer"
                            :class="{ 'border-primary bg-primary-light': form.deal_type === 'primary' }"
                        >
                            <input 
                                type="radio" 
                                name="dealType" 
                                value="primary" 
                                v-model="form.deal_type"
                                class="form-check-input mt-0"
                            >
                            <div>
                                <h6 class="mb-1">Primary / Off Plan</h6>
                            </div>
                        </label>

                        <label 
                            class="deal-type-option p-3 border rounded-3 d-flex align-items-center gap-3 cursor-pointer"
                            :class="{ 'border-primary bg-primary-light': form.deal_type === 'secondary' }"
                        >
                            <input 
                                type="radio" 
                                name="dealType" 
                                value="secondary" 
                                v-model="form.deal_type"
                                class="form-check-input mt-0"
                            >
                            <div>
                                <h6 class="mb-1">Secondary</h6>
                            </div>
                        </label>

                        <label 
                            class="deal-type-option p-3 border rounded-3 d-flex align-items-center gap-3 cursor-pointer"
                            :class="{ 'border-primary bg-primary-light': form.deal_type === 'rental' }"
                        >
                            <input 
                                type="radio" 
                                name="dealType" 
                                value="rental" 
                                v-model="form.deal_type"
                                class="form-check-input mt-0"
                            >
                            <div>
                                <h6 class="mb-1">Rental</h6>
                            </div>
                        </label>
                    </div>


                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button 
                        type="button" 
                        class="btn btn-primary" 
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
.deal-type-option {
    cursor: pointer;
    transition: all 0.2s ease;
}

.deal-type-option:hover {
    background-color: #f8f9fa;
    border-color: #0d6efd !important;
}

.deal-type-option .form-check-input {
    cursor: pointer;
    width: 1.2em;
    height: 1.2em;
}

.bg-primary-light {
    background-color:#01062C !important;
}
.deal-type-option h6{
        font-size: 16px !important;
}
.deal-type-option.bg-primary-light h6{
    color:#fff !important;
}
</style>