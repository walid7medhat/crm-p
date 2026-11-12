<template>
  <div v-if="hasContent" class="pd-section-wrap">
    <div class="pd-card">
      <div class="pd-summary-grid">
        <div class="pd-metric pd-metric--active">
          <div class="pd-metric__icon"><i class="ri-money-dollar-circle-line"></i></div>
          <div class="pd-metric__label">Selling Price (AED)</div>
          <div class="pd-metric__value">{{ formatAedRaw(sellingPrice) }}</div>
        </div>
        <div class="pd-metric" v-if="isUnderConstruction">
          <div class="pd-metric__icon"><i class="ri-price-tag-3-line"></i></div>
          <div class="pd-metric__label">Original Price (AED)</div>
          <div class="pd-metric__value">{{ formatAedRaw(originalPrice) }}</div>
        </div>
        <div class="pd-metric" v-if="paymentPlanLabel">
          <div class="pd-metric__icon"><i class="ri-pie-chart-line"></i></div>
          <div class="pd-metric__label">Payment Plan (%)</div>
          <div class="pd-metric__value">{{ paymentPlanLabel || '—' }}</div>
        </div>
        <div class="pd-metric" v-if="isUnderConstruction">
          <div class="pd-metric__icon"><i class="ri-funds-line"></i></div>
          <div class="pd-metric__label">Premium (AED)</div>
          <div class="pd-metric__value" :class="{ 'pd-text-danger': premiumAmount < 0 }">
            {{ formatAedRaw(premiumAmount) }}
          </div>
        </div>
        <div class="pd-metric" v-if="nocPercentage > 0 && isUnderConstruction">
          <div class="pd-metric__icon"><i class="ri-percent-line"></i></div>
          <div class="pd-metric__label">NOC (%)</div>
          <div class="pd-metric__value">{{ nocPercentage }}</div>
        </div>
      </div>

      <div v-if="nocPercentage > 0 && isUnderConstruction" class="pd-noc-strip">
        <span class="pd-noc-strip__item">
          NOC Required: <strong>{{ formatAed(nocAmount) }}</strong>
          <span class="pd-text-muted">({{ nocPercentage }}% of OP)</span>
        </span>
        <span class="pd-noc-strip__item">
          Paid: <strong>{{ formatAed(paidTotalAed) }}</strong>
          <span class="pd-text-muted">({{ paidPercentDisplay }}%)</span>
        </span>
        <span class="pd-noc-strip__item">
          Remaining: <strong>{{ formatAed(nocRemainingAed) }}</strong>
        </span>
        <span class="pd-badge" :class="nocRequirementMet ? 'pd-badge--paid' : 'pd-badge--upcoming'">
          {{ nocRequirementMet ? 'NOC met' : 'Below NOC' }}
        </span>
      </div>

      <div v-if="breakdownRows.length > 2 && listing.completion_status !='Completed'" class="pd-section-heading">Installment Breakdown</div>
      <div v-if="breakdownRows.length > 2 && listing.completion_status !='Completed'" class="pd-table-wrap">
        <table class="pd-table">
          <thead>
            <tr>
              <th>Payment Type</th>
              <th>Percentage (%)</th>
              <th>Amount (AED)</th>
              <th>Date</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in breakdownRows" :key="row.id">
              <td>{{ row.type }}</td>
              <td>{{ formatRowPercentage(row) }}</td>
              <td :class="{ 'pd-text-danger': row.type === 'Premium' && row.amount < 0 }">
                {{ formatRowAmount(row) }}
              </td>
              <td>{{ formatDateDisplay(row.date) }}</td>
              <td>
                <span class="pd-badge" :class="badgeClass(row.status)">{{ row.status }}</span>
              </td>
            </tr>
            <tr class="pd-total-row">
              <td><strong>Total</strong></td>
              <td><strong>{{ tableTotals.percentTotal }}{{ tableTotals.percentTotal !== '—' ? '%' : '' }}</strong></td>
              <td><strong>{{ formatAed(tableTotals.amountTotal) }}</strong></td>
              <td colspan="2"></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="expenseRows.length > 0" class="pd-section-heading">Other Costs</div>
      <div v-if="expenseRows.length > 0" class="pd-table-wrap pd-table-wrap--section">
        <table class="pd-table">
          <thead>
            <tr>
              <th>Fee Type</th>
              <th>Detail (%)</th>
              <th>Amount (AED)</th>
              <th>VAT (AED)</th>
              <th>Total (AED)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="nocFixedAmount > 0">
              <td>NOC Fees</td>
              <td class="pd-text-muted">—</td>
              <td>{{ formatAed(nocFixedAmount) }}</td>
              <td>{{ nocVat > 0 ? formatAed(nocVat) : '—' }}</td>
              <td class="pd-fw-semibold">{{ formatAed(nocFixedAmount + nocVat) }}</td>
            </tr>
            <tr v-for="row in expenseRows" :key="row.id">
              <td>{{ row.label }}</td>
              <td class="pd-text-muted">{{ row.detail }}</td>
              <td>{{ formatAed(row.amount) }}</td>
              <td>{{ row.vatEnabled ? formatAed(row.vat) : '—' }}</td>
              <td class="pd-fw-semibold">{{ formatAed(row.total) }}</td>
            </tr>
            <tr class="pd-total-row">
              <td colspan="2"><strong>Total</strong></td>
              <td><strong>{{ formatAed(expensesSubtotal) }}</strong></td>
              <td><strong>{{ formatAed(expensesTotalVat) }}</strong></td>
              <td><strong>{{ formatAed(expensesGrandTotal) }}</strong></td>
            </tr>
          </tbody>
        </table>
      </div>

      <p class="pd-disclaimer">
        <i class="ri-information-line"></i>
        Please note that all fees mentioned are indicative and may change based on the developer’s policy, government authority requirements, or applicable regulations at the time of purchase.
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { paymentPlanSelectionLabel } from '@/composables/listingPaymentPlanPresets';
import { nocVatAmount } from '@/utils/dealCostRules';

