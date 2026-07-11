import { computed } from 'vue';
import Swal from 'sweetalert2';
import { parsePriceInputDigits } from '@/utils/priceInputFormat';
import {
  PAYMENT_PLAN_PARSE_ERROR,
  paymentPlanSelectionLabel,
  resolvePaymentPlanOption,
} from '@/composables/listingPaymentPlanPresets';

export const STATUS_PAID = 'Paid';
export const STATUS_DUE_ON_TRANSFER = 'Due on transfer';
export const STATUS_UPCOMING = 'Upcoming';
export const NOC_PAID_BELOW_REQUIRED_MSG =
  'Installment schedule does not meet the required NOC percentage of original price.';
export const SELLING_BELOW_OP_WARN_MSG = 'Selling below original price';
export const SELLING_BELOW_OP_CONFIRM_MSG =
  'Selling price is lower than original price. Are you sure you want to continue?';

const BREAKDOWN_SELLING_TOLERANCE_AED = 1;
const MIXED_INSTALLMENT_TYPES_MSG = 'Cannot mix percentage and amount installment types.';

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

const formatAed = (value) =>
  new Intl.NumberFormat('en-AE', {
    style: 'currency',
    currency: 'AED',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(Number(value ?? 0));

export function useListingPaymentBreakdown({
  form,
  breakdownInstallments,
  installmentDraft,
  isUnderConstruction,
  breakdownPaidOnLoadIds = null,
}) {
  const selectedPaymentPlanOption = computed(() => resolvePaymentPlanOption(form.value.payment_plans));

  const isPaymentPlanSelectionParseValid = computed(() => {
    if (form.value.payment_plans == null || form.value.payment_plans === '') return true;
    const o = selectedPaymentPlanOption.value;
    if (!o || o.invalid) return false;
    return Number.isFinite(o.initial_percent) && Number.isFinite(o.handover_percent);
  });

  const paymentPlanFieldInvalid = computed(
    () =>
      isUnderConstruction.value &&
      form.value.payment_plans != null &&
      form.value.payment_plans !== '' &&
      !isPaymentPlanSelectionParseValid.value,
  );

  const paymentPlanFieldError = computed(() =>
    paymentPlanFieldInvalid.value ? PAYMENT_PLAN_PARSE_ERROR : '',
  );

  const paymentPlanMissingForPublish = computed(
    () => isUnderConstruction.value && (form.value.payment_plans == null || form.value.payment_plans === ''),
  );

  const selectedPaymentPlanLabel = computed(() => {
    const label = paymentPlanSelectionLabel(form.value.payment_plans);
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

  const originalContractPriceNum = computed(() =>
    Number(parsePriceInputDigits(form.value.original_price) || 0),
  );

  const sellingPriceNum = computed(() => Number(parsePriceInputDigits(form.value.price) || 0));

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

  const initialPaymentTarget = computed(() => (originalPriceNum.value * initialPercentForm.value) / 100);
  const ucTrancheAed = computed(() => initialPaymentTarget.value);
  const handoverAmountForm = computed(() => originalPriceNum.value - initialPaymentTarget.value);

  const installmentToAmount = (entry) => {
    if (entry.type === 'percentage') return (originalPriceNum.value * Number(entry.value || 0)) / 100;
    return Number(entry.value || 0);
  };

  const pastDueInstallmentsAed = computed(() =>
    breakdownInstallments.value.reduce((sum, entry) => {
      if (!isDatePaid(entry?.date)) return sum;
      return sum + installmentToAmount(entry);
    }, 0),
  );

  const scheduledInstallmentsAed = computed(() =>
    breakdownInstallments.value.reduce((sum, entry) => sum + installmentToAmount(entry), 0),
  );

  const paidPercentOfOp = computed(() =>
    (pastDueInstallmentsAed.value / Math.max(1, originalPriceNum.value)) * 100,
  );
  const getNocFixedAmount = computed(() => {
    if (nocFixedAmount !== null && typeof nocFixedAmount === 'object' && 'value' in nocFixedAmount) {
      return Number(nocFixedAmount.value || 0);
    }
    return Number(form.value.noc_fixed_amount || 0);
  });
   const nocPercentOfOp = computed(() => {
    const fixedAmount = getNocFixedAmount.value;
    const op = originalPriceNum.value;
    if (op <= 0 || fixedAmount <= 0) return 0;
    return (fixedAmount / op) * 100;
  });
const nocFixedAmount = computed(() => {
    return Number(form.value.noc_fixed_amount || 0);
  });
const nocRequiredAed = computed(() => {
    return getNocFixedAmount.value;
  });
  const nocRequirementMet = computed(() => {
    const required = nocRequiredAed.value;
    if (required <= 0) return true;
    return scheduledInstallmentsAed.value >= required - 0.01;
  });

  const nocRemainingAed = computed(() => {
    const required = nocRequiredAed.value;
    if (required <= 0) return 0;
    return Math.max(0, required - scheduledInstallmentsAed.value);
  });

  const nocProgressBarPct = computed(() => {
    const required = nocRequiredAed.value;
    if (required <= 0) return 100;
    return Math.min(100, (scheduledInstallmentsAed.value / required) * 100);
  });

  const nocProgressPaidLabel = computed(() => {
    const required = nocRequiredAed.value;
    if (required <= 0) return '—';
    return `${formatAed(scheduledInstallmentsAed.value)} / ${formatAed(required)}`;
  });

  const nocRequirementWarningActive = computed(
    () => isUnderConstruction.value && nocRequiredAed.value > 0 && !nocRequirementMet.value,
  );

  const breakdownGrandTotal = computed(
    () => scheduledInstallmentsAed.value + handoverAmountForm.value + premiumAmountForm.value,
  );

  const breakdownMatchesSelling = computed(() => {
    if (!isUnderConstruction.value) return true;
    const sp = sellingPriceNum.value;
    if (!(sp > 0)) return false;
    return Math.abs(breakdownGrandTotal.value - sp) < BREAKDOWN_SELLING_TOLERANCE_AED;
  });

  const breakdownSellingDelta = computed(() => breakdownGrandTotal.value - sellingPriceNum.value);

  const breakdownSellingDeltaMessage = computed(() => {
    if (!isUnderConstruction.value || !(sellingPriceNum.value > 0)) return '';
    const d = breakdownSellingDelta.value;
    if (Math.abs(d) < BREAKDOWN_SELLING_TOLERANCE_AED) return '';
    if (d < -BREAKDOWN_SELLING_TOLERANCE_AED) {
      return `AED ${Math.round(Math.abs(d)).toLocaleString('en-AE')} remaining unallocated`;
    }
    return `Breakdown exceeds selling price by AED ${Math.round(d).toLocaleString('en-AE')}`;
  });

  const breakdownSellingPriceMismatchActive = computed(
    () => isUnderConstruction.value && sellingPriceNum.value > 0 && !breakdownMatchesSelling.value,
  );

  const hasMixedInstallmentTypes = computed(() => {
    if (!breakdownInstallments.value.length) return false;
    const first = breakdownInstallments.value[0]?.type;
    return breakdownInstallments.value.some((e) => e?.type !== first);
  });

  const mixedInstallmentTypesError = computed(() =>
    hasMixedInstallmentTypes.value ? MIXED_INSTALLMENT_TYPES_MSG : '',
  );

  const usesOnlyPercentageInstallments = computed(
    () =>
      breakdownInstallments.value.length > 0 &&
      breakdownInstallments.value.every((e) => e?.type === 'percentage'),
  );

  const totalInstallmentPercent = computed(() =>
    breakdownInstallments.value.reduce((sum, entry) => {
      if (entry?.type !== 'percentage') return sum;
      return sum + Number(entry.value || 0);
    }, 0),
  );

  const installmentPercentMatchesPlan = computed(() => {
    if (!usesOnlyPercentageInstallments.value) return true;
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

  const handoverAmountNegativeBlock = computed(
    () => isUnderConstruction.value && handoverAmountForm.value < -0.01,
  );

  const getHandoverDateError = () => {
    if (!isUnderConstruction.value) return '';
    const raw = form.value.handover_date;
    if (!raw) return '';
    const handoverDay = startOfDay(raw);
    if (Number.isNaN(handoverDay.getTime())) return '';
    const today = startOfDay(new Date());
    if (handoverDay.getTime() < today.getTime()) return 'Handover date cannot be in the past.';
    let maxInstTs = null;
    for (const entry of breakdownInstallments.value) {
      if (!entry?.date) continue;
      const d = startOfDay(entry.date);
      if (Number.isNaN(d.getTime())) continue;
      const t = d.getTime();
      if (maxInstTs === null || t > maxInstTs) maxInstTs = t;
    }
    if (maxInstTs !== null && handoverDay.getTime() <= maxInstTs) {
      return 'Handover date must be after all installments.';
    }
    return '';
  };

  const paymentHandoverDateError = computed(() => getHandoverDateError());

  const resolveInstallmentRowStatus = (entry, cumulativeAfter) => {
    if (isDatePaid(entry?.date)) return STATUS_PAID;
    if (nocPercentOfOp.value <= 0) return STATUS_UPCOMING;
    if (cumulativeAfter <= nocRequiredAed.value + 0.01) return STATUS_DUE_ON_TRANSFER;
    return STATUS_UPCOMING;
  };

  const paymentBreakdownRows = computed(() => {
    const rows = [];
    let id = 1;
    const sorted = breakdownInstallments.value
      .slice()
      .sort((a, b) => new Date(a.date) - new Date(b.date));
    let cumulative = 0;
    sorted.forEach((entry) => {
      const amount = installmentToAmount(entry);
      cumulative += amount;
      rows.push({
        id: entry.id,
        entryId: entry.id,
        type: 'Installment',
        percentage: ((amount / Math.max(1, originalPriceNum.value)) * 100).toFixed(2),
        amount,
        date: entry.date,
        status: resolveInstallmentRowStatus(entry, cumulative),
      });
    });

    rows.push({
      id: `premium-${id++}`,
      entryId: null,
      type: 'Premium',
      percentage: '',
      amount: premiumAmountForm.value,
      date: '',
      status: premiumIsNegative.value ? SELLING_BELOW_OP_WARN_MSG : STATUS_DUE_ON_TRANSFER,
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

  const breakdownRowStatusClass = (status) => {
    if (status === STATUS_PAID) return 'bg-success-subtle text-success-emphasis';
    if (status === SELLING_BELOW_OP_WARN_MSG) return 'bg-danger-subtle text-danger-emphasis';
    if (status === STATUS_DUE_ON_TRANSFER || status === 'Due to transfer') {
      return 'bg-warning-subtle text-warning-emphasis';
    }
    return 'bg-primary-subtle text-primary-emphasis';
  };

  const publishPaymentBreakdownBlocked = computed(() => {
    if (!isUnderConstruction.value) return false;
    if (paymentPlanMissingForPublish.value) return true;
    if (form.value.payment_plans != null && form.value.payment_plans !== '' && !isPaymentPlanSelectionParseValid.value) {
      return true;
    }
    if (hasMixedInstallmentTypes.value) return true;
    if (percentageInstallmentPlanMismatchError.value) return true;
    if (breakdownSellingPriceMismatchActive.value) return true;
    if (handoverAmountNegativeBlock.value) return true;
    if (getHandoverDateError()) return true;
    return false;
  });

  const publishPaymentBreakdownBlockTitle = computed(() => {
    if (!publishPaymentBreakdownBlocked.value) return '';
    if (paymentPlanMissingForPublish.value) return 'Select a payment plan.';
    if (!isPaymentPlanSelectionParseValid.value) return PAYMENT_PLAN_PARSE_ERROR;
    if (hasMixedInstallmentTypes.value) return MIXED_INSTALLMENT_TYPES_MSG;
    if (percentageInstallmentPlanMismatchError.value) return percentageInstallmentPlanMismatchError.value;
    if (breakdownSellingPriceMismatchActive.value) return 'Payment breakdown total does not match selling price.';
    if (handoverAmountNegativeBlock.value) return 'Handover amount is invalid.';
    if (getHandoverDateError()) return getHandoverDateError();
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
      rows.push({ id: 'mix', level: 'ok', icon: '✓', text: 'Installment rows use a single type.' });
    }

    if (usesOnlyPercentageInstallments.value && breakdownInstallments.value.length) {
      rows.push({
        id: 'pctplan',
        level: installmentPercentMatchesPlan.value ? 'ok' : 'err',
        icon: installmentPercentMatchesPlan.value ? '✓' : '✕',
        text: installmentPercentMatchesPlan.value
          ? `Installment percentages match payment plan (${initialPercentForm.value.toFixed(0)}%).`
          : percentageInstallmentPlanMismatchError.value,
      });
    }

    if (breakdownMatchesSelling.value) {
      rows.push({ id: 'grand', level: 'ok', icon: '✓', text: 'Breakdown total matches selling price.' });
    } else if (sellingPriceNum.value > 0) {
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
      rows.push({ id: 'noc', level: 'ok', icon: '✓', text: 'NOC transfer requirement covered.' });
    } else {
      rows.push({
        id: 'noc',
        level: 'err',
        icon: '✕',
        text: `AED ${Math.round(nocRemainingAed.value).toLocaleString('en-AE')} remaining to satisfy NOC transfer requirement.`,
      });
    }

    if (sellingBelowOriginalActive.value) {
      rows.push({ id: 'svop', level: 'warn', icon: '⚠', text: SELLING_BELOW_OP_WARN_MSG });
    } else if (originalContractPriceNum.value > 0) {
      rows.push({ id: 'svop', level: 'ok', icon: '✓', text: 'Selling price is at or above original price.' });
    }

    const he = paymentHandoverDateError.value;
    if (!form.value.handover_date) {
      rows.push({ id: 'ho', level: 'warn', icon: '⚠', text: 'Set handover date.' });
    } else if (he) {
      rows.push({ id: 'ho', level: 'err', icon: '✕', text: he });
    } else {
      rows.push({ id: 'ho', level: 'ok', icon: '✓', text: 'Handover date is valid.' });
    }

    if (premiumIsNegative.value) {
      rows.push({ id: 'prem', level: 'warn', icon: '⚠', text: 'Selling price below original price (negative premium).' });
    }

    return rows;
  });

  return {
    selectedPaymentPlanOption,
    isPaymentPlanSelectionParseValid,
    paymentPlanFieldInvalid,
    paymentPlanFieldError,
    paymentPlanMissingForPublish,
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
    nocFixedAmount,
    pastDueInstallmentsAed,
    scheduledInstallmentsAed,
    paidAmountForm: pastDueInstallmentsAed,
    //  nocFixedAmount: getNocFixedAmount,
    paidPercentOfOp,
    nocPercentOfOp,
    nocRequiredAed,
    nocRequirementMet,
    nocRemainingAed,
    nocProgressBarPct,
    nocProgressPaidLabel,
    nocRequirementWarningActive,
    breakdownGrandTotal,
    breakdownMatchesSelling,
    breakdownSellingDeltaMessage,
    breakdownSellingPriceMismatchActive,
    mixedInstallmentTypesError,
    percentageInstallmentPlanMismatchError,
    handoverAmountNegativeBlock,
    paymentHandoverDateError,
    paymentBreakdownRows,
    breakdownRowStatusClass,
    publishPaymentBreakdownBlocked,
    publishPaymentBreakdownBlockTitle,
    paymentBreakdownValidationSummary,
    formatAed,
    formatDateShort: (dateLike) => {
      const date = new Date(dateLike);
      if (Number.isNaN(date.getTime())) return '—';
      return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    },
  };
}

export async function confirmSellingBelowOriginalIfNeeded(originalContractPriceNum, sellingPriceNum) {
  if (!(originalContractPriceNum > 0 && sellingPriceNum > 0 && sellingPriceNum + 0.01 < originalContractPriceNum)) {
    return true;
  }
  const result = await Swal.fire({
    icon: 'warning',
    title: 'Confirm selling price',
    text: SELLING_BELOW_OP_CONFIRM_MSG,
    showCancelButton: true,
    confirmButtonText: 'Continue',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#0d6efd',
  });
  return result.isConfirmed === true;
}
