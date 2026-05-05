<template>
    <div>
        <div class="info-section section-highlight"  v-if="!showOnlySection || showOnlySection === 'leadInfo'">
             <div class="info-section-title mb-2">Lead Information</div>
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
            <label class="form-label-custom">Primary Phone</label>
            <b-form-input 
                v-model="form.work_phone" 
                placeholder="Enter Phone Number" 
                class="custom-input"
                :class="{ 'is-invalid': validationErrors.work_phone }"
                  :disabled="useSecondaryEmail || !canEditPhoneEmail"
            />
            <div v-if="validationErrors.work_phone" class="invalid-feedback d-block">
                {{ validationErrors.work_phone[0] }}
            </div>
        </div>

        <div class="info-group">
            <label class="form-label-custom">Primary Email</label>
            <b-form-input 
                v-model="form.email" 
                placeholder="Enter Your Email" 
                class="custom-input"
                :class="{ 'is-invalid': validationErrors.email }"
                  :disabled="useSecondaryEmail || !canEditPhoneEmail"
            />
            <div v-if="validationErrors.email" class="invalid-feedback d-block">
                {{ validationErrors.email[0] }}
            </div>
        </div>
        <div class="info-group">
             <label class="form-label-custom d-flex align-items-center justify-content-between">
                <span>Secondary Phone</span>
        
                <div class="form-check m-0">
                    <input 
                        type="checkbox" 
                        class="form-check-input"
                        v-model="useSecondaryPhone"
                        :disabled="!form.work_phone_2"
                            @change="swapPhones"

                    >
                    <label class="form-check-label small mt-1">Use as primary</label>
                </div>
            </label>
            <b-form-input 
                v-model="form.work_phone_2" 
                placeholder="Enter Phone Number" 
                class="custom-input"
                :class="{ 'is-invalid': validationErrors.work_phone_2 }"
                    :disabled="!canEditPhoneEmail"
            />
            <div v-if="validationErrors.work_phone_2" class="invalid-feedback d-block">
                {{ validationErrors.work_phone_2[0] }}
            </div>
        </div>
        <div class="col">
            <label class="form-label-custom d-flex align-items-center justify-content-between">
                <span>Secondary Email</span>
                
                <div class="form-check m-0">
                    <input 
                        type="checkbox" 
                        class="form-check-input"
                        v-model="useSecondaryEmail"
                        :disabled="!form.secondary_email"
                            @change="swapEmails"

                    >
                    <label class="form-check-label small mt-1">Use as primary</label>
                </div>
            </label>
            <b-form-input 
                v-model="form.secondary_email" 
                placeholder="Enter Your Secondary Email" 
                class="custom-input"
                :class="{ 'is-invalid': validationErrors.secondary_email }"
                    :disabled="!canEditPhoneEmail"

            />
            <div v-if="validationErrors.secondary_email" class="invalid-feedback d-block">
                {{ validationErrors.secondary_email[0] }}
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
    </div>
     <div class="info-section section-highlight"  v-if="!showOnlySection || showOnlySection === 'leadInfo'">
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
        </div>
    <div class="info-section section-highlight"  v-if="!showOnlySection || showOnlySection === 'clientRequirement'">
         <div class="info-section-title mb-2">Client Required Info</div>
        <!-- Additional Fields (same UX as Create) -->
           <!-- Location / Area -->
                <div class="info-group">
                    <label class="form-label-custom">Location </label>
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
                            <span v-bind="attributes">
                                <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                            </span>
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
                <!-- Lead Type (Sale/Rent) -  -->
                    <div class="info-group">
                        <label class="form-label-custom">Lead Type <span class="text-danger">*</span></label>
                        <v-select 
                            v-model="form.lead_type" 
                            :options="leadTypeOptions" 
                            :reduce="option => option.value"
                            label="text"
                            placeholder="Select Lead Type"
                            class="custom-v-select"
                            :class="{ 'is-invalid-select': validationErrors.lead_type }"
                        >
                            <template #open-indicator="{ attributes }">
                                <span v-bind="attributes">
                                    <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                </span>
                            </template>
                        </v-select>
                        <div v-if="validationErrors.lead_type" class="invalid-feedback d-block">
                            {{ validationErrors.lead_type[0] }}
                        </div>
                    </div>
                    <!-- Property Status (Ready/Off Plan/Both) -  -->
                    <div class="info-group"  v-if="!isRentOnly">
                        <label class="form-label-custom">Property Status <span class="text-danger">*</span></label>
                        <v-select 
                            v-model="form.property_status" 
                            :options="propertyStatusOptions" 
                            :reduce="option => option.value"
                            label="text"
                            placeholder="Select Property Status"
                            class="custom-v-select"
                            :class="{ 'is-invalid-select': validationErrors.property_status }"
                        >
                            <template #open-indicator="{ attributes }">
                                <span v-bind="attributes">
                                    <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                </span>
                            </template>
                        </v-select>
                        <div v-if="validationErrors.property_status" class="invalid-feedback d-block">
                            {{ validationErrors.property_status[0] }}
                        </div>
                    </div>
                <!-- Property Type -->
                  <div class="info-group">
                        <label class="form-label-custom">Property Type</label>
                        <v-select 
                            v-model="form.property_type_id"
                            :options="propertyTypeOptions"
                            :reduce="option => option.value"
                            label="text"
                            placeholder="Select Property Type"
                            class="custom-v-select"
                        >
                            <template #open-indicator="{ attributes }">
                                <span v-bind="attributes">
                                    <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                </span>
                            </template>
                        </v-select>
                    </div>
         
  <!-- Bedrooms -->
                <div class="info-group"  v-if="!isPlotsOrLand">
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
                <!-- Budget -->
                <div class="info-group mb-0">
                    <label class="form-label-custom">Budget (AED)</label>
                    <div
                        ref="budgetTriggerRef"
                        class="budget-field-wrap"
                        :class="{ 'is-invalid-group': budgetFieldInvalid }"
                    >
                        <button
                            type="button"
                            class="custom-date-trigger"
                            @click.stop="toggleBudgetDropdown"
                        >
                            <span>{{ budgetDisplay }}</span>
                            <iconify-icon icon="lucide:chevron-down" />
                        </button>
                        <div
                            v-if="showBudgetDropdown"
                            ref="budgetDropdownPanelRef"
                            class="budget-dropdown budget-dropdown--inline"
                            @mousedown.stop
                            @click.stop
                        >
                            <div class="budget-from-to-row">
                                <div class="budget-col">
                                    <label class="budget-input-label">From</label>
                                    <input
                                        :value="budgetFromDisplay"
                                        type="text"
                                        inputmode="numeric"
                                        autocomplete="off"
                                        placeholder="0"
                                        class="form-control custom-input budget-dropdown-input"
                                        :class="{ 'is-invalid': !!(validationErrors.budget_from || validationErrors.budget) }"
                                        @mousedown.stop
                                        @click.stop
                                        @input="onBudgetFromInput($event.target.value)"
                                    />
                                </div>
                                <div class="budget-col">
                                    <label class="budget-input-label">To</label>
                                    <input
                                        :value="budgetToDisplay"
                                        type="text"
                                        inputmode="numeric"
                                        autocomplete="off"
                                        placeholder="0"
                                        class="form-control custom-input budget-dropdown-input"
                                        :class="{ 'is-invalid': !!(validationErrors.budget_to || validationErrors.budget) }"
                                        @mousedown.stop
                                        @click.stop
                                        @input="onBudgetToInput($event.target.value)"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="budgetErrorMessage" class="invalid-feedback d-block mt-1">
                    {{ budgetErrorMessage }}
                </div>
                 
                

                <!-- Purpose -->
                <div class="info-group"  v-if="!isRentOnly">
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
               
                
                


                <!-- Source (required) -->
                <!--<div class="info-group">-->
                <!--    <label class="form-label-custom">Source <span class="text-danger">*</span></label>-->
                <!--    <v-select -->
                <!--        v-model="form.lead_source" -->
                <!--        :options="sourceOptions" -->
                <!--        :reduce="option => option.value"-->
                <!--        label="text"-->
                <!--        placeholder="Select Source"-->
                <!--        class="custom-v-select"-->
                <!--        :class="{ 'is-invalid-select': validationErrors.lead_source }"-->
                <!--    >-->
                <!--        <template #open-indicator="{ attributes }">-->
                <!--            <span v-bind="attributes">-->
                <!--                <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>-->
                <!--            </span>-->
                <!--        </template>-->
                <!--    </v-select>-->
                <!--    <div v-if="validationErrors.lead_source" class="invalid-feedback d-block">-->
                <!--        {{ validationErrors.lead_source[0] }}-->
                <!--    </div>-->
                <!--</div>-->

             

                <!-- Lead Status (for Qualified stage) -->
                <div class="info-group" v-if="shouldShowField('status_lead')" >
                    <label class="form-label-custom">Quality Status</label>
                    <v-select 
                        v-model="form.status_lead" 
                        :options="leadStatusOptions" 
                        :reduce="option => option.value"
                        label="text"
                        placeholder="Select Status"
                        class="custom-v-select"
                    >
                        <template #open-indicator="{ attributes }">
                            <span v-bind="attributes">
                                <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                            </span>
                        </template>
                    </v-select>
                </div>

                <!-- Available Date (for Future stage) -->
                <div class="info-group" v-if="shouldShowField('available_date')" >
                    <label class="form-label-custom">Available Date</label>
                    <AdvancedDatePicker
                        v-model="form.available_date"
                        date-only
                        placeholder="Select date"
                        class="custom-input"
                    />
                </div>

                <!-- Branch (for Shared stage) -->
                <div class="info-group" v-if="shouldShowField('branch')" >
                    <label class="form-label-custom">Branch</label>
                    <v-select 
                        v-model="form.branch" 
                        :options="branchOptions" 
                        :reduce="option => option.value"
                        label="text"
                        placeholder="Select Branch"
                        class="custom-v-select"
                    >
                        <template #open-indicator="{ attributes }">
                            <span v-bind="attributes">
                                <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                            </span>
                        </template>
                    </v-select>
                </div>

                <!-- Lost Reason (for Lost stage) -->
                <div class="info-group" v-if="shouldShowField('lost_reason')" >
                    <label class="form-label-custom">Lost Reason</label>
                    <v-select 
                        v-model="form.lost_reason" 
                        :options="lostReasonOptions" 
                        :reduce="option => option.value"
                        label="text"
                        placeholder="Select Lost Reason"
                        class="custom-v-select"
                    >
                        <template #open-indicator="{ attributes }">
                            <span v-bind="attributes">
                                <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                            </span>
                        </template>
                    </v-select>
                </div>
    </div>
       
        <!-- Old + Add Custom Field UI replaced by Additional fields section -->
        <!-- Responsible Person -->
        <!--<div class="responsible-person-box p-3 radius-8 shadow-sm mb-3">-->
        <!--    <div class="d-flex align-items-center justify-content-between mb-3">-->
        <!--        <label class="info-label mb-0"> Person</label>-->
        <!--        <div class="d-flex flex-column align-items-end">-->
        <!--            <b-dropdown -->
        <!--                variant="link" -->
        <!--                toggle-class="text-decoration-none p-0 no-caret-custom" -->
        <!--                no-caret-->
        <!--                right-->
        <!--                class="change-person-dropdown"-->
        <!--            >-->
        <!--                <template #button-content>-->
        <!--                    <div class="btn-change-person-text">-->
        <!--                        <iconify-icon icon="lucide:user-plus" class="change-person-icon"></iconify-icon>-->
        <!--                        <span>Change Responsible Person</span>-->
        <!--                    </div>-->
        <!--                </template>-->
                    
        <!--            <div class="dropdown-search-wrapper p-3">-->
        <!--                <div class="d-flex align-items-center justify-content-between border-bottom mb-3 pb-2">-->
        <!--                    <span class="modal-title-dropdown">Change Responsible Person</span>-->
        <!--                </div>-->
        <!--                <div class="search-input-wrapper mb-3">-->
        <!--                    <b-form-input -->
        <!--                        v-model="searchQuery" -->
        <!--                        placeholder="Search Responsible Person" -->
        <!--                        class="dropdown-search-input"-->
        <!--                    />-->
        <!--                    <iconify-icon icon="lucide:search" class="search-icon"></iconify-icon>-->
        <!--                </div>-->
                        
        <!--                <div class="user-list-scroll">-->
        <!--                    <div -->
        <!--                        v-for="user in filteredUsers" -->
        <!--                        :key="user.id"-->
        <!--                        class="user-item d-flex align-items-center justify-content-between p-2"-->
        <!--                        @click="selectUser(user)"-->
        <!--                        :class="{ 'selected': form.responsible_person_id === user.id }"-->
        <!--                    >-->
        <!--                        <div class="d-flex align-items-center gap-2">-->
        <!--                            <img :src="user.avatar || 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'" class="user-item-avatar" />-->
        <!--                            <div class="user-item-info">-->
        <!--                                <div class="user-item-name">{{ user.name }}</div>-->
        <!--                            </div>-->
        <!--                        </div>-->
        <!--                        <iconify-icon -->
        <!--                            v-if="form.responsible_person_id === user.id" -->
        <!--                            icon="lucide:check" -->
        <!--                            class="text-warning"-->
        <!--                        ></iconify-icon>-->
        <!--                    </div>-->
        <!--                    <div v-if="filteredUsers?.length === 0" class="text-center p-3 text-muted">-->
        <!--                        No persons found-->
        <!--                    </div>-->
        <!--                                </div>-->
        <!--                            </div>-->
        <!--                        </b-dropdown>-->
        <!--            <div v-if="validationErrors.responsible_person_id" class="invalid-feedback d-block" style="margin-top: 4px;">-->
        <!--                {{ validationErrors.responsible_person_id[0] }}-->
        <!--            </div>-->
        <!--        </div>-->
        <!--    </div>-->
        
               
        <!--    <div class="d-flex align-items-center gap-3">-->
        <!--        <div class="avatar-wrapper">-->
        <!--            <img -->
        <!--                v-if="!avatarError && selectedPerson?.avatar" -->
        <!--                :src="selectedPerson.avatar" -->
        <!--                class="avatar-md rounded-circle" -->
        <!--                @error="handleAvatarError"-->
        <!--            />-->
        <!--            <div v-else class="avatar-placeholder">-->
        <!--                <iconify-icon icon="lucide:user" class="avatar-icon"></iconify-icon>-->
        <!--            </div>-->
        <!--        </div>-->
        <!--        <div class="flex-grow-1">-->
        <!--            <div class="d-flex mb-1">-->
        <!--                <span class="text-xs text-secondary-light">Name</span>-->
        <!--                <span class="text-xs fw-medium">: {{ selectedPerson?.name || '----' }}</span>-->
        <!--            </div>-->
        <!--        </div>-->
        <!--    </div>-->
        <!--</div>-->
    </div>

