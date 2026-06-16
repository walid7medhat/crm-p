import { fmt, fmtp } from './useRoiCalculations.js'

const PDF_WIDTH = 1123

function th(t, col, a) {
  return `<th style="background:${col || '#01062D'};color:#fff;padding:6px 8px;font-size:7.5pt;letter-spacing:.06em;text-transform:uppercase;text-align:${a || 'left'};white-space:nowrap;">${t}</th>`
}

function td(t, o = {}) {
  return `<td style="padding:5px 8px;font-size:8pt;text-align:${o.align || 'left'};color:${o.color || '#222'};font-weight:${o.bold ? 700 : 400};white-space:nowrap;">${t}</td>`
}

function kpiCell(label, value, bg, color) {
  const labelColor = bg === '#FAA300' ? 'rgba(1,6,45,.6)' : '#888'
  return `<td style="width:16.66%;vertical-align:top;background:${bg};border:1px solid #e0e0e0;padding:10px 12px;">
    <div style="font-size:6.5pt;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:${labelColor};margin-bottom:5px;">${label}</div>
    <div style="font-size:14pt;font-weight:800;color:${color};line-height:1;">${value}</div>
  </td>`
}

function metaCell(label, value) {
  return `<td style="width:33.33%;vertical-align:top;padding:6px 8px;">
    <div style="font-size:6.5pt;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#FAA300;margin-bottom:3px;">${label}</div>
    <div style="font-size:9pt;font-weight:700;color:#01062D;">${value}</div>
  </td>`
}

