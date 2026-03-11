<template>
  <Teleport to="body">
    <div v-if="show" class="complete-fields-overlay" @click.self="closeModal">
      <div class="complete-fields-modal">
        <!-- Header -->
        <div class="modal-header-deal p-3">
          <div class="d-flex justify-content-between align-items-center w-100">
            <div class="d-flex align-items-center gap-3">
              <span class="modal-title">Complete Required Fields</span>
              <div class="deals-type-tabs-inline d-flex gap-2">
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

        <!-- Stage Progress -->
        <div class="deal-progress-wrapper py-3 px-3" v-if="targetStageName">
          <div class="deal-progress-bar">
            <div class="deal-stage-pill active">
              <div class="stage-circle">
                <div class="stage-dot" style="background-color: #3B82F6;"></div>
              </div>
              <span class="stage-text">{{ targetStageName }}</span>
            </div>
          </div>
          <p class="text-muted small mt-2 mb-0">
            <iconify-icon icon="lucide:info" class="me-1"></iconify-icon>
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
            <!-- Source and Deal Name Section -->
            <section v-if="hasSourceAndDealNameFields()" class="form-section">
              <h6 class="section-title mb-3">Source and Deal Name</h6>
              <div class="form-card p-3 radius-12">
                <div class="row g-3">
                  <div class="col-md-6" v-if="hasField('source')">
                    <label class="form-label-custom">Source <span class="text-danger">*</span></label>
                    <v-select 
                      v-model="formData.source" 
                      :options="sources" 
                      :reduce="item => item.name" 
                      label="name" 
                      placeholder="Select Source" 
                      class="custom-v-select" 
                    />
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
             <section  v-if="hasField('lost_reason')"class="form-section">
              <h6 class="section-title mb-3">Lost Reason </h6>
                    <div class="col-md-6" v-if="hasField('lost_reason')">
                    <!--<label class="form-label-custom">Lost Reason <span class="text-danger">*</span></label>-->
                   <b-form-input 
                      v-model="formData.lost_reason" 
                      placeholder="Enter Lost Reason" 
                      class="custom-input"
                    />
                  </div>
                  </section>
            <!-- Buyer Section -->
            <section v-if="hasPartyFields('buyer') || documentTypesByParty.buyer.length > 0" class="form-section">
              <h6 class="section-title mb-3" v-if="hasPartyFields('buyer')">Buyer Details</h6>
              <div class="form-card p-3 radius-12" v-if="hasPartyFields('buyer')">
                <div class="row g-3">
                  <!-- Buyer First Name -->
                  <div class="col-md-4" v-if="hasField('buyer_first_name')">
                    <label class="form-label-custom">First Name <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.buyer_first_name" 
                      placeholder="Enter First Name" 
                      class="custom-input"
                    />
                  </div>
                  
                  <!-- Buyer Last Name -->
                  <div class="col-md-4" v-if="hasField('buyer_last_name')">
                    <label class="form-label-custom">Last Name <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.buyer_last_name" 
                      placeholder="Enter Last Name" 
                      class="custom-input"
                    />
                  </div>
                  
                  <!-- Buyer Date of Birth -->
                  <div class="col-md-4" v-if="hasField('buyer_dob')">
                    <label class="form-label-custom">Date Of Birth <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.buyer_dob" 
                      type="date" 
                      class="custom-input"
                    />
                  </div>
                  
                  <!-- Buyer Phone -->
                  <div class="col-md-4" v-if="hasField('buyer_phone')">
                    <label class="form-label-custom">Phone <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.buyer_phone" 
                      placeholder="Enter Phone" 
                      class="custom-input"
                    />
                  </div>
                  
                  <!-- Buyer Email -->
                  <div class="col-md-4" v-if="hasField('buyer_email')">
                    <label class="form-label-custom">Email <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.buyer_email" 
                      type="email" 
                      placeholder="Enter Email" 
                      class="custom-input"
                    />
                  </div>
                  
                  <!-- Buyer Nationality -->
                  <div class="col-md-4" v-if="hasField('buyer_nationality')">
                    <label class="form-label-custom">Nationality <span class="text-danger">*</span></label>
                    <v-select 
                      v-model="formData.buyer_nationality" 
                      :options="nationalityOptions" 
                      :reduce="item => item.value" 
                      label="text" 
                      placeholder="Select Nationality" 
                      class="custom-v-select"
                    />
                  </div>
                  
                  <!-- Buyer Residency Status -->
                  <div class="col-md-4" v-if="hasField('buyer_residency_status')">
                    <label class="form-label-custom">Residency Status <span class="text-danger">*</span></label>
                    <v-select 
                      v-model="formData.buyer_residency_status" 
                      :options="residencyOptions" 
                      :reduce="item => item.value" 
                      label="text" 
                      placeholder="Select Status" 
                      class="custom-v-select"
                    />
                  </div>
                  
                  <!-- Buyer City -->
                  <div class="col-md-4" v-if="hasField('buyer_city')">
                    <label class="form-label-custom">City Of Residence <span class="text-danger">*</span></label>
                    <b-form-input 
                      v-model="formData.buyer_city" 
                      placeholder="Enter City" 
                      class="custom-input"
                    />
                  </div>
                  
                  <!-- Buyer Country -->
                  <div class="col-md-4" v-if="hasField('buyer_country')">
                    <label class="form-label-custom">Country Of Residence</label>
                    <v-select 
                      v-model="formData.buyer_country" 
                      :options="countryOptions" 
                      :reduce="item => item.value" 
                      label="text" 
                      placeholder="Select Country" 
                      class="custom-v-select"
                    />
                  </div>
                  
                  <!-- Buyer Language -->
                  <div class="col-md-4" v-if="hasField('buyer_language')">
                    <label class="form-label-custom">Language <span class="text-danger">*</span></label>
                    <v-select 
                      v-model="formData.buyer_language" 
                      :options="languageOptions" 
                      :reduce="item => item.value" 
                      label="text" 
                      placeholder="Select Language" 
                      class="custom-v-select"
                    />
                  </div>
                  
                  <!-- Buyer Amount -->
                  <div class="col-md-4" v-if="hasField('buyer_amount')">
                    <label class="form-label-custom">Amount</label>
                    <div class="input-group-custom">
                      <b-form-input v-model="formData.buyer_amount" type="number" placeholder="Enter Amount" class="custom-input" />
                      <v-select 
                        v-model="formData.currency" 
                        :options="currencyOptions" 
                        :reduce="o => o.value" 
                        label="text" 
                        :clearable="false" 
                        class="custom-v-select-inline" 
                      />
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
                    <v-select v-model="formData.seller_nationality" :options="nationalityOptions" :reduce="item => item.value" label="text" placeholder="Select Nationality" class="custom-v-select" />
                  </div>
                  
                  <!-- Seller Residency Status -->
                  <div class="col-md-4" v-if="hasField('seller_residency_status')">
                    <label class="form-label-custom">Residency Status <span class="text-danger">*</span></label>
                    <v-select v-model="formData.seller_residency_status" :options="residencyOptions" :reduce="item => item.value" label="text" placeholder="Select Status" class="custom-v-select" />
                  </div>
                  
                  <!-- Seller City -->
                  <div class="col-md-4" v-if="hasField('seller_city')">
                    <label class="form-label-custom">City Of Residence <span class="text-danger">*</span></label>
                    <b-form-input v-model="formData.seller_city" placeholder="Enter City" class="custom-input" />
                  </div>
                  
                  <!-- Seller Country -->
                  <div class="col-md-4" v-if="hasField('seller_country')">
                    <label class="form-label-custom">Country Of Residence</label>
                    <v-select v-model="formData.seller_country" :options="countryOptions" :reduce="item => item.value" label="text" placeholder="Select Country" class="custom-v-select" />
                  </div>
                  
                  <!-- Seller Language -->
                  <div class="col-md-4" v-if="hasField('seller_language')">
                    <label class="form-label-custom">Language <span class="text-danger">*</span></label>
                    <v-select v-model="formData.seller_language" :options="languageOptions" :reduce="item => item.value" label="text" placeholder="Select Language" class="custom-v-select" />
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
                    <v-select v-model="formData.tenant_nationality" :options="nationalityOptions" :reduce="item => item.value" label="text" placeholder="Select Nationality" class="custom-v-select" />
                  </div>
                  
                  <!-- Tenant Residency Status -->
                  <div class="col-md-4" v-if="hasField('tenant_residency_status')">
                    <label class="form-label-custom">Residency Status <span class="text-danger">*</span></label>
                    <v-select v-model="formData.tenant_residency_status" :options="residencyOptions" :reduce="item => item.value" label="text" placeholder="Select Status" class="custom-v-select" />
                  </div>
                  
                  <!-- Tenant City -->
                  <div class="col-md-4" v-if="hasField('tenant_city')">
                    <label class="form-label-custom">City Of Residence <span class="text-danger">*</span></label>
                    <b-form-input v-model="formData.tenant_city" placeholder="Enter City" class="custom-input" />
                  </div>
                  
                  <!-- Tenant Country -->
                  <div class="col-md-4" v-if="hasField('tenant_country')">
                    <label class="form-label-custom">Country Of Residence</label>
                    <v-select v-model="formData.tenant_country" :options="countryOptions" :reduce="item => item.value" label="text" placeholder="Select Country" class="custom-v-select" />
                  </div>
                  
                  <!-- Tenant Language -->
                  <div class="col-md-4" v-if="hasField('tenant_language')">
                    <label class="form-label-custom">Language <span class="text-danger">*</span></label>
                    <v-select v-model="formData.tenant_language" :options="languageOptions" :reduce="item => item.value" label="text" placeholder="Select Language" class="custom-v-select" />
                  </div>
                  
                  <!-- Tenant Amount -->
                  <div class="col-md-4" v-if="hasField('tenant_amount')">
                    <label class="form-label-custom">Amount</label>
                    <div class="input-group-custom">
                      <b-form-input v-model="formData.tenant_amount" type="number" placeholder="Enter Amount" class="custom-input" />
                      <v-select v-model="formData.currency" :options="currencyOptions" :reduce="o => o.value" label="text" :clearable="false" class="custom-v-select-inline" />
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
                    <v-select v-model="formData.landlord_nationality" :options="nationalityOptions" :reduce="item => item.value" label="text" placeholder="Select Nationality" class="custom-v-select" />
                  </div>
                  
                  <!-- Landlord Residency Status -->
                  <div class="col-md-4" v-if="hasField('landlord_residency_status')">
                    <label class="form-label-custom">Residency Status <span class="text-danger">*</span></label>
                    <v-select v-model="formData.landlord_residency_status" :options="residencyOptions" :reduce="item => item.value" label="text" placeholder="Select Status" class="custom-v-select" />
                  </div>
                  
                  <!-- Landlord City -->
                  <div class="col-md-4" v-if="hasField('landlord_city')">
                    <label class="form-label-custom">City Of Residence <span class="text-danger">*</span></label>
                    <b-form-input v-model="formData.landlord_city" placeholder="Enter City" class="custom-input" />
                  </div>
                  
                  <!-- Landlord Country -->
                  <div class="col-md-4" v-if="hasField('landlord_country')">
                    <label class="form-label-custom">Country Of Residence</label>
                    <v-select v-model="formData.landlord_country" :options="countryOptions" :reduce="item => item.value" label="text" placeholder="Select Country" class="custom-v-select" />
                  </div>
                  
                  <!-- Landlord Language -->
                  <div class="col-md-4" v-if="hasField('landlord_language')">
                    <label class="form-label-custom">Language <span class="text-danger">*</span></label>
                    <v-select v-model="formData.landlord_language" :options="languageOptions" :reduce="item => item.value" label="text" placeholder="Select Language" class="custom-v-select" />
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
                  <div class="col-md-4" v-if="hasField('unit_no')">
                    <label class="form-label-custom">Unit No <span class="text-danger">*</span></label>
                    <b-form-input v-model="formData.unit_no" placeholder="Enter Unit No" class="custom-input" />
                  </div>
                  
                  <div class="col-md-4" v-if="hasField('property_type_id')">
                    <label class="form-label-custom">Property Type <span class="text-danger">*</span></label>
                    <v-select 
                      v-model="formData.property_type_id" 
                      :options="propertyTypes" 
                      :reduce="item => item.id" 
                      label="name" 
                      placeholder="Select Property Type" 
                      class="custom-v-select"
                    />
                  </div>
                  
                  <div class="col-md-4" v-if="hasField('subcommunity_id')">
                    <label class="form-label-custom">Subcommunity <span class="text-danger">*</span></label>
                    <v-select 
                      v-model="formData.subcommunity_id" 
                      :options="areas" 
                      :reduce="item => item.id" 
                      label="name" 
                      placeholder="Search Subcommunity..." 
                      class="custom-v-select"
                      :filterable="false"
                      @search="onSearchAreas"
                    />
                  </div>
                  
                  <div class="col-md-4" v-if="hasField('bedrooms')">
                    <label class="form-label-custom">Bedrooms</label>
                    <v-select 
                      v-model="formData.bedrooms" 
                      :options="bedroomOptions" 
                      :reduce="o => o.value" 
                      label="text" 
                      placeholder="Select Bedroom" 
                      class="custom-v-select" 
                    />
                  </div>
                  
                  <div class="col-md-4" v-if="hasField('area_id')">
                    <label class="form-label-custom">Area</label>
                    <v-select 
                      v-model="formData.area_id" 
                      :options="areas" 
                      :reduce="item => item.id" 
                      label="name" 
                      placeholder="Select Area" 
                      :filterable="false"
                      class="custom-v-select" 
                    />
                  </div>
                  
                  <div class="col-md-4" v-if="hasField('unit_size')">
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
                      <v-select 
                        v-model="formData.currency" 
                        :options="currencyOptions" 
                        :reduce="o => o.value" 
                        label="text" 
                        :clearable="false" 
                        class="custom-v-select-inline" 
                      />
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
          <div class="d-flex align-items-center justify-content-end gap-3">
            <button class="btn-clear" @click="closeModal" :disabled="submitting">Cancel</button>
            <button class="btn-next-step" @click="submitForm" :disabled="submitting">
              <span v-if="submitting">
                <b-spinner small></b-spinner> Saving...
              </span>
              <span v-else>
                Save & Move Deal
                <iconify-icon icon="lucide:chevron-right" class="ms-1" />
              </span>
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
  deal: { type: Object, default: null }
})

