import { ref, computed, watch, unref } from 'vue';
import { withDefaultDealCostLines, isDefaultDealCostLabel } from '@/utils/dealCostRules';

const UAE_ASSIGNMENT_VAT_RATE = 0.05;

export const assignmentExpenseTypeOptions = [
  { label: 'Percentage (%)', value: 'percentage' },
  { label: 'Fixed (AED)', value: 'fixed' },
];

export const assignmentExpenseBaseOptions = [
  { label: 'Original Price ', value: 'op' },
  { label: 'Sale Price', value: 'sp' },
  { label: 'Premium ', value: 'premium' },
];

export function parseAssignmentExpenseLines(raw) {
  if (raw == null || raw === '') return [];
  if (Array.isArray(raw)) return raw;
  if (typeof raw === 'string') {
    try {
      const parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return [];
    }
  }
  return [];
}

/**
 * Cost lines of a listing. The read-only default lines (agency, transfer/DLD,
 * admin/trustee fee) come from utils/dealCostRules and are rebuilt whenever the
 * emirate (area), completion status or selling price changes.
 */
export function useListingAssignmentExpenses({
  originalPriceNum,
  sellingPriceNum,
  premiumAmountForm,
  formatAed,
  dealCostSettings = null,
  area = null,
  completionStatus = null,
}) {
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

  const parseNumber = (val) => {
    if (val == null) return 0;
    if (typeof val === 'number') return val;
    const cleaned = String(val).replace(/,/g, '').trim();
    return Number(cleaned) || 0;
  };

  const assignmentExpenseLineAmount = (line) => {
    if (!line) return 0;
    const baseAmount = getAssignmentExpenseBaseAmount(line.base);
    let value = parseNumber(line.value);
    if (line.calcType !== 'percentage') return Number.isFinite(value) ? value : 0;
    if (!Number.isFinite(baseAmount) || !Number.isFinite(value)) return 0;
    // Percentages stored ×100 (e.g. 200 for 2%).
    if (value > 100) value /= 100;
    return (baseAmount * value) / 100;
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

  const resetAssignmentExpenseDraft = () => {
    assignmentExpenseDraft.value = {
      label: '',
      calcType: 'percentage',
      base: 'op',
      value: null,
      vatEnabled: false,
    };
  };

  /** (Re)build the default lines for the current emirate / status / selling price. */
  const addDefaultDealCosts = () => {
    assignmentExpenseLines.value = withDefaultDealCostLines(assignmentExpenseLines.value, {
      settings: unref(dealCostSettings) || {},
      area: unref(area),
      completionStatus: unref(completionStatus),
      sellingPrice: unref(sellingPriceNum),
    });
  };

  watch(
    () => [unref(area), unref(completionStatus), unref(sellingPriceNum)],
    () => addDefaultDealCosts(),
  );

  const loadAssignmentExpenseLines = (raw) => {
    assignmentExpenseLines.value = parseAssignmentExpenseLines(raw)
      .filter((line) => !isDefaultDealCostLabel(line?.label))
      .map((line, i) => ({
        id: line.id != null ? Number(line.id) : Date.now() + i,
        label: String(line.label || ''),
        calcType: line.calcType === 'fixed' ? 'fixed' : 'percentage',
        base: line.base || 'op',
        value: parseNumber(line.value),
        vatEnabled: !!line.vatEnabled,
        isDefault: false,
        isReadonly: false,
      }));

    addDefaultDealCosts();
  };

  const addAssignmentExpenseLine = (onError) => {
    const label = String(assignmentExpenseDraft.value.label || '').trim();
    if (!label) {
      onError?.('Enter a label for the cost line');
      return false;
    }
    const value = parseNumber(assignmentExpenseDraft.value.value);
    if (!Number.isFinite(value) || value <= 0) {
      onError?.('Enter a valid value greater than zero');
      return false;
    }
    assignmentExpenseLines.value.push({
      id: Date.now() + Math.floor(Math.random() * 1000),
      label,
      calcType: assignmentExpenseDraft.value.calcType,
      base: assignmentExpenseDraft.value.base || 'op',
      value,
      vatEnabled: !!assignmentExpenseDraft.value.vatEnabled,
      isDefault: false,
      isReadonly: false,
    });
    resetAssignmentExpenseDraft();
    return true;
  };

  const removeAssignmentExpenseLine = (id) => {
    const line = assignmentExpenseLines.value.find(l => l.id === id);
    if (line && line.isDefault) return;
    assignmentExpenseLines.value = assignmentExpenseLines.value.filter((l) => l.id !== id);
  };

  return {
    assignmentExpenseLines,
    assignmentExpenseDraft,
    assignmentExpenseLineAmount,
    assignmentExpenseLineVat,
    assignmentExpenseLineTotal,
    assignmentExpensesSubtotal,
    assignmentExpensesTotalVat,
    assignmentExpensesGrandTotal,
    resetAssignmentExpenseDraft,
    loadAssignmentExpenseLines,
    addAssignmentExpenseLine,
    removeAssignmentExpenseLine,
    addDefaultDealCosts,
  };
}
