/**
 * vue-select (append-to-body), vue-tel-input, budget portal, calendars, modals, etc.
 * render outside the kanban search anchor. Document-level mousedown handlers must
 * ignore these or filters / phone / date fields appear broken.
 */

function classString(el) {
  if (!el || el.nodeType !== 1) return ''
  const c = el.className
  if (typeof c === 'string') return c
  if (c && typeof c.baseVal === 'string') return c.baseVal
  return String(c || '')
}

/** Heuristic: does this element look like an overlay menu used by search fields? */
function elementLooksLikePortaledSearchUi(el) {
  const s = classString(el)
  if (!s) return false
  // vue-select 3/4 (append-to-body)
  if (s.includes('vs__dropdown')) return true
  if (s.includes('vs__select-list')) return true
  // vue-tel-input (country list is often portaled; avoid matching vti__input inside field)
  if (s.includes('vti__dropdown')) return true
  // Lead search budget popover
  if (s.includes('budget-dropdown--portal')) return true
  // Shared date picker (Deal search DOB / etc.)
  if (s.includes('date-time-picker-overlay')) return true
  if (s.includes('date-time-picker-modal')) return true
  // Lead / deal custom range modals
  if (s.includes('lr-modal-backdrop')) return true
  if (s.includes('lr-date-modal')) return true
  return false
}

function closestPortaledMatch(target) {
  if (!target || typeof target.closest !== 'function') return false

  if (target.closest('.modal')) return true
  if (elementLooksLikePortaledSearchUi(target)) return true
  if (target.closest('.budget-dropdown--portal')) return true
  if (target.closest('.vs__dropdown-menu')) return true
  if (target.closest('.vs__select-list')) return true
  if (target.closest('[class*="vs__dropdown"]')) return true
  if (target.closest('.vti__dropdown')) return true
  if (target.closest('.vti__dropdown-list')) return true
  if (target.closest('.vti__dropdown-item')) return true
  if (target.closest('.date-time-picker-overlay')) return true
  if (target.closest('.lr-modal-backdrop')) return true
  if (target.closest('.lr-date-modal')) return true
  if (target.closest('.flatpickr-calendar')) return true
  if (target.closest('.b-calendar-grid')) return true
  if (target.closest('.b-popover')) return true
  if (target.closest('.swal2-container')) return true

  return false
}

/**
 * @param {EventTarget|null} target
 * @param {MouseEvent} [event] — pass through for composedPath (shadow DOM / retargeting)
 */
export function isKanbanSearchPortaledUiClick(target, event) {
  if (closestPortaledMatch(target)) return true

  if (event && typeof event.composedPath === 'function') {
    for (const node of event.composedPath()) {
      if (!node || node === document || node === window) continue
      if (node.nodeType === 1 && elementLooksLikePortaledSearchUi(node)) return true
      if (node.nodeType === 1 && typeof node.closest === 'function') {
        if (node.closest('.modal')) return true
      }
    }
  }

  return false
}