const emit = defineEmits(['save', 'closed', 'open-deal'])

// State
const formData = ref({})
const submitting = ref(false)
const loading = ref(false)

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
  console.log('Calculating document types, missingFields:', props.missingFields)
  
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
    `${partyType}_party`
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
}

// Close modal
function closeModal() {
  formData.value = {}
  submitting.value = false
  emit('closed')
}

// Options for selects
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
  { value: 'AE', text: 'United Arab Emirates' },
  { value: 'SA', text: 'Saudi Arabia' },
  { value: 'EG', text: 'Egypt' },
  { value: 'JO', text: 'Jordan' },
  { value: 'LB', text: 'Lebanon' },
  { value: 'SY', text: 'Syria' },
  { value: 'PS', text: 'Palestine' },
  { value: 'IQ', text: 'Iraq' },
  { value: 'YE', text: 'Yemen' },
  { value: 'OM', text: 'Oman' },
  { value: 'QA', text: 'Qatar' },
  { value: 'KW', text: 'Kuwait' },
  { value: 'BH', text: 'Bahrain' },
  { value: 'GB', text: 'United Kingdom' },
  { value: 'US', text: 'United States' },
  { value: 'CA', text: 'Canada' },
  { value: 'AU', text: 'Australia' },
  { value: 'IN', text: 'India' },
  { value: 'PK', text: 'Pakistan' },
  { value: 'other', text: 'Other' }
]

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

