import { computed, ref, watch } from 'vue'
import {
  EMIRATES,
  DEFAULT_INPUTS,
  RENT_ESCALATION_PCT,
  MAX_MGMT_FEE_PCT,
  MORTGAGE_TERM_EXPAT,
  MORTGAGE_TERM_NATIONAL,
} from './uaeMarketConfig.js'

function clamp(value, min, max) {
  return Math.min(Math.max(value, min), max)
}

function monthlyPayment(principal, annualRate, years) {
  if (!principal || principal <= 0 || !years) return 0
  const monthlyRate = annualRate / 100 / 12
  const n = years * 12
  if (monthlyRate === 0) return principal / n
  return (principal * monthlyRate * (1 + monthlyRate) ** n) / ((1 + monthlyRate) ** n - 1)
}

function remainingBalance(principal, annualRate, years, paymentsMade) {
  if (!principal || principal <= 0) return 0
  const monthlyRate = annualRate / 100 / 12
  const n = years * 12
  if (paymentsMade >= n) return 0
  if (monthlyRate === 0) return Math.max(0, principal - (principal / n) * paymentsMade)
  const pmt = monthlyPayment(principal, annualRate, years)
  return (
    principal * (1 + monthlyRate) ** paymentsMade -
    pmt * (((1 + monthlyRate) ** paymentsMade - 1) / monthlyRate)
  )
}

export function formatAed(value, compact = false) {
  if (value == null || Number.isNaN(value)) return 'AED 0'
  const abs = Math.abs(value)
  if (compact && abs >= 1_000_000) {
    return `AED ${(value / 1_000_000).toFixed(2)}M`
  }
  if (compact && abs >= 1_000) {
    return `AED ${(value / 1_000).toFixed(1)}K`
  }
  return new Intl.NumberFormat('en-AE', {
    style: 'currency',
    currency: 'AED',
    maximumFractionDigits: 0,
  }).format(value)
}

export function formatPct(value, digits = 2) {
  if (value == null || Number.isNaN(value)) return '0%'
  return `${value.toFixed(digits)}%`
}

