<template>
  <Teleport to="body">
    <div v-if="modelValue" class="lpb-modal-backdrop" @click.self="close">
      <div class="lpb-modal lpb-modal--compact" role="dialog" aria-labelledby="lpb-modal-title" aria-modal="true">
        <div class="lpb-modal-header">
          <div>
            <h5 id="lpb-modal-title" class="lpb-modal-title mb-0">Payment breakdown &amp; NOC</h5>
            <p v-if="listingTitle" class="lpb-modal-subtitle mb-0">{{ listingTitle }}</p>
          </div>
          <button type="button" class="btn-close btn-close-sm" aria-label="Close" @click="close" />
        </div>

        <div class="lpb-modal-body">
          <div v-if="isLoading" class="lpb-center-state">
            <div class="spinner-border spinner-border-sm text-primary" role="status" />
            <span>Loading listing…</span>
          </div>

          <div v-else-if="loadError" class="alert alert-danger lpb-alert mb-0">{{ loadError }}</div>

          <div v-else class="lpb-layout">
            <!-- Left column: prices, plan, summary -->
            <div class="lpb-col lpb-col--form">
              <section class="lpb-section">
                <h6 class="lpb-section-title">Prices &amp; plan</h6>
                <div class="row g-2">
                  <div class="col-sm-6">
                    <label class="lpb-label">Original price (OP)</label>
                    <div class="lpb-input-shell">
                      <span v-if="priceFieldIsEmpty(form.original_price)" class="lpb-input-hint">OP in AED</span>
                      <input
                        :value="formatPriceInputDisplay(form.original_price)"
                        type="text"
                        inputmode="numeric"
                        class="form-control lpb-control"
                        @input="form.original_price = parsePriceInputDigits($event.target.value)"
                      />
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <label class="lpb-label">Selling price</label>
                    <div class="lpb-input-shell">
                      <span v-if="priceFieldIsEmpty(form.price)" class="lpb-input-hint">Selling in AED</span>
                      <input
                        :value="formatPriceInputDisplay(form.price)"
                        type="text"
                        inputmode="numeric"
                        class="form-control lpb-control"
                        @input="form.price = parsePriceInputDigits($event.target.value)"
                      />
                    </div>
                  </div>
                  <!-- Payment plan - يظهر فقط للـ Under Construction -->
                  <div class="col-12" v-if="isUnderConstruction">
                    <label class="lpb-label">Payment plan</label>
                    <v-select
                      v-model="form.payment_plans"
                      :options="paymentPlanOptions"
                      label="label"
                      placeholder="Select plan"
                      :clearable="true"
                      :searchable="true"
                      class="lpb-vselect"
                      :class="{ 'is-invalid': paymentPlanFieldInvalid, 'lpb-vselect-empty': !form.payment_plans }"
                    />
                    <div v-if="paymentPlanFieldInvalid" class="lpb-hint lpb-hint--err">{{ paymentPlanFieldError }}</div>
                  </div>
                  <div class="col-12" v-else>
                    <div class="text-muted small mt-2">
                      <i class="fas fa-info-circle me-1"></i>
                      Payment plan not required for completed properties.
                    </div>
                  </div>
                </div>
              </section>

              <!-- Summary - يظهر فقط للـ Under Construction -->
              <section class="lpb-section lpb-summary-grid" v-if="isUnderConstruction">
                <div class="lpb-summary-item">
                  <span class="lpb-summary-label">UC tranche</span>
                  <span class="lpb-summary-value">{{ initialPercentForm.toFixed(0) }}%</span>
                  <span class="lpb-summary-sub">{{ formatAed(ucTrancheAed) }}</span>
                </div>
                <div class="lpb-summary-item">
                  <span class="lpb-summary-label">Premium</span>
                  <span class="lpb-summary-value" :class="{ 'text-danger': premiumIsNegative }">{{ premiumDisplayAed }}</span>
                  <span v-if="premiumIsNegative" class="lpb-summary-sub text-danger">Below OP</span>
                </div>
                <div class="lpb-summary-item">
                  <span class="lpb-summary-label">Handover</span>
                  <span class="lpb-summary-value">{{ formatAed(handoverAmountForm) }}</span>
                  <span class="lpb-summary-sub">{{ installmentPercentForm.toFixed(0) }}% of OP</span>
                </div>
              </section>

              <!-- Handover & NOC -->
              <section class="lpb-section">
                <h6 class="lpb-section-title">Handover &amp; NOC</h6>
                <div class="row g-2">
                  <!-- Handover date - يظهر فقط للـ Under Construction -->
                  <div class="col-sm-6" v-if="isUnderConstruction">
                    <label class="lpb-label">Handover date</label>
                    <AdvancedDatePicker
                      v-model="form.handover_date"
                      date-only
                      dob-layout
                      compact
                      :block-future-dates="false"
                      placeholder="Handover date"
                      class="lpb-date-picker"
                      :invalid="!!paymentHandoverDateError"
                    />
                    <div v-if="paymentHandoverDateError" class="lpb-hint lpb-hint--err">{{ paymentHandoverDateError }}</div>
                  </div>
                  <div class="col-sm-6" :class="{ 'col-sm-12': !isUnderConstruction }">
                    <label class="lpb-label">
                      NOC Fees
                      <span class="text-muted fw-normal small">(AED)</span>
                      <span class="badge ms-1" :class="currentNocType === 'Off-Plan' ? 'bg-warning' : 'bg-success'" style="font-size: 8px;">
                        {{ currentNocType }}
                      </span>
                    </label>
                    <div class="input-group input-group-sm">
                      <span class="input-group-text bg-light">
                        <i class="fas fa-shield-alt" :class="text-muted"></i>
                      </span>
                      <input
                        v-model.number="form.noc_fixed_amount"
                        type="number"
                        min="0"
                        step="1000"
                        class="form-control lpb-control bg-light"
                        disabled="true"
                       
                        placeholder="Enter NOC amount"
                      />
                      <span class="input-group-text" v-if="developerNocValue > 0">
                        <i class="fas fa-lock text-primary" title="Auto-populated from developer"></i>
                      </span>
                    </div>
                    <small class="text-muted d-block mt-1" style="font-size: 9px;">
                      <span v-if="developerNocValue > 0" class="text-primary">
                        <i class="fas fa-info-circle"></i> 
                        Auto-populated from developer ({{ currentNocType }}): 
                        <strong>{{ formatAed(developerNocValue) }}</strong>
                      </span>
                      <span v-else-if="selectedProject && developerNocValue === 0" class="text-muted">
                        <i class="fas fa-info-circle"></i> No NOC fees for this developer
                      </span>
                      <span v-else class="text-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        NOC value modified manually
                      </span>
                    </small>
                  </div>
                </div>
                <div class="lpb-noc-mini mt-2">
                  <span>Required: <strong>{{ formatAed(nocRequiredAed) }}</strong></span>
                  <span class="mx-1">·</span>
                  <span>Scheduled: <strong>{{ formatAed(scheduledInstallmentsAed) }}</strong></span>
                </div>
              </section>
              <!-- Validation - يظهر فقط للـ Under Construction -->
              <section v-if="isUnderConstruction && paymentBreakdownValidationSummary.length" class="lpb-section lpb-validation">
                <h6 class="lpb-section-title">Checks</h6>
                <ul class="lpb-validation-list">
                  <li
                    v-for="item in paymentBreakdownValidationSummary"
                    :key="item.id"
                    :class="`lpb-val lpb-val--${item.level}`"
                  >
                    <span aria-hidden="true">{{ item.icon }}</span>
                    <span>{{ item.text }}</span>
                  </li>
                </ul>
              </section>
            </div>

            <!-- Right column: installments - يظهر فقط للـ Under Construction -->
            <div class="lpb-col lpb-col--table" v-if="isUnderConstruction">
              <section class="lpb-section lpb-section--fill">
                <h6 class="lpb-section-title">Installments</h6>

                <div class="row g-2 align-items-end lpb-add-row">
                  <div class="col-4">
                    <label class="lpb-label">Type</label>
                    <v-select
                      v-model="installmentDraft.type"
                      :options="installmentTypeOptions"
                      :reduce="(item) => item.value"
                      label="label"
                      :clearable="false"
                      class="lpb-vselect"
                    />
                  </div>
                  <div class="col-3">
                    <label class="lpb-label">{{ installmentDraft.type === 'percentage' ? '%' : 'AED' }}</label>
                    <div class="lpb-input-shell">
                      <span v-if="numberFieldIsEmpty(installmentDraft.value)" class="lpb-input-hint">0</span>
                      <input
                        v-model.number="installmentDraft.value"
                        type="number"
                        min="0"
                        class="form-control lpb-control"
                      />
                    </div>
                  </div>
                  <div class="col-3">
                    <label class="lpb-label">Due date</label>
                    <AdvancedDatePicker
                      v-model="installmentDraft.date"
                      date-only
                      dob-layout
                      compact
                      :block-future-dates="false"
                      placeholder="Date"
                      class="lpb-date-picker"
                    />
                  </div>
                  <div class="col-2">
                    <button type="button" class="btn btn-primary lpb-btn-add w-100" @click="addInstallment">+ Add</button>
                  </div>
                </div>

                <div v-if="addInstallmentError" class="lpb-hint lpb-hint--err mt-1">{{ addInstallmentError }}</div>
                <div v-if="breakdownSellingPriceMismatchActive" class="lpb-inline-alert mt-2">
                  Total mismatch. {{ breakdownSellingDeltaMessage }}
                </div>
                <div v-if="mixedInstallmentTypesError" class="lpb-inline-alert mt-1">{{ mixedInstallmentTypesError }}</div>
                <div v-if="percentageInstallmentPlanMismatchError" class="lpb-inline-alert mt-1">
                  {{ percentageInstallmentPlanMismatchError }}
                </div>

                <div class="lpb-table-wrap">
                  <table class="table lpb-table mb-0">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(row, idx) in paymentBreakdownRows" :key="row.id">
                        <td>{{ idx + 1 }}</td>
                        <td>{{ row.type }}</td>
                        <td :class="{ 'text-danger': row.type === 'Premium' && row.amount < 0 }">
                          {{ formatAed(row.amount) }}
                        </td>
                        <td>{{ row.type === 'Premium' ? '—' : formatDateShort(row.date) }}</td>
                        <td>
                          <span class="lpb-status-badge" :class="breakdownRowStatusClass(row.status)">
                            {{ row.status }}
                          </span>
                        </td>
                        <td class="text-end">
                          <button
                            v-if="row.entryId"
                            type="button"
                            class="btn btn-link lpb-btn-remove"
                            @click="removeInstallment(row.entryId)"
                          >
                            Remove
                          </button>
                        </td>
                      </tr>
                      <tr v-if="!paymentBreakdownRows.length">
                        <td colspan="6" class="text-center text-muted py-3">No rows yet — add installments above</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </section>
            </div>

            <!-- للـ Completed: رسالة بدلاً من جدول الأقساط -->
            <div class="lpb-col lpb-col--table" v-else>
              <section class="lpb-section lpb-section--fill d-flex align-items-center justify-content-center">
                <div class="text-center text-muted">
                  <i class="fas fa-check-circle fa-2x mb-2 d-block" style="color: #28a745;"></i>
                  <p class="mb-0">No installments required for completed properties.</p>
                </div>
              </section>
            </div>
          </div>

          <!-- Assignment deal costs - يظهر دائماً -->
          <section class="lpb-section lpb-section--expenses mt-2">
            <h6 class="lpb-section-title">Assignment deal costs</h6>
            <p class="lpb-expenses-hint mb-2">DLD, agency, mortgage fees, and other charges — separate from installments.</p>

            <!-- إضافة تكلفة جديدة -->
            <!-- <div class="row g-2 align-items-end lpb-expenses-add">
              <div class="col-12 col-md-3">
                <label class="lpb-label">Label</label>
                <div class="lpb-input-shell">
                  <span v-if="textFieldIsEmpty(assignmentExpenseDraft.label)" class="lpb-input-hint">e.g. DLD, Agency fee</span>
                  <input v-model="assignmentExpenseDraft.label" type="text" class="form-control lpb-control" />
                </div>
              </div>
              <div class="col-6 col-md-2">
                <label class="lpb-label">Type</label>
                <v-select
                  v-model="assignmentExpenseDraft.calcType"
                  :options="assignmentExpenseTypeOptions"
                  :reduce="(item) => item.value"
                  label="label"
                  :clearable="false"
                  class="lpb-vselect"
                />
              </div>
              <div v-if="assignmentExpenseDraft.calcType === 'percentage'" class="col-6 col-md-2">
                <label class="lpb-label">Base</label>
                <v-select
                  v-model="assignmentExpenseDraft.base"
                  :options="assignmentExpenseBaseOptions"
                  :reduce="(item) => item.value"
                  label="label"
                  :clearable="false"
                  class="lpb-vselect"
                />
              </div>
              <div class="col-6" :class="assignmentExpenseDraft.calcType === 'percentage' ? 'col-md-2' : 'col-md-3'">
                <label class="lpb-label">{{ assignmentExpenseDraft.calcType === 'percentage' ? 'Value (%)' : 'Amount (AED)' }}</label>
                <div class="lpb-input-shell">
                  <span v-if="numberFieldIsEmpty(assignmentExpenseDraft.value)" class="lpb-input-hint">0</span>
                  <input
                    v-model.number="assignmentExpenseDraft.value"
                    type="number"
                    min="0"
                    step="any"
                    class="form-control lpb-control"
                  />
                </div>
              </div>
              <div class="col-6 col-md-2 d-flex align-items-end">
                <div class="form-check form-switch mb-2">
                  <input
                    id="lpb-assignment-expense-vat"
                    v-model="assignmentExpenseDraft.vatEnabled"
                    class="form-check-input"
                    type="checkbox"
                  />
                  <label class="form-check-label lpb-label mb-0" for="lpb-assignment-expense-vat">VAT 5%</label>
                </div>
              </div>
              <div class="col-12 col-md-2">
                <button type="button" class="btn btn-outline-primary lpb-btn-add w-100" @click="addExpenseLine">+ Add cost</button>
              </div>
            </div> -->

            <div v-if="addExpenseError" class="lpb-hint lpb-hint--err mt-1">{{ addExpenseError }}</div>

            <!-- جدول التكاليف -->
            <div class="lpb-expenses-table-wrap mt-2">
              <table class="table lpb-table lpb-expenses-table mb-0">
                <thead>
                  <tr>
                    <th>Label</th>
                    <th class="d-none d-lg-table-cell">Base</th>
                    <th>Type</th>
                    <th>Value</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">VAT</th>
                    <th class="text-end">Total</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <!-- عرض Agency Fee كبند تلقائي إذا كان سعر البيع > 0 -->
                  <tr v-if="Number(sellingPriceNum) > 0" class="table-light">
                    <td>
                      <span class="fw-semibold">Agency Fee</span>
                      <span class="badge bg-primary ms-1" style="font-size: 8px;">2%</span>
                    </td>
                    <td class="d-none d-lg-table-cell">SP</td>
                    <td>Percentage</td>
                    <td>2%</td>
                    <td class="text-end">{{ formatAed((Number(sellingPriceNum) * 2) / 100) }}</td>
                    <td class="text-end">—</td>
                    <td class="text-end fw-semibold">{{ formatAed((Number(sellingPriceNum) * 2) / 100) }}</td>
                    <td></td>
                  </tr>
                  
                  <!-- التكاليف الأخرى -->
                  <tr v-for="line in assignmentExpenseLines" :key="line.id">
                    <td>
                      <div class="lpb-input-shell">
                        <span v-if="textFieldIsEmpty(line.label)" class="lpb-input-hint">Label</span>
                        <input v-model="line.label" type="text" class="form-control lpb-control lpb-control--inline" :disabled="line.isReadonly" />
                      </div>
                    </td>
                    <td class="d-none d-lg-table-cell">
                      <v-select
                        v-if="line.calcType === 'percentage'"
                        v-model="line.base"
                        :options="assignmentExpenseBaseOptions"
                        :reduce="(item) => item.value"
                        label="label"
                        :clearable="false"
                        class="lpb-vselect lpb-vselect--inline"
                        :disabled="line.isReadonly"
                      />
                      <span v-else class="text-muted">—</span>
                    </td>
                    <td>
                      <v-select
                        v-model="line.calcType"
                        :options="assignmentExpenseTypeOptions"
                        :reduce="(item) => item.value"
                        label="label"
                        :clearable="false"
                        class="lpb-vselect lpb-vselect--inline"
                        :disabled="line.isReadonly"
                      />
                    </td>
                    <td>
                      <input v-model.number="line.value" type="number" min="0" step="any" class="form-control lpb-control lpb-control--inline" :disabled="line.isReadonly" />
                    </td>
                    <td class="text-end text-nowrap">{{ formatAed(assignmentExpenseLineAmount(line)) }}</td>
                    <td class="text-end text-nowrap">
                      <label class="d-inline-flex align-items-center gap-1 mb-0">
                        <input v-model="line.vatEnabled" type="checkbox" class="form-check-input m-0" :disabled="line.isReadonly" />
                        <span>{{ line.vatEnabled ? formatAed(assignmentExpenseLineVat(line)) : '—' }}</span>
                      </label>
                    </td>
                    <td class="text-end text-nowrap fw-semibold">{{ formatAed(assignmentExpenseLineTotal(line)) }}</td>
                    <td class="text-end">
                      <button type="button" class="btn btn-link lpb-btn-remove" @click="removeAssignmentExpenseLine(line.id)" :disabled="line.isReadonly">×</button>
                    </td>
                  </tr>
                  <tr v-if="!assignmentExpenseLines.length && Number(sellingPriceNum) <= 0">
                    <td colspan="8" class="text-center text-muted py-3">No cost lines yet — add DLD, agency, or other fees above.</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- ملخص التكاليف مع Agency Fee -->
            <div v-if="assignmentExpenseLines.length > 0 || Number(sellingPriceNum) > 0" class="lpb-expenses-summary row g-2 mt-2 pt-2 border-top">
              <div class="col-4">
                <span class="lpb-summary-label">Subtotal</span>
                <div class="lpb-summary-value">{{ formatAed(expensesSubtotalWithAgency) }}</div>
              </div>
              <div class="col-4">
                <span class="lpb-summary-label">VAT (5%)</span>
                <div class="lpb-summary-value">{{ formatAed(expensesVatWithAgency) }}</div>
              </div>
              <div class="col-4">
                <span class="lpb-summary-label">Grand total</span>
                <div class="lpb-summary-value text-primary">{{ formatAed(expensesGrandTotalWithAgency) }}</div>
              </div>
            </div>
          </section>

          <div v-if="saveError" class="alert alert-danger lpb-alert mt-2 mb-0">{{ saveError }}</div>
        </div>

        <div class="lpb-modal-footer">
          <button type="button" class="btn btn-light btn-sm" :disabled="isSaving" @click="close">Cancel</button>
          <button
            type="button"
            class="btn btn-outline-primary btn-sm"
            :disabled="isLoading || !!loadError"
            @click="showPaymentPreview = true"
          >
            <i class="ri-eye-line me-1" aria-hidden="true" />
            Preview
          </button>
          <button
            type="button"
            class="btn btn-primary btn-sm"
            :disabled="isLoading || isSaving || !!loadError"
            @click="save"
          >
            <span v-if="isSaving" class="spinner-border spinner-border-sm me-1" />
            Save breakdown
          </button>
        </div>
      </div>
    </div>

    <PaymentDetailsPreviewModal
      v-model="showPaymentPreview"
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
      :breakdown-rows="paymentBreakdownRows"
      :assignment-expense-rows="assignmentExpenseLines"
      :assignment-expenses-subtotal="assignmentExpensesSubtotal"
      :assignment-expenses-total-vat="assignmentExpensesTotalVat"
      :assignment-expenses-grand-total="assignmentExpensesGrandTotal"
    />
  </Teleport>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import vSelect from 'vue-select';
