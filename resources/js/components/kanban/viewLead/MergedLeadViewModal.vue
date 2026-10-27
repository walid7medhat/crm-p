<template>
    <Teleport to="body">
        <Transition name="mv">
            <div
                v-if="modelValue"
                class="mv-overlay"
                @click.self="close"
            >
                <div class="mv-dialog" role="dialog" aria-modal="true" :aria-label="dialogTitle">
                    <header class="mv-header">
                        <div class="mv-header__main">
                            <span class="mv-header__badge">Merged lead</span>
                            <p class="mv-header__title">{{ dialogTitle }}</p>
                            <p v-if="isElevated && stageLabel" class="mv-header__stage">{{ stageLabel }}</p>
                        </div>
                        <button type="button" class="mv-close" aria-label="Close" @click="close">
                            <iconify-icon icon="lucide:x" width="18" height="18" />
                        </button>
                    </header>

                    <div class="mv-body">
                        <p v-if="loading" class="mv-state">Loading…</p>
                        <p v-else-if="error" class="mv-state mv-state--error">{{ error }}</p>

                        <div v-else-if="lead" class="mv-layout" :class="{ 'mv-layout--elevated': isElevated }">
                            <div class="mv-main">
                                <LeadInfoView
                                    :lead="leadForView"
                                    :readonly="true"
                                    :can-edit="false"
                                    :show-edit-icon="false"
                                    :show-responsible-section="isElevated"
                                />
                            </div>

                            <aside v-if="isElevated" class="mv-side">
                                <div class="mv-tabs" role="tablist">
                                    <button
                                        v-for="tab in sideTabs"
                                        :key="tab.id"
                                        type="button"
                                        class="mv-tab"
                                        role="tab"
                                        :class="{ 'is-active': activeTab === tab.id }"
                                        :aria-selected="activeTab === tab.id"
                                        @click="activeTab = tab.id"
                                    >
                                        {{ tab.label }}
                                        <span v-if="tab.count" class="mv-tab__n">{{ tab.count }}</span>
                                    </button>
                                </div>

                                <div class="mv-side__panel">
                                    <template v-if="activeTab === 'details'">
                                        <div class="mv-kv" v-for="row in mergeDetailRows" :key="row.label">
                                            <span>{{ row.label }}</span>
                                            <strong>{{ row.value }}</strong>
                                        </div>
                                        <p v-if="!mergeDetailRows.length" class="mv-empty">No merge details</p>
                                    </template>

                                    <template v-else-if="activeTab === 'comments'">
                                        <div v-if="!(lead.comments || []).length" class="mv-empty">No comments</div>
                                        <article
                                            v-for="c in lead.comments || []"
                                            :key="c.id"
                                            class="mv-note"
                                        >
                                            <time>{{ formatWhen(c.created_at) }}</time>
                                            <p>{{ c.comment || '—' }}</p>
                                        </article>
                                    </template>

                                    <template v-else-if="activeTab === 'activity'">
                                        <div v-if="!(lead.activities || []).length" class="mv-empty">No activity</div>
                                        <article
                                            v-for="a in lead.activities || []"
                                            :key="a.id"
                                            class="mv-note"
                                        >
                                            <time>{{ formatWhen(a.created_at) }}</time>
                                            <p>{{ a.title || a.type || 'Activity' }}</p>
                                        </article>
                                    </template>

                                    <template v-else>
                                        <div v-if="!(lead.histories || []).length" class="mv-empty">No history</div>
                                        <article
                                            v-for="h in lead.histories || []"
                                            :key="h.id"
                                            class="mv-note"
                                        >
                                            <time>{{ formatWhen(h.created_at) }}</time>
                                            <p>{{ historyLabel(h) }}</p>
                                        </article>
                                    </template>
                                </div>
                            </aside>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import api from '@/plugins/axios'
import LeadInfoView from './LeadInfoView.vue'

