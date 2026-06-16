export const EMIRATES = {
  dubai: {
    id: 'dubai',
    label: 'Dubai',
    transferFeePct: 4,
    transferFeeLabel: 'DLD Transfer Fee',
    mortgageRegFee: 4200,
    mortgageRegLabel: 'DLD Mortgage Reg',
    regAuthority: 'DLD (Dubai Land Department)',
    rentalAuth: 'RERA Rental Index (rera.gov.ae)',
    svcAuth: 'RERA rate card (AED/sqft p.a.)',
  },
  abudhabi: {
    id: 'abudhabi',
    label: 'Abu Dhabi',
    transferFeePct: 2,
    transferFeeLabel: 'ADM Registration Fee',
    mortgageRegFee: 1500,
    mortgageRegLabel: 'DoM Mortgage Reg',
    regAuthority: 'DoM (Dept. of Municipalities)',
    rentalAuth: 'ADRC Rental Index (adrc.gov.ae)',
    svcAuth: 'ADRC rate card (Abu Dhabi Regulation & Compliance)',
  },
}

export const BRAND_COLORS = {
  navy: '#0b0736',
  navyMid: '#2a1548',
  purple: '#7c5cbf',
  purpleDark: '#5b3d8f',
  gold: '#f59e0b',
  goldLegacy: '#FAA300',
  gray: '#94a3b8',
  green: '#22c55e',
  blue: '#3b82f6',
  red: '#ef4444',
  dim: 'rgba(255,255,255,.65)',
}

export const DEFAULT_FORM = {
  clientName: '',
  agentName: '',
  projectName: '',
  unitRef: '',
  currency: 'AED',
  emirate: 'dubai',
  pp: '',
  paymentType: 'mortgage',
  downPct: '20',
  mortgageRate: '4.5',
  mortgageTerm: '25',
  agencyPct: '2',
  otherAcqCosts: '5000',
  annualRent: '',
  vacancyRate: '5',
  serviceCharge: '',
  mgmtPct: '5',
  maintenance: '5000',
  insurance: '3000',
  appRate: '5',
  holdYears: '5',
}

export const MAX_MGMT_FEE_PCT = 5
export const RENT_ESCALATION_PCT = 5