import 'vue-select/dist/vue-select.css';
import api from '@/plugins/axios';
import AdvancedDatePicker from '@/components/shared/AdvancedDatePicker.vue';
import { parsePriceInputDigits, formatPriceInputDisplay } from '@/utils/priceInputFormat';
import { paymentPlanOptions, paymentPlanSelectionLabel, resolvePaymentPlanOption } from '@/composables/listingPaymentPlanPresets';
import { useListingPaymentBreakdown } from '@/composables/useListingPaymentBreakdown';
import { parsePaymentBreakdown } from '@/utils/listingPaymentBreakdownStatus';
import PaymentDetailsPreviewModal from '@/components/payment-plans/PaymentDetailsPreviewModal.vue';
import {
  useListingAssignmentExpenses,
  assignmentExpenseTypeOptions,
  assignmentExpenseBaseOptions,
  parseAssignmentExpenseLines,
} from '@/composables/useListingAssignmentExpenses';
import '../../../css/listing-payment-breakdown-modal.css';

const priceFieldIsEmpty = (v) => !parsePriceInputDigits(v);
const textFieldIsEmpty = (v) => !String(v ?? '').trim();
const numberFieldIsEmpty = (v) => v == null || v === '' || (typeof v === 'number' && Number.isNaN(v));

const listingHasPreviewBreakdownData = (p) =>
  p?.original_price != null ||
  parsePaymentBreakdown(p?.payment_breakdown).length > 0 ||
  parseAssignmentExpenseLines(p?.assignment_expense_lines).length > 0 ||
  p?.handover_date != null;

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  listingId: { type: [Number, String], default: null },
  listingPreview: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'saved']);

