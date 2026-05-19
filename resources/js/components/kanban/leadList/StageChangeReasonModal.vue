<!-- StageChangeReasonModal.vue -->
<template>

    <div v-if="visible" class="stage-change-modal-overlay" @click.self="closeModal">
        <div class="stage-change-modal" :class="{ 'modal-wide': missingFields.length > 0 || interactionMode }">
            <div class="modal-header">
                <h5 class="modal-title">{{ isConversion ? 'Complete Lead Information' : `Move Lead to ${targetStageName}` }}</h5>
                <button class="close-btn-custom" @click="closeModal">
                    <iconify-icon icon="lucide:x" width="20" height="20"></iconify-icon>
                </button>
            </div>
            
            <div class="modal-body">
                <!-- Reason Section -->
                <div v-if="interactionMode || targetStageOrder !== 6" class="mb-4 box-shadow">
                    <template v-if="interactionMode">
                        <label class="form-label">Call Result <span class="text-danger">*</span></label>
                        <div class="call-result-grid mb-3">
                            <button
                                type="button"
                                class="call-result-card"
                                :class="{ 'is-active': formData.interaction_result === 'answered' }"
                                @click="formData.interaction_result = 'answered'"
                            >
                                <iconify-icon icon="lucide:phone-call" class="call-result-icon"></iconify-icon>
                                <span class="call-result-text">Answered</span>
                            </button>
                            <button
                                type="button"
                                class="call-result-card"
                                :class="{ 'is-active': formData.interaction_result === 'no_answer' }"
                                @click="formData.interaction_result = 'no_answer'"
                            >
                                <iconify-icon icon="lucide:phone-off" class="call-result-icon"></iconify-icon>
                                <span class="call-result-text">No Answer</span>
                            </button>
                        </div>

                        <div v-if="formData.interaction_result === 'answered'">
                            <label for="interaction-note" class="form-label">
                                Comment <span class="text-danger">*</span>
                            </label>
                            <textarea
                                id="interaction-note"
                                v-model="formData.interaction_note"
                                rows="3"
                                class="form-control reason-textarea"
                                placeholder="Write purpose..."
                                required
                            ></textarea>
                        </div>

                        <div v-else-if="formData.interaction_result === 'no_answer'">
                            <label for="interaction-note-reminder" class="form-label">
                                Reminder Activity <span class="text-danger">*</span>
                            </label>
                            <textarea
                                id="interaction-note-reminder"
                                v-model="formData.interaction_note"
                                rows="5"
                                class="form-control reason-textarea reason-textarea-lg"
                                placeholder="Type reminder activity title"
                                required
                            ></textarea>

                            <div class="reminder-controls mt-3">
                                <button
                                    type="button"
                                    class="activity-control-btn d-flex align-items-center gap-2"
                                    @click.stop="showDateTimePicker = true"
                                >
                                    <iconify-icon icon="lucide:calendar" class="activity-control-icon"></iconify-icon>
                                    <span class="activity-control-text">{{ formattedReminderDate }}</span>
                                </button>

                                <div class="reminder-dropdown-wrapper position-relative">
                                    <button
                                        ref="reminderButtonRef"
                                        type="button"
                                        class="activity-control-btn activity-control-btn-bell d-flex align-items-center gap-2"
                                        @click.stop="toggleReminderDropdown"
                                    >
                                        <iconify-icon icon="lucide:bell" class="activity-control-icon bell-icon"></iconify-icon>
                                        <span class="activity-control-text">Reminder</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template v-else-if="targetStageOrder !== 6">
                        <label for="reason" class="form-label">
                            Please provide a reason for moving this lead <span class="text-danger">*</span>
                        </label>
                        <textarea
                            id="reason"
                            v-model="formData.reason"
                            rows="3"
                            class="form-control reason-textarea"
                            placeholder="Text Here"
                            required
                        ></textarea>
                    </template>
                </div>

                <!-- Deal Name (first section, standalone) -->
                <div v-if="missingFields.includes('deal_name') && targetStageOrder === 6" class="form-group mb-3">
                    <label class="form-label">Deal Name <span class="text-danger">*</span></label>
                    <input 
                        type="text" 
                        v-model="formData.deal_name" 
                        class="form-control deal-name-input" 
                        placeholder="Enter deal name"
                        required
                    />
                </div>

                <!-- Dynamic Form Based on missingFields -->
                <div v-if="missingFields.length > 0" class="dynamic-form">
    <!-- 🟨 Status & Meta -->
                        <div 
                            v-if="['status_lead','lead_type','deal_name','property_status','available_date','branch','why_lost_lead','lost_reason'].some(f => missingFields.includes(f))" 
                            class="box-shadow lead_qualification lead-qualification-card"
                        >
                             <h5 class="section-title ">Lead Qualification</h5>

                            <div
                                v-if="targetStageOrder === 6 || missingFields.includes('status_lead') || missingFields.includes('lead_type') || missingFields.includes('property_status')"
                                class="lead-qualification-trio"
                            >
                            <!-- Lead Status -->
                                <div v-if="missingFields.includes('status_lead')" class="form-group mb-0 lead-qual-field">
                                    <!-- حالة خاصة للمرحلة 6 (Converted) -->
                                    <template v-if="targetStageOrder === 6">
                                        <label class="form-label ">Quality Status</label>
                                        <v-select 
                                            v-model="formData.lead_status"
                                            :options="convertedStatusOptions"
                                            :reduce="opt => opt.value"
                                            label="text"
                                            placeholder="Select status"
                                            :searchable="false"
                                            :clearable="false"
                                            class="custom-v-select searchable-select lead-qual-select--unified"
                                        >
                                            <template #open-indicator="{ attributes }">
                                                <span v-bind="attributes">
                                                    <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                                </span>
                                            </template>
                                        </v-select>
                                    </template>
                                
                                    <!-- حالة المرحلة 4 (Quality Status: Hot/Warm/Cold) -->
                                    <template v-else-if="targetStageOrder === 4">
                                        <label class="form-label ">Quality Status</label>
                                        <v-select 
                                            v-model="formData.lead_status"
                                            :options="hotWarmLeadOptions"
                                            :reduce="opt => opt.value"
                                            label="text"
                                            placeholder="Select quality"
                                            :searchable="false"
                                            :clearable="false"
                                            class="custom-v-select searchable-select lead-qual-select lead-qual-select--quality lead-qual-select--unified"
                                        >
                                            <template #selected-option="option">
                                                <span class="qs-sel">
                                                    <span
                                                        class="qs-dd-dot qs-dd-dot--sm"
                                                        :class="{ 'is-filled': true }"
                                                        :style="{
                                                            '--qs-ring': qualityMetaForValue(option.value).ringColor,
                                                            '--qs-fill': qualityMetaForValue(option.value).fillColor,
                                                        }"
                                                        aria-hidden="true"
                                                    />
                                                    <span class="qs-sel-label">{{ qualityMetaForValue(option.value).label }}</span>
                                                </span>
                                            </template>
                                            <template #option="option">
                                                <div class="qs-opt-wrap">
                                                    <div class="qs-dd-row">
                                                        <span
                                                            class="qs-dd-dot"
                                                            :class="{ 'is-filled': formData.lead_status === option.value }"
                                                            :style="{
                                                                '--qs-ring': qualityMetaForValue(option.value).ringColor,
                                                                '--qs-fill': qualityMetaForValue(option.value).fillColor,
                                                            }"
                                                            aria-hidden="true"
                                                        />
                                                        <span class="qs-dd-text">
                                                            <span class="qs-dd-title">{{ qualityMetaForValue(option.value).label }}</span>
                                                        </span>
                                                    </div>
                                                    <div class="qs-dd-hover-tip" role="tooltip">
                                                        <iconify-icon icon="lucide:sparkles" class="qs-dd-hover-tip__icon" />
                                                        {{ qualityMetaForValue(option.value).tooltip }}
                                                    </div>
                                                </div>
                                            </template>
                                            <template #open-indicator="{ attributes }">
                                                <span v-bind="attributes">
                                                    <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                                </span>
                                            </template>
                                        </v-select>
                                    </template>
                                
                                    <!-- باقي المراحل (9، 10، وغيرها) -->
                                    <template v-else>
                                        <label class="form-label ">Quality Status</label>
                                        <v-select append-to-body
                                            v-if="targetStageOrder === 9"
                                            v-model="formData.lead_status"
                                            :options="leadPoolStatusOptions"
                                            :reduce="opt => opt.value"
                                            label="text"
                                            placeholder="Select Status"
                                            class="custom-v-select searchable-select lead-qual-select lead-qual-select--unified"
                                        >
                                            <template #open-indicator="{ attributes }">
                                                <span v-bind="attributes">
                                                    <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                                </span>
                                            </template>
                                        </v-select>
                                
                                        <v-select append-to-body
                                            v-else-if="targetStageOrder === 10"
                                            v-model="formData.lead_status"
                                            :options="unqualifiedStatusOptions"
                                            :reduce="opt => opt.value"
                                            label="text"
                                            placeholder="Select Status"
                                            class="custom-v-select searchable-select lead-qual-select lead-qual-select--unified"
                                        >
                                            <template #open-indicator="{ attributes }">
                                                <span v-bind="attributes">
                                                    <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                                </span>
                                            </template>
                                        </v-select>
                                
                                        <v-select append-to-body
                                            v-else
                                            v-model="formData.lead_status"
                                            :options="defaultLeadStatusOptions"
                                            :reduce="opt => opt.value"
                                            label="text"
                                            placeholder="Not Selected"
                                            class="custom-v-select searchable-select lead-qual-select lead-qual-select--unified"
                                        >
                                            <template #open-indicator="{ attributes }">
                                                <span v-bind="attributes">
                                                    <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                                </span>
                                            </template>
                                        </v-select>
                                    </template>
                                </div>
                        <!-- Lead Type (Sale/Rent) -->
                        <div v-if="missingFields.includes('lead_type')" class="form-group mb-0 lead-qual-field">
                            <label class="form-label ">Lead Type <span class="text-danger">*</span></label>
                            <v-select append-to-body
                                v-model="formData.lead_type"
                                :options="leadTypeOptions"
                                :reduce="opt => opt.value"
                                label="text"
                                placeholder="Select Lead Type"
                                class="custom-v-select searchable-select lead-qual-select lead-qual-select--enhanced lead-qual-select--unified"
                                @update:model-value="handleLeadTypeChange"
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                            </v-select>
                        </div>

                        <!-- Property Status (Ready/Off Plan/Both) -->
                        <div v-if="missingFields.includes('property_status')  && !isRentOnly" class="form-group mb-0 lead-qual-field">
                            <label class="form-label ">Property Status <span class="text-danger">*</span></label>
                            <v-select append-to-body
                                v-model="formData.property_status"
                                :options="propertyStatusOptions"
                                :reduce="opt => opt.value"
                                label="text"
                                placeholder="Select Property Status"
                                class="custom-v-select searchable-select lead-qual-select lead-qual-select--enhanced lead-qual-select--unified"
                            >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                            </v-select>
                        </div>
                            </div>
                            <!-- Available Date -->
                            <div v-if="missingFields.includes('available_date')" class="form-group mb-3">
                                <label class="form-label">Available Date</label>
                                <AdvancedDatePicker
                                    v-model="formData.available_date"
                                    date-only
                                    placeholder="Select date"
                                />
                            </div>

                            <!-- Branch -->
                            <div v-if="missingFields.includes('branch')" class="form-group mb-3">
                                <label class="form-label">Branch</label>
                                <v-select append-to-body
                                    v-model="formData.branch"
                                    :options="branchOptions"
                                    :reduce="opt => opt.value"
                                    label="text"
                                    placeholder="Select Branch"
                                    class="custom-v-select searchable-select"
                                >
                                    <template #open-indicator="{ attributes }">
                                        <span v-bind="attributes">
                                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                        </span>
                                    </template>
                                </v-select>
                            </div>

                            <!-- Why Lost -->
                            <div v-if="missingFields.includes('why_lost_lead') || missingFields.includes('lost_reason')" class="form-group mb-3 lost-reason-field">
                                <label class="form-label">Why Lost</label>
                                <v-select append-to-body
                                    v-model="formData.lost_reason"
                                    :options="lostReasonOptions"
                                    :reduce="opt => opt.value"
                                    label="text"
                                    placeholder="Select Reason"
                                    class="custom-v-select searchable-select lost-reason-select"
                                >
                                    <template #open-indicator="{ attributes }">
                                        <span v-bind="attributes">
                                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                        </span>
                                    </template>
                                </v-select>
                            </div>
                        </div>
                        <div 
                            v-if="['salutation'].some(f => missingFields.includes(f)) && !(interactionMode && formData.interaction_result === 'no_answer')" 
                            class="box-shadow"
                        >
                        <h5 class="section-title">Lead Information</h5>
                            <div v-if="missingFields.includes('salutation')" class="form-group mb-3">
                                <label class="form-label">Salutation</label>
                                <v-select append-to-body
                                    v-model="formData.salutation"
                                    :options="salutationOptions"
                                    :reduce="opt => opt.value"
                                    label="text"
                                    placeholder="Not Selected"
                                    class="custom-v-select searchable-select"
                                >
                                 <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                                </v-select>
                            </div>
                       </div>
                        <!-- 🟦 Basic Info -->
                        <div 
                            v-if="['budget_from','budget_to','area_id','property_type_id','bedrooms','purpose_buying'].some(f => missingFields.includes(f))" 
                            class="box-shadow client-req-order"
                        >
                          <h5 class="section-title">Client Requirement</h5>
                          <div v-if="missingFields.includes('budget_from') || missingFields.includes('budget_to')" class="form-group mb-3" style="order: 6;">
                              <label class="form-label">Budget (AED)</label>
                              <div
                                  ref="budgetTriggerRef"
                                  class="budget-field-wrap"
                              >
                                  <button
                                      type="button"
                                      class="custom-date-trigger"
                                      @click.stop="toggleBudgetDropdown"
                                  >
                                      <span>{{ budgetDisplay }}</span>
                                      <iconify-icon icon="lucide:chevron-down" />
                                  </button>
                              </div>
                          </div>

                            <div v-if="missingFields.includes('area_id')" class="form-group mb-3" style="order: 1;">
                                <label class="form-label">Location / Area</label>
                                <v-select append-to-body
                                    v-model="formData.area_id"
                                    :options="areaOptions"
                                    :reduce="opt => opt.value"
                                    label="text"
                                    placeholder="Not Selected"
                                    class="custom-v-select searchable-select"
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
                                            <span class="location-option-name">{{ locationFirstLine(option.text) }}</span>
                                            <span class="location-option-subtitle">{{ locationSecondLine(option.text) }}</span>
                                        </div>
                                    </div>
                                </template>
                                <template #selected-option="option">
                                    <div v-if="option" class="location-selected">
                                        <span class="location-selected-name">{{ locationFirstLine(option.text) }}</span>
                                        <span class="location-selected-subtitle">{{ locationSecondLine(option.text) }}</span>
                                    </div>
                                </template>
                                </v-select>
                            </div>
                            <div v-if="missingFields.includes('bedrooms') && !isPlotsOrLand" class="form-group mb-3" style="order: 5;">
                                <label class="form-label">How Many Bedrooms?</label>
                                <v-select append-to-body
                                    v-model="formData.bedrooms"
                                    :options="bedroomOptions"
                                    :reduce="opt => opt.value"
                                    label="text"
                                    placeholder="Select Bedrooms"
                                    class="custom-v-select searchable-select"
                                >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                                </v-select>
                            </div>
                            <div v-if="missingFields.includes('property_type_id')" class="form-group mb-3" style="order: 2;">
                                <label class="form-label">Property Type</label>
                                <v-select append-to-body
                                    v-model="formData.property_type_id"
                                    :options="propertyTypeOptions"
                                    :reduce="opt => opt.value"
                                    label="text"
                                    placeholder="Not Selected"
                                    class="custom-v-select searchable-select"
                                >
                               <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                                </v-select>
                            </div>

                            

                            <div v-if="missingFields.includes('purpose_buying')  && !isRentOnly" class="form-group mb-3" style="order: 8;">
                                <label class="form-label">Purpose Of Purchase</label>
                                <v-select append-to-body
                                    v-model="formData.purpose_buying"
                                    :options="purposeOptions"
                                    :reduce="opt => opt.value"
                                    label="text"
                                    placeholder="Not Selected"
                                    class="custom-v-select searchable-select"
                                >
                                <template #open-indicator="{ attributes }">
                                    <span v-bind="attributes">
                                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                                    </span>
                                </template>
                                </v-select>
                            </div>
                        </div>

                    

                    </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-light" @click="closeModal">Cancel</button>
                <button type="button" class="btn btn-primary" @click="handleSubmit" :disabled="isSubmitting">
                    <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-2"></span>
                    Submit
                </button>
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="showReminderDropdown"
                ref="reminderDropdownPanelRef"
                class="reminder-dropdown reminder-dropdown--portal"
                :style="reminderDropdownStyle"
                @click.stop
            >
                <div class="reminder-options">
                    <div
                        class="reminder-option"
                        v-for="option in reminderOptions"
                        :key="option.value"
                        @click="selectReminderOption(option.value)"
                    >
                        <span class="reminder-option-text">{{ option.label }}</span>
                        <div class="reminder-checkbox" :class="{ 'checked': reminders.includes(option.value) }">
                            <iconify-icon
                                v-if="reminders.includes(option.value)"
                                icon="lucide:check"
                                class="check-icon"
                            ></iconify-icon>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
        <Teleport to="body">
            <div
                v-if="showBudgetDropdown"
                ref="budgetDropdownPanelRef"
                class="budget-dropdown budget-dropdown--portal"
                :style="budgetDropdownStyle"
                @mousedown.stop
                @click.stop
            >
                <div class="budget-from-to-row">
                    <div v-if="missingFields.includes('budget_from')" class="budget-col">
                        <label class="budget-input-label">From</label>
                        <input
                            v-model="budgetFromDisplay"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            placeholder="0"
                            class="form-control budget-input budget-dropdown-input"
                            @mousedown.stop
                            @click.stop
                            @input="onBudgetFromInput"
                        >
                    </div>
                    <div v-if="missingFields.includes('budget_to')" class="budget-col">
                        <label class="budget-input-label">To</label>
                        <input
                            v-model="budgetToDisplay"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            placeholder="0"
                            class="form-control budget-input budget-dropdown-input"
                            @mousedown.stop
                            @click.stop
                            @input="onBudgetToInput"
                        >
                    </div>
                </div>
            </div>
        </Teleport>

        <DateTimePicker
            :show="showDateTimePicker"
            :model-value="reminderDate"
            @update:show="showDateTimePicker = $event"
            @update:model-value="handleCustomDateSelected"
            @apply="handleCustomDateApply"
            @cancel="handleCustomDateCancel"
        />
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import api from '@/plugins/axios'
import DateTimePicker from '../shared/DateTimePicker.vue'
import AdvancedDatePicker from '@/components/shared/AdvancedDatePicker.vue'
import { formatBudgetThousands, parseBudgetThousandsInput } from '@/utils/budgetInput'

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    leadId: {
        type: Number,
        default: null
    },
    targetStageId: {
        type: Number,
        default: null
    },
    targetStageName: {
        type: String,
        default: ''
    },
    targetStageOrder: {
        type: Number,
        default: null
    },
    missingFields: {
        type: Array,
        default: () => []
    },
    leadData: {
        type: Object,
        default: null
    },
    isConversion: {
        type: Boolean,
        default: false
    },
    interactionMode: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['update:modelValue', 'submit', 'closed'])

