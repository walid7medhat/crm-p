<template>
  <div class="dashboard-main-body property-form-page">
  <div class="row gy-4 mt-2 property-form-root">

    <!-- 🏡 Property Details -->
    <div class="col-lg-12">
      <div class="card">
        <div class="card-header">
          <h6 class="card-title mb-0">Property Details</h6>
        </div>
        <div class="card-body">
          <div class="row gy-3">
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
            <!--<div class="col-md-4" v-if="form.rented_status === 'Rented' ">-->
            <!--  <label class="form-label">Rented Until</label>-->
            <!--  <input -->
            <!--    v-model="form.rented_until" -->
            <!--    type="date" -->
            <!--    class="form-control" -->
            <!--    placeholder="Select date"-->
            <!--  />-->
            <!--</div>-->

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
            
            <div class="col-md-4">
              <label class="form-label">Completion Status</label>
              <v-select 
                v-model="form.completionStatus" 
                :options="completionStatusOptions" 
                placeholder="Select Completion Status"
              />
            </div>
            
            <div class="col-md-4">
              <label class="form-label">Project 
                <span v-if="!selectedProject" class="text-danger">*</span></label>
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
           
            <!-- Area -->
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
                :class="{ 'project-areas': selectedProject }"
              />
              <div v-if="isLoadingAreas" class="text-muted small mt-1">Loading areas...</div>
              
              <div v-if="selectedProject && form.projectAreas.length === 0" class="text-warning small mt-1">
                <i class="fas fa-exclamation-circle"></i>
                No specific areas found for this project. Please select from general areas.
              </div>
            </div>
        
            <!--<div class="col-md-4">-->
            <!--  <label class="form-label">Unit Number</label>-->
            <!--  <input v-model="form.unit_number" type="text" class="form-control" placeholder="Enter unit number" />-->
            <!--</div>-->
            <div class="col-md-4">
              <label class="form-label">Unit Number</label>
              <input 
                v-model="form.unit_number" 
                type="text" 
                class="form-control" 
                :class="{ 'is-invalid': unitNumberError }"
                placeholder="Enter unit number" 
                @blur="validateUnitNumber"
                @input="clearUnitNumberError"
              />
              <div v-if="isLoadingUnitNumber" class="text-muted small mt-1">
                <i class="fas fa-spinner fa-spin me-1"></i>
                Checking unit number...
              </div>
              <div v-if="unitNumberError" class="invalid-feedback d-block">
                <i class="fas fa-exclamation-circle me-1"></i>
                {{ unitNumberError }}
              </div>
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
           
            <div class="col-md-3">
              <label class="form-label">Size (sqm)</label>
              <input
                v-model.number="form.size_sqmt" placeholder="Enter Size (sqm)"
                type="number"
                class="form-control"
                @blur="convertSqmToSqft"
                @keydown="preventNumberInvalidKeys"
              />
            </div>

            <div class="col-md-3">
              <label class="form-label">Size (sqft)</label>
              <input
                v-model.number="form.size_sqft"
                type="number"
                class="form-control"
                @blur="convertSqftToSqm" placeholder="Enter Size (sqft)"
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
                :value="formatPriceInputDisplay(form.original_price)"
                type="text"
                inputmode="numeric"
                class="form-control"
                placeholder="Original price in AED"
                @input="form.original_price = parsePriceInputDigits($event.target.value)"
              />
            </div>
            <div class="col-md-4">
              <label class="form-label">Selling price <span class="text-muted fw-normal small">(listing price)</span></label>
              <input
                :value="formatPriceInputDisplay(form.price)"
                type="text"
                inputmode="numeric"
                class="form-control"
                placeholder="Selling price in AED"
                @input="form.price = parsePriceInputDigits($event.target.value)"
              />
            </div>
            <div v-if="form.completionStatus !== 'Completed'" class="col-md-4">
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
            <!-- <div v-else-if="form.completionStatus === 'Completed'" class="col-md-4">
              <div class="alert alert-info py-2 px-3 mb-0 small">
                <i class="fas fa-info-circle me-1"></i>
                No payment plan required for completed properties.
              </div>
            </div> -->
            <div v-if="sellingPriceVsOpWarning" class="col-12">
              <div class="alert alert-warning py-2 px-3 mb-0 small" role="status">{{ sellingPriceVsOpWarning }}</div>
            </div>
          </div>

          <!-- <div v-if="!isUnderConstruction" class="alert alert-light border mb-0 mt-2" role="note">
            <p class="mb-0 text-muted small">
              Set <strong>Completion status</strong> to <strong>Under Construction</strong> to add installments, NOC, and assignment costs below.
            </p>
          </div> -->

          <div class="row gy-3 mt-1">
            <div  v-if="isUnderConstruction" class="col-12">
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
            <!-- <div class="col-md-4">
              <label class="form-label">NOC <span class="text-muted fw-normal small">(% of original price)</span></label>
              <v-select
                v-model="form.noc_percentage"
                :options="nocPercentageOptions"
                :reduce="(item) => item.value"
                label="label"
                placeholder="0 – 50"
              />
              <small class="text-muted d-block mt-1">
                NOC % applies to <strong>original price (OP)</strong> only (not the payment-plan split). Example: OP 1,000,000 AED and NOC 25% → <strong>250,000 AED</strong> must be covered by total installments entered below.
                <strong>0</strong> = no NOC payment check.
              </small>
            </div> -->
          <div class="col-md-4" v-if="showNocField">
            <label class="form-label">
              NOC Fees 
              <span class="text-muted fw-normal small">(AED)</span>
              <span class="badge bg-info text-dark ms-2">{{ currentNocType }}</span>
            </label>
            
            <div class="input-group">
              <span class="input-group-text bg-light">
                <i class="fas fa-shield-alt text-muted"></i>
              </span>
              <input
                v-model.number="form.noc_fixed_amount"
                type="number"
                min="0"
                step="1000"
                class="form-control bg-light"
                :disabled="true"
                placeholder="Enter NOC amount"
              />
            
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

       
            <!-- NOC Summary Card - يظهر فقط عند تفعيل NOC -->
            <div v-if="showNocField  && nocFixedAmount > 0" class="col-md-12">
              <div class="noc-summary-card border rounded-3 p-3 mb-3 bg-white">
                <div class="text-uppercase text-muted small fw-semibold mb-2">
                  NOC summary 
                  <span class="badge bg-light text-dark ms-2">{{ currentNocType }}</span>
                </div>
                <div class="row g-2 small">
                  <div class="col-md-4">
                    <div class="text-muted">NOC required</div>
                    <div class="fw-semibold">{{ formatAed(nocRequiredAed) }}</div>
                    <div v-if="nocPercentOfOp > 0" class="text-muted">({{ nocPercentOfOp.toFixed(1) }}% of OP)</div>
                  </div>
                  <div class="col-md-4">
                    <div class="text-muted">Scheduled installments</div>
                    <div class="fw-semibold">{{ formatAed(scheduledInstallmentsAed) }}</div>
                    <div class="text-muted small">Past due: {{ formatAed(paidAmountForm) }}</div>
                  </div>
                  <div class="col-md-4">
                    <div class="text-muted">Remaining for NOC</div>
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
                  <td>{{ formatAed(row.amount) }}</td>
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


          <section class="assignment-expenses-panel mt-4" aria-labelledby="assignment-expenses-heading">
            <div class="assignment-expenses-panel__head d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
              <div>
                <h6 id="assignment-expenses-heading" class="assignment-expenses-panel__title mb-0">Assignment deal costs</h6>
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
                    id="assignment-expense-draft-vat"
                    v-model="assignmentExpenseDraft.vatEnabled"
                    class="form-check-input"
                    type="checkbox"
                  />
                  <label class="form-check-label small" for="assignment-expense-draft-vat">VAT 5%</label>
                </div>
              </div>
              <div class="col-12 col-md-2">
                <button type="button" class="btn btn-sm btn-outline-primary w-100" @click="addAssignmentExpenseLine">
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
                          disabled
                          :checked="line.label == 'Agency Fee'"
                        />
                          <span>{{ line.label === 'Agency Fee' ? formatAed(assignmentExpenseLineVat(line)) : 'inc vat' }}</span>
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
    </div>

    <!-- 💰 Mortgage & Rent Info -->
          <div class="col-lg-12">
          <div class="card">
            <div class="card-header">
              <h6 class="card-title mb-0">Mortgage & Rent Info</h6>
            </div>
            <div class="card-body">
              <div class="row gy-3">
                <div class="col-md-4">
                  <label class="form-label">Mortgage Status</label>
                  <v-select 
                    v-model="form.mortgageStatus" 
                    :options="mortgageStatusOptions" 
                    placeholder="Select Mortgage Status"
                  />
                </div>
    
                <div v-if="form.mortgageStatus !== 'Non-Mortgaged'" class="col-md-4">
                  <label class="form-label">Mortgage Amount</label>
                  <input v-model="form.mortgageAmount" type="number" class="form-control" placeholder="Enter Mortgage Amount" @keydown="preventNumberInvalidKeys" />
                </div>
    
                <!-- Force new row on desktop so first line only has Mortgage fields -->
                <div class="w-100 d-none d-md-block"></div>
    
                <div class="col-md-4" v-if="!isUnderConstruction">
                  <label class="form-label">Occupancy Status</label>
                  <v-select 
                    v-model="form.occupancyStatus" 
                    :options="occupancyStatusOptions" 
                    placeholder="Select Occupancy Status"
                  />
                </div>
    
                <template v-if="form.occupancyStatus === 'Rented' && !isUnderConstruction">
                  <div class="col-md-4">
                    <label class="form-label">Rent Expiry Date</label>
                    <input v-model="form.rentExpiryDate" type="date" class="form-control" placeholder="Enter Rent Expiry Date" />
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Rent Amount</label>
                    <input v-model="form.rentAmount" type="number" class="form-control" placeholder="Enter Rent Amount" @keydown="preventNumberInvalidKeys" />
                  </div>
                </template>
    
                <div v-if="form.mortgageStatus !== 'Non-Mortgaged'" class="col-md-12">
                  <label class="form-label">Comment</label>
                  <textarea v-model="form.mortgageComment" rows="3" class="form-control" placeholder="Enter Mortgage Comment" ></textarea>
                </div>
              </div>
            </div>
          </div>
        </div>

    <!-- 🖼️ Gallery Section -->
    <div class="col-lg-12">
      <div class="card">
        <div class="card-header">
          <h6 class="card-title mb-0">Property Gallery</h6>
        </div>
        <div class="card-body">
          <div class="row gy-3">
           <!--<div class="col-12">-->
           <!--   <label class="form-label">Google Drive Link (Optional)</label>-->
           <!--   <div class="input-group">-->
           <!--     <span class="input-group-text">-->
           <!--       <i class="fab fa-google-drive"></i>-->
           <!--     </span>-->
           <!--     <input -->
           <!--       v-model="form.driveLink" -->
           <!--       type="url" -->
           <!--       class="form-control" -->
           <!--       placeholder="https://drive.google.com/drive/folders/..."-->
           <!--     />-->
           <!--   </div>-->
           <!--   <div class="text-muted small mt-1">-->
           <!--         <small>You can add a Google Drive link containing additional property images</small>-->
           <!--     </div>-->
    
           <!-- </div>-->
            <div class="col-12">
              <label class="form-label">Upload Property Images</label>
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
                <strong>First image will be set as the hero image.</strong>
              </div>
            </div>

            <!-- Gallery Preview (drag tiles to reorder; the first tile is the hero) -->
            <div class="col-12" v-if="form.gallery.length > 0">
              <label class="form-label mb-3">
                Gallery Preview
                <small class="text-muted ms-2">Drag images to reorder — the first one is the hero.</small>
              </label>
              <draggable
                v-model="form.gallery"
                tag="div"
                class="row g-3 listing-gallery-draggable"
                item-key="_uid"
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
                  <div class="gallery-item position-relative" :class="{ 'hero-image': index === 0 }">
                    <div class="card h-100 border-primary" v-if="index === 0">
                      <div class="card-header bg-primary text-white py-1 text-center">
                        <small><i class="fas fa-star me-1"></i> Hero Image</small>
                      </div>
                      <img 
                        :src="item.preview || getImagePreview(item.file || item)" 
                        class="card-img-top gallery-image" 
                        alt="Gallery image"
                        style="height: 200px; object-fit: cover;"
                        @error="handleImageError"
                      />
                      <div class="card-body p-3">
                        <p class="card-text small text-truncate mb-1">{{ item.name || item.file?.name }}</p>
                        <p class="card-text small text-muted">{{ formatFileSize(item.size || item.file?.size) }}</p>
                      </div>
                      <button
                        type="button"
                        class="btn-close no-drag position-absolute top-0 end-0 m-2 bg-danger rounded-circle p-1"
                        @click="removeGalleryImage(index)"
                        style="--bs-bg-opacity: 0.8;"
                      ></button>
                    </div>
                    
                    <div class="card h-100" v-else>
                      <img 
                        :src="item.preview || getImagePreview(item.file || item)" 
                        class="card-img-top gallery-image" 
                        alt="Gallery image"
                        style="height: 200px; object-fit: cover;"
                        @error="handleImageError"
                      />
                      <div class="card-body p-3">
                        <p class="card-text small text-truncate mb-1">{{ item.name || item.file?.name }}</p>
                        <p class="card-text small text-muted">{{ formatFileSize(item.size || item.file?.size) }}</p>
                        <div class="d-flex gap-1 mt-2">
                          <button
                            type="button"
                            class="btn btn-sm btn-outline-primary no-drag"
                            @click="setAsHeroImage(index)"
                            title="Set as hero image"
                          >
                            Set as hero image
                          </button>
                        </div>
                      </div>
                      <button
                        type="button"
                        class="btn-close no-drag position-absolute top-0 end-0 m-2 bg-danger rounded-circle p-1"
                        @click="removeGalleryImage(index)"
                        style="--bs-bg-opacity: 0.8;"
                      ></button>
                    </div>
                  </div>
                </div>
                </template>
              </draggable>
            </div>

            <!-- Empty State -->
            <div class="col-12" v-else>
              <div class="text-center py-5 border rounded bg-light">
                <i class="fas fa-images fa-3x text-muted mb-3"></i>
                <p class="text-muted mb-0">No images uploaded yet. Add some photos to showcase your property!</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Floor Plans Section -->
        <!-- Floor Plans Section -->
