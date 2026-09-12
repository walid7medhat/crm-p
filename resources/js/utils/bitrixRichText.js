/**
 * Format Bitrix24 BBCode / rich-text comments for safe HTML display.
 * Handles [url], [p], bare URLs, and strips remaining BBCode tags.
 */

function escapeHtml(text) {
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

function sanitizeHref(url) {
  const raw = String(url || '').trim()
  if (!raw) return null
  // Allow http(s) and protocol-relative; reject javascript:/data: etc.
  if (/^(https?:\/\/|\/\/)/i.test(raw)) return raw
  if (/^[a-z][a-z0-9+.-]*:/i.test(raw)) return null
  return `https://${raw}`
}

function linkHtml(href, label) {
  const safeHref = sanitizeHref(href)
  if (!safeHref) return escapeHtml(label || href)
  const safeLabel = escapeHtml(label || href)
  return `<a href="${escapeHtml(safeHref)}" target="_blank" rel="noopener noreferrer" class="bitrix-rich-link">${safeLabel}</a>`
}

/**
 * @param {string|null|undefined} raw
 * @returns {string} Safe HTML string (empty string when nothing to show)
 */
export function formatBitrixRichText(raw) {
  if (raw == null || raw === '') return ''

  let text = String(raw)
  text = text.replace(/&nbsp;/gi, ' ')
  text = text.replace(/<br\s*\/?>/gi, '\n')

  // Placeholder tokens so we can escape the rest safely
  const links = []
  const stash = (html) => {
    const token = `\u0000LINK${links.length}\u0000`
    links.push(html)
    return token
  }

  // [url=href]label[/url]
  text = text.replace(/\[url=([^\]]+)\]([\s\S]*?)\[\/url\]/gi, (_, href, label) => {
    return stash(linkHtml(href, String(label).trim() || href))
  })

  // [url]href[/url]
  text = text.replace(/\[url\]([\s\S]*?)\[\/url\]/gi, (_, href) => {
    const clean = String(href).trim()
    return stash(linkHtml(clean, clean))
  })

  // Strip common Bitrix BBCode wrappers (keep inner text)
  text = text.replace(/\[(\/)?(p|b|i|u|s|code|quote|list|\*|size|color|font|left|center|right|justify)(=[^\]]*)?\]/gi, '')

  // Escape remaining text
  text = escapeHtml(text)

  // Linkify bare URLs that were not already wrapped
  text = text.replace(
    /(https?:\/\/[^\s<&]+)|(www\.[^\s<&]+)/gi,
    (match) => {
      // Trim common trailing punctuation from auto-detected URLs
      let url = match
      let trailing = ''
      while (/[.,);:!?]$/.test(url)) {
        trailing = url.slice(-1) + trailing
        url = url.slice(0, -1)
      }
      return stash(linkHtml(url, url)) + trailing
    }
  )

  // Restore link HTML
  text = text.replace(/\u0000LINK(\d+)\u0000/g, (_, idx) => links[Number(idx)] || '')

  // Preserve line breaks
  text = text.replace(/\n/g, '<br>')

  return text.trim()
}

/**
 * Extract Property Finder / Bayut (and similar portal) URLs from free text / BBCode.
 * @param {string|null|undefined} raw
 * @returns {{ url: string, label: string, portal: 'bayut'|'propertyfinder'|'other' }[]}
 */
export function extractPortalLinks(raw) {
  if (raw == null || raw === '') return []

  let text = String(raw)
  const found = []
  const seen = new Set()

  const push = (url) => {
    let safe = sanitizeHref(url)
    if (!safe) return
    // Drop accidental BBCode leftovers from partial matches
    safe = safe.replace(/\[\/?url[^\]]*\]/gi, '').replace(/\[\/?[a-z0-9=]+\]/gi, '')
    safe = sanitizeHref(safe)
    if (!safe) return

    const key = safe.toLowerCase().replace(/\/+$/, '')
    if (seen.has(key)) return
    seen.add(key)

    const lower = safe.toLowerCase()
    let portal = 'other'
    let label = 'Portal link'
    if (lower.includes('bayut.com')) {
      portal = 'bayut'
      label = 'Bayut'
    } else if (lower.includes('propertyfinder') || lower.includes('property-finder')) {
      portal = 'propertyfinder'
      label = 'Property Finder'
    }

    found.push({ url: safe, label, portal })
  }

  // [url=href]label[/url] — extract then remove so bare-URL scan does not double-match
  text = text.replace(/\[url=([^\]]+)\]([\s\S]*?)\[\/url\]/gi, (_, href) => {
    push(href)
    return ' '
  })

  // [url]href[/url]
  text = text.replace(/\[url\]([\s\S]*?)\[\/url\]/gi, (_, href) => {
    push(String(href).trim())
    return ' '
  })

  // Strip remaining simple BBCode wrappers
  text = text.replace(/\[(\/)?(p|b|i|u|s|code|quote|list|\*|size|color|font)(=[^\]]*)?\]/gi, ' ')

  // Bare URLs (exclude '[' so BBCode fragments cannot attach)
  text.replace(/(https?:\/\/[^\s<&\[\]]+)|(www\.[^\s<&\[\]]+)/gi, (match) => {
    let url = match
    while (/[.,);:!?]$/.test(url)) url = url.slice(0, -1)
    push(url)
    return ''
  })

  return found.filter((item) => item.portal === 'bayut' || item.portal === 'propertyfinder' || item.portal === 'other')
}
