export const LEAD_DRAFT_VERSION = 1
export const LEAD_DRAFT_TTL_MS = 14 * 24 * 60 * 60 * 1000
const MAX_DRAFT_CHARS = 200_000
const SENSITIVE_KEYS = new Set([
    'password',
    'password_confirmation',
    'token',
    'access_token',
    'refresh_token',
    'remember_token',
    'authorization',
    'api_token',
])

export function normalizeDraftVersion(value) {
    if (value == null || value === '') return null
    if (typeof value === 'string' || typeof value === 'number') return String(value)
    try {
        return JSON.stringify(value)
    } catch {
        return null
    }
}

export function buildLeadDraftKey({ origin, userId, scope, leadId }) {
    const safeOrigin = encodeURIComponent(String(origin || 'local'))
    const safeUser = encodeURIComponent(String(userId || 'none'))
    const safeScope = scope === 'edit' ? 'edit' : 'create'
    const safeLead = safeScope === 'edit' ? encodeURIComponent(String(leadId || 'unknown')) : 'new'
    return `oia.leadDraft.v1.${safeOrigin}.${safeUser}.${safeScope}.${safeLead}`
}

export function stableSerialize(value) {
    return JSON.stringify(sortValue(value))
}

function sortValue(value) {
    if (Array.isArray(value)) return value.map(sortValue)
    if (value && typeof value === 'object') {
        return Object.keys(value)
            .sort()
            .reduce((out, key) => {
                const next = value[key]
                if (typeof next === 'undefined' || typeof next === 'function') return out
                out[key] = sortValue(next)
                return out
            }, {})
    }
    return value
}

export function pickFields(source, fields) {
    const out = {}
    fields.forEach((key) => {
        const value = source?.[key]
        out[key] = value === undefined ? null : sanitizeDraftValue(value)
    })
    return out
}

export function sanitizeDraftValue(value, depth = 0) {
    if (depth > 6) return null
    if (value == null) return value
    if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') return value
    if (Array.isArray(value)) return value.slice(0, 50).map((item) => sanitizeDraftValue(item, depth + 1))
    if (typeof value === 'object') {
        const out = {}
        Object.keys(value).forEach((key) => {
            if (SENSITIVE_KEYS.has(String(key).toLowerCase())) return
            const next = value[key]
            if (typeof next === 'undefined' || typeof next === 'function') return
            out[key] = sanitizeDraftValue(next, depth + 1)
        })
        return out
    }
    return null
}

export function decideLeadDraftRecovery(record, currentVersion) {
    if (!isDraftRecord(record)) return 'drop'
    const current = normalizeDraftVersion(currentVersion)
    const base = normalizeDraftVersion(record.baseVersion)
    if (base !== null && current !== null && base !== current) return 'prompt'
    if (base === null && current !== null) return 'prompt'
    return 'restore'
}

function isDraftRecord(record) {
    return !!record
        && record.v === LEAD_DRAFT_VERSION
        && record.data
        && typeof record.data === 'object'
        && !Array.isArray(record.data)
}

export function readLeadDraft(storage, key, expected = {}) {
    let raw = null
    try {
        raw = storage.getItem(key)
    } catch {
        return null
    }
    if (!raw) return null

    let parsed = null
    try {
        parsed = JSON.parse(raw)
    } catch {
        removeLeadDraft(storage, key)
        return null
    }

    const matchesOwner = !expected.userId || String(parsed?.userId) === String(expected.userId)
    const matchesScope = !expected.scope || parsed?.scope === expected.scope
    const matchesLead = expected.scope !== 'edit'
        || String(parsed?.leadId ?? '') === String(expected.leadId ?? '')
    const fresh = Number(parsed?.savedAt) > 0 && (Date.now() - Number(parsed.savedAt)) <= LEAD_DRAFT_TTL_MS

    if (!isDraftRecord(parsed) || !matchesOwner || !matchesScope || !matchesLead || !fresh) {
        removeLeadDraft(storage, key)
        return null
    }

    return parsed
}

export function writeLeadDraft(storage, key, record) {
    let existing = null
    try {
        const raw = storage.getItem(key)
        existing = raw ? JSON.parse(raw) : null
    } catch {
        existing = null
    }

    if (existing && Number(existing.savedAt) > Number(record.savedAt)) {
        return { ok: false, reason: 'stale' }
    }

    const payload = {
        v: LEAD_DRAFT_VERSION,
        savedAt: Number(record.savedAt) || Date.now(),
        userId: record.userId,
        scope: record.scope,
        leadId: record.leadId ?? null,
        baseVersion: normalizeDraftVersion(record.baseVersion),
        data: sanitizeDraftValue(record.data) || {},
    }

    try {
        const json = JSON.stringify(payload)
        if (json.length > MAX_DRAFT_CHARS) return { ok: false, reason: 'too-large' }
        storage.setItem(key, json)
        return { ok: true }
    } catch {
        return { ok: false, reason: 'unavailable' }
    }
}

export function removeLeadDraft(storage, key) {
    try {
        storage.removeItem(key)
    } catch {
        /* ignore private-mode and quota failures */
    }
}