<div class="col-lg-12"  v-if="selectedProject && filteredFloorPlans.length > 0">
  <div class="card">
    <div class="card-header">
      <h6 class="card-title mb-0">Floor Plans</h6>
    </div>
    <div class="card-body">
      <div class="row gy-3">
        <!-- Project Floor Plans Gallery - Single Selection -->
        <div class="col-md-12" v-if="selectedProject && filteredFloorPlans.length > 0">
          <div class="card">
            <div class="card-header bg-light">
              <div class="d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0">Select One Floor Plan from Project</h6>
                <span class="badge bg-primary" v-if="selectedProjectFloorPlan">
                  <i class="fas fa-check me-1"></i>
                  Selected
                </span>
                <span class="badge bg-secondary" v-else>
                  <i class="fas fa-times me-1"></i>
                  Not Selected
                </span>
              </div>
            </div>
            <div class="card-body">
              <div class="row gy-3">
                <div class="col-12">
                  <div class="mb-3">
                    <label class="form-label mb-1">Choose One Floor Plan</label>
                    <div class="text-muted small">
                      Click on a plan to select it. Only one plan can be selected from the project.
                    </div>
                  </div>
                  
                  <!-- Floor Plans Gallery Grid - Single Selection -->
                  <div class="floor-plans-gallery">
                    <div class="row g-3">
                      <div 
                        v-for="floorPlan in filteredFloorPlans" 
                        :key="'project-plan-' + floorPlan.id"
                        class="col-xl-2-4 col-lg-3 col-md-4 col-6"
                      >
                        <div 
                          class="floor-plan-card position-relative h-100"
                          :class="{ 
                            'selected-single': isSelectedProjectFloorPlan(floorPlan),
                            'not-selected': selectedProjectFloorPlan && !isSelectedProjectFloorPlan(floorPlan)
                          }"
                        >
                          <div class="card h-100 border cursor-pointer">
                            <!-- Selection Badge -->
                            <div 
                              v-if="isSelectedProjectFloorPlan(floorPlan)" 
                              class="position-absolute top-0 end-0 m-2 z-1"
                            >
                              <span class="badge bg-success">
                                <i class="fas fa-check-circle me-1"></i>
                                Selected
                              </span>
                            </div>
                            
                            <!-- Floor Plan Image -->
                            <div class="floor-plan-image-container position-relative">
                              <img 
                                :src="floorPlan.image_url" 
                                :alt="floorPlan.name"
                                class="floor-plan-image w-100"
                                :style="{ 
                                  height: '150px', 
                                  objectFit: 'cover',
                                  cursor: 'pointer'
                                }"
                                @error="handleFloorPlanImageError($event, floorPlan)"
                               @click.stop="openFloorPlanViewer(floorPlan)"
                              />
                              
                              <!-- Selection Overlay -->
                              <div 
                                v-if="isSelectedProjectFloorPlan(floorPlan)"
                                class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
                                style="background: rgba(25, 135, 84, 0.2); pointer-events: none;"
                              >
                                <div class="selection-check">
                                  <i class="fas fa-check-circle fa-2x text-white"></i>
                                </div>
                              </div>
                              
                              <!-- Click to Select Overlay -->
                              <div 
                                v-else
                                class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
                                style="background: rgba(13, 110, 253, 0.1); opacity: 0; transition: opacity 0.3s; pointer-events: none;"
                                :style="{ opacity: floorPlan.isHovered ? 1 : 0 }"
                              >
                                <div class="selection-hint">
                                  <i class="fas fa-mouse-pointer fa-2x text-primary"></i>
                                </div>
                              </div>
                            </div>
                            
                            <!-- Floor Plan Info -->
                            <div class="card-body p-3">
                              <h6 
                                class="floor-plan-name text-truncate mb-2"
                                style="font-size: 0.9rem !important; font-weight: 600;"
                                :title="floorPlan.name"
                              >
                                {{ floorPlan.name }}
                              </h6>
                              
                              <!-- Plan Details -->
                              <div class="plan-details small text-muted">
                               
                                <div v-if="floorPlan.dimensions" class="mb-1">
                                  <i class="fas fa-ruler-combined me-1"></i>
                                  {{ floorPlan.dimensions }}
                                </div>
                              </div>
                              
                              <!-- Action Buttons -->
                              <div class="floor-plan-actions mt-3">
                                <div class="d-grid">
                                  <button 
                                    type="button"
                                    class="btn btn-sm"
                                    :class="isSelectedProjectFloorPlan(floorPlan) ? 'btn-danger' : 'btn-outline-primary'"
                                    @click.stop="isSelectedProjectFloorPlan(floorPlan) ? clearSelectedProjectFloorPlan() : selectSingleProjectFloorPlan(floorPlan)"
                                  >
                                    <i 
                                      class="fas fa-fw me-1"
                                      :class="isSelectedProjectFloorPlan(floorPlan) ? 'fa-times' : 'fa-check'"
                                    ></i>
                                    {{ isSelectedProjectFloorPlan(floorPlan) ? 'Deselect' : 'Select This Plan' }}
                                  </button>
                                </div>
                              </div>
                            </div>
                            
                            <!-- Selected Border -->
                            <div 
                              v-if="isSelectedProjectFloorPlan(floorPlan)"
                              class="position-absolute top-0 start-0 w-100 h-100 border-3 border-success"
                              style="pointer-events: none; border-radius: inherit;"
                            ></div>
                          </div>
                        </div>
                      </div>
                    </div>
                    
                    <!-- Empty State -->
                    <div 
                      v-if="projectFloorPlans.length === 0" 
                      class="text-center py-5"
                    >
                      <i class="fas fa-blueprint fa-3x text-muted mb-3"></i>
                      <p class="text-muted mb-0">No floor plans available for this project</p>
                    </div>
                      <div v-if="form.area && form.area.id && filteredFloorPlans.length === 0" 
                           class="text-center py-4 mt-3 bg-light rounded">
                        <i class="fas fa-map-marked-alt fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0">No floor plans available for the selected area</p>
                        <small class="text-muted">Try selecting a different area or clear the area filter</small>
                      </div>
                    
                    <!-- Selected Plan Display -->
                    <div v-if="selectedProjectFloorPlan" class="mt-4">
                      <div class="alert alert-success">
                        <div class="d-flex align-items-center">
                          <div class="flex-shrink-0">
                            <i class="fas fa-check-circle fa-2x"></i>
                          </div>
                          <div class="flex-grow-1 ms-3">
                            <h6 class="mb-1">Selected Floor Plan</h6>
                            <p class="mb-0">
                              <strong>{{ selectedProjectFloorPlan.name }}</strong>
                              <!--<span v-if="selectedProjectFloorPlan.order" class="ms-2">-->
                              <!--  (Order #{{ selectedProjectFloorPlan.order }})-->
                              <!--</span>-->
                            </p>
                          </div>
                          <button 
                            class="btn btn-sm btn-outline-danger"
                            @click="clearSelectedProjectFloorPlan"
                          >
                            <i class="fas fa-times me-1"></i>
                            Remove
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Upload Floor Plans Section - Multiple Uploads Allowed -->
        <!--<div class="col-12">-->
        <!--  <div class="card">-->
        <!--    <div class="card-header">-->
        <!--      <div class="d-flex justify-content-between align-items-center">-->
        <!--        <h6 class="card-title mb-0">Upload Custom Floor Plans</h6>-->
        <!--        <span class="badge bg-info" v-if="customFloorPlansCount > 0">-->
        <!--          {{ customFloorPlansCount }} uploaded-->
        <!--        </span>-->
        <!--      </div>-->
        <!--    </div>-->
        <!--    <div class="card-body">-->
        <!--      <div class="row gy-3">-->
        <!--        <div class="col-12">-->
        <!--          <div class="mb-3">-->
                    <!--<label class="form-label">Upload Your Own Floor Plans</label>-->
                    <!--<div class="text-muted small mb-2">-->
                    <!--  You can upload multiple floor plan images. These will be added alongside any selected project plan.-->
                    <!--</div>-->
        <!--            <input -->
        <!--              type="file" -->
        <!--              class="form-control" -->
        <!--              multiple -->
        <!--              @change="handleFloorPlanUpload" -->
        <!--              accept="image/*"-->
        <!--              ref="floorPlanInput"-->
        <!--            />-->
        <!--          </div>-->
                  
                  <!-- Current Floor Plans Display -->
        <!--          <div v-if="form.floorPlans.length > 0">-->
        <!--            <label class="form-label mb-3">Current Floor Plans</label>-->
                    
                    <!-- Stats -->
        <!--            <div class="row mb-3">-->
        <!--              <div class="col-md-4">-->
        <!--                <div class="card bg-light">-->
        <!--                  <div class="card-body text-center py-2">-->
        <!--                    <h5 class="mb-0">{{ form.floorPlans.length }}</h5>-->
        <!--                    <small class="text-muted">Total Plans</small>-->
        <!--                  </div>-->
        <!--                </div>-->
        <!--              </div>-->
        <!--              <div class="col-md-4">-->
        <!--                <div class="card bg-light">-->
        <!--                  <div class="card-body text-center py-2">-->
        <!--                    <h5 class="mb-0">{{ selectedProjectFloorPlan ? 1 : 0 }}</h5>-->
        <!--                    <small class="text-muted">From Project</small>-->
        <!--                  </div>-->
        <!--                </div>-->
        <!--              </div>-->
        <!--              <div class="col-md-4">-->
        <!--                <div class="card bg-light">-->
        <!--                  <div class="card-body text-center py-2">-->
        <!--                    <h5 class="mb-0">{{ customFloorPlansCount }}</h5>-->
        <!--                    <small class="text-muted">Custom Uploads</small>-->
        <!--                  </div>-->
        <!--                </div>-->
        <!--              </div>-->
        <!--            </div>-->
                    
                    <!-- Floor Plans Grid -->
        <!--            <div class="floor-plans-preview">-->
        <!--              <div class="row g-3">-->
        <!--                <div -->
        <!--                  v-for="(item, index) in form.floorPlans" -->
        <!--                  :key="index"-->
        <!--                  class="col-xl-2-4 col-lg-3 col-md-4 col-6"-->
        <!--                >-->
        <!--                  <div class="floor-plan-item position-relative">-->
        <!--                    <div class="card h-100">-->
                              <!-- Source Badge -->
        <!--                      <div class="position-absolute top-0 start-0 m-2 z-1">-->
        <!--                        <span -->
        <!--                          class="badge"-->
        <!--                          :class="item.fromProject ? 'bg-success' : 'bg-primary'"-->
        <!--                        >-->
        <!--                          <i -->
        <!--                            class="fas fa-fw me-1"-->
        <!--                            :class="item.fromProject ? 'fa-building' : 'fa-upload'"-->
        <!--                          ></i>-->
        <!--                          {{ item.fromProject ? 'Project' : 'Custom' }}-->
        <!--                        </span>-->
        <!--                      </div>-->
                              
                              <!-- Image -->
        <!--                      <img -->
        <!--                        :src="item.preview || getImagePreview(item.file || item)" -->
        <!--                        class="card-img-top" -->
        <!--                        alt="Floor plan"-->
        <!--                        style="height: 150px; object-fit: cover;"-->
        <!--                        @error="handleImageError"-->
        <!--                      />-->
                              
                              <!-- Content -->
        <!--                      <div class="card-body p-3">-->
        <!--                        <div class="mb-2">-->
        <!--                          <label class="form-label small">Plan Name</label>-->
        <!--                          <input -->
        <!--                            v-model="item.customName" -->
        <!--                            type="text" -->
        <!--                            class="form-control form-control-sm" -->
        <!--                            placeholder="Enter plan name"-->
        <!--                            @change="updateFloorPlanName(index, $event)"-->
        <!--                          />-->
        <!--                        </div>-->
        <!--                        <p class="small text-truncate mb-1" :title="item.name || item.file?.name">-->
        <!--                          {{ item.name || item.file?.name }}-->
        <!--                        </p>-->
        <!--                        <p class="small text-muted">-->
        <!--                          {{ formatFileSize(item.size || item.file?.size) }}-->
        <!--                        </p>-->
        <!--                      </div>-->
                              
                              <!-- Remove Button -->
        <!--                      <button -->
        <!--                        type="button" -->
        <!--                        class="btn-close position-absolute top-0 end-0 m-2 bg-danger rounded-circle p-1"-->
        <!--                        @click="removeFloorPlan(index)"-->
        <!--                        style="--bs-bg-opacity: 0.8;"-->
        <!--                        :title="item.fromProject ? 'Remove project plan' : 'Remove custom plan'"-->
        <!--                      ></button>-->
        <!--                    </div>-->
        <!--                  </div>-->
        <!--                </div>-->
        <!--              </div>-->
        <!--            </div>-->
        <!--          </div>-->
                  
                  <!-- Empty State -->
        <!--          <div class="col-12" v-if="form.floorPlans.length === 0">-->
        <!--            <div class="text-center py-5 border rounded bg-light">-->
        <!--              <i class="fas fa-blueprint fa-3x text-muted mb-3"></i>-->
        <!--              <h6 class="text-muted mb-2">No Floor Plans Added</h6>-->
        <!--              <p class="text-muted small mb-3">-->
        <!--                Select one plan from the project or upload your own floor plans.-->
        <!--              </p>-->
        <!--              <button -->
        <!--                class="btn btn-outline-primary"-->
        <!--                @click="$refs.floorPlanInput.click()"-->
        <!--              >-->
        <!--                <i class="fas fa-upload me-2"></i>-->
        <!--                Upload Floor Plans-->
        <!--              </button>-->
        <!--            </div>-->
        <!--          </div>-->
        <!--        </div>-->
        <!--      </div>-->
        <!--    </div>-->
        <!--  </div>-->
        <!--</div>-->
      </div>
    </div>
  </div>
</div>
   <!-- 📄 Property Documents Section -->
    <div class="col-lg-12">
      <div class="card">
        <div class="card-header">
          <h6 class="card-title mb-0">Property Documents</h6>
        </div>
        <div class="card-body">
          <div class="row gy-3">
            <!--<div class="col-md-4">-->
            <!--  <label class="form-label">SPA Document</label>-->
            <!--  <input-->
            <!--    type="file"-->
            <!--    class="form-control"-->
            <!--    accept=".pdf,.jpg,.jpeg,.png,.svg"-->
            <!--    @change="handlePropertyDocumentUpload($event, 'spa_document')"-->
            <!--  />-->
            <!--  <div v-if="form.spa_document" class="small text-muted mt-1 d-flex align-items-center justify-content-between gap-2">-->
            <!--    <span class="text-truncate">{{ form.spa_document.name }}</span>-->
            <!--    <button type="button" class="btn btn-sm btn-outline-danger" @click="removePropertyDocument('spa_document')">-->
            <!--      Remove-->
            <!--    </button>-->
            <!--  </div>-->
            <!--</div>-->

            <!--<div class="col-md-4">-->
            <!--  <label class="form-label">Other Document</label>-->
            <!--  <input-->
            <!--    type="file"-->
            <!--    class="form-control"-->
            <!--    accept=".pdf,.jpg,.jpeg,.png,.svg"-->
            <!--    @change="handlePropertyDocumentUpload($event, 'other_document')"-->
            <!--  />-->
            <!--  <div v-if="form.other_document" class="small text-muted mt-1 d-flex align-items-center justify-content-between gap-2">-->
            <!--    <span class="text-truncate">{{ form.other_document.name }}</span>-->
            <!--    <button type="button" class="btn btn-sm btn-outline-danger" @click="removePropertyDocument('other_document')">-->
            <!--      Remove-->
            <!--    </button>-->
            <!--  </div>-->
            <!--</div>-->

            <div class="col-12">
              <label class="form-label">Additional Documents</label>
              <input
                type="file"
                class="form-control"
                multiple
                accept=".pdf,.jpg,.jpeg,.png,.svg"
                @change="handleAdditionalDocumentsUpload"
              />
              <div v-if="form.additionalDocuments && form.additionalDocuments.length" class="mt-2">
                <div
                  v-for="(item, index) in form.additionalDocuments"
                  :key="'new-' + index"
                  class="d-flex align-items-center justify-content-between gap-2 small text-muted mb-1"
                >
                  <span class="text-truncate">{{ item.name || item.file?.name }}</span>
                  <button type="button" class="btn btn-sm btn-outline-danger" @click="removeAdditionalDocument(index)">
                    Remove
                  </button>
                </div>
              </div>
            </div>

            <div class="col-12">
              <div class="text-muted small">
                Allowed types: PDF, JPG, JPEG, PNG, SVG. Max 10MB per file.
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- Owner Section -->
    <div class="col-lg-12">
      <div class="card">
        <div class="card-header">
          <h6 class="card-title mb-0">Owner Details</h6>
        </div>
        <div class="card-body">
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
                + Add New Owner
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
              Save as Draft
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
              Publish Listing
            </button>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Add Owner Modal -->
    <div v-if="showAddOwner" class="modal-backdrop">
      <div class="modal-container">
        <!-- Modal Header -->
        <div class="modal-header d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-3">
          </div>
          <button class="btn-close" @click="showAddOwner = false">
            <i class="fas fa-times"></i>
          </button>
        </div>

        <!-- Modal Body -->
        <div class="modal-body">
          
          <!-- Section 1: Personal Information -->
          <div class="section">
            <div class="section-title">
              <i class="fas fa-id-card"></i>
              <span>Personal Information</span>
              <span class="badge badge-primary ms-2">Required</span>
            </div>
            
            <div class="row">
              <!-- Salutation -->
              <div class="col-md-3 col-sm-6">
                <label class="form-label required">Salutation</label>
                <v-select 
                  v-model="newOwner.salutation" 
                  :options="salutationOptions" 
                  placeholder="Select Salutation"
                  :clearable="true"
                />
              </div>

              <!-- First Name -->
              <div class="col-md-3 col-sm-6">
                <label class="form-label required">First Name</label>
                <input 
                  v-model="newOwner.first_name" 
                  type="text" 
                  class="form-control" 
                  placeholder="Enter First Name"
                  required
                  @input="filterNameInput('first_name')"
                />
              </div>

              <!-- Last Name -->
              <div class="col-md-3 col-sm-6">
                <label class="form-label required">Last Name</label>
                <input 
                  v-model="newOwner.last_name" 
                  type="text" 
                  class="form-control" 
                  placeholder="Enter Last Name"
                  required
                  @input="filterNameInput('last_name')"
                />
              </div>

              <!-- Email -->
              <div class="col-md-3 col-sm-6">
                <label class="form-label ">Email Address</label>
                <input 
                  v-model="newOwner.email" 
                  type="email" 
                  class="form-control" 
                  placeholder="Enter Email"
                />
              </div>
            </div>
          </div>

          <!-- Section 2: Contact Information -->
          <div class="section">
            <div class="section-title">
              <i class="fas fa-phone-alt"></i>
              <span>Contact Information</span>
              <span class="badge badge-primary ms-2">Required</span>
            </div>
            
            <div class="row">
              <!-- Primary Phone -->
              <div class="col-md-4 col-sm-6">
                <label class="form-label required">Primary Phone</label>
                <div class="input-group">
                  <input 
                    v-model="newOwner.phone_number" 
                    type="tel" 
                    class="form-control" 
                    placeholder="Enter Phone Number"
                    required
                    @input="filterNumberInput('phone_number')"
                  />
                </div>
              </div>

              <!-- WhatsApp -->
              <div class="col-md-4 col-sm-6">
                <label class="form-label">WhatsApp Number</label>
                <div class="input-group">
                  <input 
                    v-model="newOwner.whatsapp_number" 
                    type="tel" 
                    class="form-control" 
                    placeholder="Enter WhatsApp"
                    @input="filterNumberInput('whatsapp_number')"
                  />
                </div>
              </div>

              <!-- Secondary Phone -->
              <div class="col-md-4 col-sm-6">
                <label class="form-label">Secondary Phone</label>
                <div class="input-group">
                  <input 
                    v-model="newOwner.second_phone_number" 
                    type="tel" 
                    class="form-control" 
                    placeholder="Enter Second Phone"
                    @input="filterNumberInput('second_phone_number')"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Section 3: Nationality & Residence -->
          <div class="section">
            <div class="section-title">
              <i class="fas fa-globe"></i>
              <span>Nationality & Residence</span>
            </div>
            
            <div class="row">
              <!-- Nationality -->
              <div class="col-md-4 col-sm-6">
                <label class="form-label ">Nationality</label>
                <v-select 
                  v-model="newOwner.nationality" 
                  :options="nationalities" 
                  placeholder="Select Nationality" 
                  @update:modelValue="handleNationalityChange"
                  :clearable="true"
                />
              </div>

              <!-- Residency Status -->
              <div class="col-md-4 col-sm-6">
                <label class="form-label ">Residency Status</label>
                <v-select
                  v-model="newOwner.residency_status" 
                  :options="residencyStatusOptions"
                  :reduce="option => option.value"
                  placeholder="Select Residency Status"
                  :disabled="newOwner.nationality === 'UAE'"
                  :clearable="true"
                />
              </div>

              <!-- Location -->
              <div class="col-md-4 col-sm-6">
                <label class="form-label ">{{ getLocationLabel() }}</label>
                <v-select
                  v-model="newOwner.location_id" 
                  :options="locations"
                  :reduce="location => location.id"
                  :label="'name'"
                  :placeholder="getLocationPlaceholder()"
                  :disabled="!newOwner.residency_status"
                  :loading="isLoadingLocations"
                  :clearable="true"
                >
                  <template #option="location">
                    <div class="d-flex align-items-center gap-2">
                      <i class="fas fa-map-marker-alt text-primary"></i>
                      <span>{{ location.name }}</span>
                    </div>
                  </template>
                </v-select>
              </div>
            </div>
          </div>

          <!-- Section 4: Document Upload -->
          <div class="section">
            <div class="section-title">
              <i class="fas fa-file-upload"></i>
              <span>Document Upload</span>
              <span class="badge badge-warning ms-2">Optional</span>
            </div>
            
            <div class="row">
              <!-- ID Front -->
              <div class="col-md-3 col-sm-6">
                <label class="form-label">ID Front Copy</label>
                <div class="file-upload-area" @click="$refs.idFront.click()">
                  <div class="file-upload-icon">
                    <i class="fas fa-id-card"></i>
                  </div>
                  <div class="file-upload-text">Upload ID Front</div>
                  <div class="file-upload-hint">Max 10MB • JPG, PNG, PDF</div>
                  <input 
                    ref="idFront"
                    type="file" 
                    class="d-none" 
                    @change="handleNewOwnerFile($event, 'id_front')" 
                    accept=".jpg,.jpeg,.png,.pdf"
                  />
                </div>
                <div v-if="newOwner.id_front" class="mt-2">
                  <span class="badge badge-success">
                    <i class="fas fa-check me-1"></i>
                    {{ newOwner.id_front.name }}
                  </span>
                </div>
              </div>

              <!-- ID Back -->
              <div class="col-md-3 col-sm-6">
                <label class="form-label">ID Back Copy</label>
                <div class="file-upload-area" @click="$refs.idBack.click()">
                  <div class="file-upload-icon">
                    <i class="fas fa-id-card"></i>
                  </div>
                  <div class="file-upload-text">Upload ID Back</div>
                  <div class="file-upload-hint">Max 10MB • JPG, PNG, PDF</div>
                  <input 
                    ref="idBack"
                    type="file" 
                    class="d-none" 
                    @change="handleNewOwnerFile($event, 'id_back')" 
                    accept=".jpg,.jpeg,.png,.pdf"
                  />
                </div>
                <div v-if="newOwner.id_back" class="mt-2">
                  <span class="badge badge-success">
                    <i class="fas fa-check me-1"></i>
                    {{ newOwner.id_back.name }}
                  </span>
                </div>
              </div>

              <!-- Visa Copy -->
              <div class="col-md-3 col-sm-6">
                <label class="form-label">Visa Copy</label>
                <div class="file-upload-area" @click="$refs.visaCopy.click()">
                  <div class="file-upload-icon">
                    <i class="fas fa-passport"></i>
                  </div>
                  <div class="file-upload-text">Upload Visa</div>
                  <div class="file-upload-hint">Max 10MB • JPG, PNG, PDF</div>
                  <input 
                    ref="visaCopy"
                    type="file" 
                    class="d-none" 
                    @change="handleNewOwnerFile($event, 'visa_copy')" 
                    accept=".jpg,.jpeg,.png,.pdf"
                  />
                </div>
                <div v-if="newOwner.visa_copy" class="mt-2">
                  <span class="badge badge-success">
                    <i class="fas fa-check me-1"></i>
                    {{ newOwner.visa_copy.name }}
                  </span>
                </div>
              </div>

              <!-- Passport Copy -->
              <div class="col-md-3 col-sm-6">
                <label class="form-label">Passport Copy</label>
                <div class="file-upload-area" @click="$refs.passportCopy.click()">
                  <div class="file-upload-icon">
                    <i class="fas fa-passport"></i>
                  </div>
                  <div class="file-upload-text">Upload Passport</div>
                  <div class="file-upload-hint">Max 10MB • JPG, PNG, PDF</div>
                  <input 
                    ref="passportCopy"
                    type="file" 
                    class="d-none" 
                    @change="handleNewOwnerFile($event, 'passport_copy')" 
                    accept=".jpg,.jpeg,.png,.pdf"
                  />
                </div>
                <div v-if="newOwner.passport_copy" class="mt-2">
                  <span class="badge badge-success">
                    <i class="fas fa-check me-1"></i>
                    {{ newOwner.passport_copy.name }}
                  </span>
                </div>
              </div>
                <!-- Additional Documents -->
              <div class="col-md-12 mt-3">
                <label class="form-label">Additional Documents</label>
                <div class="file-upload-area" @click="$refs.additionalDocs.click()">
                  <div class="file-upload-icon">
                    <i class="fas fa-file-alt"></i>
                  </div>
                  <div class="file-upload-text">Upload Additional Documents</div>
                  <div class="file-upload-hint">You can select multiple files • Max 5MB each • JPG, PNG, PDF</div>
                  <input
                    ref="additionalDocs"
                    type="file"
                    class="d-none"
                    multiple
                    @change="handleNewOwnerAdditionalDocuments"
                    accept=".jpg,.jpeg,.png,.pdf"
                  />
                </div>
                <div v-if="newOwner.additional_documents && newOwner.additional_documents.length" class="mt-2">
                  <div
                    v-for="(file, index) in newOwner.additional_documents"
                    :key="index"
                    class="badge bg-secondary me-2 mb-2 d-inline-flex align-items-center"
                  >
                    <i class="fas fa-file me-1"></i>
                    <span class="text-truncate" style="max-width: 180px;">{{ file.name }}</span>
                    <button
                      type="button"
                      class="btn-close btn-close-white ms-2"
                      aria-label="Remove"
                      @click.stop="removeNewOwnerAdditionalDocument(index)"
                    ></button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Section 5: Additional Notes -->
          <div class="section">
            <div class="section-title">
              <i class="fas fa-sticky-note"></i>
              <span>Additional Notes</span>
              <span class="badge badge-warning ms-2">Optional</span>
            </div>
            
            <div class="col-md-12">
              <label class="form-label">Owner Notes</label>
              <textarea 
                v-model="newOwner.notes" 
                rows="4" 
                class="form-control" 
                placeholder="Add any additional notes about the owner..."
              ></textarea>
              <div class="text-muted mt-2">
                <small>Add any important information that might be useful for future reference.</small>
              </div>
            </div>
          </div>

        </div>

        <!-- Modal Footer -->
        <div class="modal-footer">
          <div class="d-flex justify-content-between w-100">
            <div>
              <button class="btn btn-outline-primary" @click="resetNewOwnerForm">
                <i class="fas fa-redo me-2"></i>
                Reset Form
              </button>
            </div>
            <div class="d-flex gap-3">
              <button class="btn btn-secondary" @click="showAddOwner = false">
                <i class="fas fa-times me-2"></i>
                Cancel
              </button>
              <button 
                class="btn btn-primary" 
                @click="submitNewOwner"
                :disabled="isSubmitting"
              >
                <i class="fas fa-save me-2"></i>
                <span v-if="isSubmitting">
                  <span class="spinner-border spinner-border-sm me-2"></span>
                  Saving...
                </span>
                <span v-else>Save Owner</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- Floor Plan Image Viewer Modal -->
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
  </div>
</template>

<script setup>
import { ref, watch, onMounted, computed, getCurrentInstance } from "vue";
import api from "@/plugins/axios";
import vSelect from "vue-select";
import { LISTING_FEATURE_OPTIONS, LISTING_FEATURE_KEYS } from "@/config/listingFeatures";
import draggable from "vuedraggable";
import "vue-select/dist/vue-select.css";
import PaymentDetailsPreviewModal from "@/components/payment-plans/PaymentDetailsPreviewModal.vue";
import AdvancedDatePicker from "@/components/shared/AdvancedDatePicker.vue";
import { parsePriceInputDigits, formatPriceInputDisplay } from "@/utils/priceInputFormat";
import Swal from "sweetalert2";
const { proxy } = getCurrentInstance();

const showPaymentDetailsPreview = ref(false);

const saleRentOptions = ['Sale', 'Rent'];
const rentedStatusOptions = ['Available', 'Rented']; 
/** Structured presets: `invalid` plans are listed but block publish until changed. */
const paymentPlanOptions = [
  { label: '50/50', initial_percent: 50, handover_percent: 50 },
  { label: '40/60', initial_percent: 40, handover_percent: 60 },
  { label: '80/20', initial_percent: 80, handover_percent: 20 },
  { label: '15/85', initial_percent: 15, handover_percent: 85 },
  { label: '65/35', initial_percent: 65, handover_percent: 35 },
  { label: '60/40', initial_percent: 60, handover_percent: 40 },
  { label: '20/80', initial_percent: 20, handover_percent: 80 },
  { label: '35/65', initial_percent: 35, handover_percent: 65 },
  { label: '10/90', initial_percent: 10, handover_percent: 90 },
  { label: '55/45', initial_percent: 55, handover_percent: 45 },
  { label: '45/55', initial_percent: 45, handover_percent: 55 },
  { label: '70/30', initial_percent: 70, handover_percent: 30 },
  { label: '30/70', initial_percent: 30, handover_percent: 70 },
  { label: '25/75', initial_percent: 25, handover_percent: 75 },
  { label: '75/25', initial_percent: 75, handover_percent: 25 },
  { label: '10/1% Monthly', initial_percent: 10, handover_percent: 90 },
  { label: '1% Monthly', initial_percent: 53, handover_percent: 47 },
  { label: '20/1% Monthly', initial_percent: 20, handover_percent: 80 },
  { label: '30/1% Monthly', initial_percent: 30, handover_percent: 70 },
  { label: '85/15', initial_percent: 85, handover_percent: 15 },
  { label: '90/10', initial_percent: 90, handover_percent: 10 },
  { label: '100%', initial_percent: 100, handover_percent: 0 },
  { label: '10% down payment, 8-year installments', initial_percent: null, handover_percent: null, invalid: true },
];

const findPaymentPlanOptionByLabel = (label) => {
  const s = String(label ?? '').trim();
  if (!s) return null;
  return paymentPlanOptions.find((p) => p.label === s) ?? null;
};

const attemptParseLegacyPaymentPlanLabel = (label) => {
  const s = String(label ?? '').trim();
  if (!s) return null;
  const m = s.match(/^(\d+(?:\.\d+)?)\s*\/\s*(\d+(?:\.\d+)?)$/);
  if (!m) return { label: s, initial_percent: null, handover_percent: null, invalid: true };
  const lo = Number(m[1]);
  const hi = Number(m[2]);
  if (![lo, hi].every(Number.isFinite)) return { label: s, initial_percent: null, handover_percent: null, invalid: true };
  if (lo < 0 || hi < 0 || lo > 100 || hi > 100) return { label: s, initial_percent: null, handover_percent: null, invalid: true };
  if (Math.abs(lo + hi - 100) > 0.01) return { label: s, initial_percent: null, handover_percent: null, invalid: true };
  return { label: s, initial_percent: lo, handover_percent: hi, legacyParsed: true };
};

const resolvePaymentPlanOption = (raw) => {
  if (raw == null || raw === '') return null;
  if (typeof raw === 'object' && !Array.isArray(raw) && raw.label != null) {
    const hit = findPaymentPlanOptionByLabel(raw.label);
    if (hit) return hit;
    if (Number.isFinite(raw.initial_percent) && Number.isFinite(raw.handover_percent)) return raw;
    return attemptParseLegacyPaymentPlanLabel(raw.label);
  }
  if (Array.isArray(raw) && raw.length > 0) return resolvePaymentPlanOption(raw[0]);
  if (typeof raw === 'string') return findPaymentPlanOptionByLabel(raw) ?? attemptParseLegacyPaymentPlanLabel(raw);
  return null;
};
const completionStatusOptions = ['Completed', 'Under Construction'];
const furnishedStatusOptions = ['Furnished', 'Unfurnished'];
const ownershipTypeOptions = ['Freehold', 'Leasehold'];
const mortgageStatusOptions = ['Mortgaged', 'Non-Mortgaged'];
const occupancyStatusOptions = [
  'Owner Occupied',
  'Holiday Home',
  'Rented',
  'Vacant'
];
const salutationOptions = ['Mr', 'Mrs', 'Ms'];
const residencyStatusOptions = [
  { value: 'resident', label: 'Resident' },
  { value: 'non_resident', label: 'Non Resident' }
];

const projects = ref([]);
const isLoadingProjects = ref(false);
const selectedProject = ref(null);
const owners = ref([]);
const selectedOwner = ref(null);
const showAddOwner = ref(false);
const isLoadingOwners = ref(false);
const unitViews = ref([]);
const isLoadingUnitViews = ref(false);
const layoutTypes = ref([]);
const isLoadingLayoutTypes = ref(false);
const propertyTypes = ref([]);
const isLoadingPropertyTypes = ref(false);
const developers = ref([]);
const isLoadingDevelopers = ref(false);
const areas = ref([]);
const isLoadingAreas = ref(false);

const projectFloorPlans = ref([]);
const selectedProjectFloorPlans = ref([]);
const isLoadingProjectFloorPlans = ref(false);

const showFloorPlanViewer = ref(false);
const selectedFloorPlanForViewer = ref(null);


const projectAreasForFilter = ref([]);
const selectedAreaForFilter = ref(null);
const filteredFloorPlans = ref([]);


const openFloorPlanViewer = (floorPlan) => {
  selectedFloorPlanForViewer.value = floorPlan;
  showFloorPlanViewer.value = true;
};

const closeFloorPlanViewer = () => {
  showFloorPlanViewer.value = false;
  selectedFloorPlanForViewer.value = null;
};

const newOwner = ref({
  salutation: "", first_name: "", last_name: "", email: "",
  phone_number: "", whatsapp_number: "", second_phone_number: "",
  nationality: "", residency_status: "", location_id: "",
  id_front: null, id_back: null, visa_copy: null, passport_copy: null,additional_documents: [], notes: "",
});
const locations = ref([]);
const isLoadingLocations = ref(false);
const isSubmitting = ref(false);
const hotDealOptions = ['Yes', 'No'];
const installmentTypeOptions = [
  { label: 'Percentage', value: 'percentage' },
  { label: 'Amount', value: 'amount' },
];

const form = ref({
  title: "", unit_number: "", ownership_type: null, saleOrRent: "",
  completionStatus: "", area: null, developer: null, property_type: null,
  price: "", original_price: "", number_of_bedrooms: "", number_of_bathrooms: "",
  layout_type: null, unit_view: null, furnished_status: "",
  size_sqmt: "", size_sqft: "", floorPlans: [], gallery: [],
  comment: "", mortgageStatus: "", occupancyStatus: "",
  mortgageAmount: "", rentExpiryDate: "", rentAmount: "",
  mortgageComment: "", projectAreas: [], rented_status: "",      
  rented_until: "", payment_plan: "", payment_plans: null, driveLink: "", is_hot_deal: "",
  handover_date: "",
  noc_percentage: 0,
  // Boolean for every known feature (from the shared LISTING_FEATURE_KEYS list).
  ...LISTING_FEATURE_KEYS.reduce((acc, k) => { acc[k] = false; return acc; }, {}),
    spa_document: null, desk_document: null, other_document: null,
  additionalDocuments: [],
});

/** Matches v-select value and common API spellings so the payment / NOC block can show after load. */
const isUnderConstruction = computed(() => {
  const s = String(form.value.completionStatus ?? '').trim().toLowerCase().replace(/_/g, ' ');
  return s === 'under construction' || s === 'off plan';
});

const breakdownInstallments = ref([]);
const installmentDraft = ref({
  type: 'percentage',
  value: null,
  date: new Date().toISOString().slice(0, 10),
});

const nocPercentageOptions = [
  { label: '0', value: 0 },
  { label: '10', value: 10 },
  { label: '20', value: 20 },
  { label: '30', value: 30 },
  { label: '40', value: 40 },
  { label: '50', value: 50 },
];
// ✅ جلب تكاليف الصفقة الثابتة
const dealCostSettings = ref([]);
const isLoadingDealCosts = ref(false);

const fetchDealCosts = async () => {
  try {
    isLoadingDealCosts.value = true;
    const response = await api.get("/settings/deal-costs");
    const data = response.data.data || response.data;
    
    console.log('📦 Deal cost API response:', data);
    
    // تخزين الإعدادات
    if (data.settings) {
      const settings = {};
      Object.keys(data.settings).forEach(key => {
        const numKey = parseInt(key) || key;
        settings[numKey] = data.settings[key];
      });
      // ✅ إضافة Agency Fee إذا لم تكن موجودة
      if (!settings['3'] && !settings['agency_fee']) {
        settings['agency_fee'] = 2; // 2% افتراضي
      }
      if (!settings['4'] && !settings['transfer_fee']) {
        settings['transfer_fee'] = 2; // 2% افتراضي
      }
      dealCostSettings.value = settings;
    } else if (data.details) {
      const settings = {};
      data.details.forEach(item => {
        settings[item.key] = item.value;
      });
      if (!settings['agency_fee']) {
        settings['agency_fee'] = 2;
      }
      if (!settings['transfer_fee']) {
        settings['transfer_fee'] = 2;
      }
      
      dealCostSettings.value = settings;
    } else {
      dealCostSettings.value = data;
      if (!dealCostSettings.value['agency_fee']) {
        dealCostSettings.value['agency_fee'] = 2;
      }
      if (!dealCostSettings.value['transfer_fee']) {
        dealCostSettings.value['transfer_fee'] = 2;
      }
    }
    
    console.log('✅ Deal cost settings loaded:', dealCostSettings.value);
    addDefaultDealCosts();
    
  } catch (error) {
    console.error('❌ Error fetching deal costs:', error);
    // ✅ استخدام قيم افتراضية
    dealCostSettings.value = {
      dari_admin_fee: 0,
      adgm_admin_fee: 0,
      agency_fee: 2, // ✅ 2% افتراضي
      transfer_fee:2,
    };
    addDefaultDealCosts();
    proxy.$showNotification("ℹ️ Using default deal costs", "info");
  } finally {
    isLoadingDealCosts.value = false;
  }
};


// ✅ إضافة التكاليف الثابتة تلقائياً (في صفحة الإنشاء)
const addDefaultDealCosts = () => {
  console.log('📝 Adding default deal costs. Settings:', dealCostSettings.value);
  console.log('📍 Current area in create form:', form.value.area);
  
  // ✅ استخدام المفاتيح النصية
  const fees = {
    dariAdminFee: dealCostSettings.value['dari_admin_fee'] || dealCostSettings.value['1'] || 0,
    adgmAdminFee: dealCostSettings.value['adgm_admin_fee'] || dealCostSettings.value['2'] || 0,
    agencyFee: dealCostSettings.value['agency_fee'] || dealCostSettings.value['3'] || 2,
    transferFee: dealCostSettings.value['transfer_fee'] || dealCostSettings.value['4'] || 2,
  };
  
  // ✅ 1. Agency Fee (2% من سعر البيع)
  const agencyFeeValue = Number(fees.agencyFee) || 2;
   const transferFeeValue = Number(fees.transferFee) || 2;
  console.log(`✅ Agency Fee: ${agencyFeeValue}% of Selling Price`);
  
  const existingAgency = assignmentExpenseLines.value.find(
    line => line.label === 'Agency Fee' && line.isDefault
  );
   const existingTransfer = assignmentExpenseLines.value.find(
    line => line.label === 'Transfer Fee' && line.isDefault
  );
  
  if (!existingAgency) {
    assignmentExpenseLines.value.push({
      id: Date.now() + 3,
      label: 'Agency Fee',
      calcType: 'percentage',
      base: 'sp',
      value: agencyFeeValue,
      vatEnabled: false,
      isDefault: true,
      isReadonly: true
    });
    console.log('✅ Agency Fee added');
  } else {
    existingAgency.value = agencyFeeValue;
    console.log('✅ Agency Fee updated');
  }
  if (!existingTransfer) {
    assignmentExpenseLines.value.push({
      id: Date.now() + 3,
      label: 'Transfer Fee',
      calcType: 'percentage',
      base: 'sp',
      value: transferFeeValue,
      vatEnabled: false,
      isDefault: true,
      isReadonly: true
    });
    console.log('✅ Transfer Fee added');
  } else {
    existingAgency.value = transferFeeValue;
    console.log('✅ Transfer Fee updated');
  }
  
  // ✅ 2. تحديد نوع Admin Fee بناءً على المنطقة
  const areaData = form.value.area;
  const feeType = getAdminFeeTypeFromArea(areaData);
  console.log(`📍 Admin fee type: ${feeType}`);
  
  // ✅ إزالة التكاليف الإدارية القديمة
  const adminFeeIndices = [];
  assignmentExpenseLines.value.forEach((line, index) => {
    if ((line.label === 'Dari Admin Fee' || line.label === 'ADGM Admin Fee') && line.isDefault) {
      adminFeeIndices.push(index);
    }
  });
  adminFeeIndices.reverse().forEach(index => {
    assignmentExpenseLines.value.splice(index, 1);
    console.log(`🗑️ Removed old admin fee at index ${index}`);
  });

  // ✅ 3. إضافة التكلفة الإدارية المناسبة
  if (feeType === 'adgm') {
    // ADGM Admin Fee
    if (Number(fees.adgmAdminFee) > 0) {
      assignmentExpenseLines.value.push({
        id: Date.now() + 2,
        label: 'ADGM Admin Fee',
        calcType: 'fixed',
        base: null,
        value: Number(fees.adgmAdminFee),
        vatEnabled: false,
        isDefault: true,
        isReadonly: true,
        isAdminFee: true,
      });
      console.log(`✅ ADGM Admin Fee added: ${fees.adgmAdminFee} AED`);
    }
  } else {
    // Dari Admin Fee
    if (Number(fees.dariAdminFee) > 0) {
      assignmentExpenseLines.value.push({
        id: Date.now() + 1,
        label: 'Dari Admin Fee',
        calcType: 'fixed',
        base: null,
        value: Number(fees.dariAdminFee),
        vatEnabled: false,
        isDefault: true,
        isReadonly: true,
        isAdminFee: true,
      });
      console.log(`✅ Dari Admin Fee added: ${fees.dariAdminFee} AED`);
    }
  }
  
  console.log('📊 Current assignmentExpenseLines:', assignmentExpenseLines.value);
};

// ✅ دالة لتحديد نوع التكلفة بناءً على المنطقة
const getAdminFeeTypeFromArea = (areaData) => {
  if (!areaData) return 'dari';
  
  // استخدام is_adgm من الـ API إذا كانت موجودة
  if (areaData.is_adgm !== undefined) {
    return areaData.is_adgm ? 'adgm' : 'dari';
  }
  
  // استخدام all_names
  if (areaData.all_names && Array.isArray(areaData.all_names)) {
    const adgmTerms = ['maryah island', 'reem island'];
    const isAdgm = areaData.all_names.some(name => 
      adgmTerms.some(term => 
        String(name).toLowerCase().includes(term.toLowerCase().trim())
      )
    );
    return isAdgm ? 'adgm' : 'dari';
  }
  
  // استخدام hierarchy
  if (areaData.hierarchy && Array.isArray(areaData.hierarchy)) {
    const adgmTerms = ['maryah island', 'reem island'];
    const isAdgm = areaData.hierarchy.some(h => 
      adgmTerms.some(term => 
        String(h.name || '').toLowerCase().includes(term.toLowerCase().trim())
      )
    );
    return isAdgm ? 'adgm' : 'dari';
  }
  
  // Fallback
  const areaName = String(areaData.name || areaData.area_title || areaData.title || '').toLowerCase();
  if (areaName.includes('maryah') || areaName.includes('reem')) {
    return 'adgm';
  }
  
  return 'dari';
};
const selectedPaymentPlanOption = computed(() => resolvePaymentPlanOption(form.value.payment_plans));

const isPaymentPlanSelectionParseValid = computed(() => {
  if (form.value.payment_plans == null || form.value.payment_plans === '') return true;
  const o = selectedPaymentPlanOption.value;
  if (!o) return false;
  if (o.invalid) return false;
  return Number.isFinite(o.initial_percent) && Number.isFinite(o.handover_percent);
});

const PAYMENT_PLAN_PARSE_ERROR =
  'This payment plan cannot be used for automated checks. Choose a standard split plan (for example 30/70).';

const paymentPlanFieldInvalid = computed(
  () =>
    isUnderConstruction.value &&
    form.value.payment_plans != null &&
    form.value.payment_plans !== '' &&
    !isPaymentPlanSelectionParseValid.value,
);

const paymentPlanFieldError = computed(() => (paymentPlanFieldInvalid.value ? PAYMENT_PLAN_PARSE_ERROR : ''));

const paymentPlanMissingForPublish = computed(
  () => isUnderConstruction.value && (form.value.payment_plans == null || form.value.payment_plans === ''),
);

/** v-model: preset object, legacy string, or legacy { label, value }. */
const paymentPlanSelectionLabel = (raw) => {
  if (raw == null || raw === '') return '';
  if (typeof raw === 'string') return raw;
  if (Array.isArray(raw) && raw.length > 0) {
    const p = raw[0];
    if (typeof p === 'string') return p;
    if (p && typeof p === 'object') return p.label || p.value || '';
  }
  if (typeof raw === 'object') return raw.label || raw.value || '';
  return '';
};

const firstPaymentPlanLabel = computed(() => paymentPlanSelectionLabel(form.value.payment_plans));

const selectedPaymentPlanLabel = computed(() => {
  const label = firstPaymentPlanLabel.value;
  return label || 'No plan selected';
});

const initialPercentForm = computed(() => {
  const o = selectedPaymentPlanOption.value;
  if (!o || o.invalid || !Number.isFinite(o.initial_percent)) return 0;
  return Math.max(0, Math.min(100, o.initial_percent));
});

const installmentPercentForm = computed(() => {
  const o = selectedPaymentPlanOption.value;
  if (!o || o.invalid) return 0;
  if (Number.isFinite(o.handover_percent)) return Math.max(0, Math.min(100, o.handover_percent));
  return Math.max(0, 100 - initialPercentForm.value);
});
const originalPriceNum = computed(() =>
  Number(parsePriceInputDigits(form.value.original_price) || parsePriceInputDigits(form.value.price) || 0),
);
/** OP from the Original price field only (for selling vs contract sanity checks). */
const originalContractPriceNum = computed(() => Number(parsePriceInputDigits(form.value.original_price) || 0));
const sellingPriceNum = computed(() => Number(parsePriceInputDigits(form.value.price) || 0));

const SELLING_SIGNIFICANTLY_BELOW_OP_MSG = 'Selling price is significantly below original price.';
/** Warn when selling &lt; this fraction of contract OP (e.g. 0.7 = below 70%). */
const SELLING_VS_OP_WARN_RATIO = 0.7;

const getSellingPriceVsOpWarning = () => {
  const op = originalContractPriceNum.value;
  const sp = sellingPriceNum.value;
  if (!(op > 0) || !(sp > 0)) return '';
  if (sp < op * SELLING_VS_OP_WARN_RATIO) return SELLING_SIGNIFICANTLY_BELOW_OP_MSG;
  return '';
};

const sellingPriceVsOpWarning = computed(() => getSellingPriceVsOpWarning());

/** When `VITE_LISTING_BLOCK_SUBMIT_SELLING_BELOW_OP` is true/1, publish is blocked if selling is below the warn ratio vs OP. */
const shouldBlockSubmitSellingBelowOp = () =>
  String(import.meta.env?.VITE_LISTING_BLOCK_SUBMIT_SELLING_BELOW_OP ?? '').toLowerCase() === 'true' ||
  import.meta.env?.VITE_LISTING_BLOCK_SUBMIT_SELLING_BELOW_OP === '1';

const initialPaymentTarget = computed(() => (originalPriceNum.value * initialPercentForm.value) / 100);
/** Under-construction tranche in AED (same as initialPaymentTarget). */
const ucTrancheAed = computed(() => initialPaymentTarget.value);
const handoverAmountForm = computed(() => Math.max(0, originalPriceNum.value - initialPaymentTarget.value));
const premiumAmountForm = computed(() => sellingPriceNum.value - originalPriceNum.value);
const premiumIsNegative = computed(() => premiumAmountForm.value < -0.01);
const premiumDisplayAed = computed(() => {
  const v = premiumAmountForm.value;
  if (Math.abs(v) < 0.5) return formatAed(0);
  if (v < 0) return `-${formatAed(Math.abs(v))}`;
  return formatAed(v);
});
const sellingBelowOriginalActive = computed(() => {
  const op = originalContractPriceNum.value;
  const sp = sellingPriceNum.value;
  return op > 0 && sp > 0 && sp + 0.01 < op;
});

/** Local calendar start of day (compare installment `type="date"` strings reliably). */
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

/** Past dates are intentional — they mean the installment was already paid.
 *  The only error still raised here is a missing date on the draft row. */
const getBreakdownInstallmentDateError = () => {
  if (!isUnderConstruction.value) return '';
  const draftDate = installmentDraft.value?.date;
  if (!draftDate) return '';
  const d = startOfDay(draftDate);
  if (Number.isNaN(d.getTime())) return 'Please enter a valid installment date.';
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

const HANDOVER_AFTER_INSTALLMENTS_MSG = 'Handover date must be after all installments.';
const HANDOVER_IN_PAST_MSG = 'Handover date cannot be in the past.';

const getHandoverDateError = () => {
  if (!isUnderConstruction.value) return '';
  const raw = form.value.handover_date;
  if (!raw) return '';
  const handoverDay = startOfDay(raw);
  if (Number.isNaN(handoverDay.getTime())) return '';

  const today = startOfDay(new Date());
  if (handoverDay.getTime() < today.getTime()) return HANDOVER_IN_PAST_MSG;

  let maxInstTs = null;
  for (const entry of breakdownInstallments.value) {
    if (!entry?.date) continue;
    const d = startOfDay(entry.date);
    if (Number.isNaN(d.getTime())) continue;
    const t = d.getTime();
    if (maxInstTs === null || t > maxInstTs) maxInstTs = t;
  }
  if (maxInstTs !== null && handoverDay.getTime() <= maxInstTs) return HANDOVER_AFTER_INSTALLMENTS_MSG;
  return '';
};

const paymentHandoverDateError = computed(() => getHandoverDateError());

const installmentToAmount = (entry) => {
  if (entry.type === 'percentage') return (originalPriceNum.value * Number(entry.value || 0)) / 100;
  return Number(entry.value || 0);
};

const formatAed = (value) =>
  new Intl.NumberFormat('en-AE', { style: 'currency', currency: 'AED', maximumFractionDigits: 0 }).format(Number(value || 0));

const formatDateShort = (dateLike) => {
  const date = new Date(dateLike);
  if (Number.isNaN(date.getTime())) return '—';
  return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

/** Sum of installments with due date on or before today only. */
const paidAmountForm = computed(() =>
  breakdownInstallments.value.reduce((sum, entry) => {
    if (!isDatePaid(entry?.date)) return sum;
    return sum + installmentToAmount(entry);
  }, 0),
);

/** All scheduled installments (paid + upcoming) — for selling-price breakdown total. */
const allInstallmentsAed = computed(() =>
  breakdownInstallments.value.reduce((sum, entry) => sum + installmentToAmount(entry), 0),
);

/** % of OP covered by paid installments only. */
const paidPercentOfOp = computed(() =>
  (paidAmountForm.value / Math.max(1, originalPriceNum.value)) * 100,
);

/** NOC % applies to original price (OP) only — not the payment-plan split (0,10,…,50). */
const nocPercentOfOp = computed(() => {
  // ✅ استخدام القيمة الثابتة بدلاً من النسبة المئوية
  const fixedAmount = Number(form.value.noc_fixed_amount || 0);
  const op = originalPriceNum.value;
  if (op <= 0 || fixedAmount <= 0) return 0;
  return (fixedAmount / op) * 100;
});


const nocRequiredAed = computed(() => {
  // ✅ استخدام القيمة الثابتة مباشرة
  return Number(form.value.noc_fixed_amount || 0);
});
const nocFixedAmount = computed(() => {
  return Number(form.value.noc_fixed_amount || 0);
});
const nocRequirementMet = computed(() => {
  const required = nocRequiredAed.value;
  if (required <= 0) return true;
  // ✅ مقارنة مجموع الأقساط مع NOC المطلوب
  return allInstallmentsAed.value >= required - 0.01;
});


const NOC_PAID_BELOW_REQUIRED_MSG = 'Paid installments (past due dates) do not meet the required NOC amount.';

const nocRequirementWarningActive = computed(
  () => isUnderConstruction.value && nocRequiredAed.value > 0 && !nocRequirementMet.value,
);



const scheduledInstallmentsAed = computed(() => allInstallmentsAed.value);
const nocRemainingAed = computed(() => {
  const required = nocRequiredAed.value;
  const paid = allInstallmentsAed.value;
  return Math.max(0, required - paid);
});
const nocRemainingPctOfOp = computed(() => {
  const op = originalPriceNum.value;
  if (op <= 0) return 0;
  return (nocRemainingAed.value / op) * 100;
});

const nocProgressBarPct = computed(() => {
  const required = nocRequiredAed.value;
  if (required <= 0) return 100;
  return Math.min(100, (allInstallmentsAed.value / required) * 100);
});

const nocProgressPaidLabel = computed(() => {
  const required = nocRequiredAed.value;
  if (required <= 0) return '—';
  return `${formatAed(allInstallmentsAed.value)} / ${formatAed(required)}`;
});


const BREAKDOWN_SELLING_TOLERANCE_AED = 1;
const MIXED_INSTALLMENT_TYPES_MSG = 'Cannot mix percentage and amount installment types.';
const SELLING_BELOW_OP_BLOCK_MSG = 'Selling price cannot be lower than original price.';

const breakdownGrandTotal = computed(
  () => allInstallmentsAed.value + handoverAmountForm.value + premiumAmountForm.value,
);

const breakdownMatchesSelling = computed(() => {
  if (!isUnderConstruction.value) return true;
  const sp = sellingPriceNum.value;
  if (!(sp > 0)) return false;
  return Math.abs(breakdownGrandTotal.value - sp) < BREAKDOWN_SELLING_TOLERANCE_AED;
});

const breakdownSellingDelta = computed(() => breakdownGrandTotal.value - sellingPriceNum.value);

const breakdownSellingDeltaMessage = computed(() => {
  if (!isUnderConstruction.value) return '';
  if (!(sellingPriceNum.value > 0)) return '';
  const d = breakdownSellingDelta.value;
  if (Math.abs(d) < BREAKDOWN_SELLING_TOLERANCE_AED) return '';
  if (d < -BREAKDOWN_SELLING_TOLERANCE_AED) {
    const rem = Math.round(Math.abs(d));
    return `AED ${rem.toLocaleString('en-AE')} remaining unallocated`;
  }
  const ex = Math.round(d);
  return `Breakdown exceeds selling price by AED ${ex.toLocaleString('en-AE')}`;
});

const breakdownSellingPriceMismatchActive = computed(
  () => isUnderConstruction.value && sellingPriceNum.value > 0 && !breakdownMatchesSelling.value,
);

const hasMixedInstallmentTypes = computed(() => {
  if (!breakdownInstallments.value.length) return false;
  const first = breakdownInstallments.value[0]?.type;
  return breakdownInstallments.value.some((e) => e?.type !== first);
});

const mixedInstallmentTypesError = computed(() => (hasMixedInstallmentTypes.value ? MIXED_INSTALLMENT_TYPES_MSG : ''));

const usesOnlyPercentageInstallments = computed(() => {
  if (!breakdownInstallments.value.length) return false;
  return breakdownInstallments.value.every((e) => e?.type === 'percentage');
});

const usesOnlyAmountInstallments = computed(() => {
  if (!breakdownInstallments.value.length) return false;
  return breakdownInstallments.value.every((e) => e?.type === 'amount');
});

const totalInstallmentPercent = computed(() =>
  breakdownInstallments.value.reduce((sum, entry) => {
    if (entry?.type !== 'percentage') return sum;
    return sum + Number(entry.value || 0);
  }, 0),
);

const installmentPercentMatchesPlan = computed(() => {
  if (!usesOnlyPercentageInstallments.value) return true;
  if (!Number.isFinite(initialPercentForm.value)) return true;
  return Math.abs(totalInstallmentPercent.value - initialPercentForm.value) < 0.01;
});

const percentageInstallmentPlanMismatchError = computed(() => {
  if (!isUnderConstruction.value || !usesOnlyPercentageInstallments.value) return '';
  if (!breakdownInstallments.value.length) return '';
  if (!installmentPercentMatchesPlan.value) {
    return `Installment percentages must total ${initialPercentForm.value.toFixed(0)}% to match the payment plan (currently ${totalInstallmentPercent.value.toFixed(2)}%).`;
  }
  return '';
});

const sellingBelowOriginalPriceError = computed(() => {
  const op = originalContractPriceNum.value;
  const sp = sellingPriceNum.value;
  if (!(op > 0) || !(sp > 0)) return '';
  if (sp + 0.01 < op) return SELLING_BELOW_OP_BLOCK_MSG;
  return '';
});

const handoverAmountNegativeBlock = computed(
  () => isUnderConstruction.value && (!Number.isFinite(handoverAmountForm.value) || handoverAmountForm.value < 0),
);

const publishPaymentBreakdownBlocked = computed(() => {
  if (!isUnderConstruction.value) return false;
  if (paymentPlanMissingForPublish.value) return true;
  if (form.value.payment_plans != null && form.value.payment_plans !== '' && !isPaymentPlanSelectionParseValid.value) return true;
  if (hasMixedInstallmentTypes.value) return true;
  if (percentageInstallmentPlanMismatchError.value) return true;
  if (breakdownSellingPriceMismatchActive.value) return true;
  if (nocRequirementWarningActive.value) return true;
  if (handoverAmountNegativeBlock.value) return true;
  if (getHandoverDateError()) return true;
  if (getBreakdownPercentageCapError()) return true;
  return false;
});

const publishPaymentBreakdownBlockTitle = computed(() => {
  if (!publishPaymentBreakdownBlocked.value) return '';
  if (paymentPlanMissingForPublish.value) return 'Select a payment plan.';
  if (form.value.payment_plans != null && form.value.payment_plans !== '' && !isPaymentPlanSelectionParseValid.value) {
    return PAYMENT_PLAN_PARSE_ERROR;
  }
  if (hasMixedInstallmentTypes.value) return MIXED_INSTALLMENT_TYPES_MSG;
  if (percentageInstallmentPlanMismatchError.value) return percentageInstallmentPlanMismatchError.value;
  if (breakdownSellingPriceMismatchActive.value) return 'Payment breakdown total does not match selling price.';
  if (nocRequirementWarningActive.value) return NOC_PAID_BELOW_REQUIRED_MSG;
  if (handoverAmountNegativeBlock.value) return 'Handover amount is invalid.';
  if (getHandoverDateError()) return getHandoverDateError();
  if (getBreakdownPercentageCapError()) return getBreakdownPercentageCapError();
  return 'Fix payment breakdown before publishing.';
});

const paymentBreakdownValidationSummary = computed(() => {
  if (!isUnderConstruction.value) return [];

  const rows = [];

  if (paymentPlanMissingForPublish.value) {
    rows.push({ id: 'plan', level: 'err', icon: '✕', text: 'Select a payment plan.' });
  } else if (!isPaymentPlanSelectionParseValid.value) {
    rows.push({ id: 'plan', level: 'err', icon: '✕', text: PAYMENT_PLAN_PARSE_ERROR });
  } else {
    rows.push({ id: 'plan', level: 'ok', icon: '✓', text: 'Payment plan is valid.' });
  }

  if (hasMixedInstallmentTypes.value) {
    rows.push({ id: 'mix', level: 'err', icon: '✕', text: MIXED_INSTALLMENT_TYPES_MSG });
  } else {
    rows.push({
      id: 'mix',
      level: 'ok',
      icon: '✓',
      text: 'Installment rows use a single type (all percentage or all amount).',
    });
  }

  if (hasMixedInstallmentTypes.value) {
    rows.push({ id: 'pctplan', level: 'warn', icon: '⚠', text: 'Percentage vs plan: fix mixed types first.' });
  } else if (usesOnlyPercentageInstallments.value && breakdownInstallments.value.length) {
    if (installmentPercentMatchesPlan.value) {
      rows.push({
        id: 'pctplan',
        level: 'ok',
        icon: '✓',
        text: `Installment percentages match payment plan (${initialPercentForm.value.toFixed(0)}%).`,
      });
    } else {
      rows.push({
        id: 'pctplan',
        level: 'err',
        icon: '✕',
        text: percentageInstallmentPlanMismatchError.value || 'Installment percentages do not match the plan.',
      });
    }
  } else if (usesOnlyAmountInstallments.value && breakdownInstallments.value.length) {
    rows.push({
      id: 'pctplan',
      level: 'ok',
      icon: '✓',
      text: 'Amount mode: percentage sum vs plan is not applied.',
    });
  } else {
    rows.push({
      id: 'pctplan',
      level: 'warn',
      icon: '⚠',
      text: 'Add installments (percentage rows must sum to the plan’s under-construction %).',
    });
  }

  if (!(sellingPriceNum.value > 0)) {
    rows.push({
      id: 'grand',
      level: 'warn',
      icon: '⚠',
      text: 'Enter selling price to validate installments + handover + premium total.',
    });
  } else if (breakdownMatchesSelling.value) {
    rows.push({ id: 'grand', level: 'ok', icon: '✓', text: 'Breakdown total matches selling price.' });
  } else {
    rows.push({
      id: 'grand',
      level: 'err',
      icon: '✕',
      text: `Payment breakdown total does not match selling price.${breakdownSellingDeltaMessage.value ? ` ${breakdownSellingDeltaMessage.value}` : ''}`,
    });
  }

  if (nocPercentOfOp.value <= 0) {
    rows.push({ id: 'noc', level: 'ok', icon: '✓', text: 'NOC check off (0%).' });
  } else if (nocRequirementMet.value) {
    rows.push({ id: 'noc', level: 'ok', icon: '✓', text: 'NOC requirement met.' });
  } else {
    rows.push({ id: 'noc', level: 'err', icon: '✕', text: NOC_PAID_BELOW_REQUIRED_MSG });
  }
  if (nocRequiredAed.value <= 0) {
    rows.push({ id: 'noc', level: 'ok', icon: '✓', text: 'No NOC fees required.' });
  } else if (nocRequirementMet.value) {
    rows.push({ id: 'noc', level: 'ok', icon: '✓', text: `NOC requirement met (${formatAed(nocRequiredAed.value)}).` });
  } else {
    rows.push({ id: 'noc', level: 'err', icon: '✕', text: `NOC fees of ${formatAed(nocRequiredAed.value)} not fully covered by installments.` });
  }
  const he = paymentHandoverDateError.value;
  if (!form.value.handover_date) {
    rows.push({
      id: 'ho',
      level: 'warn',
      icon: '⚠',
      text: 'Set handover date (required for a complete handover schedule).',
    });
  } else if (he) {
    rows.push({ id: 'ho', level: 'err', icon: '✕', text: he });
  } else {
    rows.push({ id: 'ho', level: 'ok', icon: '✓', text: 'Handover date is valid.' });
  }

  if (!(originalContractPriceNum.value > 0)) {
    rows.push({
      id: 'svop',
      level: 'warn',
      icon: '⚠',
      text: 'Enter original price (OP) to enforce selling ≥ OP.',
    });
  } else if (sellingBelowOriginalActive.value) {
    rows.push({ id: 'svop', level: 'warn', icon: '⚠', text: 'Selling below original price' });
  } else {
    rows.push({ id: 'svop', level: 'ok', icon: '✓', text: 'Selling price is at or above original price.' });
  }

  if (premiumIsNegative.value) {
    rows.push({ id: 'prem', level: 'warn', icon: '⚠', text: 'Negative premium — selling below original price.' });
  }

  if (handoverAmountNegativeBlock.value) {
    rows.push({ id: 'hamt', level: 'err', icon: '✕', text: 'Handover amount is invalid or negative.' });
  } else {
    rows.push({ id: 'hamt', level: 'ok', icon: '✓', text: 'Handover amount (OP balance) is valid.' });
  }

  if (paymentBreakdownInstallmentDateError.value) {
    rows.push({ id: 'idate', level: 'err', icon: '✕', text: paymentBreakdownInstallmentDateError.value });
  }

  if (paymentBreakdownPercentageCapError.value) {
    rows.push({ id: 'pcap', level: 'err', icon: '✕', text: paymentBreakdownPercentageCapError.value });
  }

  if (paymentBreakdownDuplicateInstallmentDateWarning.value) {
    rows.push({
      id: 'dupd',
      level: 'warn',
      icon: '⚠',
      text: paymentBreakdownDuplicateInstallmentDateWarning.value,
    });
  }

  if (paymentPlanDurationWarning.value) {
    rows.push({ id: 'dur', level: 'warn', icon: '⚠', text: paymentPlanDurationWarning.value });
  }

  return rows;
});

const STATUS_PAID = 'Paid';
const STATUS_DUE_ON_TRANSFER = 'Due on transfer';
const STATUS_UPCOMING = 'Upcoming';

const resolveNocInstallmentStatus = (cumulativeAfter) => {
  if (nocPercentOfOp.value <= 0) return STATUS_UPCOMING;
  if (cumulativeAfter <= nocRequiredAed.value + 0.01) return STATUS_DUE_ON_TRANSFER;
  return STATUS_UPCOMING;
};

const paymentBreakdownRows = computed(() => {
  const rows = [];
  let id = 1;
  const sorted = breakdownInstallments.value.slice().sort((a, b) => new Date(a.date) - new Date(b.date));
  let cumulative = 0;

  sorted.forEach((entry) => {
    const amount = installmentToAmount(entry);
    cumulative += amount;
    let status;
    if (isDatePaid(entry?.date)) status = STATUS_PAID;
    else status = resolveNocInstallmentStatus(cumulative);

    rows.push({
      id: entry.id,
      entryId: entry.id,
      type: 'Installment',
      percentage: ((amount / Math.max(1, originalPriceNum.value)) * 100).toFixed(2),
      amount,
      date: entry.date,
      status,
    });
  });

  rows.push({
    id: `premium-${id++}`,
    entryId: null,
    type: 'Premium',
    percentage: '',
    amount: premiumAmountForm.value,
    date: '',
    status: premiumIsNegative.value ? 'Selling below original price' : STATUS_UPCOMING,
  });

  if (Math.abs(handoverAmountForm.value) > 0.01) {
    rows.push({
      id: `handover-${id++}`,
      entryId: null,
      type: `Handover (${installmentPercentForm.value.toFixed(0)}%)`,
      percentage: installmentPercentForm.value.toFixed(2),
      amount: handoverAmountForm.value,
      date: form.value.handover_date || '',
      status: isDatePaid(form.value.handover_date) ? STATUS_PAID : STATUS_UPCOMING,
    });
  }

  return rows;
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

const breakdownRowStatusClass = (status) => {
  if (status === STATUS_PAID) return 'bg-success-subtle text-success-emphasis';
  if (status === 'Selling below original price') return 'bg-danger-subtle text-danger-emphasis';
  if (status === STATUS_DUE_ON_TRANSFER || status === 'Due to transfer') {
    return 'bg-warning-subtle text-warning-emphasis';
  }
  return 'bg-primary-subtle text-primary-emphasis';
};

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
  if (breakdownInstallments.value.length > 0) {
    const firstType = breakdownInstallments.value[0].type;
    if (installmentDraft.value.type !== firstType) {
      proxy.$showNotification(MIXED_INSTALLMENT_TYPES_MSG, 'error');
      return;
    }
  }
  const draftDay = startOfDay(installmentDraft.value.date);
  if (Number.isNaN(draftDay.getTime())) {
    proxy.$showNotification('Please enter a valid installment date.', 'error');
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


const UAE_ASSIGNMENT_VAT_RATE = 0.05;

const assignmentExpenseTypeOptions = [
  { label: 'Percentage (%)', value: 'percentage' },
  { label: 'Fixed (AED)', value: 'fixed' },
];

const assignmentExpenseBaseOptions = [
  { label: 'Original Price (OP)', value: 'op' },
  { label: 'Sale Price (SP)', value: 'sp' },
  { label: 'Premium (SP − OP)', value: 'premium' },
];

const assignmentExpenseLines = ref([]);
const assignmentExpenseDraft = ref({
  label: '',
  calcType: 'percentage',
  base: 'op',
  value: null,
  vatEnabled: false,
});

const getAssignmentExpenseBaseAmount = (base) => {
  if (base === 'op') return originalPriceNum.value;
  if (base === 'sp') return sellingPriceNum.value;
  if (base === 'premium') return premiumAmountForm.value;
  return 0;
};

const assignmentExpenseLineAmount = (line) => {
  if (!line) return 0;
  if (line.calcType === 'percentage') {
    return (getAssignmentExpenseBaseAmount(line.base) * Number(line.value || 0)) / 100;
  }
  return Number(line.value || 0);
};

const assignmentExpenseLineVat = (line) =>
  line?.vatEnabled ? assignmentExpenseLineAmount(line) * UAE_ASSIGNMENT_VAT_RATE : 0;

const assignmentExpenseLineTotal = (line) =>
  assignmentExpenseLineAmount(line) + assignmentExpenseLineVat(line);

const assignmentExpensesSubtotal = computed(() =>
  assignmentExpenseLines.value.reduce((sum, line) => sum + assignmentExpenseLineAmount(line), 0),
);

const assignmentExpensesTotalVat = computed(() =>
  assignmentExpenseLines.value.reduce((sum, line) => sum + assignmentExpenseLineVat(line), 0),
);

const assignmentExpensesGrandTotal = computed(() =>
  assignmentExpenseLines.value.reduce((sum, line) => sum + assignmentExpenseLineTotal(line), 0),
);

/** Rows for Payment Details preview modal (assignment deal costs). */
const assignmentExpensePreviewRows = computed(() =>
  assignmentExpenseLines.value.map((line) => {
    const baseLabel =
      line.calcType === 'percentage'
        ? (assignmentExpenseBaseOptions.find((b) => b.value === line.base)?.label ?? '—')
        : '—';
    const valueDisplay =
      line.calcType === 'percentage'
        ? `${Number(line.value || 0)}%`
        : formatAed(Number(line.value || 0));
    return {
      id: line.id,
      label: line.label || '—',
      baseLabel,
      typeLabel: line.calcType === 'percentage' ? 'Percentage' : 'Fixed',
      valueDisplay,
      amount: assignmentExpenseLineAmount(line),
      vat: assignmentExpenseLineVat(line),
      total: assignmentExpenseLineTotal(line),
      hasVat: !!line.vatEnabled,
    };
  }),
);

const resetAssignmentExpenseDraft = () => {
  assignmentExpenseDraft.value = {
    label: '',
    calcType: 'percentage',
    base: 'op',
    value: null,
    vatEnabled: false,
  };
};

const addAssignmentExpenseLine = () => {
  const label = String(assignmentExpenseDraft.value.label || '').trim();
  if (!label) {
    proxy.$showNotification('Enter a label for the cost line', 'error');
    return;
  }
  const value = Number(assignmentExpenseDraft.value.value);
  if (!Number.isFinite(value) || value <= 0) {
    proxy.$showNotification('Enter a valid value greater than zero', 'error');
    return;
  }
  assignmentExpenseLines.value.push({
    id: Date.now() + Math.floor(Math.random() * 1000),
    label,
    calcType: assignmentExpenseDraft.value.calcType,
    base: assignmentExpenseDraft.value.base || 'op',
    value,
    vatEnabled: !!assignmentExpenseDraft.value.vatEnabled,
  });
  resetAssignmentExpenseDraft();
};

const removeAssignmentExpenseLine = (id) => {
  assignmentExpenseLines.value = assignmentExpenseLines.value.filter((line) => line.id !== id);
};

const removeBreakdownInstallment = (entryId) => {
  breakdownInstallments.value = breakdownInstallments.value.filter((entry) => entry.id !== entryId);
};

// Shared source of truth — see resources/js/config/listingFeatures.js and
// ListingResource::FEATURE_LABELS for the matching backend list.
const listingFeatureOptions = LISTING_FEATURE_OPTIONS;

const isLoadingUnitNumber = ref(false);
const unitNumberError = ref("");
watch(
  () => form.value.area,
  (newArea, oldArea) => {
    if (!newArea && !oldArea) return;
    
    console.log('🔄 Area changed in create form from:', oldArea?.name, 'to:', newArea?.name);
    console.log('📍 New area data:', newArea);
    
    addDefaultDealCosts();
  },
  { deep: true }
);
watch(() => [form.value.unit_number, selectedProject.value, form.value.saleOrRent, form.value.area], 
  ([newUnitNumber, newProject, newStatus, newArea], [oldUnitNumber, oldProject, oldStatus, oldArea]) => {
  if ((newProject !== oldProject || newStatus !== oldStatus || newArea !== oldArea) && newUnitNumber) {
    setTimeout(() => {
      validateUnitNumber();
    }, 500);
  }
}, { deep: true });
watch(() => form.value.completionStatus, (newStatus) => {
  console.log('🔄 Completion status changed:', newStatus);
  const s = String(newStatus ?? '').trim().toLowerCase().replace(/_/g, ' ');
  const isComp = s === 'completed';
  const isUC = s === 'under construction' || s === 'off plan';

  if (isComp) {
    form.value.payment_plans = null;
    form.value.payment_plan = null;
    breakdownInstallments.value = [];
    // assignmentExpenseLines.value = [];
    form.value.handover_date = '';
    form.value.noc_percentage = 0;
    form.value.original_price = '';
        updateNocBasedOnStatus();

  }
  if (isUC) {
    form.value.occupancyStatus = '';
    form.value.rentExpiryDate = '';
    form.value.rentAmount = '';
    updateNocBasedOnStatus();
 if (assignmentExpenseLines.value.length === 0) {
      addDefaultDealCosts();
    }
    console.log('🏗️ Property under construction - occupancy status cleared');
  }
});
watch(() => form.value.area, (newArea, oldArea) => {
  if (newArea && newArea.id !== oldArea?.id && form.value.unit_number) {
    setTimeout(() => {
      validateUnitNumber();
    }, 300);
  }
});

watch(() => form.value.payment_plans, (newValue) => {
  const label = paymentPlanSelectionLabel(newValue);
  form.value.payment_plan = label ? JSON.stringify([label]) : null;
}, { deep: true });

watch(() => form.value.saleOrRent, (newValue) => {
  if (newValue === 'Sale') {
    form.value.rented_status = "";
    form.value.rented_until = "";
  }
});

watch(() => newOwner.value.residency_status, async (newStatus, oldStatus) => {
  if (newOwner.value.nationality === 'UAE') return;
  if (newStatus !== oldStatus) {
    newOwner.value.location_id = "";
    if (newStatus) await fetchLocations(newStatus);
    else locations.value = [];
  }
});

watch(() => selectedProject.value, async (newProject, oldProject) => {
  if (newProject) {
    try {
      // تحميل مناطق المشروع
      const response = await api.get(`/listings/projects/${newProject.id}/areas`);
      const projectAreasData = response.data.data || response.data;
      const filteredAreas = projectAreasData.filter(
        area => area.children_count == 0
      );
      form.value.projectAreas = filteredAreas.map(area => ({
        id: area.id,
        name: area.area_parents_title || area.name || area.title,
        project_id: newProject.id
      }));
      form.value.area = null;
      
      // ✅ حفظ قيم NOC من المطور
       if (newProject.developer) {
        const readyValue = Number(newProject.developer.noc_fees_ready || 0);
        form.value.noc_fees_ready = !isNaN(readyValue) && readyValue >= 0 ? readyValue : 0;
        
        const offPlanValue = Number(newProject.developer.noc_fees_off_plan || 0);
        form.value.noc_fees_off_plan = !isNaN(offPlanValue) && offPlanValue >= 0 ? offPlanValue : 0;
        
        console.log(`✅ Developer NOC values loaded:
          - Ready: ${formatAed(form.value.noc_fees_ready)}
          - Off-Plan: ${formatAed(form.value.noc_fees_off_plan)}`);
        
        // ✅ تأكد من استدعاء هذه الدالة
        updateNocBasedOnStatus();
      }
      
      console.log(`✅ Loaded ${form.value.projectAreas.length} areas for project:`, newProject.name);
    } catch (error) {
      console.error('❌ Error fetching project areas:', error);
      form.value.projectAreas = [];
      proxy.$showNotification("⚠️ Could not load project areas. Using general areas.", "warning");
    }
  } else {
    // إعادة تعيين عند إلغاء اختيار المشروع
    form.value.projectAreas = [];
    form.value.area = null;
    form.value.noc_fixed_amount = 0;
    form.value.noc_fees_ready = 0;
    form.value.noc_fees_off_plan = 0;
  }
});
// watch(() => selectedProject.value, async (newProject) => {
//   if (newProject) {
//     await fetchProjectFloorPlans(newProject.id);
//   } else {
//     projectFloorPlans.value = [];
//     selectedProjectFloorPlans.value = [];
//   }
// }, { immediate: true });


const fetchProjectAreasForFilter = async (projectId) => {
  try {
    const response = await api.get(`/listings/projects/${projectId}/areas`);
    projectAreasForFilter.value = response.data.data || response.data;
    console.log('✅ Project areas for filter:', projectAreasForFilter.value);
  } catch (error) {
    console.error('❌ Error fetching project areas for filter:', error);
    projectAreasForFilter.value = [];
  }
};

const fetchProjectFloorPlans = async (projectId) => {
  try {
    isLoadingProjectFloorPlans.value = true;
    
    await fetchProjectAreasForFilter(projectId);
    
    const response = await api.get(`/listings/projects/${projectId}/floor-plans`);
    const allFloorPlans = response.data.data || response.data;
    
    // Log area information for debugging
    console.log(`📊 Loaded ${allFloorPlans.length} floor plans for project ${projectId}`);
    console.log('Floor plan area assignments:', allFloorPlans.map(plan => ({
      name: plan.name,
      area_id: plan.area_id,
      area_name: plan.area_name || 'Not specified'
    })));
    
    projectFloorPlans.value = allFloorPlans.map(plan => ({
      ...plan,
      area_id: plan.area_id || null 
    }));
    
    // Apply current area filter if an area is selected
    if (form.value.area && form.value.area.id) {
      filterFloorPlansByArea(form.value.area.id);
    } else {
      filteredFloorPlans.value = projectFloorPlans.value;
    }
    
    console.log('✅ Project floor plans loaded:', projectFloorPlans.value.length);
    console.log('📊 Filtered floor plans:', filteredFloorPlans.value.length);
  } catch (error) {
    console.error('❌ Error fetching project floor plans:', error);
    projectFloorPlans.value = [];
    filteredFloorPlans.value = [];
    proxy.$showNotification("⚠️ Could not load project floor plans", "warning");
  } finally {
    isLoadingProjectFloorPlans.value = false;
  }
};const filterFloorPlansByArea = (areaId) => {
  if (!areaId) {
    filteredFloorPlans.value = projectFloorPlans.value;
    console.log('Showing all floor plans (no area filter)');
  } else {
    filteredFloorPlans.value = projectFloorPlans.value.filter(plan => {
      // For project 1788,1833, include plans without area_id for al reef downtown and al reef villas
      if (selectedProject.value?.id === 1788 || selectedProject.value?.id === 1833) {
        return true;
      }
      return plan.area_id === areaId;
    });
    console.log(`Filtered floor plans for area ${areaId}:`, filteredFloorPlans.value.length);
  }
};






const handleProjectFloorPlanSelection = (selectedPlans) => {
  selectedPlans.forEach(plan => {
    const alreadyExists = form.value.floorPlans.some(fp => 
      fp.id === plan.id || fp.name === plan.name
    );
    
    if (!alreadyExists) {
      form.value.floorPlans.push({
        id: plan.id,
        name: plan.name,
        image_url: plan.image_url,
        order: plan.order,
        customName: plan.name,
        fromProject: true,
        projectFloorPlanId: plan.id
      });
    }
  });
  
  const removedPlans = selectedProjectFloorPlans.value.filter(sp => 
    !selectedPlans.some(p => p.id === sp.id)
  );
  
  removedPlans.forEach(removedPlan => {
    const index = form.value.floorPlans.findIndex(fp => 
      fp.projectFloorPlanId === removedPlan.id
    );
    if (index !== -1) {
      form.value.floorPlans.splice(index, 1);
    }
  });
  
  selectedProjectFloorPlans.value = selectedPlans;
};

// const removeFloorPlan = (index) => {
//   const removedPlan = form.value.floorPlans[index];
  
//   if (removedPlan.fromProject) {
//     const selectedIndex = selectedProjectFloorPlans.value.findIndex(
//       plan => plan.id === removedPlan.projectFloorPlanId
//     );
//     if (selectedIndex !== -1) {
//       selectedProjectFloorPlans.value.splice(selectedIndex, 1);
//     }
//   }
  
//   if (form.value.floorPlans[index] && form.value.floorPlans[index].preview) {
//     URL.revokeObjectURL(form.value.floorPlans[index].preview);
//   }
//   form.value.floorPlans.splice(index, 1);
//   proxy.$showNotification("🗑️ Floor plan removed", "info");
// };
// Variables for single project floor plan selection
const selectedProjectFloorPlan = ref(null);

// Computed property
// Add this to your computed properties section
const isPlotOrLand = computed(() => {
  const plotTypes = ['Plot', 'Land', 'Residential Plot', 'Commercial Plot'];
  if (!form.value.property_type) return false;
  
  const propertyTypeName = form.value.property_type.name || form.value.property_type;
  return plotTypes.some(type => 
    propertyTypeName.toLowerCase().includes(type.toLowerCase())
  );
});

const customFloorPlansCount = computed(() => {
  return form.value.floorPlans.filter(fp => !fp.fromProject).length;
});

// Functions for handling single project floor plan selection
const isSelectedProjectFloorPlan = (floorPlan) => {
  return selectedProjectFloorPlan.value?.id === floorPlan.id;
};

const selectSingleProjectFloorPlan = (floorPlan) => {
      if (selectedProject.value?.id !== 1788 || selectedProject.value?.id !==1833) {

  if (form.value.area && form.value.area.id) {
    if (!floorPlan.area_id ) {
      proxy.$showNotification(`⚠️ This floor plan doesn't have an area assigned`, "warning");
      return;
    }
    
    if (floorPlan.area_id !== form.value.area.id) {
      proxy.$showNotification(`⚠️ This floor plan is not available in the selected area`, "warning");
      return;
    }
  }
      }
  
  // Remove any existing project plan from form.floorPlans
  form.value.floorPlans = form.value.floorPlans.filter(fp => !fp.fromProject);
  
  // Update selected project floor plan
  selectedProjectFloorPlan.value = floorPlan;
  
  // Add to form.floorPlans
  const newPlan = {
    id: floorPlan.id,
    name: floorPlan.name,
    image_url: floorPlan.image_url,
    order: floorPlan.order,
    dimensions: floorPlan.dimensions,
    area_id: floorPlan.area_id,
    customName: floorPlan.name,
    fromProject: true,
    projectFloorPlanId: floorPlan.id
  };
  
  form.value.floorPlans.unshift(newPlan);
  proxy.$showNotification(`✅ Selected project floor plan: "${floorPlan.name}"`, "success");
};

const clearSelectedProjectFloorPlan = () => {
  if (selectedProjectFloorPlan.value) {
    const planName = selectedProjectFloorPlan.value.name;
    
    // Remove from form.floorPlans
    form.value.floorPlans = form.value.floorPlans.filter(fp => !fp.fromProject);
    
    // Clear selection
    selectedProjectFloorPlan.value = null;
    
    proxy.$showNotification(`🗑️ Removed project floor plan: "${planName}"`, "info");
  }
};

// Update the existing removeFloorPlan function
const removeFloorPlan = (index) => {
  const removedPlan = form.value.floorPlans[index];
  
  if (removedPlan.fromProject) {
    // If removing a project plan, clear the selection
    clearSelectedProjectFloorPlan();
  } else {
    // Remove custom plan
    if (form.value.floorPlans[index] && form.value.floorPlans[index].preview) {
      URL.revokeObjectURL(form.value.floorPlans[index].preview);
    }
    form.value.floorPlans.splice(index, 1);
    proxy.$showNotification("🗑️ Custom floor plan removed", "info");
  }
};

// Update the existing handleFloorPlanUpload function
const handleFloorPlanUpload = (e) => {
  const files = Array.from(e.target.files);
  if (files.length > 0) {
    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/svg+xml', 'image/webp'];
    const validFiles = files.filter(file => {
      if (!validTypes.includes(file.type)) {
        proxy.$showNotification(`❌ File "${file.name}" is not a valid image type.`, "error");
        return false;
      }
      if (file.size > 10 * 1024 * 1024) {
        proxy.$showNotification(`❌ Floor plan "${file.name}" is too large. Max size is 10MB.`, "error");
        return false;
      }
      return true;
    });

    if (validFiles.length > 0) {
      const filesWithNames = validFiles.map(file => ({
        file: file, 
        name: file.name, 
        size: file.size, 
        type: file.type,
        customName: file.name.replace(/\.[^/.]+$/, ""), 
        preview: URL.createObjectURL(file),
        isNewUpload: true
      }));
      
      // Add custom plans to form.floorPlans
      form.value.floorPlans = [...form.value.floorPlans, ...filesWithNames];
      e.target.value = '';
      proxy.$showNotification(`✅ Added ${validFiles.length} custom floor plan(s)`, "success");
    }
  }
};

// Update watch for project change

watch(() => selectedProject.value, async (newProject) => {
  if (newProject) {
    await fetchProjectFloorPlans(newProject.id);
    
    selectedAreaForFilter.value = null; // Reset filter
    clearSelectedProjectFloorPlan();
     if (newProject?.developer) {
      updateNocBasedOnStatus();
    }
  } else {
    projectFloorPlans.value = [];
    filteredFloorPlans.value = [];
    projectAreasForFilter.value = [];
    clearSelectedProjectFloorPlan();
  }
}, { immediate: true });

// Replace the existing watch for form.value.area with this enhanced version
watch(() => form.value.area, (newArea) => {
  console.log('🔄 Area changed value in form:', newArea);
  console.log('📊 Current project ID:', selectedProject.value?.id);
  
  if (!selectedProject.value) {
    console.log('⚠️ No project selected, cannot filter floor plans');
    filteredFloorPlans.value = [];
    return;
  }
  
  // Special handling for project ID 1788,1833 or when no area is selected
  if (!newArea || !newArea.id) {
    // Show all floor plans when no area is selected
    filteredFloorPlans.value = projectFloorPlans.value;
    console.log('Showing all floor plans (no area selected):', filteredFloorPlans.value.length);
  } else {
    // Filter floor plans by area
    filteredFloorPlans.value = projectFloorPlans.value.filter(plan => {
      // If plan has no area_id, include it only if it's the only plan or has special flag
    
        // For project 1788, include plans without area_id
        if (selectedProject.value.id === 1788 || selectedProject.value.id ==1833) {
          console.log(`Including floor plan "${plan.name}" with no area assignment for project 1788`);
          return true;
        }
        
      // Normal area matching
      return plan.area_id === newArea.id;
    });
    
    console.log(`Filtered floor plans for area ${newArea.name} (ID: ${newArea.id}):`, filteredFloorPlans.value.length);
    
    // Check if current selected floor plan is still valid
    if (selectedProjectFloorPlan.value) {
      const isStillValid = filteredFloorPlans.value.some(
        plan => plan.id === selectedProjectFloorPlan.value.id
      );
      if (!isStillValid) {
        clearSelectedProjectFloorPlan();
        proxy.$showNotification(`⚠️ Selected floor plan is not available in "${newArea.name}" area`, "warning");
      }
    }
  }
}, { immediate: true, deep: true });// Functions for handling project floor plans selection
const isProjectFloorPlanSelected = (floorPlan) => {
  return selectedProjectFloorPlans.value.some(
    plan => plan.id === floorPlan.id
  );
};



watch(() => projectFloorPlans.value, (newPlans) => {
  console.log('🔄 Project floor plans updated:', newPlans.length);
  
  if (selectedAreaForFilter.value) {
    filteredFloorPlans.value = newPlans.filter(
      plan => plan.area_id === selectedAreaForFilter.value
    );
  } else {
    filteredFloorPlans.value = newPlans;
  }
}, { deep: true });

const toggleProjectFloorPlanSelection = (floorPlan) => {
  const index = selectedProjectFloorPlans.value.findIndex(
    plan => plan.id === floorPlan.id
  );
  
  if (index === -1) {
    // Add to selection
    selectedProjectFloorPlans.value.push(floorPlan);
    
    // Add to form.floorPlans if not already there
    const alreadyInForm = form.value.floorPlans.some(
      fp => fp.projectFloorPlanId === floorPlan.id
    );
    
    if (!alreadyInForm) {
      form.value.floorPlans.push({
        id: floorPlan.id,
        name: floorPlan.name,
        image_url: floorPlan.image_url,
        order: floorPlan.order,
        dimensions: floorPlan.dimensions,
        rooms: floorPlan.rooms,
        customName: floorPlan.name,
        fromProject: true,
        projectFloorPlanId: floorPlan.id
      });
    }
    
    proxy.$showNotification(`✅ Added "${floorPlan.name}" to floor plans`, "success");
  } else {
    // Remove from selection
    selectedProjectFloorPlans.value.splice(index, 1);
    
    // Remove from form.floorPlans
    const formIndex = form.value.floorPlans.findIndex(
      fp => fp.projectFloorPlanId === floorPlan.id
    );
    if (formIndex !== -1) {
      form.value.floorPlans.splice(formIndex, 1);
    }
    
    proxy.$showNotification(`🗑️ Removed "${floorPlan.name}" from floor plans`, "info");
  }
};

const clearAllProjectFloorPlans = () => {
  // Remove from form.floorPlans
  form.value.floorPlans = form.value.floorPlans.filter(
    fp => !fp.fromProject
  );
  
  // Clear selection
  selectedProjectFloorPlans.value = [];
  
  proxy.$showNotification("🗑️ All project floor plans cleared", "info");
};

const previewFloorPlanImage = (floorPlan) => {
  if (floorPlan.image_url) {
    window.open(floorPlan.image_url, '_blank');
  }
};

const handleFloorPlanImageError = (event, floorPlan) => {
  console.error('❌ Floor plan image failed to load:', floorPlan.name);
  event.target.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjBmMGYwIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCxzYW5zLXNlcmlmIiBmb250LXNpemU9IjE0IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjNjY2Ij5GTE9PUiBQTEFOPC90ZXh0Pjx0ZXh0IHg9IjUwJSIgeT0iNjUlIiBmb250LWZhbWlseT0iQXJpYWwsc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxMiIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0iIzg4OCI+SW1hZ2Ugbm90IGF2YWlsYWJsZTwvdGV4dD48L3N2Zz4=';
};
// 6. computed properties
const agentId = computed(() => {
  try {
    const userData = localStorage.getItem('user');
    return userData ? JSON.parse(userData).id : null;
  } catch (error) {
    console.error('❌ Error parsing user data:', error);
    return null;
  }
});

const number_of_bedrooms = computed({
  get: () => form.value.number_of_bedrooms,
  set: (val) => {
    if (!val) form.value.number_of_bedrooms = '';
    else if (typeof val === 'object' && 'value' in val) form.value.number_of_bedrooms = parseInt(val.value) || '';
    else form.value.number_of_bedrooms = parseInt(val) || '';
  }
});

const number_of_bathrooms = computed({
  get: () => form.value.number_of_bathrooms,
  set: (val) => {
    if (!val) form.value.number_of_bathrooms = '';
    else if (typeof val === 'object' && 'value' in val) form.value.number_of_bathrooms = parseInt(val.value) || '';
    else form.value.number_of_bathrooms = parseInt(val) || '';
  }
});

const filteredAreas = computed(() => {
  if (selectedProject.value && form.value.projectAreas.length > 0) {
    return form.value.projectAreas;
  }
  return areas.value.filter(area => area.children_count == 0);
});

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
    console.error("❌ Error fetching projects:", error);
    proxy.$showNotification("❌ Failed to load projects.", "error");
  } finally {
    isLoadingProjects.value = false;
  }
};

const fetchLayoutTypes = async () => {
  try {
    isLoadingLayoutTypes.value = true;
    const response = await api.get("/listings/layout_types");
    const layoutTypesData = response.data.data || response.data;
    layoutTypes.value = layoutTypesData.map(layout => ({
      id: layout.id,
      name: layout.name || layout.layout_name || layout.title
    }));
    console.log('✅ Layout types loaded:', layoutTypes.value);
  } catch (error) {
    console.error("❌ Error fetching layout types:", error.response || error);
    proxy.$showNotification("❌ Failed to load layout types.", "error");
  } finally {
    isLoadingLayoutTypes.value = false;
  }
};

const fetchUnitViews = async () => {
  try {
    isLoadingUnitViews.value = true;
    const response = await api.get("/listings/unit_views");
    const unitViewsData = response.data.data || response.data;
    unitViews.value = unitViewsData.map(view => ({
      id: view.id,
      name: view.name || view.view_name || view.title
    }));
    console.log('✅ Unit views loaded:', unitViews.value);
  } catch (error) {
    console.error("❌ Error fetching unit views:", error.response || error);
    proxy.$showNotification("❌ Failed to load unit views.", "error");
  } finally {
    isLoadingUnitViews.value = false;
  }
};

const filterNameInput = (field) => {
  if (!newOwner.value[field]) return;
  newOwner.value[field] = newOwner.value[field].replace(/[^a-zA-Z\u0600-\u06FF\s]/g, '');
};

const filterNumberInput = (field) => {
  if (!newOwner.value[field]) return;
  newOwner.value[field] = newOwner.value[field].replace(/[^0-9]/g, '');
};

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
      phone_number: owner.phone_number
    }));
  } catch (error) {
    console.error("❌ Error fetching owners:", error);
    proxy.$showNotification("❌ Failed to load owners.", "error");
  } finally {
    isLoadingOwners.value = false;
  }
};

