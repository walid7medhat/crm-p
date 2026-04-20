<template>
  <Teleport to="body">
    <div v-if="show" class="complete-fields-overlay" @click.self="closeModal">
      <div
        class="complete-fields-modal deal-figma-ui"
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

        <!-- Stage progress (same chrome as View Deal modal) -->
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
            </div>
            <div>
              {{ unresolvedMissingLabels.join(' • ') }}
            </div>
             -->

            <!-- Source and Deal Name Section -->
            <section v-if="hasSourceAndDealNameFields()" class="form-section">
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
                    />
                  </div>
                 
                </div>
              </div>
            </section>
            <section v-if="hasField('lost_reason')" class="form-section">
              <div class="form-card p-3 radius-12">
                <label class="form-label-custom">Enter Reason For Deal Lost</label>
                <textarea
                  v-model="formData.lost_reason"
                  class="lost-reason-textarea"
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
                  <!-- Buyer First Name -->
                  <div class="col-md-6" v-if="hasField('buyer_first_name')">
                    <label class="form-label-custom">Buyer First Name <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.buyer_first_name" 
                      placeholder="Enter First Name" 
                      class="custom-input"
                    />
                  </div>
                  
                  <!-- Buyer Last Name -->
                  <div class="col-md-6" v-if="hasField('buyer_last_name')">
                    <label class="form-label-custom">Buyer Last Name <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.buyer_last_name" 
                      placeholder="Enter Last Name" 
                      class="custom-input"
                    />
                  </div>
                  
                  <!-- Buyer Phone -->
                  <div class="col-md-6" v-if="hasField('buyer_phone')">
                    <label class="form-label-custom">Buyer Phone Number <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.buyer_phone" 
                      placeholder="Enter Phone Number" 
                      class="custom-input"
                    />
                  </div>
                  
                  <!-- Buyer Email -->
                  <div class="col-md-6" v-if="hasField('buyer_email')">
                    <label class="form-label-custom">Buyer Email <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.buyer_email" 
                      type="email" 
                      placeholder="Enter Your Email" 
                      class="custom-input"
                    />
                  </div>
                  
                  <!-- Buyer Nationality -->
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
                    >
                   <template #open-indicator="{ attributes }">
                      <span v-bind="attributes">
                          <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                      </span>
                    </template>
                  
                   </v-select>
                  </div>
                  
                  <!-- Buyer Residency Status -->
                  <div class="col-md-6" v-if="hasField('buyer_residency_status')">
                    <label class="form-label-custom">Buyer Residency Status <span class="text-danger">*</span></label>
                    <v-select
 append-to-body 
                      v-model="formData.buyer_residency_status" 
                      :options="residencyOptions" 
                      :reduce="item => item.value" 
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
                  
                  <!-- Buyer Country -->
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
                    >
                     <template #open-indicator="{ attributes }">
                        <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                        </span>
                      </template>
                  
                    </v-select>
                  </div>
                  
                  <!-- Buyer City -->
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
                  >
                    <template #open-indicator="{ attributes }">
                      <span v-bind="attributes">
                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                      </span>
                    </template>
                  </v-select>
                </div>

                  <!-- Buyer Date of Birth -->
                  <div class="col-md-6" v-if="hasField('buyer_dob')">
                    <label class="form-label-custom">Buyer Date Of Birth <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.buyer_dob" 
                      type="date" 
                      class="custom-input"
                    />
                  </div>

                  <!-- Buyer Language -->
                  <div class="col-md-6" v-if="hasField('buyer_language')">
                    <label class="form-label-custom">Buyer Language <span class="text-danger">*</span></label>
                    <v-select
 append-to-body 
                      v-model="formData.buyer_language" 
                      :options="languageOptions" 
                      :reduce="item => item.value" 
                      label="text" 
                      placeholder="Select Language" 
                      class="custom-v-select"
                    >
                  
                        <template #open-indicator="{ attributes }">
                            <span v-bind="attributes">
                                <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                            </span>
                          </template>
                    </v-select>
                  </div>
                  
                  <!-- Buyer Amount -->
                  <div class="col-md-4" v-if="hasField('buyer_amount')">
                    <label class="form-label-custom">Amount</label>
                    <div class="input-group-custom">
                      <b-form-input v-model="formData.buyer_amount" type="number" placeholder="Enter Amount" class="custom-input" />
                      <div class="currency-fixed-display">
                        {{ formData.currency || 'AED' }}
                      </div>
                  </div>
                  </div>
                  
                  <!-- Buyer Party Missing -->
                  <div class="col-12" v-if="hasField('buyer_party')">
                    <div class="alert alert-warning py-2 mb-0">
                      <iconify-icon icon="lucide:alert-triangle" class="me-2"></iconify-icon>
                      <span class="small">Buyer information is required. Please add buyer details.</span>
                    </div>
                  </div>
                </div>

               
              </div>
               <!-- Buyer Documents -->
                <div class="mt-3" v-if="documentTypesByParty.buyer.length > 0">
                  <label class="form-label-custom">Buyer Documents</label>
                  <DocumentUpload
                    v-model="formData.buyer_documents"
                    category="buyer"
                    :document-types="documentTypesByParty.buyer"
                    ref="buyerDocUploadRef"
                  />
                </div>
            </section>

            <!-- Seller Section -->
            <section v-if="hasPartyFields('seller') || documentTypesByParty.seller.length > 0" class="form-section">
              <h6 class="section-title mb-3" v-if="hasPartyFields('seller')">Seller Details</h6>
              <div class="form-card p-3 radius-12" v-if="hasPartyFields('seller')">
                <div class="row g-3">
                  <!-- Seller First Name -->
                  <div class="col-md-4" v-if="hasField('seller_first_name')">
                    <label class="form-label-custom">First Name <span class="text-danger">*</span></label>
                    <b-form-input v-model="formData.seller_first_name" placeholder="Enter First Name" class="custom-input" />
                  </div>
                  
                  <!-- Seller Last Name -->
                  <div class="col-md-4" v-if="hasField('seller_last_name')">
                    <label class="form-label-custom">Last Name <span class="text-danger">*</span></label>
                    <b-form-input v-model="formData.seller_last_name" placeholder="Enter Last Name" class="custom-input" />
                  </div>
                  
                  <!-- Seller Date of Birth -->
                  <div class="col-md-4" v-if="hasField('seller_dob')">
                    <label class="form-label-custom">Date Of Birth <span class="text-danger">*</span></label>
                    <b-form-input v-model="formData.seller_dob" type="date" class="custom-input" />
                  </div>
                  
                  <!-- Seller Phone -->
                  <div class="col-md-4" v-if="hasField('seller_phone')">
                    <label class="form-label-custom">Phone <span class="text-danger">*</span></label>
                    <b-form-input v-model="formData.seller_phone" placeholder="Enter Phone" class="custom-input" />
                  </div>
                  
                  <!-- Seller Email -->
                  <div class="col-md-4" v-if="hasField('seller_email')">
                    <label class="form-label-custom">Email <span class="text-danger">*</span></label>
                    <b-form-input v-model="formData.seller_email" type="email" placeholder="Enter Email" class="custom-input" />
                  </div>
                  
                  <!-- Seller Nationality -->
                  <div class="col-md-4" v-if="hasField('seller_nationality')">
                    <label class="form-label-custom">Nationality <span class="text-danger">*</span></label>
                    <v-select
                      append-to-body v-model="formData.seller_nationality" :options="nationalityOptions" :reduce="item => item.value" label="text" placeholder="Select Nationality" class="custom-v-select" >
                      <template #open-indicator="{ attributes }">
                        <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                        </span>
                      </template>
                     </v-select>
                  </div>
                  
                  <!-- Seller Residency Status -->
                  <div class="col-md-4" v-if="hasField('seller_residency_status')">
                    <label class="form-label-custom">Residency Status <span class="text-danger">*</span></label>
                    <v-select
                        append-to-body v-model="formData.seller_residency_status" :options="residencyOptions" :reduce="item => item.value" label="text" placeholder="Select Status" class="custom-v-select">
                      
                       <template #open-indicator="{ attributes }">
                          <span v-bind="attributes">
                              <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                          </span>
                        </template>
                      </v-select>
                  </div>
                  
               
                  
                  <!-- Seller Country -->
                  <div class="col-md-4" v-if="hasField('seller_country')">
                    <label class="form-label-custom">Country Of Residence</label>
                    <v-select
                        append-to-body v-model="formData.seller_country" :options="countryOptions" :reduce="item => item.value" label="text" placeholder="Select Country" class="custom-v-select" >
                    
                             <template #open-indicator="{ attributes }">
                              <span v-bind="attributes">
                                  <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                              </span>
                            </template>
                      </v-select>
                  </div>
                  <!-- Seller City -->
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
                      >
                        <template #open-indicator="{ attributes }">
                          <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                          </span>
                        </template>
                      </v-select>
                    </div>
                  <!-- Seller Language -->
                  <div class="col-md-4" v-if="hasField('seller_language')">
                    <label class="form-label-custom">Language <span class="text-danger">*</span></label>
                    <v-select
                        append-to-body v-model="formData.seller_language" :options="languageOptions" :reduce="item => item.value" label="text" placeholder="Select Language" class="custom-v-select" >
                        <template #open-indicator="{ attributes }">
                          <span v-bind="attributes">
                              <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                          </span>
                        </template>
                      </v-select>
                  </div>
                  
                  <!-- Seller Party Missing -->
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
                  <label class="form-label-custom">Seller Documents</label>
                  <DocumentUpload
                    v-model="formData.seller_documents"
                    category="seller"
                    :document-types="documentTypesByParty.seller"
                    ref="sellerDocUploadRef"
                  />
                </div>
            </section>

            <!-- Tenant Section -->
            <section v-if="hasPartyFields('tenant') || documentTypesByParty.tenant.length > 0" class="form-section">
              <h6 class="section-title mb-3" v-if="hasPartyFields('tenant')">Tenant Details</h6>
              <div class="form-card p-3 radius-12" v-if="hasPartyFields('tenant')">
                <div class="row g-3">
                  <!-- Tenant First Name -->
                  <div class="col-md-4" v-if="hasField('tenant_first_name')">
                    <label class="form-label-custom">First Name <span class="text-danger">*</span></label>
                    <b-form-input v-model="formData.tenant_first_name" placeholder="Enter First Name" class="custom-input" />
                  </div>
                  
                  <!-- Tenant Last Name -->
                  <div class="col-md-4" v-if="hasField('tenant_last_name')">
                    <label class="form-label-custom">Last Name <span class="text-danger">*</span></label>
                    <b-form-input v-model="formData.tenant_last_name" placeholder="Enter Last Name" class="custom-input" />
                  </div>
                  
                  <!-- Tenant Phone -->
                  <div class="col-md-4" v-if="hasField('tenant_phone')">
                    <label class="form-label-custom">Phone <span class="text-danger">*</span></label>
                    <b-form-input v-model="formData.tenant_phone" placeholder="Enter Phone" class="custom-input" />
                  </div>
                  
                  <!-- Tenant Email -->
                  <div class="col-md-4" v-if="hasField('tenant_email')">
                    <label class="form-label-custom">Email <span class="text-danger">*</span></label>
                    <b-form-input v-model="formData.tenant_email" type="email" placeholder="Enter Email" class="custom-input" />
                  </div>
                  
                  <!-- Tenant Nationality -->
                  <div class="col-md-4" v-if="hasField('tenant_nationality')">
                    <label class="form-label-custom">Nationality <span class="text-danger">*</span></label>
                    <v-select
                          append-to-body v-model="formData.tenant_nationality" :options="nationalityOptions" :reduce="item => item.value" label="text" placeholder="Select Nationality" class="custom-v-select" >
                         <template #open-indicator="{ attributes }">
                              <span v-bind="attributes">
                                  <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                              </span>
                            </template>
                        
                    </v-select>
                  </div>
                  
                  <!-- Tenant Residency Status -->
                  <div class="col-md-4" v-if="hasField('tenant_residency_status')">
                    <label class="form-label-custom">Residency Status <span class="text-danger">*</span></label>
                    <v-select
                          append-to-body v-model="formData.tenant_residency_status" :options="residencyOptions" :reduce="item => item.value" label="text" placeholder="Select Status" class="custom-v-select" >
                    
                     <template #open-indicator="{ attributes }">
                        <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                        </span>
                      </template>
                    </v-select>
                  </div>
                  
                
                  
                  <!-- Tenant Country -->
                  <div class="col-md-4" v-if="hasField('tenant_country')">
                    <label class="form-label-custom">Country Of Residence</label>
                    <v-select
                        append-to-body v-model="formData.tenant_country" :options="countryOptions" :reduce="item => item.value" label="text" placeholder="Select Country" class="custom-v-select" >
                       
                         <template #open-indicator="{ attributes }">
                            <span v-bind="attributes">
                                <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                            </span>
                          </template>
                    
                      </v-select>
                  </div>
                  <!-- Tenant City -->
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
                      >
                        <template #open-indicator="{ attributes }">
                          <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                          </span>
                        </template>
                      </v-select>
                    </div>
                  
                  <!-- Tenant Language -->
                  <div class="col-md-4" v-if="hasField('tenant_language')">
                    <label class="form-label-custom">Language <span class="text-danger">*</span></label>
                    <v-select
                        append-to-body v-model="formData.tenant_language" :options="languageOptions" :reduce="item => item.value" label="text" placeholder="Select Language" class="custom-v-select" >
                       
                         <template #open-indicator="{ attributes }">
                            <span v-bind="attributes">
                                <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                            </span>
                          </template>
                    
                      </v-select>
                  </div>
                  
                  <!-- Tenant Amount -->
                  <div class="col-md-4" v-if="hasField('tenant_amount')">
                    <label class="form-label-custom">Amount</label>
                    <div class="input-group-custom">
                      <b-form-input v-model="formData.tenant_amount" type="number" placeholder="Enter Amount" class="custom-input" />
                      <div class="currency-fixed-display">
                          {{ formData.currency || 'AED' }}
                        </div>
                    </div>
                  </div>
                  
                  <!-- Tenant Party Missing -->
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
                  <label class="form-label-custom">Tenant Documents</label>
                  <DocumentUpload
                    v-model="formData.tenant_documents"
                    category="tenant"
                    :document-types="documentTypesByParty.tenant"
                    ref="tenantDocUploadRef"
                  />
                </div>
            </section>

            <!-- Landlord Section -->
            <section v-if="hasPartyFields('landlord') || documentTypesByParty.landlord.length > 0" class="form-section">
              <h6 class="section-title mb-3" v-if="hasPartyFields('landlord')">Landlord Details</h6>
              <div class="form-card p-3 radius-12" v-if="hasPartyFields('landlord')">
                <div class="row g-3">
                  <!-- Landlord First Name -->
                  <div class="col-md-4" v-if="hasField('landlord_first_name')">
                    <label class="form-label-custom">First Name <span class="text-danger">*</span></label>
                    <b-form-input v-model="formData.landlord_first_name" placeholder="Enter First Name" class="custom-input" />
                  </div>
                  
                  <!-- Landlord Last Name -->
                  <div class="col-md-4" v-if="hasField('landlord_last_name')">
                    <label class="form-label-custom">Last Name <span class="text-danger">*</span></label>
                    <b-form-input v-model="formData.landlord_last_name" placeholder="Enter Last Name" class="custom-input" />
                  </div>
                  
                  <!-- Landlord Date of Birth -->
                  <div class="col-md-4" v-if="hasField('landlord_dob')">
                    <label class="form-label-custom">Date Of Birth <span class="text-danger">*</span></label>
                    <b-form-input v-model="formData.landlord_dob" type="date" class="custom-input" />
                  </div>
                  
                  <!-- Landlord Phone -->
                  <div class="col-md-4" v-if="hasField('landlord_phone')">
                    <label class="form-label-custom">Phone <span class="text-danger">*</span></label>
                    <b-form-input v-model="formData.landlord_phone" placeholder="Enter Phone" class="custom-input" />
                  </div>
                  
                  <!-- Landlord Email -->
                  <div class="col-md-4" v-if="hasField('landlord_email')">
                    <label class="form-label-custom">Email <span class="text-danger">*</span></label>
                    <b-form-input v-model="formData.landlord_email" type="email" placeholder="Enter Email" class="custom-input" />
                  </div>
                  
                  <!-- Landlord Nationality -->
                  <div class="col-md-4" v-if="hasField('landlord_nationality')">
                    <label class="form-label-custom">Nationality <span class="text-danger">*</span></label>
                    <v-select
                            append-to-body v-model="formData.landlord_nationality" :options="nationalityOptions" :reduce="item => item.value" label="text" placeholder="Select Nationality" class="custom-v-select" >
                     <template #open-indicator="{ attributes }">
                        <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                        </span>
                      </template>
                    
                    </v-select>
                  </div>
                  
                  <!-- Landlord Residency Status -->
                  <div class="col-md-4" v-if="hasField('landlord_residency_status')">
                    <label class="form-label-custom">Residency Status <span class="text-danger">*</span></label>
                    <v-select
                            append-to-body v-model="formData.landlord_residency_status" :options="residencyOptions" :reduce="item => item.value" label="text" placeholder="Select Status" class="custom-v-select" >
                    
                     <template #open-indicator="{ attributes }">
                        <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                        </span>
                      </template>
                    </v-select>
                  </div>
                  
                  
                  
                  <!-- Landlord Country -->
                  <div class="col-md-4" v-if="hasField('landlord_country')">
                    <label class="form-label-custom">Country Of Residence</label>
                    <v-select
                      append-to-body v-model="formData.landlord_country" :options="countryOptions" :reduce="item => item.value" label="text" placeholder="Select Country" class="custom-v-select" >
                       
                       <template #open-indicator="{ attributes }">
                        <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                        </span>
                      </template>
                    
                    </v-select>
                  </div>
                  <!-- Landlord City -->
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
                      >
                        <template #open-indicator="{ attributes }">
                          <span v-bind="attributes">
                            <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                          </span>
                        </template>
                      </v-select>
                    </div>
                  
                  <!-- Landlord Language -->
                  <div class="col-md-4" v-if="hasField('landlord_language')">
                    <label class="form-label-custom">Language <span class="text-danger">*</span></label>
                    <v-select
                      append-to-body v-model="formData.landlord_language" :options="languageOptions" :reduce="item => item.value" label="text" placeholder="Select Language" class="custom-v-select" >
                       
                       <template #open-indicator="{ attributes }">
                          <span v-bind="attributes">
                              <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                          </span>
                        </template>
                    
                    </v-select>
                  </div>
                  
                  <!-- Landlord Party Missing -->
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
                  <label class="form-label-custom">Landlord Documents</label>
                  <DocumentUpload
                    v-model="formData.landlord_documents"
                    category="landlord"
                    :document-types="documentTypesByParty.landlord"
                    ref="landlordDocUploadRef"
                  />
                </div>
            </section>

            <!-- Property Details Section -->
            <section v-if="hasPropertyFields()" class="form-section">
              <h6 class="section-title mb-3">Property Details</h6>
              <div class="form-card p-3 radius-12">
                <div class="row g-3">
                  <div class="col-md-6" v-if="hasField('unit_no')">
                    <label class="form-label-custom">Unit No <span class="text-danger">*</span></label>
                    <b-form-input v-model="formData.unit_no" placeholder="Enter Unit No" class="custom-input" />
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
                    >
                      <template #open-indicator="{ attributes }">
                          <span v-bind="attributes">
                              <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                          </span>
                        </template>
                  
                     </v-select>
                  </div>
                  
                  <div class="col-md-6" v-if="hasField('subcommunity_id')">
                    <label class="form-label-custom">Subcommunity <span class="text-danger">*</span></label>
                    <v-select
                        append-to-body 
                      v-model="formData.subcommunity_id" 
                      :options="areas" 
                      :reduce="item => item.id" 
                      label="name" 
                      placeholder="Search Subcommunity..." 
                      class="custom-v-select"
                      :filterable="true"
                      @search="onSearchAreas"
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
                    >
                   <template #open-indicator="{ attributes }">
                      <span v-bind="attributes">
                          <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                      </span>
                    </template>
                   </v-select>
                  </div>
                  
                  <div class="col-md-6" v-if="hasField('area_id')">
                    <label class="form-label-custom">Area</label>
                    <v-select
                        append-to-body 
                      v-model="formData.area_id" 
                      :options="areas" 
                      :reduce="item => item.id" 
                      label="name" 
                      placeholder="Select Area" 
                      :filterable="true"
                      class="custom-v-select" 
                        @search="onSearchAreas"
                    >
                  
                   <template #open-indicator="{ attributes }">
                      <span v-bind="attributes">
                          <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                      </span>
                    </template>
                   </v-select>
                  </div>
                  
                  <div class="col-md-6" v-if="hasField('unit_size')">
                    <label class="form-label-custom">Unit Size</label>
                    <b-form-input v-model="formData.unit_size" placeholder="Enter Unit Size (sq. ft)" class="custom-input" />
                  </div>
                </div>
              </div>
            </section>

            <!-- Deal Financials Section -->
            <section v-if="hasFinancialFields()" class="form-section">
              <h6 class="section-title mb-3">Deal Financials</h6>
              <div class="form-card p-3 radius-12">
                <div class="row g-3">
                  <div class="col-md-4" v-if="hasField('deal_total_amount')">
                    <label class="form-label-custom">Deal Total Amount</label>
                    <div class="input-group-custom">
                      <b-form-input v-model="formData.deal_total_amount" type="number" placeholder="Enter Amount" class="custom-input" />
                      <div class="currency-fixed-display">
                        {{ formData.currency || 'AED' }}
                      </div>
                      </div>
                  </div>
                  
                  <div class="col-md-4" v-if="hasField('deal_commission')">
                    <label class="form-label-custom">Deal Commission %</label>
                    <b-form-input v-model="formData.deal_commission" type="number" placeholder="Enter Commission %" class="custom-input" />
                  </div>
                  
                  <div class="col-md-4" v-if="hasField('agent_share')">
                    <label class="form-label-custom">Agent Share %</label>
                    <b-form-input v-model="formData.agent_share" type="number" placeholder="Enter Agent Share %" class="custom-input" />
                  </div>
                  
                  <div class="col-md-4" v-if="hasField('company_share')">
                    <label class="form-label-custom">Company Share %</label>
                    <b-form-input v-model="formData.company_share" type="number" placeholder="Enter Company Share %" class="custom-input" />
                  </div>
                </div>
              </div>
            </section>
              <!-- Responsible Person -->
              <div class="col-12 mt-3" v-if="hasField('responsible_person_id')">
                <ResponsiblePersonSelector 
                  v-model="formData.responsible_person_id" 
                  :users="users" 
                />
              </div>
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
import api from '@/plugins/axios'

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