const visible = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
})

const isSubmitting = ref(false)
const areas = ref([])
const propertyTypes = ref([])

const showFields = computed(() => {
    return [3, 4, 5, 7, 8, 9, 10].includes(props.targetStageOrder)
})
const leadTypeOptions = [
    { value: 'sale', text: 'Sale' },
    { value: 'rent', text: 'Rent' },
    //  { value: 'both', text: 'Both' },
]

const propertyStatusOptions = [
    { value: 'ready', text: 'Ready' },
    { value: 'off_plan', text: 'Off Plan' },
    { value: 'both', text: 'Both' }
]

const hotWarmLeadOptions = [
    { value: 'cold', text: 'Cold Lead' },
    { value: 'warm', text: 'Warm Lead' },
    { value: 'hot', text: 'Hot Lead' }
]
const convertedStatusOptions = [
    { value: 'converted', text: 'Converted' }
]

/** Section 3: Cold / Warm / Hot radio columns (same values as hotWarmLeadOptions). */
const qualityTemperatureRadios = [
    {
        value: 'cold',
        label: 'Cold',
        ringColor: '#38bdf8',
        fillColor: '#0ea5e9',
        tooltip: "Cold leads are fresh or haven't been contacted recently.",
    },
    {
        value: 'warm',
        label: 'Warm',
        ringColor: '#f59e0b',
        fillColor: '#d97706',
        tooltip: 'Warm leads are engaged recently, likely 1-3 months ago.',
    },
    {
        value: 'hot',
        label: 'Hot',
        ringColor: '#f43f5e',
        fillColor: '#e11d48',
        tooltip: 'Hot leads are very active and ready for follow-up.',
    },
]