const customOwnerFilter = (option, label, search) => {
  if (!search || search.trim() === '') return true;
  const searchTerm = search.toLowerCase().trim();
  const cleanPhoneNumber = (phone) => phone ? phone.replace(/[\s+()-]/g, '').toLowerCase() : '';
  
  if (option.email && option.email.toLowerCase().includes(searchTerm)) return true;
  if (option.phone_number && cleanPhoneNumber(option.phone_number).includes(cleanPhoneNumber(searchTerm))) return true;
  if (option.whatsapp_number && cleanPhoneNumber(option.whatsapp_number).includes(cleanPhoneNumber(searchTerm))) return true;
  if (option.second_phone_number && cleanPhoneNumber(option.second_phone_number).includes(cleanPhoneNumber(searchTerm))) return true;
  return false;
};

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
    console.error("❌ Error fetching property types:", error);
    proxy.$showNotification("❌ Failed to load property types.", "error");
  } finally {
    isLoadingPropertyTypes.value = false;
  }
};

const fetchDevelopers = async () => {
  try {
    isLoadingDevelopers.value = true;
    const response = await api.get("/listings/developers");
    const developersData = response.data.data || response.data;
    developers.value = developersData.map(developer => ({
      id: developer.id,
      name: developer.name || developer.developer_name || developer.title
    }));
  } catch (error) {
    console.error("❌ Error fetching developers:", error);
    proxy.$showNotification("❌ Failed to load developers.", "error");
  } finally {
    isLoadingDevelopers.value = false;
  }
};

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
    console.error("❌ Error fetching areas:", error);
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
  if (newOwner.value.nationality === 'UAE') return 'City';
  else if (newOwner.value.residency_status === 'resident') return 'Emirate';
  else if (newOwner.value.residency_status === 'non_resident') return 'Country';
  return 'Emirate or Country';
};

