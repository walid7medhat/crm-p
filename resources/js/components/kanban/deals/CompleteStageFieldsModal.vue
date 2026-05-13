<template>
  <Teleport to="body">
    <div v-if="show" class="complete-fields-overlay" @click.self="onOverlayClick">
      <div
        class="complete-fields-modal complete-stage-modal deal-figma-ui"
        :class="{
          'complete-fields-modal--compact': isCompactStageModal,
          'complete-fields-modal--deal-won': isDealWonStage,
          'complete-fields-modal--lost-reason': isLostReasonOnly
        }"
      >
        <!-- Header -->
        <div class="modal-header-deal p-3">
          <div class="d-flex justify-content-between align-items-start gap-3 w-100">
            <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-2 gap-md-3 min-w-0 flex-grow-1">
              <div class="complete-fields-title-wrap min-w-0 flex-grow-1">
                <span class="modal-title complete-fields-main-title">
                  Complete All The Required Fields To Change Deal Stage
                </span>
              </div>
              <div class="deals-type-tabs-inline d-flex gap-2 flex-shrink-0">
                <span class="deals-type-tab-inline active">
                  {{ getDealTypeName(dealType) }}
                </span>
              </div>
            </div>
            <button class="close-btn" @click="closeModal" :disabled="submitting">
              <iconify-icon icon="lucide:x"></iconify-icon>
            </button>
          </div>
        </div>

        <!-- Stage progress -->
        <div class="deal-progress-wrapper py-2 px-3" v-if="targetStageName">
          <!-- <div class="deal-progress-label">Pipeline</div> -->
          <div class="deal-progress-bar">
            <div class="deal-stage-pill active" aria-current="step">
              <div class="stage-circle">
                <div class="stage-dot" style="background-color: #3b82f6;"></div>
              </div>
              <span class="stage-text">{{ targetStageName }}</span>
            </div>
          </div>
          <p class="deal-progress-hint mb-0 mt-2">
            <iconify-icon icon="lucide:info" class="me-1" aria-hidden="true"></iconify-icon>
            Complete the required fields below to move to this stage
          </p>
        </div>

        <!-- Scrollable Form Area -->
        <div class="form-scroll-area p-3">
          <!-- Loading State -->
          <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
          </div>

          <!-- Form Sections -->
          <div v-else class="complete-fields-form">
            
            <!-- Lost Reason Section -->
            <section v-if="shouldShowField('lost_reason')" class="form-section">
              <div class="form-card p-3 radius-12">
                <label class="form-label-custom">Enter Reason For Deal Lost <span v-if="isRequiredField('lost_reason')" class="text-danger">*</span></label>
                <textarea
                  v-model="formData.lost_reason"
                  class="lost-reason-textarea"
                  :class="{ 'is-invalid': isFieldInvalid('lost_reason') }"
                  placeholder="Text Here"
                  rows="4"
                ></textarea>
              </div>
            </section>

            <!-- Buyer Section with Collapsible -->
            <section v-if="!shouldHideBuyer && (showPartyDetailFields('buyer') || documentTypesByParty.buyer.length > 0)" class="form-section">
           
              <div 
                class="section-collapsible-header" 
                :class="getSectionHeaderClass('buyer')"
                @click="toggleSection('buyer')"
              >
                <h6 class="section-title mb-0">Buyer Details</h6>
                 <div class="d-flex">
                  <span v-if="hasRequiredInSection('buyer') && !isSectionComplete('buyer')" class="required-badge">Required</span>
                  <span v-if="isSectionComplete('buyer')" class="completed-badge">Completed</span>
                  <iconify-icon :icon="isSectionOpen('buyer') ? 'lucide:chevron-down' : 'lucide:chevron-right'" class="collapse-icon"></iconify-icon>
                  </div>
              </div>
              
              <div
                v-show="isSectionOpen('buyer')"
                class="section-content"
                @focusout="onCollapsibleSectionFocusOut('buyer', $event)"
              >
                <div class="form-card p-3 radius-12" v-if="showPartyDetailFields('buyer')">
                  <div class="row g-3">
                    <!-- Buyer fields -->
                    <div class="col-md-6" v-if="shouldShowField('buyer_first_name')">
                      <label class="form-label-custom">Buyer First Name <span v-if="isRequiredField('buyer_first_name')" class="text-danger">*</span></label>
                      <b-form-input 
                        v-model="formData.buyer_first_name" 
                        placeholder="Enter First Name" 
                        class="custom-input"
                        :class="{ 'is-invalid': isFieldInvalid('buyer_first_name') }"
                      />
                    </div>
                    
                    <div class="col-md-6" v-if="shouldShowField('buyer_last_name')">
                      <label class="form-label-custom">Buyer Last Name <span v-if="isRequiredField('buyer_last_name')" class="text-danger">*</span></label>
                      <b-form-input 
                        v-model="formData.buyer_last_name" 
                        placeholder="Enter Last Name" 
                        class="custom-input compact-placeholder-field"
                        :class="{ 'is-invalid': isFieldInvalid('buyer_last_name') }"
                      />
                    </div>
                    
                    <div class="col-md-6" v-if="shouldShowField('buyer_phone')">
                      <label class="form-label-custom">Buyer Phone Number <span v-if="isRequiredField('buyer_phone')" class="text-danger">*</span></label>
                      <CrmPhoneInput 
                        v-model="formData.buyer_phone" 
                        placeholder="Enter Phone Number" 
                        :invalid="validationAttempted && hasField('buyer_phone') && isFieldInvalid('buyer_phone')"
                        :show-errors="validationAttempted && hasField('buyer_phone')"
                      />
                    </div>
                    
                    <div class="col-md-6" v-if="shouldShowField('buyer_email')">
                      <label class="form-label-custom">Buyer Email <span v-if="isRequiredField('buyer_email')" class="text-danger">*</span></label>
                      <b-form-input 
                        v-model="formData.buyer_email" 
                        type="email" 
                        placeholder="Enter Your Email" 
                        class="custom-input"
                        :class="{ 'is-invalid': isFieldInvalid('buyer_email') }"
                      />
                    </div>
                    
                    <div class="col-md-6" v-if="shouldShowField('buyer_nationality')">
                      <label class="form-label-custom">Buyer Nationality <span v-if="isRequiredField('buyer_nationality')" class="text-danger">*</span></label>
                      <v-select
                        append-to-body 
                        v-model="formData.buyer_nationality" 
                          :reduce="item => item.text"
                          :options="nationalityOptions"
                        label="text" 
                        placeholder="Select Nationality" 
                        class="custom-v-select"
                        :class="{ 'is-invalid': isFieldInvalid('buyer_nationality') }"
                      >
                        <template #open-indicator="{ attributes }">
                          <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                          </span>
                        </template>
                        <template #option="{ text, code }">
                          <div class="d-flex align-items-center gap-2">
                            <img 
                              :src="`https://flagcdn.com/w20/${code}.png`" 
                              width="20"
                            />
                            <span>{{ text }}</span>
                          </div>
                        </template>
                        <template #selected-option="{ text, code }">
                          <div class="d-flex align-items-center gap-2">
                            <img 
                              :src="`https://flagcdn.com/w20/${code}.png`" 
                              width="20"
                            />
                            <span>{{ text }}</span>
                          </div>
                        </template>
                      </v-select>
                    </div>
                    
                    <div class="col-md-6" v-if="shouldShowField('buyer_residency_status') || (documentTypesByParty.buyer.length > 0 && ['primary', 'secondary'].includes(effectiveDealTypeForDocs))">
                      <label class="form-label-custom">Buyer Residency Status <span v-if="isRequiredField('buyer_residency_status')" class="text-danger">*</span></label>
                      <v-select
                        append-to-body
                        v-model="formData.buyer_residency_status"
                        :options="buyerResidencyOptions"
                        :reduce="item => item.value" 
                        label="text" 
                        placeholder="Resident or Non Resident" 
                        :clearable="false"
                        class="custom-v-select"
                        :class="{ 'is-invalid': isFieldInvalid('buyer_residency_status') }"
                      >
                        <template #open-indicator="{ attributes }">
                          <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                          </span>
                        </template>
                      </v-select>
                    </div>
                    
                    <div class="col-md-6" v-if="shouldShowField('buyer_country') && (showBuyerCountryField || hasField('buyer_country'))">
                      <label class="form-label-custom">Buyer Country Of Residence <span v-if="isRequiredField('buyer_country')" class="text-danger">*</span></label>
                      <v-select
                        append-to-body 
                        v-model="formData.buyer_country" 
                        :options="countryOptions" 
                        :reduce="item => item.value" 
                        label="text" 
                        placeholder="Select Country" 
                        class="custom-v-select"
                        :class="{ 'is-invalid': isFieldInvalid('buyer_country') }"
                      >
                        <template #open-indicator="{ attributes }">
                          <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                          </span>
                        </template>
                      </v-select>
                    </div>
                    
                    <div class="col-md-6" v-if="shouldShowField('buyer_city') && showBuyerCityField">
                      <label class="form-label-custom">Buyer City Of Residence <span v-if="isRequiredField('buyer_city')" class="text-danger">*</span></label>
                      <v-select
                        append-to-body 
                        v-model="formData.buyer_city" 
                        :options="buyerCityOptions" 
                        :reduce="item => item.value" 
                        label="text" 
                        placeholder="Select City" 
                        class="custom-v-select"
                        :class="{ 'is-invalid': isFieldInvalid('buyer_city') }"
                      >
                        <template #open-indicator="{ attributes }">
                          <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                          </span>
                        </template>
                      </v-select>
                    </div>

                    <div class="col-md-6 buyer-dob-field" v-if="shouldShowField('buyer_dob')">
                      <label class="form-label-custom">Buyer Date Of Birth <span v-if="isRequiredField('buyer_dob')" class="text-danger">*</span></label>
                      <AdvancedDatePicker
                        v-model="formData.buyer_dob"
                        date-only
                        dob-layout
                        placeholder="Select date of birth"
                        :invalid="isFieldInvalid('buyer_dob')"
                      />
                    </div>

                    <div class="col-md-6" v-if="shouldShowField('buyer_language')">
                      <label class="form-label-custom">Buyer Language <span v-if="isRequiredField('buyer_language')" class="text-danger">*</span></label>
                      <v-select
                        append-to-body 
                        :model-value="normalizeLanguageSelection(formData.buyer_language)"
                        @update:modelValue="(v) => { formData.buyer_language = normalizeLanguageSelection(v) }"
                        :options="languageOptions" 
                        :reduce="item => item.value" 
                        label="text" 
                        placeholder="Select Language(s)" 
                        class="custom-v-select buyer-language-select"
                        :multiple="true"
                        :searchable="true"
                        :close-on-select="false"
                        :class="{ 'is-invalid': isFieldInvalid('buyer_language') }"
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
                
                <!-- Buyer Documents -->
                <div v-if="documentTypesByParty.buyer.length > 0" class="mt-3">
                  <label class="section-title mb-3">Buyer Documents <span v-if="documentTypesByParty.buyer.some((d) => d.required)" class="text-danger">*</span></label>
                  <DocumentUpload
                    v-model="formData.buyer_documents"
                    category="buyer"
                    compact
                    :document-types="documentTypesByParty.buyer"
                    :identification-requirement-mode="identificationRequirementModeForParty('buyer')"
                    :show-errors="validationAttempted"
                    :missing-document-types="missingDocumentTypesByParty.buyer"
                    ref="buyerDocUploadRef"
                    class="form-card p-3 radius-12"
                  />
                </div>
              </div>
            </section>

            <!-- Seller Section -->
            <section v-if="!shouldHideSeller && (showPartyDetailFields('seller') || documentTypesByParty.seller.length > 0)" class="form-section">
              <div 
                class="section-collapsible-header"
                :class="getSectionHeaderClass('seller')"
                @click="toggleSection('seller')"
              >
                <h6 class="section-title mb-0">Seller Details</h6>
                 <div class="d-flex">
                  <span v-if="hasRequiredInSection('seller') && !isSectionComplete('seller')" class="required-badge">Required</span>
                  <span v-if="isSectionComplete('seller')" class="completed-badge">Completed</span>
                  <iconify-icon :icon="isSectionOpen('seller') ? 'lucide:chevron-down' : 'lucide:chevron-right'" class="collapse-icon"></iconify-icon>
                  </div>
              </div>
              
              <div
                v-show="isSectionOpen('seller')"
                class="section-content"
                @focusout="onCollapsibleSectionFocusOut('seller', $event)"
              >
                <div class="form-card p-3 radius-12" v-if="showPartyDetailFields('seller')">
                  <div class="row g-3">
                    <!-- Seller fields -->
                    <div class="col-md-4" v-if="shouldShowField('seller_first_name')">
                      <label class="form-label-custom">First Name <span v-if="isRequiredField('seller_first_name')" class="text-danger">*</span></label>
                      <b-form-input 
                        v-model="formData.seller_first_name" 
                        placeholder="Enter First Name" 
                        class="custom-input"
                        :class="{ 'is-invalid': isFieldInvalid('seller_first_name') }"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('seller_last_name')">
                      <label class="form-label-custom">Last Name <span v-if="isRequiredField('seller_last_name')" class="text-danger">*</span></label>
                      <b-form-input 
                        v-model="formData.seller_last_name" 
                        placeholder="Enter Last Name" 
                        class="custom-input"
                        :class="{ 'is-invalid': isFieldInvalid('seller_last_name') }"
                      />
                    </div>
                    
                    <div class="col-md-4 buyer-dob-field" v-if="shouldShowField('seller_dob')">
                      <label class="form-label-custom">Date Of Birth <span v-if="isRequiredField('seller_dob')" class="text-danger">*</span></label>
                      <AdvancedDatePicker
                        v-model="formData.seller_dob"
                        date-only
                        dob-layout
                        placeholder="Select date of birth"
                        :invalid="isFieldInvalid('seller_dob')"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('seller_phone')">
                      <label class="form-label-custom">Phone <span v-if="isRequiredField('seller_phone')" class="text-danger">*</span></label>
                      <CrmPhoneInput 
                        v-model="formData.seller_phone" 
                        placeholder="Enter Phone" 
                        :invalid="validationAttempted && hasField('seller_phone') && isFieldInvalid('seller_phone')"
                        :show-errors="validationAttempted && hasField('seller_phone')"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('seller_email')">
                      <label class="form-label-custom">Email <span v-if="isRequiredField('seller_email')" class="text-danger">*</span></label>
                      <b-form-input 
                        v-model="formData.seller_email" 
                        type="email" 
                        placeholder="Enter Email" 
                        class="custom-input"
                        :class="{ 'is-invalid': isFieldInvalid('seller_email') }"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('seller_nationality')">
                      <label class="form-label-custom">Nationality <span v-if="isRequiredField('seller_nationality')" class="text-danger">*</span></label>
                      <v-select
                        append-to-body 
                        v-model="formData.seller_nationality" 
                          :reduce="item => item.text"
                          :options="nationalityOptions"
                        label="text" 
                        placeholder="Select Nationality" 
                        class="custom-v-select"
                        :class="{ 'is-invalid': isFieldInvalid('seller_nationality') }"
                      >
                        <template #open-indicator="{ attributes }">
                          <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                          </span>
                        </template>
                        <template #option="{ text, code }">
                          <div class="d-flex align-items-center gap-2">
                            <img 
                              :src="`https://flagcdn.com/w20/${code}.png`" 
                              width="20"
                            />
                            <span>{{ text }}</span>
                          </div>
                        </template>
                        <template #selected-option="{ text, code }">
                          <div class="d-flex align-items-center gap-2">
                            <img 
                              :src="`https://flagcdn.com/w20/${code}.png`" 
                              width="20"
                            />
                            <span>{{ text }}</span>
                          </div>
                        </template>
                      </v-select>
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('seller_residency_status')">
                      <label class="form-label-custom">Residency Status <span v-if="isRequiredField('seller_residency_status')" class="text-danger">*</span></label>
                      <v-select
                        append-to-body 
                        v-model="formData.seller_residency_status" 
                        :options="residencyOptions" 
                        :reduce="item => item.value" 
                        label="text" 
                        placeholder="Select Status" 
                        class="custom-v-select"
                        :class="{ 'is-invalid': isFieldInvalid('seller_residency_status') }"
                      >
                        <template #open-indicator="{ attributes }">
                          <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                          </span>
                        </template>
                      </v-select>
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('seller_country') && showSellerCountryField">
                      <label class="form-label-custom">Country Of Residence</label>
                      <v-select
                        append-to-body 
                        v-model="formData.seller_country" 
                        :options="countryOptions" 
                        :reduce="item => item.value" 
                        label="text" 
                        placeholder="Select Country" 
                        class="custom-v-select"
                        :class="{ 'is-invalid': isFieldInvalid('seller_country') }"
                      >
                        <template #open-indicator="{ attributes }">
                          <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                          </span>
                        </template>
                      </v-select>
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('seller_city') && showSellerCityField">
                      <label class="form-label-custom">City Of Residence <span v-if="isRequiredField('seller_city')" class="text-danger">*</span></label>
                      <v-select
                        append-to-body 
                        v-model="formData.seller_city" 
                        :options="sellerCityOptions" 
                        :reduce="item => item.value" 
                        label="text" 
                        placeholder="Select City" 
                        class="custom-v-select"
                        :class="{ 'is-invalid': isFieldInvalid('seller_city') }"
                      >
                        <template #open-indicator="{ attributes }">
                          <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                          </span>
                        </template>
                      </v-select>
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('seller_language')">
                      <label class="form-label-custom">Language <span v-if="isRequiredField('seller_language')" class="text-danger">*</span></label>
                      <v-select
                        append-to-body 
                        v-model="formData.seller_language" 
                        :options="languageOptions" 
                        :reduce="item => item.value" 
                        label="text" 
                        placeholder="Select Language" 
                        class="custom-v-select"
                        :class="{ 'is-invalid': isFieldInvalid('seller_language') }"
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
                
                <!-- Seller Documents -->
                <div class="mt-3" v-if="documentTypesByParty.seller.length > 0">
                  <label class="section-title mb-3">Seller Documents <span v-if="documentTypesByParty.seller.some((d) => d.required)" class="text-danger">*</span></label>
                  <DocumentUpload
                    v-model="formData.seller_documents"
                    category="seller"
                    compact
                    :document-types="documentTypesByParty.seller"
                    :identification-requirement-mode="identificationRequirementModeForParty('seller')"
                    :show-errors="validationAttempted"
                    :missing-document-types="missingDocumentTypesByParty.seller"
                    ref="sellerDocUploadRef"
                    class="form-card p-3 radius-12"
                  />
                </div>
              </div>
            </section>

            <!-- Tenant Section -->
            <section v-if="!shouldHideTenant && (showPartyDetailFields('tenant') || documentTypesByParty.tenant.length > 0)" class="form-section">
              <div 
                class="section-collapsible-header"
                :class="getSectionHeaderClass('tenant')"
                @click="toggleSection('tenant')"
              >
                <h6 class="section-title mb-0">Tenant Details</h6>
                 <div class="d-flex">
                  <span v-if="hasRequiredInSection('tenant') && !isSectionComplete('tenant')" class="required-badge">Required</span>
                  <span v-if="isSectionComplete('tenant')" class="completed-badge">Completed</span>
                  <iconify-icon :icon="isSectionOpen('tenant') ? 'lucide:chevron-down' : 'lucide:chevron-right'" class="collapse-icon"></iconify-icon>
                </div>
              </div>
              
              <div
                v-show="isSectionOpen('tenant')"
                class="section-content"
                @focusout="onCollapsibleSectionFocusOut('tenant', $event)"
              >
                <div class="form-card p-3 radius-12" v-if="showPartyDetailFields('tenant')">
                  <div class="row g-3">
                    <div class="col-md-4" v-if="shouldShowField('tenant_first_name')">
                      <label class="form-label-custom">First Name <span v-if="isRequiredField('tenant_first_name')" class="text-danger">*</span></label>
                      <b-form-input 
                        v-model="formData.tenant_first_name" 
                        placeholder="Enter First Name" 
                        class="custom-input"
                        :class="{ 'is-invalid': isFieldInvalid('tenant_first_name') }"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('tenant_last_name')">
                      <label class="form-label-custom">Last Name <span v-if="isRequiredField('tenant_last_name')" class="text-danger">*</span></label>
                      <b-form-input 
                        v-model="formData.tenant_last_name" 
                        placeholder="Enter Last Name" 
                        class="custom-input"
                        :class="{ 'is-invalid': isFieldInvalid('tenant_last_name') }"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('tenant_phone')">
                      <label class="form-label-custom">Phone <span v-if="isRequiredField('tenant_phone')" class="text-danger">*</span></label>
                      <CrmPhoneInput 
                        v-model="formData.tenant_phone" 
                        placeholder="Enter Phone" 
                        :invalid="validationAttempted && hasField('tenant_phone') && isFieldInvalid('tenant_phone')"
                        :show-errors="validationAttempted && hasField('tenant_phone')"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('tenant_email')">
                      <label class="form-label-custom">Email <span v-if="isRequiredField('tenant_email')" class="text-danger">*</span></label>
                      <b-form-input 
                        v-model="formData.tenant_email" 
                        type="email" 
                        placeholder="Enter Email" 
                        class="custom-input"
                        :class="{ 'is-invalid': isFieldInvalid('tenant_email') }"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('tenant_nationality')">
                      <label class="form-label-custom">Nationality <span v-if="isRequiredField('tenant_nationality')" class="text-danger">*</span></label>
                      <v-select
                        append-to-body 
                        v-model="formData.tenant_nationality" 
                          :reduce="item => item.text"
                        :options="nationalityOptions"
                        label="text" 
                        placeholder="Select Nationality" 
                        class="custom-v-select"
                        :class="{ 'is-invalid': isFieldInvalid('tenant_nationality') }"
                      >
                        <template #open-indicator="{ attributes }">
                          <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                          </span>
                        </template>
                        <template #option="{ text, code }">
                          <div class="d-flex align-items-center gap-2">
                            <img 
                              :src="`https://flagcdn.com/w20/${code}.png`" 
                              width="20"
                            />
                            <span>{{ text }}</span>
                          </div>
                        </template>
                        <template #selected-option="{ text, code }">
                          <div class="d-flex align-items-center gap-2">
                            <img 
                              :src="`https://flagcdn.com/w20/${code}.png`" 
                              width="20"
                            />
                            <span>{{ text }}</span>
                          </div>
                        </template>
                      </v-select>
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('tenant_residency_status')">
                      <label class="form-label-custom">Residency Status <span v-if="isRequiredField('tenant_residency_status')" class="text-danger">*</span></label>
                      <v-select
                        append-to-body 
                        v-model="formData.tenant_residency_status" 
                        :options="residencyOptions" 
                        :reduce="item => item.value" 
                        label="text" 
                        placeholder="Select Status" 
                        class="custom-v-select"
                        :class="{ 'is-invalid': isFieldInvalid('tenant_residency_status') }"
                      >
                        <template #open-indicator="{ attributes }">
                          <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                          </span>
                        </template>
                      </v-select>
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('tenant_country') && showTenantCountryField">
                      <label class="form-label-custom">Country Of Residence</label>
                      <v-select
                        append-to-body 
                        v-model="formData.tenant_country" 
                        :options="countryOptions" 
                        :reduce="item => item.value" 
                        label="text" 
                        placeholder="Select Country" 
                        class="custom-v-select"
                        :class="{ 'is-invalid': isFieldInvalid('tenant_country') }"
                      >
                        <template #open-indicator="{ attributes }">
                          <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                          </span>
                        </template>
                      </v-select>
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('tenant_city') && showTenantCityField">
                      <label class="form-label-custom">City Of Residence <span v-if="isRequiredField('tenant_city')" class="text-danger">*</span></label>
                      <v-select
                        append-to-body 
                        v-model="formData.tenant_city" 
                        :options="tenantCityOptions" 
                        :reduce="item => item.value" 
                        label="text" 
                        placeholder="Select City" 
                        class="custom-v-select"
                        :class="{ 'is-invalid': isFieldInvalid('tenant_city') }"
                      >
                        <template #open-indicator="{ attributes }">
                          <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                          </span>
                        </template>
                      </v-select>
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('tenant_language')">
                      <label class="form-label-custom">Language <span v-if="isRequiredField('tenant_language')" class="text-danger">*</span></label>
                      <v-select
                        append-to-body 
                        v-model="formData.tenant_language" 
                        :options="languageOptions" 
                        :reduce="item => item.value" 
                        label="text" 
                        placeholder="Select Language" 
                        class="custom-v-select"
                        :class="{ 'is-invalid': isFieldInvalid('tenant_language') }"
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
                
                <!-- Tenant Documents -->
                <div class="mt-3" v-if="documentTypesByParty.tenant.length > 0">
                  <label class="section-title mb-3">Tenant Documents <span v-if="documentTypesByParty.tenant.some((d) => d.required)" class="text-danger">*</span></label>
                  <DocumentUpload
                    v-model="formData.tenant_documents"
                    category="tenant"
                    compact
                    :document-types="documentTypesByParty.tenant"
                    :identification-requirement-mode="identificationRequirementModeForParty('tenant')"
                    :show-errors="validationAttempted"
                    :missing-document-types="missingDocumentTypesByParty.tenant"
                    ref="tenantDocUploadRef"
                    class="form-card p-3 radius-12"
                  />
                </div>
              </div>
            </section>

            <!-- Landlord Section -->
            <section v-if="!shouldHideLandlord && (showPartyDetailFields('landlord') || documentTypesByParty.landlord.length > 0)" class="form-section">
              <div 
                class="section-collapsible-header"
                :class="getSectionHeaderClass('landlord')"
                @click="toggleSection('landlord')"
              >
                <h6 class="section-title mb-0">Landlord Details</h6>
                 <div class="d-flex">
                    <span v-if="hasRequiredInSection('landlord') && !isSectionComplete('landlord')" class="required-badge">Required</span>
                    <span v-if="isSectionComplete('landlord')" class="completed-badge">Completed</span>
                    <iconify-icon :icon="isSectionOpen('landlord') ? 'lucide:chevron-down' : 'lucide:chevron-right'" class="collapse-icon"></iconify-icon>
                  </div>
              </div>
              
              <div
                v-show="isSectionOpen('landlord')"
                class="section-content"
                @focusout="onCollapsibleSectionFocusOut('landlord', $event)"
              >
                <div class="form-card p-3 radius-12" v-if="showPartyDetailFields('landlord')">
                  <div class="row g-3">
                    <div class="col-md-4" v-if="shouldShowField('landlord_first_name')">
                      <label class="form-label-custom">First Name <span v-if="isRequiredField('landlord_first_name')" class="text-danger">*</span></label>
                      <b-form-input 
                        v-model="formData.landlord_first_name" 
                        placeholder="Enter First Name" 
                        class="custom-input"
                        :class="{ 'is-invalid': isFieldInvalid('landlord_first_name') }"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('landlord_last_name')">
                      <label class="form-label-custom">Last Name <span v-if="isRequiredField('landlord_last_name')" class="text-danger">*</span></label>
                      <b-form-input 
                        v-model="formData.landlord_last_name" 
                        placeholder="Enter Last Name" 
                        class="custom-input"
                        :class="{ 'is-invalid': isFieldInvalid('landlord_last_name') }"
                      />
                    </div>
                    
                    <div class="col-md-4 buyer-dob-field" v-if="shouldShowField('landlord_dob')">
                      <label class="form-label-custom">Date Of Birth <span v-if="isRequiredField('landlord_dob')" class="text-danger">*</span></label>
                      <AdvancedDatePicker
                        v-model="formData.landlord_dob"
                        date-only
                        dob-layout
                        placeholder="Select date of birth"
                        :invalid="isFieldInvalid('landlord_dob')"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('landlord_phone')">
                      <label class="form-label-custom">Phone <span v-if="isRequiredField('landlord_phone')" class="text-danger">*</span></label>
                    
                      <CrmPhoneInput 
                        v-model="formData.landlord_phone" 
                        placeholder="Enter Phone Number" 
                        :invalid="validationAttempted && hasField('landlord_phone') && isFieldInvalid('landlord_phone')"
                        :show-errors="validationAttempted && hasField('landlord_phone')"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('landlord_email')">
                      <label class="form-label-custom">Email <span v-if="isRequiredField('landlord_email')" class="text-danger">*</span></label>
                      <b-form-input 
                        v-model="formData.landlord_email" 
                        type="email" 
                        placeholder="Enter Email" 
                        class="custom-input"
                        :class="{ 'is-invalid': isFieldInvalid('landlord_email') }"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('landlord_nationality')">
                      <label class="form-label-custom">Nationality <span v-if="isRequiredField('landlord_nationality')" class="text-danger">*</span></label>
                      <v-select
                        append-to-body 
                        v-model="formData.landlord_nationality" 
                          :reduce="item => item.text"
                        :options="nationalityOptions"
                        label="text" 
                        placeholder="Select Nationality" 
                        class="custom-v-select"
                        :class="{ 'is-invalid': isFieldInvalid('landlord_nationality') }"
                      >
                        <template #open-indicator="{ attributes }">
                          <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                          </span>
                        </template>
                        <template #option="{ text, code }">
                          <div class="d-flex align-items-center gap-2">
                            <img 
                              :src="`https://flagcdn.com/w20/${code}.png`" 
                              width="20"
                            />
                            <span>{{ text }}</span>
                          </div>
                        </template>
                        <template #selected-option="{ text, code }">
                        <div class="d-flex align-items-center gap-2">
                          <img 
                            :src="`https://flagcdn.com/w20/${code}.png`" 
                            width="20"
                          />
                          <span>{{ text }}</span>
                        </div>
                      </template>
                      </v-select>
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('landlord_residency_status')">
                      <label class="form-label-custom">Residency Status <span v-if="isRequiredField('landlord_residency_status')" class="text-danger">*</span></label>
                      <v-select
                        append-to-body 
                        v-model="formData.landlord_residency_status" 
                        :options="residencyOptions" 
                        :reduce="item => item.value" 
                        label="text" 
                        placeholder="Select Status" 
                        class="custom-v-select"
                        :class="{ 'is-invalid': isFieldInvalid('landlord_residency_status') }"
                      >
                        <template #open-indicator="{ attributes }">
                          <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                          </span>
                        </template>
                      </v-select>
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('landlord_country') && showLandlordCountryField">
                      <label class="form-label-custom">Country Of Residence</label>
                      <v-select
                        append-to-body 
                        v-model="formData.landlord_country" 
                        :options="countryOptions" 
                        :reduce="item => item.value" 
                        label="text" 
                        placeholder="Select Country" 
                        class="custom-v-select"
                        :class="{ 'is-invalid': isFieldInvalid('landlord_country') }"
                      >
                        <template #open-indicator="{ attributes }">
                          <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                          </span>
                        </template>
                      </v-select>
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('landlord_city') && showLandlordCityField">
                      <label class="form-label-custom">City Of Residence <span v-if="isRequiredField('landlord_city')" class="text-danger">*</span></label>
                      <v-select
                        append-to-body 
                        v-model="formData.landlord_city" 
                        :options="landlordCityOptions" 
                        :reduce="item => item.value" 
                        label="text" 
                        placeholder="Select City" 
                        class="custom-v-select"
                        :class="{ 'is-invalid': isFieldInvalid('landlord_city') }"
                      >
                        <template #open-indicator="{ attributes }">
                          <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                          </span>
                        </template>
                      </v-select>
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('landlord_language')">
                      <label class="form-label-custom">Language <span v-if="isRequiredField('landlord_language')" class="text-danger">*</span></label>
                      <v-select
                        append-to-body 
                        v-model="formData.landlord_language" 
                        :options="languageOptions" 
                        :reduce="item => item.value" 
                        label="text" 
                        placeholder="Select Language" 
                        class="custom-v-select"
                        :class="{ 'is-invalid': isFieldInvalid('landlord_language') }"
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
                
                <!-- Landlord Documents -->
                <div class="mt-3" v-if="documentTypesByParty.landlord.length > 0">
                  <label class="section-title mb-3">Landlord Documents <span v-if="documentTypesByParty.landlord.some((d) => d.required)" class="text-danger">*</span></label>
                  <DocumentUpload
                    v-model="formData.landlord_documents"
                    category="landlord"
                    compact
                    :document-types="documentTypesByParty.landlord"
                    :identification-requirement-mode="identificationRequirementModeForParty('landlord')"
                    :show-errors="validationAttempted"
                    :missing-document-types="missingDocumentTypesByParty.landlord"
                    ref="landlordDocUploadRef"
                    class="form-card p-3 radius-12"
                  />
                </div>
              </div>
            </section>

            <!-- Multi Properties Section -->
            <section v-if="dealProperties.length > 0" class="form-section">
                <div 
                    class="section-collapsible-header properties-section"
                    :class="getSectionHeaderClass('properties')"
                    @click="toggleSection('properties')"
                  >
                    <div class="d-flex">
                      <h6 class="section-title mb-0">Properties Details</h6>
                    </div>
                    <div class="d-flex add-new"> 
                      <span v-if="hasRequiredInSection('properties') && !isSectionComplete('properties')" class="required-badge">Required</span>
                      <span v-if="isSectionComplete('properties')" class="completed-badge">Completed</span>
                      <button 
                        type="button"
                        class="add-property-btn ms-auto"
                        @click.stop="addNewProperty"
                        :disabled="loading || submitting"
                        title="Add another property"
                      >
                        <iconify-icon icon="lucide:plus" class="me-1"></iconify-icon>
                        Add Property
                      </button>
                      <iconify-icon :icon="isSectionOpen('properties') ? 'lucide:chevron-down' : 'lucide:chevron-right'" class="collapse-icon"></iconify-icon>
                    </div>
                  </div>
                
                <div
                  v-show="isSectionOpen('properties')"
                  class="section-content"
                  @focusout="onCollapsibleSectionFocusOut('properties', $event)"
                >
                    <!-- Loading state for property data -->
                    <div v-if="isLoadingPropertyData" class="text-center py-3">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <span class="ms-2 text-muted">Loading property data...</span>
                    </div>
                    
                    <template v-else>
                        <div 
                            v-for="(property, propIndex) in dealProperties" 
                            :key="property.id || propIndex"
                            class="property-card-in-modal mb-4"
                            :class="{ 'property-missing': validationAttempted && hasPropertyMissing(propIndex) }"
                        >
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge bg-secondary">Property {{ propIndex + 1 }}</span>
                                <span v-if="validationAttempted && hasPropertyMissing(propIndex)" class="badge bg-danger text-white">
                                    Missing Required Fields
                                </span>
                                 <button 
                                    v-if="dealProperties.length > 1"
                                    type="button"
                                    class="btn-remove-property"
                                    @click.stop="removeProperty(propIndex)"
                                    :disabled="loading || submitting"
                                    title="Remove property"
                                >
                                    <iconify-icon icon="lucide:trash-2"></iconify-icon>
                                </button>
                            </div>
                            
                            <div class="row g-3">
                                 <!-- Area / Address -->
                                <div class="col-md-6" v-if="shouldShowPropertyField('area_id', property)">
                                    <label class="form-label-custom">Property Address <span v-if="isPropertyFieldRequired('area_id', propIndex)" class="text-danger">*</span></label>
                                    <v-select
                                        :model-value="property.area_id"
                                        @update:modelValue="(val) => onPropertyAreaSelected(val, propIndex)"
                                        :options="areas"
                                        :reduce="item => item.id"
                                        label="name"
                                        placeholder="Select Address..."
                                        class="custom-v-select"
                                        :class="{ 'is-invalid': isPropertyFieldInvalid(property, 'area_id') }"
                                    >
                                        <template #open-indicator="{ attributes }">
                                            <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" /></span>
                                        </template>
                                        <template #option="option">
                                            <div class="location-option">
                                                <iconify-icon icon="lucide:map-pin" class="location-icon" />
                                                <div>
                                                    <div class="fw-semibold">{{ option.name }}</div>
                                                    <div class="small text-muted">{{ option.area_parents_title }}</div>
                                                </div>
                                            </div>
                                        </template>
                                    </v-select>
                                </div>
                               
                                
                                <!-- Property Type -->
                                <div class="col-md-6" v-if="shouldShowPropertyField('property_type_id', property)">
                                    <label class="form-label-custom">Property Type <span v-if="isPropertyFieldRequired('property_type_id', propIndex)" class="text-danger">*</span></label>
                                    <v-select
                                        :model-value="property.property_type_id"
                                        @update:modelValue="(val) => updateProperty(propIndex, 'property_type_id', val)"
                                        :options="propertyTypes"
                                        :reduce="item => item.id"
                                        label="name"
                                        placeholder="Select Type"
                                        class="custom-v-select"
                                        :class="{ 'is-invalid': isPropertyFieldInvalid(property, 'property_type_id') }"
                                    >
                                        <template #open-indicator="{ attributes }">
                                            <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" /></span>
                                        </template>
                                    </v-select>
                                </div>
                                
                                <!-- Bedrooms -->
                                <div class="col-md-6" v-if="shouldShowPropertyField('bedrooms', property)">
                                    <label class="form-label-custom">Bedrooms <span v-if="isPropertyFieldRequired('bedrooms', propIndex)" class="text-danger">*</span></label>
                                    <v-select
                                        :model-value="property.bedrooms"
                                        @update:modelValue="(val) => updateProperty(propIndex, 'bedrooms', val)"
                                        :options="bedroomOptions"
                                        :reduce="o => o.value"
                                        label="text"
                                        placeholder="Select Bedrooms"
                                        class="custom-v-select"
                                        :class="{ 'is-invalid': isPropertyFieldInvalid(property, 'bedrooms') }"
                                    >
                                        <template #open-indicator="{ attributes }">
                                            <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" /></span>
                                        </template>
                                    </v-select>
                                </div>
                      
                                <!-- Rental Price (للصفقات الإيجارية) -->
                                <div class="col-md-6" v-if="shouldShowPropertyField('rental_price', property)">
                                    <label class="form-label-custom">Rental Price <span v-if="isPropertyFieldRequired('rental_price', propIndex)" class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <b-form-input 
                                            v-model="property.rental_price"
                                            @update:modelValue="(val) => updateProperty(propIndex, 'rental_price', val)"
                                            type="number"
                                            placeholder="Amount" 
                                            class="custom-input"
                                            :class="{ 'is-invalid': isPropertyFieldInvalid(property, 'rental_price') }"
                                        />
                                        <span class="input-group-text">AED</span>
                                    </div>
                                </div>
                                
                                <!-- Purchase Price -->
                                <div class="col-md-6" v-if="shouldShowPropertyField('purchase_price', property)">
                                    <label class="form-label-custom"> {{ isWonStage ? 'Purchase Price' : 'Purchase Price' }} <span v-if="isPropertyFieldRequired('purchase_price', propIndex)" class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input
                                            :value="property.purchase_price ?? ''"
                                            @keypress="onMoneyKeypress"
                                            @input="(e) => onPropertyMoneyInput(propIndex, 'purchase_price', e.target.value)"
                                            type="text"
                                            inputmode="numeric"
                                            autocomplete="off"
                                             :placeholder="isWonStage ? 'Enter Purchase Price' : 'Enter Purchase Price'"
                                            class="form-control custom-input"
                                            :class="{ 'is-invalid': isPropertyFieldInvalid(property, 'purchase_price') }"
                                        />
                                        <span class="input-group-text">AED</span>
                                    </div>
                                </div>
                                
                             
                                
                                <!-- Developer -->
                                <div class="col-md-6" v-if="shouldShowPropertyField('developer_id', property)">
                                    <label class="form-label-custom">Developer <span v-if="isPropertyFieldRequired('developer_id', propIndex)" class="text-danger">*</span></label>
                                    <v-select
                                        :model-value="property.developer_id"
                                        @update:modelValue="(val) => updateProperty(propIndex, 'developer_id', val)"
                                        :options="developers"
                                        :reduce="item => item.id"
                                        label="name"
                                        placeholder="Select Developer"
                                        class="custom-v-select"
                                        :class="{ 'is-invalid': isPropertyFieldInvalid(property, 'developer_id') }"
                                    >
                                        <template #open-indicator="{ attributes }">
                                            <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" /></span>
                                        </template>
                                    </v-select>
                                </div>

                                <div class="col-md-6" v-if="shouldShowPropertyField('developer_name', property)">
                                    <label class="form-label-custom">Developer Sales Person Name <span v-if="isPropertyFieldRequired('developer_name', propIndex)" class="text-danger">*</span></label>
                                    <b-form-input
                                    v-model="property.developer_name"
                                        @update:modelValue="(val) => updateProperty(propIndex, 'developer_name', val)"
                                        placeholder="Enter Sales Person Name"
                                        class="custom-input"
                                        :class="{ 'is-invalid': isPropertyFieldInvalid(property, 'developer_name') }"
                                    />
                                </div>
                                
                                <!-- Developer Sales Person Phone (لـ Secondary) -->
                                <div class="col-md-6" v-if="shouldShowPropertyField('developer_phone', property)">
                                    <label class="form-label-custom">Developer Sales Person Phone <span v-if="isPropertyFieldRequired('developer_phone', propIndex)" class="text-danger">*</span></label>
                                  
                                    <CrmPhoneInput 
                                    v-model="property.developer_phone" 
                                    placeholder="Enter Phone" 
                                    :invalid="isPropertyFieldInvalid(property, 'developer_phone') "
                                    :show-errors="isPropertyFieldInvalid(property, 'developer_phone') "
                                  />
                                </div>
                                
                                <!-- Budget (CreateLead style) -->
                                <div class="col-md-6" v-if="shouldShowPropertyField('budget_from', property) || shouldShowPropertyField('budget_to', property)">
                                    <label class="form-label-custom">
                                      Budget (AED)
                                      <span v-if="isPropertyFieldRequired('budget_from', propIndex) || isPropertyFieldRequired('budget_to', propIndex)" class="text-danger">*</span>
                                    </label>
                                    <div
                                      class="budget-field-wrap-stage"
                                      :class="{
                                        'is-invalid-group': isPropertyFieldInvalid(property, 'budget_from') || isPropertyFieldInvalid(property, 'budget_to')
                                      }"
                                    >
                                      <button
                                        type="button"
                                        class="custom-date-trigger-stage"
                                        @click.stop="togglePropertyBudgetDropdown(propIndex)"
                                      >
                                        <span>{{ getPropertyBudgetDisplay(property) }}</span>
                                        <iconify-icon :icon="openBudgetDropdownIndex === propIndex ? 'lucide:chevron-up' : 'lucide:chevron-down'" />
                                      </button>
                                      <div
                                        v-if="openBudgetDropdownIndex === propIndex"
                                        class="budget-dropdown-stage"
                                        @mousedown.stop
                                        @click.stop
                                        @focusout="onBudgetDropdownFocusOut(propIndex, $event)"
                                      >
                                        <div class="budget-from-to-row-stage">
                                          <div class="budget-col-stage">
                                            <label class="budget-input-label-stage">From</label>
                                            <input
                                              :value="property.budget_from ?? ''"
                                              type="text"
                                              inputmode="numeric"
                                              autocomplete="off"
                                              placeholder="0"
                                              class="form-control custom-input budget-dropdown-input-stage"
                                              :class="{ 'is-invalid': isPropertyFieldInvalid(property, 'budget_from') }"
                                              @mousedown.stop
                                              @click.stop
                                              @keypress="onMoneyKeypress"
                                              @input="(e) => onPropertyMoneyInput(propIndex, 'budget_from', e.target.value)"
                                            />
                                          </div>
                                          <div class="budget-col-stage">
                                            <label class="budget-input-label-stage">To</label>
                                            <input
                                              :value="property.budget_to ?? ''"
                                              type="text"
                                              inputmode="numeric"
                                              autocomplete="off"
                                              placeholder="0"
                                              class="form-control custom-input budget-dropdown-input-stage"
                                              :class="{ 'is-invalid': isPropertyFieldInvalid(property, 'budget_to') }"
                                              @mousedown.stop
                                              @click.stop
                                              @keypress="onMoneyKeypress"
                                              @input="(e) => onPropertyMoneyInput(propIndex, 'budget_to', e.target.value)"
                                            />
                                          </div>
                                        </div>
                                      </div>
                                    </div>
                                </div>
                                          
                                <!-- Unit Size -->
                                <div class="col-md-6" v-if="shouldShowPropertyField('unit_size', property)">
                                    <label class="form-label-custom">Unit Size (sq.ft) <span v-if="isPropertyFieldRequired('unit_size', propIndex)" class="text-danger">*</span></label>
                                    <b-form-input 
                                        v-model="property.unit_size"
                                        @update:modelValue="(val) => updateProperty(propIndex, 'unit_size', val)"
                                        type="number"
                                        placeholder="Size in sq.ft" 
                                        class="custom-input"
                                        :class="{ 'is-invalid': isPropertyFieldInvalid(property, 'unit_size') }"
                                    />
                                </div>
                                 <!-- Unit No -->
                                <div class="col-md-6" v-if="shouldShowPropertyField('unit_no', property)">
                                    <label class="form-label-custom">Unit No <span v-if="isPropertyFieldRequired('unit_no', propIndex)" class="text-danger">*</span></label>
                                    <b-form-input 
                                    v-model="property.unit_no"
                                        @update:modelValue="(val) => updateProperty(propIndex, 'unit_no', val)"
                                        placeholder="Enter Unit No" 
                                        class="custom-input"
                                        :class="{ 'is-invalid': isPropertyFieldInvalid(property, 'unit_no') }"
                                    />
                                </div>
                                
                                <!-- Property Documents (Payment Proof + SPA — same idea as Create Deal / PropertyCard) -->
                                <!-- Property Documents (Payment Proof + SPA) -->
                                <div class="col-12 mt-3 property-documents-block">
                                    <label class="section-title mb-2">Property Documents <span v-if="isPropertyDocumentsSectionRequired" class="text-danger">*</span></label>
                                    
                                    <div
                                      v-if="validationAttempted && getMissingPropertyDocTypesForProperty(propIndex).some(t => t === 'eoi' || t === 'eoi_document')"
                                      class="small text-danger mb-2"
                                    >
                                      EOI is required for this property. Please add an EOI document.
                                    </div>
                                    <div
                                      v-if="validationAttempted && getMissingPropertyDocTypesForProperty(propIndex).some(t => t === 'booking' || t === 'booking_document')"
                                      class="small text-danger mb-2"
                                    >
                                      Booking form is required for this property. Please add a booking document.
                                    </div>
                                    <div
                                      v-if="validationAttempted && getMissingPropertyDocTypesForProperty(propIndex).some(t => t === 'payment_proof' || t === 'payment')"
                                      class="small text-danger mb-2"
                                    >
                                      Payment Proof is required for this property. Please add Payment Proof Document.
                                    </div>
                                    <div
                                      v-if="validationAttempted && getMissingPropertyDocTypesForProperty(propIndex).some(t => t === 'spa' || t === 'spa_document')"
                                      class="small text-danger mb-2"
                                    >
                                      SPA Document is required for this property. Please add SPA Document.
                                    </div>
                                    
                                    <DocumentUpload
                                        :modelValue="propertyDocumentsCombined[propIndex] || []"
                                        @update:modelValue="(val) => updatePropertyDocuments(propIndex, val)"
                                        category="property"
                                        :deal-id="props.deal?.id || props.dealId"
                                        :property-id="property?.id || null"
                                        :document-types="propertyDocTypesForModal"
                                        compact
                                        :show-errors="validationAttempted"
                                        :missing-document-types="getMissingPropertyDocTypesForProperty(propIndex)"
                                        :key="`property-docs-${propIndex}-${property?.id || 'new'}`"
                                    />
                                </div>
                            </div>
                        </div>
                        
                        <div v-if="dealProperties.length === 0" class="alert alert-warning">
                            <iconify-icon icon="lucide:alert-triangle" class="me-2"></iconify-icon>
                            At least one property is required for this stage. Please add a property.
                        </div>
                    </template>
                </div>
            </section>
            
            <!-- Deal Financials Section -->
            <section v-if="shouldShowFinancialSection" class="form-section">
              <div 
                  class="section-collapsible-header"
                  :class="getSectionHeaderClass('financials')"
                  @click="toggleSection('financials')"
                >
                  <h6 class="section-title mb-0">Deal Financials</h6>
                  <div class="d-flex">
                    <span v-if="hasRequiredInSection('financials') && !isSectionComplete('financials')" class="required-badge">Required</span>
                    <span v-if="isSectionComplete('financials')" class="completed-badge">Completed</span>
                    <iconify-icon :icon="isSectionOpen('financials') ? 'lucide:chevron-down' : 'lucide:chevron-right'" class="collapse-icon"></iconify-icon>
                  </div>
                </div>
              
              <div
                v-show="isSectionOpen('financials')"
                class="section-content"
                @focusout="onCollapsibleSectionFocusOut('financials', $event)"
              >
                <div class="form-card p-3 radius-12">
                  <div class="row g-3">
                    <div class="col-md-6" v-if="shouldShowField('deal_total_amount')">
                      <label class="form-label-custom">Deal amount <span v-if="isRequiredField('deal_total_amount')" class="text-danger">*</span></label>
                      <div class="input-group">
                        <span class="input-group-text">AED</span>
                        <input
                          :value="displayedDealAmount"
                          type="text"
                          inputmode="numeric"
                          autocomplete="off"
                          placeholder="Enter deal amount"
                          class="form-control custom-input compact-placeholder-field"
                          :class="{ 'is-invalid': isFieldInvalid('deal_total_amount') }"
                          @keypress="onMoneyKeypress"
                          @input="(e) => onDealAmountInput(e.currentTarget.value)"
                        />
                      </div>
                    </div>
                    <div class="col-md-6" v-if="shouldShowField('deal_commission')">
                      <label class="form-label-custom">Deal Commission % <span v-if="isRequiredField('deal_commission')" class="text-danger">*</span></label>
                      <b-form-input
                        v-model="formData.deal_commission"
                        type="number"
                        placeholder="Enter Commission %"
                        class="custom-input compact-placeholder-field"
                        :class="{ 'is-invalid': isFieldInvalid('deal_commission') }"
                      />
                    </div>
                  </div>
                </div>
              </div>
            </section>
          </div>
        </div>

        <!-- Footer -->
        <div class="modal-footer-custom p-3">
          <div v-if="unresolvedMissingKeys.length > 0" class="small text-danger mb-2">
            <iconify-icon icon="lucide:alert-circle" class="me-1"></iconify-icon>
            {{ unresolvedMissingKeys.length }} required field(s) still missing.
          </div>
          <div class="d-flex align-items-center justify-content-end gap-3">
            <button class="btn-clear" @click="closeModal" :disabled="submitting">Cancel</button>
            <button
              class="btn-next-step"
              type="button"
              @click="submitForm"
              :disabled="loading || submitting"
            >
              <span v-if="submitting">
                <b-spinner small></b-spinner> Saving...
              </span>
              <span v-else>Save</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick, shallowRef } from 'vue'
