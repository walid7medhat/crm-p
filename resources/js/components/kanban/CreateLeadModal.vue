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
        <div class="create-lead-modal-content p-4">
            <!-- Header with Close Button -->
            <div class="d-flex justify-content-between align-items-center px-3 border-bottom">
                <span class="modal-title">Create New Lead</span>
                <button class="close-btn-top" @click="show = false">
                    <iconify-icon icon="lucide:x"></iconify-icon>
                </button>
            </div>

            <!-- Stepper -->
            <div class="stepper-container my-3">
                <div class="stepper-wrapper d-flex align-items-center justify-content-center">
                    <div class="step-item" :class="{ 'active': currentStep >= 1 }">
                        <div class="step-circle">1</div>
                        <div class="step-label">General Information</div>
                    </div>
                    <div class="step-line"></div>
                    <div class="step-item" :class="{ 'active': currentStep >= 2 }">
                        <div class="step-circle">2</div>
                        <div class="step-label">More Information</div>
                    </div>
                </div>
            </div>

            <!-- Form Content -->
            <div class="form-scroll-area">
                <div v-if="currentStep === 1" class="step-content">
                    <div class="row g-4 p-4 position-relative">
                        <!-- Lead Name -->
                        <div class="col-md-6">
                            <label class="form-label-custom">Lead Name</label>
                            <b-form-input v-model="form.leadName" placeholder="Enter Lead Name" class="custom-input" />
                        </div>
                        <!-- Stage -->
                        <div class="col-md-6">
                            <label class="form-label-custom">Stage</label>
                            <b-form-select v-model="form.stage" :options="stageOptions" class="custom-select" />
                        </div>

                        <!-- Salutation, First Name, Last Name, Position -->
                        <div class="col-md-3">
                            <label class="form-label-custom">Salutatione</label>
                            <b-form-select v-model="form.salutation" :options="salutationOptions" class="custom-select" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">First Name</label>
                            <b-form-input v-model="form.firstName" placeholder="Enter Your First Name *" class="custom-input" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">Last Name</label>
                            <b-form-input v-model="form.lastName" placeholder="Enter Your Last Name *" class="custom-input" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">Position</label>
                            <b-form-input v-model="form.position" placeholder="Enter Your Position" class="custom-input" />
                        </div>

                        <!-- Contact Details Section -->
                            <div class="contact-details-card p-3">
                                <span class="section-title d-block">Contact Details</span>
                                <div class="d-flex justify-content-between align-items-center gap-2">
                                    <div class="col">
                                        <label class="form-label-custom">Contact</label>
                                        <div class="input-group-custom">
                                            <b-form-input v-model="form.phone" placeholder="Enter Phone Number" class="custom-input" />
                                            <b-form-select v-model="form.phoneType" :options="phoneTypeOptions" class="custom-select" />
                                        </div>
                                    </div>
                                    <div class="col">
                                        <label class="form-label-custom">Email</label>
                                        <div class="input-group-custom">
                                            <b-form-input v-model="form.email" placeholder="Enter Your Email" class="custom-input" />
                                            <b-form-select v-model="form.emailType" :options="emailTypeOptions" class="custom-select" />
                                        </div>
                                    </div>
                                    <div class="col">
                                        <label class="form-label-custom">Secondary Phone</label>
                                        <b-form-input v-model="form.secondaryPhone" placeholder="Enter Phone Number" class="custom-input" />
                                    </div>
                                </div>
                            </div>

                        <!-- Comments -->
                        <div class="col-12">
                            <label class="form-label-custom">Comments</label>
                            <b-form-textarea 
                                v-model="form.comments" 
                                placeholder="Text Here" 
                                rows="4" 
                                class="custom-textarea"
                            ></b-form-textarea>
                        </div>

                        <!-- Selector, Ad ID, Purpose Of Purchase -->
                        <div class="col-md-4">
                            <label class="form-label-custom">Selector</label>
                            <b-form-select v-model="form.selector" :options="selectorOptions" class="custom-select" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-custom">Ad ID</label>
                            <b-form-input v-model="form.adId" placeholder="Enter Ad Id" class="custom-input" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-custom">Purpose Of Purchase</label>
                            <b-form-select v-model="form.purpose" :options="purposeOptions" class="custom-select" />
                        </div>
                    </div>
                </div>

                <div v-if="currentStep === 2" class="step-content">
                    <div class="row g-4 p-4 position-relative">
                        <!-- Source -->
                        <div class="col-md-4">
                            <label class="form-label-custom">Source</label>
                            <b-form-select v-model="form.source" :options="sourceOptions" class="custom-select" />
                        </div>
                        <!-- Lead Source -->
                        <div class="col-md-4">
                            <label class="form-label-custom">Lead Source</label>
                            <b-form-input v-model="form.leadSource" placeholder="Add Lead Source" class="custom-input" />
                        </div>
                        <!-- Address -->
                        <div class="col-md-4">
                            <label class="form-label-custom">Address</label>
                            <b-form-input v-model="form.address" placeholder="Enter Address" class="custom-input" />
                        </div>

                        <!-- Source Information -->
                        <div class="col-12">
                            <label class="form-label-custom">Source Information</label>
                            <b-form-textarea v-model="form.sourceInfo" placeholder="Text Here" class="custom-textarea" />
                        </div>

                        <!-- How Many Bedrooms -->
                        <div class="col-md-6">
                            <label class="form-label-custom">How Many Bedrooms</label>
                            <b-form-select v-model="form.bedrooms" :options="bedroomOptions" class="custom-select" />
                        </div>
                        <!-- Interested In -->
                        <div class="col-md-6">
                            <label class="form-label-custom">Intrested In</label>
                            <b-form-select v-model="form.interestedIn" :options="interestOptions" class="custom-select" />
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
            <div class="modal-footer-custom d-flex align-items-center justify-content-between">
                <button 
                    class="btn-prev" 
                    :disabled="currentStep === 1"
                    @click="currentStep--"
                >
                    <iconify-icon icon="lucide:chevron-left" class="me-1"></iconify-icon>
                    Previous
                </button>
                <div class="d-flex gap-3">
                    <button class="btn-clear" @click="resetForm">Clear</button>
                    <button 
                        v-if="currentStep < 2" 
                        class="btn-next-step" 
                        @click="currentStep++"
                    >
                        Next Step
                        <iconify-icon icon="lucide:chevron-right" class="ms-1"></iconify-icon>
                    </button>
                    <button 
                        v-else 
                        class="btn-next-step" 
                        @click="submitForm"
                    >
                        Create Lead
                    </button>
                </div>
            </div>
        </div>
    </b-modal>
