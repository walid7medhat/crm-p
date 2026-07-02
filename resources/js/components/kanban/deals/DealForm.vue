<template>
  <div class="deal-form-container deal-figma-ui" :class="{ 'inline-mode': inlineMode }">
    <div v-if="missingFieldLabels.length" class="alert alert-warning py-2 mb-3">
      <div class="small fw-semibold mb-1">Missing fields for selected stage</div>
      <div class="small">{{ missingFieldLabels.join(' • ') }}</div>
    </div>

    <!-- Source and Deal Name -->
    <section v-if="isSectionVisible('deal_information')" class="form-section">
      <h6 class="section-title mb-3">About Deal</h6>
      <div class="form-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label-custom">Deal Name <span class="text-danger">*</span></label>
            <b-form-input v-model="form.deal_name" placeholder="Enter Deal Name" class="custom-input" :class="{ 'is-invalid': showErrors && !form.deal_name }" />
            <div v-if="showErrors && fieldErrors.deal_name" class="invalid-feedback d-block">{{ fieldErrors.deal_name }}</div>
          </div>
          <div class="col-md-6">
            <label class="form-label-custom">Source <span class="text-danger">*</span></label>
            <v-select v-model="form.source" :options="sources" :reduce="item => item.name" label="name" placeholder="Not Selected" class="custom-v-select" :class="{ 'is-invalid': showErrors && !form.source }" clearable>
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
              </template>
            </v-select>
            <div v-if="showErrors && fieldErrors.source" class="invalid-feedback d-block">{{ fieldErrors.source }}</div>
          </div>
        </div>
      </div>
    </section>
  <!-- ✅ Stage Dates Section -->
    <section v-if="isSectionVisible('stage_dates')  " class="form-section">
      <h6 class="section-title mb-3">Stage Dates</h6>
      <div class="form-card p-3 radius-12">
        <div class="row g-3">
          <!-- Primary Stages -->
          <template v-if="dealType === 'primary'">
            <div class="col-md-4" v-if="shouldShowStageDate('eoi_date')">
              <label class="form-label-custom">EOI Date</label>
              <AdvancedDatePicker 
                v-model="form.eoi_date" 
                date-only 
                 dob-layout
                placeholder="Select EOI date"
                class="custom-input"
                :clearable="true"
                :disabled="false"
              />
            </div>
            <div class="col-md-4" v-if="shouldShowStageDate('booking_date')">
              <label class="form-label-custom">Booking Date</label>
              <AdvancedDatePicker 
                v-model="form.booking_date" 
                date-only 
                 dob-layout
                placeholder="Select Booking date"
                class="custom-input"
                :clearable="true"
                :disabled="false"
              />
            </div>
            <div class="col-md-4" v-if="shouldShowStageDate('spa_date')">
              <label class="form-label-custom">SPA Date</label>
              <AdvancedDatePicker 
                v-model="form.spa_date" 
                date-only 
                 dob-layout
                placeholder="Select SPA date"
                class="custom-input"
                :clearable="true"
                :disabled="false"
              />
            </div>
          </template>

          <!-- Secondary Stages -->
          <template v-else-if="dealType === 'secondary'">
            <div class="col-md-4" v-if="shouldShowStageDate('security_deposit_date')">
              <label class="form-label-custom">Security Deposit Date</label>
              <AdvancedDatePicker 
                v-model="form.security_deposit_date" 
                date-only 
                 dob-layout
                placeholder="Select Security Deposit date"
                class="custom-input"
                :clearable="true"
                :disabled="false"
              />
            </div>
            <div class="col-md-4" v-if="shouldShowStageDate('mou_date')">
              <label class="form-label-custom">MOU Date</label>
              <AdvancedDatePicker 
                v-model="form.mou_date" 
                date-only 
                 dob-layout
                placeholder="Select MOU date"
                class="custom-input"
                :clearable="true"
                :disabled="false"
              />
            </div>
            <div class="col-md-4" v-if="shouldShowStageDate('noc_date')">
              <label class="form-label-custom">NOC Date</label>
              <AdvancedDatePicker 
                v-model="form.noc_date" 
                date-only 
                 dob-layout
                placeholder="Select NOC date"
                class="custom-input"
                :clearable="true"
                :disabled="false"
              />
            </div>
          </template>

          <!-- Rental Stages -->
          <template v-else-if="dealType === 'rental'">
            <div class="col-md-4" v-if="shouldShowStageDate('application_date')">
              <label class="form-label-custom">Application Date</label>
              <AdvancedDatePicker
                v-model="form.application_date"
                date-only
                dob-layout
                placeholder="Select Application date"
                class="custom-input"
              />
            </div>
            <div class="col-md-4" v-if="shouldShowStageDate('contract_date')">
              <label class="form-label-custom">Contract Date</label>
              <AdvancedDatePicker
                v-model="form.contract_date"
                date-only
                dob-layout
                placeholder="Select Contract date"
                class="custom-input"
              />
            </div>
            <div class="col-md-4" v-if="shouldShowStageDate('ejari_date')">
              <label class="form-label-custom">Ejari Date</label>
              <AdvancedDatePicker
                v-model="form.ejari_date"
                date-only
                dob-layout
                placeholder="Select Ejari date"
                class="custom-input"
              />
            </div>
          </template>

          <!-- Won Date (all types) -->
          <div class="col-md-4" v-if="shouldShowStageDate('won_date')">
            <label class="form-label-custom">Won Date</label>
            <AdvancedDatePicker 
              v-model="form.won_date" 
              date-only 
               dob-layout
              placeholder="Select Won date"
              class="custom-input"
              :clearable="true"
                :disabled="false"
            />
          </div>
        </div>
      </div>
    </section>
    <!-- Buyer Section -->
    <section v-if="(dealType === 'primary' || dealType === 'secondary') && isSectionVisible('buyer_details')" class="form-section">
      <h6 class="section-title mb-3">Buyer Details</h6>
      <div class="form-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label-custom">Buyer First Name <span class="text-danger">*</span></label><b-form-input v-model="form.buyer_first_name" placeholder="Enter First Name" class="custom-input" :class="{ 'is-invalid': showErrors && !form.buyer_first_name }" /><div v-if="showErrors && fieldErrors.buyer_first_name" class="invalid-feedback d-block">{{ fieldErrors.buyer_first_name }}</div></div>
          <div class="col-md-4"><label class="form-label-custom">Buyer Last Name <span class="text-danger">*</span></label><b-form-input v-model="form.buyer_last_name" placeholder="Enter Last Name" class="custom-input" :class="{ 'is-invalid': showErrors && !form.buyer_last_name }" /><div v-if="showErrors && fieldErrors.buyer_last_name" class="invalid-feedback d-block">{{ fieldErrors.buyer_last_name }}</div></div>
          <div class="col-md-4">
            <label class="form-label-custom">Buyer Date Of Birth <span class="text-danger">*</span></label>
            <AdvancedDatePicker type="date" date-only dob-layout placeholder="Select date" v-model="form.buyer_dob" class="custom-input" :invalid="showErrors && !!fieldErrors.buyer_dob" />
            <div v-if="showErrors && fieldErrors.buyer_dob" class="invalid-feedback d-block">{{ fieldErrors.buyer_dob }}</div>
          </div>
          <div class="col-md-4"><label class="form-label-custom">Buyer Phone Number <span class="text-danger">*</span></label><CrmPhoneInput v-model="form.buyer_phone" placeholder="Enter Phone" :invalid="showErrors && (!!fieldErrors.buyer_phone || !form.buyer_phone)" :show-errors="showErrors" /><div v-if="showErrors && fieldErrors.buyer_phone" class="invalid-feedback d-block">{{ fieldErrors.buyer_phone }}</div></div>
          <div class="col-md-4"><label class="form-label-custom">Buyer Email <span class="text-danger">*</span></label><b-form-input v-model="form.buyer_email" type="email" placeholder="Enter Email" class="custom-input" :class="{ 'is-invalid': showErrors && !form.buyer_email }" /><div v-if="showErrors && fieldErrors.buyer_email" class="invalid-feedback d-block">{{ fieldErrors.buyer_email }}</div></div>
          <div class="col-md-4">
            <label class="form-label-custom">Buyer Nationality <span class="text-danger">*</span></label>
            <v-select v-model="form.buyer_nationality" :options="nationalityOptions" :reduce="item => item.text" label="text" placeholder="Select Nationality" class="custom-v-select" :class="{ 'is-invalid': showErrors && !form.buyer_nationality }" clearable>
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
            <div v-if="showErrors && fieldErrors.buyer_nationality" class="invalid-feedback d-block">{{ fieldErrors.buyer_nationality }}</div>
          </div>
          <div class="col-md-4" >
            <label class="form-label-custom">Buyer Residency Status <span class="text-danger">*</span></label>
            <v-select v-model="form.buyer_residency_status" :options="buyerResidencyOptions" :reduce="item => item.value" label="text" placeholder="Resident or Non Resident" :clearable="false" class="custom-v-select" :class="{ 'is-invalid': showErrors && !form.buyer_residency_status }">
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
              </template>
              
            </v-select>
            <div v-if="showErrors && fieldErrors.buyer_residency_status" class="invalid-feedback d-block">{{ fieldErrors.buyer_residency_status }}</div>
          </div>
          <div class="col-md-4"  v-if="form.buyer_residency_status !== 'resident'">
            <label class="form-label-custom">Buyer Country Of Residence <span class="text-danger">*</span></label>
            <v-select v-model="form.buyer_country" :options="countryOptions" :reduce="item => item.value" label="text" placeholder="Select Country" class="custom-v-select" clearable>
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
              </template>
            </v-select>
            <div v-if="showErrors && fieldErrors.buyer_country" class="invalid-feedback d-block">{{ fieldErrors.buyer_country }}</div>
          </div>
          <div class="col-md-4"  v-if="form.buyer_residency_status === 'resident'">
            <label class="form-label-custom">Buyer City Of Residence <span class="text-danger">*</span></label>
            <v-select v-model="form.buyer_city" :options="buyerCityOptions" :reduce="item => item.value" label="text" placeholder="Select City" class="custom-v-select" :class="{ 'is-invalid': showErrors && !form.buyer_city }" clearable>
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
              </template>
            </v-select>
            <div v-if="showErrors && fieldErrors.buyer_city" class="invalid-feedback d-block">{{ fieldErrors.buyer_city }}</div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Buyer Language <span class="text-danger">*</span></label>
            <v-select :model-value="normalizeLanguageSelection(form.buyer_language)" @update:modelValue="updateBuyerLanguage" :options="languageOptions" :reduce="item => item.value" label="text" placeholder="Select Language(s)" class="custom-v-select buyer-language-select" :multiple="true" :searchable="true" :close-on-select="false" :class="{ 'is-invalid': showErrors && !hasLanguageSelection(form.buyer_language) }" clearable>
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
              </template>
                <template #deselect="{ option }">
                <span class="custom-remove-icon">
                  <iconify-icon icon="lucide:x-circle"></iconify-icon>
                </span>
              </template>
            </v-select>
            <div v-if="showErrors && fieldErrors.buyer_language" class="invalid-feedback d-block">{{ fieldErrors.buyer_language }}</div>
          </div>
        </div>
        <div class="mt-3">
          <label class="section-title">Buyer Documents</label>
          <DocumentUpload v-if="dealType === 'primary'" v-model="form.buyer_documents" category="buyer" :document-types="primaryBuyerDocTypes" :compact="inlineMode" :show-errors="showErrors" ref="buyerDocUploadRef" />
          <DocumentUpload v-else-if="dealType === 'secondary'" v-model="form.buyer_documents" category="buyer" :document-types="secondaryBuyerDocTypes" :compact="inlineMode" :show-errors="showErrors" ref="buyerDocUploadRef" />
        </div>
      </div>
    </section>

    <!-- Seller Section (for Secondary only) -->
    <section v-if="dealType === 'secondary' && !shouldHideSeller && isSectionVisible('seller_details')" class="form-section">
      <h6 class="section-title mb-3">Seller Details</h6>
      <div class="form-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label-custom">First Name <span class="text-danger">*</span></label><b-form-input v-model="form.seller_first_name" placeholder="Enter First Name" class="custom-input" :class="{ 'is-invalid': showErrors && !form.seller_first_name }" /><div v-if="showErrors && fieldErrors.seller_first_name" class="invalid-feedback d-block">{{ fieldErrors.seller_first_name }}</div></div>
          <div class="col-md-4"><label class="form-label-custom">Last Name <span class="text-danger">*</span></label><b-form-input v-model="form.seller_last_name" placeholder="Enter Last Name" class="custom-input" :class="{ 'is-invalid': showErrors && !form.seller_last_name }" /><div v-if="showErrors && fieldErrors.seller_last_name" class="invalid-feedback d-block">{{ fieldErrors.seller_last_name }}</div></div>
          <div class="col-md-4">
            <label class="form-label-custom">Date Of Birth <span class="text-danger">*</span></label>
            <AdvancedDatePicker type="date" dob-layout date-only placeholder="Select date" v-model="form.seller_dob" class="custom-input" :invalid="showErrors && !!fieldErrors.seller_dob" />
            <div v-if="showErrors && fieldErrors.seller_dob" class="invalid-feedback d-block">{{ fieldErrors.seller_dob }}</div>
          </div>
          <div class="col-md-4"><label class="form-label-custom">Phone <span class="text-danger">*</span></label><CrmPhoneInput v-model="form.seller_phone" placeholder="Enter Phone" :invalid="showErrors && (!!fieldErrors.seller_phone || !form.seller_phone)" :show-errors="showErrors" /><div v-if="showErrors && fieldErrors.seller_phone" class="invalid-feedback d-block">{{ fieldErrors.seller_phone }}</div></div>
          <div class="col-md-4"><label class="form-label-custom">Email <span class="text-danger">*</span></label><b-form-input v-model="form.seller_email" type="email" placeholder="Enter Email" class="custom-input" :class="{ 'is-invalid': showErrors && !form.seller_email }" /><div v-if="showErrors && fieldErrors.seller_email" class="invalid-feedback d-block">{{ fieldErrors.seller_email }}</div></div>
          <div class="col-md-4">
            <label class="form-label-custom">Nationality <span class="text-danger">*</span></label>
            <v-select v-model="form.seller_nationality" :options="nationalityOptions" :reduce="item => item.text" label="text" placeholder="Select Nationality" class="custom-v-select" :class="{ 'is-invalid': showErrors && !form.seller_nationality }" clearable>
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
            <div v-if="showErrors && fieldErrors.seller_nationality" class="invalid-feedback d-block">{{ fieldErrors.seller_nationality }}</div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Residency Status <span class="text-danger">*</span></label>
            <v-select v-model="form.seller_residency_status" :options="residencyOptions" :reduce="item => item.value" label="text" placeholder="Select Status" class="custom-v-select" :class="{ 'is-invalid': showErrors && !form.seller_residency_status }" clearable>
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
              </template>
            </v-select>
            <div v-if="showErrors && fieldErrors.seller_residency_status" class="invalid-feedback d-block">{{ fieldErrors.seller_residency_status }}</div>
          </div>
          <div class="col-md-4"  v-if="form.seller_residency_status !== 'resident'">
            <label class="form-label-custom">Country Of Residence <span class="text-danger">*</span></label>
            <v-select v-model="form.seller_country" :options="countryOptions" :reduce="item => item.value" label="text" placeholder="Select Country" class="custom-v-select" :class="{ 'is-invalid': showErrors && !form.seller_country }" clearable>
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
              </template>
            </v-select>
            <div v-if="showErrors && fieldErrors.seller_country" class="invalid-feedback d-block">{{ fieldErrors.seller_country }}</div>
          </div>
          <div class="col-md-4" v-if="form.seller_residency_status === 'resident'">
            <label class="form-label-custom">City Of Residence <span class="text-danger">*</span></label>
            <v-select v-model="form.seller_city" :options="sellerCityOptions" :reduce="item => item.value" label="text" placeholder="Select City" class="custom-v-select" :class="{ 'is-invalid': showErrors && !form.seller_city }" clearable>
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
              </template>
            </v-select>
            <div v-if="showErrors && fieldErrors.seller_city" class="invalid-feedback d-block">{{ fieldErrors.seller_city }}</div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Language <span class="text-danger">*</span></label>
            <v-select v-model="form.seller_language" :options="languageOptions" :reduce="item => item.value" label="text" placeholder="Select Language" class="custom-v-select" :class="{ 'is-invalid': showErrors && !form.seller_language }" clearable>
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
              </template>
            </v-select>
            <div v-if="showErrors && fieldErrors.seller_language" class="invalid-feedback d-block">{{ fieldErrors.seller_language }}</div>
          </div>
        </div>
        <div class="mt-3"><label class="section-title">Seller Documents</label><DocumentUpload v-model="form.seller_documents" category="seller" :document-types="sellerDocTypes" :compact="inlineMode" :show-errors="showErrors" ref="sellerDocUploadRef" /></div>
      </div>
    </section>

    <!-- Tenant Section (for Rental) -->
    <section v-if="dealType === 'rental' && isSectionVisible('tenant_details')" class="form-section">
      <h6 class="section-title mb-3">Tenant Details</h6>
      <div class="form-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label-custom">First Name <span class="text-danger">*</span></label><b-form-input v-model="form.tenant_first_name" placeholder="Enter First Name" class="custom-input" :class="{ 'is-invalid': showErrors && !form.tenant_first_name }" /><div v-if="showErrors && fieldErrors.tenant_first_name" class="invalid-feedback d-block">{{ fieldErrors.tenant_first_name }}</div></div>
          <div class="col-md-4"><label class="form-label-custom">Last Name <span class="text-danger">*</span></label><b-form-input v-model="form.tenant_last_name" placeholder="Enter Last Name" class="custom-input" :class="{ 'is-invalid': showErrors && !form.tenant_last_name }" /><div v-if="showErrors && fieldErrors.tenant_last_name" class="invalid-feedback d-block">{{ fieldErrors.tenant_last_name }}</div></div>
          <div class="col-md-4">
            <label class="form-label-custom">Date Of Birth</label>
            <AdvancedDatePicker type="date" dob-layout date-only placeholder="Select date" v-model="form.tenant_dob" class="custom-input" :invalid="showErrors && !!fieldErrors.tenant_dob" />
            <div v-if="showErrors && fieldErrors.tenant_dob" class="invalid-feedback d-block">{{ fieldErrors.tenant_dob }}</div>
          </div>
          <div class="col-md-4"><label class="form-label-custom">Phone <span class="text-danger">*</span></label><CrmPhoneInput v-model="form.tenant_phone" placeholder="Enter Phone" :invalid="showErrors && (!!fieldErrors.tenant_phone || !form.tenant_phone)" :show-errors="showErrors" /><div v-if="showErrors && fieldErrors.tenant_phone" class="invalid-feedback d-block">{{ fieldErrors.tenant_phone }}</div></div>
          <div class="col-md-4"><label class="form-label-custom">Email <span class="text-danger">*</span></label><b-form-input v-model="form.tenant_email" type="email" placeholder="Enter Email" class="custom-input" :class="{ 'is-invalid': showErrors && !form.tenant_email }" /><div v-if="showErrors && fieldErrors.tenant_email" class="invalid-feedback d-block">{{ fieldErrors.tenant_email }}</div></div>
          <div class="col-md-4">
            <label class="form-label-custom">Nationality <span class="text-danger">*</span></label>
            <v-select v-model="form.tenant_nationality" :options="nationalityOptions" :reduce="item => item.text" label="text" placeholder="Select Nationality" class="custom-v-select" :class="{ 'is-invalid': showErrors && !form.tenant_nationality }" clearable>
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
            <div v-if="showErrors && fieldErrors.tenant_nationality" class="invalid-feedback d-block">{{ fieldErrors.tenant_nationality }}</div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Residency Status <span class="text-danger">*</span></label>
            <v-select v-model="form.tenant_residency_status" :options="residencyOptions" :reduce="item => item.value" label="text" placeholder="Select Status" class="custom-v-select" :class="{ 'is-invalid': showErrors && !form.tenant_residency_status }" clearable>
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
              </template>
            </v-select>
            <div v-if="showErrors && fieldErrors.tenant_residency_status" class="invalid-feedback d-block">{{ fieldErrors.tenant_residency_status }}</div>
          </div>
          <div class="col-md-4" v-if="form.tenant_residency_status !== 'resident'">
            <label class="form-label-custom">Country Of Residence <span class="text-danger">*</span></label>
            <v-select v-model="form.tenant_country" :options="countryOptions" :reduce="item => item.value" label="text" placeholder="Select Country" class="custom-v-select" :class="{ 'is-invalid': showErrors && !form.tenant_country }" clearable>
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
              </template>
            </v-select>
            <div v-if="showErrors && fieldErrors.tenant_country" class="invalid-feedback d-block">{{ fieldErrors.tenant_country }}</div>
          </div>
          <div class="col-md-4" v-if="form.tenant_residency_status === 'resident'">
            <label class="form-label-custom">City Of Residence <span class="text-danger">*</span></label>
            <v-select v-model="form.tenant_city" :options="tenantCityOptions" :reduce="item => item.value" label="text" placeholder="Select City" class="custom-v-select" :class="{ 'is-invalid': showErrors && !form.tenant_city }" clearable>
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
              </template>
            </v-select>
            <div v-if="showErrors && fieldErrors.tenant_city" class="invalid-feedback d-block">{{ fieldErrors.tenant_city }}</div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Language <span class="text-danger">*</span></label>
            <v-select v-model="form.tenant_language" :options="languageOptions" :reduce="item => item.value" label="text" placeholder="Select Language" class="custom-v-select" :class="{ 'is-invalid': showErrors && !form.tenant_language }" clearable>
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
              </template>
            </v-select>
            <div v-if="showErrors && fieldErrors.tenant_language" class="invalid-feedback d-block">{{ fieldErrors.tenant_language }}</div>
          </div>
        </div>
        <div class="mt-3"><label class="section-title">Tenant Documents</label><DocumentUpload v-model="form.tenant_documents" category="tenant" :document-types="tenantDocTypes" :compact="inlineMode" :show-errors="showErrors" ref="tenantDocUploadRef" /></div>
      </div>
    </section>

    <!-- Landlord Section (for Rental) -->
    <section v-if="dealType === 'rental' && !shouldHideLandlord && isSectionVisible('landlord_details')" class="form-section">
      <h6 class="section-title mb-3">Landlord Details</h6>
      <div class="form-card p-3 radius-12">
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label-custom">First Name <span class="text-danger">*</span></label><b-form-input v-model="form.landlord_first_name" placeholder="Enter First Name" class="custom-input" :class="{ 'is-invalid': showErrors && !form.landlord_first_name }" /><div v-if="showErrors && fieldErrors.landlord_first_name" class="invalid-feedback d-block">{{ fieldErrors.landlord_first_name }}</div></div>
          <div class="col-md-4"><label class="form-label-custom">Last Name <span class="text-danger">*</span></label><b-form-input v-model="form.landlord_last_name" placeholder="Enter Last Name" class="custom-input" :class="{ 'is-invalid': showErrors && !form.landlord_last_name }" /><div v-if="showErrors && fieldErrors.landlord_last_name" class="invalid-feedback d-block">{{ fieldErrors.landlord_last_name }}</div></div>
          <div class="col-md-4">
            <label class="form-label-custom">Date Of Birth <span class="text-danger">*</span></label>
            <AdvancedDatePicker type="date" dob-layout date-only placeholder="Select date" v-model="form.landlord_dob" class="custom-input" :invalid="showErrors && !!fieldErrors.landlord_dob" />
            <div v-if="showErrors && fieldErrors.landlord_dob" class="invalid-feedback d-block">{{ fieldErrors.landlord_dob }}</div>
          </div>
          <div class="col-md-4"><label class="form-label-custom">Phone <span class="text-danger">*</span></label><CrmPhoneInput v-model="form.landlord_phone" placeholder="Enter Phone" :invalid="showErrors && (!!fieldErrors.landlord_phone || !form.landlord_phone)" :show-errors="showErrors" /><div v-if="showErrors && fieldErrors.landlord_phone" class="invalid-feedback d-block">{{ fieldErrors.landlord_phone }}</div></div>
          <div class="col-md-4"><label class="form-label-custom">Email <span class="text-danger">*</span></label><b-form-input v-model="form.landlord_email" type="email" placeholder="Enter Email" class="custom-input" :class="{ 'is-invalid': showErrors && !form.landlord_email }" /><div v-if="showErrors && fieldErrors.landlord_email" class="invalid-feedback d-block">{{ fieldErrors.landlord_email }}</div></div>
          <div class="col-md-4">
            <label class="form-label-custom">Nationality <span class="text-danger">*</span></label>
            <v-select v-model="form.landlord_nationality" :options="nationalityOptions" :reduce="item => item.text" label="text" placeholder="Select Nationality" class="custom-v-select" :class="{ 'is-invalid': showErrors && !form.landlord_nationality }" clearable>
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
            <div v-if="showErrors && fieldErrors.landlord_nationality" class="invalid-feedback d-block">{{ fieldErrors.landlord_nationality }}</div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Residency Status <span class="text-danger">*</span></label>
            <v-select v-model="form.landlord_residency_status" :options="residencyOptions" :reduce="item => item.value" label="text" placeholder="Select Status" class="custom-v-select" :class="{ 'is-invalid': showErrors && !form.landlord_residency_status }" clearable>
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
              </template>
            </v-select>
            <div v-if="showErrors && fieldErrors.landlord_residency_status" class="invalid-feedback d-block">{{ fieldErrors.landlord_residency_status }}</div>
          </div>
          <div class="col-md-4" v-if="form.landlord_residency_status !== 'resident'">
            <label class="form-label-custom">Country Of Residence <span class="text-danger">*</span></label>
            <v-select v-model="form.landlord_country" :options="countryOptions" :reduce="item => item.value" label="text" placeholder="Select Country" class="custom-v-select" :class="{ 'is-invalid': showErrors && !form.landlord_country }" clearable>
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
              </template>
            </v-select>
            <div v-if="showErrors && fieldErrors.landlord_country" class="invalid-feedback d-block">{{ fieldErrors.landlord_country }}</div>
          </div>
          <div class="col-md-4" v-if="form.landlord_residency_status === 'resident'">
            <label class="form-label-custom">City Of Residence <span class="text-danger">*</span></label>
            <v-select v-model="form.landlord_city" :options="landlordCityOptions" :reduce="item => item.value" label="text" placeholder="Select City" class="custom-v-select" :class="{ 'is-invalid': showErrors && !form.landlord_city }" clearable>
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
              </template>
            </v-select>
            <div v-if="showErrors && fieldErrors.landlord_city" class="invalid-feedback d-block">{{ fieldErrors.landlord_city }}</div>
          </div>
          <div class="col-md-4">
            <label class="form-label-custom">Language <span class="text-danger">*</span></label>
            <v-select v-model="form.landlord_language" :options="languageOptions" :reduce="item => item.value" label="text" placeholder="Select Language" class="custom-v-select" :class="{ 'is-invalid': showErrors && !form.landlord_language }" clearable>
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
              </template>
            </v-select>
            <div v-if="showErrors && fieldErrors.landlord_language" class="invalid-feedback d-block">{{ fieldErrors.landlord_language }}</div>
          </div>
        </div>
        <div class="mt-3"><label class="section-title">Landlord Documents</label><DocumentUpload v-model="form.landlord_documents" category="landlord" :document-types="landlordDocTypes" :compact="inlineMode" :show-errors="showErrors" ref="landlordDocUploadRef" /></div>
      </div>
    </section>

    <!-- ========== PROPERTY DETAILS SECTION (Multi Properties) ========== -->
    <section v-if="isSectionVisible('property_details')" class="form-section">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="section-title mb-0">Property Details</h6>
        <button 
          v-if="showMultiProperties"
          type="button" 
          class="btn-add-property"
          @click="addNewProperty"
        >
          <iconify-icon icon="lucide:plus" class="me-1"></iconify-icon>
          Add Property
        </button>
      </div>
      
      <div class="form-card p-3 radius-12">
        <!-- Multi Properties Mode -->
        <div v-if="showMultiProperties && propertiesList.length > 0">
          <PropertyList
            ref="propertyListRef"
            v-model="propertiesList"
            :deal-id="form.deal_id || null"
            :property-types="propertyTypes"
            :areas="areas"
            :developers="developers"
            :show-errors="showErrors"
            :required-fields="props.missingFields"
            :deal-type="dealType"
            :selected-stage-name="selectedStageName"
            :selected-stage-order="selectedStageOrder"
          :property-doc-types="propertyDocTypes"
             :show-property-documents="showPropertyDocuments"
              :inline-mode="inlineMode"
            @search-areas="(search) => emit('search-areas', search)"
          />
        </div>
        
        <!-- Legacy Single Property Mode -->
        <div v-else class="row g-3">
          <div class="col-md-6">
            <label class="form-label-custom">Property Address <span class="text-danger">*</span></label>
            <v-select v-model="form.area_id" :options="areas" :reduce="item => item.id" label="name" placeholder="Select Location..." class="custom-v-select" @update:modelValue="onAreaSelected" @search="(search) => emit('search-areas', search)" :class="{ 'is-invalid': showErrors && !form.area_id }" clearable>
              <template #option="option">
                <div class="location-option"><i class="ri-map-pin-line location-option-icon"></i><div class="location-option-text"><span class="location-option-name">{{ option.name }}</span><span class="location-option-subtitle">{{ option.area_parents_title }}</span></div></div>
              </template>
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
              </template>
            </v-select>
          </div>
          <div class="col-md-6" v-if="showSingleListingPicker">
            <label class="form-label-custom">Select Unit <span v-if="isSingleListingRequired" class="text-danger">*</span></label>
            <v-select v-model="selectedListing" :options="availableListings" :reduce="item => item" label="display_name" placeholder="Select a unit..." class="custom-v-select" :class="{ 'is-invalid': showErrors && isSingleListingRequired && !form.listing_id }" @update:modelValue="onListingSelected" :disabled="isLoadingListings" clearable>
              <template #option="option">
                <div><strong>{{ option.unit_number || 'No Unit' }}</strong><span class="text-muted ms-2">- {{ option.property_type?.name || 'N/A' }}</span><div class="small text-muted">{{ option.bedrooms_text }} | {{ option.size_sqft || 'N/A' }} sqft</div><div class="small text-success">{{ option.status === 'converted' ? 'Sold' : 'Rented' }}</div></div>
              </template>
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
              </template>
            </v-select>
            <div class="small text-muted mt-1" v-if="isLoadingListings"><b-spinner small></b-spinner> Loading units...</div>
            <div class="small text-muted mt-1" v-else-if="!form.area_id"><iconify-icon icon="lucide:info" class="me-1"></iconify-icon> Select a property address first</div>
            <div class="small text-muted mt-1" v-else-if="availableListings.length === 0"><iconify-icon icon="lucide:alert-circle" class="me-1"></iconify-icon> No {{ dealType === 'secondary' ? 'sold' : 'rented' }} units available for you in this area</div>
            <div class="small text-muted mt-1" v-else><iconify-icon icon="lucide:info" class="me-1"></iconify-icon> Showing {{ dealType === 'secondary' ? 'sold' : 'rented' }} units in this location</div>
            <div v-if="showErrors && isSingleListingRequired && !form.listing_id" class="invalid-feedback d-block">Please select a unit</div>
          </div>

          <!-- Secondary: read-only summary of the unit auto-filled from the chosen listing. -->
          <div class="col-12" v-if="hidePropertyDetailFields && selectedListingSummary">
            <div class="listing-summary-card">
              <div class="listing-summary-title">
                <iconify-icon icon="lucide:home" class="me-1"></iconify-icon>
                Selected Unit
              </div>
              <div class="listing-summary-grid">
                <div><span class="listing-summary-label">Unit No</span><span class="listing-summary-value">{{ selectedListingSummary.unit_number || '—' }}</span></div>
                <div><span class="listing-summary-label">Property Type</span><span class="listing-summary-value">{{ selectedListingSummary.property_type?.name || '—' }}</span></div>
                <div><span class="listing-summary-label">Bedrooms</span><span class="listing-summary-value">{{ selectedListingSummary.bedrooms_text || '—' }}</span></div>
                <div><span class="listing-summary-label">Unit Size</span><span class="listing-summary-value">{{ selectedListingSummary.size_sqft ? `${selectedListingSummary.size_sqft} sqft` : '—' }}</span></div>
              </div>
            </div>
          </div>

          <div class="col-md-6" v-if="!hidePropertyDetailFields"><label class="form-label-custom">Unit No <span class="text-danger">*</span></label><b-form-input v-model="form.unit_no" placeholder="Enter Unit No" class="custom-input" :class="{ 'is-invalid': showErrors && !form.unit_no }" /></div>
          <div class="col-md-4" v-if="!hidePropertyDetailFields">
            <label class="form-label-custom">Property Type <span class="text-danger">*</span></label>
            <v-select v-model="form.property_type_id" :options="propertyTypes" :reduce="item => item.id" label="name" placeholder="Select Property Type" class="custom-v-select" :class="{ 'is-invalid': showErrors && !form.property_type_id }" clearable>
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
              </template>
            </v-select>
          </div>
          <div class="col-md-4" v-if="!hidePropertyDetailFields && showBedroomsFieldInProperty">
            <label class="form-label-custom">Bedrooms</label>
            <v-select v-model="form.bedrooms" :options="bedroomOptions" :reduce="o => o.value" label="text" placeholder="Select Bedroom" class="custom-v-select" clearable>
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
              </template>
            </v-select>
          </div>
          <div class="col-md-4" v-if="!hidePropertyDetailFields"><label class="form-label-custom">Unit Size (sq.ft)</label><b-form-input v-model="form.unit_size" placeholder="Enter Unit Size" class="custom-input" /></div>
            <div v-if="showBudgetFields" class="col-md-4">
              <label class="form-label-custom">
                  Budget (AED)
                  <span v-if="isBudgetRequired" class="text-danger">*</span>
              </label>
              <div
                  ref="budgetTriggerRef"
                  class="budget-field-wrap"
                  :class="{ 'is-invalid-group': (showErrors && isBudgetRequired && !form.value.budget_from && !form.value.budget_to) || fieldErrors.budget_from || fieldErrors.budget_to }"
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
              <div v-if="showErrors && isBudgetRequired && !form.value.budget_from && !form.value.budget_to" class="invalid-feedback d-block">
                  Budget range is required
              </div>
              <div v-if="fieldErrors.budget_from || fieldErrors.budget_to" class="invalid-feedback d-block">
                  {{ fieldErrors.budget_from || fieldErrors.budget_to }}
              </div>
          </div>
          <div class="col-md-4" v-if="showPurchasePrice">
          <label class="form-label-custom">  
            <span v-if="isWonStage">Amount</span>
            <span v-else>Purchase Price</span>
            <span v-if="isPurchasePriceRequired" class="text-danger">*</span>
          </label>
        <div class="input-group"><span class="input-group-text">AED</span><b-form-input v-model="form.purchase_price" type="text" inputmode="numeric" placeholder="Amount" class="custom-input" :class="{ 'is-invalid': showErrors && isPurchasePriceRequired && !form.purchase_price }" @keypress="onMoneyKeypress" /></div></div>
          <div class="col-md-4">
            <label class="form-label-custom">Developer</label>
            <v-select v-model="form.developer_id" :options="developers" :reduce="item => item.id" label="name" placeholder="Select Developer" class="custom-v-select" clearable :disabled="lockPropertyFieldsUntilListing">
              <template #open-indicator="{ attributes }">
                <span v-bind="attributes"><iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon></span>
              </template>
            </v-select>
          </div>
          <!-- Developer sales person — deal-specific, not on the listing, so always shown. -->
          <div class="col-md-4"><label class="form-label-custom">Developer sales person name</label><b-form-input v-model="form.developer_name" placeholder="Enter Developer Name" class="custom-input" /></div>
          <div class="col-md-4">
            <label class="form-label-custom">Developer sales person phone</label>
            <CrmPhoneInput
              :key="`developer-phone-${props.selectedStageId || 'default'}`"
              v-model="form.developer_phone"
              placeholder="Enter Phone"
              :invalid="showErrors && !!fieldErrors.developer_phone"
              :show-errors="showErrors"
            />
            <div v-if="showErrors && fieldErrors.developer_phone" class="invalid-feedback d-block">{{ fieldErrors.developer_phone }}</div>
          </div>
        </div>
      </div>
    </section>

    <!-- Deal Financials -->
    <section v-if="isSectionVisible('deal_financials') && isWonStage" class="form-section">
      <h6 class="section-title mb-3">Deal Financials</h6>
      <div class="form-card p-3 radius-12">
        <div class="row g-3">
          <!-- Deal Total Amount -->
          <div class="col-md-4">
            <label class="form-label-custom">Deal Total Amount</label>
            <div class="input-group">
              <span class="input-group-text">AED</span>
              <b-form-input v-model="form.deal_total_amount" type="text" inputmode="numeric" placeholder="Enter Total Amount" class="custom-input" @keypress="onMoneyKeypress" />
            </div>
            <div v-if="showErrors && fieldErrors.deal_total_amount" class="invalid-feedback d-block">{{ fieldErrors.deal_total_amount }}</div>
          </div>
          
          <!-- Deal Commission % -->
          <div class="col-md-4">
            <label class="form-label-custom">Deal Commission %</label>
            <div class="input-group">
              <b-form-input v-model="form.deal_commission" type="number" placeholder="Enter Commission %" class="custom-input" />
              <span class="input-group-text">%</span>
            </div>
            <div v-if="showErrors && fieldErrors.deal_commission" class="invalid-feedback d-block">{{ fieldErrors.deal_commission }}</div>
          </div>
        </div>
      </div>
    </section>

    <!-- Responsible Person -->
    <div v-if="!inlineMode" class="col-12">
      <ResponsiblePersonSelector v-model="form.responsible_person_id" :users="users" :responsible-person="responsiblePerson" :class="{ 'is-invalid': showErrors && !form.responsible_person_id }" />
      <div v-if="showErrors && fieldErrors.responsible_person_id" class="invalid-feedback d-block">{{ fieldErrors.responsible_person_id }}</div>
    </div>
  </div>
  <Teleport to="body">
    <div
        v-if="showBudgetDropdown"
        ref="budgetDropdownPanelRef"
        class="budget-dropdown budget-dropdown--portal"
        :style="budgetDropdownStyle"
        @click.stop
    >
        <div class="budget-from-to-row">
            <div class="budget-col">
                <label class="budget-input-label">From</label>
                <b-form-input
                    :model-value="form.value.budget_from ? formatBudgetWithCommas(form.value.budget_from) : ''"
                    placeholder="0"
                    inputmode="numeric"
                    class="custom-input budget-dropdown-input"
                    @keypress="onMoneyKeypress"
                    @update:model-value="(val) => setBudgetValue('budget_from', val)"
                />
            </div>
            <div class="budget-col">
                <label class="budget-input-label">To</label>
                <b-form-input
                    :model-value="form.value.budget_to ? formatBudgetWithCommas(form.value.budget_to) : ''"
                    placeholder="0"
                    inputmode="numeric"
                    class="custom-input budget-dropdown-input"
                    @keypress="onMoneyKeypress"
                    @update:model-value="(val) => setBudgetValue('budget_to', val)"
                />
            </div>
        </div>
    </div>