import { BFormInput, BSpinner } from 'bootstrap-vue-3'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import DocumentUpload from './DocumentUpload.vue'
import AdvancedDatePicker from '@/components/shared/AdvancedDatePicker.vue'
import CrmPhoneInput from '@/components/common/CrmPhoneInput.vue'
import api from '@/plugins/axios'
import { isNonEmptyPhoneValid } from '@/utils/phone'
import { normalizeLanguageSelection } from '@/composables/useLanguageMultiSelect'
import countries from "i18n-iso-countries";
import en from "i18n-iso-countries/langs/en.json";

// تسجيل اللغة
countries.registerLocale(en);
const nationalityOptions = Object.entries(
  countries.getNames("en", { select: "official" })
).map(([code, name]) => ({
  value: code.toLowerCase(), // eg
  text: name, // Egypt
  code: code.toLowerCase()
}));
const isLoadingPropertyData = ref(true)
const isDataLoaded = ref(false)
const isDataInitialized = ref(false)
const propertyTypesLoaded = ref(false)
const developersLoaded = ref(false)
const areasLoaded = ref(false)
const props = defineProps({
  show: { type: Boolean, default: false },
  dealId: { type: [Number, String], default: null },
  dealType: { type: String, default: 'primary' },
  targetStageId: { type: [Number, String], default: null },
  targetStageName: { type: String, default: '' },
  targetStageOrder: { type:[ Number,String], default: null },
  missingFields: { type: Array, default: () => [] },
  missingFieldsGrouped: { type: Object, default: () => ({ sections: [] }) },
  missingFieldsGroupedByStage: { type: Object, default: () => ({ stages: [] }) },
  groupedMissing: { type: Object, default: () => ({ sections: [], by_stage: [] }) },
  deal: { type: Object, default: null },
  requiredFields: { type: Array, default: () => [] } 
})

