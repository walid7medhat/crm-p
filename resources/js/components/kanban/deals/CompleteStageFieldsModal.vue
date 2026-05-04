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
          <div class="deal-progress-label">Pipeline</div>
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
            <!-- <div v-if="missingFieldLabels.length > 0" class="alert alert-info py-2 mb-3">
              <div class="small fw-semibold mb-1">Missing required data:</div>
              <div class="small">
                {{ missingFieldLabels.join(' • ') }}
              </div>
            </div> -->
            <!-- <div>
              {{ unresolvedMissingLabels.join(' • ') }}
            </div> -->
            <!-- Source and Deal Name Section -->
            <!-- <section v-if="hasSourceAndDealNameFields()" class="form-section">
              <h6 class="section-title mb-3">Source and Deal Name</h6>
              <div class="form-card p-3 radius-12">
                <div class="row g-3">
                  <div class="col-md-6" v-if="hasField('source')">
                    <label class="form-label-custom">Source <span class="text-danger">*</span></label>
                    <v-select
                      append-to-body 
                      v-model="formData.source" 
                      :options="sources" 
                      :reduce="item => item.name" 
                      label="name" 
                      placeholder="Select Source" 
                      class="custom-v-select"
                      :class="{ 'is-invalid': isFieldInvalid('source') }"
                    >
                      <template #open-indicator="{ attributes }">
                        <span v-bind="attributes">
                          <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                        </span>
                      </template>
                    </v-select>
                  </div>
                  <div class="col-md-6" v-if="hasField('deal_name')">
                    <label class="form-label-custom">Deal Name <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.deal_name" 
                      placeholder="Enter Deal Name" 
                      class="custom-input"
                      :class="{ 'is-invalid': isFieldInvalid('deal_name') }"
                    />
                  </div>
                </div>
              </div>
            </section> -->

            <!-- Lost Reason Section -->
            <section v-if="hasField('lost_reason')" class="form-section">
              <div class="form-card p-3 radius-12">
                <label class="form-label-custom">Enter Reason For Deal Lost</label>
                <textarea
                  v-model="formData.lost_reason"
                  class="lost-reason-textarea"
                  :class="{ 'is-invalid': isFieldInvalid('lost_reason') }"
                  placeholder="Text Here"
                  rows="4"
                ></textarea>
              </div>
            </section>

            <!-- Buyer Section -->
            <section v-if="hasPartyFields('buyer') || documentTypesByParty.buyer.length > 0" class="form-section">
              <h6 class="section-title mb-3" v-if="hasPartyFields('buyer')">Buyer Details</h6>
              <div class="form-card p-3 radius-12" v-if="hasPartyFields('buyer')">
                <div class="row g-3">
                  <div class="col-md-6" v-if="hasField('buyer_first_name')">
                    <label class="form-label-custom">Buyer First Name <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.buyer_first_name" 
                      placeholder="Enter First Name" 
                      class="custom-input"
                      :class="{ 'is-invalid': isFieldInvalid('buyer_first_name') }"
                    />
                  </div>
                  
                  <div class="col-md-6" v-if="hasField('buyer_last_name')">
                    <label class="form-label-custom">Buyer Last Name <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.buyer_last_name" 
                      placeholder="Enter Last Name" 
                      class="custom-input compact-placeholder-field"
                      :class="{ 'is-invalid': isFieldInvalid('buyer_last_name') }"
                    />
                  </div>
                  
                  <div class="col-md-6" v-if="hasField('buyer_phone')">
                    <label class="form-label-custom">Buyer Phone Number <span class="text-danger">*</span></label>
                    <CrmPhoneInput 
                      v-model="formData.buyer_phone" 
                      placeholder="Enter Phone Number" 
                      :invalid="isFieldInvalid('buyer_phone')"
                      :show-errors="isFieldInvalid('buyer_phone')"
                    />
                  </div>
                  
                  <div class="col-md-6" v-if="hasField('buyer_email')">
                    <label class="form-label-custom">Buyer Email <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.buyer_email" 
                      type="email" 
                      placeholder="Enter Your Email" 
                      class="custom-input"
                      :class="{ 'is-invalid': isFieldInvalid('buyer_email') }"
                    />
                  </div>
                  
                  <div class="col-md-6" v-if="hasField('buyer_nationality')">
                    <label class="form-label-custom">Buyer Nationality <span class="text-danger">*</span></label>
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
                  
                  <div class="col-md-6" v-if="hasField('buyer_residency_status') || (documentTypesByParty.buyer.length > 0 && ['primary', 'secondary'].includes(effectiveDealTypeForDocs))">
                    <label class="form-label-custom">Buyer Residency Status <span class="text-danger">*</span></label>
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
                  
                  <div class="col-md-6" v-if="hasField('buyer_country')">
                    <label class="form-label-custom">Buyer Country Of Residence <span class="text-danger">*</span></label>
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
                  
                  <div class="col-md-6" v-if="hasField('buyer_city')">
                    <label class="form-label-custom">Buyer City Of Residence <span class="text-danger">*</span></label>
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

                  <div class="col-md-6" v-if="hasField('buyer_dob')">
                    <label class="form-label-custom">Buyer Date Of Birth <span class="text-danger">*</span></label>
                    <AdvancedDatePicker
                      v-model="formData.buyer_dob"
                      date-only
                      placeholder="Select date"
                      class="custom-input"
                      :invalid="isFieldInvalid('buyer_dob')"
                    />
                  </div>

                  <div class="col-md-6" v-if="hasField('buyer_language')">
                    <label class="form-label-custom">Buyer Language <span class="text-danger">*</span></label>
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
                  
                  
                  <div class="col-12" v-if="hasField('buyer_party')">
                    <div class="alert alert-warning py-2 mb-0">
                      <iconify-icon icon="lucide:alert-triangle" class="me-2"></iconify-icon>
                      <span class="small">Buyer information is required. Please add buyer details.</span>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Buyer Documents -->
              <div
                v-if="documentTypesByParty.buyer.length > 0"
               
                :class="hasPartyFields('buyer') ? 'mt-3' : 'mt-0'"
              >
                <label class="section-title  mb-3">Buyer Documents</label>
                
                <DocumentUpload
                  v-model="formData.buyer_documents"
                  category="buyer"
                  compact
                  :document-types="documentTypesByParty.buyer"
                  :show-errors="true"
                  :missing-document-types="missingDocumentTypesByParty.buyer"
                  ref="buyerDocUploadRef"
                   class="form-card p-3 radius-12"
                />
              </div>
            </section>

            <!-- Seller Section -->
            <section v-if="!shouldHideSeller && (hasPartyFields('seller') || documentTypesByParty.seller.length > 0)" class="form-section">
              <h6 class="section-title mb-3" v-if="hasPartyFields('seller')">Seller Details</h6>
              <div class="form-card p-3 radius-12" v-if="hasPartyFields('seller')">
                <div class="row g-3">
                  <div class="col-md-4" v-if="hasField('seller_first_name')">
                    <label class="form-label-custom">First Name <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.seller_first_name" 
                      placeholder="Enter First Name" 
                      class="custom-input"
                      :class="{ 'is-invalid': isFieldInvalid('seller_first_name') }"
                    />
                  </div>
                  
                  <div class="col-md-4" v-if="hasField('seller_last_name')">
                    <label class="form-label-custom">Last Name <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.seller_last_name" 
                      placeholder="Enter Last Name" 
                      class="custom-input"
                      :class="{ 'is-invalid': isFieldInvalid('seller_last_name') }"
                    />
                  </div>
                  
                  <div class="col-md-4" v-if="hasField('seller_dob')">
                    <label class="form-label-custom">Date Of Birth <span class="text-danger">*</span></label>
                    <AdvancedDatePicker
                      v-model="formData.seller_dob"
                      date-only
                      placeholder="Select date"
                      class="custom-input"
                      :invalid="isFieldInvalid('seller_dob')"
                    />
                  </div>
                  
                  <div class="col-md-4" v-if="hasField('seller_phone')">
                    <label class="form-label-custom">Phone <span class="text-danger">*</span></label>
                    <CrmPhoneInput 
                      v-model="formData.seller_phone" 
                      placeholder="Enter Phone" 
                      :invalid="isFieldInvalid('seller_phone')"
                      :show-errors="isFieldInvalid('seller_phone')"
                    />
                  </div>
                  
                  <div class="col-md-4" v-if="hasField('seller_email')">
                    <label class="form-label-custom">Email <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.seller_email" 
                      type="email" 
                      placeholder="Enter Email" 
                      class="custom-input"
                      :class="{ 'is-invalid': isFieldInvalid('seller_email') }"
                    />
                  </div>
                  
                  <div class="col-md-4" v-if="hasField('seller_nationality')">
                    <label class="form-label-custom">Nationality <span class="text-danger">*</span></label>
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
                  
                  <div class="col-md-4" v-if="hasField('seller_residency_status')">
                    <label class="form-label-custom">Residency Status <span class="text-danger">*</span></label>
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
                  
                  <div class="col-md-4" v-if="hasField('seller_country')">
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
                  
                  <div class="col-md-4" v-if="hasField('seller_city')">
                    <label class="form-label-custom">City Of Residence <span class="text-danger">*</span></label>
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
                  
                  <div class="col-md-4" v-if="hasField('seller_language')">
                    <label class="form-label-custom">Language <span class="text-danger">*</span></label>
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
                  
                  <div class="col-12" v-if="hasField('seller_party')">
                    <div class="alert alert-warning py-2 mb-0">
                      <iconify-icon icon="lucide:alert-triangle" class="me-2"></iconify-icon>
                      <span class="small">Seller information is required. Please add seller details.</span>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Seller Documents -->
              <div class="mt-3" v-if="documentTypesByParty.seller.length > 0">
                <label class="section-title  mb-3">Seller Documents</label>
                <DocumentUpload
                  v-model="formData.seller_documents"
                  category="seller"
                  compact
                  :document-types="documentTypesByParty.seller"
                  :show-errors="true"
                  :missing-document-types="missingDocumentTypesByParty.seller"
                  ref="sellerDocUploadRef"
                   class="form-card p-3 radius-12"
                />
              </div>
            </section>

            <!-- Tenant Section -->
            <section v-if="hasPartyFields('tenant') || documentTypesByParty.tenant.length > 0" class="form-section">
              <h6 class="section-title mb-3" v-if="hasPartyFields('tenant')">Tenant Details</h6>
              <div class="form-card p-3 radius-12" v-if="hasPartyFields('tenant')">
                <div class="row g-3">
                  <div class="col-md-4" v-if="hasField('tenant_first_name')">
                    <label class="form-label-custom">First Name <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.tenant_first_name" 
                      placeholder="Enter First Name" 
                      class="custom-input"
                      :class="{ 'is-invalid': isFieldInvalid('tenant_first_name') }"
                    />
                  </div>
                  
                  <div class="col-md-4" v-if="hasField('tenant_last_name')">
                    <label class="form-label-custom">Last Name <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.tenant_last_name" 
                      placeholder="Enter Last Name" 
                      class="custom-input"
                      :class="{ 'is-invalid': isFieldInvalid('tenant_last_name') }"
                    />
                  </div>
                  
                  <div class="col-md-4" v-if="hasField('tenant_phone')">
                    <label class="form-label-custom">Phone <span class="text-danger">*</span></label>
                    <CrmPhoneInput 
                      v-model="formData.tenant_phone" 
                      placeholder="Enter Phone" 
                      :invalid="isFieldInvalid('tenant_phone')"
                      :show-errors="isFieldInvalid('tenant_phone')"
                    />
                  </div>
                  
                  <div class="col-md-4" v-if="hasField('tenant_email')">
                    <label class="form-label-custom">Email <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.tenant_email" 
                      type="email" 
                      placeholder="Enter Email" 
                      class="custom-input"
                      :class="{ 'is-invalid': isFieldInvalid('tenant_email') }"
                    />
                  </div>
                  
                  <div class="col-md-4" v-if="hasField('tenant_nationality')">
                    <label class="form-label-custom">Nationality <span class="text-danger">*</span></label>
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
                  
                  <div class="col-md-4" v-if="hasField('tenant_residency_status')">
                    <label class="form-label-custom">Residency Status <span class="text-danger">*</span></label>
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
                  
                  <div class="col-md-4" v-if="hasField('tenant_country')">
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
                  
                  <div class="col-md-4" v-if="hasField('tenant_city')">
                    <label class="form-label-custom">City Of Residence <span class="text-danger">*</span></label>
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
                  
                  <div class="col-md-4" v-if="hasField('tenant_language')">
                    <label class="form-label-custom">Language <span class="text-danger">*</span></label>
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
                  
                  
                  <div class="col-12" v-if="hasField('tenant_party')">
                    <div class="alert alert-warning py-2 mb-0">
                      <iconify-icon icon="lucide:alert-triangle" class="me-2"></iconify-icon>
                      <span class="small">Tenant information is required. Please add tenant details.</span>
                    </div>
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
                  :show-errors="true"
                  :missing-document-types="missingDocumentTypesByParty.tenant"
                  ref="tenantDocUploadRef"
                   class="form-card p-3 radius-12"
                />
              </div>
            </section>

            <!-- Landlord Section -->
            <section v-if="!shouldHideTenant  && (hasPartyFields('landlord') || documentTypesByParty.landlord.length > 0)" class="form-section">
              <h6 class="section-title mb-3" v-if="hasPartyFields('landlord')">Landlord Details</h6>
              <div class="form-card p-3 radius-12" v-if="hasPartyFields('landlord')">
                <div class="row g-3">
                  <div class="col-md-4" v-if="hasField('landlord_first_name')">
                    <label class="form-label-custom">First Name <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.landlord_first_name" 
                      placeholder="Enter First Name" 
                      class="custom-input"
                      :class="{ 'is-invalid': isFieldInvalid('landlord_first_name') }"
                    />
                  </div>
                  
                  <div class="col-md-4" v-if="hasField('landlord_last_name')">
                    <label class="form-label-custom">Last Name <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.landlord_last_name" 
                      placeholder="Enter Last Name" 
                      class="custom-input"
                      :class="{ 'is-invalid': isFieldInvalid('landlord_last_name') }"
                    />
                  </div>
                  
                  <div class="col-md-4" v-if="hasField('landlord_dob')">
                    <label class="form-label-custom">Date Of Birth <span class="text-danger">*</span></label>
                    <AdvancedDatePicker
                      v-model="formData.landlord_dob"
                      date-only
                      placeholder="Select date"
                      class="custom-input"
                      :invalid="isFieldInvalid('landlord_dob')"
                    />
                  </div>
                  
                  <div class="col-md-4" v-if="hasField('landlord_phone')">
                    <label class="form-label-custom">Phone <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.landlord_phone" 
                      placeholder="Enter Phone" 
                      class="custom-input"
                      :class="{ 'is-invalid': isFieldInvalid('landlord_phone') }"
                    />
                  </div>
                  
                  <div class="col-md-4" v-if="hasField('landlord_email')">
                    <label class="form-label-custom">Email <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.landlord_email" 
                      type="email" 
                      placeholder="Enter Email" 
                      class="custom-input"
                      :class="{ 'is-invalid': isFieldInvalid('landlord_email') }"
                    />
                  </div>
                  
                  <div class="col-md-4" v-if="hasField('landlord_nationality')">
                    <label class="form-label-custom">Nationality <span class="text-danger">*</span></label>
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
                  
                  <div class="col-md-4" v-if="hasField('landlord_residency_status')">
                    <label class="form-label-custom">Residency Status <span class="text-danger">*</span></label>
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
                  
                  <div class="col-md-4" v-if="hasField('landlord_country')">
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
                  
                  <div class="col-md-4" v-if="hasField('landlord_city')">
                    <label class="form-label-custom">City Of Residence <span class="text-danger">*</span></label>
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
                  
                  <div class="col-md-4" v-if="hasField('landlord_language')">
                    <label class="form-label-custom">Language <span class="text-danger">*</span></label>
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
                  
                  <div class="col-12" v-if="hasField('landlord_party')">
                    <div class="alert alert-warning py-2 mb-0">
                      <iconify-icon icon="lucide:alert-triangle" class="me-2"></iconify-icon>
                      <span class="small">Landlord information is required. Please add landlord details.</span>
                    </div>
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
                  :show-errors="true"
                  :missing-document-types="missingDocumentTypesByParty.landlord"
                  ref="landlordDocUploadRef"
                   class="form-card p-3 radius-12"
                />
              </div>
            </section>

            <!-- Property Details Section -->
            <section v-if="hasPropertyFields()" class="form-section">
              <h6 class="section-title mb-3">Property Details</h6>
              <div class="form-card p-3 radius-12">
                <div class="row g-3">
                  <div class="col-md-6" v-if="hasLocationField()">
                    <label class="form-label-custom">Property Address <span class="text-danger">*</span></label>
                    <v-select
                      append-to-body 
                      v-model="locationSelectModel" 
                      :options="areas" 
                      :reduce="item => item.id" 
                      label="name" 
                      placeholder="Select Location..." 
                      class="custom-v-select"
                      :filterable="true"
                      :searchable="true"
                      :clearable="true"
                      :class="{ 'is-invalid': isFieldInvalid('area_id') || isFieldInvalid('subcommunity_id') }"
                      @search="onSearchAreas"
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

                  <div class="col-md-6" v-if="hasLocationField() && availableListings.length > 0">
                    <label class="form-label-custom">Select Unit <span class="text-danger">*</span></label>
                    <v-select
                      append-to-body 
                      v-model="selectedListing" 
                      :options="availableListings" 
                      :reduce="item => item" 
                      label="display_name" 
                      placeholder="Select a unit..." 
                      class="custom-v-select"
                      :class="{ 'is-invalid': isFieldInvalid('listing_id') }"
                      @update:modelValue="onListingSelected"
                      :disabled="isLoadingListings"
                    >
                      <template #option="option">
                        <div>
                          <strong>{{ option.unit_number || 'No Unit' }}</strong>
                          <span class="text-muted ms-2">- {{ option.property_type?.name || 'N/A' }}</span>
                          <div class="small text-muted">{{ option.bedrooms_text }} | {{ option.size_sqft || 'N/A' }} sqft</div>
                        </div>
                      </template>
                      <template #open-indicator="{ attributes }">
                        <span v-bind="attributes">
                          <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                        </span>
                      </template>
                    </v-select>
                    <div class="small text-muted mt-1" v-if="!isLoadingListings">
                      <iconify-icon icon="lucide:info" class="me-1"></iconify-icon>
                      Showing available units in this location
                    </div>
                    <div class="small text-muted mt-1" v-else>
                      <b-spinner small></b-spinner> Loading units...
                    </div>
                  </div>

                  <div class="col-md-6" v-if="hasField('unit_no')">
                    <label class="form-label-custom">Unit No <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.unit_no" 
                      placeholder="Enter Unit No" 
                      class="custom-input compact-placeholder-field"
                      :class="{ 'is-invalid': isFieldInvalid('unit_no') }"
                    />
                  </div>
                  
                  <div class="col-md-6" v-if="hasField('property_type_id')">
                    <label class="form-label-custom">Property Type <span class="text-danger">*</span></label>
                    <v-select
                      append-to-body 
                      v-model="formData.property_type_id" 
                      :options="propertyTypes" 
                      :reduce="item => item.id" 
                      label="name" 
                      placeholder="Select Property Type" 
                      class="custom-v-select"
                      :class="{ 'is-invalid': isFieldInvalid('property_type_id') }"
                    >
                      <template #open-indicator="{ attributes }">
                        <span v-bind="attributes">
                          <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                        </span>
                      </template>
                    </v-select>
                  </div>
                  
                  <div class="col-md-6" v-if="hasField('bedrooms')">
                    <label class="form-label-custom">Bedrooms</label>
                    <v-select
                      append-to-body 
                      v-model="formData.bedrooms" 
                      :options="bedroomOptions" 
                      :reduce="o => o.value" 
                      label="text" 
                      placeholder="Select Bedroom" 
                      class="custom-v-select"
                      :class="{ 'is-invalid': isFieldInvalid('bedrooms') }"
                    >
                      <template #open-indicator="{ attributes }">
                        <span v-bind="attributes">
                          <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                        </span>
                      </template>
                    </v-select>
                  </div>
                  
                  <div class="col-md-6" v-if="hasField('unit_size')">
                    <label class="form-label-custom">Unit Size (sq.ft)</label>
                    <b-form-input 
                      v-model="formData.unit_size" 
                      placeholder="Enter Unit Size" 
                      class="custom-input compact-placeholder-field"
                      :class="{ 'is-invalid': isFieldInvalid('unit_size') }"
                    />
                  </div>
                   <div class="col-md-6" v-if="hasField('developer_id')">
                      <label class="form-label-custom">Developer</label>
                      <v-select
                        append-to-body 
                        v-model="formData.developer_id" 
                        :options="developers" 
                        :reduce="item => item.id" 
                        label="name" 
                        placeholder="Developer" 
                        class="custom-v-select"
                        :class="{ 'is-invalid': isFieldInvalid('developer_id') }"
                      >
                        <template #open-indicator="{ attributes }">
                          <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                          </span>
                        </template>
                      </v-select>
                    </div>

                  <div class="col-md-6" v-if="hasField('developer_name')">
                    <label class="form-label-custom">Developer sales person name</label>
                    <b-form-input 
                      v-model="formData.developer_name" 
                      placeholder="Enter Developer Name" 
                      class="custom-input"
                      :class="{ 'is-invalid': isFieldInvalid('developer_name') }"
                    />
                  </div>

                  <div class="col-md-6" v-if="hasField('developer_phone')">
                    <label class="form-label-custom">Developer sales person phone</label>
                    <CrmPhoneInput 
                      v-model="formData.developer_phone" 
                      placeholder="Enter Developer Phone Number" 
                      :invalid="isFieldInvalid('developer_phone')"
                      :show-errors="isFieldInvalid('developer_phone')"
                    />
                  </div>
                </div>
              </div>
            </section>

            <!-- Deal Financials Section -->
            <section v-if="hasFinancialFields()" class="form-section">
              <h6 class="section-title mb-3">Deal Financials</h6>
              <div class="form-card p-3 radius-12">
                <div class="row g-3">
                  
                  <div class="col-md-4" v-if="hasField('deal_commission')">
                    <label class="form-label-custom">Deal Commission %</label>
                    <b-form-input 
                      v-model="formData.deal_commission" 
                      type="number" 
                      placeholder="Enter Commission %" 
                      class="custom-input compact-placeholder-field"
                      :class="{ 'is-invalid': isFieldInvalid('deal_commission') }"
                    />
                  </div>
                  
                  <div class="col-md-4" v-if="hasField('agent_share')">
                    <label class="form-label-custom">Agent Share %</label>
                    <b-form-input 
                      v-model="formData.agent_share" 
                      type="number" 
                      placeholder="Enter Agent Share %" 
                      class="custom-input"
                      :class="{ 'is-invalid': isFieldInvalid('agent_share') }"
                    />
                  </div>
                  
                  <div class="col-md-4" v-if="hasField('company_share')">
                    <label class="form-label-custom">Company Share %</label>
                    <b-form-input 
                      v-model="formData.company_share" 
                      type="number" 
                      placeholder="Enter Company Share %" 
                      class="custom-input"
                      :class="{ 'is-invalid': isFieldInvalid('company_share') }"
                    />
                  </div>
                </div>
              </div>
            </section>
            
            <!-- Responsible Person -->
            <!-- <div class="col-12 mt-3" v-if="hasField('responsible_person_id')">
              <ResponsiblePersonSelector 
                v-model="formData.responsible_person_id" 
                :users="users" 
                :class="{ 'is-invalid': isFieldInvalid('responsible_person_id') }"
              />
            </div> -->
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
            <button class="btn-next-step" @click="submitForm" :disabled="!canSubmit">
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
import { ref, computed, watch, onMounted } from 'vue'
import { BFormInput, BSpinner } from 'bootstrap-vue-3'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import DocumentUpload from './DocumentUpload.vue'
import ResponsiblePersonSelector from '../shared/ResponsiblePersonSelector.vue'
import AdvancedDatePicker from '@/components/shared/AdvancedDatePicker.vue'
import CrmPhoneInput from '@/components/common/CrmPhoneInput.vue'
import api from '@/plugins/axios'
import { isNonEmptyPhoneValid } from '@/utils/phone'
import { normalizeLanguageSelection } from '@/composables/useLanguageMultiSelect'

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
const hasListingId = ref(false)

