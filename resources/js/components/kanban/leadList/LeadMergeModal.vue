<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="lm-overlay"
            @click.self="close"
        >
            <div
                ref="panelRef"
                class="lm-panel"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="titleId"
                tabindex="-1"
                @keydown.esc.prevent="close"
            >
                <div class="lm-panel__glow" aria-hidden="true"></div>

                <header class="lm-head">
                    <div class="lm-head__text">
                        <span class="lm-kicker">Duplicate lead merge</span>
                        <h6 :id="titleId">{{ title }}</h6>
                        <p v-if="step < 3 && leads.length" class="lm-count">
                            {{ leads.length }} candidate{{ leads.length === 1 ? '' : 's' }} in this group
                        </p>
                    </div>
                    <button
                        type="button"
                        class="lm-x"
                        aria-label="Close"
                        :disabled="submitting"
                        @click="close"
                    >
                        <iconify-icon icon="lucide:x" />
                    </button>
                </header>

                <div class="lm-body">
                    <p v-if="loading && step !== 2" class="lm-muted lm-center">
                        <span class="lm-spinner" aria-hidden="true"></span>
                        Loading…
                    </p>
                    <p v-if="error" class="lm-error" role="alert">{{ error }}</p>

                    <!-- STEP 1: select primary -->
                    <template v-if="!loading && step === 1">
                        <p class="lm-help">
                            Choose the <strong>primary lead</strong> that will remain on the board.
                            Its stage and assignment stay unchanged. Eligible duplicates will be linked into it
                            (soft merge) — not deleted.
                        </p>
                        <p v-if="hasPool" class="lm-note">
                            Lead Pool leads are merged automatically every day, and only with other Lead Pool leads.
                            They cannot be selected for a manual merge.
                        </p>

                        <label
                            v-for="l in leads"
                            :key="l.id"
                            class="lm-card"
                            :class="{ 'is-selected': defaultId === l.id }"
                        >
                            <span class="lm-radio" aria-hidden="true">
                                <span class="lm-radio__dot"></span>
                            </span>
                            <input v-model="defaultId" type="radio" :value="l.id" class="lm-sr" />
                            <div class="lm-card__main">
                                <div class="lm-card__title-row">
                                    <strong>#{{ l.id }} · {{ l.lead_name || 'Untitled' }}</strong>
                                    <span v-if="defaultId === l.id" class="lm-pill lm-pill--primary">Primary</span>
                                    <MatchByChips :match-by="l.match_by" />
                                </div>
                                <div class="lm-facts">
                                    <span class="lm-fact">
                                        <iconify-icon icon="lucide:phone" class="lm-fact__icon" />
                                        <span class="lm-fact__val">{{ l.work_phone || '—' }}</span>
                                    </span>
                                    <span class="lm-fact">
                                        <iconify-icon icon="lucide:mail" class="lm-fact__icon" />
                                        <span class="lm-fact__val">{{ l.email || '—' }}</span>
                                    </span>
                                    <span class="lm-fact lm-fact--stage">
                                        <iconify-icon icon="lucide:layers" class="lm-fact__icon" />
                                        <span class="lm-fact__val">{{ l.stage_name || '—' }}</span>
                                    </span>
                                    <span v-if="l.responsible_name" class="lm-fact">
                                        <iconify-icon icon="lucide:user" class="lm-fact__icon" />
                                        <span class="lm-fact__val">{{ l.responsible_name }}</span>
                                    </span>
                                    <span v-if="l.created_at" class="lm-fact">
                                        <iconify-icon icon="lucide:calendar" class="lm-fact__icon" />
                                        <span class="lm-fact__val">{{ formatDate(l.created_at) }}</span>
                                    </span>
                                </div>
                                <span
                                    v-if="l.property_type || l.area || l.bedrooms != null"
                                    class="lm-submeta"
                                >
                                    {{ propertyLine(l) }}
                                </span>
                                <ul v-if="defaultId && conflictHints(l).length" class="lm-diffs">
                                    <li v-for="(hint, i) in conflictHints(l)" :key="i">{{ hint }}</li>
                                </ul>
                            </div>
                        </label>
                    </template>

                    <!-- STEP 2: preview -->
                    <template v-else-if="step === 2 && preview && !loading">
                        <div class="lm-default">
                            <span class="lm-badge">Primary lead</span>
                            <strong>#{{ preview.default.id }} · {{ preview.default.lead_name }}</strong>
                            <div class="lm-facts">
                                <span class="lm-fact">
                                    <iconify-icon icon="lucide:phone" class="lm-fact__icon" />
                                    <span class="lm-fact__val">{{ preview.default.work_phone || '—' }}</span>
                                </span>
                                <span class="lm-fact">
                                    <iconify-icon icon="lucide:mail" class="lm-fact__icon" />
                                    <span class="lm-fact__val">{{ preview.default.email || '—' }}</span>
                                </span>
                                <span class="lm-fact lm-fact--stage">
                                    <iconify-icon icon="lucide:layers" class="lm-fact__icon" />
                                    <span class="lm-fact__val">{{ preview.default.stage_name }} · unchanged</span>
                                </span>
                                <span v-if="preview.default.responsible_name" class="lm-fact">
                                    <iconify-icon icon="lucide:user" class="lm-fact__icon" />
                                    <span class="lm-fact__val">{{ preview.default.responsible_name }}</span>
                                </span>
                            </div>
                        </div>

                        <div class="lm-summary-box">
                            <h6 class="lm-sub">What will happen</h6>
                            <ul class="lm-bullets">
                                <li>
                                    <strong>{{ preview.eligible.length }}</strong> duplicate
                                    {{ preview.eligible.length === 1 ? 'record' : 'records' }}
                                    will be linked into the primary lead and hidden from the normal board.
                                </li>
                                <li>
                                    The primary lead’s identity, stage, and assignment are kept as they are —
                                    other leads’ fields are not copied over.
                                </li>
                                <li>
                                    Comments and activities stay on their original records. Merged leads remain
                                    viewable under <strong>Merged Leads</strong> on the primary lead.
                                </li>
                                <li>This action cannot be undone from this screen.</li>
                            </ul>
                        </div>

                        <h6 class="lm-sub">Will be merged ({{ preview.eligible.length }})</h6>
                        <p v-if="!preview.eligible.length" class="lm-muted">
                            No lead can be merged into this primary selection.
                        </p>
                        <div
                            v-for="l in preview.eligible"
                            :key="l.id"
                            class="lm-row lm-row--ok"
                        >
                            <strong>#{{ l.id }} · {{ l.lead_name }}</strong>
                            <span>{{ l.work_phone || '—' }} · {{ l.stage_name }}</span>
                            <MatchByChips :match-by="l.match_by" />
                        </div>

                        <template v-if="preview.ineligible.length">
                            <h6 class="lm-sub">Will stay separate ({{ preview.ineligible.length }})</h6>
                            <div
                                v-for="l in preview.ineligible"
                                :key="l.id"
                                class="lm-row lm-row--no"
                            >
                                <strong>#{{ l.id }} · {{ l.lead_name }}</strong>
                                <span>{{ l.work_phone || '—' }} · {{ l.stage_name }}</span>
                                <MatchByChips :match-by="l.match_by" />
                                <em>{{ l.reason }}</em>
                            </div>
                        </template>
                    </template>

                    <p v-else-if="loading && step === 2" class="lm-muted lm-center">
                        <span class="lm-spinner" aria-hidden="true"></span>
                        Building preview…
                    </p>

                    <!-- STEP 3: success -->
                    <template v-else-if="step === 3 && result">
                        <div class="lm-success">
                            <div class="lm-success__icon" aria-hidden="true">
                                <iconify-icon icon="lucide:check" />
                            </div>
                            <h6>Merge complete</h6>
                            <p>
                                Primary lead
                                <strong>#{{ result.default_lead_id }}</strong>
                                <template v-if="result.lead_name"> · {{ result.lead_name }}</template>
                            </p>
                            <p class="lm-muted">
                                {{ result.merged_ids?.length || 0 }} duplicate
                                {{ (result.merged_ids?.length || 0) === 1 ? 'record was' : 'records were' }}
                                linked. Open <strong>Merged Leads</strong> on the primary record to review them.
                            </p>
                        </div>
                    </template>
                </div>

                <footer class="lm-foot">
                    <template v-if="step === 1">
                        <button type="button" class="lm-btn lm-btn--ghost" @click="close">Cancel</button>
                        <button
                            type="button"
                            class="lm-btn lm-btn--3d"
                            :disabled="!defaultId || loading"
                            @click="goPreview"
                        >
                            <span class="lm-btn__shine" aria-hidden="true"></span>
                            Next
                        </button>
                    </template>
                    <template v-else-if="step === 2">
                        <button
                            type="button"
                            class="lm-btn lm-btn--ghost"
                            :disabled="submitting"
                            @click="error = ''; step = 1"
                        >
                            Back
                        </button>
                        <button
                            type="button"
                            class="lm-btn lm-btn--3d"
                            :disabled="submitting || !preview?.eligible.length"
                            @click="confirm"
                        >
                            <span class="lm-btn__shine" aria-hidden="true"></span>
                            <span v-if="submitting" class="lm-btn__loading">
                                <span class="lm-spinner lm-spinner--sm" aria-hidden="true"></span>
                                Merging…
                            </span>
                            <span v-else>Confirm merge</span>
                        </button>
                    </template>
                    <template v-else-if="step === 3">
                        <button type="button" class="lm-btn lm-btn--ghost" @click="finish(false)">
                            Done
                        </button>
                        <button type="button" class="lm-btn lm-btn--3d" @click="finish(true)">
                            <span class="lm-btn__shine" aria-hidden="true"></span>
                            <iconify-icon icon="lucide:external-link" />
                            Open primary lead
                        </button>
                    </template>
                </footer>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import api from '@/plugins/axios'