/**
 * Union of normalized required/missing keys seen while this modal is open.
 * Missing arrays shrink as the user fills fields; this set keeps label asterisks stable.
 */
const accumulatedRequiredKeys = shallowRef(new Set())

function resetAccumulatedRequiredKeys() {
  accumulatedRequiredKeys.value = new Set()
}

function accumulateRequiredKeysFromProps() {
  const next = new Set(accumulatedRequiredKeys.value)
  const addKey = (raw) => {
    if (raw === null || raw === undefined || raw === '') return
    next.add(normalizeMissingFieldKey(String(raw)))
  }
  ;(props.requiredFields || []).forEach(addKey)
  ;(props.missingFields || []).forEach(addKey)
  const byStage = props.groupedMissing?.by_stage || props.missingFieldsGroupedByStage?.stages || []
  byStage.forEach((stage) => {
    ;(stage?.fields || stage?.missing_fields || []).forEach(addKey)
  })
  accumulatedRequiredKeys.value = next
}

/** After clicking Save: show red borders + scroll to first invalid (not before). */
const validationAttempted = ref(false)

// ========== Collapsible Sections State ==========
const openSections = ref({
  buyer: true,
  seller: true,
  tenant: true,
  landlord: true,
  properties: true,
  financials: true
})
// Add new property with same required fields structure
const addNewProperty = () => {
  
    const dt = normalizedDealType.value
    const shouldShowUnitSize = dt === 'primary' 
    const shouldShowBedrooms = dt !== 'rental' 
    
    const newProperty = {
        id: Date.now(),
        new:1,
        sort_order: localProperties.value.length,
        unit_no: '',
        property_type_id: null,
        bedrooms: null,
        unit_size: shouldShowUnitSize ? '' : null,  
        area_id: null,
        developer_id: null,
        developer_name: '',
        developer_phone: '',
        budget_from: null,
        budget_to: null,
        purchase_price: null,
        commission: null,
        payment_proof: [],
        spa_document: []
    }
    
    localProperties.value.push(newProperty)
    
    // Initialize documents array for the new property
    const newDocArray = []
    const updatedCombined = { ...propertyDocumentsCombined.value }
    updatedCombined[localProperties.value.length - 1] = newDocArray
    propertyDocumentsCombined.value = updatedCombined
    
    if (!formData.value.properties) {
        formData.value.properties = []
    }
    formData.value.properties.push({ ...newProperty })
    
    openSections.value.properties = true
    recentlyOpenedSection.value = 'properties'
    if (recentlyOpenedTimer.value) {
        clearTimeout(recentlyOpenedTimer.value)
    }
    recentlyOpenedTimer.value = setTimeout(() => {
        recentlyOpenedSection.value = null
    }, 3000)
    
    nextTick(() => {
        const modalEl = document.querySelector('.complete-fields-modal')
        if (modalEl) {
            const scrollRoot = modalEl.querySelector('.form-scroll-area')
            const newPropertyEl = modalEl.querySelector(`.property-card-in-modal:last-child`)
            if (scrollRoot && newPropertyEl) {
                newPropertyEl.scrollIntoView({ behavior: 'smooth', block: 'center' })
            }
        }
    })
    
    console.log('New property added:', newProperty)
}// Remove property by index
const removeProperty = (propIndex) => {
    if (localProperties.value.length <= 1) {
        console.warn('Cannot remove the last property')
        return
    }
    
    localProperties.value.splice(propIndex, 1)
    
    const updatedCombined = { ...propertyDocumentsCombined.value }
    delete updatedCombined[propIndex]
    propertyDocumentsCombined.value = updatedCombined
    
    localProperties.value.forEach((prop, idx) => {
        prop.sort_order = idx
    })
    
    formData.value.properties = [...localProperties.value]
    
    reinitializePropertyDocuments()
    
    console.log('Property removed at index:', propIndex)
}
// Check if a section has no unresolved missing fields (completed)
function isSectionComplete(section) {
  const unresolved = unresolvedMissingKeys.value || []
  
  switch(section) {
    case 'buyer':
      return !unresolved.some(k => k.startsWith('buyer_') || k.startsWith('buyer_document_'))
    case 'seller':
      return !unresolved.some(k => k.startsWith('seller_') || k.startsWith('seller_document_'))
    case 'tenant':
      return !unresolved.some(k => k.startsWith('tenant_') || k.startsWith('tenant_document_'))
    case 'landlord':
      return !unresolved.some(k => k.startsWith('landlord_') || k.startsWith('landlord_document_'))
    case 'properties':
      return !unresolved.some(k => k.startsWith('property_') || k === 'at_least_one_property')
    case 'financials':
      return !unresolved.some(k => ['deal_commission', 'deal_total_amount'].includes(k))
    default:
      return false
  }
}

// Get section header class based on completion status
function getSectionHeaderClass(section) {
  const hasReq = hasRequiredInSection(section)
  const isComplete = isSectionComplete(section)
  
  if (isComplete && hasReq) {
    return 'has-completed'
  }
  if (hasReq && !isComplete) {
    return 'has-required'
  }
  return ''
}
function toggleSection(section) {
    const isComplete = isSectionComplete(section)
  

  const isCurrentlyOpen = isSectionOpen(section)
  if (isCurrentlyOpen && hasUnresolvedInSection(section)) {
    return
  }

  const nextOpen = !openSections.value[section]
  openSections.value[section] = nextOpen
  
  // When user opens another section, auto-close completed sections.
  if (nextOpen) {
    const sections = ['buyer', 'seller', 'tenant', 'landlord', 'properties', 'financials']
    sections.forEach((key) => {
      if (key !== section && isSectionOpen(key) && !hasUnresolvedInSection(key)) {
        openSections.value[key] = false
      }
    })

    // إذا تم فتح القسم يدوياً، ضع علامة لمنع الإغلاق التلقائي لفترة
    recentlyOpenedSection.value = section
    if (recentlyOpenedTimer.value) {
      clearTimeout(recentlyOpenedTimer.value)
    }
    recentlyOpenedTimer.value = setTimeout(() => {
      recentlyOpenedSection.value = null
    }, 3000) // 3 ثواني - فترة كافية لملء البيانات
  }
}
function isSectionOpen(section) {
  return openSections.value[section] !== false
}

/**
 * PRIMARY: show budget only before BOOKING (stage order 1–2). BOOKING+ hides it.
 * Other deal types: unchanged.
 */
function isBudgetVisibleForPrimaryDeal() {
  const dealLike = currentDealData.value || props.deal
  const dt = normalizeDealTypeForDocuments(dealLike?.deal_type ?? dealLike?.type ?? props.dealType)
  
  // إذا لم تكن الصفقة من نوع Primary، أظهر الحقل
  if (dt !== 'primary') return true
  
  // الحصول على اسم المرحلة المستهدفة
  const targetStageName = props.targetStageName || ''
  const targetStageNameLower = targetStageName.toLowerCase().trim()
  
  // قائمة المراحل التي يجب أن يظهر فيها حقل Budget
  const stagesWhereBudgetVisible = ['eoi', 'new']
  
  // التحقق من اسم المرحلة
  const isVisible = stagesWhereBudgetVisible.some(stageName => 
    targetStageNameLower.includes(stageName)
  )
  
  console.log("Target Stage:", targetStageName, "Budget Visible:", isVisible)
  
  return isVisible
}

/** Collapse section when focus leaves it and nothing is left missing in that section. */
function isLikelyUIPortalFocus() {
  const el = document.activeElement
  if (!el || !(el instanceof HTMLElement)) return false
  return Boolean(
    el.closest('.vs__dropdown-menu')
    || el.closest('.flatpickr-calendar')
    || el.closest('.budget-dropdown-stage')
  )
}

function onCollapsibleSectionFocusOut(sectionKey, event) {
  // const root = event.currentTarget
  // if (!(root instanceof HTMLElement)) return
  // const related = event.relatedTarget
  // if (related instanceof Node && root.contains(related)) return
  // requestAnimationFrame(() => {
  //   if (root.contains(document.activeElement)) return
  //   if (isLikelyUIPortalFocus()) return
  //   if (!hasUnresolvedInSection(sectionKey)) {
  //     openSections.value[sectionKey] = false
  //   }
  // })
}

function onBudgetDropdownFocusOut(propIndex, event) {
  const root = event.currentTarget
  if (!(root instanceof HTMLElement)) return
  const related = event.relatedTarget
  if (related instanceof Node && root.contains(related)) return
  requestAnimationFrame(() => {
    if (root.contains(document.activeElement)) return
    if (openBudgetDropdownIndex.value === propIndex) {
      openBudgetDropdownIndex.value = null
    }
  })
}

function hasRequiredInSection(section) {
  // Check if any field in this section is required (has missing)
  switch(section) {
    case 'buyer':
      return effectiveMissingFields.value.some(f => 
        f.startsWith('buyer_') || f.startsWith('buyer_document_')
      )
    case 'seller':
      return effectiveMissingFields.value.some(f => 
        f.startsWith('seller_') || f.startsWith('seller_document_')
      )
    case 'tenant':
      return effectiveMissingFields.value.some(f => 
        f.startsWith('tenant_') || f.startsWith('tenant_document_')
      )
    case 'landlord':
      return effectiveMissingFields.value.some(f => 
        f.startsWith('landlord_') || f.startsWith('landlord_document_')
      )
    case 'properties':
      return effectiveMissingFields.value.some(f => 
        f.startsWith('property_') || f === 'at_least_one_property'
      )
    case 'financials':
      return effectiveMissingFields.value.some(f =>
        ['deal_commission', 'deal_total_amount'].includes(f)
      )
    default:
      return false
  }
}



// ========== Helper Functions ==========
const showBudgetFields = computed(() => {
  if (!isBudgetVisibleForPrimaryDeal()) return false
  const missingKeys = effectiveMissingFields.value || []
  const hasBudgetFrom = missingKeys.some(key => key.includes('budget_from'))
  const hasBudgetTo = missingKeys.some(key => key.includes('budget_to'))
  return hasBudgetFrom || hasBudgetTo
})

const showPurchasePrice = computed(() => {
  const missingKeys = effectiveMissingFields.value || []
  const hasMissingPurchasePrice = missingKeys.some((key) => key.includes('purchase_price'))
  const hasPurchaseValue = localProperties.value.some((property) => !!property?.purchase_price)
  return hasMissingPurchasePrice || hasPurchaseValue
})

// Property documents
const propertyDocumentsCombined = ref({})

const propertyDocumentTypes = computed(() => {
  const docTypes = []
  const missingKeys = effectiveMissingFields.value || []
  
  missingKeys.forEach(key => {
    if (key.includes('property_document_')) {
      const docType = key.replace(/property_document_/g, '')
      docTypes.push({
        id: docType,
        name: docType === 'payment_proof' ? 'Payment Proof' : 'SPA Document',
        required: true
      })
    }
  })
  
  return docTypes
})

const missingPropertyDocumentTypes = computed(() => {
  const missing = []
  const missingKeys = effectiveMissingFields.value || []
  
  missingKeys.forEach(key => {
    if (key.includes('property_document_')) {
      const docType = key.replace(/property_document_/g, '')
      missing.push(docType)
    }
  })
  
  return missing
})

const hasPropertyDocumentRequirements = computed(() => {
  return missingPropertyDocumentTypes.value.length > 0
})

// Function to check if property has missing fields - FIXED for index patterns
function hasPropertyMissing(propIndex) {
  const property = dealProperties.value[propIndex]
  if (!property) return false
  
  const missingKeys = effectiveMissingFields.value || []
  
  // Check for required fields for this specific property
  const hasMissing = missingKeys.some(key => {
    // Pattern: property_0_unit_no, property_0_area_id, etc.
    const match = key.match(/property_(\d+)_(.+)/)
    if (match) {
      const idx = parseInt(match[1])
      const field = match[2]
      if (idx === propIndex) {
        const value = property[field]
        return value === null || value === undefined || value === ''
      }
    }
    return false
  })
  
  return hasMissing
}

function isBedroomsExcludedForProperty(property) {
  const propertyTypeId = property?.property_type_id
  if (!propertyTypeId) return false
  const selectedType = propertyTypes.value.find((t) => t.id === propertyTypeId)
  const typeName = selectedType?.name?.toLowerCase() || ''
  return typeName.includes('land') || typeName.includes('plot')
}

/** Stage-required property columns from API + accumulated snapshot, optional row index. */
function isPropertyFieldRequiredByStageList(fieldName, propIndex = null) {
  const normalizedFieldName = normalizePropertyFieldKey(fieldName)
  const reqList =
    accumulatedRequiredKeys.value.size > 0
      ? Array.from(accumulatedRequiredKeys.value)
      : (props.requiredFields || []).map((k) => normalizeMissingFieldKey(String(k)))
  if (!reqList.length) return false
  for (const rawKey of reqList) {
    const key = String(rawKey)
    if (key.includes('property_document_')) continue
    const m = key.match(/^property_(\d+)_(.+)$/i)
    if (!m) continue
    const idx = parseInt(m[1], 10)
    const fieldPart = normalizePropertyFieldKey(m[2])
    if (fieldPart !== normalizedFieldName) continue
    if (propIndex !== null && propIndex !== undefined && idx !== propIndex) continue
    if (normalizedFieldName === 'bedrooms' && localProperties.value[idx] && isBedroomsExcludedForProperty(localProperties.value[idx])) {
      continue
    }
    return true
  }
  return false
}