const emit = defineEmits(['save', 'closed', 'open-deal'])
// Add this function to get existing data from the deal
function getExistingFieldValue(key) {
  const deal = props.deal
  if (!deal) return ''
  
  // Handle direct fields
  if (deal[key] !== undefined && deal[key] !== null) return deal[key]
  
  // Handle party fields (buyer, seller, tenant, landlord)
  const partyTypes = ['buyer', 'seller', 'tenant', 'landlord']
  for (const partyType of partyTypes) {
    if (key.startsWith(partyType + '_') && !key.includes('_document_')) {
      const party = deal.parties?.find(p => p.party_type === partyType)
      if (!party) return ''
      
      const field = key.replace(partyType + '_', '')
      // Map field names to party object properties
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
      if (field === 'amount') return ''
      return party[field] || ''
    }
  }
  
  return ''
}

// Add this function to get existing documents from the deal
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
/** Align API / deal payload values with primary | secondary | rental for document UI */
function normalizeDealTypeForDocuments(raw) {
  const s = String(raw ?? '')
    .toLowerCase()
    .trim()
    .replace(/\s+/g, '_')
  if (!s) return 'primary'
  if (s.includes('secondary') || s.includes('resale')) return 'secondary'
  if (s.includes('rental') || s.includes('lease')) return 'rental'
  if (
    s.includes('primary')
    || s.includes('off_plan')
    || s.includes('offplan')
    || s.includes('off-plan')
    || s === 'sale'
  ) {
    return 'primary'
  }
  if (s === 'primary' || s === 'secondary' || s === 'rental') return s
  return 'primary'
}