export function buildRoiPdfHtml(r, todayLabel) {
  const c = r.cur
  const E = r.em
  const f = r.f
  const ts = "font-family:'Segoe UI',Arial,sans-serif;font-size:8pt;border-collapse:collapse;width:100%;margin-bottom:14px;"

  const kpiArr = [
    ['Purchase Price', fmt(r.pp, c), '#01062D', '#fff'],
    ['Total Cash Invested', fmt(r.totalCashInvested, c), '#f0fff8', '#2ECC8A'],
    ['Annual Cash Flow', fmt(r.annualCashFlow, c), r.annualCashFlow >= 0 ? '#f0fff8' : '#fff5f5', r.annualCashFlow >= 0 ? '#2ECC8A' : '#E05C5C'],
    ['Cap Rate', fmtp(r.capRate), '#f0f7ff', '#4BA8D4'],
    ['Cash-on-Cash', fmtp(r.cashOnCash), '#FAA300', '#01062D'],
    ['Total ROI', fmtp(r.totalROI), '#FAA300', '#01062D'],
  ]

  let body = `<table cellpadding="0" cellspacing="8" style="width:100%;border-collapse:separate;border-spacing:8px;margin-bottom:18px;"><tr>`
  kpiArr.forEach((k) => { body += kpiCell(k[0], k[1], k[2], k[3]) })
  body += '</tr></table>'

  body += `<div style="font-size:7.5pt;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#FAA300;margin-bottom:6px;">Acquisition Cost Breakdown</div><table style="${ts}"><tr>${th('Cost Item')}${th('Amount', '#01062D', 'right')}${th('Notes')}</tr>`
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
    body += `<tr style="background:${tot ? '#fffbf0' : i % 2 === 0 ? '#fff' : '#f9f9f9'}">${td(a[0], { bold: tot, color: tot ? '#C08000' : '#222' })}${td(a[1], { align: 'right', bold: tot, color: tot ? '#C08000' : '#222' })}${td(a[2], { color: '#888' })}</tr>`
  })
  body += '</table>'

  if (!r.isCash) {
    body += `<div style="font-size:7.5pt;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#FAA300;margin:6px 0;">Mortgage Summary</div><table style="${ts}"><tr>${th('Item')}${th('Value', '#01062D', 'right')}</tr>`
    const mort = [
      ['Loan Amount', fmt(r.loan, c)],
      ['Interest Rate', `${fmtp(r.rate)} p.a.`],
      ['Loan Term', `${r.termYrs} years`],
      ['Monthly Payment', fmt(r.monthly, c)],
      ['Total Interest', fmt(r.totalInterest, c)],
    ]
    mort.forEach((row, i) => {
      body += `<tr style="background:${i % 2 === 0 ? '#fff' : '#f9f9f9'}">${td(row[0])}${td(row[1], { align: 'right', bold: true })}</tr>`
    })
    body += '</table>'
  }

  body += `<div style="font-size:7.5pt;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#FAA300;margin-bottom:6px;">Annual Income &amp; Expense Analysis</div><table style="${ts}"><tr>${th('Item')}${th('Annual', '#2ECC8A', 'right')}${th('Monthly', '#2ECC8A', 'right')}</tr>`
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
  ir.forEach((row) => {
    const col = row[1] >= 0 ? '#2ECC8A' : '#E05C5C'
    const bg = row[2] ? '#fffbf0' : '#fff'
    const s = row[1] < 0 ? '-' : ''
    body += `<tr style="background:${bg}">${td(row[0], { bold: row[2], color: row[2] ? '#222' : '#444' })}${td(s + fmt(Math.abs(row[1]), c), { align: 'right', color: row[2] ? col : '#444', bold: row[2] })}${td(s + fmt(Math.abs(row[1] / 12), c), { align: 'right', color: '#888' })}</tr>`
  })
  body += '</table>'

  body += `<div style="font-size:7.5pt;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#FAA300;margin-bottom:6px;">${r.holdYrs}-Year Investment Projection · ${fmtp(r.appPct)} p.a. appreciation · 5% annual rent escalation</div><table style="${ts}"><tr>${th('Year')}${th('Property Value', '#2ECC8A', 'right')}${th('Capital Gain', '#2ECC8A', 'right')}${r.isCash ? '' : th('Loan Balance', '#888', 'right')}${r.isCash ? '' : th('Net Equity', '#4BA8D4', 'right')}${th('Annual Rent', '#4BA8D4', 'right')}${th('NOI', '#4BA8D4', 'right')}${th('Cumul. NOI', '#4BA8D4', 'right')}${th('ROE Capital', '#2ECC8A', 'right')}${th('Total ROI', '#FAA300', 'right')}</tr>`
  r.yearRows.forEach((row, i) => {
    const bg = row.isLast ? '#fffbf0' : i % 2 === 0 ? '#fff' : '#f9f9f9'
    body += `<tr style="background:${bg}">${td(`Year ${row.yr}`, { bold: row.isLast, color: row.isLast ? '#C08000' : '#222' })}${td(fmt(row.val, c), { align: 'right', color: '#2ECC8A', bold: true })}${td(`+${fmt(row.capitalGain, c)}`, { align: 'right', color: '#2ECC8A' })}${r.isCash ? '' : td(fmt(row.balance, c), { align: 'right', color: '#888' })}${r.isCash ? '' : td(fmt(row.equity, c), { align: 'right', color: '#4BA8D4', bold: true })}${td(fmt(row.yGross, c), { align: 'right', color: '#4BA8D4' })}${td(fmt(row.yNOI, c), { align: 'right', color: row.yNOI >= 0 ? '#2ECC8A' : '#E05C5C' })}${td(fmt(row.cum, c), { align: 'right', color: '#4BA8D4', bold: true })}${td(fmtp(row.roeCapital), { align: 'right', color: '#2ECC8A', bold: true })}${td(fmtp(row.totalROI), { align: 'right', color: row.isLast ? '#C08000' : '#444', bold: row.isLast })}</tr>`
  })
  body += '</table>'

  const meta = [
    ['Client', f.clientName || '—'],
    ['Project', f.projectName || '—'],
    ['Unit', f.unitRef || '—'],
    ['Agent', f.agentName || 'Oia Properties'],
    ['Emirate', `${E.label} · ${r.isCash ? 'Cash' : 'Mortgage'}`],
    ['Property Price', fmt(r.pp, c)],
  ]

  const emirateSlug = E.label.replace(/\s/g, '-')
  const clientSlug = (f.clientName || 'Client').replace(/[^\w\s-]/g, '').trim().replace(/\s+/g, '-') || 'Client'
  const filename = `Oia-Properties-ROI-${emirateSlug}-${clientSlug}-${Date.now()}.pdf`

  const bodyHtml = `
    <div style="font-family:'Segoe UI',Arial,sans-serif;background:#fff;color:#222;width:${PDF_WIDTH}px;">
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#01062D;margin-bottom:18px;">
        <tr>
          <td style="padding:14px 20px;vertical-align:top;">
            <div style="font-size:18pt;font-weight:800;color:#FAA300;letter-spacing:.05em;">OIA PROPERTIES</div>
            <div style="font-size:8pt;color:rgba(255,255,255,.5);letter-spacing:.15em;text-transform:uppercase;margin-top:2px;">Return on Investment Analysis Report</div>
          </td>
          <td style="padding:14px 20px;text-align:right;vertical-align:top;">
            <div style="font-size:9pt;font-weight:600;color:#fff;">${f.clientName || 'Client'}</div>
            <div style="font-size:8pt;color:rgba(255,255,255,.5);margin-top:2px;">${f.projectName || ''}${f.unitRef ? ` · ${f.unitRef}` : ''}</div>
            <div style="font-size:7.5pt;color:#FAA300;margin-top:3px;">Prepared: ${todayLabel}</div>
          </td>
        </tr>
      </table>
      <div style="padding:0 20px 20px;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#fafafa;border:1px solid #eee;margin-bottom:16px;">
          <tr>${meta.slice(0, 3).map((m) => metaCell(m[0], m[1])).join('')}</tr>
          <tr>${meta.slice(3, 6).map((m) => metaCell(m[0], m[1])).join('')}</tr>
        </table>
        ${body}
        <div style="border-top:1px solid #eee;margin-top:16px;padding-top:10px;font-size:7pt;color:#aaa;line-height:1.7;">
          <strong style="color:#888;">Disclaimer:</strong> Prepared by Oia Properties for illustrative purposes only. All projections are estimates — not guaranteed. Figures exclude VAT and applicable municipal fees. Not financial, legal, or investment advice.<br>
          <strong style="color:#01062D;">Oia Properties</strong> · oiaproperties.com · Abu Dhabi, UAE · +971 2 444 0089 · 27 years UAE real estate experience
        </div>
      </div>
    </div>`

  return { bodyHtml, filename }
}

