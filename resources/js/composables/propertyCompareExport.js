/**
 * PNG and PDF export for the property comparison.
 * Drawn on a canvas so title underlines and table rules stay off the text.
 */

const SHORT_LABELS = {
  price: 'Price',
  'price-sqft': 'Per sqft',
  purpose: 'Purpose',
  type: 'Type',
  bedrooms: 'Beds',
  bathrooms: 'Baths',
  'size-sqft': 'Size',
  location: 'Area',
  completion: 'Status',
}

const PAGE_W = 1280
const PAD = 28
const LABEL_W = 148
const INK = '#0f172a'
const MUTED = '#64748b'
const LINE = '#e2e8f0'
const LABEL_BG = '#f1f5f9'
const ROW_ALT = '#f8fafc'
const ACCENT = '#6d28d9'

function displayLabel(item) {
  return SHORT_LABELS[item?.id] || item?.label || ''
}

function displayValue(value) {
  if (value == null || value === '' || value === 'Not set' || value === 'Not available') return '—'
  return String(value)
}

function loadImage(src) {
  if (!src) return Promise.resolve(null)
  return new Promise((resolve) => {
    const image = new Image()
    image.crossOrigin = 'anonymous'
    image.onload = () => resolve(image)
    image.onerror = () => resolve(null)
    image.src = src
  })
}

function wrapLines(ctx, text, maxWidth, maxLines) {
  const words = String(text || '').split(/\s+/).filter(Boolean)
  if (!words.length) return []
  const lines = []
  let line = ''
  words.forEach((word) => {
    const next = line ? `${line} ${word}` : word
    if (ctx.measureText(next).width > maxWidth && line) {
      lines.push(line)
      line = word
    } else {
      line = next
    }
  })
  if (line) lines.push(line)
  if (lines.length <= maxLines) return lines
  const kept = lines.slice(0, maxLines)
  let last = kept[maxLines - 1]
  while (last.length > 1 && ctx.measureText(`${last}…`).width > maxWidth) last = last.slice(0, -1)
  kept[maxLines - 1] = `${last}…`
  return kept
}

function roundedRect(ctx, x, y, w, h, r) {
  const radius = Math.min(r, w / 2, h / 2)
  ctx.beginPath()
  ctx.moveTo(x + radius, y)
  ctx.arcTo(x + w, y, x + w, y + h, radius)
  ctx.arcTo(x + w, y + h, x, y + h, radius)
  ctx.arcTo(x, y + h, x, y, radius)
  ctx.arcTo(x, y, x + w, y, radius)
  ctx.closePath()
}

function drawCover(ctx, image, x, y, w, h) {
  ctx.save()
  roundedRect(ctx, x, y, w, h, 10)
  ctx.clip()
  ctx.fillStyle = '#e8edf5'
  ctx.fillRect(x, y, w, h)
  if (image) {
    const scale = Math.max(w / image.width, h / image.height)
    const dw = image.width * scale
    const dh = image.height * scale
    ctx.drawImage(image, x + (w - dw) / 2, y + (h - dh) / 2, dw, dh)
  } else {
    ctx.fillStyle = MUTED
    ctx.font = '13px Arial'
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    ctx.fillText('No image', x + w / 2, y + h / 2)
  }
  ctx.restore()
}

function drawCenteredLines(ctx, lines, centerX, top, lineHeight, color, font) {
  ctx.fillStyle = color
  ctx.font = font
  ctx.textAlign = 'center'
  ctx.textBaseline = 'top'
  lines.forEach((line, index) => {
    ctx.fillText(line, centerX, top + index * lineHeight)
  })
}