</Teleport>
</template>

<script setup>
import { ref, watch, computed, onMounted ,onBeforeUnmount } from 'vue'
import { BFormInput } from 'bootstrap-vue-3'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import DocumentUpload from './DocumentUpload.vue'
import ResponsiblePersonSelector from '../shared/ResponsiblePersonSelector.vue'
import PropertyList from './PropertyList.vue'
import AdvancedDatePicker from '@/components/shared/AdvancedDatePicker.vue'
import CrmPhoneInput from '@/components/common/CrmPhoneInput.vue'
import api from '@/plugins/axios'
import { buildListingFilterParams } from '@/composables/useDealListingPicker'
import { isNonEmptyPhoneValid } from '@/utils/phone'
import { getCurrentInstance } from 'vue'
import { normalizeLanguageSelection, hasLanguageSelection } from '@/composables/useLanguageMultiSelect'
import countries from "i18n-iso-countries";
import en from "i18n-iso-countries/langs/en.json";
countries.registerLocale(en);
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
  selectedStageName: { type: String, default: '' },
  selectedStageOrder: { type: [Number, String], default: 0 },
  missingFields: { type: Array, default: () => [] },
  activeEditSection: { type: String, default: null },
  inlineMode: { type: Boolean, default: false }
})

const emit = defineEmits(['update:modelValue', 'search-areas', 'search-subcommunities', 'update:hasListingId', 'update:dealType'])

