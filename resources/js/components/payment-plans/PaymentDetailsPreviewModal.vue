<template>
  <Teleport to="body">
    <Transition name="pd-fade">
      <div
        v-if="modelValue"
        class="pd-modal-backdrop"
        role="dialog"
        aria-modal="true"
        aria-labelledby="pd-modal-title"
        @click.self="close"
      >
        <div class="pd-modal-shell">
          <div class="pd-modal-card">
            <div class="pd-modal-head">
              <div>
                <div id="pd-modal-title" class="pd-modal-title">Payment details</div>
                <div class="pd-title-accent" aria-hidden="true" />
              </div>
              <button type="button" class="pd-close btn btn-link" aria-label="Close" @click="close">
                ×
              </button>
            </div>

            <div class="pd-section-heading">Summary</div>
            <div class="pd-summary-grid">
              <div class="pd-card pd-card--hero">
                <div class="pd-card-label">Selling price</div>
                <div class="pd-card-value">{{ formatAed(sellingPrice) }}</div>
              </div>
              <div class="pd-card pd-card--muted">
                <div class="pd-card-label">Original price (OP)</div>
                <div class="pd-card-value pd-card-value--dark">{{ formatAed(originalPrice) }}</div>
              </div>
              <div class="pd-card pd-card--muted">
                <div class="pd-card-label">Payment plan (%)</div>
                <div class="pd-card-value pd-card-value--dark">{{ paymentPlanLabel || '—' }}</div>
              </div>
              <div class="pd-card pd-card--muted">
                <div class="pd-card-label">Premium (selling − OP)</div>
                <div class="pd-card-value pd-card-value--dark">{{ formatAed(premiumAmount) }}</div>
              </div>
            </div>

            <div v-if="Number(nocPercent) > 0" class="pd-noc-strip">
              <span class="pd-noc-strip__item">
                NOC <strong>{{ nocPercentDisplay }}%</strong>
                · Required <strong>{{ formatAed(nocRequiredAed) }}</strong>
              </span>
              <span class="pd-noc-strip__item">
                Paid <strong>{{ formatAed(paidTotalAed) }}</strong>
                <span class="text-muted">({{ paidPercentOfOpDisplay }}% of OP)</span>
              </span>
              <span class="pd-noc-strip__item">
                Remaining <strong>{{ formatAed(nocRemainingAed) }}</strong>
              </span>
              <span class="pd-badge" :class="nocRequirementMet ? 'pd-badge--paid' : 'pd-badge--upcoming'">
                {{ nocRequirementMet ? 'NOC met' : 'Below NOC' }}
              </span>
            </div>

            <div class="pd-section-heading">Installment breakdown</div>
            <div class="pd-table-wrap">
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
                  <tr v-for="(row, idx) in normalizedRows" :key="row.id" :class="idx % 2 === 1 ? 'pd-row-alt' : ''">
                    <td>{{ row.type }}</td>
                    <td>{{ formatRowPercentage(row) }}</td>
                    <td>{{ formatAed(row.amount) }}</td>
                    <td>{{ formatDateDisplay(row.date) }}</td>
                    <td>
                      <span class="pd-badge" :class="badgeClass(row.status)">{{ row.status }}</span>
                    </td>
                  </tr>
                  <tr v-if="normalizedRows.length === 0">
                    <td colspan="5" class="pd-empty">No breakdown rows yet. Add installments below in the form.</td>
                  </tr>
                  <tr v-if="normalizedRows.length > 0" class="pd-total-row">
                    <td><strong>Total</strong></td>
                    <td><strong>{{ breakdownTableTotals.percentTotal }}{{ breakdownTableTotals.percentTotal !== '—' ? '%' : '' }}</strong></td>
                    <td><strong>{{ formatAed(breakdownTableTotals.amountTotal) }}</strong></td>
                    <td colspan="2"></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-if="expenseTableRows.length > 0" class="pd-section-heading">Assignment deal costs</div>
            <div v-if="expenseTableRows.length > 0" class="pd-table-wrap pd-table-wrap--section">
              <table class="pd-table">
                <thead>
                  <tr>
                    <th><span class="pd-th-pill">Label</span></th>
                    <th><span class="pd-th-pill">Detail</span></th>
                    <th class="text-end"><span class="pd-th-pill">Amount</span></th>
                    <th class="text-end"><span class="pd-th-pill">VAT</span></th>
                    <th class="text-end"><span class="pd-th-pill">Total</span></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, idx) in expenseTableRows" :key="row.id" :class="idx % 2 === 1 ? 'pd-row-alt' : ''">
                    <td>{{ row.label }}</td>
                    <td class="text-muted">{{ row.detail }}</td>
                    <td class="text-end">{{ row.amount }}</td>
                    <td class="text-end">{{ row.vat }}</td>
                    <td class="text-end fw-semibold">{{ row.total }}</td>
                  </tr>
                  <tr class="pd-total-row">
                    <td colspan="2"><strong>Total</strong></td>
                    <td class="text-end"><strong>{{ formatAed(assignmentExpensesSubtotal) }}</strong></td>
                    <td class="text-end"><strong>{{ formatAed(assignmentExpensesTotalVat) }}</strong></td>
                    <td class="text-end"><strong>{{ formatAed(expensesGrandTotalDisplay) }}</strong></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="pd-modal-foot">
              <button type="button" class="btn btn-secondary btn-sm pd-btn-close" @click="close">Close</button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  sellingPrice: { type: Number, default: 0 },
  originalPrice: { type: Number, default: 0 },
  premiumAmount: { type: Number, default: 0 },
  paymentPlanLabel: { type: String, default: '' },
  initialPercent: { type: Number, default: 0 },
  handoverPercent: { type: Number, default: 0 },
  ucTrancheAed: { type: Number, default: 0 },
  handoverAmountAed: { type: Number, default: 0 },
  handoverDate: { type: String, default: '' },
  paidTotalAed: { type: Number, default: 0 },
  paidPercentOfOp: { type: Number, default: 0 },
  nocPercent: { type: Number, default: 0 },
  nocRequiredAed: { type: Number, default: 0 },
  nocRemainingAed: { type: Number, default: 0 },
  nocRequirementMet: { type: Boolean, default: true },
  nocProgressLabel: { type: String, default: '—' },
  /** Same shape as `paymentBreakdownRows` in listing forms: { id, type, percentage, amount, date, status } */
  breakdownRows: { type: Array, default: () => [] },
  /** Raw or preview rows from form: { id, label, calcType, base, value, vatEnabled, ... } */
  assignmentExpenseRows: { type: Array, default: () => [] },
  assignmentExpensesSubtotal: { type: Number, default: 0 },
  assignmentExpensesTotalVat: { type: Number, default: 0 },
  assignmentExpensesGrandTotal: { type: Number, default: 0 },
});

