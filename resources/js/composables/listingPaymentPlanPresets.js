/** Shared payment plan presets for listing create/edit forms. */
export const paymentPlanOptions = [
  { label: '50/50', initial_percent: 50, handover_percent: 50 },
  { label: '40/60', initial_percent: 40, handover_percent: 60 },
  { label: '80/20', initial_percent: 80, handover_percent: 20 },
  { label: '15/85', initial_percent: 15, handover_percent: 85 },
  { label: '65/35', initial_percent: 65, handover_percent: 35 },
  { label: '60/40', initial_percent: 60, handover_percent: 40 },
  { label: '20/80', initial_percent: 20, handover_percent: 80 },
  { label: '35/65', initial_percent: 35, handover_percent: 65 },
  { label: '10/90', initial_percent: 10, handover_percent: 90 },
  { label: '55/45', initial_percent: 55, handover_percent: 45 },
  { label: '45/55', initial_percent: 45, handover_percent: 55 },
  { label: '70/30', initial_percent: 70, handover_percent: 30 },
  { label: '30/70', initial_percent: 30, handover_percent: 70 },
  { label: '25/75', initial_percent: 25, handover_percent: 75 },
  { label: '75/25', initial_percent: 75, handover_percent: 25 },
  { label: '10/1% Monthly', initial_percent: 10, handover_percent: 90 },
  { label: '20/1% Monthly', initial_percent: 20, handover_percent: 80 },
  { label: '30/1% Monthly', initial_percent: 30, handover_percent: 70 },
  { label: '85/15', initial_percent: 85, handover_percent: 15 },
  { label: '90/10', initial_percent: 90, handover_percent: 10 },
  { label: '10% down payment, 8-year installments', initial_percent: null, handover_percent: null, invalid: true },
];

export const PAYMENT_PLAN_PARSE_ERROR =
  'This payment plan cannot be used for automated checks. Choose a standard split plan (for example 30/70).';

const findPaymentPlanOptionByLabel = (label) => {
  const s = String(label ?? '').trim();
  if (!s) return null;
  return paymentPlanOptions.find((p) => p.label === s) ?? null;
};

export const attemptParseLegacyPaymentPlanLabel = (label) => {
  const s = String(label ?? '').trim();
  if (!s) return null;
  const m = s.match(/^(\d+(?:\.\d+)?)\s*\/\s*(\d+(?:\.\d+)?)$/);
  if (!m) return { label: s, initial_percent: null, handover_percent: null, invalid: true };
  const lo = Number(m[1]);
  const hi = Number(m[2]);
  if (![lo, hi].every(Number.isFinite)) return { label: s, initial_percent: null, handover_percent: null, invalid: true };
  if (lo < 0 || hi < 0 || lo > 100 || hi > 100) return { label: s, initial_percent: null, handover_percent: null, invalid: true };
  if (Math.abs(lo + hi - 100) > 0.01) return { label: s, initial_percent: null, handover_percent: null, invalid: true };
  return { label: s, initial_percent: lo, handover_percent: hi, legacyParsed: true };
};

export const resolvePaymentPlanOption = (raw) => {
  if (raw == null || raw === '') return null;
  if (typeof raw === 'object' && !Array.isArray(raw) && raw.label != null) {
    const hit = findPaymentPlanOptionByLabel(raw.label);
    if (hit) return hit;
    if (Number.isFinite(raw.initial_percent) && Number.isFinite(raw.handover_percent)) return raw;
    return attemptParseLegacyPaymentPlanLabel(raw.label);
  }
  if (Array.isArray(raw) && raw.length > 0) return resolvePaymentPlanOption(raw[0]);
  if (typeof raw === 'string') return findPaymentPlanOptionByLabel(raw) ?? attemptParseLegacyPaymentPlanLabel(raw);
  return null;
};

export const paymentPlanSelectionLabel = (raw) => {
  if (raw == null || raw === '') return '';
  if (typeof raw === 'string') return raw;
  if (Array.isArray(raw) && raw.length > 0) {
    const p = raw[0];
    if (typeof p === 'string') return p;
    if (p && typeof p === 'object') return p.label || p.value || '';
  }
  if (typeof raw === 'object') return raw.label || raw.value || '';
  return '';
};