// ========== Refs ==========
const form = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) })
// Derived from form.listing_id — single source of truth. Becomes true the moment a listing is
// chosen (only possible after picking an area that has listings) and resets when cleared.
const hasListingId = computed(() => !!form.value?.listing_id)
const responsiblePerson = computed(() => {
  const id = form.value?.responsible_person_id
  if (!id || !props.users.length) return null
  return props.users.find(u => u.id === id) || null
})
// ========== Budget Dropdown  ==========
const showBudgetDropdown = ref(false)
const budgetTriggerRef = ref(null)
const budgetDropdownPanelRef = ref(null)
const budgetDropdownStyle = ref({})

const budgetDisplay = computed(() => {
    const from = form.value.budget_from ? formatBudgetWithCommas(form.value.budget_from) : ''
    const to = form.value.budget_to ? formatBudgetWithCommas(form.value.budget_to) : ''
    if (!from && !to) return 'Select budget range'
    if (from && to) return `${from} - ${to}`
    if (from) return `From ${from}`
    return `To ${to}`
})
function normalizeBudgetString(value) {
    return String(value ?? '').replace(/[^\d]/g, '')
}

function formatBudgetWithCommas(value) {
    if (!value && value !== 0) return ''
    const digits = normalizeBudgetString(value)
    if (!digits) return ''
    return Number(digits).toLocaleString('en-US')
}

