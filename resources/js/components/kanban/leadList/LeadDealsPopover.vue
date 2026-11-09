<template>
    <Teleport to="body">
        <div
            v-if="modelValue && isSheet"
            class="ldp-backdrop"
            @click.stop="close"
        ></div>
        <div
            v-if="modelValue"
            ref="popupRef"
            class="ldp"
            :class="{ 'ldp--sheet': isSheet }"
            :style="isSheet ? null : popupStyle"
            role="dialog"
            aria-label="Deals"
            @click.stop
            @pointerenter="(e) => e.pointerType === 'mouse' && emit('hover-enter', e)"
            @pointerleave="(e) => e.pointerType === 'mouse' && emit('hover-leave', e)"
        >
            <header class="ldp-head">
                <span class="ldp-flag"><iconify-icon icon="lucide:handshake" /></span>
                <span class="ldp-title">Deals</span>
                <span class="ldp-count">{{ items.length }}</span>
                <button type="button" class="ldp-close" aria-label="Close" @click="close">
                    <iconify-icon icon="lucide:x" />
                </button>
            </header>

            <div class="ldp-body">
                <p v-if="!items.length" class="ldp-hint">No deals.</p>

                <article
                    v-for="deal in items"
                    :key="deal.id"
                    class="ldp-row"
                    role="button"
                    tabindex="0"
                    @click="openDeal(deal)"
                    @keydown.enter="openDeal(deal)"
                >
                    <div class="ldp-row__top">
                        <strong class="ldp-row__name">{{ deal.deal_name || 'Deal' }}</strong>
                        <iconify-icon icon="lucide:chevron-right" class="ldp-row__go" aria-hidden="true" />
                    </div>
                    <div class="ldp-row__meta">
                        <span v-if="deal.deal_type" class="ldp-chip">{{ dealTypeLabel(deal.deal_type) }}</span>
                        <span v-if="deal.stage?.name" class="ldp-stage">
                            <span class="ldp-dot" :style="deal.stage.color ? { background: deal.stage.color } : null"></span>
                            {{ deal.stage.name }}
                        </span>
                        <span v-if="deal.responsible_person?.name">{{ deal.responsible_person.name }}</span>
                    </div>
                    <div v-if="fmtCreated(deal.created_at)" class="ldp-row__time">
                        <iconify-icon icon="lucide:clock-3" aria-hidden="true" />
                        <span>Created {{ fmtCreated(deal.created_at) }}</span>
                    </div>
                </article>
                <p v-if="loading" class="ldp-hint ldp-hint--sm">Loading details…</p>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
/**
 * Deals made from a converted lead — opened from the deal count on a lead card, on hover
 * or click. Same pattern as MergedLeadsModal. Shows the card's own deals (number + type)
 * at once, then the full rows (name, stage, responsible) from /leads/{id}/can-convert —
 * the same list as the lead modal header's "Deals".
 */
import { ref, watch, nextTick, onUnmounted } from 'vue'
import api from '@/plugins/axios'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    leadId: { type: [Number, String], default: null },
    /** The card's deals ({ id, deal_number, deal_type }), shown until details load. */
    deals: { type: Array, default: () => [] },
    triggerElement: { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'hover-enter', 'hover-leave', 'open-deal'])

const POPUP_WIDTH = 290
const MOBILE_MAX = 768
const CACHE_TTL_MS = 60_000
const cache = new Map()

const TYPE_LABELS = { primary: 'Primary', secondary: 'Secondary', rental: 'Rental' }
const dealTypeLabel = (type) => TYPE_LABELS[type] || String(type)

const items = ref([])
const loading = ref(false)
const popupRef = ref(null)
const popupStyle = ref({})
const isSheet = ref(typeof window !== 'undefined' && window.innerWidth <= MOBILE_MAX)

const close = () => emit('update:modelValue', false)

/**
 * Deal created time. can-convert sends "Y-m-d H:i:s" already in the app timezone (UAE),
 * so format those wall-clock parts as-is instead of re-converting by the browser zone.
 */
function fmtCreated(value) {
    const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(String(value || ''))
    if (!m) return ''
    const d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3], +m[4], +m[5]))
    return d.toLocaleString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric',
        hour: 'numeric', minute: '2-digit', timeZone: 'UTC',
    })
}

function openDeal(deal) {
    emit('open-deal', deal)
    close()
}

