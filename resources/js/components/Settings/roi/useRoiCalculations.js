import { computed, reactive, ref } from 'vue'
import { BRAND_COLORS, DEFAULT_FORM, EMIRATES, MAX_MGMT_FEE_PCT, RENT_ESCALATION_PCT } from './uaeMarketConfig.js'

export const C = BRAND_COLORS

export function num(s) {
  return parseFloat(s) || 0
}

export function fmt(n, c = 'AED') {
  return `${c} ${Number(n || 0).toLocaleString('en-AE', { maximumFractionDigits: 0 })}`
}

export function fmtp(n) {
  return `${Number(n || 0).toFixed(1)}%`
}

export function calcROI(f) {
  const pp = num(f.pp)
  const isCash = f.paymentType === 'cash'
  const dpPct = num(f.downPct)
  const down = isCash ? pp : pp * dpPct / 100
  const loan = pp - down
  const rate = num(f.mortgageRate)
  const termYrs = num(f.mortgageTerm)
  const mr = rate / 100 / 12
  const n = termYrs * 12
  const monthly = isCash ? 0 : (mr > 0 ? loan * (mr * Math.pow(1 + mr, n)) / (Math.pow(1 + mr, n) - 1) : loan / n)
  const totalInterest = isCash ? 0 : monthly * n - loan
  const E = EMIRATES[f.emirate] || EMIRATES.dubai
  const dldFee = pp * E.transferFeePct / 100
  const agencyFee = pp * num(f.agencyPct) / 100
  const mortgageFee = isCash ? 0 : loan * 0.01
  const registrationFee = isCash ? 0 : E.mortgageRegFee
  const otherCosts = num(f.otherAcqCosts)
  const totalAcqCosts = dldFee + agencyFee + mortgageFee + registrationFee + otherCosts
  const totalCashInvested = down + totalAcqCosts
  const totalCostBasis = pp + totalAcqCosts
  const grossRent = num(f.annualRent)
  const vacancyLoss = grossRent * num(f.vacancyRate) / 100
  const netRent = grossRent - vacancyLoss
  const svcCharge = num(f.serviceCharge)
  const mgmtFee = netRent * num(f.mgmtPct) / 100
  const maintenance = num(f.maintenance)
  const insurance = num(f.insurance)
  const totalOpEx = svcCharge + mgmtFee + maintenance + insurance
  const noi = netRent - totalOpEx
  const annualDebtSvc = monthly * 12
  const annualCashFlow = noi - annualDebtSvc
  const monthlyCF = annualCashFlow / 12
  const capRate = totalCostBasis > 0 ? noi / totalCostBasis * 100 : 0
  const cashOnCash = totalCashInvested > 0 ? annualCashFlow / totalCashInvested * 100 : 0
  const grm = grossRent > 0 ? pp / grossRent : 0
  const onePercent = pp > 0 ? grossRent / 12 / pp * 100 : 0
  const impliedYield = pp > 0 ? grossRent / pp * 100 : 0
  const holdYrs = num(f.holdYears)
  const appPct = num(f.appRate)
  const RENT_ESC = RENT_ESCALATION_PCT / 100
  const yearRows = []
  let cum = 0
  const yrs = Math.min(holdYrs, 10)

  for (let y = 1; y <= yrs; y += 1) {
    const val = pp * Math.pow(1 + appPct / 100, y)
    const capitalGain = val - pp
    const balance = isCash ? 0 : (mr > 0
      ? loan * (Math.pow(1 + mr, n) - Math.pow(1 + mr, y * 12)) / (Math.pow(1 + mr, n) - 1)
      : Math.max(0, loan * (1 - y / termYrs)))
    const equity = val - balance
    const yGross = grossRent * Math.pow(1 + RENT_ESC, y - 1)
    const yVac = yGross * num(f.vacancyRate) / 100
    const yNet = yGross - yVac
    const yMgmt = yNet * num(f.mgmtPct) / 100
    const yNOI = yNet - svcCharge - yMgmt - maintenance - insurance
    cum += yNOI
    const totalReturn = capitalGain + cum
    const totalROIy = totalCashInvested > 0 ? totalReturn / totalCashInvested * 100 : 0
    const roeCapital = totalCashInvested > 0 ? capitalGain / totalCashInvested * 100 : 0
    yearRows.push({
      yr: y, val, capitalGain, balance, equity, yGross, yNOI, cum, totalReturn, roeCapital, totalROI: totalROIy, isLast: y === yrs,
    })
  }

  const fy = yearRows[yearRows.length - 1] || {}
  const totalROI = fy.totalROI || 0
  const annualizedROI = totalCashInvested > 0 && holdYrs > 0
    ? (Math.pow(1 + totalROI / 100, 1 / holdYrs) - 1) * 100
    : 0

  return {
    pp, isCash, down, loan, rate, termYrs, monthly, totalInterest,
    dldFee, agencyFee, mortgageFee, registrationFee, otherCosts,
    totalAcqCosts, totalCashInvested, totalCostBasis,
    grossRent, vacancyLoss, netRent, svcCharge, mgmtFee, maintenance, insurance,
    totalOpEx, noi, annualDebtSvc, annualCashFlow, monthlyCF,
    capRate, cashOnCash, grm, onePercent, impliedYield,
    holdYrs, appPct, yearRows, totalROI, annualizedROI,
    cur: f.currency, em: E, f: { ...f },
  }
}