function setBudgetValue(key, value) {
    const digits = normalizeBudgetString(value)
    form.value[key] = digits ? Number(digits) : null
}

function onMoneyKeypress(e) {
  if (!/^\d$/.test(e.key)) e.preventDefault()
}

function getBudgetTriggerElement() {
    let el = budgetTriggerRef.value
    if (Array.isArray(el)) el = el.find(Boolean)
    if (el && typeof el.getBoundingClientRect === 'function') return el
    if (el?.$el && typeof el.$el.getBoundingClientRect === 'function') return el.$el
    return null
}

function updateBudgetDropdownPosition() {
    const el = getBudgetTriggerElement()
    if (!el) return
    const r = el.getBoundingClientRect()
    budgetDropdownStyle.value = {
        position: 'fixed',
        top: `${Math.round(r.bottom + 6)}px`,
        left: `${Math.round(r.left)}px`,
        width: `${Math.max(Math.round(r.width), 220)}px`,
        zIndex: '10060'
    }
}

function removeBudgetDropdownListeners() {
    window.removeEventListener('scroll', updateBudgetDropdownPosition, true)
    window.removeEventListener('resize', updateBudgetDropdownPosition)
}

async function toggleBudgetDropdown() {
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

function onDocumentClick(event) {
    if (!showBudgetDropdown.value) return
    const t = event.target
    const triggerEl = getBudgetTriggerElement()
    if (triggerEl?.contains(t) || budgetDropdownPanelRef.value?.contains(t)) return
    showBudgetDropdown.value = false
    removeBudgetDropdownListeners()
}

// ========== Multi Properties ==========
const propertiesList = ref([])
const propertyListRef = ref(null)
const showMultiProperties = ref(false)

// ========== Listings ==========
const availableListings = ref([])
const selectedListing = ref(null)
const isLoadingListings = ref(false)
const currentUser = ref(null)

// ========== Document Upload Refs ==========
const buyerDocUploadRef = ref(null)
const sellerDocUploadRef = ref(null)
const tenantDocUploadRef = ref(null)
const landlordDocUploadRef = ref(null)

// ========== Computed ==========
const showBudgetFields = computed(() => {
  const stageName = props.selectedStageName?.toLowerCase() || ''
  return stageName.includes('eoi')
})
const isWonStage = computed(() => {
  const stageName = props.selectedStageName?.toLowerCase() || ''
  return stageName.includes('won') || stageName.includes('deal won')
})
const isBudgetRequired = computed(() => {
  const missingFields = props.missingFields || []
  return missingFields.includes('budget_from') || missingFields.includes('budget_to')
})

const showPurchasePrice = computed(() => {
  const dt = props.dealType
  if (dt !== 'primary' && dt !== 'secondary') return false
  // Secondary deals: purchase price is the only manual figure the user enters (the listing
  // covers everything else), so make it visible regardless of stage.
  if (dt === 'secondary') return true
  const order = Number(props.selectedStageOrder) || 0
  if (order >= 3) return true
  const stageName = props.selectedStageName?.toLowerCase() || ''
  return stageName.includes('booking') || stageName.includes('mou') || stageName.includes('spa') || stageName.includes('won')
})

const isPurchasePriceRequired = computed(() => {
  // Secondary deals: purchase price is mandatory — it's the only manual figure on the form.
  if (props.dealType === 'secondary') return true
  const missingFields = props.missingFields || []
  return missingFields.includes('purchase_price')
})
const showPropertyDocuments = computed(() => {
  const stageName = props.selectedStageName?.toLowerCase() || ''
  return stageName.includes('eoi') || stageName.includes('booking') || stageName.includes('spa') || stageName.includes('won')
})

const shouldHideSeller = computed(() => hasListingId.value && props.dealType === 'secondary')
const shouldHideLandlord = computed(() => hasListingId.value && props.dealType === 'rental')

// Single-property mode listing picker: show whenever the deal type uses a listing
// (secondary/rental), regardless of whether listings have been fetched yet.
const showSingleListingPicker = computed(() =>
  !showMultiProperties.value && (props.dealType === 'secondary' || props.dealType === 'rental')
)
const isSingleListingRequired = computed(() => props.dealType === 'secondary')

// Secondary deals: property details belong to the listing. Hide the manual inputs
// entirely and show a read-only summary of the chosen unit instead.
const hidePropertyDetailFields = computed(() => props.dealType === 'secondary')

const selectedListingSummary = computed(() => {
  if (!form.value?.listing_id) return null
  return availableListings.value.find(l => l.id === form.value.listing_id) || selectedListing.value
})

// ========== Document Types ==========
const normalizeResidencyStatus = (status) => {
  if (!status) return 'non_resident'
  const value = String(status).toLowerCase()
  return value === 'resident' ? 'resident' : 'non_resident'
}

const getRequiredDocumentsByResidency = (residencyStatus) => {
  return normalizeResidencyStatus(residencyStatus) === 'resident' ? ['passport', 'national_id'] : ['passport']
}

const isSpaStageOrLater = computed(() => {
  const stageName = String(props.selectedStageName || '').toLowerCase()
  return stageName.includes('spa signed') || stageName.includes('deal done') || stageName.includes('deal won') || stageName.includes('transfer') || stageName.includes('handover') || stageName.includes('closed')
})

const isEoiStageOrLater = computed(() => {
  const stageName = String(props.selectedStageName || '').toLowerCase()
  return stageName.includes('eoi') || isSpaStageOrLater.value
})

const primaryBuyerDocTypes = computed(() => {
  const residencyStatus = form.value?.buyer_residency_status
  const requiredResidencyDocs = getRequiredDocumentsByResidency(residencyStatus)
  const docs = []
  if (requiredResidencyDocs.includes('passport')) docs.push({ id: 'passport', name: 'Passport', required: true })
  if (requiredResidencyDocs.includes('national_id')) docs.push({ id: 'national_id', name: 'Emirates ID', required: true })
  docs.push({ id: 'kyc', name: 'KYC', required: isSpaStageOrLater.value })
  // docs.push({ id: 'spa', name: 'Buyer SPA', required: isSpaStageOrLater.value })
  // docs.push({ id: 'payment_proof', name: 'Buyer Payment Proof', required: isEoiStageOrLater.value })
  return docs
})
const propertyDocTypes = computed(() => {
  const stageName = props.selectedStageName?.toLowerCase() || ''
  const dt = props.dealType
  const order = Number(props.selectedStageOrder) || 0

  // SECONDARY: order 3 = MOU (MOU required), 4 = NOC (MOU + NOC required, cumulative),
  // 5 = Won (MOU + NOC required). Payment Proof / SPA remain optional.
  if (dt === 'secondary') {
    if (order >= 5 || stageName.includes('won') || stageName.includes('deal won')) {
      return [
        { id: 'mou', name: 'MOU Document', required: true },
        { id: 'noc', name: 'NOC Document', required: true },
        { id: 'payment_proof', name: 'Payment Proof', required: false },
        { id: 'spa', name: 'SPA Document', required: false },
      ]
    }
    if (order >= 4 || stageName.includes('noc')) {
      return [
        { id: 'mou', name: 'MOU Document', required: true },
        { id: 'noc', name: 'NOC Document', required: true },
        { id: 'payment_proof', name: 'Payment Proof', required: false },
      ]
    }
    if (order >= 3 || stageName.includes('mou')) {
      return [
        { id: 'mou', name: 'MOU Document', required: true },
        { id: 'payment_proof', name: 'Payment Proof', required: false },
      ]
    }
    if (order >= 2) {
      return [
        { id: 'payment_proof', name: 'Payment Proof', required: false },
      ]
    }
    return []
  }

  // PRIMARY (existing flow)
  if (stageName.includes('eoi')) {
    return [
      { id: 'eoi', name: 'EOI Document', required: true },
    ]
  }
  if (stageName.includes('booking')) {
    return [
      { id: 'eoi', name: 'EOI Document', required: true },
      { id: 'booking', name: 'Booking Form', required: true },
      { id: 'payment_proof', name: 'Payment Proof', required: false },
    ]
  }
  if (stageName.includes('spa')) {
    return [
      { id: 'eoi', name: 'EOI Document', required: true },
      { id: 'booking', name: 'Booking Form', required: true },
      { id: 'payment_proof', name: 'Payment Proof', required: false },
      { id: 'spa', name: 'SPA Document', required: true },
    ]
  }
  if (stageName.includes('won') || stageName.includes('deal won')) {
    return [
      { id: 'eoi', name: 'EOI Document', required: true },
      { id: 'booking', name: 'Booking Form', required: true },
      { id: 'payment_proof', name: 'Payment Proof', required: true },
      { id: 'spa', name: 'SPA Document', required: true },
    ]
  }

  return []
})
const secondaryBuyerDocTypes = computed(() => {
  const residencyStatus = form.value?.buyer_residency_status
  const requiredResidencyDocs = getRequiredDocumentsByResidency(residencyStatus)
  const order = Number(props.selectedStageOrder) || 0
  const docs = []
  if (requiredResidencyDocs.includes('passport')) docs.push({ id: 'passport', name: 'Buyer Passport', required: true })
  if (requiredResidencyDocs.includes('national_id')) docs.push({ id: 'national_id', name: 'Buyer Emirates ID', required: true })
  // Security Deposit appears from stage 2 (Security Deposit) onwards.
  if (order >= 2) {
    docs.push({ id: 'security_deposit', name: 'Buyer Security Deposit', required: false })
  }
  return docs
})

const sellerDocTypes = computed(() => {
  // When a listing is attached, seller = listing's owner — no doc rows, no required asterisks.
  if (shouldHideSeller.value) return []
  const residencyStatus = form.value?.seller_residency_status
  const requiredDocs = getRequiredDocumentsByResidency(residencyStatus)
  const order = Number(props.selectedStageOrder) || 0
  const allDocs = {
    passport: { id: 'passport', name: 'Seller Passport', required: true },
    national_id: { id: 'national_id', name: 'Seller Emirates ID', required: true },
    title_deed: { id: 'title_deed', name: 'Unit SPA / Title Deed', required: false },
  }
  const docs = requiredDocs.map(docType => allDocs[docType]).filter(doc => doc)
  // Security Deposit appears from stage 2 (Security Deposit) onwards.
  if (order >= 2) {
    docs.push({ id: 'security_deposit', name: 'Seller Security Deposit', required: false })
  }
  return docs
})

const tenantDocTypes = computed(() => {
  const residencyStatus = form.value?.tenant_residency_status
  const requiredDocs = getRequiredDocumentsByResidency(residencyStatus)
  const allDocs = { passport: { id: 'passport', name: 'Tenant Passport', required: true }, national_id: { id: 'national_id', name: 'Tenant Emirates ID', required: false }, kyc: { id: 'kyc', name: 'Tenant KYC', required: false }, payment_proof: { id: 'payment_proof', name: 'Tenant Proof of Payment', required: false }, ejari: { id: 'ejari', name: 'Ejari Contract', required: false }, tenancy_contract: { id: 'tenancy_contract', name: 'Tenancy Contract', required: false }, move_in_form: { id: 'move_in_form', name: 'Move In Form', required: false } }
  return requiredDocs.map(docType => allDocs[docType]).filter(doc => doc)
})

const landlordDocTypes = computed(() => {
  // When a listing is attached, landlord = listing's owner — no doc rows, no required asterisks.
  if (shouldHideLandlord.value) return []
  const residencyStatus = form.value?.landlord_residency_status
  const requiredDocs = getRequiredDocumentsByResidency(residencyStatus)
  const allDocs = { passport: { id: 'passport', name: 'Landlord Passport', required: true }, national_id: { id: 'national_id', name: 'Landlord Emirates ID', required: true }, title_deed: { id: 'title_deed', name: 'Title Deed', required: true } }
  return requiredDocs.map(docType => allDocs[docType]).filter(doc => doc)
})

// ========== City Options ==========
// ========== Improved Watchers for Residency & Country ==========
function setupResidencyAndCityWatchers() {
  const parties = ['buyer', 'seller', 'tenant', 'landlord']
  
  parties.forEach(party => {
    // مراقبة تغيير Residency Status
    watch(() => form.value[`${party}_residency_status`], (newStatus) => {
      if (newStatus === 'non_resident') {
        form.value[`${party}_city`] = null
      }
    })
    
    // مراقبة تغيير Country للسكان فقط (لتعيين مدينة تلقائية)
    watch(() => form.value[`${party}_country`], (newCountry) => {
      if (form.value[`${party}_residency_status`] === 'resident' && newCountry === 'United Arab Emirates') {
        const uaeCities = citiesByCountry['United Arab Emirates']
        if (uaeCities && uaeCities.length > 0 && !form.value[`${party}_city`]) {
          form.value[`${party}_city`] = uaeCities[0].value
        }
      }
    })
  })
}

setupResidencyAndCityWatchers()
const citiesByCountry = {
  'United Arab Emirates': [{ value: 'Abu Dhabi', text: 'Abu Dhabi' }, { value: 'Dubai', text: 'Dubai' }, { value: 'Sharjah', text: 'Sharjah' }, { value: 'Ajman', text: 'Ajman' }, { value: 'Ras Al Khaimah', text: 'Ras Al Khaimah' }, { value: 'Umm Al Quwain', text: 'Umm Al Quwain' }, { value: 'Fujairah', text: 'Fujairah' }, { value: 'Al Ain', text: 'Al Ain' }],
  'Saudi Arabia': [{ value: 'Riyadh', text: 'Riyadh' }, { value: 'Jeddah', text: 'Jeddah' }, { value: 'Mecca', text: 'Mecca' }, { value: 'Medina', text: 'Medina' }, { value: 'Dammam', text: 'Dammam' }, { value: 'Khobar', text: 'Khobar' }, { value: 'Tabuk', text: 'Tabuk' }, { value: 'Abha', text: 'Abha' }],
  'Egypt': [{ value: 'Cairo', text: 'Cairo' }, { value: 'Giza', text: 'Giza' }, { value: 'Alexandria', text: 'Alexandria' }],
  'India': [{ value: 'Mumbai', text: 'Mumbai' }, { value: 'Delhi', text: 'Delhi' }, { value: 'Bangalore', text: 'Bangalore' }],
  'Pakistan': [{ value: 'Karachi', text: 'Karachi' }, { value: 'Lahore', text: 'Lahore' }, { value: 'Islamabad', text: 'Islamabad' }],
}

const buyerCityOptions = computed(() => {
 if (form.value?.buyer_residency_status === 'resident') {
    const uaeCities = citiesByCountry['United Arab Emirates']
    return uaeCities || []
  }
})

const sellerCityOptions = computed(() => {
 if (form.value?.seller_residency_status === 'resident') {
    const uaeCities = citiesByCountry['United Arab Emirates']
    return uaeCities || []
  }
})

const tenantCityOptions = computed(() => {
 if (form.value?.tenant_residency_status === 'resident') {
    const uaeCities = citiesByCountry['United Arab Emirates']
    return uaeCities || []
  }
})

const landlordCityOptions = computed(() => {
  if (form.value?.landlord_residency_status === 'resident') {
    const uaeCities = citiesByCountry['United Arab Emirates']
    return uaeCities || []
  }
})

// ========== Functions ==========
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
   stage_dates: ['stage_dates'], 
}

