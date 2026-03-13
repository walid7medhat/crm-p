<template>
    <b-modal 
        id="create-lead-modal" 
        v-model="show"
        hide-header
        hide-footer
        size="xl"
        centered
        body-class="p-0"
    >
        <div class="create-lead-modal-content p-3">
            <!-- Header with Close Button -->
            <ModalHeader title="Create New Lead" @close="show = false" />

            <!-- Stage Selector -->
            <StageSelector class="px-1" v-model="form.stage_id" />
            <div v-if="validationErrors.stage_id" class="invalid-feedback d-block px-1 mb-2">
                {{ validationErrors.stage_id[0] }}
            </div>

            <!-- Form Content -->
            <div class="form-scroll-area">
                <div class="step-content">
                    <div class="row g-4 p-4 position-relative">
                        <!-- Lead Name -->
                        <div class="col-12">
                            <label class="form-label-custom">Lead Name</label>
                            <b-form-input 
                                v-model="form.lead_name" 
                                placeholder="Enter Lead Name" 
                                class="custom-input"
                                :class="{ 'is-invalid': validationErrors.lead_name }"
                            />
                            <div v-if="validationErrors.lead_name" class="invalid-feedback d-block">
                                {{ validationErrors.lead_name[0] }}
                            </div>
                        </div>

                        <!-- Salutation, First Name, Last Name, Position -->
                        <div class="col-md-3">
                            <label class="form-label-custom">Salutation</label>
                            <v-select 
                                v-model="form.salutation" 
                                :options="salutationOptions" 
                                :reduce="option => option.value"
                                label="text"
                                placeholder="Not Selected"
                                class="custom-v-select"
                                :class="{ 'is-invalid-select': validationErrors.salutation }"
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                            </v-select>
                            <div v-if="validationErrors.salutation" class="invalid-feedback d-block">
                                {{ validationErrors.salutation[0] }}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">First Name</label>
                            <b-form-input 
                                v-model="form.first_name" 
                                placeholder="Enter Your First Name *" 
                                class="custom-input"
                                :class="{ 'is-invalid': validationErrors.first_name }"
                            />
                            <div v-if="validationErrors.first_name" class="invalid-feedback d-block">
                                {{ validationErrors.first_name[0] }}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">Last Name</label>
                            <b-form-input 
                                v-model="form.last_name" 
                                placeholder="Enter Your Last Name *" 
                                class="custom-input"
                                :class="{ 'is-invalid': validationErrors.last_name }"
                            />
                            <div v-if="validationErrors.last_name" class="invalid-feedback d-block">
                                {{ validationErrors.last_name[0] }}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <!-- Field removed as per new form structure -->
                        </div>

                        <!-- Contact Details Section -->
                            <div class="contact-details-card p-3">
                                <span class="section-title d-block mb-3">Contact Details</span>
                                <div class="d-flex justify-content-between align-items-center gap-2">
                                    <div class="col">
                                        <label class="form-label-custom">Phone Number</label>
                                        <b-form-input 
                                            v-model="form.work_phone" 
                                            placeholder="Enter Phone Number" 
                                            class="custom-input"
                                            :class="{ 'is-invalid': validationErrors.work_phone }"
                                        />
                                        <div v-if="validationErrors.work_phone" class="invalid-feedback d-block">
                                            {{ validationErrors.work_phone[0] }}
                                        </div>
                                    </div>
                                    <div class="col">
                                        <label class="form-label-custom">Email</label>
                                        <b-form-input 
                                            v-model="form.email" 
                                            placeholder="Enter Your Email" 
                                            class="custom-input"
                                            :class="{ 'is-invalid': validationErrors.email }"
                                        />
                                        <div v-if="validationErrors.email" class="invalid-feedback d-block">
                                            {{ validationErrors.email[0] }}
                                        </div>
                                    </div>
                                    <div class="col">
                                        <label class="form-label-custom">Work Phone</label>
                                        <b-form-input 
                                            v-model="form.work_phone_2" 
                                            placeholder="Enter Phone Number" 
                                            class="custom-input"
                                            :class="{ 'is-invalid': validationErrors.work_phone_2 }"
                                        />
                                        <div v-if="validationErrors.work_phone_2" class="invalid-feedback d-block">
                                            {{ validationErrors.work_phone_2[0] }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <!-- Comments -->
                        <div class="col-12">
                            <label class="form-label-custom">Comments</label>
                            <b-form-textarea 
                                v-model="form.comment" 
                                placeholder="Text Here" 
                                rows="4" 
                                class="custom-textarea"
                                :class="{ 'is-invalid': validationErrors.comment }"
                            ></b-form-textarea>
                            <div v-if="validationErrors.comment" class="invalid-feedback d-block">
                                {{ validationErrors.comment[0] }}
                            </div>
                        </div>



                        <!-- How Many Bedrooms -->
                        <div class="col-md-3">
                            <label class="form-label-custom">How Many Bedrooms</label>
                            <v-select 
                                v-model="form.bedrooms" 
                                :options="bedroomOptions" 
                                :reduce="option => option.value"
                                label="text"
                                placeholder="Select Bedrooms"
                                class="custom-v-select"
                                :class="{ 'is-invalid-select': validationErrors.bedrooms }"
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                            </v-select>
                            <div v-if="validationErrors.bedrooms" class="invalid-feedback d-block">
                                {{ validationErrors.bedrooms[0] }}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">Budget</label>
                            <div class="input-group-custom" :class="{ 'is-invalid-group': validationErrors.budget || validationErrors.currency }">
                                <b-form-input 
                                    v-model="form.budget"  
                                    type="number" 
                                    placeholder="Enter Budget" 
                                    class="custom-input"
                                    :class="{ 'is-invalid': validationErrors.budget }"
                                />
                                <v-select 
                                    v-model="form.currency" 
                                    :options="currencyOptions" 
                                    :reduce="option => option.value"
                                    label="text"
                                    :clearable="false"
                                    :searchable="false"
                                    class="custom-v-select-inline"
                                    :class="{ 'is-invalid-select': validationErrors.currency }"
                                >
                                    <template #open-indicator="{ attributes }">
                                        <span v-bind="attributes">
                                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                        </span>
                                    </template>
                                </v-select>
                            </div>
                            <div v-if="validationErrors.budget" class="invalid-feedback d-block">
                                {{ validationErrors.budget[0] }}
                            </div>
                            <div v-if="validationErrors.currency" class="invalid-feedback d-block">
                                {{ validationErrors.currency[0] }}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">Purpose Of Purchase</label>
                            <v-select 
                                v-model="form.purpose_buying" 
                                :options="purposeOptions" 
                                :reduce="option => option.value"
                                label="text"
                                placeholder="Select Purpose"
                                class="custom-v-select"
                                :class="{ 'is-invalid-select': validationErrors.purpose_buying }"
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                            </v-select>
                            <div v-if="validationErrors.purpose_buying" class="invalid-feedback d-block">
                                {{ validationErrors.purpose_buying[0] }}
                            </div>
                        </div>
                         <!-- Source -->
                         <div class="col-md-3">
                            <label class="form-label-custom">Source</label>
                            <v-select 
                                v-model="form.lead_source" 
                                :options="sourceOptions" 
                                :reduce="option => option.value"
                                label="text"
                                placeholder="Select Source"
                                class="custom-v-select"
                                :class="{ 'is-invalid-select': validationErrors.lead_source }"
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                            </v-select>
                            <div v-if="validationErrors.lead_source" class="invalid-feedback d-block">
                                {{ validationErrors.lead_source[0] }}
                            </div>
                        </div>

                        <!-- Source Information -->
                        <div class="col-12">
                            <label class="form-label-custom">More Information</label>
                            <b-form-textarea 
                                v-model="form.source_information" 
                                placeholder="Text Here" 
                                class="custom-textarea"
                                :class="{ 'is-invalid': validationErrors.source_information }"
                            />
                            <div v-if="validationErrors.source_information" class="invalid-feedback d-block">
                                {{ validationErrors.source_information[0] }}
                            </div>
                        </div>

                        <!-- Responsible Person Card -->
                        <ResponsiblePersonSelector
                            v-model="form.responsible_person_id"
                            :responsible-person="form.responsible_person"
                            :users="users"
                            :validation-error="validationErrors.responsible_person_id ? validationErrors.responsible_person_id[0] : null"
                            @user-selected="handleUserSelected"
                        />

                        <!-- Add Custom Field Link -->
                        <div class="col-12 mt-2">
                            <a href="#" class="add-custom-field-link" @click.prevent>Add Custom Field</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="modal-footer-custom">
                <!-- Error Message -->
                <div v-if="errorMessage" class="alert alert-danger mb-3" role="alert">
                    {{ errorMessage }}
                </div>
                
                <div class="d-flex align-items-center justify-content-end gap-3">
                    <button class="btn-clear" @click="resetForm" :disabled="isSubmitting">Clear</button>
                    <button 
                        class="btn-next-step" 
                        @click="submitForm"
                        :disabled="isSubmitting"
                    >
                        <span v-if="isSubmitting">Creating...</span>
                        <span v-else>Add</span>
                    </button>
                </div>
            </div>
        </div>
    </b-modal>
</template>

<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { BModal, BFormInput, BFormSelect, BFormTextarea } from 'bootstrap-vue-3'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import api from '@/plugins/axios'
import avatar1 from '@/assets/images/users/user1.png'
import ModalHeader from '../shared/ModalHeader.vue'
import StageSelector from '../shared/StageSelector.vue'
import ResponsiblePersonSelector from '../shared/ResponsiblePersonSelector.vue'

const props = defineProps({
    modelValue: Boolean
})

const emit = defineEmits(['update:modelValue', 'lead-created'])

const show = ref(props.modelValue)
const users = ref([])
const sources = ref([])
const isLoadingUsers = ref(false)
const isLoadingSources = ref(false)
const isSubmitting = ref(false)
const errorMessage = ref('')
const sourceOptions = ref([])
const validationErrors = ref({})

watch(() => props.modelValue, (val) => {
    show.value = val
})

watch(show, (val) => {
    if (val) {
        form.value.stage_id = null
        emit('update:modelValue', null)
    } else {
        // Clear validation errors when modal is closed
        validationErrors.value = {}
        errorMessage.value = ''
    }
})

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

const fetchUsers = async () => {
    try {
        isLoadingUsers.value = true
        const response = await api.get('/available-responsible-persons')
        if (response.data && (response.data.data || response.data).length > 0) {
            users.value = response.data.data || response.data
        }
    } catch (error) {
        console.error('Error fetching users:', error)
    } finally {
        isLoadingUsers.value = false
    }
}

onMounted(() => {
    fetchUsers()
    fetchSources()
})

const form = ref({
    lead_name: '',
    stage_id: null,
    salutation: null,
    first_name: '',
    last_name: '',
    work_phone: '',
    email: '',
    work_phone_2: '',
    comment: '',
    lead_source: '',
    source_information: '',
    bedrooms: null,
    purpose_buying: null,
    responsible_person_id: 1, // Default or selected
    // responsible_person: {
    //     id: 1,
    //     name: 'Ahmad Mahfoz',
    //     email: 'testuseremail@gmail.com',
    //     position: '--',
    //     avatar: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'
    // },
    budget: null,
    currency: "AED",
})


const salutationOptions = [
    // { value: null, text: 'Not Selected' },
    { value: 'Mr', text: 'Mr' },
    { value: 'Ms', text: 'Ms' },
    { value: 'Mrs', text: 'Mrs' }
]

const phoneTypeOptions = [
    { value: 'Work Phone', text: 'Work Phone' },
    { value: 'Mobile', text: 'Mobile' }
]

const currencyOptions = [
    // { value: 'USD', text: 'USD $' },
    { value: 'AED', text: 'AED د.إ' },
]

const emailTypeOptions = [
    { value: 'Work', text: 'Work' },
    { value: 'Personal', text: 'Personal' }
]

const selectorOptions = [
    { value: null, text: 'Not Selected' }
]

const purposeOptions = [
    // { value: null, text: 'Select Purpose' }
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
]

const bedroomOptions = [
    // { value: null, text: 'Select Bedrooms' },
    { value: 'studio', text: 'Studio' },
    { value: 1, text: '1' },
    { value: 2, text: '2' },
    { value: 3, text: '3' },
    { value: 4, text: '4' },
    { value: 5, text: '5' },
    { value: 6, text: '6' },
    { value: 7, text: '7' },
    { value: 8, text: '8' },
    { value: 9, text: '9' },
]

// Handle user selected from ResponsiblePersonSelector
const handleUserSelected = (user) => {
    form.value.responsible_person = user
}

// Helper function to clear error message when all validation errors are fixed
const clearErrorMessageIfNeeded = () => {
    // If there are no more validation errors, clear the general error message
    if (Object.keys(validationErrors.value).length === 0) {
        errorMessage.value = ''
    }
}

// Watch form fields and clear their validation errors when user modifies them
watch(() => form.value.lead_name, () => {
    if (validationErrors.value.lead_name) {
        delete validationErrors.value.lead_name
        clearErrorMessageIfNeeded()
    }
})

watch(() => form.value.first_name, () => {
    if (validationErrors.value.first_name) {
        delete validationErrors.value.first_name
        clearErrorMessageIfNeeded()
    }
})

watch(() => form.value.last_name, () => {
    if (validationErrors.value.last_name) {
        delete validationErrors.value.last_name
        clearErrorMessageIfNeeded()
    }
})

watch(() => form.value.salutation, () => {
    if (validationErrors.value.salutation) {
        delete validationErrors.value.salutation
        clearErrorMessageIfNeeded()
    }
})

watch(() => form.value.email, () => {
    if (validationErrors.value.email) {
        delete validationErrors.value.email
        clearErrorMessageIfNeeded()
    }
})

watch(() => form.value.work_phone, () => {
    if (validationErrors.value.work_phone) {
        delete validationErrors.value.work_phone
        clearErrorMessageIfNeeded()
    }
})

watch(() => form.value.work_phone_2, () => {
    if (validationErrors.value.work_phone_2) {
        delete validationErrors.value.work_phone_2
        clearErrorMessageIfNeeded()
    }
})

watch(() => form.value.comment, () => {
    if (validationErrors.value.comment) {
        delete validationErrors.value.comment
        clearErrorMessageIfNeeded()
    }
})

watch(() => form.value.bedrooms, () => {
    if (validationErrors.value.bedrooms) {
        delete validationErrors.value.bedrooms
        clearErrorMessageIfNeeded()
    }
})

watch(() => form.value.budget, () => {
    if (validationErrors.value.budget) {
        delete validationErrors.value.budget
        clearErrorMessageIfNeeded()
    }
})

watch(() => form.value.currency, () => {
    if (validationErrors.value.currency) {
        delete validationErrors.value.currency
        clearErrorMessageIfNeeded()
    }
})

watch(() => form.value.purpose_buying, () => {
    if (validationErrors.value.purpose_buying) {
        delete validationErrors.value.purpose_buying
        clearErrorMessageIfNeeded()
    }
})

watch(() => form.value.lead_source, () => {
    if (validationErrors.value.lead_source) {
        delete validationErrors.value.lead_source
        clearErrorMessageIfNeeded()
    }
})

watch(() => form.value.source_information, () => {
    if (validationErrors.value.source_information) {
        delete validationErrors.value.source_information
        clearErrorMessageIfNeeded()
    }
})

watch(() => form.value.stage_id, () => {
    if (validationErrors.value.stage_id) {
        delete validationErrors.value.stage_id
        clearErrorMessageIfNeeded()
    }
})

watch(() => form.value.responsible_person_id, () => {
    if (validationErrors.value.responsible_person_id) {
        delete validationErrors.value.responsible_person_id
        clearErrorMessageIfNeeded()
    }
})

const resetForm = () => {
    form.value = {
        lead_name: '',
        stage_id: null,
        salutation: null,
        first_name: '',
        last_name: '',
        work_phone: '',
        email: '',
        work_phone_2: '',
        comment: '',
        lead_source: '',
        source_information: '',
        bedrooms: null,
        purpose_buying: null,
        responsible_person_id: 1,
        budget: null,
        currency: null,
    }
    validationErrors.value = {}
    errorMessage.value = ''
}

const submitForm = async () => {
    try {
        isSubmitting.value = true
        errorMessage.value = ''
        validationErrors.value = {}
        
        const payload = {
            ...form.value,
            responsible_person_id: form.value.responsible_person_id,
            stage_id: form.value.stage_id
        }
        
        const response = await api.post('/leads', payload)
        
        console.log('✅ Lead created successfully:', response.data)
        
        // Success: close modal, reset form, and emit event to refetch leads
        show.value = false
        resetForm()
        
        console.log('📤 Emitting lead-created event to parent')
        emit('lead-created', response.data)
        
        // Show success notification
        $showNotification('Lead created successfully!', 'success')
        
    } catch (error) {
        // Error: show error message, don't close modal, don't reset form
        console.error('❌ Error creating lead:', error)
        
        // Check if it's a validation error (422 status)
        if (error.response && error.response.status === 422) {
            // Laravel validation errors format: { field: ["error message"] }
            const errors = error.response.data.errors || error.response.data
            validationErrors.value = errors
            
            errorMessage.value = 'Please fix the validation errors below.'
            $showNotification('Please check the form for errors', 'warning')
        } else {
            // General error
            errorMessage.value = error.response?.data?.message || 'Failed to create lead. Please try again.'
            $showNotification(errorMessage.value, 'error')
        }
    } finally {
        isSubmitting.value = false
    }
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
.create-lead-modal-content {
    background: #fff;
    border-radius: 12px;
}

.modal-title {
    font-family: Montserrat;
    font-weight: 600;
    font-style: SemiBold;
    font-size: 16px;
    color: #01062C;
}


/* Form Styles */
.step-content .row {
    padding: 20px !important;
    margin: 0 !important;
}

.form-label-custom {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: #000;
    margin-bottom: 8px;
}

.section-title {
    font-family: Montserrat;
    font-weight: 500;
    font-style: Medium;
    font-size: 13px;
    color: #01062C;
}

.custom-textarea {
    height: auto;
    padding: 12px 15px;
}

.form-label-custom {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: #000000;
    margin-bottom: 2px;
}

.custom-input, .custom-textarea {
    height: 42px !important;
    border-radius: 10px !important;
    border: 1px solid #E2E8F0 !important;
    font-size: 13px !important;
    color: #64748B !important;
    font-family: 'Montserrat';
}

.custom-textarea {
    height: 143px !important;
    padding: 12px 15px !important;
}

/* Custom v-select styles to match the image */
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
    /* border-radius: 12px; */
    border: 1px solid #E2E8F0;
    box-shadow: 0px 10px 30px rgba(0, 0, 0, 0.1);
    padding: 0;
    margin-top: 5px;
    z-index: 1100;
}

:deep(.custom-v-select .vs__dropdown-option) {
    padding: 5px 10px;
    /* border-radius: 0px 0px 8px 8px; */
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

/* Inline v-select for input groups */
:deep(.custom-v-select-inline) {
    width: 100px;
    min-width: 100px;
    position: relative;
}

:deep(.custom-v-select-inline .vs__dropdown-toggle) {
    height: 42px !important;
    border: none !important;
    border-left: 1px solid #E2E8F0 !important;
    border-radius: 0 8px 8px 0 !important;
    padding: 0 !important;
    background: #fff !important;
    display: flex;
    align-items: center;
    cursor: pointer;
}

:deep(.custom-v-select-inline .vs__selected-options) {
    padding: 0 0 0 8px !important;
    margin: 0 !important;
    flex-basis: auto !important;
    flex-grow: 1;
    display: flex;
    align-items: center;
    overflow: hidden;
    max-width: calc(100% - 30px);
}

:deep(.custom-v-select-inline .vs__selected) {
    color: #64748B !important;
    font-size: 13px !important;
    margin: 0 !important;
    padding: 0 !important;
    position: static !important;
    line-height: normal !important;
    background: transparent !important;
    border: none !important;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block !important;
}

:deep(.custom-v-select-inline .vs__actions) {
    padding: 0 8px 0 4px !important;
    margin: 0 !important;
    display: flex;
    align-items: center;
    cursor: pointer;
}

:deep(.custom-v-select-inline .vs__search) {
    display: none !important;
}

:deep(.custom-v-select-inline .vs__dropdown-menu) {
    width: 150px !important;
    min-width: 150px !important;
    left: auto !important;
    right: 0 !important;
    /* border-radius: 12px; */
    border: 1px solid #E2E8F0;
    box-shadow: 0px 10px 30px rgba(0, 0, 0, 0.1);
    padding: 0px;
    margin-top: 5px;
    z-index: 9999 !important;
    position: absolute !important;
}

:deep(.custom-v-select-inline .vs__dropdown-option) {
    /* padding: 10px 15px; */
    /* border-radius: 8px; */
    font-size: 14px;
    color: #475569;
    transition: all 0.2s;
    margin: 1px;
}

:deep(.custom-v-select-inline .vs__dropdown-option--highlight) {
    background: #FAA300 !important;
    color: #fff !important;
}

:deep(.custom-v-select-inline .vs__dropdown-option--selected) {
    background: #FAA300;
    color: #fff;
}

:deep(.custom-v-select-inline .vs__open-indicator) {
    cursor: pointer;
    pointer-events: auto;
    display: flex;
    align-items: center;
    justify-content: center;
}

:deep(.custom-v-select-inline .vs__open-indicator > span) {
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

:deep(.custom-v-select-inline .vs__open-indicator-icon) {
    font-size: 16px;
    color: #64748B;
}

.custom-input::placeholder, .custom-textarea::placeholder {
    color: #64748B !important;
    opacity: 1;
    font-size: 13px !important;
    font-family: 'Montserrat';
}

.input-group-custom {
    display: flex;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    overflow: visible;
    align-items: stretch;
    position: relative;
}

.input-group-custom .custom-input {
    border: none !important;
    flex-grow: 1 !important;
    border-radius: 8px 0 0 8px !important;
    padding: 0 8px !important;
}

.modal-footer-custom {
    border-top: 1px solid #F4F4F4;
    padding: 15px;
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

.contact-details-card {
    background: #FFFFFF;
    border: 1px solid #F3F3F3;
    border-radius: 10px;
    box-shadow: 1px 1px 5px 5px #00000005;
}

.add-custom-field-link {
    font-size: 13px;
    color: #3B82F6;
    text-decoration: underline;
    font-family: 'Montserrat';
}

/* Footer Buttons */
.btn-prev {
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
}

.btn-prev:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #F4F4F4;
}

.btn-clear {
    background: #F4F4F4;
    border: none;
    padding: 10px 25px;
    border-radius: 100px;
    font-size: 14px;
    color: #01062C;
    cursor: pointer;
}

.btn-clear:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-next-step {
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

.btn-next-step:hover:not(:disabled) {
    background: #0f172a;
}

.btn-next-step:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}


/* Validation Error Styles */
.custom-input.is-invalid,
.custom-textarea.is-invalid {
    border-color: #DC2626 !important;
    background-color: #FEF2F2 !important;
}

.custom-input.is-invalid:focus,
.custom-textarea.is-invalid:focus {
    border-color: #DC2626 !important;
    box-shadow: 0 0 0 0.2rem rgba(220, 38, 38, 0.1) !important;
}

.invalid-feedback {
    font-size: 12px;
    color: #DC2626;
    margin-top: 4px;
    font-family: 'Montserrat';
}

/* V-Select Validation Styles */
:deep(.custom-v-select.is-invalid-select .vs__dropdown-toggle) {
    border-color: #DC2626 !important;
    background-color: #FEF2F2 !important;
}

:deep(.custom-v-select-inline.is-invalid-select .vs__dropdown-toggle) {
    border-left-color: #DC2626 !important;
    background-color: #FEF2F2 !important;
}

.input-group-custom.is-invalid-group {
    border-color: #DC2626 !important;
}

.input-group-custom.is-invalid-group .custom-input {
    background-color: #FEF2F2 !important;
}

</style>
<style>
    .modal-dialog {
        z-index: 1060 !important;
    }
    .modal-content {
        border-radius: 16px !important;
        border: none !important;
    }
</style>


