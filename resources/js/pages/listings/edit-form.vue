<template>
  <div class="mt-3">
    <!-- Loading State -->
    <div v-if="isLoading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <p class="mt-2 text-muted">Loading property data...</p>
    </div>

    <!-- Main Form -->
    <div v-else class="row gy-4">
      <!-- 🏡 Property Details -->
      <div class="col-lg-12">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="card-title mb-0">Property Details</h6>
            <span class="badge bg-info">Edit Mode</span>
          </div>
          <div class="card-body">
            <div class="row gy-3">
              <!-- Sale or Rent -->
              <div class="col-md-4">
                <label class="form-label">Sale or Rent</label>
                <v-select 
                  v-model="form.saleOrRent" 
                  :options="saleRentOptions" 
                  placeholder="Select Sale or Rent"
                />
              </div>
                <div class="col-md-4">
                  <label class="form-label">Hot Deal</label>
                  <v-select 
                    v-model="form.is_hot_deal" 
                    :options="hotDealOptions" 
                    placeholder="Select Hot Deal"
                  />
                </div>
           
              <!-- Rented Status -->
              <!--<div class="col-md-4">-->
              <!--  <label class="form-label">Rented Status</label>-->
              <!--  <v-select -->
              <!--    v-model="form.rented_status" -->
              <!--    :options="rentedStatusOptions" -->
              <!--    placeholder="Select Rented Status"-->
              <!--  />-->
            
              <!--</div>-->

              <!-- Rented Until -->
              <!--<div class="col-md-4" v-if="form.rented_status === 'Rented'">-->
              <!--  <label class="form-label">Rented Until</label>-->
              <!--  <input -->
              <!--    v-model="form.rented_until" -->
              <!--    type="date" -->
              <!--    class="form-control" -->
              <!--    placeholder="Select date"-->
              <!--  />-->
              <!--</div>-->

              <!-- Property Type -->
              <div class="col-md-4">
                <label class="form-label">Property Type</label>
                <v-select 
                  v-model="form.property_type" 
                  :options="propertyTypes" 
                  label="name" 
                  placeholder="Select Property Type"
                  :disabled="isLoadingPropertyTypes"
                />
                <div v-if="isLoadingPropertyTypes" class="text-muted small mt-1">Loading property types...</div>
              </div>

              <!-- Completion Status -->
              <div class="col-md-4">
                <label class="form-label">Completion Status</label>
                <v-select 
                  v-model="form.completionStatus" 
                  :options="completionStatusOptions" 
                  placeholder="Select Completion Status"
                />
              </div>

              <!-- Project Selection -->
              <div class="col-md-4">
                <label class="form-label">Project 
                  <span v-if="!selectedProject" class="text-danger">*</span>
                </label>
                <v-select 
                  v-model="selectedProject" 
                  :options="projects" 
                  label="name" 
                  placeholder="Select Project"
                  :disabled="isLoadingProjects"
                  :clearable="true"
                >
                  <template #option="{ name, area }">
                    <div class="d-flex flex-column">
                      <strong>{{ name }}</strong>
                      <small class="text-muted" v-if="area">
                        {{ area.name || area.area_parents_title }}
                      </small>
                    </div>
                  </template>
                </v-select>
                <div v-if="isLoadingProjects" class="text-muted small mt-1">Loading projects...</div>
              </div>

              <!-- Area (Conditional) -->
              <div class="col-md-4">
                <label class="form-label">Address 
                  <span v-if="!selectedProject" class="text-danger">*</span>
                </label>
                <v-select
                  v-model="form.area"
                  :options="filteredAreas"
                  label="name"
                  :placeholder="getAreaPlaceholder()"
                  :disabled="isLoadingAreas"
                  :class="{ 'project-areas': selectedProject && form.projectAreas.length > 0 }"
                  :key="areaSelectKey"
                />
                <span v-if="!form.area?.name"> {{form.old_area}}</span>
                <div v-if="isLoadingAreas" class="text-muted small mt-1">Loading areas...</div>
                
                <div v-if="selectedProject && form.projectAreas.length === 0" class="text-warning small mt-1">
                  <i class="fas fa-exclamation-circle"></i>
                  No specific areas found for this project. Please select from general areas.
                </div>
              </div>
              
              <!-- Unit Number -->
              <div class="col-md-4" v-if="canShowOwnerSection">
                <label class="form-label">Unit Number</label>
                <input v-model="form.unit_number" type="text" class="form-control" placeholder="Enter unit number" />
              </div>

            </div>
          </div>
        </div>
      </div>

      <!-- 🏠 Unit Specifications -->
      <div class="col-lg-12">
        <div class="card">
          <div class="card-header">
            <h6 class="card-title mb-0">Unit Specifications</h6>
          </div>
          <div class="card-body">
            <div class="row gy-3">
              <!-- Bedrooms -->
              <div class="col-md-3" v-if="!isPlotOrLand">
                <label class="form-label">Number of Bedrooms</label>
                <v-select
                  v-model="form.number_of_bedrooms"
                  :options="bedroomOptions"
                  placeholder="Select bedrooms"
                  :reduce="option => option.value"
                  label="label"
                />
              </div>

              <!-- Bathrooms -->
              <div class="col-md-3" v-if="!isPlotOrLand">
                <label class="form-label">Number of Bathrooms</label>
                <v-select
                  v-model="form.number_of_bathrooms"
                  :options="bathroomOptions"
                  placeholder="Select bathrooms"
                  :reduce="option => option.value"
                  label="label"
                />
              </div>
           
              <!-- Size sqm -->
              <div class="col-md-3">
                <label class="form-label">Size (sqm)</label>
                <input
                  v-model.number="form.size_sqmt" 
                  placeholder="Enter Size (sqm)"
                  type="number"
                  class="form-control"
                  @blur="convertSqmToSqft"
                @keydown="preventNumberInvalidKeys"
                />
              </div>

              <!-- Size sqft -->
              <div class="col-md-3">
                <label class="form-label">Size (sqft)</label>
                <input
                  v-model.number="form.size_sqft"
                  type="number"
                  class="form-control"
                  @blur="convertSqftToSqm" 
                  placeholder="Enter Size (sqft)"
                @keydown="preventNumberInvalidKeys"
                />
              </div>
             <div class="col-md-12">
              <label class="form-label">Additional Features</label>
              <div class="listing-feature-grid">
                <button
                  v-for="feature in listingFeatureOptions"
                  :key="feature.key"
                  type="button"
                  class="listing-feature-item"
                  :class="{ 'is-selected': form[feature.key] }"
                  @click="form[feature.key] = !form[feature.key]"
                >
                  <span class="listing-feature-label">{{ feature.label }}</span>
                </button>
              </div>
            </div>
              <!-- Comment -->
              <div class="col-md-12">
                <label class="form-label">Note</label>
                <textarea v-model="form.comment" rows="3" class="form-control" placeholder="Write notes..."></textarea>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 💳 Payment breakdown & NOC (off-plan; fields enabled when completion = Under Construction) -->
      <div class="col-lg-12">
        <div class="card">
          <div class="card-header">
            <h6 class="card-title mb-0">Payment Breakdown &amp; NOC</h6>
          </div>
          <div class="card-body payment-breakdown-card-body">
            <div class="row gy-3 payment-breakdown-prices">
              <div class="col-md-4">
                <label class="form-label">Original price (OP) <span class="text-muted fw-normal small">(developer / contract)</span></label>
                <input
                  :value="formatDecimalPriceDisplay(form.original_price)"
                  type="text"
                  inputmode="numeric"
                  class="form-control"
                  placeholder="Original price in AED"
                  @input="form.original_price = parseDecimalPrice($event.target.value)"
                />
              </div>
              <div class="col-md-4">
                <label class="form-label">Selling price <span class="text-muted fw-normal small">(listing price)</span></label>
                <input
                  :value="formatDecimalPriceDisplay(form.price)"
                  type="text"
                  inputmode="numeric"
                  class="form-control"
                  placeholder="Selling price in AED"
                  @input="form.price = parseDecimalPrice($event.target.value)"
                />
              </div>
              <div v-if="isUnderConstruction" class="col-md-4">
                <label class="form-label">Payment plan</label>
                <v-select
                  v-model="form.payment_plans"
                  :options="paymentPlanOptions"
                  label="label"
                  placeholder="Search and select one plan"
                  :clearable="true"
                  :close-on-select="true"
                  :searchable="true"
                  :class="{ 'is-invalid': paymentPlanFieldInvalid }"
                />
                <div class="text-muted small mt-1">
                  <small>One plan only — type to search the list.</small>
                </div>
                <div v-if="paymentPlanFieldInvalid" class="text-danger small mt-1" role="alert">
                  {{ paymentPlanFieldError }}
                </div>
              </div>
              <div v-if="sellingBelowOriginalActive" class="col-12">
                <div class="alert alert-warning py-2 px-3 mb-0 small" role="status">{{ SELLING_BELOW_OP_WARN_MSG }}</div>
              </div>
            </div>

            <!-- <div v-if="!isUnderConstruction" class="alert alert-light border mb-0 mt-2" role="note">
              <p class="mb-0 text-muted small">
                Set <strong>Completion status</strong> to <strong>Under Construction</strong> to add installments, NOC, and payment plan details below.
              </p>
            </div> -->

            <div class="row gy-3 mt-1">
              <div class="col-12" v-if="isUnderConstruction">
                <div class="payment-calc-summary border rounded-3 p-3 bg-light">
                  <div class="row g-3 small">
                    <div class="col-md-4">
                      <div class="text-muted text-uppercase fw-semibold mb-1">Under-construction tranche</div>
                      <div class="fs-6 fw-semibold">{{ initialPercentForm.toFixed(0) }}% of OP</div>
                      <div class="text-muted">{{ formatAed(ucTrancheAed) }}</div>
                      <div class="text-muted mt-1">Due before handover per plan (e.g. 30 in 30/70).</div>
                    </div>
                    <div class="col-md-4">
                      <div class="text-muted text-uppercase fw-semibold mb-1">Premium</div>
                      <div class="fs-6 fw-semibold">Selling − OP</div>
                      <div :class="{ 'text-danger': premiumIsNegative }">{{ premiumDisplayAed }}</div>
                      <div v-if="premiumIsNegative" class="text-danger small mt-1">Selling price below original price</div>
                      <span v-if="sellingBelowOriginalActive" class="badge bg-warning text-dark mt-1">Selling below original price</span>
                    </div>
                    <div class="col-md-4">
                      <div class="text-muted text-uppercase fw-semibold mb-1">Handover balance</div>
                      <div class="fs-6 fw-semibold">{{ installmentPercentForm.toFixed(0) }}% of OP</div>
                      <div>{{ formatAed(handoverAmountForm) }}</div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="breakdownSellingPriceMismatchActive && isUnderConstruction" class="col-12">
                <div class="alert alert-danger py-2 px-3 mb-0 small" role="alert">
                  <strong>Payment breakdown total does not match selling price.</strong>
                  <span v-if="breakdownSellingDeltaMessage" class="d-block mt-1">{{ breakdownSellingDeltaMessage }}</span>
                </div>
              </div>

              <div v-if="mixedInstallmentTypesError && isUnderConstruction" class="col-12">
                <div class="alert alert-danger py-2 px-3 mb-0 small" role="alert">{{ mixedInstallmentTypesError }}</div>
              </div>

              <div v-if="percentageInstallmentPlanMismatchError && isUnderConstruction" class="col-12">
                <div class="alert alert-danger py-2 px-3 mb-0 small" role="alert">{{ percentageInstallmentPlanMismatchError }}</div>
              </div>

              <div class="col-md-4" v-if="isUnderConstruction">
                <label class="form-label">Handover date</label>
                <AdvancedDatePicker
                  v-model="form.handover_date"
                  date-only
                  dob-layout
                  :block-future-dates="false"
                  placeholder="Select handover date"
                  class="payment-breakdown-date-picker"
                  :invalid="!!paymentHandoverDateError"
                />
                <small class="text-muted">Remaining on handover: {{ formatAed(handoverAmountForm) }}</small>
                <div v-if="paymentHandoverDateError" class="text-danger small mt-1" role="alert">{{ paymentHandoverDateError }}</div>
              </div>

              <div class="col-md-4" v-if="isUnderConstruction">
                <label class="form-label">Total paid (installments with past due date)</label>
                <input
                  :value="`${formatAed(paidAmountForm)} (${paidPercentOfOp.toFixed(2)}% of OP)`"
                  type="text"
                  class="form-control"
                  readonly
                />
              </div>
             <!-- NOC - تظهر في كلتا الحالتين -->
              <div class="col-md-4" v-if="showNocField">
                <label class="form-label">
                  NOC Fees 
                  <span class="text-muted fw-normal small">(AED)</span>
                  <span class="badge" :class="currentNocType === 'Off-Plan' ? 'bg-warning' : 'bg-success'">
                    {{ currentNocType }}
                  </span>
                </label>
                
                <div class="input-group">
                  <span class="input-group-text bg-light">
                    <i class="fas fa-shield-alt" :class="isNocAutoPopulated ? 'text-primary' : 'text-muted'"></i>
                  </span>
                  <input
                    v-model.number="form.noc_fixed_amount"
                    type="number"
                    min="0"
                    step="1000"
                    class="form-control"
                    :disabled="isNocAutoPopulated"
                    :class="{ 'bg-light': isNocAutoPopulated }"
                    placeholder="Enter NOC amount"
                  />
                  <span class="input-group-text" v-if="isNocAutoPopulated">
                    <i class="fas fa-lock text-primary" title="Auto-populated from developer"></i>
                  </span>
                </div>
                
                <small class="text-muted d-block mt-1">
                  NOC fees must be covered by total installments.
                  <span v-if="isNocAutoPopulated && developerNocValue > 0" class="text-primary d-block">
                    <i class="fas fa-info-circle"></i> 
                    Auto-populated from developer ({{ currentNocType }}): 
                    <strong>{{ formatAed(developerNocValue) }}</strong>
                  </span>
                  <span v-else-if="developerNocValue === 0" class="text-muted d-block">
                    <i class="fas fa-info-circle"></i> No NOC fees for this developer ({{ currentNocType }})
                  </span>
                  <span v-else class="text-warning d-block">
                    <i class="fas fa-exclamation-triangle"></i> 
                    NOC value modified manually
                  </span>
                </small>
              </div>

              <!-- NOC Summary Card - تظهر في كلتا الحالتين عند وجود NOC -->
              <div v-if="showNocField && nocFixedAmount > 0" class="col-md-12">
                <div class="noc-summary-card border rounded-3 p-3 mb-3 bg-white">
                  <div class="text-uppercase text-muted small fw-semibold mb-2">
                    NOC summary 
                    <span class="badge" :class="currentNocType === 'Off-Plan' ? 'bg-warning' : 'bg-success'">
                      {{ currentNocType }}
                    </span>
                  </div>
                  <div class="row g-2 small">
                    <div class="col-md-4">
                      <div class="text-muted">NOC required</div>
                      <div class="fw-semibold">{{ formatAed(nocRequiredAed) }}</div>
                      <div v-if="nocPercentOfOp > 0" class="text-muted">({{ nocPercentOfOp.toFixed(1) }}% of OP)</div>
                    </div>
                    <div class="col-md-4">
                      <div class="text-muted">Status</div>
                      <div class="fw-semibold" :class="nocRequirementMet ? 'text-success' : 'text-warning'">
                        {{ nocRequirementMet ? '✅ Covered' : '⚠️ Pending' }}
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="text-muted">Remaining</div>
                      <div class="fw-semibold" :class="nocRequirementMet ? 'text-success' : 'text-warning'">
                        {{ nocFixedAmount <= 0 ? '—' : formatAed(nocRemainingAed) }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              
            </div>

            <hr class="my-3">

            <div class="row gy-3 align-items-end" v-if="isUnderConstruction">
              <div class="col-md-3">
                <label class="form-label">Installment type</label>
                <v-select
                  v-model="installmentDraft.type"
                  :options="installmentTypeOptions"
                  :reduce="(item) => item.value"
                  label="label"
                  placeholder="Choose type"
                />
              </div>
              <div class="col-md-3">
                <label class="form-label">{{ installmentDraft.type === 'percentage' ? 'Percentage' : 'Amount (AED)' }}</label>
                <input
                  v-model.number="installmentDraft.value"
                  type="number"
                  min="0"
                  class="form-control"
                  placeholder="Enter value"
                  @keydown="preventNumberInvalidKeys"
                />
              </div>
              <div class="col-md-3">
                <label class="form-label">Date</label>
                <AdvancedDatePicker
                  v-model="installmentDraft.date"
                  date-only
                  dob-layout
                  :block-future-dates="false"
                  placeholder="Select installment date"
                  class="payment-breakdown-date-picker"
                  :invalid="!!paymentBreakdownInstallmentDateError"
                />
              </div>
              <div class="col-md-3">
                <button type="button" class="btn btn-primary w-100" @click="addBreakdownInstallment">
                  + Add installment
                </button>
              </div>
              <div v-if="paymentBreakdownInstallmentDateError" class="col-12">
                <div class="text-danger small" role="alert">{{ paymentBreakdownInstallmentDateError }}</div>
              </div>
              <div v-if="paymentBreakdownPercentageCapError" class="col-12">
                <div class="text-danger small" role="alert">{{ paymentBreakdownPercentageCapError }}</div>
              </div>
              <div v-if="paymentBreakdownDuplicateInstallmentDateWarning" class="col-12">
                <div class="alert alert-warning py-2 px-3 mb-0 small" role="status">
                  {{ paymentBreakdownDuplicateInstallmentDateWarning }}
                </div>
              </div>
              <div v-if="paymentPlanDurationWarning" class="col-12">
                <div class="alert alert-warning py-2 px-3 mb-0 small" role="status">
                  {{ paymentPlanDurationWarning }}
                </div>
              </div>
            </div>

            <div class="table-responsive mt-3" v-if="isUnderConstruction">
              <table class="table table-sm align-middle">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Type</th>
                    <th>%</th>
                    <th>Amount</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, idx) in paymentBreakdownRows" :key="row.id">
                    <td>{{ idx + 1 }}</td>
                    <td>{{ row.type }}</td>
                    <td>{{ row.type === 'Premium' ? '—' : `${row.percentage}%` }}</td>
                    <td :class="{ 'text-danger': row.type === 'Premium' && row.amount < 0 }">{{ formatAed(row.amount) }}</td>
                    <td>{{ row.type === 'Premium' ? '—' : formatDateShort(row.date) }}</td>
                    <td>
                      <span class="badge" :class="breakdownRowStatusClass(row.status)">
                        {{ row.status }}
                      </span>
                    </td>
                    <td class="text-end">
                      <button v-if="row.entryId" type="button" class="btn btn-sm btn-outline-danger" @click="removeBreakdownInstallment(row.entryId)">
                        Remove
                      </button>
                    </td>
                  </tr>
                  <tr v-if="paymentBreakdownRows.length === 0">
                    <td colspan="7" class="text-center text-muted">No installments yet.</td>
                  </tr>
                  <tr v-if="paymentBreakdownRows.length > 0" class="table-light fw-semibold">
                    <td colspan="2">Total</td>
                    <td>{{ paymentBreakdownTableTotals.percentTotal }}{{ paymentBreakdownTableTotals.percentTotal !== '—' ? '%' : '' }}</td>
                    <td>{{ formatAed(paymentBreakdownTableTotals.amountTotal) }}</td>
                    <td colspan="3"></td>
                  </tr>
                </tbody>
              </table>
            </div>


            <section class="assignment-expenses-panel mt-4" aria-labelledby="edit-assignment-expenses-heading">
              <div class="assignment-expenses-panel__head d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                  <h6 id="edit-assignment-expenses-heading" class="assignment-expenses-panel__title mb-0">Assignment deal costs</h6>
                  <p class="assignment-expenses-panel__subtitle small text-muted mb-0 mt-1">
                    DLD, agency, mortgage fees, and other charges — calculated separately from installments.
                  </p>
                </div>
              </div>

              <!-- <div class="assignment-expenses-add row g-2 g-md-3 align-items-end mb-3">
                <div class="col-12 col-md-3">
                  <label class="form-label small mb-1">Label</label>
                  <input
                    v-model="assignmentExpenseDraft.label"
                    type="text"
                    class="form-control form-control-sm"
                    placeholder="e.g. DLD, Agency fee"
                  />
                </div>
                <div class="col-6 col-md-2">
                  <label class="form-label small mb-1">Type</label>
                  <v-select
                    v-model="assignmentExpenseDraft.calcType"
                    :options="assignmentExpenseTypeOptions"
                    :reduce="(item) => item.value"
                    label="label"
                    :clearable="false"
                  />
                </div>
                <div v-if="assignmentExpenseDraft.calcType === 'percentage'" class="col-6 col-md-2">
                  <label class="form-label small mb-1">Base</label>
                  <v-select
                    v-model="assignmentExpenseDraft.base"
                    :options="assignmentExpenseBaseOptions"
                    :reduce="(item) => item.value"
                    label="label"
                    :clearable="false"
                  />
                </div>
                <div class="col-6" :class="assignmentExpenseDraft.calcType === 'percentage' ? 'col-md-2' : 'col-md-3'">
                  <label class="form-label small mb-1">
                    {{ assignmentExpenseDraft.calcType === 'percentage' ? 'Value (%)' : 'Amount (AED)' }}
                  </label>
                  <input
                    v-model.number="assignmentExpenseDraft.value"
                    type="number"
                    min="0"
                    step="any"
                    class="form-control form-control-sm"
                    placeholder="0"
                    @keydown="preventNumberInvalidKeys"
                  />
                </div>
                <div class="col-6 col-md-2 d-flex align-items-end">
                  <div class="form-check form-switch assignment-expenses-vat-switch mb-2">
                    <input
                      id="edit-assignment-expense-draft-vat"
                      v-model="assignmentExpenseDraft.vatEnabled"
                      class="form-check-input"
                      type="checkbox"
                    />
                    <label class="form-check-label small" for="edit-assignment-expense-draft-vat">VAT 5%</label>
                  </div>
                </div>
                <div class="col-12 col-md-2">
                  <button type="button" class="btn btn-sm btn-outline-primary w-100" @click="onAddAssignmentExpenseLine">
                    + Add cost line
                  </button>
                </div>
              </div> -->

              <div class="assignment-expenses-table-wrap">
                <table class="table table-sm assignment-expenses-table mb-0">
                  <thead>
                    <tr>
                      <th>Label</th>
                      <th class="d-none d-md-table-cell">Base</th>
                      <th>Type</th>
                      <th>Value</th>
                      <th class="text-end">Amount</th>
                      <th class="text-end">VAT (5%)</th>
                      <th class="text-end">Total</th>
                      <th class="text-end" style="width: 4rem;"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="line in assignmentExpenseLines" :key="line.id">
                      <td>
                        <input
                          v-model="line.label"
                          type="text"
                          class="form-control form-control-sm assignment-expenses-inline-input"
                          placeholder="Label"
                           :disabled="line.isReadonly"
                        />
                      </td>
                      <td class="d-none d-md-table-cell">
                        <v-select
                          v-if="line.calcType === 'percentage'"
                          v-model="line.base"
                          :options="assignmentExpenseBaseOptions"
                          :reduce="(item) => item.value"
                          label="label"
                          :clearable="false"
                          class="assignment-expenses-inline-select"
                           :disabled="line.isReadonly"
                        />
                        <span v-else class="text-muted small">—</span>
                      </td>
                      <td>
                        <v-select
                          v-model="line.calcType"
                          :options="assignmentExpenseTypeOptions"
                          :reduce="(item) => item.value"
                          label="label"
                          :clearable="false"
                          class="assignment-expenses-inline-select"
                           :disabled="line.isReadonly"
                        />
                      </td>
                      <td>
                        <div class="d-flex align-items-center gap-1">
                          <input
                            v-model.number="line.value"
                            type="number"
                            min="0"
                            step="any"
                            class="form-control form-control-sm assignment-expenses-inline-input assignment-expenses-value-input"
                            @keydown="preventNumberInvalidKeys"
                             :disabled="line.isReadonly"
                          />
                          <span class="text-muted small text-nowrap">{{ line.calcType === 'percentage' ? '%' : 'AED' }}</span>
                        </div>
                      </td>
                      <td class="text-end text-nowrap">{{ formatAed(assignmentExpenseLineAmount(line)) }}</td>
                      <td class="text-end text-nowrap">
                        <label class="assignment-expenses-vat-inline d-inline-flex align-items-center justify-content-end gap-1 mb-0 small">
                          <input
                            v-model="line.vatEnabled"
                            type="checkbox"
                            class="form-check-input m-0 flex-shrink-0"
                            :title="'Apply 5% VAT on ' + formatAed(assignmentExpenseLineAmount(line))"
                          />
                          <span>{{ line.vatEnabled ? formatAed(assignmentExpenseLineVat(line)) : '—' }}</span>
                        </label>
                      </td>
                      <td class="text-end text-nowrap fw-semibold">{{ formatAed(assignmentExpenseLineTotal(line)) }}</td>
                      <!-- <td class="text-end">
                        <button
                          type="button"
                          class="btn btn-sm btn-link text-danger p-0"
                          title="Remove"
                          @click="removeAssignmentExpenseLine(line.id)"
                        >
                          ×
                        </button>
                      </td> -->
                    </tr>
                    <tr v-if="assignmentExpenseLines.length === 0">
                      <td colspan="8" class="text-center text-muted py-4">
                        No cost lines yet. Add DLD, agency, or other fees above.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div
                v-if="assignmentExpenseLines.length > 0"
                class="assignment-expenses-summary row g-2 g-md-3 mt-3 pt-3 border-top"
              >
                <div class="col-4 col-md-4">
                  <div class="assignment-expenses-summary__label">Subtotal (excl. VAT)</div>
                  <div class="assignment-expenses-summary__value">{{ formatAed(assignmentExpensesSubtotal) }}</div>
                </div>
                <div class="col-4 col-md-4">
                  <div class="assignment-expenses-summary__label">Total VAT (5%)</div>
                  <div class="assignment-expenses-summary__value">{{ formatAed(assignmentExpensesTotalVat) }}</div>
                </div>
                <div class="col-4 col-md-4">
                  <div class="assignment-expenses-summary__label">Grand total</div>
                  <div class="assignment-expenses-summary__value assignment-expenses-summary__value--grand">
                    {{ formatAed(assignmentExpensesGrandTotal) }}
                  </div>
                </div>
              </div>
            </section>

            <div class="payment-validation-summary border rounded-3 p-3 mt-3 bg-white" v-if="isUnderConstruction">
              <div class="fw-semibold small text-uppercase text-muted mb-2">Validation summary</div>
              <ul class="list-unstyled small mb-0 payment-validation-summary-list">
                <li
                  v-for="item in paymentBreakdownValidationSummary"
                  :key="item.id"
                  class="d-flex align-items-start gap-2 py-1"
                  :class="`payment-val-${item.level}`"
                >
                  <span class="payment-val-icon" aria-hidden="true">{{ item.icon }}</span>
                  <span>{{ item.text }}</span>
                </li>
              </ul>
            </div>

            <div class="payment-breakdown-actions d-flex flex-wrap gap-2 justify-content-end mt-3 pt-3 border-top">
              <button
                type="button"
                class="btn btn-outline-primary"
                @click="showPaymentDetailsPreview = true"
              >
                <i class="fas fa-eye me-1"></i>
                Preview
              </button>
              <button
                type="button"
                class="btn btn-primary"
                @click="handleSubmit('draft')"
                :disabled="isSubmitting"
              >
                <i class="fas fa-save me-1"></i>
                Save
              </button>
            </div>
          </div>
        </div>
      </div>
      <PaymentDetailsPreviewModal
        v-model="showPaymentDetailsPreview"
        :selling-price="sellingPriceNum"
        :original-price="originalPriceNum"
        :premium-amount="premiumAmountForm"
        :payment-plan-label="selectedPaymentPlanLabel"
        :initial-percent="initialPercentForm"
        :handover-percent="installmentPercentForm"
        :uc-tranche-aed="ucTrancheAed"
        :handover-amount-aed="handoverAmountForm"
        :handover-date="form.handover_date || ''"
        :paid-total-aed="paidAmountForm"
        :paid-percent-of-op="paidPercentOfOp"
        :noc-percent="nocPercentOfOp"
        :noc-required-aed="nocRequiredAed"
        :noc-remaining-aed="nocRemainingAed"
        :noc-requirement-met="nocRequirementMet"
        :noc-progress-label="nocProgressPaidLabel"
        :noc-fixed-amount="nocFixedAmount"
        :noc-type="currentNocType"
        :breakdown-rows="paymentBreakdownRows"
        :is-under-construction="isUnderConstruction"
        :assignment-expense-rows="assignmentExpenseLines"
        :assignment-expenses-subtotal="assignmentExpensesSubtotal"
        :assignment-expenses-total-vat="assignmentExpensesTotalVat"
        :assignment-expenses-grand-total="assignmentExpensesGrandTotal"
      />

      <!-- 💰 Mortgage & Rent Info -->
      <div class="col-lg-12">
        <div class="card">
          <div class="card-header">
            <h6 class="card-title mb-0">Mortgage & Rent Info</h6>
          </div>
          <div class="card-body">
            <div class="row gy-3">
              <!-- Mortgage Status -->
              <div class="col-md-4">
                <label class="form-label">Mortgage Status</label>
                <v-select 
                  v-model="form.mortgageStatus" 
                  :options="mortgageStatusOptions" 
                  placeholder="Select Mortgage Status"
                />
              </div>
              
              <!-- Mortgage Amount: hidden when Non-Mortgaged / Non Mortgage -->
              <div v-if="!isNonMortgaged" class="col-md-4">
                <label class="form-label">Mortgage Amount</label>
                <input v-model="form.mortgageAmount" type="number" class="form-control" placeholder="Enter Mortgage Amount" @keydown="preventNumberInvalidKeys" />
              </div>
              <!-- Force new row on desktop so first line only has Mortgage fields -->
                <div class="w-100 d-none d-md-block"></div>

              <!-- Occupancy Status -->
              <div class="col-md-4" v-if="!isUnderConstruction">
                <label class="form-label">Occupancy Status</label>
                <v-select 
                  v-model="form.occupancyStatus" 
                  :options="occupancyStatusOptions" 
                  placeholder="Select Occupancy Status"
                />
              </div>

              <!-- Rent fields: hidden when Vacant -->
              <template v-if="form.occupancyStatus === 'Rented' && !isUnderConstruction">
                <div class="col-md-4">
                  <label class="form-label">Rent Expiry Date</label>
                  <input v-model="form.rentExpiryDate" type="date" class="form-control" placeholder="Enter Rent Expiry Date" :min="minRentExpiryDate" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">Rent Amount</label>
                  <input v-model="form.rentAmount" type="number" class="form-control" placeholder="Enter Rent Amount" @keydown="preventNumberInvalidKeys" />
                </div>
              </template>

              <!-- Mortgage Comment: hidden when Non-Mortgaged / Non Mortgage -->
              <div v-if="!isNonMortgaged" class="col-md-12">
                <label class="form-label">Comment</label>
                <textarea v-model="form.mortgageComment" rows="3" class="form-control" placeholder="Enter Mortgage Comment"></textarea>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 🖼️ Gallery Section -->
      <div class="col-lg-12">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="card-title mb-0">Property Gallery</h6>
            <div class="total-gallery-count">
              <span class="badge" :class="totalGalleryCount >= 10 ? 'bg-success' : 'bg-warning'">
                Total: {{ totalGalleryCount }}/10
              </span>
            </div>
          </div>
          <div class="card-body">
            <div class="row gy-3">
                <!--<div class="col-12">-->
                <!--  <label class="form-label">Google Drive Link (Optional)</label>-->
                <!--  <div class="input-group">-->
                <!--    <span class="input-group-text">-->
                <!--      <i class="fab fa-google-drive"></i>-->
                <!--    </span>-->
                <!--    <input -->
                <!--      v-model="form.drive_link" -->
                <!--      type="url" -->
                <!--      class="form-control" -->
                <!--      placeholder="https://drive.google.com/drive/folders/..."-->
                <!--    />-->
                <!--  </div>-->
                <!--  <div class="text-muted small mt-1">-->
                <!--        <small>You can add a Google Drive link containing additional property images</small>-->
                <!--    </div>-->
        
                <!--</div>-->
              <div class="col-12">
                <label class="form-label">Upload New Property Images</label>
                <input 
                  type="file" 
                  class="form-control" 
                  multiple 
                  @change="handleGalleryUpload" 
                  accept="image/*"
                  ref="galleryInput"
                />
                <div class="text-muted small mt-1">
                  You can choose multiple images (PNG, JPG, JPEG, SVG, WebP). Max 10MB per image.
                  <strong class="text-primary">First image will be set as the hero image automatically if no hero image exists.</strong>
                </div>
              </div>

              <!-- Gallery Preview -->
              <div class="col-12" v-if="combinedGallery.length > 0">
                <label class="form-label mb-3">Gallery Preview</label>
                <div class="alert alert-info">
                  <i class="fas fa-info-circle me-2"></i>
                  <strong>First image</strong> is the current hero image. Click "Set as hero" on any image to change it.
                </div>
                <draggable
                  v-model="combinedGalleryModel"
                  tag="div"
                  class="row g-3 listing-gallery-draggable"
                  item-key="_key"
                  ghost-class="listing-gallery-ghost"
                  chosen-class="listing-gallery-chosen"
                  drag-class="listing-gallery-drag"
                  :animation="180"
                  filter=".no-drag"
                  :prevent-on-filter="true"
                >
                  <template #item="{ element: item, index }">
                  <div
                    class="col-xl-3 col-lg-4 col-md-6"
                  >
                    <div class="gallery-item position-relative" :class="{ 'hero-image': isHeroImage(item) }">
                      <!-- Hero Image Card -->
                      <div class="card h-100 border-primary" v-if="isHeroImage(item)">
                        <div class="card-header bg-primary text-white py-1 text-center">
                          <small><i class="fas fa-star me-1"></i> Current Hero Image</small>
                        </div>
                        <img 
                          :src="item.image_url || item.preview || getImagePreview(item.file || item)" 
                          class="card-img-top gallery-image" 
                          alt="Gallery image"
                          style="height: 200px; object-fit: cover;"
                          @error="handleImageError"
                          loading="lazy"
                        />
                        <div class="card-body p-3">
                          <p class="card-text small text-truncate mb-1">{{ item.name || item.file?.name }}</p>
                          <p class="card-text small text-muted" v-if="item.created_at">
                            Uploaded: {{ formatDate(item.created_at) }}
                          </p>
                          <p class="card-text small text-muted" v-else>
                            {{ formatFileSize(item.size || item.file?.size) }}
                          </p>
                        </div>
                        <button
                          type="button"
                          class="btn-close no-drag position-absolute top-0 end-0 m-2 bg-danger rounded-circle p-1"
                          @click="removeGalleryItem(item, index)"
                          style="--bs-bg-opacity: 0.8;"
                          :title="item.id ? 'Delete image' : 'Remove image'"
                        ></button>
                      </div>
                      
                      <!-- Regular Gallery Card -->
                      <div class="card h-100" v-else>
                        <img 
                          :src="item.image_url || item.preview || getImagePreview(item.file || item)" 
                          class="card-img-top gallery-image" 
                          alt="Gallery image"
                          style="height: 200px; object-fit: cover;"
                          @error="handleImageError"
                          loading="lazy"
                        />
                        <div class="card-body p-3">
                          <p class="card-text small text-truncate mb-1">{{ item.name || item.file?.name }}</p>
                          <p class="card-text small text-muted" v-if="item.created_at">
                            Uploaded: {{ formatDate(item.created_at) }}
                          </p>
                          <p class="card-text small text-muted" v-else>
                            {{ formatFileSize(item.size || item.file?.size) }}
                          </p>
                          <div class="d-flex gap-1 mt-2">
                            <button
                              type="button"
                              class="btn btn-sm btn-outline-primary no-drag"
                              @click="setAsHeroImage(item)"
                              :disabled="isSettingHero"
                              title="Set as hero image"
                            >
                              <i class="fas fa-star me-1"></i> 
                              {{ isSettingHero ? 'Setting...' : 'Set as Hero' }}
                            </button>
                          </div>
                        </div>
                        <button
                          type="button"
                          class="btn-close no-drag position-absolute top-0 end-0 m-2 bg-danger rounded-circle p-1"
                          @click="removeGalleryItem(item, index)"
                          style="--bs-bg-opacity: 0.8;"
                          :title="item.id ? 'Delete image' : 'Remove image'"
                        ></button>
                      </div>
                    </div>
                  </div>
                  </template>
                </draggable>
              </div>

              <!-- Empty State -->
              <div class="col-12" v-if="combinedGallery.length === 0">
                <div class="text-center py-5 border rounded bg-light">
                  <i class="fas fa-images fa-3x text-muted mb-3"></i>
                  <p class="text-muted mb-0">No images uploaded yet. Add some photos to showcase your property!</p>
                  <p class="text-muted small mt-2">The first image will be used as the hero property image.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 📐 Floor Plans Section -->
            <div class="col-lg-12" v-if="selectedProject && filteredProjectFloorPlans.length > 0" >
                  <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                      <div>
                        <h6 class="card-title mb-0">Floor Plans</h6>
                        <small class="text-muted">Total: {{ totalFloorPlans }} plans</small>
                      </div>
                      <!--<div class="d-flex align-items-center gap-2">-->
                      <!--  <button -->
                      <!--    type="button" -->
                      <!--    class="btn btn-sm btn-primary"-->
                      <!--    @click="$refs.floorPlanInput.click()"-->
                      <!--  >-->
                      <!--    <i class="fas fa-upload me-1"></i>-->
                      <!--    Upload New-->
                      <!--  </button>-->
                      <!--</div>-->
                    </div>
                    
                    <div class="card-body">
                      <div v-if="selectedProject && filteredProjectFloorPlans.length > 0" class="mb-5">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                          <h6 class="fw-bold mb-0">
                            <i class="fas fa-building text-primary me-2"></i>
                            Project Floor Plans: {{ selectedProject.name }}
                          </h6>
                          <div>
                            <span class="badge bg-info me-2">
                              {{ importedFloorPlansCount }} selected
                            </span>
                            <button 
                              v-if="!loadingProjectFloorPlans"
                              type="button" 
                              class="btn btn-sm btn-outline-secondary"
                              @click="refreshProjectFloorPlans"
                            >
                              <i class="fas fa-sync-alt"></i>
                            </button>
                          </div>
                        </div>
                        
                        <div class="row g-3">
                          <div 
                            v-for="floorPlan in filteredProjectFloorPlans" 
                            :key="`project-${floorPlan.id}`"
                            class="col-xl-2-4 col-lg-3 col-md-4 col-6"
                          >
                            <div class="floor-plan-card" 
                                 :class="{ 
                                   'selected': isFloorPlanImported(floorPlan.id),
                                   'existing': isExistingFloorPlan(floorPlan.id) && !isFloorPlanImported(floorPlan.id)
                                 }"
                                 @click="toggleImportFloorPlan(floorPlan)">
                              <div class="card h-100">
                                <div class="position-relative">
                                  <img 
                                    :src="floorPlan.image_url" 
                                    class="card-img-top floor-plan-image" 
                                    alt="Project floor plan"
                                    style="height: 150px; object-fit: cover;"
                                    @error="handleImageError"
                                    loading="lazy"
                                    @click.stop="openFloorPlanViewer(floorPlan)"
                                  />
                                  
                                  <!-- Status Badge -->
                                  <div class="position-absolute top-0 start-0 m-2">
                                    <span 
                                      v-if="isFloorPlanImported(floorPlan.id)" 
                                      class="badge bg-success"
                                    >
                                      <i class="fas fa-check-circle me-1"></i>
                                      Selected
                                    </span>
                                    <span 
                                      v-else-if="isExistingFloorPlan(floorPlan.id)"
                                      class="badge bg-secondary"
                                    >
                                      <i class="fas fa-check-circle me-1"></i>
                                      Already Added
                                    </span>
                                    <span 
                                      v-else
                                      class="badge bg-light text-dark"
                                    >
                                      <i class="fas fa-plus-circle me-1"></i>
                                      Click to select
                                    </span>
                                  </div>
                                  
                                  <!-- Select/Remove Icon -->
                                  <div class="position-absolute top-0 end-0 m-2">
                                    <span 
                                      v-if="isFloorPlanImported(floorPlan.id)"
                                      class="badge bg-danger"
                                    >
                                      <i class="fas fa-times"></i>
                                    </span>
                                  </div>
                                </div>
                                
                                <div class="card-body p-3">
                                  <h6 class="card-title small text-truncate mb-2" :title="floorPlan.name">
                                    {{ floorPlan.name || 'Unnamed Plan' }}
                                  </h6>
                                  
                                  <div class="plan-info small text-muted">
                                    <div v-if="floorPlan.dimensions" class="mt-1">
                                      <i class="fas fa-ruler-combined me-1"></i>
                                      {{ floorPlan.dimensions }}
                                    </div>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                        
                        <!-- Load More Button -->
                        <div v-if="projectFloorPlans.length < totalProjectFloorPlans" class="text-center mt-3">
                          <button 
                            type="button" 
                            class="btn btn-sm btn-outline-secondary"
                            @click="loadMoreProjectFloorPlans"
                            :disabled="loadingProjectFloorPlans"
                          >
                            <span v-if="loadingProjectFloorPlans">
                              <span class="spinner-border spinner-border-sm me-2"></span>
                              Loading...
                            </span>
                            <span v-else>
                              <i class="fas fa-arrow-down me-1"></i>
                              Load More ({{ totalProjectFloorPlans - projectFloorPlans.length }})
                            </span>
                          </button>
                        </div>
                      </div>
                
                      <div>
                        <div class="d-flex align-items-center justify-content-between mb-3">
                          <h6 class="fw-bold mb-0">
                            <i class="fas fa-layer-group text-primary me-2"></i>
                            Property Floor Plans
                          </h6>
                          <span class="badge bg-primary">{{ allPropertyFloorPlans.length }} plans</span>
                        </div>
                        
                        <!-- Hidden file input -->
                        <input 
                          type="file" 
                          class="d-none" 
                          multiple 
                          @change="handleFloorPlanUpload" 
                          accept="image/*"
                          ref="floorPlanInput"
                        />
                        
                        <!-- All floor plans in one grid -->
                        <div v-if="allPropertyFloorPlans.length > 0" class="row g-3">
                          <div 
                            v-for="(plan, index) in allPropertyFloorPlans" 
                            :key="plan.id || plan.tempId || `plan-${index}`"
                            class="col-xl-2-4 col-lg-3 col-md-4 col-6"
                          >
                            <div class="floor-plan-item">
                              <div class="card h-100" >
                                <!-- Image with click to view -->
                                <div class="position-relative" @click="openFloorPlanViewer(plan)">
                                  <img 
                                    :src="plan.image_url || plan.preview || getImagePreview(plan.file || plan)" 
                                    class="card-img-top floor-plan-image" 
                                    :alt="plan.name || 'Floor plan'"
                                    style="height: 150px; object-fit: cover; cursor: pointer;"
                                    @error="handleImageError"
                                    loading="lazy"
                                  />
                                  
                                 
                                </div>
                                
                                <div class="card-body p-3">
                                  <!-- Editable Name -->
                                  <div class="mb-2">
                                    <input 
                                      v-if="!plan.id || plan.is_imported || plan.is_new"
                                      type="text"
                                      class="form-control form-control-sm"
                                      v-model="plan.customName"
                                      :placeholder="'Enter plan name'"
                                      @blur="updatePlanName(plan)"
                                    />
                                    <div v-else class="d-flex justify-content-between align-items-center">
                                      <span class="small fw-bold text-truncate" :title="plan.name">
                                        {{ plan.name }}
                                      </span>
                                      <button 
                                        class="btn btn-sm btn-link p-0"
                                        @click="editFloorPlanName(plan)"
                                      >
                                        <i class="fas fa-pencil-alt text-primary"></i>
                                      </button>
                                    </div>
                                  </div>
                                  
                                  <!-- Plan Details -->
                                  <div class="small text-muted mb-2">
                                    <div v-if="plan.dimensions">
                                      <i class="fas fa-ruler-combined me-1"></i>
                                      {{ plan.dimensions }}
                                    </div>
                                    <div v-else-if="plan.size">
                                      {{ plan.size }} sqm
                                    </div>
                                  </div>
                                  
                                  <!-- Actions -->
                                  <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-muted">
                                      {{ getPlanFileSize(plan) }}
                                    </small>
                                    <button 
                                      type="button"
                                      class="btn btn-sm btn-outline-danger"
                                      @click="removePlan(plan, index)"
                                    >
                                      <i class="fas fa-trash-alt"></i>
                                    </button>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                        
                        <!-- Empty State -->
                        <!--<div v-else class="text-center py-5 border rounded bg-light">-->
                        <!--  <i class="fas fa-blueprint fa-3x text-muted mb-3"></i>-->
                        <!--  <h6 class="text-muted mb-2">No Floor Plans Yet</h6>-->
                        <!--  <p class="text-muted small mb-3">-->
                        <!--    Add floor plans to showcase your property layout-->
                        <!--  </p>-->
                        <!--  <button -->
                        <!--    type="button"-->
                        <!--    class="btn btn-primary"-->
                        <!--    @click="$refs.floorPlanInput.click()"-->
                        <!--  >-->
                        <!--    <i class="fas fa-upload me-2"></i>-->
                        <!--    Upload Floor Plans-->
                        <!--  </button>-->
                        <!--</div>-->
                      </div>
                    </div>
                  </div>
                </div>

      <!-- 📄 Property Documents -->
      <div class="col-lg-12">
        <div class="card">
          <div class="card-header">
            <h6 class="card-title mb-0">Property Documents</h6>
          </div>
          <div class="card-body">
            <div class="row gy-3">
              <div class="col-12">
                <label class="form-label">Additional Documents</label>
                <input
                  type="file"
                  class="form-control"
                  multiple
                  accept=".pdf,.jpg,.jpeg,.png,.svg"
                  @change="handleAdditionalDocumentsUpload"
                />
                <div v-if="existingAdditionalDocuments.length" class="mt-3">
                  <label class="form-label small text-muted">Existing ({{ existingAdditionalDocuments.length }})</label>
                  <div class="list-group list-group-flush">
                    <div
                      v-for="doc in existingAdditionalDocuments"
                      :key="doc.id"
                      class="list-group-item d-flex align-items-center justify-content-between"
                    >
                      <a :href="doc.url" target="_blank" rel="noopener" class="text-truncate flex-grow-1 me-2">{{ doc.name }}</a>
                      <button type="button" class="btn btn-sm btn-outline-danger" @click="deleteExistingAdditionalDocument(doc.id)">Remove</button>
                    </div>
                  </div>
                </div>
                <div v-if="form.additionalDocuments && form.additionalDocuments.length" class="mt-2">
                  <label class="form-label small text-muted">New uploads</label>
                  <div v-for="(item, index) in form.additionalDocuments" :key="'new-' + index" class="d-flex align-items-center justify-content-between gap-2 small mb-1">
                    <span class="text-truncate">{{ item.name || item.file?.name }}</span>
                    <button type="button" class="btn btn-sm btn-outline-danger" @click="removeAdditionalDocument(index)">Remove</button>
                  </div>
                </div>
                <div class="text-muted small mt-1">PDF, JPG, PNG, SVG. Max 10MB per file.</div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!--  Owner Section -->
      <div class="col-lg-12" >
        <div class="card">
          <div class="card-header" v-if="canShowOwnerSection">
            <h6 class="card-title mb-0">Owner Details</h6>
          </div>
          <div class="card-body" v-if="canShowOwnerSection">
            <div class="row align-items-end gy-3">
              <div class="col-md-6 col-sm-8">
                <label class="form-label">Select Owner</label>
                <v-select
                  v-model="selectedOwner"
                  :options="owners"
                  label="full_name"
                  placeholder="Search by phone or email"
                  :filterable="true"
                  :filter-by="customOwnerFilter"
                  :disabled="isLoadingOwners"
                  :reduce="owner => owner"
                >
                  <template #option="{ full_name, email, phone_number, whatsapp_number, second_phone_number }">
                    <div class="d-flex flex-column">
                      <strong>{{ full_name }}</strong>
                      <small class="text-muted">
                        {{ email }}
                        <span v-if="phone_number || whatsapp_number || second_phone_number">
                          | {{ phone_number || whatsapp_number || second_phone_number }}
                        </span>
                      </small>
                    </div>
                  </template>
                  
                  <template #selected-option="{ full_name, email, phone_number }">
                    <div>
                      {{ full_name }}
                      <small class="text-muted ms-2">
                        {{ email || phone_number }}
                      </small>
                    </div>
                  </template>
                </v-select>
                <div v-if="isLoadingOwners" class="text-muted small mt-1">Loading owners...</div>
              </div>
              <div class="col-md-3 col-sm-4">
                <button v-if="proxy.$hasPermission('owners-create')" class="btn btn-primary w-100 mt-3 mt-md-0" @click="showAddOwner = true">
                  <i class="fas fa-plus me-1"></i> Add New Owner
                </button>
              </div>
            </div>
          </div>
          
          <div class="card-footer text-center footer-pt">
            <div class="d-flex gap-2 justify-content-center">
              <button
                type="button"
                class="btn btn-outline-secondary"
                @click="handleSubmit('draft')"
                :disabled="isSubmitting"
              >
                <i class="fas fa-save me-1"></i>
                Update as Draft
              </button>
              <button
                type="button"
                class="btn btn-outline-primary"
                @click="handleSubmit('preview')"
                :disabled="isSubmitting"
              >
                <i class="fas fa-eye me-1"></i>
                Preview
              </button>
              <button
                type="button"
                class="btn btn-primary"
                @click="handleSubmit('publish')"
                :disabled="isSubmitting || publishPaymentBreakdownBlocked"
                :title="publishPaymentBreakdownBlockTitle"
              >
                <i class="fas fa-paper-plane me-1"></i>
                Update & Publish
              </button>
            </div>
            
            <!-- Validation Hints -->
            <!--<div class="mt-2 text-center">-->
            <!--  <small class="text-danger">-->
            <!--    <i class="fas fa-info-circle me-1"></i>-->
            <!--    To publish: 10+ gallery images required-->
            <!--  </small>-->
            <!--</div>-->
          </div>
        </div>
      </div>
    </div>

    <!-- Add Owner Modal -->
    <div v-if="showAddOwner" class="modal-backdrop">
      <div class="modal-container">
        <div class="modal-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Add New Owner</h5>
          <button class="btn-close" @click="closeAddOwner"></button>
        </div>

        <div class="modal-body">
          <div class="row gy-3">
            <!-- Salutation -->
            <div class="col-md-3 col-sm-6">
              <label class="form-label">Salutation</label>
              <v-select 
                v-model="newOwner.salutation" 
                :options="salutationOptions" 
                placeholder="Select Salutation"
              />
            </div>

            <!-- First Name -->
            <div class="col-md-3 col-sm-6">
              <label class="form-label">First Name</label>
              <input 
                v-model="newOwner.first_name" 
                type="text" 
                class="form-control" 
                placeholder="Enter First Name"
                @input="filterNameInput('first_name')"
              />
            </div>

            <!-- Last Name -->
            <div class="col-md-3 col-sm-6">
              <label class="form-label">Last Name</label>
              <input 
                v-model="newOwner.last_name" 
                type="text" 
                class="form-control" 
                placeholder="Enter Last Name"
                @input="filterNameInput('last_name')"
              />
            </div>

            <!-- Email -->
            <div class="col-md-3 col-sm-6">
              <label class="form-label">Email</label>
              <input v-model="newOwner.email" type="email" class="form-control" placeholder="Enter Email"/>
            </div>

            <!-- Phone -->
            <div class="col-md-4 col-sm-6">
              <label class="form-label">Primary Phone</label>
              <input 
                v-model="newOwner.phone_number" 
                type="text" 
                class="form-control" 
                placeholder="Enter Phone"
                @input="filterNumberInput('phone_number')"
              />
            </div>

            <!-- WhatsApp -->
            <div class="col-md-4 col-sm-6">
              <label class="form-label">Whatsapp</label>
              <input 
                v-model="newOwner.whatsapp_number" 
                type="text" 
                class="form-control" 
                placeholder="Enter Whatsapp"
                @input="filterNumberInput('whatsapp_number')"
              />
            </div>

            <!-- Second Phone -->
            <div class="col-md-4 col-sm-6">
              <label class="form-label">Second Phone</label>
              <input 
                v-model="newOwner.second_phone_number" 
                type="text" 
                class="form-control" 
                placeholder="Enter Second Phone"
                @input="filterNumberInput('second_phone_number')"
              />
            </div>

            <!-- Nationality -->
            <div class="col-md-6 col-sm-12">
              <label class="form-label">Nationality</label>
              <v-select 
                v-model="newOwner.nationality" 
                :options="nationalities" 
                placeholder="Select nationality" 
                @update:modelValue="handleNationalityChange"
              />
            </div>

            <!-- Residency Status -->
            <div class="col-md-3 col-sm-6">
              <label class="form-label">Residency Status</label>
              <v-select
                v-model="newOwner.residency_status" 
                :options="residencyStatusOptions"
                :reduce="option => option.value"
                placeholder="Select Residency Status"
                :disabled="newOwner.nationality === 'UAE'"
              />
            </div>

            <!-- Location -->
            <div class="col-md-3 col-sm-6">
              <label class="form-label">{{ getLocationLabel() }}</label>
              <v-select
                v-model="newOwner.location_id"
                :options="locations"
                label="name"
                :reduce="(loc) => loc.id"
                :placeholder="getLocationPlaceholder()"
                :disabled="!newOwner.residency_status"
                :loading="isLoadingLocations"
              />
            </div>

            <!-- Documents -->
            <div class="col-md-3 col-sm-6">
              <label class="form-label">ID Front</label>
              <input type="file" class="form-control" @change="handleNewOwnerFile($event, 'id_front')" accept=".jpg,.jpeg,.png,.pdf" />
            </div>

            <div class="col-md-3 col-sm-6">
              <label class="form-label">ID Back</label>
              <input type="file" class="form-control" @change="handleNewOwnerFile($event, 'id_back')" accept=".jpg,.jpeg,.png,.pdf" />
            </div>

            <div class="col-md-3 col-sm-6">
              <label class="form-label">Visa Copy</label>
              <input type="file" class="form-control" @change="handleNewOwnerFile($event, 'visa_copy')" accept=".jpg,.jpeg,.png,.pdf" />
            </div>

            <div class="col-md-3 col-sm-6">
              <label class="form-label">Passport Copy</label>
              <input type="file" class="form-control" @change="handleNewOwnerFile($event, 'passport_copy')" accept=".jpg,.jpeg,.png,.pdf" />
            </div>

            <!-- Notes -->
            <div class="col-md-12">
              <label class="form-label">Notes</label>
              <textarea v-model="newOwner.notes" rows="3" class="form-control" placeholder="Write notes..."></textarea>
            </div>
          </div>
        </div>

        <div class="modal-footer text-end mt-3">
          <button class="btn btn-secondary me-2" @click="closeAddOwner">Cancel</button>
          <button 
            class="btn btn-primary" 
            @click="submitNewOwner"
            :disabled="isSubmittingOwner"
          >
            <span v-if="isSubmittingOwner">
              <span class="spinner-border spinner-border-sm me-2"></span>
              Saving...
            </span>
            <span v-else>Save Owner</span>
          </button>
        </div>
      </div>
    </div>
    <Teleport to="body">
  <div v-if="showFloorPlanViewer" class="floor-plan-viewer-modal" @click.self="closeFloorPlanViewer">
    <div class="viewer-container">
      <div class="viewer-header">
        <h5 class="viewer-title">
          {{ selectedFloorPlanForViewer?.name }}
          <span v-if="selectedFloorPlanForViewer?.dimensions" class="ms-2 text-muted">
            {{ selectedFloorPlanForViewer.dimensions }}
          </span>
        </h5>
        <button class="btn-close btn-close-white" @click="closeFloorPlanViewer">
          <!--<i class="fas fa-times"></i>-->
        </button>
      </div>
      
      <div class="viewer-body">
        <img 
          :src="selectedFloorPlanForViewer?.image_url" 
          :alt="selectedFloorPlanForViewer?.name"
          class="viewer-image"
          @error="handleFloorPlanImageError"
        />
      </div>
      
      <div class="viewer-footer">
        <div class="viewer-controls">
          <button class="btn btn-light" @click="closeFloorPlanViewer">
            <i class="fas fa-times me-2"></i>
            Close
          </button>
          
        </div>
      </div>
    </div>
  </div>