// function isSectionVisible(sectionName) {
//   const active = props.activeEditSection
//   if (!active) return true
//   return (sectionAliases[sectionName] || [sectionName]).includes(active)
// }
function isSectionVisible(sectionName) {
  const active = props.activeEditSection
  console.log(`🟣 isSectionVisible(${sectionName}) - activeEditSection: "${active}"`)
  if (!active) {
    console.log('  - no active section, returns true')
    return true
  }
  const aliases = sectionAliases[sectionName] || [sectionName]
  const result = aliases.includes(active)
  console.log(`  - aliases:`, aliases)
  console.log(`  - result: ${result}`)
  return result
}

function isDocumentEditMode(documentSectionKey) {
  return props.activeEditSection === documentSectionKey
}

const updateBuyerLanguage = (value) => { form.value.buyer_language = normalizeLanguageSelection(value) }

function clearAllDocuments() {
  form.value.buyer_documents = []
  form.value.seller_documents = []
  form.value.tenant_documents = []
  form.value.landlord_documents = []
  const refs = [buyerDocUploadRef.value, sellerDocUploadRef.value, tenantDocUploadRef.value, landlordDocUploadRef.value]
  refs.forEach((r) => { if (r && typeof r.clearAllFiles === 'function') r.clearAllFiles() })
}

