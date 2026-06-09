export const EMIRATES = {
  dubai: {
    id: 'dubai',
    label: 'Dubai',
    emoji: '🏙',
    registrationFeeLabel: 'DLD Transfer Fee',
    registrationFeePct: 4,
    mortgageRegistration: 4200,
    authority: 'RERA',
    rentalIndex: 'RERA Rental Index',
    appreciationIndex: 'RERA Property Price Index',
    serviceChargeHint: 'Based on RERA service charge index',
    rentalHint: 'Reference: RERA Rental Index',
    appreciationHint: 'Reference: RERA Property Price Index',
  },
  abuDhabi: {
    id: 'abuDhabi',
    label: 'Abu Dhabi',
    emoji: '🕌',
    registrationFeeLabel: 'ADM Registration Fee',
    registrationFeePct: 2,
    mortgageRegistration: 1500,
    authority: 'ADREC',
    rentalIndex: 'ADREC Rental Index',
    appreciationIndex: 'ADREC Property Index',
    serviceChargeHint: 'Based on ADREC service charge standards',
    rentalHint: 'Reference: ADREC Rental Index',
    appreciationHint: 'Reference: ADREC Property Index',
  },
}

export const DEFAULT_INPUTS = {
  emirate: 'dubai',
  purchasePrice: 2500000,
  closingCosts: 15000,
  rehabCost: 0,
  agencyFeePct: 2,
  downPaymentPct: 25,
  mortgageAmount: null,
  interestRate: 4.49,
  mortgageTermYears: 25,
  isUaeNational: false,
  annualRentalIncome: 120000,
  serviceCharges: 18000,
  maintenance: 8000,
  insurance: 3500,
  utilities: 0,
  propertyManagementFeePct: 5,
  annualAppreciationPct: 5,
  holdPeriodYears: 5,
}

export const RENT_ESCALATION_PCT = 5
export const MAX_MGMT_FEE_PCT = 5
export const MORTGAGE_TERM_EXPAT = 25
export const MORTGAGE_TERM_NATIONAL = 30
