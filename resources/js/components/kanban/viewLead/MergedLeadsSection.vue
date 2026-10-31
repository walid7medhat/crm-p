<template>
    <div v-if="count" class="ml">
        <span class="ml__label">Merged leads</span>
        <button
            type="button"
            class="ml__count"
            :aria-label="`Show ${count} merged lead${count === 1 ? '' : 's'}`"
            @click.stop="popover.open(leadId, $event.currentTarget)"
            @pointerenter="popover.hoverOpen(leadId, $event)"
            @pointerleave="popover.hoverLeave"
        >
            <iconify-icon icon="lucide:git-merge" />
            {{ count }}
        </button>
    </div>

    <MergedLeadsModal
        v-model="popover.show.value"
        :lead-id="popover.leadId.value"
        :trigger-element="popover.trigger.value"
        :z-index="12500"
        openable
        @hover-enter="popover.cancelClose"
        @hover-leave="popover.hoverLeave"
        @open-lead="open"
    />

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
import MergedLeadsModal from '../leadList/MergedLeadsModal.vue'
import { useMergedLeadsPopover } from '../leadList/composables/useMergedLeadsPopover'

const props = defineProps({ leadId: { type: Number, required: true } })

const ELEVATED_ROLES = ['super_admin', 'admin', 'branch_admin']

const popover = useMergedLeadsPopover()
const count = ref(0)
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

function open(item) {
    if (!item?.id) return
    selectedId.value = item.id
    showView.value = true
}

async function load() {
    count.value = 0
    elevatedFromApi.value = null
    popover.show.value = false
    if (!props.leadId) return
    try {
        const res = await api.get(`/leads/${props.leadId}/merged-leads`)
        count.value = (res.data?.data || []).length
        if (typeof res.data?.meta?.elevated_merged_view === 'boolean') {
            elevatedFromApi.value = res.data.meta.elevated_merged_view
        }
    } catch {
        count.value = 0
    }
}

watch(() => props.leadId, load, { immediate: true })
defineExpose({ reload: load })
</script>

<style scoped>
.ml {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 8px;
    padding: 6px 10px;
    min-height: 32px;
    border: 1px solid #e8edf3;
    border-radius: 8px;
    background: #fff;
}

.ml__label {
    font-family: Montserrat, sans-serif;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: #64748b;
}

.ml__count {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    height: 20px;
    padding: 0 8px;
    border: 0;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    color: var(--color-primary);
    background: var(--color-primary-soft);
    cursor: pointer;
    transition: filter 0.15s;
}

.ml__count:hover {
    filter: brightness(0.95);
}

.ml__count:focus-visible {
    outline: 2px solid var(--color-primary-muted);
    outline-offset: 2px;
}
</style>