const props = defineProps({
  listing: { type: Object, default: () => ({}) },
  // ✅ إضافة nocPercentage كـ Prop
  nocPercentage: { type: Number, default: 0 },
});
const isUnderConstruction = computed(() => {
  const s = String(props.listing?.completion_status ?? '').trim().toLowerCase().replace(/_/g, ' ');
  return s === 'under construction' || s === 'off plan';
});
const UAE_VAT_RATE = 0.05;

const toNumber = (v) => {
  if (v === null || v === undefined || v === '') return 0;
  const n = Number(String(v).replace(/[^0-9.-]/g, ''));
  return Number.isFinite(n) ? n : 0;
};

const sellingPrice = computed(() => props.listing?.price ?? props.listing?.selling_price);
const originalPrice = computed(() => props.listing?.original_price) || sellingPrice.value;
const premiumAmount = computed(() => sellingPrice.value - originalPrice.value);
const nocFixedAmount = computed(() => Number(props.listing?.noc_fixed_amount || 0));
// Dubai NOC fees are quoted + 5% VAT (listing.is_dubai comes from the API).
const nocVat = computed(() => nocVatAmount(nocFixedAmount.value, !!props.listing?.is_dubai));
const nocType = computed(() => props.listing?.noc_type || 'Off-Plan');

// ✅ NOC Percentage من Props
const nocPercentage = computed(() => props.nocPercentage || props.listing?.noc_percentage || 0);

// ✅ NOC Amount من النسبة المئوية
const nocAmount = computed(() => {
  if (originalPrice.value <= 0 || nocPercentage.value <= 0) return 0;
  return (originalPrice.value * Math.max(0, Math.min(100, nocPercentage.value))) / 100;
});

// ✅ NOC Percent (للتوافق مع الكود القديم)
const nocPercent = computed(() => nocPercentage.value);

const rawBreakdown = computed(() => {
  const raw = props.listing?.payment_breakdown;
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
});

const rawExpenses = computed(() => {
  const raw = props.listing?.assignment_expense_lines;
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
});

const paymentPlanLabel = computed(() => {
  const label = paymentPlanSelectionLabel(props.listing?.payment_plan);
  return label || '';
});

const installmentToAmount = (entry) => {
  if (!entry) return 0;
  if (entry.type === 'percentage') {
    return (originalPrice.value * entry.value) / 100;
  }
  return entry.value;
};

