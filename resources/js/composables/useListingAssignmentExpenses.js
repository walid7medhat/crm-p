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
  dealCostSettings = null, 
  area = null,
}) {
  const assignmentExpenseLines = ref([]);
  const assignmentExpenseDraft = ref({
    label: '',
    calcType: 'percentage',
    base: 'op',
    value: null,
    vatEnabled: false,
  });

  const getAdminFeeTypeFromArea = (areaData) => {
    if (!areaData) return 'dari';
    
    if (areaData.is_adgm !== undefined) {
      return areaData.is_adgm ? 'adgm' : 'dari';
    }
    
    if (areaData.all_names && Array.isArray(areaData.all_names)) {
      const adgmTerms = ['maryah island', 'reem island'];
      const isAdgm = areaData.all_names.some(name => 
        adgmTerms.some(term => 
          String(name).toLowerCase().includes(term.toLowerCase().trim())
        )
      );
      return isAdgm ? 'adgm' : 'dari';
    }
    
    if (areaData.hierarchy && Array.isArray(areaData.hierarchy)) {
      const adgmTerms = ['maryah island', 'reem island'];
      const isAdgm = areaData.hierarchy.some(h => 
        adgmTerms.some(term => 
          String(h.name || '').toLowerCase().includes(term.toLowerCase().trim())
        )
      );
      return isAdgm ? 'adgm' : 'dari';
    }
    
    const areaName = String(areaData.name || areaData.area_title || areaData.title || '').toLowerCase();
    if (areaName.includes('maryah') || areaName.includes('reem')) {
      return 'adgm';
    }
    
    return 'dari';
  };

  const getAssignmentExpenseBaseAmount = (base) => {
    if (base === 'op') return originalPriceNum.value;
    if (base === 'sp') return sellingPriceNum.value;
    if (base === 'premium') return premiumAmountForm.value;
    return 0;
  };

  // ✅ دالة مساعدة لتنظيف الأرقام
  const parseNumber = (val) => {
    if (val == null) return 0;
    if (typeof val === 'number') return val;
    const cleaned = String(val).replace(/,/g, '').trim();
    return Number(cleaned) || 0;
  };

  // ✅ دالة حساب المبلغ مع التحقق من القيم
  // ✅ دالة حساب المبلغ مع التحقق من القيم
const assignmentExpenseLineAmount = (line) => {
  if (!line) return 0;

  console.log('========================================');
  console.log('🧮 assignmentExpenseLineAmount CALLED');
  console.log('📋 Line:', JSON.stringify(line, null, 2));
  console.log('📋 Line label:', line.label);
  console.log('📋 Line value (raw):', line.value);
  console.log('📋 Line calcType:', line.calcType);
  console.log('📋 Line base:', line.base);

  // 🔍 تحقق من قيم المصدر
  console.log('🔍 sellingPriceNum.value:', sellingPriceNum.value);
  console.log('🔍 originalPriceNum.value:', originalPriceNum.value);
  console.log('🔍 premiumAmountForm.value:', premiumAmountForm.value);

  const baseAmount = getAssignmentExpenseBaseAmount(line.base);
  console.log('💰 Base amount from getAssignmentExpenseBaseAmount:', baseAmount);
  console.log('💰 Base amount type:', typeof baseAmount);

  let value = parseNumber(line.value);
  console.log('📊 Value after parseNumber:', value);
  console.log('📊 Value type after parseNumber:', typeof value);

  if (!Number.isFinite(baseAmount) || !Number.isFinite(value)) {
    console.log('❌ Invalid numbers - returning 0');
    return 0;
  }

  if (line.calcType === 'percentage') {
    // ✅ إذا كانت القيمة كبيرة جداً (أكثر من 100)، فهي مضروبة في 100
    if (value > 100) {
      console.log(`⚠️ Value ${value} > 100, dividing by 100`);
      value = value / 100;
      console.log(`✅ New value: ${value}`);
    }
    
    console.log(`📐 Calculating: (${baseAmount} * ${value}) / 100`);
    const result = (baseAmount * value) / 100;
    console.log(`✅ Result: ${result}`);
    return result;
  }

  console.log(`💰 Fixed amount: ${value}`);
  return value;
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
    console.log('📝 Adding default deal costs. Settings:', dealCostSettings?.value);
    console.log('📍 Area data:', area?.value);

     console.log('🔍🔍🔍 SELLING PRICE NUM:', sellingPriceNum.value);
  console.log('🔍🔍🔍 ORIGINAL PRICE NUM:', originalPriceNum.value);
  console.log('🔍🔍🔍 PREMIUM AMOUNT:', premiumAmountForm.value);
    
    // ✅ قراءة القيم مع التصحيح التلقائي
    const fees = {
      dariAdminFee: parseNumber(dealCostSettings?.value?.dari_admin_fee || dealCostSettings?.value?.['1'] || 0),
      adgmAdminFee: parseNumber(dealCostSettings?.value?.adgm_admin_fee || dealCostSettings?.value?.['2'] || 0),
      agencyFee: parseNumber(dealCostSettings?.value?.agency_fee || dealCostSettings?.value?.['3'] || 2),
      transferFee: parseNumber(dealCostSettings?.value?.transfer_fee || dealCostSettings?.value?.['4'] || 2),
    };

    // ✅ تصحيح النسب المئوية إذا كانت أكثر من 100
    let agencyFeeValue = fees.agencyFee;
    let transferFeeValue = fees.transferFee;
    
    if (agencyFeeValue > 100) {
      agencyFeeValue = agencyFeeValue / 100;
      console.log(`⚠️ Agency Fee corrected from ${fees.agencyFee} to ${agencyFeeValue}`);
    }
    
    if (transferFeeValue > 100) {
      transferFeeValue = transferFeeValue / 100;
      console.log(`⚠️ Transfer Fee corrected from ${fees.transferFee} to ${transferFeeValue}`);
    }

    console.log(`✅ Agency Fee: ${agencyFeeValue}% of Selling Price`);
    console.log(`✅ Transfer Fee: ${transferFeeValue}% of Selling Price`);
    
    // ✅ 1. Agency Fee
    const existingAgency = assignmentExpenseLines.value.find(
      line => (line.label === 'Agency Fees' || line.label === 'Agency Fee') && line.isDefault
    );
    
    if (!existingAgency) {
      assignmentExpenseLines.value.push({
        id: Date.now() + 3,
        label: 'Agency Fees',
        calcType: 'percentage',
        base: 'sp',
        value: agencyFeeValue,
        vatEnabled: true,
        isDefault: true,
        isReadonly: true,
        isAgency: true,
      });
      console.log('✅ Agency Fee added');
    } else {
      existingAgency.value = agencyFeeValue;
      console.log('✅ Agency Fee updated');
    }
    
    // ✅ 2. Transfer Fee
    const existingTransfer = assignmentExpenseLines.value.find(
      line => (line.label === 'Transfer Fees' || line.label === 'Transfer Fee' ) && line.isDefault
    );
    
    if (!existingTransfer) {
      assignmentExpenseLines.value.push({
        id: Date.now() + 4,
        label: 'Transfer Fees',
        calcType: 'percentage',
        base: 'sp',
        value: transferFeeValue,
        vatEnabled: false,
        isDefault: true,
        isReadonly: true,
        isAgency: true,
      });
      console.log('✅ Transfer Fee added');
    } else {
      existingTransfer.value = transferFeeValue;
      console.log('✅ Transfer Fee updated');
    }

    // ✅ 3. Admin Fee
    const areaData = area?.value;
    const feeType = getAdminFeeTypeFromArea(areaData);
    console.log(`📍 Area: "${areaData?.name || areaData?.area_title || 'Unknown'}" → Admin fee type: ${feeType}`);
    
    // إزالة التكاليف الإدارية القديمة
    const adminFeeIndices = [];
    assignmentExpenseLines.value.forEach((line, index) => {
      if ((line.label === 'Dari Admin Fees' || line.label === 'ADGM Admin Fees') && line.isDefault) {
        adminFeeIndices.push(index);
      }
    });
    adminFeeIndices.reverse().forEach(index => {
      assignmentExpenseLines.value.splice(index, 1);
    });

    // إضافة التكلفة الإدارية المناسبة
    if (feeType === 'adgm') {
      if (fees.adgmAdminFee > 0) {
        const existingAdgm = assignmentExpenseLines.value.find(
          line => line.label === 'ADGM Admin Fees' && line.isDefault
        );
        if (!existingAdgm) {
          assignmentExpenseLines.value.push({
            id: Date.now() + 2,
            label: 'ADGM Admin Fees',
            calcType: 'fixed',
            base: null,
            value: fees.adgmAdminFee,
            vatEnabled: false,
            isDefault: true,
            isReadonly: true,
            isAdminFee: true,
          });
          console.log(`✅ ADGM Admin Fee added: ${fees.adgmAdminFee} AED`);
        } else {
          existingAdgm.value = fees.adgmAdminFee;
        }
      }
    } else {
      if (fees.dariAdminFee > 0) {
        const existingDari = assignmentExpenseLines.value.find(
          line => line.label === 'Dari Admin Fees' && line.isDefault
        );
        if (!existingDari) {
          assignmentExpenseLines.value.push({
            id: Date.now() + 1,
            label: 'Dari Admin Fees',
            calcType: 'fixed',
            base: null,
            value: fees.dariAdminFee,
            vatEnabled: false,
            isDefault: true,
            isReadonly: true,
            isAdminFee: true,
          });
          console.log(`✅ Dari Admin Fee added: ${fees.dariAdminFee} AED`);
        } else {
          existingDari.value = fees.dariAdminFee;
        }
      }
    }
    
    console.log('📊 Current assignmentExpenseLines:', assignmentExpenseLines.value);
  };

  const loadAssignmentExpenseLines = (raw) => {
    const arr = parseAssignmentExpenseLines(raw);
    
    assignmentExpenseLines.value = [];
    
    if (arr.length > 0) {
      arr.forEach((line, i) => {
        const isDefault = line.label === 'Dari Admin Fees' || line.label === 'ADGM Admin Fees' || line.label === 'Agency Fees' || line.label === 'Transfer Fees'  || line.label === 'Agency Fee' || line.label === 'Transfer Fee';
        if (!isDefault) {
          assignmentExpenseLines.value.push({
            id: line.id != null ? Number(line.id) : Date.now() + i,
            label: String(line.label || ''),
            calcType: line.calcType === 'fixed' ? 'fixed' : 'percentage',
            base: line.base || 'op',
            value: parseNumber(line.value),
            vatEnabled: line.label === 'Transfer Fee' ? true : !!line.vatEnabled,
            isDefault: false,
            isReadonly: false,
          });
        }
      });
    }
    
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
    
    if (line && line.isDefault) {
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
    addDefaultDealCosts,
  };
}