// ============ تعريف جميع refs ============
const isLoading = ref(false);
const isSaving = ref(false);
const loadError = ref('');
const saveError = ref('');
const addInstallmentError = ref('');
const addExpenseError = ref('');
const listingTitle = ref('');
const showPaymentPreview = ref(false);
const selectedProject = ref(null);
const dealCostSettings = ref({});

const form = ref({
  price: '',
  original_price: '',
  payment_plans: null,
  payment_plan: '',
  handover_date: '',
  noc_percentage: 0,
  noc_fixed_amount: 0,
  completionStatus: 'Under Construction',
});

const breakdownInstallments = ref([]);
const installmentDraft = ref({
  type: 'percentage',
  value: null,
  date: new Date().toISOString().slice(0, 10),
});

// ============ Computed Properties ============
const isUnderConstruction = computed(() => {
  const s = String(form.value.completionStatus ?? '').trim().toLowerCase().replace(/_/g, ' ');
  return s === 'under construction' || s === 'off plan';
});

const nocFixedAmount = computed(() => {
  return Number(form.value.noc_fixed_amount || 0);
});

const developerNocValue = computed(() => {
    console.log('🔍 developerNocValue computed called');
  console.log('📌 selectedProject.value:', selectedProject.value);
  console.log('📌 selectedProject.value?.developer:', selectedProject.value?.developer);
  if (!selectedProject.value || !selectedProject.value.developer) return 0;
  
  const status = String(form.value.completionStatus ?? '').trim().toLowerCase().replace(/_/g, ' ');
  const isUC = status === 'under construction' || status === 'off plan';
  console.log("selectedProject.value.developer"+selectedProject.value.developer);
  if (isUC) {
    return Number(selectedProject.value.developer.noc_fees_off_plan || 0);
  } else {
    return Number(selectedProject.value.developer.noc_fees_ready || 0);
  }
});