</Teleport>

  </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted, computed, getCurrentInstance } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "@/plugins/axios";
import vSelect from "vue-select";
import { LISTING_FEATURE_OPTIONS, LISTING_FEATURE_KEYS } from "@/config/listingFeatures";
import draggable from "vuedraggable";
import "vue-select/dist/vue-select.css";
import PaymentDetailsPreviewModal from "@/components/payment-plans/PaymentDetailsPreviewModal.vue";
import AdvancedDatePicker from "@/components/shared/AdvancedDatePicker.vue";
import { parsePriceInputDigits,parseDecimalPrice,formatDecimalPriceDisplay, formatPriceInputDisplay } from "@/utils/priceInputFormat";
import {
  paymentPlanOptions,
  paymentPlanSelectionLabel,
  resolvePaymentPlanOption,
} from "@/composables/listingPaymentPlanPresets";
import {
  useListingPaymentBreakdown,
  NOC_PAID_BELOW_REQUIRED_MSG,
  SELLING_BELOW_OP_WARN_MSG,
  confirmSellingBelowOriginalIfNeeded,
} from "@/composables/useListingPaymentBreakdown";
import {
  useListingAssignmentExpenses,
  parseAssignmentExpenseLines,
  assignmentExpenseTypeOptions,
  assignmentExpenseBaseOptions,
} from "@/composables/useListingAssignmentExpenses";