const emit = defineEmits(['save', 'closed', 'open-deal'])

// State
const formData = ref({})
const submitting = ref(false)
const loading = ref(false)
let submitResetTimer = null

// Data from API
const users = ref([])
const sources = ref([])
const propertyTypes = ref([])
const developers = ref([])
const areas = ref([])
const projects = ref([])

// Document upload refs
const buyerDocUploadRef = ref(null)
const sellerDocUploadRef = ref(null)
const tenantDocUploadRef = ref(null)
const landlordDocUploadRef = ref(null)

// ================ حساب document types مرة واحدة فقط ================
const documentTypesByParty = computed(() => {
  const result = {
    buyer: [],
    seller: [],
    tenant: [],
    landlord: []
  }
  
  if (!props.missingFields || !Array.isArray(props.missingFields)) {
    return result
  }
  
  props.missingFields.forEach(field => {
    if (field.endsWith('_party')) return
    if (field && field.includes('_document_')) {
      const [partyType, docPart] = field.split('_document_')
      const docType = docPart
      
      if (partyType && docType && result.hasOwnProperty(partyType)) {
        result[partyType].push({
          id: docType,
          name: docType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
          required: true
        })
      }
    }
  })
  
  console.log('Document types by party:', result)
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

const missingFieldLabels = computed(() => {
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
  const missingKeys = props.missingFields || []

  missingKeys.forEach((key) => {
    if (!key) return
    if (key === 'currency') return
    if (key.endsWith('_party')) return

    if (key.includes('_document_')) {
      const [partyType, docType] = key.split('_document_')
      const docs = formData.value?.[`${partyType}_documents`] || []
      const hasDoc = Array.isArray(docs) && docs.some((doc) => doc?.file && doc?.document_type === docType)
      if (!hasDoc) unresolved.push(key)
      return
    }

    const value = formData.value?.[key]
    const isEmpty = value === null || value === undefined || value === ''
    if (isEmpty) unresolved.push(key)
  })

  return unresolved
})

const canSubmit = computed(() => {
  return !loading.value && !submitting.value && unresolvedMissingKeys.value.length === 0
})

const isCompactStageModal = computed(() => {
  const count = (props.missingFields || []).length
  return count > 0 && count <= 4
})

const isDealWonStage = computed(() => {
  return String(props.targetStageName || '').toLowerCase().includes('deal won')
})

const isLostReasonOnly = computed(() => {
  const keys = props.missingFields || []
  return keys.length === 1 && keys[0] === 'lost_reason'
})

// Load initial data
onMounted(async () => {
  await Promise.all([
    fetchUsers(),
    fetchSources(),
    fetchPropertyTypes()
  ])
})

// Watch for modal open
const isInitialized = ref(false)
watch(() => formData.value.buyer_documents, (newVal) => {
  console.log('buyer_documents changed:', newVal)
}, { deep: true })
watch(() => props.show, async (val) => {
  if (val && !isInitialized.value) {
    isInitialized.value = true
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

// Initialize form with missing fields
async function initializeForm() {
  loading.value = true
  try {
    const missingFieldKeys = props.missingFields || []
    console.log('Initializing form with missing fields:', missingFieldKeys)
    
    const initial = {}
    
    // Initialize each missing field (except document fields)
    missingFieldKeys.forEach(key => {
      if (!key.includes('_document_')) {
        initial[key] = getInitialValue(key)
      }
    })
    
    formData.value = { ...initial }
    if (!formData.value.currency) formData.value.currency = 'AED'
    
    // Initialize document arrays for each party
    const parties = ['buyer', 'seller', 'tenant', 'landlord']
    parties.forEach(party => {
      const partyDocs = missingFieldKeys.filter(key => 
        key.startsWith(`${party}_document_`)
      )
      if (partyDocs.length > 0) {
        formData.value[`${party}_documents`] = []
      }
    })
    
    if (missingFieldKeys.includes('subcommunity_id')) {
      await fetchAreas()
    }
    
    console.log('Form initialized:', formData.value)
    console.log('Form data keys:', Object.keys(formData.value))
    
  } catch (error) {
    console.error('Error initializing form:', error)
  } finally {
    loading.value = false
  }
}

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
      if (field === 'amount') return party.amount || ''
      return party[field] || ''
    }
  }
  
  return ''
}

// ================ دوال التحقق ================

// التحقق من وجود حقل معين
function hasField(fieldKey) {
  return props.missingFields?.includes(fieldKey) || false
}

// التحقق من وجود حقول طرف معين
function hasPartyFields(partyType) {
  if (!props.missingFields || props.missingFields.length === 0) return false
  
  const possibleFields = [
    `${partyType}_first_name`,
    `${partyType}_last_name`,
    `${partyType}_phone`,
    `${partyType}_email`,
    `${partyType}_nationality`,
    `${partyType}_dob`,
    `${partyType}_residency_status`,
    `${partyType}_city`,
    `${partyType}_country`,
    `${partyType}_language`,
    `${partyType}_amount`,
  ]
  
  return possibleFields.some(field => props.missingFields.includes(field))
}

// التحقق من وجود حقول property
function hasPropertyFields() {
  const propertyFields = [
    'unit_no', 'property_type_id', 'subcommunity_id', 
    'bedrooms', 'area_id', 'unit_size'
  ]
  return propertyFields.some(field => hasField(field))
}

// التحقق من وجود حقول financial
function hasFinancialFields() {
  const financialFields = [
    'deal_total_amount', 'deal_commission', 
    'agent_share', 'company_share'
  ]
  return financialFields.some(field => hasField(field))
}

// التحقق من وجود source and deal name
function hasSourceAndDealNameFields() {
  const fields = ['source', 'deal_name']
  return fields.some(field => hasField(field))
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
  { value: 'ar', text: 'Arabic' },
  { value: 'en', text: 'English' },
  { value: 'fr', text: 'French' },
  { value: 'es', text: 'Spanish' },
  { value: 'de', text: 'German' },
  { value: 'it', text: 'Italian' },
  { value: 'ru', text: 'Russian' },
  { value: 'zh', text: 'Chinese' },
  { value: 'hi', text: 'Hindi' },
  { value: 'ur', text: 'Urdu' },
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
  { value: 'other', text: 'Other' }
]

const residencyOptions = [
  { value: 'citizen', text: 'Citizen' },
  { value: 'resident', text: 'Resident' },
  { value: 'investor', text: 'Investor' },
  { value: 'tourist', text: 'Tourist' },
  { value: 'student', text: 'Student' },
  { value: 'other', text: 'Other' }
]

const countryOptions = [
  { value: 'United Arab Emirates', text: 'United Arab Emirates' },
  { value: 'Saudi Arabia', text: 'Saudi Arabia' },
  { value: 'Egypt', text: 'Egypt' },
  { value: 'Jordan', text: 'Jordan' },
  { value: 'Lebanon', text: 'Lebanon' },
  { value: 'Syria', text: 'Syria' },
  { value: 'Palestine', text: 'Palestine' },
  { value: 'Iraq', text: 'Iraq' },
  { value: 'Yemen', text: 'Yemen' },
  { value: 'Oman', text: 'Oman' },
  { value: 'Qatar', text: 'Qatar' },
  { value: 'Kuwait', text: 'Kuwait' },
  { value: 'Bahrain', text: 'Bahrain' },
  { value: 'United Kingdom', text: 'United Kingdom' },
  { value: 'United States', text: 'United States' },
  { value: 'Canada', text: 'Canada' },
  { value: 'Australia', text: 'Australia' },
  { value: 'India', text: 'India' },
  { value: 'Pakistan', text: 'Pakistan' },
  { value: 'other', text: 'Other' }
];

// Update citiesByCountry to use objects with value/text pairs
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
  ],
  'other': []
};


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
  font-size: 12px;
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
}

:deep(.custom-v-select .vs__search::placeholder) {
  font-size: 12px;
  color: #9ca3af;
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

</style>