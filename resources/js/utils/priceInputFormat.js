/** Strip non-digits for storing listing price fields (AED, whole numbers). */
export function parsePriceInputDigits(raw) {
  return String(raw ?? '').replace(/\D/g, '');
}

/** Thousand separators for display in inputs (digits-only stored value). */
export function formatPriceInputDisplay(stored) {
  const digits = parsePriceInputDigits(stored);
  if (!digits) return '';
  return digits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}