</template>

<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { BModal, BFormInput, BFormSelect, BFormTextarea, BDropdown, BDropdownItem } from 'bootstrap-vue-3'
import api from '@/plugins/axios'
import avatar1 from '@/assets/images/users/user1.png'

const props = defineProps({
    modelValue: Boolean
})

const emit = defineEmits(['update:modelValue'])

const show = ref(props.modelValue)
const currentStep = ref(1)
const users = ref([
    {
        id: 1,
        name: 'Maria Guan',
        email: 'mariaguan@gmail.com',
        position: 'Sales Agent',
        avatar: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'
    },
    {
        id: 2,
        name: 'Chris Lynch',
        email: 'chrislynch@gmail.com',
        position: 'Team Lead',
        avatar: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'
    },
    {
        id: 3,
        name: 'Dia Lewis',
        email: 'dialewis@gmail.com',
        position: 'Manager',
        avatar: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'
    },
    {
        id: 4,
        name: 'Brian Williams',
        email: 'brianwilliams@gmail.com',
        position: 'Sales Agent',
        avatar: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'
    }
])
const searchQuery = ref('')
const isLoadingUsers = ref(false)

watch(() => props.modelValue, (val) => {
    show.value = val
    if (val) currentStep.value = 1
})

watch(show, (val) => {
    emit('update:modelValue', val)
})

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
    leadName: '',
    stage: 'New leads',
    salutation: null,
    firstName: '',
    lastName: '',
    position: '',
    phone: '',
    phoneType: 'Work Phone',
    email: '',
    emailType: 'Work',
    secondaryPhone: '',
    comments: '',
    selector: null,
    adId: '',
    purpose: null,
    source: null,
    leadSource: '',
    address: '',
    sourceInfo: '',
    bedrooms: null,
    interestedIn: null,
    responsible_person_id: 1, // Default or selected
    responsible_person: {
        id: 1,
        name: 'Ahmad Mahfoz',
        email: 'testuseremail@gmail.com',
        position: '--',
        avatar: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'
    }
})