const route = useRoute();
const router = useRouter();
const { proxy } = getCurrentInstance();
const showPaymentDetailsPreview = ref(false);
const propertyData = ref(null);
// Mode and Loading
const isEditMode = ref(true);
const propertyId = ref(null);
const isLoading = ref(true);
const isSubmitting = ref(false);
const isSubmittingOwner = ref(false);

const rentedStatusOptions = ['Available', 'Rented'];

// Existing Data
const existingGalleryImages = ref([]);
const existingAdditionalDocuments = ref([]);
const currentHeroImage = ref(null);
// Floor Plan Variables
const showProjectFloorPlans = ref(false);
const projectFloorPlans = ref([]);
const totalProjectFloorPlans = ref(0);
const loadingProjectFloorPlans = ref(false);
const currentProjectPage = ref(1);
const activeFloorPlanTab = ref('existing');
const importedFloorPlans = ref([]);
const existingFloorPlans = ref([]);
const newFloorPlans = ref([]); 
const floorPlanTab = ref('existing');


const filteredProjectFloorPlans = ref([]);
const selectedAreaFilter = ref(null);
// Add this to your computed properties section
const isPlotOrLand = computed(() => {
  const plotTypes = ['Plot', 'Land', 'Residential Plot', 'Commercial Plot'];
  if (!form.value.property_type) return false;
  
  const propertyTypeName = form.value.property_type.name || form.value.property_type;
  return plotTypes.some(type => 
    propertyTypeName.toLowerCase().includes(type.toLowerCase())
  );
});
// Floor Plan Computed Properties
const totalFloorPlans = computed(() => {
  return existingFloorPlans.value.length + 
         importedFloorPlans.value.length + 
         form.value.floorPlans.length;
});

const importedFloorPlansCount = computed(() => {
  return importedFloorPlans.value.length;
});




const canShowOwnerSection = computed(() => {
  return (propertyData.value && propertyData.value.canShowOwner === true ) || (form.value && form.value.canShowOwner === true);
});



// Projects and Areas
const projects = ref([]);
const isLoadingProjects = ref(false);
const selectedProject = ref(null);
const areaSelectKey = ref(0);


const showFloorPlanViewer = ref(false);
const selectedFloorPlanForViewer = ref(null);

const openFloorPlanViewer = (floorPlan) => {
  selectedFloorPlanForViewer.value = floorPlan;
  showFloorPlanViewer.value = true;
};

const closeFloorPlanViewer = () => {
  showFloorPlanViewer.value = false;
  selectedFloorPlanForViewer.value = null;
};

// Options for v-select fields
const saleRentOptions = ['Sale', 'Rent'];
const completionStatusOptions = ['Completed', 'Under Construction'];
const furnishedStatusOptions = ['Furnished', 'Unfurnished'];
const ownershipTypeOptions = ['freehold', 'leasehold'];
const mortgageStatusOptions = ['Mortgage', 'Non Mortgage'];
const occupancyStatusOptions = [  'Owner Occupied',
  'Holiday Home',
  'Rented',
  'Vacant'];
const salutationOptions = ['Mr', 'Mrs', 'Ms'];
const residencyStatusOptions = [
  { value: 'resident', label: 'Resident' },
  { value: 'non_resident', label: 'Non Resident' }
];
/** Hide mortgage amount/comment when Non-Mortgaged (create form uses 'Non-Mortgaged', edit uses 'Non Mortgage') */
const isNonMortgaged = computed(() => {
  const s = form.value.mortgageStatus || '';
  return s === 'Non Mortgage' || s === 'Non-Mortgaged';
});

/** Minimum date for rent expiry (today) – no backdated entries */
const minRentExpiryDate = computed(() => {
  const d = new Date();
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
});
// Bedroom and bathroom options
const bedroomOptions = [
    { label: "Studio", value: 0 },
  { label: "1", value: 1 },
  { label: "2", value: 2 },
  { label: "3", value: 3 },
  { label: "4", value: 4 },
  { label: "5", value: 5 },
  { label: "6", value: 6 },
  { label: "7", value: 7 },
  { label: "8", value: 8 },
  { label: "9", value: 9 },
  { label: "10+", value: 10 }
];

const bathroomOptions = [
  { label: "1", value: 1 },
  { label: "2", value: 2 },
  { label: "3", value: 3 },
  { label: "4", value: 4 },
  { label: "5", value: 5 },
  { label: "6", value: 6 },
  { label: "7", value: 7 },
  { label: "8", value: 8 },
  { label: "9", value: 9 },
  { label: "10+", value: 10 }
];

