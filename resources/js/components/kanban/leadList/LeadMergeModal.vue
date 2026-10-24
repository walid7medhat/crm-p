<template>
    <Teleport to="body">
        <div v-if="show" class="lm-overlay" @click.self="close">
            <div class="lm-panel" role="dialog" aria-modal="true">
                <header class="lm-head">
                    <h6>{{ step === 1 ? 'Merge duplicates: select the default lead' : 'Merge preview' }}</h6>
                    <button type="button" class="lm-x" aria-label="Close" @click="close">
                        <iconify-icon icon="lucide:x" />
                    </button>
                </header>

                <div class="lm-body">
                    <p v-if="loading" class="lm-muted">Loading…</p>
                    <p v-else-if="error" class="lm-error">{{ error }}</p>

                    <!-- STEP 1 -->
                    <template v-else-if="step === 1">
                        <p v-if="hasPool" class="lm-note">
                            Lead Pool leads are merged automatically every day, and only with other Lead Pool leads.
                        </p>
                        <label
                            v-for="l in leads" :key="l.id"
                            class="lm-card" :class="{ 'is-selected': defaultId === l.id }"
                        >
                            <input v-model="defaultId" type="radio" :value="l.id" />
                            <div class="lm-card__main">
                                <strong>#{{ l.id }} · {{ l.lead_name || 'Untitled' }}</strong>
                                <span>{{ l.work_phone || '—' }} · {{ l.email || '—' }}</span>
                                <span>Stage: {{ l.stage_name || '—' }}</span>
                                <span v-if="l.property_type || l.area">
                                    {{ [l.property_type, l.area, l.bedrooms != null ? l.bedrooms + ' BR' : null].filter(Boolean).join(' · ') }}
                                </span>
                            </div>
                        </label>
                    </template>

                    <!-- STEP 2 -->
                    <template v-else-if="preview">
                        <div class="lm-default">
                            <span class="lm-badge">DEFAULT LEAD</span>
                            <strong>#{{ preview.default.id }} · {{ preview.default.lead_name }}</strong>
                            <span>{{ preview.default.work_phone || '—' }} · {{ preview.default.email || '—' }}</span>
                            <span>Stage: {{ preview.default.stage_name }} (stays unchanged)</span>
                        </div>

                        <h6 class="lm-sub">Will be merged ({{ preview.eligible.length }})</h6>
                        <p v-if="!preview.eligible.length" class="lm-muted">No lead can be merged into this default.</p>
                        <div v-for="l in preview.eligible" :key="l.id" class="lm-row lm-row--ok">
                            <strong>#{{ l.id }} · {{ l.lead_name }}</strong>
                            <span>{{ l.work_phone || '—' }} · {{ l.stage_name }}</span>
                        </div>

                        <template v-if="preview.ineligible.length">
                            <h6 class="lm-sub">Will stay separate ({{ preview.ineligible.length }})</h6>
                            <div v-for="l in preview.ineligible" :key="l.id" class="lm-row lm-row--no">
                                <strong>#{{ l.id }} · {{ l.lead_name }}</strong>
                                <span>{{ l.work_phone || '—' }} · {{ l.stage_name }}</span>
                                <em>{{ l.reason }}</em>
                            </div>
                        </template>
                    </template>
                </div>

                <footer class="lm-foot">
                    <template v-if="step === 1">
                        <button type="button" class="lm-btn lm-btn--ghost" @click="close">Cancel</button>
                        <button type="button" class="lm-btn" :disabled="!defaultId || loading" @click="goPreview">Next</button>
                    </template>
                    <template v-else>
                        <button type="button" class="lm-btn lm-btn--ghost" :disabled="submitting" @click="step = 1">Back</button>
                        <button
                            type="button" class="lm-btn"
                            :disabled="submitting || !preview?.eligible.length"
                            @click="confirm"
                        >{{ submitting ? 'Merging…' : 'Confirm Merge' }}</button>
                    </template>
                </footer>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import api from '@/plugins/axios'

const props = defineProps({
    modelValue: Boolean,
    leadId: { type: Number, default: null },
})
const emit = defineEmits(['update:modelValue', 'merged'])

