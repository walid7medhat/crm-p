<template>
  <div class="deal-form-container">
    <div v-if="missingFieldLabels.length" class="alert alert-warning py-2 mb-3">
      <div class="small fw-semibold mb-1">Missing fields for selected stage</div>
      <div class="small">{{ missingFieldLabels.join(' • ') }}</div>
    </div>

    <!-- Source and Deal Name (Common for all) -->
    <section class="form-section">
      <h6 class="section-title mb-3">Source and Deal Name</h6>
      <div class="form-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label-custom">Source <span class="text-danger">*</span></label>
            <v-select 
              v-model="form.source" 
              :options="sources" 
              :reduce="item => item.name" 
              label="name" 
              placeholder="Select Source" 
              class="custom-v-select" 
              :class="{ 'is-invalid': showErrors && !form.source }"
            />
              <div v-if="showErrors && fieldErrors.source" class="invalid-feedback d-block">
                {{ fieldErrors.source }}
              </div>
          </div>
          <div class="col-md-6">
            <label class="form-label-custom">Deal Name <span class="text-danger">*</span></label>
            <b-form-input 
              v-model="form.deal_name" 
              placeholder="Enter Deal Name" 
              class="custom-input"
              :class="{ 'is-invalid': showErrors && !form.deal_name }"
            />
             <div v-if="showErrors && fieldErrors.deal_name" class="invalid-feedback d-block">
                {{ fieldErrors.deal_name }}
              </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Buyer Section (for Primary & Secondary) -->
    <section v-if="dealType === 'primary' || dealType === 'secondary'" class="form-section">
      <h6 class="section-title mb-3">Buyer Details</h6>
      <div class="form-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label-custom">First Name <span class="text-danger">*</span></label>
            <b-form-input 
              v-model="form.buyer_first_name" 
              placeholder="Enter First Name" 
              class="custom-input"
              :class="{ 'is-invalid': showErrors && !form.buyer_first_name }"
            />
              <div v-if="showErrors && fieldErrors.buyer_first_name" class="invalid-feedback d-block">
                {{ fieldErrors.buyer_first_name }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Last Name <span class="text-danger">*</span></label>
            <b-form-input 
              v-model="form.buyer_last_name" 
              placeholder="Enter Last Name" 
              class="custom-input"
              :class="{ 'is-invalid': showErrors && !form.buyer_last_name }"
            />
              <div v-if="showErrors && fieldErrors.buyer_last_name" class="invalid-feedback d-block">
                {{ fieldErrors.buyer_last_name }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Date Of Birth <span class="text-danger">*</span></label>
            <b-form-input 
              v-model="form.buyer_dob" 
              type="date" 
              class="custom-input"
              :class="{ 'is-invalid': showErrors && !form.buyer_dob }"
            />
             <div v-if="showErrors && fieldErrors.buyer_dob" class="invalid-feedback d-block">
                {{ fieldErrors.buyer_dob }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Phone <span class="text-danger">*</span></label>
            <b-form-input 
              v-model="form.buyer_phone" 
              placeholder="Enter Phone" 
              class="custom-input"
              :class="{ 'is-invalid': showErrors && !form.buyer_phone }"
            />
             <div v-if="showErrors && fieldErrors.buyer_phone" class="invalid-feedback d-block">
                {{ fieldErrors.buyer_phone }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Email <span class="text-danger">*</span></label>
            <b-form-input 
              v-model="form.buyer_email" 
              type="email" 
              placeholder="Enter Email" 
              class="custom-input"
              :class="{ 'is-invalid': showErrors && !form.buyer_email }"
            />
             <div v-if="showErrors && fieldErrors.buyer_email" class="invalid-feedback d-block">
                {{ fieldErrors.buyer_email }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Nationality <span class="text-danger">*</span></label>
            <v-select 
              v-model="form.buyer_nationality" 
              :options="nationalityOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Nationality" 
              class="custom-v-select"
              :class="{ 'is-invalid': showErrors && !form.buyer_nationality }"
            />
             <div v-if="showErrors && fieldErrors.buyer_nationality" class="invalid-feedback d-block">
                {{ fieldErrors.buyer_nationality }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Residency Status <span class="text-danger">*</span></label>
            <v-select 
              v-model="form.buyer_residency_status" 
              :options="residencyOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Status" 
              class="custom-v-select"
              :class="{ 'is-invalid': showErrors && !form.buyer_residency_status }"
            />
              <div v-if="showErrors && fieldErrors.buyer_residency_status" class="invalid-feedback d-block">
                {{ fieldErrors.buyer_residency_status }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">City Of Residence <span class="text-danger">*</span></label>
            <b-form-input 
              v-model="form.buyer_city" 
              placeholder="Enter City" 
              class="custom-input"
              :class="{ 'is-invalid': showErrors && !form.buyer_city }"
            />
             <div v-if="showErrors && fieldErrors.buyer_city" class="invalid-feedback d-block">
                {{ fieldErrors.buyer_city }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Country Of Residence</label>
            <v-select 
              v-model="form.buyer_country" 
              :options="countryOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Country" 
              class="custom-v-select" 
            />
             <div v-if="showErrors && fieldErrors.buyer_country" class="invalid-feedback d-block">
                {{ fieldErrors.buyer_country }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Language <span class="text-danger">*</span></label>
            <v-select 
              v-model="form.buyer_language" 
              :options="languageOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Language" 
              class="custom-v-select"
              :class="{ 'is-invalid': showErrors && !form.buyer_language }"
            />
            <div v-if="showErrors && fieldErrors.buyer_language" class="invalid-feedback d-block">
                {{ fieldErrors.buyer_language }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Amount</label>
            <div class="input-group-custom">
              <b-form-input v-model="form.amount" type="number" placeholder="Enter Amount" class="custom-input" />
              <v-select 
                v-model="form.currency" 
                :options="currencyOptions" 
                :reduce="o => o.value" 
                label="text" 
                :clearable="false" 
                class="custom-v-select-inline" 
              />
             <div v-if="showErrors && fieldErrors.amount" class="invalid-feedback d-block">
                {{ fieldErrors.amount }}
              </div>
            </div>
          </div>
        </div>

        <!-- Buyer Documents -->
        <div class="mt-3">
          <label class="form-label-custom">Buyer Documents</label>
          <DocumentUpload
            v-if="dealType === 'primary'"
            v-model="form.buyer_documents"
            category="buyer"
            :document-types="primaryBuyerDocTypes"
            :show-errors="showErrors"
            ref="buyerDocUploadRef"
          />
          <DocumentUpload
            v-else-if="dealType === 'secondary'"
            v-model="form.buyer_documents"
            category="buyer"
            :document-types="secondaryBuyerDocTypes"
            :show-errors="showErrors"
            ref="buyerDocUploadRef"
          />
        </div>
      </div>
    </section>

    <!-- Seller Section (for Secondary only) -->
    <section v-if="dealType === 'secondary'" class="form-section">
      <h6 class="section-title mb-3">Seller Details</h6>
      <div class="form-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label-custom">First Name <span class="text-danger">*</span></label>
            <b-form-input 
              v-model="form.seller_first_name" 
              placeholder="Enter First Name" 
              class="custom-input"
              :class="{ 'is-invalid': showErrors && !form.seller_first_name }"
            />
             <div v-if="showErrors && fieldErrors.seller_first_name" class="invalid-feedback d-block">
                {{ fieldErrors.seller_first_name }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Last Name <span class="text-danger">*</span></label>
            <b-form-input 
              v-model="form.seller_last_name" 
              placeholder="Enter Last Name" 
              class="custom-input"
              :class="{ 'is-invalid': showErrors && !form.seller_last_name }"
            />
            <div v-if="showErrors && fieldErrors.seller_last_name" class="invalid-feedback d-block">
                {{ fieldErrors.seller_last_name }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Date Of Birth <span class="text-danger">*</span></label>
            <b-form-input 
              v-model="form.seller_dob" 
              type="date" 
              class="custom-input"
              :class="{ 'is-invalid': showErrors && !form.seller_dob }"
            />
             <div v-if="showErrors && fieldErrors.seller_dob" class="invalid-feedback d-block">
                {{ fieldErrors.seller_dob }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Phone <span class="text-danger">*</span></label>
            <b-form-input 
              v-model="form.seller_phone" 
              placeholder="Enter Phone" 
              class="custom-input"
              :class="{ 'is-invalid': showErrors && !form.seller_phone }"
            />
             <div v-if="showErrors && fieldErrors.seller_phone" class="invalid-feedback d-block">
                {{ fieldErrors.seller_phone }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Email <span class="text-danger">*</span></label>
            <b-form-input 
              v-model="form.seller_email" 
              type="email" 
              placeholder="Enter Email" 
              class="custom-input"
              :class="{ 'is-invalid': showErrors && !form.seller_email }"
            />
            <div v-if="showErrors && fieldErrors.seller_email" class="invalid-feedback d-block">
                {{ fieldErrors.seller_email }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Nationality <span class="text-danger">*</span></label>
            <v-select 
              v-model="form.seller_nationality" 
              :options="nationalityOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Nationality" 
              class="custom-v-select"
              :class="{ 'is-invalid': showErrors && !form.seller_nationality }"
            />
            <div v-if="showErrors && fieldErrors.seller_nationality" class="invalid-feedback d-block">
                {{ fieldErrors.seller_nationality }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Residency Status <span class="text-danger">*</span></label>
            <v-select 
              v-model="form.seller_residency_status" 
              :options="residencyOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Status" 
              class="custom-v-select"
              :class="{ 'is-invalid': showErrors && !form.seller_residency_status }"
            />
             <div v-if="showErrors && fieldErrors.seller_residency_status" class="invalid-feedback d-block">
                {{ fieldErrors.seller_residency_status }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">City Of Residence <span class="text-danger">*</span></label>
            <b-form-input 
              v-model="form.seller_city" 
              placeholder="Enter City" 
              class="custom-input"
              :class="{ 'is-invalid': showErrors && !form.seller_city }"
            />
              <div v-if="showErrors && fieldErrors.seller_city" class="invalid-feedback d-block">
                {{ fieldErrors.seller_city }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Country Of Residence</label>
            <v-select 
              v-model="form.seller_country" 
              :options="countryOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Country" 
              class="custom-v-select" 
            />
             <div v-if="showErrors && fieldErrors.seller_country" class="invalid-feedback d-block">
                {{ fieldErrors.seller_country }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Language <span class="text-danger">*</span></label>
            <v-select 
              v-model="form.seller_language" 
              :options="languageOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Language" 
              class="custom-v-select"
              :class="{ 'is-invalid': showErrors && !form.seller_language }"
            />
              <div v-if="showErrors && fieldErrors.seller_language" class="invalid-feedback d-block">
                {{ fieldErrors.seller_language }}
              </div>
          </div>
        </div>

        <!-- Seller Documents -->
        <div class="mt-3">
          <label class="form-label-custom">Seller Documents</label>
          <DocumentUpload 
            v-model="form.seller_documents"
            category="seller"
            :document-types="sellerDocTypes"
            :show-errors="showErrors"
            ref="sellerDocUploadRef"
          />
        </div>
      </div>
    </section>

    <!-- Tenant Section (for Rental) -->
    <section v-if="dealType === 'rental'" class="form-section">
      <h6 class="section-title mb-3">Tenant Details</h6>
      <div class="form-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label-custom">First Name <span class="text-danger">*</span></label>
            <b-form-input 
              v-model="form.tenant_first_name" 
              placeholder="Enter First Name" 
              class="custom-input"
              :class="{ 'is-invalid': showErrors && !form.tenant_first_name }"
            />
            <div v-if="showErrors && fieldErrors.tenant_first_name" class="invalid-feedback d-block">
                {{ fieldErrors.tenant_first_name }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Last Name <span class="text-danger">*</span></label>
            <b-form-input 
              v-model="form.tenant_last_name" 
              placeholder="Enter Last Name" 
              class="custom-input"
              :class="{ 'is-invalid': showErrors && !form.tenant_last_name }"
            />
             <div v-if="showErrors && fieldErrors.tenant_last_name" class="invalid-feedback d-block">
                {{ fieldErrors.tenant_last_name }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Date Of Birth</label>
            <b-form-input v-model="form.tenant_dob" type="date" class="custom-input" />
            <div v-if="showErrors && fieldErrors.tenant_dob" class="invalid-feedback d-block">
                {{ fieldErrors.tenant_dob }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Phone <span class="text-danger">*</span></label>
            <b-form-input 
              v-model="form.tenant_phone" 
              placeholder="Enter Phone" 
              class="custom-input"
              :class="{ 'is-invalid': showErrors && !form.tenant_phone }"
            />
            <div v-if="showErrors && fieldErrors.tenant_phone" class="invalid-feedback d-block">
                {{ fieldErrors.tenant_phone }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Email <span class="text-danger">*</span></label>
            <b-form-input 
              v-model="form.tenant_email" 
              type="email" 
              placeholder="Enter Email" 
              class="custom-input"
              :class="{ 'is-invalid': showErrors && !form.tenant_email }"
            />
             <div v-if="showErrors && fieldErrors.tenant_email" class="invalid-feedback d-block">
                {{ fieldErrors.tenant_email }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Nationality <span class="text-danger">*</span></label>
            <v-select 
              v-model="form.tenant_nationality" 
              :options="nationalityOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Nationality" 
              class="custom-v-select"
              :class="{ 'is-invalid': showErrors && !form.tenant_nationality }"
            />
            <div v-if="showErrors && fieldErrors.tenant_nationality" class="invalid-feedback d-block">
                {{ fieldErrors.tenant_nationality }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Residency Status <span class="text-danger">*</span></label>
            <v-select 
              v-model="form.tenant_residency_status" 
              :options="residencyOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Status" 
              class="custom-v-select"
              :class="{ 'is-invalid': showErrors && !form.tenant_residency_status }"
            />
             <div v-if="showErrors && fieldErrors.tenant_residency_status" class="invalid-feedback d-block">
                {{ fieldErrors.tenant_residency_status }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">City Of Residence <span class="text-danger">*</span></label>
            <b-form-input 
              v-model="form.tenant_city" 
              placeholder="Enter City" 
              class="custom-input"
              :class="{ 'is-invalid': showErrors && !form.tenant_city }"
            />
             <div v-if="showErrors && fieldErrors.tenant_city" class="invalid-feedback d-block">
                {{ fieldErrors.tenant_city }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Country Of Residence</label>
            <v-select 
              v-model="form.tenant_country" 
              :options="countryOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Country" 
              class="custom-v-select" 
            />
             <div v-if="showErrors && fieldErrors.tenant_country" class="invalid-feedback d-block">
                {{ fieldErrors.tenant_country }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Language <span class="text-danger">*</span></label>
            <v-select 
              v-model="form.tenant_language" 
              :options="languageOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Language" 
              class="custom-v-select"
              :class="{ 'is-invalid': showErrors && !form.tenant_language }"
            />
            <div v-if="showErrors && fieldErrors.tenant_language" class="invalid-feedback d-block">
                {{ fieldErrors.tenant_language }}
              </div>
          </div>
        </div>

        <!-- Tenant Documents -->
        <div class="mt-3">
          <label class="form-label-custom">Tenant Documents</label>
          <DocumentUpload 
            v-model="form.tenant_documents"
            category="tenant"
            :document-types="tenantDocTypes"
            :show-errors="showErrors"
            ref="tenantDocUploadRef"
          />
        </div>
      </div>
    </section>

    <!-- Landlord Section (for Rental) -->
    <section v-if="dealType === 'rental'" class="form-section">
      <h6 class="section-title mb-3">Landlord Details</h6>
      <div class="form-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label-custom">First Name <span class="text-danger">*</span></label>
            <b-form-input 
              v-model="form.landlord_first_name" 
              placeholder="Enter First Name" 
              class="custom-input"
              :class="{ 'is-invalid': showErrors && !form.landlord_first_name }"
            />
              <div v-if="showErrors && fieldErrors.landlord_first_name" class="invalid-feedback d-block">
                {{ fieldErrors.landlord_first_name }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Last Name <span class="text-danger">*</span></label>
            <b-form-input 
              v-model="form.landlord_last_name" 
              placeholder="Enter Last Name" 
              class="custom-input"
              :class="{ 'is-invalid': showErrors && !form.landlord_last_name }"
            />
             <div v-if="showErrors && fieldErrors.landlord_last_name" class="invalid-feedback d-block">
                {{ fieldErrors.landlord_last_name }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Date Of Birth <span class="text-danger">*</span></label>
            <b-form-input 
              v-model="form.landlord_dob" 
              type="date" 
              class="custom-input"
              :class="{ 'is-invalid': showErrors && !form.landlord_dob }"
            />
              <div v-if="showErrors && fieldErrors.landlord_dob" class="invalid-feedback d-block">
                {{ fieldErrors.landlord_dob }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Phone <span class="text-danger">*</span></label>
            <b-form-input 
              v-model="form.landlord_phone" 
              placeholder="Enter Phone" 
              class="custom-input"
              :class="{ 'is-invalid': showErrors && !form.landlord_phone }"
            />
             <div v-if="showErrors && fieldErrors.landlord_phone" class="invalid-feedback d-block">
                {{ fieldErrors.landlord_phone }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Email <span class="text-danger">*</span></label>
            <b-form-input 
              v-model="form.landlord_email" 
              type="email" 
              placeholder="Enter Email" 
              class="custom-input"
              :class="{ 'is-invalid': showErrors && !form.landlord_email }"
            />
             <div v-if="showErrors && fieldErrors.landlord_email" class="invalid-feedback d-block">
                {{ fieldErrors.landlord_email }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Nationality <span class="text-danger">*</span></label>
            <v-select 
              v-model="form.landlord_nationality" 
              :options="nationalityOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Nationality" 
              class="custom-v-select"
              :class="{ 'is-invalid': showErrors && !form.landlord_nationality }"
            />
             <div v-if="showErrors && fieldErrors.landlord_nationality" class="invalid-feedback d-block">
                {{ fieldErrors.landlord_nationality }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Residency Status <span class="text-danger">*</span></label>
            <v-select 
              v-model="form.landlord_residency_status" 
              :options="residencyOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Status" 
              class="custom-v-select"
              :class="{ 'is-invalid': showErrors && !form.landlord_residency_status }"
            />
             <div v-if="showErrors && fieldErrors.landlord_residency_status" class="invalid-feedback d-block">
                {{ fieldErrors.landlord_residency_status }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">City Of Residence <span class="text-danger">*</span></label>
            <b-form-input 
              v-model="form.landlord_city" 
              placeholder="Enter City" 
              class="custom-input"
              :class="{ 'is-invalid': showErrors && !form.landlord_city }"
            />
             <div v-if="showErrors && fieldErrors.landlord_city" class="invalid-feedback d-block">
                {{ fieldErrors.landlord_city }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Country Of Residence</label>
            <v-select 
              v-model="form.landlord_country" 
              :options="countryOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Country" 
              class="custom-v-select" 
            />
             <div v-if="showErrors && fieldErrors.landlord_country" class="invalid-feedback d-block">
                {{ fieldErrors.landlord_country }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Language <span class="text-danger">*</span></label>
            <v-select 
              v-model="form.landlord_language" 
              :options="languageOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Language" 
              class="custom-v-select"
              :class="{ 'is-invalid': showErrors && !form.landlord_language }"
            />
               <div v-if="showErrors && fieldErrors.landlord_language" class="invalid-feedback d-block">
                {{ fieldErrors.landlord_language }}
              </div>
          </div>
        </div>

        <!-- Landlord Documents -->
        <div class="mt-3">
          <label class="form-label-custom">Landlord Documents</label>
          <DocumentUpload 
            v-model="form.landlord_documents"
            category="landlord"
            :document-types="landlordDocTypes"
            :show-errors="showErrors"
            ref="landlordDocUploadRef"
          />
        </div>
      </div>
    </section>

    <!-- Property Details (Common for all) -->
    <section class="form-section">
      <h6 class="section-title mb-3">Property Details</h6>
      <div class="form-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label-custom">Unit No <span class="text-danger">*</span></label>
            <b-form-input 
              v-model="form.unit_no" 
              placeholder="Enter Unit No" 
              class="custom-input"
              :class="{ 'is-invalid': showErrors && !form.unit_no }"
            />
            <div v-if="showErrors && fieldErrors.unit_no" class="invalid-feedback d-block">
                {{ fieldErrors.unit_no }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Property Type <span class="text-danger">*</span></label>
            <v-select 
              v-model="form.property_type_id" 
              :options="propertyTypes" 
              :reduce="item => item.id" 
              label="name" 
              placeholder="Select Property Type" 
              class="custom-v-select"
              :class="{ 'is-invalid': showErrors && !form.property_type_id }"
            />
             <div v-if="showErrors && fieldErrors.property_type_id" class="invalid-feedback d-block">
                {{ fieldErrors.property_type_id }}
              </div>
          </div>
         <div class="col-md-4">
              <label class="form-label-custom">Subcommunity <span class="text-danger">*</span></label>
              <v-select 
                v-model="form.subcommunity_id" 
                :options="areas" 
                :reduce="item => item.id" 
                label="name" 
                placeholder="Select Subcommunity..." 
                class="custom-v-select"
                :filterable="true" 
                :searchable="true" 
                :clearable="true"
                @open="() => $emit('search-areas', '', form.area_id)"  
                @search="(search) => $emit('search-areas', search, form.area_id)" 
                :class="{ 'is-invalid': showErrors && !form.subcommunity_id }"
              />
              <div v-if="showErrors && fieldErrors.subcommunity_id" class="invalid-feedback d-block">
                {{ fieldErrors.subcommunity_id }}
              </div>
         </div>
          <div class="col-md-4">
                  <label class="form-label-custom">Area <span class="text-danger">*</span></label>
                  <v-select 
                    v-model="form.area_id" 
                    :options="areas" 
                    :reduce="item => item.id" 
                    label="name" 
                    placeholder="Select Area..." 
                    class="custom-v-select"
                    :filterable="true"
                    :searchable="true"
                    :clearable="true"
                    @open="() => $emit('search-areas', '')"  
                    @search="(search) => $emit('search-areas', search)" 
                    :class="{ 'is-invalid': showErrors && !form.area_id }"
                  />
                  <div v-if="showErrors && fieldErrors.area_id" class="invalid-feedback d-block">
                    {{ fieldErrors.area_id }}
                  </div>
            </div>
          <div class="col-md-4">
            <label class="form-label-custom">Bedrooms</label>
            <v-select 
              v-model="form.bedrooms" 
              :options="bedroomOptions" 
              :reduce="o => o.value" 
              label="text" 
              placeholder="Select Bedroom" 
              class="custom-v-select" 
            />
             <div v-if="showErrors && fieldErrors.bedrooms" class="invalid-feedback d-block">
                {{ fieldErrors.bedrooms }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Project Name</label>
            <v-select 
              v-model="form.project_id" 
              :options="projects" 
              :reduce="item => item.id" 
              label="name" 
              placeholder="Search Project..." 
              class="custom-v-select"
              :filterable="false"
              @search="searchProjects"
                @open="() => searchProjects('')"              />
            <div v-if="showErrors && fieldErrors.project_id" class="invalid-feedback d-block">
                {{ fieldErrors.project_id }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Developer</label>
            <v-select 
              v-model="form.developer_id" 
              :options="developers" 
              :reduce="item => item.id" 
              label="name" 
              placeholder="Select Developer" 
              class="custom-v-select" 
            />
            <div v-if="showErrors && fieldErrors.developer_id" class="invalid-feedback d-block">
                {{ fieldErrors.developer_id }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Unit Size</label>
            <b-form-input v-model="form.unit_size" placeholder="Enter Unit Size (sq. ft)" class="custom-input" />
            <div v-if="showErrors && fieldErrors.unit_size" class="invalid-feedback d-block">
                {{ fieldErrors.unit_size }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Property Link</label>
            <b-form-input v-model="form.property_link" placeholder="Enter Property Link" class="custom-input" />
            <div v-if="showErrors && fieldErrors.property_link" class="invalid-feedback d-block">
                {{ fieldErrors.property_link }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Property Reference</label>
            <b-form-input v-model="form.property_reference" placeholder="Enter Reference" class="custom-input" />
             <div v-if="showErrors && fieldErrors.property_reference" class="invalid-feedback d-block">
                {{ fieldErrors.property_reference }}
              </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Deal Financials (Common for all) -->
    <section class="form-section">
      <h6 class="section-title mb-3">Deal Financials</h6>
      <div class="form-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label-custom">Deal Total Amount</label>
            <div class="input-group-custom">
              <b-form-input v-model="form.deal_total_amount" type="number" placeholder="Enter Amount" class="custom-input" />
              <v-select 
                v-model="form.currency" 
                :options="currencyOptions" 
                :reduce="o => o.value" 
                label="text" 
                :clearable="false" 
                class="custom-v-select-inline" 
              />
               <div v-if="showErrors && fieldErrors.deal_total_amount" class="invalid-feedback d-block">
                {{ fieldErrors.deal_total_amount }}
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Deal Commission %</label>
            <b-form-input v-model="form.deal_commission" type="number" placeholder="Enter Commission %" class="custom-input" />
             <div v-if="showErrors && fieldErrors.deal_commission" class="invalid-feedback d-block">
                {{ fieldErrors.deal_commission }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Agent Share %</label>
            <b-form-input v-model="form.agent_share" type="number" placeholder="Enter Agent Share %" class="custom-input" />
            <div v-if="showErrors && fieldErrors.agent_share" class="invalid-feedback d-block">
                {{ fieldErrors.agent_share }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Company Share %</label>
            <b-form-input v-model="form.company_share" type="number" placeholder="Enter Company Share %" class="custom-input" />
             <div v-if="showErrors && fieldErrors.company_share" class="invalid-feedback d-block">
                {{ fieldErrors.company_share }}
              </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Responsible Person -->
    <div class="col-12">
      <ResponsiblePersonSelector 
        v-model="form.responsible_person_id" 
        :users="users" 
        :responsible-person="responsiblePerson"
        :class="{ 'is-invalid': showErrors && !form.responsible_person_id }"
      />
        <div v-if="showErrors && fieldErrors.responsible_person_id" class="invalid-feedback d-block">
                {{ fieldErrors.responsible_person_id }}
              </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { BFormInput } from 'bootstrap-vue-3'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import DocumentUpload from './DocumentUpload.vue'
import ResponsiblePersonSelector from '../shared/ResponsiblePersonSelector.vue' 
import api from '@/plugins/axios'

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  dealType: { type: String, default: 'primary' },
  users: { type: Array, default: () => [] },
  sources: { type: Array, default: () => [] },
  propertyTypes: { type: Array, default: () => [] },
  developers: { type: Array, default: () => [] },
  areas: { type: Array, default: () => [] },
  subCommunities: { type: Array, default: () => [] },
  usersLoading: { type: Boolean, default: false },
  showErrors: { type: Boolean, default: false },
    fieldErrors: { type: Object, default: () => ({}) },
      selectedStageId: { type: [Number, String], default: null },
  missingFields: { type: Array, default: () => [] }
})

const emit = defineEmits(['update:modelValue', 'search-areas', 'search-subcommunities'])

const form = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v)
})

const responsiblePerson = computed(() => {
  const id = form.value?.responsible_person_id
  if (!id || !props.users.length) return null
  return props.users.find(u => u.id === id) || null
})

const projects = ref([])
const subCommunities = ref([])

const missingFieldLabels = computed(() => {
  if (!Array.isArray(props.missingFields) || !props.missingFields.length) return []
  return props.missingFields.map((k) => String(k).replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase()))
})

// Document type options based on requirements
const primaryBuyerDocTypes = [
  { id: 'national_id', name: 'National ID', required: true },
  { id: 'passport', name: 'Passport', required: true },
  { id: 'kyc', name: 'KYC', required: true },
  { id: 'spa', name: 'Buyer SPA', required: false },
  { id: 'payment_proof', name: 'Buyer Payment Proof', required: false }
]

const secondaryBuyerDocTypes = [
  { id: 'noc', name: 'NOC Letter', required: false },
  { id: 'national_id', name: 'Buyer National ID', required: true },
  { id: 'passport', name: 'Buyer Passport', required: true },
  { id: 'kyc', name: 'Buyer KYC', required: true },
  { id: 'payment_proof', name: 'Buyer Payment Proof', required: false },
  { id: 'title_deed', name: 'New Title Deed / New SPA', required: false }
]

const sellerDocTypes = [
  { id: 'national_id', name: 'Seller National ID', required: true },
  { id: 'passport', name: 'Seller Passport', required: true },
  { id: 'title_deed', name: 'Unit SPA / Title Deed', required: false }
]

const tenantDocTypes = [
  { id: 'national_id', name: 'Tenant National ID', required: false },
  { id: 'passport', name: 'Tenant Passport', required: true },
  { id: 'kyc', name: 'Tenant KYC', required: true },
  { id: 'visa', name: 'Tenant Visa', required: true },
  { id: 'payment_proof', name: 'Tenant Proof of Payment', required: false },
  { id: 'ejari', name: 'Tawtheeq/Ejari Contract', required: false },
  { id: 'tenancy_contract', name: 'Tenancy Contract', required: true },
  { id: 'move_in_form', name: 'Move In Form', required: true }
]

const landlordDocTypes = [
  { id: 'title_deed', name: 'Title Deed', required: true },
  { id: 'passport', name: 'Landlord Passport', required: true },
  { id: 'national_id', name: 'Landlord National ID', required: true },
  { id: 'visa', name: 'Landlord Visa', required: true }
]

// Refs for document upload components
const buyerDocUploadRef = ref(null)
const sellerDocUploadRef = ref(null)
const tenantDocUploadRef = ref(null)
const landlordDocUploadRef = ref(null)

// Clear all documents function
function clearAllDocuments() {
  console.log('Clearing all documents...')
  if (buyerDocUploadRef.value) buyerDocUploadRef.value.clearAllFiles()
  if (sellerDocUploadRef.value) sellerDocUploadRef.value.clearAllFiles()
  if (tenantDocUploadRef.value) tenantDocUploadRef.value.clearAllFiles()
  if (landlordDocUploadRef.value) landlordDocUploadRef.value.clearAllFiles()
}

// Validation function
// Validation function
function validateForm() {
  const errors = []
  const fieldErrorsObj = {}
  
  // Check stage
  if (!props.selectedStageId) {
    errors.push('Please select a stage for the deal')
    fieldErrorsObj.stage_id = 'Stage is required'
  }
  
  // Required fields check
  if (!form.value.source) {
    errors.push('Source is required')
    fieldErrorsObj.source = 'Source is required'
  }
  if (!form.value.deal_name) {
    errors.push('Deal name is required')
    fieldErrorsObj.deal_name = 'Deal name is required'
  }
  if (!form.value.unit_no) {
    errors.push('Unit number is required')
    fieldErrorsObj.unit_no = 'Unit number is required'
  }
  if (!form.value.property_type_id) {
    errors.push('Property type is required')
    fieldErrorsObj.property_type_id = 'Property type is required'
  }
  if (!form.value.subcommunity_id) {
    errors.push('Subcommunity is required')
    fieldErrorsObj.subcommunity_id = 'Subcommunity is required'
  }
  if (!form.value.responsible_person_id) {
    errors.push('Responsible person is required')
    fieldErrorsObj.responsible_person_id = 'Responsible person is required'
  }
  
  // Validate based on deal type
  if (props.dealType === 'primary' || props.dealType === 'secondary') {
    if (!form.value.buyer_first_name) {
      errors.push('Buyer first name is required')
      fieldErrorsObj.buyer_first_name = 'First name is required'
    }
    if (!form.value.buyer_last_name) {
      errors.push('Buyer last name is required')
      fieldErrorsObj.buyer_last_name = 'Last name is required'
    }
    if (!form.value.buyer_phone) {
      errors.push('Buyer phone is required')
      fieldErrorsObj.buyer_phone = 'Phone is required'
    }
    if (!form.value.buyer_email) {
      errors.push('Buyer email is required')
      fieldErrorsObj.buyer_email = 'Email is required'
    }
    if (!form.value.buyer_nationality) {
      errors.push('Buyer nationality is required')
      fieldErrorsObj.buyer_nationality = 'Nationality is required'
    }
    if (!form.value.buyer_dob) {
      errors.push('Buyer date of birth is required')
      fieldErrorsObj.buyer_dob = 'Date of birth is required'
    }
    if (!form.value.buyer_residency_status) {
      errors.push('Buyer residency status is required')
      fieldErrorsObj.buyer_residency_status = 'Residency status is required'
    }
    if (!form.value.buyer_city) {
      errors.push('Buyer city is required')
      fieldErrorsObj.buyer_city = 'City is required'
    }
    if (!form.value.buyer_language) {
      errors.push('Buyer language is required')
      fieldErrorsObj.buyer_language = 'Language is required'
    }
  }
  
  if (props.dealType === 'secondary') {
    if (!form.value.seller_first_name) {
      errors.push('Seller first name is required')
      fieldErrorsObj.seller_first_name = 'First name is required'
    }
    if (!form.value.seller_last_name) {
      errors.push('Seller last name is required')
      fieldErrorsObj.seller_last_name = 'Last name is required'
    }
    if (!form.value.seller_phone) {
      errors.push('Seller phone is required')
      fieldErrorsObj.seller_phone = 'Phone is required'
    }
    if (!form.value.seller_email) {
      errors.push('Seller email is required')
      fieldErrorsObj.seller_email = 'Email is required'
    }
    if (!form.value.seller_nationality) {
      errors.push('Seller nationality is required')
      fieldErrorsObj.seller_nationality = 'Nationality is required'
    }
    if (!form.value.seller_dob) {
      errors.push('Seller date of birth is required')
      fieldErrorsObj.seller_dob = 'Date of birth is required'
    }
    if (!form.value.seller_residency_status) {
      errors.push('Seller residency status is required')
      fieldErrorsObj.seller_residency_status = 'Residency status is required'
    }
    if (!form.value.seller_city) {
      errors.push('Seller city is required')
      fieldErrorsObj.seller_city = 'City is required'
    }
    if (!form.value.seller_language) {
      errors.push('Seller language is required')
      fieldErrorsObj.seller_language = 'Language is required'
    }
  }
  
  if (props.dealType === 'rental') {
    // Tenant validation
    if (!form.value.tenant_first_name) {
      errors.push('Tenant first name is required')
      fieldErrorsObj.tenant_first_name = 'First name is required'
    }
    if (!form.value.tenant_last_name) {
      errors.push('Tenant last name is required')
      fieldErrorsObj.tenant_last_name = 'Last name is required'
    }
    if (!form.value.tenant_phone) {
      errors.push('Tenant phone is required')
      fieldErrorsObj.tenant_phone = 'Phone is required'
    }
    if (!form.value.tenant_email) {
      errors.push('Tenant email is required')
      fieldErrorsObj.tenant_email = 'Email is required'
    }
    if (!form.value.tenant_nationality) {
      errors.push('Tenant nationality is required')
      fieldErrorsObj.tenant_nationality = 'Nationality is required'
    }
    if (!form.value.tenant_residency_status) {
      errors.push('Tenant residency status is required')
      fieldErrorsObj.tenant_residency_status = 'Residency status is required'
    }
    if (!form.value.tenant_city) {
      errors.push('Tenant city is required')
      fieldErrorsObj.tenant_city = 'City is required'
    }
    if (!form.value.tenant_language) {
      errors.push('Tenant language is required')
      fieldErrorsObj.tenant_language = 'Language is required'
    }
    
    // Landlord validation
    if (!form.value.landlord_first_name) {
      errors.push('Landlord first name is required')
      fieldErrorsObj.landlord_first_name = 'First name is required'
    }
    if (!form.value.landlord_last_name) {
      errors.push('Landlord last name is required')
      fieldErrorsObj.landlord_last_name = 'Last name is required'
    }
    if (!form.value.landlord_phone) {
      errors.push('Landlord phone is required')
      fieldErrorsObj.landlord_phone = 'Phone is required'
    }
    if (!form.value.landlord_email) {
      errors.push('Landlord email is required')
      fieldErrorsObj.landlord_email = 'Email is required'
    }
    if (!form.value.landlord_nationality) {
      errors.push('Landlord nationality is required')
      fieldErrorsObj.landlord_nationality = 'Nationality is required'
    }
    if (!form.value.landlord_dob) {
      errors.push('Landlord date of birth is required')
      fieldErrorsObj.landlord_dob = 'Date of birth is required'
    }
    if (!form.value.landlord_residency_status) {
      errors.push('Landlord residency status is required')
      fieldErrorsObj.landlord_residency_status = 'Residency status is required'
    }
    if (!form.value.landlord_city) {
      errors.push('Landlord city is required')
      fieldErrorsObj.landlord_city = 'City is required'
    }
    if (!form.value.landlord_language) {
      errors.push('Landlord language is required')
      fieldErrorsObj.landlord_language = 'Language is required'
    }
  }
  
  return { errors, fieldErrorsObj }
}

// Search functions
function onSearchProjects(search) {
  console.log('Searching projects with term:', search)
  emit('search-projects', search)
}
async function searchProjects(search) {
  if (!search) return
  try {
    const response = await api.get('/listings/projects', { params: { search } })
    projects.value = response.data?.data ?? response.data ?? []
  } catch (e) {
    console.error('Error searching projects:', e)
  }
}
watch(() => props.fieldErrors, (newVal) => {
  console.log('fieldErrors in DealForm:', newVal)
}, { deep: true, immediate: true })

watch(() => props.showErrors, (newVal) => {
  console.log('showErrors in DealForm:', newVal)
}, { immediate: true })

function onSearchAreas(search) {
  emit('search-areas', search)
}

function onSearchSubCommunities(search) {
  emit('search-subcommunities', search)
}

// Expose functions to parent
defineExpose({
  clearAllDocuments,
  validateForm
})

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
/* Compact scale — aligned with Create Lead modal */
.section-title { font-size: 14px !important; font-weight: 600; color: #01062C; font-family: 'Montserrat', sans-serif; margin-bottom: 10px; letter-spacing: -0.01em; }
.form-card { background: #fff; border: 1px solid #eef2f7; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04); padding: 0.875rem 1rem !important; }
.radius-12 { border-radius: 10px; }
.form-label-custom { font-size: 12px !important; font-weight: 500; color: #475569; margin-bottom: 4px; display: block; font-family: 'Montserrat', sans-serif; }
.custom-input { height: 36px !important; min-height: 36px; border-radius: 8px !important; border: 1px solid #E2E8F0 !important; font-size: 13px !important; font-family: 'Montserrat', sans-serif; }
.custom-input.is-invalid { border-color: #dc3545 !important; }
.input-group-custom { display: flex; border: 1px solid #E2E8F0; border-radius: 10px; overflow: hidden; }
.input-group-custom .custom-input { border: none !important; flex: 1; border-radius: 10px 0 0 10px !important; }
:deep(.custom-v-select) { font-size: 13px; }
:deep(.custom-v-select .vs__dropdown-toggle) { height: 36px !important; min-height: 36px; border-radius: 8px; border: 1px solid #E2E8F0; font-size: 13px; padding: 2px 8px; }
:deep(.custom-v-select.is-invalid .vs__dropdown-toggle) { border-color: #dc3545 !important; }
:deep(.custom-v-select .vs__selected), :deep(.custom-v-select .vs__search) { font-size: 13px; }
:deep(.custom-v-select-inline) { min-width: 120px; }
:deep(.custom-v-select-inline .vs__dropdown-toggle) { height: 36px !important; min-height: 36px; border: none; border-left: 1px solid #E2E8F0; border-radius: 0 8px 8px 0; font-size: 13px; }
.doc-tabs { gap: 6px; }
.doc-tab { height: 30px; min-height: 30px; padding: 0 12px; border-radius: 100px; border: 1px solid #E2E8F0; background: #F1F5F9; font-size: 12px; color: #64748B; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.doc-tab.active { background: #2196F3; color: #fff; border-color: #2196F3; }
.upload-zone { border-style: dashed !important; border-color: #E2E8F0 !important; background: #F8FAFC; }
.upload-icon { font-size: 36px; color: #94A3B8; }
.upload-text { font-size: 14px; color: #475569; margin: 0; }
.tag-pill { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background: #F1F5F9; border-radius: 100px; font-size: 13px; }
.tag-remove { cursor: pointer; font-size: 16px; }
.btn-tag-search { background: transparent; border: none; color: #3B82F6; font-size: 14px; cursor: pointer; }
.add-custom-field-link { font-size: 14px; color: #3B82F6; text-decoration: underline; }
.form-section { margin-top: 14px; }
.form-section:first-of-type { margin-top: 0; }
</style>