export function useRoiCalculations(initial = {}) {
  const inputs = ref({ ...DEFAULT_INPUTS, ...initial })

  const emirateConfig = computed(() => EMIRATES[inputs.value.emirate] || EMIRATES.dubai)

  const maxMortgageTerm = computed(() =>
    inputs.value.isUaeNational ? MORTGAGE_TERM_NATIONAL : MORTGAGE_TERM_EXPAT,
  )

  watch(
    () => inputs.value.isUaeNational,
    () => {
      if (inputs.value.mortgageTermYears > maxMortgageTerm.value) {
        inputs.value.mortgageTermYears = maxMortgageTerm.value
      }
    },
  )

  watch(
    () => inputs.value.propertyManagementFeePct,
    (val) => {
      if (val > MAX_MGMT_FEE_PCT) {
        inputs.value.propertyManagementFeePct = MAX_MGMT_FEE_PCT
      }
    },
  )

  watch(
    () => [inputs.value.purchasePrice, inputs.value.downPaymentPct],
    () => {
      const price = Number(inputs.value.purchasePrice) || 0
      const downPct = Number(inputs.value.downPaymentPct) || 0
      inputs.value.mortgageAmount = Math.round(price * (1 - downPct / 100))
    },
    { immediate: true },
  )

  const registrationFee = computed(() => {
    const price = Number(inputs.value.purchasePrice) || 0
    return Math.round(price * (emirateConfig.value.registrationFeePct / 100))
  })

  const agencyFee = computed(() => {
    const price = Number(inputs.value.purchasePrice) || 0
    return Math.round(price * ((Number(inputs.value.agencyFeePct) || 0) / 100))
  })

  const downPaymentAmount = computed(() => {
    const price = Number(inputs.value.purchasePrice) || 0
    return Math.round(price * ((Number(inputs.value.downPaymentPct) || 0) / 100))
  })

  const totalAcquisitionCost = computed(() => {
    return (
      downPaymentAmount.value +
      registrationFee.value +
      emirateConfig.value.mortgageRegistration +
      agencyFee.value +
      (Number(inputs.value.closingCosts) || 0) +
      (Number(inputs.value.rehabCost) || 0)
    )
  })

  const monthlyMortgage = computed(() =>
    monthlyPayment(
      Number(inputs.value.mortgageAmount) || 0,
      Number(inputs.value.interestRate) || 0,
      Number(inputs.value.mortgageTermYears) || 0,
    ),
  )

  const annualDebtService = computed(() => monthlyMortgage.value * 12)

  function rentForYear(yearIndex) {
    const base = Number(inputs.value.annualRentalIncome) || 0
    return base * (1 + RENT_ESCALATION_PCT / 100) ** (yearIndex - 1)
  }

  function operatingExpensesForYear(rent) {
    const mgmtPct = clamp(Number(inputs.value.propertyManagementFeePct) || 0, 0, MAX_MGMT_FEE_PCT)
    const fixed =
      (Number(inputs.value.serviceCharges) || 0) +
      (Number(inputs.value.maintenance) || 0) +
      (Number(inputs.value.insurance) || 0) +
      (Number(inputs.value.utilities) || 0)
    return fixed + rent * (mgmtPct / 100)
  }

  const yearOneRent = computed(() => rentForYear(1))
  const yearOneNoi = computed(() => yearOneRent.value - operatingExpensesForYear(yearOneRent.value))
  const yearOneCashFlow = computed(() => yearOneNoi.value - annualDebtService.value)

  const capRate = computed(() => {
    const price = Number(inputs.value.purchasePrice) || 0
    if (!price) return 0
    return (yearOneNoi.value / price) * 100
  })

  const grossRentalYield = computed(() => {
    const price = Number(inputs.value.purchasePrice) || 0
    if (!price) return 0
    return (yearOneRent.value / price) * 100
  })

  const grm = computed(() => {
    if (!yearOneRent.value) return 0
    return (Number(inputs.value.purchasePrice) || 0) / yearOneRent.value
  })

  const onePctRule = computed(() => {
    const price = Number(inputs.value.purchasePrice) || 0
    if (!price) return 0
    return ((yearOneRent.value / 12) / price) * 100
  })

  const cashOnCash = computed(() => {
    if (!totalAcquisitionCost.value) return 0
    return (yearOneCashFlow.value / totalAcquisitionCost.value) * 100
  })

  const yearlyProjections = computed(() => {
    const years = clamp(Number(inputs.value.holdPeriodYears) || 1, 1, 30)
    const appreciation = (Number(inputs.value.annualAppreciationPct) || 0) / 100
    const purchase = Number(inputs.value.purchasePrice) || 0
    const principal = Number(inputs.value.mortgageAmount) || 0
    const rate = Number(inputs.value.interestRate) || 0
    const term = Number(inputs.value.mortgageTermYears) || 0

    let cumulativeNoi = 0
    let cumulativeCashFlow = 0
    const rows = []

    for (let year = 1; year <= years; year += 1) {
      const rent = rentForYear(year)
      const opex = operatingExpensesForYear(rent)
      const noi = rent - opex
      cumulativeNoi += noi
      const cashFlow = noi - annualDebtService.value
      cumulativeCashFlow += cashFlow

      const propertyValue = purchase * (1 + appreciation) ** year
      const appreciationGain = propertyValue - purchase
      const loanBalance = remainingBalance(principal, rate, term, year * 12)
      const totalEquity = propertyValue - loanBalance

      rows.push({
        year,
        propertyValue,
        annualRent: rent,
        noi,
        cumulativeNoi,
        appreciationGain,
        totalEquity,
        cashFlow,
        cumulativeCashFlow,
        loanBalance,
      })
    }

    return rows
  })

  const finalProjection = computed(() => {
    const rows = yearlyProjections.value
    return rows.length ? rows[rows.length - 1] : null
  })

  const totalRoi = computed(() => {
    const final = finalProjection.value
    if (!final || !totalAcquisitionCost.value) return 0
    const totalReturn =
      final.totalEquity + final.cumulativeCashFlow - totalAcquisitionCost.value
    return (totalReturn / totalAcquisitionCost.value) * 100
  })

  const annualizedRoi = computed(() => {
    const years = yearlyProjections.value.length
    if (!years || totalRoi.value <= -100) return 0
    return ((1 + totalRoi.value / 100) ** (1 / years) - 1) * 100
  })

  const kpis = computed(() => ({
    totalRoi: totalRoi.value,
    monthlyCashFlow: yearOneCashFlow.value / 12,
    capRate: capRate.value,
    cashOnCash: cashOnCash.value,
    annualizedRoi: annualizedRoi.value,
    grossRentalYield: grossRentalYield.value,
    grm: grm.value,
    onePctRule: onePctRule.value,
    noi: yearOneNoi.value,
  }))

  const chartData = computed(() => {
    const rows = yearlyProjections.value
    const labels = rows.map((r) => `Y${r.year}`)
    return {
      labels,
      roiGrowth: rows.map((_, i) => {
        const slice = rows.slice(0, i + 1)
        const last = slice[slice.length - 1]
        const invested = totalAcquisitionCost.value
        if (!invested) return 0
        return ((last.totalEquity + last.cumulativeCashFlow - invested) / invested) * 100
      }),
      propertyValue: rows.map((r) => r.propertyValue),
      rentalIncome: rows.map((r) => r.annualRent),
      equity: rows.map((r) => r.totalEquity),
    }
  })

  function setEmirate(id) {
    if (EMIRATES[id]) inputs.value.emirate = id
  }

  function resetInputs() {
    inputs.value = { ...DEFAULT_INPUTS }
  }

  return {
    inputs,
    emirateConfig,
    maxMortgageTerm,
    registrationFee,
    agencyFee,
    downPaymentAmount,
    totalAcquisitionCost,
    monthlyMortgage,
    yearlyProjections,
    kpis,
    chartData,
    setEmirate,
    resetInputs,
    formatAed,
    formatPct,
  }
}
