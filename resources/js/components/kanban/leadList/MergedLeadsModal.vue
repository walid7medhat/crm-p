<template>
    <Teleport to="body">
        <div v-if="modelValue && isSheet" class="mlp-backdrop" @click.stop="close"></div>
        <div
            v-if="modelValue"
            ref="popupRef"
            class="mlp"
            :class="{ 'mlp--sheet': isSheet }"
            :style="isSheet ? null : popupStyle"
            role="dialog"
            aria-label="Merged leads"
            @click.stop
            @pointerenter="(e) => e.pointerType === 'mouse' && emit('hover-enter', e)"
            @pointerleave="(e) => e.pointerType === 'mouse' && emit('hover-leave', e)"
        >
            <header class="mlp-head">
                <span class="mlp-flag"><iconify-icon icon="lucide:git-merge" /></span>
                <span class="mlp-title">Merged leads</span>
                <span v-if="!loading && !error" class="mlp-count">{{ items.length }}</span>
                <button type="button" class="mlp-close" aria-label="Close" @click="close">
                    <iconify-icon icon="lucide:x" />
                </button>
            </header>

            <div class="mlp-body">
                <p v-if="loading" class="mlp-hint">Loading…</p>
                <p v-else-if="error" class="mlp-hint mlp-hint--error">{{ error }}</p>
                <p v-else-if="!items.length" class="mlp-hint">No merged leads.</p>

                <article v-for="item in items" :key="item.id" class="mlp-row">
                    <strong class="mlp-row__name">{{ item.lead_name || 'Untitled lead' }}</strong>
                    <div v-if="item.stage_name || ownerName(item) || (isAdmin && item.merged_at)" class="mlp-row__meta">
                        <span v-if="item.stage_name" class="mlp-chip">{{ item.stage_name }}</span>
                        <span v-if="ownerName(item)">{{ ownerName(item) }}</span>
                        <span v-if="isAdmin && item.merged_at">Merged {{ fmt(item.merged_at) }}</span>
                    </div>
                </article>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, watch, nextTick, onUnmounted } from 'vue'
import api, { getApiErrorMessage } from '@/plugins/axios'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    leadId: { type: [Number, String], default: null },
    triggerElement: { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'hover-enter', 'hover-leave'])

const ADMIN_ROLES = ['super_admin', 'admin', 'branch_admin']
const POPUP_WIDTH = 280
const MOBILE_MAX = 768
const CACHE_TTL_MS = 60_000
const cache = new Map()

const items = ref([])
const loading = ref(false)
const error = ref('')
const popupRef = ref(null)
const popupStyle = ref({})
const isSheet = ref(typeof window !== 'undefined' && window.innerWidth <= MOBILE_MAX)

const isAdmin = (() => {
    try {
        const roles = JSON.parse(localStorage.getItem('user') || 'null')?.roles
        return Array.isArray(roles) && roles.some((r) => ADMIN_ROLES.includes(r))
    } catch {
        return false
    }
})()

const close = () => emit('update:modelValue', false)

const ownerName = (item) => {
    const p = item?.responsible_person
    return p && typeof p === 'object' ? p.name || '' : p || ''
}

const fmt = (value) => {
    const d = new Date(value)
    if (!value || Number.isNaN(d.getTime())) return '—'
    return d.toLocaleString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric',
        hour: 'numeric', minute: '2-digit', timeZone: 'Asia/Dubai',
    })
}

async function load(id) {
    if (!id) return
    const cached = cache.get(id)
    if (cached && Date.now() - cached.at < CACHE_TTL_MS) {
        items.value = cached.rows
        error.value = ''
        return
    }
    loading.value = true
    error.value = ''
    items.value = []
    try {
        const response = await api.get(`/leads/${id}/merged-leads`)
        const rows = Array.isArray(response.data?.data) ? response.data.data : []
        cache.set(id, { at: Date.now(), rows })
        if (props.leadId === id) items.value = rows
    } catch (e) {
        if (props.leadId === id) error.value = getApiErrorMessage(e, 'Could not load merged leads')
    } finally {
        if (props.leadId === id) loading.value = false
    }
}

