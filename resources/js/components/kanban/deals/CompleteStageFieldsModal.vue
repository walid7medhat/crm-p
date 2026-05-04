<template>
  <Teleport to="body">
    <div v-if="show" class="complete-fields-overlay" @click.self="closeModal">
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
            <button class="close-btn" @click="closeModal">
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
                <label class="form-label-custom">Enter Reason For Deal Lost <span v-if="hasField('lost_reason')" class="text-danger">*</span></label>
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
                :class="{ 'has-required': hasRequiredInSection('buyer') }"
                @click="toggleSection('buyer')"
              >
                <iconify-icon :icon="isSectionOpen('buyer') ? 'lucide:chevron-down' : 'lucide:chevron-right'" class="collapse-icon"></iconify-icon>
                <h6 class="section-title mb-0">Buyer Details</h6>
                <span v-if="hasRequiredInSection('buyer')" class="required-badge">Required</span>
              </div>
              
              <div v-show="isSectionOpen('buyer')" class="section-content">
                <div class="form-card p-3 radius-12" v-if="showPartyDetailFields('buyer')">
                  <div class="row g-3">
                    <!-- Buyer fields -->
                    <div class="col-md-6" v-if="shouldShowField('buyer_first_name')">
                      <label class="form-label-custom">Buyer First Name <span v-if="hasField('buyer_first_name')" class="text-danger">*</span></label>
                      <b-form-input 
                        v-model="formData.buyer_first_name" 
                        placeholder="Enter First Name" 
                        class="custom-input"
                        :class="{ 'is-invalid': isFieldInvalid('buyer_first_name') }"
                      />
                    </div>
                    
                    <div class="col-md-6" v-if="shouldShowField('buyer_last_name')">
                      <label class="form-label-custom">Buyer Last Name <span v-if="hasField('buyer_last_name')" class="text-danger">*</span></label>
                      <b-form-input 
                        v-model="formData.buyer_last_name" 
                        placeholder="Enter Last Name" 
                        class="custom-input compact-placeholder-field"
                        :class="{ 'is-invalid': isFieldInvalid('buyer_last_name') }"
                      />
                    </div>
                    
                    <div class="col-md-6" v-if="shouldShowField('buyer_phone')">
                      <label class="form-label-custom">Buyer Phone Number <span v-if="hasField('buyer_phone')" class="text-danger">*</span></label>
                      <CrmPhoneInput 
                        v-model="formData.buyer_phone" 
                        placeholder="Enter Phone Number" 
                        :invalid="validationAttempted && hasField('buyer_phone') && isFieldInvalid('buyer_phone')"
                        :show-errors="validationAttempted && hasField('buyer_phone')"
                      />
                    </div>
                    
                    <div class="col-md-6" v-if="shouldShowField('buyer_email')">
                      <label class="form-label-custom">Buyer Email <span v-if="hasField('buyer_email')" class="text-danger">*</span></label>
                      <b-form-input 
                        v-model="formData.buyer_email" 
                        type="email" 
                        placeholder="Enter Your Email" 
                        class="custom-input"
                        :class="{ 'is-invalid': isFieldInvalid('buyer_email') }"
                      />
                    </div>
                    
                    <div class="col-md-6" v-if="shouldShowField('buyer_nationality')">
                      <label class="form-label-custom">Buyer Nationality <span v-if="hasField('buyer_nationality')" class="text-danger">*</span></label>
                      <v-select
                        append-to-body 
                        v-model="formData.buyer_nationality" 
                        :options="nationalityOptions" 
                        :reduce="item => item.value" 
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
                      </v-select>
                    </div>
                    
                    <div class="col-md-6" v-if="shouldShowField('buyer_residency_status') || (documentTypesByParty.buyer.length > 0 && ['primary', 'secondary'].includes(effectiveDealTypeForDocs))">
                      <label class="form-label-custom">Buyer Residency Status <span v-if="hasField('buyer_residency_status')" class="text-danger">*</span></label>
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
                    
                    <div class="col-md-6" v-if="shouldShowField('buyer_country') && showBuyerCountryField">
                      <label class="form-label-custom">Buyer Country Of Residence <span v-if="hasField('buyer_country')" class="text-danger">*</span></label>
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
                      <label class="form-label-custom">Buyer City Of Residence <span v-if="hasField('buyer_city')" class="text-danger">*</span></label>
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

                    <div class="col-md-6" v-if="shouldShowField('buyer_dob')">
                      <label class="form-label-custom">Buyer Date Of Birth <span v-if="hasField('buyer_dob')" class="text-danger">*</span></label>
                      <AdvancedDatePicker
                        v-model="formData.buyer_dob"
                        date-only
                        placeholder="Select date"
                        class="custom-input"
                        :invalid="isFieldInvalid('buyer_dob')"
                      />
                    </div>

                    <div class="col-md-6" v-if="shouldShowField('buyer_language')">
                      <label class="form-label-custom">Buyer Language <span v-if="hasField('buyer_language')" class="text-danger">*</span></label>
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
                  <label class="section-title mb-3">Buyer Documents</label>
                  <DocumentUpload
                    v-model="formData.buyer_documents"
                    category="buyer"
                    compact
                    :document-types="documentTypesByParty.buyer"
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
                :class="{ 'has-required': hasRequiredInSection('seller') }"
                @click="toggleSection('seller')"
              >
                <iconify-icon :icon="isSectionOpen('seller') ? 'lucide:chevron-down' : 'lucide:chevron-right'" class="collapse-icon"></iconify-icon>
                <h6 class="section-title mb-0">Seller Details</h6>
                <span v-if="hasRequiredInSection('seller')" class="required-badge">Required</span>
              </div>
              
              <div v-show="isSectionOpen('seller')" class="section-content">
                <div class="form-card p-3 radius-12" v-if="showPartyDetailFields('seller')">
                  <div class="row g-3">
                    <!-- Seller fields -->
                    <div class="col-md-4" v-if="shouldShowField('seller_first_name')">
                      <label class="form-label-custom">First Name <span v-if="hasField('seller_first_name')" class="text-danger">*</span></label>
                      <b-form-input 
                        v-model="formData.seller_first_name" 
                        placeholder="Enter First Name" 
                        class="custom-input"
                        :class="{ 'is-invalid': isFieldInvalid('seller_first_name') }"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('seller_last_name')">
                      <label class="form-label-custom">Last Name <span v-if="hasField('seller_last_name')" class="text-danger">*</span></label>
                      <b-form-input 
                        v-model="formData.seller_last_name" 
                        placeholder="Enter Last Name" 
                        class="custom-input"
                        :class="{ 'is-invalid': isFieldInvalid('seller_last_name') }"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('seller_dob')">
                      <label class="form-label-custom">Date Of Birth <span v-if="hasField('seller_dob')" class="text-danger">*</span></label>
                      <AdvancedDatePicker
                        v-model="formData.seller_dob"
                        date-only
                        placeholder="Select date"
                        class="custom-input"
                        :invalid="isFieldInvalid('seller_dob')"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('seller_phone')">
                      <label class="form-label-custom">Phone <span v-if="hasField('seller_phone')" class="text-danger">*</span></label>
                      <CrmPhoneInput 
                        v-model="formData.seller_phone" 
                        placeholder="Enter Phone" 
                        :invalid="validationAttempted && hasField('seller_phone') && isFieldInvalid('seller_phone')"
                        :show-errors="validationAttempted && hasField('seller_phone')"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('seller_email')">
                      <label class="form-label-custom">Email <span v-if="hasField('seller_email')" class="text-danger">*</span></label>
                      <b-form-input 
                        v-model="formData.seller_email" 
                        type="email" 
                        placeholder="Enter Email" 
                        class="custom-input"
                        :class="{ 'is-invalid': isFieldInvalid('seller_email') }"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('seller_nationality')">
                      <label class="form-label-custom">Nationality <span v-if="hasField('seller_nationality')" class="text-danger">*</span></label>
                      <v-select
                        append-to-body 
                        v-model="formData.seller_nationality" 
                        :options="nationalityOptions" 
                        :reduce="item => item.value" 
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
                      </v-select>
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('seller_residency_status')">
                      <label class="form-label-custom">Residency Status <span v-if="hasField('seller_residency_status')" class="text-danger">*</span></label>
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
                      <label class="form-label-custom">City Of Residence <span v-if="hasField('seller_city')" class="text-danger">*</span></label>
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
                      <label class="form-label-custom">Language <span v-if="hasField('seller_language')" class="text-danger">*</span></label>
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
                  <label class="section-title mb-3">Seller Documents</label>
                  <DocumentUpload
                    v-model="formData.seller_documents"
                    category="seller"
                    compact
                    :document-types="documentTypesByParty.seller"
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
                :class="{ 'has-required': hasRequiredInSection('tenant') }"
                @click="toggleSection('tenant')"
              >
                <iconify-icon :icon="isSectionOpen('tenant') ? 'lucide:chevron-down' : 'lucide:chevron-right'" class="collapse-icon"></iconify-icon>
                <h6 class="section-title mb-0">Tenant Details</h6>
                <span v-if="hasRequiredInSection('tenant')" class="required-badge">Required</span>
              </div>
              
              <div v-show="isSectionOpen('tenant')" class="section-content">
                <div class="form-card p-3 radius-12" v-if="showPartyDetailFields('tenant')">
                  <div class="row g-3">
                    <div class="col-md-4" v-if="shouldShowField('tenant_first_name')">
                      <label class="form-label-custom">First Name <span v-if="hasField('tenant_first_name')" class="text-danger">*</span></label>
                      <b-form-input 
                        v-model="formData.tenant_first_name" 
                        placeholder="Enter First Name" 
                        class="custom-input"
                        :class="{ 'is-invalid': isFieldInvalid('tenant_first_name') }"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('tenant_last_name')">
                      <label class="form-label-custom">Last Name <span v-if="hasField('tenant_last_name')" class="text-danger">*</span></label>
                      <b-form-input 
                        v-model="formData.tenant_last_name" 
                        placeholder="Enter Last Name" 
                        class="custom-input"
                        :class="{ 'is-invalid': isFieldInvalid('tenant_last_name') }"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('tenant_phone')">
                      <label class="form-label-custom">Phone <span v-if="hasField('tenant_phone')" class="text-danger">*</span></label>
                      <CrmPhoneInput 
                        v-model="formData.tenant_phone" 
                        placeholder="Enter Phone" 
                        :invalid="validationAttempted && hasField('tenant_phone') && isFieldInvalid('tenant_phone')"
                        :show-errors="validationAttempted && hasField('tenant_phone')"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('tenant_email')">
                      <label class="form-label-custom">Email <span v-if="hasField('tenant_email')" class="text-danger">*</span></label>
                      <b-form-input 
                        v-model="formData.tenant_email" 
                        type="email" 
                        placeholder="Enter Email" 
                        class="custom-input"
                        :class="{ 'is-invalid': isFieldInvalid('tenant_email') }"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('tenant_nationality')">
                      <label class="form-label-custom">Nationality <span v-if="hasField('tenant_nationality')" class="text-danger">*</span></label>
                      <v-select
                        append-to-body 
                        v-model="formData.tenant_nationality" 
                        :options="nationalityOptions" 
                        :reduce="item => item.value" 
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
                      </v-select>
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('tenant_residency_status')">
                      <label class="form-label-custom">Residency Status <span v-if="hasField('tenant_residency_status')" class="text-danger">*</span></label>
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
                      <label class="form-label-custom">City Of Residence <span v-if="hasField('tenant_city')" class="text-danger">*</span></label>
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
                      <label class="form-label-custom">Language <span v-if="hasField('tenant_language')" class="text-danger">*</span></label>
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
                  <label class="section-title mb-3">Tenant Documents</label>
                  <DocumentUpload
                    v-model="formData.tenant_documents"
                    category="tenant"
                    compact
                    :document-types="documentTypesByParty.tenant"
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
                :class="{ 'has-required': hasRequiredInSection('landlord') }"
                @click="toggleSection('landlord')"
              >
                <iconify-icon :icon="isSectionOpen('landlord') ? 'lucide:chevron-down' : 'lucide:chevron-right'" class="collapse-icon"></iconify-icon>
                <h6 class="section-title mb-0">Landlord Details</h6>
                <span v-if="hasRequiredInSection('landlord')" class="required-badge">Required</span>
              </div>
              
              <div v-show="isSectionOpen('landlord')" class="section-content">
                <div class="form-card p-3 radius-12" v-if="showPartyDetailFields('landlord')">
                  <div class="row g-3">
                    <div class="col-md-4" v-if="shouldShowField('landlord_first_name')">
                      <label class="form-label-custom">First Name <span v-if="hasField('landlord_first_name')" class="text-danger">*</span></label>
                      <b-form-input 
                        v-model="formData.landlord_first_name" 
                        placeholder="Enter First Name" 
                        class="custom-input"
                        :class="{ 'is-invalid': isFieldInvalid('landlord_first_name') }"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('landlord_last_name')">
                      <label class="form-label-custom">Last Name <span v-if="hasField('landlord_last_name')" class="text-danger">*</span></label>
                      <b-form-input 
                        v-model="formData.landlord_last_name" 
                        placeholder="Enter Last Name" 
                        class="custom-input"
                        :class="{ 'is-invalid': isFieldInvalid('landlord_last_name') }"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('landlord_dob')">
                      <label class="form-label-custom">Date Of Birth <span v-if="hasField('landlord_dob')" class="text-danger">*</span></label>
                      <AdvancedDatePicker
                        v-model="formData.landlord_dob"
                        date-only
                        placeholder="Select date"
                        class="custom-input"
                        :invalid="isFieldInvalid('landlord_dob')"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('landlord_phone')">
                      <label class="form-label-custom">Phone <span v-if="hasField('landlord_phone')" class="text-danger">*</span></label>
                      <b-form-input 
                        v-model="formData.landlord_phone" 
                        placeholder="Enter Phone" 
                        class="custom-input"
                        :class="{ 'is-invalid': isFieldInvalid('landlord_phone') }"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('landlord_email')">
                      <label class="form-label-custom">Email <span v-if="hasField('landlord_email')" class="text-danger">*</span></label>
                      <b-form-input 
                        v-model="formData.landlord_email" 
                        type="email" 
                        placeholder="Enter Email" 
                        class="custom-input"
                        :class="{ 'is-invalid': isFieldInvalid('landlord_email') }"
                      />
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('landlord_nationality')">
                      <label class="form-label-custom">Nationality <span v-if="hasField('landlord_nationality')" class="text-danger">*</span></label>
                      <v-select
                        append-to-body 
                        v-model="formData.landlord_nationality" 
                        :options="nationalityOptions" 
                        :reduce="item => item.value" 
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
                      </v-select>
                    </div>
                    
                    <div class="col-md-4" v-if="shouldShowField('landlord_residency_status')">
                      <label class="form-label-custom">Residency Status <span v-if="hasField('landlord_residency_status')" class="text-danger">*</span></label>
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
                      <label class="form-label-custom">City Of Residence <span v-if="hasField('landlord_city')" class="text-danger">*</span></label>
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
                      <label class="form-label-custom">Language <span v-if="hasField('landlord_language')" class="text-danger">*</span></label>
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
                  <label class="section-title mb-3">Landlord Documents</label>
                  <DocumentUpload
                    v-model="formData.landlord_documents"
                    category="landlord"
                    compact
                    :document-types="documentTypesByParty.landlord"
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
                    class="section-collapsible-header"
                    :class="{ 'has-required': true }"
                    @click="toggleSection('properties')"
                >
                    <iconify-icon :icon="isSectionOpen('properties') ? 'lucide:chevron-down' : 'lucide:chevron-right'" class="collapse-icon"></iconify-icon>
                    <h6 class="section-title mb-0">Properties Details</h6>
                    <span class="required-badge">Required</span>
                </div>
                
                <div v-show="isSectionOpen('properties')" class="section-content">
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
                            </div>
                            
                            <div class="row g-3">
                                <!-- Unit No -->
                                <div class="col-md-6" v-if="shouldShowPropertyField('unit_no', property)">
                                    <label class="form-label-custom">Unit No <span v-if="isPropertyFieldRequired('unit_no')" class="text-danger">*</span></label>
                                    <b-form-input 
                                        :value="property.unit_no"
                                        @update:modelValue="(val) => updateProperty(propIndex, 'unit_no', val)"
                                        placeholder="Enter Unit No" 
                                        class="custom-input"
                                        :class="{ 'is-invalid': isPropertyFieldInvalid(property, 'unit_no') }"
                                    />
                                </div>
                                
                                <!-- Property Type -->
                                <div class="col-md-6" v-if="shouldShowPropertyField('property_type_id', property)">
                                    <label class="form-label-custom">Property Type <span v-if="isPropertyFieldRequired('property_type_id')" class="text-danger">*</span></label>
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
                                    <label class="form-label-custom">Bedrooms <span v-if="isPropertyFieldRequired('bedrooms')" class="text-danger">*</span></label>
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
                                
                                <!-- Unit Size -->
                                <div class="col-md-6" v-if="shouldShowPropertyField('unit_size', property)">
                                    <label class="form-label-custom">Unit Size (sq.ft) <span v-if="isPropertyFieldRequired('unit_size')" class="text-danger">*</span></label>
                                    <b-form-input 
                                        :value="property.unit_size"
                                        @update:modelValue="(val) => updateProperty(propIndex, 'unit_size', val)"
                                        type="number"
                                        placeholder="Size in sq.ft" 
                                        class="custom-input"
                                        :class="{ 'is-invalid': isPropertyFieldInvalid(property, 'unit_size') }"
                                    />
                                </div>
                                
                                <!-- Rental Price (للصفقات الإيجارية) -->
                                <div class="col-md-6" v-if="shouldShowPropertyField('rental_price', property)">
                                    <label class="form-label-custom">Rental Price <span v-if="isPropertyFieldRequired('rental_price')" class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <b-form-input 
                                            :value="property.rental_price"
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
                                    <label class="form-label-custom">Purchase Price <span v-if="isPropertyFieldRequired('purchase_price')" class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <b-form-input 
                                            :value="property.purchase_price"
                                            @update:modelValue="(val) => updateProperty(propIndex, 'purchase_price', val)"
                                            type="number"
                                            placeholder="Amount" 
                                            class="custom-input"
                                            :class="{ 'is-invalid': isPropertyFieldInvalid(property, 'purchase_price') }"
                                        />
                                        <span class="input-group-text">AED</span>
                                    </div>
                                </div>
                                
                                <!-- Area / Address -->
                                <div class="col-md-6" v-if="shouldShowPropertyField('area_id', property)">
                                    <label class="form-label-custom">Property Address <span v-if="isPropertyFieldRequired('area_id')" class="text-danger">*</span></label>
                                    <v-select
                                        :model-value="property.area_id"
                                        @update:modelValue="(val) => updateProperty(propIndex, 'area_id', val)"
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
                                
                                <!-- Developer -->
                                <div class="col-md-6" v-if="shouldShowPropertyField('developer_id', property)">
                                    <label class="form-label-custom">Developer <span v-if="isPropertyFieldRequired('developer_id')" class="text-danger">*</span></label>
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
                                
                                <!-- Developer Name (لـ Secondary) -->
                                <div class="col-md-6" v-if="shouldShowPropertyField('developer_name', property)">
                                    <label class="form-label-custom">Developer Name <span v-if="isPropertyFieldRequired('developer_name')" class="text-danger">*</span></label>
                                    <b-form-input 
                                        :value="property.developer_name"
                                        @update:modelValue="(val) => updateProperty(propIndex, 'developer_name', val)"
                                        placeholder="Enter Developer Name" 
                                        class="custom-input"
                                        :class="{ 'is-invalid': isPropertyFieldInvalid(property, 'developer_name') }"
                                    />
                                </div>
                                
                                <!-- Developer Phone (لـ Secondary) -->
                                <div class="col-md-6" v-if="shouldShowPropertyField('developer_phone', property)">
                                    <label class="form-label-custom">Developer Phone <span v-if="isPropertyFieldRequired('developer_phone')" class="text-danger">*</span></label>
                                    <b-form-input 
                                        :value="property.developer_phone"
                                        @update:modelValue="(val) => updateProperty(propIndex, 'developer_phone', val)"
                                        placeholder="Enter Developer Phone" 
                                        class="custom-input"
                                        :class="{ 'is-invalid': isPropertyFieldInvalid(property, 'developer_phone') }"
                                    />
                                </div>
                                
                                <!-- Budget From/To (for EOI stages) -->
                                <div class="col-md-6" v-if="shouldShowPropertyField('budget_from', property)">
                                    <label class="form-label-custom">Budget From <span v-if="isPropertyFieldRequired('budget_from')" class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">AED</span>
                                        <b-form-input 
                                            :value="property.budget_from"
                                            @update:modelValue="(val) => updateProperty(propIndex, 'budget_from', val)"
                                            type="number"
                                            placeholder="Min" 
                                            class="custom-input"
                                            :class="{ 'is-invalid': isPropertyFieldInvalid(property, 'budget_from') }"
                                        />
                                    </div>
                                </div>
                                
                                <div class="col-md-6" v-if="shouldShowPropertyField('budget_to', property)">
                                    <label class="form-label-custom">Budget To <span v-if="isPropertyFieldRequired('budget_to')" class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">AED</span>
                                        <b-form-input 
                                            :value="property.budget_to"
                                            @update:modelValue="(val) => updateProperty(propIndex, 'budget_to', val)"
                                            type="number"
                                            placeholder="Max" 
                                            class="custom-input"
                                            :class="{ 'is-invalid': isPropertyFieldInvalid(property, 'budget_to') }"
                                        />
                                    </div>
                                </div>
                                
                                <!-- Property Documents (Payment Proof + SPA — same idea as Create Deal / PropertyCard) -->
                                <div class="col-12 mt-3 property-documents-block">
                                    <label class="section-title mb-2">Property Documents</label>
                                    <DocumentUpload
                                        :modelValue="propertyDocumentsCombined[propIndex]"
                                        @update:modelValue="(val) => updatePropertyDocuments(propIndex, val)"
                                        category="property"
                                        :document-types="propertyDocTypesForModal"
                                        compact
                                        :show-errors="validationAttempted"
                                        :missing-document-types="missingPropertyDocumentTypes"
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
                :class="{ 'has-required': hasRequiredInSection('financials') }"
                @click="toggleSection('financials')"
              >
                <iconify-icon :icon="isSectionOpen('financials') ? 'lucide:chevron-down' : 'lucide:chevron-right'" class="collapse-icon"></iconify-icon>
                <h6 class="section-title mb-0">Deal Financials</h6>
                <span v-if="hasRequiredInSection('financials')" class="required-badge">Required</span>
              </div>
              
              <div v-show="isSectionOpen('financials')" class="section-content">
                <div class="form-card p-3 radius-12">
                  <div class="row g-3">
                    <div class="col-md-6" v-if="shouldShowField('deal_total_amount')">
                      <label class="form-label-custom">Deal amount <span v-if="hasField('deal_total_amount')" class="text-danger">*</span></label>
                      <div class="input-group">
                        <span class="input-group-text">AED</span>
                        <b-form-input
                          v-model="formData.deal_total_amount"
                          type="number"
                          placeholder="Enter deal amount"
                          class="custom-input compact-placeholder-field"
                          :class="{ 'is-invalid': isFieldInvalid('deal_total_amount') }"
                        />
                      </div>
                    </div>
                    <div class="col-md-6" v-if="shouldShowField('deal_commission')">
                      <label class="form-label-custom">Deal Commission % <span v-if="hasField('deal_commission')" class="text-danger">*</span></label>
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
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { BFormInput, BSpinner } from 'bootstrap-vue-3'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import DocumentUpload from './DocumentUpload.vue'
import AdvancedDatePicker from '@/components/shared/AdvancedDatePicker.vue'
import CrmPhoneInput from '@/components/common/CrmPhoneInput.vue'
import api from '@/plugins/axios'
import { isNonEmptyPhoneValid } from '@/utils/phone'
import { normalizeLanguageSelection } from '@/composables/useLanguageMultiSelect'
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
  missingFields: { type: Array, default: () => [] },
  missingFieldsGrouped: { type: Object, default: () => ({ sections: [] }) },
  missingFieldsGroupedByStage: { type: Object, default: () => ({ stages: [] }) },
  groupedMissing: { type: Object, default: () => ({ sections: [], by_stage: [] }) },
  deal: { type: Object, default: null }
})

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

