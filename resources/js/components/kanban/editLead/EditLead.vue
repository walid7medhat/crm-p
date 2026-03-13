<template>
    <div>
        <div class="info-group edit">
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

        <div class="info-group">
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

        <div class="info-group">
            <label class="form-label-custom">First Name</label>
            <b-form-input 
                v-model="form.first_name" 
                placeholder="Enter Your First Name" 
                class="custom-input"
                :class="{ 'is-invalid': validationErrors.first_name }"
            />
            <div v-if="validationErrors.first_name" class="invalid-feedback d-block">
                {{ validationErrors.first_name[0] }}
            </div>
        </div>

        <div class="info-group">
            <label class="form-label-custom">Last Name</label>
            <b-form-input 
                v-model="form.last_name" 
                placeholder="Enter Your Last Name" 
                class="custom-input"
                :class="{ 'is-invalid': validationErrors.last_name }"
            />
            <div v-if="validationErrors.last_name" class="invalid-feedback d-block">
                {{ validationErrors.last_name[0] }}
            </div>
        </div>

        <div class="info-group">
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

        <div class="info-group">
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

        <div class="info-group">
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

        <!--<div class="info-group">-->
        <!--    <label class="form-label-custom">Comment</label>-->
        <!--    <b-form-textarea -->
        <!--        v-model="form.comment" -->
        <!--        placeholder="Text Here" -->
        <!--        rows="4" -->
        <!--        class="custom-textarea"-->
        <!--        :class="{ 'is-invalid': validationErrors.comment }"-->
        <!--    ></b-form-textarea>-->
        <!--    <div v-if="validationErrors.comment" class="invalid-feedback d-block">-->
        <!--        {{ validationErrors.comment[0] }}-->
        <!--    </div>-->
        <!--</div>-->

        <div class="info-group">
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

        <div class="info-group">
            <label class="form-label-custom">Bedrooms</label>
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

        <div class="info-group">
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

        <div class="info-group">
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

        <div class="info-group mb-3">
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

        <!-- Responsible Person -->
        <div class="responsible-person-box p-3 radius-8 shadow-sm mb-3">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <label class="info-label mb-0">Responsible Person</label>
                <div class="d-flex flex-column align-items-end">
                    <b-dropdown 
                        variant="link" 
                        toggle-class="text-decoration-none p-0 no-caret-custom" 
                        no-caret
                        right
                        class="change-person-dropdown"
                    >
                        <template #button-content>
                            <div class="btn-change-person-text">
                                <iconify-icon icon="lucide:user-plus" class="change-person-icon"></iconify-icon>
                                <span>Change Person</span>
                            </div>
                        </template>
                    
                    <div class="dropdown-search-wrapper p-3">
                        <div class="d-flex align-items-center justify-content-between border-bottom mb-3 pb-2">
                            <span class="modal-title-dropdown">Change Responsible Person</span>
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
                                    </div>
                                </div>
                                <iconify-icon 
                                    v-if="form.responsible_person_id === user.id" 
                                    icon="lucide:check" 
                                    class="text-warning"
                                ></iconify-icon>
                            </div>
                            <div v-if="filteredUsers?.length === 0" class="text-center p-3 text-muted">
                                No persons found
                            </div>
                                        </div>
                                    </div>
                                </b-dropdown>
                    <div v-if="validationErrors.responsible_person_id" class="invalid-feedback d-block" style="margin-top: 4px;">
                        {{ validationErrors.responsible_person_id[0] }}
                    </div>
                </div>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-wrapper">
                    <img 
                        v-if="!avatarError && selectedPerson?.avatar" 
                        :src="selectedPerson.avatar" 
                        class="avatar-md rounded-circle" 
                        @error="handleAvatarError"
                    />
                    <div v-else class="avatar-placeholder">
                        <iconify-icon icon="lucide:user" class="avatar-icon"></iconify-icon>
                    </div>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex mb-1">
                        <span class="text-xs text-secondary-light">Name</span>
                        <span class="text-xs fw-medium">: {{ selectedPerson?.name || '----' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { BFormInput, BFormTextarea, BDropdown } from 'bootstrap-vue-3'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import api from '@/plugins/axios'

const props = defineProps({
    lead: {
        type: Object,
        required: true
    },
    stageId: {
        type: Number,
        default: null
    }
})

const emit = defineEmits(['save', 'cancel', 'updated'])

const avatarError = ref(false)
const users = ref([])
const searchQuery = ref('')
const selectedPerson = ref(null)
const validationErrors = ref({})
const isSubmitting = ref(false)
const errorMessage = ref('')

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
    budget: null,
    currency: 'AED',
    bedrooms: null,
    purpose_buying: null,
    lead_source: '',
    source_information: '',
    responsible_person_id: null
})

