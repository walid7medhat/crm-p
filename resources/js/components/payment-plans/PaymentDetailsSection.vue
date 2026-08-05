<template>
  <div v-if="hasContent" class="pd-section-wrap">
    <div class="pd-card">
      <div class="pd-card-head">
        <div>
          <div class="pd-card-title">Payment details
            <!-- <span class="badge" :class="nocType === 'Ready' || nocType === 'Completed' ? 'bg-success' : 'bg-warning text-dark'">
            {{ nocType }}
          </span> -->
          </div>
          <div class="pd-title-accent" aria-hidden="true" />
        </div>
      </div>

      <div class="pd-section-heading">Summary</div>
      <div class="pd-summary-grid">
        <div class="pd-cell pd-cell--hero">
          <div class="pd-cell-label">Selling price</div>
          <div class="pd-cell-value">{{ formatAed(sellingPrice) }}</div>
        </div>
        <div class="pd-cell pd-cell--muted">
          <div class="pd-cell-label">Original price </div>
          <div class="pd-cell-value pd-cell-value--dark">{{ formatAed(originalPrice) }}</div>
        </div>
        <div class="pd-cell pd-cell--muted" v-if="paymentPlanLabel">
          <div class="pd-cell-label">Payment plan</div>
          <div class="pd-cell-value pd-cell-value--dark">{{ paymentPlanLabel || '—' }}</div>
        </div>
        <div class="pd-cell pd-cell--muted">
          <div class="pd-cell-label">Premium </div>
          <div class="pd-cell-value pd-cell-value--dark" :class="{ 'pd-text-danger': premiumAmount < 0 }">
            {{ formatAed(premiumAmount) }}
          </div>
        </div>
      </div>

        <!-- NOC Section - Percentage Only -->
      <div v-if="nocPercentage > 0 && isUnderConstruction" class="pd-noc-strip">
        <span class="pd-noc-strip__item">
          <span class="badge bg-info text-white">
            <!-- <i class="fas fa-percentage me-1"></i> -->
            NOC 
          </span>
          <span class="ms-1">Required:</span>
          <strong>{{ formatAed(nocAmount) }}</strong>
          <span class="text-muted ms-1">({{ nocPercentage }}% of OP)</span>
        </span>
        
        <span class="pd-noc-strip__item">
          <i class="fas fa-check-circle text-success me-1"></i>
          Paid: <strong>{{ formatAed(paidTotalAed) }}</strong>
          <span class="text-muted">({{ paidPercentDisplay }}% of OP)</span>
        </span>
        
        <span class="pd-noc-strip__item">
          <i class="fas fa-clock text-warning me-1"></i>
          Remaining: <strong>{{ formatAed(nocRemainingAed) }}</strong>
        </span>
        
        <span class="pd-badge" :class="nocRequirementMet ? 'pd-badge--paid' : 'pd-badge--upcoming'">
          <i :class="nocRequirementMet ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle'" class="me-1"></i>
          {{ nocRequirementMet ? 'NOC met ✅' : 'Below NOC ⚠️' }}
        </span>
      </div>

      <div v-if="breakdownRows.length > 2 && listing.completion_status !='Completed'" class="pd-section-heading">Installment breakdown</div>
      <div v-if="breakdownRows.length > 2 && listing.completion_status !='Completed'" class="pd-table-wrap">
        <table class="pd-table">
          <thead>
            <tr>
              <th><span class="pd-th-pill">Payment type</span></th>
              <th><span class="pd-th-pill">Percentage (%)</span></th>
              <th><span class="pd-th-pill">Amount (AED)</span></th>
              <th><span class="pd-th-pill">Date</span></th>
              <th><span class="pd-th-pill">Status</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, idx) in breakdownRows" :key="row.id" :class="idx % 2 === 1 ? 'pd-row-alt' : ''">
              <td>{{ row.type }}</td>
              <td>{{ formatRowPercentage(row) }}</td>
              <td :class="{ 'pd-text-danger': row.type === 'Premium' && row.amount < 0 }">
                {{ formatAed(row.amount) }}
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

      <div v-if="expenseRows.length > 0" class="pd-section-heading">Other costs</div>
      <div v-if="expenseRows.length > 0" class="pd-table-wrap pd-table-wrap--section">
        <table class="pd-table">
          <thead>
            <tr>
              <th><span class="pd-th-pill">Label</span></th>
              <th><span class="pd-th-pill">Detail</span></th>
              <th class=""><span class="pd-th-pill">Amount</span></th>
              <th class=""><span class="pd-th-pill">VAT</span></th>
              <th class=""><span class="pd-th-pill">Total</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="nocFixedAmount > 0">
              <td>NOC Fees</td>
              <td class="text-muted">
                —
                <!-- <span class="text-muted d-block" style="font-size:0.85em;">
                  {{ nocType === 'Ready' || nocType === 'Completed' ? 'Ready' : 'Off-Plan' }}
                </span> -->
              </td>
              <td>{{ formatAed(nocFixedAmount) }}</td>
              <td>—</td>
              <td class="fw-semibold">{{ formatAed(nocFixedAmount) }}</td>
            </tr>
            <tr v-for="(row, idx) in expenseRows" :key="row.id" :class="idx % 2 === 1 ? 'pd-row-alt' : ''">
              <td>{{ row.label }}</td>
              <td class="pd-text-muted">{{ row.detail }}</td>
              <td class="">{{ formatAed(row.amount) }}</td>
              <td class="">{{ row.vatEnabled ? formatAed(row.vat) : '—' }}</td>
              <td class=" pd-fw-semibold">{{ formatAed(row.total) }}</td>
            </tr>
           
            <tr class="pd-total-row">
              <td colspan="2"><strong>Total</strong></td>
              <td class=""><strong>{{ formatAed(expensesSubtotal) }}</strong></td>
              <td class=""><strong>{{ formatAed(expensesTotalVat) }}</strong></td>
              <td class=""><strong>{{ formatAed(expensesGrandTotal) }}</strong></td>
            </tr>
          </tbody>
        </table>
      </div>
      <p class="pd-noc-strip mt-3">Please note that all fees mentioned are indicative and may change based on the developer’s policy, government authority requirements, or applicable regulations at the time of purchase.</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { paymentPlanSelectionLabel } from '@/composables/listingPaymentPlanPresets';

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
const nocFixedAmount = computed(() => props.listing?.noc_fixed_amount || 0);
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
    rows.push({
      id: 'handover-row',
      type: `Handover (${handoverPercent.value.toFixed(0)}%)`,
      percentage: handoverPercent.value.toFixed(2),
      amount: handoverAmountAed.value,
      date: handoverPaid ? '' : (props.listing?.handover_date || ''),
      status: handoverPaid ? 'Paid' : 'Upcoming',
    });
  }

  return rows;
});