const currentNocType = computed(() => {
  const status = String(form.value.completionStatus || '').trim().toLowerCase().replace(/_/g, ' ');
  const isUC = status === 'under construction' || status === 'off plan';
  return isUC ? 'Off-Plan' : 'Ready';
});

const nocPercentageOptions = [
  { label: '0%', value: 0 },
  { label: '10%', value: 10 },
  { label: '20%', value: 20 },
  { label: '30%', value: 30 },
  { label: '40%', value: 40 },
  { label: '50%', value: 50 },
];

const installmentTypeOptions = [
  { label: 'Percentage', value: 'percentage' },
  { label: 'Amount (AED)', value: 'amount' },
];

// ============ useListingPaymentBreakdown ============
const {
  selectedPaymentPlanLabel,
  initialPercentForm,
  installmentPercentForm,
  originalPriceNum,
  sellingPriceNum,
  premiumAmountForm,
  premiumIsNegative,
  premiumDisplayAed,
  ucTrancheAed,
  handoverAmountForm,
  paymentPlanFieldInvalid,
  paymentPlanFieldError,
  paymentHandoverDateError,
  paymentBreakdownRows,
  breakdownRowStatusClass,
  breakdownSellingPriceMismatchActive,
  breakdownSellingDeltaMessage,
  mixedInstallmentTypesError,
  percentageInstallmentPlanMismatchError,
  paymentBreakdownValidationSummary,
  nocPercentOfOp,
  nocRequiredAed,
  nocRemainingAed,
  nocRequirementMet,
  nocProgressPaidLabel,
  paidAmountForm,
  paidPercentOfOp,
  scheduledInstallmentsAed,
  formatAed,
  formatDateShort,
  installmentToAmount,
  initialPaymentTarget,
} = useListingPaymentBreakdown({
  form,
  breakdownInstallments,
  installmentDraft,
  isUnderConstruction,
});

