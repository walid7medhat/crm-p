<template>
    <Teleport to="body">
        <div v-if="modelValue" class="mv-overlay" @click.self="close">
            <div class="mv-panel" role="dialog" aria-modal="true">
                <header class="mv-head">
                    <div class="mv-mark"><iconify-icon icon="lucide:git-merge" /></div>
                    <div class="mv-title">
                        <span class="mv-kicker">Merged lead · read only</span>
                        <strong>{{ lead?.lead_name || 'Merged lead' }}</strong>
                    </div>
                    <button type="button" class="mv-x" aria-label="Close" @click="close">
                        <iconify-icon icon="lucide:x" />
                    </button>
                </header>

                <div class="mv-body">
                    <p v-if="loading" class="mv-muted">Loading…</p>
                    <p v-else-if="error" class="mv-error">{{ error }}</p>
                    <LeadInfoView
                        v-else-if="lead"
                        :lead="lead"
                        :readonly="true"
                        :can-edit="false"
                        :show-edit-icon="false"
                        :show-responsible-section="false"
                    />
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue'
import api from '@/plugins/axios'
import LeadInfoView from './LeadInfoView.vue'

const props = defineProps({
    modelValue: Boolean,
    parentId: { type: Number, default: null },   // the default lead (authorizes access)
    mergedId: { type: Number, default: null },
})
const emit = defineEmits(['update:modelValue'])

const lead = ref(null)
const loading = ref(false)
const error = ref('')

const close = () => emit('update:modelValue', false)

async function load() {
    lead.value = null
    error.value = ''
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

watch(() => [props.modelValue, props.mergedId], ([open]) => { if (open) load() }, { immediate: true })
</script>

<style scoped>
.mv-overlay { position: fixed; inset: 0; z-index: 12100; background: rgba(15,23,42,.45);
    display: flex; align-items: center; justify-content: center; padding: 16px; }
.mv-panel { width: min(720px, 100%); max-height: 88dvh; display: flex; flex-direction: column;
    background: #f7f3f8; border-radius: 16px; overflow: hidden; box-shadow: 0 24px 60px rgba(15,23,42,.25); }
.mv-head { display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: #fff;
    border-bottom: 1px solid #f0e8f4; }
.mv-mark { width: 28px; height: 28px; border-radius: 8px; display: grid; place-items: center;
    background: var(--gradient-primary); color: #fff; font-size: 15px; flex: 0 0 28px; }
.mv-title { display: flex; flex-direction: column; min-width: 0; flex: 1; }
.mv-kicker { font-size: 9px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--color-primary); }
.mv-title strong { font-size: 14px; color: #1c1424; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mv-x { border: 0; background: none; font-size: 18px; color: #64748b; cursor: pointer; }
.mv-body { padding: 10px; overflow-y: auto; }
.mv-muted { color: #94a3b8; font-size: 13px; }
.mv-error { color: #dc2626; font-size: 13px; }
</style>