import MatchByChips from './MatchByChips.vue'

const props = defineProps({
    modelValue: Boolean,
    leadId: { type: Number, default: null },
})
const emit = defineEmits(['update:modelValue', 'merged'])

const show = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
})

const titleId = 'lead-merge-title'
const panelRef = ref(null)
const step = ref(1)
const leads = ref([])
const defaultId = ref(null)
const preview = ref(null)
const result = ref(null)
const loading = ref(false)
const submitting = ref(false)
const error = ref('')

const hasPool = computed(() => leads.value.some((l) => l.is_pool))
const selectedLead = computed(() => leads.value.find((l) => l.id === defaultId.value) || null)

const title = computed(() => {
    if (step.value === 1) return 'Select the primary lead'
    if (step.value === 2) return 'Review merge summary'
    return 'Merge successful'
})

const notify = (m, t = 'info') => window.$showNotification?.(m, t)

const close = () => {
    if (submitting.value) return
    if (step.value === 3 && result.value) {
        finish(false)
        return
    }
    show.value = false
}

function formatDate(v) {
    if (!v) return '—'
    try {
        return new Date(v).toLocaleDateString(undefined, {
            year: 'numeric', month: 'short', day: 'numeric',
        })
    } catch {
        return String(v)
    }
}

function propertyLine(l) {
    return [l.property_type, l.area, l.bedrooms != null ? `${l.bedrooms} BR` : null]
        .filter(Boolean)
        .join(' · ')
}

