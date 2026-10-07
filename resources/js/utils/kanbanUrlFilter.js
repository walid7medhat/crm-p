/**
 * Links that open the Leads / Deals board already filtered (e.g. from the home dashboard).
 *
 *   /kanban?filter=<json>       leads board
 *   /kanban_deal?filter=<json>  deals board
 *
 * The JSON holds the same query object the search modal produces (status_lead, stage_id,
 * source, created_from/created_to, responsible_person_id, …), the filter chips to show in
 * the navbar, and for deals the deal type tab. kanban_deal.vue applies it once the board
 * is ready, tells the navbar (chips), then removes ?filter from the URL so a reload
 * doesn't re-apply a stale filter.
 */

/**
 * @param {'leads'|'deals'} board
 * @param {object} query  search query (same keys as the search modal)
 * @param {{ id: string, queryKey: string, label: string, value: string }[]} [chips]
 * @param {{ dealType?: 'primary'|'secondary'|'rental' }} [options]
 * @returns {{ path: string, query: { filter: string } }}
 */
export function kanbanFilterLink(board, query = {}, chips = [], options = {}) {
  const clean = {}
  Object.entries(query || {}).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '' && !(Array.isArray(value) && !value.length)) {
      clean[key] = value
    }
  })
  const payload = { board, query: clean, chips }
  if (board === 'deals' && options.dealType) payload.dealType = options.dealType
  return {
    path: board === 'deals' ? '/kanban_deal' : '/kanban',
    query: { filter: JSON.stringify(payload) },
  }
}

/**
 * Navigate to a kanbanFilterLink(). Saves the board tab (and deal type) BEFORE navigating:
 * the navbar and the boards start from these saved values — when they differed, the
 * navbar's tab switch cleared the search and the deals board's type switch reloaded with
 * no filter, wiping the filter we were opening with.
 */
export function openKanbanFilterLink(router, location) {
  const parsed = readKanbanUrlFilter({ query: location?.query })
  if (parsed) {
    try {
      localStorage.setItem('kanban_active_tab', parsed.board === 'deals' ? 'deals' : 'leads')
      if (parsed.board === 'deals' && parsed.dealType) {
        localStorage.setItem('kanban_deal_type', parsed.dealType)
      }
    } catch {
      /* ignore */
    }
  }
  return router.push(location).catch(() => {})
}

/** Parsed ?filter=… from a route, or null when missing / invalid. */
export function readKanbanUrlFilter(route) {
  const raw = route?.query?.filter
  if (!raw || typeof raw !== 'string') return null
  try {
    const parsed = JSON.parse(raw)
    if (!parsed || typeof parsed !== 'object') return null
    const board = parsed.board === 'deals' ? 'deals' : 'leads'
    return {
      board,
      query: parsed.query && typeof parsed.query === 'object' ? parsed.query : {},
      chips: Array.isArray(parsed.chips) ? parsed.chips : [],
      dealType: ['primary', 'secondary', 'rental'].includes(parsed.dealType) ? parsed.dealType : null,
    }
  } catch {
    return null
  }
}