// Nationalities
const nationalities = ref([
  "Afghanistan","Albania","Algeria","Andorra","Angola","Antigua and Barbuda","Argentina","Armenia","Australia","Austria",
  "Azerbaijan","Bahamas","Bahrain","Bangladesh","Barbados","Belarus","Belgium","Belize","Benin","Bhutan",
  "Bolivia","Bosnia and Herzegovina","Botswana","Brazil","Brunei","Bulgaria","Burkina Faso","Burundi",
  "Cabo Verde","Cambodia","Cameroon","Canada","Central African Republic","Chad","Chile","China","Colombia",
  "Comoros","Congo (Congo-Brazzaville)","Costa Rica","Croatia","Cuba","Cyprus","Czechia","Denmark",
  "Djibouti","Dominica","Dominican Republic","Ecuador","Egypt","El Salvador","Equatorial Guinea","Eritrea",
  "Estonia","Eswatini","Ethiopia","Fiji","Finland","France","Gabon","Gambia","Georgia","Germany","Ghana",
  "Greece","Grenada","Guatemala","Guinea","Guinea-Bissau","Guyana","Haiti","Honduras","Hungary","Iceland",
  "India","Indonesia","Iran","Iraq","Ireland","Israel","Italy","Jamaica","Japan","Jordan","Kazakhstan",
  "Kenya","Kiribati","Kuwait","Kyrgyzstan","Laos","Latvia","Lebanon","Lesotho","Liberia","Libya",
  "Liechtenstein","Lithuania","Luxembourg","Madagascar","Malawi","Malaysia","Maldives","Mali","Malta",
  "Marshall Islands","Mauritania","Mauritius","Mexico","Micronesia","Moldova","Monaco","Mongolia",
  "Montenegro","Morocco","Mozambique","Myanmar","Namibia","Nauru","Nepal","Netherlands","New Zealand",
  "Nicaragua","Niger","Nigeria","North Korea","North Macedonia","Norway","Oman","Pakistan","Palau",
  "Palestine","Panama","Papua New Guinea","Paraguay","Peru","Philippines","Poland","Portugal","Qatar",
  "Romania","Russia","Rwanda","Saint Kitts and Nevis","Saint Lucia","Saint Vincent and the Grenadines",
  "Samoa","San Marino","Sao Tome and Principe","Saudi Arabia","Senegal","Serbia","Seychelles",
  "Sierra Leone","Singapore","Slovakia","Slovenia","Solomon Islands","Somalia","South Africa","South Korea",
  "South Sudan","Spain","Sri Lanka","Sudan","Suriname","Sweden","Switzerland","Syria","Taiwan","Tajikistan",
  "Tanzania","Thailand","Timor-Leste","Togo","Tonga","Trinidad and Tobago","Tunisia","Turkey",
  "Turkmenistan","Tuvalu","Uganda","Ukraine","United Arab Emirates","United Kingdom","United States",
  "Uruguay","Uzbekistan","Vanuatu","Vatican City","Venezuela","Vietnam","Yemen","Zambia","Zimbabwe"
]);

const getAgentId = () => {
  try {
    const userData = localStorage.getItem('user');
    if (userData) {
      const user = JSON.parse(userData);
      return user.id;
    }
    return null;
  } catch (error) {
    console.error('❌ Error parsing user data from localStorage:', error);
    return null;
  }
};

const agentId = computed(() => {
  return getAgentId();
});

// Owners from API
const owners = ref([]);
const selectedOwner = ref(null);
const showAddOwner = ref(false);
const isLoadingOwners = ref(false);

// Unit Views from API
const unitViews = ref([]);
const isLoadingUnitViews = ref(false);

// Layout Types from API
const layoutTypes = ref([]);
const isLoadingLayoutTypes = ref(false);

// Property Types from API
const propertyTypes = ref([]);
const isLoadingPropertyTypes = ref(false);

// Areas from API
const areas = ref([]);
const isLoadingAreas = ref(false);
const hotDealOptions = ['Yes', 'No'];
const installmentTypeOptions = [
  { label: 'Percentage', value: 'percentage' },
  { label: 'Amount', value: 'amount' },
];
const nocPercentageOptions = [
  { label: '0', value: 0 },
  { label: '10', value: 10 },
  { label: '20', value: 20 },
  { label: '30', value: 30 },
  { label: '40', value: 40 },
  { label: '50', value: 50 },
];

const selectedProjectFloorPlan = ref(null);
const uploadedFloorPlan = ref(null);

// Shared source of truth — see resources/js/config/listingFeatures.js and
// ListingResource::FEATURE_LABELS for the matching backend list.
const listingFeatureOptions = LISTING_FEATURE_OPTIONS;
const form = ref({
  title: "",
  unit_number: "",
  old_area:"",
  ownership_type: "",
  saleOrRent: "",
  completionStatus: "",
  area: null,
  property_type: null,
  price: "",
  number_of_bedrooms: "",
  number_of_bathrooms: "",
  layout_type: null,
  unit_view: null,
  furnished_status: "",
  size_sqmt: "",
  size_sqft: "",
  floorPlans: [],
  gallery: [],
  comment: "",
  mortgageStatus: "",
  occupancyStatus: "",
  mortgageAmount: "",
  rentExpiryDate: "",
  rentAmount: "",
  mortgageComment: "",
  projectAreas: [], 
  rented_status: "",
  rented_until: "",
  payment_plans: null,
  payment_plan: "",
   is_hot_deal: "",
  // Boolean for every known feature (from the shared LISTING_FEATURE_KEYS list).
  ...LISTING_FEATURE_KEYS.reduce((acc, k) => { acc[k] = false; return acc; }, {}),
  project_id: null,
  original_price: "",
  handover_date: "",
  noc_percentage: 0,
   noc_fixed_amount: 0,
});
// ✅ جلب تكاليف الصفقة الثابتة
const dealCostSettings = ref([]);
const isLoadingDealCosts = ref(false);

const fetchDealCosts = async () => {
  try {
    isLoadingDealCosts.value = true;
    const response = await api.get("/settings/deal-costs");
    const data = response.data.data || response.data;
    
    console.log('📦 Deal cost API response:', data);
    
    // Store settings properly
    if (data.settings) {
      const settings = {};
      Object.keys(data.settings).forEach(key => {
        const numKey = parseInt(key) || key;
        settings[numKey] = data.settings[key];
      });
      dealCostSettings.value = settings;
    } else if (data.details) {
      const settings = {};
      data.details.forEach(item => {
        settings[item.key] = item.value;
      });
      dealCostSettings.value = settings;
    } else {
      dealCostSettings.value = data;
    }
    
    console.log('✅ Deal cost settings loaded:', dealCostSettings.value);
    
    // The default costs will be added by loadAssignmentExpenseLines
    
  } catch (error) {
    console.error('❌ Error fetching deal costs:', error);
    // Use default values
    dealCostSettings.value = {
      dari_admin_fee: 0,
      adgm_admin_fee: 0
    };
    proxy.$showNotification("ℹ️ Using default deal costs (0). You can edit them manually.", "info");
  } finally {
    isLoadingDealCosts.value = false;
  }
};
/** Matches v-select value and common API spellings so the payment / NOC block can show after load. */
const isUnderConstruction = computed(() => {
  const s = String(form.value.completionStatus ?? '').trim().toLowerCase().replace(/_/g, ' ');
  return s === 'under construction' || s === 'off plan';
});

const breakdownInstallments = ref([]);
/** Installment row ids that were already due/paid when the listing was loaded (edit only); may keep past dates. */
const breakdownPaidOnLoadIds = ref(new Set());
const installmentDraft = ref({
  type: 'percentage',
  value: null,
  date: new Date().toISOString().slice(0, 10),
});

const {
  selectedPaymentPlanLabel,
  initialPercentForm,
  installmentPercentForm,
  originalPriceNum,
  originalContractPriceNum,
  sellingPriceNum,
  premiumAmountForm,
  premiumIsNegative,
  premiumDisplayAed,
  sellingBelowOriginalActive,
  initialPaymentTarget,
  ucTrancheAed,
  handoverAmountForm,
  installmentToAmount,
  scheduledInstallmentsAed,
  paidAmountForm,
  paidPercentOfOp,
  nocPercentOfOp,
  nocRequiredAed,
  nocRequirementMet,
  nocRemainingAed,
  nocProgressBarPct,
  nocProgressPaidLabel,
  nocRequirementWarningActive,
  breakdownSellingPriceMismatchActive,
  breakdownSellingDeltaMessage,
  mixedInstallmentTypesError,
  percentageInstallmentPlanMismatchError,
  paymentHandoverDateError,
  paymentPlanFieldInvalid,
  paymentPlanFieldError,
  paymentBreakdownRows,
  breakdownRowStatusClass,
  publishPaymentBreakdownBlocked,
  publishPaymentBreakdownBlockTitle,
  paymentBreakdownValidationSummary,
  formatAed,
  formatDateShort, nocFixedAmount,
  
} = useListingPaymentBreakdown({
  form,
  breakdownInstallments,
  installmentDraft,
  isUnderConstruction,
  breakdownPaidOnLoadIds,
  
});

const dealCostSettingsComputed = computed(() => dealCostSettings.value);

const {
  assignmentExpenseLines,
  assignmentExpenseDraft,
  assignmentExpenseLineAmount,
  assignmentExpenseLineVat,
  assignmentExpenseLineTotal,
  assignmentExpensesSubtotal,
  assignmentExpensesTotalVat,
  assignmentExpensesGrandTotal,
  loadAssignmentExpenseLines,
  addAssignmentExpenseLine,
  removeAssignmentExpenseLine,
  nocFixedAmount: nocFixedAmountFromComposable,
  addDefaultDealCosts,
  
} = useListingAssignmentExpenses({
  
  originalPriceNum,
  sellingPriceNum,
  premiumAmountForm,
  formatAed,
   nocFixedAmount,
   dealCostSettings,
});
// const nocFixedAmount = computed(() => {
//   return Number(form.value.noc_fixed_amount || 0);
// });

const currentNocType = computed(() => {
  const status = String(form.value.completionStatus || '').trim().toLowerCase().replace(/_/g, ' ');
  const isUC = status === 'under construction' || status === 'off plan';
  return isUC ? 'Off-Plan' : 'Ready';
});

const developerNocValue = computed(() => {
  if (!selectedProject.value || !selectedProject.value.developer) return 0;
  
  const status = String(form.value.completionStatus || '').trim().toLowerCase().replace(/_/g, ' ');
  const isUC = status === 'under construction' || status === 'off plan';
  
  if (isUC) {
    return Number(selectedProject.value.developer.noc_fees_off_plan || 0);
  } else {
    return Number(selectedProject.value.developer.noc_fees_ready || 0);
  }
});

const isNocEnabled = computed(() => {
  if (!selectedProject.value) return false;
  if (!selectedProject.value.developer) return false;
  
  const status = String(form.value.completionStatus || '').trim().toLowerCase().replace(/_/g, ' ');
  const isUC = status === 'under construction' || status === 'off plan';
  const isReady = status === 'completed';
  
  if (!isUC && !isReady) return false;
  
  return true;
});

const isNocAutoPopulated = computed(() => {
  if (!isNocEnabled.value) return false;
  return developerNocValue.value > 0;
});

const showNocField = computed(() => {
  return isNocEnabled.value;
});
const onAddAssignmentExpenseLine = () => {
  addAssignmentExpenseLine((msg) => proxy.$showNotification(msg, 'error'));
};

const nocRemainingPctOfOp = computed(() => {
  const op = originalPriceNum.value;
  if (op <= 0) return 0;
  return (nocRemainingAed.value / op) * 100;
});

const paymentBreakdownTableTotals = computed(() => {
  const rows = paymentBreakdownRows.value;
  let percentTotal = 0;
  let amountTotal = 0;
  let hasPercent = false;
  for (const row of rows) {
    amountTotal += Number(row.amount || 0);
    if (row.type === 'Premium') continue;
    const p = parseFloat(row.percentage);
    if (Number.isFinite(p)) {
      percentTotal += p;
      hasPercent = true;
    }
  }
  return {
    percentTotal: hasPercent ? percentTotal.toFixed(2) : '—',
    amountTotal,
    rowCount: rows.length,
  };
});
const updateNocBasedOnStatus = () => {
  const status = String(form.value.completionStatus || '').trim().toLowerCase().replace(/_/g, ' ');
  const isUC = status === 'under construction' || status === 'off plan';
  const isReady = status === 'completed';
  
  console.log('🔄 updateNocBasedOnStatus called with status:', status);
  console.log('📊 Selected project:', selectedProject.value);
  
  if (!selectedProject.value || !selectedProject.value.developer) {
    console.log('⚠️ No project or developer selected');
    form.value.noc_fixed_amount = 0;
    return;
  }
  
  let nocValue = 0;
  
  if (isUC) {
    nocValue = Number(selectedProject.value.developer.noc_fees_off_plan || 0);
    console.log(`✅ Using Off-Plan NOC: ${formatAed(nocValue)}`);
  } else if (isReady) {
    nocValue = Number(selectedProject.value.developer.noc_fees_ready || 0);
    console.log(`✅ Using Ready NOC: ${formatAed(nocValue)}`);
    
    // عند Completed، تنظيف بيانات الدفع
    form.value.payment_plans = null;
    form.value.payment_plan = null;
    breakdownInstallments.value = [];
    assignmentExpenseLines.value = [];
    form.value.handover_date = '';
    // form.value.original_price = '';
  } else {
    console.log('⚠️ Unknown status, setting NOC to 0');
  }
  
  form.value.noc_fixed_amount = !isNaN(nocValue) && nocValue >= 0 ? nocValue : 0;
  console.log('✅ NOC fixed amount set to:', form.value.noc_fixed_amount);
};
const startOfDay = (value) => {
  const d = value instanceof Date ? new Date(value.getTime()) : new Date(value);
  if (Number.isNaN(d.getTime())) return d;
  return new Date(d.getFullYear(), d.getMonth(), d.getDate());
};

const isDatePaid = (dateLike) => {
  if (!dateLike) return false;
  const paymentDate = startOfDay(dateLike);
  if (Number.isNaN(paymentDate.getTime())) return false;
  return paymentDate.getTime() <= startOfDay(new Date()).getTime();
};

const INSTALLMENT_DATE_PAST_MSG = 'Installment date cannot be in the past.';

const captureBreakdownPaidOnLoadIds = (entries) => {
  const s = new Set();
  const today = startOfDay(new Date());
  for (const e of entries) {
    const d = startOfDay(e.date);
    if (!Number.isNaN(d.getTime()) && d.getTime() <= today.getTime()) {
      s.add(String(e.id));
    }
  }
  breakdownPaidOnLoadIds.value = s;
};

/** Edit: past dates allowed only for rows that were paid/due on load; new draft rows must be today or later. */
const getBreakdownInstallmentDateError = () => {
  if (!isUnderConstruction.value) return '';
  const today = startOfDay(new Date());
  for (const entry of breakdownInstallments.value) {
    if (!entry?.date) continue;
    const d = startOfDay(entry.date);
    if (Number.isNaN(d.getTime())) continue;
    if (d.getTime() < today.getTime()) {
      if (breakdownPaidOnLoadIds.value.has(String(entry.id))) continue;
      return INSTALLMENT_DATE_PAST_MSG;
    }
  }
  const draftDate = installmentDraft.value?.date;
  if (draftDate) {
    const d = startOfDay(draftDate);
    if (!Number.isNaN(d.getTime()) && d.getTime() < today.getTime()) return INSTALLMENT_DATE_PAST_MSG;
  }
  return '';
};

const paymentBreakdownInstallmentDateError = computed(() => getBreakdownInstallmentDateError());

const DUPLICATE_INSTALLMENT_DATE_MSG = 'Multiple installments share the same due date.';
/** Warning only (does not block submit). Compares due dates via `startOfDay`. Future: optional auto-merge of same-date rows. */
const getBreakdownDuplicateInstallmentDateWarning = () => {
  if (!isUnderConstruction.value) return '';
  const dayCounts = new Map();
  for (const entry of breakdownInstallments.value) {
    if (!entry?.date) continue;
    const d = startOfDay(entry.date);
    if (Number.isNaN(d.getTime())) continue;
    const k = d.getTime();
    dayCounts.set(k, (dayCounts.get(k) || 0) + 1);
  }
  for (const n of dayCounts.values()) {
    if (n > 1) return DUPLICATE_INSTALLMENT_DATE_MSG;
  }
  return '';
};

const paymentBreakdownDuplicateInstallmentDateWarning = computed(() =>
  getBreakdownDuplicateInstallmentDateWarning(),
);

/** Max span from earliest→latest installment, or distance from today→latest due, before warning (years). */
const PAYMENT_PLAN_DURATION_WARN_YEARS = 10;
const PAYMENT_PLAN_DURATION_LONG_MSG = 'Payment plan duration appears unusually long.';
const MS_PER_YEAR_APPROX = 86400000 * 365.25;

/** Warning only. Flags schedules longer than `PAYMENT_PLAN_DURATION_WARN_YEARS` or latest due beyond that horizon from today. */
const getPaymentPlanDurationWarning = () => {
  if (!isUnderConstruction.value) return '';
  const rawDates = [];
  for (const entry of breakdownInstallments.value) {
    if (entry?.date) rawDates.push(entry.date);
  }
  if (installmentDraft.value?.date) rawDates.push(installmentDraft.value.date);
  if (!rawDates.length) return '';

  let minTs = null;
  let maxTs = null;
  for (const raw of rawDates) {
    const d = startOfDay(raw);
    if (Number.isNaN(d.getTime())) continue;
    const t = d.getTime();
    if (minTs === null || t < minTs) minTs = t;
    if (maxTs === null || t > maxTs) maxTs = t;
  }
  if (minTs === null || maxTs === null) return '';

  const limitMs = PAYMENT_PLAN_DURATION_WARN_YEARS * MS_PER_YEAR_APPROX;
  if (maxTs - minTs > limitMs) return PAYMENT_PLAN_DURATION_LONG_MSG;
  const todayTs = startOfDay(new Date()).getTime();
  if (maxTs - todayTs > limitMs) return PAYMENT_PLAN_DURATION_LONG_MSG;
  return '';
};

const paymentPlanDurationWarning = computed(() => getPaymentPlanDurationWarning());

const PERCENTAGE_EXCEEDS_CAP_MSG = 'Percentage cannot exceed 100%.';

const isBreakdownPercentageType = (t) => String(t || '') === 'percentage';

const getBreakdownPercentageCapError = () => {
  if (!isUnderConstruction.value) return '';
  for (const entry of breakdownInstallments.value) {
    if (!isBreakdownPercentageType(entry?.type)) continue;
    const v = Number(entry.value);
    if (Number.isFinite(v) && v > 100) return PERCENTAGE_EXCEEDS_CAP_MSG;
  }
  if (isBreakdownPercentageType(installmentDraft.value?.type)) {
    const v = Number(installmentDraft.value.value);
    if (Number.isFinite(v) && v > 100) return PERCENTAGE_EXCEEDS_CAP_MSG;
  }
  return '';
};

const paymentBreakdownPercentageCapError = computed(() => getBreakdownPercentageCapError());

const addBreakdownInstallment = () => {
  const value = Number(installmentDraft.value.value || 0);
  if (!value || value <= 0) {
    proxy.$showNotification('Please enter a valid installment value', 'error');
    return;
  }
  if (isBreakdownPercentageType(installmentDraft.value.type) && value > 100) {
    proxy.$showNotification(PERCENTAGE_EXCEEDS_CAP_MSG, 'error');
    return;
  }
  if (!installmentDraft.value.date) {
    proxy.$showNotification('Please select installment date', 'error');
    return;
  }
  const draftDay = startOfDay(installmentDraft.value.date);
  const today = startOfDay(new Date());
  if (!Number.isNaN(draftDay.getTime()) && draftDay.getTime() < today.getTime()) {
    proxy.$showNotification(INSTALLMENT_DATE_PAST_MSG, 'error');
    return;
  }
  const newEntry = {
    id: Date.now(),
    type: installmentDraft.value.type,
    value,
    date: installmentDraft.value.date,
  };
  const newAmount = installmentToAmount(newEntry);
  const currentInstallmentTotal = breakdownInstallments.value.reduce(
    (sum, entry) => sum + installmentToAmount(entry),
    0,
  );
  if (currentInstallmentTotal + newAmount > initialPaymentTarget.value) {
    proxy.$showNotification('Installment exceeds under-construction amount', 'error');
    return;
  }
  breakdownInstallments.value.push(newEntry);
  installmentDraft.value.value = null;
};

const removeBreakdownInstallment = (entryId) => {
  breakdownInstallments.value = breakdownInstallments.value.filter((entry) => entry.id !== entryId);
};

// Computed Properties
const combinedGallery = computed(() => {
  return [...existingGalleryImages.value, ...form.value.gallery];
});

/** Writable mirror used by <draggable v-model="combinedGalleryModel">. The
 *  setter receives the reordered combined list and splits it back into the two
 *  source arrays (existing vs new) while preserving the new display order. */
const combinedGalleryModel = computed({
  get: () => combinedGallery.value,
  set: (ordered) => {
    const nextExisting = [];
    const nextNew = [];
    ordered.forEach((item, idx) => {
      const sortOrder = idx + 1;
      if (item && item.id) {
        nextExisting.push({ ...item, order: sortOrder, sort_order: sortOrder });
      } else {
        nextNew.push(item);
      }
    });
    existingGalleryImages.value = nextExisting;
    form.value.gallery = nextNew;
  },
});

const totalGalleryCount = computed(() => {
  return existingGalleryImages.value.length + form.value.gallery.length;
});

const filteredAreas = computed(() => {
  if (selectedProject.value && form.value.projectAreas.length > 0) {
    return form.value.projectAreas;
  }
  return areas.value.filter(area => area.children_count === 0);
});

// New Owner Data
const newOwner = ref({
  salutation: "",
  first_name: "",
  last_name: "",
  email: "",
  phone_number: "",
  whatsapp_number: "",
  second_phone_number: "",
  nationality: "",
  residency_status: "",
  location_id: "",
  id_front: null,
  id_back: null,
  visa_copy: null,
  passport_copy: null,
  notes: "",
});

// Locations for residency status
const locations = ref([]);
const isLoadingLocations = ref(false);