const getLocationPlaceholder = () => {
  if (newOwner.value.nationality === 'UAE') return 'Select City';
  else if (newOwner.value.residency_status === 'resident') return 'Select Emirate';
  else if (newOwner.value.residency_status === 'non_resident') return 'Select Country';
  return 'Select location';
};

const fetchLocations = async (residencyStatus) => {
  try {
    isLoadingLocations.value = true;
    locations.value = [];
    const response = await api.get(
      `/listings/owners/locations/available?residency_status=${residencyStatus}`
    );
    locations.value = response.data.data || response.data;
  } catch (error) {
    locations.value = [];
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
    const formData = new FormData();
    for (const key in newOwner.value) {
      const value = newOwner.value[key];
      if (key === 'additional_documents' && Array.isArray(value)) {
        value.forEach(file => {
          if (file instanceof File) {
            formData.append('additional_documents[]', file);
          }
        });
      } else if (value instanceof File) formData.append(key, value);
      else if (value !== null && value !== "") formData.append(key, value);
    }

    const response = await api.post("/listings/owners", formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });

    const createdOwner = response.data?.data || response.data;
    await fetchOwners();
    
    const newOwnerInList = owners.value.find(owner => owner.id === createdOwner.id);
    if (newOwnerInList) selectedOwner.value = newOwnerInList;

    newOwner.value = {
      salutation: "", first_name: "", last_name: "", email: "",
      phone_number: "", whatsapp_number: "", second_phone_number: "",
      nationality: "", residency_status: "", location_id: "",
      id_front: null, id_back: null, visa_copy: null, passport_copy: null, notes: "",
      additional_documents: [],
    };
    locations.value = [];
    showAddOwner.value = false;
    proxy.$showNotification("✅ Owner added successfully!", "success");
  } catch (error) {
    console.error("❌ Error adding owner:", error);
    if (error.response?.data?.errors) {
      const errorMessages = Object.values(error.response.data.errors).flat().join(', ');
      proxy.$showNotification(`❌ Validation Error: ${errorMessages}`, "error");
    } else {
      proxy.$showNotification("❌ Failed to add owner.", "error");
    }
  }
};