async function load(id) {
    items.value = Array.isArray(props.deals) ? props.deals : []
    if (!id) return
    const cached = cache.get(id)
    if (cached && Date.now() - cached.at < CACHE_TTL_MS) {
        items.value = cached.rows
        return
    }
    loading.value = true
    try {
        const response = await api.get(`/leads/${id}/can-convert`)
        const rows = response.data?.data?.deals
        if (Array.isArray(rows) && rows.length) {
            cache.set(id, { at: Date.now(), rows })
            if (props.leadId === id) items.value = rows
        }
    } catch (_) {
        // No access to the details: keep the card's deal numbers.
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
.ldp {
    position: fixed;
    z-index: 1050;
    display: flex;
    flex-direction: column;
    max-height: 320px;
    background: #fff;
    border-radius: 12px;
    border: 1px solid rgba(11, 7, 54, 0.08);
    box-shadow: 0 10px 28px rgba(11, 7, 54, 0.18);
    overflow: hidden;
    animation: ldp-in 0.14s ease-out;
}
@keyframes ldp-in {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}
.ldp-head {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 9px 10px 9px 12px;
    border-bottom: 1px solid #eef2f7;
}
/* Same 3D keycap as the card's deal count */
.ldp-flag {
    width: 22px;
    height: 22px;
    border-radius: 7px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    background: linear-gradient(160deg, #6ee7b7 0%, #10b981 45%, #059669 100%);
    color: #fff;
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.45),
        inset 0 -2px 0 rgba(0, 0, 0, 0.15),
        0 2px 0 #065f46;
}
.ldp-title { font-size: 13px; font-weight: 700; color: #0b0736; }
.ldp-count {
    flex: 1;
    font-size: 12px;
    font-weight: 700;
    color: #059669;
}
.ldp-close {
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
.ldp-close:hover { background: rgba(11, 7, 54, 0.06); color: #0b0736; }
.ldp-body {
    padding: 8px 10px 10px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
/* Thin scrollbar */
.ldp-body {
    scrollbar-width: thin;
    scrollbar-color: #d1d5db transparent;
}
.ldp-body::-webkit-scrollbar { width: 4px; }
.ldp-body::-webkit-scrollbar-track { background: transparent; }
.ldp-body::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 999px;
}
.ldp-body::-webkit-scrollbar-thumb:hover { background: var(--color-primary, #9ca3af); }
.ldp-hint { margin: 6px 0; color: #64748b; font-size: 12px; text-align: center; }
.ldp-hint--sm { margin: 2px 0 0; font-size: 11px; }
.ldp-row {
    border: 1px solid #eef2f7;
    border-radius: 9px;
    padding: 7px 9px;
    background: #fbfbfd;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
}
.ldp-row:hover,
.ldp-row:focus-visible {
    border-color: #a7f3d0;
    background: #f0fdf4;
    outline: none;
}
.ldp-row__top {
    display: flex;
    align-items: center;
    gap: 6px;
}
.ldp-row__name {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 12.5px;
    color: #0b0736;
}
.ldp-row:hover .ldp-row__name { color: #047857; }
.ldp-row__go {
    flex-shrink: 0;
    font-size: 13px;
    color: #94a3b8;
    transition: transform 0.15s, color 0.15s;
}
.ldp-row:hover .ldp-row__go { color: #059669; transform: translateX(2px); }
.ldp-row__meta {
    margin-top: 4px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px 8px;
    font-size: 11px;
    color: #64748b;
}
.ldp-row__time {
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 10.5px;
    color: #94a3b8;
}
.ldp-row__time iconify-icon { font-size: 11px; }
.ldp-chip {
    padding: 1px 7px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    color: #047857;
    background: rgba(16, 185, 129, 0.14);
}
.ldp-stage {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.ldp-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #94a3b8;
}

.ldp-backdrop {
    position: fixed;
    inset: 0;
    z-index: 12090;
    background: rgba(15, 23, 42, 0.45);
}
.ldp--sheet {
    left: 0;
    right: 0;
    bottom: 0;
    width: 100%;
    max-height: 60dvh;
    z-index: 12100;
    border-radius: 18px 18px 0 0;
    padding-bottom: env(safe-area-inset-bottom, 0px);
    animation: ldp-up 0.22s ease-out;
}
@keyframes ldp-up {
    from { transform: translateY(100%); }
    to { transform: translateY(0); }
}
</style>