// Hero image functions
const isSettingHero = ref(false);

// Watch for saleOrRent changes to handle rented_status logic
watch(() => form.value.saleOrRent, (newValue) => {
  if (newValue === 'Sale') {
    form.value.rented_status = "";
    form.value.rented_until = "";
  }
});
watch(() => form.value.saleOrRent, (newValue) => {
  if (newValue === 'Sale') {
    form.value.rented_status = "";
    form.value.rented_until = "";
    
    // If sale and completed, clear payment plans
    if (form.value.completionStatus === 'Completed') {
      form.value.payment_plans = null;
      form.value.payment_plan = null;
    }
  }
});
watch(() => form.value.completionStatus, (newStatus) => {
  const s = String(newStatus ?? '').trim().toLowerCase().replace(/_/g, ' ');
  const isUC = s === 'under construction' || s === 'off plan';
  const isComp = s === 'completed';

  if (isUC) {
    form.value.occupancyStatus = '';
    form.value.rentExpiryDate = '';
    form.value.rentAmount = '';
    updateNocBasedOnStatus();

    console.log('🏗️ Property under construction - occupancy status cleared');
  } else if (isComp) {
    form.value.payment_plans = null;
    form.value.payment_plan = null;
    breakdownInstallments.value = [];
    breakdownPaidOnLoadIds.value = new Set();
    // assignmentExpenseLines.value = [];
    form.value.handover_date = '';
    form.value.noc_percentage = 0;
    // form.value.original_price = '';
    updateNocBasedOnStatus();
    
  
  }
});

// Watch for payment_plans changes to convert to payment_plan string (single plan as JSON array)
watch(() => form.value.payment_plans, (newValue) => {
  const label = paymentPlanSelectionLabel(newValue);
  form.value.payment_plan = label ? JSON.stringify([label]) : null;
}, { deep: true });
watch(() => form.value.area, (newArea) => {
  console.log('🔄 Area changed in form:', newArea);
  
  if (!selectedProject.value) {
    console.log('⚠️ No project selected, cannot filter floor plans');
    filteredProjectFloorPlans.value = projectFloorPlans.value;
    return;
  }
  
  if (!newArea || !newArea.id) {
    filteredProjectFloorPlans.value = projectFloorPlans.value;
    console.log('Showing all floor plans (no area selected):', filteredProjectFloorPlans.value.length);
  } else {
      // ✅ التصحيح: للمشروع 1788 لا تطبق الفلترة
      if(form.value.project_id != 1788 || form.value.project_id != 1833){
        filterFloorPlansByArea(newArea.id);
      } else {
        filteredProjectFloorPlans.value = projectFloorPlans.value;
        console.log('Project 1788: Showing all floor plans without area filter');
      }
    
    // شيل الفلور بلانز المستوردة اللي مش في المنطقة الجديدة
    importedFloorPlans.value = importedFloorPlans.value.filter(plan => {
      if (plan.project_floor_plan_id) {
        const originalPlan = projectFloorPlans.value.find(p => p.id === plan.project_floor_plan_id);
        if (originalPlan && originalPlan.area_id !== newArea.id && form.value.project_id != 1788 && form.value.project_id != 1833) {
          return false;
        }
      }
      return true;
    });
  }
}, { immediate: true, deep: true });

// Fetch projects from API
const fetchProjects = async () => {
  try {
    isLoadingProjects.value = true;
    const response = await api.get("/listings/projects");
    
    const projectsData = response.data.data || response.data;
    
    projects.value = projectsData.map(project => ({
      id: project.id,
      name: project.title || project.name,
      area: project.area,
      area_id: project.area_id,
         developer: project.developer 
    }));
    
    console.log('✅ Projects loaded:', projects.value);
  } catch (error) {
    console.error("❌ Error fetching projects:", error.response || error);
    proxy.$showNotification("❌ Failed to load projects. Please try again.", "error");
  } finally {
    isLoadingProjects.value = false;
  }
};

// Fetch property data for editing
const fetchPropertyData = async (id) => {
  try {
    isLoading.value = true;
    console.log('🔄 Fetching property data for edit, ID:', id);
    
    const response = await api.get(`/listings/properties/${id}`);
    const propertyData = response.data.data || response.data;
    
    console.log('📥 Property data loaded for editing:', propertyData);
    
    console.log('🔍 API returned fields:', {
      payment_plan: propertyData.payment_plan_json,
      payment_plan_raw: propertyData.payment_plan,
      rented_status: propertyData.rented_status,
      rented_until: propertyData.rented_until,
       drive_link: propertyData.drive_link
    });
    
    await Promise.all([
      fetchPropertyTypes(),
      fetchAreas(),
      fetchOwners(),
      fetchProjects()
    ]);
    
    // ✅ تأكد من وجود التكاليف الافتراضية
    // حتى لو كانت هناك بيانات من الخادم، تأكد من وجود التكاليف الافتراضية
    const hasDari = assignmentExpenseLines.value.some(
      line => line.label === 'Dari Admin Fee'
    );
    const hasAdgm = assignmentExpenseLines.value.some(
      line => line.label === 'ADGM Admin Fee'
    );
    
    
    let matchedProject = null;
    if (propertyData.project && propertyData.project.id) {
      matchedProject = projects.value.find(p => p.id === propertyData.project.id);
    }
        existingFloorPlans.value = propertyData.floor_plans || [];

     if (propertyData.imported_floor_plans) {
        importedFloorPlans.value = propertyData.imported_floor_plans.map(plan => ({
          ...plan,
          editName: plan.name
        }));
      }
    
   
    selectedProject.value = matchedProject;
    
    if (selectedProject.value) {
      try {
        const response = await api.get(`/listings/projects/${selectedProject.value.id}/areas`);
        const projectAreasData = response.data.data || response.data;
        
        form.value.projectAreas = projectAreasData.map(area => ({
          id: area.id,
          name: area.area_parents_title || area.name || area.title,
          project_id: selectedProject.value.id
        }));
        
        console.log(`✅ Loaded ${form.value.projectAreas.length} areas for project:`, selectedProject.value.name);
      } catch (error) {
        console.error('❌ Error fetching project areas:', error);
        form.value.projectAreas = [];
      }
    }
    
    const matchedPropertyType = propertyTypes.value.find(pt => pt.id === propertyData.property_type?.id);
    const matchedArea = areas.value.find(a => a.id === propertyData.area?.id);
    
    const matchedLayoutType = propertyData.layout_type ? 
      layoutTypes.value.find(lt => lt.id === propertyData.layout_type.id) : null;
    
    const matchedUnitView = propertyData.unit_view ? 
      unitViews.value.find(uv => uv.id === propertyData.unit_view.id) : null;
    
    let loadedPaymentPlanValue = null;
    let paymentPlanString = '';
    
    const paymentPlanField = propertyData.payment_plan_json || 
                            propertyData.payment_plan || 
                            propertyData.payment_plan_string;
    
    console.log('💳 Payment plan field found:', paymentPlanField);
    console.log('💳 Type of payment plan:', typeof paymentPlanField);
    
    if (paymentPlanField) {
      try {
        let planStrings = [];
        if (typeof paymentPlanField === 'string' && paymentPlanField.trim().startsWith('[')) {
          const parsedPlans = JSON.parse(paymentPlanField);
          if (Array.isArray(parsedPlans)) {
            planStrings = parsedPlans.map((plan) => String(plan)).filter(Boolean);
            console.log('✅ Parsed payment plans array (first used):', planStrings);
          }
        } else if (typeof paymentPlanField === 'string') {
          planStrings = [paymentPlanField];
          console.log('✅ Single payment plan string:', planStrings);
        } else if (Array.isArray(paymentPlanField)) {
          planStrings = paymentPlanField.map((plan) => String(plan)).filter(Boolean);
          console.log('✅ Direct payment plans array (first used):', planStrings);
        }
        if (planStrings.length > 0) {
          loadedPaymentPlanValue = planStrings[0];
          paymentPlanString = JSON.stringify([planStrings[0]]);
        }
      } catch (e) {
        console.error('❌ Error parsing payment plan:', e);
        const fallback = String(paymentPlanField);
        loadedPaymentPlanValue = fallback;
        paymentPlanString = JSON.stringify([fallback]);
      }
    }
    
    const rentedStatus = propertyData.rented_status;
    
    const rentedUntil = propertyData.rented_until;
    
    console.log('🏠 Rented fields:', {
      rented_status: rentedStatus,
      rented_until: rentedUntil
    });
     const driveLink = propertyData.drive_link || "";

    let loadedBreakdown = [];
    const rawPb = propertyData.payment_breakdown;
    if (rawPb) {
      try {
        const arr = Array.isArray(rawPb) ? rawPb : JSON.parse(rawPb);
        if (Array.isArray(arr)) {
          loadedBreakdown = arr.map((row, i) => ({
            id: row.id != null ? Number(row.id) : Date.now() + i,
            type: row.type === 'amount' ? 'amount' : 'percentage',
            value: Number(row.value || 0),
            date: (row.date && String(row.date).slice(0, 10)) || new Date().toISOString().slice(0, 10),
          }));
        }
      } catch (e) {
        console.warn('payment_breakdown load failed', e);
      }
    }
    breakdownInstallments.value = loadedBreakdown;
    captureBreakdownPaidOnLoadIds(loadedBreakdown);
      // In fetchPropertyData function, when loading property data:
     if (propertyData.assignment_expense_lines) {
        loadAssignmentExpenseLines(propertyData.assignment_expense_lines);
      } else {
        // This will load default costs
        loadAssignmentExpenseLines([]);
      }
    form.value = {
      ...form.value,
      title: propertyData.title || "",
      unit_number: propertyData.unit_number || "",
      old_area:propertyData.old_area || "",
      ownership_type: propertyData.ownership_type || "",
      saleOrRent: propertyData.listing_status || "",
      completionStatus: propertyData.completion_status || "",
      area: matchedArea || null,
      property_type: matchedPropertyType || null,
      price: String(propertyData.price ?? propertyData.selling_price ?? ''),
      number_of_bedrooms: propertyData.number_of_bedrooms || "",
      number_of_bathrooms: propertyData.number_of_bathrooms || "",
      layout_type: matchedLayoutType || null,
      unit_view: matchedUnitView || null,
      furnished_status: propertyData.furnished_status || "",
      size_sqmt: propertyData.size_sqmt || "",
      size_sqft: propertyData.size_sqft || "",
      comment: propertyData.comment || "",
      mortgageStatus: propertyData.mortgage_status || "",
      occupancyStatus: propertyData.occupancy_status || "",
      mortgageAmount: propertyData.mortgage_amount || "",
      rentExpiryDate: propertyData.rent_expiry_date || "",
      rentAmount: propertyData.rent_amount || "",
      mortgageComment: propertyData.mortgage_comment || "",
      rented_status: rentedStatus || "",
      rented_until: rentedUntil || "",
      drive_link: driveLink,
      is_hot_deal:propertyData.is_hot_deal,
      payment_plans: loadedPaymentPlanValue
        ? (resolvePaymentPlanOption(loadedPaymentPlanValue) ?? loadedPaymentPlanValue)
        : null,
      payment_plan: paymentPlanString || "",
       canShowOwner: propertyData.canShowOwner ,
      original_price: propertyData.original_price != null && propertyData.original_price !== ''
        ? String(Math.round(Number(propertyData.original_price))) : '',
      handover_date: propertyData.handover_date
        ? String(propertyData.handover_date).slice(0, 10) : '',
      noc_percentage: [0, 10, 20, 30, 40, 50].includes(Number(propertyData.noc_percentage))
        ? Number(propertyData.noc_percentage) : 0,
         noc_fixed_amount: propertyData.noc_fixed_amount || 0,
      noc_type: propertyData.noc_type || 'Off-Plan',
      noc_fees_ready: propertyData.noc_fees_ready || 0,
      noc_fees_off_plan: propertyData.noc_fees_off_plan || 0,
    };
    
    // Load additional features generically — walks the shared key list so any
    // new feature added to LISTING_FEATURE_KEYS shows up here automatically.
    if (propertyData.additional_features) {
      const isTruthy = (v) => v === true || v === 1 || v === '1';
      LISTING_FEATURE_KEYS.forEach((k) => {
        form.value[k] = isTruthy(propertyData.additional_features[k]);
      });
    }
          
    existingFloorPlans.value = propertyData.floor_plans || [];
    existingGalleryImages.value = propertyData.gallery_images || [];
    existingAdditionalDocuments.value = propertyData.additional_documents || [];
    currentHeroImage.value = propertyData.hero_image || propertyData.main_image || null;

    if (propertyData.owner) {
      selectedOwner.value = {
        id: propertyData.owner.id,
        full_name: propertyData.owner.full_name || `${propertyData.owner.first_name} ${propertyData.owner.last_name}`,
        first_name: propertyData.owner.first_name,
        last_name: propertyData.owner.last_name,
        email: propertyData.owner.email,
        phone_number: propertyData.owner.phone_number,
        whatsapp_number: propertyData.owner.whatsapp_number,
        second_phone_number: propertyData.owner.second_phone_number
      };
    }
    
    console.log('✅ Form data after mapping:', form.value);
    console.log('🔍 New fields loaded successfully:', {
      rented_status: form.value.rented_status,
      rented_until: form.value.rented_until,
      payment_plans: form.value.payment_plans,
      payment_plan: form.value.payment_plan,
      is_hot_deal: form.value.is_hot_deal
    });
    
    console.log('🔍 v-select data check:', {
      paymentPlanOptions: paymentPlanOptions.length,
      payment_plan_label: paymentPlanSelectionLabel(form.value.payment_plans)
    });
    
    proxy.$showNotification("✅ Property data loaded successfully!", "success");
    
  } catch (error) {
    console.error("❌ Error fetching property data:", error.response || error);
    
    if (error.response) {
      console.error('❌ Response data:', error.response.data);
      console.error('❌ Response status:', error.response.status);
    }
    
    proxy.$showNotification("❌ Failed to load property data.", "error");
    router.back();
  } finally {
    isLoading.value = false;
  }
};
// Drive Link Functions
const openDriveLink = () => {
  if (form.value.drive_link) {
    window.open(form.value.drive_link, '_blank', 'noopener,noreferrer');
  }
};

const copyDriveLink = async () => {
  if (form.value.drive_link) {
    try {
      await navigator.clipboard.writeText(form.value.drive_link);
      proxy.$showNotification("✅ تم نسخ رابط Drive إلى الحافظة", "success");
    } catch (err) {
      console.error('❌ Error copying link:', err);
      proxy.$showNotification("❌ فشل نسخ الرابط", "error");
    }
  }
};

const validateDriveLink = (link) => {
  if (!link) return true;
  
  const drivePatterns = [
    /^https:\/\/drive\.google\.com\/drive\/folders\//,
    /^https:\/\/drive\.google\.com\/file\/d\//,
    /^https:\/\/drive\.google\.com\/open\?id=/,
    /^https:\/\/drive\.google\.com\/.*/
  ];
  
  return drivePatterns.some(pattern => pattern.test(link));
};
watch(() => selectedProject.value, async (newProject, oldProject) => {
  form.value.project_id = newProject?.id || null;

  console.log('🔄 Project changed:', newProject?.name, 'from', oldProject?.name);
  
  if (newProject) {
    try {
      // تحميل مناطق المشروع
      const response = await api.get(`/listings/projects/${newProject.id}/areas`);
      const projectAreasData = response.data.data || response.data;
      
      form.value.projectAreas = projectAreasData.map(area => ({
        id: area.id,
        name: area.area_parents_title || area.name || area.title,
        project_id: newProject.id
      }));
      
      console.log(`✅ Loaded ${form.value.projectAreas.length} areas for project:`, newProject.name);
      
      // ✅ حفظ قيم NOC من المطور
      if (newProject.developer) {
        const readyValue = Number(newProject.developer.noc_fees_ready || 0);
        form.value.noc_fees_ready = !isNaN(readyValue) && readyValue >= 0 ? readyValue : 0;
        
        const offPlanValue = Number(newProject.developer.noc_fees_off_plan || 0);
        form.value.noc_fees_off_plan = !isNaN(offPlanValue) && offPlanValue >= 0 ? offPlanValue : 0;
        
        console.log(`✅ Developer NOC values loaded:
          - Ready: ${formatAed(form.value.noc_fees_ready)}
          - Off-Plan: ${formatAed(form.value.noc_fees_off_plan)}`);
        
        updateNocBasedOnStatus();
      }
      
      if (form.value.area && form.value.area.id) {
        const areaExistsInNewProject = form.value.projectAreas.some(
          area => area.id === form.value.area.id
        );
        
        if (!areaExistsInNewProject) {
          console.log('⚠️ Current area not in new project, clearing it');
          form.value.area = null;
        } else {
          console.log('✅ Current area exists in new project, keeping it');
        }
      }
      
      areaSelectKey.value++;
      await fetchProjectFloorPlans(1);
      importedFloorPlans.value = [];
      showProjectFloorPlans.value = false;
      
    } catch (error) {
      console.error('❌ Error fetching project areas:', error);
      form.value.projectAreas = [];
      proxy.$showNotification("⚠️ Could not load project areas. Using general areas.", "warning");
    }
  } else {
    form.value.projectAreas = [];
    form.value.area = null;
    areaSelectKey.value++;
    projectFloorPlans.value = [];
    totalProjectFloorPlans.value = 0;
    importedFloorPlans.value = [];
    showProjectFloorPlans.value = false;
    form.value.noc_fixed_amount = 0;
    form.value.noc_fees_ready = 0;
    form.value.noc_fees_off_plan = 0;
  }
});
watch(() => selectedProject.value, async (newProject) => {
  if (newProject) {
    await fetchProjectFloorPlans(1);
    importedFloorPlans.value = [];
    showProjectFloorPlans.value = false;
  } else {
    projectFloorPlans.value = [];
    totalProjectFloorPlans.value = 0;
    importedFloorPlans.value = [];
    showProjectFloorPlans.value = false;

  }
  
}, { immediate: true });

const setAsHeroImage = async (item) => {
  try {
    isSettingHero.value = true;
    
    if (item.id) {
      const response = await api.post(`/listings/properties/${propertyId.value}/set-hero-image`, {
        gallery_image_id: item.id
      });

      if (response.data.data) {
        currentHeroImage.value = response.data.data.hero_image_url;
        
        existingGalleryImages.value = existingGalleryImages.value.map(img => ({
          ...img,
          is_hero: img.id === item.id
        }));
        
        proxy.$showNotification("✅ Hero image updated successfully!", "success");
      }
    } else {
      await saveWithHeroImage(item);
    }
  } catch (error) {
    console.error("❌ Error updating hero image:", error);
    proxy.$showNotification("❌ Failed to update hero image", "error");
  } finally {
    isSettingHero.value = false;
  }
};

const saveWithHeroImage = async (heroImageItem) => {
  try {
    isSubmitting.value = true;
    
    const formData = new FormData();
    formData.append('action', 'draft');
    formData.append('hero_image_from_gallery', 'first_new_image');

    formData.append('owner_id', selectedOwner.value.id);
    formData.append('agent_id', agentId.value);
    formData.append('property_type_id', form.value.property_type.id);
    formData.append('area_id', form.value.area.id);

    const textFields = {
      'unit_number': form.value.unit_number,
      'ownership_type': form.value.ownership_type,
      'listing_status': form.value.saleOrRent,
      'completion_status': form.value.completionStatus,
      'price': form.value.price,
      'number_of_bedrooms': form.value.number_of_bedrooms,
      'number_of_bathrooms': form.value.number_of_bathrooms,
      'size_sqmt': form.value.size_sqmt,
      'size_sqft': form.value.size_sqft,
      'comment': form.value.comment,
    };

    Object.entries(textFields).forEach(([key, value]) => {
      if (value !== null && value !== undefined && value !== '') {
        formData.append(key, value);
      }
    });

    if (selectedProject.value && selectedProject.value.id) {
      formData.append('project_id', selectedProject.value.id);
    }

    // Same ordering payload as the main update path: send existing image orders
    // and a parallel `new_gallery_order` for new files.
    {
      const ordered = combinedGallery.value;
      ordered.forEach((item, idx) => {
        if (item && item.id) {
          formData.append(`gallery_orders[${item.id}]`, idx + 1);
        }
      });
      const positions = new Map();
      ordered.forEach((it, idx) => {
        if (it && !it.id) positions.set(it, idx + 1);
      });
      if (form.value.gallery.length > 0) {
        form.value.gallery.forEach((item, index) => {
          const file = item.file || item;
          if (file instanceof File) {
            formData.append(`gallery[${index}]`, file);
            const pos = positions.get(item) ?? index + 1;
            formData.append(`new_gallery_order[${index}]`, pos);
          }
        });
      }
    }

    formData.append('_method', 'PUT');
    const response = await api.post(`/listings/properties/${propertyId.value}`, formData, {
      headers: { 
        "Content-Type": "multipart/form-data",
      },
    });

    console.log("✅ Save with hero image response:", response.data);
    
    if (response.data.data) {
      const propertyData = response.data.data;
      
      currentHeroImage.value = propertyData.hero_image_url || 
                              (form.value.gallery[0]?.preview || 
                               getImagePreview(form.value.gallery[0]?.file || form.value.gallery[0]));
      
      if (form.value.gallery.length > 0) {
        const newGalleryItems = form.value.gallery.map((item, index) => ({
          id: `temp-${Date.now()}-${index}`, 
          image_url: item.preview || getImagePreview(item.file || item),
          name: item.name || item.file?.name,
          created_at: new Date().toISOString(),
          is_new: true
        }));
        
        existingGalleryImages.value = [...existingGalleryImages.value, ...newGalleryItems];
        form.value.gallery = []; 
      }
    }
    
    proxy.$showNotification("✅ Image set as hero and changes saved!", "success");
    
  } catch (error) {
    console.error("❌ Error saving with hero image:", error);
    proxy.$showNotification("❌ Failed to save changes", "error");
  } finally {
    isSubmitting.value = false;
  }
};