function qualityMetaForValue(value) {
    const q = qualityTemperatureRadios.find((o) => o.value === value)
    return (
        q || {
            ringColor: '#94a3b8',
            fillColor: '#64748b',
            tooltip: '',
            label: '',
        }
    )
}

const leadPoolStatusOptions = [
    { value: 'no_answer', text: 'No Answer' },
    { value: 'contacted', text: 'Contacted' },
    { value: 'wrong_person', text: 'Wrong Person' }
]

const unqualifiedStatusOptions = [

      { value: 'wrong_contact_details', text: 'Wrong Contact Details' },
        { value: 'no_answer_multiple_calls', text: 'No Answer — Multiple Calls' },
        { value: 'job_seeker', text: 'Job Seeker' },
        { value: 'broker', text: 'Broker' },
        { value: 'registered_by_mistake', text: 'Registered by Mistake' },
        { value: 'spam_leads', text: 'Spam Leads' },
        { value: 'blacklist', text: 'Black Lists' },
    
]

const defaultLeadStatusOptions = [
    { value: 'cold', text: 'Cold' },
    { value: 'warm', text: 'Warm' },
    { value: 'hot', text: 'Hot' }
]
const reminderOptions = [
    { label: 'When event starts', value: '0' },
    { label: '15 minutes before', value: '15' },
    { label: '30 minutes before', value: '30' },
    { label: '1 hour before', value: '60' },
    { label: '2 hours before', value: '120' },
    { label: '1 day before', value: '1440' }
]
const showReminderDropdown = ref(false)
const showDateTimePicker = ref(false)
const reminderDate = ref(new Date())
const reminders = ref([])
const reminderButtonRef = ref(null)
const reminderDropdownPanelRef = ref(null)
const reminderDropdownStyle = ref({})


// Add this computed property with your other computed properties
const isPlotsOrLand = computed(() => {
    const propertyTypeId = formData.value.property_type_id;
    if (!propertyTypeId) return false;
    
    const selectedType = propertyTypeOptions.value.find(opt => opt.value === propertyTypeId);
    if (!selectedType) return false;
    
    const typeName = selectedType.text.toLowerCase();
    console.log(typeName);
    return typeName.includes('plots') || typeName.includes('land');
});
// Add this computed property
const isRentOnly = computed(() => {
    return formData.value.lead_type === 'rent';
});

const budgetFromDisplay = ref('')
const budgetToDisplay = ref('')
const showBudgetDropdown = ref(false)
const budgetTriggerRef = ref(null)
const budgetDropdownPanelRef = ref(null)
const budgetDropdownStyle = ref({})

const budgetDisplay = computed(() => {
    const from = budgetFromDisplay.value || ''
    const to = budgetToDisplay.value || ''
    if (!from && !to) return 'Select budget range'
    if (from && to) return `${from} - ${to}`
    if (from) return `From ${from}`
    return `To ${to}`
})

const onBudgetFromInput = () => {
    const { numeric, display } = parseBudgetThousandsInput(budgetFromDisplay.value)
    formData.value.budget_from = numeric ?? ''
    budgetFromDisplay.value = display
}

