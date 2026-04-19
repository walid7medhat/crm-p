const KEY = 'si:cmd-palette-history'
const MAX = 18

export function getSiCommandHistory() {
  try {
    const raw = localStorage.getItem(KEY)
    const arr = raw ? JSON.parse(raw) : []
    return Array.isArray(arr) ? arr : []
  } catch {
    return []
  }
}

export function pushSiCommandHistory(entry) {
  try {
    const cur = getSiCommandHistory().filter(
      (x) => !(x.kind === entry.kind && x.id === entry.id && x.tab === entry.tab)
    )
    cur.unshift({ ...entry, ts: Date.now() })
    localStorage.setItem(KEY, JSON.stringify(cur.slice(0, MAX)))
  } catch {
    /* ignore */
  }
}

export function clearSiCommandHistory() {
  try {
    localStorage.removeItem(KEY)
  } catch {
    /* ignore */
  }
}