function conflictHints(l) {
    const primary = selectedLead.value
    if (!primary || primary.id === l.id) return []
    const hints = []
    const miss = (label, val) => {
        if (val == null || val === '') hints.push(`${label} missing on this lead`)
    }
    const diff = (label, a, b) => {
        if (a && b && String(a).trim() !== String(b).trim()) {
            hints.push(`${label} differs from primary`)
        }
    }
    miss('Email', l.email)
    miss('Phone', l.work_phone)
    diff('Email', l.email, primary.email)
    diff('Stage', l.stage_name, primary.stage_name)
    diff('Agent', l.responsible_name, primary.responsible_name)
    return hints.slice(0, 3)
}

async function load() {
    step.value = 1
    defaultId.value = null
    preview.value = null
    result.value = null
    error.value = ''
    if (!props.leadId) return
    loading.value = true
    try {
        const res = await api.get(`/leads/${props.leadId}/merge/candidates`)
        leads.value = res.data?.data?.leads || []
        await nextTick()
        panelRef.value?.focus?.()
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not load duplicate leads'
        leads.value = []
    } finally {
        loading.value = false
    }
}

async function goPreview() {
    if (!defaultId.value || loading.value) return
    loading.value = true
    error.value = ''
    try {
        const res = await api.post('/leads/merge/preview', { default_lead_id: defaultId.value })
        preview.value = res.data?.data
        step.value = 2
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not build the merge preview'
    } finally {
        loading.value = false
    }
}