const onBudgetToInput = () => {
    const { numeric, display } = parseBudgetThousandsInput(budgetToDisplay.value)
    formData.value.budget_to = numeric ?? ''
    budgetToDisplay.value = display
}

const handleLeadTypeChange = (value) => {
    formData.value.lead_type = value || ''
}

const updateBudgetDropdownPosition = () => {
    const el = budgetTriggerRef.value?.$el || budgetTriggerRef.value
    if (!el || typeof el.getBoundingClientRect !== 'function') return
    const r = el.getBoundingClientRect()
    budgetDropdownStyle.value = {
        position: 'fixed',
        top: `${Math.round(r.bottom + 8)}px`,
        left: `${Math.round(r.left)}px`,
        minWidth: `${Math.max(Math.round(r.width), 260)}px`,
        zIndex: '12060'
    }
}

const removeBudgetDropdownListeners = () => {
    window.removeEventListener('scroll', updateBudgetDropdownPosition, true)
    window.removeEventListener('resize', updateBudgetDropdownPosition)
}

const toggleBudgetDropdown = async () => {
    const next = !showBudgetDropdown.value
    showBudgetDropdown.value = next
    if (next) {
        await nextTick()
        updateBudgetDropdownPosition()
        window.addEventListener('scroll', updateBudgetDropdownPosition, true)
        window.addEventListener('resize', updateBudgetDropdownPosition)
    } else {
        removeBudgetDropdownListeners()
    }
}

const closeBudgetDropdown = () => {
    showBudgetDropdown.value = false
    removeBudgetDropdownListeners()
}

const syncBudgetDisplayFields = () => {
    budgetFromDisplay.value = formData.value.budget_from ? 
        formatBudgetThousands(formData.value.budget_from) : ''
    budgetToDisplay.value = formData.value.budget_to ? 
        formatBudgetThousands(formData.value.budget_to) : ''
}

const formattedReminderDate = computed(() => {
    const date = reminderDate.value ? new Date(reminderDate.value) : new Date()
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
    const dayName = days[date.getDay()]
    const monthName = months[date.getMonth()]
    const day = date.getDate()
    const hours = date.getHours()
    const minutes = date.getMinutes()
    const ampm = hours >= 12 ? 'pm' : 'am'
    const displayHours = hours % 12 || 12
    const displayMinutes = minutes < 10 ? `0${minutes}` : minutes
    return `${dayName}, ${monthName} ${day}, ${displayHours}:${displayMinutes} ${ampm}`
})

const selectReminderOption = (value) => {
    const index = reminders.value.indexOf(value)
    if (index > -1) {
        reminders.value.splice(index, 1)
    } else {
        reminders.value.push(value)
    }
}

const updateReminderDropdownPosition = () => {
    const el = reminderButtonRef.value?.$el || reminderButtonRef.value
    if (!el || typeof el.getBoundingClientRect !== 'function') return
    const r = el.getBoundingClientRect()
    reminderDropdownStyle.value = {
        position: 'fixed',
        top: `${Math.round(r.bottom + 8)}px`,
        left: `${Math.round(r.left)}px`,
        minWidth: `${Math.max(Math.round(r.width), 260)}px`,
        zIndex: '12050',
    }
}

const removeReminderDropdownListeners = () => {
    window.removeEventListener('scroll', updateReminderDropdownPosition, true)
    window.removeEventListener('resize', updateReminderDropdownPosition)
}

const closeReminderDropdown = () => {
    showReminderDropdown.value = false
    removeReminderDropdownListeners()
}

const toggleReminderDropdown = async () => {
    const next = !showReminderDropdown.value
    showReminderDropdown.value = next
    if (next) {
        await nextTick()
        updateReminderDropdownPosition()
        window.addEventListener('scroll', updateReminderDropdownPosition, true)
        window.addEventListener('resize', updateReminderDropdownPosition)
    } else {
        removeReminderDropdownListeners()
    }
}

const handleCustomDateSelected = (date) => {
    reminderDate.value = date
}

const handleCustomDateApply = (date) => {
    reminderDate.value = date
    closeReminderDropdown()
}

const handleCustomDateCancel = () => {}

const handleClickOutside = (event) => {
    const t = event.target
    const trigger = reminderButtonRef.value?.$el || reminderButtonRef.value
    const budgetTrigger = budgetTriggerRef.value?.$el || budgetTriggerRef.value
    if (trigger?.contains(t) || reminderDropdownPanelRef.value?.contains(t)) return
    if (budgetTrigger?.contains(t) || budgetDropdownPanelRef.value?.contains(t)) return
    closeReminderDropdown()
    closeBudgetDropdown()
}

const branchOptions = [
    { value: 'Abu Dhabi', text: 'Abu Dhabi' },
    { value: 'Dubai', text: 'Dubai' },
    { value: 'Sharjah', text: 'Sharjah' }
]

const lostReasonOptions = [
    // { value: 'bought_direct_from_developer', text: 'Bought Direct from Developer' },
    // { value: 'changed_decision', text: 'Changed Decision' },
    // { value: 'clients_budget_is_too_low', text: "Client's Budget Is Too Low" }
    { value: 'already bought', text: "Already bought" }
  
]

const salutationOptions = [
    { value: 'Mr.', text: 'Mr.' },
    { value: 'Ms.', text: 'Ms.' },
    { value: 'Mrs.', text: 'Mrs.' },
    { value: 'Dr.', text: 'Dr.' }
]

const purposeOptions = [
    { value: 'Live in', text: 'Live in' },
    { value: 'Short-term investment', text: 'Short-term investment' },
    { value: 'Long-term investment', text: 'Long-term investment' },
    // { value: 'Holiday home', text: 'Holiday home' },
    // { value: 'Rental', text: 'Rental' },
]

const bedroomOptions = computed(() => {
    const base = [{ value: 'Studio', text: 'Studio' }]
    const nums = Array.from({ length: 9 }, (_, i) => ({ value: i + 1, text: String(i + 1) }))
    return [...base, ...nums]
})

const locationFirstLine = (value) => {
    const name = String(value || '').trim()
    if (!name) return '—'
    const idx = name.indexOf(',')
    return idx > 0 ? name.slice(0, idx).trim() : name
}

const locationSecondLine = (value) => {
    const name = String(value || '').trim()
    if (!name) return 'UAE'
    const idx = name.indexOf(',')
    const rest = idx > 0 ? name.slice(idx + 1).trim() : ''
    return rest || 'UAE'
}

const areaOptions = computed(() => (areas.value || []).map(area => ({
    value: area.id,
    text: area.name
})))

const propertyTypeOptions = computed(() => (propertyTypes.value || []).map(type => ({
    value: type.id,
    text: type.name
})))

const formData = ref({
    reason: '',
    interaction_result: '',
    interaction_note: '',
    salutation: '',
    budget_from: '',
    budget_to: '',
    lead_type: '',
    property_status: '',
    currency: 'AED',
    area_id: '',
    property_type_id: '',
    bedrooms: '',
    purpose_buying: '',
    lead_source: '',
    lead_status: props.targetStageOrder === 6 ? 'converted' : '',
    available_date: '',
    branch: '',
    lost_reason: '',
    deal_name: ''

})

const loadLookupData = async () => {
    try {
        const [areasRes, typesRes] = await Promise.all([
            api.get('/listings/areas'),
            api.get('/listings/property-types')
        ])
        areas.value = areasRes.data.data || []
        propertyTypes.value = typesRes.data.data || []
    } catch (error) {
        console.error('Error loading lookup data:', error)
    }
}

const closeModal = () => {
    visible.value = false
    resetForm()
    emit('closed')
}

const resetForm = () => {
    formData.value = {
        reason: '',
        interaction_result: '',
        interaction_note: '',
        salutation: '',
        // budget: '',
        budget_from: '',
        budget_to: '',
        lead_type: '',
        property_status: '',
        currency: 'AED',
        area_id: '',
        property_type_id: '',
        bedrooms: '',
        purpose_buying: '',
        lead_source: '',
        lead_status: '',
        available_date: '',
        branch: '',
        lost_reason: '',
        deal_name: '',
    }
      budgetFromDisplay.value = ''
    budgetToDisplay.value = ''
    reminderDate.value = new Date()
    reminders.value = []
    showReminderDropdown.value = false
    showDateTimePicker.value = false
    removeReminderDropdownListeners()
    isSubmitting.value = false
}

