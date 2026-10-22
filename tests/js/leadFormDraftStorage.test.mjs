import test from 'node:test'
import assert from 'node:assert/strict'
import {
    LEAD_DRAFT_TTL_MS,
    buildLeadDraftKey,
    decideLeadDraftRecovery,
    pickFields,
    readLeadDraft,
    removeLeadDraft,
    sanitizeDraftValue,
    stableSerialize,
    writeLeadDraft,
} from '../../resources/js/utils/leadFormDraftStorage.js'

function memoryStorage() {
    const data = new Map()
    return {
        getItem: (key) => (data.has(key) ? data.get(key) : null),
        setItem: (key, value) => data.set(key, String(value)),
        removeItem: (key) => data.delete(key),
    }
}

test('draft keys isolate user, environment, scope, and lead', () => {
    const createA = buildLeadDraftKey({ origin: 'https://crm.example', userId: '4', scope: 'create' })
    const createB = buildLeadDraftKey({ origin: 'https://crm.example', userId: '9', scope: 'create' })
    const editA = buildLeadDraftKey({ origin: 'https://crm.example', userId: '4', scope: 'edit', leadId: 15 })
    const editB = buildLeadDraftKey({ origin: 'https://crm.example', userId: '4', scope: 'edit', leadId: 16 })
    const otherEnv = buildLeadDraftKey({ origin: 'https://staging.example', userId: '4', scope: 'edit', leadId: 15 })
    assert.notEqual(createA, createB)
    assert.notEqual(createA, editA)
    assert.notEqual(editA, editB)
    assert.notEqual(editA, otherEnv)
})

test('older writes cannot replace a newer draft', () => {
    const storage = memoryStorage()
    const key = buildLeadDraftKey({ origin: 'https://crm.example', userId: '4', scope: 'edit', leadId: 8 })
    const newer = writeLeadDraft(storage, key, {
        savedAt: 200,
        userId: '4',
        scope: 'edit',
        leadId: 8,
        baseVersion: 'v2',
        data: { form: { lead_name: 'New' } },
    })
    const older = writeLeadDraft(storage, key, {
        savedAt: 100,
        userId: '4',
        scope: 'edit',
        leadId: 8,
        baseVersion: 'v2',
        data: { form: { lead_name: 'Old' } },
    })
    assert.equal(newer.ok, true)
    assert.equal(older.ok, false)
    assert.equal(older.reason, 'stale')
    assert.equal(JSON.parse(storage.getItem(key)).data.form.lead_name, 'New')
})

test('corrupt, expired, and foreign drafts are dropped', () => {
    const storage = memoryStorage()
    const key = buildLeadDraftKey({ origin: 'https://crm.example', userId: '4', scope: 'create' })
    storage.setItem(key, '{not json')
    assert.equal(readLeadDraft(storage, key, { userId: '4', scope: 'create' }), null)
    assert.equal(storage.getItem(key), null)

    writeLeadDraft(storage, key, {
        savedAt: Date.now() - LEAD_DRAFT_TTL_MS - 1000,
        userId: '4',
        scope: 'create',
        data: { form: { lead_name: 'Expired' } },
    })
    assert.equal(readLeadDraft(storage, key, { userId: '4', scope: 'create' }), null)

    const otherKey = buildLeadDraftKey({ origin: 'https://crm.example', userId: '9', scope: 'create' })
    writeLeadDraft(storage, otherKey, {
        savedAt: Date.now(),
        userId: '9',
        scope: 'create',
        data: { form: { lead_name: 'Other user' } },
    })
    const own = readLeadDraft(storage, key, { userId: '4', scope: 'create' })
    assert.equal(own, null)
    assert.equal(JSON.parse(storage.getItem(otherKey)).data.form.lead_name, 'Other user')
})

test('edit drafts for different leads never mix', () => {
    const storage = memoryStorage()
    const first = buildLeadDraftKey({ origin: 'https://crm.example', userId: '4', scope: 'edit', leadId: 3 })
    const second = buildLeadDraftKey({ origin: 'https://crm.example', userId: '4', scope: 'edit', leadId: 4 })
    writeLeadDraft(storage, first, {
        savedAt: Date.now(),
        userId: '4',
        scope: 'edit',
        leadId: 3,
        baseVersion: 'a',
        data: { form: { first_name: 'Amina' } },
    })
    writeLeadDraft(storage, second, {
        savedAt: Date.now(),
        userId: '4',
        scope: 'edit',
        leadId: 4,
        baseVersion: 'b',
        data: { form: { first_name: 'Bilal' } },
    })
    assert.equal(readLeadDraft(storage, first, { userId: '4', scope: 'edit', leadId: 3 }).data.form.first_name, 'Amina')
    assert.equal(readLeadDraft(storage, second, { userId: '4', scope: 'edit', leadId: 4 }).data.form.first_name, 'Bilal')
    assert.equal(readLeadDraft(storage, first, { userId: '4', scope: 'edit', leadId: 4 }), null)
})

test('recovery prompts only when server data is newer than the draft base', () => {
    const same = { v: 1, data: { form: { lead_name: 'A' } }, baseVersion: '2026-10-21T10:00:00Z' }
    assert.equal(decideLeadDraftRecovery(same, '2026-10-21T10:00:00Z'), 'restore')
    assert.equal(decideLeadDraftRecovery(same, '2026-10-21T12:00:00Z'), 'prompt')
    assert.equal(decideLeadDraftRecovery({ v: 1, data: { form: {} }, baseVersion: null }, null), 'restore')
    assert.equal(decideLeadDraftRecovery({ v: 1, data: null }, null), 'drop')
})

test('draft storage strips secrets and ignores key order', () => {
    const clean = sanitizeDraftValue({
        lead_name: 'Portal lead',
        token: 'secret',
        nested: { password: 'nope', note: 'keep' },
    })
    assert.deepEqual(clean, { lead_name: 'Portal lead', nested: { note: 'keep' } })
    assert.equal(stableSerialize({ b: 1, a: { d: 2, c: 3 } }), stableSerialize({ a: { c: 3, d: 2 }, b: 1 }))
    assert.deepEqual(pickFields({ lead_name: 'A', token: 'x' }, ['lead_name']), { lead_name: 'A' })
})

test('removeLeadDraft deletes only the requested key', () => {
    const storage = memoryStorage()
    storage.setItem('keep', '1')
    storage.setItem('drop', '1')
    removeLeadDraft(storage, 'drop')
    assert.equal(storage.getItem('drop'), null)
    assert.equal(storage.getItem('keep'), '1')
})