// ========== Validation ==========
// ========== Validation ==========
function validateForm() {
   console.log('=== DealForm.validateForm called ===')
  console.log('props.selectedStageId:', props.selectedStageId)
  console.log('form.value:', JSON.stringify(form.value, null, 2))
  const errors = []
  const fieldErrorsObj = {}
  
  if (!props.selectedStageId) { errors.push('Please select a stage for the deal'); fieldErrorsObj.stage_id = 'Stage is required' }
  if (!form.value.source) { errors.push('Source is required'); fieldErrorsObj.source = 'Source is required' }
  if (!form.value.deal_name) { errors.push('Deal name is required'); fieldErrorsObj.deal_name = 'Deal name is required' }
  if (!form.value.responsible_person_id) { errors.push('Responsible person is required'); fieldErrorsObj.responsible_person_id = 'Responsible person is required' }
  
  if (props.dealType === 'primary' || props.dealType === 'secondary') {
    if (!form.value.buyer_first_name) { errors.push('Buyer first name is required'); fieldErrorsObj.buyer_first_name = 'First name is required' }
    if (!form.value.buyer_last_name) { errors.push('Buyer last name is required'); fieldErrorsObj.buyer_last_name = 'Last name is required' }
    if (!form.value.buyer_phone) { errors.push('Buyer phone is required'); fieldErrorsObj.buyer_phone = 'Phone is required' }
    if (!form.value.buyer_email) { errors.push('Buyer email is required'); fieldErrorsObj.buyer_email = 'Email is required' }
    if (!form.value.buyer_nationality) { errors.push('Buyer nationality is required'); fieldErrorsObj.buyer_nationality = 'Nationality is required' }
    if (!form.value.buyer_dob) { errors.push('Buyer date of birth is required'); fieldErrorsObj.buyer_dob = 'Date of birth is required' }
    if (!form.value.buyer_residency_status) { errors.push('Buyer residency status is required'); fieldErrorsObj.buyer_residency_status = 'Residency status is required' }
    
    // ✅ Buyer city - مطلوب فقط للمقيمين (resident)
    if (form.value.buyer_residency_status === 'resident') {
      if (!form.value.buyer_city) {
        errors.push('Buyer city is required')
        fieldErrorsObj.buyer_city = 'City is required'
      }
    }
    
    // ✅ Buyer country - مطلوب فقط لغير المقيمين (non_resident)
    if (form.value.buyer_residency_status !== 'resident') {
      if (!form.value.buyer_country) {
        errors.push('Buyer country is required')
        fieldErrorsObj.buyer_country = 'Country is required'
      }
    }
    
    if (!hasLanguageSelection(form.value.buyer_language)) {
      errors.push('Buyer language is required')
      fieldErrorsObj.buyer_language = 'Language is required'
    }
    if (form.value.buyer_phone && !isNonEmptyPhoneValid(form.value.buyer_phone)) {
      errors.push('Buyer phone is invalid')
      fieldErrorsObj.buyer_phone = 'Invalid phone number'
    }
  }
  
  if (props.dealType === 'secondary' && !shouldHideSeller.value) {
    if (!form.value.seller_first_name) { errors.push('Seller first name is required'); fieldErrorsObj.seller_first_name = 'First name is required' }
    if (!form.value.seller_last_name) { errors.push('Seller last name is required'); fieldErrorsObj.seller_last_name = 'Last name is required' }
    if (!form.value.seller_phone) { errors.push('Seller phone is required'); fieldErrorsObj.seller_phone = 'Phone is required' }
    if (!form.value.seller_email) { errors.push('Seller email is required'); fieldErrorsObj.seller_email = 'Email is required' }
    if (!form.value.seller_nationality) { errors.push('Seller nationality is required'); fieldErrorsObj.seller_nationality = 'Nationality is required' }
    if (!form.value.seller_dob) { errors.push('Seller date of birth is required'); fieldErrorsObj.seller_dob = 'Date of birth is required' }
    if (!form.value.seller_residency_status) { errors.push('Seller residency status is required'); fieldErrorsObj.seller_residency_status = 'Residency status is required' }
    
    // ✅ Seller city - مطلوب فقط للمقيمين
    if (form.value.seller_residency_status === 'resident') {
      if (!form.value.seller_city) {
        errors.push('Seller city is required')
        fieldErrorsObj.seller_city = 'City is required'
      }
    }
    
    // ✅ Seller country - مطلوب فقط لغير المقيمين
    if (form.value.seller_residency_status !== 'resident') {
      if (!form.value.seller_country) {
        errors.push('Seller country is required')
        fieldErrorsObj.seller_country = 'Country is required'
      }
    }
    
    if (!form.value.seller_language) { errors.push('Seller language is required'); fieldErrorsObj.seller_language = 'Language is required' }
    if (form.value.seller_phone && !isNonEmptyPhoneValid(form.value.seller_phone)) {
      errors.push('Seller phone is invalid')
      fieldErrorsObj.seller_phone = 'Invalid phone number'
    }
  }
  
  if (props.dealType === 'rental') {
    if (!form.value.tenant_first_name) { errors.push('Tenant first name is required'); fieldErrorsObj.tenant_first_name = 'First name is required' }
    if (!form.value.tenant_last_name) { errors.push('Tenant last name is required'); fieldErrorsObj.tenant_last_name = 'Last name is required' }
    if (!form.value.tenant_phone) { errors.push('Tenant phone is required'); fieldErrorsObj.tenant_phone = 'Phone is required' }
    if (!form.value.tenant_email) { errors.push('Tenant email is required'); fieldErrorsObj.tenant_email = 'Email is required' }
    if (!form.value.tenant_nationality) { errors.push('Tenant nationality is required'); fieldErrorsObj.tenant_nationality = 'Nationality is required' }
    if (!form.value.tenant_residency_status) { errors.push('Tenant residency status is required'); fieldErrorsObj.tenant_residency_status = 'Residency status is required' }
    
    // ✅ Tenant city - مطلوب فقط للمقيمين
    if (form.value.tenant_residency_status === 'resident') {
      if (!form.value.tenant_city) {
        errors.push('Tenant city is required')
        fieldErrorsObj.tenant_city = 'City is required'
      }
    }
    
    // ✅ Tenant country - مطلوب فقط لغير المقيمين
    if (form.value.tenant_residency_status !== 'resident') {
      if (!form.value.tenant_country) {
        errors.push('Tenant country is required')
        fieldErrorsObj.tenant_country = 'Country is required'
      }
    }
    
    if (!form.value.tenant_language) { errors.push('Tenant language is required'); fieldErrorsObj.tenant_language = 'Language is required' }
    if (form.value.tenant_phone && !isNonEmptyPhoneValid(form.value.tenant_phone)) {
      errors.push('Tenant phone is invalid')
      fieldErrorsObj.tenant_phone = 'Invalid phone number'
    }
    
    if (!shouldHideLandlord.value) {
      if (!form.value.landlord_first_name) { errors.push('Landlord first name is required'); fieldErrorsObj.landlord_first_name = 'First name is required' }
      if (!form.value.landlord_last_name) { errors.push('Landlord last name is required'); fieldErrorsObj.landlord_last_name = 'Last name is required' }
      if (!form.value.landlord_phone) { errors.push('Landlord phone is required'); fieldErrorsObj.landlord_phone = 'Phone is required' }
      if (!form.value.landlord_email) { errors.push('Landlord email is required'); fieldErrorsObj.landlord_email = 'Email is required' }
      if (!form.value.landlord_nationality) { errors.push('Landlord nationality is required'); fieldErrorsObj.landlord_nationality = 'Nationality is required' }
      if (!form.value.landlord_dob) { errors.push('Landlord date of birth is required'); fieldErrorsObj.landlord_dob = 'Date of birth is required' }
      if (!form.value.landlord_residency_status) { errors.push('Landlord residency status is required'); fieldErrorsObj.landlord_residency_status = 'Residency status is required' }
      
      // ✅ Landlord city - مطلوب فقط للمقيمين
      if (form.value.landlord_residency_status === 'resident') {
        if (!form.value.landlord_city) {
          errors.push('Landlord city is required')
          fieldErrorsObj.landlord_city = 'City is required'
        }
      }
      
      // ✅ Landlord country - مطلوب فقط لغير المقيمين
      if (form.value.landlord_residency_status !== 'resident') {
        if (!form.value.landlord_country) {
          errors.push('Landlord country is required')
          fieldErrorsObj.landlord_country = 'Country is required'
        }
      }
      
      if (!form.value.landlord_language) { errors.push('Landlord language is required'); fieldErrorsObj.landlord_language = 'Language is required' }
      if (form.value.landlord_phone && !isNonEmptyPhoneValid(form.value.landlord_phone)) {
        errors.push('Landlord phone is invalid')
        fieldErrorsObj.landlord_phone = 'Invalid phone number'
      }
    }
  }
  // ========== Secondary: listing_id + purchase_price mandatory for every property ==========
  // The property must be tied to a sold-out listing, and the user must enter a purchase price
  // (the only manual figure on a secondary deal — everything else comes from the listing).
  if (props.dealType === 'secondary') {
    if (showMultiProperties.value && propertiesList.value.length > 0) {
      propertiesList.value.forEach((property, idx) => {
        if (!property?.listing_id) {
          errors.push(`Property ${idx + 1}: Please select a sold unit`)
          fieldErrorsObj[`property_${idx}_listing_id`] = 'Please select a sold unit'
        }
        if (!property?.purchase_price) {
          errors.push(`Property ${idx + 1}: Purchase price is required`)
          fieldErrorsObj[`property_${idx}_purchase_price`] = 'Purchase price is required'
        }
      })
    } else {
      if (!form.value?.listing_id) {
        errors.push('Please select a sold unit for the property')
        fieldErrorsObj.listing_id = 'Please select a sold unit'
      }
      if (!form.value?.purchase_price) {
        errors.push('Purchase price is required')
        fieldErrorsObj.purchase_price = 'Purchase price is required'
      }
    }
  }

  // ========== Property field validation (primary + secondary) ==========
  // Stage 2 (EOI/Security Deposit): area, property type, unit no required.
  // Stage 3+ (Booking/MOU and beyond): + bedrooms, unit_size, purchase_price.
  if (props.dealType === 'primary' || props.dealType === 'secondary') {
    const stageOrder = Number(props.selectedStageOrder) || 0
    const baseRequired = ['area_id', 'property_type_id', 'unit_no']
    const stage3Plus = ['bedrooms', 'unit_size', 'purchase_price']
    const required = stageOrder >= 3 ? [...baseRequired, ...stage3Plus] : baseRequired

    const labelFor = (f) => ({
      area_id: 'Property address',
      property_type_id: 'Property type',
      unit_no: 'Unit no',
      bedrooms: 'Bedrooms',
      unit_size: 'Unit size',
      purchase_price: 'Purchase price',
    }[f] || f)

    const hasValue = (v) => v !== null && v !== undefined && String(v).trim() !== ''

    if (showMultiProperties.value && propertiesList.value.length > 0) {
      propertiesList.value.forEach((property, idx) => {
        required.forEach((field) => {
          if (!hasValue(property?.[field])) {
            errors.push(`Property ${idx + 1}: ${labelFor(field)} is required`)
            fieldErrorsObj[`property_${idx}_${field}`] = `${labelFor(field)} is required`
          }
        })
      })
    } else {
      required.forEach((field) => {
        if (!hasValue(form.value?.[field])) {
          errors.push(`${labelFor(field)} is required`)
          fieldErrorsObj[field] = `${labelFor(field)} is required`
        }
      })
    }
  }

   if (showMultiProperties.value && propertiesList.value.length > 0) {
    // في حالة الـ multi properties
    propertiesList.value.forEach((property, idx) => {
      const devPhone = property.developer_phone
      if (devPhone && String(devPhone).trim() !== '' && !isNonEmptyPhoneValid(devPhone)) {
        errors.push(`Property ${idx + 1}: Developer phone is invalid`)
        fieldErrorsObj[`property_${idx}_developer_phone`] = 'Invalid phone number'
      }
    })
  } else {
    // في حالة الـ single property mode
    const devPhone = form.value.developer_phone
    if (devPhone && String(devPhone).trim() !== '' && !isNonEmptyPhoneValid(devPhone)) {
      errors.push('Developer phone is invalid')
      fieldErrorsObj.developer_phone = 'Invalid phone number'
    }
  }

  // ========== MOU / NOC property-doc enforcement (secondary) ==========
  // MOU required at MOU stage (order >= 3); NOC also required at NOC stage (order >= 4).
  if (props.dealType === 'secondary') {
    const stageOrder = Number(props.selectedStageOrder) || 0
    const stageName = String(props.selectedStageName || '').toLowerCase()
    const mouRequired = stageOrder >= 3 || stageName.includes('mou') || stageName.includes('noc') || stageName.includes('won')
    const nocRequired = stageOrder >= 4 || stageName.includes('noc') || stageName.includes('won')

    const hasDocs = (arr) => Array.isArray(arr) && arr.some((d) => d && (d.file || d.url || d.file_url || d.path || d.is_existing))

    if (showMultiProperties.value && propertiesList.value.length > 0) {
      propertiesList.value.forEach((property, idx) => {
        if (mouRequired && !hasDocs(property?.mou_documents)) {
          errors.push(`Property ${idx + 1}: MOU document is required`)
          fieldErrorsObj[`property_${idx}_document_mou`] = 'MOU document is required'
        }
        if (nocRequired && !hasDocs(property?.noc_documents)) {
          errors.push(`Property ${idx + 1}: NOC document is required`)
          fieldErrorsObj[`property_${idx}_document_noc`] = 'NOC document is required'
        }
      })
    } else {
      if (mouRequired && !hasDocs(form.value?.mou_documents)) {
        errors.push('MOU document is required')
        fieldErrorsObj.property_document_mou = 'MOU document is required'
      }
      if (nocRequired && !hasDocs(form.value?.noc_documents)) {
        errors.push('NOC document is required')
        fieldErrorsObj.property_document_noc = 'NOC document is required'
      }
    }
  }
 
  
    console.log('Final errors:', errors)
  return { errors, fieldErrorsObj }
}
// ========== Listings Functions ==========
const getCurrentUser = () => {
  try { const userData = localStorage.getItem('user'); if (userData) currentUser.value = JSON.parse(userData) } 
  catch (error) { console.error('Error getting user:', error) }
}

const fetchAvailableListings = async (areaId) => {
  if (!areaId) { availableListings.value = []; return }
  if (!currentUser.value?.id) { getCurrentUser(); if (!currentUser.value?.id) return }
  try {
    isLoadingListings.value = true
    const params = buildListingFilterParams({ dealType: props.dealType, areaId, user: currentUser.value })
    const response = await api.get('/listings/properties', { params })
    const listings = response.data.data || []
    availableListings.value = listings.map(listing => ({
      id: listing.id, unit_number: listing.unit_number, property_type: listing.property_type,
      property_type_id: listing.property_type_id, bedrooms: listing.number_of_bedrooms,
      bedrooms_text: listing.number_of_bedrooms === 0 ? 'Studio' : `${listing.number_of_bedrooms} Bed`,
      size_sqft: listing.size_sqft, developer_id: listing.developer_id, status: listing.status,
      display_name: `${listing.unit_number || 'No Unit'} - ${listing.property_type?.name || 'Property'}`
    }))
  } catch (error) { console.error('Error fetching listings:', error) } 
  finally { isLoadingListings.value = false }
}

