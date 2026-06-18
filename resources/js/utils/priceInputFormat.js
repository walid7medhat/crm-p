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

// ✅ دوال جديدة للتعامل مع الأرقام العشرية
export function parseDecimalPrice(raw) {
  if (!raw) return '';
  // السماح بالأرقام والنقطة العشرية
  const cleaned = String(raw).replace(/[^0-9.]/g, '');
  // معالجة النقاط العشرية المتعددة
  const parts = cleaned.split('.');
  if (parts.length > 2) {
    return parts[0] + '.' + parts.slice(1).join('');
  }
  const num = parseFloat(cleaned);
  if (isNaN(num) || num === 0) return '';
  return String(num);
}

export function formatDecimalPriceDisplay(stored) {
  if (!stored) return '';
  const num = typeof stored === 'string' ? parseFloat(stored) : Number(stored);
  if (isNaN(num) || num === 0) return '';
  return num.toLocaleString('en-AE', {
    maximumFractionDigits: 2,
    minimumFractionDigits: 0
  });
}