const effectiveDealTypeForDocs = computed(() =>
  normalizeDealTypeForDocuments(
    props.deal?.deal_type ?? props.deal?.type ?? props.dealType,
  ),
)

// State
const formData = ref({})
const submitting = ref(false)
const loading = ref(false)
let submitResetTimer = null
const invalidFields = ref(new Set())

// Data from API
const users = ref([])
const sources = ref([])
const propertyTypes = ref([])
const developers = ref([])
const areas = ref([])
// const projects = ref([])

// Document upload refs
const buyerDocUploadRef = ref(null)
const sellerDocUploadRef = ref(null)
const tenantDocUploadRef = ref(null)
const landlordDocUploadRef = ref(null)

const availableListings = ref([])
const selectedListing = ref(null)
const isLoadingListings = ref(false)
const currentUser = ref(null)

const locationFirstLine = (area) => {
    return area.name || ''
}

const locationSecondLine = (area) => {
    return area.area_parents_title || ''
}
const getCurrentUser = () => {
  try {
    const userData = localStorage.getItem('user')
    if (userData) {
      currentUser.value = JSON.parse(userData)
    }
  } catch (error) {
    console.error('Error getting user:', error)
  }
}

function normalizeLocationId(value) {
  if (value === null || value === undefined || value === '') return null
  if (typeof value === 'number') return value
  const asNumber = Number(value)
  return Number.isFinite(asNumber) ? asNumber : value
}

async function initializeForm() {
  loading.value = true
  try {
    const missingFieldKeys = effectiveMissingFields.value || []
    console.log('Initializing form with missing fields:', missingFieldKeys)
    
    const initial = {}
    
    // For ALL fields that exist in the deal (not just missing ones)
    // First, get all possible field keys from the deal
    const deal = props.deal
    if (deal) {
      // Add direct fields
      Object.keys(deal).forEach(key => {
        if (deal[key] !== null && deal[key] !== undefined && 
            !key.includes('parties') && !key.includes('documents') &&
            typeof deal[key] !== 'object') {
          initial[key] = deal[key]
        }
      })
      
      // Add party fields
      const partyTypes = ['buyer', 'seller', 'tenant', 'landlord']
      partyTypes.forEach(partyType => {
        const party = deal.parties?.find(p => p.party_type === partyType)
        if (party) {
          // Add all party fields
          const partyFields = ['first_name', 'last_name', 'phone', 'email', 'nationality', 
                               'date_of_birth', 'residency_status', 'city', 'country', 
                               'language', 'amount']
          partyFields.forEach(field => {
            const key = `${partyType}_${field}`
            let value = party[field]
            if (field === 'date_of_birth') value = party.date_of_birth
            if (value !== null && value !== undefined && value !== '') {
              initial[key] = value
              console.log(`Setting ${key} to ${value}`)
            }
          })
        }
      })

      // Hydrate location IDs from nested deal objects when direct ids are missing.
      if ((initial.area_id === null || initial.area_id === undefined || initial.area_id === '') && deal.area?.id) {
        initial.area_id = deal.area.id
      }
      if ((initial.subcommunity_id === null || initial.subcommunity_id === undefined || initial.subcommunity_id === '') && deal.subcommunity?.id) {
        initial.subcommunity_id = deal.subcommunity.id
      }
    }
    
    // Then override with any missing fields that might have default values
    missingFieldKeys.forEach(key => {
      if (!key.includes('_document_') && initial[key] === undefined) {
        initial[key] = getExistingFieldValue(key)
      }
    })
    
    formData.value = { ...initial }
    formData.value.area_id = normalizeLocationId(formData.value.area_id)
    formData.value.subcommunity_id = normalizeLocationId(formData.value.subcommunity_id)
    
    // Initialize document arrays with existing documents
    const parties = ['buyer', 'seller', 'tenant', 'landlord']
    parties.forEach(party => {
      const existingDocs = getExistingDocuments(party)
      if (existingDocs.length > 0) {
        formData.value[`${party}_documents`] = existingDocs
        console.log(`Loaded ${existingDocs.length} existing documents for ${party}`)
      } else {
        formData.value[`${party}_documents`] = []
      }
    })

    // Ensure document arrays exist for all parties
    const docTypes = documentTypesByParty.value
    parties.forEach((party) => {
      if (docTypes[party]?.length && formData.value[`${party}_documents`] === undefined) {
        formData.value[`${party}_documents`] = []
      }
    })
    
    if (missingFieldKeys.includes('subcommunity_id') || missingFieldKeys.includes('area_id')) {
      await fetchAreas()
    }
    
    console.log('Form initialized with existing data:', formData.value)
    console.log('Form data keys with values:', Object.keys(formData.value))
    
    // Mark fields that are already filled as valid
    missingFieldKeys.forEach(key => {
      if (!key.includes('_document_')) {
        const value = formData.value[key]
        const hasValue = value !== null && value !== undefined && value !== '' && 
                        (typeof value !== 'string' || value.trim() !== '')
        if (hasValue && invalidFields.value.has(key)) {
          invalidFields.value.delete(key)
        }
      }
    })
    
  } catch (error) {
    console.error('Error initializing form:', error)
  } finally {
    loading.value = false
  }
}
// دالة جلب الـ Listings المتاحة (التي باعها أو أجرها الـ Agent الحالي)
const fetchAvailableListings = async (areaId) => {
  if (!areaId) {
    availableListings.value = []
    return
  }
  
  if (!currentUser.value?.id) {
    getCurrentUser()
    if (!currentUser.value?.id) return
  }
  
  try {
    isLoadingListings.value = true
    
    const params = {
      area_id: areaId,
      sold_by_agent_id: currentUser.value.id,
      per_page: 100
    }
    
    const response = await api.get('/listings/properties', { params })
    
    const listings = response.data.data || []
    availableListings.value = listings.map(listing => ({
      id: listing.id,
      unit_number: listing.unit_number,
      property_type: listing.property_type,
      property_type_id: listing.property_type_id,
      bedrooms: listing.number_of_bedrooms,
      bedrooms_text: listing.number_of_bedrooms === 0 ? 'Studio' : `${listing.number_of_bedrooms} Bed`,
      bathrooms: listing.number_of_bathrooms,
      size_sqft: listing.size_sqft,
      // project_id: listing.project_id,
      // project_name: listing.project?.title,
      developer_id: listing.developer_id,
      status: listing.status,
      display_name: `${listing.unit_number || 'No Unit'} - ${listing.property_type?.name || 'Property'} (${listing.status === 'converted' ? 'Sold' : 'Rented'})`
    }))
    
  } catch (error) {
    console.error('Error fetching listings:', error)
  } finally {
    isLoadingListings.value = false
  }
}
function isFieldInvalid(fieldKey) {
  if (!fieldKey) return false

  const phoneKeys = ['buyer_phone', 'seller_phone', 'tenant_phone', 'landlord_phone', 'developer_phone']
  
  // Check if this field is required for the current stage
  const isRequired = effectiveMissingFields.value.includes(fieldKey)
  
  // For document fields
  if (fieldKey.includes('_document_')) {
    const [partyType, docType] = fieldKey.split('_document_')
    return missingDocumentTypesByParty.value[partyType]?.includes(docType) || false
  }
  
  // For regular fields: check if value is empty
  const value = formData.value?.[fieldKey]
  const isEmpty = value === null || value === undefined || value === '' || 
                  (typeof value === 'string' && value.trim() === '')

  if (phoneKeys.includes(fieldKey)) {
    if (isRequired && isEmpty) return true
    if (!isEmpty && !isNonEmptyPhoneValid(value)) return true
    if (!isRequired && !isEmpty && !isNonEmptyPhoneValid(value)) return true
    return false
  }
  
  // If not required, no need to show error
  if (!isRequired) return false
  
  // Field is invalid if it's required AND empty
  return isEmpty
}

// Add this function to mark fields as valid when they're filled
function markFieldValid(fieldKey) {
  if (invalidFields.value.has(fieldKey)) {
    invalidFields.value.delete(fieldKey)
  }
}
function normalizeResidencyStatus(status) {
  if (!status) return 'non_resident'
  const value = String(status).toLowerCase()
  if (value === 'resident') return 'resident'
  if (value === 'non_resident' || value === 'non-resident') return 'non_resident'
  if (value === 'citizen' || value === 'investor' || value === 'student') return 'resident'
  return 'non_resident'
}

function getRequiredDocumentsByResidency(residencyStatus) {
  return normalizeResidencyStatus(residencyStatus) === 'resident'
    ? ['passport', 'national_id']
    : ['passport']
}

const isKycRequiredFromSpaStage = computed(() => {
  const stageName = String(props.targetStageName || '').toLowerCase()
  return (
    stageName.includes('spa signed') ||
    stageName.includes('deal done') ||
    stageName.includes('deal won') ||
    stageName.includes('transfer') ||
    stageName.includes('handover') ||
    stageName.includes('closed')
  )
})
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
const isSpaRequiredFromSpaStage = computed(() => isKycRequiredFromSpaStage.value)

const isPaymentProofRequiredFromEoiStage = computed(() => {
  const stageName = String(props.targetStageName || '').toLowerCase()
  return stageName.includes('eoi') || isKycRequiredFromSpaStage.value
})
// ================ حساب document types مرة واحدة فقط ================
// ================ حساب document types حسب حالة الإقامة ================
// ================ حساب document types حسب حالة الإقامة (ديناميكي) ================
const documentTypesByParty = computed(() => {
  const result = {
    buyer: [],
    seller: [],
    tenant: [],
    landlord: []
  }
  
  // الحصول على حالة الإقامة لكل طرف من formData مباشرة
  const buyerResidency = formData.value?.buyer_residency_status
  const sellerResidency = formData.value?.seller_residency_status
  const tenantResidency = formData.value?.tenant_residency_status
  const landlordResidency = formData.value?.landlord_residency_status
  
  // تحديد المستندات المطلوبة حسب حالة الإقامة فقط (بدون الاعتماد على missingFields)
  const getDocsForResidency = (residencyStatus) => getRequiredDocumentsByResidency(residencyStatus)
  
  // Always show buyer documents for primary/secondary stage moves (use deal payload + tab).
  // This prevents cases where moving from New to later stages hides required docs.
  if (
    ['primary', 'secondary'].includes(effectiveDealTypeForDocs.value)
    || hasEffectiveMissingPrefix('buyer_document_')
    || buyerResidency
  ) {
    const buyerDocDefinitions = [
      { id: 'passport', name: 'Passport' },
      // { id: 'visa', name: 'Residence  Visa' },
      { id: 'national_id', name: 'Emirates ID' },
      { id: 'kyc', name: 'KYC' },
      // { id: 'spa', name: 'Buyer SPA' },
      // { id: 'payment_proof', name: 'Buyer Payment Proof' },
    ]
    
    const requiredBuyerResidencyDocs = getDocsForResidency(buyerResidency)
    buyerDocDefinitions.forEach((doc) => {
      // Match DealForm.vue: residency controls passport/visa/national ID; stage controls KYC/SPA/payment proof.
      const isCoreBuyerDoc = requiredBuyerResidencyDocs.includes(doc.id)
      const isKycRequiredByStage = doc.id === 'kyc' && isKycRequiredFromSpaStage.value
      const isSpaRequiredByStage = doc.id === 'spa' && isSpaRequiredFromSpaStage.value
      const isPaymentProofRequiredByStage = doc.id === 'payment_proof' && isPaymentProofRequiredFromEoiStage.value
      result.buyer.push({
        ...doc,
        required: isCoreBuyerDoc || isKycRequiredByStage || isSpaRequiredByStage || isPaymentProofRequiredByStage,
      })
    })
  }
  
  // نفس الشيء لـ seller (always offer seller docs on secondary deals)
  if (effectiveDealTypeForDocs.value === 'secondary' || hasEffectiveMissingPrefix('seller_document_') || sellerResidency) {
    const requiredDocs = getDocsForResidency(sellerResidency)
    requiredDocs.forEach(docType => {
      result.seller.push({
        id: docType,
        name: docType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
        required: true
      })
    })
  }
  
  // نفس الشيء لـ tenant (rental deals: show tenant docs even when not yet in missingFields)
  if (effectiveDealTypeForDocs.value === 'rental' || hasEffectiveMissingPrefix('tenant_document_') || tenantResidency) {
    const requiredDocs = getDocsForResidency(tenantResidency)
    requiredDocs.forEach(docType => {
      result.tenant.push({
        id: docType,
        name: docType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
        required: true
      })
    })
  }
  
  // نفس الشيء لـ landlord
  if (effectiveDealTypeForDocs.value === 'rental' || hasEffectiveMissingPrefix('landlord_document_') || landlordResidency) {
    const requiredDocs = getDocsForResidency(landlordResidency)
    requiredDocs.forEach(docType => {
      result.landlord.push({
        id: docType,
        name: docType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
        required: true
      })
    })
  }
  
  console.log('Document types by party (dynamic):', result)
  return result
})
const groupedMissingSections = computed(() => {
  if (Array.isArray(props.groupedMissing?.sections) && props.groupedMissing.sections.length) {
    return props.groupedMissing.sections
  }

  if (Array.isArray(props.missingFieldsGrouped?.sections) && props.missingFieldsGrouped.sections.length) {
    return props.missingFieldsGrouped.sections
  }

  return []
})