const onAreaSelected = (areaId) => {
  selectedListing.value = null
  if (form.value) {
    form.value.unit_no = ''; form.value.property_type_id = null; form.value.bedrooms = null
    form.value.unit_size = ''; form.value.developer_id = null
  }
   const selectedArea = props.areas.find(a => a.id === areaId)

    if (!selectedArea) return

    form.value.area_id = areaId

    // 👇 أهم جزء
    if (selectedArea.project?.developer_id) {
        form.value.developer_id = selectedArea.project.developer_id
    } else if (selectedArea.developer_id) {
        form.value.developer_id = selectedArea.developer_id
    } else {
        form.value.developer_id = null
    }
  if (props.dealType === 'secondary' || props.dealType === 'rental') fetchAvailableListings(areaId)
  else availableListings.value = []
}

const onListingSelected = (listing) => {
  // hasListingId is computed off form.listing_id — just set/clear it and the flag follows.
  if (!listing) { form.value.listing_id = null; return }
  form.value.unit_no = listing.unit_number || ''
  form.value.property_type_id = listing.property_type_id
  form.value.bedrooms = listing.bedrooms === 0 ? 'studio' : String(listing.bedrooms)
  form.value.unit_size = listing.size_sqft || ''
  form.value.developer_id = listing.developer_id
  form.value.listing_id = listing.id
}

// ========== Multi Properties Functions ==========
const checkShowMultiProperties = () => {
  const dt = props.dealType
  const order = Number(props.selectedStageOrder) || 0
  const stageName = props.selectedStageName?.toLowerCase() || ''
  const stagesWithMultiProps = ['eoi', 'booking', 'mou', 'spa', 'won', 'deal won']
  const orderMatch = (dt === 'primary' || dt === 'secondary') && order >= 2
  const nameMatch = stagesWithMultiProps.some(stage => stageName.includes(stage))
  const shouldShow = orderMatch || nameMatch
  showMultiProperties.value = shouldShow
  if (shouldShow && propertiesList.value.length === 0) initPropertiesFromForm()
}

const initPropertiesFromForm = () => {
  const firstProperty = {
    id: form.value.property_id || Date.now(), 
    sort_order: 0, 
    unit_no: form.value.unit_no || '', 
    property_type_id: form.value.property_type_id || null,
    bedrooms: form.value.bedrooms || null, 
    unit_size: form.value.unit_size || '', 
    area_id: form.value.area_id || null,
    developer_id: form.value.developer_id || null, 
    developer_name: form.value.developer_name || '',
    developer_phone: form.value.developer_phone || '', 
    budget_from: form.value.budget_from || null,
    budget_to: form.value.budget_to || null, 
    purchase_price: form.value.purchase_price || null,   
    commission: null, 
    payment_proof: Array.isArray(form.value.payment_proof) ? [...form.value.payment_proof] : [],
    spa_document: Array.isArray(form.value.spa_document) ? [...form.value.spa_document] : [],
    // ✅ إضافة المستندات الجديدة
    eoi_documents: Array.isArray(form.value.eoi_documents) ? [...form.value.eoi_documents] : [],
    booking_documents: Array.isArray(form.value.booking_documents) ? [...form.value.booking_documents] : [],
    mou_documents: Array.isArray(form.value.mou_documents) ? [...form.value.mou_documents] : [],
    noc_documents: Array.isArray(form.value.noc_documents) ? [...form.value.noc_documents] : [],
    listing_id: form.value.listing_id || null
  }
  propertiesList.value = [firstProperty]
}

const addNewProperty = () => {
  if (propertyListRef.value?.addProperty) {
    propertyListRef.value.addProperty(true)
    return
  }
  propertiesList.value.push({
    id: Date.now() + Math.random(), 
    sort_order: propertiesList.value.length, 
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
    spa_document: [],
    // ✅ إضافة المستندات الجديدة
    eoi_documents: [],
    booking_documents: [],
    mou_documents: [],
    noc_documents: [],
    listing_id: null
  })
}

const getPropertiesData = () => {
  console.log('getPropertiesData called, showMultiProperties:', showMultiProperties.value)
  console.log('propertiesList:', propertiesList.value)
  
  if (showMultiProperties.value && propertiesList.value.length > 0) {
    const dataToSend = propertiesList.value.map((prop, index) => {
      // استخراج الملفات الفعلية فقط
      let eoiFiles = []
      let bookingFiles = []
      let mouFiles = []
      let nocFiles = []
      let paymentProofFiles = []
      let spaDocumentFiles = []

      // ✅ EOI Documents
      if (prop.eoi_documents && Array.isArray(prop.eoi_documents)) {
        eoiFiles = prop.eoi_documents
          .filter(doc => doc && doc.file instanceof File)
          .map(doc => doc.file)
      }

      // ✅ Booking Documents
      if (prop.booking_documents && Array.isArray(prop.booking_documents)) {
        bookingFiles = prop.booking_documents
          .filter(doc => doc && doc.file instanceof File)
          .map(doc => doc.file)
      }

      // ✅ MOU Documents
      if (prop.mou_documents && Array.isArray(prop.mou_documents)) {
        mouFiles = prop.mou_documents
          .filter(doc => doc && doc.file instanceof File)
          .map(doc => doc.file)
      }

      // ✅ NOC Documents
      if (prop.noc_documents && Array.isArray(prop.noc_documents)) {
        nocFiles = prop.noc_documents
          .filter(doc => doc && doc.file instanceof File)
          .map(doc => doc.file)
      }

      // Payment Proof
      if (prop.payment_proof && Array.isArray(prop.payment_proof)) {
        paymentProofFiles = prop.payment_proof
          .filter(doc => doc && doc.file instanceof File)
          .map(doc => doc.file)
      }

      // SPA Document
      if (prop.spa_document && Array.isArray(prop.spa_document)) {
        spaDocumentFiles = prop.spa_document
          .filter(doc => doc && doc.file instanceof File)
          .map(doc => doc.file)
      }

      return {
        sort_order: index,
        unit_no: prop.unit_no || '',
        property_type_id: prop.property_type_id || null,
        bedrooms: prop.bedrooms || null,
        unit_size: prop.unit_size || '',
        area_id: prop.area_id || null,
        project_id: prop.project_id || null,
        // Linked listing (secondary / rental). Backend stores it on deal_properties.listing_id.
        listing_id: prop.listing_id || null,
        developer_id: prop.developer_id || null,
        developer_name: prop.developer_name || '',
        developer_phone: prop.developer_phone || '',
        budget_from: prop.budget_from || null,
        budget_to: prop.budget_to || null,
        purchase_price: prop.purchase_price || null,
        commission: prop.commission || null,
        rental_price: prop.rental_price || null,
        // ✅ إضافة جميع المستندات
        eoi_documents: eoiFiles,
        booking_documents: bookingFiles,
        mou_documents: mouFiles,
        noc_documents: nocFiles,
        payment_proof: paymentProofFiles,
        spa_document: spaDocumentFiles,
      }
    })
    
    console.log('Properties data to send:', dataToSend)
    return dataToSend
  }
  return null
}

// ========== Watchers ==========
// Notify parent when hasListingId flips. Source of truth is the computed above, which is
// derived from form.listing_id — set when a listing is chosen (only after picking an area
// that has listings) and cleared when the listing is removed.
watch(hasListingId, (val) => emit('update:hasListingId', val), { immediate: true })
watch(() => props.selectedStageName, () => checkShowMultiProperties(), { immediate: true })
watch(() => props.selectedStageOrder, () => checkShowMultiProperties())
watch(() => props.dealType, () => checkShowMultiProperties())
watch(() => shouldHideSeller.value, (hide) => {
  if (hide) { form.value.seller_first_name = ''; form.value.seller_last_name = ''; form.value.seller_dob = ''; form.value.seller_phone = ''; form.value.seller_email = ''; form.value.seller_nationality = ''; form.value.seller_residency_status = ''; form.value.seller_city = ''; form.value.seller_country = ''; form.value.seller_language = ''; form.value.seller_documents = [] }
})
watch(() => shouldHideLandlord.value, (hide) => {
  if (hide) { form.value.landlord_first_name = ''; form.value.landlord_last_name = ''; form.value.landlord_dob = ''; form.value.landlord_phone = ''; form.value.landlord_email = ''; form.value.landlord_nationality = ''; form.value.landlord_residency_status = ''; form.value.landlord_city = ''; form.value.landlord_country = ''; form.value.landlord_language = ''; form.value.landlord_documents = [] }
})
watch(() => form.value.property_type_id, (newTypeId) => {
  if (!showBedroomsFieldInProperty.value) {
    form.value.bedrooms = null
  }
})
// ========== Expose ==========
defineExpose({ clearAllDocuments, validateForm, getPropertiesData, propertiesList })

// ========== Options ==========


const nationalityOptions = Object.entries(
  countries.getNames("en", { select: "official" })
).map(([code, name]) => ({
  value: code.toLowerCase(), // eg
  text: name, // Egypt
  code: code.toLowerCase()
}));

const residencyOptions = [{ value: 'resident', text: 'Resident' }, { value: 'non_resident', text: 'Non Resident' }]
const buyerResidencyOptions = [{ value: 'resident', text: 'Resident' }, { value: 'non_resident', text: 'Non Resident' }]

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

const bedroomOptions = [
  { value: 'studio', text: 'Studio' }, { value: '1', text: '1 Bedroom' }, { value: '2', text: '2 Bedrooms' },
  { value: '3', text: '3 Bedrooms' }, { value: '4', text: '4 Bedrooms' }, { value: '5', text: '5 Bedrooms' }
]

getCurrentUser()
const showPropertyCommission = computed(() => {
  const stageName = props.selectedStageName?.toLowerCase() || ''
  return stageName.includes('won') || stageName.includes('deal won')
})
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
     emit('update:areas', areasData)
   
    
    console.log(`Loaded ${props.areas.length} areas`)
  } catch (error) {
    console.error('Error loading areas:', error)
  }
}
// أضف هذا مع الـ computed الموجودة (بعد showPropertyCommission مثلاً)
const showBedroomsFieldInProperty = computed(() => {
  const propertyTypeId = form.value.property_type_id
  if (!propertyTypeId) return true
  
  const selectedType = props.propertyTypes.find(t => t.id === propertyTypeId)
  const typeName = selectedType?.name?.toLowerCase() || ''
  
  if (typeName.includes('land') || typeName.includes('plot')) {
     form.value.bedrooms = null
    return false
  }
  
  return true
})

const stageDates = computed(() => {
  console.log('🔵🔵🔵 DealForm.stageDates - START 🔵🔵🔵')
  console.log('1. props.dealType:', props.dealType)
  console.log('2. props.activeEditSection:', props.activeEditSection)
  console.log('3. form.value (props.modelValue):', JSON.stringify(form.value, null, 2))
  console.log('4. form.value.eoi_date:', form.value.eoi_date)
  console.log('5. form.value.booking_date:', form.value.booking_date)
  console.log('6. form.value.spa_date:', form.value.spa_date)
  
  const dealType = props.dealType || 'primary'
  const config = stageDateConfig[dealType] || stageDateConfig.primary
  
  console.log('7. config:', config)
  
  const dates = []
  
  config.forEach(field => {
    const value = form.value[field.key]
    console.log(`8. field ${field.key}:`, value)
    if (value) {
      dates.push({
        ...field,
        value: formatDateDisplay(value),
        class: stageDateColors[field.key] || ''
      })
    }
  })
  
  console.log('9. final dates:', dates)
  console.log('🔵🔵🔵 DealForm.stageDates - END 🔵🔵🔵')
  return dates
})