</template>

<script setup>
import { ref, watch, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { BFormInput, BFormTextarea, BDropdown } from 'bootstrap-vue-3'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import api from '@/plugins/axios'
import AdvancedDatePicker from '@/components/shared/AdvancedDatePicker.vue'
import { formatBudgetThousands, parseBudgetThousandsInput } from '@/utils/budgetInput'

const props = defineProps({
    lead: {
        type: Object,
        required: true
    },
    stageId: {
        type: Number,
        default: null
    } ,showOnlySection: { 
        type: String,
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

const budgetFieldInvalid = computed(() =>
    !!(validationErrors.value.budget_from || validationErrors.value.budget_to || validationErrors.value.budget)
)
const budgetErrorMessage = computed(
    () =>
        validationErrors.value.budget_to?.[0] ||
        validationErrors.value.budget_from?.[0] ||
        validationErrors.value.budget?.[0] ||
        ''
)

const useSecondaryEmail = ref(false)
const useSecondaryPhone = ref(false)

const showAdditionalPanel = ref(false)

const areas = ref([])
const propertyTypeOptions = ref([])

const isLoadingAreas = ref(false)
const isLoadingPropertyTypes = ref(false)
// Show/hide property details section
const showPropertyDetails = ref(false)
const currentStageOrder = computed(() => {
    return props.lead?.stage?.order || 0
})

const shouldShowField = (fieldKey) => {
    const order = currentStageOrder.value
    
    const fieldVisibility = {
        // Stage 3: Follow Up
        'salutation': [3,4,5,6,7],
        // Stage 4: Qualified
        'bedrooms': [3,4,5,6],
        'budget': [3,4,5,6],
        'purpose_buying': [3,4,5,6],
        'lead_source': [3,4,5,6],
        'area_id': [3,4,5,6],
        'property_type_id': [4,5,6],
        'status_lead': [4,5,6,9,10],
        // Stage 5: Future
        'available_date': [5,6],
        // Stage 7: Shared
        'branch': [7],
        // Stage 8: Lost
        'lost_reason': [8],
    }
    
    const stages = fieldVisibility[fieldKey] || []
    return stages.includes(order)
}

const leadStatusOptions = computed(() => {
    const stageOrder = currentStageOrder.value
    
    // Stage 4: Qualified - Hot/Warm/Cold فقط
    if (stageOrder === 4) {
        return [
            { value: 'cold', text: 'Cold Lead' },
            { value: 'warm', text: 'Warm Lead' },
            { value: 'hot', text: 'Hot Lead' }
        ]
    }
    
    // Stage 9: Lead Pool
    if (stageOrder === 9) {
        return [
            { value: 'no_answer', text: 'No Answer' },
            { value: 'contacted', text: 'Contacted' },
            { value: 'wrong_person', text: 'Wrong Person' }
        ]
    }
    
    // Stage 10: Unqualified
    if (stageOrder === 10) {
        return [
            { value: 'not_interested', text: 'Not Interested' },
            { value: 'wrong_contact_details', text: 'Wrong Contact Details' },
            { value: 'no_answer_multiple_calls', text: 'No Answer — Multiple Calls' },
            { value: 'job_seeker', text: 'Job Seeker' },
            { value: 'broker', text: 'Broker' },
            { value: 'registered_by_mistake', text: 'Registered by Mistake' },
          
                { value: 'blacklist', text: 'blacklist' },

        ]
    }
    
    return [
        { value: 'cold', text: 'Cold' },
        { value: 'warm', text: 'Warm' },
        { value: 'hot', text: 'Hot' }
    ]
})
const leadTypeOptions = [
    { value: 'sale', text: 'Sale' },
    { value: 'rent', text: 'Rent' },
    //  { value: 'both', text: 'Both' },
]

// Property Status Options
const propertyStatusOptions = [
    { value: 'ready', text: 'Ready' },
    { value: 'off_plan', text: 'Off Plan' },
    { value: 'both', text: 'Both' }
]
// Branch Options
const branchOptions = [
    { value: 'Abu Dhabi', text: 'Abu Dhabi' },
    { value: 'Dubai', text: 'Dubai' },
    // { value: 'Sharjah', text: 'Sharjah' }
]

// Lost Reason Options
const lostReasonOptions = [
    // { value: 'lost_by_other_company', text: 'Lost by Other Company' },
    // { value: 'lost_by_our_company', text: 'Lost by Our Company' }
    { value: 'already_bought', text: "Already bought" }
]

const isPlotsOrLand = computed(() => {
    const propertyTypeId = form.value.property_type_id;
    if (!propertyTypeId) return false;
    
    const selectedType = propertyTypeOptions.value.find(opt => opt.value === propertyTypeId);
    if (!selectedType) return false;
    
    const typeName = selectedType.text.toLowerCase();
    return typeName.includes('plot') || typeName.includes('land') || typeName.includes('plots') || typeName.includes('lands');
})

const isRentOnly = computed(() => {
    return form.value.lead_type === 'rent';
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
    budget_from: null,
    budget_to: null,
    currency: 'AED',
    bedrooms: null,
    purpose_buying: null,
    lead_source: '',
    source_information: '',
    responsible_person_id: null,
    area_id: null,
    property_type_id: null,
     status_lead: null,
    available_date: null,
    branch: null,
    lost_reason: null
})

const budgetFromDisplay = ref('')
const budgetToDisplay = ref('')
const showBudgetDropdown = ref(false)
const budgetTriggerRef = ref(null)
const budgetDropdownPanelRef = ref(null)

const budgetDisplay = computed(() => {
    const from = budgetFromDisplay.value || ''
    const to = budgetToDisplay.value || ''
    if (!from && !to) return 'Select budget range'
    if (from && to) return `${from} - ${to}`
    if (from) return `From ${from}`
    return `To ${to}`
})

function onBudgetFromInput(val) {
    const { numeric, display } = parseBudgetThousandsInput(val)
    form.value.budget_from = numeric
    budgetFromDisplay.value = display
}

function onBudgetToInput(val) {
    const { numeric, display } = parseBudgetThousandsInput(val)
    form.value.budget_to = numeric
    budgetToDisplay.value = display
}

function syncBudgetDisplayFields() {
    budgetFromDisplay.value = formatBudgetThousands(form.value.budget_from)
    budgetToDisplay.value = formatBudgetThousands(form.value.budget_to)
}

async function toggleBudgetDropdown() {
    showBudgetDropdown.value = !showBudgetDropdown.value
    await nextTick()
}

function closeBudgetDropdown() {
    showBudgetDropdown.value = false
}

function onDocumentClick(event) {
    if (!showBudgetDropdown.value) return
    const target = event.target
    const triggerEl = budgetTriggerRef.value
    const path = typeof event.composedPath === 'function' ? event.composedPath() : []
    if (
        triggerEl?.contains(target) ||
        budgetDropdownPanelRef.value?.contains(target) ||
        path.includes(triggerEl) ||
        path.includes(budgetDropdownPanelRef.value)
    ) return
    closeBudgetDropdown()
}

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

// Currency is fixed to AED in this edit form

const bedroomOptions = [
    { value: '0', text: 'Studio' },
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
    { value: 'Live in', text: 'Live in' },
    { value: 'Short-term investment', text: 'Short-term investment' },
    { value: 'Long-term investment', text: 'Long-term investment' },
    // { value: 'Holiday home', text: 'Holiday home' },
    // { value: 'Rental', text: 'Rental' },
]

const sourceOptions = ref([
    { value: 'Website', text: 'Website' },
    { value: 'Referral', text: 'Referral' },
    { value: 'Social Media', text: 'Social Media' },
    { value: 'Advertisement', text: 'Advertisement' },
    { value: 'Cold Call', text: 'Cold Call' },
    { value: 'Other', text: 'Other' }
])

const additionalFieldOptions = [
    { key: 'bedrooms', label: 'How Many Bedrooms' },
    { key: 'budget', label: 'Budget' },
    { key: 'purpose_buying', label: 'Purpose Of Purchase' },
    { key: 'lead_source', label: 'Source', required: true },
    { key: 'area_id', label: 'Location' },
    { key: 'property_type_id', label: 'Property Type' },
]

const enabledAdditionalKeys = ref(['lead_source'])

const isAdditionalEnabled = (key) => enabledAdditionalKeys.value.includes(key)

const additionalLabel = (key) => {
    return additionalFieldOptions.find(o => o.key === key)?.label || key
}

const selectedAdditionalSummary = computed(() => {
    const enabled = new Set(enabledAdditionalKeys.value)
    return additionalFieldOptions.map(o => o.key).filter(k => enabled.has(k))
})

const ensureAdditionalDataLoaded = async (key) => {
    if (key === 'area_id' && areas.value.length === 0) {
        await fetchAreas()
    }
    if (key === 'property_type_id' && propertyTypeOptions.value.length === 0) {
        await fetchPropertyTypes()
    }
}

const removeAdditional = (key) => {
    if (key === 'lead_source') return
    enabledAdditionalKeys.value = enabledAdditionalKeys.value.filter(k => k !== key)
    if (key === 'area_id') form.value.area_id = null
    if (key === 'property_type_id') form.value.property_type_id = null
    if (key === 'bedrooms') form.value.bedrooms = null
    if (key === 'budget') {
        form.value.budget_from = null
        form.value.budget_to = null
        budgetFromDisplay.value = ''
        budgetToDisplay.value = ''
        closeBudgetDropdown()
    }
    if (key === 'purpose_buying') form.value.purpose_buying = null
}

const toggleAdditional = async (key) => {
    if (key === 'lead_source') return
    if (isAdditionalEnabled(key)) {
        removeAdditional(key)
        return
    }
    enabledAdditionalKeys.value = [...enabledAdditionalKeys.value, key]
    await ensureAdditionalDataLoaded(key)
}

const shouldShowAdditional = (key) => isAdditionalEnabled(key)
const swapEmails = () => {
    const temp = form.value.email
    form.value.email = form.value.secondary_email
    form.value.secondary_email = temp
}
const swapPhones = () => {
    const temp = form.value.work_phone
    form.value.work_phone = form.value.work_phone_2
    form.value.work_phone_2 = temp
}
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
// Old "+ Add Custom Field" flow replaced by Additional fields checklist
const fetchAreas = async () => {
    try {
        isLoadingAreas.value = true

        const response = await api.get("/listings/areas/?has_listings=true")
        const data = response.data.data || response.data

        areas.value = data.map(area => ({
            id: area.id,
            name: area.name || area.title,
            parent: area.area_parents_title || null
        }))

    } catch (e) {
        console.error(e)
    } finally {
        isLoadingAreas.value = false
    }
}
const fetchPropertyTypes = async () => {
    try {
        isLoadingPropertyTypes.value = true

        const res = await api.get('/listings/property-types')
        const data = res.data.data || res.data

      
         propertyTypeOptions.value = data.map(item => ({
            value: item.id,  
            text: item.name  
        }))

    } catch (e) {
        console.error(e)
    } finally {
        isLoadingPropertyTypes.value = false
    }
}
const locationFirstLine = (area) => area?.name || ''
const locationSecondLine = (area) => area?.parent || ''
// Initialize form with lead data
const initializeForm = () => {
    let budgetFrom = props.lead?.budget_from ?? null
    let budgetTo = props.lead?.budget_to ?? null
    if (
        budgetFrom == null &&
        budgetTo == null &&
        props.lead?.budget != null &&
        props.lead?.budget !== ''
    ) {
        budgetFrom = props.lead.budget
    }
    form.value = {
        lead_name: props.lead?.lead_name || '',
        stage_id: props.stageId || props.lead?.stage?.id || null,
        salutation: props.lead?.salutation || null,
        first_name: props.lead?.first_name || '',
        last_name: props.lead?.last_name || '',
        work_phone: props.lead?.work_phone || '',
        email: props.lead?.email || '',
        work_phone_2: props.lead?.work_phone_2 || '',
        secondary_email:props.lead?.secondary_email || '',
        comment: props.lead?.comment || '',
        budget_from: budgetFrom,
        budget_to: budgetTo,
        currency: props.lead?.currency || 'AED',
        bedrooms: props.lead?.bedrooms || null,
        purpose_buying: props.lead?.purpose_buying || null,
        lead_source: props.lead?.lead_source || '',
        source_information: props.lead?.source_information || '',
        responsible_person_id: props.lead?.responsible_person?.id || null,
        area_id: props.lead?.area_id || null,
        property_type_id: props.lead?.property_type_id || null,
        status_lead: props.lead?.status_lead || null,
        available_date: props.lead?.available_date || null,
        branch: props.lead?.branch || null,
        lost_reason: props.lead?.why_lost_lead || null,
        lead_type: props.lead?.lead_type || null,
        property_status: props.lead?.property_status || null,
    }
    selectedPerson.value = props.lead?.responsible_person || null
    syncBudgetDisplayFields()
}
const canEditPhoneEmail = computed(() => {
    return props.lead?.can_edit_phone_email ?? false
})
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

watch(() => form.value.secondary_email, () => {
    if (validationErrors.value.secondary_email) {
        delete validationErrors.value.secondary_email
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

watch(() => [form.value.budget_from, form.value.budget_to], () => {
    ;['budget', 'budget_from', 'budget_to'].forEach((k) => {
        if (validationErrors.value[k]) delete validationErrors.value[k]
    })
    clearErrorMessageIfNeeded()
})

// currency is fixed, no watcher needed

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
onMounted(async () => {
    initializeForm()
    document.addEventListener('click', onDocumentClick)
    await fetchUsers()

    if (props.lead?.area_id || props.lead?.property_type_id) {
        await fetchAreas()
        await fetchPropertyTypes()
    }

    // Auto-enable additional fields if lead already has values
    const next = new Set(enabledAdditionalKeys.value)
    if (props.lead?.bedrooms != null && props.lead?.bedrooms !== '') next.add('bedrooms')
    if (
        (props.lead?.budget_from != null && props.lead?.budget_from !== '') ||
        (props.lead?.budget_to != null && props.lead?.budget_to !== '') ||
        (props.lead?.budget != null && props.lead?.budget !== '')
    ) {
        next.add('budget')
    }
    if (props.lead?.purpose_buying) next.add('purpose_buying')
    if (props.lead?.area_id) next.add('area_id')
    if (props.lead?.property_type_id) next.add('property_type_id')
    enabledAdditionalKeys.value = Array.from(next)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick)
})

const handleAvatarError = () => {
    avatarError.value = true
}

const handleCancel = () => {
    emit('cancel')
}
const validateBudget = () => {
    const from = form.value.budget_from ? parseFloat(form.value.budget_from) : null
    const to = form.value.budget_to ? parseFloat(form.value.budget_to) : null
    
    if (from !== null && to !== null && from > to) {
        return 'Budget From cannot be greater than Budget To'
    }
    return null
}
const handleSave = async () => {
    try {
        isSubmitting.value = true
        errorMessage.value = ''
        validationErrors.value = {}
          const budgetError = validateBudget()
        if (budgetError) {
            validationErrors.value.budget = [budgetError]
            if (window.$showNotification) {
                window.$showNotification(budgetError, 'warning')
            }
            return
        }
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
.info-section {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 14px;
    margin-bottom: 18px;
    background: #ffffff;
    overflow: visible;
}

.info-section-title {
    font-size: 12px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 12px;
    padding-bottom: 0;
    border-bottom: none;
}

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

.budget-from-to-row .info-group {
    margin-bottom: 0;
}

.budget-field-wrap {
    position: relative;
    overflow: visible;
}

.custom-date-trigger {
    width: 100%;
    height: 42px;
    border-radius: 10px;
    border: 1px solid #E2E8F0;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 12px;
    font-size: 13px;
    color: #64748B;
    font-family: 'Montserrat';
}

.custom-date-trigger:hover {
    border-color: #cbd5e1;
}

.budget-dropdown--inline {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    width: 100%;
    min-width: 220px;
    z-index: 60;
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    box-shadow: 0 10px 24px rgba(2, 6, 23, 0.12);
    padding: 10px;
}

.budget-from-to-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.budget-col {
    min-width: 0;
}

.budget-input-label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #334155;
    margin-bottom: 6px;
}

.budget-dropdown-input {
    height: 38px !important;
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
    /*overflow: hidden;*/
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
    font-size: 15px;
    color: #cfdbec;
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

@media (max-width: 768px) {
    .additional-checklist {
        grid-template-columns: 1fr;
    }
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
:deep(.custom-v-select .vs__open-indicator-icon) {
    font-size: 12px;
    color: #64748b52;
}
.custom-date-trigger svg,.custom-date-trigger i ,.form-label-custom svg ,.form-label-custom i{
    color: #64748b52 !important;
}
/* تصغير علامة الإغلاق (X) في v-select */
:deep(.custom-v-select .vs__clear) {
    width: 16px !important;
    height: 16px !important;
    font-size: 12px !important;
    line-height: 1 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}

:deep(.custom-v-select .vs__clear svg) {
    width: 12px !important;
    height: 12px !important;
     fill: #64748b52;
}

/* للـ client-req-vselect نفس الشيء */
:deep(.client-req-vselect .vs__clear) {
    width: 16px !important;
    height: 16px !important;
    font-size: 12px !important;
    line-height: 1 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}

:deep(.client-req-vselect .vs__clear svg) {
    width: 12px !important;
    height: 12px !important;
}

.vs__clear {
    width: 16px !important;
    height: 16px !important;
    font-size: 12px !important;
    line-height: 1 !important;
}

.vs__clear svg {
    width: 12px !important;
    height: 12px !important;
}


/* تأثير الـ highlight */
.section-highlight {
    animation: highlight-pulse 0.5s ease-in-out 3;
    border: 2px solid #FAA300 !important;
    background: linear-gradient(90deg, #FFF8E7, #FFFFFF) !important;
    box-shadow: 0 0 0 2px rgba(250, 163, 0, 0.2) !important;
    transition: all 0.3s ease;
}

@keyframes highlight-pulse {
    0% {
        border-color: #FAA300;
        box-shadow: 0 0 0 0 rgba(250, 163, 0, 0.4);
    }
    50% {
        border-color: #FFD700;
        box-shadow: 0 0 0 4px rgba(250, 163, 0, 0.2);
    }
    100% {
        border-color: #FAA300;
        box-shadow: 0 0 0 0 rgba(250, 163, 0, 0);
    }
}
</style>
