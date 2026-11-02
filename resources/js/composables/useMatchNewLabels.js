/**
 * "New" badge in lead matching view — only for the listing the agent opened
 * from a match notification / Show (not every scored match).
 * Hidden 24 hours after that open.
 */
const STORAGE_KEY = 'crm.matchNewOpened.v1'
const DAY_MS = 24 * 60 * 60 * 1000

function readMap() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    const parsed = raw ? JSON.parse(raw) : {}
    return parsed && typeof parsed === 'object' ? parsed : {}
  } catch {
    return {}
  }
}

function writeMap(map) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(map))
  } catch { /* quota / private mode */ }
}

function keyFor(leadId, listingId) {
  return `${Number(leadId)}:${Number(listingId)}`
}

/** Stamp the notified listing when Show opens the lead (idempotent). */
export function markMatchOpenedOnLead(leadId, listingId) {
  const id = Number(leadId)
  const listing = Number(listingId)
  if (!id || !listing) return null
  const map = readMap()
  const key = keyFor(id, listing)
  if (!map[key]) {
    map[key] = Date.now()
    writeMap(map)
  }
  return map[key]
}

/** True only if this lead+listing was stamped from Show and still within 24h. */
export function isMatchNewOnLead(leadId, listingId) {
  const id = Number(leadId)
  const listing = Number(listingId)
  if (!id || !listing) return false
  const map = readMap()
  const opened = map[keyFor(id, listing)]
  if (!opened) return false
  return Date.now() - Number(opened) < DAY_MS
}
