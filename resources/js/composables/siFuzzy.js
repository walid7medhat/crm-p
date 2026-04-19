/** Lightweight fuzzy rank for combobox / palette (no backend). */

function norm(s) {
  return String(s || '')
    .toLowerCase()
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
}

/**
 * @param {string} text
 * @param {string} query
 * @returns {number} score 0 = no match
 */
export function fuzzyScore(text, query) {
  const t = norm(text)
  const q = norm(query).trim()
  if (!q) return 1
  if (!t) return 0
  if (t === q) return 100
  if (t.startsWith(q)) return 80
  if (t.includes(q)) return 60
  let qi = 0
  for (let i = 0; i < t.length && qi < q.length; i++) {
    if (t[i] === q[qi]) qi++
  }
  if (qi === q.length) return 40
  return 0
}

/**
 * @template T
 * @param {T[]} items
 * @param {(item: T) => string} getHaystack
 * @param {string} query
 * @returns {T[]}
 */
export function rankByFuzzy(items, getHaystack, query) {
  const q = String(query || '').trim()
  if (!q) return [...items]
  return items
    .map((item) => ({ item, s: fuzzyScore(getHaystack(item), q) }))
    .filter((x) => x.s > 0)
    .sort((a, b) => b.s - a.s)
    .map((x) => x.item)
}
