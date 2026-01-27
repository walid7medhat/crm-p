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

            <!-- Form Content -->
            <div class="form-scroll-area">
                <div class="step-content">
                    <div class="row g-4 p-4 position-relative">
                        <!-- Lead Name -->
                        <div class="col-12">
                            <label class="form-label-custom">Lead Name</label>
                            <b-form-input v-model="form.lead_name" placeholder="Enter Lead Name" class="custom-input" />
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
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                            </v-select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">First Name</label>
                            <b-form-input v-model="form.first_name" placeholder="Enter Your First Name *" class="custom-input" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">Last Name</label>
                            <b-form-input v-model="form.last_name" placeholder="Enter Your Last Name *" class="custom-input" />
                        </div>
                        <div class="col-md-3">
                            <!-- Field removed as per new form structure -->
                        </div>

                        <!-- Contact Details Section -->
                            <div class="contact-details-card p-3">
                                <span class="section-title d-block mb-3">Contact Details</span>
                                <div class="d-flex justify-content-between align-items-center gap-2">
                                    <div class="col">
                                        <label class="form-label-custom">Contact</label>
                                        <b-form-input v-model="form.whatsapp_number" placeholder="Enter Phone Number" class="custom-input" />
                                    </div>
                                    <div class="col">
                                        <label class="form-label-custom">Email</label>
                                        <b-form-input v-model="form.email" placeholder="Enter Your Email" class="custom-input" />
                                    </div>
                                    <div class="col">
                                        <label class="form-label-custom">Secondary Phone</label>
                                        <b-form-input v-model="form.work_phone_2" placeholder="Enter Phone Number" class="custom-input" />
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
                            ></b-form-textarea>
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
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                            </v-select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">Budget</label>
                            <div class="input-group-custom">
                                <b-form-input v-model="form.budget"  type="number" placeholder="Enter Budget" class="custom-input" />
                                <v-select 
                                    v-model="form.currency" 
                                    :options="currencyOptions" 
                                    :reduce="option => option.value"
                                    label="text"
                                    :clearable="false"
                                    :searchable="false"
                                    class="custom-v-select-inline"
                                >
                                    <template #open-indicator="{ attributes }">
                                        <span v-bind="attributes">
                                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                        </span>
                                    </template>
                                </v-select>
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
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                            </v-select>
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
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                            </v-select>
                        </div>

                        <!-- Source Information -->
                        <div class="col-12">
                            <label class="form-label-custom">Source Information</label>
                            <b-form-textarea v-model="form.source_information" placeholder="Text Here" class="custom-textarea" />
                        </div>

                        <!-- Responsible Person Card -->
                        <div class="col-12 mt-3">
                            <div class="responsible-person-card p-3">
                                <span class="section-title d-block mb-3">Responsible Person</span>
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-wrapper">
                                            <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s" alt="Avatar" class="responsible-avatar" />
                                        </div>
                                        <div class="responsible-info">
                                            <div class="info-row">
                                                <span class="info-label">Name</span>
                                                <span class="info-separator">:</span>
                                                <span class="info-value fw-bold">{{ form.responsible_person?.name || '--' }}</span>
                                            </div>
                                            <div class="info-row">
                                                <span class="info-label">Email</span>
                                                <span class="info-separator">:</span>
                                                <span class="info-value">{{ form.responsible_person?.email || '--' }}</span>
                                            </div>
                                            <div class="info-row">
                                                <span class="info-label">Position</span>
                                                <span class="info-separator">:</span>
                                                <span class="info-value">{{ form.responsible_person?.position || '--' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="department-badge">
                                            Department : Sales
                                        </div>
                                        <b-dropdown 
                                            variant="link" 
                                            toggle-class="text-decoration-none p-0 no-caret-custom" 
                                            no-caret
                                            right
                                            class="change-person-dropdown"
                                        >
                                            <template #button-content>
                                                <button class="btn-change-person">
                                                    Change Person
                                                    <iconify-icon icon="lucide:user-plus" class="ms-1"></iconify-icon>
                                                </button>
                                            </template>
                                            
                                            <div class="dropdown-search-wrapper p-3">
                                                <div class="d-flex align-items-center justify-content-between border-bottom mb-3">
                                                    <span class="modal-title-dropdown">Change Responsible Person</span>
                                                    <button class="close-btn-top" @click="show = false">
                                                        <iconify-icon icon="lucide:x"></iconify-icon>
                                                    </button>
                                                </div>
                                                <div class="search-input-wrapper mb-3">
                                                    <b-form-input 
                                                        v-model="searchQuery" 
                                                        placeholder="Search Person" 
                                                        class="dropdown-search-input"
                                                    />
                                                    <iconify-icon icon="lucide:search" class="search-icon"></iconify-icon>
                                                </div>
                                                
                                                <div class="user-list-scroll">
                                                    <div 
                                                        v-for="user in filteredUsers" 
                                                        :key="user.id"
                                                        class="user-item d-flex align-items-center justify-content-between p-2"
                                                        @click="selectUser(user)"
                                                        :class="{ 'selected': form.responsible_person_id === user.id }"
                                                    >
                                                        <div class="d-flex align-items-center gap-2">
                                                            <img :src="user.avatar || 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'" class="user-item-avatar" />
                                                            <div class="user-item-info">
                                                                <div class="user-item-name">{{ user.name }}</div>
                                                                <div class="user-item-email">{{ user.email }}</div>
                                                            </div>
                                                        </div>
                                                        <iconify-icon 
                                                            v-if="form.responsible_person_id === user.id" 
                                                            icon="lucide:check" 
                                                            class="text-warning"
                                                        ></iconify-icon>
                                                    </div>
                                                    <div v-if="filteredUsers.length === 0" class="text-center p-3 text-muted">
                                                        No persons found
                                                    </div>
                                                </div>
                                            </div>
                                        </b-dropdown>
                                    </div>
                                </div>
                            </div>
                        </div>

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
import { BModal, BFormInput, BFormSelect, BFormTextarea, BDropdown, BDropdownItem } from 'bootstrap-vue-3'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import api from '@/plugins/axios'
import avatar1 from '@/assets/images/users/user1.png'
import ModalHeader from './ModalHeader.vue'
import StageSelector from './StageSelector.vue'

const props = defineProps({
    modelValue: Boolean
})

const emit = defineEmits(['update:modelValue', 'lead-created'])

const show = ref(props.modelValue)
const users = ref([])
const sources = ref([])
const searchQuery = ref('')
const isLoadingUsers = ref(false)
const isLoadingSources = ref(false)
const isSubmitting = ref(false)
const errorMessage = ref('')
const sourceOptions = ref([])

watch(() => props.modelValue, (val) => {
    show.value = val
})

watch(show, (val) => {
    if (val) {
        form.value.stage_id = null
        emit('update:modelValue', null)
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
                    value: source.id,
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

const filteredUsers = computed(() => {
    if (!searchQuery.value) return users.value
    return users.value.filter(user => 
        user.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        user.email.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
})

const selectUser = (user) => {
    form.value.responsible_person_id = user.id
    form.value.responsible_person = user
}

const form = ref({
    lead_name: '',
    stage_id: null,
    salutation: null,
    first_name: '',
    last_name: '',
    whatsapp_number: '',
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
    currency: null,
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
    { value: 'USD', text: 'USD $' },
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
const resetForm = () => {
    form.value = {
        lead_name: '',
        stage_id: null,
        salutation: null,
        first_name: '',
        last_name: '',
        whatsapp_number: '',
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
}

const submitForm = async () => {
    try {
        isSubmitting.value = true
        errorMessage.value = ''
        
        const payload = {
            ...form.value,
            responsible_person_id: form.value.responsible_person_id,
            stage_id: form.value.stage_id
        }
        
        const response = await api.post('/leads', payload)
        
        // Success: close modal, reset form, and emit event to refetch leads
        show.value = false
        resetForm()
        emit('lead-created')
        
    } catch (error) {
        // Error: show error message, don't close modal, don't reset form
        console.error('Error creating lead:', error)
        errorMessage.value = error.response?.data?.message || 'Failed to create lead. Please try again.'
    } finally {
        isSubmitting.value = false
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

.modal-title-dropdown {
    font-family: Montserrat;
    font-weight: 500;
    font-style: Medium;
    font-size: 14px;
}

.border-bottom {
    border-bottom: 1px solid #F4F4F4;
}

.close-btn-top {
    background: transparent;
    font-size: 20px;
    color: #000;
    font-weight: 500;
    cursor: pointer;
    margin-bottom: 10px;
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

.responsible-person-card {
    background: #FFFFFF;
    border: 1px solid #F3F3F3;
    border-radius: 10px;
    box-shadow: 1px 1px 5px 5px #00000005;
}

.responsible-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
}

.responsible-info {
    font-family: 'Montserrat';
    font-size: 14px;
}

.info-row {
    display: flex;
    align-items: center;
    margin-bottom: 4px;
}

.info-label {
    width: 60px;
    color: #64748B;
}

.info-separator {
    margin: 0 8px;
    color: #64748B;
}

.info-value {
    color: #01062C;
}

.department-badge {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    padding: 8px 20px;
    border-radius: 100px;
    font-size: 13px;
    color: #475569;
}

.btn-change-person {
    background:#FAA300;
    border: none;
    padding: 8px 20px;
    border-radius: 100px;
    font-size: 14px;
    color: #FFFFFF;
    display: flex;
    align-items: center;
    cursor: pointer;
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

/* Dropdown Styles */
:deep(.change-person-dropdown .dropdown-toggle::after) {
    display: none !important;
}

:deep(.change-person-dropdown .dropdown-menu) {
    width: 380px;
    border-radius: 12px;
    border: 1px solid #E2E8F0;
    box-shadow: 0px 10px 30px rgba(0, 0, 0, 0.1);
    padding: 0;
    margin-top: 10px;
}

.dropdown-header-title {
    font-family: 'Montserrat';
    font-weight: 600;
    font-size: 16px;
    color: #01062C;
}

.search-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.dropdown-search-input {
    height: 45px !important;
    border-radius: 25px !important;
    padding-left: 20px !important;
    padding-right: 45px !important;
    border: 1px solid #E2E8F0 !important;
    font-size: 14px !important;
}

.search-icon {
    position: absolute;
    right: 15px;
    color: #FAA300;
    font-size: 20px;
}

.user-list-scroll {
    max-height: 300px;
    overflow-y: auto;
    padding-right: 5px;
}

/* Custom Scrollbar */
.user-list-scroll::-webkit-scrollbar {
    width: 4px;
}

.user-list-scroll::-webkit-scrollbar-track {
    background: #F1F5F9;
    border-radius: 10px;
}

.user-list-scroll::-webkit-scrollbar-thumb {
    background: #CBD5E1;
    border-radius: 10px;
}

.user-item {
    cursor: pointer;
    border-radius: 8px;
    transition: background 0.2s;
    margin-bottom: 4px;
}

.user-item:hover {
    background: #F8FAFC;
}

.user-item.selected {
    background: #FFFBEB;
}

.user-item-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}

.user-item-name {
    font-weight: 600;
    font-size: 14px;
    color: #01062C;
    font-family: 'Montserrat';
}

.user-item-email {
    font-size: 12px;
    color: #64748B;
    font-family: 'Montserrat';
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