// ============ useListingAssignmentExpenses ============
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
  addDefaultDealCosts,
} = useListingAssignmentExpenses({
  originalPriceNum,
  sellingPriceNum,
  premiumAmountForm,
  formatAed,
  nocFixedAmount: nocFixedAmount,
  dealCostSettings: dealCostSettings,
});

// ============ حساب التكاليف مع Agency Fee ============
const agencyFeePercent = computed(() => 2);

const agencyFeeAmount = computed(() => {
  const sp = Number(sellingPriceNum.value || 0);
  return (sp * agencyFeePercent.value) / 100;
});

const expensesSubtotalWithAgency = computed(() => {
  const agency = agencyFeeAmount.value;
  const subtotal = Number(assignmentExpensesSubtotal.value || 0);
  return subtotal + agency;
});

const expensesVatWithAgency = computed(() => {
  const agencyVat = agencyFeeAmount.value * 0.05;
  const vat = Number(assignmentExpensesTotalVat.value || 0);
  return vat + agencyVat;
});

const expensesGrandTotalWithAgency = computed(() => {
  return expensesSubtotalWithAgency.value + expensesVatWithAgency.value;
});

// ============ Functions ============
const close = () => emit('update:modelValue', false);

const fetchDealCosts = async () => {
  try {
    const response = await api.get("/settings/deal-costs");
    const data = response.data.data || response.data;
    
    if (data.settings) {
      const settings = {};
      Object.keys(data.settings).forEach(key => {
        const numKey = parseInt(key) || key;
        settings[numKey] = data.settings[key];
      });
      if (!settings['3'] && !settings['agency_fee']) {
        settings['agency_fee'] = 2;
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
      dealCostSettings.value = settings;
    } else {
      dealCostSettings.value = data;
      if (!dealCostSettings.value['agency_fee']) {
        dealCostSettings.value['agency_fee'] = 2;
      }
    }
    
    console.log('✅ Deal cost settings loaded:', dealCostSettings.value);
    
    if (typeof addDefaultDealCosts === 'function') {
      addDefaultDealCosts();
    }
    
  } catch (error) {
    console.error('❌ Error fetching deal costs:', error);
    dealCostSettings.value = {
      dari_admin_fee: 0,
      adgm_admin_fee: 0,
      agency_fee: 2
    };
    if (typeof addDefaultDealCosts === 'function') {
      addDefaultDealCosts();
    }
  }
};

const hydrateFromProperty = (propertyData) => {
  listingTitle.value = propertyData.title || propertyData.reference_number || `Listing #${propertyData.id}`;
  console.log('🔍 hydrateFromProperty called with:', propertyData);

  if (propertyData.project) {
    selectedProject.value = {
      id: propertyData.project.id,
      name: propertyData.project.title || propertyData.project.name,
      developer: propertyData.project.developerData
    };
  }

  let paymentPlanValue = propertyData.payment_plans ?? propertyData.payment_plan;
  if (typeof paymentPlanValue === 'string' && paymentPlanValue.startsWith('[')) {
    try {
      const arr = JSON.parse(paymentPlanValue);
      paymentPlanValue = arr[0] ?? paymentPlanValue;
    } catch {
      /* keep string */
    }
  }
  if (Array.isArray(paymentPlanValue) && paymentPlanValue.length) {
    paymentPlanValue = paymentPlanValue[0];
  }

  const status = String(propertyData.completion_status ?? '').trim().toLowerCase().replace(/_/g, ' ');
  const isUC = status === 'under construction' || status === 'off plan';
  
  let nocValue = 0;
  if (selectedProject.value?.developer) {
    if (isUC) {
      nocValue = Number(selectedProject.value.developer.noc_fees_off_plan || 0);
    } else {
      nocValue = Number(selectedProject.value.developer.noc_fees_ready || 0);
    }
  }

  form.value = {
    price: String(propertyData.price ?? propertyData.selling_price ?? ''),
    original_price:
      propertyData.original_price != null && propertyData.original_price !== ''
        ? String(Math.round(Number(propertyData.original_price)))
        : '',
    payment_plans: paymentPlanValue ? resolvePaymentPlanOption(paymentPlanValue) ?? paymentPlanValue : null,
    payment_plan: propertyData.payment_plan || '',
    handover_date: propertyData.handover_date ? String(propertyData.handover_date).slice(0, 10) : '',
    noc_percentage: nocValue > 0 ? 100 : 0,
    noc_fixed_amount: nocValue,
    completionStatus: propertyData.completion_status || 'Under Construction',
  };

  const rawPb = propertyData.payment_breakdown;
  let loaded = [];
  if (rawPb) {
    try {
      const arr = Array.isArray(rawPb) ? rawPb : JSON.parse(rawPb);
      if (Array.isArray(arr)) {
        loaded = arr.map((row, i) => ({
          id: row.id != null ? Number(row.id) : Date.now() + i,
          type: row.type === 'amount' ? 'amount' : 'percentage',
          value: Number(row.value || 0),
          date: (row.date && String(row.date).slice(0, 10)) || new Date().toISOString().slice(0, 10),
        }));
      }
    } catch {
      loaded = [];
    }
  }
  breakdownInstallments.value = loaded;
  loadAssignmentExpenseLines(propertyData.assignment_expense_lines);
};

const loadListing = async () => {
  if (!props.listingId) return;
  isLoading.value = true;
  loadError.value = '';
  try {
    await fetchDealCosts();
    if (props.listingPreview && listingHasPreviewBreakdownData(props.listingPreview)) {
      hydrateFromProperty({ ...props.listingPreview, id: props.listingId });
      return;
    }
    const response = await api.get(`/listings/properties/${props.listingId}`);
    const propertyData =
      response?.data?.data?.property ||
      response?.data?.data ||
      response?.data?.property ||
      response?.data;
    if (!propertyData?.id) {
      loadError.value = 'Could not load listing data.';
      return;
    }
    hydrateFromProperty(propertyData);
  } catch (e) {
    loadError.value = e?.response?.data?.message || 'Failed to load listing.';
  } finally {
    isLoading.value = false;
  }
};

watch(
  () => form.value.completionStatus,
  (newStatus) => {
    if (!selectedProject.value?.developer) return;
    
    const status = String(newStatus ?? '').trim().toLowerCase().replace(/_/g, ' ');
    const isUC = status === 'under construction' || status === 'off plan';
    
    let nocValue = 0;
    if (isUC) {
      nocValue = Number(selectedProject.value.developer.noc_fees_off_plan || 0);
    } else {
      nocValue = Number(selectedProject.value.developer.noc_fees_ready || 0);
    }
    
    form.value.noc_fixed_amount = nocValue;
  }
);
// ✅ مراقبة تغيير المشروع وتحديث NOC
watch(
  () => selectedProject.value,
  (newProject) => {
    if (!newProject?.developer) {
      form.value.noc_fixed_amount = 0;
      return;
    }
    
    const status = String(form.value.completionStatus ?? '').trim().toLowerCase().replace(/_/g, ' ');
    const isUC = status === 'under construction' || status === 'off plan';
    
    let nocValue = 0;
    if (isUC) {
      nocValue = Number(newProject.developer.noc_fees_off_plan || 0);
    } else {
      nocValue = Number(newProject.developer.noc_fees_ready || 0);
    }
    
    console.log('🔄 NOC updated from project:', {
      project: newProject.name,
      isUC,
      nocValue,
      developer: newProject.developer
    });
    
    form.value.noc_fixed_amount = nocValue;
  },
  { deep: true, immediate: true }
);
watch(
  () => [props.modelValue, props.listingId],
  ([open, id]) => {
    if (open && id) {
      saveError.value = '';
      addInstallmentError.value = '';
      addExpenseError.value = '';
      showPaymentPreview.value = false;
      loadListing();
    }
  },
);

const addInstallment = () => {
  addInstallmentError.value = '';
  const value = Number(installmentDraft.value.value || 0);
  if (!value || value <= 0) {
    addInstallmentError.value = 'Enter a valid installment value.';
    return;
  }
  if (!installmentDraft.value.date) {
    addInstallmentError.value = 'Select a due date.';
    return;
  }
  const newEntry = {
    id: Date.now(),
    type: installmentDraft.value.type,
    value,
    date: installmentDraft.value.date,
  };
  const newAmount = installmentToAmount(newEntry);
  const currentTotal = breakdownInstallments.value.reduce((sum, e) => sum + installmentToAmount(e), 0);
  if (currentTotal + newAmount > initialPaymentTarget.value + 0.01) {
    addInstallmentError.value = 'Installment exceeds under-construction tranche for this plan.';
    return;
  }
  breakdownInstallments.value.push(newEntry);
  installmentDraft.value.value = null;
};

const removeInstallment = (entryId) => {
  breakdownInstallments.value = breakdownInstallments.value.filter((e) => e.id !== entryId);
};

const addExpenseLine = () => {
  addExpenseError.value = '';
  const ok = addAssignmentExpenseLine((msg) => {
    addExpenseError.value = msg;
  });
  if (!ok && !addExpenseError.value) addExpenseError.value = 'Could not add cost line.';
};

const save = async () => {
  if (!props.listingId) return;
  isSaving.value = true;
  saveError.value = '';
  try {
    const planLabel = paymentPlanSelectionLabel(form.value.payment_plans);
    const payload = {
      original_price: parsePriceInputDigits(form.value.original_price) || null,
      selling_price: parsePriceInputDigits(form.value.price) || null,
      payment_plan: planLabel ? JSON.stringify([planLabel]) : null,
      payment_breakdown: breakdownInstallments.value,
      assignment_expense_lines: assignmentExpenseLines.value,
      noc_percentage: Number(form.value.noc_percentage ?? 0),
      noc_fixed_amount: Number(form.value.noc_fixed_amount || 0),
      handover_date: form.value.handover_date || null,
    };
    const response = await api.patch(`/listings/properties/${props.listingId}/payment-breakdown`, payload);
    emit('saved', response?.data?.data || response?.data);
    close();
  } catch (e) {
    saveError.value = e?.response?.data?.message || 'Failed to save payment breakdown.';
  } finally {
    isSaving.value = false;
  }
};
</script>

<style scoped>
.lpb-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1060;
  background: rgba(15, 23, 42, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.5rem;
}

.lpb-modal {
  background: #fff;
  border-radius: 10px;
  width: min(1180px, 98vw);
  max-height: 94vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.22);
  font-size: 11px;
  line-height: 1.35;
  color: #334155;
}