const props = defineProps({
    modelValue: Boolean,
    parentId: { type: Number, default: null },
    mergedId: { type: Number, default: null },
    elevated: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])

const lead = ref(null)
const loading = ref(false)
const error = ref('')
const activeTab = ref('details')

const isElevated = computed(() => !!(props.elevated || lead.value?.elevated_merged_view))

const dialogTitle = computed(() => {
    if (loading.value) return 'Loading…'
    return lead.value?.lead_name || 'Merged lead'
})

const stageLabel = computed(() => (
    lead.value?.original_stage_name
    || lead.value?.stage_name
    || lead.value?.stage?.name
    || ''
))

/** Shape API payload like a normal lead view object for LeadInfoView. */
const leadForView = computed(() => {
    const l = lead.value
    if (!l) return null
    return {
        ...l,
        can_edit: false,
        hide_responsible_person: !isElevated.value,
        stage: isElevated.value
            ? (l.stage || (l.stage_name ? { id: l.stage_id, name: l.stage_name } : null))
            : null,
    }
})

const sideTabs = computed(() => [
    { id: 'details', label: 'Details', count: 0 },
    { id: 'comments', label: 'Comments', count: (lead.value?.comments || []).length },
    { id: 'activity', label: 'Activity', count: (lead.value?.activities || []).length },
    { id: 'history', label: 'History', count: (lead.value?.histories || []).length },
])

const MERGE_TYPE_LABELS = {
    new_lead_auto: 'Automatic (New Lead)',
    eligible_auto: 'Automatic (eligible stages)',
    pool_auto: 'Automatic (Lead Pool)',
    manual: 'Manual merge',
}

const mergeDetailRows = computed(() => {
    if (!isElevated.value || !lead.value) return []
    const l = lead.value
    const rows = [
        { label: 'Original stage', value: stageLabel.value || null },
        { label: 'Assigned to', value: l.responsible_person?.name || null },
        {
            label: 'Merge type',
            value: l.merge_type
                ? (MERGE_TYPE_LABELS[l.merge_type] || String(l.merge_type).replace(/_/g, ' '))
                : null,
        },
        { label: 'Merged at', value: l.merged_at ? formatWhen(l.merged_at) : null },
    ]
    return rows.filter((r) => r.value)
})

const close = () => emit('update:modelValue', false)

function formatWhen(v) {
    if (!v) return '—'
    try {
        return new Date(v).toLocaleString(undefined, {
            year: 'numeric', month: 'short', day: 'numeric',
            hour: '2-digit', minute: '2-digit',
        })
    } catch {
        return String(v)
    }
}

function historyLabel(h) {
    const c = h?.changes
    if (!c) return 'Change'
    if (typeof c === 'string') {
        try {
            return JSON.parse(c)?.action || 'Change'
        } catch {
            return 'Change'
        }
    }
    return c?.action || 'Change'
}

async function load() {
    lead.value = null
    error.value = ''
    activeTab.value = 'details'
    if (!props.parentId || !props.mergedId) return
    loading.value = true
    try {
        const res = await api.get(`/leads/${props.parentId}/merged-leads/${props.mergedId}`)
        lead.value = res.data?.data
    } catch (e) {
        error.value = e.response?.data?.message || 'You cannot view this lead'
    } finally {
        loading.value = false
    }
}

watch(() => [props.modelValue, props.mergedId], ([open]) => {
    if (open) load()
}, { immediate: true })
</script>

<style scoped>
.mv-overlay {
    position: fixed;
    inset: 0;
    z-index: 13000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(11, 7, 54, 0.45);
    backdrop-filter: blur(2px);
}

.mv-dialog {
    width: min(960px, 100%);
    max-height: min(90dvh, 860px);
    display: flex;
    flex-direction: column;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 18px 50px rgba(15, 23, 42, 0.24);
    overflow: hidden;
}

.mv-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: #fff;
    border-bottom: 1px solid #e5e7eb;
    flex-shrink: 0;
}

