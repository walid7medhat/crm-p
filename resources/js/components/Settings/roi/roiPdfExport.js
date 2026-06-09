import { jsPDF } from 'jspdf'
import { EMIRATES } from './uaeMarketConfig.js'
import { formatAed, formatPct } from './useRoiCalculations.js'

const PURPLE = [42, 21, 72]
const GOLD = [245, 197, 24]
const MUTED = [107, 114, 128]

function drawTable(doc, {
  startY,
  columns,
  rows,
  colWidths,
  marginLeft = 14,
  fontSize = 8.5,
  rowHeight = 7,
  headFill = PURPLE,
  headText = [255, 255, 255],
  alternateFill = [248, 250, 252],
  striped = false,
}) {
  const tableWidth = colWidths.reduce((sum, width) => sum + width, 0)
  let y = startY

  doc.setFontSize(fontSize)
  doc.setFont('helvetica', 'bold')
  doc.setFillColor(...headFill)
  doc.setTextColor(...headText)
  doc.rect(marginLeft, y, tableWidth, rowHeight, 'F')

  let x = marginLeft
  columns.forEach((column, index) => {
    doc.text(String(column), x + 2, y + rowHeight - 2.2, { maxWidth: colWidths[index] - 4 })
    x += colWidths[index]
  })
  y += rowHeight

  doc.setFont('helvetica', 'normal')
  doc.setTextColor(...PURPLE)

  rows.forEach((row, rowIndex) => {
    if (striped && rowIndex % 2 === 1) {
      doc.setFillColor(...alternateFill)
      doc.rect(marginLeft, y, tableWidth, rowHeight, 'F')
    }

    x = marginLeft
    row.forEach((cell, cellIndex) => {
      doc.text(String(cell), x + 2, y + rowHeight - 2.2, { maxWidth: colWidths[cellIndex] - 4 })
      x += colWidths[cellIndex]
    })
    y += rowHeight
  })

  return y + 4
}

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

  y = drawTable(doc, {
    startY: y,
    columns: ['Assumption', 'Value'],
    rows: summaryRows,
    colWidths: [70, 100],
  })

  doc.setFont('helvetica', 'bold')
  doc.text('Key Performance Indicators', 14, y)
  y += 4

  y = drawTable(doc, {
    startY: y,
    columns: ['Metric', 'Value'],
    rows: [
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
    colWidths: [70, 100],
    headFill: GOLD,
    headText: PURPLE,
    striped: true,
  })

  if (y > 240) {
    doc.addPage()
    y = 18
  }

  doc.setFont('helvetica', 'bold')
  doc.setTextColor(...PURPLE)
  doc.text('Yearly Projection', 14, y)
  y += 4

  y = drawTable(doc, {
    startY: y,
    columns: ['Year', 'Property Value', 'Annual Rent', 'NOI', 'Cum. NOI', 'Appr. Gain', 'Total Equity'],
    rows: yearlyProjections.map((row) => [
      row.year,
      formatAed(row.propertyValue),
      formatAed(row.annualRent),
      formatAed(row.noi),
      formatAed(row.cumulativeNoi),
      formatAed(row.appreciationGain),
      formatAed(row.totalEquity),
    ]),
    colWidths: [10, 30, 28, 24, 26, 28, 30],
    marginLeft: 10,
    fontSize: 7,
    rowHeight: 6,
    striped: true,
    alternateFill: [241, 245, 249],
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