.lpb-modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #e2e8f0;
  flex-shrink: 0;
}

.lpb-modal-title {
  font-size: 0.875rem;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.25;
}

.lpb-modal-subtitle {
  font-size: 0.7rem;
  color: #64748b;
  margin-top: 0.15rem;
  font-weight: 500;
}

.lpb-modal-body {
  padding: 0.65rem 1rem;
  overflow-y: auto;
  flex: 1;
  min-height: 0;
}

.lpb-modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.4rem;
  padding: 0.5rem 1rem;
  border-top: 1px solid #e2e8f0;
  flex-shrink: 0;
}

.lpb-layout {
  display: grid;
  grid-template-columns: minmax(280px, 340px) 1fr;
  gap: 0.75rem;
  align-items: stretch;
  min-height: 420px;
}

@media (max-width: 900px) {
  .lpb-layout {
    grid-template-columns: 1fr;
    min-height: auto;
  }
}

.lpb-col--form {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.lpb-col--table {
  min-width: 0;
}

.lpb-section {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 0.5rem 0.6rem;
}

.lpb-section--fill {
  height: 100%;
  display: flex;
  flex-direction: column;
  min-height: 380px;
}

.lpb-section-title {
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #475569;
  margin: 0 0 0.35rem;
}

.lpb-label {
  display: block;
  font-size: 0.625rem;
  font-weight: 600;
  color: #64748b;
  margin-bottom: 0.2rem;
  line-height: 1.2;
}

.lpb-control {
  font-size: 0.75rem;
  padding: 0.3rem 0.5rem;
  min-height: 32px;
}

.lpb-control::placeholder {
  font-size: 0.625rem;
  color: #94a3b8;
  opacity: 1;
}

.lpb-control--inline {
  font-size: 0.7rem;
  min-height: 28px;
  padding: 0.15rem 0.35rem;
}

.lpb-control--inline::placeholder {
  font-size: 0.6rem;
}

.lpb-hint {
  font-size: 10px;
  margin-top: 0.15rem;
}

.lpb-hint--err {
  color: #b91c1c;
}

.lpb-summary-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.35rem;
}