const initialPercentFromPlan = computed(() => {
  const raw = props.listing?.payment_plan;
  if (!raw) return 0;
  let label = '';
  if (Array.isArray(raw)) label = String(raw[0] || '');
  else if (typeof raw === 'string') {
    try {
      const parsed = JSON.parse(raw);
      label = Array.isArray(parsed) ? String(parsed[0] || '') : String(raw);
    } catch {
      label = raw;
    }
  } else if (typeof raw === 'object') label = String(raw.label || '');
  const m = String(label).match(/(\d+)\s*\/\s*(\d+)/);
  if (!m) return 0;
  return Math.max(0, Math.min(100, Number(m[1])));
});

const handoverPercent = computed(() => Math.max(0, 100 - initialPercentFromPlan.value));

const ucTrancheAed = computed(() => (originalPrice.value * initialPercentFromPlan.value) / 100);
const handoverAmountAed = computed(() => originalPrice.value - ucTrancheAed.value);

const isDatePaid = (dateLike) => {
  if (!dateLike) return false;
  const d = new Date(dateLike);
  if (Number.isNaN(d.getTime())) return false;
  const dayStart = new Date(d.getFullYear(), d.getMonth(), d.getDate());
  const today = new Date();
  const todayStart = new Date(today.getFullYear(), today.getMonth(), today.getDate());
  return dayStart.getTime() <= todayStart.getTime();
};

const scheduledInstallmentsAed = computed(() =>
  rawBreakdown.value.reduce((sum, entry) => sum + installmentToAmount(entry), 0),
);

const paidTotalAed = computed(() =>
  rawBreakdown.value.reduce((sum, entry) => {
    if (!isDatePaid(entry?.date)) return sum;
    return sum + installmentToAmount(entry);
  }, 0),
);

const paidPercentDisplay = computed(() => {
  if (originalPrice.value <= 0) return '0.00';
  return ((paidTotalAed.value / originalPrice.value) * 100).toFixed(2);
});
const nocRemainingAmount = computed(() => {
  const required = nocRequiredAmount.value;
  const paid = Number(props.paidTotalAed || 0);
  return Math.max(0, required - paid);
});

const isNocCovered = computed(() => {
  const required = nocRequiredAmount.value;
  if (required <= 0) return true;
  const paid = Number(props.paidTotalAed || 0);
  return paid >= required - 0.01;
});
// ✅ NOC Required من النسبة المئوية
const nocRequiredAed = computed(() => nocAmount.value);

// ✅ NOC Remaining
const nocRemainingAed = computed(() => 
  Math.max(0, nocRequiredAed.value - paidTotalAed.value)
);
// ✅ NOC Requirement Met
const nocRequirementMet = computed(() => {
  if (nocPercentage.value <= 0) return true;
  return paidTotalAed.value >= nocRequiredAed.value - 0.01;
});

const breakdownRows = computed(() => {
  const paidRows = [];
  const notPaidRows = [];
  let cumulative = 0;
  const sorted = rawBreakdown.value
    .slice()
    .sort((a, b) => new Date(a?.date || 0) - new Date(b?.date || 0));

  sorted.forEach((entry, idx) => {
    const amount = installmentToAmount(entry);
    cumulative += amount;
    const paid = isDatePaid(entry?.date);
    let status = 'Upcoming';
    if (paid) status = 'Paid';
    else if (nocPercentage.value > 0 && cumulative <= nocRequiredAed.value + 0.01) status = 'Due on transfer';

    const row = {
      id: entry?.id != null ? `inst-${entry.id}` : `inst-${idx}`,
      type: 'Installment',
      percentage: originalPrice.value > 0 ? ((amount / originalPrice.value) * 100).toFixed(2) : '',
      amount,
      // date: paid ? '' : (entry?.date || ''), // only show date if NOT paid
      date:(entry?.date || ''),
      status,
    };

    if (paid) paidRows.push(row);
    else notPaidRows.push(row);
  });

  const rows = [...paidRows];

  if (rawBreakdown.value.length > 0 || originalPrice.value > 0 || sellingPrice.value > 0) {
    rows.push({
      id: 'premium-row',
      type: 'Premium',
      percentage: '',
      amount: premiumAmount.value,
      date: '',
      status: premiumAmount.value < -0.01 ? 'Selling below original price' : 'Due on transfer',
    });
  }

  rows.push(...notPaidRows);

  if (Math.abs(handoverAmountAed.value) > 0.01) {
    const handoverPaid = isDatePaid(props.listing?.handover_date);
    const handoverIsFull = handoverPercent.value >= 100;
    rows.push({
      id: 'handover-row',
      type: `Handover (${handoverPercent.value.toFixed(0)}%)`,
      percentage: handoverPercent.value.toFixed(2),
      amount: handoverAmountAed.value,
      date: handoverPaid ? '' : (props.listing?.handover_date || ''),
      status: handoverPaid ? 'Paid' : 'Upcoming',
      dashOut: handoverIsFull,
    });
  }

  return rows;
});