const handleNewOwnerFile = (e, field) => {
  const file = e.target.files[0];
  if (file) newOwner.value[field] = file;
};
const handleNewOwnerAdditionalDocuments = (e) => {
  const files = Array.from(e.target.files || []);
  if (!files.length) return;

  const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
  const validFiles = files.filter(file => {
    if (!validTypes.includes(file.type)) {
      proxy.$showNotification(`❌ File "${file.name}" is not a valid type.`, "error");
      return false;
    }
    if (file.size > 5 * 1024 * 1024) {
      proxy.$showNotification(`❌ File "${file.name}" is too large. Max size is 5MB.`, "error");
      return false;
    }
    return true;
  });

  if (validFiles.length) {
    newOwner.value.additional_documents = [
      ...newOwner.value.additional_documents,
      ...validFiles
    ];
    proxy.$showNotification(`✅ Added ${validFiles.length} additional document(s)`, "success");
  }

  e.target.value = '';
};

const removeNewOwnerAdditionalDocument = (index) => {
  if (!Array.isArray(newOwner.value.additional_documents)) return;
  newOwner.value.additional_documents.splice(index, 1);
};
// const handleFloorPlanUpload = (e) => {
//   const files = Array.from(e.target.files);
//   if (files.length > 0) {
//     const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/svg+xml', 'image/webp'];
//     const validFiles = files.filter(file => {
//       if (!validTypes.includes(file.type)) {
//         proxy.$showNotification(`❌ File "${file.name}" is not a valid image type.`, "error");
//         return false;
//       }
//       if (file.size > 10 * 1024 * 1024) {
//         proxy.$showNotification(`❌ Floor plan "${file.name}" is too large. Max size is 10MB.`, "error");
//         return false;
//       }
//       return true;
//     });

