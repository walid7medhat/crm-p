/**
 * Normalize HR / CRM employee id strings for matching (handles "#EMPEMP-006" vs "EMP-006").
 * Note: A single `/EMP+/g` pass does not collapse "EMPEMP"; repeated "EMP" segments use `(EMP)+`.
 *
 * Does NOT collapse "EMPD-XXX" into "EMP-XXX" — EMP-XXX and EMPD-XXX are genuinely distinct
 * employee code series in the external biometric system (e.g. EMP-060 and EMPD-060 are two
 * different people). Collapsing them previously caused attendance data to be silently
 * overwritten between unrelated employees whenever their numeric suffix matched.
 */
export function normalizeEmployeeId(rawId) {
  if (rawId == null || rawId === '') return null

  return rawId
    .toString()
    .toUpperCase()
    .replace(/#/g, '')
    .replace(/(EMP)+/g, 'EMP')
    .replace(/EMP+/g, 'EMP')
    .trim()
}
