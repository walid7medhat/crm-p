import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { getAppOrigin } from '@/plugins/axios'
import { showUnsavedChangesDialog, showConfirmation } from '@/composables/useConfirmation'
import {
    buildLeadDraftKey,
    decideLeadDraftRecovery,
    normalizeDraftVersion,
    readLeadDraft,
    removeLeadDraft,
    stableSerialize,
    writeLeadDraft,
} from '@/utils/leadFormDraftStorage'

const leaveGuards = new Set()
const AUTOSAVE_MS = 400

export async function confirmRegisteredLeadDrafts() {
    for (const guard of [...leaveGuards]) {
        const allowed = await guard()
        if (!allowed) return false
    }
    return true
}

function readUserId() {
    try {
        const user = JSON.parse(localStorage.getItem('user') || 'null')
        const id = Number(user?.id)
        if (!Number.isInteger(id) || id <= 0) return null
        return String(id)
    } catch {
        return null
    }
}

function browserStorage() {
    try {
        return window.localStorage
    } catch {
        return null
    }
}

export function useLeadFormDraft(options) {
    const statusLabel = ref('')
    const isApplying = ref(false)
    const armed = ref(false)
    const pending = ref(false)
    const draftSaved = ref(false)
    const baseline = ref('')
    const live = computed(() => stableSerialize(options.getSnapshot()))
    const isDirty = computed(() => armed.value && live.value !== baseline.value)

    let paused = false
    let timer = null
    let changeStamp = 0
    let boundKey = null
    let boundUserId = null
    let boundLeadId = null
    let baseVersion = null
    let choiceInflight = null
    let leaveInflight = null
    let listening = false
    let restoredSerialized = null

    function storageKey() {
        if (!boundUserId || !boundKey) return null
        return boundKey
    }

    function currentStorage() {
        return browserStorage()
    }

    function clearTimer() {
        if (timer) {
            clearTimeout(timer)
            timer = null
        }
    }

    function refreshStatus() {
        if (!isDirty.value) {
            statusLabel.value = ''
            return
        }
        statusLabel.value = pending.value || !draftSaved.value ? 'Unsaved changes' : 'Draft saved'
    }

    function flushNow() {
        clearTimer()
        if (!armed.value || paused || isApplying.value || !isDirty.value) return
        const storage = currentStorage()
        const key = storageKey()
        if (!storage || !key) {
            pending.value = false
            refreshStatus()
            return
        }
        const savedAt = changeStamp || Date.now()
        const result = writeLeadDraft(storage, key, {
            savedAt,
            userId: boundUserId,
            scope: options.scope,
            leadId: boundLeadId,
            baseVersion,
            data: options.getSnapshot(),
        })
        pending.value = false
        if (result.ok) draftSaved.value = true
        refreshStatus()
    }

    function noteChange() {
        const now = Date.now()
        changeStamp = now > changeStamp ? now : changeStamp + 1
        pending.value = true
        draftSaved.value = false
        refreshStatus()
        clearTimer()
        timer = setTimeout(flushNow, AUTOSAVE_MS)
    }

    function rememberBaseline() {
        baseline.value = live.value
    }

    function acceptCurrent() {
        clearTimer()
        paused = true
        const storage = currentStorage()
        const key = storageKey()
        if (storage && key) removeLeadDraft(storage, key)
        rememberBaseline()
        pending.value = false
        draftSaved.value = false
        paused = false
        refreshStatus()
    }

    function runPaused(fn) {
        paused = true
        try {
            fn()
        } finally {
            paused = false
        }
    }

    function begin() {
        boundUserId = readUserId()
        boundLeadId = options.scope === 'edit' ? (options.getLeadId?.() ?? null) : null
        baseVersion = options.scope === 'edit' ? normalizeDraftVersion(options.getBaseVersion?.()) : null
        boundKey = boundUserId
            ? buildLeadDraftKey({
                origin: getAppOrigin(),
                userId: boundUserId,
                scope: options.scope,
                leadId: boundLeadId,
            })
            : null
        clearTimer()
        rememberBaseline()
        pending.value = false
        draftSaved.value = false
        paused = true
        armed.value = false
        refreshStatus()
    }

    async function recover() {
        paused = true
        armed.value = false
        try {
            const storage = currentStorage()
            const key = storageKey()
            if (!storage || !key) return false
            const record = readLeadDraft(storage, key, {
                userId: boundUserId,
                scope: options.scope,
                leadId: boundLeadId,
            })
            if (!record) return false
            if (stableSerialize(record.data) === baseline.value) {
                removeLeadDraft(storage, key)
                return false
            }
            const decision = decideLeadDraftRecovery(record, options.scope === 'edit' ? options.getBaseVersion?.() : null)
            if (decision === 'drop') {
                removeLeadDraft(storage, key)
                return false
            }
            if (decision === 'prompt') {
                const restore = await showConfirmation({
                    title: 'Recover unsaved edits?',
                    message: 'This lead was updated after your draft was saved. Restore your unsaved edits, or keep the latest saved lead?',
                    confirmText: 'Restore draft',
                    cancelText: 'Keep latest lead',
                    type: 'warning',
                    closeOnOverlay: false,
                    zIndex: 160000,
                })
                if (!restore) {
                    removeLeadDraft(storage, key)
                    return false
                }
            }
            isApplying.value = true
            options.applySnapshot(record.data)
            restoredSerialized = stableSerialize(options.getSnapshot())
            draftSaved.value = true
            await nextTick()
            return true
        } finally {
            isApplying.value = false
            paused = false
            armed.value = true
            refreshStatus()
        }
    }

    async function ask() {
        if (!isDirty.value) return 'clean'
        if (choiceInflight) return choiceInflight
        choiceInflight = showUnsavedChangesDialog().finally(() => {
            choiceInflight = null
        })
        return choiceInflight
    }

    async function confirmLeave() {
        if (leaveInflight) return leaveInflight
        leaveInflight = resolveLeave().finally(() => {
            leaveInflight = null
        })
        return leaveInflight
    }

    async function resolveLeave() {
        if (options.isBusy?.()) return false
        flushNow()
        const choice = await ask()
        if (choice === 'clean') return true
        if (choice === 'continue') return false
        if (choice === 'discard') {
            acceptCurrent()
            return true
        }
        if (choice === 'save') {
            const saved = await options.onSave?.()
            return saved === true
        }
        return false
    }

    function onVisibility() {
        if (document.visibilityState === 'hidden') flushNow()
    }

    function startListeners() {
        if (listening || typeof window === 'undefined' || typeof document === 'undefined') return
        listening = true
        document.addEventListener('visibilitychange', onVisibility)
    }

    function stopListeners() {
        if (!listening || typeof window === 'undefined' || typeof document === 'undefined') return
        listening = false
        document.removeEventListener('visibilitychange', onVisibility)
    }

    watch(live, () => {
        if (!armed.value || paused || isApplying.value) return
        if (restoredSerialized && live.value === restoredSerialized) {
            restoredSerialized = null
            refreshStatus()
            return
        }
        restoredSerialized = null
        if (live.value === baseline.value) {
            clearTimer()
            const storage = currentStorage()
            const key = storageKey()
            if (storage && key) removeLeadDraft(storage, key)
            pending.value = false
            draftSaved.value = false
            refreshStatus()
            return
        }
        noteChange()
    })

    leaveGuards.add(confirmLeave)
    startListeners()

    onBeforeUnmount(() => {
        leaveGuards.delete(confirmLeave)
        if (armed.value && !paused && isDirty.value) flushNow()
        clearTimer()
        stopListeners()
    })

    return {
        statusLabel,
        isDirty,
        isApplying,
        begin,
        recover,
        ask,
        confirmLeave,
        acceptCurrent,
        runPaused,
        flushNow,
    }
}
