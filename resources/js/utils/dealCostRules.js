/**
 * Default deal-cost lines for a listing (the read-only rows in "Assignment / other costs")
 * and the developer NOC fee — one place for the add form, edit form and the
 * payment-breakdown modal.
 *
 * Abu Dhabi (anything not in Dubai):
 *   Agency 2% of SP + VAT · Transfer 2% of SP (1% in Al Reef) · Dari or ADGM admin fee
 * Dubai:
 *   Agency 2% of SP + VAT · DLD 4% of SP · Trustee fee
 *     ready (title deed):  SP ≥ 500,000 → 4,200 · below → 2,100
 *     off-plan (oqood):    SP ≥ 500,000 → 5,250 · below → 3,675
 *
 * NOC is not a cost line: it is listing.noc_fixed_amount (developer fee, + 5% VAT in Dubai).
 */

export const VAT_RATE = 0.05;
export const DUBAI_DLD_PERCENT = 4;
export const DUBAI_TRUSTEE_PRICE_THRESHOLD = 500000;
export const DUBAI_TRUSTEE_FEES = {
  titleDeed: { above: 4200, below: 2100 },
  oqood: { above: 5250, below: 3675 },
};
const AL_REEF_TRANSFER_FEE_PERCENT = 1;
const ADGM_TERMS = ['al maryah island', 'al reem island'];

export const DEFAULT_LABELS = {
  agency: 'Agency Fees',
  transfer: 'Transfer Fees',
  dariAdmin: 'Dari Admin Fees',
  adgmAdmin: 'ADGM Admin Fees',
  dld: 'DLD Fees',
  trusteeTitleDeed: 'Trustee Fees (Title Deed)',
  trusteeOqood: 'Trustee Fees (Oqood)',
};

// Every label a default line has ever been saved with (old singular spellings too), so a
// reload drops them and rebuilds them for the listing's current emirate/price/status.
const DEFAULT_LABEL_SET = new Set([
  ...Object.values(DEFAULT_LABELS),
  'Agency Fee', 'Transfer Fee', 'Dari Admin Fee', 'ADGM Admin Fee',
]);

export const isDefaultDealCostLabel = (label) => DEFAULT_LABEL_SET.has(String(label || '').trim());

const toNumber = (v) => {
  if (v == null || v === '') return 0;
  if (typeof v === 'number') return Number.isFinite(v) ? v : 0;
  const n = Number(String(v).replace(/,/g, '').trim());
  return Number.isFinite(n) ? n : 0;
};

// Settings sometimes store a percentage ×100 (e.g. 200 for 2%).
const normalizePercent = (v, fallback) => {
  let n = toNumber(v) || fallback;
  if (n > 100) n /= 100;
  return n;
};

export const isOffPlanStatus = (status) => {
  const s = String(status ?? '').trim().toLowerCase().replace(/_/g, ' ');
  return s === 'under construction' || s === 'off plan';
};

const areaNames = (area) => {
  if (!area) return [];
  if (typeof area === 'string') return area.split(',');
  return [
    // forms store "Community, Sub-area, Dubai" as the name, so split it too
    ...String(area.name ?? '').split(','),
    ...(Array.isArray(area.all_names) ? area.all_names : []),
    ...(Array.isArray(area.hierarchy) ? area.hierarchy.map((h) => h?.name) : []),
    ...String(area.area_parents_title || area.area_title || '').split(','),
  ];
};

/** Area object (any API shape) or "X, Y, Dubai" label → is it inside the Dubai city? */
export const isDubaiArea = (area) => {
  if (!area) return false;
  if (typeof area === 'object') {
    if (typeof area.is_dubai === 'boolean') return area.is_dubai;
    if (area.city_name) return String(area.city_name).trim().toLowerCase() === 'dubai';
  }
  return areaNames(area).some((n) => n && String(n).trim().toLowerCase() === 'dubai');
};

const isAlReefArea = (area) => areaNames(area).some((n) => n && /\bal[\s-]*reef\b/i.test(String(n)));

const isAdgmArea = (area) => {
  if (!area || typeof area !== 'object') return false;
  if (area.is_adgm !== undefined) return !!area.is_adgm;
  return areaNames(area).some((n) => n && ADGM_TERMS.some((t) => String(n).toLowerCase().includes(t)));
};