/** Fallback: keys still reported as missing (when `required_fields` is empty or partial). */
function isPropertyFieldRequiredFromMissing(fieldName, propIndex = null) {
  const missingKeys = effectiveMissingFields.value || []
  const normalizedFieldName = normalizePropertyFieldKey(fieldName)
  return missingKeys.some((key) => {
    const match = key.match(/property_(\d+)_(.+)/)
    if (match) {
      const idx = parseInt(match[1], 10)
      const field = normalizePropertyFieldKey(match[2])
      if (field !== normalizedFieldName) return false
      if (propIndex !== null && propIndex !== undefined && idx !== propIndex) return false
      if (field === 'bedrooms' && localProperties.value[idx] && isBedroomsExcludedForProperty(localProperties.value[idx])) {
        return false
      }
      return true
    }
    if (normalizePropertyFieldKey(key.replace('property_', '')) === normalizedFieldName) {
      if (normalizedFieldName === 'bedrooms' && propIndex !== null && propIndex !== undefined) {
        const prop = localProperties.value[propIndex]
        if (prop && isBedroomsExcludedForProperty(prop)) return false
      }
      if (propIndex !== null && propIndex !== undefined) return false
      return true
    }
    return false
  })
}

/**
 * Whether a property column is required for this stage (label asterisk).
 * Pass `propIndex` from the properties v-for so each row matches its own required keys.
 */
function isPropertyFieldRequired(fieldName, propIndex = null) {
  if (isPropertyFieldRequiredByStageList(fieldName, propIndex)) return true
  return isPropertyFieldRequiredFromMissing(fieldName, propIndex)
}

function getDealPropertyIndex(property) {
  if (!property) return null
  const list = dealProperties.value || []
  let idx = list.findIndex((p) => p === property)
  if (idx !== -1) return idx
  if (property.id != null && property.id !== undefined) {
    idx = list.findIndex((p) => p?.id != null && String(p.id) === String(property.id))
  }
  return idx !== -1 ? idx : null
}
function getRequiredFieldsForProperty() {
  const missingKeys = effectiveMissingFields.value || []
  const requiredFields = new Set()
  
  missingKeys.forEach(key => {
    // Pattern: property_0_unit_no
    const match = key.match(/property_\d+_(.+)/)
    if (match) {
      requiredFields.add(match[1])
    }
    // Pattern: property_unit_no (بدون index)
    if (key.startsWith('property_') && !key.includes('property_document_')) {
      const field = key.replace('property_', '')
      requiredFields.add(field)
    }
  })
  
  console.log('Required property fields:', Array.from(requiredFields))
  return Array.from(requiredFields)
}
// Check if specific property field is invalid (only after Save)
function isPropertyFieldInvalid(property, fieldName) {
  if (!validationAttempted.value) return false
  const pIdx = getDealPropertyIndex(property)
  const isRequired = isPropertyFieldRequired(fieldName, pIdx !== null && pIdx !== -1 ? pIdx : null)
  if (!isRequired) return false
  
  const normalizedFieldName = normalizePropertyFieldKey(fieldName)
  const value =
    normalizedFieldName === 'developer_phone'
      ? (property.developer_phone ?? property.developer_sales_phone)
      : property[normalizedFieldName]
  return value === null || value === undefined || value === ''
}

// Update property
function updateProperty(propIndex, field, value) {
  if (dealProperties.value[propIndex]) {
    dealProperties.value[propIndex][field] = value

    if (field === 'developer_id') {
      const selectedDeveloper = developers.value.find(dev => String(dev?.id) === String(value))
      if (selectedDeveloper) {
        const normalizedName =
          selectedDeveloper.name ||
          selectedDeveloper.developer_name ||
          selectedDeveloper.contact_person ||
          ''
        const normalizedPhone =
          selectedDeveloper.developer_phone ||
          selectedDeveloper.sales_phone ||
          selectedDeveloper.phone ||
          selectedDeveloper.contact_phone ||
          selectedDeveloper.mobile ||
          ''

        // if (normalizedName) {
        //   dealProperties.value[propIndex].developer_name = normalizedName
        // }
        // if (normalizedPhone) {
        //   dealProperties.value[propIndex].developer_phone = normalizedPhone
        // }
      }
    }
    
    if (!formData.value.properties) {
      formData.value.properties = []
    }
    formData.value.properties[propIndex] = { ...dealProperties.value[propIndex] }
  }
}

function onPropertyMoneyInput(propIndex, field, rawValue) {
  updateProperty(propIndex, field, formatDealAmountThousands(rawValue))
}

function onMoneyKeypress(e) {
  if (!/^\d$/.test(e.key)) e.preventDefault()
}

// Check if bedrooms field should be shown for property
function showBedroomsForProperty(property) {
  const propertyTypeId = property.property_type_id
  if (!propertyTypeId) return true
  
  const selectedType = propertyTypes.value.find(t => t.id === propertyTypeId)
  const typeName = selectedType?.name?.toLowerCase() || ''
  
  if (typeName.includes('land') || typeName.includes('plot')) {
    return false
  }
  
  return true
}

// Initialize property documents
function initializePropertyDocuments() {
  if (!dealProperties.value.length) return
  
  dealProperties.value.forEach((property, idx) => {
    if (propertyDocumentsCombined.value[idx] && propertyDocumentsCombined.value[idx].length) {
      return
    }
    
    const paymentProof = property.payment_proof || []
    const spaDocument = property.spa_document || []
    
    let docs = []
    
    if (typeof paymentProof === 'string') {
      try {
        const parsed = JSON.parse(paymentProof)
        docs.push(...(Array.isArray(parsed) ? parsed : []))
      } catch { docs.push(...(Array.isArray(paymentProof) ? paymentProof : [])) }
    } else {
      docs.push(...(Array.isArray(paymentProof) ? paymentProof : []))
    }
    
    if (typeof spaDocument === 'string') {
      try {
        const parsed = JSON.parse(spaDocument)
        docs.push(...(Array.isArray(parsed) ? parsed : []))
      } catch { docs.push(...(Array.isArray(spaDocument) ? spaDocument : [])) }
    } else {
      docs.push(...(Array.isArray(spaDocument) ? spaDocument : []))
    }
    
    propertyDocumentsCombined.value[idx] = docs.map(doc => ({
      ...doc,
      document_type: doc.document_type || (doc.original_name ? (doc.original_name.includes('payment') ? 'payment_proof' : 'spa') : 'payment_proof'),
      url: doc.url || doc.path || null,
      file: doc.file || null,
      name: doc.original_name || doc.name || 'Document'
    }))
  })
}

const emit = defineEmits(['save', 'closed', 'openDeal'])

// State
const formData = ref({})
const localProperties = ref([])
const submitting = ref(false)
const loading = ref(false)
let submitResetTimer = null
const invalidFields = ref(new Set())
const hasListingId = ref(false)

// Data from API
const users = ref([])
const sources = ref([])
const propertyTypes = ref([])
const developers = ref([])
const areas = ref([])

// Document upload refs
const buyerDocUploadRef = ref(null)
const sellerDocUploadRef = ref(null)
const tenantDocUploadRef = ref(null)
const landlordDocUploadRef = ref(null)

const availableListings = ref([])
const selectedListing = ref(null)
const isLoadingListings = ref(false)
const currentUser = ref(null)
const currentDealData = ref(null)

const dealProperties = computed(() => {
  return localProperties.value
})
const openBudgetDropdownIndex = ref(null)

// Helper functions
function getDealTypeName(type) {
  const types = {
    primary: 'Primary',
    secondary: 'Secondary',
    rental: 'Rental'
  }
  return types[type] || type
}

/** Formats integer part with comma thousands; preserves a single decimal part while typing. */
function formatDealAmountThousands(raw) {
  const cleaned = String(raw ?? '')
    .replace(/,/g, '')
    .replace(/[^\d.]/g, '')
  const firstDot = cleaned.indexOf('.')
  const hasDot = firstDot !== -1
  const intRaw = hasDot ? cleaned.slice(0, firstDot) : cleaned
  const fracRaw = hasDot ? cleaned.slice(firstDot + 1).replace(/\./g, '') : ''
  if (!intRaw && !fracRaw && !hasDot) return ''
  const intFormatted = intRaw ? intRaw.replace(/\B(?=(\d{3})+(?!\d))/g, ',') : ''
  if (hasDot) return intFormatted + '.' + fracRaw
  return intFormatted
}

const dealAmountManuallyEdited = ref(false)

function onDealAmountInput(val) {
  dealAmountManuallyEdited.value = true
  formData.value.deal_total_amount = formatDealAmountThousands(val)
}

const displayedDealAmount = computed(() => {
  return dealAmountManuallyEdited.value
    ? (formData.value.deal_total_amount ?? '')
    : computedTotalAmount.value
})

function parseDealAmountNumeric(raw) {
  return Number(String(raw ?? '').replace(/,/g, '').trim())
}

function toNullableNumeric(raw) {
  const parsed = parseDealAmountNumeric(raw)
  return Number.isFinite(parsed) ? parsed : null
}

function normalizeDealTypeForDocuments(raw) {
  const s = String(raw ?? '').toLowerCase().trim().replace(/\s+/g, '_')
  if (!s) return 'primary'
  if (s.includes('secondary') || s.includes('resale')) return 'secondary'
  if (s.includes('rental') || s.includes('lease')) return 'rental'
  if (s.includes('primary') || s.includes('off_plan') || s.includes('offplan') || s.includes('off-plan') || s === 'sale') return 'primary'
  if (s === 'primary' || s === 'secondary' || s === 'rental') return s
  return 'primary'
}

const effectiveDealTypeForDocs = computed(() =>
  normalizeDealTypeForDocuments(props.deal?.deal_type ?? props.deal?.type ?? props.dealType)
)

const normalizedDealType = computed(() => effectiveDealTypeForDocs.value)

// Get existing field value from deal
function getExistingFieldValue(key) {
  const deal = currentDealData.value || props.deal
  if (!deal) return ''
  
  if (deal[key] !== undefined && deal[key] !== null) return deal[key]
  
  const partyTypes = ['buyer', 'seller', 'tenant', 'landlord']
  for (const partyType of partyTypes) {
    if (key.startsWith(partyType + '_') && !key.includes('_document_')) {
      const party = deal.parties?.find(p => p.party_type === partyType)
      if (!party) return ''
      
      const field = key.replace(partyType + '_', '')
      if (field === 'first_name') return party.first_name || ''
      if (field === 'last_name') return party.last_name || ''
      if (field === 'phone') return party.phone || ''
      if (field === 'email') return party.email || ''
      if (field === 'nationality') return party.nationality || ''
      if (field === 'dob') return party.date_of_birth || ''
      if (field === 'residency_status') return party.residency_status || ''
      if (field === 'city') return party.city || ''
      if (field === 'country') return party.country || ''
      if (field === 'language') return party.language || ''
      return party[field] || ''
    }
  }
  
  return ''
}

// Get existing documents from deal
function getExistingDocuments(partyType) {
  const deal = currentDealData.value || props.deal
  if (!deal?.parties) return []
  
  const party = deal.parties.find(p => p.party_type === partyType)
  if (!party?.documents) return []
  
  return party.documents.map((doc) => {
    const resolvedUrl = doc.url || doc.file_url || null
    const hasServerFile = !!(resolvedUrl || doc.path) && !(doc.file instanceof File)
    return {
      id: doc.id,
      file: doc.file instanceof File ? doc.file : null,
      url: resolvedUrl,
      path: doc.path || null,
      document_type: doc.document_type,
      name: doc.file_name || doc.name || doc.document_type,
      size: doc.file_size || doc.size || 0,
      mime_type: doc.mime_type || '',
      isUploading: false,
      uploaded: true,
      existing: true,
      is_existing: hasServerFile,
    }
  })
}

function hydratePropertyDocsFromDealDocuments(deal) {
  if (!deal || !Array.isArray(deal.documents) || localProperties.value.length === 0) return

  const propertyDocs = deal.documents.filter((doc) => {
    const category = String(doc?.document_category || doc?.category || '').toLowerCase()
    const docType = String(doc?.document_type || doc?.type || '').toLowerCase()
    const isPropertyKind =
      docType.includes('spa') ||
      docType.includes('payment') ||
      docType.includes('eoi') ||
      docType === 'booking' ||
      docType.includes('booking')
    return (
      category === 'property' ||
      category === 'properties' ||
      category.includes('property') ||
      (!category && isPropertyKind)
    )
  })
  if (propertyDocs.length === 0) return

  // Stage-change modal currently edits one property block at a time in primary flow.
  // If docs come from deal-level "documents", merge them onto first property arrays.
  const firstProperty = localProperties.value[0]
  if (!firstProperty) return

  const existingPayment = Array.isArray(firstProperty.payment_proof) ? [...firstProperty.payment_proof] : []
  const existingSpa = Array.isArray(firstProperty.spa_document) ? [...firstProperty.spa_document] : []
  const existingEoi = Array.isArray(firstProperty.eoi_documents) ? [...firstProperty.eoi_documents] : []
  const existingBooking = Array.isArray(firstProperty.booking_documents) ? [...firstProperty.booking_documents] : []
  const existingMou = Array.isArray(firstProperty.mou_documents) ? [...firstProperty.mou_documents] : []
  const existingNoc = Array.isArray(firstProperty.noc_documents) ? [...firstProperty.noc_documents] : []

  propertyDocs.forEach((doc) => {
    const rawType = String(doc?.document_type || doc?.type || '').toLowerCase()
    const labelHint = String(doc?.file_name || doc?.name || doc?.original_name || '').toLowerCase()
    let normalizedType = rawType
    if (rawType === 'spa' || rawType === 'spa_document' || rawType.includes('spa') || labelHint.includes('spa')) {
      normalizedType = 'spa_document'
    } else if (
      rawType === 'payment' ||
      rawType === 'payment_proof' ||
      rawType.includes('payment') ||
      labelHint.includes('payment') ||
      labelHint.includes('proof')
    ) {
      normalizedType = 'payment_proof'
    } else if (rawType.includes('eoi') || labelHint.includes('eoi')) {
      normalizedType = 'eoi'
    } else if (rawType === 'booking' || rawType.includes('booking') || labelHint.includes('booking')) {
      normalizedType = 'booking'
    } else if (rawType === 'mou' || rawType.includes('mou') || labelHint.includes('mou')) {
      normalizedType = 'mou'
    } else if (rawType === 'noc' || rawType.includes('noc') || labelHint.includes('noc')) {
      normalizedType = 'noc'
    }

    const normalizedDoc = {
      document_type: normalizedType,
      name: doc?.file_name || doc?.name || normalizedType,
      original_name: doc?.file_name || doc?.original_name || doc?.name || normalizedType,
      url: doc?.url || doc?.file_url || doc?.file_path || doc?.path || null,
      path: doc?.file_path || doc?.path || null,
      existing: true,
      uploaded: true,
    }

    if (normalizedType === 'payment_proof') {
      existingPayment.push(normalizedDoc)
    }
    if (normalizedType === 'spa_document') {
      existingSpa.push(normalizedDoc)
    }
    if (normalizedType === 'eoi') {
      existingEoi.push(normalizedDoc)
    }
    if (normalizedType === 'booking') {
      existingBooking.push(normalizedDoc)
    }
    if (normalizedType === 'mou') {
      existingMou.push(normalizedDoc)
    }
    if (normalizedType === 'noc') {
      existingNoc.push(normalizedDoc)
    }
  })

  firstProperty.payment_proof = existingPayment
  firstProperty.spa_document = existingSpa
  firstProperty.eoi_documents = existingEoi
  firstProperty.booking_documents = existingBooking
  firstProperty.mou_documents = existingMou
  firstProperty.noc_documents = existingNoc
}

function normalizeStoredDocs(raw) {
  if (!raw) return []
  if (Array.isArray(raw)) return raw
  if (typeof raw === 'string') {
    try {
      const parsed = JSON.parse(raw)
      if (Array.isArray(parsed)) return parsed
      if (parsed && typeof parsed === 'object') return [parsed]
      return []
    } catch {
      return []
    }
  }
  if (typeof raw === 'object') return [raw]
  return []
}

// Fetch functions
async function fetchUsers() {
  try {
    const response = await api.get('/available-responsible-persons')
    const responseData = response.data
    users.value = responseData?.data || responseData || []
  } catch (error) {
    console.error('Error fetching users:', error)
  }
}

async function fetchSources() {
  try {
    const response = await api.get('/sources')
    const responseData = response.data
    sources.value = responseData?.data || responseData || []
  } catch (error) {
    console.error('Error fetching sources:', error)
  }
}

async function fetchPropertyTypes() {
  try {
    const response = await api.get('/listings/property-types')
    const responseData = response.data
    propertyTypes.value = responseData?.data || responseData || []
    propertyTypesLoaded.value = true
  } catch (error) {
    console.error('Error fetching property types:', error)
    propertyTypesLoaded.value = true // حتى لو فشل، ننهي حالة التحميل
  }
}

async function fetchDevelopers() {
  try {
    const response = await api.get('/listings/developers')
    developers.value = response.data?.data || response.data || []
    developersLoaded.value = true
  } catch (error) {
    console.error('Error fetching developers:', error)
    developersLoaded.value = true
  }
}

async function fetchAllAreas() {
  try {
    const response = await api.get('/listings/areas')
    const responseData = response.data
    let areasData = []
    
    if (responseData?.data?.data) {
      areasData = responseData.data.data
    } else if (responseData?.data && Array.isArray(responseData.data)) {
      areasData = responseData.data
    } else if (Array.isArray(responseData)) {
      areasData = responseData
    } else {
      areasData = []
    }
    areas.value = areasData
    areasLoaded.value = true
  } catch (error) {
    console.error('Error loading areas:', error)
    areasLoaded.value = true
  }
}
async function fetchAreas(search = '') {
  try {
    const response = await api.get('/listings/areas', { params: { search } })
    const responseData = response.data
    areas.value = responseData?.data || responseData || []
  } catch (error) {
    console.error('Error fetching areas:', error)
  }
}

function onSearchAreas(search) {
  fetchAreas(search)
}
// Update property documents - دالة محدثة
const missingPropertyDocumentTypesPerProperty = ref({})

// دالة لتحديد أنواع المستندات المطلوبة لكل خاصية
function getMissingPropertyDocTypesForProperty(propIndex) {
  const missingKeys = effectiveMissingFields.value || []
  const missing = new Set()
  
  missingKeys.forEach(key => {
    // EOI
    if (key === `property_${propIndex}_document_eoi` || (propIndex === 0 && key === 'property_0_document_eoi')) {
      missing.add('eoi')
    }
    // Booking
    if (key === `property_${propIndex}_document_booking` || (propIndex === 0 && key === 'property_0_document_booking')) {
      missing.add('booking')
    }
    // SPA
    if (key === `property_${propIndex}_document_spa` || (propIndex === 0 && key === 'property_0_document_spa')) {
      missing.add('spa')
    }
    // Payment Proof
    if (key === `property_${propIndex}_document_payment_proof` || (propIndex === 0 && key === 'property_0_document_payment_proof')) {
      missing.add('payment_proof')
    }
    // MOU
    if (key === `property_${propIndex}_document_mou` || (propIndex === 0 && key === 'property_0_document_mou')) {
      missing.add('mou')
    }
    // NOC
    if (key === `property_${propIndex}_document_noc` || (propIndex === 0 && key === 'property_0_document_noc')) {
      missing.add('noc')
    }
  })

  const prop = localProperties.value[propIndex]
  if (prop) {
    if (propertyStoredDocArrayHasContent(prop.eoi_documents)) missing.delete('eoi')
    if (propertyStoredDocArrayHasContent(prop.booking_documents)) missing.delete('booking')
    if (propertyStoredDocArrayHasContent(prop.spa_document)) missing.delete('spa')
    if (propertyStoredDocArrayHasContent(prop.payment_proof)) missing.delete('payment_proof')
    if (propertyStoredDocArrayHasContent(prop.mou_documents)) missing.delete('mou')
    if (propertyStoredDocArrayHasContent(prop.noc_documents)) missing.delete('noc')
  }
  
  return Array.from(missing)
}
// في دالة updatePropertyDocuments
const updatePropertyDocuments = async  (propIndex, newDocuments) => {
    console.log(`Updating documents for property ${propIndex}:`, newDocuments)
    
    // ✅ تحديث propertyDocumentsCombined
    const updatedCombined = { ...propertyDocumentsCombined.value }
    updatedCombined[propIndex] = newDocuments
    propertyDocumentsCombined.value = updatedCombined
    
    // ✅ تحديث localProperties
    if (localProperties.value[propIndex]) {
        // تجميع المستندات حسب النوع
        const eoiDocs = newDocuments.filter(doc => 
            doc.document_type === 'eoi' || 
            doc.document_type === 'eoi_document' ||
            (doc.original_name && doc.original_name.toLowerCase().includes('eoi'))
        )
        
        const bookingDocs = newDocuments.filter(doc => 
            doc.document_type === 'booking' || 
            doc.document_type === 'booking_document' ||
            (doc.original_name && doc.original_name.toLowerCase().includes('booking'))
        )
        
        const paymentDocs = newDocuments.filter(doc => 
            doc.document_type === 'payment_proof' || 
            doc.document_type === 'payment' ||
            (doc.original_name && doc.original_name.toLowerCase().includes('payment'))
        )
        
        const spaDocs = newDocuments.filter(doc =>
            doc.document_type === 'spa' ||
            doc.document_type === 'spa_document' ||
            (doc.original_name && doc.original_name.toLowerCase().includes('spa'))
        )

        const mouDocs = newDocuments.filter(doc =>
            doc.document_type === 'mou' ||
            doc.document_type === 'mou_document' ||
            (doc.original_name && doc.original_name.toLowerCase().includes('mou'))
        )

        const nocDocs = newDocuments.filter(doc =>
            doc.document_type === 'noc' ||
            doc.document_type === 'noc_document' ||
            (doc.original_name && doc.original_name.toLowerCase().includes('noc'))
        )

        // تحديث المصفوفات
        localProperties.value[propIndex].eoi_documents = eoiDocs
        localProperties.value[propIndex].booking_documents = bookingDocs
        localProperties.value[propIndex].payment_proof = paymentDocs
        localProperties.value[propIndex].spa_document = spaDocs
        localProperties.value[propIndex].mou_documents = mouDocs
        localProperties.value[propIndex].noc_documents = nocDocs
        
        console.log('Updated localProperties:', {
            eoi_documents: localProperties.value[propIndex].eoi_documents?.length,
            booking_documents: localProperties.value[propIndex].booking_documents?.length,
            payment_proof: localProperties.value[propIndex].payment_proof?.length,
            spa_document: localProperties.value[propIndex].spa_document?.length
        })
    }
    
    // ✅ تحديث formData
    if (!formData.value.properties) {
        formData.value.properties = []
    }
    if (formData.value.properties[propIndex]) {
        formData.value.properties[propIndex] = { ...localProperties.value[propIndex] }
    }
    
    // ✅ force re-evaluate unresolvedMissingKeys
    await nextTick()
}
const onPropertyAreaSelected = (areaId, propIndex) => {
    const property = localProperties.value[propIndex]
    if (!property) return
    
    const selectedArea = areas.value.find(a => a.id === areaId)
    
    if (!selectedArea) return
    
    // تحديث المنطقة
    updateProperty(propIndex, 'area_id', areaId)
    
    // تعيين المطور تلقائياً إذا كان موجوداً في بيانات المنطقة
    let developerId = null
    
    if (selectedArea.project?.developer_id) {
        developerId = selectedArea.project.developer_id
    } else if (selectedArea.developer_id) {
        developerId = selectedArea.developer_id
    }
    
    if (developerId) {
        updateProperty(propIndex, 'developer_id', developerId)
    }
}



