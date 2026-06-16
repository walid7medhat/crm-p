import { computed, reactive, ref } from 'vue'
import { fmt, fmtp } from '../roi/useRoiCalculations.js'

export { fmt, fmtp }

export const DEFAULT_ROE_FORM = {
  clientName: '',
  agentName: '',
  projectName: '',
  unitRef: '',
  currency: 'AED',
  pp: '',
  downPct: '5',
  handoverPct: '35',
  signingDate: '',
  handoverDate: '',
  appA: '18.75',
  appB: '30',
  downPctR: '20',
  mortgageRate: '4.5',
  mortgageTerm: '25',
  appAReady: '6',
  holdYears: '5',
  rentalYield: '6.5',
  serviceCharge: '',
}

let instId = 0
export function createInstalment(overrides = {}) {
  return { id: instId++, label: overrides.label || 'Instalment', pct: overrides.pct || '', date: overrides.date || '' }
}

export function num(s) {
  return parseFloat(s) || 0
}

export function fmtDt(s) {
  if (!s) return '—'
  const d = new Date(s)
  return Number.isNaN(d.getTime()) ? s : d.toLocaleDateString('en-AE', { day: 'numeric', month: 'short', year: 'numeric' })
}

export function calcOffPlan(form, insts) {
  const pp = num(form.pp)
  const dpPct = num(form.downPct)
  const hoPct = num(form.handoverPct)
  const conPct = insts.reduce((s, r) => s + num(r.pct), 0)
  const down = pp * dpPct / 100
  const handoverDue = pp * hoPct / 100
  const totalPaidA = pp * (dpPct + conPct) / 100
  const totalPaidB = pp
  const appA = num(form.appA)
  const appB = num(form.appB)
  const exitA = pp * (1 + appA / 100)
  const gainA = exitA - pp
  const roeA = totalPaidA > 0 ? (gainA / totalPaidA) * 100 : 0
  const exitB = pp * (1 + appB / 100)
  const gainB = exitB - pp
  const roeB = totalPaidB > 0 ? (gainB / totalPaidB) * 100 : 0
  const n = insts.length
  const rows = []
  let cum = down
  insts.forEach((inst, i) => {
    const pct = num(inst.pct)
    const amount = pp * pct / 100
    cum += amount
    const frac = (i + 1) / n
    const vA = pp * (1 + (appA * frac) / 100)
    const vB = pp * (1 + (appB * frac) / 100)
    const gA = vA - pp
    const gB = vB - pp
    const pgA = i > 0 ? rows[i - 1].gA : 0
    const pgB = i > 0 ? rows[i - 1].gB : 0
    rows.push({
      label: inst.label,
      pct,
      date: inst.date,
      amount,
      cum,
      vA,
      vB,
      ygA: gA - pgA,
      ygB: gB - pgB,
      gA,
      gB,
      roeA: cum > 0 ? (gA / cum) * 100 : 0,
      roeB: cum > 0 ? (gB / cum) * 100 : 0,
      isLast: i === n - 1,
    })
  })
  return {
    mode: 'offplan',
    pp,
    dpPct,
    conPct,
    hoPct,
    down,
    handoverDue,
    totalPaidA,
    totalPaidB,
    appA,
    appB,
    exitA,
    gainA,
    roeA,
    exitB,
    gainB,
    roeB,
    rows,
    cur: form.currency,
  }
}

