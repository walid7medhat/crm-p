import { fmt, fmtp, fmtDt } from './useRoeCalculations.js'
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
} from '../calculatorPdfTheme.js'

export function buildRoePdfHtml(r, todayLabel) {
  const f = r.f
  const cur = r.cur
  const ts = tableStyle()
  let body = ''

  const kpis = r.mode === 'offplan'
    ? [
        { label: 'Purchase Price', value: fmt(r.pp, cur) },
        { label: 'Invested — Scen A', value: fmt(r.totalPaidA, cur), color: C.green },
        { label: 'Invested — Scen B', value: fmt(r.totalPaidB, cur), color: C.blue },
        { label: 'ROE — Scenario A', value: fmtp(r.roeA), color: C.green },
        { label: 'ROE — Scenario B', value: fmtp(r.roeB), accent: true },
      ]
    : [
        { label: 'Purchase Price', value: fmt(r.pp, cur) },
        ...(r.isCash ? [] : [
          { label: 'Monthly Payment', value: fmt(r.monthly, cur), color: C.blue },
          { label: 'Total Interest', value: fmt(r.totalInterest, cur), color: C.red },
        ]),
        { label: 'Capital Gain', value: fmt(r.capitalGain, cur), color: C.green },
        { label: 'ROE on Equity', value: fmtp(r.roeOnDown), color: C.purple },
        { label: 'Annual Net Rent', value: fmt(r.netRental, cur), color: C.blue, accent: true },
      ]

  body += kpiRow(kpis)

  if (r.mode === 'offplan') {
    body += sectionTitle('Payment Schedule')
    body += `<table style="${ts}"><tr>${th('Payment')}${th('Date')}${th('%', 'purple', 'right')}${th('Amount', 'purple', 'right')}${th('Cumul. Paid', 'purple', 'right')}${th('Scenario A', 'green', 'right')}${th('Scenario B', 'blue', 'right')}</tr>`
    body += `<tr style="background:${C.bg};">${td('Down Payment', { bold: true })}${td(fmtDt(f.signingDate), { color: C.muted, small: true })}${td(fmtp(r.dpPct), { align: 'right' })}${td(fmt(r.down, cur), { align: 'right', bold: true })}${td(fmt(r.down, cur), { align: 'right', color: C.muted })}${td('PAID', { align: 'right', color: C.muted, small: true })}${td('PAID', { align: 'right', color: C.muted, small: true })}</tr>`
    r.rows.forEach((row, i) => {
      body += `<tr style="background:${rowBg(i, row.isLast)};">${td(row.label + (row.isLast ? ' ← Exit A' : ''), { bold: row.isLast, color: row.isLast ? C.green : C.text })}${td(fmtDt(row.date), { color: C.goldDark, small: true })}${td(fmtp(row.pct), { align: 'right', bold: true })}${td(fmt(row.amount, cur), { align: 'right', bold: true })}${td(fmt(row.cum, cur), { align: 'right', color: C.muted })}${td(row.isLast ? 'PAID · EXITS ✓' : 'PAID', { align: 'right', color: row.isLast ? C.green : C.muted, bold: row.isLast })}${td('PAID', { align: 'right', color: C.muted, small: true })}</tr>`
    })
    body += `<tr style="background:${C.redBg};">${td('Handover Payment', { bold: true, color: C.red })}${td(fmtDt(f.handoverDate), { color: C.muted, small: true })}${td(fmtp(r.hoPct), { align: 'right', color: C.red })}${td(fmt(r.handoverDue, cur), { align: 'right', bold: true, color: C.red })}${td(fmt(r.pp, cur), { align: 'right', color: C.muted })}${td('NOT PAID ✗', { align: 'right', bold: true, color: C.red })}${td('PAID ✓', { align: 'right', bold: true, color: C.blue })}</tr></table>`

    body += '<table width="100%" cellpadding="0" cellspacing="8" style="border-collapse:separate;border-spacing:8px;margin-bottom:12px;"><tr>'
    ;[
      { app: r.appA, inv: r.totalPaidA, ho: 'Not paid — exits early', exit: r.exitA, gain: r.gainA, roe: r.roeA, col: C.green, bg: C.greenBg, title: 'Scenario A · Pre-Handover Exit', sub: `Exits at last instalment — skips ${fmtp(r.hoPct)} handover` },
      { app: r.appB, inv: r.totalPaidB, ho: fmt(r.handoverDue, cur), exit: r.exitB, gain: r.gainB, roe: r.roeB, col: C.blue, bg: C.blueBg, title: 'Scenario B · Holds to Handover', sub: 'Pays full 100% — sells after completion' },
    ].forEach((s) => {
      body += `<td style="width:50%;vertical-align:top;border:1px solid ${C.border};border-radius:8px;overflow:hidden;">
        <table width="100%" cellpadding="0" cellspacing="0"><tr><td style="height:3px;background:${s.col};padding:0;font-size:0;">&nbsp;</td></tr>
        <tr><td style="background:${s.bg};padding:10px 12px;border-bottom:1px solid ${C.border};">
          <div style="font-size:7pt;font-weight:700;text-transform:uppercase;color:${s.col};font-family:'Segoe UI',Arial,sans-serif;">${s.title}</div>
          <div style="font-size:6.5pt;color:${C.muted};margin-top:3px;font-family:'Segoe UI',Arial,sans-serif;">${s.sub}</div>
        </td></tr></table><div style="padding:8px 12px;">`
      ;[['Appreciation assumed', fmtp(s.app), false], ['Total invested', fmt(s.inv, cur), false], ['Handover payment', s.ho, false], ['Sale price', fmt(s.exit, cur), false], ['Capital gain', fmt(s.gain, cur), true], ['ROE', fmtp(s.roe), true]].forEach((it) => {
        body += `<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:3px;"><tr>
          <td style="font-size:7pt;color:${it[2] ? C.text : C.muted};font-weight:${it[2] ? 700 : 400};padding:5px 0;border-bottom:1px solid ${C.border};font-family:'Segoe UI',Arial,sans-serif;">${it[0]}</td>
          <td style="font-size:${it[2] ? '10' : '7.5'}pt;font-weight:${it[2] ? 800 : 500};color:${it[2] ? s.col : C.text};text-align:right;padding:5px 0;border-bottom:1px solid ${C.border};font-family:'Segoe UI',Arial,sans-serif;">${it[1]}</td>
        </tr></table>`
      })
      body += '</div></td>'
    })
    body += '</tr></table>'

    body += sectionTitle('Capital Appreciation Per Instalment')
    body += `<table style="${ts}"><tr>${th('Instalment')}${th('Date')}${th('%', 'purple', 'right')}${th('Cumul. Paid', 'purple', 'right')}${th('Value (A)', 'green', 'right')}${th('Gain (A)', 'green', 'right')}${th('ROE (A)', 'green', 'right')}${th('Value (B)', 'blue', 'right')}${th('Gain (B)', 'blue', 'right')}${th('ROE (B)', 'blue', 'right')}</tr>`
    r.rows.forEach((row, i) => {
      body += `<tr style="background:${rowBg(i, row.isLast)};">${td(row.label + (row.isLast ? ' ★' : ''), { bold: row.isLast, color: row.isLast ? C.green : C.text })}${td(fmtDt(row.date), { color: C.goldDark, small: true })}${td(fmtp(row.pct), { align: 'right', bold: true })}${td(fmt(row.cum, cur), { align: 'right', color: C.muted })}${td(fmt(row.vA, cur), { align: 'right', bold: true, color: C.green })}${td(`+${fmt(row.ygA, cur)}`, { align: 'right', color: C.green })}${td(fmtp(row.roeA), { align: 'right', color: C.green, bold: row.isLast })}${td(fmt(row.vB, cur), { align: 'right', bold: true, color: C.blue })}${td(`+${fmt(row.ygB, cur)}`, { align: 'right', color: C.blue })}${td(fmtp(row.roeB), { align: 'right', color: C.blue, bold: row.isLast })}</tr>`
    })
    body += '</table>'
  }

  if (r.mode === 'ready') {
    if (!r.isCash) {
      body += sectionTitle('Mortgage Summary')
      body += '<table width="100%" cellpadding="0" cellspacing="6" style="border-collapse:separate;border-spacing:6px;margin-bottom:12px;"><tr>'
      ;[['Loan Amount', fmt(r.loan, cur)], ['Interest Rate', `${fmtp(r.rate)} p.a.`], ['Loan Term', `${r.termYrs} years`], ['Monthly Payment', fmt(r.monthly, cur)], ['Total Repaid', fmt(r.monthly * r.termYrs * 12, cur)], ['Total Interest', fmt(r.totalInterest, cur)]].forEach((p) => {
        body += `<td style="background:${C.card};border:1px solid ${C.border};border-radius:8px;padding:10px;vertical-align:top;">
          <div style="font-size:6pt;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:${C.muted};margin-bottom:4px;font-family:'Segoe UI',Arial,sans-serif;">${p[0]}</div>
          <div style="font-size:11pt;font-weight:800;color:${C.navy};font-family:'Segoe UI',Arial,sans-serif;">${p[1]}</div>
        </td>`
      })
      body += '</tr></table>'
    }
    body += sectionTitle(`${r.holdYrs}-Year Investment Projection`)
    const extra = r.isCash ? '' : th('Loan Balance', 'purple', 'right') + th('Net Equity', 'blue', 'right')
    body += `<table style="${ts}"><tr>${th('Year')}${th('Property Value', 'green', 'right')}${th('Capital Gain', 'green', 'right')}${extra}${th('Cash Invested', 'purple', 'right')}${th('ROE (Capital)', 'green', 'right')}${th('Cumul. Net Rental', 'blue', 'right')}${th('Total ROE', 'gold', 'right')}</tr>`
    r.yearRows.forEach((row, i) => {
      const extraTd = r.isCash ? '' : td(fmt(row.balance, cur), { align: 'right', color: C.muted }) + td(fmt(row.equity, cur), { align: 'right', bold: true, color: C.blue })
      body += `<tr style="background:${rowBg(i, row.isLast)};">${td(`Year ${row.yr}`, { bold: row.isLast, color: row.isLast ? C.goldDark : C.text })}${td(fmt(row.val, cur), { align: 'right', bold: true, color: C.green })}${td(`+${fmt(row.capitalGain, cur)}`, { align: 'right', color: C.green })}${extraTd}${td(fmt(row.cashIn, cur), { align: 'right', color: C.muted })}${td(fmtp(row.roeCapital), { align: 'right', bold: true, color: C.green })}${td(fmt(row.cumRental, cur), { align: 'right', color: C.blue })}${td(fmtp(row.totalRoe), { align: 'right', bold: row.isLast, color: row.isLast ? C.goldDark : C.text })}</tr>`
    })
    body += '</table>'
  }

  const projectLine = `${f.projectName || ''}${f.unitRef ? ` · ${f.unitRef}` : ''}`
  const slug = (f.clientName || 'Client').replace(/[^\w\s-]/g, '').trim().replace(/\s+/g, '-') || 'Client'
  const filename = `Oia-Properties-ROE-${r.mode}-${slug}-${Date.now()}.pdf`

  const bodyHtml = pdfShell({
    header: pdfHeader({
      reportTitle: 'Capital Appreciation & Return on Equity Report',
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
      ['Purchase Type', r.mode === 'offplan' ? 'Off-Plan' : `Ready · ${r.isCash ? 'Cash' : 'Mortgage'}`],
      ['Property Price', fmt(r.pp, cur)],
    ]),
    body,
    disclaimer: pdfDisclaimer(
      'Prepared by Oia Properties for illustrative purposes only. All projections are forward-looking estimates — not guaranteed. DLD transfer fees (4%), agency commissions, mortgage fees, and service charges are excluded. Not financial or investment advice.',
    ),
  })

  return { bodyHtml, filename }
}

export async function downloadRoePdf(r, todayLabel, mountElement) {
  if (!r) return false
  const { bodyHtml, filename } = buildRoePdfHtml(r, todayLabel)
  return downloadPdfHtml({ bodyHtml, filename, mountElement })
}