const emit = defineEmits(['update:modelValue']);

const close = () => emit('update:modelValue', false);

const initialPercentDisplay = computed(() =>
  Number.isFinite(props.initialPercent) ? Math.max(0, Math.min(100, props.initialPercent)).toFixed(0) : '0',
);

const handoverPercentDisplay = computed(() =>
  Number.isFinite(props.handoverPercent) ? Math.max(0, Math.min(100, props.handoverPercent)).toFixed(0) : '0',
);

const nocPercentDisplay = computed(() =>
  Number.isFinite(props.nocPercent) ? Math.max(0, Math.min(100, props.nocPercent)).toFixed(0) : '0',
);

const paidPercentOfOpDisplay = computed(() =>
  Number.isFinite(props.paidPercentOfOp) ? props.paidPercentOfOp.toFixed(2) : '0.00',
);

const normalizedRows = computed(() => {
  const rows = Array.isArray(props.breakdownRows) ? props.breakdownRows : [];
  return rows.map((r) => ({
    id: r.id,
    type: r.type ?? '—',
    percentage: r.percentage,
    amount: Number(r.amount || 0),
    date: r.date,
    status: r.status ?? 'Upcoming',
  }));
});

const formatAed = (n) =>
  new Intl.NumberFormat('en-AE', { style: 'currency', currency: 'AED', maximumFractionDigits: 0 }).format(
    Number(n || 0),
  );

