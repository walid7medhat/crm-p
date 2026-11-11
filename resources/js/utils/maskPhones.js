/**
 * Hide phone numbers inside free text (lead "More Information" answers / comments) the same
 * way Primary / Secondary Phone are hidden: keep the first 3 characters, star the rest.
 */

const STAR = '★'

// Links are left untouched (their ids / query strings are full of digits).
const LINK_OR_PHONE =
  /(https?:\/\/\S+|www\.\S+|<a\b[\s\S]*?<\/a>|\[url[\s\S]*?\[\/url\])|(\+?\d[\d\s\-()]{5,}\d)/gi

const DATE_LIKE = /^\d{1,4}[-\s]\d{1,2}[-\s]\d{1,4}$/

/** Question labels whose whole answer is a phone number. */
const PHONE_LABEL = /phone|mobile|whats\s*app|\btel\b|contact\s*(no|number)/i

/** '+971501234567' → '+97★★★★★★★★★★' (same as the Phone field when it is hidden). */
export function maskValue(value) {
  const text = String(value ?? '')
  if (text.length <= 3) return text
  return text.slice(0, 3) + STAR.repeat(text.length - 3)
}

function looksLikePhone(candidate) {
  const digits = candidate.replace(/\D/g, '')
  if (DATE_LIKE.test(candidate.trim())) return false
  // Long runs are phones; shorter ones only with an international / trunk prefix
  // (keeps plain budgets such as 1500000 readable).
  if (digits.length >= 9) return true
  return digits.length >= 7 && /^(\+|0)/.test(candidate.trim())
}

/** Mask every phone number found in the text, leaving everything else as is. */
export function maskPhonesInText(raw) {
  if (raw == null || raw === '') return raw
  return String(raw).replace(LINK_OR_PHONE, (match, link, phone) => {
    if (link || !phone || !looksLikePhone(phone)) return match
    return maskValue(phone)
  })
}

/** Mask one More Information answer: whole value for phone questions, else phones inside it. */
export function maskQuestionAnswer(question, answer) {
  if (answer == null || answer === '') return answer
  if (typeof answer !== 'string' && typeof answer !== 'number') return answer
  if (PHONE_LABEL.test(String(question || '').replace(/_/g, ' '))) {
    return maskValue(answer)
  }
  return maskPhonesInText(answer)
}