// Re-initialize property documents - للاستدعاء عند تغيير البيانات
// Re-initialize property documents
function reinitializePropertyDocuments() {
    if (!localProperties.value.length) return
    
    const newPropertyDocumentsCombined = {}
    
    localProperties.value.forEach((property, idx) => {
        const propertyDocs = normalizeStoredDocs(property.documents)
        const paymentFromPropertyDocs = propertyDocs.filter((d) => {
          const t = String(d?.document_type || d?.type || '').toLowerCase()
          return t.includes('payment') || t.includes('proof')
        })
        const spaFromPropertyDocs = propertyDocs.filter((d) => {
          const t = String(d?.document_type || d?.type || '').toLowerCase()
          return t.includes('spa')
        })
        const eoiFromPropertyDocs = propertyDocs.filter((d) => {
          const t = String(d?.document_type || d?.type || '').toLowerCase()
          return t.includes('eoi')
        })
        const bookingFromPropertyDocs = propertyDocs.filter((d) => {
          const t = String(d?.document_type || d?.type || '').toLowerCase()
          return t === 'booking' || t.includes('booking')
        })
        const mouFromPropertyDocs = propertyDocs.filter((d) => {
          const t = String(d?.document_type || d?.type || '').toLowerCase()
          return t === 'mou' || t.includes('mou')
        })
        const nocFromPropertyDocs = propertyDocs.filter((d) => {
          const t = String(d?.document_type || d?.type || '').toLowerCase()
          return t === 'noc' || t.includes('noc')
        })

        const paymentProof = normalizeStoredDocs(
          property.payment_proof ||
          property.payment_proofs ||
          property.payment_proof_raw ||
          paymentFromPropertyDocs
        )
        const spaDocument = normalizeStoredDocs(
          property.spa_document ||
          property.spa_documents ||
          property.spa_document_raw ||
          property.spa ||
          spaFromPropertyDocs
        )
        const eoiDocument = normalizeStoredDocs(
          property.eoi_documents ||
          property.eoi_document ||
          property.eoi ||
          property.eoi_documents_raw ||
          eoiFromPropertyDocs
        )
        const bookingDocument = normalizeStoredDocs(
          property.booking_documents ||
          property.booking_document ||
          property.booking_documents_raw ||
          bookingFromPropertyDocs
        )
        const mouDocument = normalizeStoredDocs(
          property.mou_documents ||
          property.mou_document ||
          property.mou ||
          property.mou_documents_raw ||
          mouFromPropertyDocs
        )
        const nocDocument = normalizeStoredDocs(
          property.noc_documents ||
          property.noc_document ||
          property.noc ||
          property.noc_documents_raw ||
          nocFromPropertyDocs
        )
        
        let docs = []
        
        // معالجة payment_proof
        if (Array.isArray(paymentProof)) {
            paymentProof.forEach(doc => {
                docs.push({
                    ...doc,
                    document_type: 'payment_proof',
                    url: doc.url || doc.path || null,
                    file: doc.file || null,
                    name: doc.original_name || doc.name || 'Payment Proof',
                    uploaded: true,
                    existing: true
                })
            })
        }
        
        // معالجة spa_document
        if (Array.isArray(spaDocument)) {
            spaDocument.forEach(doc => {
                docs.push({
                    ...doc,
                    document_type: 'spa',
                    url: doc.url || doc.path || null,
                    file: doc.file || null,
                    name: doc.original_name || doc.name || 'SPA Document',
                    uploaded: true,
                    existing: true
                })
            })
        }

        if (Array.isArray(eoiDocument)) {
            eoiDocument.forEach((doc) => {
                docs.push({
                    ...doc,
                    document_type: 'eoi',
                    url: doc.url || doc.path || null,
                    file: doc.file || null,
                    name: doc.original_name || doc.name || 'EOI',
                    uploaded: true,
                    existing: true,
                })
            })
        }

        if (Array.isArray(bookingDocument)) {
            bookingDocument.forEach((doc) => {
                docs.push({
                    ...doc,
                    document_type: 'booking',
                    url: doc.url || doc.path || null,
                    file: doc.file || null,
                    name: doc.original_name || doc.name || 'Booking Form',
                    uploaded: true,
                    existing: true,
                })
            })
        }

        if (Array.isArray(mouDocument)) {
            mouDocument.forEach((doc) => {
                docs.push({
                    ...doc,
                    document_type: 'mou',
                    url: doc.url || doc.path || null,
                    file: doc.file || null,
                    name: doc.original_name || doc.name || 'MOU Document',
                    uploaded: true,
                    existing: true,
                })
            })
        }

        if (Array.isArray(nocDocument)) {
            nocDocument.forEach((doc) => {
                docs.push({
                    ...doc,
                    document_type: 'noc',
                    url: doc.url || doc.path || null,
                    file: doc.file || null,
                    name: doc.original_name || doc.name || 'NOC Document',
                    uploaded: true,
                    existing: true,
                })
            })
        }

        newPropertyDocumentsCombined[idx] = docs
    })
    
    // استبدال الكائن بالكامل لضمان reactivity
    propertyDocumentsCombined.value = newPropertyDocumentsCombined
}
// Force refresh property documents - للاستدعاء بعد تحميل البيانات
function forceRefreshPropertyDocuments() {
    setTimeout(() => {
        reinitializePropertyDocuments()
    }, 100)
}
// Initialize form
// دالة لتنسيق التاريخ إلى YYYY-MM-DD
function formatDateForPicker(dateValue) {
  if (!dateValue) return null;
  
  try {
    // إذا كان بالفعل بصيغة YYYY-MM-DD
    if (typeof dateValue === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(dateValue)) {
      return dateValue;
    }
    
    // محاولة تحويل التاريخ
    const date = new Date(dateValue);
    if (isNaN(date.getTime())) return null;
    
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    
    return `${year}-${month}-${day}`;
  } catch (error) {
    console.warn('Error formatting date:', dateValue, error);
    return null;
  }
}
async function initializeForm() {
  loading.value = true
  try {
    const missingFieldKeys = effectiveMissingFields.value || []
    console.log('Initializing form with missing fields:', missingFieldKeys)
    
    const initial = {}
    let deal = props.deal
    // Always use fresh full deal data (with documents/properties) to avoid
    // false "required again" on already uploaded property docs.
    if (deal?.id) {
      try {
        const resp = await api.get(`/deals/${deal.id}`)
        deal = resp?.data?.data || deal
      } catch (e) {
        console.warn('Could not refresh full deal data, using passed deal:', e)
      }
    }
    currentDealData.value = deal
    
    if (deal) {
      Object.keys(deal).forEach(key => {
        if (deal[key] !== null && deal[key] !== undefined && 
            !key.includes('parties') && !key.includes('documents') &&
            typeof deal[key] !== 'object') {
          initial[key] = deal[key]
        }
      })
      
      const partyTypes = ['buyer', 'seller', 'tenant', 'landlord']
      partyTypes.forEach(partyType => {
        const party = deal.parties?.find(p => p.party_type === partyType)
        if (party) {
          const partyFields = ['first_name', 'last_name', 'phone', 'email', 'nationality', 
                               'date_of_birth', 'residency_status', 'city', 'country', 'language']
          partyFields.forEach(field => {
            const key = `${partyType}_${field}`
            let value = party[field]
             if (party.date_of_birth) {
                initial[`${partyType}_dob`] = formatDateForPicker(party.date_of_birth);
              }
    
            if (value !== null && value !== undefined && value !== '') {
              initial[key] = value
            }
          })
        }
      })

      if ((initial.area_id === null || initial.area_id === undefined) && deal.area?.id) {
        initial.area_id = deal.area.id
      }
      if ((initial.subcommunity_id === null || initial.subcommunity_id === undefined) && deal.subcommunity?.id) {
        initial.subcommunity_id = deal.subcommunity.id
      }
    }
    
    missingFieldKeys.forEach(key => {
      if (!key.includes('_document_') && !key.includes('property_') && initial[key] === undefined) {
        initial[key] = getExistingFieldValue(key)
      }
    })
    
    formData.value = { ...initial }
    
    if (formData.value.deal_total_amount !== null && formData.value.deal_total_amount !== undefined && formData.value.deal_total_amount !== '') {
      const asStr = String(formData.value.deal_total_amount).replace(/,/g, '')
      const n = Number(asStr)
      if (Number.isFinite(n)) {
        formData.value.deal_total_amount = formatDealAmountThousands(asStr)
      }
    }
    
    // Initialize document arrays
    const parties = ['buyer', 'seller', 'tenant', 'landlord']
    parties.forEach(party => {
      const existingDocs = getExistingDocuments(party)
      formData.value[`${party}_documents`] = existingDocs.length > 0 ? existingDocs : []
    })
    
    // Initialize properties - FIXED: Handle property_0_field patterns
    const requiresProperties = missingFieldKeys.some(key => 
      key === 'at_least_one_property' || key.includes('property_')
    )

    console.log('Checking properties:', {
      requiresProperties,
      hasPropertiesFromDeal: props.deal?.properties?.length > 0,
      propertiesCount: props.deal?.properties?.length || 0
    })

    if (deal?.properties && deal.properties.length > 0) {
      localProperties.value = JSON.parse(JSON.stringify(deal.properties)).map((p) => ({
        ...p,
        developer_name: p?.developer_name || p?.developer_contact_name || '',
        developer_phone: p?.developer_phone || p?.developer_contact_phone || '',
      }))
      console.log('Loaded existing properties:', localProperties.value)
    } else if (requiresProperties) {
      console.log('Creating default property')
      localProperties.value = [{
        id: Date.now(),
        new:1,
        sort_order: 0,
        unit_no: '',
        property_type_id: null,
        bedrooms: null,
        unit_size: '',
        area_id: null,
        developer_id: null,
        developer_name: '',
        developer_phone: '',
        budget_from: null,
        budget_to: null,
        purchase_price: null,
        commission: null,
        payment_proof: [],
        spa_document: []
      }]
    }

    hydratePropertyDocsFromDealDocuments(deal)

    if (localProperties.value.length > 0) {
      formData.value.properties = [...localProperties.value]
    }
    
    // Initialize property documents
if (localProperties.value.length > 0) {
    reinitializePropertyDocuments()
}
const sections = ['buyer', 'seller', 'tenant', 'landlord', 'properties', 'financials']
sections.forEach(section => {
  // Only open sections that have unresolved missing fields
  openSections.value[section] = hasUnresolvedInSection(section)
})
    
  } catch (error) {
    console.error('Error initializing form:', error)
  } finally {
    loading.value = false
      setTimeout(() => {
        if (propertyTypesLoaded.value && developersLoaded.value && areasLoaded.value) {
            isLoadingPropertyData.value = false
        }
    }, 500)
  }
}

// Passport / Emirates ID (national_id) depend on residency; API still lists both keys — we filter in UI.
const RESIDENCY_PROOF_DOCUMENT_IDS = new Set(['passport', 'national_id'])

function normalizeResidencyValue(residencyStatus) {
  return String(residencyStatus ?? '')
    .trim()
    .toLowerCase()
    .replace(/-/g, '_')
}

function allowedResidencyProofDocuments(residencyStatus) {
  if (normalizeResidencyValue(residencyStatus) === 'non_resident') {
    return new Set(['passport'])
  }
  return new Set(['passport', 'national_id'])
}

function getRequiredDocumentsByResidency(residencyStatus) {
  return Array.from(allowedResidencyProofDocuments(residencyStatus))
}

/** Resident: Emirates ID + passport required; non-resident: passport required only (no Emirates ID slot). */
function residencyProofDocumentRequired(party, docId) {
  if (!RESIDENCY_PROOF_DOCUMENT_IDS.has(docId)) return true
  const raw = normalizeResidencyValue(formData.value?.[`${party}_residency_status`])
  if (raw === 'non_resident') return docId === 'passport'
  return true
}

function identificationRequirementModeForParty(party) {
  return normalizeResidencyValue(formData.value?.[`${party}_residency_status`]) === 'resident'
    ? 'all'
    : 'either'
}
const computedTotalAmount = computed(() => {
  let total = 0
  // استخدم localProperties.value مباشرة
  const properties = localProperties.value
  for (let i = 0; i < properties.length; i++) {
    const prop = properties[i]
    let price = prop.purchase_price
    if (price) {
      // تنظيف الرقم من الفواصل
      if (typeof price === 'string') {
        price = price.replace(/,/g, '')
      }
      const numPrice = Number(price)
      if (!isNaN(numPrice)) {
        total += numPrice
      }
    }
  }
  // إرجاع الرقم منسقاً للعرض
  return total > 0 ? formatDealAmountThousands(String(total)) : ''
})
watch(computedTotalAmount, (val) => {
  if (dealAmountManuallyEdited.value) return
  formData.value.deal_total_amount = val || null
})
// مراقبة التغييرات على purchase_price لكل خاصية
watch(
  () => localProperties.value.map(p => p.purchase_price),
  (newValues) => {
    // هذا يساعد في تحديث computedTotalAmount فوراً
    console.log('Purchase prices changed:', newValues)
  },
  { deep: true }
)
// Computed for document types
// Document types by party with deal type filtering
const documentTypesByParty = computed(() => {
  const result = { buyer: [], seller: [], tenant: [], landlord: [] }
  const pushDocKeys = (keyList) => {
    ;(keyList || []).forEach((key) => {
      if (typeof key !== 'string' || !key.includes('_document_')) return
      const [partyType, docType] = key.split('_document_')
      if (result[partyType] && !result[partyType].some((d) => d.id === docType)) {
        result[partyType].push({
          id: docType,
          name: docType.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase()),
          required: true,
        })
      }
    })
  }
  pushDocKeys(effectiveMissingFields.value || [])
  pushDocKeys(props.requiredFields || [])
  pushDocKeys(Array.from(accumulatedRequiredKeys.value || []))

  // ✅ Security Deposit appears for buyer + seller in SECONDARY deals from stage 2 (Security Deposit) onwards,
  // even if the backend hasn't surfaced it via missing_fields and even if `targetStageOrder` is missing.
  // OPTIONAL — shown in UI but not required (does not block submission).
  if (normalizedDealType.value === 'secondary') {
    const targetOrder = Number(props.targetStageOrder) || 0
    const targetStageName = String(props.targetStageName || '').toLowerCase()
    const shouldShowSecurityDeposit =
      targetOrder >= 2 ||
      targetStageName.includes('security') ||
      targetStageName.includes('deposit') ||
      targetStageName.includes('mou') ||
      targetStageName.includes('noc') ||
      targetStageName.includes('won') ||
      targetStageName.includes('spa')

    if (shouldShowSecurityDeposit) {
      ['buyer', 'seller'].forEach((party) => {
        const existing = result[party].find((d) => d.id === 'security_deposit')
        if (existing) {
          // Force-optional even if a residency/missing-key path inserted it as required earlier.
          existing.required = false
        } else {
          result[party].push({
            id: 'security_deposit',
            name: 'Security Deposit',
            required: false,
          })
        }
      })
    }
  }

  // إضافة مستندات بناءً على حالة الإقامة ولكن فقط للأطراف المسموح بها
  const parties = ['buyer', 'seller', 'tenant', 'landlord']
  parties.forEach(party => {
   
    if (!isPartyAllowed(party)) return; // تخطى إذا كان الطرف غير مسموح به

    const residencyStatus = formData.value?.[`${party}_residency_status`]
    const requiredDocs = getRequiredDocumentsByResidency(residencyStatus)
    
    requiredDocs.forEach(docType => {
      if (!result[party].some(doc => doc.id === docType)) {
        result[party].push({
          id: docType,
          name: docType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
          required: residencyProofDocumentRequired(party, docType),
        })
      }
    })
  })

  // Keep showing document slots that are already filled (e.g. EOI done, booking still required).
  parties.forEach((party) => {
    if (!isPartyAllowed(party)) return
    const existing = formData.value?.[`${party}_documents`]
    if (!Array.isArray(existing)) return
    const seen = new Set((result[party] || []).map((d) => d.id))
    existing.forEach((doc) => {
      const id = doc?.document_type
      if (!id || typeof id !== 'string' || seen.has(id)) return
      const hasFile =
        isPendingUploadFile(doc.file) ||
        !!(doc.url || doc.file_url || doc.path || doc.file_name || doc.name)
      if (!hasFile) return
      result[party].push({
        id,
        name: id.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase()),
        required: false,
      })
      seen.add(id)
    })
  })

  parties.forEach((party) => {
    if (!isPartyAllowed(party)) return
    const allowed = allowedResidencyProofDocuments(formData.value?.[`${party}_residency_status`])
    result[party] = result[party]
      .filter((doc) => {
        if (!RESIDENCY_PROOF_DOCUMENT_IDS.has(doc.id)) return true
        return allowed.has(doc.id)
      })
      .map((doc) => {
        if (!RESIDENCY_PROOF_DOCUMENT_IDS.has(doc.id)) return doc
        return { ...doc, required: residencyProofDocumentRequired(party, doc.id) }
      })
  })

  return result
})

// أضف هذه الدالة المساعدة
function isPartyAllowed(party) {
    const dealType = normalizedDealType.value
    if (dealType === 'primary') {
        return party === 'buyer' // فقط buyer مسموح به
    }
    if (dealType === 'secondary') {
        return party === 'buyer' || party === 'seller'
    }
    if (dealType === 'rental') {
        return party === 'tenant' || party === 'landlord'
    }
    return false
}
const missingDocumentTypesByParty = computed(() => {
  const result = { buyer: [], seller: [], tenant: [], landlord: [] }
  const missingKeys = effectiveMissingFields.value || []

  missingKeys.forEach(key => {
    if (key.includes('_document_')) {
      const [partyType, docType] = key.split('_document_')
      if (result[partyType]) {
        result[partyType].push(docType)
      }
    }
  })

  // Security Deposit is OPTIONAL for secondary at stage 2+ — visible but not required.

  const parties = ['buyer', 'seller', 'tenant', 'landlord']
  parties.forEach((party) => {
    const allowed = allowedResidencyProofDocuments(formData.value?.[`${party}_residency_status`])
    result[party] = result[party].filter((docType) => {
      if (!RESIDENCY_PROOF_DOCUMENT_IDS.has(docType)) return true
      if (!allowed.has(docType)) return false
      return residencyProofDocumentRequired(party, docType)
    })
  })

  return result
})

const RESIDENCY_DOC_PARTIES = ['buyer', 'seller', 'tenant', 'landlord']
watch(
  () => RESIDENCY_DOC_PARTIES.map((p) => formData.value?.[`${p}_residency_status`]),
  () => {
    RESIDENCY_DOC_PARTIES.forEach((party) => {
      if (normalizeResidencyValue(formData.value?.[`${party}_residency_status`]) !== 'non_resident') {
        return
      }
      const k = `${party}_documents`
      const docs = formData.value[k]
      if (!Array.isArray(docs) || !docs.length) return
      const next = docs.filter((d) => d?.document_type !== 'national_id')
      if (next.length !== docs.length) {
        formData.value[k] = next
      }
    })
  },
)

// Check if field is required (stage rules)
function hasField(fieldKey) {
  // استخدم missingFields الأصلي بدلاً من effectiveMissingFields
  const originalMissing = props.missingFields || []
  const byStageMissing = (props.groupedMissing?.by_stage || props.missingFieldsGroupedByStage?.stages || [])
    .flatMap(stage => stage?.fields || stage?.missing_fields || [])
  
  const allOriginalMissing = [...originalMissing, ...byStageMissing]
  
  return allOriginalMissing.some(key => 
    normalizeMissingFieldKey(key) === normalizeMissingFieldKey(fieldKey)
  )
}
function isRequiredField(fieldKey) {
  const normalized = normalizeMissingFieldKey(fieldKey)
  if (accumulatedRequiredKeys.value.has(normalized)) return true
  if (Array.isArray(props.requiredFields) && props.requiredFields.length > 0) {
    if (props.requiredFields.some((key) => normalizeMissingFieldKey(String(key)) === normalized)) {
      return true
    }
  }
  /** When `required_fields` was missing from API, still show * using opening missing snapshot. */
  return hasField(fieldKey)
}
/** Show full party / deal forms (not only “missing” keys) so users can see and edit existing data. */
function shouldShowField(fieldKey) {
  if (!fieldKey) return false
  if (fieldKey === 'lost_reason') return hasField('lost_reason')
  if (hasField(fieldKey)) return true
  
  const dt = normalizedDealType.value

  if (fieldKey.startsWith('buyer_')) return dt === 'primary' || dt === 'secondary'
  if (fieldKey.startsWith('seller_')) return dt === 'secondary'
  if (fieldKey.startsWith('tenant_')) return dt === 'rental'
  if (fieldKey.startsWith('landlord_')) return dt === 'rental'
  if (['deal_commission', 'deal_total_amount'].includes(fieldKey)) return true
  return false
}