export async function waitForPdfPaint() {
  await new Promise((resolve) => {
    requestAnimationFrame(() => requestAnimationFrame(resolve))
  })
  await new Promise((resolve) => setTimeout(resolve, 350))
}

export async function exportRoiPdfFromElement(element, filename) {
  if (!element) return false

  await waitForPdfPaint()

  try {
    const html2pdf = (await import('html2pdf.js')).default
    const pdf = await html2pdf()
      .set({
        margin: [8, 8, 8, 8],
        filename,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: {
          scale: 2,
          useCORS: true,
          allowTaint: true,
          backgroundColor: '#ffffff',
          logging: false,
          width: PDF_WIDTH,
          windowWidth: PDF_WIDTH,
          scrollX: 0,
          scrollY: 0,
        },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' },
        pagebreak: { mode: ['css', 'legacy'] },
      })
      .from(element)
      .toPdf()
      .get('pdf')

    pdf.save(filename)
    return true
  } catch (err) {
    console.error('ROI PDF export failed:', err)
    return false
  }
}

export async function downloadRoiPdf(r, todayLabel, mountElement) {
  if (!r) return false

  const { bodyHtml, filename } = buildRoiPdfHtml(r, todayLabel)

  if (mountElement) {
    mountElement.innerHTML = bodyHtml
    return exportRoiPdfFromElement(mountElement, filename)
  }

  const container = document.createElement('div')
  container.setAttribute('aria-hidden', 'true')
  container.style.cssText = [
    'position:fixed',
    'top:0',
    'left:0',
    `width:${PDF_WIDTH}px`,
    'background:#ffffff',
    'z-index:2147483646',
    'pointer-events:none',
    'overflow:visible',
  ].join(';')
  container.innerHTML = bodyHtml
  document.body.appendChild(container)

  try {
    return await exportRoiPdfFromElement(container, filename)
  } finally {
    document.body.removeChild(container)
  }
}

/** @deprecated use downloadRoiPdf */
export function generateRoiReportPdf({ inputs: _inputs, ...rest }) {
  void rest
  return downloadRoiPdf(null)
}