/** Developer NOC fee (AED, before VAT) for this emirate and completion status. */
export const developerNocFee = (developer, { isDubai = false, isOffPlan = false } = {}) => {
  if (!developer) return 0;
  const column = `${isDubai ? 'dubai_' : ''}${isOffPlan ? 'noc_fees_off_plan' : 'noc_fees_ready'}`;
  return toNumber(developer[column]);
};

/** Dubai trustee fee: title deed (ready) or oqood (off-plan), by selling price. */
export const dubaiTrusteeFee = (sellingPrice, isOffPlan) => {
  const table = isOffPlan ? DUBAI_TRUSTEE_FEES.oqood : DUBAI_TRUSTEE_FEES.titleDeed;
  return toNumber(sellingPrice) >= DUBAI_TRUSTEE_PRICE_THRESHOLD ? table.above : table.below;
};

/** NOC VAT: Dubai NOC fees are quoted + 5% VAT. */
export const nocVatAmount = (nocAmount, isDubai) => (isDubai ? toNumber(nocAmount) * VAT_RATE : 0);

// Stable ids so Vue keys don't churn when the defaults are rebuilt.
const DEFAULT_IDS = {
  agency: 9000001, transfer: 9000002, dariAdmin: 9000003, adgmAdmin: 9000004,
  dld: 9000005, trustee: 9000006,
};

const defaultLine = (id, label, extra) => ({
  id, label, base: null, vatEnabled: false, isDefault: true, isReadonly: true, ...extra,
});

/**
 * The default lines for a listing.
 * @param {object} ctx { settings, area, completionStatus, sellingPrice }
 */
export const buildDefaultDealCostLines = ({ settings = {}, area = null, completionStatus = '', sellingPrice = 0 } = {}) => {
  const s = settings || {};
  const agencyPercent = normalizePercent(s.agency_fee ?? s['3'], 2);
  const lines = [
    defaultLine(DEFAULT_IDS.agency, DEFAULT_LABELS.agency, {
      calcType: 'percentage', base: 'sp', value: agencyPercent, vatEnabled: true, isAgency: true,
    }),
  ];

  if (isDubaiArea(area)) {
    const offPlan = isOffPlanStatus(completionStatus);
    lines.push(
      defaultLine(DEFAULT_IDS.dld, DEFAULT_LABELS.dld, { calcType: 'percentage', base: 'sp', value: DUBAI_DLD_PERCENT }),
      defaultLine(DEFAULT_IDS.trustee, offPlan ? DEFAULT_LABELS.trusteeOqood : DEFAULT_LABELS.trusteeTitleDeed, {
        calcType: 'fixed', value: dubaiTrusteeFee(sellingPrice, offPlan), isAdminFee: true,
      }),
    );
    return lines;
  }

  const transferPercent = isAlReefArea(area)
    ? AL_REEF_TRANSFER_FEE_PERCENT
    : normalizePercent(s.transfer_fee ?? s['4'], 2);
  lines.push(defaultLine(DEFAULT_IDS.transfer, DEFAULT_LABELS.transfer, {
    calcType: 'percentage', base: 'sp', value: transferPercent,
  }));

  const adgm = isAdgmArea(area);
  const adminFee = toNumber(adgm ? (s.adgm_admin_fee ?? s['2']) : (s.dari_admin_fee ?? s['1']));
  if (adminFee > 0) {
    lines.push(defaultLine(
      adgm ? DEFAULT_IDS.adgmAdmin : DEFAULT_IDS.dariAdmin,
      adgm ? DEFAULT_LABELS.adgmAdmin : DEFAULT_LABELS.dariAdmin,
      { calcType: 'fixed', value: adminFee, isAdminFee: true },
    ));
  }
  return lines;
};

/** User-added lines kept as they are, default lines rebuilt for the current context. */
export const withDefaultDealCostLines = (lines, ctx) => [
  ...(Array.isArray(lines) ? lines : []).filter((l) => !(l?.isDefault || isDefaultDealCostLabel(l?.label))),
  ...buildDefaultDealCostLines(ctx),
];