function showPartyDetailFields(partyType) {
  const dt = normalizedDealType.value
  if (partyType === 'buyer') return dt === 'primary' || dt === 'secondary'
  if (partyType === 'seller') return dt === 'secondary'
  if (partyType === 'tenant') return dt === 'rental'
  if (partyType === 'landlord') return dt === 'rental'
  return false
}

function shouldShowPropertyField(fieldName, property) {
  const isNewProperty = property && property.new; 
  const dt = normalizedDealType.value
  const targetOrder = props.targetStageOrder || 0
  switch (fieldName) {
    case 'unit_no':
    case 'property_type_id':
    case 'area_id':
      return true
      
    case 'unit_size':
      // عرض الحقل للخاصية الجديدة في الـ primary deal حتى لو كان فارغاً
      if (isNewProperty && dt === 'primary') return true
      return isPropertyFieldRequired(fieldName) || !!property?.[fieldName] || dt !== 'primary'
      
    case 'bedrooms':
      // عرض الحقل للخاصية الجديدة إذا كانت تظهر bedrooms بشكل عام
      if (isNewProperty) return true
      return showBedroomsForProperty(property)
      
    case 'developer_id':
    case 'developer_name':
    case 'developer_phone':
      if (dt === 'secondary') return true
      if (isNewProperty && (dt === 'primary' || dt === 'secondary')) return true
      return isPropertyFieldRequired(fieldName) || !!property?.[fieldName] || !!property?.developer_name || !!property?.developer_phone || !!property?.developer_id
      
    case 'rental_price':
      return dt === 'rental'
      
    case 'purchase_price':
      if (dt !== 'primary' && dt !== 'secondary') return false
      if (targetOrder >= 3) return true
      return showPurchasePrice.value
      
    default:
      return isPropertyFieldRequired(fieldName)
  }
}

function isBudgetField(fieldKey) {
  if (typeof fieldKey !== 'string') return false
  const normalized = fieldKey.toLowerCase()
  return normalized.includes('budget_from') || normalized.includes('budget_to')
}
function getPropertyBudgetDisplay(property) {
  const from = property?.budget_from
  const to = property?.budget_to
  if (!from && !to) return 'Select budget range'
  if (from && to) return `${from} - ${to}`
  if (from) return `From ${from}`
  return `To ${to}`
}

function togglePropertyBudgetDropdown(propIndex) {
  openBudgetDropdownIndex.value = openBudgetDropdownIndex.value === propIndex ? null : propIndex
}

const PROPERTY_MODAL_DOC_SPECS = [
  { id: 'eoi', name: 'EOI Document', missingFragments: ['document_eoi', 'eoi_document'], localKey: 'eoi_documents' },
  { id: 'booking', name: 'Booking Form', missingFragments: ['document_booking', 'booking_document'], localKey: 'booking_documents' },
  { id: 'noc', name: 'NOC Document', missingFragments: ['document_noc', 'noc_document'], localKey: 'noc_documents' },
  { id: 'mou', name: 'MOU Document', missingFragments: ['document_mou', 'mou_document'], localKey: 'mou_documents' },
  { id: 'spa', name: 'SPA Document', missingFragments: ['document_spa', 'spa_document'], localKey: 'spa_document' },
  { id: 'payment_proof', name: 'Payment Proof', missingFragments: ['document_payment', 'payment_proof'], localKey: 'payment_proof' },
]

/**
 * Optional doc ids to surface (always visible, never required) based on deal type + target stage order.
 * Lets users upload secondary's MOU (stage 3) / NOC (stage 4) even though the backend doesn't list them as missing.
 * Cumulative: MOU stays visible from stage 3 onwards; NOC from stage 4 onwards.
 * Stage-name fallback covers cases where targetStageOrder isn't propagated correctly.
 */
function alwaysVisibleDocIdsForSecondary() {
  const dt = normalizedDealType.value
  if (dt !== 'secondary') return new Set()
  const targetOrder = Number(props.targetStageOrder) || 0
  const targetStageName = String(props.targetStageName || '').toLowerCase()

  // Stages where MOU should still appear (MOU + all later stages).
  const isAtOrAfterMou =
    targetOrder >= 3 ||
    targetStageName.includes('mou') ||
    targetStageName.includes('noc') ||
    targetStageName.includes('won') ||
    targetStageName.includes('spa')

  // Stages where NOC should appear (NOC + all later stages).
  const isAtOrAfterNoc =
    targetOrder >= 4 ||
    targetStageName.includes('noc') ||
    targetStageName.includes('won')

  // Stages where SPA appears (Won/SPA).
  const isAtOrAfterSpa =
    targetOrder >= 5 ||
    targetStageName.includes('won') ||
    targetStageName.includes('spa')

  const ids = new Set(['payment_proof'])
  if (isAtOrAfterMou) ids.add('mou')
  if (isAtOrAfterNoc) ids.add('noc')
  if (isAtOrAfterSpa) ids.add('spa')
  return ids
}

const propertyDocTypesForModal = computed(() => {
  const missingKeys = effectiveMissingFields.value || []
  const propsList = localProperties.value || []

  const missingMatches = (fragments) =>
    missingKeys.some((k) => {
      const s = String(k)
      return fragments.some((frag) => s.includes(frag))
    })

  const anyPropertyHas = (localKey) =>
    propsList.some((p) => propertyStoredDocArrayHasContent(p?.[localKey]))

  const alwaysVisible = alwaysVisibleDocIdsForSecondary()

  return PROPERTY_MODAL_DOC_SPECS.filter(
    (spec) => missingMatches(spec.missingFragments) || anyPropertyHas(spec.localKey) || alwaysVisible.has(spec.id)
  ).map((spec) => ({
    id: spec.id,
    name: spec.name,
    required: missingMatches(spec.missingFragments),
  }))
})
const isPropertyDocumentsSectionRequired = computed(() =>
  propertyDocTypesForModal.value.some((d) => d.required)
)

function hasPartyFields(partyType) {
  const missingKeys = effectiveMissingFields.value || []
  const prefixes = [`${partyType}_`, `${partyType}_document_`]
  const hasAny = missingKeys.some(key => prefixes.some(prefix => key.startsWith(prefix)))
  console.log(`hasPartyFields(${partyType}):`, hasAny, 'missingKeys:', missingKeys)
  return hasAny
}

function hasFinancialFields() {
  const missingKeys = effectiveMissingFields.value || []
  return missingKeys.some(key => ['deal_commission', 'deal_total_amount'].includes(key))
}

const shouldShowFinancialSection = computed(() => {
  // Only show in Won stage
  const targetStageNameLower = (props.targetStageName || '').toLowerCase()
  const isWonStage = targetStageNameLower.includes('won') || 
                      targetStageNameLower.includes('closed') ||
                      targetStageNameLower.includes('completed')
  
  if (!isWonStage) return false
  
  return ['deal_commission', 'deal_total_amount'].some((k) => shouldShowField(k))
})
const isWonStage = computed(() => {
  const targetStageNameLower = (props.targetStageName || '').toLowerCase()
  return targetStageNameLower.includes('won') || 
         targetStageNameLower.includes('closed') ||
         targetStageNameLower.includes('completed')
})
const hasPropertyRequirements = computed(() => {
  const missingKeys = effectiveMissingFields.value || []
  return missingKeys.some(key => key.includes('property_') || key === 'at_least_one_property')
})

// Field invalid check (only after Save)
function isFieldInvalid(fieldKey) {
  if (!fieldKey || !validationAttempted.value) return false
  
  const isRequired = hasField(fieldKey)
  if (!isRequired) return false
  
  const value = formData.value?.[fieldKey]
  const isEmpty = value === null || value === undefined || value === '' || 
                  (typeof value === 'string' && value.trim() === '')
  return isEmpty
}

// Effective missing fields

// Effective missing fields - مع فلترة صارمة حسب نوع الصفقة
function normalizeMissingFieldKey(field) {
  if (typeof field !== 'string') return field

  const normalized = field.trim().toLowerCase()
  const partyPrefixes = ['buyer', 'seller', 'tenant', 'landlord']

  for (const party of partyPrefixes) {
    if (normalized === `${party}_date_of_birth`) return `${party}_dob`
    if (normalized === `${party}_country_of_residence`) return `${party}_country`
    if (normalized === `${party}_city_of_residence`) return `${party}_city`
  }

  return normalizePropertyFieldKey(normalized)
}

function normalizePropertyFieldKey(field) {
  if (typeof field !== 'string') return field
  const normalized = field.trim().toLowerCase()

  const propertyPrefixMatch = normalized.match(/^property_(\d+_)?(.+)$/)
  if (propertyPrefixMatch) {
    const indexPrefix = propertyPrefixMatch[1] || ''
    const fieldName = propertyPrefixMatch[2]
    if (fieldName === 'developer_sales_phone') {
      return `property_${indexPrefix}developer_phone`
    }
    if (fieldName === 'developer_sales_person_phone') {
      return `property_${indexPrefix}developer_phone`
    }
    if (fieldName === 'developer_sales_person_name') {
      return `property_${indexPrefix}developer_name`
    }
    return normalized
  }

  if (normalized === 'developer_sales_phone') {
    return 'developer_phone'
  }
  if (normalized === 'developer_sales_person_phone') {
    return 'developer_phone'
  }
  if (normalized === 'developer_sales_person_name') {
    return 'developer_name'
  }

  return normalized
}

const effectiveMissingFields = computed(() => {
  const direct = Array.isArray(props.missingFields) ? props.missingFields : []
  const byStage = props.groupedMissing?.by_stage || props.missingFieldsGroupedByStage?.stages || []
  console.log("direct",direct);
  console.log("byStage",byStage);
  let allFields = []
  
  if (!Array.isArray(byStage) || byStage.length === 0) {
    allFields = [...direct]
  } else {
    const targetStageName = String(props.targetStageName || '').toLowerCase().trim()
    const targetStageNumber = Number((targetStageName.match(/\d+/) || [])[0] || NaN)
    
    const cumulative = new Set()
    byStage.forEach((stage) => {
      const stageNumber = Number((String(stage?.stage_name || '').match(/\d+/) || [])[0] || 0)
      
      let shouldInclude = false
      if (targetStageNumber > 0 && stageNumber > 0) {
        shouldInclude = stageNumber <= targetStageNumber
      } else {
        shouldInclude = cumulative.size === 0
      }
      
      if (shouldInclude) {
        const fields = stage?.fields || stage?.missing_fields || []
        fields.forEach(field => {
          if (typeof field === 'string') {
            cumulative.add(normalizeMissingFieldKey(field))
          }
        })
      }
    })
    
    direct.forEach(key => cumulative.add(normalizeMissingFieldKey(key)))
    allFields = Array.from(cumulative)
  }
  if (!Array.isArray(byStage) || byStage.length === 0) {
    allFields = allFields.map(normalizeMissingFieldKey)
  }
  
  // ✅ فلترة صارمة حسب نوع الصفقة
  const dealType = normalizedDealType.value
  const currentStageName = String(props.targetStageName || '').toLowerCase().trim()
  const targetStageNumber = Number((currentStageName.match(/\d+/) || [])[0] || NaN)
  
  const filteredFields = allFields.filter(field => {
    if (typeof field !== 'string') return true
    
    if (dealType === 'primary') {
      // إخفاء Seller, Tenant, Landlord
      if (field.startsWith('seller_') || field.includes('seller_document_')) return false
      if (field.startsWith('tenant_') || field.includes('tenant_document_')) return false
      if (field.startsWith('landlord_') || field.includes('landlord_document_')) return false
      return true
    }

    if (dealType === 'secondary') {
      // إخفاء Tenant, Landlord
      if (field.startsWith('tenant_') || field.includes('tenant_document_')) return false
      if (field.startsWith('landlord_') || field.includes('landlord_document_')) return false
      return true
    }

    if (dealType === 'rental') {
      // إخفاء Buyer, Seller
      if (field.startsWith('buyer_') || field.includes('buyer_document_')) return false
      if (field.startsWith('seller_') || field.includes('seller_document_')) return false
      return true
    }
    
    return true
  })
   // ADD THIS: Filter out bedrooms missing fields for land/plot properties
  const finalFilteredFields = filteredFields.filter(field => {
    // Check if this is a bedrooms missing field
    if (field.match(/property_\d+_bedrooms/) || field === 'property_bedrooms') {
      // Find the property index
      let propIndex = null
      let match = field.match(/property_(\d+)_bedrooms/)
      if (match) {
        propIndex = parseInt(match[1])
      }
      
      // If we have properties data, check if this property is land/plot
      if (propIndex !== null && localProperties.value[propIndex]) {
        const property = localProperties.value[propIndex]
        const propertyTypeId = property?.property_type_id
        if (propertyTypeId) {
          const selectedType = propertyTypes.value.find(t => t.id === propertyTypeId)
          const typeName = selectedType?.name?.toLowerCase() || ''
          if (typeName.includes('land') || typeName.includes('plot')) {
            return false // Remove bedrooms from missing fields for land/plot
          }
        }
      }
    }
    return true
  })
  const shouldHideBudget = !isBudgetVisibleForPrimaryDeal()
  const finalWithBudgetFilter = finalFilteredFields.filter(field => {
    if (shouldHideBudget && isBudgetField(field)) {
      return false
    }
    return true
  })

  // ✅ Security Deposit is OPTIONAL — strip any *_document_security_deposit keys
  // so the badge count, submit blocker, and required indicators all ignore it.
  const withoutSecurityDeposit = finalWithBudgetFilter.filter(
    (field) => !/^(buyer|seller)_document_security_deposit$/.test(field)
  )

  return withoutSecurityDeposit
})

// Unresolved missing keys for submit button
// Unresolved missing keys for submit button
const unresolvedMissingKeys = computed(() => {
  const unresolved = []
  const missingKeys = effectiveMissingFields.value || []
  
  missingKeys.forEach(key => {
if (key.startsWith('property_document_')) {
  const rawDocType = key.replace('property_document_', '')
  
  // ✅ توحيد أسماء أنواع المستندات
  let normalizedDocType = rawDocType
  if (rawDocType === 'spa') {
    normalizedDocType = 'spa_document'
  } else if (rawDocType === 'payment') {
    normalizedDocType = 'payment_proof'
  } else if (rawDocType === 'booking') {
    normalizedDocType = 'booking_document' // ✅ إضافة معالجة booking
  } else if (rawDocType === 'eoi') {
    normalizedDocType = 'eoi_document' // ✅ إضافة معالجة eoi
  } else if (rawDocType === 'mou') {
    normalizedDocType = 'mou_document'
  } else if (rawDocType === 'noc') {
    normalizedDocType = 'noc_document'
  }

  // ✅ التحقق من المستندات في localProperties
  let hasPropertyDoc = false

  // التحقق من propertyDocumentsCombined (المستندات الجديدة)
  hasPropertyDoc = localProperties.value.some((property, propIndex) => {
    const docs = propertyDocumentsCombined.value?.[propIndex] || []
    if (Array.isArray(docs) && docs.some(doc => {
      const docType = doc?.document_type || ''
      // ✅ مقارنة مع normalizedDocType و rawDocType
      const hasFileOrUrl = !!(doc?.file || doc?.url)
      return hasFileOrUrl && (
        docType === normalizedDocType ||
        docType === rawDocType ||
        docType === normalizedDocType.replace('_document', '') ||
        (rawDocType === 'booking' && (docType === 'booking' || docType === 'booking_document')) ||
        (rawDocType === 'eoi' && (docType === 'eoi' || docType === 'eoi_document')) ||
        (rawDocType === 'mou' && (docType === 'mou' || docType === 'mou_document')) ||
        (rawDocType === 'noc' && (docType === 'noc' || docType === 'noc_document'))
      )
    })) {
      return true
    }
    return false
  })
  
  // ✅ التحقق من المستندات الموجودة في property نفسها
  if (!hasPropertyDoc) {
    hasPropertyDoc = localProperties.value.some((property) => {
      // تحديد أي مصفوفة مستندات نبحث فيها
      let existingDocs = null
      if (normalizedDocType === 'booking_document' || rawDocType === 'booking') {
        existingDocs = property.booking_documents
      } else if (normalizedDocType === 'eoi_document' || rawDocType === 'eoi') {
        existingDocs = property.eoi_documents
      } else if (normalizedDocType === 'mou_document' || rawDocType === 'mou') {
        existingDocs = property.mou_documents
      } else if (normalizedDocType === 'noc_document' || rawDocType === 'noc') {
        existingDocs = property.noc_documents
      } else if (normalizedDocType === 'spa_document' || rawDocType === 'spa') {
        existingDocs = property.spa_document
      } else if (normalizedDocType === 'payment_proof' || rawDocType === 'payment') {
        existingDocs = property.payment_proof
      }
      
      if (existingDocs) {
        let existingDocsArray = existingDocs
        if (typeof existingDocsArray === 'string') {
          try {
            existingDocsArray = JSON.parse(existingDocsArray)
          } catch(e) {
            existingDocsArray = []
          }
        }
        if (Array.isArray(existingDocsArray) && existingDocsArray.some(doc => 
          !!(doc?.file || doc?.url || doc?.path || doc?.original_name)
        )) {
          return true
        }
        if (existingDocsArray && typeof existingDocsArray === 'object' && 
            !!(existingDocsArray.file || existingDocsArray.url || existingDocsArray.path)) {
          return true
        }
      }
      return false
    })
  }
  
  if (!hasPropertyDoc && !unresolved.includes(key)) {
    unresolved.push(key)
  }
  return
}

    // API / stage engine uses property_0_document_eoi (indexed), not only property_document_eoi
    const indexedPropDocMatch = key.match(/^property_(\d+)_document_(.+)$/)
    if (indexedPropDocMatch) {
      const propIndex = parseInt(indexedPropDocMatch[1], 10)
      const rawDocType = indexedPropDocMatch[2]

      let normalizedDocType = rawDocType
      if (rawDocType === 'spa') {
        normalizedDocType = 'spa_document'
      } else if (rawDocType === 'payment') {
        normalizedDocType = 'payment_proof'
      } else if (rawDocType === 'booking') {
        normalizedDocType = 'booking_document'
      } else if (rawDocType === 'eoi') {
        normalizedDocType = 'eoi_document'
      }

      let hasPropertyDoc = false
      const combinedDocs = propertyDocumentsCombined.value?.[propIndex] || []
      if (
        Array.isArray(combinedDocs) &&
        combinedDocs.some((doc) => {
          const docType = doc?.document_type || ''
          const hasFileOrUrl = !!(doc?.file || doc?.url)
          return (
            hasFileOrUrl &&
            (docType === normalizedDocType ||
              docType === rawDocType ||
              docType === normalizedDocType.replace('_document', '') ||
              (rawDocType === 'booking' &&
                (docType === 'booking' || docType === 'booking_document')) ||
              (rawDocType === 'eoi' && (docType === 'eoi' || docType === 'eoi_document')) ||
              (rawDocType === 'mou' && (docType === 'mou' || docType === 'mou_document')) ||
              (rawDocType === 'noc' && (docType === 'noc' || docType === 'noc_document')))
          )
        })
      ) {
        hasPropertyDoc = true
      }

      if (!hasPropertyDoc && localProperties.value[propIndex]) {
        const property = localProperties.value[propIndex]
        let existingDocs = null
        if (normalizedDocType === 'booking_document' || rawDocType === 'booking') {
          existingDocs = property.booking_documents
        } else if (normalizedDocType === 'eoi_document' || rawDocType === 'eoi') {
          existingDocs = property.eoi_documents
        } else if (normalizedDocType === 'spa_document' || rawDocType === 'spa') {
          existingDocs = property.spa_document
        } else if (
          normalizedDocType === 'payment_proof' ||
          rawDocType === 'payment' ||
          rawDocType === 'payment_proof'
        ) {
          existingDocs = property.payment_proof
        }

        if (existingDocs) {
          let existingDocsArray = existingDocs
          if (typeof existingDocsArray === 'string') {
            try {
              existingDocsArray = JSON.parse(existingDocsArray)
            } catch {
              existingDocsArray = []
            }
          }
          if (
            Array.isArray(existingDocsArray) &&
            existingDocsArray.some(
              (doc) => !!(doc?.file || doc?.url || doc?.path || doc?.original_name),
            )
          ) {
            hasPropertyDoc = true
          }
          if (
            existingDocsArray &&
            typeof existingDocsArray === 'object' &&
            !Array.isArray(existingDocsArray) &&
            !!(existingDocsArray.file || existingDocsArray.url || existingDocsArray.path)
          ) {
            hasPropertyDoc = true
          }
        }
      }

      if (!hasPropertyDoc && !unresolved.includes(key)) {
        unresolved.push(key)
      }
      return
    }

    if (key.includes('_document_')) {
      const [partyType, docType] = key.split('_document_')
      if (RESIDENCY_PROOF_DOCUMENT_IDS.has(docType)) {
        const allowed = allowedResidencyProofDocuments(formData.value?.[`${partyType}_residency_status`])
        if (!allowed.has(docType)) return
        if (!residencyProofDocumentRequired(partyType, docType)) return
      }
      const docs = formData.value?.[`${partyType}_documents`] || []
      const hasDoc = Array.isArray(docs) && docs.some(doc => 
        (doc?.file || doc?.url) && doc?.document_type === docType
      )
      if (!hasDoc && !unresolved.includes(key)) {
        unresolved.push(key)
      }
    } else if (key.includes('property_')) {
      // Handle property fields - check against localProperties
      const match = key.match(/property_(\d+)_(.+)/)
      if (match) {
        const propIndex = parseInt(match[1])
        const fieldName = normalizePropertyFieldKey(match[2])
        const property = localProperties.value[propIndex]
        if (property) {
          const value =
            fieldName === 'developer_phone'
              ? (property.developer_phone ?? property.developer_sales_phone)
              : property[fieldName]
          if (value === null || value === undefined || value === '') {
            if (!unresolved.includes(key)) {
              unresolved.push(key)
            }
          }
        } else {
          if (!unresolved.includes(key)) {
            unresolved.push(key)
          }
        }
      } else {
        if (!unresolved.includes(key)) {
          unresolved.push(key)
        }
      }
    } else if (key === 'at_least_one_property') {
      if (localProperties.value.length === 0 && !unresolved.includes(key)) {
        unresolved.push(key)
      }
    } else {
      const value = formData.value?.[key]
      const isEmpty = value === null || value === undefined || value === ''
      if (isEmpty && !unresolved.includes(key)) {
        unresolved.push(key)
      }
    }
  })
  
  console.log('=== unresolvedMissingKeys Debug ===')
  console.log('Unresolved keys:', unresolved)
  console.log('===================================')
  
  return unresolved
})