const show = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
})

const step = ref(1)
const leads = ref([])
const defaultId = ref(null)
const preview = ref(null)
const loading = ref(false)
const submitting = ref(false)
const error = ref('')

const hasPool = computed(() => leads.value.some((l) => l.is_pool))
const notify = (m, t = 'info') => window.$showNotification?.(m, t)

const close = () => { if (!submitting.value) show.value = false }

async function load() {
    step.value = 1
    defaultId.value = null
    preview.value = null
    error.value = ''
    if (!props.leadId) return
    loading.value = true
    try {
        const res = await api.get(`/leads/${props.leadId}/merge/candidates`)
        leads.value = res.data?.data?.leads || []
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not load duplicate leads'
    } finally {
        loading.value = false
    }
}

async function goPreview() {
    if (!defaultId.value) return
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
    try {
        const res = await api.post('/leads/merge/confirm', {
            default_lead_id: preview.value.default.id,
            lead_ids: preview.value.eligible.map((l) => l.id),
        })
        const data = res.data?.data
        notify('Leads merged successfully', 'success')
        show.value = false
        emit('merged', data?.default_lead_id || preview.value.default.id)
    } catch (e) {
        notify(e.response?.data?.message || 'Merge failed', 'error')
    } finally {
        submitting.value = false
    }
}

watch(show, (v) => { if (v) load() }, { immediate: true })
</script>

<style scoped>
.lm-overlay { position: fixed; inset: 0; z-index: 12100; background: rgba(15,23,42,.45);
    display: flex; align-items: center; justify-content: center; padding: 16px; }
.lm-panel { width: min(560px, 100%); max-height: 86dvh; display: flex; flex-direction: column;
    background: #fff; border-radius: 16px; box-shadow: 0 24px 60px rgba(15,23,42,.25); overflow: hidden; }
.lm-head, .lm-foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 14px 18px; }
.lm-head { border-bottom: 1px solid #eef2f7; }
.lm-head h6 { margin: 0; font-size: 15px !important; font-weight: 700; color: #0B0736; }
.lm-foot { border-top: 1px solid #eef2f7; justify-content: flex-end; }
.lm-x { border: 0; background: none; font-size: 18px; color: #64748b; cursor: pointer; }
.lm-body { padding: 14px 18px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; }
.lm-muted { color: #94a3b8; font-size: 13px; margin: 0; }
.lm-error { color: #dc2626; font-size: 13px; margin: 0; }
.lm-note { background: #fff7ed; border: 1px dashed #fdba74; border-radius: 10px; padding: 8px 10px; font-size: 12px; color: #9a3412; margin: 0; }
.lm-card { display: flex; gap: 10px; align-items: flex-start; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 12px; cursor: pointer; }
.lm-card.is-selected { border-color: var(--color-primary); background: #faf5ff; }
.lm-card__main { display: flex; flex-direction: column; gap: 2px; font-size: 12px; color: #475569; }
.lm-card__main strong { color: #0B0736; font-size: 13px; }
.lm-default { display: flex; flex-direction: column; gap: 3px; padding: 12px; border: 2px solid var(--color-primary);
    border-radius: 12px; background: #faf5ff; font-size: 12px; color: #475569; }
.lm-badge { align-self: flex-start; background: var(--gradient-primary); color: #fff; font-size: 10px; font-weight: 800;
    letter-spacing: .08em; padding: 2px 8px; border-radius: 999px; }
.lm-sub { margin: 6px 0 0; font-size: 12px !important; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #64748b; }
.lm-row { display: flex; flex-direction: column; gap: 2px; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 10px; font-size: 12px; color: #475569; }
.lm-row--ok { border-left: 4px solid #16a34a; }
.lm-row--no { border-left: 4px solid #f59e0b; background: #fffbeb; }
.lm-row em { color: #92400e; font-style: normal; font-size: 11px; }
.lm-btn { border: 0; border-radius: 999px; padding: 9px 20px; background: var(--gradient-primary); color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; }
.lm-btn:disabled { opacity: .45; cursor: not-allowed; }
.lm-btn--ghost { background: #f1f5f9; color: #0B0736; }
</style>