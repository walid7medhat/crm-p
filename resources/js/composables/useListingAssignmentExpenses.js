import { ref, computed } from 'vue';

const UAE_ASSIGNMENT_VAT_RATE = 0.05;

export const assignmentExpenseTypeOptions = [
  { label: 'Percentage (%)', value: 'percentage' },
  { label: 'Fixed (AED)', value: 'fixed' },
];

export const assignmentExpenseBaseOptions = [
  { label: 'Original Price (OP)', value: 'op' },
  { label: 'Sale Price (SP)', value: 'sp' },
  { label: 'Premium (SP − OP)', value: 'premium' },
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

export function useListingAssignmentExpenses({
  originalPriceNum,
  sellingPriceNum,
  premiumAmountForm,
  formatAed,
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

  const resetAssignmentExpenseDraft = () => {
    assignmentExpenseDraft.value = {
      label: '',
      calcType: 'percentage',
      base: 'op',
      value: null,
      vatEnabled: false,
    };
  };

  const loadAssignmentExpenseLines = (raw) => {
    const arr = parseAssignmentExpenseLines(raw);
    assignmentExpenseLines.value = arr.map((line, i) => ({
      id: line.id != null ? Number(line.id) : Date.now() + i,
      label: String(line.label || ''),
      calcType: line.calcType === 'fixed' ? 'fixed' : 'percentage',
      base: line.base || 'op',
      value: Number(line.value || 0),
      vatEnabled: !!line.vatEnabled,
    }));
  };

  const addAssignmentExpenseLine = (onError) => {
    const label = String(assignmentExpenseDraft.value.label || '').trim();
    if (!label) {
      onError?.('Enter a label for the cost line');
      return false;
    }
    const value = Number(assignmentExpenseDraft.value.value);
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
    });
    resetAssignmentExpenseDraft();
    return true;
  };

  const removeAssignmentExpenseLine = (id) => {
    assignmentExpenseLines.value = assignmentExpenseLines.value.filter((line) => line.id !== id);
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
  };
}