export function calcReady(form, paymentType) {
  const pp = num(form.pp)
  const dpPct = num(form.downPctR)
  const rate = num(form.mortgageRate)
  const termYrs = num(form.mortgageTerm)
  const appPct = num(form.appAReady)
  const holdYrs = num(form.holdYears) || 5
  const rentalYield = num(form.rentalYield)
  const svcCharge = num(form.serviceCharge)
  const isCash = paymentType === 'cash'
  const down = isCash ? pp : (pp * dpPct) / 100
  const loan = pp - down
  const mr = rate / 100 / 12
  const nt = termYrs * 12
  const monthly = isCash ? 0 : (mr > 0 ? loan * (mr * (1 + mr) ** nt) / ((1 + mr) ** nt - 1) : nt > 0 ? loan / nt : 0)
  const totalInterest = isCash ? 0 : Math.max(0, monthly * nt - loan)
  const netRental = (pp * rentalYield) / 100 - svcCharge
  const capitalGain = pp * (1 + appPct / 100) ** holdYrs - pp
  const base = isCash ? pp : down
  const roeOnDown = base > 0 ? (capitalGain / base) * 100 : 0
  const yearRows = []
  const maxY = Math.min(holdYrs, 10)
  for (let y = 1; y <= maxY; y += 1) {
    const val = pp * (1 + appPct / 100) ** y
    const cg = val - pp
    const balance = isCash ? 0 : (mr > 0
      ? loan * ((1 + mr) ** nt - (1 + mr) ** (y * 12)) / ((1 + mr) ** nt - 1)
      : Math.max(0, loan * (1 - y / termYrs)))
    const equity = val - balance
    const cashIn = isCash ? pp : down + monthly * 12 * y
    const cumRental = netRental * y
    const roeCapital = base > 0 ? (cg / base) * 100 : 0
    const totalRoe = base > 0 ? ((cg + cumRental) / base) * 100 : 0
    yearRows.push({
      yr: y, val, capitalGain: cg, balance, equity, cashIn, cumRental, roeCapital, totalRoe, isLast: y === maxY,
    })
  }
  return {
    mode: 'ready',
    pp,
    down,
    loan,
    rate,
    termYrs,
    monthly,
    totalInterest,
    appPct,
    holdYrs,
    rentalYield,
    netRental,
    capitalGain,
    roeOnDown,
    isCash,
    yearRows,
    cur: form.currency,
  }
}

