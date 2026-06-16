/** Shared CRM-branded PDF theme for ROI & ROE calculators */
export const PDF_WIDTH = 1123

export const C = {
  deep: '#2a1548',
  dark: '#5b3d8f',
  purple: '#7c5cbf',
  gold: '#f59e0b',
  goldDark: '#d97706',
  goldBg: '#fffbeb',
  navy: '#0b0736',
  text: '#1e1b2e',
  muted: '#64748b',
  border: '#e8e2f0',
  bg: '#f8f6fb',
  purpleBg: '#f3f0f8',
  card: '#ffffff',
  green: '#16a34a',
  greenBg: '#f0fdf4',
  blue: '#2563eb',
  blueBg: '#eff6ff',
  red: '#dc2626',
  redBg: '#fef2f2',
}

const FF = "'Segoe UI',system-ui,Arial,sans-serif"

export function esc(s) {
  return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

export function sectionTitle(text) {
  return `<table width="100%" cellpadding="0" cellspacing="0" style="margin:14px 0 8px;"><tr>
    <td style="width:4px;background:${C.gold};border-radius:2px;"></td>
    <td style="padding-left:8px;font-size:7.5pt;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:${C.dark};font-family:${FF};">${text}</td>
  </tr></table>`
}

export function th(t, tone = 'purple', align = 'left') {
  const bg = tone === 'green' ? C.green : tone === 'blue' ? C.blue : tone === 'gold' ? C.goldDark : C.dark
  return `<th style="background:${bg};color:#fff;padding:7px 9px;font-size:6.5pt;font-weight:700;letter-spacing:.08em;text-transform:uppercase;text-align:${align};font-family:${FF};white-space:nowrap;border-bottom:2px solid ${C.gold};">${t}</th>`
}

export function td(t, o = {}) {
  const align = o.align || 'left'
  const color = o.color || C.text
  const weight = o.bold ? 700 : 400
  const bg = o.bg || 'transparent'
  const size = o.small ? '7pt' : '7.5pt'
  return `<td style="padding:6px 9px;font-size:${size};text-align:${align};color:${color};font-weight:${weight};background:${bg};font-family:${FF};white-space:nowrap;border-bottom:1px solid ${C.border};">${t}</td>`
}

export function tableStyle() {
  return `font-family:${FF};font-size:7.5pt;border-collapse:collapse;width:100%;margin-bottom:12px;background:${C.card};border:1px solid ${C.border};border-radius:6px;overflow:hidden;`
}

export function rowBg(i, highlight = false) {
  if (highlight) return C.goldBg
  return i % 2 === 0 ? C.card : C.bg
}

export function kpiRow(items) {
  let html = `<table width="100%" cellpadding="0" cellspacing="6" style="border-collapse:separate;border-spacing:6px;margin-bottom:14px;"><tr>`
  items.forEach((k) => {
    const isAccent = k.accent
    const bg = isAccent ? `linear-gradient(135deg,${C.dark} 0%,${C.deep} 100%)` : C.card
    const border = isAccent ? 'none' : `1px solid ${C.border}`
    const labelColor = isAccent ? 'rgba(255,255,255,.7)' : C.muted
    const valueColor = isAccent ? C.gold : (k.color || C.navy)
    const bar = isAccent ? '' : `<td style="width:3px;background:${C.gold};border-radius:2px 0 0 2px;"></td>`
    html += `<td style="vertical-align:top;background:${isAccent ? C.dark : C.card};border:${border};border-radius:8px;overflow:hidden;">
      <table width="100%" cellpadding="0" cellspacing="0"><tr>${bar}
        <td style="padding:10px 12px;background:${isAccent ? 'transparent' : C.card};">
          <div style="font-size:6pt;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:${labelColor};margin-bottom:5px;font-family:${FF};">${k.label}</div>
          <div style="font-size:13pt;font-weight:800;color:${valueColor};line-height:1.1;font-family:${FF};">${k.value}</div>
          ${k.sub ? `<div style="font-size:6pt;color:${isAccent ? 'rgba(255,255,255,.55)' : C.muted};margin-top:4px;font-family:${FF};">${k.sub}</div>` : ''}
        </td>
      </tr></table></td>`
  })
  html += '</tr></table>'
  return html
}

export function metaGrid(rows) {
  let html = `<table width="100%" cellpadding="0" cellspacing="0" style="background:${C.purpleBg};border:1px solid ${C.border};border-radius:8px;margin-bottom:14px;overflow:hidden;">`
  for (let i = 0; i < rows.length; i += 3) {
    html += '<tr>'
    rows.slice(i, i + 3).forEach(([label, value]) => {
      html += `<td style="width:33.33%;padding:10px 12px;vertical-align:top;border-right:1px solid ${C.border};border-bottom:1px solid ${C.border};">
        <div style="font-size:6pt;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:${C.purple};margin-bottom:4px;font-family:${FF};">${label}</div>
        <div style="font-size:8.5pt;font-weight:700;color:${C.navy};font-family:${FF};">${esc(value)}</div>
      </td>`
    })
    html += '</tr>'
  }
  html += '</table>'
  return html
}

export function pdfHeader({ reportTitle, clientName, projectLine, todayLabel, agentName }) {
  return `<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:14px;border-radius:10px;overflow:hidden;border:1px solid ${C.border};">
    <tr><td colspan="2" style="height:4px;background:linear-gradient(90deg,${C.gold},${C.purple});padding:0;font-size:0;line-height:0;">&nbsp;</td></tr>
    <tr style="background:linear-gradient(135deg,${C.deep} 0%,${C.dark} 55%,${C.purple} 100%);">
      <td style="padding:16px 20px;vertical-align:top;">
        <div style="font-size:17pt;font-weight:800;color:${C.gold};letter-spacing:.04em;font-family:${FF};">OIA PROPERTIES</div>
        <div style="font-size:7pt;color:rgba(255,255,255,.65);letter-spacing:.14em;text-transform:uppercase;margin-top:4px;font-family:${FF};">${reportTitle}</div>
      </td>
      <td style="padding:16px 20px;text-align:right;vertical-align:top;">
        <div style="font-size:9pt;font-weight:700;color:#fff;font-family:${FF};">${esc(clientName || 'Client')}</div>
        <div style="font-size:7.5pt;color:rgba(255,255,255,.6);margin-top:3px;font-family:${FF};">${esc(projectLine || '')}</div>
        <div style="font-size:7pt;color:${C.gold};margin-top:5px;font-family:${FF};">Prepared: ${todayLabel}${agentName ? ` · ${esc(agentName)}` : ''}</div>
      </td>
    </tr>
  </table>`
}

export function pdfDisclaimer(text) {
  return `<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:14px;background:${C.purpleBg};border:1px solid ${C.border};border-radius:8px;">
    <tr><td style="padding:10px 14px;font-size:6.5pt;color:${C.muted};line-height:1.65;font-family:${FF};">
      <strong style="color:${C.dark};">Disclaimer:</strong> ${text}
      <div style="margin-top:6px;"><strong style="color:${C.navy};">Oia Properties</strong> · oiaproperties.com · Abu Dhabi, UAE · +971 2 444 0089 · 27 years UAE real estate</div>
    </td></tr>
  </table>`
}

export function pdfShell({ header, meta, body, disclaimer }) {
  return `<div style="font-family:${FF};background:${C.bg};color:${C.text};width:${PDF_WIDTH}px;padding:16px 18px 20px;">
    ${header}
    ${meta}
    <div style="background:${C.card};border:1px solid ${C.border};border-radius:10px;padding:14px 16px;">
      ${body}
    </div>
    ${disclaimer}
  </div>`
}

export async function waitForPdfPaint() {
  await new Promise((resolve) => { requestAnimationFrame(() => requestAnimationFrame(resolve)) })
  await new Promise((resolve) => setTimeout(resolve, 350))
}

export async function exportPdfFromElement(element, filename) {
  if (!element) return false
  await waitForPdfPaint()
  try {
    const html2pdf = (await import('html2pdf.js')).default
    const pdf = await html2pdf().set({
      margin: [6, 6, 6, 6],
      filename,
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: {
        scale: 2,
        useCORS: true,
        allowTaint: true,
        backgroundColor: C.bg,
        logging: false,
        width: PDF_WIDTH,
        windowWidth: PDF_WIDTH,
        scrollX: 0,
        scrollY: 0,
      },
      jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' },
      pagebreak: { mode: ['css', 'legacy'] },
    }).from(element).toPdf().get('pdf')
    pdf.save(filename)
    return true
  } catch (err) {
    console.error('PDF export failed:', err)
    return false
  }
}

export async function downloadPdfHtml({ bodyHtml, filename, mountElement }) {
  if (mountElement) {
    mountElement.innerHTML = bodyHtml
    return exportPdfFromElement(mountElement, filename)
  }
  const container = document.createElement('div')
  container.setAttribute('aria-hidden', 'true')
  container.style.cssText = `position:fixed;top:0;left:0;width:${PDF_WIDTH}px;background:${C.bg};z-index:2147483646;pointer-events:none;`
  container.innerHTML = bodyHtml
  document.body.appendChild(container)
  try {
    return await exportPdfFromElement(container, filename)
  } finally {
    document.body.removeChild(container)
  }
}
