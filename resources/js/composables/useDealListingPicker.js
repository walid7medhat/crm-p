// Shared helpers for the "pick a sold/rented listing" picker used on secondary &
// rental deals (Create modal, change-stage modal, inline editor).
//
// Builds the /listings/properties query params based on the current user's role:
//   - super_admin / admin → see every sold listing
//   - manager / team_lead → see listings sold by anyone in their team hierarchy
//   - everyone else       → only their own sold inventory
//
// Always:
//   - filters by status (converted for secondary, rented for rental)
//   - excludes listings that are already attached to a deal (`not_in_deals=true`)

function readCurrentUser() {
  try {
    const raw = localStorage.getItem('user')
    if (!raw) return null
    return JSON.parse(raw)
  } catch (_) {
    return null
  }
}

function hasRole(user, role) {
  const list = Array.isArray(user?.roles) ? user.roles : []
  return list.includes(role)
}

export function buildListingFilterParams({ dealType, areaId, user = null }) {
  const u = user || readCurrentUser()
  const params = {
    area_id: areaId,
    per_page: 100,
    not_in_deals: true,
  }

  if (dealType === 'secondary') params.status = 'converted'
  else if (dealType === 'rental') params.status = 'rented'

  if (!u) return params

  if (hasRole(u, 'super_admin') || hasRole(u, 'admin')) {
    // No agent scope — admins/super-admins see all sold/rented inventory.
    return params
  }

  if (hasRole(u, 'manager') || hasRole(u, 'team_lead')) {
    params.sold_by_team = true
    return params
  }

  if (u.id) params.sold_by_agent_id = u.id
  return params
}

export { readCurrentUser, hasRole }