// Validation for budget range
const budgetRangeError = ref('')

const validateBudgetRange = () => {
    const from = parseFloat(formData.value.budget_from)
    const to = parseFloat(formData.value.budget_to)
    
    if (from && to && from > to) {
        budgetRangeError.value = 'Budget From cannot be greater than Budget To'
        return false
    }
    budgetRangeError.value = ''
    return true
}

const handleSubmit = async () => {
    if (props.interactionMode) {
        if (!formData.value.interaction_result) {
            $showNotification('Please select answer or no answer', 'warning')
            return
        }
        if (!formData.value.interaction_note.trim()) {
            $showNotification('Please provide a note', 'warning')
            return
        }
    } else {
        // Validate reason
        if (props.targetStageOrder !== 6 && !formData.value.reason.trim()) {
            $showNotification('Please provide a reason', 'warning')
            return
        }
    }
    
    const isNoAnswerMode = props.interactionMode && formData.value.interaction_result === 'no_answer'
    const isPlotsOrLand = checkIsPlotsOrLand() 
    const isRentOnly = formData.value.lead_type === 'rent'
    
    // Validate based on stage and missing fields
    for (const field of props.missingFields) {
        
        // 1. Salutation - يتم تجاهله في وضع no_answer
        if (field === 'salutation' && !formData.value.salutation && !isNoAnswerMode) {
            $showNotification('Please select salutation', 'warning')
            return
        }
        
        // 2. Lead Type - يتم تجاهله في وضع no_answer
        if (field === 'lead_type' && !formData.value.lead_type && !isNoAnswerMode) {
            $showNotification('Please select lead type (Sale/Rent)', 'warning')
            return
        }
        
        // 3. Property Status - يتم إخفاؤه إذا كان lead type = rent أو في وضع no_answer
        if (field === 'property_status' && !formData.value.property_status && !isNoAnswerMode && !isRentOnly) {
            $showNotification('Please select property status', 'warning')
            return
        }
        
        // 4. Budget From
        if (field === 'budget_from' && !formData.value.budget_from && !isNoAnswerMode) {
            $showNotification('Please enter minimum budget', 'warning')
            return
        }
        
        // 5. Budget To
        if (field === 'budget_to' && !formData.value.budget_to && !isNoAnswerMode) {
            $showNotification('Please enter maximum budget', 'warning')
            return
        }
        
        // 6. Validate budget range if both are present
        if ((field === 'budget_from' || field === 'budget_to') && 
            formData.value.budget_from && formData.value.budget_to && !isNoAnswerMode) {
            if (!validateBudgetRange()) {
                $showNotification(budgetRangeError.value, 'warning')
                return
            }
        }
        
        // 7. Area ID
        if (field === 'area_id' && !formData.value.area_id && !isNoAnswerMode) {
            $showNotification('Please select area', 'warning')
            return
        }
        
        // 8. Property Type ID
        if (field === 'property_type_id' && !formData.value.property_type_id && !isNoAnswerMode) {
            $showNotification('Please select property type', 'warning')
            return
        }
        
        // 9. Bedrooms - يتم إخفاؤه إذا كان property type = plots/land
        if (field === 'bedrooms' && !formData.value.bedrooms && !isNoAnswerMode && !isPlotsOrLand) {
            $showNotification('Please select bedrooms', 'warning')
            return
        }
        
        // 10. Purpose Buying - يتم إخفاؤه إذا كان lead type = rent
        if (field === 'purpose_buying' && !formData.value.purpose_buying && !isNoAnswerMode && !isRentOnly) {
            $showNotification('Please select purpose', 'warning')
            return
        }
        
        // 11. Status Lead
        if (field === 'status_lead' && !isNoAnswerMode) {
            const targetOrder = props.targetStageOrder
            
            if (targetOrder === 4) {
                if (!formData.value.lead_status) {
                    $showNotification('Please select lead status (cold/warm/hot)', 'warning')
                    return
                }
            } else if (targetOrder === 6) {
                if (!formData.value.lead_status) {
                    $showNotification('Please select conversion status', 'warning')
                    return
                }
            } else if (targetOrder === 9) {
                if (!formData.value.lead_status) {
                    $showNotification('Please select lead pool status', 'warning')
                    return
                }
            } else if (targetOrder === 10) {
                if (!formData.value.lead_status) {
                    $showNotification('Please select unqualified status', 'warning')
                    return
                }
            }
        }
        
        // 12. Available Date
        if (field === 'available_date' && !formData.value.available_date) {
            $showNotification('Please select available date', 'warning')
            return
        }
        
        // 13. Branch
        if (field === 'branch' && !formData.value.branch) {
            $showNotification('Please select branch', 'warning')
            return
        }
        
        // 14. Lost Reason
        if ((field === 'why_lost_lead' || field === 'lost_reason') && !formData.value.lost_reason) {
            $showNotification('Please select lost reason', 'warning')
            return
        }
        if (field === 'deal_name'  && !formData.value.deal_name.trim()) {
            $showNotification('Please enter deal name', 'warning')
            return
        }
    }
    
    isSubmitting.value = true
    let bedroomsValue = formData.value.bedrooms
    if (bedroomsValue === 'Studio' || bedroomsValue === 'studio') {
        bedroomsValue = 0
    }
    
    try {
        const createLeadComment = async (text) => {
            const formData = new FormData()
            formData.append('lead_id', String(props.leadId))
            formData.append('comment', text || '')
            await api.post('/leads/add/new/comments', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            })
        }

        if (props.interactionMode && formData.value.interaction_result === 'no_answer') {
            const payload = {
                lead_id: props.leadId,
                title: formData.value.interaction_note,
                reminder_date: (reminderDate.value instanceof Date ? reminderDate.value : new Date(reminderDate.value)).toISOString()
            }
            if (reminders.value.length > 0) {
                payload.reminders = reminders.value
            }
            await api.post('/leads/activities', payload)
        }

        const reasonText = props.interactionMode
            ? (formData.value.interaction_result === 'answered'
                ? `Answered: ${formData.value.interaction_note}`
                : `No Answer - Reminder: ${formData.value.interaction_note}`)
            : formData.value.reason

        if (props.interactionMode && formData.value.interaction_result === 'answered') {
            await createLeadComment(reasonText)
        }

        const submitData = props.interactionMode
            ? {
                leadId: props.leadId,
                targetStageId: props.targetStageId,
                reason: reasonText,
                interaction_result: formData.value.interaction_result,
                ...(formData.value.interaction_result !== 'no_answer' && {
                    salutation: formData.value.salutation,
                    budget_from: formData.value.budget_from,
                    budget_to: formData.value.budget_to,
                    lead_type: formData.value.lead_type,
                    property_status: formData.value.property_status,
                    area_id: formData.value.area_id,
                    property_type_id: formData.value.property_type_id,
                    bedrooms: bedroomsValue,
                    purpose_buying: formData.value.purpose_buying,
                    lead_status: formData.value.lead_status,
                    deal_name: formData.value.deal_name,
                })
            }
            : {
                leadId: props.leadId,
                targetStageId: props.targetStageId,
                reason: reasonText,
                interaction_result: formData.value.interaction_result,
                salutation: formData.value.salutation,
                budget_from: formData.value.budget_from,
                budget_to: formData.value.budget_to,
                lead_type: formData.value.lead_type,
                ...(formData.value.lead_type !== 'rent' && {
                    property_status: formData.value.property_status,
                }),
                area_id: formData.value.area_id,
                property_type_id: formData.value.property_type_id,
                ...(!checkIsPlotsOrLand() && {
                    bedrooms: bedroomsValue,
                }),
                ...(formData.value.lead_type !== 'rent' && {
                    purpose_buying: formData.value.purpose_buying,
                }),
                lead_source: formData.value.lead_source,
                lead_status: formData.value.lead_status,
                available_date: formData.value.available_date,
                branch: formData.value.branch,
                lost_reason: formData.value.lost_reason,
                deal_name: formData.value.deal_name,
            }
        
        // Remove empty fields
        Object.keys(submitData).forEach(key => {
            if (submitData[key] === null || submitData[key] === undefined || submitData[key] === '') {
                delete submitData[key]
            }
        })
        
        emit('submit', submitData)
        closeModal()
    } catch (error) {
        console.error('Error submitting:', error)
        $showNotification('An error occurred while submitting', 'error')
    } finally {
        isSubmitting.value = false
    }
}

