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
  dealCostSettings = null, // ✅ استقبل الإعدادات
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

  // ✅ دالة لإضافة التكاليف الافتراضية
const addDefaultDealCosts = () => {
  console.log('📝 Adding default deal costs. Settings:', dealCostSettings.value);
  
  // ✅ استخدام المفاتيح النصية
  const fees = {
    dariAdminFee: dealCostSettings.value['dari_admin_fee'] || dealCostSettings.value['1'] || 0,
    adgmAdminFee: dealCostSettings.value['adgm_admin_fee'] || dealCostSettings.value['2'] || 0,
    agencyFee: dealCostSettings.value['agency_fee'] || dealCostSettings.value['3'] || 2 // ✅ 2% افتراضي
  };
  
  // ✅ Agency Fee (2% من سعر البيع)
  const agencyFeeValue = Number(fees.agencyFee) || 2; // 2% افتراضي
  console.log(`✅ Agency Fee: ${agencyFeeValue}% of Selling Price`);
  
  const existingAgency = assignmentExpenseLines.value.find(
    line => line.label === 'Agency Fee' && line.isDefault
  );
  if (!existingAgency) {
    assignmentExpenseLines.value.push({
      id: Date.now() + 3,
      label: 'Agency Fee',
      calcType: 'percentage', // ✅ نسبة مئوية
      base: 'sp', // ✅ من سعر البيع (Selling Price)
      value: agencyFeeValue, // ✅ 2% أو القيمة من الإعدادات
      vatEnabled: false,
      isDefault: true,
      isReadonly: true
    });
    console.log('✅ Agency Fee added');
  } else {
    existingAgency.value = agencyFeeValue;
    console.log('✅ Agency Fee updated');
  }
  
  // Dari Admin Fee
  if (Number(fees.dariAdminFee) > 0) {
    console.log(`✅ Found Dari Admin Fee: ${fees.dariAdminFee}`);
    const existing = assignmentExpenseLines.value.find(
      line => line.label === 'Dari Admin Fee' && line.isDefault
    );
    if (!existing) {
      assignmentExpenseLines.value.push({
        id: Date.now() + 1,
        label: 'Dari Admin Fee',
        calcType: 'fixed',
        base: null,
        value: Number(fees.dariAdminFee),
        vatEnabled: false,
        isDefault: true,
        isReadonly: true
      });
      console.log('✅ Dari Admin Fee added');
    }
  }
  
  // ADGM Admin Fee
  if (Number(fees.adgmAdminFee) > 0) {
    console.log(`✅ Found ADGM Admin Fee: ${fees.adgmAdminFee}`);
    const existing = assignmentExpenseLines.value.find(
      line => line.label === 'ADGM Admin Fee' && line.isDefault
    );
    if (!existing) {
      assignmentExpenseLines.value.push({
        id: Date.now() + 2,
        label: 'ADGM Admin Fee',
        calcType: 'fixed',
        base: null,
        value: Number(fees.adgmAdminFee),
        vatEnabled: false,
        isDefault: true,
        isReadonly: true
      });
      console.log('✅ ADGM Admin Fee added');
    }
  }
  
  console.log('📊 Current assignmentExpenseLines:', assignmentExpenseLines.value);
};
const loadAssignmentExpenseLines = (raw) => {
  const arr = parseAssignmentExpenseLines(raw);
  
  // Clear existing lines first
  assignmentExpenseLines.value = [];
  
  if (arr.length > 0) {
    // Add server data
    arr.forEach((line, i) => {
      assignmentExpenseLines.value.push({
        id: line.id != null ? Number(line.id) : Date.now() + i,
        label: String(line.label || ''),
        calcType: line.calcType === 'fixed' ? 'fixed' : 'percentage',
        base: line.base || 'op',
        value: Number(line.value || 0),
        vatEnabled: !!line.vatEnabled,
        isDefault: line.label === 'Dari Admin Fee' || line.label === 'ADGM Admin Fee',
        isReadonly: true,
      });
    });
  }
  
  // Always add default costs after loading server data
  addDefaultDealCosts();
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
      isDefault: false,
      isReadonly: true
    });
    resetAssignmentExpenseDraft();
    return true;
  };

  const removeAssignmentExpenseLine = (id) => {
    const line = assignmentExpenseLines.value.find(l => l.id === id);
    
    // ✅ منع حذف التكاليف الافتراضية
    if (line && line.isDefault) {
      // يمكنك عرض رسالة أو تجاهل الحذف
      console.warn('⚠️ Cannot remove default cost line:', line.label);
      return;
    }
    
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
    addDefaultDealCosts, // ✅ تصدير الدالة للاستخدام الخارجي
  };
}