const groupedSectionFieldKeys = computed(() => {
  const keys = []
  groupedMissingSections.value.forEach((section) => {
    ;(section.fields || []).forEach((field) => {
      const key = typeof field === 'string' ? field : field?.key || field?.field || ''
      if (key) keys.push(key)
    })
  })
  return keys
})

function extractFieldKeysFromStageEntry(stageEntry) {
  const fields = stageEntry?.fields || stageEntry?.missing_fields || []
  if (!Array.isArray(fields)) return []
  return fields
    .map((field) => (typeof field === 'string' ? field : field?.key || field?.field || ''))
    .filter(Boolean)
}

function normalizeStageLabel(label) {
  return String(label || '')
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]/g, '')
}

const effectiveMissingFields = computed(() => {
  const excludedFields = new Set(['buyer_amount', 'tenant_amount', 'deal_total_amount', 'currency'])
  const direct = Array.isArray(props.missingFields) ? props.missingFields : []
  const byStage = props.groupedMissing?.by_stage
    || props.missingFieldsGroupedByStage?.stages
    || []

  // ✅ إذا كان هناك listing_id محدد، نستخدم نوع الصفقة لتحديد الحقول المطلوبة
  if (hasListingId.value) {
    const dealType = formData.value?.deal_type
    
    if (dealType === 'secondary') {
      // إخفاء جميع حقول Seller
      const filtered = [...direct, ...groupedSectionFieldKeys.value]
        .filter(key => !excludedFields.has(String(key)))
        .filter(key => !key.startsWith('seller_') && !key.includes('seller_document_'))
      return Array.from(new Set(filtered))
    }
    
    if (dealType === 'rental') {
      // إخفاء جميع حقول Landlord
      const filtered = [...direct, ...groupedSectionFieldKeys.value]
        .filter(key => !excludedFields.has(String(key)))
        .filter(key => !key.startsWith('landlord_') && !key.includes('landlord_document_'))
      return Array.from(new Set(filtered))
    }
    
    // Primary: إخفاء Seller, Tenant, Landlord كلهم
    const filtered = [...direct, ...groupedSectionFieldKeys.value]
      .filter(key => !excludedFields.has(String(key)))
      .filter(key => {
        return !key.startsWith('seller_') && 
               !key.includes('seller_document_') &&
               !key.startsWith('tenant_') && 
               !key.includes('tenant_document_') &&
               !key.startsWith('landlord_') && 
               !key.includes('landlord_document_')
      })
    return Array.from(new Set(filtered))
  }

  // ❌ بدون listing_id: نعتمد على الموجود من stage (الحالة الأصلية)
  if (!Array.isArray(byStage) || byStage.length === 0) {
    let result = Array.from(new Set([...direct, ...groupedSectionFieldKeys.value]))
      .filter((key) => !excludedFields.has(String(key)))
    return result
  }

  const targetStageId = String(props.targetStageId ?? '')
  const targetStageName = String(props.targetStageName || '').toLowerCase().trim()
  const targetStageNameNormalized = normalizeStageLabel(targetStageName)
  const targetStageNumber = Number((targetStageName.match(/\d+/) || [])[0] || NaN)

  const normalized = byStage.map((stage, idx) => {
    const stageId = String(stage?.stage_id ?? stage?.id ?? '')
    const stageName = String(stage?.stage_name ?? stage?.name ?? stage?.title ?? '').toLowerCase().trim()
    const stageNameNormalized = normalizeStageLabel(stageName)
    const stageNumber = Number((stageName.match(/\d+/) || [])[0] || NaN)
    const orderRaw = stage?.order ?? stage?.stage_order ?? stage?.position ?? null
    const order = Number.isFinite(Number(orderRaw)) ? Number(orderRaw) : idx
    const isMatchById = !!targetStageId && stageId === targetStageId
    const isMatchByName = !!targetStageName && stageName === targetStageName
    const isMatchByNormalizedName = !!targetStageNameNormalized && stageNameNormalized === targetStageNameNormalized
    const isMatchByStageNumber = Number.isFinite(targetStageNumber) && Number.isFinite(stageNumber) && targetStageNumber === stageNumber
    return {
      idx,
      order,
      fields: extractFieldKeysFromStageEntry(stage),
      isTargetMatch: isMatchById || isMatchByName || isMatchByNormalizedName || isMatchByStageNumber,
    }
  })

  const orderedStages = [...normalized].sort((a, b) => {
    if (a.order === b.order) return a.idx - b.idx
    return a.order - b.order
  })

  const targetStageIndex = orderedStages.findIndex((entry) => entry.isTargetMatch)

  if (targetStageIndex === -1) {
    const union = new Set([...direct, ...groupedSectionFieldKeys.value])
    orderedStages.forEach((entry) => entry.fields.forEach((key) => union.add(key)))
    return Array.from(union).filter((key) => !excludedFields.has(String(key)))
  }

  const cumulative = new Set()
  orderedStages
    .slice(0, targetStageIndex + 1)
    .forEach((entry) => entry.fields.forEach((key) => cumulative.add(key)))

  if (!cumulative.size) {
    direct.forEach((key) => cumulative.add(key))
  }
  groupedSectionFieldKeys.value.forEach((key) => cumulative.add(key))
  return Array.from(cumulative).filter((key) => !excludedFields.has(String(key)))
})

function hasEffectiveMissingPrefix(prefix) {
  return effectiveMissingFields.value.some((field) => String(field).startsWith(prefix))
}

const missingFieldLabels = computed(() => {
  if (effectiveMissingFields.value.length) {
    return effectiveMissingFields.value.map((k) =>
      String(k).replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
    )
  }
  const labels = []
  groupedMissingSections.value.forEach((section) => {
    ;(section.fields || []).forEach((field) => {
      labels.push(field.label || field.key)
    })
  })
  return labels
})
const unresolvedMissingLabels = computed(() => {
  return unresolvedMissingKeys.value.map(key => {
    const label = missingFieldLabels.value.find(l => l === key)
    return label || key
  })
})

const unresolvedMissingKeys = computed(() => {
  const unresolved = []
  const missingKeys = effectiveMissingFields.value || []

  missingKeys.forEach((key) => {
    if (!key) return
    if (key === 'currency') return
    if (key.endsWith('_party')) return

    if (key.includes('_document_')) {
      const [partyType, docType] = key.split('_document_')

      // Buyer docs for primary/secondary: rules are applied explicitly below (match DealForm).
      if (partyType === 'buyer' && ['primary', 'secondary'].includes(effectiveDealTypeForDocs.value)) {
        return
      }
      
      // التحقق من حالة الإقامة لهذا الطرف
      let residencyStatus = null
      let nationality = null
      
      switch(partyType) {
        case 'buyer':
          residencyStatus = formData.value?.buyer_residency_status
          nationality = formData.value?.buyer_nationality
          break
        case 'seller':
          residencyStatus = formData.value?.seller_residency_status
          nationality = formData.value?.seller_nationality
          break
        case 'tenant':
          residencyStatus = formData.value?.tenant_residency_status
          nationality = formData.value?.tenant_nationality
          break
        case 'landlord':
          residencyStatus = formData.value?.landlord_residency_status
          nationality = formData.value?.landlord_nationality
          break
      }
      
      // إذا لم يتم تحديد حالة الإقامة بعد، نعتبر passport فقط مطلوب
      if (!residencyStatus) {
        if (docType === 'passport') {
          const docs = formData.value?.[`${partyType}_documents`] || []
          const hasDoc = Array.isArray(docs) && docs.some((doc) => (doc?.file || doc?.url || doc?.file_url) && doc?.document_type === docType)
          if (!hasDoc) {
            unresolved.push(key)
            if (!invalidFields.value.has(key)) invalidFields.value.add(key)
          } else {
            if (invalidFields.value.has(key)) invalidFields.value.delete(key)
          }
        }
        return
      }
      
      // الحصول على المستندات المطلوبة حسب حالة الإقامة
      const requiredDocs = getRequiredDocumentsByResidency(residencyStatus)
      
      // فقط تحقق من المستند إذا كان مطلوباً حسب حالة الإقامة
      if (requiredDocs.includes(docType)) {
        const docs = formData.value?.[`${partyType}_documents`] || []
        const hasDoc = Array.isArray(docs) && docs.some((doc) => (doc?.file || doc?.url || doc?.file_url) && doc?.document_type === docType)
        if (!hasDoc) {
          unresolved.push(key)
          if (!invalidFields.value.has(key)) invalidFields.value.add(key)
        } else {
          if (invalidFields.value.has(key)) invalidFields.value.delete(key)
        }
      }
      return
    }

    const value = formData.value?.[key]
    const isEmpty = value === null || value === undefined || value === ''
    if (isEmpty) {
      unresolved.push(key)
      if (!invalidFields.value.has(key)) invalidFields.value.add(key)
    } else {
      if (invalidFields.value.has(key)) invalidFields.value.delete(key)
    }
  })

  if (['primary', 'secondary'].includes(effectiveDealTypeForDocs.value)) {
    const missingKeys = effectiveMissingFields.value || []
    const hasKycInCumulativeMissing = missingKeys.includes('buyer_document_kyc')
    const hasSpaInCumulativeMissing = missingKeys.includes('buyer_document_spa')
    const hasPaymentProofInCumulativeMissing = missingKeys.includes('buyer_document_payment_proof')

    if (isKycRequiredFromSpaStage.value || hasKycInCumulativeMissing) {
      const buyerDocs = formData.value?.buyer_documents || []
      const hasBuyerKyc = Array.isArray(buyerDocs) && buyerDocs.some(
        (doc) => doc?.document_type === 'kyc' && (doc?.file || doc?.url || doc?.file_url),
      )
      if (!hasBuyerKyc && !unresolved.includes('buyer_document_kyc')) {
        unresolved.push('buyer_document_kyc')
        if (!invalidFields.value.has('buyer_document_kyc')) invalidFields.value.add('buyer_document_kyc')
      } else if (hasBuyerKyc && invalidFields.value.has('buyer_document_kyc')) {
        invalidFields.value.delete('buyer_document_kyc')
      }
    }

    {
      const buyerDocs = formData.value?.buyer_documents || []
      const requiredFromStart = getRequiredDocumentsByResidency(formData.value?.buyer_residency_status)
      requiredFromStart.forEach((docType) => {
        const hasDoc = Array.isArray(buyerDocs) && buyerDocs.some(
          (doc) => doc?.document_type === docType && (doc?.file || doc?.url || doc?.file_url),
        )
        const key = `buyer_document_${docType}`
        if (!hasDoc && !unresolved.includes(key)) {
          unresolved.push(key)
          if (!invalidFields.value.has(key)) invalidFields.value.add(key)
        } else if (hasDoc && invalidFields.value.has(key)) {
          invalidFields.value.delete(key)
        }
      })
    }

    if (isSpaRequiredFromSpaStage.value || hasSpaInCumulativeMissing) {
      const buyerDocs = formData.value?.buyer_documents || []
      const hasBuyerSpa = Array.isArray(buyerDocs) && buyerDocs.some(
        (doc) => doc?.document_type === 'spa' && (doc?.file || doc?.url || doc?.file_url),
      )
      if (!hasBuyerSpa && !unresolved.includes('buyer_document_spa')) {
        unresolved.push('buyer_document_spa')
        if (!invalidFields.value.has('buyer_document_spa')) invalidFields.value.add('buyer_document_spa')
      } else if (hasBuyerSpa && invalidFields.value.has('buyer_document_spa')) {
        invalidFields.value.delete('buyer_document_spa')
      }
    }

    if (isPaymentProofRequiredFromEoiStage.value || hasPaymentProofInCumulativeMissing) {
      const buyerDocs = formData.value?.buyer_documents || []
      const hasBuyerPaymentProof = Array.isArray(buyerDocs) && buyerDocs.some(
        (doc) => doc?.document_type === 'payment_proof' && (doc?.file || doc?.url || doc?.file_url),
      )
      if (!hasBuyerPaymentProof && !unresolved.includes('buyer_document_payment_proof')) {
        unresolved.push('buyer_document_payment_proof')
        if (!invalidFields.value.has('buyer_document_payment_proof')) invalidFields.value.add('buyer_document_payment_proof')
      } else if (hasBuyerPaymentProof && invalidFields.value.has('buyer_document_payment_proof')) {
        invalidFields.value.delete('buyer_document_payment_proof')
      }
    }
  }

  return unresolved
})

const canSubmit = computed(() => {
  return !loading.value && !submitting.value && unresolvedMissingKeys.value.length === 0
})