const checkIsPlotsOrLand = () => {
    const propertyTypeId = formData.value.property_type_id;
    if (!propertyTypeId) return false;
    
    const selectedType = propertyTypeOptions.value.find(opt => opt.value === propertyTypeId);
    if (!selectedType) return false;
    
    const typeName = selectedType.text.toLowerCase();
    return typeName.includes('plot') || typeName.includes('land') || typeName.includes('plots') || typeName.includes('lands');
}

watch(visible, (newVal) => {
    if (newVal) {
        loadLookupData()
        if (props.leadData) {
            formData.value.salutation = props.leadData.salutation || ''
            formData.value.budget_from = props.leadData.budget_from || ''
            formData.value.budget_to = props.leadData.budget_to || ''
            formData.value.lead_type = props.leadData.lead_type || ''
            formData.value.property_status = props.leadData.property_status || ''
            formData.value.currency = props.leadData.currency || 'AED'
            formData.value.area_id = props.leadData.area_id || ''
            formData.value.property_type_id = props.leadData.property_type_id || ''
            formData.value.bedrooms = props.leadData.bedrooms || ''
            formData.value.purpose_buying = props.leadData.purpose_buying || ''
            formData.value.lead_source = props.leadData.lead_source || ''
            // للمرحلة 6، اجعل القيمة 'converted' إذا لم تكن موجودة
            if (props.targetStageOrder === 6) {
                formData.value.lead_status = props.leadData.lead_status || 'converted'
            } else {
                formData.value.lead_status = props.leadData.lead_status || ''
            }
            formData.value.deal_name = props.leadData.deal_name || ''
            syncBudgetDisplayFields()
        } else if (props.targetStageOrder === 6) {
            // إذا لم يوجد lead data وكانت المرحلة 6، اجعل القيمة 'converted'
            formData.value.lead_status = 'converted'
        }
    }
})
onMounted(() => {
    console.log(props.targetStageOrder);
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
    removeReminderDropdownListeners()
    removeBudgetDropdownListeners()
})

defineExpose({
    show: () => { visible.value = true },
    hide: () => { visible.value = false }
})
</script>

<style scoped>
.client-req-order {
    display: flex;
    flex-direction: column;
}

/* نفس الـ styles الموجود مع إضافة الـ reason-textarea */
.stage-change-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000  !important;
    pointer-events: auto !important;
}

.stage-change-modal {
    background: #ffffff;
    border-radius: 14px;
    width: min(92vw, 760px);
    max-width: 760px;
    max-height: 92vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #e8edf3;
    box-shadow: 0 18px 55px rgba(15, 23, 42, 0.18);
    pointer-events: auto !important;
    position: relative;
    z-index: 2001 !important;
}

.stage-change-modal * {
    pointer-events: auto !important;
}

/* إزالة أي تأثيرات قد تمنع الكتابة */
.form-control,
.form-select,
.reason-textarea {
    pointer-events: auto !important;
    background: white !important;
    user-select: text !important;
}

.stage-change-modal.modal-wide {
    max-width: 860px;
}

.modal-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #edf1f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(180deg, #fcfdff 0%, #ffffff 100%);
}

.modal-title {
    margin: 0;
    font-size: 0.92rem !important;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: 0.1px;
}

.btn-close {
     position: absolute;
    top: 8px;
    right: -61px;
    width: 83px;
    height: 49px;
    color: rgb(255, 255, 255);
    font-size: 18px;
    line-height: 1;
    box-shadow: rgba(15, 23, 42, 0.2) 0px 8px 16px;
    z-index: -1;
    display: flex;
    justify-content: center;
    align-items: center;
    border-width: 1px;
    border-style: solid;
    border-color: rgba(115, 62, 135, 0.75);
    border-image: initial;
    border-radius: 999px;
    background: var(--gradient-crm, linear-gradient(135deg, #0b0736 0%, #733e87 100%));
    padding: 0px;
    transition: filter 0.2s;
}

.modal-body {
    padding: 1rem 1.25rem;
    background: #fbfcfe;
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
}

.modal-footer {
    padding: 0.85rem 1.25rem;
    border-top: 1px solid #edf1f6;
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    background: #ffffff;
}

.reason-textarea {
    width: 100%;
    padding: 0.5rem 0.625rem;
    border: 1px solid #d6dee8;
    border-radius: 10px;
    font-size: 0.8rem;
    min-height: 70px;
    resize: vertical;
    color: #0f172a;
    background: #ffffff;
}

.reason-textarea-lg {
    min-height: 140px;
}

.reason-textarea:focus {
    outline: none;
    border-color: #0ea5e9;
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.12);
}

.form-group {
    margin-bottom: 0.55rem;
}

.form-label {
    display: block;
    margin-bottom: 0.45rem;
    font-weight: 650;
    font-size: 0.74rem;
    color: #1f2937;
}

.form-control,
.form-select {
    width: 100%;
    padding: 0.4rem 0.55rem;
    border: 1px solid #d6dee8;
    border-radius: 7px;
    font-size: 0.75rem;
    transition: border-color 0.15s ease-in-out;
    min-height: 34px;
    color: #0f172a;
    background: #ffffff;
}

.form-control::placeholder {
    font-size: 0.75rem;
    color: #9ca3af;
}

.deal-name-input::placeholder {
    font-size: 0.75rem !important;
    color: #9ca3af;
    opacity: 1;
}

.deal-name-input::-webkit-input-placeholder {
    font-size: 0.75rem !important;
}

.deal-name-input::-moz-placeholder {
    font-size: 0.75rem !important;
    opacity: 1;
}

/* Unified placeholder typography across this popup (inputs + selects) */
.stage-change-modal input::placeholder,
.stage-change-modal textarea::placeholder,
.stage-change-modal .form-control::placeholder,
.stage-change-modal .budget-input::placeholder {
    font-size: 0.75rem !important;
    color: #9ca3af !important;
    font-family: inherit !important;
    opacity: 1 !important;
}

:deep(.stage-change-modal .vs__search::placeholder) {
    font-size: 0.75rem !important;
    color: #9ca3af !important;
    font-family: inherit !important;
    opacity: 1 !important;
}

.form-control:focus,
.form-select:focus {
    outline: none;
    border-color: #0ea5e9;
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.12);
}

.input-group {
    display: flex;
    gap: 0.5rem;
}

.input-group .form-control {
    flex: 1;
}

.input-group .form-select {
    width: auto;
    min-width: 80px;
}

.text-danger {
    color: #ef4444;
}

.btn {
    padding: 0.25rem 0.7rem;
    border-radius: 50px;
    height: 36px;
    width: 84px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 12px;
    border: 1px solid transparent;
        text-align: center;
    align-items: center;
    justify-content: center;
}
.modal-footer{
        justify-content: center;

}
.btn-light {
    background-color: #f3f5f8;
    border-color: #e3e8ef;
    color: #0f172a;
}

.btn-light:hover {
    background-color: #e7ebf1;
}

.btn-primary {
    background-color: #0b1220;
    border-color: #0b1220;
    color: #FFFFFF;
}

