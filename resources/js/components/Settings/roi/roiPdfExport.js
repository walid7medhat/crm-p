import { fmt, fmtp } from './useRoiCalculations.js'
import {
  C,
  th,
  td,
  sectionTitle,
  tableStyle,
  rowBg,
  kpiRow,
  metaGrid,
  pdfHeader,
  pdfDisclaimer,
  pdfShell,
  downloadPdfHtml,
  exportPdfFromElement,
} from '../calculatorPdfTheme.js'

export { exportPdfFromElement as exportRoiPdfFromElement } from '../calculatorPdfTheme.js'

export function buildRoiPdfHtml(r, todayLabel) {
  const c = r.cur
  const E = r.em
  const f = r.f
  const ts = tableStyle()

  let body = kpiRow([
    { label: 'Purchase Price', value: fmt(r.pp, c) },
    { label: 'Total Cash Invested', value: fmt(r.totalCashInvested, c), color: C.green },
    {
      label: 'Annual Cash Flow',
      value: fmt(r.annualCashFlow, c),
      color: r.annualCashFlow >= 0 ? C.green : C.red,
    },
    { label: 'Cap Rate', value: fmtp(r.capRate), color: C.blue },
    { label: 'Cash-on-Cash', value: fmtp(r.cashOnCash), color: C.purple },
    { label: 'Total ROI', value: fmtp(r.totalROI), accent: true },
  ])

  body += sectionTitle('Acquisition Cost Breakdown')
  body += `<table style="${ts}"><tr>${th('Cost Item')}${th('Amount', 'purple', 'right')}${th('Notes', 'purple')}</tr>`
  const ar = [
    ['Purchase Price', fmt(r.pp, c), ''],
    [`${E.transferFeeLabel} (${E.transferFeePct}%)`, fmt(r.dldFee, c), `${E.regAuthority} — mandatory`],
    ['Agency Commission', fmt(r.agencyFee, c), `${f.agencyPct}% of purchase price`],
  ]
  if (!r.isCash) {
    ar.push(['Mortgage Arrangement Fee (~1%)', fmt(r.mortgageFee, c), 'Bank fee approx. 1% of loan'])
    ar.push([E.mortgageRegLabel, fmt(r.registrationFee, c), `${E.label} mortgage registration`])
  }
  ar.push(['Other Costs', fmt(r.otherCosts, c), 'Conveyancing, NOC, etc.'])
  ar.push(['Total Acquisition Costs', fmt(r.totalAcqCosts, c), 'Excludes purchase price'])
  ar.push(['Total Cash Invested', fmt(r.totalCashInvested, c), r.isCash ? 'Full cash purchase' : 'Down payment + all costs'])
  ar.forEach((a, i) => {
    const tot = a[0].indexOf('Total') === 0
    body += `<tr style="background:${rowBg(i, tot)};">${td(a[0], { bold: tot, color: tot ? C.goldDark : C.text })}${td(a[1], { align: 'right', bold: tot, color: tot ? C.goldDark : C.text })}${td(a[2], { color: C.muted, small: true })}</tr>`
  })
  body += '</table>'

  if (!r.isCash) {
    body += sectionTitle('Mortgage Summary')
    body += `<table style="${ts}"><tr>${th('Item')}${th('Value', 'purple', 'right')}</tr>`
    ;[
      ['Loan Amount', fmt(r.loan, c)],
      ['Interest Rate', `${fmtp(r.rate)} p.a.`],
      ['Loan Term', `${r.termYrs} years`],
      ['Monthly Payment', fmt(r.monthly, c)],
      ['Total Interest', fmt(r.totalInterest, c)],
    ].forEach((row, i) => {
      body += `<tr style="background:${rowBg(i)};">${td(row[0])}${td(row[1], { align: 'right', bold: true, color: C.navy })}</tr>`
    })
    body += '</table>'
  }

  body += sectionTitle('Annual Income & Expense Analysis')
  body += `<table style="${ts}"><tr>${th('Item')}${th('Annual', 'green', 'right')}${th('Monthly', 'blue', 'right')}</tr>`
  const ir = [
    ['Gross Rental Income', r.grossRent, true],
    ['Vacancy Loss', -r.vacancyLoss, false],
    ['Net Rental Income', r.netRent, true],
    [`Service Charge (${E.label === 'Dubai' ? 'RERA' : 'ADRC'})`, -r.svcCharge, false],
    ['Property Management', -r.mgmtFee, false],
    ['Maintenance / Repairs', -r.maintenance, false],
    ['Insurance', -r.insurance, false],
    ['Net Operating Income (NOI)', r.noi, true],
  ]
  if (!r.isCash) ir.push(['Annual Debt Service', -r.annualDebtSvc, false])
  ir.push(['Net Annual Cash Flow', r.annualCashFlow, true])
  ir.forEach((row, i) => {
    const col = row[1] >= 0 ? C.green : C.red
    const highlight = row[2]
    const s = row[1] < 0 ? '-' : ''
    body += `<tr style="background:${rowBg(i, highlight)};">${td(row[0], { bold: highlight })}${td(s + fmt(Math.abs(row[1]), c), { align: 'right', color: highlight ? col : C.muted, bold: highlight })}${td(s + fmt(Math.abs(row[1] / 12), c), { align: 'right', color: C.muted, small: true })}</tr>`
  })
  body += '</table>'

  body += sectionTitle(`${r.holdYrs}-Year Projection · ${fmtp(r.appPct)} p.a. · 5% rent escalation`)
  body += `<table style="${ts}"><tr>${th('Year')}${th('Property Value', 'green', 'right')}${th('Capital Gain', 'green', 'right')}${r.isCash ? '' : th('Loan Balance', 'purple', 'right')}${r.isCash ? '' : th('Net Equity', 'blue', 'right')}${th('Annual Rent', 'blue', 'right')}${th('NOI', 'green', 'right')}${th('Cumul. NOI', 'blue', 'right')}${th('ROE Capital', 'green', 'right')}${th('Total ROI', 'gold', 'right')}</tr>`
  r.yearRows.forEach((row, i) => {
    body += `<tr style="background:${rowBg(i, row.isLast)};">${td(`Year ${row.yr}`, { bold: row.isLast, color: row.isLast ? C.goldDark : C.text })}${td(fmt(row.val, c), { align: 'right', color: C.green, bold: true })}${td(`+${fmt(row.capitalGain, c)}`, { align: 'right', color: C.green })}${r.isCash ? '' : td(fmt(row.balance, c), { align: 'right', color: C.muted })}${r.isCash ? '' : td(fmt(row.equity, c), { align: 'right', color: C.blue, bold: true })}${td(fmt(row.yGross, c), { align: 'right', color: C.blue })}${td(fmt(row.yNOI, c), { align: 'right', color: row.yNOI >= 0 ? C.green : C.red })}${td(fmt(row.cum, c), { align: 'right', color: C.blue, bold: true })}${td(fmtp(row.roeCapital), { align: 'right', color: C.green, bold: true })}${td(fmtp(row.totalROI), { align: 'right', color: row.isLast ? C.goldDark : C.text, bold: row.isLast })}</tr>`
  })
  body += '</table>'

  const projectLine = `${f.projectName || ''}${f.unitRef ? ` · ${f.unitRef}` : ''}`
  const emirateSlug = E.label.replace(/\s/g, '-')
  const clientSlug = (f.clientName || 'Client').replace(/[^\w\s-]/g, '').trim().replace(/\s+/g, '-') || 'Client'
  const filename = `Oia-Properties-ROI-${emirateSlug}-${clientSlug}-${Date.now()}.pdf`

  const bodyHtml = pdfShell({
    header: pdfHeader({
      reportTitle: 'Return on Investment Analysis Report',
      clientName: f.clientName,
      projectLine,
      todayLabel,
      agentName: f.agentName || 'Oia Properties',
    }),
    meta: metaGrid([
      ['Client', f.clientName || '—'],
      ['Project', f.projectName || '—'],
      ['Unit', f.unitRef || '—'],
      ['Agent', f.agentName || 'Oia Properties'],
      ['Emirate', `${E.label} · ${r.isCash ? 'Cash' : 'Mortgage'}`],
      ['Property Price', fmt(r.pp, c)],
    ]),
    body,
    disclaimer: pdfDisclaimer(
      'Prepared by Oia Properties for illustrative purposes only. All projections are estimates — not guaranteed. Figures exclude VAT and applicable municipal fees. Not financial, legal, or investment advice.',
    ),
  })

  return { bodyHtml, filename }
}

export async function downloadRoiPdf(r, todayLabel, mountElement) {
  if (!r) return false
  const { bodyHtml, filename } = buildRoiPdfHtml(r, todayLabel)
  return downloadPdfHtml({ bodyHtml, filename, mountElement })
}

/** @deprecated use downloadRoiPdf */
export function generateRoiReportPdf({ inputs: _inputs, ...rest }) {
  void rest
  return downloadRoiPdf(null)
}
