/** Parse payment_breakdown from API (array or JSON string). */
export function parsePaymentBreakdown(raw) {
  if (raw == null || raw === '') return [];
  if (Array.isArray(raw)) return raw;
  if (typeof raw === 'string') {
    try {
      const parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return [];
    }
  }
  return [];
}

export function isUnderConstructionListing(property) {
  const s = String(property?.completion_status ?? '')
    .trim()
    .toLowerCase()
    .replace(/_/g, ' ');
  return s === 'under construction' || s === 'off plan';
}

export function listingHasPaymentBreakdown(property) {
  if (property?.has_payment_breakdown === true) return true;
  if (property?.has_payment_breakdown === false) return false;
  return parsePaymentBreakdown(property?.payment_breakdown).length > 0;
}

/** Off-plan listing with no installment breakdown rows yet. */
export function listingNeedsPaymentBreakdownHighlight(property) {
  return  !listingHasPaymentBreakdown(property);
}

export function getStoredAuthUser() {
  try {
    const userStr = localStorage.getItem('user');
    if (!userStr) return null;
    return JSON.parse(userStr);
  } catch {
    return null;
  }
}

export function getUserRoleNames(user) {
  if (!user) return [];
  const names = [];
  if (user.role_name) names.push(String(user.role_name).toLowerCase());
  if (user.role) names.push(String(user.role).toLowerCase());
  if (Array.isArray(user.roles)) {
    user.roles.forEach((r) => {
      if (typeof r === 'string') names.push(r.toLowerCase());
      else if (r?.name) names.push(String(r.name).toLowerCase());
    });
  }
  return [...new Set(names.filter(Boolean))];
}

export function isPaymentBreakdownAdminUser(user = getStoredAuthUser()) {
  const roles = getUserRoleNames(user);
  return roles.some((r) => r === 'super_admin' || r === 'admin');
}

/** Sales / agents: only listings where they are the assigned agent. Admins: all. */
export function canQuickEditPaymentBreakdown(property) {
  if (property?.can_edit_payment_breakdown === true) return true;
  if (property?.can_edit_payment_breakdown === false) return false;

  const user = getStoredAuthUser();
  if (!user?.id) return false;

  if (isPaymentBreakdownAdminUser(user)) return true;

  const agentId = property?.agent?.id ?? property?.agent_id;
  if (agentId == null) return false;

  return Number(agentId) === Number(user.id);
}