.mv-header__main {
    flex: 1;
    min-width: 0;
}

.mv-header__badge {
    display: inline-block;
    margin-bottom: 2px;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--color-primary);
}

.mv-dialog .mv-header__title {
    margin: 0 !important;
    padding: 0 !important;
    font-family: Montserrat, sans-serif !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    line-height: 1.3 !important;
    color: #0B0736 !important;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mv-header__stage {
    margin: 2px 0 0;
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
}

.mv-close {
    width: 32px;
    height: 32px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: #64748b;
    display: inline-grid;
    place-items: center;
    cursor: pointer;
    flex-shrink: 0;
}
.mv-close:hover {
    background: #f1f5f9;
    color: #0B0736;
}

.mv-body {
    flex: 1;
    min-height: 0;
    overflow: auto;
    padding: 12px;
    -webkit-overflow-scrolling: touch;
}

.mv-state {
    margin: 12px;
    font-size: 13px;
    color: #64748b;
}
.mv-state--error { color: #b91c1c; }

.mv-layout {
    display: block;
}

.mv-layout--elevated {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(260px, 0.9fr);
    gap: 12px;
    align-items: start;
}

.mv-main {
    background: #fff;
    border: 1px solid #e8edf3;
    border-radius: 12px;
    padding: 4px 2px;
    min-width: 0;
}

.mv-side {
    background: #fff;
    border: 1px solid #e8edf3;
    border-radius: 12px;
    min-width: 0;
    display: flex;
    flex-direction: column;
    max-height: min(70dvh, 640px);
    overflow: hidden;
}

.mv-tabs {
    display: flex;
    gap: 2px;
    padding: 8px 8px 0;
    border-bottom: 1px solid #eef2f7;
    overflow-x: auto;
}

.mv-tab {
    border: 0;
    background: transparent;
    padding: 8px 10px;
    font-size: 12px;
    font-weight: 650;
    color: #64748b;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.mv-tab.is-active {
    color: #0B0736;
    border-bottom-color: var(--color-primary);
}
.mv-tab__n {
    font-size: 10px;
    font-weight: 700;
    color: var(--color-primary);
    background: var(--color-primary-soft);
    border-radius: 999px;
    padding: 0 5px;
    min-width: 16px;
    text-align: center;
}

.mv-side__panel {
    padding: 10px 12px 12px;
    overflow: auto;
    flex: 1;
}

.mv-kv {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: 12px;
}
.mv-kv span { color: #94a3b8; font-weight: 600; }
.mv-kv strong {
    color: #0B0736;
    font-weight: 650;
    text-align: right;
    word-break: break-word;
}

.mv-note {
    padding: 8px 0;
    border-bottom: 1px solid #f1f5f9;
}
.mv-note time {
    display: block;
    font-size: 10px;
    font-weight: 600;
    color: #94a3b8;
    margin-bottom: 2px;
}
.mv-note p {
    margin: 0;
    font-size: 12px;
    color: #334155;
    line-height: 1.4;
}

.mv-empty {
    margin: 8px 0;
    font-size: 12px;
    color: #94a3b8;
}

.mv-enter-active,
.mv-leave-active { transition: opacity 0.15s ease; }
.mv-enter-active .mv-dialog,
.mv-leave-active .mv-dialog { transition: transform 0.15s ease, opacity 0.15s ease; }
.mv-enter-from,
.mv-leave-to { opacity: 0; }
.mv-enter-from .mv-dialog,
.mv-leave-to .mv-dialog {
    opacity: 0;
    transform: translateY(8px) scale(0.98);
}

@media (max-width: 840px) {
    .mv-layout--elevated {
        grid-template-columns: 1fr;
    }
    .mv-side {
        max-height: none;
    }
    .mv-dialog {
        width: 100%;
        max-height: 92dvh;
    }
    .mv-overlay {
        padding: 8px;
        align-items: flex-end;
    }
}
</style>
