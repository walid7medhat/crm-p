import { isValidPhoneNumber } from 'libphonenumber-js'

/**
 * @param {string|null|undefined} value
 * @returns {boolean} true if empty, or a valid international number
 */
export function isNonEmptyPhoneValid(value) {
  const s = value == null ? '' : String(value).trim()
  if (!s) return true
  try {
    return isValidPhoneNumber(s)
  } catch {
    return false
  }
}
