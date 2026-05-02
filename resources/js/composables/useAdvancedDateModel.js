/**
 * Shared date/datetime helpers aligned with Activity reminder UX (ActivitySection + DateTimePicker).
 *
 * Date-only API fields (YYYY-MM-DD): always parse/format in **local** calendar components to avoid
 * "ISO midnight UTC" shifting the displayed day in negative-offset timezones.
 */

const WEEKDAYS_SHORT = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
const MONTHS_LONG = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
]

/**
 * Parse API / form values into a Date. Supports YYYY-MM-DD (local), ISO strings, timestamps, Date.
 * @param {string|Date|number|null|undefined} value
 * @returns {Date|null}
 */
export function parseToDate(value) {
  if (value == null || value === '') return null
  if (value instanceof Date) {
    return Number.isNaN(value.getTime()) ? null : value
  }
  if (typeof value === 'number' && Number.isFinite(value)) {
    const d = new Date(value)
    return Number.isNaN(d.getTime()) ? null : d
  }
  if (typeof value === 'string') {
    const trimmed = value.trim()
    const ymd = /^(\d{4})-(\d{2})-(\d{2})$/.exec(trimmed)
    if (ymd) {
      const y = Number(ymd[1])
      const m = Number(ymd[2]) - 1
      const day = Number(ymd[3])
      return new Date(y, m, day, 0, 0, 0, 0)
    }
    const parsed = new Date(trimmed)
    return Number.isNaN(parsed.getTime()) ? null : parsed
  }
  return null
}

/**
 * Calendar date only → YYYY-MM-DD for Laravel / form payloads (local timezone).
 * @param {Date} date
 * @returns {string}
 */
export function toDateOnlyApiString(date) {
  if (!(date instanceof Date) || Number.isNaN(date.getTime())) return ''
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

/**
 * Same visible format as ActivitySection `formattedCustomDate` / reminder picker (date + time).
 * @param {string|Date|null|undefined} dateLike
 * @param {string} emptyLabel
 */
export function formatReminderStyle(dateLike, emptyLabel = 'Select date and time') {
  const date = parseToDate(dateLike)
  if (!date) return emptyLabel

  const dayName = WEEKDAYS_SHORT[date.getDay()]
  const monthName = MONTHS_LONG[date.getMonth()]
  const day = date.getDate()
  const hours = date.getHours()
  const minutes = date.getMinutes()
  const ampm = hours >= 12 ? 'pm' : 'am'
  const displayHours = hours % 12 || 12
  const displayMinutes = minutes < 10 ? `0${minutes}` : `${minutes}`

  return `${dayName}, ${monthName} ${day}, ${displayHours}:${displayMinutes} ${ampm}`
}

/**
 * Long date-only label for DOB-style fields (no time), includes year.
 */
export function formatDateOnlyLong(dateLike, emptyLabel = 'Select date') {
  const date = parseToDate(dateLike)
  if (!date) return emptyLabel

  const dayName = WEEKDAYS_SHORT[date.getDay()]
  const monthName = MONTHS_LONG[date.getMonth()]
  const day = date.getDate()
  const year = date.getFullYear()
  return `${dayName}, ${monthName} ${day}, ${year}`
}

/**
 * Activity / API datetime payloads (reminder_date, deadlines) — preserves existing ISO contract.
 */
export function toIsoApiString(dateLike) {
  const date = parseToDate(dateLike)
  if (!date) return null
  return date.toISOString()
}

export function useAdvancedDateModel() {
  return {
    parseToDate,
    toDateOnlyApiString,
    formatReminderStyle,
    formatDateOnlyLong,
    toIsoApiString,
  }
}