const canSubmit = computed(() => {
  return !loading.value && !submitting.value && unresolvedMissingKeys.value.length === 0
})

/** Scroll form from top: first visible invalid/missing control (top-to-bottom order). */
function scrollToFirstValidationError() {
  const modalEl = document.querySelector('.complete-fields-modal')
  if (!modalEl) return

  const scrollRoot = modalEl.querySelector('.form-scroll-area') || modalEl
  const unresolved = unresolvedMissingKeys.value || []

  let propertyDocBtn = null
  const firstMissingPropertyDoc =
    unresolved.find((k) => k.startsWith('property_document_')) ||
    unresolved.find((k) => /^property_\d+_document_/.test(k))
  if (firstMissingPropertyDoc) {
    const indexed = firstMissingPropertyDoc.match(/^property_\d+_document_(.+)$/)
    const docTypeRaw = indexed
      ? indexed[1]
      : firstMissingPropertyDoc.replace('property_document_', '')
    const docTypeLabel =
      docTypeRaw === 'spa'
        ? 'spa document'
        : docTypeRaw === 'payment_proof'
          ? 'payment proof'
          : docTypeRaw.replace(/_/g, ' ')
    const addButtons = Array.from(modalEl.querySelectorAll('.document-property-section-add'))
    propertyDocBtn =
      addButtons.find((btn) =>
        String(btn?.textContent || '').toLowerCase().includes(`add ${docTypeLabel}`.toLowerCase()),
      ) || modalEl.querySelector('.document-property-section-add--missing')
  }

  const selector = [
    '.document-property-section-add--missing',
    '.budget-field-wrap-stage.is-invalid-group',
    '.crm-phone-input.is-invalid',
    '.custom-v-select.is-invalid',
    '.vue-tel-input.is-invalid',
    'input.is-invalid',
    'textarea.is-invalid',
    'select.is-invalid',
    '.is-invalid',
  ].join(', ')

  const seen = new Set()
  const targets = []

  const pushIfVisible = (el) => {
    if (!el || seen.has(el)) return
    const style = window.getComputedStyle(el)
    if (style.display === 'none' || style.visibility === 'hidden') return
    const r = el.getBoundingClientRect()
    if (r.width < 1 && r.height < 1) return
    seen.add(el)
    targets.push(el)
  }

  scrollRoot.querySelectorAll(selector).forEach(pushIfVisible)
  if (propertyDocBtn) pushIfVisible(propertyDocBtn)

  if (targets.length === 0) return

  targets.sort((a, b) => {
    const ra = a.getBoundingClientRect()
    const rb = b.getBoundingClientRect()
    const dy = ra.top - rb.top
    if (Math.abs(dy) > 1) return dy
    return ra.left - rb.left
  })

  const target = targets[0]
  const rootRect = scrollRoot.getBoundingClientRect()
  const targetRect = target.getBoundingClientRect()
  const margin = 12
  const nextTop = scrollRoot.scrollTop + (targetRect.top - rootRect.top) - margin
  scrollRoot.scrollTo({ top: Math.max(0, nextTop), behavior: 'smooth' })

  try {
    if (target.matches?.('input, textarea, select, button') && !target.disabled) {
      target.focus({ preventScroll: true })
    } else {
      const focusable = target.querySelector?.(
        'input:not([type="hidden"]):not([disabled]), textarea:not([disabled]), select:not([disabled]), button:not([disabled]), .vs__search',
      )
      focusable?.focus?.({ preventScroll: true })
    }
  } catch {
    /* ignore focus errors */
  }
}

function isPendingUploadFile(value) {
  if (!value) return false
  if (typeof File !== 'undefined' && value instanceof File) return true
  if (typeof Blob !== 'undefined' && value instanceof Blob) return true
  return false
}

/** Entries already on the server (path/url/metadata). Pending browser Files are sent via multipart and must not be dropped here. */
function persistedPropertyDocMetadataList(docs) {
  if (!Array.isArray(docs)) return []
  return docs.filter((doc) => {
    if (!doc || typeof doc !== 'object') return false
    if (isPendingUploadFile(doc.file)) return false
    return true
  })
}

/** True if this property doc array already has a stored file or a pending upload. */
function propertyStoredDocArrayHasContent(arr) {
  if (!Array.isArray(arr) || !arr.length) return false
  return arr.some((doc) => {
    if (!doc || typeof doc !== 'object') return false
    if (isPendingUploadFile(doc.file)) return true
    return !!(
      doc.path ||
      doc.file_path ||
      doc.url ||
      doc.file_url ||
      doc.original_name ||
      doc.file_name ||
      doc.name
    )
  })
}

// Submit form — validate on click: scroll to first error, then save when complete
async function submitForm() {
  if (loading.value || submitting.value) return

  validationAttempted.value = true
  await nextTick()

  ;['buyer', 'seller', 'tenant', 'landlord', 'properties', 'financials'].forEach((section) => {
    if (hasUnresolvedInSection(section)) {
      openSections.value[section] = true
    }
  })
  await nextTick()

  if (unresolvedMissingKeys.value.length > 0) {
    await nextTick()
    requestAnimationFrame(() => {
      scrollToFirstValidationError()
    })
    return
  }
  console.log('Form Data before submit:', {
  formData: formData.value,
  properties: localProperties.value,
  documents: {
    buyer: formData.value.buyer_documents,
    seller: formData.value.seller_documents
  }
})

// وللتحقق من missing fields:
console.log('Missing fields:', effectiveMissingFields.value)
console.log('Unresolved keys:', unresolvedMissingKeys.value)

  submitting.value = true

  const payload = {}
  const documents = []
  const allowedPayloadKeys = new Set([
    'source',
    'deal_name',
    'deal_total_amount',
    'deal_commission',
    'property_link',
    'property_reference',
    'lost_reason',
    'listing_id',
    'buyer_first_name', 'buyer_last_name', 'buyer_phone', 'buyer_email', 'buyer_nationality', 'buyer_residency_status', 'buyer_city', 'buyer_country', 'buyer_dob', 'buyer_language',
    'seller_first_name', 'seller_last_name', 'seller_phone', 'seller_email', 'seller_nationality', 'seller_residency_status', 'seller_city', 'seller_country', 'seller_dob', 'seller_language',
    'tenant_first_name', 'tenant_last_name', 'tenant_phone', 'tenant_email', 'tenant_nationality', 'tenant_residency_status', 'tenant_city', 'tenant_country', 'tenant_language',
    'landlord_first_name', 'landlord_last_name', 'landlord_phone', 'landlord_email', 'landlord_nationality', 'landlord_residency_status', 'landlord_city', 'landlord_country', 'landlord_dob', 'landlord_language',
  ])
  
  // Collect regular fields
  Object.keys(formData.value).forEach(key => {
    if (!allowedPayloadKeys.has(key)) return
    if (!key.includes('_documents') && 
        formData.value[key] !== null && 
        formData.value[key] !== undefined && 
        formData.value[key] !== '') {
      if (key === 'deal_commission') {
        const val = Number(formData.value[key])
        if (Number.isFinite(val) && val >= 0 && val <= 999.99) {
          payload[key] = val
        }
        return
      }
      if (key === 'deal_total_amount') {
        const val = parseDealAmountNumeric(formData.value[key])
        if (Number.isFinite(val) && val >= 0 && val <= 9999999999999.99) {
          payload[key] = val
        }
        return
      }
      payload[key] = formData.value[key]
    }
  })
  
  // Collect documents
  const docRefs = [
    { ref: buyerDocUploadRef, party: 'buyer', key: 'buyer_documents' },
    { ref: sellerDocUploadRef, party: 'seller', key: 'seller_documents' },
    { ref: tenantDocUploadRef, party: 'tenant', key: 'tenant_documents' },
    { ref: landlordDocUploadRef, party: 'landlord', key: 'landlord_documents' }
  ]
  
  docRefs.forEach(({ party, key }) => {
    if (formData.value[key] && Array.isArray(formData.value[key])) {
      formData.value[key].forEach(doc => {
        if (isPendingUploadFile(doc.file)) {
          documents.push({
            file: doc.file,
            document_type: doc.document_type,
            category: party,
            party_type: party
          })
        }
      })
    }
  })
  
  // Collect property documents
  // Add properties to payload
  if (localProperties.value.length > 0) {
    payload.properties = localProperties.value.map((prop, index) => {
      // جمع الملفات الجديدة من eoi_documents
      const eoiFiles = []
      if (prop.eoi_documents && Array.isArray(prop.eoi_documents)) {
        prop.eoi_documents.forEach(doc => {
          if (isPendingUploadFile(doc.file)) {
            eoiFiles.push(doc.file)
          }
        })
      }
      
      // جمع الملفات الجديدة من booking_documents
      const bookingFiles = []
      if (prop.booking_documents && Array.isArray(prop.booking_documents)) {
        prop.booking_documents.forEach(doc => {
          if (isPendingUploadFile(doc.file)) {
            bookingFiles.push(doc.file)
          }
        })
      }
      
      // جمع الملفات الجديدة من payment_proof
      const paymentFiles = []
      if (prop.payment_proof && Array.isArray(prop.payment_proof)) {
        prop.payment_proof.forEach(doc => {
          if (isPendingUploadFile(doc.file)) {
            paymentFiles.push(doc.file)
          }
        })
      }
      
      // جمع الملفات الجديدة من spa_document
      const spaFiles = []
      if (prop.spa_document && Array.isArray(prop.spa_document)) {
        prop.spa_document.forEach(doc => {
          if (isPendingUploadFile(doc.file)) {
            spaFiles.push(doc.file)
          }
        })
      }

      // جمع الملفات الجديدة من mou_documents
      const mouFiles = []
      if (prop.mou_documents && Array.isArray(prop.mou_documents)) {
        prop.mou_documents.forEach(doc => {
          if (isPendingUploadFile(doc.file)) {
            mouFiles.push(doc.file)
          }
        })
      }

      // جمع الملفات الجديدة من noc_documents
      const nocFiles = []
      if (prop.noc_documents && Array.isArray(prop.noc_documents)) {
        prop.noc_documents.forEach(doc => {
          if (isPendingUploadFile(doc.file)) {
            nocFiles.push(doc.file)
          }
        })
      }

      return {
        sort_order: index,
        unit_no: prop.unit_no || '',
        property_type_id: prop.property_type_id || null,
        bedrooms: prop.bedrooms || null,
        unit_size: prop.unit_size || '',
        area_id: prop.area_id || null,
        developer_id: prop.developer_id || null,
        developer_name: typeof prop.developer_name === 'string' ? prop.developer_name.trim() : (prop.developer_name || ''),
        developer_phone: typeof prop.developer_phone === 'string' ? prop.developer_phone.trim() : (prop.developer_phone || ''),
        developer_sales_person_name: typeof prop.developer_name === 'string' ? prop.developer_name.trim() : (prop.developer_name || ''),
        developer_sales_person_phone: typeof (prop.developer_phone ?? prop.developer_sales_phone) === 'string'
          ? (prop.developer_phone ?? prop.developer_sales_phone).trim()
          : ((prop.developer_phone ?? prop.developer_sales_phone) || ''),
        developer_sales_phone: typeof (prop.developer_phone ?? prop.developer_sales_phone) === 'string'
          ? (prop.developer_phone ?? prop.developer_sales_phone).trim()
          : ((prop.developer_phone ?? prop.developer_sales_phone) || ''),
        budget_from: toNullableNumeric(prop.budget_from),
        budget_to: toNullableNumeric(prop.budget_to),
        purchase_price: toNullableNumeric(prop.purchase_price),
        commission: prop.commission || null,
        // Persisted metadata + new files (previously only raw Files were sent — wiped EOI/booking/payment/SPA on save)
        eoi_documents: [...persistedPropertyDocMetadataList(prop.eoi_documents), ...eoiFiles.map((f) => ({ file: f }))],
        booking_documents: [...persistedPropertyDocMetadataList(prop.booking_documents), ...bookingFiles.map((f) => ({ file: f }))],
        mou_documents: [...persistedPropertyDocMetadataList(prop.mou_documents), ...mouFiles.map((f) => ({ file: f }))],
        noc_documents: [...persistedPropertyDocMetadataList(prop.noc_documents), ...nocFiles.map((f) => ({ file: f }))],
        payment_proof: [...persistedPropertyDocMetadataList(prop.payment_proof), ...paymentFiles.map((f) => ({ file: f }))],
        spa_document: [...persistedPropertyDocMetadataList(prop.spa_document), ...spaFiles.map((f) => ({ file: f }))],
      }
    })
  }

  // Guard against oversized multipart uploads (server limit is 8MB).
  // Keep a safety margin for non-file form fields/boundary bytes.
  let totalUploadBytes = documents.reduce((sum, doc) => {
    const size = doc?.file?.size || 0
    return sum + size
  }, 0)
  if (payload.properties && Array.isArray(payload.properties)) {
    payload.properties.forEach((prop) => {
      ;['payment_proof', 'spa_document', 'eoi_documents', 'booking_documents', 'mou_documents', 'noc_documents'].forEach((k) => {
        const arr = prop[k]
        if (!Array.isArray(arr)) return
        arr.forEach((item) => {
          const f = item && typeof item === 'object' && 'size' in item && isPendingUploadFile(item) ? item : item?.file
          if (f && typeof f.size === 'number') totalUploadBytes += f.size
        })
      })
    })
  }
  const maxSafeUploadBytes = 10 * 1024 * 1024
  if (totalUploadBytes > maxSafeUploadBytes) {
    const mb = (totalUploadBytes / (1024 * 1024)).toFixed(2)
    const msg = `Uploaded files are too large (${mb} MB). Please reduce file size to under 10 MB total.`
    if (window?.$showNotification) {
      window.$showNotification(msg, 'warning')
    } else {
      alert(msg)
    }
    submitting.value = false
    return
  }
  


console.log('Properties type:', typeof payload.properties, payload.properties)
if (payload.properties && typeof payload.properties === 'string') {
    console.warn('Properties is string, trying to parse')
    try {
        payload.properties = JSON.parse(payload.properties)
    } catch(e) {
        console.error('Failed to parse properties', e)
    }
}
const finalPayload = {
    ...payload,
    properties: payload.properties // تأكد من أنه array
}
  console.log('Final payload:', finalPayload)

  emit('save', { payload: finalPayload, documents, stage_id: props.targetStageId })
  // Keep modal locked briefly to avoid accidental close while parent save request is in flight.
  submitResetTimer = setTimeout(() => {
    submitting.value = false
    submitResetTimer = null
  }, 5000)
}

// Close modal
function closeModal() {
  if (submitting.value) return
  validationAttempted.value = false
  openBudgetDropdownIndex.value = null
  document.body?.classList?.remove('complete-stage-open')
  const viewModal = document.getElementById('view-deal-modal')
  viewModal?.removeAttribute?.('inert')
  openSections.value = {
    buyer: true,
    seller: true,
    tenant: true,
    landlord: true,
    properties: true,
    financials: true,
  }
  formData.value = {}
  localProperties.value = []
  dealAmountManuallyEdited.value = false
  submitting.value = false
  if (submitResetTimer) {
    clearTimeout(submitResetTimer)
    submitResetTimer = null
  }
  emit('closed')
}

function onOverlayClick() {
  // Prevent accidental close while working with file pickers/dropdowns in this critical modal.
  if (submitting.value) return
}

// Watch for modal show
watch(() => props.show, async (val) => {
    if (val) {
        resetAccumulatedRequiredKeys()
        accumulateRequiredKeysFromProps()
        dealAmountManuallyEdited.value = false
        document.body?.classList?.add('complete-stage-open')
        // Prevent underlying bootstrap modal from trapping focus/clicks
        const viewModal = document.getElementById('view-deal-modal')
        viewModal?.setAttribute?.('inert', '')
        validationAttempted.value = false
        // إعادة تعيين حالة تحميل الـ Properties
        isLoadingPropertyData.value = true
        
        // إذا لم يتم تهيئة البيانات، قم بتحميلها
        if (!isDataInitialized.value) {
            propertyTypesLoaded.value = false
            developersLoaded.value = false
            areasLoaded.value = false
            
            await fetchAllAreas()
            await Promise.all([
                fetchPropertyTypes(),
                fetchDevelopers()
            ])
            isDataInitialized.value = true
        }
        
        await initializeForm()

        // Focus first input inside the stage modal so typing works
        await nextTick()
        const root = document.querySelector('.complete-fields-modal')
        const firstFocusable = root?.querySelector?.(
          'input:not([disabled]):not([type="hidden"]), textarea:not([disabled]), [contenteditable="true"], .vs__search',
        )
        firstFocusable?.focus?.()
    } else {
        resetAccumulatedRequiredKeys()
        document.body?.classList?.remove('complete-stage-open')
        const viewModal = document.getElementById('view-deal-modal')
        viewModal?.removeAttribute?.('inert')
        submitting.value = false
        if (submitResetTimer) {
          clearTimeout(submitResetTimer)
          submitResetTimer = null
        }
    }
}, { immediate: true })

watch(
  () => [props.requiredFields, props.missingFields, props.groupedMissing, props.missingFieldsGroupedByStage],
  () => {
    if (props.show) accumulateRequiredKeysFromProps()
  },
  { deep: true },
)

watch(
  () => [props.dealId, props.targetStageId],
  () => {
    if (props.show) {
      resetAccumulatedRequiredKeys()
      accumulateRequiredKeysFromProps()
    }
  },
)

// دالة لتنسيق تاريخ الميلاد إلى YYYY-MM-DD لـ AdvancedDatePicker
function formatDateForDatePicker(dateValue) {
  if (!dateValue) return null;
  
  try {
    // إذا كان التاريخ null أو undefined
    if (!dateValue) return null;
    
    // إذا كان بالفعل بصيغة YYYY-MM-DD
    if (typeof dateValue === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(dateValue)) {
      return dateValue;
    }
    
    // محاولة تحويل التاريخ
    const date = new Date(dateValue);
    if (isNaN(date.getTime())) return null;
    
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    
    return `${year}-${month}-${day}`;
  } catch (error) {
    console.warn('Error formatting date:', dateValue, error);
    return null;
  }
}
// أضف هذه المتغيرات في بداية الـ script (بعد const openSections)
const recentlyOpenedSection = ref(null)
const recentlyOpenedTimer = ref(null)

// قم بتعديل دالة hasUnresolvedInSection لإضافة منطق جديد
function hasUnresolvedInSection(section) {
  const unresolved = unresolvedMissingKeys.value || []

  switch (section) {
    case 'buyer':
      return unresolved.some((k) => k.startsWith('buyer_') || k.startsWith('buyer_document_'))
    case 'seller':
      return unresolved.some((k) => k.startsWith('seller_') || k.startsWith('seller_document_'))
    case 'tenant':
      return unresolved.some((k) => k.startsWith('tenant_') || k.startsWith('tenant_document_'))
    case 'landlord':
      return unresolved.some((k) => k.startsWith('landlord_') || k.startsWith('landlord_document_'))
    case 'properties':
      return unresolved.some((k) => k.startsWith('property_') || k === 'at_least_one_property')
    case 'financials':
      return unresolved.some((k) => ['deal_commission', 'deal_total_amount'].includes(k))
    default:
      return false
  }
}

// قم بتعديل watch الخاص بـ unresolvedMissingKeys
// watch(unresolvedMissingKeys, () => {
//   const sections = ['buyer', 'seller', 'tenant', 'landlord', 'properties', 'financials']
//   sections.forEach((section) => {
//     if (hasUnresolvedInSection(section)) {
//       openSections.value[section] = true
//     }
//   })
// }, { deep: true })
// Options for selects
const languageOptions = [
  { value: 'arabic', text: 'Arabic' },
  { value: 'english', text: 'English' },
  { value: 'french', text: 'French' },
  { value: 'spanish', text: 'Spanish' },
  { value: 'german', text: 'German' },
  { value: 'italian', text: 'Italian' },
  { value: 'portuguese', text: 'Portuguese' },
  { value: 'russian', text: 'Russian' },
  { value: 'chinese', text: 'Chinese (Mandarin)' },
  { value: 'japanese', text: 'Japanese' },
  { value: 'korean', text: 'Korean' },
  { value: 'hindi', text: 'Hindi' },
  { value: 'urdu', text: 'Urdu' },
  { value: 'bengali', text: 'Bengali' },
  { value: 'turkish', text: 'Turkish' },
  { value: 'persian', text: 'Persian (Farsi)' },
  { value: 'swahili', text: 'Swahili' },
  { value: 'hausa', text: 'Hausa' },
  { value: 'amharic', text: 'Amharic' },
  { value: 'dutch', text: 'Dutch' },
  { value: 'greek', text: 'Greek' },
  { value: 'hebrew', text: 'Hebrew' },
  { value: 'thai', text: 'Thai' },
  { value: 'vietnamese', text: 'Vietnamese' },
  { value: 'malay', text: 'Malay' },
  { value: 'indonesian', text: 'Indonesian' },
  { value: 'filipino', text: 'Filipino (Tagalog)' },
  { value: 'polish', text: 'Polish' },
  { value: 'ukrainian', text: 'Ukrainian' },
  { value: 'czech', text: 'Czech' },
  { value: 'romanian', text: 'Romanian' },
  { value: 'hungarian', text: 'Hungarian' },
  { value: 'swedish', text: 'Swedish' },
  { value: 'norwegian', text: 'Norwegian' },
  { value: 'danish', text: 'Danish' },
  { value: 'finnish', text: 'Finnish' },
  { value: 'other', text: 'Other' }
];