const isCompactStageModal = computed(() => {
  const count = (effectiveMissingFields.value || []).length
  return count > 0 && count <= 4
})

const isDealWonStage = computed(() => {
  return String(props.targetStageName || '').toLowerCase().includes('deal won')
})

const isLostReasonOnly = computed(() => {
  const keys = effectiveMissingFields.value || []
  return keys.length === 1 && keys[0] === 'lost_reason'
})
async function fetchDevelopers() {
  try {
    const response = await api.get('/listings/developers')
    developers.value = response.data?.data || response.data || []
  } catch (error) {
    console.error('Error fetching developers:', error)
  }
}
// const fetchProjects = async () => {
//   try {
//     const response = await api.get('/listings/projects', { 
//       params: { per_page: 1000 } 
//     })
//     projects.value = response.data?.data ?? response.data ?? []
//     console.log(`Loaded ${projects.value.length} projects`)
//   } catch (error) {
//     console.error('Error loading projects:', error)
//   }
// }

const fetchAllAreas = async () => {
  try {
      const response = await api.get('/listings/areas')
    
    // معالجة البيانات
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
     props.areas = areasData
         areas.value = areasData

     emit('update:areas', areasData)
   
    
    console.log(`Loaded ${props.areas.length} areas`)
  } catch (error) {
    console.error('Error loading areas:', error)
  }
}
// Load initial data
onMounted(async () => {
        fetchAllAreas()
  await Promise.all([
    fetchUsers(),
    fetchSources(),
    fetchPropertyTypes(),
     fetchDevelopers() ,
      // fetchProjects() 
  ])
  getCurrentUser()
})
// دالة عند اختيار المنطقة
const onAreaSelected = (areaId) => {
  selectedListing.value = null
  
  // إعادة تعيين بيانات العقار
  if (formData.value) {
    formData.value.unit_no = ''
    formData.value.property_type_id = null
    formData.value.bedrooms = null
    formData.value.unit_size = ''
    // formData.value.project_id = null
    formData.value.developer_id = null

  }
  
  fetchAvailableListings(areaId)
}

// دالة عند اختيار Listing
const onListingSelected = (listing) => {
   if (!listing) {
    hasListingId.value = false
    return
  }
  
  hasListingId.value = true
  formData.value.unit_no = listing.unit_number || ''
  formData.value.property_type_id = listing.property_type_id
  formData.value.bedrooms = listing.bedrooms === 0 ? 'studio' : String(listing.bedrooms)
  formData.value.unit_size = listing.size_sqft || ''
  // formData.value.project_id = listing.project_id
  formData.value.developer_id = listing.developer_id
  formData.value.listing_id = listing.id
  formData.value.listing_status = listing.status
}
// Watch for modal open
watch(() => formData.value, (newVal) => {
  // Check each missing field to see if it's now filled
  effectiveMissingFields.value.forEach(fieldKey => {
    if (fieldKey.includes('_document_')) {
      // Skip document fields here - handled separately
      return
    }
    
    const value = newVal[fieldKey]
    const isEmpty = value === null || value === undefined || value === '' || (typeof value === 'string' && value.trim() === '')
    
    if (isEmpty) {
      if (!invalidFields.value.has(fieldKey)) {
        invalidFields.value.add(fieldKey)
      }
    } else {
      if (invalidFields.value.has(fieldKey)) {
        invalidFields.value.delete(fieldKey)
      }
    }
  })
}, { deep: true })

const shouldHideSeller = computed(() => {
  return hasListingId.value && formData.value?.deal_type === 'secondary'
})

const shouldHideLandlord = computed(() => {
  return hasListingId.value && formData.value?.deal_type === 'rental'
})

const shouldHideTenant = computed(() => {
  return hasListingId.value && formData.value?.deal_type !== 'rental'
})
watch(() => props, (newVal) => {
  console.log('Props received by modal:', {
    show: newVal.show,
    dealId: newVal.dealId,
    targetStageId: newVal.targetStageId,
    targetStageName: newVal.targetStageName,
    missingFields: newVal.missingFields,
    groupedMissing: newVal.groupedMissing,
    deal: newVal.deal
  })
}, { deep: true, immediate: true })
const isInitialized = ref(false)
watch(() => formData.value.buyer_documents, (newVal) => {
  console.log('buyer_documents changed:', newVal)
}, { deep: true })
watch(() => props.show, async (val) => {
  if (val && !isInitialized.value) {
    isInitialized.value = true
     if (areas.value.length === 0) {
      await fetchAllAreas()
    }
    await initializeForm()
    console.log('Missing fields:', props.missingFields)
  } else if (!val) {
    isInitialized.value = false
    submitting.value = false
    if (submitResetTimer) {
      clearTimeout(submitResetTimer)
      submitResetTimer = null
    }
  }
})


// Fetch users
async function fetchUsers() {
  try {
    const response = await api.get('/available-responsible-persons')
    const responseData = response.data
    users.value = responseData?.data || responseData || []
  } catch (error) {
    console.error('Error fetching users:', error)
    users.value = []
  }
}

// Fetch sources
async function fetchSources() {
  try {
    const response = await api.get('/sources')
    const responseData = response.data
    sources.value = responseData?.data || responseData || []
  } catch (error) {
    console.error('Error fetching sources:', error)
    sources.value = []
  }
}

// Fetch property types
async function fetchPropertyTypes() {
  try {
    const response = await api.get('/listings/property-types')
    const responseData = response.data
    propertyTypes.value = responseData?.data || responseData || []
  } catch (error) {
    console.error('Error fetching property types:', error)
    propertyTypes.value = []
  }
}

// Fetch areas
async function fetchAreas(search = '') {
  try {
    const response = await api.get('/listings/areas', { 
      params: { search, } 
    })
    const responseData = response.data
    areas.value = responseData?.data || responseData || []
  } catch (error) {
    console.error('Error fetching areas:', error)
  }
}
watch(() => formData.value.developer_id, (newId) => {
  if (newId && developers.value.length) {
    const dev = developers.value.find(d => d.id === newId)
    console.log('Developer found:', dev)
  }
})
// Search areas
function onSearchAreas(search) {
  fetchAreas(search)
}

// Get initial value from deal
function getInitialValue(key) {
  const deal = props.deal
  if (!deal) return ''
  
  
  
  // Direct field
  if (deal[key] !== undefined && deal[key] !== null) return deal[key]
  
  // Handle party fields
  const partyTypes = ['buyer', 'seller', 'tenant', 'landlord']
  for (const partyType of partyTypes) {
    if (key.startsWith(partyType + '_') && !key.includes('_document_')) {
      const party = deal.parties?.find(p => p.party_type === partyType)
      if (!party) return ''
      
      const field = key.replace(partyType + '_', '')
      // Map dob to date_of_birth
      if (field === 'dob') return party.date_of_birth || ''
      if (field === 'amount') return ''
      return party[field] || ''
    }
  }
  
  return ''
}

function hasValueForField(fieldKey) {
  if (fieldKey === 'area_id' || fieldKey === 'subcommunity_id') {
    const ownValue = formData.value?.[fieldKey]
    if (ownValue !== null && ownValue !== undefined && ownValue !== '') return true

    const alternateKey = fieldKey === 'area_id' ? 'subcommunity_id' : 'area_id'
    const alternateValue = formData.value?.[alternateKey]
    if (alternateValue !== null && alternateValue !== undefined && alternateValue !== '') return true

    const dealDirect = props.deal?.[fieldKey]
    if (dealDirect !== null && dealDirect !== undefined && dealDirect !== '') return true
    const dealAlternate = props.deal?.[alternateKey]
    if (dealAlternate !== null && dealAlternate !== undefined && dealAlternate !== '') return true

    const dealAreaId = props.deal?.area?.id
    if (dealAreaId !== null && dealAreaId !== undefined && dealAreaId !== '') return true
    const dealSubcommunityId = props.deal?.subcommunity?.id
    if (dealSubcommunityId !== null && dealSubcommunityId !== undefined && dealSubcommunityId !== '') return true

    return false
  }

  const value = formData.value?.[fieldKey]
  if (Array.isArray(value)) return value.length > 0
  if (typeof value === 'string') return value.trim() !== ''
  return value !== null && value !== undefined
}

function hasAnyValueInFields(fields) {
  return fields.some((field) => hasValueForField(field))
}

/** Stages may require `area_id` (primary / many steps) or `subcommunity_id` (secondary early stages) — same Area picker. */
const locationFormKey = computed(() => {
  const req = effectiveMissingFields.value || []
  if (req.includes('subcommunity_id')) return 'subcommunity_id'
  if (req.includes('area_id')) return 'area_id'
  if (hasValueForField('subcommunity_id')) return 'subcommunity_id'
  if (hasValueForField('area_id')) return 'area_id'
  return 'area_id'
})

const locationSelectModel = computed({
  get() {
    const key = locationFormKey.value
    const preferred = normalizeLocationId(formData.value?.[key])
    if (preferred !== null && preferred !== undefined && preferred !== '') return preferred
    const areaValue = normalizeLocationId(formData.value?.area_id)
    if (areaValue !== null && areaValue !== undefined && areaValue !== '') return areaValue
    const subcommunityValue = normalizeLocationId(formData.value?.subcommunity_id)
    if (subcommunityValue !== null && subcommunityValue !== undefined && subcommunityValue !== '') return subcommunityValue
    return null
  },
  set(val) {
    if (!formData.value) return
    const normalizedVal = normalizeLocationId(val)
    const req = effectiveMissingFields.value || []
    if (req.includes('subcommunity_id')) formData.value.subcommunity_id = normalizedVal
    if (req.includes('area_id')) formData.value.area_id = normalizedVal
    if (!req.includes('subcommunity_id') && !req.includes('area_id')) {
      formData.value[locationFormKey.value] = normalizedVal
    }
    onAreaSelected(normalizedVal)
  },
})

function hasLocationField() {
  return hasField('area_id') || hasField('subcommunity_id')
}

watch(
  () => [formData.value?.area_id, formData.value?.subcommunity_id],
  ([areaId, subcommunityId]) => {
    if (!formData.value) return
    if ((areaId === null || areaId === undefined || areaId === '') && subcommunityId) {
      formData.value.area_id = subcommunityId
    } else if ((subcommunityId === null || subcommunityId === undefined || subcommunityId === '') && areaId) {
      formData.value.subcommunity_id = areaId
    }
  },
  { immediate: true },
)

function hasField(fieldKey) {
  const allRequired = effectiveMissingFields.value || []
  return allRequired.includes(fieldKey) || hasValueForField(fieldKey)
}

function hasPartyFields(partyType) {
  const requiredFields = effectiveMissingFields.value || []
  const possibleFields = [
    `${partyType}_first_name`, `${partyType}_last_name`, `${partyType}_phone`,
    `${partyType}_email`, `${partyType}_nationality`, `${partyType}_dob`,
    `${partyType}_residency_status`, `${partyType}_city`, `${partyType}_country`,
    `${partyType}_language`,
  ]
  return (
    possibleFields.some(field => requiredFields.includes(field)) ||
    hasAnyValueInFields(possibleFields)
  )
}

function hasPropertyFields() {
  const requiredFields = effectiveMissingFields.value || []
  const propertyFields = ['unit_no', 'property_type_id', 'bedrooms', 'area_id', 'subcommunity_id', 'unit_size','developer_id', 'developer_name', 'developer_phone']
  return (
    propertyFields.some(field => requiredFields.includes(field)) ||
    hasAnyValueInFields(propertyFields)
  )
}

function hasFinancialFields() {
  const requiredFields = effectiveMissingFields.value || []
  const financialFields = ['deal_commission', 'agent_share', 'company_share']
  return (
    financialFields.some(field => requiredFields.includes(field)) ||
    hasAnyValueInFields(financialFields)
  )
}

function hasSourceAndDealNameFields() {
  const requiredFields = effectiveMissingFields.value || []
  const fields = ['source', 'deal_name']
  return (
    fields.some(field => requiredFields.includes(field)) ||
    hasAnyValueInFields(fields)
  )
}
// Get deal type name
function getDealTypeName(type) {
  const types = {
    primary: 'Primary / Off Plan',
    secondary: 'Secondary',
    rental: 'Rental'
  }
  return types[type] || type
}

// Submit form

