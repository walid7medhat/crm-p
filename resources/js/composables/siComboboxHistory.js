const PREFIX = 'si:cb-recent:'
const MAX = 8

export function getSiComboboxRecents(key) {
  if (!key) return []
  try {
    const raw = localStorage.getItem(PREFIX + key)
    const arr = raw ? JSON.parse(raw) : []
    return Array.isArray(arr) ? arr : []
  } catch {
    return []
  }
}

export function pushSiComboboxRecent(key, entry) {
  if (!key || entry == null) return
  try {
    const v = typeof entry === 'object' ? entry : { value: entry, label: String(entry) }
    const cur = getSiComboboxRecents(key).filter((x) => String(x.value) !== String(v.value))
    cur.unshift({ value: v.value, label: v.label || String(v.value) })
    localStorage.setItem(PREFIX + key, JSON.stringify(cur.slice(0, MAX)))
  } catch {
    /* ignore */
  }
}
