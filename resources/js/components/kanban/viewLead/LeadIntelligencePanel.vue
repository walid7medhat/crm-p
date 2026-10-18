<template>
    <aside class="lead-intel-panel" role="dialog" aria-label="Lead Intelligence" @keydown.esc.stop.prevent="$emit('close')">
        <header class="lead-intel-head">
            <div>
                <p class="lead-intel-kicker">Lead Intelligence</p>
                <p class="lead-intel-name">{{ leadName || 'This lead' }}</p>
            </div>
            <button type="button" class="lead-intel-close" aria-label="Close Lead Intelligence" @click="$emit('close')">
                <iconify-icon icon="lucide:x"></iconify-icon>
            </button>
        </header>

        <div class="lead-intel-body">
            <p class="lead-intel-note">Search starts only when you ask. Nothing found here is written back to the lead.</p>

            <button
                v-if="viewState === 'idle' || viewState === 'empty'"
                type="button"
                class="lead-intel-primary"
                :disabled="saving"
                @click="startScan(viewState === 'empty')"
            >
                {{ viewState === 'empty' ? 'Scan again' : 'Scan This Lead' }}
            </button>

            <div v-if="viewState === 'loading'" class="lead-intel-status" role="status">
                <span class="lead-intel-bar" :style="{ width: `${progress}%` }"></span>
                <p>{{ scan?.message || 'Searching public sources…' }}</p>
                <p class="lead-intel-hint">Progress is checked every few seconds. You can keep working on this lead.</p>
                <button type="button" class="lead-intel-text" @click="cancelScan">Cancel</button>
            </div>

            <div v-else-if="viewState === 'failed'" class="lead-intel-status is-error" role="alert">
                <p>{{ scan?.message || error || 'The scan could not be completed.' }}</p>
                <a v-if="scan?.manual_search_url" class="lead-intel-link" :href="scan.manual_search_url" target="_blank" rel="noopener noreferrer">Open a public search</a>
                <button type="button" class="lead-intel-primary" @click="startScan(true)">Retry</button>
            </div>

            <div v-else-if="viewState === 'empty'" class="lead-intel-status">
                <p>Not found in available sources.</p>
                <p class="lead-intel-hint">No public result was strong enough to connect to this lead. A matching name alone is not shown as the same person.</p>
                <a v-if="scan?.manual_search_url" class="lead-intel-link" :href="scan.manual_search_url" target="_blank" rel="noopener noreferrer">Open a public search</a>
            </div>

            <template v-else-if="viewState === 'partial' || viewState === 'completed'">
                <p v-if="viewState === 'partial'" class="lead-intel-banner">Some searches failed. What follows is a partial result.</p>
                <p v-if="scan?.identity_message" class="lead-intel-banner" :class="{ 'is-warn': scan.identity_status === 'not_confirmed' || scan.identity_status === 'conflicting' }">
                    {{ scan.identity_message }}
                </p>
                <p v-if="scan?.limitation" class="lead-intel-hint">{{ scan.limitation }}</p>

                <div v-if="scan?.comparison?.length" class="lead-intel-compare">
                    <p class="lead-intel-label">Compare possible matches</p>
                    <div class="lead-intel-table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Result</th>
                                    <th>Assessment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in scan.comparison" :key="row.url">
                                    <td>
                                        <a :href="row.url" target="_blank" rel="noopener noreferrer">{{ row.title || row.url }}</a>
                                    </td>
                                    <td>{{ tierLabel(row.tier) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <details v-for="block in sectionBlocks" :key="block.key" class="lead-intel-section" open>
                    <summary>{{ block.title }}</summary>
                    <article v-for="(item, index) in block.items" :key="`${block.key}-${index}`" class="lead-intel-item">
                        <div class="lead-intel-item-top">
                            <span>{{ item.label }}</span>
                            <span class="lead-intel-quality">{{ qualityLabel(item.quality) }}</span>
                        </div>
                        <p>{{ item.value }}</p>
                        <p v-if="item.note" class="lead-intel-hint">{{ item.note }}</p>
                        <ul v-if="item.reasons?.length">
                            <li v-for="reason in item.reasons" :key="reason">{{ reason }}</li>
                        </ul>
                        <ul v-if="item.uncertainties?.length">
                            <li v-for="note in item.uncertainties" :key="note">{{ note }}</li>
                        </ul>
                        <p v-for="source in item.sources || []" :key="source.url" class="lead-intel-source">
                            <a :href="source.url" target="_blank" rel="noopener noreferrer">{{ source.title || source.url }}</a>
                            <span v-if="source.published_at">Published {{ source.published_at }}.</span>
                            <span v-if="source.retrieved_at">Retrieved {{ source.retrieved_at }}.</span>
                        </p>
                    </article>
                </details>

                <section v-if="scan?.ai?.summary" class="lead-intel-item">
                    <div class="lead-intel-item-top">
                        <span>{{ scan.ai.label || 'AI-generated summary of source material' }}</span>
                        <span class="lead-intel-quality">Unverified</span>
                    </div>
                    <p>{{ scan.ai.summary }}</p>
                </section>

                <button type="button" class="lead-intel-text" @click="startScan(true)">Scan again</button>
            </template>
        </div>
    </aside>
</template>

<script setup>
import { computed, onUnmounted, ref, watch } from 'vue'
import api from '@/plugins/axios'
import { leadIntelligenceViewState } from './leadIntelligenceState.mjs'

const props = defineProps({
    leadId: { type: [Number, String], required: true },
    leadName: { type: String, default: '' },
})

defineEmits(['close'])

const scan = ref(null)
const loading = ref(false)
const saving = ref(false)
const error = ref('')
let pollTimer = null

const viewState = computed(() => leadIntelligenceViewState({
    scan: scan.value,
    loading: loading.value,
    error: error.value,
}))

const progress = computed(() => {
    const value = Number(scan.value?.progress || 8)
    return Math.max(8, Math.min(100, value))
})

const sectionBlocks = computed(() => {
    const sections = scan.value?.sections || {}
    return [
        ['identity', 'Potential professional identity'],
        ['career', 'Career and employment'],
        ['company', 'Company and business context'],
        ['presence', 'Public professional presence'],
        ['findings', 'Important findings'],
    ].map(([key, title]) => ({ key, title, items: sections[key] || [] }))
        .filter((block) => block.items.length)
})

const tierLabel = (tier) => ({
    strong: 'Strongly supported potential match',
    possible: 'Possible match requiring manual verification',
    weak: 'Weak match with insufficient evidence',
    conflicting: 'Conflicting or likely unrelated result',
}[tier] || 'Unverified')

const qualityLabel = (quality) => ({
    'source-supported': 'Source-supported',
    corroborated: 'Corroborated',
    'possible-match': 'Possible match',
    unverified: 'Unverified',
    conflicting: 'Conflicting evidence',
    'not-found': 'Not found',
}[quality] || 'Unverified')

const scanError = (err, fallback) => {
    const status = err?.response?.status
    const message = err?.response?.data?.message || ''
    if (status === 429 || /too many attempts/i.test(message)) {
        return 'Please wait a few minutes before scanning this lead again.'
    }
    return message || fallback
}

const applyPayload = (payload) => {
    scan.value = payload || null
    if (payload?.polling) schedulePoll()
    else stopPoll()
}

const loadLatest = async () => {
    loading.value = true
    error.value = ''
    try {
        const response = await api.get(`/leads/${props.leadId}/intelligence-scans/latest`)
        applyPayload(response.data?.data || null)
    } catch (err) {
        error.value = scanError(err, 'The scan could not be loaded.')
        scan.value = null
    } finally {
        loading.value = false
    }
}

const refresh = async () => {
    if (!scan.value?.id) return
    try {
        const response = await api.get(`/leads/${props.leadId}/intelligence-scans/${scan.value.id}`)
        applyPayload(response.data?.data || scan.value)
    } catch (err) {
        error.value = scanError(err, 'The scan could not be refreshed.')
        stopPoll()
    }
}

const startScan = async (force = false) => {
    saving.value = true
    error.value = ''
    try {
        const response = await api.post(`/leads/${props.leadId}/intelligence-scans`, { force: force ? 1 : 0 })
        applyPayload(response.data?.data || null)
    } catch (err) {
        error.value = scanError(err, 'The scan could not be started.')
    } finally {
        saving.value = false
    }
}

const cancelScan = async () => {
    if (!scan.value?.id) return
    try {
        const response = await api.post(`/leads/${props.leadId}/intelligence-scans/${scan.value.id}/cancel`)
        applyPayload(response.data?.data || null)
    } catch (err) {
        error.value = scanError(err, 'The scan could not be cancelled.')
    }
}

const schedulePoll = () => {
    if (pollTimer) return
    pollTimer = window.setInterval(refresh, 2500)
}

const stopPoll = () => {
    if (pollTimer) {
        window.clearInterval(pollTimer)
        pollTimer = null
    }
}

watch(() => props.leadId, () => {
    scan.value = null
    loadLatest()
}, { immediate: true })

onUnmounted(stopPoll)
</script>

<style scoped>
.lead-intel-panel {
    position: absolute;
    top: 0;
    right: 0;
    z-index: 30;
    display: flex;
    flex-direction: column;
    width: min(420px, 100%);
    height: 100%;
    background: #fff;
    border-left: 1px solid #e5e7eb;
    box-shadow: -12px 0 30px rgba(15, 23, 42, 0.08);
}
.lead-intel-head, .lead-intel-body { padding: 14px 16px; }
.lead-intel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-bottom: 1px solid #eef2f7;
}
.lead-intel-kicker {
    margin: 0;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--color-primary, #0f766e);
}
.lead-intel-name { margin: 2px 0 0; font-size: 15px; font-weight: 650; color: #111827; }
.lead-intel-close, .lead-intel-primary, .lead-intel-text {
    border: 0;
    cursor: pointer;
}
.lead-intel-close {
    width: 32px;
    height: 32px;
    border-radius: 999px;
    background: #f3f4f6;
    color: #111827;
}
.lead-intel-body { overflow: auto; }
.lead-intel-note, .lead-intel-hint, .lead-intel-banner { color: #4b5563; font-size: 13px; }
.lead-intel-primary {
    margin: 12px 0;
    padding: 8px 12px;
    border-radius: 999px;
    background: var(--gradient-primary, #0f766e);
    color: #fff;
    font-weight: 650;
}
.lead-intel-primary:disabled { opacity: 0.6; }
.lead-intel-text { background: transparent; color: var(--color-primary, #0f766e); font-weight: 650; padding: 0; }
.lead-intel-status { position: relative; padding: 12px; border-radius: 12px; background: #f8fafc; overflow: hidden; }
.lead-intel-status.is-error { background: #fef2f2; }
.lead-intel-bar {
    position: absolute;
    left: 0;
    top: 0;
    height: 3px;
    background: var(--color-primary, #0f766e);
}
.lead-intel-banner {
    margin: 0 0 8px;
    padding: 8px 10px;
    border-radius: 10px;
    background: #f8fafc;
}
.lead-intel-banner.is-warn { background: #fff7ed; }
.lead-intel-section { margin: 10px 0; border-top: 1px solid #eef2f7; padding-top: 8px; }
.lead-intel-section summary { cursor: pointer; font-weight: 700; }
.lead-intel-item { margin-top: 8px; }
.lead-intel-item p, .lead-intel-item li { font-size: 13px; color: #1f2937; }
.lead-intel-item-top { display: flex; justify-content: space-between; gap: 8px; font-size: 12px; font-weight: 700; }
.lead-intel-quality { color: #0f766e; font-weight: 650; }
.lead-intel-source, .lead-intel-link { font-size: 12px; }
.lead-intel-compare { margin: 8px 0 12px; }
.lead-intel-label { font-size: 12px; font-weight: 700; }
.lead-intel-table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 12px; }
th, td { text-align: left; padding: 6px 4px; border-bottom: 1px solid #eef2f7; vertical-align: top; }
@media (max-width: 720px) {
    .lead-intel-panel { width: 100%; }
}
</style>
