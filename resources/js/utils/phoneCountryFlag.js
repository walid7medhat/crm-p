/**
 * Runtime phone-country flag helpers for Kanban / Lead Pool cards.
 * Mirrors LeadSearchPhoneField flag emoji + PhoneSearch UAE rules;
 * international detection uses libphonenumber-js (already in the project).
 * No API / DB involvement — detects from the displayed work_phone string.
 */

import { parsePhoneNumberFromString, getCountryCallingCode } from 'libphonenumber-js'

const regionNames =
  typeof Intl !== 'undefined' && Intl.DisplayNames
    ? new Intl.DisplayNames(['en'], { type: 'region' })
    : null

/** Same mobile / landline trunks as App\Support\PhoneSearch. */
const UAE_MOBILE = ['50', '52', '54', '55', '56', '58']
const UAE_AREAS = ['2', '3', '4', '6', '7', '9']

const UAE_STRICT_RULES = (() => {
  const rules = []
  for (const prefix of UAE_MOBILE) {
    rules.push([`0${prefix}`, 10])
    rules.push([`971${prefix}`, 12])
    rules.push([`9710${prefix}`, 13])
    rules.push([`00971${prefix}`, 14])
    rules.push([`009710${prefix}`, 15])
  }
  for (const area of UAE_AREAS) {
    rules.push([`0${area}`, 9])
    rules.push([`971${area}`, 11])
    rules.push([`9710${area}`, 12])
    rules.push([`00971${area}`, 13])
    rules.push([`009710${area}`, 14])
  }
  return rules
})()

/** phone string → ISO (or '') — avoids re-parsing the same number on every render */
const isoCache = new Map()
const MAX_CACHE = 2000

function phoneDigits(phone) {
  return String(phone || '')
    .replace(/[+\s\-()./]/g, '')
    .slice(0, 64)
}

function isUaeDigits(digits) {
  if (!digits) return false
  return UAE_STRICT_RULES.some(
    ([prefix, length]) => digits.length === length && digits.startsWith(prefix),
  )
}

function internationalCandidates(raw, digits) {
  const candidates = []
  if (raw.includes('+')) candidates.push(raw)
  if (digits.startsWith('00') && digits.length > 2) {
    candidates.push(`+${digits.slice(2)}`)
  }
  // Bare "9715…" / "4479…" (Bitrix/Meta) — only accepted when parse is valid.
  if (digits && !digits.startsWith('0') && digits.length >= 10) {
    candidates.push(`+${digits}`)
  }
  return [...new Set(candidates)]
}

/**
 * @returns {string} Uppercase ISO 3166-1 alpha-2, or '' when unknown / invalid.
 */
export function detectPhoneCountryIso(phone) {
  const raw = String(phone ?? '').trim()
  if (!raw) return ''

  if (isoCache.has(raw)) return isoCache.get(raw)

  let iso = ''
  const digits = phoneDigits(raw)

  if (isUaeDigits(digits)) {
    iso = 'AE'
  } else {
    for (const candidate of internationalCandidates(raw, digits)) {
      try {
        const parsed = parsePhoneNumberFromString(candidate)
        if (!parsed || !parsed.isValid()) continue
        const country = String(parsed.country || '').toUpperCase()
        if (/^[A-Z]{2}$/.test(country) && country !== 'ZZ') {
          iso = country
          break
        }
      } catch {
        // try next candidate
      }
    }
  }

  if (isoCache.size >= MAX_CACHE) isoCache.clear()
  isoCache.set(raw, iso)
  return iso
}

/** Same regional-indicator approach as LeadSearchPhoneField.flagEmoji */
export function flagEmojiFromIso(iso) {
  const code = String(iso || '')
    .trim()
    .toUpperCase()
  if (!/^[A-Z]{2}$/.test(code) || code === 'ZZ') return ''
  return code.replace(/./g, (char) => String.fromCodePoint(127397 + char.charCodeAt(0)))
}

export function countryNameFromIso(iso) {
  const code = String(iso || '')
    .trim()
    .toUpperCase()
  if (!/^[A-Z]{2}$/.test(code)) return ''
  try {
    return regionNames?.of(code) || code
  } catch {
    return code
  }
}

export function callingCodeFromIso(iso) {
  const code = String(iso || '')
    .trim()
    .toUpperCase()
  if (!/^[A-Z]{2}$/.test(code)) return ''
  try {
    return String(getCountryCallingCode(code) || '')
  } catch {
    return ''
  }
}

/** e.g. "United Arab Emirates · +971" */
export function phoneCountryTooltip(iso) {
  const name = countryNameFromIso(iso)
  if (!name) return ''
  const dial = callingCodeFromIso(iso)
  return dial ? `${name} · +${dial}` : name
}

/** Tiny flag emoji for a raw work_phone, or '' when unknown. */
export function phoneCountryFlagFromPhone(phone) {
  return flagEmojiFromIso(detectPhoneCountryIso(phone))
}

/** Hover title for a raw work_phone, or ''. */
export function phoneCountryTitleFromPhone(phone) {
  return phoneCountryTooltip(detectPhoneCountryIso(phone))
}