const formatDateDisplay = (dateLike) => {
  if (dateLike === null || dateLike === undefined || dateLike === '') return '—';
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
  if (status === 'Due on transfer' || status === 'Due to transfer') return 'pd-badge--due';
  if (status === 'Due payment') return 'pd-badge--due';
  if (status === 'Remaining') return 'pd-badge--remaining';
  return 'pd-badge--upcoming';
};

const breakdownTableTotals = computed(() => {
  const rows = normalizedRows.value;
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
  };
});

const formatExpenseDetail = (row) => {
  const calcType = row?.calcType ?? (row?.typeLabel === 'Percentage' ? 'percentage' : 'fixed');
  const baseLabels = { op: 'OP', sp: 'SP', premium: 'premium' };
  if (calcType === 'percentage') {
    const baseShort = row?.baseLabel
      ? String(row.baseLabel)
          .replace('Original Price (OP)', 'OP')
          .replace('Sale Price (SP)', 'SP')
          .replace('Premium (SP − OP)', 'premium')
      : baseLabels[row?.base] || '';
    const pct = row?.valueDisplay ?? `${Number(row?.value || 0)}%`;
    return baseShort && baseShort !== '—' ? `${pct} of ${baseShort}` : pct;
  }
  return row?.valueDisplay ?? formatAed(Number(row?.value ?? row?.amount ?? 0));
};

const expenseTableRows = computed(() => {
  const rows = Array.isArray(props.assignmentExpenseRows) ? props.assignmentExpenseRows : [];
  return rows.map((row) => ({
    id: row?.id ?? row?.label,
    label: row?.label || '—',
    detail: formatExpenseDetail(row),
    amount: formatAed(expenseLineAmount(row)),
    vat: row?.vatEnabled ? formatAed(expenseLineVat(row)) : '—',
    total: formatAed(expenseLineTotal(row)),
  }));
});


const UAE_VAT_RATE = 0.05;

const expenseBaseAmount = (base) => {
  if (base === 'op') return Number(props.originalPrice || 0);
  if (base === 'sp') return Number(props.sellingPrice || 0);
  if (base === 'premium') return Number(props.premiumAmount || 0);
  return 0;
};

const expenseLineAmount = (row) => {
  if (!row) return 0;
  const calcType = row.calcType ?? (row.typeLabel === 'Percentage' ? 'percentage' : 'fixed');
  if (calcType === 'percentage') {
    return (expenseBaseAmount(row.base) * Number(row.value || 0)) / 100;
  }
  return Number(row.value ?? row.amount ?? 0);
};

const expenseLineVat = (row) => (row?.vatEnabled ? expenseLineAmount(row) * UAE_VAT_RATE : 0);

const expenseLineTotal = (row) => expenseLineAmount(row) + expenseLineVat(row);

const expenseNoteLines = computed(() => {
  const rows = Array.isArray(props.assignmentExpenseRows) ? props.assignmentExpenseRows : [];
  const baseLabels = { op: 'OP', sp: 'SP', premium: 'premium' };
  return rows.map((row) => {
    const label = String(row?.label || 'Cost').trim();
    const calcType = row?.calcType ?? (row?.typeLabel === 'Percentage' ? 'percentage' : 'fixed');
    let detail = '';
    if (calcType === 'percentage') {
      const baseShort = row?.baseLabel
        ? String(row.baseLabel)
            .replace('Original Price (OP)', 'OP')
            .replace('Sale Price (SP)', 'SP')
            .replace('Premium (SP − OP)', 'premium')
        : baseLabels[row?.base] || '';
      const pct = row?.valueDisplay ?? `${Number(row?.value || 0)}%`;
      detail = baseShort && baseShort !== '—' ? `${pct} of ${baseShort}` : pct;
    } else {
      detail = row?.valueDisplay ?? formatAed(Number(row?.value ?? row?.amount ?? 0));
    }
    const total = expenseLineTotal(row);
    let text = `${label} — ${detail} · ${formatAed(total)}`;
    if (row?.vatEnabled && expenseLineVat(row) > 0) {
      text += ` (incl. VAT ${formatAed(expenseLineVat(row))})`;
    }
    return { id: row?.id ?? label, text };
  });
});