function position() {
    isSheet.value = window.innerWidth <= MOBILE_MAX
    if (isSheet.value) return

    const trigger = props.triggerElement
    if (!trigger?.getBoundingClientRect) {
        popupStyle.value = { top: '50%', left: '50%', transform: 'translate(-50%, -50%)', width: `${POPUP_WIDTH}px` }
        return
    }

    const rect = trigger.getBoundingClientRect()
    const vw = window.innerWidth
    const vh = window.innerHeight
    const width = Math.min(POPUP_WIDTH, vw - 32)
    const height = popupRef.value?.offsetHeight || 160
    const gap = 6

    let left = rect.right + gap
    if (left + width > vw - 16) {
        left = rect.left - width - gap >= 16 ? rect.left - width - gap : vw - width - 16
    }

    let top = rect.top
    if (top + height > vh - 16) top = vh - height - 16

    popupStyle.value = {
        top: `${Math.max(16, top)}px`,
        left: `${Math.max(16, left)}px`,
        width: `${width}px`,
    }
}

const onOutsideClick = (event) => {
    if (popupRef.value?.contains(event.target)) return
    if (props.triggerElement?.contains?.(event.target)) return
    close()
}
const onKey = (event) => {
    if (event.key === 'Escape') close()
}

function bind(on) {
    const method = on ? 'addEventListener' : 'removeEventListener'
    document[method]('click', onOutsideClick)
    document[method]('keydown', onKey)
    window[method]('resize', onViewportChange)
    window[method]('scroll', onViewportChange, true)
}

// Mobile browsers fire resize/scroll when the address bar moves; the sheet must stay open.
function onViewportChange(event) {
    if (isSheet.value) return
    if (event?.type === 'scroll' && popupRef.value?.contains(event.target)) return
    close()
}

watch(
    () => [props.modelValue, props.leadId, props.triggerElement],
    async ([open, id], prev) => {
        bind(false)
        if (!open) return
        if (!prev || prev[1] !== id || !prev[0]) load(id)
        await nextTick()
        position()
        requestAnimationFrame(position)
        setTimeout(() => bind(true), 0)
    },
    { immediate: true },
)

watch(items, () => nextTick(position))

onUnmounted(() => bind(false))
</script>

<style scoped>
.mlp {
    position: fixed;
    z-index: 1050;
    display: flex;
    flex-direction: column;
    max-height: 300px;
    background: #fff;
    border-radius: 12px;
    border: 1px solid rgba(11, 7, 54, 0.08);
    box-shadow: 0 10px 28px rgba(11, 7, 54, 0.18);
    overflow: hidden;
    animation: mlp-in 0.14s ease-out;
}
@keyframes mlp-in {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}
.mlp-head {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 9px 10px 9px 12px;
    border-bottom: 1px solid #eef2f7;
}
.mlp-flag {
    width: 22px;
    height: 22px;
    border-radius: 7px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    background: var(--gradient-primary, var(--color-primary));
    color: #fff;
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.35),
        inset 0 -2px 0 rgba(0, 0, 0, 0.18),
        0 2px 0 color-mix(in srgb, var(--color-primary) 60%, #000);
}
.mlp-title { font-size: 13px; font-weight: 700; color: #0b0736; }
.mlp-count {
    flex: 1;
    font-size: 12px;
    font-weight: 700;
    color: var(--color-primary);
}
.mlp-close {
    width: 24px;
    height: 24px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: #6b7280;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.mlp-close:hover { background: rgba(11, 7, 54, 0.06); color: #0b0736; }
.mlp-body {
    padding: 8px 10px 10px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.mlp-hint { margin: 6px 0; color: #64748b; font-size: 12px; text-align: center; }
.mlp-hint--error { color: #b91c1c; }
.mlp-row {
    border: 1px solid #eef2f7;
    border-radius: 9px;
    padding: 7px 9px;
    background: #fbfbfd;
}
.mlp-row__name {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 12.5px;
    color: #0b0736;
}
.mlp-row__meta {
    margin-top: 4px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px 8px;
    font-size: 11px;
    color: #64748b;
}
.mlp-chip {
    padding: 1px 7px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    color: var(--color-primary);
    background: rgba(var(--color-primary-rgb), 0.12);
}

.mlp-backdrop {
    position: fixed;
    inset: 0;
    z-index: 12090;
    background: rgba(15, 23, 42, 0.45);
}
.mlp--sheet {
    left: 0;
    right: 0;
    bottom: 0;
    width: 100%;
    max-height: 60dvh;
    z-index: 12100;
    border-radius: 18px 18px 0 0;
    padding-bottom: env(safe-area-inset-bottom, 0px);
    animation: mlp-up 0.22s ease-out;
}
@keyframes mlp-up {
    from { transform: translateY(100%); }
    to { transform: translateY(0); }
}
</style>