const currencyOptions = [
  { value: 'AED', text: 'AED - UAE Dirham' },
  { value: 'USD', text: 'USD - US Dollar' },
  { value: 'EUR', text: 'EUR - Euro' },
  { value: 'GBP', text: 'GBP - British Pound' },
  { value: 'SAR', text: 'SAR - Saudi Riyal' },
  { value: 'QAR', text: 'QAR - Qatari Riyal' },
  { value: 'KWD', text: 'KWD - Kuwaiti Dinar' },
  { value: 'BHD', text: 'BHD - Bahraini Dinar' },
  { value: 'OMR', text: 'OMR - Omani Rial' },
  { value: 'EGP', text: 'EGP - Egyptian Pound' }
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
  border-radius: 12px;
  width: 900px;
  max-width: 95vw;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.modal-header-deal {
  border-bottom: 1px solid #F4F4F4;
}

.modal-title {
  font-weight: 600;
  font-size: 16px;
  color: #01062C;
  font-family: 'Montserrat', sans-serif;
}

.deals-type-tabs-inline {
  flex-wrap: wrap;
}

.deals-type-tab-inline {
  padding: 6px 14px;
  border-radius: 100px;
  border: none;
  font-size: 12px;
  font-weight: 500;
  background: #0F172A;
  color: #fff;
  font-family: 'Montserrat', sans-serif;
}

.close-btn {
  width: 32px;
  height: 32px;
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
  border-bottom: 1px solid #F4F4F4;
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
  gap: 8px;
  padding: 6px 12px;
  border-radius: 30px;
  border: 1px solid #3B82F6;
  background: #EFF6FF;
}

.deal-stage-pill .stage-circle {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
}

.deal-stage-pill .stage-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #3B82F6;
}

