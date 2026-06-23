/**
 * Whether to skip user-facing notifications for a `lead.updated` Echo payload.
 * Only Bitrix sync events are suppressed; CRM events always notify (Test 5).
 */
export function shouldSuppressLeadUpdateNotification(event) {
    // CRM actions always notify — including edits to Bitrix-synced leads (Test 5).
    if (event?.source === 'crm' || event?.user_id) {
        return false
    }
    return event?.source === 'bitrix'
}
