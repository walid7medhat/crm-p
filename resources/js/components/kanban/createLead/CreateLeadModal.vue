    <template>
        <b-modal 
            id="create-lead-modal" 
            v-model="show"
            hide-header
            hide-footer
            size="xl"
            centered
            body-class="p-0"
            dialog-class="kanban-mobile-fullscreen-modal"
        >
            <div class="create-lead-modal-content p-3">
                <div class="create-lead-modal-top">
                    <ModalHeader title="Create New Lead" @close="closeModal" />

                    <StageSelector
                        class="px-1"
                        v-model="form.stage_id"
                        :require-validation="false"
                        @stage-change-request="handleStageChangeRequest"
                    />
                    <div v-if="validationErrors.stage_id" class="invalid-feedback d-block px-1 mb-0">
                        {{ validationErrors.stage_id[0] }}
                    </div>
                </div>

                <!-- Form Content -->
                <div class="form-scroll-area">
                    <div class="step-content">
                        <div class="row g-4 p-0 p-md-4 position-relative">
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
    
                          
    
                            <!-- Contact Details Section -->
                                <div class="contact-details-card p-3">
                                    <span class="section-title d-block mb-3">Lead Information</span>
                                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                                                      <!-- Salutation, First Name, Last Name, Position -->
                                        <div class="col">
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
                                        <div class="col">
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
                                        <div class="col">
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
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center gap-2">
                                        
                                        <div class="col">
                                            <label class="form-label-custom">Primary Phone</label>
                                            <CrmPhoneInput 
                                                :model-value="form.work_phone"
                                                @update:model-value="(val) => { form.work_phone = val; console.log('Phone:', val); }"
                                                placeholder="Enter Phone Number" 
                                                :invalid="!!validationErrors.work_phone"
                                                :show-errors="showPhoneFieldErrors"
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
                                            <label class="form-label-custom">Secondary Phone</label>
                                            <CrmPhoneInput 
                                             :model-value="form.work_phone_2"
                                                @update:model-value="(val) => { form.work_phone_2 = val; console.log('Phone2:', val); }"
                                                placeholder="Enter Phone Number" 
                                                :invalid="!!validationErrors.work_phone_2"
                                                :show-errors="showPhoneFieldErrors"
                                               
                                            />
                                            <div v-if="validationErrors.work_phone_2" class="invalid-feedback d-block">
                                                {{ validationErrors.work_phone_2[0] }}
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
                                    </div>
                                </div>
    
                            <!-- Comments -->
                            <div class="col-12">
                                <label class="form-label-custom">Notes</label>
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
                            <div class="col-12" v-if="currentStageOrder !== 0">
                                <div class="additional-fields-card p-3">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <div>
                                            <span class="section-title d-block">Client Requirement</span>
                                        </div>
                                    </div>
    
                                    <div class="row gy-3 gx-0 mt-0 client-requirements-grid">
                                      
    
                                        <!-- Bedrooms (Stage 4) -->
                                        <div v-if="shouldShowField('bedrooms') && !isPlotsOrLand" class="col-md-4" style="order: 5;">
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
    
                                        <!-- Budget (single trigger with From/To dropdown) -->
                                        <div v-if="shouldShowField('budget')" class="col-md-4" style="order: 6;">
                                            <label class="form-label-custom">Budget (AED)</label>
                                            <div
                                                ref="budgetTriggerRef"
                                                class="budget-field-wrap"
                                                :class="{ 'is-invalid-group': !!(validationErrors.budget_from || validationErrors.budget_to || validationErrors.budget) }"
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
                                                                @mousedown.stop
                                                                @click.stop
                                                                @input="onBudgetToInput($event.target.value)"
                                                            />
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
    
                                        <!-- Purpose (Stage 4) -->
                                        <div v-if="shouldShowField('purpose_buying') && !isRentOnly" class="col-md-4" style="order: 8;">
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
    
                                        <!-- Source (Stage 4) -->
                                            <div class="col-md-4" style="order: 20;">
                                                <label class="form-label-custom">Source <span class="text-danger">*</span></label>
                                                <v-select 
                                                    v-model="form.lead_source" 
                                                    :options="dynamicSourceOptions" 
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
                                            
                                            <!-- حقول العميل المحيل (تظهر فقط عند اختيار Referral لموظف المبيعات) -->
                                            <div v-if="isReferralSelected" class="col-md-12" style="order: 21;">
                                                <div class="referral-client-card p-3">
                                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                                        <span class="section-title d-block">Source Client Information (Referral)</span>
                                                        <span class="text-muted small">Who referred this lead?</span>
                                                    </div>
                                                    <div class="row g-3">
                                                        <div class="col-md-3">
                                                            <label class="form-label-custom">Source Client Name <span class="text-danger">*</span></label>
                                                            <b-form-input 
                                                                v-model="form.source_client_name" 
                                                                placeholder="Enter name of person who referred"
                                                                class="custom-input"
                                                                :class="{ 'is-invalid': validationErrors.source_client_name }"
                                                            />
                                                            <div v-if="validationErrors.source_client_name" class="invalid-feedback d-block">
                                                                {{ validationErrors.source_client_name[0] }}
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label-custom">Source Client Phone <span class="text-danger">*</span></label>
                                                            <CrmPhoneInput 
                                                                v-model="form.source_client_phone" 
                                                                placeholder="Enter phone number"
                                                                :invalid="!!validationErrors.source_client_phone"
                                                                :show-errors="showPhoneFieldErrors"
                                                            />
                                                            <div v-if="validationErrors.source_client_phone" class="invalid-feedback d-block">
                                                                {{ validationErrors.source_client_phone[0] }}
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label-custom">Source Client Email</label>
                                                            <b-form-input 
                                                                v-model="form.source_client_email" 
                                                                placeholder="Enter email (optional)"
                                                                class="custom-input"
                                                                :class="{ 'is-invalid': validationErrors.source_client_email }"
                                                            />
                                                            <div v-if="validationErrors.source_client_email" class="invalid-feedback d-block">
                                                                {{ validationErrors.source_client_email[0] }}
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label-custom">Relation with Client</label>
                                                            <b-form-input 
                                                                v-model="form.source_relation" 
                                                                placeholder="e.g., Friend, Family, Colleague"
                                                                class="custom-input"
                                                                :class="{ 'is-invalid': validationErrors.source_relation }"
                                                            />
                                                            <div v-if="validationErrors.source_relation" class="invalid-feedback d-block">
                                                                {{ validationErrors.source_relation[0] }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="mt-2 text-muted small">
                                                        <iconify-icon icon="lucide:info"></iconify-icon>
                                                        This information helps track who referred this lead
                                                    </div>
                                                </div>
                                            </div>
                                                <!-- حقل اختيار العميل الموجود (يظهر فقط عند اختيار Self Lead لموظف المبيعات) -->
                                            <div v-if="isSelfLeadSelected" class="col-md-12" style="order: 21;">
                                                <div class="self-lead-client-card p-3">
                                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                                        <span class="section-title d-block">Select Existing Client</span>
                                                        <span class="text-muted small">Choose a client from your list</span>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-12">
                                                            <label class="form-label-custom">Search/Select Client <span class="text-danger">*</span></label>
                                                            <v-select
                                                                v-model="selectedExistingClient"
                                                                :options="clientsList"
                                                                :reduce="client => client"
                                                                :label="clientLabel"
                                                                :loading="isLoadingClients"
                                                                placeholder="Type to search or select a client..."
                                                                class="custom-v-select"
                                                                @option:selected="handleClientSelected"
                                                            >
                                                                <template #option="option">
                                                                    <div class="client-option">
                                                                        <div class="client-option-name">
                                                                            <strong>{{ option.name || option.lead_name || `${option.first_name} ${option.last_name}` }}</strong>
                                                                        </div>
                                                                        <div class="client-option-details">
                                                                            <span v-if="option.email" class="me-2">
                                                                                <iconify-icon icon="lucide:mail" width="12"></iconify-icon>
                                                                                {{ option.email }}
                                                                            </span>
                                                                            <span v-if="option.work_phone || option.phone">
                                                                                <iconify-icon icon="lucide:phone" width="12"></iconify-icon>
                                                                                {{ option.work_phone || option.phone }}
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </template>
                                                                <template #selected-option="option">
                                                                    <div v-if="option">
                                                                        <div class="selected-client-info">
                                                                            <strong>{{ option.name || option.lead_name || `${option.first_name} ${option.last_name}` }}</strong>
                                                                            <div class="small text-muted">
                                                                                {{ option.email }} | {{ option.work_phone || option.phone }}
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </template>
                                                                <template #no-options>
                                                                    <div class="text-center p-3">
                                                                        No clients found. Create a new client first.
                                                                    </div>
                                                                </template>
                                                            </v-select>
                                                            <div class="text-muted small mt-2">
                                                                <iconify-icon icon="lucide:info"></iconify-icon>
                                                                Selecting a client will auto-fill their information
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <!-- Location / Area (Stage 4) -->
                                        <div v-if="shouldShowField('area_id')" class="col-md-4" style="order: 1;">
                                            <label class="form-label-custom">Location / Area</label>
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
                                                            <span class="location-option-name">{{ locationFirstLine(option) }}</span>
                                                            <span class="location-option-subtitle">{{ locationSecondLine(option) }}</span>
                                                        </div>
                                                    </div>
                                                </template>
    
                                                <template #selected-option="option">
                                                    <div v-if="option" class="location-selected">
                                                        <span class="location-selected-name">{{ locationFirstLine(option) }}</span>
                                                        <span class="location-selected-subtitle">{{ locationSecondLine(option) }}</span>
                                                    </div>
                                                </template>
                                            </v-select>
                                        </div>
    
                                        <!-- Property Type (Stage 4) -->
                                        <div v-if="shouldShowField('property_type_id')" class="col-md-4" style="order: 2;">
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
    
                                        <!-- Lead Type (Sale/Rent) - جديد -->
                                        <div v-if="shouldShowField('lead_type')" class="col-md-4" style="order: 3;">
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
                                        
                                        <!-- Property Status (Ready/Off Plan/Both) - جديد -->
                                        <div v-if="shouldShowField('property_status') && !isRentOnly" class="col-md-4" style="order: 4;">
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
    
                                        <!-- Lead Status (Stages 4, 9, 10) -->
                                        <div v-if="shouldShowField('lead_status') || shouldShowField('lead_status_pool') || shouldShowField('unqualified_status')" class="col-md-4">
                                            <label class="form-label-custom">Quality Status</label>
                                            <v-select 
                                                v-model="form.status_lead" 
                                                :options="getLeadStatusOptions()" 
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
    
                                        <!-- Available Date (Stage 5) -->
                                        <div v-if="shouldShowField('available_date')" class="col-md-4">
                                            <label class="form-label-custom">Available Date</label>
                                            <AdvancedDatePicker
                                             dob-layout
                                                v-model="form.available_date"
                                                date-only
                                                placeholder="Select date"
                                                class="custom-input"
                                            />
                                        </div>
    
                                        <!-- Branch (Stage 7) -->
                                        <div v-if="shouldShowField('branch')" class="col-md-4">
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
    
                                        <!-- Lost Reason (Stage 8) -->
                                        <div v-if="shouldShowField('lost_reason')" class="col-md-4">
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
                    
                    <div
                        class="d-flex align-items-center gap-3"
                        :class="isMobileModal ? 'modal-footer-actions--mobile' : 'justify-content-end'"
                    >
                        <button
                            v-if="isMobileModal"
                            type="button"
                            class="btn-clear btn-cancel-mobile"
                            @click="closeModal"
                            :disabled="isSubmitting"
                        >
                            Cancel
                        </button>
                        <button
                            v-else
                            type="button"
                            class="btn-clear"
                            @click="resetForm"
                            :disabled="isSubmitting"
                        >
                            Clear
                        </button>
                        <button
                            type="button"
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
    import { ref, watch, computed, onMounted, onBeforeUnmount, nextTick, inject } from 'vue'
    import { BModal, BFormInput, BFormSelect, BFormTextarea } from 'bootstrap-vue-3'
    import vSelect from 'vue-select'
    import 'vue-select/dist/vue-select.css'
    import api from '@/plugins/axios'
    import avatar1 from '@/assets/images/users/user1.png'
    import ModalHeader from '../shared/ModalHeader.vue'
    import StageSelector from '../shared/StageSelector.vue'
    import ResponsiblePersonSelector from '../shared/ResponsiblePersonSelector.vue'
    import AdvancedDatePicker from '@/components/shared/AdvancedDatePicker.vue'
    import CrmPhoneInput from '@/components/common/CrmPhoneInput.vue'
    import { formatBudgetThousands, parseBudgetThousandsInput } from '@/utils/budgetInput'
    import { isNonEmptyPhoneValid } from '@/utils/phone'
    
    const props = defineProps({
        modelValue: Boolean
    })
    
    const emit = defineEmits(['update:modelValue', 'lead-created'])
    
    const show = ref(props.modelValue)
    const kanbanIsMobile = inject('kanbanIsMobile', ref(false))
    const isMobileViewportLocal = ref(false)
    const isMobileModal = computed(() => Boolean(kanbanIsMobile.value) || isMobileViewportLocal.value)

    const syncMobileModal = () => {
        if (typeof window === 'undefined') return
        isMobileViewportLocal.value = window.matchMedia('(max-width: 768px)').matches
    }

    const closeModal = () => {
        show.value = false
        emit('update:modelValue', false)
    }
    const users = ref([])
    const sources = ref([])
    const isLoadingUsers = ref(false)
    const isLoadingSources = ref(false)
    const isSubmitting = ref(false)
    const errorMessage = ref('')
    const sourceOptions = ref([])
    const validationErrors = ref({})
    const showPhoneFieldErrors = ref(false)
    const showAdditionalPanel = ref(false)
    // additional fields selection
    
    
    const clientsList = ref([])
    const isLoadingClients = ref(false)
    const selectedExistingClient = ref(null)



    const areas = ref([])
    const isLoadingAreas = ref(false)
    const propertyTypeOptions = ref([])
    
    
    const isSalesUser = ref(false)
    
    // خيارات الـ Source لحالة sales
    const salesSourceOptions = [
        { value: 'self_lead', text: 'Self Lead' },
        { value: 'referral', text: 'Referral' }
    ]
    
    
    // تحميل المراحل
    const stages = ref([])
    
    // دالة لتحديد ما إذا كان الحقل يجب أن يظهر
    const shouldShowField = (fieldKey) => {
        const order = currentStageOrder.value
        
        const fieldVisibility = {
            // Stage 3: Follow Up
            'salutation': [1,2,3,4,5,6],
            // Stage 4: Qualified
            'bedrooms': [1,2,3,4,5,6],
            'budget': [1,2,3,4,5,6],
            'lead_type': [1,2,3,4,5,6],
            'property_status': [1,2,3,4,5,6],
            'purpose_buying': [1,2,3,4,5,6],
            'lead_source': [1,2,3,4,5,6],
            'area_id': [1,2,3,4,5,6],
            'property_type_id': [1,2,3,4,5,6],
            'lead_status': [4,5],
            // Stage 5: Future
            'available_date': [5],
            // Stage 7: Shared
            'branch': [7],
            // Stage 8: Lost
            'lost_reason': [8],
            // Stage 9: Lead Pool
            'lead_status_pool': [9],
            // Stage 10: Unqualified
            'unqualified_status': [10],
        }
        
          const stages = fieldVisibility[fieldKey] || []
        return stages.includes(order) && order !== 0  
    }
    
    // Lead Type Options
    const leadTypeOptions = [
        { value: 'sale', text: 'Sale' },
        { value: 'rent', text: 'Rent' },
        // { value: 'both', text: 'Both' },
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
    // ==================== الشروط حسب نوع الـ Lead ====================
    
    // التحقق من نوع العقار (Plots or Land) - بناءً على property_type_id المختار
    const isPlotsOrLand = computed(() => {
        const propertyTypeId = form.value.property_type_id
        if (!propertyTypeId) return false
        
        const selectedType = propertyTypeOptions.value.find(opt => opt.value === propertyTypeId)
        if (!selectedType) return false
        
        const typeName = selectedType.text.toLowerCase()
        return typeName.includes('plot') || typeName.includes('land') || typeName.includes('plots') || typeName.includes('lands')
    })
    
    // التحقق من نوع الـ lead (Rent only)
    const isRentOnly = computed(() => {
        return form.value.lead_type?.toLowerCase() === 'rent'
    })
    
    // دالة للتحقق من الميزانية
    const validateBudgetRange = () => {
        const from = parseFloat(form.value.budget_from)
        const to = parseFloat(form.value.budget_to)
        
        if (from && to && from > to) {
            return 'Budget From cannot be greater than Budget To'
        }
        return null
    }
    // دالة للحصول على اسم المرحلة
    const getStageName = () => {
        const stage = stages.value.find(s => s.id === form.value.stage_id)
        return stage?.name || ''
    }
    // دالة لتحديد خيارات Lead Status حسب المرحلة
    const getLeadStatusOptions = () => {
        const order = currentStageOrder.value
        
        if (order === 4) {
            return [
                { value: 'cold', text: 'Cold Lead' },
                { value: 'warm', text: 'Warm Lead' },
                { value: 'hot', text: 'Hot Lead' }
            ]
        } else if (order === 9) {
            return [
                { value: 'no_answer', text: 'No Answer' },
                { value: 'contacted', text: 'Contacted' },
                { value: 'wrong_person', text: 'Wrong Person' }
            ]
        } else if (order === 10) {
            return [
                // { value: 'not_interested', text: 'Not Interested' },
                { value: 'wrong_contact_details', text: 'Wrong Contact Details' },
                { value: 'no_answer_multiple_calls', text: 'No Answer — Multiple Calls' },
                { value: 'job_seeker', text: 'Job Seeker' },
                { value: 'broker', text: 'Broker' },
                { value: 'registered_by_mistake', text: 'Registered by Mistake' },
              { value: 'spam_leads', text: 'Spam Leads' },
                  { value: 'blacklist', text: 'Black List' },

            ]
        }
        return []
    }
    
    const fetchStages = async () => {
        try {
            const response = await api.get('/stages')
            
            // افترض أن الـ API يعيد { data: { data: [...] } }
            let stagesData = response.data?.data?.data || response.data?.data || response.data || []
            
            // إذا كانت stagesData كائن وليس مصفوفة
            if (!Array.isArray(stagesData)) {
                // إذا كان الكائن يحتوي على stages
                if (stagesData.stages && Array.isArray(stagesData.stages)) {
                    stagesData = stagesData.stages
                } else {
                    stagesData = []
                }
            }
            
            stages.value = stagesData.map(stage => ({
                id: stage.id,
                name: stage.name,
                order: stage.order
            }))
            
            console.log('Stages loaded:', stages.value)
        } catch (error) {
            console.error('Error fetching stages:', error)
            stages.value = []
        }
    }
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
    // جلب قائمة العملاء للمستخدم الحالي
const fetchClientsList = async () => {
    try {
        isLoadingClients.value = true
        // غير هذا الـ endpoint حسب API الخاص بك
        const response = await api.get('/my-clients') // أو '/clients?assigned_to_me=true'
        clientsList.value = response.data.data || response.data || []
        console.log('Clients loaded:', clientsList.value)
    } catch (error) {
        console.error('Error fetching clients:', error)
        clientsList.value = []
    } finally {
        isLoadingClients.value = false
    }
}

// عند اختيار عميل موجود
const handleClientSelected = (client) => {
    if (client) {
        // تعبئة بيانات العميل في النموذج
        form.value.lead_name = client.name || client.lead_name || `${client.first_name || ''} ${client.last_name || ''}`
        form.value.first_name = client.first_name || ''
        form.value.last_name = client.last_name || ''
        form.value.email = client.email || ''
        form.value.work_phone = client.work_phone || client.phone || ''
        form.value.salutation = client.salutation || null
        form.value.secondary_email = client.secondary_email || ''
        form.value.work_phone_2 = client.work_phone_2 || ''
        
        // أي حقول أخرى تريد تعبئتها
        form.value.area_id = client.area_id || null
        form.value.property_type_id = client.property_type_id || null
        
        selectedExistingClient.value = client
    }
}

// مسح بيانات العميل
const clearClientData = () => {
    form.value.lead_name = ''
    form.value.first_name = ''
    form.value.last_name = ''
    form.value.email = ''
    form.value.work_phone = ''
    form.value.salutation = null
    form.value.secondary_email = ''
    form.value.work_phone_2 = ''
    selectedExistingClient.value = null
}
    const form = ref({
        lead_name: '',
        stage_id: null,
        salutation: null,
        first_name: '',
        last_name: '',
        work_phone: '',
        email: '',
        secondary_email: '',
        work_phone_2: '',
        comment: '',
        lead_source: '',
        source_information: '',
        bedrooms: null,
        purpose_buying: null,
        responsible_person_id: loggedInUserId,
        budget_from: null,
        budget_to: null,
        currency: 'AED',
        area_id: null,
        property_type_id: null,
        status_lead: null,
        available_date: null,
        lead_type:null,
        property_status:null,
        branch: null,
        lost_reason: null,
          source_client_name: '',
        source_client_phone: '',
        source_client_email: '',
        source_relation: ''
    })
    const fetchCurrentUserRole = () => {
        try {
            const userData = localStorage.getItem('user')
            if (userData) {
                const user = JSON.parse(userData)
                const role = user.roles.includes('sales') 
                isSalesUser.value = role 
                console.log('Is sales user:', isSalesUser.value)
            }
        } catch (error) {
            console.error('Error fetching user role:', error)
            isSalesUser.value = false
        }
    }
    
    
   // هل تم اختيار referral
        const isReferralSelected = computed(() => {
            return isSalesUser.value && form.value.lead_source === 'referral'
        })
        
        // هل تم اختيار self_lead
        const isSelfLeadSelected = computed(() => {
            return isSalesUser.value && form.value.lead_source === 'self_lead'
        })
            
    // خيارات الـ Source حسب دور المستخدم
    const dynamicSourceOptions = computed(() => {
        if (isSalesUser.value) {
            return salesSourceOptions
        }
        return sourceOptions.value
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
    
    function closeBudgetDropdown() {
        showBudgetDropdown.value = false
    }
    
    function toggleBudgetDropdown() {
        showBudgetDropdown.value = !showBudgetDropdown.value
    }
    
    function onDocumentClick(event) {
        if (!showBudgetDropdown.value) return
        const target = event.target
        const triggerEl = budgetTriggerRef.value
        if (triggerEl?.contains?.(target) || budgetDropdownPanelRef.value?.contains?.(target)) return
        closeBudgetDropdown()
    }
    
    const currentStageOrder = computed(() => {
        const selectedStage = stages.value.find(s => s.id === form.value.stage_id)
        return selectedStage?.order || 0
    })
    
    const handleStageChangeRequest = ({ stageId, stageName, stageOrder }) => {
        console.log('🎯 handleStageChangeRequest called:', { stageId, stageName, stageOrder })
    
        form.value.stage_id = stageId
    
        console.log('✅ Stage selected:', stageName, 'ID:', stageId)
    }
    
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
        emit('update:modelValue', val)
        if (val) {
            form.value.stage_id = null
        } else {
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
            if (isSalesUser.value) {
                sourceOptions.value = salesSourceOptions
                return
            }
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
    watch(() => form.value.stage_id, (newStageId) => {
        console.log('Stage changed to:', newStageId)
        // إعادة تعيين الحقول الخاصة بالمرحلة السابقة
        if (!shouldShowField('bedrooms')) form.value.bedrooms = null
        if (!shouldShowField('budget')) {
            form.value.budget_from = null
            form.value.budget_to = null
            budgetFromDisplay.value = ''
            budgetToDisplay.value = ''
            closeBudgetDropdown()
        }
        if (!shouldShowField('purpose_buying')) form.value.purpose_buying = null
        if (!shouldShowField('area_id')) form.value.area_id = null
        if (!shouldShowField('property_type_id')) form.value.property_type_id = null
        if (!shouldShowField('lead_status')) form.value.lead_status = null
        if (!shouldShowField('available_date')) form.value.available_date = null
        if (!shouldShowField('lead_type')) form.value.lead_type = null
        if (!shouldShowField('property_status')) form.value.property_status = null
        if (!shouldShowField('branch')) form.value.branch = null
        if (!shouldShowField('lost_reason')) form.value.lost_reason = null
    })
    watch(() => form.value.lead_source, async (newVal) => {
        if (newVal === 'referral') {
            // Referral: امسح بيانات العميل المحيل القديمة
            form.value.source_client_name = ''
            form.value.source_client_phone = ''
            form.value.source_client_email = ''
            form.value.source_relation = ''
            clearClientData() // مسح بيانات العميل المختار
        } else if (newVal === 'self_lead') {
            // Self Lead: جلب قائمة العملاء
            if (clientsList.value.length === 0) {
                await fetchClientsList()
            }
            clearClientData() // مسح بيانات العميل القديمة
        } else {
            // للمستخدمين العاديين
            // clearClientData()
        }
    })
    onMounted(() => {
        syncMobileModal()
        window.addEventListener('resize', syncMobileModal, { passive: true })
        document.addEventListener('click', onDocumentClick)
        fetchUsers()
        fetchSources()
         fetchStages() 
        fetchAreas() 
        fetchPropertyTypes()
         fetchCurrentUserRole() 
    })
    
    onBeforeUnmount(() => {
        window.removeEventListener('resize', syncMobileModal)
        document.removeEventListener('click', onDocumentClick)
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
        { value: 'Live in', text: 'Live in' },
        { value: 'Short-term investment', text: 'Short-term investment' },
        { value: 'Long-term investment', text: 'Long-term investment' },
        // { value: 'Holiday home', text: 'Holiday home' },
        // { value: 'Rental', text: 'Rental' },
    ]
    
    const bedroomOptions = [
        // { value: null, text: 'Select Bedrooms' },
        { value:0, text: 'Studio' },
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
        if (key === 'budget') {
            form.value.budget_from = null
            form.value.budget_to = null
            budgetFromDisplay.value = ''
            budgetToDisplay.value = ''
            closeBudgetDropdown()
        }
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
    
    watch(() => [form.value.budget_from, form.value.budget_to], () => {
        ;['budget', 'budget_from', 'budget_to'].forEach((k) => {
            if (validationErrors.value[k]) delete validationErrors.value[k]
        })
        clearErrorMessageIfNeeded()
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
    // أضف هذه الـ watches بعد الـ watches الموجودة
    watch(() => form.value.status_lead, () => {
        if (validationErrors.value.status_lead) {
            delete validationErrors.value.status_lead
            clearErrorMessageIfNeeded()
        }
    })
    
    watch(() => form.value.available_date, () => {
        if (validationErrors.value.available_date) {
            delete validationErrors.value.available_date
            clearErrorMessageIfNeeded()
        }
    })
    
    watch(() => form.value.branch, () => {
        if (validationErrors.value.branch) {
            delete validationErrors.value.branch
            clearErrorMessageIfNeeded()
        }
    })
    
    watch(() => form.value.lost_reason, () => {
        if (validationErrors.value.lost_reason) {
            delete validationErrors.value.lost_reason
            clearErrorMessageIfNeeded()
        }
    })
    // مراقبة تغيير lead type
    watch(() => form.value.lead_type, (newVal) => {
        if (newVal?.toLowerCase() === 'rent') {
            // إذا كان Rent، امسح property_status و purpose_buying
            form.value.property_status = null
            form.value.purpose_buying = null
        }
    })
    
    // مراقبة تغيير property type
    watch(() => form.value.property_type_id, (newVal) => {
        if (!newVal) return
        const selectedType = propertyTypeOptions.value.find(opt => opt.value === newVal)
        if (selectedType) {
            const typeName = selectedType.text.toLowerCase()
            if (typeName.includes('plot') || typeName.includes('land') || typeName.includes('plots') || typeName.includes('lands')) {
                // إذا كان Plots/Land، امسح bedrooms
                form.value.bedrooms = null
            }
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
            budget_from: null,
            budget_to: null,
            currency: "AED",
            area_id: null,
            property_type_id: null,
              status_lead: null,
            available_date: null,
            property_status: null,
            lead_type: null,
            branch: null,
            lost_reason: null,
             source_client_name: '',
            source_client_phone: '',
            source_client_email: '',
            source_relation: ''
        }
          selectedExistingClient.value = null 
        validationErrors.value = {}
        errorMessage.value = ''
        showPhoneFieldErrors.value = false
        closeBudgetDropdown()
        syncBudgetDisplayFields()
    }
    
    const submitForm = async () => {
        try {
            isSubmitting.value = true
            errorMessage.value = ''
            validationErrors.value = {}
            showPhoneFieldErrors.value = true
             const budgetError = validateBudgetRange()
            if (budgetError) {
                validationErrors.value.budget = [budgetError]
                $showNotification(budgetError, 'warning')
                return
            }
            console.log('PHONE:', form.value.work_phone)
            if (form.value.work_phone && !isNonEmptyPhoneValid(form.value.work_phone)) {
                validationErrors.value.work_phone = ['Enter a valid phone number']
                $showNotification('Primary phone is not valid', 'warning')
                return
            }
            console.log('PHONE2:', form.value.work_phone_2)
            if (form.value.work_phone_2 && !isNonEmptyPhoneValid(form.value.work_phone_2)) {
                validationErrors.value.work_phone_2 = ['Enter a valid phone number']
                $showNotification('Secondary phone is not valid', 'warning')
                return
            }
            if (isSalesUser.value && form.value.lead_source === 'referral' && form.value.source_client_phone
                && !isNonEmptyPhoneValid(form.value.source_client_phone)) {
                validationErrors.value.source_client_phone = ['Enter a valid phone number']
                $showNotification('Source client phone is not valid', 'warning')
                return
            }
            
            const payload = {
                ...form.value,
                responsible_person_id: form.value.responsible_person_id,
                stage_id: form.value.stage_id
            }
             if (isSalesUser.value && form.value.lead_source === 'referral') {
                payload.source_client_name = form.value.source_client_name
                payload.source_client_phone = form.value.source_client_phone
                payload.source_client_email = form.value.source_client_email
                payload.source_relation = form.value.source_relation
            }
            if (isSalesUser.value && form.value.lead_source === 'self_lead' && selectedExistingClient.value) {
                // إرسال client_id بدلاً من البيانات
                payload.existing_client_id = selectedExistingClient.value.id
                // يمكنك أيضاً إرسال البيانات المأخوذة من العميل
                payload.first_name = form.value.first_name
                payload.last_name = form.value.last_name
                payload.email = form.value.email
                payload.work_phone = form.value.work_phone
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
        color: #0B0736;
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
        color: #0B0736;
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
        min-height: 42px;
        height: 42px;
        border-radius: 10px;
        border: 1px solid #E2E8F0;
        background: #fff;
        padding: 0 8px;
        display: flex !important;
        align-items: center !important;
        box-sizing: border-box;
    }
    
    :deep(.custom-v-select .vs__selected-options) {
        display: flex !important;
        flex-wrap: nowrap;
        overflow: hidden;
        max-width: calc(100% - 30px);
        min-width: 0;
        flex: 1 1 auto;
        align-items: center !important;
        align-self: center !important;
        height: auto !important;
    }

    :deep(.custom-v-select.vs--single .vs__selected-options) {
        align-items: center !important;
    }
    
    :deep(.custom-v-select .vs__selected) {
        font-size: 13px;
        color: #64748B;
        margin: 0;
        padding: 0;
        white-space: nowrap;
        text-overflow: ellipsis;
        overflow: hidden;
        max-width: 100%;
        min-width: 0;
        display: flex !important;
        align-items: center !important;
        align-self: center !important;
        height: auto !important;
        line-height: 1.35 !important;
        box-sizing: border-box;
        flex: 0 1 auto;
    }
    
    :deep(.custom-v-select .vs__search) {
        font-size: 13px;
        color: #64748B;
        margin: 0;
        padding: 0 4px;
        align-self: center !important;
        height: auto !important;
        min-height: 0 !important;
        line-height: 1.35 !important;
        box-sizing: border-box;
    }

    :deep(.custom-v-select .vs__placeholder) {
        align-self: center !important;
        display: flex !important;
        align-items: center !important;
        height: auto !important;
        margin: 0 !important;
        font-size: 13px;
    }
    
    :deep(.custom-v-select .vs__search::placeholder) {
        color: #64748B;
    }
    
    :deep(.custom-v-select .vs__actions) {
        padding: 0 8px;
        align-self: center !important;
        display: flex !important;
        align-items: center !important;
    }
    
    :deep(.custom-v-select .vs__open-indicator-icon) {
        font-size: 15px;
        color: #cfdbec;
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
        background: #733E87 !important;
        color: #fff !important;
    }
    
    :deep(.custom-v-select .vs__dropdown-option--selected) {
        background: #733E87;
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
        min-height: 42px !important;
        border: none !important;
        border-left: 1px solid #E2E8F0 !important;
        border-radius: 0 8px 8px 0 !important;
        padding: 0 !important;
        background: #fff !important;
        display: flex !important;
        align-items: stretch !important;
        cursor: pointer;
        box-sizing: border-box;
    }
    
    :deep(.custom-v-select-inline .vs__selected-options) {
        padding: 0 0 0 8px !important;
        margin: 0 !important;
        flex-basis: auto !important;
        flex-grow: 1;
        display: flex !important;
        align-items: stretch !important;
        align-self: stretch !important;
        height: 100% !important;
        overflow: hidden;
        max-width: calc(100% - 30px);
        min-width: 0;
    }
    
    :deep(.custom-v-select-inline .vs__selected) {
        color: #64748B !important;
        font-size: 13px !important;
        margin: 0 !important;
        padding: 0 !important;
        position: static !important;
        line-height: 1.25 !important;
        background: transparent !important;
        border: none !important;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: flex !important;
        align-items: center !important;
        align-self: stretch !important;
        height: 100% !important;
        min-width: 0;
        flex: 0 1 auto;
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
        background: #733E87 !important;
        color: #fff !important;
    }
    
    :deep(.custom-v-select-inline .vs__dropdown-option--selected) {
        background: #733E87;
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
    .client-requirements-grid {
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
    .step-content .row.client-requirements-grid {
        padding-top: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        padding-bottom: 0 !important;
    }
    .additional-fields-card .col-md-4{
        padding-top: 0 !important;
        padding-left: 0 !important;
        padding-right: 12px !important;
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
        color: #0B0736;
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
        color: #0B0736;
        transition: all 0.2s;
    }
    
    .additional-chip:hover {
        background: #F8FAFC;
        border-color: #CBD5E1;
    }
    
    .additional-chip.active {
        border-color: #0B0736;
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
        color: #0B0736;
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
        color: #0B0736;
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
        color: #0B0736;
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
        background: #0B0736;
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
        color: #0B0736;
        cursor: pointer;
    }
    
    .btn-clear:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .btn-next-step {
        background: #0B0736;
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
      color: #0B0736;
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
      color: #0B0736;
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
    
    @media (max-width: 768px) {
      .step-content .row,
      .create-lead-modal-content .step-content .row {
        padding: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
      }
      .kanban-mobile-fullscreen-modal {
        margin: 0 !important;
        max-width: 100% !important;
        width: 100% !important;
        height: 100dvh !important;
      }
      .kanban-mobile-fullscreen-modal .modal-content {
        height: 100dvh !important;
        border-radius: 0 !important;
      }
      .kanban-mobile-fullscreen-modal .modal-body {
        height: 100dvh !important;
        padding: 0 !important;
      }
      .create-lead-modal-content {
        height: 100dvh;
        max-height: 100dvh;
        border-radius: 0 !important;
        padding: calc(8px + env(safe-area-inset-top, 0px)) 0 0 !important;
        display: flex;
        flex-direction: column;
        background: #f8fbff;
        overflow: hidden;
      }
      .create-lead-modal-top {
        flex-shrink: 0;
        position: sticky;
        top: 0;
        z-index: 6;
        background: #f8fbff;
        padding: 0 10px 10px;
      }
      :deep(.header-modal-wrapper) {
        position: relative;
        top: auto;
        z-index: 1;
        margin: 0 !important;
        padding: 8px 12px 10px !important;
        background: #f8fbff;
        border-bottom: none !important;
      }
      :deep(.header-close-btn-top) {
        position: relative !important;
        top: auto !important;
        right: auto !important;
        width: 40px !important;
        height: 40px !important;
        border-radius: 999px !important;
        border: 1px solid #e5e7eb !important;
        background: #f8fafc !important;
        color: #0b0736 !important;
        box-shadow: none !important;
        z-index: 2 !important;
        margin-bottom: 0 !important;
      }
      :deep(.header-close-btn-top iconify-icon),
      :deep(.header-close-btn-top svg) {
        color: #0b0736 !important;
      }
      :deep(.header-modal-title) {
        font-size: 17px !important;
        font-weight: 700 !important;
      }
      .form-scroll-area {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        padding-bottom: calc(90px + env(safe-area-inset-bottom, 0px));
      }
      .modal-header-custom {
        background: #fff;
        border-radius: 14px;
        padding: 10px 14px !important;
        margin-bottom: 10px;
      }
      :deep(.stage-selector-wrapper) {
        background: #fff;
        border-radius: 14px;
        border: 1px solid #eef2f7;
        margin: 0 0 8px;
        padding: 10px 8px;
        overflow: visible;
      }
      :deep(.stage-selector-wrapper .stage-container) {
        overflow-y: visible;
        padding-top: 2px;
        padding-bottom: 4px;
      }
      :deep(.stage-selector-wrapper .stage-pill) {
        min-height: 34px;
        align-items: center;
      }
      :deep(.stage-selector-wrapper .stage-text) {
        font-size: 11px !important;
        line-height: 1.3;
      }
      .form-card,
      .contact-details-card,
      .additional-fields-card {
        border-radius: 14px !important;
        border: 1px solid #eef2f7 !important;
        box-shadow: 0 2px 8px rgba(2, 6, 23, 0.04) !important;
        padding: 12px !important;
        margin-bottom: 10px;
      }
      .section-title {
        font-size: 16px !important;
        font-weight: 700 !important;
      }
      .form-label-custom {
        font-size: 14px !important;
        font-weight: 500 !important;
        margin-bottom: 6px !important;
        color: #0f172a !important;
      }
      .step-content .row > [class*="col-"] {
        width: 100% !important;
        max-width: 100% !important;
        flex: 0 0 100% !important;
      }
      .custom-input,
      .custom-textarea,
      .custom-date-trigger,
      .input-group-custom,
      .budget-field-wrap {
        width: 100% !important;
      }
      :deep(.custom-v-select),
      :deep(.custom-v-select-inline),
      :deep(.custom-v-select .vs__dropdown-toggle),
      :deep(.custom-v-select-inline .vs__dropdown-toggle) {
        width: 100% !important;
        max-width: 100% !important;
      }
      .custom-input,
      .custom-textarea,
      .custom-date-trigger,
      :deep(.custom-v-select .vs__dropdown-toggle) {
        height: 46px !important;
        min-height: 46px !important;
        border-radius: 12px !important;
        font-size: 14px !important;
      }
      .custom-input::placeholder,
      .custom-textarea::placeholder,
      :deep(.custom-v-select .vs__search::placeholder) {
        font-size: 14px !important;
        color: #9aa6b2 !important;
      }
      :deep(.custom-v-select .vs__selected),
      :deep(.custom-v-select .vs__search) {
        font-size: 14px !important;
        line-height: 1.2 !important;
      }
      .modal-footer-custom {
        position: sticky;
        /* Keep Save/Cancel buttons above the fixed bottom Lead/Listing actions on mobile. */
        bottom: calc(72px + env(safe-area-inset-bottom, 0px));
        left: 0;
        right: 0;
        background: #fff;
        border-top: 1px solid #edf2f7;
        padding: 12px 14px calc(12px + env(safe-area-inset-bottom, 0px));
        z-index: 6;
      }
      .modal-footer-actions--mobile {
        justify-content: stretch !important;
      }
      .modal-footer-actions--mobile .btn-clear,
      .modal-footer-actions--mobile .btn-next-step {
        flex: 1 1 0;
        min-width: 0;
      }
      .btn-cancel-mobile {
        background: #fff !important;
        border: 1px solid #d1d9e6 !important;
        color: #0b0736 !important;
      }
      .btn-clear,
      .btn-next-step {
        height: 44px !important;
        min-width: 122px;
        font-size: 15px !important;
        font-weight: 600 !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
      }
      .contact-details-card .d-flex {
        flex-direction: column !important;
        align-items: stretch !important;
      }
      .contact-details-card .col {
        width: 100%;
        max-width: 100%;
        flex: 0 0 100%;
      }
    }
    
    /* Self Lead Client Card Styles */
.self-lead-client-card {
    background: #F0F9FF;
    border: 1px solid #BAE6FD;
    border-radius: 10px;
    padding: 16px;
    margin-top: 8px;
}

.self-lead-client-card .section-title {
    color: #0369A1;
}

/* Client Option Styles */
.client-option {
    padding: 8px 0;
}

.client-option-name {
    font-size: 14px;
    margin-bottom: 4px;
}

.client-option-details {
    font-size: 12px;
    color: #64748B;
}

.client-option-details span {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.selected-client-info {
    font-size: 14px;
}

/* Referral Client Card Styles */
.referral-client-card {
    background: #FFF8E7;
    border: 1px solid #FFE5B4;
    border-radius: 10px;
    padding: 16px;
    margin-top: 8px;
}

.referral-client-card .section-title {
    color: #B45309;
}

@media (max-width: 768px) {
    .self-lead-client-card .row,
    .referral-client-card .row {
        flex-direction: column;
    }
    .client-option-details {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
}
    </style>
    
    