.deal-stage-pill .stage-text {
  font-size: 12px;
  color: #01062C;
  font-weight: 500;
}

.form-scroll-area {
  max-height: 60vh;
  overflow-y: auto;
  padding: 0;
}

.form-section {
  margin-top: 10px;
}

.section-title { 
  font-size: 14px !important; 
  font-weight: 600; 
  color: #01062C; 
  font-family: 'Montserrat'; 
}

.form-card { 
  background: #fff; 
  border: 1px solid #F3F3F3; 
  box-shadow: 1px 1px 5px rgba(0,0,0,0.03); 
}

.radius-12 { 
  border-radius: 12px; 
}

.form-label-custom { 
  font-size: 13px; 
  font-weight: 500; 
  color: #000; 
  margin-bottom: 6px; 
  display: block; 
  font-family: 'Montserrat'; 
}

.custom-input { 
  height: 42px !important; 
  border-radius: 10px !important; 
  border: 1px solid #E2E8F0 !important; 
  font-size: 13px !important; 
  font-family: 'Montserrat'; 
  width: 100%;
  padding: 0 12px;
}

.input-group-custom { 
  display: flex; 
  border: 1px solid #E2E8F0; 
  border-radius: 10px; 
  overflow: hidden; 
}

.input-group-custom .custom-input { 
  border: none !important; 
  flex: 1; 
  border-radius: 10px 0 0 10px !important; 
}

:deep(.custom-v-select-inline) { 
  min-width: 120px; 
}

:deep(.custom-v-select-inline .vs__dropdown-toggle) { 
  height: 42px; 
  border: none; 
  border-left: 1px solid #E2E8F0; 
  border-radius: 0 10px 10px 0; 
}

:deep(.vs__dropdown-toggle) {
  border: 1px solid #E2E8F0;
  border-radius: 10px;
  min-height: 42px;
}

.modal-footer-custom {
  border-top: 1px solid #F4F4F4;
  background: white;
}

.btn-clear {
  background: #F4F4F4;
  border: none;
  padding: 10px 25px;
  border-radius: 100px;
  font-size: 14px;
  color: #01062C;
  cursor: pointer;
  font-family: 'Montserrat', sans-serif;
}

.btn-next-step {
  background: #01062C;
  border: none;
  padding: 10px 20px;
  border-radius: 100px;
  font-size: 14px;
  color: #fff;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  cursor: pointer;
  transition: background 0.2s;
  font-family: 'Montserrat', sans-serif;
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
</style>