export function useRoiCalculations() {
  const form = reactive({ ...DEFAULT_FORM })
  const result = ref(null)
  const err = ref('')

  const em = computed(() => EMIRATES[form.emirate] || EMIRATES.dubai)

  const today = computed(() => new Date().toLocaleDateString('en-AE', {
    day: 'numeric', month: 'long', year: 'numeric',
  }))

  function hydrateAgentName() {
    try {
      const user = JSON.parse(localStorage.getItem('user') || 'null')
      if (user?.name && !form.agentName) form.agentName = user.name
    } catch { /* ignore */ }
  }

  function run() {
    err.value = ''
    if (!num(form.pp)) {
      err.value = 'Enter a purchase price.'
      return false
    }
    if (form.paymentType === 'mortgage' && num(form.downPct) < 20) {
      err.value = 'UAE minimum down payment is 20% for expats / 15% for nationals on first property.'
      return false
    }
    if (num(form.mgmtPct) > MAX_MGMT_FEE_PCT) {
      err.value = 'Property management fee cannot exceed 5% — UAE market maximum.'
      return false
    }
    result.value = calcROI(form)
    return true
  }

  const kpis = computed(() => {
    const x = result.value
    if (!x) return []
    const c = x.cur
    const cfTone = x.annualCashFlow >= 0 ? 'green' : 'red'
    const arr = [
      { l: 'Purchase Price', v: fmt(x.pp, c), tone: 'muted' },
      { l: 'Cash Invested', v: fmt(x.totalCashInvested, c), tone: 'blue', s: x.isCash ? 'Full cash' : `${fmt(x.down, c)} down` },
    ]
    if (!x.isCash) {
      arr.push({ l: 'Monthly Payment', v: fmt(x.monthly, c), tone: 'blue', s: `${x.termYrs}yrs @ ${fmtp(x.rate)} p.a.` })
    }
    arr.push(
      { l: 'Annual Cash Flow', v: fmt(x.annualCashFlow, c), tone: cfTone, s: `${fmt(x.monthlyCF, c)} / mo` },
      { l: 'Cash-on-Cash', v: fmtp(x.cashOnCash), accent: true, s: `On ${fmt(x.totalCashInvested, c)}` },
      { l: 'Cap Rate', v: fmtp(x.capRate), tone: 'blue', s: 'NOI / total cost' },
      { l: `${x.holdYrs}-Yr Total ROI`, v: fmtp(x.totalROI), accent: true, s: `${fmtp(x.annualizedROI)} annualised` },
    )
    return arr
  })

  const acqRows = computed(() => {
    const x = result.value
    if (!x) return []
    const c = x.cur
    const E = x.em
    const f = x.f
    const rows = [
      { item: 'Purchase Price', amount: fmt(x.pp, c), note: '', bold: true, amountBold: true },
      { item: `${E.transferFeeLabel} (${E.transferFeePct}%)`, amount: fmt(x.dldFee, c), note: `${E.regAuthority} — mandatory` },
      { item: `Agency Commission (${f.agencyPct}%)`, amount: fmt(x.agencyFee, c), note: 'Standard UAE agency fee' },
    ]
    if (!x.isCash) {
      rows.push(
        { item: 'Mortgage Arrangement Fee (~1%)', amount: fmt(x.mortgageFee, c), note: 'Bank fee on loan amount' },
        { item: E.mortgageRegLabel, amount: fmt(x.registrationFee, c), note: `${E.label} mortgage registration` },
      )
    }
    rows.push(
      { item: 'Other Costs', amount: fmt(x.otherCosts, c), note: 'NOC, conveyancing, admin' },
      { item: 'Total Acquisition Costs', amount: fmt(x.totalAcqCosts, c), note: 'On top of purchase price', total: true, amountBold: true },
      { item: 'Total Cash Invested', amount: fmt(x.totalCashInvested, c), note: x.isCash ? 'Full cash purchase' : 'Down payment + all costs', total: true, amountBold: true, big: true },
    )
    return rows
  })

  const mortItems = computed(() => {
    const x = result.value
    if (!x || x.isCash) return []
    const c = x.cur
    return [
      ['Loan Amount', fmt(x.loan, c)],
      ['Interest Rate', `${fmtp(x.rate)} p.a.`],
      ['Loan Term', `${x.termYrs} years`],
      ['Monthly Payment', fmt(x.monthly, c)],
      ['Total Repaid', fmt(x.monthly * x.termYrs * 12, c)],
      ['Total Interest Cost', fmt(x.totalInterest, c)],
    ]
  })

  const incomeRows = computed(() => {
    const x = result.value
    if (!x) return []
    const c = x.cur
    const f = x.f
    const E = x.em
    const cfTone = x.annualCashFlow >= 0 ? 'green' : 'red'
    const money = (nn) => `${nn < 0 ? '− ' : ''}${fmt(Math.abs(nn), c)}`
    const rows = [
      { label: 'Gross Rental Income (Yr 1)', col1: fmt(x.grossRent, c), col2: fmt(x.grossRent / 12, c), valTone: 'green', bold: true },
      { label: 'Implied Gross Yield', col1: fmtp(x.impliedYield), col2: 'on purchase price', valTone: 'blue', col2Tone: 'muted' },
      { label: `Vacancy Loss (${f.vacancyRate}%)`, col1: money(-x.vacancyLoss), col2: money(-x.vacancyLoss / 12), valTone: 'red', bg: 'red' },
      { label: 'Net Rental Income', col1: fmt(x.netRent, c), col2: fmt(x.netRent / 12, c), valTone: 'green', bold: true },
      { label: `Service Charge (${E.label === 'Dubai' ? 'RERA' : 'ADRC'})`, col1: money(-x.svcCharge), col2: money(-x.svcCharge / 12), valTone: 'red', bg: 'red' },
      { label: `Property Management (${f.mgmtPct}%)`, col1: money(-x.mgmtFee), col2: money(-x.mgmtFee / 12), valTone: 'red', bg: 'red' },
      { label: 'Maintenance & Repairs', col1: money(-x.maintenance), col2: money(-x.maintenance / 12), valTone: 'red', bg: 'red' },
      { label: 'Insurance', col1: money(-x.insurance), col2: money(-x.insurance / 12), valTone: 'red', bg: 'red' },
      { label: 'Net Operating Income — Yr 1 (NOI)', col1: fmt(x.noi, c), col2: fmt(x.noi / 12, c), valTone: 'gold', bold: true, emphasis: true, bg: 'hl' },
    ]
    if (!x.isCash) {
      rows.push({ label: 'Annual Debt Service', col1: money(-x.annualDebtSvc), col2: money(-x.monthly), valTone: 'red', bg: 'red' })
    }
    rows.push({ label: 'Net Annual Cash Flow — Yr 1', col1: fmt(x.annualCashFlow, c), col2: fmt(x.monthlyCF, c), valTone: cfTone, bold: true, emphasis: true, bg: 'hl' })
    return rows
  })

  const ratios = computed(() => {
    const x = result.value
    if (!x) return []
    return [
      { l: 'Cap Rate', v: fmtp(x.capRate), h: 'NOI / Total Cost', tone: 'blue' },
      { l: 'Cash-on-Cash', v: fmtp(x.cashOnCash), h: 'CF / Cash Invested', tone: 'green' },
      { l: 'Gross Rent Multiplier', v: `${x.grm.toFixed(1)}x`, h: 'Price / Gross Rent', tone: 'muted' },
      { l: '1% Rule', v: fmtp(x.onePercent), h: 'Mo Rent / Price', tone: 'muted' },
    ]
  })

  function rowClass(bg) {
    if (bg === 'hl') return 'roi-calc__row--hl'
    if (bg === 'red') return 'roi-calc__row--red'
    return ''
  }

  function valClass(tone) {
    if (!tone) return ''
    const map = { green: 'roi-calc__td-green', red: 'roi-calc__td-red', blue: 'roi-calc__td-blue', gold: 'roi-calc__td-gold', muted: 'roi-calc__td-muted' }
    return map[tone] || ''
  }

  return {
    form,
    result,
    err,
    em,
    today,
    kpis,
    acqRows,
    mortItems,
    incomeRows,
    ratios,
    rowClass,
    valClass,
    run,
    hydrateAgentName,
    fmt,
    fmtp,
  }
}