const expensesGrandTotalDisplay = computed(() => {
  if (Number(props.assignmentExpensesGrandTotal) > 0) return props.assignmentExpensesGrandTotal;
  const rows = Array.isArray(props.assignmentExpenseRows) ? props.assignmentExpenseRows : [];
  return rows.reduce((sum, row) => sum + expenseLineTotal(row), 0);
});

</script>

<style scoped>
/* Typography: compact; no font size above 15px inside this modal. */
.pd-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1080;
  background: rgba(15, 23, 42, 0.55);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 1.5rem;
  overflow-y: auto;
}

.pd-modal-shell {
  width: 100%;
  max-width: 1040px;
  margin: auto;
}

.pd-modal-card {
  background: #f4f6f9;
  border-radius: 16px;
  padding: 1.25rem 1.35rem 1rem;
  box-shadow: 0 24px 48px rgba(15, 23, 42, 0.18);
  font-size: 12px;
  line-height: 1.35;
  color: #1e293b;
  position: relative;
  overflow: hidden;
}

/* Oia Properties logo — very light watermark (public/oia-properties-logo-watermark.svg) */
.pd-modal-card::before {
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
}

.pd-modal-card > * {
  position: relative;
  z-index: 1;
}

.pd-modal-card :deep(.btn) {
  font-size: 12px !important;
  line-height: 1.25;
}

.pd-modal-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
  position: relative;
  padding-right: 1.75rem;
}

