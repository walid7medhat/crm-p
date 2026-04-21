<template>
  <div class="deal-form-container deal-figma-ui" :class="{ 'inline-mode': inlineMode }">
    <div v-if="missingFieldLabels.length" class="alert alert-warning py-2 mb-3">
      <div class="small fw-semibold mb-1">Missing fields for selected stage</div>
      <div class="small">{{ missingFieldLabels.join(' • ') }}</div>
    </div>

    <!-- Source and Deal Name (Common for all) -->
    <section v-if="isSectionVisible('deal_information')" class="form-section">
      <h6 class="section-title mb-3">About Deal</h6>
      <div class="form-card p-3 radius-12">
        <div v-if="!isDocumentEditMode('buyer_documents')" class="row g-3">
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
          <div class="col-md-6">
            <label class="form-label-custom">Source <span class="text-danger">*</span></label>
            <v-select 
              v-model="form.source" 
              :options="sources" 
              :reduce="item => item.name" 
              label="name" 
              placeholder="Not Selected" 
              class="custom-v-select" 
              :class="{ 'is-invalid': showErrors && !form.source }"
            >
                 <template #open-indicator="{ attributes }">
                  <span v-bind="attributes">
                      <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                  </span>
                </template>
          
            </v-select>
              <div v-if="showErrors && fieldErrors.source" class="invalid-feedback d-block">
                {{ fieldErrors.source }}
              </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Buyer Section (for Primary & Secondary) -->
    <section v-if="(dealType === 'primary' || dealType === 'secondary') && isSectionVisible('buyer_details')" class="form-section">
      <h6 class="section-title mb-3">Buyer Details</h6>
      <div class="form-card p-3 radius-12">
        <div v-if="!isDocumentEditMode('buyer_documents')" class="row g-3">
          <div class="col-md-4">
            <label class="form-label-custom">Buyer First Name <span class="text-danger">*</span></label>
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
            <label class="form-label-custom">Buyer Last Name <span class="text-danger">*</span></label>
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
            <label class="form-label-custom">Buyer Date Of Birth <span class="text-danger">*</span></label>
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
            <label class="form-label-custom">Buyer Phone Number <span class="text-danger">*</span></label>
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
            <label class="form-label-custom">Buyer Email <span class="text-danger">*</span></label>
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
            <label class="form-label-custom">Buyer Nationality <span class="text-danger">*</span></label>
            <v-select 
              v-model="form.buyer_nationality" 
              :options="nationalityOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Nationality" 
              class="custom-v-select"
              :class="{ 'is-invalid': showErrors && !form.buyer_nationality }"
            >
          
               <template #open-indicator="{ attributes }">
                  <span v-bind="attributes">
                      <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                  </span>
                </template>
            </v-select>
             <div v-if="showErrors && fieldErrors.buyer_nationality" class="invalid-feedback d-block">
                {{ fieldErrors.buyer_nationality }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Buyer Residency Status <span class="text-danger">*</span></label>
            <v-select 
              v-model="form.buyer_residency_status" 
              :options="residencyOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Status" 
              class="custom-v-select"
              :class="{ 'is-invalid': showErrors && !form.buyer_residency_status }"
            >
               <template #open-indicator="{ attributes }">
                  <span v-bind="attributes">
                      <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                  </span>
                </template>
          
             </v-select>
              <div v-if="showErrors && fieldErrors.buyer_residency_status" class="invalid-feedback d-block">
                {{ fieldErrors.buyer_residency_status }}
              </div>
          </div>
          
          <div class="col-md-4">
            <label class="form-label-custom">Buyer Country Of Residence <span class="text-danger">*</span></label>
            <v-select 
              v-model="form.buyer_country" 
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
             <div v-if="showErrors && fieldErrors.buyer_country" class="invalid-feedback d-block">
                {{ fieldErrors.buyer_country }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Buyer City Of Residence <span class="text-danger">*</span></label>
            <v-select 
              v-model="form.buyer_city" 
              :options="buyerCityOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select City" 
              class="custom-v-select"
              :class="{ 'is-invalid': showErrors && !form.buyer_city }"
            >
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes">
                  <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                </span>
              </template>
            </v-select>
            <div v-if="showErrors && fieldErrors.buyer_city" class="invalid-feedback d-block">
              {{ fieldErrors.buyer_city }}
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Buyer Language <span class="text-danger">*</span></label>
            <v-select 
              v-model="form.buyer_language" 
              :options="languageOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select Language" 
              class="custom-v-select"
              :class="{ 'is-invalid': showErrors && !form.buyer_language }"
            >
                <template #open-indicator="{ attributes }">
                  <span v-bind="attributes">
                      <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                  </span>
                </template>
          
            </v-select>
            <div v-if="showErrors && fieldErrors.buyer_language" class="invalid-feedback d-block">
                {{ fieldErrors.buyer_language }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Amount &amp; Currency</label>
            <div class="input-group-custom">
              <b-form-input v-model="form.amount" type="number" placeholder="Enter Amount" class="custom-input" />
              <v-select 
                v-model="form.currency" 
                :options="currencyOptions" 
                :reduce="o => o.value" 
                label="text" 
                :clearable="false" 
                class="custom-v-select-inline" 
              >
               <template #open-indicator="{ attributes }">
                  <span v-bind="attributes">
                      <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                  </span>
                </template>
             </v-select>
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
    <section v-if="dealType === 'secondary' && isSectionVisible('seller_details')" class="form-section">
      <h6 class="section-title mb-3">Seller Details</h6>
      <div class="form-card p-3 radius-12">
        <div v-if="!isDocumentEditMode('seller_documents')" class="row g-3">
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
            >
             <template #open-indicator="{ attributes }">
                  <span v-bind="attributes">
                      <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                  </span>
                </template>
            </v-select>
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
            >
          
             <template #open-indicator="{ attributes }">
                  <span v-bind="attributes">
                      <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                  </span>
                </template>  
           </v-select>
             <div v-if="showErrors && fieldErrors.seller_residency_status" class="invalid-feedback d-block">
                {{ fieldErrors.seller_residency_status }}
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
            >
             <template #open-indicator="{ attributes }">
                  <span v-bind="attributes">
                      <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                  </span>
                </template>
            </v-select>
             <div v-if="showErrors && fieldErrors.seller_country" class="invalid-feedback d-block">
                {{ fieldErrors.seller_country }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">City Of Residence <span class="text-danger">*</span></label>
            <v-select 
              v-model="form.seller_city" 
              :options="sellerCityOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select City" 
              class="custom-v-select"
              :class="{ 'is-invalid': showErrors && !form.seller_city }"
            >
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes">
                  <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                </span>
              </template>
            </v-select>
            <div v-if="showErrors && fieldErrors.seller_city" class="invalid-feedback d-block">
              {{ fieldErrors.seller_city }}
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
            > 
           <template #open-indicator="{ attributes }">
                  <span v-bind="attributes">
                      <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                  </span>
                </template>
             </v-select>
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
    <section v-if="dealType === 'rental' && isSectionVisible('tenant_details')" class="form-section">
      <h6 class="section-title mb-3">Tenant Details</h6>
      <div class="form-card p-3 radius-12">
        <div v-if="!isDocumentEditMode('tenant_documents')" class="row g-3">
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
            >
                <template #open-indicator="{ attributes }">
                  <span v-bind="attributes">
                      <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                  </span>
                </template>
            </v-select>
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
            > 
               <template #open-indicator="{ attributes }">
                  <span v-bind="attributes">
                      <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                  </span>
                </template>
          
            </v-select>
             <div v-if="showErrors && fieldErrors.tenant_residency_status" class="invalid-feedback d-block">
                {{ fieldErrors.tenant_residency_status }}
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
            >
              <template #open-indicator="{ attributes }">
                  <span v-bind="attributes">
                      <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                  </span>
                </template>
          
            </v-select>
             <div v-if="showErrors && fieldErrors.tenant_country" class="invalid-feedback d-block">
                {{ fieldErrors.tenant_country }}
              </div>
          </div>
          <div class="col-md-4">
          <label class="form-label-custom">City Of Residence <span class="text-danger">*</span></label>
          <v-select 
            v-model="form.tenant_city" 
            :options="tenantCityOptions" 
            :reduce="item => item.value" 
            label="text" 
            placeholder="Select City" 
            class="custom-v-select"
            :class="{ 'is-invalid': showErrors && !form.tenant_city }"
          >
            <template #open-indicator="{ attributes }">
              <span v-bind="attributes">
                <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
              </span>
            </template>
          </v-select>
          <div v-if="showErrors && fieldErrors.tenant_city" class="invalid-feedback d-block">
            {{ fieldErrors.tenant_city }}
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
            >
           <template #open-indicator="{ attributes }">
                  <span v-bind="attributes">
                      <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                  </span>
                </template>
            </v-select>
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
    <section v-if="dealType === 'rental' && isSectionVisible('landlord_details')" class="form-section">
      <h6 class="section-title mb-3">Landlord Details</h6>
      <div class="form-card p-3 radius-12">
        <div v-if="!isDocumentEditMode('landlord_documents')" class="row g-3">
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
             >
             <template #open-indicator="{ attributes }">
                  <span v-bind="attributes">
                      <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                  </span>
                </template>
              
              </v-select>
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
            >
             <template #open-indicator="{ attributes }">
                  <span v-bind="attributes">
                      <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                  </span>
                </template> 
          
           </v-select>
             <div v-if="showErrors && fieldErrors.landlord_residency_status" class="invalid-feedback d-block">
                {{ fieldErrors.landlord_residency_status }}
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
            >
              <template #open-indicator="{ attributes }">
                  <span v-bind="attributes">
                      <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                  </span>
                </template>
          
           </v-select>
             <div v-if="showErrors && fieldErrors.landlord_country" class="invalid-feedback d-block">
                {{ fieldErrors.landlord_country }}
              </div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">City Of Residence <span class="text-danger">*</span></label>
            <v-select 
              v-model="form.landlord_city" 
              :options="landlordCityOptions" 
              :reduce="item => item.value" 
              label="text" 
              placeholder="Select City" 
              class="custom-v-select"
              :class="{ 'is-invalid': showErrors && !form.landlord_city }"
            >
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes">
                  <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                </span>
              </template>
            </v-select>
            <div v-if="showErrors && fieldErrors.landlord_city" class="invalid-feedback d-block">
              {{ fieldErrors.landlord_city }}
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
            >
              <template #open-indicator="{ attributes }">
                  <span v-bind="attributes">
                      <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                  </span>
                </template>
          
            </v-select>
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

   <!-- Property Details Section -->
    <section v-if="isSectionVisible('property_details')" class="form-section">
      <h6 class="section-title mb-3">Property Details</h6>
      <div class="form-card p-3 radius-12">
        <div class="row g-3">
          
          <!-- Area (Location) - Required -->
          <div class="col-md-6">
            <label class="form-label-custom">Location <span class="text-danger">*</span></label>
            <v-select 
              v-model="form.area_id" 
              :options="areas" 
              :reduce="item => item.id" 
              label="name" 
              placeholder="Select Location..." 
              class="custom-v-select"
              :filterable="true"
              :searchable="true"
              :clearable="true"
              @update:modelValue="onAreaSelected"
              @open="() => $emit('search-areas', '')"  
              @search="(search) => $emit('search-areas', search)" 
              :class="{ 'is-invalid': showErrors && !form.area_id }"
            >
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes">
                  <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                </span>
              </template>
            </v-select>
            <div v-if="showErrors && fieldErrors.area_id" class="invalid-feedback d-block">
              {{ fieldErrors.area_id }}
            </div>
          </div>

          <!-- Select Listing (Unit) - يظهر بعد اختيار المنطقة -->
          <div class="col-md-6" v-if="availableListings.length > 0">
            <label class="form-label-custom">Select Unit <span class="text-danger">*</span></label>
            <v-select 
              v-model="selectedListing" 
              :options="availableListings" 
              :reduce="item => item" 
              label="display_name" 
              placeholder="Select a unit..." 
              class="custom-v-select"
              @update:modelValue="onListingSelected"
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
            <div class="small text-muted mt-1">
              <i class="ri-information-line"></i> Showing available units in this location
            </div>
          </div>

          <!-- Unit No - يتم تعبئته تلقائياً من الـ Listing -->
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

          <!-- Property Type - يتم تعبئته تلقائياً -->
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
              
            >
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes">
                  <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                </span>
              </template>
            </v-select>
            <div v-if="showErrors && fieldErrors.property_type_id" class="invalid-feedback d-block">
              {{ fieldErrors.property_type_id }}
            </div>
          </div>

          <!-- Bedrooms - يتم تعبئته تلقائياً -->
          <div class="col-md-4">
            <label class="form-label-custom">Bedrooms</label>
            <v-select 
              v-model="form.bedrooms" 
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
            <div v-if="showErrors && fieldErrors.bedrooms" class="invalid-feedback d-block">
              {{ fieldErrors.bedrooms }}
            </div>
          </div>

          <!-- Unit Size - يتم تعبئته تلقائياً -->
          <div class="col-md-4">
            <label class="form-label-custom">Unit Size (sq.ft)</label>
            <b-form-input 
              v-model="form.unit_size" 
              placeholder="Enter Unit Size" 
              class="custom-input"
              
            />
            <div v-if="showErrors && fieldErrors.unit_size" class="invalid-feedback d-block">
              {{ fieldErrors.unit_size }}
            </div>
          </div>

          <!-- Project Name - يتم تعبئته تلقائياً -->
          <div class="col-md-4">
            <label class="form-label-custom">Project Name</label>
            <v-select 
              v-model="form.project_id" 
              :options="projects" 
              :reduce="item => item.id" 
              label="name" 
              placeholder="Search Project..." 
              class="custom-v-select"
              :filterable="true"
                :searchable="true"
                @search="searchProjects"
            >
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes">
                  <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                </span>
              </template>
            </v-select>
            <div v-if="showErrors && fieldErrors.project_id" class="invalid-feedback d-block">
              {{ fieldErrors.project_id }}
            </div>
          </div>

          <!-- Developer - يتم تعبئته تلقائياً -->
          <div class="col-md-4">
            <label class="form-label-custom">Developer</label>
            <v-select 
              v-model="form.developer_id" 
              :options="developers" 
              :reduce="item => item.id" 
              label="name" 
              placeholder="Select Developer" 
              class="custom-v-select"
              
            >
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes">
                  <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                </span>
              </template>
            </v-select>
            <div v-if="showErrors && fieldErrors.developer_id" class="invalid-feedback d-block">
              {{ fieldErrors.developer_id }}
            </div>
          </div>

          <!-- Property Link - مخفي حالياً -->
          <!-- <div class="col-md-4">
            <label class="form-label-custom">Property Link</label>
            <b-form-input v-model="form.property_link" placeholder="Enter Property Link" class="custom-input" />
          </div> -->

          <!-- Property Reference - مخفي حالياً -->
          <!-- <div class="col-md-4">
            <label class="form-label-custom">Property Reference</label>
            <b-form-input v-model="form.property_reference" placeholder="Enter Reference" class="custom-input" />
          </div> -->

        </div>
      </div>
    </section>

    <!-- Deal Financials (Common for all) -->
    <section v-if="isSectionVisible('deal_financials')" class="form-section">
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
              >
                <template #open-indicator="{ attributes }">
                  <span v-bind="attributes">
                      <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                  </span>
                </template>
            
              </v-select>
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
          <!--<div class="col-md-4">-->
          <!--  <label class="form-label-custom">Agent Share %</label>-->
          <!--  <b-form-input v-model="form.agent_share" type="number" placeholder="Enter Agent Share %" class="custom-input" />-->
          <!--  <div v-if="showErrors && fieldErrors.agent_share" class="invalid-feedback d-block">-->
          <!--      {{ fieldErrors.agent_share }}-->
          <!--    </div>-->
          <!--</div>-->
          <!--<div class="col-md-4">-->
          <!--  <label class="form-label-custom">Company Share %</label>-->
          <!--  <b-form-input v-model="form.company_share" type="number" placeholder="Enter Company Share %" class="custom-input" />-->
          <!--   <div v-if="showErrors && fieldErrors.company_share" class="invalid-feedback d-block">-->
          <!--      {{ fieldErrors.company_share }}-->
          <!--    </div>-->
          <!--</div>-->
        </div>
      </div>
    </section>

    <!-- Responsible Person -->
    <div v-if="!inlineMode" class="col-12">
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
import { ref, watch, computed,onMounted } from 'vue'
import { BFormInput } from 'bootstrap-vue-3'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import DocumentUpload from './DocumentUpload.vue'
import ResponsiblePersonSelector from '../shared/ResponsiblePersonSelector.vue' 
import api from '@/plugins/axios'
import { getCurrentInstance } from 'vue'

const { proxy } = getCurrentInstance()
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
  ,
  activeEditSection: { type: String, default: null },
  inlineMode: { type: Boolean, default: false }
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

const sectionAliases = {
  deal_information: ['deal_information', 'source_deal_name', 'client_details'],
  buyer_details: ['buyer_details', 'buyer_documents'],
  seller_details: ['seller_details', 'seller_documents'],
  tenant_details: ['tenant_details', 'tenant_documents'],
  landlord_details: ['landlord_details', 'landlord_documents'],
  property_details: ['property_details', 'property_documents'],
  deal_financials: ['deal_financials'],
}

function isSectionVisible(sectionName) {
  const active = props.activeEditSection
  if (!active) return true
  return (sectionAliases[sectionName] || [sectionName]).includes(active)
}

function isDocumentEditMode(documentSectionKey) {
  return props.activeEditSection === documentSectionKey
}

// Document type options based on requirements
// دالة لتحديد المستندات المطلوبة حسب حالة الإقامة فقط
function getRequiredDocumentsByResidency(residencyStatus) {
  // Citizen - يطلب Passport + National ID
  if (residencyStatus === 'citizen') {
    return ['passport', 'national_id']
  }
  
  // مقيم (Resident) - يطلب Passport + Visa + National ID
  if (residencyStatus === 'resident') {
    return ['passport', 'visa', 'national_id']
  }
  
  // غير مقيم / سائح (Non-resident / Tourist) - يطلب Passport فقط
  if (residencyStatus === 'non_resident' || residencyStatus === 'tourist') {
    return ['passport']
  }
  
  // Investor - يطلب Passport + Investor Visa + National ID
  if (residencyStatus === 'investor') {
    return ['passport', 'visa', 'national_id']
  }
  
  // Student - يطلب Passport + Student Visa + National ID
  if (residencyStatus === 'student') {
    return ['passport', 'visa', 'national_id']
  }
  
  // الحالة الافتراضية - يطلب Passport فقط
  return ['passport']
}

// قوائم المستندات الديناميكية حسب حالة الإقامة
const primaryBuyerDocTypes = computed(() => {
  const residencyStatus = form.value?.buyer_residency_status
  const requiredDocs = getRequiredDocumentsByResidency(residencyStatus)
  
  const allDocs = {
    passport: { id: 'passport', name: 'Passport', required: true },
    national_id: { id: 'national_id', name: 'National ID', required: false },
    kyc: { id: 'kyc', name: 'KYC', required: false },
    visa: { id: 'visa', name: 'Visa', required: false },
    spa: { id: 'spa', name: 'Buyer SPA', required: false },
    payment_proof: { id: 'payment_proof', name: 'Buyer Payment Proof', required: false }
  }
  
  return requiredDocs.map(docType => allDocs[docType]).filter(doc => doc)
})

const secondaryBuyerDocTypes = computed(() => {
  const residencyStatus = form.value?.buyer_residency_status
  const requiredDocs = getRequiredDocumentsByResidency(residencyStatus)
  
  const allDocs = {
    passport: { id: 'passport', name: 'Buyer Passport', required: true },
    national_id: { id: 'national_id', name: 'Buyer National ID', required: true },
    kyc: { id: 'kyc', name: 'Buyer KYC', required: false },
    visa: { id: 'visa', name: 'Buyer Visa', required: false },
    noc: { id: 'noc', name: 'NOC Letter', required: false },
    payment_proof: { id: 'payment_proof', name: 'Buyer Payment Proof', required: false },
    title_deed: { id: 'title_deed', name: 'New Title Deed / New SPA', required: false }
  }
  
  return requiredDocs.map(docType => allDocs[docType]).filter(doc => doc)
})

const sellerDocTypes = computed(() => {
  const residencyStatus = form.value?.seller_residency_status
  const requiredDocs = getRequiredDocumentsByResidency(residencyStatus)
  
  const allDocs = {
    passport: { id: 'passport', name: 'Seller Passport', required: true },
    national_id: { id: 'national_id', name: 'Seller National ID', required: true },
    visa: { id: 'visa', name: 'Seller Visa', required: true },
    title_deed: { id: 'title_deed', name: 'Unit SPA / Title Deed', required: false }
  }
  
  return requiredDocs.map(docType => allDocs[docType]).filter(doc => doc)
})

const tenantDocTypes = computed(() => {
  const residencyStatus = form.value?.tenant_residency_status
  const requiredDocs = getRequiredDocumentsByResidency(residencyStatus)
  
  const allDocs = {
    passport: { id: 'passport', name: 'Tenant Passport', required: true },
    national_id: { id: 'national_id', name: 'Tenant National ID', required: false },
    kyc: { id: 'kyc', name: 'Tenant KYC', required: false },
    visa: { id: 'visa', name: 'Tenant Visa', required: false },
    payment_proof: { id: 'payment_proof', name: 'Tenant Proof of Payment', required: false },
    ejari: { id: 'ejari', name: 'Tawtheeq/Ejari Contract', required: false },
    tenancy_contract: { id: 'tenancy_contract', name: 'Tenancy Contract', required: false },
    move_in_form: { id: 'move_in_form', name: 'Move In Form', required: false }
  }
  
  return requiredDocs.map(docType => allDocs[docType]).filter(doc => doc)
})

const landlordDocTypes = computed(() => {
  const residencyStatus = form.value?.landlord_residency_status
  const requiredDocs = getRequiredDocumentsByResidency(residencyStatus)
  
  const allDocs = {
    passport: { id: 'passport', name: 'Landlord Passport', required: true },
    national_id: { id: 'national_id', name: 'Landlord National ID', required: true },
    visa: { id: 'visa', name: 'Landlord Visa', required: true },
    title_deed: { id: 'title_deed', name: 'Title Deed', required: true }
  }
  
  return requiredDocs.map(docType => allDocs[docType]).filter(doc => doc)
})

// Computed property for city options based on selected country
const buyerCityOptions = computed(() => {
  const country = form.value?.buyer_country || 'other'
  return citiesByCountry[country] || citiesByCountry['other']
})

const sellerCityOptions = computed(() => {
  const country = form.value?.seller_country || 'other'
  return citiesByCountry[country] || citiesByCountry['other']
})

const tenantCityOptions = computed(() => {
  const country = form.value?.tenant_country || 'other'
  return citiesByCountry[country] || citiesByCountry['other']
})

const landlordCityOptions = computed(() => {
  const country = form.value?.landlord_country || 'other'
  return citiesByCountry[country] || citiesByCountry['other']
})
// Refs for document upload components
const buyerDocUploadRef = ref(null)
const sellerDocUploadRef = ref(null)
const tenantDocUploadRef = ref(null)
const landlordDocUploadRef = ref(null)

const __debug = import.meta.env.DEV
const dlog = (...args) => {
  if (__debug) console.log(...args)
}

// Clear all documents function
function clearAllDocuments() {
  dlog('Clearing all documents...')
  // Reset model arrays first (DocumentUpload is v-model driven).
  form.value.buyer_documents = []
  form.value.seller_documents = []
  form.value.tenant_documents = []
  form.value.landlord_documents = []

  // Backward-compatible guard if child component exposes a clear method.
  const refs = [
    buyerDocUploadRef.value,
    sellerDocUploadRef.value,
    tenantDocUploadRef.value,
    landlordDocUploadRef.value,
  ]
  refs.forEach((r) => {
    if (r && typeof r.clearAllFiles === 'function') {
      r.clearAllFiles()
    }
  })
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
  dlog('Searching projects with term:', search)
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
watch(() => form.value.project_id, async (newProjectId) => {
  if (newProjectId && !projects.value.some(p => p.id === newProjectId)) {
    try {
      const response = await api.get(`/listings/projects/${newProjectId}`)
      if (response.data) {
        projects.value.push(response.data)
      }
    } catch (error) {
      console.error('Error fetching specific project:', error)
    }
  }
}, { immediate: true })
watch(() => props.fieldErrors, (newVal) => {
  dlog('fieldErrors in DealForm:', newVal)
}, { deep: true, immediate: true })

watch(() => props.showErrors, (newVal) => {
  dlog('showErrors in DealForm:', newVal)
}, { immediate: true })
const fetchProjects = async () => {
  try {
    const response = await api.get('/listings/projects', { 
      params: { per_page: 1000 } 
    })
    projects.value = response.data?.data ?? response.data ?? []
    console.log(`Loaded ${projects.value.length} projects`)
  } catch (error) {
    console.error('Error loading projects:', error)
  }
}
onMounted(() => {
  fetchProjects()
  getCurrentUser()
})

function onSearchAreas(search) {
  emit('search-areas', search)
}

function onSearchSubCommunities(search) {
  emit('search-subcommunities', search)
}
const availableListings = ref([])
const selectedListing = ref(null)
const isLoadingListings = ref(false)
const currentUser = ref(null)

// جلب بيانات المستخدم الحالي
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

// دالة جلب الـ Listings المتاحة (التي باعها أو أجرها الـ Agent الحالي)
const fetchAvailableListings = async (areaId) => {
  if (!areaId) {
    availableListings.value = []
    return
  }
  
  // التأكد من وجود المستخدم
  if (!currentUser.value?.id) {
    getCurrentUser()
    if (!currentUser.value?.id) return
  }
  
  try {
    isLoadingListings.value = true
    
    const params = {
      area_id: areaId,
      sold_by_agent_id: currentUser.value.id,  // التي قام بها هذا الـ Agent
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
      project_id: listing.project_id,
      project_name: listing.project?.title,
      developer_id: listing.developer_id,
      developer_name: listing.developer?.name,
      status: listing.status, // 'converted' or 'rented'
      display_name: `${listing.unit_number || 'No Unit'} - ${listing.property_type?.name || 'Property'} (${listing.status === 'converted' ? 'Sold' : 'Rented'})`
    }))
    
    if (availableListings.value.length === 0) {
      proxy.$showNotification('No sold or rented units found for you in this location', 'info')
    }
    
  } catch (error) {
    console.error('Error fetching listings:', error)
    proxy.$showNotification('Failed to load available units', 'error')
  } finally {
    isLoadingListings.value = false
  }
}

// دالة عند اختيار المنطقة
const onAreaSelected = (areaId) => {
  // إعادة تعيين الـ listing المختار
  selectedListing.value = null
  // إعادة تعيين بيانات العقار
  if (form.value) {
    form.value.unit_no = ''
    form.value.property_type_id = null
    form.value.bedrooms = null
    form.value.unit_size = ''
    form.value.project_id = null
    form.value.developer_id = null
  }
  // جلب الـ Listings المتاحة
  fetchAvailableListings(areaId)
}

// دالة عند اختيار Listing
const onListingSelected = (listing) => {
  if (!listing) return
  
  // تعبئة بيانات Property Details من الـ Listing المختار
  form.value.unit_no = listing.unit_number || ''
  form.value.property_type_id = listing.property_type_id
  form.value.bedrooms = listing.bedrooms === 0 ? 'studio' : String(listing.bedrooms)
  form.value.unit_size = listing.size_sqft || ''
  form.value.project_id = listing.project_id
  form.value.developer_id = listing.developer_id
  
  // يمكن إضافة listing_id إلى الـ form لربط الديل بالـ listing
  form.value.listing_id = listing.id
  form.value.listing_status = listing.status // 'converted' or 'rented'
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
  { value: 'citizen', text: 'Citizen' },
  { value: 'resident', text: 'Resident' },
  { value: 'investor', text: 'Investor' },
  { value: 'tourist', text: 'Tourist' },
  { value: 'student', text: 'Student' },
  { value: 'other', text: 'Other' }
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
const currencyOptions = [
  { value: 'AED', text: 'AED' },
  { value: 'USD', text: 'USD' },
  { value: 'EUR', text: 'EUR' },
  { value: 'GBP', text: 'GBP' },
  { value: 'SAR', text: 'SAR' },
  { value: 'QAR', text: 'QAR' },
  { value: 'KWD', text: 'KWD' },
  { value: 'BHD', text: 'BHD' },
  { value: 'OMR', text: 'OMR' },
  { value: 'EGP', text: 'EGP' }
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

watch(
  () => form.value?.currency,
  (val) => {
    if (!val && form.value) form.value.currency = 'AED'
  },
  { immediate: true }
)


// Update watchers to work with the new format
watch(() => form.value?.buyer_country, (newCountry, oldCountry) => {
  if (form.value?.buyer_city) {
    const validCities = citiesByCountry[newCountry] || citiesByCountry['other']
    const isValid = validCities.some(city => city.value === form.value?.buyer_city)
    if (!isValid) {
      form.value.buyer_city = null
    }
  }
})

watch(() => form.value?.seller_country, (newCountry, oldCountry) => {
  if (form.value?.seller_city) {
    const validCities = citiesByCountry[newCountry] || citiesByCountry['other']
    const isValid = validCities.some(city => city.value === form.value?.seller_city)
    if (!isValid) {
      form.value.seller_city = null
    }
  }
})

watch(() => form.value?.tenant_country, (newCountry, oldCountry) => {
  if (form.value?.tenant_city) {
    const validCities = citiesByCountry[newCountry] || citiesByCountry['other']
    const isValid = validCities.some(city => city.value === form.value?.tenant_city)
    if (!isValid) {
      form.value.tenant_city = null
    }
  }
})

watch(() => form.value?.landlord_country, (newCountry, oldCountry) => {
  if (form.value?.landlord_city) {
    const validCities = citiesByCountry[newCountry] || citiesByCountry['other']
    const isValid = validCities.some(city => city.value === form.value?.landlord_city)
    if (!isValid) {
      form.value.landlord_city = null
    }
  }
})
</script>

<style scoped>
/* Figma deal forms — Inter, 16px sections, 12px labels, 14px inputs */
.section-title { font-size: 16px !important; font-weight: 600; color: var(--deal-navy-deep, #01062c); font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif); margin-bottom: 10px; letter-spacing: -0.02em; line-height: 1.35; }
.form-card { background: #fff; border: 1px solid #e5e7eb; box-shadow: none; padding: 0.875rem 1rem !important; }
.radius-12 { border-radius: 8px; }
.form-label-custom { font-size: 12px !important; font-weight: 500; color: var(--deal-text-muted, #64748b); margin-bottom: 4px; display: block; font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif); }
.custom-input { height: 42px !important; min-height: 42px; border-radius: 8px !important; border: 1px solid #e5e7eb !important; font-size: 13px !important; font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif); }
.custom-input::placeholder { font-size: 11px !important; color: #9ca3af; font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif); }
.custom-input.is-invalid { border-color: #dc3545 !important; }
.input-group-custom { display: flex; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
.input-group-custom .custom-input { border: none !important; flex: 1; border-radius: 8px 0 0 8px !important; }
:deep(.custom-v-select) { font-size: 13px; }
:deep(.custom-v-select .vs__dropdown-toggle) { height: 42px !important; min-height: 42px; border-radius: 8px; border: 1px solid #e5e7eb; font-size: 13px; padding: 2px 8px; }
:deep(.custom-v-select.is-invalid .vs__dropdown-toggle) { border-color: #dc3545 !important; }
:deep(.custom-v-select .vs__selected), :deep(.custom-v-select .vs__search) { font-size: 13px; }
:deep(.custom-v-select .vs__search::placeholder) { font-size: 11px !important; color: #9ca3af; }
:deep(.custom-v-select .vs__placeholder) { font-size: 11px !important; color: #9ca3af; }
:deep(.custom-v-select-inline) { min-width: 120px; }
:deep(.custom-v-select-inline .vs__dropdown-toggle) { height: 42px !important; min-height: 42px; border: none; border-left: 1px solid #e5e7eb; border-radius: 0 8px 8px 0; font-size: 11px; }
:deep(.custom-v-select-inline .vs__selected) { font-size: 11px; font-weight: 500; color: #64748b; }
:deep(.custom-v-select-inline .vs__search::placeholder) { font-size: 10px !important; color: #9ca3af; }
:deep(.custom-v-select-inline .vs__placeholder) { font-size: 10px !important; color: #9ca3af; }
.doc-tabs { gap: 8px; }
.doc-tab { height: 32px; min-height: 32px; padding: 0 14px; border-radius: 100px; border: 1px solid #E2E8F0; background: #fff; font-size: 12px; font-weight: 500; color: #64748B; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif); }
.doc-tab.active { background: #0F172A; color: #fff; border-color: #0F172A; }
.upload-zone { border-style: dashed !important; border-color: #E2E8F0 !important; background: #F8FAFC; }
.upload-icon { font-size: 36px; color: #94A3B8; }
.upload-text { font-size: 14px; color: #475569; margin: 0; }
.tag-pill { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background: #F1F5F9; border-radius: 100px; font-size: 13px; }
.tag-remove { cursor: pointer; font-size: 16px; }
.btn-tag-search { background: transparent; border: none; color: var(--deal-navy, #0f172a); font-size: 14px; font-weight: 500; cursor: pointer; }
.add-custom-field-link { font-size: 14px; color: var(--deal-navy, #0f172a); font-weight: 500; text-decoration: underline; }
.form-section { margin-top: 14px; }
.form-section:first-of-type { margin-top: 0; }

/* Inline per-section edit mode */
.inline-mode .section-title {
  display: none !important;
}
.inline-mode .form-card {
  border: 0 !important;
  box-shadow: none !important;
  background: transparent !important;
  padding: 0 !important;
}
.inline-mode .form-section {
  margin-top: 0 !important;
}
.inline-mode :deep(.row.g-3) {
  display: flex;
  flex-direction: column;
  gap: 12px !important;
}
.inline-mode :deep(.row.g-3 > [class*='col-']) {
  width: 100% !important;
  max-width: 100% !important;
  flex: 0 0 100% !important;
  padding-left: 0 !important;
  padding-right: 0 !important;
}
:deep(.custom-v-select .vs__open-indicator-icon) {
    font-size: 13px;
    color: #cfdbec;
}

:deep(.custom-v-select svg) {
    vertical-align: middle !important;
}
</style>