const tableTotals = computed(() => {
  let percentTotal = 0;
  let amountTotal = 0;
  let hasPercent = false;
  for (const row of breakdownRows.value) {
    if (row.dashOut) continue;
    amountTotal += row.amount;
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
  };
});

const baseLabels = { op: 'OP', sp: 'SP', premium: 'premium' };
const baseAmount = (base) => {
  if (base === 'op') return originalPrice.value;
  if (base === 'sp') return sellingPrice.value;
  if (base === 'premium') return premiumAmount.value;
  return 0;
};

const expenseLineAmount = (line) => {
  if (!line) return 0;
  const calcType = line.calcType ?? (line.typeLabel === 'Percentage' ? 'percentage' : 'fixed');
  if (calcType === 'percentage') {
    return (baseAmount(line.base) * line.value) / 100;
  }
  return line.value ?? line.amount;
};

const expenseLineVat = (line) => (line?.vatEnabled ? expenseLineAmount(line) * UAE_VAT_RATE : 0);
const expenseLineTotal = (line) => expenseLineAmount(line) + expenseLineVat(line);

const expenseRows = computed(() =>
  rawExpenses.value.map((line, idx) => {
    const calcType = line?.calcType === 'fixed' ? 'fixed' : 'percentage';
    const amount = expenseLineAmount(line);
    const vat = expenseLineVat(line);
    const detail =
      calcType === 'percentage'
        ? `${line?.value}% `
        : formatAed(line?.value);
    return {
      id: line?.id != null ? `exp-${line.id}` : `exp-${idx}`,
      label: line?.label || '—',
      detail,
      amount,
      vat,
      total: amount + vat,
      vatEnabled: !!line?.vatEnabled,
    };
  }),
);

// Totals include the NOC row (same as the offer PDF).
const expensesSubtotal = computed(() =>
  expenseRows.value.reduce((sum, r) => sum + r.amount, 0) + nocFixedAmount.value,
);
const expensesTotalVat = computed(() =>
  expenseRows.value.reduce((sum, r) => sum + r.vat, 0) + nocVat.value,
);
const expensesGrandTotal = computed(() =>
  expenseRows.value.reduce((sum, r) => sum + r.total, 0) + nocFixedAmount.value + nocVat.value,
);

// ✅ تحديث hasContent ليشمل NOC Percentage
const hasContent = computed(
  () =>
    rawBreakdown.value.length > 0 ||
    rawExpenses.value.length > 0 ||
    (originalPrice.value > 0 && Math.abs(premiumAmount.value) > 0.01) ||
    paymentPlanLabel.value ||
    nocPercentage.value > 0,
);

