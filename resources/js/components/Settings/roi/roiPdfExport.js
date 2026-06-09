import { jsPDF } from 'jspdf'
import autoTable from 'jspdf-autotable'
import { EMIRATES } from './uaeMarketConfig.js'
import { formatAed, formatPct } from './useRoiCalculations.js'

const PURPLE = [42, 21, 72]
const GOLD = [245, 197, 24]
const MUTED = [107, 114, 128]

export function generateRoiReportPdf({
  inputs,
  emirateConfig,
  kpis,
  yearlyProjections,
  registrationFee,
  agencyFee,
  downPaymentAmount,
  totalAcquisitionCost,
}) {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const emirate = EMIRATES[inputs.emirate] || EMIRATES.dubai
  let y = 18

  doc.setFillColor(...PURPLE)
  doc.rect(0, 0, 210, 42, 'F')
  doc.setTextColor(255, 255, 255)
  doc.setFontSize(18)
  doc.setFont('helvetica', 'bold')
  doc.text('Oia Properties', 14, 16)
  doc.setFontSize(11)
  doc.setFont('helvetica', 'normal')
  doc.text('UAE Real Estate Investment Report', 14, 24)
  doc.setTextColor(...GOLD)
  doc.text(`${emirate.label} · ${emirate.authority}`, 14, 32)

  y = 52
  doc.setTextColor(...PURPLE)
  doc.setFontSize(10)
  doc.setFont('helvetica', 'bold')
  doc.text('Investment Summary', 14, y)
  y += 6

  const summaryRows = [
    ['Purchase Price', formatAed(inputs.purchasePrice)],
    ['Down Payment', `${inputs.downPaymentPct}% (${formatAed(downPaymentAmount)})`],
    [`${emirateConfig.registrationFeeLabel}`, formatAed(registrationFee)],
    ['Agency Fee', `${inputs.agencyFeePct}% (${formatAed(agencyFee)})`],
    ['Mortgage Registration', formatAed(emirateConfig.mortgageRegistration)],
    ['Total Cash Invested', formatAed(totalAcquisitionCost)],
    ['Annual Rental Income', formatAed(inputs.annualRentalIncome)],
    ['Interest Rate', `${inputs.interestRate}% · ${inputs.mortgageTermYears} yrs`],
    ['Hold Period', `${inputs.holdPeriodYears} years`],
    ['Appreciation Assumption', `${inputs.annualAppreciationPct}% p.a.`],
    ['Rent Escalation', '5% p.a. (compounded)'],
  ]

  autoTable(doc, {
    startY: y,
    head: [['Assumption', 'Value']],
    body: summaryRows,
    theme: 'plain',
    styles: { fontSize: 8.5, cellPadding: 2.5, textColor: PURPLE },
    headStyles: { fillColor: PURPLE, textColor: [255, 255, 255], fontStyle: 'bold' },
    columnStyles: { 0: { cellWidth: 70 }, 1: { cellWidth: 100 } },
    margin: { left: 14, right: 14 },
  })

  y = doc.lastAutoTable.finalY + 8
  doc.setFont('helvetica', 'bold')
  doc.text('Key Performance Indicators', 14, y)
  y += 4

  autoTable(doc, {
    startY: y,
    head: [['Metric', 'Value']],
    body: [
      ['Total ROI', formatPct(kpis.totalRoi)],
      ['Annualized ROI', formatPct(kpis.annualizedRoi)],
      ['Cash-on-Cash Return', formatPct(kpis.cashOnCash)],
      ['Cap Rate', formatPct(kpis.capRate)],
      ['Gross Rental Yield', formatPct(kpis.grossRentalYield)],
      ['Monthly Cash Flow', formatAed(kpis.monthlyCashFlow)],
      ['NOI (Year 1)', formatAed(kpis.noi)],
      ['GRM', kpis.grm.toFixed(2)],
      ['1% Rule', formatPct(kpis.onePctRule)],
    ],
    theme: 'striped',
    styles: { fontSize: 8.5, cellPadding: 2.5 },
    headStyles: { fillColor: GOLD, textColor: PURPLE, fontStyle: 'bold' },
    alternateRowStyles: { fillColor: [248, 250, 252] },
    margin: { left: 14, right: 14 },
  })

  y = doc.lastAutoTable.finalY + 8
  if (y > 240) {
    doc.addPage()
    y = 18
  }

  doc.setFont('helvetica', 'bold')
  doc.setTextColor(...PURPLE)
  doc.text('Yearly Projection', 14, y)

  autoTable(doc, {
    startY: y + 4,
    head: [['Year', 'Property Value', 'Annual Rent', 'NOI', 'Cum. NOI', 'Appr. Gain', 'Total Equity']],
    body: yearlyProjections.map((row) => [
      row.year,
      formatAed(row.propertyValue),
      formatAed(row.annualRent),
      formatAed(row.noi),
      formatAed(row.cumulativeNoi),
      formatAed(row.appreciationGain),
      formatAed(row.totalEquity),
    ]),
    theme: 'striped',
    styles: { fontSize: 7, cellPadding: 2, overflow: 'linebreak' },
    headStyles: { fillColor: PURPLE, textColor: [255, 255, 255], fontStyle: 'bold', fontSize: 7 },
    alternateRowStyles: { fillColor: [241, 245, 249] },
    margin: { left: 10, right: 10 },
  })

  const pageCount = doc.getNumberOfPages()
  for (let i = 1; i <= pageCount; i += 1) {
    doc.setPage(i)
    doc.setFontSize(7)
    doc.setTextColor(...MUTED)
    doc.setFont('helvetica', 'normal')
    doc.text(
      'Disclaimer: This report is for illustrative purposes only. Market conditions, fees, and returns may vary. Not financial advice.',
      14,
      287,
      { maxWidth: 182 },
    )
    doc.text(`Oia Properties · Page ${i} of ${pageCount}`, 160, 292)
  }

  doc.save(`Oia-Properties-ROI-${emirate.label.replace(/\s/g, '-')}-${Date.now()}.pdf`)
}