const isHeroImage = (item) => {
  if (!item.id && currentHeroImage.value && item.preview) {
    return currentHeroImage.value.includes(item.preview) || 
           item.preview.includes(currentHeroImage.value);
  }
  
  if (item.id && currentHeroImage.value) {
    const itemUrl = item.image_url || '';
    const heroUrl = currentHeroImage.value || '';
    
    return itemUrl.includes(heroUrl.split('/').pop()) || 
           heroUrl.includes(itemUrl.split('/').pop()) ||
           itemUrl === heroUrl;
  }
  
  return false;
};

// Fetch layout types from API
const fetchLayoutTypes = async () => {
  try {
    isLoadingLayoutTypes.value = true;
    const response = await api.get("/listings/layout_types");
    
    const layoutTypesData = response.data.data || response.data;
    
    layoutTypes.value = layoutTypesData.map(layout => ({
      id: layout.id,
      name: layout.name || layout.layout_name || layout.title
    }));
    
  } catch (error) {
    console.error("❌ Error fetching layout types:", error.response || error);
    proxy.$showNotification("❌ Failed to load layout types.", "error");
  } finally {
    isLoadingLayoutTypes.value = false;
  }
};

// Fetch unit views from API
const fetchUnitViews = async () => {
  try {
    isLoadingUnitViews.value = true;
    const response = await api.get("/listings/unit_views");
    
    const unitViewsData = response.data.data || response.data;
    
    unitViews.value = unitViewsData.map(view => ({
      id: view.id,
      name: view.name || view.view_name || view.title
    }));
    
  } catch (error) {
    console.error("❌ Error fetching unit views:", error.response || error);
    proxy.$showNotification("❌ Failed to load unit views.", "error");
  } finally {
    isLoadingUnitViews.value = false;
  }
};

// Fetch owners from API
const fetchOwners = async () => {
  try {
    isLoadingOwners.value = true;
    const response = await api.get("/listings/owners");
    
    const ownersData = response.data.data || response.data;
    
    owners.value = ownersData.map(owner => ({
      id: owner.id,
      full_name: owner.full_name || `${owner.first_name} ${owner.last_name}`,
      first_name: owner.first_name,
      last_name: owner.last_name,
      email: owner.email,
      phone_number: owner.phone_number,
      whatsapp_number: owner.whatsapp_number,
      second_phone_number: owner.second_phone_number
    }));
    
  } catch (error) {
    console.error("❌ Error fetching owners:", error.response || error);
    proxy.$showNotification("❌ Failed to load owners.", "error");
  } finally {
    isLoadingOwners.value = false;
  }
};

const customOwnerFilter = (option, label, search) => {
  if (!search || search.trim() === '') return true;
  
  const searchTerm = search.toLowerCase().trim();
  
  const cleanPhoneNumber = (phone) => {
    if (!phone) return '';
    return phone.replace(/[\s+()-]/g, '').toLowerCase();
  };
  
  if (option.email && option.email.toLowerCase().includes(searchTerm)) {
    return true;
  }
  
  if (option.phone_number) {
    const cleanPhone = cleanPhoneNumber(option.phone_number);
    const cleanSearch = cleanPhoneNumber(searchTerm);
    if (cleanPhone.includes(cleanSearch)) return true;
  }
  
  if (option.whatsapp_number) {
    const cleanWhatsapp = cleanPhoneNumber(option.whatsapp_number);
    const cleanSearch = cleanPhoneNumber(searchTerm);
    if (cleanWhatsapp.includes(cleanSearch)) return true;
  }
  
  if (option.second_phone_number) {
    const cleanSecondPhone = cleanPhoneNumber(option.second_phone_number);
    const cleanSearch = cleanPhoneNumber(searchTerm);
    if (cleanSecondPhone.includes(cleanSearch)) return true;
  }
  
  return false;
};

// Fetch property types from API
const fetchPropertyTypes = async () => {
  try {
    isLoadingPropertyTypes.value = true;
    const response = await api.get("/listings/property-types");
    
    const propertyTypesData = response.data.data || response.data;
    
    propertyTypes.value = propertyTypesData.map(type => ({
      id: type.id,
      name: type.name || type.type_name || type.title
    }));
    
  } catch (error) {
    console.error("❌ Error fetching property types:", error.response || error);
    proxy.$showNotification("❌ Failed to load property types.", "error");
  } finally {
    isLoadingPropertyTypes.value = false;
  }
};

// Fetch areas from API
const fetchAreas = async () => {
  try {
    isLoadingAreas.value = true;
    const response = await api.get("/listings/areas");
    
    const areasData = response.data.data || response.data;
    
    areas.value = areasData.map(area => ({
      id: area.id,
      name: area.area_parents_title || area.name || area.title,
      children_count: area.children_count ?? 0
    }));
    
  } catch (error) {
    console.error("❌ Error fetching areas:", error.response || error);
    proxy.$showNotification("❌ Failed to load areas.", "error");
  } finally {
    isLoadingAreas.value = false;
  }
};

const getAreaPlaceholder = () => {
  if (selectedProject.value && form.value.projectAreas.length > 0) {
    return `Select area in ${selectedProject.value.name}`;
  }
  return "Select area";
};

// New owner functions
const filterNameInput = (field) => {
  if (!newOwner.value[field]) return;
  newOwner.value[field] = newOwner.value[field]
    .replace(/[^a-zA-Z\u0600-\u06FF\s]/g, '');
};

const filterNumberInput = (field) => {
  if (!newOwner.value[field]) return;
  newOwner.value[field] = newOwner.value[field].replace(/[^0-9]/g, '');
};

const handleNationalityChange = (newNationality) => {
  if (newNationality === 'UAE') {
    newOwner.value.residency_status = 'resident';
    fetchLocations('resident');
  } else {
    newOwner.value.residency_status = 'non_resident';
    newOwner.value.location_id = "";
    fetchLocations('non_resident'); 
  }
};

const getLocationLabel = () => {
  if (newOwner.value.nationality === 'UAE') {
    return 'City';
  } else if (newOwner.value.residency_status === 'resident') {
    return 'Emirate';
  } else if (newOwner.value.residency_status === 'non_resident') {
    return 'Country';
  }
  return 'Emirate or Country';
};

const getLocationPlaceholder = () => {
  if (newOwner.value.nationality === 'UAE') {
    return 'Select City';
  } else if (newOwner.value.residency_status === 'resident') {
    return 'Select Emirate';
  } else if (newOwner.value.residency_status === 'non_resident') {
    return 'Select Country';
  }
  return 'Select location';
};

// Watch for residency_status changes
watch(
  () => newOwner.value.residency_status,
  async (newStatus, oldStatus) => {
    if (newStatus === 'resident') {
      newOwner.value.location_id = "";
      await fetchLocations('resident');
    }
  }
);

// Function to fetch locations from API
const fetchLocations = async (residencyStatus) => {
  if (residencyStatus === 'non_resident') {
    // Use local nationalities array instead of API
    locations.value = nationalities.value.map((country, index) => ({
      id: `local-${index}`,
      name: country,
      type: 'country',
    }));
    return;
  }

  // resident → fetch from API
  try {
    isLoadingLocations.value = true;
    locations.value = [];
    const response = await api.get(`/listings/owners/locations/available?residency_status=${residencyStatus}`);
    locations.value = response.data.data || response.data;
  } finally {
    isLoadingLocations.value = false;
  }
};

const submitNewOwner = async () => {
  const token = localStorage.getItem('token');
  if (!token) {
    proxy.$showNotification("❌ You are not logged in!", "error");
    return;
  }

  try {
    isSubmittingOwner.value = true;
    const formData = new FormData();

    for (const key in newOwner.value) {
      const value = newOwner.value[key];
      if (value instanceof File) {
        formData.append(key, value);
      } else if (value !== null && value !== "") {
        formData.append(key, value);
      }
    }

    const response = await api.post("/listings/owners", formData, {
      headers: {
        "Content-Type": "multipart/form-data",
      },
    });

    const createdOwner = response.data?.data || response.data;

    await fetchOwners();

    const newOwnerInList = owners.value.find(owner => owner.id === createdOwner.id);
    if (newOwnerInList) {
      selectedOwner.value = newOwnerInList;
    }

    newOwner.value = {
      salutation: "",
      first_name: "",
      last_name: "",
      email: "",
      phone_number: "",
      whatsapp_number: "",
      second_phone_number: "",
      nationality: "",
      residency_status: "",
      location_id: "",
      id_front: null,
      id_back: null,
      visa_copy: null,
      passport_copy: null,
      notes: "",
    };

    locations.value = [];
    
    showAddOwner.value = false;
    proxy.$showNotification("✅ Owner added successfully and selected automatically!", "success");
  } catch (error) {
    console.error("❌ Error adding owner:", error.response || error);
    if (error.response?.data?.errors) {
      const errorMessages = Object.values(error.response.data.errors).flat().join(', ');
      proxy.$showNotification(`❌ Validation Error: ${errorMessages}`, "error");
    } else {
      proxy.$showNotification("❌ Failed to add owner. Check console for details.", "error");
    }
  } finally {
    isSubmittingOwner.value = false;
  }
};

const handleNewOwnerFile = (e, field) => {
  const file = e.target.files[0];
  if (file) newOwner.value[field] = file;
};



// Floor Plan Functions
// ============ FLOOR PLANS - METHODS ============
const fetchProjectFloorPlans = async (page = 1) => {
  if (!selectedProject.value) return;
  
  try {
    loadingProjectFloorPlans.value = true;
    
    const response = await api.get(`/listings/projects/${selectedProject.value.id}/floor-plans`, {
      params: {
        page: page,
        per_page: 12
      }
    });
    
    const data = response.data.data || response.data;
    
    let newPlans = [];
    if (Array.isArray(data)) {
      newPlans = data;
    } else if (data.floor_plans) {
      newPlans = data.floor_plans;
    }
    
    newPlans = newPlans.map(plan => ({
      ...plan,
      area_id: plan.area_id || plan.area?.id || null,
      area_name: plan.area?.name || null
    }));
    
    if (page === 1) {
      projectFloorPlans.value = newPlans;
    } else {
      projectFloorPlans.value = [...projectFloorPlans.value, ...newPlans];
    }
    
    totalProjectFloorPlans.value = response.data.total || newPlans.length;
    currentProjectPage.value = page;
    
    // ✅ التصحيح: للمشروع 1788 لا تطبق الفلترة، للمشاريع الأخرى طبق الفلترة إذا كانت المنطقة محددة
    if (form.value.area && form.value.area.id && form.value.project_id != 1788 && form.value.project_id != 1833) {
      filterFloorPlansByArea(form.value.area.id);
    } else {
      filteredProjectFloorPlans.value = projectFloorPlans.value;
      console.log('Showing all floor plans (no filter or project 1788)');
    }
    
    console.log('✅ Project floor plans loaded:', projectFloorPlans.value.length);
    console.log('📊 Filtered floor plans:', filteredProjectFloorPlans.value.length);
    
  } catch (error) {
    console.error("❌ Error fetching project floor plans:", error);
    proxy.$showNotification("❌ Failed to load project floor plans", "error");
  } finally {
    loadingProjectFloorPlans.value = false;
  }
};
  
const resetAreaFilter = () => {
  form.value.area = null;
  filteredProjectFloorPlans.value = projectFloorPlans.value;
  proxy.$showNotification("🔄 Area filter cleared", "info");
};

const filterFloorPlansByArea = (areaId) => {
  if (!areaId || !projectFloorPlans.value.length) {
    filteredProjectFloorPlans.value = projectFloorPlans.value;
    return;
  }
  
  // ✅ للمشروع 1788، اعرض جميع المخططات (لا تقم بالفلترة)
  if (form.value.project_id == 1788 || form.value.project_id ==1833) {
    filteredProjectFloorPlans.value = projectFloorPlans.value;
    console.log('Project 1788: Skipping area filter');
    return;
  }
  
  filteredProjectFloorPlans.value = projectFloorPlans.value.filter(plan => {
    if (plan.area_id === areaId) return true;
    if (Number(plan.area_id) === Number(areaId)) return true;
    if (plan.area && (plan.area.id === areaId || Number(plan.area.id) === Number(areaId))) return true;
    return false;
  });
  
  console.log(`🎯 Filtered to ${filteredProjectFloorPlans.value.length} plans for area ${areaId}`);
};
const getFloorPlansCountByArea = (areaId) => {
  if (!projectFloorPlans.value.length) return 0;
  return projectFloorPlans.value.filter(plan => 
    plan.area_id === areaId || plan.area?.id === areaId
  ).length;
};

const refreshProjectFloorPlans = async () => {
  await fetchProjectFloorPlans(1);
};

const loadMoreProjectFloorPlans = async () => {
  if (!selectedProject.value || loadingProjectFloorPlans.value) return;
  await fetchProjectFloorPlans(currentProjectPage.value + 1);
};

const isFloorPlanImported = (projectFloorPlanId) => {
  return importedFloorPlans.value.some(
    plan => plan.project_floor_plan_id === projectFloorPlanId
  );
};

const isExistingFloorPlan = (projectFloorPlanId) => {
  return existingFloorPlans.value.some(
    plan => plan.project_floor_plan_id === projectFloorPlanId
  );
};

const toggleImportFloorPlan = (projectFloorPlan) => {
  if (existingFloorPlans.value.length > 0) {
    proxy.$showNotification(
      "❌ Cannot select floor plan. This property already has an existing floor plan. Please delete it first.",
      "error"
    );
    return;
  }
  
  if (selectedProjectFloorPlan.value?.project_floor_plan_id === projectFloorPlan.id) {
    selectedProjectFloorPlan.value = null;
    importedFloorPlans.value = [];
    proxy.$showNotification(`🗑️ Removed "${projectFloorPlan.name}"`, "info");
  } else {
    if (newFloorPlans.value.length > 0) {
      if (!confirm('You have an uploaded floor plan. Replace it with this project floor plan?')) {
        return;
      }
      if (newFloorPlans.value[0].preview) {
        URL.revokeObjectURL(newFloorPlans.value[0].preview);
      }
      newFloorPlans.value = [];
      uploadedFloorPlan.value = null;
    }
    
    const importedPlan = {
      id: `imported-${Date.now()}-${projectFloorPlan.id}`,
      name: projectFloorPlan.name,
      customName: projectFloorPlan.name,
      image_url: projectFloorPlan.image_url,
      project_id: selectedProject.value.id,
      project_floor_plan_id: projectFloorPlan.id,
      dimensions: projectFloorPlan.dimensions,
      is_imported: true,
      type: 'imported'
    };
    
    importedFloorPlans.value = [importedPlan];
    selectedProjectFloorPlan.value = importedPlan;
    
    proxy.$showNotification(`✅ Selected "${projectFloorPlan.name}" as floor plan`, "success");
  }
};

const handleFloorPlanUpload = (e) => {
  if (existingFloorPlans.value.length > 0) {
    proxy.$showNotification(
      "❌ Cannot upload floor plan. This property already has an existing floor plan. Please delete it first.",
      "error"
    );
    e.target.value = '';
    return;
  }
  
  const files = Array.from(e.target.files);
  if (files.length > 0) {
    const file = files[0];
    
    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/svg+xml', 'image/webp'];
    
    if (!validTypes.includes(file.type)) {
      proxy.$showNotification(`❌ File "${file.name}" is not a valid image type.`, "error");
      e.target.value = '';
      return;
    }
    
    if (file.size > 10 * 1024 * 1024) {
      proxy.$showNotification(`❌ Floor plan "${file.name}" is too large. Max size is 10MB.`, "error");
      e.target.value = '';
      return;
    }

    if (selectedProjectFloorPlan.value) {
      if (!confirm('You have a project floor plan selected. Replace it with this uploaded file?')) {
        e.target.value = '';
        return;
      }
      selectedProjectFloorPlan.value = null;
      importedFloorPlans.value = [];
    }

    const newPlan = {
      file: file,
      name: file.name,
      size: file.size,
      type: file.type,
      customName: file.name.replace(/\.[^/.]+$/, ""),
      preview: URL.createObjectURL(file),
      is_new: true,
      type: 'new'
    };
    
    newFloorPlans.value = [newPlan];
    uploadedFloorPlan.value = newPlan;
    
    e.target.value = '';
    proxy.$showNotification(`✅ Floor plan uploaded: ${file.name}`, "success");
  }
};


const hasExistingFloorPlan = computed(() => {
  return existingFloorPlans.value.length > 0;
});

const allPropertyFloorPlans = computed(() => {
  const all = [];
  
  if (existingFloorPlans.value.length > 0) {
    all.push({
      ...existingFloorPlans.value[0],
      type: 'existing',
      customName: existingFloorPlans.value[0].name
    });
  } 
  else if (importedFloorPlans.value.length > 0) {
    all.push({
      ...importedFloorPlans.value[0],
      type: 'imported',
      customName: importedFloorPlans.value[0].customName || importedFloorPlans.value[0].name
    });
  } 
  else if (newFloorPlans.value.length > 0) {
    all.push({
      ...newFloorPlans.value[0],
      type: 'new',
      customName: newFloorPlans.value[0].customName || newFloorPlans.value[0].name,
      tempId: `new-${Date.now()}-${Math.random()}`
    });
  }
  
  return all;
});

const removeCurrentFloorPlan = () => {
  if (existingFloorPlans.value.length > 0) {
    if (confirm('Are you sure you want to delete the existing floor plan?')) {
      deleteFloorPlan(existingFloorPlans.value[0].id);
    }
  } else if (importedFloorPlans.value.length > 0) {
    selectedProjectFloorPlan.value = null;
    importedFloorPlans.value = [];
    proxy.$showNotification("🗑️ Project floor plan removed", "info");
  } else if (newFloorPlans.value.length > 0) {
    if (newFloorPlans.value[0].preview) {
      URL.revokeObjectURL(newFloorPlans.value[0].preview);
    }
    newFloorPlans.value = [];
    uploadedFloorPlan.value = null;
    proxy.$showNotification("🗑️ Uploaded floor plan removed", "info");
  }
};
const updatePlanName = (plan) => {
  if (plan.customName && plan.customName.trim() !== '') {
    plan.name = plan.customName.trim();
    
    if (plan.type === 'imported') {
      const index = importedFloorPlans.value.findIndex(p => p.id === plan.id);
      if (index !== -1) importedFloorPlans.value[index].name = plan.name;
    } else if (plan.type === 'new') {
      const index = newFloorPlans.value.findIndex(p => p.tempId === plan.tempId);
      if (index !== -1) newFloorPlans.value[index].name = plan.name;
    }
    
    proxy.$showNotification("✅ Plan name updated", "success");
  }
};

const editFloorPlanName = async (plan) => {
  const newName = prompt('Enter new name for floor plan:', plan.name);
  if (newName && newName.trim() !== '' && newName !== plan.name) {
    try {
      if (plan.id && plan.type === 'existing') {
        await api.put(`/listings/properties/${propertyId.value}/floor-plans/${plan.id}`, {
          name: newName.trim()
        });
        
        const index = existingFloorPlans.value.findIndex(fp => fp.id === plan.id);
        if (index !== -1) existingFloorPlans.value[index].name = newName.trim();
      } else {
        plan.name = newName.trim();
        plan.customName = newName.trim();
      }
      
      proxy.$showNotification("✅ Floor plan name updated", "success");
    } catch (error) {
      console.error("❌ Error updating floor plan name:", error);
      proxy.$showNotification("❌ Failed to update floor plan name", "error");
    }
  }
};

const removePlan = (plan, index) => {
  if (plan.type === 'existing' && plan.id) {
    if (confirm('Are you sure you want to delete this floor plan?')) {
      deleteFloorPlan(plan.id);
    }
  } else if (plan.type === 'imported') {
    selectedProjectFloorPlan.value = null;
    importedFloorPlans.value = [];
    proxy.$showNotification("🗑️ Project floor plan removed", "info");
  } else if (plan.type === 'new') {
    if (plan.preview) URL.revokeObjectURL(plan.preview);
    newFloorPlans.value = [];
    uploadedFloorPlan.value = null;
    proxy.$showNotification("🗑️ Uploaded floor plan removed", "info");
  }
};

const deleteFloorPlan = async (floorPlanId) => {
  try {
    await api.delete(`/listings/properties/${propertyId.value}/floor-plans/${floorPlanId}`);
    existingFloorPlans.value = existingFloorPlans.value.filter(fp => fp.id !== floorPlanId);
    proxy.$showNotification("🗑️ Floor plan deleted", "success");
  } catch (error) {
    console.error("❌ Error deleting floor plan:", error);
    proxy.$showNotification("❌ Failed to delete floor plan", "error");
  }
};