const stageOptions = ref([
    { value: 1, text: 'New' },
    { value: 2, text: 'Contacted' },
    { value: 3, text: 'Qualified' },
    { value: 4, text: 'Proposal' },
    { value: 5, text: 'Negotiation' },
    { value: 6, text: 'Won' },
    { value: 7, text: 'Lost' }
])

const salutationOptions = [
    { value: 'Mr', text: 'Mr' },
    { value: 'Ms', text: 'Ms' },
    { value: 'Mrs', text: 'Mrs' }
]

const currencyOptions = [
    // { value: 'USD', text: 'USD $' },
    { value: 'AED', text: 'AED د.إ' },
]

const bedroomOptions = [
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

const purposeOptions = [
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

const sourceOptions = ref([
    { value: 'Website', text: 'Website' },
    { value: 'Referral', text: 'Referral' },
    { value: 'Social Media', text: 'Social Media' },
    { value: 'Advertisement', text: 'Advertisement' },
    { value: 'Cold Call', text: 'Cold Call' },
    { value: 'Other', text: 'Other' }
])

// Fetch users from API
const fetchUsers = async () => {
    try {
        const response = await api.get('/available-responsible-persons')
        if (response.data && (response.data.data || response.data)?.length > 0) {
            users.value = response.data.data || response.data
        }
    } catch (error) {
        console.error('Error fetching users:', error)
    }
}

// Filtered users based on search query
const filteredUsers = computed(() => {
    if (!searchQuery.value) return users.value
    return users.value.filter(user => 
        user.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        user.email.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
})

// Select user function
const selectUser = (user) => {
    form.value.responsible_person_id = user.id
    selectedPerson.value = user
    searchQuery.value = ''
}

// Initialize form with lead data
const initializeForm = () => {
    form.value = {
        lead_name: props.lead?.lead_name || '',
        stage_id: props.stageId || props.lead?.stage?.id || null,
        salutation: props.lead?.salutation || null,
        first_name: props.lead?.first_name || '',
        last_name: props.lead?.last_name || '',
        work_phone: props.lead?.work_phone || '',
        email: props.lead?.email || '',
        work_phone_2: props.lead?.work_phone_2 || '',
        comment: props.lead?.comment || '',
        budget: props.lead?.budget || null,
        currency: props.lead?.currency || 'AED',
        bedrooms: props.lead?.bedrooms || null,
        purpose_buying: props.lead?.purpose_buying || null,
        lead_source: props.lead?.lead_source || '',
        source_information: props.lead?.source_information || '',
        responsible_person_id: props.lead?.responsible_person?.id || null
    }
    selectedPerson.value = props.lead?.responsible_person || null
}

// Watch for stageId changes from parent
watch(() => props.stageId, (newStageId) => {
    if (newStageId) {
        form.value.stage_id = newStageId
    }
})

// Watch for lead changes to reset avatar error and update selected person
watch(() => props.lead?.responsible_person, (newPerson) => {
    avatarError.value = false
    if (newPerson) {
        selectedPerson.value = newPerson
    }
})

// Helper function to clear error message when all validation errors are fixed
const clearErrorMessageIfNeeded = () => {
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

watch(() => form.value.stage_id, () => {
    if (validationErrors.value.stage_id) {
        delete validationErrors.value.stage_id
        clearErrorMessageIfNeeded()
    }
})

watch(() => form.value.salutation, () => {
    if (validationErrors.value.salutation) {
        delete validationErrors.value.salutation
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

watch(() => form.value.work_phone, () => {
    if (validationErrors.value.work_phone) {
        delete validationErrors.value.work_phone
        clearErrorMessageIfNeeded()
    }
})

watch(() => form.value.email, () => {
    if (validationErrors.value.email) {
        delete validationErrors.value.email
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

watch(() => form.value.bedrooms, () => {
    if (validationErrors.value.bedrooms) {
        delete validationErrors.value.bedrooms
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

watch(() => form.value.responsible_person_id, () => {
    if (validationErrors.value.responsible_person_id) {
        delete validationErrors.value.responsible_person_id
        clearErrorMessageIfNeeded()
    }
})

// Initialize on mount
onMounted(() => {
    initializeForm()
    fetchUsers()
})

const handleAvatarError = () => {
    avatarError.value = true
}

const handleCancel = () => {
    emit('cancel')
}

const handleSave = async () => {
    try {
        isSubmitting.value = true
        errorMessage.value = ''
        validationErrors.value = {}
        
        const payload = {
            ...form.value,
            responsible_person_id: form.value.responsible_person_id,
            stage_id: form.value.stage_id
        }
        
        console.log('Updating lead with data:', payload)
        const response = await api.put(`/leads/${props.lead.id}`, payload)
        
        console.log('✅ Lead updated successfully:', response.data)
        
        // Show success notification
        if (window.$showNotification) {
            window.$showNotification('Lead updated successfully!', 'success')
        }
        
        // Emit the updated lead data to parent component
        emit('updated', response.data)
        
        // Also emit save for backwards compatibility
        emit('save', response.data)
        
    } catch (error) {
        console.error('❌ Error updating lead:', error)
        
        // Check if it's a validation error (422 status)
        if (error.response && error.response.status === 422) {
            // Laravel validation errors format: { field: ["error message"] }
            const errors = error.response.data.errors || error.response.data
            validationErrors.value = errors
            
            errorMessage.value = 'Please fix the validation errors below.'
            if (window.$showNotification) {
                window.$showNotification('Please check the form for errors', 'warning')
            }
        } else {
            // General error
            errorMessage.value = error.response?.data?.message || 'Failed to update lead. Please try again.'
            if (window.$showNotification) {
                window.$showNotification(errorMessage.value, 'error')
            }
        }
    } finally {
        isSubmitting.value = false
    }
}

defineExpose({
    handleCancel,
    handleSave,
    isSubmitting
})
</script>

<style scoped>
.info-group {
    margin-bottom: 15px;
}

/* Form Styles for Edit Mode */
.form-label-custom {
    display: block;
    font-size: 12px;
    font-weight: 300;
    color: #666666;
    margin-top: 5px;
    margin-bottom: 5px;
    line-height: 10px;
}

.info-label {
    display: block;
    font-size: 12px;
    font-weight: 300;
    color: #666666;
    margin-top: 5px;
    line-height: 10px;
}

.custom-input, .custom-textarea {
    height: 42px !important;
    border-radius: 10px !important;
    border: 1px solid #E2E8F0 !important;
    font-size: 13px !important;
    color: #000000 !important;
    font-family: 'Montserrat';
}

.custom-textarea {
    height: 100px !important;
    padding: 12px 15px !important;
}

.custom-input::placeholder, .custom-textarea::placeholder {
    color: #64748B !important;
    opacity: 1;
    font-size: 13px !important;
    font-family: 'Montserrat';
}

/* Custom v-select styles */
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

/* Inline v-select for input groups */
.input-group-custom {
    display: flex;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    overflow: visible;
    align-items: stretch;
    position: relative;
}

.input-group-custom .custom-input {
    border: none !important;
    flex-grow: 1 !important;
    border-radius: 10px 0 0 10px !important;
    padding: 0 8px !important;
}

:deep(.custom-v-select-inline) {
    width: 100px;
    min-width: 100px;
    position: relative;
}

:deep(.custom-v-select-inline .vs__dropdown-toggle) {
    height: 42px !important;
    border: none !important;
    border-left: 1px solid #E2E8F0 !important;
    border-radius: 0 10px 10px 0 !important;
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
    border: 1px solid #E2E8F0;
    box-shadow: 0px 10px 30px rgba(0, 0, 0, 0.1);
    padding: 0px;
    margin-top: 5px;
    z-index: 9999 !important;
    position: absolute !important;
}

:deep(.custom-v-select-inline .vs__dropdown-option) {
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

.responsible-person-box {
    background: #fff;
    border: 1px solid #F3F3F3;
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.03);
}

.avatar-wrapper {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
}

.avatar-md {
    width: 48px;
    height: 48px;
    object-fit: cover;
}

.avatar-placeholder {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #F3F4F6;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #E5E7EB;
}

.avatar-icon {
    font-size: 24px;
    color: #9CA3AF;
}

.modal-footer-custom {
    padding-top: 15px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 16px;
    border-top: 1px solid #F1F5F9;
}

.btn-cancel {
    background: #F4F4F4;
    border: none;
    padding: 5px 20px;
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
    padding: 5px 20px;
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

.radius-8 { 
    border-radius: 8px; 
}

/* Change Person Button */
.btn-change-person-text {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #1C274C;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: color 0.2s;
    font-family: 'Montserrat';
}

.btn-change-person-text:hover {
    color: #E89200;
}

.change-person-icon {
    font-size: 14px;
    color: #FAA300;
    transition: color 0.2s;
}

.btn-change-person-text:hover .change-person-icon {
    color: #E89200;
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

.modal-title-dropdown {
    font-family: Montserrat;
    font-weight: 500;
    font-style: Medium;
    font-size: 14px;
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

.text-warning {
    color: #FAA300;
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

.btn-cancel:disabled,
.btn-save:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