//     if (validFiles.length > 0) {
//       const filesWithNames = validFiles.map(file => ({
//         file: file, 
//         name: file.name, 
//         size: file.size, 
//         type: file.type,
//         customName: file.name.replace(/\.[^/.]+$/, ""), 
//         preview: URL.createObjectURL(file),
//         isNewUpload: true
//       }));
//       form.value.floorPlans = [...form.value.floorPlans, ...filesWithNames];
//       e.target.value = '';
//       proxy.$showNotification(`✅ Added ${validFiles.length} new floor plan(s)`, "success");
//     }
//   }
// };

const updateFloorPlanName = (index, event) => {
  form.value.floorPlans[index].customName = event.target.value;
};

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
        // _uid gives vuedraggable a stable item-key across reorders
        _uid: `g-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        file: file, name: file.name, size: file.size, type: file.type,
        preview: URL.createObjectURL(file)
      }));
      form.value.gallery = [...form.value.gallery, ...filesWithPreview];
      e.target.value = '';
      proxy.$showNotification(`✅ Added ${validFiles.length} image(s) to gallery`, "success");
    }
  }
};
const handlePropertyDocumentUpload = (e, field) => {
  const file = e.target.files?.[0];
  if (!file) return;

  const validTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/svg+xml'];
  if (!validTypes.includes(file.type)) {
    proxy.$showNotification(`❌ File "${file.name}" is not a valid type.`, "error");
    e.target.value = '';
    return;
  }

  if (file.size > 10 * 1024 * 1024) {
    proxy.$showNotification(`❌ File "${file.name}" is too large. Max size is 10MB.`, "error");
    e.target.value = '';
    return;
  }

  form.value[field] = file;
  e.target.value = '';
  proxy.$showNotification(`✅ Added document: ${file.name}`, "success");
};

const removePropertyDocument = (field) => {
  form.value[field] = null;
  proxy.$showNotification("🗑️ Document removed", "info");
};

const handleAdditionalDocumentsUpload = (e) => {
  const files = Array.from(e.target.files || []);
  if (!files.length) return;
  const validTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/svg+xml'];
  const valid = files.filter(f => {
    if (!validTypes.includes(f.type)) {
      proxy.$showNotification(`❌ Invalid type: ${f.name}`, "error");
      return false;
    }
    if (f.size > 10 * 1024 * 1024) {
      proxy.$showNotification(`❌ File too large: ${f.name}`, "error");
      return false;
    }
    return true;
  });
  if (valid.length) {
    form.value.additionalDocuments = [...(form.value.additionalDocuments || []), ...valid.map(f => ({ file: f, name: f.name }))];
    proxy.$showNotification(`✅ Added ${valid.length} document(s)`, "success");
  }
  e.target.value = '';
};

const removeAdditionalDocument = (index) => {
  form.value.additionalDocuments.splice(index, 1);
};
const getImagePreview = (file) => {
  if (file instanceof File) return URL.createObjectURL(file);
  if (file && file.image_url) return file.image_url;
  if (file && file.path) return file.path;
  return '';
};

const handleImageError = (event) => {
  console.error('❌ Image failed to load:', event);
  event.target.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtc2l6ZT0iMTgiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIiBmaWxsPSIjOTk5Ij5JbWFnZSBub3QgZm91bmQ8L3RleHQ+PC9zdmc+';
};

const cleanupObjectURLs = () => {
  if (form.value.hero_image && form.value.hero_image.preview) {
    URL.revokeObjectURL(form.value.hero_image.preview);
  }
  form.value.floorPlans.forEach(item => { if (item.preview) URL.revokeObjectURL(item.preview); });
  form.value.gallery.forEach(item => { if (item.preview) URL.revokeObjectURL(item.preview); });
};

const removeHeroImage = () => {
  if (form.value.hero_image && form.value.hero_image.preview) {
    URL.revokeObjectURL(form.value.hero_image.preview);
  }
  form.value.hero_image = null;
  proxy.$showNotification("🗑️ Hero image removed", "info");
};

// const removeFloorPlan = (index) => {
//   if (form.value.floorPlans[index] && form.value.floorPlans[index].preview) {
//     URL.revokeObjectURL(form.value.floorPlans[index].preview);
//   }
//   form.value.floorPlans.splice(index, 1);
//   proxy.$showNotification("🗑️ Floor plan removed", "info");
// };

const removeGalleryImage = (index) => {
  if (form.value.gallery[index] && form.value.gallery[index].preview) {
    URL.revokeObjectURL(form.value.gallery[index].preview);
  }
  form.value.gallery.splice(index, 1);
  proxy.$showNotification("🗑️ Image removed from gallery", "info");
};

const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const setAsHeroImage = (index) => {
  if (index === 0) return;
  const selectedImage = form.value.gallery[index];
  form.value.gallery.splice(index, 1);
  form.value.gallery.unshift(selectedImage);
  proxy.$showNotification("✅ Image set as hero property image", "success");
};
const getFloorPlansStats = computed(() => {
  const projectPlans = form.value.floorPlans.filter(fp => fp.fromProject).length;
  const uploadedPlans = form.value.floorPlans.filter(fp => fp.isNewUpload).length;
  const totalPlans = form.value.floorPlans.length;
  
  return {
    projectPlans,
    uploadedPlans,
    totalPlans,
    hasProjectPlans: projectPlans > 0,
    hasUploadedPlans: uploadedPlans > 0,
  };
});
const listingPriceForApi = () => {
  const raw = parsePriceInputDigits(form.value.price);
  const p = Number(raw || 0);
  if (isUnderConstruction.value && p > 0) return String(Math.round(p));
  return raw;
};
const handleSubmit = async (action = 'draft') => {
  try {
    isSubmitting.value = true;
       const plotTypes = ['Plot', 'Land', 'Residential Plot', 'Commercial Plot']; // Adjust based on your data
    const isPlot= form.value.property_type && 
           plotTypes.some(type => 
               form.value.property_type.name.toLowerCase().includes(type.toLowerCase())
           );
           
          if (!isUnderConstruction.value && !form.value.occupancyStatus) {
            proxy.$showNotification("❌ Please select occupancy status!", "error");
            isSubmitting.value = false;
            return;
          }
    if (!selectedOwner.value) {
      proxy.$showNotification("❌ Please select an owner first!", "error");
      isSubmitting.value = false;
      return;
    }
     if (form.value.unit_number && selectedProject.value) {
      if (unitNumberError.value) {
        proxy.$showNotification(`❌ ${unitNumberError.value}`, "error");
        isSubmitting.value = false;
        return;
      }
    }
    if (form.value.saleOrRent === 'Rent' && form.value.rented_status === 'Rented' && !form.value.rented_until) {
      proxy.$showNotification("❌ Please select rental end date!", "error");
      isSubmitting.value = false;
      return;
    }

    const currentAgentId = agentId.value;
    if (!currentAgentId) {
      proxy.$showNotification("❌ Agent ID not found. Please login again.", "error");
      isSubmitting.value = false;
      return;
    }
    const floorPlansSource = {
      project_plans_count: selectedProjectFloorPlans.value.length,
      uploaded_plans_count: form.value.floorPlans.filter(fp => fp.isNewUpload).length,
      total_plans: form.value.floorPlans.length,
      selected_project_plans: selectedProjectFloorPlans.value.map(p => p.id),
    };
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

    if (isUnderConstruction.value && action === 'publish') {
      if (publishPaymentBreakdownBlocked.value) {
        proxy.$showNotification(
          publishPaymentBreakdownBlockTitle.value || 'Fix payment breakdown before publishing.',
          'error',
        );
        isSubmitting.value = false;
        return;
      }
      if (sellingBelowOriginalActive.value) {
        const confirmed = await Swal.fire({
          icon: 'warning',
          title: 'Confirm selling price',
          text: 'Selling price is lower than original price. Are you sure you want to continue?',
          showCancelButton: true,
          confirmButtonText: 'Continue',
          cancelButtonText: 'Cancel',
        });
        if (!confirmed.isConfirmed) {
          isSubmitting.value = false;
          return;
        }
      }
    }

    if (action !== 'draft') {
      if (isUnderConstruction.value) {
              // ✅ للـ Under Construction: original_price مطلوب
              if (!form.value.original_price || parsePriceInputDigits(form.value.original_price) <= 0) {
                proxy.$showNotification("❌ Please enter original price for under construction properties!", "error");
                isSubmitting.value = false;
                return;
              }
            }
      if (!form.value.completionStatus) {
        proxy.$showNotification("❌ Please select completion status!", "error");
        isSubmitting.value = false;
        return;
      }
      
      if (form.value.gallery.length === 0) {
        proxy.$showNotification("❌ At least one gallery image is required!", "error");
        isSubmitting.value = false;
        return;
      }
      
      if (form.value.gallery.length < 10 && !isPlot) {
        proxy.$showNotification(`❌ At least 10 gallery images are required! Currently you have ${form.value.gallery.length}.`, "error");
        isSubmitting.value = false;
        return;
      }else if (form.value.gallery.length < 1 && isPlot){
          proxy.$showNotification(`❌ At least 1 gallery images are required! Currently you have ${form.value.gallery.length}.`, "error");
        isSubmitting.value = false;
        return;
      }
    if (form.value.gallery.length > 15) {
          proxy.$showNotification(
            `❌ Maximum 15 gallery images are allowed. Currently you have ${form.value.gallery.length}.`,
            "error"
          );
          isSubmitting.value = false;
          return;
        }

      if (action === 'publish' && shouldBlockSubmitSellingBelowOp() && getSellingPriceVsOpWarning()) {
        proxy.$showNotification(SELLING_SIGNIFICANTLY_BELOW_OP_MSG, 'error');
        isSubmitting.value = false;
        return;
      }

    }

    
 
    const formData = new FormData();
    formData.append('action', action);
    formData.append('owner_id', selectedOwner.value.id);
    formData.append('agent_id', currentAgentId);
    formData.append('property_type_id', form.value.property_type.id);
    formData.append('area_id', form.value.area.id);
    formData.append('unit_view_id', form.value.unit_view?.id ?? "");
    formData.append('layout_type_id', form.value.layout_type?.id ?? "");
    listingFeatureOptions.forEach(feature => {
      formData.append(
        `additional_features[${feature.key}]`,
        form.value[feature.key] ? 1 : 0
      );
    });

    if (form.value.rented_status) formData.append('rented_status', form.value.rented_status);
    if (form.value.rented_until) formData.append('rented_until', form.value.rented_until);
    if (form.value.payment_plan) formData.append('payment_plan', form.value.payment_plan);
    
    if (form.value.spa_document instanceof File) formData.append('spa_document', form.value.spa_document);
    if (form.value.desk_document instanceof File) formData.append('desk_document', form.value.desk_document);
    if (form.value.other_document instanceof File) formData.append('other_document', form.value.other_document);
    (form.value.additionalDocuments || []).forEach((item) => {
      const file = item?.file || item;
      if (file instanceof File) formData.append('additional_documents[]', file);
    });

    if (form.value.gallery.length > 0) {
      const firstImage = form.value.gallery[0].file || form.value.gallery[0];
      if (firstImage instanceof File) formData.append('hero_image', firstImage);
    }
    if (form.value.driveLink) {
      formData.append('drive_link', form.value.driveLink);
    }
     
    formData.append('is_hot_deal', form.value.is_hot_deal || 'No');
    
    const textFields = {
      'unit_number': form.value.unit_number, 'ownership_type': form.value.ownership_type,
      'listing_status': form.value.saleOrRent, 'completion_status': form.value.completionStatus,
      'price': listingPriceForApi(), 'number_of_bedrooms': form.value.number_of_bedrooms,
      'number_of_bathrooms': form.value.number_of_bathrooms, 'size_sqmt': form.value.size_sqmt,
      'size_sqft': form.value.size_sqft, 'furnished_status': form.value.furnished_status,
      'comment': form.value.comment, 'mortgage_status': form.value.mortgageStatus,
      'occupancy_status': form.value.occupancyStatus, 'mortgage_amount': form.value.mortgageAmount,
      'rent_expiry_date': form.value.rentExpiryDate, 'rent_amount': form.value.rentAmount,
      'mortgage_comment': form.value.mortgageComment,
     
    };

    Object.entries(textFields).forEach(([key, value]) => {
      if (value !== null && value !== undefined && value !== '') formData.append(key, value);
    });

    if (form.value.original_price !== '' && form.value.original_price != null) {
      const opDigits = parsePriceInputDigits(form.value.original_price);
      if (opDigits !== '') formData.append('original_price', opDigits);
    }
    const sellingForDb = parsePriceInputDigits(form.value.price);
    if (sellingForDb !== '') {
      formData.append('selling_price', sellingForDb);
    }

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

    if (form.value.developer) formData.append('developer_id', form.value.developer.id);
    if (selectedProject.value) formData.append('project_id', selectedProject.value.id);

        if (form.value.floorPlans.length > 0) {
          let floorPlanIndex = 0;
          
          form.value.floorPlans.forEach((item) => {
            if (item.isNewUpload && item.file instanceof File) {
              formData.append(`floor_plans[${floorPlanIndex}]`, item.file);
              formData.append(`floor_plan_names[${floorPlanIndex}]`, item.customName || item.name.replace(/\.[^/.]+$/, ""));
              floorPlanIndex++;
            }
            else if (item.fromProject && item.projectFloorPlanId) {
              formData.append(`project_floor_plan_ids[]`, item.projectFloorPlanId);
            }
          });
        }

    if (form.value.gallery.length > 0) {
      // form.value.gallery is now ordered by the user's drag-and-drop.
      // Send each file in display order and a parallel `new_gallery_order[i]=N`
      // so the backend writes the chosen `order` on each gallery_images row.
      form.value.gallery.forEach((item, index) => {
        const file = item.file || item;
        if (file instanceof File) {
          formData.append(`gallery[${index}]`, file);
          formData.append(`new_gallery_order[${index}]`, index + 1);
        }
      });
    }
    if (selectedProjectFloorPlans.value.length > 0) {
      selectedProjectFloorPlans.value.forEach(plan => {
        formData.append('project_floor_plan_ids[]', plan.id);
      });
    }

    console.log('📤 Sending form data with action:', action);
    console.log('📋 Payment plan JSON:', form.value.payment_plan);

    const response = await api.post("/listings/properties", formData, {
      headers: { "Content-Type": "multipart/form-data" },
      timeout: 30000,
    });

    console.log("✅ Success Response:", response.data);
    const propertyId = response.data.data?.id || response.data.id;
    
    let successMessage;
    if (action === 'draft') successMessage = "✅ Property saved as draft successfully!";
    else if (action === 'preview') successMessage = "✅ Property saved for preview!";
    else successMessage = "✅ Property published successfully!";
    
    proxy.$showNotification(successMessage, "success");
    
    if (action === 'preview') proxy.$router.push(`/property-details/${propertyId}`);
    else proxy.$router.push(`/property-details/${propertyId}`);
    
  } catch (error) {
    console.error("❌ Full Error:", error);
    if (error.code === 'ECONNABORTED') proxy.$showNotification("❌ Request timeout. Please try again.", "error");
    else if (error.response?.status === 413) proxy.$showNotification("❌ File too large. Please reduce image sizes.", "error");
    else if (error.response?.data?.errors) {
      const errorMessages = Object.values(error.response.data.errors).flat().join(', ');
      proxy.$showNotification(`❌ Validation Error: ${errorMessages}`, "error");
    } else if (error.response?.data?.message) proxy.$showNotification(`❌ Server Error: ${error.response.data.message}`, "error");
    else if (error.message) proxy.$showNotification(`❌ Network Error: ${error.message}`, "error");
    else proxy.$showNotification("❌ Unexpected error occurred.", "error");
  } finally {
    isSubmitting.value = false;
  }
};

const resetForm = () => {
  cleanupObjectURLs();
  breakdownInstallments.value = [];
  assignmentExpenseLines.value = [];
  resetAssignmentExpenseDraft();
  installmentDraft.value = {
    type: 'percentage',
    value: null,
    date: new Date().toISOString().slice(0, 10),
  };
  form.value = {
    title: "", unit_number: "", ownership_type: null, saleOrRent: "", completionStatus: "",
    area: null, developer: null, property_type: null, price: "", original_price: "",
    number_of_bedrooms: "", number_of_bathrooms: "", layout_type: null, unit_view: null, furnished_status: "",
    size_sqmt: "", size_sqft: "", hero_image: null, floorPlans: [], gallery: [],
    comment: "", mortgageStatus: "", occupancyStatus: "", mortgageAmount: "",
    rentExpiryDate: "", rentAmount: "", mortgageComment: "", projectAreas: [],
    rented_status: "", rented_until: "", payment_plan: "", payment_plans: null, driveLink: "", is_hot_deal: "",
    handover_date: "", noc_percentage: 0,  noc_fixed_amount: 0,  noc_fees_ready: 0,          
     noc_fees_off_plan: 0, 
    // Boolean for every known feature (from the shared LISTING_FEATURE_KEYS list).
  ...LISTING_FEATURE_KEYS.reduce((acc, k) => { acc[k] = false; return acc; }, {}),
    spa_document: null, desk_document: null, other_document: null,
    additionalDocuments: [],
  };
  selectedOwner.value = null;
  selectedProject.value = null;
  console.log('🔄 Form has been reset');
};
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
  } else {
    console.log('⚠️ Unknown status, setting NOC to 0');
  }
  
  form.value.noc_fixed_amount = !isNaN(nocValue) && nocValue >= 0 ? nocValue : 0;
  console.log('✅ NOC fixed amount set to:', form.value.noc_fixed_amount);
};
const isNocEnabled = computed(() => {
  if (!selectedProject.value) return false;
  
  if (!selectedProject.value.developer) return false;
  
  const status = String(form.value.completionStatus || '').trim().toLowerCase().replace(/_/g, ' ');
  const isUC = status === 'under construction' || status === 'off plan';
  const isReady = status === 'completed';
  
  if (!isUC && !isReady) return false;
  
  let nocValue = 0;
  if (isUC) {
    nocValue = Number(selectedProject.value.developer.noc_fees_off_plan || 0);
  } else if (isReady) {
    nocValue = Number(selectedProject.value.developer.noc_fees_ready || 0);
  }
  if (!isUC && !isReady) return false;
  
   return nocValue > 0;
});

const isNocAutoPopulated = computed(() => {
  if (!isNocEnabled.value) return false;
  
  const status = String(form.value.completionStatus || '').trim().toLowerCase().replace(/_/g, ' ');
  const isUC = status === 'under construction' || status === 'off plan';
  
  let developerNoc = 0;
  if (isUC) {
    developerNoc = Number(selectedProject.value.developer.noc_fees_off_plan || 0);
  } else {
    developerNoc = Number(selectedProject.value.developer.noc_fees_ready || 0);
  }
  
  const currentNoc = Number(form.value.noc_fixed_amount);
  // return developerNoc > 0;
  return false
});

const showNocField = computed(() => {
  if (!isNocEnabled.value) return false;
  
  const nocValue = developerNocValue.value;
  if (nocValue <= 0) return false;
  
  console.log('🔍 showNocField: true', {
    isUnderConstruction: isUnderConstruction.value,
    selectedProject: !!selectedProject.value,
    developer: !!selectedProject.value?.developer,
    nocValue: nocValue
  });
  
  return true;
});

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
   return 0;
});
const loadPaymentPlansFromString = (paymentPlanString) => {
  if (!paymentPlanString) return [];
  try {
    const parsed = JSON.parse(paymentPlanString);
    if (Array.isArray(parsed)) {
      return parsed.map((plan) => {
        const label = typeof plan === 'string' ? plan : plan?.label || plan?.value || '';
        return (
          findPaymentPlanOptionByLabel(label) ??
          attemptParseLegacyPaymentPlanLabel(label) ?? { label, initial_percent: null, handover_percent: null, invalid: true }
        );
      });
    }
    return [{ label: paymentPlanString, value: paymentPlanString }];
  } catch (e) {
    const label = String(paymentPlanString);
    return [
      findPaymentPlanOptionByLabel(label) ??
        attemptParseLegacyPaymentPlanLabel(label) ?? { label, initial_percent: null, handover_percent: null, invalid: true },
    ];
  }
};

const convertSqmToSqft = () => {
  if (form.value.size_sqmt && !isNaN(form.value.size_sqmt)) {
    form.value.size_sqft = (form.value.size_sqmt * 10.7639).toFixed(2);
  } else {
    form.value.size_sqft = "";
  }
};

const preventNumberInvalidKeys = (event) => {
  if (['e', 'E', '+', '-'].includes(event.key)) {
    event.preventDefault();
  }
};

const convertSqftToSqm = () => {
  if (form.value.size_sqft && !isNaN(form.value.size_sqft)) {
    form.value.size_sqmt = (form.value.size_sqft / 10.7639).toFixed(2);
  } else {
    form.value.size_sqmt = "";
  }
};

const bedroomOptions = [
  { label: "Studio", value: 0 }, { label: "1", value: 1 }, { label: "2", value: 2 }, { label: "3", value: 3 },
  { label: "4", value: 4 }, { label: "5", value: 5 }, { label: "6", value: 6 },
  { label: "7", value: 7 }, { label: "8", value: 8 }, { label: "9", value: 9 },
  { label: "10+", value: 10 }
];

const bathroomOptions = [
  { label: "1", value: 1 }, { label: "2", value: 2 }, { label: "3", value: 3 },
  { label: "4", value: 4 }, { label: "5", value: 5 }, { label: "6", value: 6 },
  { label: "7", value: 7 }, { label: "8", value: 8 }, { label: "9", value: 9 },
  { label: "10+", value: 10 }
];

const nationalities = ref([
  "Afghanistan","Albania","Algeria","Andorra","Angola","Antigua and Barbuda",
  "Argentina","Armenia","Australia","Austria","Azerbaijan","Bahamas","Bahrain",
  "Bangladesh","Barbados","Belarus","Belgium","Belize","Benin","Bhutan","Bolivia",
  "Bosnia and Herzegovina","Botswana","Brazil","Brunei","Bulgaria","Burkina Faso",
  "Burundi","Cabo Verde","Cambodia","Cameroon","Canada","Central African Republic",
  "Chad","Chile","China","Colombia","Comoros","Congo (Congo-Brazzaville)",
  "Costa Rica","Croatia","Cuba","Cyprus","Czechia","Denmark","Djibouti","Dominica",
  "Dominican Republic","Ecuador","Egypt","El Salvador","Equatorial Guinea","Eritrea",
  "Estonia","Eswatini","Ethiopia","Fiji","Finland","France","Gabon","Gambia",
  "Georgia","Germany","Ghana","Greece","Grenada","Guatemala","Guinea","Guinea-Bissau",
  "Guyana","Haiti","Honduras","Hungary","Iceland","India","Indonesia","Iran","Iraq",
  "Ireland","Israel","Italy","Jamaica","Japan","Jordan","Kazakhstan","Kenya",
  "Kiribati","Kuwait","Kyrgyzstan","Laos","Latvia","Lebanon","Lesotho","Liberia",
  "Libya","Liechtenstein","Lithuania","Luxembourg","Madagascar","Malawi","Malaysia",
  "Maldives","Mali","Malta","Marshall Islands","Mauritania","Mauritius","Mexico",
  "Micronesia","Moldova","Monaco","Mongolia","Montenegro","Morocco","Mozambique",
  "Myanmar","Namibia","Nauru","Nepal","Netherlands","New Zealand","Nicaragua",
  "Niger","Nigeria","North Korea","North Macedonia","Norway","Oman","Pakistan",
  "Palau","Palestine","Panama","Papua New Guinea","Paraguay","Peru","Philippines",
  "Poland","Portugal","Qatar","Romania","Russia","Rwanda","Saint Kitts and Nevis",
  "Saint Lucia","Saint Vincent and the Grenadines","Samoa","San Marino",
  "Sao Tome and Principe","Saudi Arabia","Senegal","Serbia","Seychelles",
  "Sierra Leone","Singapore","Slovakia","Slovenia","Solomon Islands","Somalia",
  "South Africa","South Korea","South Sudan","Spain","Sri Lanka","Sudan","Suriname",
  "Sweden","Switzerland","Syria","Taiwan","Tajikistan","Tanzania","Thailand",
  "Timor-Leste","Togo","Tonga","Trinidad and Tobago","Tunisia","Turkey",
  "Turkmenistan","Tuvalu","Uganda","Ukraine","United Arab Emirates","United Kingdom",
  "United States","Uruguay","Uzbekistan","Vanuatu","Vatican City","Venezuela",
  "Vietnam","Yemen","Zambia","Zimbabwe"
]);

const clearUnitNumberError = () => {
  unitNumberError.value = "";
};

const validateUnitNumber = async () => {
  if (!form.value.unit_number || 
      !form.value.saleOrRent || 
      !selectedProject.value) {
    return true;
  }
  if (!form.value.area || !form.value.area.id) {
    unitNumberError.value = "⚠️ Please select an area first before checking unit number";
    return false;
  }
  try {
    isLoadingUnitNumber.value = true;
    unitNumberError.value = "";

    const response = await api.post("/listings/properties/validate-unit-number", {
      unit_number: form.value.unit_number,
      listing_status: form.value.saleOrRent,
      project_id: selectedProject.value.id,
      area_id: form.value.area.id
    });

    const data = response.data;

    if (data.exists) {
      unitNumberError.value = `❌ This unit number is already in use for ${form.value.saleOrRent} in this project`;
    } else {
      console.log("✅ Unit number is available");
    }

  } catch (error) {
    console.error("❌ Error validating unit number:", error);
    
    if (error.response?.status === 422) {
      const errors = error.response.data.errors;
      if (errors.unit_number) {
        unitNumberError.value = errors.unit_number[0];
      }
    } else {
      unitNumberError.value = "⚠️ Could not validate unit number. Please try again.";
    }
     return false;
  } finally {
    isLoadingUnitNumber.value = false;
  }
};

onMounted(() => {
  console.log('🔧 Component mounted, fetching data...');
  console.log('👤 Current agent ID:', agentId.value);
  
  Promise.all([
    fetchOwners(),
    fetchPropertyTypes(),
    fetchAreas(),
     fetchDealCosts() 
  ]).then(() => console.log('✅ Basic data loaded'));
   form.value.is_hot_deal = "No";
  fetchDevelopers();
  fetchUnitViews(); 
  fetchLayoutTypes();
  fetchProjects(); 
  cleanupObjectURLs();
  console.log('🚀 All data fetch requests initiated');
});
</script>

<style scoped>
/* 🔹 Global Styles */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background-color: #f8f9fa;
}

/* 🔹 Dark Blue Gradient Colors */
:root {
  --dark-blue-gradient: linear-gradient(135deg, #0c2461 0%, #1e3799 100%);
  --dark-blue-light: #1e3799;
  --dark-blue-dark: #0c2461;
  --dark-blue-hover: #2a3db0;
}

/* 🔹 Modal Styles */
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(4px);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1050;
  padding: 20px;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.modal-container {
  background: #ffffff;
  border-radius: 16px;
  width: 100%;
  max-width: 1200px;
  max-height: 85vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  position: relative;
  animation: slideUp 0.4s ease;
}

@keyframes slideUp {
  from { transform: translateY(30px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

.modal-header {
  color: white;
  padding: 24px 30px;
  border-radius: 16px 16px 0 0;
  position: sticky;
  top: 0;
  z-index: 10;
}

.modal-header h5 {
  font-size: 1.5rem;
  font-weight: 600;
  margin: 0;
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

.modal-body {
  padding: 30px;
}

.modal-footer {
  background: #f8f9fa;
  padding: 20px 30px;
  border-radius: 0 0 16px 16px;
  border-top: 1px solid #eaeaea;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

/* 🔹 Section Styles */
.section {
  background: white;
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 24px;
  border: 1px solid #eaeaea;
  transition: all 0.3s ease;
}

.section:hover {
  border-color: var(--dark-blue-light);
  box-shadow: 0 8px 25px rgba(30, 55, 153, 0.1);
}

.section-title {
  color: #2d3748;
  font-size: 1.25rem;
  font-weight: 600;
  margin-bottom: 20px;
  padding-bottom: 12px;
  border-bottom: 2px solid var(--dark-blue-light);
  display: flex;
  align-items: center;
  gap: 10px;
}

.section-title i {
  color: var(--dark-blue-light);
  font-size: 1.2rem;
}

/* 🔹 Form Styles */
.form-label {
  font-weight: 500;
  color: #4a5568;
  margin-bottom: 8px;
  display: block;
}

/*.form-control, .v-select {*/
/*  border: 1px solid #e2e8f0;*/
/*  border-radius: 10px;*/
/*  padding: 12px 16px;*/
/*  font-size: 0.95rem;*/
/*  transition: all 0.3s ease;*/
/*  background: white;*/
/*}*/

.form-control:focus, .v-select:focus {
  border-color: var(--dark-blue-light);
  box-shadow: 0 0 0 3px rgba(30, 55, 153, 0.15);
  outline: none;
}

.v-select {
  --vs-border-radius: 10px;
  --vs-border-color: #e2e8f0;
  --vs-search-input-color: #4a5568;
}

.v-select .vs__dropdown-toggle {
  border-radius: 10px;
  padding: 8px;
}

.v-select .vs__search {
  padding: 10px;
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

/* 🔹 Button Styles */
.btn {
  border-radius: 10px;
  padding: 12px 24px;
  font-weight: 500;
  font-size: 0.95rem;
  transition: all 0.3s ease;
  border: none;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.btn-primary {
  background: rgba(12, 36, 97, 0.9); /* Solid dark blue */
  color: white;
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.btn-primary:hover {
  background: rgba(12, 36, 97, 1);
  transform: translateY(-2px);
  box-shadow: 0 10px 20px rgba(12, 36, 97, 0.3);
}


.btn-primary:hover {
  background: linear-gradient(135deg, #1a3db0 0%, #2540c7 100%);
}
.btn-secondary {
  background: #6c757d;
  color: white;
}

.btn-secondary:hover {
  background: #5a6268;
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
}

.btn-outline-primary {
  border: 2px solid rgba(5, 10, 40, 0.95);
  color: rgba(5, 10, 40, 0.95);
  background: transparent;
}

.btn-outline-primary:hover {
  background: linear-gradient(90deg, rgba(255, 255, 255, 0.25) 0%, rgba(20, 30, 80, 0.79) 0%, rgba(5, 10, 40, 0.95) 100%);
  color: white;
  transform: translateY(-2px);
}

.btn-danger {
  background: linear-gradient(135deg, #ef476f 0%, #d90429 100%);
  color: white;
}

.btn-danger:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 20px rgba(239, 71, 111, 0.3);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none !important;
  box-shadow: none !important;
}

/* 🔹 File Upload Styles */
.file-upload-area {
  border: 2px dashed #cbd5e0;
  border-radius: 12px;
  padding: 40px 20px;
  text-align: center;
  background: #f8fafc;
  transition: all 0.3s ease;
  cursor: pointer;
}

.file-upload-area:hover {
  border-color: var(--dark-blue-light);
  background: #edf2f7;
}

.file-upload-area.dragover {
  border-color: var(--dark-blue-light);
  background: #e6eeff;
  transform: scale(1.02);
}

.file-upload-icon {
  font-size: 3rem;
  color: var(--dark-blue-light);
  margin-bottom: 15px;
}

.file-upload-text {
  color: #4a5568;
  margin-bottom: 10px;
}

.file-upload-hint {
  color: #718096;
  font-size: 0.875rem;
}

/* 🔹 Gallery & Floor Plan Styles */
.image-preview-container {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 16px;
  margin-top: 20px;
}

.image-card {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
  transition: all 0.3s ease;
  position: relative;
}

.image-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
  border-color: var(--dark-blue-light);
}

.image-card.hero {
  border: 3px solid var(--dark-blue-light);
  position: relative;
}

.image-card.hero::before {
  content: 'Main Image';
  position: absolute;
  top: 10px;
  left: 10px;
  background: var(--dark-blue-gradient);
  color: white;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
  z-index: 2;
}

.image-preview {
  width: 100%;
  height: 140px;
  object-fit: cover;
  transition: transform 0.3s ease;
}

.image-card:hover .image-preview {
  transform: scale(1.05);
}

.image-info {
  padding: 12px;
  background: #f8fafc;
}

.image-name {
  font-weight: 500;
  color: #2d3748;
  font-size: 0.9rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-bottom: 5px;
}

.image-size {
  color: #718096;
  font-size: 0.8rem;
}

.image-actions {
  position: absolute;
  top: 10px;
  right: 10px;
  display: flex;
  gap: 5px;
  opacity: 0;
  transition: opacity 0.3s ease;
}

.image-card:hover .image-actions {
  opacity: 1;
}

.btn-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(0, 0, 0, 0.1);
  color: #4a5568;
  font-size: 0.875rem;
  transition: all 0.3s ease;
}

.btn-icon:hover {
  background: white;
  color: #ef476f;
  transform: scale(1.1);
}

.btn-icon.primary:hover {
  color: var(--dark-blue-light);
}

/* 🔹 Badge Styles */
.badge {
  font-size: 0.75rem;
  padding: 6px 12px;
  border-radius: 20px;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.badge-primary {
  background: var(--dark-blue-gradient);
  color: white;
}

.badge-success {
  background: linear-gradient(135deg, #06d6a0 0%, #04966a 100%);
  color: white;
}

.badge-warning {
  background: linear-gradient(135deg, #ffd166 0%, #f4a261 100%);
  color: #2d3748;
}

.badge-danger {
  background: linear-gradient(135deg, #ef476f 0%, #d90429 100%);
  color: white;
}

/* 🔹 Loading States */
.loading-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.9);
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: inherit;
  z-index: 10;
  backdrop-filter: blur(5px);
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #f3f3f3;
  border-top: 3px solid var(--dark-blue-light);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

@media (max-width: 768px) {
  .modal-container {
    max-height: 90vh;
    border-radius: 12px;
    margin: 10px;
  }
  
  .modal-body {
    padding: 20px;
  }
  
  .modal-header {
    padding: 18px 20px;
  }
  
  .section {
    padding: 18px;
  }
  
  .image-preview-container {
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 12px;
  }
  
  .btn {
    padding: 10px 18px;
    font-size: 0.9rem;
  }
}

@media (max-width: 576px) {
  .modal-container {
    max-height: 95vh;
  }
  
  .modal-body {
    padding: 15px;
  }
  
  .image-preview-container {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .section-title {
    font-size: 1.1rem;
  }
}

.text-muted {
  color: #718096 !important;
}

.text-primary {
  color: var(--dark-blue-light) !important;
}

.text-success {
  color: #06d6a0 !important;
}

.text-danger {
  color: #ef476f !important;
}

.text-warning {
  color: #ffd166 !important;
}

.text-info {
  color: #0dcaf0 !important;
}

.mb-0 {
  margin-bottom: 0 !important;
}

.mt-1 {
  margin-top: 4px !important;
}

.mt-2 {
  margin-top: 8px !important;
}

.mt-3 {
  margin-top: 12px !important;
}

.mt-4 {
  margin-top: 16px !important;
}

.mb-1 {
  margin-bottom: 4px !important;
}

.mb-2 {
  margin-bottom: 8px !important;
}

.mb-3 {
  margin-bottom: 12px !important;
}

.mb-4 {
  margin-bottom: 16px !important;
}

.ms-auto {
  margin-left: auto !important;
}

.me-2 {
  margin-right: 8px !important;
}

.d-flex {
  display: flex !important;
}

.justify-content-between {
  justify-content: space-between !important;
}

.justify-content-end {
  justify-content: flex-end !important;
}

.align-items-center {
  align-items: center !important;
}

.flex-wrap {
  flex-wrap: wrap !important;
}

.gap-2 {
  gap: 8px !important;
}

.gap-3 {
  gap: 12px !important;
}

.gap-4 {
  gap: 16px !important;
}

.w-100 {
  width: 100% !important;
}

/* 🔹 Scrollbar Styling */
.modal-container::-webkit-scrollbar {
  width: 8px;
}

.modal-container::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 4px;
}

.modal-container::-webkit-scrollbar-thumb {
  background: var(--dark-blue-gradient);
  border-radius: 4px;
}

.modal-container::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(135deg, #1a3db0 0%, #2540c7 100%);
}

/* 🔹 Form Grid System */
.row {
  display: flex;
  flex-wrap: wrap;
  margin: -10px;
}

.col {
  flex: 1;
  padding: 10px;
  min-width: 200px;
}

.col-md-6 {
  flex: 0 0 50%;
  max-width: 50%;
}

.col-md-4 {
  flex: 0 0 33.333%;
  max-width: 33.333%;
}

.col-md-3 {
  flex: 0 0 25%;
  max-width: 25%;
}

@media (max-width: 992px) {
  .col-md-6 {
    flex: 0 0 100%;
    max-width: 100%;
  }
  
  .col-md-4 {
    flex: 0 0 50%;
    max-width: 50%;
  }
  
  .col-md-3 {
    flex: 0 0 50%;
    max-width: 50%;
  }
}

@media (max-width: 576px) {
  .col-md-4,
  .col-md-3 {
    flex: 0 0 100%;
    max-width: 100%;
  }
}

.card-hover {
  transition: all 0.3s ease;
}

.card-hover:hover {
  transform: translateY(-5px);
  box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
}

.input-group {
  display: flex;
  gap: 10px;
  align-items: center;
}

.input-group .form-control {
  flex: 1;
}

.input-group-text {
  background: #f8f9fa;
  border: 1px solid #e2e8f0;
  padding: 12px 16px;
  border-radius: 10px;
  color: #4a5568;
  font-weight: 500;
  white-space: nowrap;
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

.divider {
  height: 1px;
  background: linear-gradient(to right, transparent, #e2e8f0, transparent);
  margin: 24px 0;
}

.required::after {
  content: " *";
  color: #ef476f;
  font-weight: bold;
}

[data-tooltip] {
  position: relative;
  cursor: help;
}

[data-tooltip]:hover::before {
  content: attr(data-tooltip);
  position: absolute;
  bottom: 100%;
  left: 50%;
  transform: translateX(-50%);
  background: #2d3748;
  color: white;
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 0.85rem;
  white-space: nowrap;
  z-index: 1000;
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

[data-tooltip]:hover::after {
  content: '';
  position: absolute;
  bottom: 100%;
  left: 50%;
  transform: translateX(-50%);
  border: 5px solid transparent;
  border-top-color: #2d3748;
  margin-bottom: -5px;
}

.project-areas-label {
  color: #0d6efd;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 5px;
  margin-bottom: 5px;
}

.project-areas-label i {
  font-size: 14px;
}
.vs--searchable .vs__dropdown-toggle {
    min-height: 2.75rem !important;
    height: auto !important;
    padding-bottom: 0;
}
.vs__selected-options{
     height: auto;
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
.is-invalid {
  border-color: #dc3545 !important;
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e") !important;
  background-repeat: no-repeat;
  background-position: right calc(0.375em + 0.1875rem) center;
  background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.invalid-feedback {
  display: block;
  width: 100%;
  margin-top: 0.25rem;
  font-size: 0.875em;
  color: #dc3545;
}

.valid-feedback {
  display: block;
  width: 100%;
  margin-top: 0.25rem;
  font-size: 0.875em;
  color: #198754;
}
.project-floor-plan-tag {
  background-color: #e8f4fd !important;
  border-color: #0d6efd !important;
  color: #0d6efd !important;
}

.project-floor-plan-tag .tag-close {
  color: #0d6efd !important;
}

.floor-plan-source-badge {
  position: absolute;
  top: 10px;
  left: 10px;
  background: rgba(13, 110, 253, 0.9);
  color: white;
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 0.7rem;
  font-weight: 500;
  z-index: 1;
}
/* Custom column for 5 items per row on extra large screens */
.col-xl-2-4 {
  flex: 0 0 20%;
  max-width: 20%;
}

/* Floor Plan Card Styles */
.floor-plan-card {
  transition: all 0.3s ease;
}

.floor-plan-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}

.floor-plan-card.selected {
  border-color: #0d6efd !important;
  box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
}

.floor-plan-card .cursor-pointer {
  cursor: pointer;
}

.floor-plan-image-container {
  position: relative;
  overflow: hidden;
  background-color: #f8f9fa;
}

.floor-plan-image {
  transition: all 0.3s ease;
}

.floor-plan-card:hover .floor-plan-image {
  transform: scale(1.05);
}

.selection-toggle {
  opacity: 0;
  background: rgba(13, 110, 253, 0.1);
  transition: all 0.3s ease;
}

.floor-plan-card:hover .selection-toggle {
  opacity: 1;
}

.selection-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  transition: all 0.3s ease;
}

.floor-plan-card.selected .selection-toggle {
  opacity: 1;
  background: rgba(13, 110, 253, 0.2);
}

.floor-plan-name {
  font-weight: 600;
  color: #333;
}

.plan-details {
  font-size: 0.8rem;
}

.plan-details i {
  width: 16px;
  text-align: center;
}

/* Selected Plans Summary */
.selected-plans-summary {
  border-left: 4px solid #0d6efd;
}

.selected-plan-item {
  border: 1px solid #dee2e6;
  transition: all 0.3s ease;
}

.selected-plan-item:hover {
  background-color: #f8f9fa;
  transform: translateY(-2px);
}

/* Responsive adjustments */
@media (max-width: 1400px) {
  .col-xl-2-4 {
    flex: 0 0 25%;
    max-width: 25%; /* 4 per row on smaller desktops */
  }
}

@media (max-width: 992px) {
  .col-xl-2-4 {
    flex: 0 0 33.333%;
    max-width: 33.333%; /* 3 per row on tablets */
  }
}

@media (max-width: 768px) {
  .col-xl-2-4 {
    flex: 0 0 50%;
    max-width: 50%; /* 2 per row on mobile */
  }
}

@media (max-width: 576px) {
  .col-xl-2-4 {
    flex: 0 0 100%;
    max-width: 100%; /* 1 per row on very small screens */
  }
}

/* Badge styles */
.badge.bg-primary {
  background: linear-gradient(135deg, #0c2461 0%, #1e3799 100%) !important;
}

.z-1 {
  z-index: 1;
}

.border-2 {
  border-width: 2px !important;
}
/* Single Selection Styles */
.selected-single {
  transform: scale(1.02);
  transition: all 0.3s ease;
  /*z-index: 10;*/
}

.selected-single .card {
  border-color: #198754 !important;
  box-shadow: 0 0 20px rgba(25, 135, 84, 0.3) !important;
}

.not-selected {
  opacity: 0.6;
  transition: opacity 0.3s ease;
}

.not-selected:hover {
  opacity: 0.8;
}

/* Selection Check */
.selection-check {
  background: rgba(25, 135, 84, 0.8);
  border-radius: 50%;
  width: 50px;
  height: 50px;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: pulseCheck 1.5s infinite;
}

@keyframes pulseCheck {
  0% { transform: scale(1); }
  50% { transform: scale(1.1); }
  100% { transform: scale(1); }
}

/* Selection Hint */
.selection-hint {
  background: rgba(13, 110, 253, 0.8);
  border-radius: 50%;
  width: 50px;
  height: 50px;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Custom column for 5 items per row */
.col-xl-2-4 {
  flex: 0 0 20%;
  max-width: 20%;
}

/* Border Styles */
.border-3 {
  border-width: 3px !important;
}

/* Alert Styles */
.alert-success {
  background: linear-gradient(135deg, #d1e7dd 0%, #badbcc 100%);
  border-color: #a3cfbb;
}

/* Badge Styles */
.badge.bg-success {
  background: linear-gradient(135deg, #198754 0%, #157347 100%) !important;
}

.badge.bg-primary {
  background: linear-gradient(135deg, #0c2461 0%, #1e3799 100%) !important;
}

.badge.bg-info {
  background: linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%) !important;
}

/* Floor Plan Card Hover Effect */
.floor-plan-card:hover .selection-hint-container {
  opacity: 1 !important;
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

/* Button hover effects */
.btn-outline-primary:hover {
  background: linear-gradient(135deg, #0c2461 0%, #1e3799 100%);
  color: white;
  border-color: #0c2461;
}

.btn-danger:hover {
  background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
  color: white;
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

/* Drag & drop cues for the gallery preview tiles */
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

<style>
/* Property form page: let the page grow naturally; no inner scroll on the router shell */
#app main.dashboard-main > .dashboard-main-router {
  height: auto !important;
  min-height: 0 !important;
  max-height: none !important;
  overflow: visible !important;
  overflow-y: visible !important;
  overflow-x: hidden !important;
  flex: 0 0 auto !important;
}
</style>