.lpb-summary-item {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
}

.lpb-summary-label {
  font-size: 9px;
  text-transform: uppercase;
  color: #94a3b8;
  font-weight: 600;
}

.lpb-summary-value {
  font-size: 11px;
  font-weight: 700;
  color: #0f172a;
}

.lpb-summary-sub {
  font-size: 9px;
  color: #64748b;
}

.lpb-noc-mini {
  font-size: 10px;
  color: #64748b;
}

.lpb-validation-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.lpb-val {
  display: flex;
  gap: 0.35rem;
  align-items: flex-start;
  font-size: 10px;
  padding: 0.15rem 0;
}

.lpb-val--ok {
  color: #047857;
}

.lpb-val--warn {
  color: #b45309;
}

.lpb-val--err {
  color: #b91c1c;
}

.lpb-table-wrap {
  flex: 1;
  min-height: 200px;
  max-height: calc(94vh - 280px);
  overflow: auto;
  margin-top: 0.4rem;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  background: #fff;
}

.lpb-table {
  font-size: 10px;
}

.lpb-table thead th {
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: #64748b;
  font-weight: 700;
  padding: 0.35rem 0.45rem;
  background: #f1f5f9;
  position: sticky;
  top: 0;
  z-index: 1;
}

.lpb-table tbody td {
  padding: 0.3rem 0.45rem;
  vertical-align: middle;
}

