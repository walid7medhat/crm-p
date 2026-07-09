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
  area = null, // ✅ المنطقة من الـ API
}) {
  const assignmentExpenseLines = ref([]);
  const assignmentExpenseDraft = ref({
    label: '',
    calcType: 'percentage',
    base: 'op',
    value: null,
    vatEnabled: false,
  });

  // ✅ دالة لتحديد نوع التكلفة بناءً على المنطقة
  const getAdminFeeTypeFromArea = (areaData) => {
    if (!areaData) return 'dari';
    
    // استخدام القيمة من الـ API (is_adgm)
    if (areaData.is_adgm !== undefined) {
      return areaData.is_adgm ? 'adgm' : 'dari';
    }
    
    // استخدام all_names من الـ API
    if (areaData.all_names && Array.isArray(areaData.all_names)) {
      const adgmTerms = ['maryah island', 'reem island'];
      const isAdgm = areaData.all_names.some(name => 
        adgmTerms.some(term => 
          String(name).toLowerCase().includes(term.toLowerCase().trim())
        )
      );
      return isAdgm ? 'adgm' : 'dari';
    }
    
    // استخدام hierarchy من الـ API
    if (areaData.hierarchy && Array.isArray(areaData.hierarchy)) {
      const adgmTerms = ['maryah island', 'reem island'];
      const isAdgm = areaData.hierarchy.some(h => 
        adgmTerms.some(term => 
          String(h.name || '').toLowerCase().includes(term.toLowerCase().trim())
        )
      );
      return isAdgm ? 'adgm' : 'dari';
    }
    
    // Fallback: التحقق من الاسم
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
    console.log('📝 Adding default deal costs. Settings:', dealCostSettings?.value);
    console.log('📍 Area data:', area?.value);
    
    // ✅ استخدام المفاتيح النصية
    const fees = {
      dariAdminFee: Number(dealCostSettings?.value?.dari_admin_fee || dealCostSettings?.value?.['1'] || 0),
      adgmAdminFee: Number(dealCostSettings?.value?.adgm_admin_fee || dealCostSettings?.value?.['2'] || 0),
      agencyFee: Number(dealCostSettings?.value?.agency_fee || dealCostSettings?.value?.['3'] || 2),
      transferFee: Number(dealCostSettings?.value?.transfer_fee || dealCostSettings?.value?.['4'] || 2),
    };
    
    // ✅ 1. Agency Fee (2% من سعر البيع) - تضاف دائماً
    const agencyFeeValue = fees.agencyFee || 2;
    const transferFeeValue = fees.transferFee || 2;
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
        isReadonly: true,
        isAgency: true,
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
        isReadonly: true,
        isAgency: true,
      });
      console.log('✅ Transfer Fee added');
    } else {
      existingAgency.value = transferFeeValue;
      console.log('✅ Transfer Fee updated');
    }

    // ✅ 2. تحديد نوع Admin Fee بناءً على المنطقة
    const areaData = area?.value;
    const feeType = getAdminFeeTypeFromArea(areaData);
    console.log(`📍 Area: "${areaData?.name || areaData?.area_title || 'Unknown'}" → Admin fee type: ${feeType}`);
    
    // ✅ إزالة جميع التكاليف الإدارية القديمة
    const adminFeeIndices = [];
    assignmentExpenseLines.value.forEach((line, index) => {
      if ((line.label === 'Dari Admin Fee' || line.label === 'ADGM Admin Fee') && line.isDefault) {
        adminFeeIndices.push(index);
      }
    });
    // حذف من الأخر إلى الأول لتجنب مشاكل الترتيب
    adminFeeIndices.reverse().forEach(index => {
      assignmentExpenseLines.value.splice(index, 1);
      console.log(`🗑️ Removed old admin fee at index ${index}`);
    });

    // ✅ 3. إضافة التكلفة الإدارية المناسبة
    if (feeType === 'adgm') {
      // ADGM Admin Fee - لـ Maryah Island و Reem Island
      if (fees.adgmAdminFee > 0) {
        assignmentExpenseLines.value.push({
          id: Date.now() + 2,
          label: 'ADGM Admin Fee',
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
        console.log('⚠️ ADGM Admin Fee is 0, skipping');
      }
    } else {
      // Dari Admin Fee - لجميع المناطق الأخرى
      if (fees.dariAdminFee > 0) {
        assignmentExpenseLines.value.push({
          id: Date.now() + 1,
          label: 'Dari Admin Fee',
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
        console.log('⚠️ Dari Admin Fee is 0, skipping');
      }
    }
    
    console.log('📊 Current assignmentExpenseLines:', assignmentExpenseLines.value);
  };

  const loadAssignmentExpenseLines = (raw) => {
    const arr = parseAssignmentExpenseLines(raw);
    
    // Clear existing lines first
    assignmentExpenseLines.value = [];
    
    if (arr.length > 0) {
      // Add server data (non-default lines)
      arr.forEach((line, i) => {
        // Don't add default lines from server, they will be re-added by addDefaultDealCosts
        const isDefault = line.label === 'Dari Admin Fee' || line.label === 'ADGM Admin Fee' || line.label === 'Agency Fee';
        if (!isDefault) {
          assignmentExpenseLines.value.push({
            id: line.id != null ? Number(line.id) : Date.now() + i,
            label: String(line.label || ''),
            calcType: line.calcType === 'fixed' ? 'fixed' : 'percentage',
            base: line.base || 'op',
            value: Number(line.value || 0),
            vatEnabled: !!line.vatEnabled,
            isDefault: false,
            isReadonly: false,
          });
        }
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
      isReadonly: false,
    });
    resetAssignmentExpenseDraft();
    return true;
  };

  const removeAssignmentExpenseLine = (id) => {
    const line = assignmentExpenseLines.value.find(l => l.id === id);
    
    // ✅ منع حذف التكاليف الافتراضية
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