<template>
    <b-modal
        v-model="localShow"
        modal-class="add-stage-modal"
        content-class="add-stage-modal-content"
        hide-header
        hide-footer
        centered
        @hidden="handleClose"
        body-class="p-0"
    >
        <div class="modal-container p-3">
            <!-- Header -->
            <ModalHeader title="Add New Stage" @close="handleClose" />

            <!-- Form Content -->
            <div class="modal-body-custom m-3">
                <!-- Stage Tittle -->
                <div class="form-group">
                    <label class="form-label">Stage Title</label>
                    <b-form-input
                        v-model="formData.title"
                        placeholder="Enter Title"
                        class="form-input"
                        :class="{ 'is-invalid': validationErrors.name || validationErrors.title }"
                    />
                    <div v-if="validationErrors.name" class="invalid-feedback d-block">
                        {{ validationErrors.name[0] }}
                    </div>
                    <div v-if="validationErrors.title" class="invalid-feedback d-block">
                        {{ validationErrors.title[0] }}
                    </div>
                </div>

                <!-- Stage Order -->
                <!-- <div class="form-group">
                    <label class="form-label">Stage Order</label>
                    <v-select 
                        v-model="formData.order" 
                        :options="orderOptions"
                        :reduce="option => option.value"
                        label="text"
                        placeholder="Select Order"
                        class="custom-v-select"
                    >
                        <template #open-indicator="{ attributes }">
                            <span v-bind="attributes">
                                <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                            </span>
                        </template>
                    </v-select>
                </div> -->

                <!-- <div class="form-group">
                    <label class="form-label">Stage Rules</label>
                    <v-select 
                        v-model="formData.roles" 
                        :options="rolesOptions" 
                        :reduce="option => option.value"
                        label="text"
                        placeholder="Select Roles"
                        class="custom-v-select"
                    >
                        <template #open-indicator="{ attributes }">
                            <span v-bind="attributes">
                                <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                            </span>
                        </template>
                    </v-select>
                </div> -->
            </div>

            <!-- Footer Buttons -->
            <div class="modal-footer-custom">
                
                <div class="d-flex align-items-center justify-content-end gap-3 w-100">
                    <button class="btn-cancel" @click="handleClose" :disabled="isSubmitting">
                        Cancel
                    </button>
                    <button class="btn-save" @click="handleSave" :disabled="isSubmitting">
                        <span v-if="isSubmitting">Creating...</span>
                        <span v-else>Save</span>
                    </button>
                </div>
            </div>
        </div>
    </b-modal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { BModal, BFormInput } from 'bootstrap-vue-3'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import ModalHeader from './ModalHeader.vue'
import api from '@/plugins/axios'

const props = defineProps({
    modelValue: {
        type: Boolean,
        required: true
    }
})

const emit = defineEmits(['update:modelValue', 'stage-created'])

const localShow = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
})

const formData = ref({
    title: '',
    order: null,
    roles: null
})

const isSubmitting = ref(false)
const errorMessage = ref('')
const validationErrors = ref({})
const stages = ref([])

// Fetch stages and create order options
const fetchStages = async () => {
    try {
        const response = await api.get('/stages')
        stages.value = response.data.data.data || []
    } catch (error) {
        console.error('Error fetching stages:', error)
        stages.value = []
    }
}

// Computed property for order options based on fetched stages
const orderOptions = computed(() => {
    let options = stages.value.map((stage, index) => ({
        value: stage.order + 1,
        text: `After ${stage.name}`
    }))
    return options;
})

// Watch for modal opening to fetch stages
watch(localShow, (newValue) => {
    if (newValue) {
        fetchStages()
    }
})

// Clear validation errors when user starts typing
watch(() => formData.value.title, () => {
    if (validationErrors.value.name) {
        delete validationErrors.value.name
    }
    if (validationErrors.value.title) {
        delete validationErrors.value.title
    }
    if (Object.keys(validationErrors.value).length === 0) {
        errorMessage.value = ''
    }
})

const handleClose = () => {
    localShow.value = false
    resetForm()
}

const handleSave = async () => {
    try {
        isSubmitting.value = true
        errorMessage.value = ''
        validationErrors.value = {}
        
        const payload = {
            name: formData.value.title,
            // order: formData.value.order,
            // Add other fields as needed
        }
        
        const response = await api.post('/stages', payload)
        
        console.log('✅ Stage created successfully:', response.data)
        
        // Success: close modal, reset form, and emit event
        localShow.value = false
        resetForm()
        
        emit('stage-created', response.data)
        $showNotification('Stage created successfully!', 'success')
        
    } catch (error) {
        // Error: show error message, don't close modal, don't reset form
        console.error('❌ Error creating stage:', error)
        
        // Check if it's a validation error (422 status)
        if (error.response && error.response.status === 422) {
            // Laravel validation errors format: { field: ["error message"] }
            const errors = error.response.data.errors || error.response.data
            validationErrors.value = errors
            
            errorMessage.value = 'Please fix the validation errors below.'
            $showNotification('Please check the form for errors', 'warning')
        } else {
            // General error
            errorMessage.value = error.response?.data?.message || 'Failed to create stage. Please try again.'
            $showNotification(errorMessage.value, 'error')
        }
    } finally {
        isSubmitting.value = false
    }
}