.btn-primary:hover {
    background-color: #111827;
    border-color: #111827;
    color: #fff;
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.spinner-border {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    border: 0.2em solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: spinner-border 0.75s linear infinite;
}

@keyframes spinner-border {
    to {
        transform: rotate(360deg);
    }
}
.box-shadow {
   
    background-color: #ffffff;
    border: 1px solid #edf1f6;
    padding: 10px;
    margin: 6px 0px;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(15, 23, 42, 0.05);
}

/* Two fields per line in dynamic form */
.dynamic-form .box-shadow {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px 10px;
}

.dynamic-form .box-shadow.lead_qualification {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px 10px;
}

.dynamic-form .box-shadow.lead_qualification.lead-qualification-card {
    /*background: linear-gradient(165deg, #ffffff 0%, #f8fafc 45%, #f1f5f9 100%);*/
    /*border: 1px solid rgb(226 232 240 / 0.95);*/
    /*border-radius: 14px;*/
    /*padding: 14px 14px 12px;*/
    /*box-shadow:*/
    /*    0 1px 0 rgb(255 255 255 / 0.95) inset,*/
    /*    0 10px 40px -12px rgb(15 23 42 / 0.12),*/
    /*    0 2px 8px rgb(15 23 42 / 0.04);*/
}

/*.dynamic-form .box-shadow.lead_qualification.lead-qualification-card .section-title {*/
/*    color: #0f172a;*/
/*    font-weight: 700;*/
/*    letter-spacing: 0.02em;*/
/*    margin-bottom: 6px;*/
/*}*/

.lead-qualification-card__title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px !important;
    padding-bottom: 10px;
    border-bottom: 1px solid rgb(226 232 240 / 0.85);
}

.lead-qualification-card__title::before {
    content: '';
    width: 4px;
    height: 16px;
    border-radius: 4px;
    background: linear-gradient(180deg, #f59e0b 0%, #d97706 100%);
    box-shadow: 0 0 12px rgb(245 158 11 / 0.45);
}

.lead-qualification-trio {
    grid-column: 1 / -1;
    display: flex;
    align-items: stretch;
    gap: 14px;
    width: 100%;
    margin-bottom: 4px;
    overflow: visible;
    position: relative;
    z-index: 1;
}

.lead-qual-field {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
    overflow: visible;
}

.lead-qual-field__label {
    font-size: 0.68rem !important;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #64748b !important;
    margin-bottom: 0 !important;
}

.lead-qual-field :deep(.v-select) {
    width: 100%;
}

@media (max-width: 900px) {
    .lead-qualification-trio {
        flex-direction: column;
        gap: 16px;
    }
}
.dynamic-form .box-shadow .form-group {
    margin-bottom: 0 !important;
}

/* Make Why Lost select use full row width in grid layout */
.dynamic-form .box-shadow .form-group.lost-reason-field {
    grid-column: 1 / -1;
}

:deep(.searchable-select .vs__dropdown-toggle) {
    min-height: 42px;
    border: 1px solid #cfd8e3;
    border-radius: 10px;
    padding: 0 10px;
    background: #fff;
}

:deep(.searchable-select .vs__selected-options) {
    align-items: center;
    min-height: 32px;
}

:deep(.searchable-select .vs__selected),
:deep(.searchable-select .vs__search),
:deep(.searchable-select .vs__search::placeholder) {
    font-size: 0.76rem;
    color: #111827;
}

:deep(.searchable-select .vs__actions) {
    padding: 0 4px 0 8px;
}

:deep(.searchable-select .vs__dropdown-menu) {
    font-size: 0.75rem;
    z-index: 3000;
    border: 1px solid #e2e8f0;
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.14);
    border-radius: 8px;
}

:deep(.searchable-select .vs__dropdown-option) {
    color: #111827 !important;
    background: #fff !important;
    font-size: 14px !important;
}

:deep(.searchable-select .vs__dropdown-option--highlight) {
    color: #111827 !important;
    background: #f3f4f6 !important;
}