function submitForm() {
  if (!canSubmit.value) return
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
  
  // ✅ تأكد من أن formData.buyer_documents فيه بيانات
  console.log('formData before collecting:', formData.value)
  
  // Collect documents from all upload components
  const docRefs = [
    { ref: buyerDocUploadRef, party: 'buyer', key: 'buyer_documents' },
    { ref: sellerDocUploadRef, party: 'seller', key: 'seller_documents' },
    { ref: tenantDocUploadRef, party: 'tenant', key: 'tenant_documents' },
    { ref: landlordDocUploadRef, party: 'landlord', key: 'landlord_documents' }
  ]
  
  docRefs.forEach(({ ref, party, key }) => {
    // ✅ طريقة 1: من ref
    if (ref.value?.modelValue) {
      console.log(`Documents from ${party} ref:`, ref.value.modelValue)
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
    
    // ✅ طريقة 2: من formData (كـ backup)
    if (formData.value[key] && Array.isArray(formData.value[key])) {
      console.log(`Documents from ${party} formData:`, formData.value[key])
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
  
  console.log('Final documents to send:', documents.map(d => ({ 
    name: d.file.name, 
    doc_type: d.document_type,
    category: d.category 
  })))
  
  emit('save', { payload, documents, stage_id: props.targetStageId })

  // Safety fallback in case parent keeps modal open after failed API call.
  submitResetTimer = setTimeout(() => {
    submitting.value = false
    submitResetTimer = null
  }, 12000)
}

// Close modal
function closeModal() {
  formData.value = {}
  submitting.value = false
  if (submitResetTimer) {
    clearTimeout(submitResetTimer)
    submitResetTimer = null
  }
  emit('closed')
}

// Options for selects
const languageOptions = [
  { value: 'english', text: 'English' },
  { value: 'arabic', text: 'Arabic' },
  { value: 'spanish', text: 'Spanish' },
  { value: 'french', text: 'French' },
  { value: 'german', text: 'German' },
  { value: 'italian', text: 'Italian' },
  { value: 'portuguese', text: 'Portuguese' },
  { value: 'russian', text: 'Russian' },
  { value: 'chinese', text: 'Chinese' },
  { value: 'japanese', text: 'Japanese' },
  { value: 'korean', text: 'Korean' },
  { value: 'hindi', text: 'Hindi' },
  { value: 'urdu', text: 'Urdu' },
  { value: 'bengali', text: 'Bengali' },
  { value: 'punjabi', text: 'Punjabi' },
  { value: 'turkish', text: 'Turkish' },
  { value: 'dutch', text: 'Dutch' },
  { value: 'polish', text: 'Polish' },
  { value: 'ukrainian', text: 'Ukrainian' },
  { value: 'czech', text: 'Czech' },
  { value: 'swedish', text: 'Swedish' },
  { value: 'greek', text: 'Greek' },
  { value: 'hebrew', text: 'Hebrew' },
  { value: 'thai', text: 'Thai' },
  { value: 'vietnamese', text: 'Vietnamese' },
  { value: 'indonesian', text: 'Indonesian' },
  { value: 'malay', text: 'Malay' },
  { value: 'filipino', text: 'Filipino' },
  { value: 'persian', text: 'Persian (Farsi)' },
  { value: 'swahili', text: 'Swahili' },
  { value: 'romanian', text: 'Romanian' },
  { value: 'hungarian', text: 'Hungarian' },
  { value: 'serbian', text: 'Serbian' },
  { value: 'croatian', text: 'Croatian' },
  { value: 'bulgarian', text: 'Bulgarian' },
  { value: 'tamil', text: 'Tamil' },
  { value: 'telugu', text: 'Telugu' },
  { value: 'marathi', text: 'Marathi' },
  { value: 'gujarati', text: 'Gujarati' },
  { value: 'kannada', text: 'Kannada' },
  { value: 'malayalam', text: 'Malayalam' },
  { value: 'nepali', text: 'Nepali' },
  { value: 'sinhala', text: 'Sinhala' },
  { value: 'khmer', text: 'Khmer' },
  { value: 'lao', text: 'Lao' },
  { value: 'burmese', text: 'Burmese' },
  { value: 'mongolian', text: 'Mongolian' },
  { value: 'kazakh', text: 'Kazakh' },
  { value: 'uzbek', text: 'Uzbek' },
  { value: 'azerbaijani', text: 'Azerbaijani' },
  { value: 'georgian', text: 'Georgian' },
  { value: 'armenian', text: 'Armenian' },
  { value: 'albanian', text: 'Albanian' },
  { value: 'bosnian', text: 'Bosnian' },
  { value: 'macedonian', text: 'Macedonian' },
  { value: 'slovak', text: 'Slovak' },
  { value: 'slovenian', text: 'Slovenian' },
  { value: 'estonian', text: 'Estonian' },
  { value: 'latvian', text: 'Latvian' },
  { value: 'lithuanian', text: 'Lithuanian' },
  { value: 'icelandic', text: 'Icelandic' },
  { value: 'norwegian', text: 'Norwegian' },
  { value: 'danish', text: 'Danish' },
  { value: 'finnish', text: 'Finnish' },
  { value: 'welsh', text: 'Welsh' },
  { value: 'irish', text: 'Irish' },
  { value: 'scottish_gaelic', text: 'Scottish Gaelic' },
  { value: 'afrikaans', text: 'Afrikaans' },
  { value: 'amharic', text: 'Amharic' },
  { value: 'somali', text: 'Somali' },
  { value: 'yoruba', text: 'Yoruba' },
  { value: 'igbo', text: 'Igbo' },
  { value: 'hausa', text: 'Hausa' },
  { value: 'zulu', text: 'Zulu' },
  { value: 'xhosa', text: 'Xhosa' },
  { value: 'other', text: 'Other' }
]
const nationalityOptions = [
  { value: 'emirati', text: 'Emirati' },
  { value: 'saudi', text: 'Saudi' },
  { value: 'egyptian', text: 'Egyptian' },
  { value: 'jordanian', text: 'Jordanian' },
  { value: 'lebanese', text: 'Lebanese' },
  { value: 'syrian', text: 'Syrian' },
  { value: 'palestinian', text: 'Palestinian' },
  { value: 'iraqi', text: 'Iraqi' },
  { value: 'yemeni', text: 'Yemeni' },
  { value: 'omani', text: 'Omani' },
  { value: 'qatari', text: 'Qatari' },
  { value: 'kuwaiti', text: 'Kuwaiti' },
  { value: 'bahraini', text: 'Bahraini' },
  { value: 'british', text: 'British' },
  { value: 'american', text: 'American' },
  { value: 'canadian', text: 'Canadian' },
  { value: 'australian', text: 'Australian' },
  { value: 'indian', text: 'Indian' },
  { value: 'pakistani', text: 'Pakistani' },
  { value: 'bangladeshi', text: 'Bangladeshi' },
  { value: 'filipino', text: 'Filipino' },
  { value: 'chinese', text: 'Chinese' },
  { value: 'japanese', text: 'Japanese' },
  { value: 'south_korean', text: 'South Korean' },
  { value: 'malaysian', text: 'Malaysian' },
  { value: 'indonesian', text: 'Indonesian' },
  { value: 'thai', text: 'Thai' },
  { value: 'vietnamese', text: 'Vietnamese' },
  { value: 'singaporean', text: 'Singaporean' },
  { value: 'sri_lankan', text: 'Sri Lankan' },
  { value: 'nepalese', text: 'Nepalese' },
  { value: 'afghan', text: 'Afghan' },
  { value: 'iranian', text: 'Iranian' },
  { value: 'turkish', text: 'Turkish' },
  { value: 'german', text: 'German' },
  { value: 'french', text: 'French' },
  { value: 'italian', text: 'Italian' },
  { value: 'spanish', text: 'Spanish' },
  { value: 'russian', text: 'Russian' },
  { value: 'dutch', text: 'Dutch' },
  { value: 'swiss', text: 'Swiss' },
  { value: 'belgian', text: 'Belgian' },
  { value: 'austrian', text: 'Austrian' },
  { value: 'swedish', text: 'Swedish' },
  { value: 'norwegian', text: 'Norwegian' },
  { value: 'danish', text: 'Danish' },
  { value: 'finnish', text: 'Finnish' },
  { value: 'polish', text: 'Polish' },
  { value: 'czech', text: 'Czech' },
  { value: 'hungarian', text: 'Hungarian' },
  { value: 'romanian', text: 'Romanian' },
  { value: 'bulgarian', text: 'Bulgarian' },
  { value: 'greek', text: 'Greek' },
  { value: 'portuguese', text: 'Portuguese' },
  { value: 'south_african', text: 'South African' },
  { value: 'nigerian', text: 'Nigerian' },
  { value: 'kenyan', text: 'Kenyan' },
  { value: 'ethiopian', text: 'Ethiopian' },
  { value: 'somali', text: 'Somali' },
  { value: 'sudanese', text: 'Sudanese' },
  { value: 'moroccan', text: 'Moroccan' },
  { value: 'algerian', text: 'Algerian' },
  { value: 'tunisian', text: 'Tunisian' },
  { value: 'libyan', text: 'Libyan' },
  { value: 'other', text: 'Other' }
]
const residencyOptions = [
  { value: 'resident', text: 'Resident' },
  { value: 'non_resident', text: 'Non Resident' },

]
const buyerResidencyOptions = [
  { value: 'resident', text: 'Resident' },
  { value: 'non_resident', text: 'Non Resident' },
]

// قائمة كاملة بكل دول العالم (مثل صفحة Owner)
const countryOptions = [
  { value: "Afghanistan", text: "Afghanistan" },
  { value: "Albania", text: "Albania" },
  { value: "Algeria", text: "Algeria" },
  { value: "Andorra", text: "Andorra" },
  { value: "Angola", text: "Angola" },
  { value: "Antigua and Barbuda", text: "Antigua and Barbuda" },
  { value: "Argentina", text: "Argentina" },
  { value: "Armenia", text: "Armenia" },
  { value: "Australia", text: "Australia" },
  { value: "Austria", text: "Austria" },
  { value: "Azerbaijan", text: "Azerbaijan" },
  { value: "Bahamas", text: "Bahamas" },
  { value: "Bahrain", text: "Bahrain" },
  { value: "Bangladesh", text: "Bangladesh" },
  { value: "Barbados", text: "Barbados" },
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
  { value: "Cabo Verde", text: "Cabo Verde" },
  { value: "Cambodia", text: "Cambodia" },
  { value: "Cameroon", text: "Cameroon" },
  { value: "Canada", text: "Canada" },
  { value: "Central African Republic", text: "Central African Republic" },
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
  { value: "Czechia", text: "Czechia" },
  { value: "Denmark", text: "Denmark" },
  { value: "Djibouti", text: "Djibouti" },
  { value: "Dominica", text: "Dominica" },
  { value: "Dominican Republic", text: "Dominican Republic" },
  { value: "Ecuador", text: "Ecuador" },
  { value: "Egypt", text: "Egypt" },
  { value: "El Salvador", text: "El Salvador" },
  { value: "Equatorial Guinea", text: "Equatorial Guinea" },
  { value: "Eritrea", text: "Eritrea" },
  { value: "Estonia", text: "Estonia" },
  { value: "Eswatini", text: "Eswatini" },
  { value: "Ethiopia", text: "Ethiopia" },
  { value: "Fiji", text: "Fiji" },
  { value: "Finland", text: "Finland" },
  { value: "France", text: "France" },
  { value: "Gabon", text: "Gabon" },
  { value: "Gambia", text: "Gambia" },
  { value: "Georgia", text: "Georgia" },
  { value: "Germany", text: "Germany" },
  { value: "Ghana", text: "Ghana" },
  { value: "Greece", text: "Greece" },
  { value: "Grenada", text: "Grenada" },
  { value: "Guatemala", text: "Guatemala" },
  { value: "Guinea", text: "Guinea" },
  { value: "Guinea-Bissau", text: "Guinea-Bissau" },
  { value: "Guyana", text: "Guyana" },
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
  { value: "Kiribati", text: "Kiribati" },
  { value: "Kuwait", text: "Kuwait" },
  { value: "Kyrgyzstan", text: "Kyrgyzstan" },
  { value: "Laos", text: "Laos" },
  { value: "Latvia", text: "Latvia" },
  { value: "Lebanon", text: "Lebanon" },
  { value: "Lesotho", text: "Lesotho" },
  { value: "Liberia", text: "Liberia" },
  { value: "Libya", text: "Libya" },
  { value: "Liechtenstein", text: "Liechtenstein" },
  { value: "Lithuania", text: "Lithuania" },
  { value: "Luxembourg", text: "Luxembourg" },
  { value: "Madagascar", text: "Madagascar" },
  { value: "Malawi", text: "Malawi" },
  { value: "Malaysia", text: "Malaysia" },
  { value: "Maldives", text: "Maldives" },
  { value: "Mali", text: "Mali" },
  { value: "Malta", text: "Malta" },
  { value: "Marshall Islands", text: "Marshall Islands" },
  { value: "Mauritania", text: "Mauritania" },
  { value: "Mauritius", text: "Mauritius" },
  { value: "Mexico", text: "Mexico" },
  { value: "Micronesia", text: "Micronesia" },
  { value: "Moldova", text: "Moldova" },
  { value: "Monaco", text: "Monaco" },
  { value: "Mongolia", text: "Mongolia" },
  { value: "Montenegro", text: "Montenegro" },
  { value: "Morocco", text: "Morocco" },
  { value: "Mozambique", text: "Mozambique" },
  { value: "Myanmar", text: "Myanmar" },
  { value: "Namibia", text: "Namibia" },
  { value: "Nauru", text: "Nauru" },
  { value: "Nepal", text: "Nepal" },
  { value: "Netherlands", text: "Netherlands" },
  { value: "New Zealand", text: "New Zealand" },
  { value: "Nicaragua", text: "Nicaragua" },
  { value: "Niger", text: "Niger" },
  { value: "Nigeria", text: "Nigeria" },
  { value: "North Korea", text: "North Korea" },
  { value: "North Macedonia", text: "North Macedonia" },
  { value: "Norway", text: "Norway" },
  { value: "Oman", text: "Oman" },
  { value: "Pakistan", text: "Pakistan" },
  { value: "Palau", text: "Palau" },
  { value: "Palestine", text: "Palestine" },
  { value: "Panama", text: "Panama" },
  { value: "Papua New Guinea", text: "Papua New Guinea" },
  { value: "Paraguay", text: "Paraguay" },
  { value: "Peru", text: "Peru" },
  { value: "Philippines", text: "Philippines" },
  { value: "Poland", text: "Poland" },
  { value: "Portugal", text: "Portugal" },
  { value: "Qatar", text: "Qatar" },
  { value: "Romania", text: "Romania" },
  { value: "Russia", text: "Russia" },
  { value: "Rwanda", text: "Rwanda" },
  { value: "Saint Kitts and Nevis", text: "Saint Kitts and Nevis" },
  { value: "Saint Lucia", text: "Saint Lucia" },
  { value: "Saint Vincent and the Grenadines", text: "Saint Vincent and the Grenadines" },
  { value: "Samoa", text: "Samoa" },
  { value: "San Marino", text: "San Marino" },
  { value: "Sao Tome and Principe", text: "Sao Tome and Principe" },
  { value: "Saudi Arabia", text: "Saudi Arabia" },
  { value: "Senegal", text: "Senegal" },
  { value: "Serbia", text: "Serbia" },
  { value: "Seychelles", text: "Seychelles" },
  { value: "Sierra Leone", text: "Sierra Leone" },
  { value: "Singapore", text: "Singapore" },
  { value: "Slovakia", text: "Slovakia" },
  { value: "Slovenia", text: "Slovenia" },
  { value: "Solomon Islands", text: "Solomon Islands" },
  { value: "Somalia", text: "Somalia" },
  { value: "South Africa", text: "South Africa" },
  { value: "South Korea", text: "South Korea" },
  { value: "South Sudan", text: "South Sudan" },
  { value: "Spain", text: "Spain" },
  { value: "Sri Lanka", text: "Sri Lanka" },
  { value: "Sudan", text: "Sudan" },
  { value: "Suriname", text: "Suriname" },
  { value: "Sweden", text: "Sweden" },
  { value: "Switzerland", text: "Switzerland" },
  { value: "Syria", text: "Syria" },
  { value: "Taiwan", text: "Taiwan" },
  { value: "Tajikistan", text: "Tajikistan" },
  { value: "Tanzania", text: "Tanzania" },
  { value: "Thailand", text: "Thailand" },
  { value: "Timor-Leste", text: "Timor-Leste" },
  { value: "Togo", text: "Togo" },
  { value: "Tonga", text: "Tonga" },
  { value: "Trinidad and Tobago", text: "Trinidad and Tobago" },
  { value: "Tunisia", text: "Tunisia" },
  { value: "Turkey", text: "Turkey" },
  { value: "Turkmenistan", text: "Turkmenistan" },
  { value: "Tuvalu", text: "Tuvalu" },
  { value: "Uganda", text: "Uganda" },
  { value: "Ukraine", text: "Ukraine" },
  { value: "United Arab Emirates", text: "United Arab Emirates" },
  { value: "United Kingdom", text: "United Kingdom" },
  { value: "United States", text: "United States" },
  { value: "Uruguay", text: "Uruguay" },
  { value: "Uzbekistan", text: "Uzbekistan" },
  { value: "Vanuatu", text: "Vanuatu" },
  { value: "Vatican City", text: "Vatican City" },
  { value: "Venezuela", text: "Venezuela" },
  { value: "Vietnam", text: "Vietnam" },
  { value: "Yemen", text: "Yemen" },
  { value: "Zambia", text: "Zambia" },
  { value: "Zimbabwe", text: "Zimbabwe" }
];

// مدن كل بلد (كما هي موجودة عندك ولكن مع التأكد من وجود value/text)
const citiesByCountry = {
  'United Arab Emirates': [
    { value: 'Abu Dhabi', text: 'Abu Dhabi' },
    { value: 'Dubai', text: 'Dubai' },
    { value: 'Sharjah', text: 'Sharjah' },
    { value: 'Ajman', text: 'Ajman' },
    { value: 'Ras Al Khaimah', text: 'Ras Al Khaimah' },
    { value: 'Umm Al Quwain', text: 'Umm Al Quwain' },
    { value: 'Fujairah', text: 'Fujairah' },
    { value: 'Al Ain', text: 'Al Ain' }
  ],
  'Saudi Arabia': [
    { value: 'Riyadh', text: 'Riyadh' },
    { value: 'Jeddah', text: 'Jeddah' },
    { value: 'Mecca', text: 'Mecca' },
    { value: 'Medina', text: 'Medina' },
    { value: 'Dammam', text: 'Dammam' },
    { value: 'Khobar', text: 'Khobar' },
    { value: 'Tabuk', text: 'Tabuk' },
    { value: 'Abha', text: 'Abha' }
  ],
  'Egypt': [
    { value: 'Cairo', text: 'Cairo' },
    { value: 'Giza', text: 'Giza' },
    { value: 'Alexandria', text: 'Alexandria' },
    { value: 'Sharm El Sheikh', text: 'Sharm El Sheikh' },
    { value: 'Hurghada', text: 'Hurghada' },
    { value: 'Mansoura', text: 'Mansoura' },
    { value: 'Tanta', text: 'Tanta' },
    { value: 'Aswan', text: 'Aswan' },
    { value: 'Luxor', text: 'Luxor' }
  ],
  'Jordan': [
    { value: 'Amman', text: 'Amman' },
    { value: 'Zarqa', text: 'Zarqa' },
    { value: 'Irbid', text: 'Irbid' },
    { value: 'Aqaba', text: 'Aqaba' }
  ],
  'Lebanon': [
    { value: 'Beirut', text: 'Beirut' },
    { value: 'Tripoli', text: 'Tripoli' },
    { value: 'Sidon', text: 'Sidon' },
    { value: 'Tyre', text: 'Tyre' }
  ],
  'Syria': [
    { value: 'Damascus', text: 'Damascus' },
    { value: 'Aleppo', text: 'Aleppo' },
    { value: 'Homs', text: 'Homs' },
    { value: 'Latakia', text: 'Latakia' }
  ],
  'Palestine': [
    { value: 'Ramallah', text: 'Ramallah' },
    { value: 'Gaza', text: 'Gaza' },
    { value: 'Hebron', text: 'Hebron' },
    { value: 'Nablus', text: 'Nablus' }
  ],
  'Iraq': [
    { value: 'Baghdad', text: 'Baghdad' },
    { value: 'Erbil', text: 'Erbil' },
    { value: 'Basra', text: 'Basra' },
    { value: 'Mosul', text: 'Mosul' }
  ],
  'Yemen': [
    { value: 'Sanaa', text: 'Sanaa' },
    { value: 'Aden', text: 'Aden' },
    { value: 'Taiz', text: 'Taiz' },
    { value: 'Hodeidah', text: 'Hodeidah' }
  ],
  'Oman': [
    { value: 'Muscat', text: 'Muscat' },
    { value: 'Salalah', text: 'Salalah' },
    { value: 'Sohar', text: 'Sohar' },
    { value: 'Nizwa', text: 'Nizwa' }
  ],
  'Qatar': [
    { value: 'Doha', text: 'Doha' },
    { value: 'Al Rayyan', text: 'Al Rayyan' },
    { value: 'Al Wakrah', text: 'Al Wakrah' }
  ],
  'Kuwait': [
    { value: 'Kuwait City', text: 'Kuwait City' },
    { value: 'Hawalli', text: 'Hawalli' },
    { value: 'Salmiya', text: 'Salmiya' }
  ],
  'Bahrain': [
    { value: 'Manama', text: 'Manama' },
    { value: 'Riffa', text: 'Riffa' },
    { value: 'Muharraq', text: 'Muharraq' }
  ],
  'United Kingdom': [
    { value: 'London', text: 'London' },
    { value: 'Manchester', text: 'Manchester' },
    { value: 'Birmingham', text: 'Birmingham' },
    { value: 'Liverpool', text: 'Liverpool' },
    { value: 'Leeds', text: 'Leeds' },
    { value: 'Glasgow', text: 'Glasgow' }
  ],
  'United States': [
    { value: 'New York', text: 'New York' },
    { value: 'Los Angeles', text: 'Los Angeles' },
    { value: 'Chicago', text: 'Chicago' },
    { value: 'Houston', text: 'Houston' },
    { value: 'Miami', text: 'Miami' },
    { value: 'San Francisco', text: 'San Francisco' }
  ],
  'Canada': [
    { value: 'Toronto', text: 'Toronto' },
    { value: 'Vancouver', text: 'Vancouver' },
    { value: 'Montreal', text: 'Montreal' },
    { value: 'Ottawa', text: 'Ottawa' },
    { value: 'Calgary', text: 'Calgary' }
  ],
  'Australia': [
    { value: 'Sydney', text: 'Sydney' },
    { value: 'Melbourne', text: 'Melbourne' },
    { value: 'Brisbane', text: 'Brisbane' },
    { value: 'Perth', text: 'Perth' },
    { value: 'Adelaide', text: 'Adelaide' }
  ],
  'India': [
    { value: 'Mumbai', text: 'Mumbai' },
    { value: 'Delhi', text: 'Delhi' },
    { value: 'Bangalore', text: 'Bangalore' },
    { value: 'Hyderabad', text: 'Hyderabad' },
    { value: 'Chennai', text: 'Chennai' },
    { value: 'Kolkata', text: 'Kolkata' }
  ],
  'Pakistan': [
    { value: 'Karachi', text: 'Karachi' },
    { value: 'Lahore', text: 'Lahore' },
    { value: 'Islamabad', text: 'Islamabad' },
    { value: 'Rawalpindi', text: 'Rawalpindi' }
  ]
};

// دالة لجلب المدن حسب البلد المختار
function getCitiesForCountry(countryValue) {
  if (!countryValue) return [];
  const cities = citiesByCountry[countryValue] || [];
  return cities;
}


const currencyOptions = [
  { value: 'AED', text: 'AED' },

]

const bedroomOptions = [
  { value: 'studio', text: 'Studio' },
  { value: '1', text: '1 Bedroom' },
  { value: '2', text: '2 Bedrooms' },
  { value: '3', text: '3 Bedrooms' },
  { value: '4', text: '4 Bedrooms' },
  { value: '5', text: '5 Bedrooms' },
  { value: '5+', text: '5+ Bedrooms' }
]
// Buyer city options based on selected country

// جمع كل المدن من كل الدول في مصفوفة واحدة
const allCities = computed(() => {
  const all = []
  Object.keys(citiesByCountry).forEach(country => {
    if (country !== 'other' && Array.isArray(citiesByCountry[country])) {
      citiesByCountry[country].forEach(city => {
        if (!all.includes(city)) {
          all.push(city)
        }
      })
    }
  })
  return all.sort() // ترتيب أبجدي
})

const buyerCityOptions = computed(() => {
  const country = formData.value?.buyer_country
  
  // إذا لم يتم اختيار بلد، اعرض كل المدن من كل الدول
  if (!country || country === 'other' || country === '') {
    const allCitiesList = []
    Object.keys(citiesByCountry).forEach(countryKey => {
      if (countryKey !== 'other' && Array.isArray(citiesByCountry[countryKey])) {
        citiesByCountry[countryKey].forEach(city => {
          // تجنب التكرار
          if (!allCitiesList.some(c => c.value === city.value)) {
            allCitiesList.push(city)
          }
        })
      }
    })
    return allCitiesList.sort((a, b) => a.text.localeCompare(b.text))
  }
  
  // إذا تم اختيار بلد محدد، اعرض مدن ذلك البلد فقط
  const cities = citiesByCountry[country] || []
  return cities
})

const sellerCityOptions = computed(() => {
  const country = formData.value?.seller_country
  
  if (!country || country === 'other' || country === '') {
    const allCitiesList = []
    Object.keys(citiesByCountry).forEach(countryKey => {
      if (countryKey !== 'other' && Array.isArray(citiesByCountry[countryKey])) {
        citiesByCountry[countryKey].forEach(city => {
          if (!allCitiesList.some(c => c.value === city.value)) {
            allCitiesList.push(city)
          }
        })
      }
    })
    return allCitiesList.sort((a, b) => a.text.localeCompare(b.text))
  }
  
  const cities = citiesByCountry[country] || []
  return cities
})

const tenantCityOptions = computed(() => {
  const country = formData.value?.tenant_country
  
  if (!country || country === 'other' || country === '') {
    const allCitiesList = []
    Object.keys(citiesByCountry).forEach(countryKey => {
      if (countryKey !== 'other' && Array.isArray(citiesByCountry[countryKey])) {
        citiesByCountry[countryKey].forEach(city => {
          if (!allCitiesList.some(c => c.value === city.value)) {
            allCitiesList.push(city)
          }
        })
      }
    })
    return allCitiesList.sort((a, b) => a.text.localeCompare(b.text))
  }
  
  const cities = citiesByCountry[country] || []
  return cities
})

const landlordCityOptions = computed(() => {
  const country = formData.value?.landlord_country
  
  if (!country || country === 'other' || country === '') {
    const allCitiesList = []
    Object.keys(citiesByCountry).forEach(countryKey => {
      if (countryKey !== 'other' && Array.isArray(citiesByCountry[countryKey])) {
        citiesByCountry[countryKey].forEach(city => {
          if (!allCitiesList.some(c => c.value === city.value)) {
            allCitiesList.push(city)
          }
        })
      }
    })
    return allCitiesList.sort((a, b) => a.text.localeCompare(b.text))
  }
  
  const cities = citiesByCountry[country] || []
  return cities
})

// تحديث الـ watchers لضمان صحة المدينة عند تغيير البلد
watch(() => formData.value?.buyer_country, (newCountry, oldCountry) => {
  if (newCountry !== oldCountry) {
    const currentCity = formData.value?.buyer_city
    if (currentCity && newCountry && newCountry !== 'other') {
      const validCities = citiesByCountry[newCountry] || []
      const isValid = validCities.some(city => city.value === currentCity || city.text === currentCity)
      if (!isValid) {
        formData.value.buyer_city = null
      }
    } else if (newCountry === 'other' || !newCountry) {
      // إذا كان البلد 'other' أو فارغ، احتفظ بالمدينة الحالية (قد تكون من بلد آخر)
      // لا نفعل شيئاً
    }
  }
})

// نفس الشيء للسيلر والتينانت واللاندلورد...
watch(() => formData.value?.seller_country, (newCountry, oldCountry) => {
  if (newCountry !== oldCountry) {
    const currentCity = formData.value?.seller_city
    if (currentCity && newCountry && newCountry !== 'other') {
      const validCities = citiesByCountry[newCountry] || []
      const isValid = validCities.some(city => city.value === currentCity || city.text === currentCity)
      if (!isValid) {
        formData.value.seller_city = null
      }
    }
  }
})

watch(() => formData.value?.tenant_country, (newCountry, oldCountry) => {
  if (newCountry !== oldCountry) {
    const currentCity = formData.value?.tenant_city
    if (currentCity && newCountry && newCountry !== 'other') {
      const validCities = citiesByCountry[newCountry] || []
      const isValid = validCities.some(city => city.value === currentCity || city.text === currentCity)
      if (!isValid) {
        formData.value.tenant_city = null
      }
    }
  }
})

watch(() => formData.value?.landlord_country, (newCountry, oldCountry) => {
  if (newCountry !== oldCountry) {
    const currentCity = formData.value?.landlord_city
    if (currentCity && newCountry && newCountry !== 'other') {
      const validCities = citiesByCountry[newCountry] || []
      const isValid = validCities.some(city => city.value === currentCity || city.text === currentCity)
      if (!isValid) {
        formData.value.landlord_city = null
      }
    }
  }
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
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  border: 1px solid rgba(0, 0, 0, 0.08);
}

.complete-fields-modal--compact {
  width: min(640px, 94vw);
}

.complete-fields-modal--deal-won {
  /*width: min(560px, 94vw);*/
  border: 2px solid #2ea7ef;
}

.complete-fields-modal--deal-won .modal-header-deal {
  padding: 12px 14px !important;
}

.complete-fields-modal--deal-won .form-scroll-area {
  padding: 0 10px 6px;
}

.complete-fields-modal--deal-won .section-title {
  font-size: 12px !important;
  margin-bottom: 10px !important;
}

.complete-fields-modal--deal-won .form-card {
  padding: 10px !important;
}

.complete-fields-modal--deal-won .row.g-3 {
  --bs-gutter-x: 0.75rem;
  --bs-gutter-y: 0.5rem;
}

.complete-fields-modal--deal-won :deep(.col-md-4) {
  flex: 0 0 auto;
  width: 50%;
}

.complete-fields-modal--deal-won .modal-footer-custom {
  padding: 10px 16px 12px !important;
}

.complete-fields-modal--lost-reason {
  width: min(560px, 94vw);
}

.complete-fields-modal--lost-reason .form-scroll-area {
  padding: 0 12px 8px;
}

.complete-fields-modal--lost-reason .form-section {
  margin-top: 8px;
}

.lost-reason-textarea {
  width: 100%;
  min-height: 104px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px;
  font-size: 13px;
  color: #0f172a;
  resize: none;
  outline: none;
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
}

.lost-reason-textarea::placeholder {
  font-size: 10px;
  color: #9ca3af;
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
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
}

.complete-fields-title-wrap {
  max-width: 100%;
}

.complete-fields-main-title {
  display: block;
  font-size: 14px;
  line-height: 1.2;
  letter-spacing: -0.018em;
  max-width: 52ch;
  color: var(--deal-navy-deep, #01062c);
}

@media (min-width: 768px) {
  .complete-fields-main-title {
    font-size: 14px;
    max-width: 36rem;
  }
}

.deals-type-tabs-inline {
  display: none !important;
}

.deals-type-tab-inline {
  padding: 6px 14px;
  border-radius: 100px;
  border: none;
  font-size: 12px;
  font-weight: 500;
  background: #0F172A;
  color: #fff;
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
}

.close-btn {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  cursor: pointer;
  color: #64748B;
  transition: background 0.2s, color 0.2s;
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

.deal-progress-wrapper {
  display: none;
}

.deal-progress-wrapper::-webkit-scrollbar {
  display: none;
}

.deal-progress-label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #94a3b8;
  margin-bottom: 8px;
}

.deal-progress-bar {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
}

.deal-stage-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 30px;
  min-height: 30px;
  padding: 0 10px;
  border-radius: 100px;
  border: 1px solid #e2e8f0;
  background: #fff;
  transition: background 0.2s, border-color 0.2s, box-shadow 0.2s;
  white-space: nowrap;
  box-sizing: border-box;
}

.deal-stage-pill.active {
  border-color: #0f172a;
  background: #0f172a;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.12);
}

.deal-stage-pill .stage-circle {
  width: 18px;
  height: 18px;
  min-width: 18px;
  border-radius: 50%;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.deal-stage-pill.active .stage-circle {
  background: rgba(255, 255, 255, 0.15);
  border-color: rgba(255, 255, 255, 0.35);
}

.deal-stage-pill .stage-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
}

.deal-stage-pill .stage-text {
  font-size: 12px;
  color: #64748b;
  font-weight: 500;
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
  max-width: 220px;
  overflow: hidden;
  text-overflow: ellipsis;
}

.deal-stage-pill.active .stage-text {
  color: #fff !important;
  font-weight: 600;
}

.deal-progress-hint {
  font-size: 12px;
  line-height: 1.4;
  color: #64748b;
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
}

.form-scroll-area {
  flex: 1;
  /* min-height: 0; */
  /* overflow-y: auto; */
  padding: 0 14px 8px;
}

.form-section {
  margin-top: 12px;
}

.section-title { 
  font-size: 13px !important; 
  font-weight: 600; 
  color: var(--deal-navy-deep, #01062c); 
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
  letter-spacing: -0.02em;
}

.form-card { 
  background: #fff; 
  border: 1px solid #f1f5f9; 
  box-shadow: none; 
}

.radius-12 { 
  border-radius: 8px; 
}

.form-label-custom { 
  font-size: 12px; 
  font-weight: 500; 
  color: var(--deal-text-muted, #64748b); 
  margin-bottom: 6px; 
  display: block; 
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif); 
}

.custom-input { 
  height: 40px !important; 
  min-height: 40px;
  border-radius: 8px !important; 
  border: 1px solid #E2E8F0 !important; 
  font-size: 12px !important; 
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif); 
  width: 100%;
  padding: 0 12px;
}

.custom-input::placeholder {
  font-size: 10px;
  color: #9ca3af;
}

.input-group-custom { 
  display: flex; 
  border: 1px solid #E2E8F0; 
  border-radius: 8px; 
  overflow: hidden; 
}

.input-group-custom .custom-input { 
  border: none !important; 
  flex: 1; 
  border-radius: var(--deal-input-r, 10px) 0 0 var(--deal-input-r, 10px) !important; 
}

:deep(.custom-v-select-inline) { 
  min-width: 120px; 
}

:deep(.custom-v-select-inline .vs__dropdown-toggle) { 
  height: 40px !important;
  min-height: 40px !important;
  border: none; 
  border-left: 1px solid #E2E8F0; 
  border-radius: 0 var(--deal-input-r, 10px) var(--deal-input-r, 10px) 0; 
  font-size: 11px;
}

:deep(.custom-v-select .vs__dropdown-toggle) {
  border: 1px solid #E2E8F0;
  border-radius: 8px;
  min-height: 40px !important;
  height: 40px !important;
  font-size: 12px;
  overflow: hidden;
}

:deep(.custom-v-select .vs__search::placeholder) {
  font-size: 10px;
  color: #9ca3af;
}

:deep(.custom-v-select .vs__placeholder) {
  font-size: 10px;
  color: #9ca3af;
}

:deep(.buyer-language-select .vs__selected) {
  background: #dbeafe;
  color: #1d4ed8;
  border-color: #bfdbfe;
}

:deep(.buyer-language-select .vs__dropdown-option--highlight) {
  background: #eff6ff;
  color: #1e3a8a;
}

:deep(.buyer-language-select .vs__dropdown-option--selected) {
  background: #dbeafe;
  color: #1d4ed8;
  font-weight: 600;
}

/* Keep all placeholders compact in this modal (scoped to modal root) */
.complete-fields-modal input::placeholder,
.complete-fields-modal textarea::placeholder {
  font-size: 10px !important;
}

.complete-fields-modal :deep(.vs__search::placeholder),
.complete-fields-modal :deep(.vs__placeholder) {
  font-size: 10px !important;
  color: #9ca3af;
}

.compact-placeholder-field {
  font-size: 11px !important;
}

.compact-placeholder-field::placeholder {
  font-size: 9px !important;
}

:deep(.custom-v-select .vs__open-indicator),
:deep(.custom-v-select-inline .vs__open-indicator) {
  transform: scale(0.8);
  color: #94a3b8;
}

:deep(.currency-fixed .vs__selected) {
  font-size: 11px;
  color: #64748b;
  font-weight: 600;
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
  padding: 0;
  border-radius: 100px;
  font-size: 14px;
  color: #01062C;
  cursor: pointer;
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}

.btn-next-step {
  background: #01062C;
  border: none;
  width: 96px;
  height: 40px;
  padding: 0;
  border-radius: 100px;
  font-size: 14px;
  color: #fff;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.2s;
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
  line-height: 1;
}

.btn-next-step:hover:not(:disabled) {
  background: #0f172a;
}

.btn-next-step:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.alert-warning {
  background-color: #fff3cd;
  border: 1px solid #ffe69c;
  color: #664d03;
  border-radius: 8px;
}

.modal-footer-custom .d-flex {
  justify-content: center !important;
  width: 100%;
}
/* في نهاية الـ style، أضف الكود ده */

.currency-fixed-display {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 50px;
  width: auto;
  padding: 0 12px;
  background: #f8fafc;
  border-left: 1px solid #E2E8F0;
  font-size: 12px;
  font-weight: 500;
  color: #64748b;
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
  white-space: nowrap;
}

:deep(.custom-v-select .vs__open-indicator-icon) {
    font-size: 13px;
    color: #cfdbec;
}

:deep(.custom-v-select svg) {
    vertical-align: middle !important;
}
.alert svg{
    margin: 6px;

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
    
.complete-fields-modal input.is-invalid,
.complete-fields-modal textarea.is-invalid {
  border-color: #dc3545 !important;
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linecap='round' d='M6 3v3'/%3e%3cpath stroke-linecap='round' d='M6 9.5v.01'/%3e%3c/svg%3e");
  background-repeat: no-repeat;
  background-position: right calc(0.375em + 0.1875rem) center;
  background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
  padding-right: calc(1.5em + 0.75rem) !important;
}

:deep(.custom-v-select.is-invalid .vs__dropdown-toggle) {
  border-color: #dc3545 !important;
}

:deep(.responsible-person-selector.is-invalid) {
  border-color: #dc3545 !important;
}

/* Document upload error styling */
:deep(.document-upload-container.has-error) {
  border-color: #dc3545 !important;
}

:deep(.document-upload-container .document-tile.missing) {
  border-color: #dc3545 !important;
  background-color: rgba(220, 53, 69, 0.05);
}

:deep(.document-upload-container .document-tile.missing .document-status-icon) {
  color: #dc3545;
}
</style>