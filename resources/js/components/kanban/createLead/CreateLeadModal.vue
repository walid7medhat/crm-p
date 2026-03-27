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
                                        <label class="form-label-custom">Primary Phone</label>
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
                                        <label class="form-label-custom">Primary Email</label>
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
                                        <label class="form-label-custom">Secondary Email</label>
                                        <b-form-input 
                                            v-model="form.secondary_email" 
                                            placeholder="Enter Your Secondary Email" 
                                            class="custom-input"
                                            :class="{ 'is-invalid': validationErrors.secondary_email }"
                                        />
                                        <div v-if="validationErrors.secondary_email" class="invalid-feedback d-block">
                                            {{ validationErrors.secondary_email[0] }}
                                        </div>
                                    </div>
                                    <div class="col">
                                        <label class="form-label-custom">Secondary Phone</label>
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

                        <!-- Additional Fields Builder -->
                        <div class="col-12">
                            <div class="additional-fields-card p-3">
                                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                    <div>
                                        <span class="section-title d-block">Additional fields</span>
                                        <small class="text-secondary-light d-block">Show only what you need. Add/remove fields anytime.</small>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="showAdditionalPanel = !showAdditionalPanel">
                                        {{ showAdditionalPanel ? 'Hide fields' : 'Add fields' }}
                                    </button>
                                </div>

                                <!-- Always-visible summary of selected fields -->
                                <div class="selected-fields-row" v-if="selectedAdditionalSummary.length">
                                    <div class="selected-pill" v-for="k in selectedAdditionalSummary" :key="k">
                                        <span class="selected-pill-label">{{ additionalLabel(k) }}</span>
                                        <button
                                            v-if="k !== 'lead_source'"
                                            type="button"
                                            class="selected-pill-x"
                                            title="Remove"
                                            @click="removeAdditional(k)"
                                        >
                                            ×
                                        </button>
                                        <span v-else class="required-pill">Required</span>
                                    </div>
                                </div>

                                <!-- All fields checklist -->
                                <div v-if="showAdditionalPanel" class="additional-checklist">
                                    <label
                                        v-for="opt in additionalFieldOptions"
                                        :key="opt.key"
                                        class="additional-check"
                                        :class="{ disabled: opt.required, active: isAdditionalEnabled(opt.key) }"
                                    >
                                        <input
                                            type="checkbox"
                                            class="additional-check-input"
                                            :checked="isAdditionalEnabled(opt.key)"
                                            :disabled="opt.required"
                                            @change="toggleAdditional(opt.key)"
                                        />
                                        <span class="additional-check-label">{{ opt.label }}</span>
                                        <span v-if="opt.required" class="required-pill">Required</span>
                                    </label>
                                </div>

                                <!-- Selected additional fields inputs (render here, not at page bottom) -->
                                <div class="row g-3 mt-1 additional-fields-grid" v-if="enabledAdditionalKeys.length">
                                    <!-- Bedrooms -->
                                    <div v-if="shouldShowAdditional('bedrooms')" class="col-md-3">
                                        <div class="additional-input-wrap">
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
                                    </div>

                                    <!-- Budget -->
                                    <div v-if="shouldShowAdditional('budget')" class="col-md-3">
                                        <div class="additional-input-wrap">
                                        <label class="form-label-custom">Budget</label>
                                        <div class="input-group-custom" :class="{ 'is-invalid-group': validationErrors.budget }">
                                            <b-form-input 
                                                v-model="form.budget"  
                                                type="number" 
                                                placeholder="Enter Budget" 
                                                class="custom-input"
                                                :class="{ 'is-invalid': validationErrors.budget }"
                                            />
                                            <div class="currency-pill" aria-label="Currency">AED</div>
                                        </div>
                                        <div v-if="validationErrors.budget" class="invalid-feedback d-block">
                                            {{ validationErrors.budget[0] }}
                                        </div>
                                        </div>
                                    </div>

                                    <!-- Purpose -->
                                    <div v-if="shouldShowAdditional('purpose_buying')" class="col-md-3">
                                        <div class="additional-input-wrap">
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
                                    </div>

                                    <!-- Source (required) -->
                                    <div class="col-md-3">
                                        <div class="additional-input-wrap required">
                                        <label class="form-label-custom">
                                            Source <span class="text-danger">*</span>
                                        </label>
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
                                    </div>

                                    <!-- Location -->
                                    <div v-if="shouldShowAdditional('area_id')" class="col-md-6">
                                        <div class="additional-input-wrap">
                                        <label class="form-label-custom">Location</label>
                                        <v-select
                                            v-model="form.area_id"
                                            :options="areas"
                                            :reduce="area => area.id"
                                            :disabled="isLoadingAreas"
                                            label="name"
                                            placeholder="Select area"
                                            class="custom-v-select"
                                        >
                                            <template #open-indicator="{ attributes }">
                                                <i v-bind="attributes" class="ri-arrow-down-s-line dropdown-icon"></i>
                                            </template>

                                            <template #option="option">
                                                <div class="location-option">
                                                    <i class="ri-map-pin-line location-option-icon"></i>
                                                    <div class="location-option-text">
                                                        <span class="location-option-name">
                                                            {{ locationFirstLine(option) }}
                                                        </span>
                                                        <span class="location-option-subtitle">
                                                            {{ locationSecondLine(option) }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </template>

                                            <template #selected-option="option">
                                                <div v-if="option" class="location-selected">
                                                    <span class="location-selected-name">
                                                        {{ locationFirstLine(option) }}
                                                    </span>
                                                    <span class="location-selected-subtitle">
                                                        {{ locationSecondLine(option) }}
                                                    </span>
                                                </div>
                                            </template>

                                            <template #no-options>
                                                <div class="text-center p-2">
                                                    {{ isLoadingAreas ? 'Loading areas...' : 'No areas found' }}
                                                </div>
                                            </template>
                                        </v-select>
                                        </div>
                                    </div>

                                    <!-- Property Type -->
                                    <div v-if="shouldShowAdditional('property_type_id')" class="col-md-6">
                                        <div class="additional-input-wrap">
                                        <label class="form-label-custom">Property Type</label>
                                        <v-select 
                                            v-model="form.property_type_id"
                                            :options="propertyTypeOptions"
                                            :reduce="option => option.value"
                                            label="text"
                                            placeholder="Select Property Type"
                                            class="custom-v-select"
                                        />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>



                        <!-- Additional fields inputs moved into the Additional fields section -->
                        <!-- Source moved into Additional fields section (required) -->

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

                        <!-- Location / Property Type moved into the Additional fields section -->
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
import { ref, watch, computed, onMounted,nextTick  } from 'vue'
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
const showAdditionalPanel = ref(false)
// additional fields selection

const areas = ref([])
const isLoadingAreas = ref(false)
const propertyTypeOptions = ref([])
const getLoggedInUserId = () => {
    try {
        const raw = localStorage.getItem('user') 
        if (!raw) return null
        const parsed = JSON.parse(raw)
        const id = Number(parsed?.id)
        console.log(id);
        console.log(Number.isFinite(id) );
        return Number.isFinite(id) ? id : null
    } catch (error) {
        return null
    }
}
const loggedInUserId = getLoggedInUserId()
const locationFirstLine = (area) => {
    return area.name || ''
}

const locationSecondLine = (area) => {
    return area.parent || ''
}
watch(() => props.modelValue, (val) => {
    show.value = val
})

watch(show, (val) => {
    if (val) {
        // Reset stage_id when modal opens
        console.log('Modal opened, resetting stage_id to null')
        form.value.stage_id = null
        
        // Ensure the StageSelector gets the null value
        emit('update:modelValue', null)
        
        // Force refresh of StageSelector
        nextTick(() => {
            console.log('Form stage_id after reset:', form.value.stage_id)
        })
    } else {
        // Clear validation errors when modal is closed
        validationErrors.value = {}
        errorMessage.value = ''
    }
})
// Watch form.stage_id to see if it gets updated
watch(() => form.value.stage_id, (newVal) => {
    console.log('Form stage_id changed to:', newVal)
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
            const defaultUser =
                users.value.find((user) => Number(user.id) === Number(loggedInUserId)) ||
                users.value.find((user) => Number(user.id) === Number(form.value.responsible_person_id))

            if (defaultUser) {
                form.value.responsible_person_id = defaultUser.id
                form.value.responsible_person = defaultUser
            }
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
    secondary_email:'',
    work_phone_2: '',
    comment: '',
    lead_source: '',
    source_information: '',
    bedrooms: null,
    purpose_buying: null,
    responsible_person_id: loggedInUserId, // Default logged-in user
    // responsible_person: {
    //     id: 1,
    //     name: 'Ahmad Mahfoz',
    //     email: 'testuseremail@gmail.com',
    //     position: '--',
    //     avatar: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'
    // },
    budget: null,
    currency: "AED",
    area_id: null,
    property_type_id: null,
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

// Currency is fixed to AED in this modal

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

const additionalFieldOptions = [
    { key: 'bedrooms', label: 'How Many Bedrooms' },
    { key: 'budget', label: 'Budget' },
    { key: 'purpose_buying', label: 'Purpose Of Purchase' },
    { key: 'lead_source', label: 'Source', required: true },
    { key: 'area_id', label: 'Location' },
    { key: 'property_type_id', label: 'Property Type' },
]

// Default enabled (keep Source required, others optional)
const enabledAdditionalKeys = ref(['lead_source'])

const isAdditionalEnabled = (key) => enabledAdditionalKeys.value.includes(key)

const additionalLabel = (key) => {
    return additionalFieldOptions.find(o => o.key === key)?.label || key
}

const selectedAdditionalSummary = computed(() => {
    // Keep order stable as defined in additionalFieldOptions
    const enabled = new Set(enabledAdditionalKeys.value)
    return additionalFieldOptions
        .map(o => o.key)
        .filter(k => enabled.has(k))
})

const ensureAdditionalDataLoaded = async (key) => {
    if (key === 'area_id' && areas.value.length === 0) {
        await fetchAreas()
    }
    if (key === 'property_type_id' && propertyTypeOptions.value.length === 0) {
        await fetchPropertyTypes()
    }
}

const toggleAdditional = async (key) => {
    // required field
    if (key === 'lead_source') return
    if (isAdditionalEnabled(key)) {
        removeAdditional(key)
        return
    }
    enabledAdditionalKeys.value = [...enabledAdditionalKeys.value, key]
    await ensureAdditionalDataLoaded(key)
}

const removeAdditional = (key) => {
    if (key === 'lead_source') return
    enabledAdditionalKeys.value = enabledAdditionalKeys.value.filter(k => k !== key)
    if (key === 'area_id') form.value.area_id = null
    if (key === 'property_type_id') form.value.property_type_id = null
    if (key === 'bedrooms') form.value.bedrooms = null
    if (key === 'budget') form.value.budget = null
    if (key === 'purpose_buying') form.value.purpose_buying = null
}

const shouldShowAdditional = (key) => isAdditionalEnabled(key)

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
const fetchAreas = async () => {
    try {
        isLoadingAreas.value = true;

        const response = await api.get("/listings/areas/?has_listings=true");
        const areasData = response.data.data || response.data;

        areas.value = areasData.map(area => ({
            id: area.id,
            name: area.name || area.title,
            parent: area.area_parents_title || null
        }));

    } catch (error) {
        console.error("❌ Error fetching areas:", error.response || error);
    } finally {
        isLoadingAreas.value = false;
    }
};

const fetchPropertyTypes = async () => {
    try {
        const res = await api.get('/listings/property-types')
        const data = res.data.data || res.data

        propertyTypeOptions.value = data.map(item => ({
            value: item.id,
            text: item.name
        }))
    } catch (e) {
        console.error('Property types error', e)
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
watch(() => form.value.secondary_email, () => {
    if (validationErrors.value.secondary_email) {
        delete validationErrors.value.secondary_email
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

// currency is fixed, no watcher needed

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
    const defaultUser = users.value.find((user) => Number(user.id) === Number(loggedInUserId)) || null
    form.value = {
        lead_name: '',
        stage_id: null,
        salutation: null,
        first_name: '',
        last_name: '',
        work_phone: '',
        email: '',
        secondary_email:'',
        work_phone_2: '',
        comment: '',
        lead_source: '',
        source_information: '',
        bedrooms: null,
        purpose_buying: null,
        responsible_person_id: defaultUser?.id || loggedInUserId,
        responsible_person: defaultUser,
        budget: null,
        currency: "AED",
        area_id: null,
        property_type_id: null,
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
// Old "Add Custom Field" toggle replaced by the Additional fields builder.
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

.currency-pill {
    min-width: 72px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 10px;
    border-left: 1px solid #E2E8F0;
    color: #64748B;
    font-size: 13px;
    font-family: 'Montserrat';
    background: #fff;
    border-radius: 0 8px 8px 0;
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

.additional-fields-card {
    background: #FFFFFF;
    border: 1px solid #F3F3F3;
    border-radius: 10px;
    box-shadow: 1px 1px 5px 5px #00000005;
}

.additional-checklist {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-top: 8px;
    margin-bottom: 10px;
}

.additional-check {
    border: 1px solid #E2E8F0;
    background: #fff;
    border-radius: 10px;
    padding: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    transition: all 0.2s;
    font-family: 'Montserrat';
}

.additional-check:hover {
    background: #F8FAFC;
    border-color: #CBD5E1;
}

.additional-check.active {
    background: #F1F5FF;
    border-color: #3B82F6;
    box-shadow: 0 1px 0 rgba(59, 130, 246, 0.10);
}

.additional-check.active .additional-check-label {
    color: #0F172A;
}

.additional-check.active .required-pill {
    background: #EFF6FF;
    border-color: #BFDBFE;
}

.additional-check.disabled {
    opacity: 0.8;
    cursor: default;
}

.additional-check-input {
    width: 16px;
    height: 16px;
}

.additional-check-label {
    font-size: 12px;
    font-weight: 600;
    color: #01062C;
    flex: 1;
}

.required-pill {
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 999px;
    border: 1px solid #E2E8F0;
    background: #F8FAFC;
    color: #64748B;
}

.additional-input-wrap {
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    padding: 10px;
    background: #FFFFFF;
    box-shadow: 1px 1px 5px 5px #00000005;
}

.additional-input-wrap.required {
    border-color: #CBD5E1;
    background: #F8FAFC;
}

.additional-chip {
    border: 1px solid #E2E8F0;
    background: #fff;
    border-radius: 10px;
    padding: 8px 10px;
    display: inline-flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    min-width: 210px;
    font-family: 'Montserrat';
    font-size: 12px;
    color: #01062C;
    transition: all 0.2s;
}

.additional-chip:hover {
    background: #F8FAFC;
    border-color: #CBD5E1;
}

.additional-chip.active {
    border-color: #01062C;
}

.additional-chip .chip-label {
    font-weight: 600;
}

.additional-chip .chip-action {
    color: #64748B;
    font-weight: 600;
}

.add-select {
    min-width: 220px;
}

@media (max-width: 768px) {
    .additional-checklist {
        grid-template-columns: 1fr;
    }
}

.selected-fields-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 10px;
}

.selected-pill {
    border: 1px solid #E2E8F0;
    background: #fff;
    border-radius: 999px;
    padding: 6px 10px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: 'Montserrat';
    font-size: 12px;
    color: #01062C;
}

.selected-pill-label {
    font-weight: 600;
}

.selected-pill-x {
    width: 22px;
    height: 22px;
    border-radius: 999px;
    border: 1px solid #E2E8F0;
    background: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #64748B;
    font-size: 16px;
    line-height: 1;
}

.selected-pill-x:hover {
    background: #F8FAFC;
    border-color: #CBD5E1;
}

.add-more-panel {
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    background: #fff;
    padding: 10px;
}

.add-more-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 8px;
}

.add-more-title {
    font-weight: 700;
    font-size: 12px;
    color: #01062C;
    font-family: 'Montserrat';
}

.add-more-subtitle {
    font-size: 12px;
    color: #64748B;
    font-family: 'Montserrat';
    margin-top: 2px;
}

.add-more-list {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    padding: 6px 0 10px 0;
}

.add-more-item {
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    padding: 8px 10px;
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    background: #fff;
}

.add-more-item:hover {
    background: #F8FAFC;
    border-color: #CBD5E1;
}

.add-more-checkbox {
    width: 16px;
    height: 16px;
}

.add-more-label {
    font-size: 12px;
    font-weight: 600;
    color: #01062C;
    font-family: 'Montserrat';
}

.add-more-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
}

@media (max-width: 768px) {
    .add-more-list {
        grid-template-columns: 1fr;
    }
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
    
    /* Location: same height & padding as Price/Size range */
:deep(.location-select .vs__dropdown-toggle) {
  min-height: 30px !important;
  padding: 0px 8px !important;
  align-items: center !important;
  font-size: 0.65rem !important;
}

:deep(.location-select .vs__selected) {
  padding: 0 !important;
  margin: 0 !important;
  display: flex !important;
  align-items: center !important;
}

:deep(.location-select .vs__placeholder) {
  margin: 0 !important;
  position: static !important;
  width: 100%;
  text-align: center;
}

:deep(.location-select .vs__selected) {
  width: 100%;
  text-align: center;
}

:deep(.location-select .vs__selected .location-selected) {
  text-align: left;
}

/* Location selected value in 2 lines (when using selected-option slot) */
.location-selected {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  line-height: 1.2;
}

.location-selected-name {
  font-weight: 600;
  font-size: 0.75rem;
  color: #01062d;
}

.location-selected-subtitle {
  font-size: 0.7rem;
  color: #64748b;
}

/* Location dropdown options: 2 lines with icon (like image) */
.location-option {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 4px 0;
  min-height: 40px;
}

.location-option-icon {
  font-size: 1.1rem;
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
  font-size: 0.75rem;
  color: #01062d;
  line-height: 1.2;
}

.location-option-subtitle {
  font-size: 0.65rem;
  color: #64748b;
  line-height: 1.2;
}

/* Location dropdown list: wider */
:deep(.location-select + .vs__dropdown-menu),
:deep(.location-select .vs__dropdown-menu) {
  min-width: 320px !important;
  width: 100% !important;
  max-width: 400px;
}

/* All top-row inputs: same height, padding, font as Price/Size range */
.unified-input-inline {
     min-height: 30px !important;
    padding: 0px !important;
    font-size: .65rem !important;
}

:deep(.unified-input-inline.vs--single .vs__dropdown-toggle),
:deep(.form-group-inline .custom-select.vs--single .vs__dropdown-toggle) {
  min-height: 30px !important;
  padding: 2px 8px !important;
  font-size: 0.65rem !important;
}

.main-search-row-single :deep(.vs__selected),
.main-search-row-single :deep(.vs__search),
.main-search-row-single :deep(.vs__placeholder) {
  font-size: 0.65rem !important;
}

.unified-btn-inline {
  min-height: 30px !important;
  padding: 2px 8px !important;
  font-size: 0.65rem !important;
}
</style>