const tableTotals = computed(() => {
  let percentTotal = 0;
  let amountTotal = 0;
  let hasPercent = false;
  for (const row of breakdownRows.value) {
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

const expensesSubtotal = computed(() =>
  expenseRows.value.reduce((sum, r) => sum + r.amount, 0),
);
const expensesTotalVat = computed(() =>
  expenseRows.value.reduce((sum, r) => sum + r.vat, 0),
);
const expensesGrandTotal = computed(() =>
  expenseRows.value.reduce((sum, r) => sum + r.total, 0),
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

const formatDateDisplay = (dateLike) => {
  if (!dateLike) return '—';
  const d = dateLike instanceof Date ? dateLike : new Date(dateLike);
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const formatRowPercentage = (row) => {
  if (row.type === 'Premium') return '—';
  const p = row.percentage;
  if (p === '' || p === null || p === undefined) return '—';
  return `${p}%`;
};

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
  background: #f4f6f9;
  border-radius: 16px;
  padding: 1.25rem 1.35rem 1rem;
  box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
  font-size: 12px;
  line-height: 1.35;
  color: #1e293b;
  position: relative;
  overflow: hidden;
}

/* .pd-card::before {
  content: '';
  position: absolute;
  inset: -8%;
  z-index: 0;
  pointer-events: none;
  background-image: url('/oia-properties-logo-watermark.svg');
  background-repeat: no-repeat;
  background-position: center 45%;
  background-size: min(92%, 720px) auto;
  opacity: 0.045;
} */

.pd-card > * { position: relative; z-index: 1; }

.pd-card-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.pd-card-title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #0f1f3a;
  line-height: 1.2;
}

.pd-title-accent {
  width: 40px;
  height: 3px;
  background: #e85d1c;
  border-radius: 2px;
  margin-top: 0.35rem;
}

.pd-section-heading {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: #64748b;
  margin: 0.6rem 0 0.4rem;
}

.pd-summary-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.55rem;
  margin-bottom: 0.4rem;
}

@media (max-width: 900px) {
  .pd-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 520px) {
  .pd-summary-grid { grid-template-columns: 1fr; }
}

.pd-cell {
  border-radius: 10px;
  padding: 0.65rem 0.7rem 0.7rem;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.pd-cell--hero {
  background: linear-gradient(160deg, #132043 0%, #0f1f3a 100%);
  color: #fff;
  border-bottom: 3px solid #e85d1c;
  box-shadow: 0 6px 14px rgba(15, 31, 58, 0.2);
}

.pd-cell--muted {
  background: #e8ecf2;
  color: #0f1f3a;
}

.pd-cell-label {
  font-size: 11px;
  font-weight: 600;
  text-transform: capitalize;
  opacity: 0.92;
  margin-bottom: 0.25rem;
  line-height: 1.25;
}

.pd-cell--hero .pd-cell-label { color: rgba(255, 255, 255, 0.88); }

.pd-cell-value {
  font-size: 14px;
  font-weight: 700;
  line-height: 1.2;
}

.pd-cell--hero .pd-cell-value { color: #fff; }
.pd-cell-value--dark { color: #0f1f3a; }

.pd-noc-strip {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 1rem;
  background: #fff;
  border-radius: 10px;
  padding: 0.55rem 0.75rem;
  margin-bottom: 0.5rem;
  font-size: 12px;
  box-shadow: inset 0 0 0 1px rgba(15, 31, 58, 0.06);
}
.pd-noc-strip__item { white-space: nowrap; }

.pd-table-wrap {
  background: #fff;
  border-radius: 12px;
  padding: 0.55rem 0.55rem 0.4rem;
  box-shadow: inset 0 0 0 1px rgba(15, 31, 58, 0.06);
  overflow-x: auto;
}
.pd-table-wrap--section { margin-bottom: 0.5rem; }

.pd-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  font-size: 12px;
}

.pd-table thead th {
  padding: 0.25rem 0.35rem 0.45rem;
  border: none;
  font-weight: 600;
  font-size: 12px;
  text-align: left;
  vertical-align: bottom;
}

.pd-th-pill {
  display: inline-block;
  background: #0f1f3a;
  color: #fff;
  padding: 0.3rem 0.55rem;
  border-radius: 999px;
  font-size: 10px;
  letter-spacing: 0.02em;
  text-transform: capitalize;
  white-space: nowrap;
  font-weight: 600;
}

.pd-table tbody td {
  padding: 0.5rem 0.45rem;
  border: none;
  vertical-align: middle;
  color: #1e293b;
  font-size: 12px;
}

.pd-row-alt td { background: #f1f5f9; }

.pd-total-row td {
  background: #f1f5f9 !important;
  border-top: 2px solid #e2e8f0;
  font-size: 12px;
}

.pd-badge {
  display: inline-block;
  padding: 0.25rem 0.55rem;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  white-space: nowrap;
}

.pd-badge--paid { background: #22c55e; color: #fff; }
.pd-badge--due { background: #bae6fd; color: #075985; }
.pd-badge--below-op { background: #fecaca; color: #b91c1c; }
.pd-badge--remaining { background: #60a5fa; color: #fff; }
.pd-badge--upcoming { background: #fecdd3; color: #9f1239; }

.pd-text-muted { color: #64748b; }
.pd-text-danger { color: #b91c1c; }
.pd-text-end { text-align: right; }
.pd-fw-semibold { font-weight: 600; }
</style>
