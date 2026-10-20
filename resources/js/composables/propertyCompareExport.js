/**
 * PNG export for the existing property comparison. Loaded only when the user downloads.
 */

function cellText(value) {
  return value == null || value === '' ? 'Not set' : String(value)
}

function waitForImages(root) {
  const images = [...root.querySelectorAll('img')]
  return Promise.all(images.map((image) => {
    if (image.complete) return Promise.resolve()
    return new Promise((resolve) => {
      image.onload = () => resolve()
      image.onerror = () => resolve()
    })
  }))
}

function framedPhoto(src, width, height) {
  if (!src) return Promise.resolve(null)
  return new Promise((resolve) => {
    const image = new Image()
    image.crossOrigin = 'anonymous'
    image.onload = () => {
      try {
        const canvas = document.createElement('canvas')
        canvas.width = width
        canvas.height = height
        const context = canvas.getContext('2d')
        context.fillStyle = '#f1f5f9'
        context.fillRect(0, 0, width, height)
        const scale = Math.max(width / image.width, height / image.height)
        const drawnWidth = image.width * scale
        const drawnHeight = image.height * scale
        context.drawImage(image, (width - drawnWidth) / 2, (height - drawnHeight) / 2, drawnWidth, drawnHeight)
        resolve(canvas.toDataURL('image/jpeg', 0.92))
      } catch {
        resolve(null)
      }
    }
    image.onerror = () => resolve(null)
    image.src = src
  })
}

async function sheetMarkup(payload) {
  const columns = payload.listings || []
  const groups = payload.groups || []
  const width = columns.length > 2 ? 1500 : 1180
  const photoWidth = 640
  const photoHeight = 360
  const photos = await Promise.all(columns.map((listing) => framedPhoto(listing.image, photoWidth, photoHeight)))

  const cards = columns.map((listing, index) => `
    <td style="width:${Math.floor(100 / Math.max(columns.length, 1))}%;vertical-align:top;padding:0 14px 8px 0;">
      <div style="border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;background:#fff;">
        ${photos[index]
          ? `<img src="${photos[index]}" alt="" style="display:block;width:100%;height:240px;" />`
          : '<div style="height:240px;background:#f1f5f9;color:#64748b;font-size:18px;line-height:240px;text-align:center;">No image</div>'}
        <div style="padding:16px 16px 18px;text-align:center;">
          <div style="font-size:22px;font-weight:700;line-height:1.3;">${escapeHtml(listing.name)}</div>
          <div style="margin-top:8px;font-size:18px;font-weight:700;">${escapeHtml(listing.price || 'Price not set')}</div>
        </div>
      </div>
    </td>
  `).join('')

  const sections = groups.map((group) => {
    const rows = group.rows.map((item) => `
      <tr>
        <td style="padding:14px 16px;border-top:1px solid #e2e8f0;font-weight:700;width:200px;font-size:18px;vertical-align:top;">${escapeHtml(item.label)}</td>
        ${item.values.map((value) => `
          <td style="padding:14px 16px;border-top:1px solid #e2e8f0;font-size:18px;line-height:1.4;text-align:center;vertical-align:top;color:${value === 'Not set' || value === 'Not available' ? '#64748b' : '#0f172a'};">${escapeHtml(cellText(value))}</td>
        `).join('')}
      </tr>
    `).join('')
    return `
      <table style="width:100%;border-collapse:collapse;margin-top:22px;border:1px solid #e2e8f0;border-radius:12px;">
        <tbody>${rows}</tbody>
      </table>
    `
  }).join('')

  return { width, html: `
    <div style="width:${width}px;padding:36px;background:#ffffff;color:#0f172a;font-family:Arial,Helvetica,sans-serif;">
      <div style="font-size:28px;font-weight:700;">Property comparison</div>
      <table style="width:100%;border-collapse:collapse;margin-top:22px;"><tr>${cards}</tr></table>
      ${sections}
    </div>
  ` }
}

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

async function paint(sheet) {
  const html2canvas = (await import('html2canvas')).default
  return html2canvas(sheet, {
    backgroundColor: '#ffffff',
    scale: 2,
    useCORS: true,
    allowTaint: false,
    logging: false,
  })
}

export async function downloadComparisonPng(payload) {
  const { width, html } = await sheetMarkup(payload)
  const host = document.createElement('div')
  host.style.position = 'fixed'
  host.style.left = '-10000px'
  host.style.top = '0'
  host.style.width = `${width}px`
  host.style.background = '#ffffff'
  host.innerHTML = html
  document.body.appendChild(host)
  try {
    await waitForImages(host)
    let canvas
    try {
      canvas = await paint(host)
    } catch {
      host.querySelectorAll('img').forEach((image) => image.remove())
      canvas = await paint(host)
    }
    const blob = await new Promise((resolve, reject) => {
      canvas.toBlob((file) => (file ? resolve(file) : reject(new Error('empty'))), 'image/png')
    })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = 'property-comparison.png'
    link.click()
    URL.revokeObjectURL(url)
  } finally {
    host.remove()
  }
}
