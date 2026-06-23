/**
 * Normalize Echo / Pusher payload (some clients nest under `.data`).
 */
export function normalizeLeadRealtimeEvent(event) {
    if (!event || typeof event !== 'object') {
        return event
    }
    const nested = event.data
    if (
        nested &&
        typeof nested === 'object' &&
        (nested.source != null ||
            nested.lead_id != null ||
            nested.action_type != null ||
            nested.lead != null)
    ) {
        return nested
    }
    return event
}

/**
 * Skip user-facing toasts for Bitrix sync; always show CRM-originated updates.
 */
export function shouldSuppressLeadUpdateNotification(event) {
    const payload = normalizeLeadRealtimeEvent(event)

    if (payload?.source === 'crm' || payload?.user_id) {
        return false
    }
    if (payload?.source === 'bitrix') {
        return true
    }

    const leadData = payload?.lead?.data || payload?.lead
    const bitrixId = leadData?.bitrix24_id
    // Legacy Bitrix webhook: synced lead, no CRM actor (includes create + update).
    if (!payload?.user_id && bitrixId != null && bitrixId !== '') {
        return true
    }

    return false
}

/**
 * Build a local CRM notification payload after a successful API save.
 */
export function buildCrmLeadNotificationEvent(lead, actionType = 'updated', actor = null) {
    return {
        source: 'crm',
        action_type: actionType,
        user_id: actor?.id ?? null,
        user_name: actor?.name ?? null,
        lead,
    }
}