const stageOptions = [
    { value: 'New leads', text: 'New leads' }
]

const salutationOptions = [
    { value: null, text: 'Not Selected' },
    { value: 'Mr', text: 'Mr' },
    { value: 'Ms', text: 'Ms' },
    { value: 'Mrs', text: 'Mrs' }
]

const phoneTypeOptions = [
    { value: 'Work Phone', text: 'Work Phone' },
    { value: 'Mobile', text: 'Mobile' }
]

const emailTypeOptions = [
    { value: 'Work', text: 'Work' },
    { value: 'Personal', text: 'Personal' }
]

const selectorOptions = [
    { value: null, text: 'Not Selected' }
]

const purposeOptions = [
    { value: null, text: 'Select Purpose' }
]

const sourceOptions = [
    { value: null, text: 'Select Source' }
]

const bedroomOptions = [
    { value: null, text: 'Select Bedrooms' }
]

const interestOptions = [
    { value: null, text: 'Select Interest' }
]

const resetForm = () => {
    form.value = {
        leadName: '',
        stage: 'New leads',
        salutation: null,
        firstName: '',
        lastName: '',
        position: '',
        phone: '',
        phoneType: 'Work Phone',
        email: '',
        emailType: 'Work',
        secondaryPhone: '',
        comments: '',
        selector: null,
        adId: '',
        purpose: null,
        source: null,
        leadSource: '',
        address: '',
        sourceInfo: '',
        bedrooms: null,
        interestedIn: null
    }
}

const submitForm = () => {
    const payload = {
        ...form.value,
        responsible_person_id: form.value.responsible_person_id
    }
    console.log('Form submitted:', payload)
    show.value = false
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

/* Stepper Styles */
.stepper-container {
    padding: 0 40px;
}

.stepper-wrapper {
    position: relative;
    max-width: 335px;
    margin: 0 auto;
}

.step-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 2;
    flex: 0 0 150px;
}

.step-circle {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #D8D8D8;
    color: #01062C;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 8px;
    border: 1px solid #E2E8F0;
    box-shadow: 0 0 0 2px #fff, 0 0 0 3px #D8D8D8;
}

.step-item.active .step-circle {
    background: #01062C;
    color: #fff;
    border-color: #01062C;
    box-shadow: 0 0 0 2px #fff, 0 0 0 3px #FAA300;
}

.step-label {
    font-size: 13px;
    font-weight: 500;
    color: #94A3B8;
    white-space: nowrap;
}

.step-item.active .step-label {
    color: #01062C;
}

.step-line {
    flex: 1;
    height: 1px;
    border-top: 1px dashed #666666;
    margin: 0 -30px;
    margin-bottom: 25px;
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

.custom-select {
    height: 42px !important;
    border-radius: 10px !important;
    border: 1px solid #E2E8F0 !important;
    font-size: 13px !important;
    color: #64748B !important;
    font-family: 'Montserrat';
    appearance: none;
    background-color: #fff !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m7 15 5 5 5-5'/%3E%3Cpath d='m7 9 5-5 5 5'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 16px;
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
    overflow: hidden;
}

.input-group-custom .custom-input {
    border: none !important;
    flex-grow: 1 !important;
}

.input-group-custom .custom-select {
    border: none !important;
    border-left: 1px solid #E2E8F0 !important;
    border-radius: 0 !important;
    width: 130px;
    background: #FFFF !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m7 15 5 5 5-5'/%3E%3Cpath d='m7 9 5-5 5 5'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 12px center !important;
    background-size: 16px !important;
}

.modal-footer-custom {
    border-top: 1px solid #F4F4F4;
    padding: 15px;
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
}

.btn-next-step:hover {
    background: #0f172a;
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