.pd-modal-title {
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

.pd-close {
  position: absolute;
  top: -0.2rem;
  right: 0;
  font-size: 15px;
  line-height: 1;
  padding: 0.2rem 0.45rem;
  color: #64748b;
  text-decoration: none;
}

.pd-close:hover {
  color: #0f1f3a;
}

.pd-modal-head + .pd-section-heading {
  margin-top: 0;
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

.pd-summary-grid--second {
  margin-bottom: 0.2rem;
}

@media (max-width: 900px) {
  .pd-summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 520px) {
  .pd-summary-grid {
    grid-template-columns: 1fr;
  }
}

.pd-card {
  border-radius: 10px;
  padding: 0.65rem 0.7rem 0.7rem;
  min-height: 0;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.pd-card--hero {
  background: linear-gradient(160deg, #132043 0%, #0f1f3a 100%);
  color: #fff;
  border-bottom: 3px solid #e85d1c;
  box-shadow: 0 6px 14px rgba(15, 31, 58, 0.2);
}

.pd-card--muted {
  background: #e8ecf2;
  color: #0f1f3a;
}

.pd-card-label {
  font-size: 11px;
  font-weight: 600;
  text-transform: capitalize;
  opacity: 0.92;
  margin-bottom: 0.25rem;
  line-height: 1.25;
}

.pd-card--hero .pd-card-label {
  color: rgba(255, 255, 255, 0.88);
}

.pd-card-value {
  font-size: 14px;
  font-weight: 700;
  line-height: 1.2;
}

.pd-card-value--sm {
  font-size: 13px;
}

.pd-card--hero .pd-card-value {
  color: #fff;
}

.pd-card-value--dark {
  color: #0f1f3a;
}

.pd-card-sub {
  font-size: 11px;
  color: #64748b;
  margin-top: 0.2rem;
}

.pd-modal-card .pd-noc-list strong {
  font-size: 12px;
  font-weight: 600;
}

.pd-noc-panel {
  background: #fff;
  border-radius: 10px;
  padding: 0.65rem 0.75rem;
  margin-bottom: 0.4rem;
  box-shadow: inset 0 0 0 1px rgba(15, 31, 58, 0.06);
  font-size: 12px;
}

.pd-noc-list li {
  margin-bottom: 0.3rem;
}

.pd-noc-list li:last-child {
  margin-bottom: 0;
}

.pd-table-wrap {
  background: #fff;
  border-radius: 12px;
  padding: 0.55rem 0.55rem 0.4rem;
  box-shadow: inset 0 0 0 1px rgba(15, 31, 58, 0.06);
}

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

.pd-table tbody tr:first-child td {
  padding-top: 0.55rem;
}

.pd-row-alt td {
  background: #f1f5f9;
}

.pd-empty {
  text-align: center;
  color: #64748b;
  font-size: 12px;
  padding: 1.25rem 0.75rem !important;
}

.pd-badge {
  display: inline-block;
  padding: 0.25rem 0.55rem;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  white-space: nowrap;
}

.pd-badge--paid {
  background: #22c55e;
  color: #fff;
}

.pd-badge--due {
  background: #bae6fd;
  color: #075985;
}

.pd-badge--remaining {
  background: #60a5fa;
  color: #fff;
}

.pd-badge--upcoming {
  background: #fecdd3;
  color: #9f1239;
}



.pd-installment-notes {
  margin-top: 0.65rem;
  padding: 0.6rem 0.75rem 0.5rem;
  border-top: 1px dashed rgba(100, 116, 139, 0.35);
  background: #f8fafc;
  border-radius: 0 0 10px 10px;
}

.pd-installment-notes__heading {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #94a3b8;
  margin: 0;
}

.pd-installment-notes__line {
  font-size: 12px;
  line-height: 1.4;
  color: #475569;
  margin: 0;
}

.pd-installment-notes__total {
  font-size: 12px;
  color: #64748b;
  text-align: right;
  margin-top: 0.35rem;
  padding-top: 0.35rem;
  border-top: 1px solid rgba(15, 31, 58, 0.06);
}

.pd-installment-notes__total strong {
  color: #0f1f3a;
}


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

.pd-noc-strip__item {
  white-space: nowrap;
}

.pd-table-wrap--section {
  margin-bottom: 0.5rem;
}

.pd-total-row td {
  background: #f1f5f9 !important;
  border-top: 2px solid #e2e8f0;
  font-size: 12px;
}

.pd-expenses-notes {
  background: #fff;
  border-radius: 10px;
  padding: 0.65rem 0.85rem;
  margin-bottom: 0.35rem;
  box-shadow: inset 0 0 0 1px rgba(15, 31, 58, 0.06);
  border-left: 3px solid #e2e8f0;
}

.pd-expenses-notes__line {
  padding: 0.28rem 0;
  border-bottom: 1px dashed rgba(100, 116, 139, 0.2);
  font-size: 12px;
  line-height: 1.45;
  color: #334155;
}

.pd-expenses-notes__line:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.pd-expenses-notes__text {
  display: block;
}

.pd-expenses-notes__total {
  margin-top: 0.5rem;
  padding-top: 0.45rem;
  border-top: 1px solid rgba(15, 31, 58, 0.08);
  font-size: 12px;
  color: #64748b;
  text-align: right;
}

.pd-expenses-notes__total strong {
  color: #0f1f3a;
  font-weight: 700;
  margin-left: 0.25rem;
}

.pd-expenses-notes__vat-hint {
  font-size: 11px;
  color: #94a3b8;
  margin-left: 0.2rem;
}

.pd-modal-foot {
  display: flex;
  justify-content: flex-end;
  margin-top: 0.75rem;
  padding-top: 0.2rem;
}

.pd-fade-enter-active,
.pd-fade-leave-active {
  transition: opacity 0.2s ease;
}

.pd-fade-enter-from,
.pd-fade-leave-to {
  opacity: 0;
}
</style>