async function renderSheet(payload) {
  const columns = payload.listings || []
  const rows = (payload.groups || []).flatMap((group) => group.rows || [])
  const count = Math.max(columns.length, 1)
  const colW = (PAGE_W - PAD * 2 - LABEL_W) / count
  const photos = await Promise.all(columns.map((listing) => loadImage(listing.image)))

  const measure = document.createElement('canvas').getContext('2d')
  measure.font = '800 17px Arial'
  const nameLines = columns.map((listing) => wrapLines(measure, listing.name, colW - 36, 2))

  const photoH = 146
  const cardTop = 86
  const cardH = 12 + photoH + 14 + 40 + 8 + 22 + 14
  const tableTop = cardTop + cardH + 18

  measure.font = '13px Arial'
  const valueMax = colW - 20
  const rowHeights = rows.map((item) => {
    const lines = Math.max(1, ...item.values.map((value) => wrapLines(measure, displayValue(value), valueMax, 3).length))
    return Math.max(36, 16 + lines * 18)
  })
  const tableH = rowHeights.reduce((sum, height) => sum + height, 0)
  const pageH = tableTop + tableH + 24

  const canvas = document.createElement('canvas')
  canvas.width = PAGE_W * 2
  canvas.height = pageH * 2
  const ctx = canvas.getContext('2d')
  ctx.scale(2, 2)
  ctx.fillStyle = '#f7f8fb'
  ctx.fillRect(0, 0, PAGE_W, pageH)

  ctx.textBaseline = 'top'
  ctx.textAlign = 'left'
  ctx.fillStyle = INK
  ctx.font = '700 26px Arial'
  const title = 'Property Comparison'
  ctx.fillText(title, PAD, 26)
  const titleWidth = ctx.measureText(title).width
  ctx.fillStyle = ACCENT
  ctx.fillRect(PAD, 60, titleWidth, 3)

  columns.forEach((listing, index) => {
    const x = PAD + LABEL_W + index * colW + 6
    const w = colW - 12
    ctx.fillStyle = '#ffffff'
    ctx.fillRect(x, cardTop, w, cardH)
    ctx.strokeStyle = LINE
    ctx.lineWidth = 1
    ctx.strokeRect(x + 0.5, cardTop + 0.5, w - 1, cardH - 1)

    drawCover(ctx, photos[index], x + 10, cardTop + 10, w - 20, photoH)
    const center = x + w / 2
    let textY = cardTop + 10 + photoH + 14
    drawCenteredLines(ctx, nameLines[index], center, textY, 20, INK, '800 17px Arial')
    textY += 44
    ctx.font = '800 16px Arial'
    const priceLines = wrapLines(ctx, listing.price || 'Price not set', w - 24, 2)
    drawCenteredLines(ctx, priceLines, center, textY, 18, ACCENT, '800 16px Arial')
  })

  let rowY = tableTop
  rows.forEach((item, rowIndex) => {
    const rowH = rowHeights[rowIndex]
    ctx.fillStyle = LABEL_BG
    ctx.fillRect(PAD, rowY, LABEL_W, rowH)
    columns.forEach((_, index) => {
      ctx.fillStyle = rowIndex % 2 === 0 ? '#ffffff' : ROW_ALT
      ctx.fillRect(PAD + LABEL_W + index * colW, rowY, colW, rowH)
    })

    ctx.fillStyle = '#475569'
    ctx.font = '700 13px Arial'
    ctx.textAlign = 'left'
    ctx.textBaseline = 'middle'
    ctx.fillText(displayLabel(item), PAD + 12, rowY + rowH / 2)

    ctx.font = '13px Arial'
    ctx.textAlign = 'center'
    item.values.forEach((value, index) => {
      const text = displayValue(value)
      const lines = wrapLines(ctx, text, valueMax, 3)
      ctx.fillStyle = text === '—' ? '#94a3b8' : INK
      const blockH = lines.length * 18
      const start = rowY + (rowH - blockH) / 2 + 9
      lines.forEach((line, lineIndex) => {
        ctx.fillText(line, PAD + LABEL_W + index * colW + colW / 2, start + lineIndex * 18)
      })
    })

    if (rowIndex < rows.length - 1) {
      ctx.strokeStyle = LINE
      ctx.beginPath()
      ctx.moveTo(PAD, rowY + rowH + 0.5)
      ctx.lineTo(PAGE_W - PAD, rowY + rowH + 0.5)
      ctx.stroke()
    }
    rowY += rowH
  })

  ctx.strokeStyle = LINE
  ctx.lineWidth = 1
  ctx.strokeRect(PAD + 0.5, tableTop + 0.5, PAGE_W - PAD * 2 - 1, tableH - 1)

  return canvas
}

function downloadBlob(blob, filename) {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}

async function savePng(canvas) {
  const blob = await new Promise((resolve, reject) => {
    canvas.toBlob((file) => (file ? resolve(file) : reject(new Error('empty'))), 'image/png')
  })
  downloadBlob(blob, 'property-comparison.png')
}

async function savePdf(canvas) {
  const { jsPDF } = await import('jspdf')
  const pdf = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4', compress: true })
  const pageWidth = pdf.internal.pageSize.getWidth()
  const pageHeight = pdf.internal.pageSize.getHeight()
  const margin = 8
  const maxWidth = pageWidth - margin * 2
  const maxHeight = pageHeight - margin * 2
  const scale = Math.min(maxWidth / canvas.width, maxHeight / canvas.height)
  const drawWidth = canvas.width * scale
  const drawHeight = canvas.height * scale
  const x = (pageWidth - drawWidth) / 2
  const y = (pageHeight - drawHeight) / 2
  pdf.addImage(canvas.toDataURL('image/jpeg', 0.92), 'JPEG', x, y, drawWidth, drawHeight)
  pdf.save('property-comparison.pdf')
}

export async function downloadComparison(payload, format = 'png') {
  const canvas = await renderSheet(payload)
  if (format === 'pdf') await savePdf(canvas)
  else await savePng(canvas)
}