async function confirm() {
    if (submitting.value || !preview.value?.eligible.length) return
    submitting.value = true
    error.value = ''
    try {
        const res = await api.post('/leads/merge/confirm', {
            default_lead_id: preview.value.default.id,
            lead_ids: preview.value.eligible.map((l) => l.id),
        })
        const data = res.data?.data || {}
        result.value = {
            default_lead_id: data.default_lead_id || preview.value.default.id,
            merged_ids: data.merged_ids || [],
            skipped: data.skipped || [],
            lead_name: preview.value.default.lead_name,
        }
        step.value = 3
        notify('Leads merged successfully', 'success')
    } catch (e) {
        const msg = e.response?.data?.message || 'Merge failed'
        error.value = msg
        notify(msg, 'error')
    } finally {
        submitting.value = false
    }
}

async function finish(openPrimary) {
    const id = result.value?.default_lead_id
    // Close the high z-index overlay before the parent opens the lead modal,
    // otherwise the lead appears dimmed underneath this purple wash.
    show.value = false
    await nextTick()
    emit('merged', { default_lead_id: id, open: !!openPrimary })
}

watch(show, (v) => { if (v) load() }, { immediate: true })
</script>

<style scoped>
.lm-overlay {
    position: fixed;
    inset: 0;
    /* Below ViewLeadModal (12200) so a stuck overlay never dims open leads. */
    z-index: 11000;
    background: rgba(11, 7, 54, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    animation: lmFade 0.18s ease-out;
}
@keyframes lmFade {
    from { opacity: 0; }
    to { opacity: 1; }
}

.lm-panel {
    position: relative;
    width: min(620px, 100%);
    max-height: 86dvh;
    display: flex;
    flex-direction: column;
    background:
        linear-gradient(180deg, #ffffff 0%, #f8f7fc 100%);
    border-radius: 18px;
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.8) inset,
        0 24px 60px rgba(15, 23, 42, 0.28),
        0 8px 20px rgba(49, 46, 129, 0.12);
    overflow: hidden;
    outline: none;
    animation: lmRise 0.22s ease-out;
}
@keyframes lmRise {
    from { opacity: 0; transform: translateY(10px) scale(0.985); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.lm-panel__glow {
    position: absolute;
    top: -40%;
    left: 20%;
    right: 20%;
    height: 55%;
    background: radial-gradient(ellipse, rgba(var(--color-primary-rgb), 0.08), transparent 70%);
    pointer-events: none;
}

.lm-head, .lm-foot {
    position: relative;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 20px;
}
.lm-head {
    border-bottom: 1px solid #eef2f7;
    background: linear-gradient(180deg, #fff 0%, #faf9fd 100%);
}
.lm-head__text { min-width: 0; }
.lm-kicker {
    display: block;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--color-primary);
    margin-bottom: 2px;
}
.lm-head h6 {
    margin: 0;
    font-family: Montserrat, sans-serif;
    font-size: 16px !important;
    font-weight: 700;
    color: #0B0736;
}
.lm-count {
    margin: 4px 0 0;
    font-size: 12px;
    color: #64748b;
}
.lm-foot {
    border-top: 1px solid #eef2f7;
    justify-content: flex-end;
    align-items: center;
    background: #fff;
}
.lm-x {
    border: 0;
    background: #f1f5f9;
    width: 32px;
    height: 32px;
    border-radius: 10px;
    font-size: 16px;
    color: #64748b;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: background 0.15s ease, color 0.15s ease;
}
.lm-x:hover:not(:disabled) { background: #e2e8f0; color: #0B0736; }
.lm-x:disabled { opacity: 0.5; cursor: not-allowed; }

.lm-body {
    position: relative;
    padding: 16px 20px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
    -webkit-overflow-scrolling: touch;
}
.lm-help {
    margin: 0;
    font-size: 12.5px;
    line-height: 1.45;
    color: #475569;
}
.lm-muted { color: #94a3b8; font-size: 13px; margin: 0; }
.lm-center { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 28px 0; }
.lm-error {
    color: #b91c1c;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 13px;
    margin: 0;
}
.lm-note {
    background: #fff7ed;
    border: 1px dashed #fdba74;
    border-radius: 10px;
    padding: 8px 10px;
    font-size: 12px;
    color: #9a3412;
    margin: 0;
}

.lm-sr {
    position: absolute;
    opacity: 0;
    pointer-events: none;
    width: 0;
    height: 0;
}

.lm-card {
    position: relative;
    display: flex;
    gap: 12px;
    align-items: flex-start;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 12px 14px;
    cursor: pointer;
    background: #fff;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease, background 0.15s ease;
}
.lm-card:hover {
    border-color: #c4b5fd;
    box-shadow: 0 4px 14px rgba(76, 29, 149, 0.08);
}
.lm-card.is-selected {
    border-color: var(--color-primary);
    background: linear-gradient(135deg, #faf5ff 0%, #f5f3ff 100%);
    box-shadow:
        0 0 0 1px rgba(var(--color-primary-rgb), 0.2),
        0 8px 20px rgba(76, 29, 149, 0.1);
}
.lm-radio {
    width: 18px;
    height: 18px;
    margin-top: 2px;
    border-radius: 50%;
    border: 2px solid #cbd5e1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: border-color 0.15s ease, background 0.15s ease;
}
.lm-radio__dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: transparent;
    transition: background 0.15s ease, transform 0.15s ease;
    transform: scale(0.6);
}
.lm-card.is-selected .lm-radio {
    border-color: var(--color-primary);
    background: var(--color-primary);
}
.lm-card.is-selected .lm-radio__dot {
    background: #fff;
    transform: scale(1);
}
.lm-card__main {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 0;
    flex: 1;
    font-size: 12px;
    color: #475569;
}
.lm-card__title-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}
.lm-card__main strong {
    color: #0B0736;
    font-size: 13px;
    font-family: Montserrat, sans-serif;
}
.lm-facts {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.lm-fact {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    max-width: 100%;
    padding: 5px 9px;
    border-radius: 999px;
    background: #f8fafc;
    border: 1px solid #eef2f7;
    box-shadow: 0 1px 0 rgba(255, 255, 255, 0.9) inset;
    line-height: 1.2;
}
.lm-card.is-selected .lm-fact {
    background: rgba(255, 255, 255, 0.78);
    border-color: #e9d5ff;
}
.lm-fact--stage {
    background: #f5f3ff;
    border-color: #ddd6fe;
    color: #5b21b6;
}
.lm-fact__icon {
    flex: 0 0 auto;
    width: 13px;
    height: 13px;
    font-size: 13px;
    color: var(--color-primary);
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.lm-fact__val {
    min-width: 0;
    font-size: 11.5px;
    font-weight: 600;
    color: #334155;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.lm-fact--stage .lm-fact__val { color: #5b21b6; }
.lm-submeta {
    font-size: 11px;
    color: #94a3b8;
    padding-inline-start: 2px;
}
.lm-diffs {
    margin: 4px 0 0;
    padding-left: 16px;
    font-size: 11px;
    color: #b45309;
}
.lm-pill {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.lm-pill--primary {
    background: var(--gradient-primary);
    color: #fff;
}

.lm-default {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 14px;
    border: 2px solid var(--color-primary);
    border-radius: 14px;
    background: linear-gradient(135deg, #faf5ff 0%, #f5f3ff 100%);
    font-size: 12px;
    color: #475569;
    box-shadow: 0 6px 16px rgba(76, 29, 149, 0.08);
}
.lm-default strong { color: #0B0736; font-size: 14px; }
.lm-badge {
    align-self: flex-start;
    background: var(--gradient-primary);
    color: #fff;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 3px 9px;
    border-radius: 999px;
    box-shadow: 0 2px 6px rgba(76, 29, 149, 0.25);
}

.lm-summary-box {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px 14px;
    background: #fff;
}
.lm-bullets {
    margin: 6px 0 0;
    padding-left: 18px;
    font-size: 12px;
    color: #475569;
    line-height: 1.5;
}
.lm-bullets li + li { margin-top: 4px; }

.lm-sub {
    margin: 8px 0 0;
    font-size: 11px !important;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
}
.lm-row {
    display: flex;
    flex-direction: column;
    gap: 2px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 9px 11px;
    font-size: 12px;
    color: #475569;
    background: #fff;
}
.lm-row--ok { border-left: 4px solid #16a34a; }
.lm-row--no { border-left: 4px solid #f59e0b; background: #fffbeb; }
.lm-row em { color: #92400e; font-style: normal; font-size: 11px; }

.lm-success {
    text-align: center;
    padding: 28px 12px 18px;
}
.lm-success__icon {
    width: 56px;
    height: 56px;
    margin: 0 auto 12px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: #fff;
    background: var(--gradient-primary);
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.25) inset,
        0 8px 18px rgba(76, 29, 149, 0.3);
}
.lm-success h6 {
    margin: 0 0 8px;
    font-size: 18px !important;
    font-weight: 700;
    color: #0B0736;
}
.lm-success p { margin: 0 0 6px; font-size: 13px; color: #475569; }

.lm-btn {
    position: relative;
    border: 0;
    border-radius: 11px;
    padding: 9px 18px;
    font-family: Montserrat, sans-serif;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease, opacity 0.15s ease;
}
.lm-btn:disabled { opacity: 0.45; cursor: not-allowed; transform: none !important; }
.lm-btn--ghost {
    background: #f1f5f9;
    color: #0B0736;
}
.lm-btn--ghost:hover:not(:disabled) { background: #e2e8f0; }
.lm-btn--3d {
    overflow: hidden;
    background: var(--gradient-primary);
    color: #fff;
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.22) inset,
        0 -2px 0 rgba(15, 23, 42, 0.2) inset,
        0 4px 12px rgba(49, 46, 129, 0.28);
}
.lm-btn__shine {
    position: absolute;
    inset: 0;
    background: linear-gradient(115deg, transparent 0%, rgba(255,255,255,0.16) 42%, transparent 58%);
    pointer-events: none;
}
.lm-btn--3d:hover:not(:disabled) {
    filter: brightness(1.05);
    transform: translateY(-1px);
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.28) inset,
        0 -2px 0 rgba(15, 23, 42, 0.18) inset,
        0 6px 16px rgba(49, 46, 129, 0.34);
}
.lm-btn--3d:active:not(:disabled) {
    transform: translateY(1px);
    filter: brightness(0.97);
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.1) inset,
        0 2px 0 rgba(15, 23, 42, 0.25) inset,
        0 2px 4px rgba(49, 46, 129, 0.2);
}
.lm-btn--3d:focus-visible {
    outline: 2px solid #c4b5fd;
    outline-offset: 2px;
}
.lm-btn__loading { display: inline-flex; align-items: center; gap: 8px; }

.lm-spinner {
    width: 18px;
    height: 18px;
    border: 2px solid #e2e8f0;
    border-top-color: var(--color-primary);
    border-radius: 50%;
    animation: lmSpin 0.7s linear infinite;
}
.lm-spinner--sm { width: 14px; height: 14px; border-width: 2px; border-color: rgba(255,255,255,0.35); border-top-color: #fff; }
@keyframes lmSpin { to { transform: rotate(360deg); } }

@media (max-width: 560px) {
    .lm-overlay { padding: 0; align-items: flex-end; }
    .lm-panel {
        width: 100%;
        max-height: 90dvh;
        border-radius: 20px 20px 0 0;
        animation: lmSheet 0.24s ease-out;
    }
    @keyframes lmSheet {
        from { transform: translateY(100%); }
        to { transform: translateY(0); }
    }
    .lm-foot { padding-bottom: calc(12px + env(safe-area-inset-bottom, 0px)); }
}
</style>