.lpb-status-badge {
  display: inline-block;
  font-size: 9px;
  font-weight: 600;
  padding: 0.15rem 0.35rem;
  border-radius: 4px;
  white-space: nowrap;
  max-width: 110px;
  overflow: hidden;
  text-overflow: ellipsis;
}

.lpb-btn-remove {
  font-size: 10px;
  padding: 0;
  line-height: 1.2;
}

.lpb-btn-add {
  font-size: 0.7rem;
  padding: 0.3rem 0.45rem;
  min-height: 32px;
}

.lpb-inline-alert {
  font-size: 10px;
  color: #b91c1c;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 4px;
  padding: 0.25rem 0.4rem;
}

.lpb-center-state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 3rem;
  font-size: 11px;
  color: #64748b;
}

.lpb-alert {
  font-size: 11px;
  padding: 0.4rem 0.6rem;
}

/* v-select & date picker */
.lpb-vselect :deep(.vs__dropdown-toggle) {
  min-height: 32px;
  padding: 0 0.25rem;
  font-size: 0.75rem;
}

.lpb-vselect :deep(.vs__selected),
.lpb-vselect :deep(.vs__search),
.lpb-vselect :deep(.vs__dropdown-option) {
  font-size: 0.75rem;
  margin: 0;
  padding: 0.25rem 0.4rem;
}

.lpb-vselect :deep(.vs__search::placeholder),
.lpb-vselect :deep(.vs__placeholder) {
  font-size: 0.625rem !important;
  color: #94a3b8;
}

.lpb-vselect--inline :deep(.vs__dropdown-toggle) {
  min-height: 28px;
}

.lpb-vselect--inline :deep(.vs__selected),
.lpb-vselect--inline :deep(.vs__search) {
  font-size: 0.7rem;
}

.lpb-vselect--inline :deep(.vs__search::placeholder),
.lpb-vselect--inline :deep(.vs__placeholder) {
  font-size: 0.6rem !important;
}

.lpb-vselect :deep(.vs__actions) {
  padding: 0 0.35rem;
}

.lpb-date-picker :deep(.advanced-date-trigger) {
  min-height: 32px;
  padding: 0.3rem 0.5rem;
  font-size: 0.75rem;
}

.lpb-date-picker :deep(.advanced-date-text) {
  font-size: 0.75rem;
  font-weight: 500;
}

.lpb-date-picker :deep(.advanced-date-text.is-placeholder) {
  font-size: 0.625rem !important;
  font-weight: 400;
  opacity: 0.8;
  color: #94a3b8;
}

.lpb-section--expenses {
  background: #fff;
}

.lpb-expenses-hint {
  font-size: 0.625rem;
  color: #64748b;
  margin: 0;
  line-height: 1.3;
}

.lpb-expenses-table-wrap {
  max-height: 220px;
  overflow: auto;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  background: #fff;
}

.lpb-expenses-summary .lpb-summary-label {
  font-size: 8px;
}

.lpb-expenses-summary .lpb-summary-value {
  font-size: 0.7rem;
}
</style>