:deep(.searchable-select .vs__dropdown-option--selected) {
    color: #111827 !important;
    background: #e5e7eb !important;
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

:deep(.custom-v-select svg) {
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
      font-size: 14px !important;
}

:deep(.custom-v-select .vs__dropdown-option--highlight) {
    background: #733E87 !important;
    color: #fff !important;
}

:deep(.custom-v-select .vs__dropdown-option--selected) {
    background: #733E87 !important;
    color: #fff !important;
}

/* Show full reason text for Why Lost select */
:deep(.lost-reason-select .vs__dropdown-toggle) {
    height: auto;
    min-height: 42px;
}

:deep(.lost-reason-select .vs__selected-options) {
    overflow: visible;
    max-width: none;
}

:deep(.lost-reason-select .vs__selected) {
    /* white-space: normal; */
    line-height: 1.3;
    padding-top: 8px;
    padding-bottom: 8px;
    text-overflow: clip;
}

:deep(.lost-reason-select .vs__dropdown-menu) {
    min-width: max-content;
    width: max-content;
    max-width: min(92vw, 560px);
}

:deep(.lost-reason-select .vs__dropdown-option) {
    white-space: normal;
    word-break: break-word;
      font-size: 14px !important;
}

.budget-input::placeholder {
    font-size: 0.62rem !important;
    color: #9ca3af !important;
}

.budget-field-wrap {
    position: relative;
}

.custom-date-trigger {
    width: 100%;
    min-height: 42px;
    border-radius: 10px;
    border: 1px solid #d7dfeb;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 12px;
    font-size: 0.76rem;
    color: #111827;
}

.budget-dropdown--inline {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    width: 100%;
    min-width: 220px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 10px 24px rgba(2, 6, 23, 0.12);
    padding: 10px;
    z-index: 1200;
}

.budget-dropdown--portal {
    background: #fff;
    border: 1px solid #e2e8f0;
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
    min-height: 38px !important;
}

.location-option {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 3px 0;
}

.location-option-icon {
    font-size: 14px;
    color: #64748b;
    margin-top: 2px;
    flex-shrink: 0;
}

.location-option-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.location-option-name {
    font-size: 12px;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.25;
}

.location-option-subtitle {
    font-size: 11px;
    color: #64748b;
    line-height: 1.2;
}

.location-selected {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 1px;
    line-height: 1.2;
    min-width: 0;
}

.location-selected-name {
    font-size: 12px;
    font-weight: 600;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.location-selected-subtitle {
    font-size: 11px;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

/* Smooth, clean scroll for long content */
.stage-change-modal::-webkit-scrollbar {
    width: 8px;
}

.stage-change-modal::-webkit-scrollbar-thumb {
    background: #cfd8e3;
    border-radius: 999px;
}

@media (max-width: 768px) {
    .stage-change-modal-overlay {
        align-items: flex-end;
        justify-content: center;
        padding: 0;
        background-color: rgba(15, 23, 42, 0.45);
    }

    .stage-change-modal,
    .stage-change-modal.modal-wide {
        width: 100%;
        max-width: none;
        max-height: min(92dvh, 900px);
        border-radius: 22px 22px 0 0;
        margin: 0;
        align-self: flex-end;
    }

    .stage-change-modal .modal-header {
        border-radius: 22px 22px 0 0;
    }

    .dynamic-form .box-shadow {
        grid-template-columns: 1fr;
    }
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

:deep(.custom-v-select svg) {
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
    background: #733E87 !important;
    color: #fff !important;
}

:deep(.custom-v-select .vs__dropdown-option--selected) {
    background: #733E87 !important;
    color: #fff !important;
}

.section-title  {
    grid-column: span 2;
    font-size:13px !important;
}
.dynamic-form .box-shadow.lead_qualification .section-title  {
    grid-column: span 3;

}
.comment-block-title {
    font-size: 12px;
    font-weight: 700;
    color: #0f172a;
    margin: 2px 0 8px;
}

.call-result-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.call-result-card {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #ffffff;
    min-height: 86px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: #334155;
    transition: all 0.2s ease;
}

.call-result-card:hover {
    border-color: #cbd5e1;
    background: #f8fafc;
}

.call-result-card.is-active {
    border-color: #f2b84b;
    background: #fff8ea;
    box-shadow: 0 0 0 2px rgba(242, 184, 75, 0.2);
}

.call-result-icon {
    font-size: 20px;
    color: #f2b84b;
}

.call-result-text {
    font-size: 12px;
    font-weight: 600;
    color: #1f2937;
}

@media (max-width: 640px) {
    .call-result-grid {
        grid-template-columns: 1fr;
    }
}

.reminder-controls {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.activity-control-btn {
    border: 1px solid #d6dee8;
    border-radius: 10px;
    background: #fff;
    color: #344054;
    padding: 8px 12px;
    font-size: 12px;
    font-weight: 500;
}

.activity-control-icon {
    font-size: 16px;
    color: #64748b;
}

.activity-control-btn-bell {
    min-width: 116px;
    justify-content: center;
}

.reminder-dropdown-wrapper {
    position: relative;
    z-index: 3505;
}

.reminder-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    min-width: 260px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
    z-index: 3510;
}

.reminder-dropdown--portal {
    z-index: 12050;
}

.reminder-options {
    display: flex;
    flex-direction: column;
    max-height: 320px;
    overflow-y: auto;
}

.reminder-option {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    cursor: pointer;
}

.reminder-option:hover {
    background: #f8fafc;
}

.reminder-option-text {
    font-size: 12px;
    color: #334155;
}

.reminder-checkbox {
    width: 18px;
    height: 18px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    background: #ffffff;
}

.reminder-checkbox.checked {
    background: #4f46e5;
    border-color: #4f46e5;
}

.check-icon {
    font-size: 12px;
}

/* Quality Status — compact v-select dropdown (Cold / Warm / Hot) */
.qs-sel {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
}

.qs-sel-label {
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: -0.02em;
    color: #0f172a;
}

.qs-dd-row {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    text-align: left;
    width: 100%;
}

.qs-dd-dot {
    width: 14px;
    height: 14px;
    margin-top: 1px;
    border-radius: 50%;
    border: 2px solid var(--qs-ring, #94a3b8);
    background: transparent;
    box-sizing: border-box;
    flex-shrink: 0;
    transition:
        background 0.15s ease,
        border-color 0.15s ease,
        box-shadow 0.15s ease;
}

.qs-dd-dot--sm {
    width: 12px;
    height: 12px;
    margin-top: 0;
    border-width: 2px;
}

.qs-dd-dot.is-filled {
    background: var(--qs-fill, #64748b);
    border-color: var(--qs-fill, #64748b);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--qs-fill, #64748b) 22%, transparent);
}

.qs-dd-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.qs-dd-title {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: -0.01em;
    color: #0f172a;
    line-height: 1.25;
}

.qs-opt-wrap {
    position: relative;
    width: 100%;
    min-height: 100%;
    overflow: visible;
}

/* Quality tooltip floats outside the list: to the right (desktop) or above (narrow) */
.qs-dd-hover-tip {
    position: absolute;
    left: calc(100% + 12px);
    top: 50%;
    transform: translateY(-50%) translateX(-6px);
    width: min(280px, calc(100vw - 48px));
    padding: 11px 14px;
    font-size: 0.72rem;
    line-height: 1.55;
    color: #f8fafc;
    text-align: left;
    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
    background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
    border: 1px solid rgb(255 255 255 / 0.12);
    border-radius: 10px;
    box-shadow:
        0 14px 36px rgb(0 0 0 / 0.38),
        0 0 0 1px rgb(255 255 255 / 0.06) inset;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition:
        opacity 0.2s ease,
        transform 0.2s ease,
        visibility 0.2s;
    z-index: 10060;
    filter: drop-shadow(0 6px 16px rgb(0 0 0 / 0.2));
}

.qs-dd-hover-tip::before {
    content: '';
    position: absolute;
    right: 100%;
    top: 50%;
    margin-top: -6px;
    border: 6px solid transparent;
    border-right-color: #1e293b;
}

.qs-dd-hover-tip__icon {
    display: inline-block;
    vertical-align: -0.12em;
    margin-right: 6px;
    font-size: 0.8rem;
    color: #5eead4;
}

.qs-opt-wrap:hover .qs-dd-hover-tip {
    opacity: 1;
    visibility: visible;
    transform: translateY(-50%) translateX(0);
}

@media (max-width: 640px) {
    .qs-dd-hover-tip {
        left: 0;
        right: 0;
        top: auto;
        bottom: calc(100% + 8px);
        width: 100%;
        transform: translateY(4px);
    }

    .qs-dd-hover-tip::before {
        right: auto;
        left: 50%;
        top: 100%;
        margin-top: 0;
        margin-left: -6px;
        border: 6px solid transparent;
        border-right-color: transparent;
        border-top-color: #1e293b;
    }

    .qs-opt-wrap:hover .qs-dd-hover-tip {
        transform: translateY(0);
    }
}

/* Unified trio selects — equal height, brand accent */
/*:deep(.lead-qual-select--unified .vs__dropdown-toggle) {*/
    /*min-height: 48px;*/
    /*height: 48px;*/
    /*padding: 0 12px;*/
    /*border-radius: 12px;*/
    /*border: 1px solid #cbd5e1;*/
    /*background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);*/
    /*box-shadow:*/
    /*    0 1px 0 rgb(255 255 255 / 0.9) inset,*/
    /*    0 2px 8px rgb(15 23 42 / 0.06);*/
    /*transition:*/
    /*    border-color 0.2s ease,*/
    /*    box-shadow 0.2s ease;*/
/*}*/

/*:deep(.lead-qual-select--unified .vs__selected-options) {*/
/*    min-height: 44px;*/
/*    align-items: center;*/
/*}*/

/*:deep(.lead-qual-select--unified .vs__selected),*/
/*:deep(.lead-qual-select--unified .vs__search) {*/
/*    font-size: 0.78rem;*/
/*    font-weight: 600;*/
/*    margin: 0;*/
/*    padding: 0;*/
/*    color: #0f172a;*/
/*}*/

/*:deep(.lead-qual-select--unified.vs--open .vs__dropdown-toggle) {*/
/*    border-color: #f59e0b;*/
/*    box-shadow:*/
/*        0 0 0 3px rgb(245 158 11 / 0.22),*/
/*        0 2px 12px rgb(245 158 11 / 0.12);*/
/*}*/

:deep(.lead-qual-select--unified .vs__open-indicator-icon) {
    font-size: 15px;
    color: #94a3b8;
}

:deep(.lead-qual-select--quality) {
    overflow: visible !important;
}

:deep(.lead-qual-select--quality .vs__dropdown-menu) {
    padding: 8px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow:
        0 16px 40px rgb(15 23 42 / 0.16),
        0 0 0 1px rgb(255 255 255 / 0.8) inset;
    z-index: 3100;
    overflow: visible !important;
    min-width: 100%;
}

:deep(.lead-qual-select--quality .vs__dropdown-option) {
    padding: 0 !important;
    border-radius: 10px;
    margin-bottom: 4px;
    overflow: visible !important;
}

:deep(.lead-qual-select--quality .vs__dropdown-option:last-child) {
    margin-bottom: 0;
}

:deep(.lead-qual-select--quality .vs__dropdown-option--highlight),
:deep(.lead-qual-select--quality .vs__dropdown-option--selected) {
    background: transparent !important;
}

:deep(.lead-qual-select--quality .vs__dropdown-option--highlight .qs-dd-row) {
    background: #fffbeb !important;
    box-shadow: inset 0 0 0 1px rgb(245 158 11 / 0.35);
}

:deep(.lead-qual-select--quality .vs__dropdown-option--selected .qs-dd-row) {
    background: linear-gradient(90deg, rgb(255 251 235 / 0.95) 0%, #ffffff 100%) !important;
    box-shadow: inset 3px 0 0 0 #f59e0b;
}

:deep(.lead-qual-select--quality .qs-dd-row) {
    padding: 10px 10px;
    border-radius: 10px;
    transition: background 0.15s ease;
}

:deep(.lead-qual-select--enhanced .vs__dropdown-menu) {
    border-radius: 12px;
    padding: 6px;
    z-index: 3100;
    border: 1px solid #e2e8f0;
    box-shadow: 0 14px 36px rgb(15 23 42 / 0.14);
}

:deep(.lead-qual-select--enhanced .vs__dropdown-option) {
    border-radius: 10px;
    padding: 9px 12px !important;
    font-size: 0.78rem !important;
    font-weight: 600;
}

:deep(.ead-qual-select--enhanced .vs__dropdown-option--highlight) {
    background: #fffbeb !important;
    color: #0f172a !important;
}

:deep(.lead-qual-select--enhanced .vs__dropdown-option--selected) {
    background: linear-gradient(90deg, #fff7ed 0%, #ffffff 100%) !important;
    color: #0f172a !important;
    box-shadow: inset 3px 0 0 0 #f59e0b;
}
.close-btn-custom {
    /*font-size: 20px;*/
    color: #000;
}
</style>
<style>
    .vs__search, .vs__search:focus{
    z-index:0 !important;
}
 .vs__dropdown-option{
        font-size: 14px !important;
    }
</style>