const formatAed = (value) =>
  new Intl.NumberFormat('en-AE', {
    style: 'currency',
    currency: 'AED',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(Number(value ?? 0));

/** Number only (no AED prefix) for Figma metric cards that already label currency. */
const formatAedRaw = (value) =>
  new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(Number(value ?? 0));

const formatDateDisplay = (dateLike) => {
  if (!dateLike) return '—';
  const d = dateLike instanceof Date ? dateLike : new Date(dateLike);
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const formatRowPercentage = (row) => {
  if (row.type === 'Premium' || row.dashOut) return '—';
  const p = row.percentage;
  if (p === '' || p === null || p === undefined) return '—';
  return `${p}%`;
};

const formatRowAmount = (row) => (row.dashOut ? '—' : formatAed(row.amount));

const badgeClass = (status) => {
  if (status === 'Paid') return 'pd-badge--paid';
  if (status === 'Selling below original price') return 'pd-badge--below-op';
  if (status === 'Due on transfer' || status === 'Due to transfer') return 'pd-badge--due';
  if (status === 'Remaining') return 'pd-badge--remaining';
  return 'pd-badge--upcoming';
};
</script>

<style scoped>
.pd-section-wrap { width: 100%; }

.pd-card {
  background: transparent;
  border-radius: 0;
  padding: 0;
  box-shadow: none;
  font-size: 13px;
  line-height: 1.4;
  color: #1e293b;
}

.pd-section-heading {
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--color-primary);
  margin: 1.1rem 0 0.65rem;
}

.pd-summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 10px;
  margin-bottom: 0.75rem;
}

.pd-metric {
  background: #fff;
  border: 1px solid rgba(var(--color-primary-rgb), 0.12);
  border-radius: 12px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-height: 88px;
}

.pd-metric--active {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 1px rgba(var(--color-primary-rgb), 0.18);
}

.pd-metric__icon {
  width: 26px;
  height: 26px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: var(--color-primary);
  background: rgba(var(--color-primary-rgb), 0.1);
  font-size: 15px;
}

.pd-metric__label {
  font-size: 11px;
  font-weight: 500;
  color: var(--color-text-muted, #6b7280);
  line-height: 1.25;
}

.pd-metric__value {
  font-size: 14px;
  font-weight: 700;
  color: var(--color-text, #0b0736);
  line-height: 1.2;
}

.pd-noc-strip {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 1rem;
  background: #fafafb;
  border: 1px solid #eeeef2;
  border-radius: 10px;
  padding: 0.65rem 0.85rem;
  margin-bottom: 0.5rem;
  font-size: 12px;
}

.pd-noc-strip__item { white-space: nowrap; }

.pd-table-wrap {
  background: transparent;
  border-radius: 0;
  padding: 0;
  box-shadow: none;
  overflow-x: auto;
}

.pd-table-wrap--section { margin-bottom: 0.35rem; }

.pd-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0 8px;
  font-size: 13px;
}

.pd-table thead th {
  padding: 0 12px 6px;
  border: none;
  font-weight: 600;
  font-size: 12px;
  text-align: left;
  color: #9ca3af;
  vertical-align: middle;
}

.pd-table tbody td {
  padding: 12px;
  border: none;
  vertical-align: middle;
  text-align: left;
  color: #1e293b;
  font-size: 13px;
  background: #fff;
}

.pd-table tbody tr:not(.pd-total-row) td:first-child {
  border-radius: 10px 0 0 10px;
  border-left: 1px solid #ececf0;
}

.pd-table tbody tr:not(.pd-total-row) td:last-child {
  border-radius: 0 10px 10px 0;
  border-right: 1px solid #ececf0;
}

.pd-table tbody tr:not(.pd-total-row) td {
  border-top: 1px solid #ececf0;
  border-bottom: 1px solid #ececf0;
}

.pd-total-row td {
  background: rgba(var(--color-primary-rgb, 115, 62, 135), 0.08) !important;
  border: none !important;
  font-size: 13px;
}

.pd-total-row td:first-child {
  border-radius: 10px 0 0 10px !important;
}

.pd-total-row td:last-child {
  border-radius: 0 10px 10px 0 !important;
}

.pd-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  line-height: 1;
  white-space: nowrap;
  text-align: center;
}

.pd-badge--paid { background: rgba(var(--color-primary-rgb), 0.12); color: var(--color-primary); }
.pd-badge--due { background: rgba(var(--color-primary-rgb), 0.08); color: var(--color-text, #0b0736); }
.pd-badge--below-op { background: rgba(var(--color-primary-rgb), 0.14); color: var(--color-primary); }
.pd-badge--remaining { background: rgba(var(--color-primary-rgb), 0.1); color: var(--color-primary); }
.pd-badge--upcoming { background: rgba(var(--color-primary-rgb), 0.06); color: var(--color-text-muted, #6b7280); }

.pd-disclaimer {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin: 12px 0 0;
  font-size: 12px;
  line-height: 1.5;
  color: #9ca3af;
}

.pd-disclaimer i {
  margin-top: 2px;
  flex-shrink: 0;
  color: var(--color-primary, #733e87);
}

.pd-text-muted { color: #64748b; }
.pd-text-danger { color: #b91c1c; }
.pd-fw-semibold { font-weight: 600; }
</style>