function toggleSection(section) {
  openSections.value[section] = !openSections.value[section]
}

function isSectionOpen(section) {
  return openSections.value[section] !== false
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
// تعديل showBudgetFields - تشمل الحقول دي أياً كان الـ stage
const showBudgetFields = computed(() => {
  // لو الحقول دي موجودة في missing fields، اظهرها
  const missingKeys = effectiveMissingFields.value || []
  const hasBudgetFrom = missingKeys.some(key => key.includes('budget_from'))
  const hasBudgetTo = missingKeys.some(key => key.includes('budget_to'))
  
  if (hasBudgetFrom || hasBudgetTo) return true
  
  const stageName = props.targetStageName?.toLowerCase() || ''
  return stageName.includes('eoi')
})

const showPurchasePrice = computed(() => {
  const stageName = props.targetStageName?.toLowerCase() || ''
  return stageName.includes('booking') || stageName.includes('spa') || stageName.includes('won')
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

// Check if property field is required for ANY property
function isPropertyFieldRequired(fieldName) {
  const missingKeys = effectiveMissingFields.value || []
  
  console.log('Checking property field:', fieldName)
  console.log('All missing keys:', missingKeys)
  
  // Check if any missing key matches pattern property_X_fieldName
  const result = missingKeys.some(key => {
    const match = key.match(/property_\d+_(.+)/)
    if (match) {
      const field = match[1]
      return field === fieldName
    }
    // Also check for property_fieldName without index
    if (key === `property_${fieldName}`) {
      return true
    }
    return false
  })
  
  console.log(`Field ${fieldName} is required:`, result)
  return result
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
  const isRequired = isPropertyFieldRequired(fieldName)
  if (!isRequired) return false
  
  const value = property[fieldName]
  return value === null || value === undefined || value === ''
}

// Update property
function updateProperty(propIndex, field, value) {
  if (dealProperties.value[propIndex]) {
    dealProperties.value[propIndex][field] = value
    
    if (!formData.value.properties) {
      formData.value.properties = []
    }
    formData.value.properties[propIndex] = { ...dealProperties.value[propIndex] }
  }
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

const emit = defineEmits(['save', 'closed'])

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

const dealProperties = computed(() => {
  return localProperties.value
})

// Helper functions
function getDealTypeName(type) {
  const types = {
    primary: 'Primary',
    secondary: 'Secondary',
    rental: 'Rental'
  }
  return types[type] || type
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

// Get existing field value from deal
function getExistingFieldValue(key) {
  const deal = props.deal
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
  if (!props.deal?.parties) return []
  
  const party = props.deal.parties.find(p => p.party_type === partyType)
  if (!party?.documents) return []
  
  return party.documents.map(doc => ({
    file: doc.file || doc.url || null,
    url: doc.url || null,
    document_type: doc.document_type,
    name: doc.name || doc.document_type,
    size: doc.size || 0,
    isUploading: false,
    uploaded: true,
    existing: true
  }))
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
function updatePropertyDocuments(propIndex, newDocuments) {
    // تحديث الحالة المحلية
    propertyDocumentsCombined.value[propIndex] = newDocuments
    
    // تحديث property في localProperties
    if (localProperties.value[propIndex]) {
        // تجميع المستندات حسب النوع
        const paymentProofs = newDocuments.filter(doc => 
            doc.document_type === 'payment_proof' || 
            (doc.file && doc.original_name && doc.original_name.includes('payment'))
        )
        
        const spaDocuments = newDocuments.filter(doc => 
            doc.document_type === 'spa_document' || 
            doc.document_type === 'spa' ||
            (doc.file && doc.original_name && doc.original_name.includes('spa'))
        )
        
        localProperties.value[propIndex].payment_proof = paymentProofs
        localProperties.value[propIndex].spa_document = spaDocuments
    }
    
    // تحديث formData
    if (!formData.value.properties) {
        formData.value.properties = []
    }
    formData.value.properties[propIndex] = { ...localProperties.value[propIndex] }
}

// Re-initialize property documents - للاستدعاء عند تغيير البيانات
function reinitializePropertyDocuments() {
    if (!localProperties.value.length) return
    
    localProperties.value.forEach((property, idx) => {
        const paymentProof = property.payment_proof || []
        const spaDocument = property.spa_document || []
        
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
        } else if (typeof paymentProof === 'string' && paymentProof) {
            try {
                const parsed = JSON.parse(paymentProof)
                if (Array.isArray(parsed)) {
                    parsed.forEach(doc => {
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
            } catch(e) {
                console.error('Error parsing payment_proof:', e)
            }
        }
        
        // معالجة spa_document
        if (Array.isArray(spaDocument)) {
            spaDocument.forEach(doc => {
                docs.push({
                    ...doc,
                    document_type: 'spa_document',
                    url: doc.url || doc.path || null,
                    file: doc.file || null,
                    name: doc.original_name || doc.name || 'SPA Document',
                    uploaded: true,
                    existing: true
                })
            })
        } else if (typeof spaDocument === 'string' && spaDocument) {
            try {
                const parsed = JSON.parse(spaDocument)
                if (Array.isArray(parsed)) {
                    parsed.forEach(doc => {
                        docs.push({
                            ...doc,
                            document_type: 'spa_document',
                            url: doc.url || doc.path || null,
                            file: doc.file || null,
                            name: doc.original_name || doc.name || 'SPA Document',
                            uploaded: true,
                            existing: true
                        })
                    })
                }
            } catch(e) {
                console.error('Error parsing spa_document:', e)
            }
        }
        
        propertyDocumentsCombined.value[idx] = docs
    })
}

// Force refresh property documents - للاستدعاء بعد تحميل البيانات
function forceRefreshPropertyDocuments() {
    setTimeout(() => {
        reinitializePropertyDocuments()
    }, 100)
}
// Initialize form
async function initializeForm() {
  loading.value = true
  try {
    const missingFieldKeys = effectiveMissingFields.value || []
    console.log('Initializing form with missing fields:', missingFieldKeys)
    
    const initial = {}
    const deal = props.deal
    
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
            if (field === 'date_of_birth') value = party.date_of_birth
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

    if (props.deal?.properties && props.deal.properties.length > 0) {
      localProperties.value = JSON.parse(JSON.stringify(props.deal.properties))
      console.log('Loaded existing properties:', localProperties.value)
    } else if (requiresProperties) {
      console.log('Creating default property')
      localProperties.value = [{
        id: Date.now(),
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

    if (localProperties.value.length > 0) {
      formData.value.properties = [...localProperties.value]
    }
    
    // Initialize property documents
if (localProperties.value.length > 0) {
    reinitializePropertyDocuments()
}
    
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

// Get required documents by residency
function getRequiredDocumentsByResidency(residencyStatus) {
  const status = residencyStatus?.toLowerCase()
  return status === 'resident' ? ['passport', 'national_id'] : ['passport']
}

// Computed for document types
// Document types by party with deal type filtering
const documentTypesByParty = computed(() => {
  const result = { buyer: [], seller: [], tenant: [], landlord: [] }
  // استخدم القائمة المفلترة هنا
  const missingKeys = effectiveMissingFields.value || [] 

  missingKeys.forEach(key => {
    if (key.includes('_document_')) {
      const [partyType, docType] = key.split('_document_')
      if (result[partyType]) {
        result[partyType].push({
          id: docType,
          name: docType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
          required: true
        })
      }
    }
  })

  // إضافة مستندات بناءً على حالة الإقامة ولكن فقط للأطراف المسموح بها
  const parties = ['buyer', 'seller', 'tenant', 'landlord']
  parties.forEach(party => {
    // تحقق أولاً: هل هذا الطرف مسموح به في نوع الصفقة الحالي؟
    // يمكنك إضافة متغير محسوب (computed) جديد للتحقق من ذلك، أو استخدام المنطق الموجود في shouldHide...
    // لنفترض أنك أضفت computed property باسم `isPartyAllowed(party)`
    if (!isPartyAllowed(party)) return; // تخطى إذا كان الطرف غير مسموح به

    const residencyStatus = formData.value?.[`${party}_residency_status`]
    const requiredDocs = getRequiredDocumentsByResidency(residencyStatus)
    
    requiredDocs.forEach(docType => {
      if (!result[party].some(doc => doc.id === docType)) {
        result[party].push({
          id: docType,
          name: docType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
          required: true
        })
      }
    })
  })
  
  return result
})

// أضف هذه الدالة المساعدة
function isPartyAllowed(party) {
    const dealType = props.dealType || 'primary'
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
  
  return result
})

// Check if field is required (stage rules)
function hasField(fieldKey) {
  const missingKeys = effectiveMissingFields.value || []
  return missingKeys.includes(fieldKey)
}

/** Show full party / deal forms (not only “missing” keys) so users can see and edit existing data. */
function shouldShowField(fieldKey) {
  if (!fieldKey) return false
  if (fieldKey === 'lost_reason') return hasField('lost_reason')
  if (hasField(fieldKey)) return true
  const dt = props.dealType || 'primary'
  if (fieldKey.startsWith('buyer_')) return dt === 'primary' || dt === 'secondary'
  if (fieldKey.startsWith('seller_')) return dt === 'secondary'
  if (fieldKey.startsWith('tenant_')) return dt === 'rental'
  if (fieldKey.startsWith('landlord_')) return dt === 'rental'
  if (['deal_commission', 'deal_total_amount'].includes(fieldKey)) return true
  return false
}

function showPartyDetailFields(partyType) {
  const dt = props.dealType || 'primary'
  if (partyType === 'buyer') return dt === 'primary' || dt === 'secondary'
  if (partyType === 'seller') return dt === 'secondary'
  if (partyType === 'tenant') return dt === 'rental'
  if (partyType === 'landlord') return dt === 'rental'
  return false
}

function shouldShowPropertyField(fieldName, property) {
   if (fieldName === 'budget_from' || fieldName === 'budget_to') {
    return showBudgetFields.value
  }
  const dt = props.dealType || 'primary'
  switch (fieldName) {
    case 'unit_no':
    case 'property_type_id':
    case 'area_id':
    case 'unit_size':
    case 'developer_id':
    case 'developer_name':
    case 'developer_phone':
      return true
    case 'bedrooms':
      return showBedroomsForProperty(property)
    case 'rental_price':
      return dt === 'rental'
    case 'purchase_price':
      return showPurchasePrice.value && dt !== 'rental'
    case 'budget_from':
    case 'budget_to':
      return showBudgetFields.value
    default:
      return isPropertyFieldRequired(fieldName)
  }
}

const propertyDocTypesForModal = computed(() => {
  const mk = effectiveMissingFields.value || []
  const paymentRequired = mk.some(
    (k) =>
      k.includes('property_document_payment') ||
      (k.includes('payment_proof') && k.includes('property'))
  )
  const spaRequired = mk.some(
    (k) =>
      k.includes('property_document_spa') ||
      k.includes('spa_document') ||
      (k.includes('spa') && k.includes('property'))
  )
  return [
    { id: 'payment_proof', name: 'Payment Proof', required: paymentRequired },
    { id: 'spa', name: 'SPA Document', required: spaRequired }
  ]
})

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

const shouldShowFinancialSection = computed(() =>
  ['deal_commission', 'deal_total_amount'].some((k) => shouldShowField(k))
)

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
const effectiveMissingFields = computed(() => {
  const direct = Array.isArray(props.missingFields) ? props.missingFields : []
  const byStage = props.groupedMissing?.by_stage || props.missingFieldsGroupedByStage?.stages || []
  
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
            cumulative.add(field)
          }
        })
      }
    })
    
    direct.forEach(key => cumulative.add(key))
    allFields = Array.from(cumulative)
  }
  
  // ✅ فلترة صارمة حسب نوع الصفقة
  const dealType = props.dealType || 'primary'
  
  const filteredFields = allFields.filter(field => {
    if (typeof field !== 'string') return true
              
          if (dealType === 'primary') {
            // إخفاء Seller, Tenant, Landlord
            if (field.startsWith('seller_') || field.includes('seller_document_')) return false
            if (field.startsWith('tenant_') || field.includes('tenant_document_')) return false
            if (field.startsWith('landlord_') || field.includes('landlord_document_')) return false
            // Buyer و Properties مسموح بها
            return true
          }

          if (dealType === 'secondary') {
            // إخفاء Tenant, Landlord
            if (field.startsWith('tenant_') || field.includes('tenant_document_')) return false
            if (field.startsWith('landlord_') || field.includes('landlord_document_')) return false
            // Buyer, Seller, Properties مسموح بها
            return true
          }

          if (dealType === 'rental') {
            // إخفاء Buyer, Seller
            if (field.startsWith('buyer_') || field.includes('buyer_document_')) return false
            if (field.startsWith('seller_') || field.includes('seller_document_')) return false
            // Tenant, Landlord, Properties مسموح بها
            return true
          }
    
    return true
  })
  
  console.log('=== effectiveMissingFields Debug ===')
  console.log('Deal Type:', dealType)
  console.log('All fields before filter:', allFields)
  console.log('Filtered fields:', filteredFields)
  console.log('===================================')
  
  return filteredFields
})

// Unresolved missing keys for submit button
const unresolvedMissingKeys = computed(() => {
  const unresolved = []
  const missingKeys = effectiveMissingFields.value || []
       const isEoiStage = props.targetStageName?.toLowerCase().includes('eoi')

  missingKeys.forEach(key => {
  
    if (!isEoiStage && (key.includes('budget_from') || key.includes('budget_to'))) {
      return 
    }
    if (key.includes('_document_')) {
      const [partyType, docType] = key.split('_document_')
      const docs = formData.value?.[`${partyType}_documents`] || []
      const hasDoc = Array.isArray(docs) && docs.some(doc => 
        (doc?.file || doc?.url) && doc?.document_type === docType
      )
      if (!hasDoc) {
        unresolved.push(key)
      }
    } else if (key.includes('property_')) {
      // Handle property fields - check against localProperties
      const match = key.match(/property_(\d+)_(.+)/)
      if (match) {
        const propIndex = parseInt(match[1])
        const fieldName = match[2]
        const property = localProperties.value[propIndex]
        if (property) {
          const value = property[fieldName]
          if (value === null || value === undefined || value === '') {
            unresolved.push(key)
          }
        } else {
          unresolved.push(key)
        }
      } else {
        unresolved.push(key)
      }
    } else if (key === 'at_least_one_property') {
      if (localProperties.value.length === 0) {
        unresolved.push(key)
      }
    } else {
      const value = formData.value?.[key]
      const isEmpty = value === null || value === undefined || value === ''
      if (isEmpty) {
        unresolved.push(key)
      }
    }
  })
  
  return unresolved
})

const canSubmit = computed(() => {
  return !loading.value && !submitting.value && unresolvedMissingKeys.value.length === 0
})

// Submit form — validate on click: scroll to first error, then save when complete
async function submitForm() {
  if (loading.value || submitting.value) return

  validationAttempted.value = true
  await nextTick()

  if (unresolvedMissingKeys.value.length > 0) {
    const modalEl = document.querySelector('.complete-fields-modal')
    const firstInvalid = modalEl?.querySelector('.is-invalid')
    firstInvalid?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    return
  }

  submitting.value = true

  const payload = {}
  const documents = []
  
  // Collect regular fields
  Object.keys(formData.value).forEach(key => {
    if (!key.includes('_documents') && 
        formData.value[key] !== null && 
        formData.value[key] !== undefined && 
        formData.value[key] !== '') {
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
  
  docRefs.forEach(({ ref, party, key }) => {
    if (ref.value?.modelValue) {
      ref.value.modelValue.forEach(doc => {
        if (doc.file) {
          documents.push({
            file: doc.file,
            document_type: doc.document_type,
            category: party,
            party_type: party
          })
        }
      })
    }
    
    if (formData.value[key] && Array.isArray(formData.value[key])) {
      formData.value[key].forEach(doc => {
        if (doc.file) {
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
if (localProperties.value.length > 0) {
    localProperties.value.forEach((property, propIndex) => {
        const combinedDocs = propertyDocumentsCombined.value[propIndex]
        if (combinedDocs && Array.isArray(combinedDocs)) {
            combinedDocs.forEach(doc => {
                // تحقق إذا كان الملف جديد (File object) أو موجود مسبقاً
                if (doc.file && doc.file instanceof File) {
                    documents.push({
                        file: doc.file,
                        document_type: doc.document_type || 'payment_proof',
                        category: 'property',
                        property_id: property.id,
                        property_index: propIndex
                    })
                }
                // إذا كان المستند موجود مسبقاً وله URL، لا نضيفه مرة أخرى
            })
        }
    })
}
  
  // Add properties to payload
  if (localProperties.value.length > 0) {
    payload.properties = localProperties.value.map((prop, index) => ({
      sort_order: index,
      unit_no: prop.unit_no || '',
      property_type_id: prop.property_type_id || null,
      bedrooms: prop.bedrooms || null,
      unit_size: prop.unit_size || '',
      area_id: prop.area_id || null,
      developer_id: prop.developer_id || null,
      developer_name: prop.developer_name || '',
      developer_phone: prop.developer_phone || '',
      budget_from: prop.budget_from || null,
      budget_to: prop.budget_to || null,
      purchase_price: prop.purchase_price || null,
      commission: prop.commission || null,
      payment_proof: (prop.payment_proof || [])
        .filter(doc => doc?.file instanceof File)
        .map(doc => doc.file),
      spa_document: (prop.spa_document || [])
        .filter(doc => doc?.file instanceof File)
        .map(doc => doc.file),
    }))
  }

  console.log('Final payload:', { payload, documents, stage_id: props.targetStageId })
  
  emit('save', { payload, documents, stage_id: props.targetStageId })

  submitResetTimer = setTimeout(() => {
    submitting.value = false
    submitResetTimer = null
  }, 12000)
}

// Close modal
function closeModal() {
  validationAttempted.value = false
  formData.value = {}
  localProperties.value = []
  submitting.value = false
  if (submitResetTimer) {
    clearTimeout(submitResetTimer)
    submitResetTimer = null
  }
  emit('closed')
}

// Watch for modal show
watch(() => props.show, async (val) => {
    if (val) {
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
    } else {
        submitting.value = false
        if (submitResetTimer) {
            clearTimeout(submitResetTimer)
            submitResetTimer = null
        }
    }
})
const isLoadingComplete = computed(() => {
    return loading.value || isLoadingPropertyData.value
})
watch(localProperties, () => {
    if (localProperties.value.length > 0) {
        reinitializePropertyDocuments()
    }
}, { deep: true, immediate: true })
// Options for selects
const languageOptions = [
  { value: 'english', text: 'English' }, { value: 'arabic', text: 'Arabic' },
  { value: 'french', text: 'French' }, { value: 'spanish', text: 'Spanish' },
  { value: 'hindi', text: 'Hindi' }, { value: 'urdu', text: 'Urdu' }
]

const nationalityOptions = [
  { value: 'emirati', text: 'Emirati' }, { value: 'saudi', text: 'Saudi' },
  { value: 'egyptian', text: 'Egyptian' }, { value: 'indian', text: 'Indian' },
  { value: 'british', text: 'British' }, { value: 'american', text: 'American' }
]

const residencyOptions = [
  { value: 'resident', text: 'Resident' },
  { value: 'non_resident', text: 'Non Resident' }
]

const buyerResidencyOptions = [
  { value: 'resident', text: 'Resident' },
  { value: 'non_resident', text: 'Non Resident' }
]

const countryOptions = [
  { value: "United Arab Emirates", text: "United Arab Emirates" },
  { value: "Saudi Arabia", text: "Saudi Arabia" },
  { value: "Egypt", text: "Egypt" },
  { value: "India", text: "India" },
  { value: "United Kingdom", text: "United Kingdom" },
  { value: "United States", text: "United States" }
]

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
  const dealType = props.dealType || 'primary'
  // Rental: إخفاء Buyer
  if (dealType === 'rental') return true
  // Primary و Secondary: إظهار Buyer
  return false
})

const shouldHideSeller = computed(() => {
  const dealType = props.dealType || 'primary'
  // في Primary، أخفِ Seller
  if (dealType === 'primary' || dealType === 'rental') return true
  return hasListingId.value && formData.value?.deal_type === 'secondary'
})

const shouldHideLandlord = computed(() => {
  const dealType = props.dealType || 'primary'
  // في Primary، أخفِ Landlord
  if (dealType === 'primary' || dealType === 'secondary') return true
  return hasListingId.value && formData.value?.deal_type === 'rental'
})

const shouldHideTenant = computed(() => {
  const dealType = props.dealType || 'primary'
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
  z-index: 1060;
  backdrop-filter: blur(2px);
}

.complete-fields-modal {
  background: white;
  border-radius: 10px;
  width: min(760px, 94vw);
  max-width: 94vw;
  max-height: 90vh;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  border: 1px solid rgba(0, 0, 0, 0.08);
}

.modal-header-deal {
  border-bottom: 1px solid #F4F4F4;
  flex-shrink: 0;
  padding: 14px 18px !important;
}

.modal-title {
  font-weight: 500;
  font-size: 14px;
  color: #01062C;
}

.close-btn {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  cursor: pointer;
  color: #64748B;
  display: flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  border: none;
}

.close-btn:hover {
  background: #F1F5F9;
  color: #1E293B;
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
  font-size: 10px;
  color: #9ca3af;
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
</style>