import { parsePhoneNumberFromString } from 'libphonenumber-js'

export function isNonEmptyPhoneValid(value) {
  const s = value == null ? '' : String(value).trim()
  if (!s) return true

  try {
    const phone = parsePhoneNumberFromString(s)

    // لو الرقم international (+971...)
    if (phone) return phone.isValid()

    // fallback لو الرقم بدون +
    return false
  } catch {
    return false
  }
}