function shouldShowStageDate(dateKey) {
  const stageDatesByDealType = {
    primary: ['eoi_date', 'booking_date', 'spa_date', 'won_date'],
    secondary: ['security_deposit_date', 'mou_date', 'noc_date', 'won_date'],
    rental: ['application_date', 'contract_date', 'ejari_date', 'won_date'],
  }

  // Inline Stage Dates edit: always show all date fields for this deal type
  if (props.activeEditSection === 'stage_dates') {
    const allowed = stageDatesByDealType[props.dealType] || stageDatesByDealType.primary
    return allowed.includes(dateKey)
  }

  if (form.value[dateKey]) {
    return true
  }

  const stageName = props.selectedStageName?.toLowerCase() || ''
  const order = Number(props.selectedStageOrder) || 0

  const stageDateMap = {
    'eoi_date': { stages: ['eoi'], minOrder: 2 },
    'booking_date': { stages: ['booking'], minOrder: 3 },
    'spa_date': { stages: ['spa'], minOrder: 4 },
    'security_deposit_date': { stages: ['security', 'deposit'], minOrder: 2 },
    'mou_date': { stages: ['mou'], minOrder: 3 },
    'noc_date': { stages: ['noc'], minOrder: 4 },
    'application_date': { stages: ['application'], minOrder: 2 },
    'contract_date': { stages: ['contract'], minOrder: 3 },
    'ejari_date': { stages: ['ejari'], minOrder: 4 },
    'won_date': { stages: ['won', 'deal won', 'closed', 'completed'], minOrder: 5 }
  }

  const config = stageDateMap[dateKey]
  if (!config) {
    return false
  }

  const stageMatch = config.stages.some(s => stageName.includes(s))
  if (stageMatch) {
    return true
  }

  return order >= config.minOrder
}
const stageDateConfig = {
  primary: [
    { key: 'eoi_date', label: 'EOI Date', icon: 'lucide:file-text', order: 2 },
    { key: 'booking_date', label: 'Booking Date', icon: 'lucide:calendar-check', order: 3 },
    { key: 'spa_date', label: 'SPA Date', icon: 'lucide:file-signature', order: 4 },
    { key: 'won_date', label: 'Won Date', icon: 'lucide:trophy', order: 5 }
  ],
  secondary: [
    { key: 'security_deposit_date', label: 'Security Deposit Date', icon: 'lucide:shield-check', order: 2 },
    { key: 'mou_date', label: 'MOU Date', icon: 'lucide:file-check', order: 3 },
    { key: 'noc_date', label: 'NOC Date', icon: 'lucide:file-check-2', order: 4 },
    { key: 'won_date', label: 'Won Date', icon: 'lucide:trophy', order: 5 }
  ],
  rental: [
    { key: 'application_date', label: 'Application Date', icon: 'lucide:file-text', order: 2 },
    { key: 'contract_date', label: 'Contract Date', icon: 'lucide:file-signature', order: 3 },
    { key: 'ejari_date', label: 'Ejari Date', icon: 'lucide:file-check', order: 4 },
    { key: 'won_date', label: 'Won Date', icon: 'lucide:trophy', order: 5 }
  ]
}

const stageDateColors = {
  'eoi_date': 'date-eoi',
  'booking_date': 'date-booking',
  'spa_date': 'date-spa',
  'security_deposit_date': 'date-security',
  'mou_date': 'date-mou',
  'noc_date': 'date-noc',
  'won_date': 'date-won',
  'application_date': 'date-application',
  'contract_date': 'date-contract',
  'ejari_date': 'date-ejari'
}

function formatDateDisplay(dateValue) {
  if (!dateValue) return '—'
  try {
    const date = new Date(dateValue)
    if (isNaN(date.getTime())) return '—'
    return date.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    })
  } catch {
    return '—'
  }
}
onMounted(async () => {
  // fetchProjects()
  getCurrentUser()
    fetchAllAreas()

   document.addEventListener('click', onDocumentClick)

  // Hydrate single-property listing picker when the form is pre-populated with a listing
  // (edit flow via InlineSectionEditor).
  if (form.value?.area_id && form.value?.listing_id &&
      (props.dealType === 'secondary' || props.dealType === 'rental')) {
    await fetchAvailableListings(form.value.area_id)
    const match = availableListings.value.find(l => l.id === form.value.listing_id)
    if (match) selectedListing.value = match
  }
})
onBeforeUnmount(() => {
document.removeEventListener('click', onDocumentClick)
removeBudgetDropdownListeners()

})
</script>

<style scoped>
/* Figma deal forms — Inter, 16px sections, 12px labels, 14px inputs */
.section-title { font-size: 16px !important; font-weight: 600; color: var(--deal-navy-deep, #0B0736); font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif); margin-bottom: 10px; letter-spacing: -0.02em; line-height: 1.35; }
.form-card { background: #fff; border: 1px solid #e5e7eb; box-shadow: none; padding: 0.875rem 1rem !important; }
.radius-12 { border-radius: 8px; }
.listing-summary-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; }
.listing-summary-title { font-size: 13px; font-weight: 600; color: #0B0736; margin-bottom: 8px; display: flex; align-items: center; }
.listing-summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 8px 16px; }
.listing-summary-grid > div { display: flex; flex-direction: column; }
.listing-summary-label { font-size: 11px; color: #64748b; font-weight: 500; }
.listing-summary-value { font-size: 13px; color: #0f172a; font-weight: 600; }
.form-label-custom { font-size: 12px !important; font-weight: 500; color: var(--deal-text-muted, #64748b); margin-bottom: 4px; display: block; font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif); }
.custom-input { height: 42px !important; min-height: 42px; border-radius: 8px !important; border: 1px solid #e5e7eb !important; font-size: 13px !important; font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif); }
.custom-input::placeholder { font-size: 10px !important; color: #9ca3af; font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif); }
.custom-input.is-invalid { border-color: #dc3545 !important; }
.input-group-custom { display: flex; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
.input-group-custom .custom-input { border: none !important; flex: 1; border-radius: 8px 0 0 8px !important; }
:deep(.custom-v-select) { font-size: 13px; }
:deep(.custom-v-select .vs__dropdown-toggle) { height: 42px !important; min-height: 42px; border-radius: 8px; border: 1px solid #e5e7eb; font-size: 13px; padding: 0 8px; overflow: hidden; display: flex !important; align-items: stretch !important; }
:deep(.custom-v-select.is-invalid .vs__dropdown-toggle) { border-color: #dc3545 !important; }
:deep(.custom-v-select .vs__selected), :deep(.custom-v-select .vs__search) { font-size: 13px; }
:deep(.custom-v-select .vs__search::placeholder) { font-size: 10px !important; color: #9ca3af; }
:deep(.custom-v-select .vs__placeholder) { font-size: 10px !important; color: #9ca3af; }
:deep(.buyer-language-select .vs__selected) {     height: 26px !important;background: #dbeafe; color: #1d4ed8; border-color: #bfdbfe; margin:5px !important}
:deep(.buyer-language-select .vs__dropdown-option--highlight) { background: #eff6ff; color: #1e3a8a; }
:deep(.buyer-language-select .vs__dropdown-option--selected) { background: #dbeafe; color: #1d4ed8; font-weight: 600; }
:deep(.custom-v-select-inline) { min-width: 120px; }
:deep(.custom-v-select-inline .vs__dropdown-toggle) { height: 42px !important; min-height: 42px; border: none; border-left: 1px solid #e5e7eb; border-radius: 0 8px 8px 0; font-size: 11px; }
:deep(.custom-v-select-inline .vs__selected) { font-size: 11px; font-weight: 500; color: #64748b; }
:deep(.custom-v-select-inline .vs__search::placeholder) { font-size: 9px !important; color: #9ca3af; }
:deep(.custom-v-select-inline .vs__placeholder) { font-size: 9px !important; color: #9ca3af; }
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
  flex-direction: row;
  gap: 9px !important;
}
.inline-mode :deep(.row.g-3 > [class*='col-']) {
  width: 49% !important;
  max-width: 100% !important;
}

.inline-mode .form-section:has(.advanced-date-picker) :deep(.row.g-3 > [class*='col-']) {
  width: 100% !important;
  flex: 0 0 100% !important;
}
:deep(.custom-v-select .vs__open-indicator-icon) {
    font-size: 13px;
    color: #cfdbec;
}

:deep(.custom-v-select svg) {
    vertical-align: middle !important;
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
    .document-upload-container.is-compact .all-boxes-grid{
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

:deep(.vs__open-indicator) {
  color: #94a3b8 !important;
      /* margin-bottom: 10px; */
}

:deep(.vs__deselect) {
  border: none !important;
  box-shadow: none !important;
}
:deep(.custom-v-select.vs--single .vs__selected) {
  text-align: left !important;
  font-size: 13px;
  padding-left: 8px;
  margin: 0 !important;
  align-self: stretch !important;
  height: 100% !important;
  display: flex !important;
  align-items: center !important;
}

:deep(.custom-v-select .vs__search::placeholder),
:deep(.custom-v-select .vs__placeholder) {
  text-align: left !important;
  font-size: 12px;
  color: #9ca3af;
}

:deep(.custom-v-select .vs__dropdown-menu) {
  overflow-y: auto;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

:deep(.custom-v-select .vs__clear) {
  fill: #94a3b8;
  padding: 4px;
  cursor: pointer;
}


:deep(.custom-v-select .vs__clear svg) {
  display: none !important;
}

:deep(.custom-v-select .vs__clear) {
  background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="%2394a3b8" stroke-width="1.5"><path d="M18 6L6 18M6 6l12 12"/></svg>') !important;
  background-repeat: no-repeat !important;
  background-position: center !important;
  background-size: 14px !important;
  width: 24px !important;
  height: 24px !important;
}
:deep(.custom-v-select .vs__deselect svg) {
  display: none !important;
}

:deep(.custom-v-select .vs__deselect) {
  background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="%2394a3b8" stroke-width="1.5"><path d="M18 6L6 18M6 6l12 12"/></svg>') !important;
  background-repeat: no-repeat !important;
  background-position: center !important;
  background-size: 14px !important;
  width: 24px !important;
  height: 24px !important;
}
.custom-remove-icon {
  display: flex;
  align-items: center;
  cursor: pointer;
  color: #94a3b8; 
  font-size: 12px;
}


.btn-add-property {
  background: transparent;
  border: 1px solid #0B0736;
  border-radius: 100px;
  padding: 8px 20px;
  font-size: 13px;
  font-weight: 500;
  color: #0B0736;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  transition: all 0.2s;
}

.btn-add-property:hover {
  background: #0B0736;
  color: #fff;
}
/* Budget Dropdown Styles - نفس نظام Lead Search */
.budget-field-wrap {
    position: relative;
}

.budget-dropdown--portal {
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

.is-invalid-group .custom-date-trigger {
    border-color: #dc3545 !important;
}
:deep(.custom-date-trigger span){

    font-size:11px !important;
}
:deep(.custom-input::placeholder) {
  color: #94a3b8 !important;
  font-size: 11px !important;
  line-height: 38px !important;

}
</style>
<style>
.advanced-date-trigger{
  border:none !important;
}
.custom-date-trigger span{
    font-size:11px !important;
}
.custom-input::placeholder {
  color: #94a3b8 !important;
  font-size: 11px !important;
  line-height: 38px !important;
}
/* ✅ Stage Dates Display */
.stage-date-display {
  padding: 12px 14px;
  border-radius: 8px;
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  transition: all 0.2s;
  height: 100%;
}

.stage-date-display:hover {
  border-color: #cbd5e1;
  background: #f1f5f9;
}

.stage-date-label {
  font-size: 11px;
  font-weight: 500;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  display: block;
  margin-bottom: 4px;
}

.stage-date-value {
  font-size: 14px;
  font-weight: 600;
  color: #0B0736;
  display: flex;
  align-items: center;
}

/* Stage Date Colors */
.date-eoi .stage-date-label { color: #0369a1; }
.date-eoi .stage-date-value { color: #0369a1; }
.date-booking .stage-date-label { color: #b45309; }
.date-booking .stage-date-value { color: #b45309; }
.date-spa .stage-date-label { color: #1d4ed8; }
.date-spa .stage-date-value { color: #1d4ed8; }
.date-security .stage-date-label { color: #be185d; }
.date-security .stage-date-value { color: #be185d; }
.date-mou .stage-date-label { color: #065f46; }
.date-mou .stage-date-value { color: #065f46; }
.date-noc .stage-date-label { color: #4338ca; }
.date-noc .stage-date-value { color: #4338ca; }
.date-won .stage-date-label { color: #166534; }
.date-won .stage-date-value { color: #166534; }

@media (max-width: 768px) {
  .stage-date-display { padding: 10px 12px; }
  .stage-date-value { font-size: 13px; }
}</style>