export function useRoeCalculations() {
  const mode = ref('offplan')
  const paymentType = ref('mortgage')
  const form = reactive({ ...DEFAULT_ROE_FORM })
  const insts = reactive([createInstalment({ label: 'Instalment 1' })])
  const result = ref(null)
  const err = ref('')

  const allocTotal = computed(() => num(form.downPct) + insts.reduce((s, r) => s + num(r.pct), 0) + num(form.handoverPct))
  const allocOk = computed(() => Math.abs(allocTotal.value - 100) < 0.01)

  const today = computed(() => new Date().toLocaleDateString('en-AE', {
    day: 'numeric', month: 'long', year: 'numeric',
  }))

  function hydrateAgentName() {
    try {
      const user = JSON.parse(localStorage.getItem('user') || 'null')
      if (user?.name && !form.agentName) form.agentName = user.name
    } catch { /* ignore */ }
  }

  function addInst() {
    insts.push(createInstalment({ label: `Instalment ${insts.length + 1}` }))
  }

  function removeInst(id) {
    const i = insts.findIndex((r) => r.id === id)
    if (i >= 0) insts.splice(i, 1)
  }

  function setMode(m) {
    mode.value = m
    result.value = null
    err.value = ''
  }

  function setPay(p) {
    paymentType.value = p
  }

  function run() {
    err.value = ''
    if (!num(form.pp)) {
      err.value = 'Enter a purchase price.'
      return false
    }
    if (mode.value === 'offplan') {
      if (!allocOk.value) {
        err.value = `Percentages must total 100%. Currently: ${fmtp(allocTotal.value)}`
        return false
      }
      if (insts.length === 0) {
        err.value = 'Add at least one instalment.'
        return false
      }
      result.value = { ...calcOffPlan(form, insts), f: { ...form } }
    } else {
      result.value = { ...calcReady(form, paymentType.value), f: { ...form } }
    }
    return true
  }

  const offplanKpis = computed(() => {
    const x = result.value
    if (!x || x.mode !== 'offplan') return []
    const c = x.cur
    return [
      { l: 'Purchase Price', v: fmt(x.pp, c), tone: 'muted' },
      { l: 'Invested — Scen A', v: fmt(x.totalPaidA, c), tone: 'green', s: `${fmtp(x.dpPct + x.conPct)} · exits early` },
      { l: 'Invested — Scen B', v: fmt(x.totalPaidB, c), tone: 'blue', s: '100% · holds to handover' },
      { l: 'ROE — Scenario A', v: fmtp(x.roeA), accent: true, s: `At ${fmtp(x.appA)} appreciation` },
      { l: 'ROE — Scenario B', v: fmtp(x.roeB), accent: true, s: `At ${fmtp(x.appB)} appreciation` },
    ]
  })

  const readyKpis = computed(() => {
    const x = result.value
    if (!x || x.mode !== 'ready') return []
    const c = x.cur
    const arr = [{ l: 'Purchase Price', v: fmt(x.pp, c), tone: 'muted' }]
    if (!x.isCash) {
      arr.push({ l: 'Monthly Payment', v: fmt(x.monthly, c), tone: 'blue', s: `${x.termYrs}-yr @ ${fmtp(x.rate)} p.a.` })
      arr.push({ l: 'Total Interest', v: fmt(x.totalInterest, c), tone: 'red', s: 'Cost of finance' })
    }
    arr.push(
      { l: 'Capital Gain', v: fmt(x.capitalGain, c), tone: 'green', s: `${x.holdYrs} yrs @ ${fmtp(x.appPct)} p.a.` },
      { l: 'ROE on Equity', v: fmtp(x.roeOnDown), accent: true, s: `On ${fmt(x.isCash ? x.pp : x.down, c)}` },
      { l: 'Annual Net Rent', v: fmt(x.netRental, c), tone: 'blue', s: `${fmtp(x.rentalYield)} yield` },
    )
    return arr
  })

  const scenarioA = computed(() => {
    const x = result.value
    if (!x || x.mode !== 'offplan') return null
    const c = x.cur
    const f = x.f
    return {
      title: 'Scenario A · Pre-Handover Exit',
      sub: `Exits at last instalment — skips ${fmtp(x.hoPct)} handover`,
      tone: 'green',
      items: [
        ['Appreciation assumed', fmtp(x.appA)],
        ['Total cash invested', fmt(x.totalPaidA, c)],
        ['Handover payment', 'Not paid — exits early'],
        ['Exit / sale price', fmt(x.exitA, c)],
        ['Capital gain', fmt(x.gainA, c), true],
        ['ROE on invested capital', fmtp(x.roeA), true],
      ],
      signingDate: f.signingDate,
    }
  })

  const scenarioB = computed(() => {
    const x = result.value
    if (!x || x.mode !== 'offplan') return null
    const c = x.cur
    const f = x.f
    return {
      title: 'Scenario B · Holds to Handover',
      sub: 'Pays full 100% — sells after completion',
      tone: 'blue',
      items: [
        ['Appreciation assumed', fmtp(x.appB)],
        ['Total cash invested', fmt(x.totalPaidB, c)],
        ['Handover payment', fmt(x.handoverDue, c)],
        ['Exit / sale price', fmt(x.exitB, c)],
        ['Capital gain', fmt(x.gainB, c), true],
        ['ROE on invested capital', fmtp(x.roeB), true],
      ],
      handoverDate: f.handoverDate,
    }
  })

  const mortItems = computed(() => {
    const x = result.value
    if (!x || x.mode !== 'ready' || x.isCash) return []
    const c = x.cur
    return [
      ['Loan Amount', fmt(x.loan, c)],
      ['Interest Rate', `${fmtp(x.rate)} p.a.`],
      ['Loan Term', `${x.termYrs} years`],
      ['Monthly Payment', fmt(x.monthly, c)],
      ['Total Repaid', fmt(x.monthly * x.termYrs * 12, c)],
      ['Total Interest', fmt(x.totalInterest, c)],
    ]
  })

  return {
    mode,
    paymentType,
    form,
    insts,
    result,
    err,
    allocTotal,
    allocOk,
    today,
    offplanKpis,
    readyKpis,
    scenarioA,
    scenarioB,
    mortItems,
    hydrateAgentName,
    addInst,
    removeInst,
    setMode,
    setPay,
    run,
    fmtDt,
  }
}