const residencyOptions = [
  { value: 'resident', text: 'Resident' },
  { value: 'non_resident', text: 'Non Resident' }
]

const buyerResidencyOptions = [
  { value: 'resident', text: 'Resident' },
  { value: 'non_resident', text: 'Non Resident' }
]

const countryOptions = [
  { value: "Afghanistan", text: "Afghanistan" },
  { value: "Albania", text: "Albania" },
  { value: "Algeria", text: "Algeria" },
  { value: "Andorra", text: "Andorra" },
  { value: "Angola", text: "Angola" },
  { value: "Argentina", text: "Argentina" },
  { value: "Armenia", text: "Armenia" },
  { value: "Australia", text: "Australia" },
  { value: "Austria", text: "Austria" },
  { value: "Azerbaijan", text: "Azerbaijan" },
  { value: "Bahrain", text: "Bahrain" },
  { value: "Bangladesh", text: "Bangladesh" },
  { value: "Belarus", text: "Belarus" },
  { value: "Belgium", text: "Belgium" },
  { value: "Belize", text: "Belize" },
  { value: "Benin", text: "Benin" },
  { value: "Bhutan", text: "Bhutan" },
  { value: "Bolivia", text: "Bolivia" },
  { value: "Bosnia and Herzegovina", text: "Bosnia and Herzegovina" },
  { value: "Botswana", text: "Botswana" },
  { value: "Brazil", text: "Brazil" },
  { value: "Brunei", text: "Brunei" },
  { value: "Bulgaria", text: "Bulgaria" },
  { value: "Burkina Faso", text: "Burkina Faso" },
  { value: "Burundi", text: "Burundi" },
  { value: "Cambodia", text: "Cambodia" },
  { value: "Cameroon", text: "Cameroon" },
  { value: "Canada", text: "Canada" },
  { value: "Chad", text: "Chad" },
  { value: "Chile", text: "Chile" },
  { value: "China", text: "China" },
  { value: "Colombia", text: "Colombia" },
  { value: "Comoros", text: "Comoros" },
  { value: "Congo", text: "Congo" },
  { value: "Costa Rica", text: "Costa Rica" },
  { value: "Croatia", text: "Croatia" },
  { value: "Cuba", text: "Cuba" },
  { value: "Cyprus", text: "Cyprus" },
  { value: "Czech Republic", text: "Czech Republic" },
  { value: "Denmark", text: "Denmark" },
  { value: "Djibouti", text: "Djibouti" },
  { value: "Dominican Republic", text: "Dominican Republic" },
  { value: "Ecuador", text: "Ecuador" },
  { value: "Egypt", text: "Egypt" },
  { value: "El Salvador", text: "El Salvador" },
  { value: "Estonia", text: "Estonia" },
  { value: "Ethiopia", text: "Ethiopia" },
  { value: "Finland", text: "Finland" },
  { value: "France", text: "France" },
  { value: "Gabon", text: "Gabon" },
  { value: "Georgia", text: "Georgia" },
  { value: "Germany", text: "Germany" },
  { value: "Ghana", text: "Ghana" },
  { value: "Greece", text: "Greece" },
  { value: "Guatemala", text: "Guatemala" },
  { value: "Haiti", text: "Haiti" },
  { value: "Honduras", text: "Honduras" },
  { value: "Hungary", text: "Hungary" },
  { value: "Iceland", text: "Iceland" },
  { value: "India", text: "India" },
  { value: "Indonesia", text: "Indonesia" },
  { value: "Iran", text: "Iran" },
  { value: "Iraq", text: "Iraq" },
  { value: "Ireland", text: "Ireland" },
  { value: "Israel", text: "Israel" },
  { value: "Italy", text: "Italy" },
  { value: "Jamaica", text: "Jamaica" },
  { value: "Japan", text: "Japan" },
  { value: "Jordan", text: "Jordan" },
  { value: "Kazakhstan", text: "Kazakhstan" },
  { value: "Kenya", text: "Kenya" },
  { value: "Kuwait", text: "Kuwait" },
  { value: "Kyrgyzstan", text: "Kyrgyzstan" },
  { value: "Laos", text: "Laos" },
  { value: "Latvia", text: "Latvia" },
  { value: "Lebanon", text: "Lebanon" },
  { value: "Libya", text: "Libya" },
  { value: "Lithuania", text: "Lithuania" },
  { value: "Luxembourg", text: "Luxembourg" },
  { value: "Madagascar", text: "Madagascar" },
  { value: "Malaysia", text: "Malaysia" },
  { value: "Maldives", text: "Maldives" },
  { value: "Mali", text: "Mali" },
  { value: "Malta", text: "Malta" },
  { value: "Mexico", text: "Mexico" },
  { value: "Moldova", text: "Moldova" },
  { value: "Monaco", text: "Monaco" },
  { value: "Mongolia", text: "Mongolia" },
  { value: "Montenegro", text: "Montenegro" },
  { value: "Morocco", text: "Morocco" },
  { value: "Mozambique", text: "Mozambique" },
  { value: "Myanmar", text: "Myanmar" },
  { value: "Namibia", text: "Namibia" },
  { value: "Nepal", text: "Nepal" },
  { value: "Netherlands", text: "Netherlands" },
  { value: "New Zealand", text: "New Zealand" },
  { value: "Nicaragua", text: "Nicaragua" },
  { value: "Niger", text: "Niger" },
  { value: "Nigeria", text: "Nigeria" },
  { value: "North Korea", text: "North Korea" },
  { value: "Norway", text: "Norway" },
  { value: "Oman", text: "Oman" },
  { value: "Pakistan", text: "Pakistan" },
  { value: "Panama", text: "Panama" },
  { value: "Paraguay", text: "Paraguay" },
  { value: "Peru", text: "Peru" },
  { value: "Philippines", text: "Philippines" },
  { value: "Poland", text: "Poland" },
  { value: "Portugal", text: "Portugal" },
  { value: "Qatar", text: "Qatar" },
  { value: "Romania", text: "Romania" },
  { value: "Russia", text: "Russia" },
  { value: "Rwanda", text: "Rwanda" },
  { value: "Saudi Arabia", text: "Saudi Arabia" },
  { value: "Senegal", text: "Senegal" },
  { value: "Serbia", text: "Serbia" },
  { value: "Singapore", text: "Singapore" },
  { value: "Slovakia", text: "Slovakia" },
  { value: "Slovenia", text: "Slovenia" },
  { value: "Somalia", text: "Somalia" },
  { value: "South Africa", text: "South Africa" },
  { value: "South Korea", text: "South Korea" },
  { value: "Spain", text: "Spain" },
  { value: "Sri Lanka", text: "Sri Lanka" },
  { value: "Sudan", text: "Sudan" },
  { value: "Sweden", text: "Sweden" },
  { value: "Switzerland", text: "Switzerland" },
  { value: "Syria", text: "Syria" },
  { value: "Taiwan", text: "Taiwan" },
  { value: "Tanzania", text: "Tanzania" },
  { value: "Thailand", text: "Thailand" },
  { value: "Tunisia", text: "Tunisia" },
  { value: "Turkey", text: "Turkey" },
  { value: "Uganda", text: "Uganda" },
  { value: "Ukraine", text: "Ukraine" },
  { value: "United Arab Emirates", text: "United Arab Emirates" },
  { value: "United Kingdom", text: "United Kingdom" },
  { value: "United States", text: "United States" },
  { value: "Uruguay", text: "Uruguay" },
  { value: "Uzbekistan", text: "Uzbekistan" },
  { value: "Venezuela", text: "Venezuela" },
  { value: "Vietnam", text: "Vietnam" },
  { value: "Yemen", text: "Yemen" },
  { value: "Zambia", text: "Zambia" },
  { value: "Zimbabwe", text: "Zimbabwe" },
  { value: "Other", text: "Other" }
];

const bedroomOptions = [
  { value: 'studio', text: 'Studio' }, { value: '1', text: '1 Bedroom' },
  { value: '2', text: '2 Bedrooms' }, { value: '3', text: '3 Bedrooms' },
  { value: '4', text: '4 Bedrooms' }, { value: '5', text: '5 Bedrooms' }
]

// City options based on country
const citiesByCountry = {
  'United Arab Emirates': [
    { value: 'Abu Dhabi', text: 'Abu Dhabi' },
    { value: 'Dubai', text: 'Dubai' },
    { value: 'Sharjah', text: 'Sharjah' },
    { value: 'Ajman', text: 'Ajman' },
    { value: 'Ras Al Khaimah', text: 'Ras Al Khaimah' },
    { value: 'Fujairah', text: 'Fujairah' },
    { value: 'Umm Al Quwain', text: 'Umm Al Quwain' }
  ]
}

const buyerCityOptions = computed(() => {
  const residencyStatus = formData.value?.buyer_residency_status
  
  if (residencyStatus === 'resident') {
    return citiesByCountry['United Arab Emirates'] || []
  }
  
  const country = formData.value?.buyer_country
  if (country && citiesByCountry[country]) return citiesByCountry[country]
  
  return []
})

const sellerCityOptions = computed(() => {
  const residencyStatus = formData.value?.seller_residency_status
  if (residencyStatus === 'resident') {
    return citiesByCountry['United Arab Emirates'] || []
  }
  const country = formData.value?.seller_country
  if (country && citiesByCountry[country]) return citiesByCountry[country]
  return []
})

const tenantCityOptions = computed(() => {
  const residencyStatus = formData.value?.tenant_residency_status
  if (residencyStatus === 'resident') {
    return citiesByCountry['United Arab Emirates'] || []
  }
  const country = formData.value?.tenant_country
  if (country && citiesByCountry[country]) return citiesByCountry[country]
  return []
})

const landlordCityOptions = computed(() => {
  const residencyStatus = formData.value?.landlord_residency_status
  if (residencyStatus === 'resident') {
    return citiesByCountry['United Arab Emirates'] || []
  }
  const country = formData.value?.landlord_country
  if (country && citiesByCountry[country]) return citiesByCountry[country]
  return []
})


// Show city/country based on residency
const showBuyerCityField = computed(() => formData.value?.buyer_residency_status === 'resident')
const showBuyerCountryField = computed(() => formData.value?.buyer_residency_status === 'non_resident')
const showSellerCityField = computed(() => formData.value?.seller_residency_status === 'resident')
const showSellerCountryField = computed(() => formData.value?.seller_residency_status === 'non_resident')
const showTenantCityField = computed(() => formData.value?.tenant_residency_status === 'resident')
const showTenantCountryField = computed(() => formData.value?.tenant_residency_status === 'non_resident')
const showLandlordCityField = computed(() => formData.value?.landlord_residency_status === 'resident')
const showLandlordCountryField = computed(() => formData.value?.landlord_residency_status === 'non_resident')

// في Primary، يجب إخفاء Seller, Tenant, Landlord تماماً
const shouldHideBuyer = computed(() => {
  const dealType = normalizedDealType.value
  // Rental: إخفاء Buyer
  if (dealType === 'rental') return true
  // Primary و Secondary: إظهار Buyer
  return false
})

const shouldHideSeller = computed(() => {
  const dealType = normalizedDealType.value
  // في Primary، أخفِ Seller
  if (dealType === 'primary' || dealType === 'rental') return true
  return hasListingId.value && formData.value?.deal_type === 'secondary'
})

const shouldHideLandlord = computed(() => {
  const dealType = normalizedDealType.value
  // في Primary، أخفِ Landlord
  if (dealType === 'primary' || dealType === 'secondary') return true
  return hasListingId.value && formData.value?.deal_type === 'rental'
})

const shouldHideTenant = computed(() => {
  const dealType = normalizedDealType.value
  // في Primary، أخفِ Tenant
  if (dealType === 'primary' || dealType === 'secondary') return true
  return hasListingId.value && formData.value?.deal_type !== 'rental'
})

// Compact modal
const isCompactStageModal = computed(() => false)
const isDealWonStage = computed(() => false)
const isLostReasonOnly = computed(() => false)

watch([propertyTypesLoaded, developersLoaded, areasLoaded], () => {
  if (propertyTypesLoaded.value && developersLoaded.value && areasLoaded.value) {
    isLoadingPropertyData.value = false
    console.log('All property data loaded successfully')
  }
}, { deep: true })// Load data on mount
// Load data on mount
onMounted(async () => {
    isLoadingPropertyData.value = true
    isDataInitialized.value = false
    
    // إعادة تعيين حالات التحميل
    propertyTypesLoaded.value = false
    developersLoaded.value = false
    areasLoaded.value = false
    
    await fetchAllAreas()
    await Promise.all([
        fetchUsers(),
        fetchSources(),
        fetchPropertyTypes(),
        fetchDevelopers()
    ])
    
    isDataInitialized.value = true
    
    
     const style = document.createElement('style');
    style.textContent = `
        .vs__dropdown-menu {
            z-index: 46200 !important;
        }
        .flatpickr-calendar {
            z-index: 46200 !important;
        }
        [data-popper-placement] {
            z-index: 46200 !important;
        }
    `;
    document.head.appendChild(style);
})

</script>

<style scoped>
.complete-fields-overlay {
  position: fixed;
  inset: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  /* Must sit above ViewDealModal and any bootstrap backdrops */
  z-index: 30000;
  backdrop-filter: blur(2px);
}

.complete-fields-modal {
  background: white;
  border-radius: 10px;
  width: min(760px, 94vw);
  max-width: 94vw;
  max-height: 90vh;
  /*overflow-y: auto;*/
  display: flex;
  flex-direction: column;
  border: 1px solid rgba(0, 0, 0, 0.08);
  position: relative;
  z-index: 30010;
}

.modal-header-deal {
  border-bottom: 1px solid #F4F4F4;
  flex-shrink: 0;
  padding: 14px 18px !important;
    position: relative;
        background: #fff;
    border-radius: 10px;
}

.modal-title {
  font-weight: 500;
  font-size: 14px;
  color: #01062C;
}

.close-btn {
    position: absolute;
    top: 8px;
    right: -61px;
    width: 83px;
    height: 49px;
    color: #fff;
    font-size: 18px;
    line-height: 1;
    box-shadow: #0f172a33 0 8px 16px;
    z-index: -1;
    display: flex;
    justify-content: center;
    align-items: center;
    border-width: 1px;
    border-style: solid;
    border-color: #4fa5f7;
    border-image: initial;
    border-radius: 999px;
    background: linear-gradient(90deg, #2f88ef, #5db8ff);
    padding: 0;
    transition: filter .2s;
}

.close-btn:hover {
  background: #F1F5F9;
  color: #1E293B;
}
.deal-progress-hint , .modal-footer-custom .text-danger {
      display: flex;
    /* justify-content: center; */
    align-items: center;
}
.form-scroll-area {
  flex: 1;
  padding: 0 14px 8px;
  overflow-y: auto;
  max-height: calc(90vh - 180px);
}

.form-section {
  margin-top: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  overflow: hidden;
}

.section-collapsible-header {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: #f8fafc;
  cursor: pointer;
  transition: background 0.2s;
  justify-content: space-between;

}
.properties-section{
   justify-content: space-between;
}
.section-collapsible-header .add-new{
    justify-content: space-between;
    gap: 10px;
    align-items: center;
}
.section-collapsible-header:hover {
  background: #f1f5f9;
}

.section-collapsible-header.has-required {
  background: #fff8eb;
  border-left: 3px solid #faa300;
}

.collapse-icon {
  font-size: 16px;
  color: #64748b;
  transition: transform 0.2s;
}

.section-title {
  font-size: 13px !important;
  font-weight: 600;
  color: #01062c;
  margin: 0;
}

.required-badge {
  margin-left: auto;
  font-size: 10px;
  padding: 2px 8px;
  border-radius: 12px;
  background: #faa300;
  color: white;
  font-weight: 500;
}

.section-content {
  padding: 12px;
  border-top: 1px solid #e5e7eb;
  background: white;
}

.form-card {
  background: #fff;
  border: 1px solid #f1f5f9;
  border-radius: 8px;
}

.form-label-custom {
  font-size: 12px;
  font-weight: 500;
  color: #64748b;
  margin-bottom: 6px;
  display: block;
}

.custom-input {
  height: 40px !important;
  min-height: 40px;
  border-radius: 8px !important;
  border: 1px solid #E2E8F0 !important;
  font-size: 12px !important;
  width: 100%;
  padding: 0 12px;
}

.custom-input::placeholder {
  font-size: 9px;
  color: #9ca3af;
}

/* Smaller placeholders across this modal (inputs + selects + phone input) */
.complete-fields-modal ::placeholder {
  font-size: 9px !important;
}

:deep(.custom-v-select .vs__search::placeholder),
:deep(.custom-v-select .vs__selected-options .vs__search::placeholder),
:deep(.crm-phone-input .vti__input::placeholder) {
  font-size: 9px !important;
  color: #9ca3af !important;
}

.budget-field-wrap-stage {
  position: relative;
  overflow: visible;
  background: transparent;
}

.budget-field-wrap-stage.is-invalid-group {
  border-radius: 10px;
}

.custom-date-trigger-stage {
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
  cursor: pointer;
}

.budget-dropdown-stage {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  width: 100%;
  min-width: 220px;
  z-index: 30020;
  background: #fff;
  border: 1px solid #E2E8F0;
  border-radius: 10px;
  box-shadow: 0 10px 24px rgba(2, 6, 23, 0.12);
  padding: 10px;
}

/* vue-select dropdown inside stacked modals must be above everything */
:deep(.vs__dropdown-menu) {
  z-index: 30020 !important;
}

.budget-from-to-row-stage {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.budget-col-stage {
  min-width: 0;
}

.budget-input-label-stage {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: #334155;
  margin-bottom: 6px;
}

.budget-dropdown-input-stage {
  height: 38px !important;
}

.custom-input.is-invalid,
textarea.is-invalid {
  border-color: #dc3545 !important;
}

:deep(.custom-v-select .vs__dropdown-toggle) {
  border: 1px solid #E2E8F0;
  border-radius: 8px;
  min-height: 40px !important;
  height: 40px !important;
  font-size: 12px;
}

:deep(.custom-v-select.is-invalid .vs__dropdown-toggle) {
  border-color: #dc3545 !important;
}

.modal-footer-custom {
  border-top: 1px solid #F4F4F4;
  background: white;
  flex-shrink: 0;
  padding: 14px 20px !important;
}

.btn-clear {
  background: #F4F4F4;
  border: none;
  width: 96px;
  height: 40px;
  border-radius: 100px;
  font-size: 14px;
  color: #01062C;
  cursor: pointer;
    text-align: center;
  justify-content: center;
}

.btn-next-step {
  background: #01062C;
  border: none;
  width: 96px;
  height: 40px;
  border-radius: 100px;
  font-size: 14px;
  color: #fff;
  font-weight: 500;
  cursor: pointer;
  text-align: center;
  justify-content: center;
}

.btn-next-step:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.property-card-in-modal {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 12px;
  margin-bottom: 16px;
  transition: all 0.2s;
}

.property-card-in-modal.property-missing {
  border-color: #dc3545;
  background-color: #fff5f5;
}

.badge.bg-secondary {
  background: #64748b;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 11px;
}

.badge.bg-danger {
  background: #dc3545;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 11px;
}

.input-group-text {
  background: #f8fafc;
  border: 1px solid #E2E8F0;
  font-size: 12px;
}

.alert-warning {
  background-color: #fff3cd;
  border: 1px solid #ffe69c;
  color: #664d03;
  border-radius: 8px;
}

.text-danger {
  color: #dc3545;
}

.text-danger svg {
  margin-right: 4px;
}

.deals-type-tab-inline {
  padding: 6px 14px;
  border-radius: 100px;
  background: #0F172A;
  color: #fff;
  font-size: 12px;
}
:deep(   .vs__dropdown-menu ){
      z-index: 30050 !important;
}
:deep(   .flatpickr-calendar  ){
      z-index: 30050 !important;
}
:deep(   [data-popper-placement]){
      z-index: 30050 !important;
}
:deep(.advanced-date-trigger){
  border-radius: 8px !important;
  border: 1px solid #E2E8F0 !important;
}
/* Section completed styles */
.section-collapsible-header.has-completed {
  background: #f0fdf4;
  border-left: 3px solid #22c55e;
}

.completed-badge {
  font-size: 10px;
  padding: 2px 8px;
  border-radius: 12px;
  background: #22c55e;
  color: white;
  font-weight: 500;
}

.section-collapsible-header.has-required {
  background: #fff8eb;
  border-left: 3px solid #faa300;
}
</style>
<style>
    .vs__dropdown-menu {
  z-index: 30050 !important;
}

.flatpickr-calendar {
  z-index: 30050 !important;
}

[data-popper-placement] {
  z-index: 30050 !important;
}
.add-property-btn {
  background: transparent;
  border: 1px solid #3b82f6;
  color: #3b82f6;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 4px;
  cursor: pointer;
  transition: all 0.2s;
  margin-left: auto;
}

.add-property-btn:hover:not(:disabled) {
  background: #3b82f6;
  color: white;
}

.add-property-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-remove-property {
  background: transparent;
  border: none;
  color: #ef4444;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 4px;
}

.btn-remove-property:hover:not(:disabled) {
  background: #fee2e2;
  color: #dc2626;
}

.btn-remove-property:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.advanced-date-trigger{
  border-radius: 8px !important;
  border: 1px solid #E2E8F0 !important;
}
</style>