const getPlanFileSize = (plan) => {
  if (plan.size) return formatFileSize(plan.size);
  if (plan.file?.size) return formatFileSize(plan.file.size);
  return '';
};

const handleFloorPlanImageError = (event) => {
  console.error('❌ Floor plan image failed to load:', event);
  event.target.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtc2l6ZT0iMTgiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIiBmaWxsPSIjOTk5Ij5JbWFnZSBub3QgZm91bmQ8L3RleHQ+PC9zdmc+';
};
const loadAllProjectFloorPlans = async () => {
  if (!selectedProject.value) return;
  
  try {
    loadingProjectFloorPlans.value = true;
    
    const response = await api.get(`/listings/projects/${selectedProject.value.id}/floor-plans`, {
      params: {
        all: true
      }
    });
    
    const data = response.data.data || response.data;
    
    if (Array.isArray(data)) {
      projectFloorPlans.value = data;
      totalProjectFloorPlans.value = data.length;
    } else if (data.floor_plans) {
      projectFloorPlans.value = data.floor_plans;
      totalProjectFloorPlans.value = data.total || data.floor_plans.length;
    }
    
    proxy.$showNotification(`✅ Loaded ${projectFloorPlans.value.length} floor plans from project`, "success");
    
  } catch (error) {
    console.error("❌ Error loading all project floor plans:", error);
    proxy.$showNotification("❌ Failed to load project floor plans", "error");
  } finally {
    loadingProjectFloorPlans.value = false;
  }
};






const getFloorPlanBadgeClass = (floorPlan) => {
  if (floorPlan.project_floor_plan_id) return 'bg-success';
  if (floorPlan.is_imported) return 'bg-info';
  return 'bg-primary';
};

const getFloorPlanBadgeIcon = (floorPlan) => {
  if (floorPlan.project_floor_plan_id) return 'fa-building';
  if (floorPlan.is_imported) return 'fa-download';
  return 'fa-upload';
};

const getFloorPlanBadgeText = (floorPlan) => {
  if (floorPlan.project_floor_plan_id) return 'Project';
  if (floorPlan.is_imported) return 'Imported';
  return 'Custom';
};


const updateImportedPlanName = (plan, index) => {
  if (plan.customName && plan.customName.trim() !== '') {
    plan.name = plan.customName.trim();
    importedFloorPlans.value[index] = { ...plan };
    proxy.$showNotification("✅ Plan name updated", "success");
  }
};

const removeImportedFloorPlan = (index) => {
  importedFloorPlans.value.splice(index, 1);
  proxy.$showNotification("🗑️ Imported plan removed", "info");
};





const removeNewFloorPlan = (index) => {
  if (form.value.floorPlans[index] && form.value.floorPlans[index].preview) {
    URL.revokeObjectURL(form.value.floorPlans[index].preview);
  }
  form.value.floorPlans.splice(index, 1);
  proxy.$showNotification("🗑️ Floor plan removed", "info");
};




const importProjectFloorPlan = async (projectFloorPlan) => {
  try {
    const importedPlan = {
      id: `imported-${Date.now()}-${projectFloorPlan.id}`,
      name: projectFloorPlan.name,
      editName: projectFloorPlan.name,
      image_url: projectFloorPlan.image_url,
      project_id: selectedProject.value.id,
      project_floor_plan_id: projectFloorPlan.id,
      sort_order: projectFloorPlan.sort_order || 0,
      created_at: new Date().toISOString(),
      is_imported: true
    };
    
    importedFloorPlans.value.push(importedPlan);
    
    floorPlanTab.value = 'imported';
    
    proxy.$showNotification(`✅ Floor plan "${projectFloorPlan.name}" imported successfully`, "success");
    
  } catch (error) {
    console.error("❌ Error importing floor plan:", error);
    proxy.$showNotification("❌ Failed to import floor plan", "error");
  }
};





const updateFloorPlanName = async (floorPlanId, newName) => {
  if (!newName || newName.trim() === '') {
    proxy.$showNotification("❌ Plan name cannot be empty", "error");
    return;
  }
  
  try {
    await api.put(`/listings/properties/${propertyId.value}/floor-plans/${floorPlanId}`, {
      name: newName.trim()
    });
    
    const planIndex = existingFloorPlans.value.findIndex(fp => fp.id === floorPlanId);
    if (planIndex !== -1) {
      existingFloorPlans.value[planIndex].name = newName.trim();
    }
    
    proxy.$showNotification("✅ Floor plan name updated successfully", "success");
    
  } catch (error) {
    console.error("❌ Error updating floor plan name:", error);
    proxy.$showNotification("❌ Failed to update floor plan name", "error");
  }
};

const validateFloorPlanName = (item, index) => {
  if (!item.customName || item.customName.trim() === '') {
    item.nameError = 'Floor plan name is required';
    return false;
  }
  
  if (item.customName.length > 100) {
    item.nameError = 'Name must be less than 100 characters';
    return false;
  }
  
  item.nameError = '';
  return true;
};


const removeFloorPlan = (index) => {
  if (form.value.floorPlans[index] && form.value.floorPlans[index].preview) {
    URL.revokeObjectURL(form.value.floorPlans[index].preview);
  }
  form.value.floorPlans.splice(index, 1);
  proxy.$showNotification("🗑️ Floor plan removed", "info");
};

const removeExistingFloorPlan = async (floorPlanId) => {
  try {
    await api.delete(`/listings/properties/${propertyId.value}/floor-plans/${floorPlanId}`);
    existingFloorPlans.value = existingFloorPlans.value.filter(fp => fp.id !== floorPlanId);
    proxy.$showNotification("🗑️ Floor plan deleted", "success");
  } catch (error) {
    console.error("❌ Error deleting floor plan:", error);
    proxy.$showNotification("❌ Failed to delete floor plan", "error");
  }
};

// Gallery Functions
const handleGalleryUpload = (e) => {
  const files = Array.from(e.target.files);
  if (files.length > 0) {
    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/svg+xml', 'image/webp'];
    const validFiles = files.filter(file => {
      if (!validTypes.includes(file.type)) {
        proxy.$showNotification(`❌ File "${file.name}" is not a valid image type.`, "error");
        return false;
      }
      
      if (file.size > 10 * 1024 * 1024) {
        proxy.$showNotification(`❌ File "${file.name}" is too large. Max size is 10MB.`, "error");
        return false;
      }
      return true;
    });

    if (validFiles.length > 0) {
      const filesWithPreview = validFiles.map(file => ({
        file: file,
        name: file.name,
        size: file.size,
        type: file.type,
        preview: URL.createObjectURL(file)
      }));
      
      form.value.gallery = [...form.value.gallery, ...filesWithPreview];
      e.target.value = '';
      proxy.$showNotification(`✅ Added ${validFiles.length} image(s) to gallery`, "success");
    }
  }
};

const removeGalleryItem = (item, index) => {
  if (item.id) {
    // Existing image - delete from server
    removeExistingGalleryImage(item.id);
  } else {
    // New image - remove from array
    removeGalleryImage(index - existingGalleryImages.value.length);
  }
};

const removeGalleryImage = (index) => {
  if (form.value.gallery[index] && form.value.gallery[index].preview) {
    URL.revokeObjectURL(form.value.gallery[index].preview);
  }
  form.value.gallery.splice(index, 1);
  proxy.$showNotification("🗑️ Image removed from gallery", "info");
};

const removeExistingGalleryImage = async (galleryId) => {
  try {
    await api.delete(`/listings/properties/${propertyId.value}/gallery/${galleryId}`);
    existingGalleryImages.value = existingGalleryImages.value.filter(img => img.id !== galleryId);
    proxy.$showNotification("🗑️ Gallery image deleted", "success");
  } catch (error) {
    console.error("❌ Error deleting gallery image:", error);
    proxy.$showNotification("❌ Failed to delete gallery image", "error");
  }
};
const MAX_FILE_SIZE = 10 * 1024 * 1024;
const ALLOWED_ADDITIONAL_TYPES = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/svg+xml'];

const handleAdditionalDocumentsUpload = (event) => {
  const files = event.target.files ? Array.from(event.target.files) : [];
  if (!files.length) return;
  const list = form.value.additionalDocuments || [];
  for (const file of files) {
    if (file.size > MAX_FILE_SIZE) {
      proxy.$showNotification(`File "${file.name}" exceeds 10MB`, "error");
      continue;
    }
    if (!ALLOWED_ADDITIONAL_TYPES.includes(file.type)) {
      proxy.$showNotification(`File "${file.name}" has invalid type. Use PDF, JPG, PNG, SVG.`, "error");
      continue;
    }
    list.push({ file, name: file.name });
  }
  form.value.additionalDocuments = list;
  event.target.value = '';
};

const removeAdditionalDocument = (index) => {
  form.value.additionalDocuments.splice(index, 1);
};

const deleteExistingAdditionalDocument = async (documentId) => {
  try {
    await api.delete(`/listings/properties/${propertyId.value}/additional-documents/${documentId}`);
    existingAdditionalDocuments.value = existingAdditionalDocuments.value.filter(d => d.id !== documentId);
    proxy.$showNotification("Document removed", "success");
  } catch (error) {
    console.error("❌ Error deleting additional document:", error);
    proxy.$showNotification("❌ Failed to remove document", "error");
  }
};
const getImagePreview = (file) => {
  if (file instanceof File) {
    return URL.createObjectURL(file);
  }
  if (file && file.image_url) {
    return file.image_url;
  }
  if (file && file.path) {
    return file.path;
  }
  return '';
};

// Handle image loading errors
const handleImageError = (event) => {
  console.error('❌ Image failed to load:', event);
  event.target.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtc2l6ZT0iMTgiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIiBmaWxsPSIjOTk5Ij5JbWFnZSBub3QgZm91bmQ8L3RleHQ+PC9zdmc+';
};

const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString('en-US');
};

const listingPriceForApi = () => {
  const raw = parseDecimalPrice(form.value.price);
  const p = Number(raw || 0);
  if (isUnderConstruction.value && p > 0) return String(Math.round(p));
  return raw;
};

const handleSubmit = async (action = 'draft') => {
  try {
    isSubmitting.value = true;
     const plotTypes = ['Plot', 'Land', 'Residential Plot', 'Commercial Plot']; 
    const isPlot= form.value.property_type && 
           plotTypes.some(type => 
               form.value.property_type.name.toLowerCase().includes(type.toLowerCase())
           );
    console.log('🔄 Submitting form with action:', action);
     if (!isUnderConstruction.value && !form.value.occupancyStatus) {
    proxy.$showNotification("❌ Please select occupancy status!", "error");
    isSubmitting.value = false;
    return;
  }

    // Validation للـ rented fields
    if (form.value.saleOrRent === 'Rent' && form.value.rented_status === 'Rented' && !form.value.rented_until) {
      proxy.$showNotification("❌ Please select rental end date for rented properties!", "error");
      isSubmitting.value = false;
      return;
    }

    // Basic validation
    if (!selectedOwner.value) {
      proxy.$showNotification("❌ Please select an owner first!", "error");
      isSubmitting.value = false;
      return;
    }

    if (!selectedOwner.value.id) {
      proxy.$showNotification("❌ Invalid owner selected!", "error");
      isSubmitting.value = false;
      return;
    }

    const currentAgentId = agentId.value;
    if (!currentAgentId) {
      proxy.$showNotification("❌ Agent ID not found. Please login again.", "error");
      isSubmitting.value = false;
      return;
    }
 if (form.value.drive_link && !validateDriveLink(form.value.drive_link)) {
    proxy.$showNotification("❌ Invalid Drive link. It must be from Google Drive", "error");
      isSubmitting.value = false;
      return;
    }
    const requiredFields = [
      { value: form.value.property_type, message: "❌ Please select a property type!" },
      { value: form.value.area, message: "❌ Please select an area!" },
    ];

    for (const field of requiredFields) {
      if (!field.value) {
        proxy.$showNotification(field.message, "error");
        isSubmitting.value = false;
        return;
      }
    }

    if (isUnderConstruction.value) {
      const bdDateErr = getBreakdownInstallmentDateError();
      if (bdDateErr) {
        proxy.$showNotification(bdDateErr, 'error');
        isSubmitting.value = false;
        return;
      }
      const pctCapErr = getBreakdownPercentageCapError();
      if (pctCapErr) {
        proxy.$showNotification(pctCapErr, 'error');
        isSubmitting.value = false;
        return;
      }
      const handoverErr = paymentHandoverDateError.value;
      if (handoverErr) {
        proxy.$showNotification(handoverErr, 'error');
        isSubmitting.value = false;
        return;
      }
    }

    if (isUnderConstruction.value && action === 'publish') {
      if (publishPaymentBreakdownBlocked.value) {
        proxy.$showNotification(
          publishPaymentBreakdownBlockTitle.value || 'Fix payment breakdown before publishing.',
          'error',
        );
        isSubmitting.value = false;
        return;
      }
      const confirmed = await confirmSellingBelowOriginalIfNeeded(
        originalContractPriceNum.value,
        sellingPriceNum.value,
      );
      if (!confirmed) {
        isSubmitting.value = false;
        return;
      }
    }

    // For publish action, check gallery requirements
    if (action !== 'draft') {
      if (!form.value.completionStatus) {
        proxy.$showNotification("❌ Please select completion status!", "error");
        isSubmitting.value = false;
        return;
      }
      
     if (totalGalleryCount.value < 10 && !isPlot) {
        proxy.$showNotification(`❌ At least 10 gallery images are required! Currently you have ${totalGalleryCount.value}.`, "error");
        isSubmitting.value = false;
        return;
      }else if (totalGalleryCount.value < 1 && isPlot){
          proxy.$showNotification(`❌ At least 1 gallery images are required! Currently you have ${totalGalleryCount.value}.`, "error");
        isSubmitting.value = false;
        return;
      }
      if (totalGalleryCount.value > 15) {
          proxy.$showNotification(
            `❌ Maximum 15 gallery images are allowed. Currently you have ${totalGalleryCount.value}.`,
            "error"
          );
          isSubmitting.value = false;
          return;
        }

    }

    const formData = new FormData();

    // Add action first
    formData.append('action', action);

    // Add required IDs
    formData.append('owner_id', selectedOwner.value.id);
    formData.append('agent_id', currentAgentId);
    formData.append('property_type_id', form.value.property_type.id);
    formData.append('area_id', form.value.area.id);

    if (form.value.rented_status) {
      formData.append('rented_status', form.value.rented_status);
    }
    
    if (form.value.rented_until) {
      formData.append('rented_until', form.value.rented_until);
    }
    if (form.value.rentExpiryDate) {
    formData.append('rent_expiry_date', form.value.rentExpiryDate);
  }
    // if (form.value.payment_plan ) {
    //   formData.append('payment_plan', form.value.payment_plan);
    // }
     if (isUnderConstruction.value) {
      if (paymentPlanSelectionLabel(form.value.payment_plans)) {
        formData.append('payment_plan', form.value.payment_plan);
      }
    } else if (form.value.completionStatus === 'Completed') {
      // Clear any payment plans for completed properties
      form.value.payment_plans = null;
      form.value.payment_plan = null;
    }

    // Add project ID if selected
    if (selectedProject.value && selectedProject.value.id) {
      formData.append('project_id', selectedProject.value.id);
      console.log(`🏗️ Adding project_id: ${selectedProject.value.id} (Project: ${selectedProject.value.name})`);
    } else {
      console.log('🏗️ No project selected, skipping project_id');
    }

    // Add optional IDs only if they exist
    if (form.value.unit_view && form.value.unit_view.id) {
      formData.append('unit_view_id', form.value.unit_view.id);
    }

    if (form.value.layout_type && form.value.layout_type.id) {
      formData.append('layout_type_id', form.value.layout_type.id);
    }
  if (form.value.drive_link) {
      formData.append('drive_link', form.value.drive_link);
    }
  if (importedFloorPlans.value.length > 0) {
      importedFloorPlans.value.forEach((plan, index) => {
        formData.append(`imported_floor_plans[${index}][name]`, plan.name);
        formData.append(`imported_floor_plans[${index}][image_url]`, plan.image_url);
        formData.append(`imported_floor_plans[${index}][project_id]`, plan.project_id);
        formData.append(`imported_floor_plans[${index}][project_floor_plan_id]`, plan.project_floor_plan_id);
        formData.append(`imported_floor_plans[${index}][sort_order]`, plan.sort_order || index);
      });
    }
     if (form.value.floorPlans.length > 0) {
      form.value.floorPlans.forEach((item, index) => {
        const file = item.file || item;
        if (file instanceof File) {
          formData.append(`floor_plans[${index}]`, file);
          formData.append(`floor_plan_names[${index}]`, item.customName || file.name.replace(/\.[^/.]+$/, ""));
        }
      });
    }
    formData.append('is_hot_deal', form.value.is_hot_deal || 'No');
      listingFeatureOptions.forEach(feature => {
      formData.append(
        `additional_features[${feature.key}]`,
        form.value[feature.key] ? 1 : 0
      );
    });
    const textFields = {
      'unit_number': form.value.unit_number,
      'ownership_type': form.value.ownership_type,
      'listing_status': form.value.saleOrRent,
      'completion_status': form.value.completionStatus,
      'price': listingPriceForApi(),
      'number_of_bedrooms': form.value.number_of_bedrooms,
      'number_of_bathrooms': form.value.number_of_bathrooms,
      'size_sqmt': form.value.size_sqmt,
      'size_sqft': form.value.size_sqft,
      'furnished_status': form.value.furnished_status,
      'comment': form.value.comment,
      'mortgage_status': form.value.mortgageStatus,
      'occupancy_status': form.value.occupancyStatus,
      'mortgage_amount': form.value.mortgageAmount,
      'rent_expiry_date': form.value.rentExpiryDate,
      'rent_amount': form.value.rentAmount,
      'mortgage_comment': form.value.mortgageComment,
    };

    Object.entries(textFields).forEach(([key, value]) => {
      if (value !== null && value !== undefined && value !== '') {
        formData.append(key, value);
      }
    });

    if (form.value.original_price !== '' && form.value.original_price != null) {
      const opDigits = parseDecimalPrice(form.value.original_price);
      if (opDigits !== '') formData.append('original_price', opDigits);
    }
    const sellingForDb = parseDecimalPrice(form.value.price);
    if (sellingForDb !== '') {
      formData.append('selling_price', sellingForDb);
    }

      // ✅ NOC - يرسل في كلتا الحالتين (Under Construction و Completed)
      if (showNocField.value && form.value.noc_fixed_amount > 0) {
        formData.append('noc_fixed_amount', String(Math.round(Number(form.value.noc_fixed_amount || 0))));
        formData.append('noc_percentage', '0');
        formData.append('noc_type', currentNocType.value);
        formData.append('noc_fees_ready', String(form.value.noc_fees_ready || 0));
        formData.append('noc_fees_off_plan', String(form.value.noc_fees_off_plan || 0));
      } else {
        formData.append('noc_fixed_amount', '0');
        formData.append('noc_percentage', '0');
        formData.append('noc_type', 'none');
      }

      // ✅ Payment Breakdown - يرسل فقط عند Under Construction
      if (isUnderConstruction.value) {
        formData.append('payment_breakdown', JSON.stringify(breakdownInstallments.value));
       
        if (form.value.handover_date) formData.append('handover_date', form.value.handover_date);
      }
       formData.append('assignment_expense_lines', JSON.stringify(assignmentExpenseLines.value));
    // Add floor plans
    if (form.value.floorPlans.length > 0) {
      form.value.floorPlans.forEach((item, index) => {
        const file = item.file || item;
        if (file instanceof File) {
          formData.append(`floor_plans[${index}]`, file);
          formData.append(`floor_plan_names[${index}]`, item.customName || file.name.replace(/\.[^/.]+$/, ""));
        }
      });
    }

    // Persist the user's drag-and-drop order on the combined gallery.
    // `combinedGallery` is computed in display order (existing first, then new).
    // For each kept existing image we send `gallery_orders[ID]=N` so the
    // backend updates its `order` column. New files keep their existing
    // `gallery[]` payload but each gets a parallel `new_gallery_order[i]=N`
    // carrying its final position.
    {
      const ordered = combinedGallery.value;
      let newIdx = 0;
      ordered.forEach((item, idx) => {
        const finalOrder = idx + 1;
        if (item && item.id) {
          formData.append(`gallery_orders[${item.id}]`, finalOrder);
        }
      });
      if (form.value.gallery.length > 0) {
        // Compute each new file's position from the combined order.
        const positions = new Map();
        ordered.forEach((it, idx) => {
          if (it && !it.id) positions.set(it, idx + 1);
        });
        form.value.gallery.forEach((item, index) => {
          const file = item.file || item;
          if (file instanceof File) {
            formData.append(`gallery[${index}]`, file);
            const pos = positions.get(item) ?? index + 1;
            formData.append(`new_gallery_order[${index}]`, pos);
            newIdx += 1;
          }
        });
      }
    }
     // Add additional documents
    if (form.value.additionalDocuments && form.value.additionalDocuments.length > 0) {
      form.value.additionalDocuments.forEach((item, index) => {
        const file = item.file || item;
        if (file instanceof File) {
          formData.append(`additional_documents[${index}]`, file);
        }
      });
    }

    // Debug logging
    console.log('📤 Sending form data with action:', action);
    console.log('🔑 New fields:', {
      rented_status: form.value.rented_status,
      rented_until: form.value.rented_until,
      payment_plan: form.value.payment_plan,
            drive_link: form.value.drive_link
    });

    // UPDATE request
    formData.append('_method', 'PUT');
    const response = await api.post(`/listings/properties/${propertyId.value}`, formData, {
      headers: { 
        "Content-Type": "multipart/form-data",
      },
      timeout: 30000,
    });

    console.log("✅ Success Response:", response.data);
    
    const successMessage = action === 'draft' 
      ? '✅ Property draft updated successfully!' 
      : action === 'preview'
      ? '✅ Property updated for preview!'
      : '✅ Property updated and published successfully!';
    
    proxy.$showNotification(successMessage, "success");
    
    if (action === 'preview') {
      router.push(`/property-details/${propertyId.value}`);
    } else {
      router.push(`/property-details/${propertyId.value}`);
    }
    
  } catch (error) {
    console.error("❌ Full Error:", error);
    
    if (error.code === 'ECONNABORTED') {
      proxy.$showNotification("❌ Request timeout. Please try again.", "error");
    } else if (error.response?.status === 413) {
      proxy.$showNotification("❌ File too large. Please reduce image sizes.", "error");
    } else if (error.response?.data?.errors) {
      const errorMessages = Object.values(error.response.data.errors).flat().join(', ');
      proxy.$showNotification(`❌ Validation Error: ${errorMessages}`, "error");
    } else if (error.response?.data?.message) {
      proxy.$showNotification(`❌ Server Error: ${error.response.data.message}`, "error");
    } else if (error.message) {
      proxy.$showNotification(`❌ Network Error: ${error.message}`, "error");
    } else {
      proxy.$showNotification("❌ Unexpected error occurred. Please check console.", "error");
    }
  } finally {
    isSubmitting.value = false;
  }
};