const resetForm = () => {
    formData.value = {
        title: '',
        order: null,
        roles: null
    }
    errorMessage.value = ''
    validationErrors.value = {}
}

// Notification helper
const $showNotification = (message, type = 'info') => {
    if (window.$showNotification) {
        window.$showNotification(message, type)
    } else {
        console.log(`${type}: ${message}`)
    }
}
</script>

<style scoped>
:deep(.add-stage-modal .modal-dialog) {
    max-width: 720px;
}

:deep(.add-stage-modal-content) {
    border-radius: 16px;
    border: none;
    box-shadow: 0px 4px 20px rgba(0, 0, 0, 0.1);
}

.modal-container {
    background: #FFFFFF;
    border-radius: 16px;
}

:deep(.modal-header-wrapper) {
    padding: 24px 32px;
    border-bottom: 1px solid #F1F5F9;
}

:deep(.modal-title) {
    font-family: 'Montserrat', sans-serif;
    font-size: 20px;
    font-weight: 600;
    color: #0F172A;
}

:deep(.close-btn-top) {
    font-size: 24px;
}

.form-group {
    margin-bottom: 24px;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: #000000;
    margin-bottom: 2px;
}

.form-input {
    height: 42px !important;
    border-radius: 10px !important;
    border: 1px solid #E2E8F0 !important;
    font-size: 13px !important;
    color: #64748B !important;
    font-family: 'Montserrat';
    flex-grow: 1 !important;
    border-radius: 8px !important;
    padding: 0 8px !important;
}

.form-input::placeholder {
    color: #64748B !important;
    opacity: 1;
    font-size: 13px !important;
    font-family: 'Montserrat';
}

.form-input.is-invalid {
    border-color: #dc3545 !important;
}

.invalid-feedback {
    display: block;
    width: 100%;
    margin-top: 0.25rem;
    font-size: 0.875em;
    color: #dc3545;
    font-family: 'Montserrat';
}

/* V-Select Styling */
:deep(.custom-v-select) {
    font-family: 'Montserrat';
}

:deep(.custom-v-select .vs__dropdown-toggle) {
    height: 42px;
    border-radius: 10px;
    border: 1px solid #E2E8F0;
    background: #fff;
    padding: 0 8px;
}

:deep(.custom-v-select .vs__selected-options) {
    flex-wrap: nowrap;
    overflow: hidden;
    max-width: calc(100% - 30px);
}

:deep(.custom-v-select .vs__selected) {
    font-size: 13px;
    color: #64748B;
    margin: 0;
    padding: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
    max-width: 100%;
    line-height: 40px;
}

:deep(.custom-v-select .vs__search) {
    font-size: 13px;
    color: #64748B;
    margin: 0;
    padding: 0;
}

:deep(.custom-v-select .vs__search::placeholder) {
    color: #64748B;
}

:deep(.custom-v-select .vs__actions) {
    padding: 0 8px;
}

:deep(.custom-v-select .vs__open-indicator-icon) {
    font-size: 16px;
    color: #64748B;
}

:deep(svg) {
    vertical-align: middle !important;
}

:deep(.custom-v-select .vs__dropdown-menu) {
    border: 1px solid #E2E8F0;
    box-shadow: 0px 10px 30px rgba(0, 0, 0, 0.1);
    padding: 0;
    margin-top: 5px;
    z-index: 1100;
}

:deep(.custom-v-select .vs__dropdown-option) {
    padding: 5px 10px;
    font-size: 14px;
    color: #475569;
    transition: all 0.2s;
}

:deep(.custom-v-select .vs__dropdown-option--highlight) {
    background: #FAA300 !important;
    color: #fff !important;
}

:deep(.custom-v-select .vs__dropdown-option--selected) {
    background: #FAA300;
    color: #fff;
}

.alert {
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 13px;
    font-family: 'Montserrat';
}

.alert-danger {
    background-color: #FEE2E2;
    border: 1px solid #FCA5A5;
    color: #991B1B;
}

.modal-footer-custom {
    padding-top: 8px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 16px;
    border-top: 1px solid #F1F5F9;
}

.btn-cancel {
    background: #F4F4F4;
    border: none;
    padding: 10px 25px;
    border-radius: 100px;
    font-size: 14px;
    color: #01062C;
    cursor: pointer;
}

.btn-cancel:hover {
    background: #E2E8F0 !important;
}

.btn-save {
    background: #01062C;
    border: none;
    padding: 10px 20px;
    border-radius: 100px;
    font-size: 14px;
    color: #fff;
    font-weight: 400;
    display: flex;
    align-items: center;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-save:hover {
    background: #060a2b;
}
</style>
