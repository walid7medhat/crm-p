/**
 * Normalize HR / CRM employee id strings for matching (handles "#EMPEMP-006" vs "EMP-006").
 * Note: A single `/EMP+/g` pass does not collapse "EMPEMP"; repeated "EMP" segments use `(EMP)+`.
 */
export function normalizeEmployeeId(rawId) {
  if (rawId == null || rawId === '') return null

  return rawId
    .toString()
    .toUpperCase()
    .replace(/#/g, '')
    .replace(/EMPD/gi, 'EMP')
    .replace(/(EMP)+/g, 'EMP')
    .replace(/EMP+/g, 'EMP')
    .trim()
}