// Conversion functions
const convertSqmToSqft = () => {
  if (form.value.size_sqmt && !isNaN(form.value.size_sqmt)) {
    form.value.size_sqft = (form.value.size_sqmt * 10.7639).toFixed(2);
  } else {
    form.value.size_sqft = "";
  }
};

const convertSqftToSqm = () => {
  if (form.value.size_sqft && !isNaN(form.value.size_sqft)) {
    form.value.size_sqmt = (form.value.size_sqft / 10.7639).toFixed(2);
  } else {
    form.value.size_sqmt = "";
  }
};

const preventNumberInvalidKeys = (event) => {
  if (['e', 'E', '+', '-'].includes(event.key)) {
    event.preventDefault();
  }
};

const closeAddOwner = () => {
  showAddOwner.value = false;
};

// Watch hero image changes
watch(currentHeroImage, (newHeroUrl) => {
  console.log('🔄 Hero image changed to:', newHeroUrl);
});

// Clean up object URLs when component is unmounted
const cleanupObjectURLs = () => {
  // Clean up floor plans
  form.value.floorPlans.forEach(item => {
    if (item.preview) {
      URL.revokeObjectURL(item.preview);
    }
  });
  
  // Clean up gallery
  form.value.gallery.forEach(item => {
    if (item.preview) {
      URL.revokeObjectURL(item.preview);
    }
  });
   importedFloorPlans.value.forEach(item => {
    if (item.preview) {
      URL.revokeObjectURL(item.preview);
    }
  });
};

const debugFormData = () => {
  console.log('🔍 Debug Form Data:', {
    payment_plans: form.value.payment_plans,
    payment_plan: form.value.payment_plan,
    rented_status: form.value.rented_status,
    rented_until: form.value.rented_until,
    saleOrRent: form.value.saleOrRent,
      drive_link: form.value.drive_link
  });
  
  const sel = paymentPlanSelectionLabel(form.value.payment_plans);
  const missingOptions = sel && !paymentPlanOptions.includes(sel) ? [sel] : [];
  
  if (missingOptions.length > 0) {
    console.warn('⚠️ Missing payment plan options:', missingOptions);
  }
};
// Mounted
onMounted(async () => {
  console.log('🔧 Property Edit component mounted');
  
  if (route.params.id) {
    propertyId.value = route.params.id;
    
    // Load deal costs first
    await fetchDealCosts();
    
    // Load other data
    await Promise.all([
      fetchPropertyTypes(),
      fetchAreas(),
      fetchOwners(),
      fetchProjects(),
      fetchLayoutTypes(),
      fetchUnitViews(),
    ]);
    
    // Load property data
    await fetchPropertyData(propertyId.value);
    newFloorPlans.value = [];
    
    // After loading property data, ensure default costs are present
    // The loadAssignmentExpenseLines will handle this
    // But we can also call it explicitly to be safe
    setTimeout(() => {
      debugFormData();
    }, 500);
  } else {
    proxy.$showNotification("❌ No property ID provided", "error");
    router.back();
  }
});

// Cleanup on unmount
onUnmounted(() => {
  cleanupObjectURLs();
});
</script>

<style scoped>
.selected-tag {
  display: inline-flex;
  align-items: center;
  background: #e3f2fd;
  border: 1px solid #bbdefb;
  border-radius: 4px;
  padding: 2px 8px;
  margin: 2px;
  font-size: 0.875rem;
}

.tag-close {
  background: none;
  border: none;
  color: #666;
  cursor: pointer;
  font-size: 1.2rem;
  line-height: 1;
  margin-left: 4px;
  padding: 0;
}

.tag-close:hover {
  color: #f44336;
}

.noc-status-box {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  padding: 0.75rem 1rem;
  border-radius: 0.5rem;
  border: 1px solid transparent;
}

.noc-status-box.is-met {
  background: #ecfdf5;
  border-color: #a7f3d0;
  color: #065f46;
}

.noc-status-box.is-pending {
  background: #fffbeb;
  border-color: #fde68a;
  color: #92400e;
}

.noc-status-lines li {
  margin-bottom: 0.25rem;
}

.noc-remaining-highlight {
  background: #fef3c7;
  border: 1px solid #f59e0b;
  color: #78350f;
}

.noc-progress-wrap .progress {
  background-color: rgba(0, 0, 0, 0.08);
}

.payment-calc-summary {
  border-color: #e2e8f0 !important;
}

.payment-breakdown-card-body .payment-breakdown-date-picker :deep(.advanced-date-trigger) {
  min-height: calc(1.5em + 0.75rem + 2px);
  padding: 0.375rem 0.75rem;
  border-radius: var(--bs-border-radius, 0.375rem);
  border-color: var(--bs-border-color, #dee2e6);
  font-size: 1rem;
}

.payment-breakdown-card-body .payment-breakdown-date-picker :deep(.advanced-date-text) {
  font-size: 1rem;
  font-weight: 400;
}

.payment-breakdown-card-body .payment-breakdown-date-picker :deep(.advanced-date-text.is-placeholder) {
  font-size: 12px;
  opacity: 0.7;
}

.payment-breakdown-actions {
  gap: 0.5rem;
}

.assignment-expenses-panel {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 0.75rem;
  padding: 1rem 1.25rem;
}

.assignment-expenses-panel__title {
  font-size: 0.95rem;
  font-weight: 600;
  color: #1e293b;
}

.assignment-expenses-table-wrap {
  max-height: min(420px, 55vh);
  overflow: auto;
  border-radius: 0.5rem;
  border: 1px solid #e2e8f0;
  background: #fff;
}

.assignment-expenses-table thead th {
  position: sticky;
  top: 0;
  z-index: 1;
  background: #f1f5f9;
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #64748b;
  font-weight: 600;
  white-space: nowrap;
}

.assignment-expenses-table tbody td {
  vertical-align: middle;
  font-size: 0.8125rem;
}

.assignment-expenses-inline-input {
  min-width: 5rem;
  border-color: #e2e8f0;
  background: #fff;
}

.assignment-expenses-value-input {
  max-width: 6.5rem;
}

.assignment-expenses-inline-select :deep(.vs__dropdown-toggle) {
  min-height: 31px;
  font-size: 0.8125rem;
  border-color: #e2e8f0;
}

.assignment-expenses-summary__label {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #64748b;
  margin-bottom: 0.15rem;
}

.assignment-expenses-summary__value {
  font-size: 0.95rem;
  font-weight: 600;
  color: #334155;
}

.assignment-expenses-summary__value--grand {
  color: #0c2461;
  font-size: 1.05rem;
}

.assignment-expenses-vat-inline {
  cursor: pointer;
  user-select: none;
}

@media (max-width: 767.98px) {
  .assignment-expenses-panel {
    padding: 0.75rem;
  }
}

.payment-validation-summary {
  border-color: #e2e8f0 !important;
}

.payment-validation-summary-list .payment-val-ok {
  color: #047857;
}

.payment-validation-summary-list .payment-val-warn {
  color: #b45309;
}

.payment-validation-summary-list .payment-val-err {
  color: #b91c1c;
}

.payment-val-icon {
  flex-shrink: 0;
  width: 1.25rem;
  text-align: center;
}

.noc-summary-card {
  border-color: #e2e8f0 !important;
}

.v-select .vs__selected-options {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.total-gallery-count {
  margin-left: auto;
}

.floor-plan-image,
.gallery-image {
  height: 200px;
  object-fit: cover;
}

.text-primary {
  color: #0d6efd !important;
}

.text-success {
  color: #198754 !important;
}

.badge {
  font-size: 0.75em;
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  background-color: rgba(0, 0, 0, 0.55);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1050;
  padding: 15px;
}

.modal-container {
  background: #fff;
  border-radius: 12px;
  width: 95%;
  max-width: 1400px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 4px 25px rgba(0, 0, 0, 0.25);
  padding: 30px;
}

@media (max-width: 768px) {
  .card-footer .d-flex {
    flex-direction: column;
  }
  
  .card-footer .btn {
    margin-bottom: 10px;
    width: 100%;
  }
}

.v-select {
  --vs-border-radius: 8px;
}

.card {
  border: 1px solid #e9ecef;
  border-radius: 8px;
}

.card-header {
  background-color: #f8f9fa;
  border-bottom: 1px solid #e9ecef;
}

.btn-close {
  opacity: 0;
  transition: opacity 0.2s ease;
}

.gallery-item:hover .btn-close,
.floor-plan-item:hover .btn-close {
  opacity: 1;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.gallery-item.hero-image .card {
  border: 2px solid var(--bs-primary) !important;
}

.card-header.bg-primary {
  background: linear-gradient(135deg, var(--bs-primary), #0056b3) !important;
}

.btn-sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
}

.text-warning {
  color: #ffc107 !important;
}

.fa-exclamation-triangle {
  margin-right: 4px;
}

.v-select.project-areas .vs__dropdown-toggle {
  background-color: #e8f4fd !important;
  border-color: #0d6efd !important;
}

.v-select.project-areas .vs__selected {
  color: #0d6efd !important;
  font-weight: 600;
}

.v-select.project-areas .vs__dropdown-menu {
  border-color: #0d6efd;
}

.footer-pt {
  padding-top: 1.5rem !important;
}
.modal-header h5 {
  font-size: 1.5rem;
  font-weight: 600;
  margin:  10px;
  
}

.modal-header .btn-close {
  border-radius: 50%;
  padding: 8px;
  opacity: 0.8;
  transition: all 0.3s ease;
  color: white;
  border: none;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  
}

.modal-header .btn-close:hover {
  opacity: 1;
  transform: rotate(90deg);
}
.modal-backdrop .modal-container .modal-header{
    top:-10px;
}
.modal-body {
  padding: 30px;
  top:30px;
}
.vs__selected-options{
     height: auto;
}
.vs--searchable .vs__dropdown-toggle {
    min-height: 2.75rem !important;
    height: auto !important;
    padding-bottom: 0;
}
.selected-tag{
        margin: 2px;
    border: 1px solid #202645;
    border-radius: 10px;
    padding: 2px 5px;
    background: #202645;
    color: white;
}
body.swal2-toast-shown  {
    z-index: 10000 !important;
}
/* أضف في نهاية الـ style */
.project-floor-plan-item .card {
  transition: all 0.3s ease;
  border: 1px solid #e9ecef;
}

.project-floor-plan-item .card:hover {
  transform: translateY(-5px);
  box-shadow: 0 5px 15px rgba(0, 123, 255, 0.2);
  border-color: #0d6efd;
}

.project-floor-plan-item .btn-success {
  width: 32px;
  height: 32px;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
}

.nav-tabs {
  border-bottom: 2px solid #dee2e6;
}

.nav-tabs .nav-link {
  border: none;
  color: #6c757d;
  font-weight: 500;
  padding: 0.75rem 1rem;
  transition: all 0.3s ease;
}

.nav-tabs .nav-link:hover {
  color: #0d6efd;
  border: none;
}

.nav-tabs .nav-link.active {
  color: #0d6efd;
  background-color: transparent;
  border: none;
  border-bottom: 3px solid #0d6efd;
  font-weight: 600;
}

.tab-content {
  padding: 1.5rem 0;
}

.floor-plan-item .card {
  transition: all 0.3s ease;
}

.floor-plan-item .card:hover {
  transform: translateY(-3px);
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.floor-plan-item .btn-close {
  opacity: 0;
  transition: opacity 0.3s ease;
}

.floor-plan-item:hover .btn-close {
  opacity: 1;
}

.border-info {
  border-color: #0dcaf0 !important;
}

.border-success {
  border-color: #198754 !important;
}

.bg-info {
  background-color: #0dcaf0 !important;
}

.text-truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.form-control-sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.875rem;
}

.badge {
  font-size: 0.65em;
  padding: 0.25em 0.6em;
}
/* Floor Plans Section Styles */
.floor-plan-item .card {
  transition: all 0.3s ease;
  border: 1px solid #e9ecef;
}

.floor-plan-item .card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
  border-color: #0d6efd;
}

.project-floor-plan-item .card {
  transition: all 0.3s ease;
}

.project-floor-plan-item .card:hover {
  transform: translateY(-3px);
  box-shadow: 0 4px 15px rgba(0, 123, 255, 0.15);
}

/* Custom column for 5 items per row */
.col-xl-2-4 {
  flex: 0 0 20%;
  max-width: 20%;
}

/* Responsive adjustments */
@media (max-width: 1400px) {
  .col-xl-2-4 {
    flex: 0 0 25%;
    max-width: 25%;
  }
}

@media (max-width: 1200px) {
  .col-xl-2-4 {
    flex: 0 0 33.333%;
    max-width: 33.333%;
  }
}

@media (max-width: 992px) {
  .col-xl-2-4 {
    flex: 0 0 50%;
    max-width: 50%;
  }
}

@media (max-width: 576px) {
  .col-xl-2-4 {
    flex: 0 0 100%;
    max-width: 100%;
  }
}

/* Nav Tabs */
.nav-tabs {
  border-bottom: 2px solid #dee2e6;
}

.nav-tabs .nav-item .nav-link {
  border: none;
  color: #6c757d;
  font-weight: 500;
  padding: 0.75rem 1.25rem;
  transition: all 0.3s ease;
  border-radius: 0.375rem 0.375rem 0 0;
  margin-bottom: -2px;
}

.nav-tabs .nav-item .nav-link:hover {
  color: #0d6efd;
  background-color: rgba(13, 110, 253, 0.05);
  border: none;
}

.nav-tabs .nav-item .nav-link.active {
  color: #0d6efd;
  background-color: #fff;
  border: none;
  border-bottom: 3px solid #0d6efd;
  font-weight: 600;
}

/* Badge Styles */
.badge {
  font-size: 0.75em;
  padding: 0.35em 0.65em;
  font-weight: 500;
}

/* Floor Plan Image */
.floor-plan-image {
  transition: transform 0.3s ease;
}

.floor-plan-item:hover .floor-plan-image {
  transform: scale(1.05);
}

/* Border Colors */
.border-success {
  border-color: #198754 !important;
}

.border-warning {
  border-color: #ffc107 !important;
}

.border-info {
  border-color: #0dcaf0 !important;
}

/* Alert Styles */
.alert-info {
  background: linear-gradient(135deg, #cff4fc 0%, #a6e9f5 100%);
  border-color: #9eeaf9;
}

.alert-warning {
  background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
  border-color: #ffe69c;
}

/* Button Groups */
.d-grid {
  display: grid;
  gap: 0.5rem;
}

/* Form Controls */
.form-control-sm {
  border-radius: 0.375rem;
  font-size: 0.875rem;
}

/* Small Text */
.small {
  font-size: 0.875rem;
}

/* Text Truncation */
.text-truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Card Title */
.card-title {
  font-weight: 600;
  margin-bottom: 0.5rem;
}
/* Floor Plan Viewer Modal */
.floor-plan-viewer-modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.9);
  backdrop-filter: blur(8px);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
  animation: viewerFadeIn 0.3s ease;
}

.viewer-container {
  background: white;
  border-radius: 16px;
  width: 90%;
  max-width: 1200px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  animation: viewerSlideUp 0.4s ease;
}

.viewer-header {
  background: linear-gradient(135deg, #0c2461 0%, #1e3799 100%);
  color: white;
  padding: 16px 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.viewer-title {
  margin: 0;
  font-size: 1.2rem !important;
  font-weight: 600;
  color:#fff !important;
}

.viewer-body {
  flex: 1;
  padding: 24px;
  overflow: auto;
  display: flex;
  justify-content: center;
  align-items: center;
  background: #f8f9fa;
}

.viewer-image {
  max-width: 100%;
  max-height: calc(90vh - 140px);
  object-fit: contain;
  border-radius: 8px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.viewer-footer {
  padding: 16px 24px;
  background: white;
  border-top: 1px solid #eaeaea;
  display: flex;
  justify-content: flex-end;
}

.viewer-controls {
  display: flex;
  gap: 12px;
}

.btn-close-white {
  color: white;
  opacity: 0.8;
  transition: all 0.3s ease;
}

.btn-close-white:hover {
  opacity: 1;
  transform: rotate(90deg);
}

@keyframes viewerFadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes viewerSlideUp {
  from { transform: translateY(30px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .viewer-container {
    width: 95%;
    max-height: 95vh;
  }
  
  .viewer-body {
    padding: 16px;
  }
  
  .viewer-header {
    padding: 12px 16px;
  }
  
  .viewer-footer {
    padding: 12px 16px;
  }
  
  .viewer-title {
    font-size: 1rem;
  }
}

@media (max-width: 576px) {
  .viewer-controls {
    width: 100%;
  }
  
  .viewer-controls .btn {
    flex: 1;
  }
}
/* Floor Plans Styles - مبسطة */
.floor-plan-card {
  cursor: pointer;
  transition: all 0.3s ease;
}

.floor-plan-card .card {
  transition: all 0.3s ease;
  border: 2px solid transparent;
}

.floor-plan-card:hover .card {
  transform: translateY(-5px);
  box-shadow: 0 10px 25px rgba(0,0,0,0.1);
  border-color: #0d6efd;
}

.floor-plan-card.selected .card {
  border-color: #198754;
  background-color: rgba(25, 135, 84, 0.05);
}

.floor-plan-card.existing .card {
  border-color: #6c757d;
  opacity: 0.9;
}

.floor-plan-item .card {
  transition: all 0.3s ease;
  border-width: 2px;
}

.floor-plan-item .card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

.border-primary {
  border-color: #0d6efd !important;
}

.border-success {
  border-color: #198754 !important;
}

.border-warning {
  border-color: #ffc107 !important;
}

/* Custom column for 5 items per row */
.col-xl-2-4 {
  flex: 0 0 20%;
  max-width: 20%;
}

@media (max-width: 1400px) {
  .col-xl-2-4 {
    flex: 0 0 25%;
    max-width: 25%;
  }
}

@media (max-width: 1200px) {
  .col-xl-2-4 {
    flex: 0 0 33.333%;
    max-width: 33.333%;
  }
}

@media (max-width: 992px) {
  .col-xl-2-4 {
    flex: 0 0 50%;
    max-width: 50%;
  }
}

@media (max-width: 576px) {
  .col-xl-2-4 {
    flex: 0 0 100%;
    max-width: 100%;
  }
}
.listing-feature-grid {
  /* wrap onto multiple rows, each pill sized to its text */
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 4px;
}

.listing-feature-item {
  /* width = text content, no stretching, no forced min-width */
  flex: 0 0 auto;
  width: max-content;
  max-width: 100%;

  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  min-height: 30px;
  padding: 5px 14px;
  box-sizing: border-box;
  background: #fff;
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.listing-feature-item:hover {
  border-color: #cbd5e1;
  background: #f8fafc;
}

.listing-feature-item.is-selected {
  border-color: #f59e0b;
  background: #fff7e6;
  box-shadow: 0 0 0 1px rgba(245, 158, 11, 0.15);
}

.listing-feature-label {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 500;
  line-height: 1.2;
  color: #334155;
  text-align: center;
  flex: 1;
}

/* Drag & drop cues for the listing gallery tiles */
.listing-gallery-draggable .gallery-item {
  cursor: grab;
  user-select: none;
}
.listing-gallery-draggable .gallery-item:active {
  cursor: grabbing;
}
.listing-gallery-ghost {
  opacity: 0.45;
  background: #f1f5f9;
}
.listing-gallery-chosen {
  box-shadow: 0 8px 20px rgba(15, 23, 42, 0.25);
}
.listing-gallery-drag {
  transform: rotate(2deg);
}
</style>