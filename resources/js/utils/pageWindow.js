/**
 * Compact page list for pagination: first, last, and a window around the current
 * page, with '…' gaps. e.g. (6, 20) → [1, '…', 5, 6, 7, '…', 20]
 */
export function pageWindow(current, total, radius = 1) {
  const last = Math.max(1, Number(total) || 1);
  const page = Math.min(Math.max(1, Number(current) || 1), last);
  const pages = [];
  for (let i = 1; i <= last; i++) {
    if (i === 1 || i === last || (i >= page - radius && i <= page + radius)) {
      pages.push(i);
    } else if (pages[pages.length - 1] !== '…') {
      pages.push('…');
    }
  }
  return pages;
}
