<template>
    <div v-if="rows.length" class="ml" role="list" aria-label="Merged leads">
        <span class="ml__label">Merged leads</span>
        <span class="ml__count">{{ rows.length }}</span>
        <span class="ml__sep" aria-hidden="true">·</span>

        <template v-for="(r, index) in rows" :key="r.id">
            <button
                type="button"
                class="ml__name"
                role="listitem"
                :title="r.lead_name || 'Untitled lead'"
                @click="open(r.id)"
            >
                {{ r.lead_name || 'Untitled lead' }}
            </button>
            <span
                v-if="index < rows.length - 1"
                class="ml__sep"
                aria-hidden="true"
            >·</span>
        </template>
    </div>

    <MergedLeadViewModal
        v-model="showView"
        :parent-id="leadId"
        :merged-id="selectedId"
        :elevated="elevated"
    />
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import api from '@/plugins/axios'
import MergedLeadViewModal from './MergedLeadViewModal.vue'

const props = defineProps({ leadId: { type: Number, required: true } })

const ELEVATED_ROLES = ['super_admin', 'admin', 'branch_admin']

const rows = ref([])
const elevatedFromApi = ref(null)
const showView = ref(false)
const selectedId = ref(null)

const elevatedLocal = computed(() => {
    try {
        const user = JSON.parse(localStorage.getItem('user') || '{}')
        const roles = Array.isArray(user.roles)
            ? user.roles.map((r) => (typeof r === 'string' ? r : r?.name)).filter(Boolean)
            : []
        return roles.some((r) => ELEVATED_ROLES.includes(r))
    } catch {
        return false
    }
})

/** Prefer server flag so UI cannot elevate itself. */
const elevated = computed(() => (
    elevatedFromApi.value === null ? elevatedLocal.value : !!elevatedFromApi.value
))

function open(id) {
    selectedId.value = id
    showView.value = true
}

async function load() {
    rows.value = []
    elevatedFromApi.value = null
    if (!props.leadId) return
    try {
        const res = await api.get(`/leads/${props.leadId}/merged-leads`)
        rows.value = res.data?.data || []
        if (typeof res.data?.meta?.elevated_merged_view === 'boolean') {
            elevatedFromApi.value = res.data.meta.elevated_merged_view
        }
    } catch {
        rows.value = []
    }
}

watch(() => props.leadId, load, { immediate: true })
defineExpose({ reload: load })
</script>

<style scoped>
.ml {
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    align-items: center;
    gap: 6px;
    margin: 0 0 8px;
    padding: 6px 10px;
    min-height: 32px;
    border: 1px solid #e8edf3;
    border-radius: 8px;
    background: #fff;
    overflow-x: auto;
    overflow-y: hidden;
    white-space: nowrap;
    scrollbar-width: thin;
}

.ml__label {
    flex: 0 0 auto;
    font-family: Montserrat, sans-serif;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: #64748b;
}

.ml__count {
    flex: 0 0 auto;
    font-size: 10px;
    font-weight: 700;
    color: var(--color-primary);
    background: var(--color-primary-soft);
    border-radius: 999px;
    min-width: 16px;
    height: 16px;
    padding: 0 5px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.ml__sep {
    flex: 0 0 auto;
    color: #cbd5e1;
    font-size: 12px;
    line-height: 1;
}

.ml__name {
    flex: 0 1 auto;
    max-width: 180px;
    margin: 0;
    padding: 0;
    border: 0;
    background: transparent;
    font-family: Montserrat, sans-serif;
    font-size: 12px;
    font-weight: 600;
    color: #0B0736;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: pointer;
    text-align: left;
}

.ml__name:hover {
    color: var(--color-primary);
    text-decoration: underline;
}

.ml__name:focus-visible {
    outline: 2px solid var(--color-primary-muted);
    outline-offset: 2px;
    border-radius: 4px;
}
</style>
