<template>
    <Teleport to="body">
        <!-- Backdrop -->
        <div
            v-if="show"
            class="lead-deals-backdrop"
            :class="{ 'lead-deals-backdrop--sheet': isSheet }"
            @click.stop="show = false"
        ></div>

        <!-- Dropdown popup (bottom sheet on mobile) — same pattern as DuplicateLeadsModal -->
        <div
            v-if="show"
            ref="popupRef"
            class="lead-deals-dropdown"
            :class="{ 'lead-deals-dropdown--sheet': isSheet }"
            :style="isSheet ? null : popupStyle"
            @click.stop
        >
            <div class="lead-deals-modal-content">
                <div v-if="isSheet" class="sheet-handle" aria-hidden="true"></div>
                <div class="modal-header-custom d-flex justify-content-between align-items-center py-3 border-bottom">
                    <span class="modal-title mb-0">Deals ({{ deals.length }})</span>
                    <button type="button" class="close-btn" aria-label="Close deals" @click="show = false">
                        <iconify-icon icon="lucide:x"></iconify-icon>
                    </button>
                </div>

                <div class="modal-body-custom">
                    <div v-if="deals.length === 0" class="text-center py-5">
                        <p class="text-secondary">No deals for this lead yet</p>
                    </div>

                    <div v-else class="lead-deals-list d-flex flex-column">
                        <div
                            v-for="deal in deals"
                            :key="deal.id"
                            class="lead-deal-card cursor-pointer"
                            role="button"
                            tabindex="0"
                            @click="openDeal(deal)"
                            @keydown.enter="openDeal(deal)"
                        >
                            <div class="deal-card-head">
                                <span class="deal-type-icon" aria-hidden="true">
                                    <iconify-icon :icon="dealTypeIcon(deal.deal_type)"></iconify-icon>
                                </span>
                                <div class="deal-card-heading">
                                    <p class="deal-title">{{ deal.deal_name || `Deal #${deal.deal_number || deal.id}` }}</p>
                                    <span v-if="deal.deal_number" class="deal-number">{{ deal.deal_number }}</span>
                                </div>
                            </div>

                            <div class="deal-badges">
                                <span class="deal-type-badge">{{ formatDealType(deal.deal_type) }}</span>
                                <span class="deal-stage-badge">
                                    <span class="stage-dot" aria-hidden="true"></span>
                                    {{ deal.stage?.name || 'No stage' }}
                                </span>
                            </div>

                            <div v-if="deal.responsible_person?.name" class="deal-responsible">
                                <iconify-icon icon="lucide:user-round" aria-hidden="true"></iconify-icon>
                                <span>{{ deal.responsible_person.name }}</span>
                            </div>

                            <iconify-icon icon="lucide:chevron-right" class="deal-open-icon" aria-hidden="true"></iconify-icon>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, watch, nextTick, onUnmounted } from 'vue'

const props = defineProps({
    modelValue: Boolean,
    deals: {
        type: Array,
        default: () => []
    },
    triggerElement: {
        type: Object,
        default: null
    }
})

const emit = defineEmits(['update:modelValue', 'open-deal'])

const show = ref(props.modelValue)
const popupRef = ref(null)
const popupStyle = ref({})

const MOBILE_MAX = 768
const isSheet = ref(typeof window !== 'undefined' && window.innerWidth <= MOBILE_MAX)

const DEAL_TYPE_LABELS = {
    primary: 'Primary / Off Plan',
    secondary: 'Secondary',
    rental: 'Rental',
}

const DEAL_TYPE_ICONS = {
    primary: 'lucide:home',
    secondary: 'lucide:building-2',
    rental: 'lucide:key-round',
}

function formatDealType(type) {
    return DEAL_TYPE_LABELS[type] || (type ? String(type) : '----')
}

function dealTypeIcon(type) {
    return DEAL_TYPE_ICONS[type] || 'lucide:handshake'
}

const calculatePosition = async () => {
    isSheet.value = window.innerWidth <= MOBILE_MAX
    if (isSheet.value) return

    const trigger = props.triggerElement
    if (!trigger?.getBoundingClientRect) {
        popupStyle.value = {
            position: 'fixed',
            top: '50%',
            left: '50%',
            transform: 'translate(-50%, -50%)',
            width: '416px',
            maxWidth: 'calc(100vw - 32px)',
        }
        return
    }

    await nextTick()
    if (!popupRef.value) return

    requestAnimationFrame(() => {
        const rect = trigger.getBoundingClientRect()
        const viewportHeight = window.innerHeight
        const viewportWidth = window.innerWidth
        const popupWidth = Math.min(416, viewportWidth - 32)
        const estimatedHeight = Math.min(444, props.deals.length * 130 + 100)

        // The trigger sits in the lead header's right corner — align the popup's right
        // edge with it so it opens inward instead of off-screen.
        let left = rect.right - popupWidth
        left = Math.min(Math.max(16, left), viewportWidth - popupWidth - 16)

        const spaceBelow = viewportHeight - rect.bottom
        let top = spaceBelow >= estimatedHeight + 8 || spaceBelow >= rect.top
            ? rect.bottom + 8
            : rect.top - estimatedHeight - 8
        if (top < 16) top = 16
        const maxTop = viewportHeight - estimatedHeight - 16
        if (top > maxTop) top = Math.max(16, maxTop)

        popupStyle.value = {
            position: 'fixed',
            top: `${top}px`,
            left: `${left}px`,
            width: `${popupWidth}px`,
            maxWidth: 'calc(100vw - 32px)',
        }
    })
}

const handleResize = () => {
    if (show.value) calculatePosition()
}

function openDeal(deal) {
    emit('open-deal', deal)
    show.value = false
}

function addListeners() {
    window.addEventListener('resize', handleResize)
    window.addEventListener('scroll', handleResize, true)
}

function removeListeners() {
    window.removeEventListener('resize', handleResize)
    window.removeEventListener('scroll', handleResize, true)
}

watch(() => props.modelValue, (val) => {
    show.value = val
}, { immediate: true })

watch(show, (val) => {
    emit('update:modelValue', val)
    if (val) {
        nextTick(() => {
            calculatePosition()
            addListeners()
        })
    } else {
        removeListeners()
    }
}, { immediate: true })

onUnmounted(removeListeners)
</script>

<style scoped>
/* Opened from inside the lead modal, so it must sit above it (but below the
   convert-lead overlay, z-index 101800). */
.lead-deals-backdrop {
    position: fixed;
    inset: 0;
    z-index: 100040;
    background-color: transparent;
}

.lead-deals-dropdown {
    position: fixed;
    z-index: 100050;
    animation: fadeInDown 0.2s ease-out;
}

@keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.lead-deals-modal-content {
    padding: 0 18px;
    background-color: #FFFFFF;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    max-height: 444px;
    width: 416px;
    max-width: 100%;
    display: flex;
    flex-direction: column;
}

.modal-header-custom {
    background-color: #FFFFFF;
    border-bottom: 1px solid #EBECEF;
    flex-shrink: 0;
}

.modal-title {
    font-family: Montserrat;
    font-size: 14px;
    line-height: 24px;
    color: #0B0736;
}

.close-btn {
    background: none;
    border: none;
    padding: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6B7280;
    transition: color 0.2s;
}

.close-btn:hover {
    color: #0B0736;
}

.modal-body-custom {
    flex: 1;
    overflow-y: auto;
    min-height: 0;
}

/* One app theme for every card: brand purple accent on a soft lilac tint, navy text
   (same palette as the lead header "Deals" chip and the deal popups). */
.lead-deal-card {
    --accent: #733E87;
    --accent-soft: #FAF5FF;
    --accent-border: #E9D5FF;
    position: relative;
    margin: 12px 0;
    padding: 14px 40px 14px 18px;
    border: 1px solid var(--accent-border);
    border-radius: 14px;
    background: linear-gradient(135deg, var(--accent-soft) 0%, #FFFFFF 70%);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

/* Left accent bar */
.lead-deal-card::before {
    content: '';
    position: absolute;
    inset: 0 auto 0 0;
    width: 4px;
    background: var(--accent);
}

.lead-deal-card:hover,
.lead-deal-card:focus-visible {
    transform: translateY(-2px);
    border-color: var(--accent);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.10);
    outline: none;
}

.lead-deal-card:focus-visible {
    box-shadow: 0 0 0 3px rgba(115, 62, 135, 0.25), 0 8px 20px rgba(15, 23, 42, 0.10);
}

.deal-card-head {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    min-width: 0;
}

.deal-type-icon {
    flex-shrink: 0;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--accent);
    color: #FFFFFF;
    font-size: 17px;
    box-shadow: 0 4px 10px rgba(115, 62, 135, 0.30);
}

.deal-card-heading {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.deal-title {
    font-family: Montserrat;
    font-weight: 700;
    font-size: 13px;
    line-height: 18px;
    color: #0B0736;
    margin: 0;
    overflow-wrap: anywhere;
}

.deal-number {
    font-family: Montserrat;
    font-weight: 500;
    font-size: 11px;
    line-height: 14px;
    color: #64748B;
}

.deal-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.deal-type-badge,
.deal-stage-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 24px;
    padding: 0 10px;
    border-radius: 999px;
    font-family: Montserrat;
    font-weight: 600;
    font-size: 11px;
    line-height: 1;
    white-space: nowrap;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
}

.deal-type-badge {
    background: #FFFFFF;
    border: 1px solid var(--accent-border);
    color: var(--accent);
}

.deal-stage-badge {
    background: #F3E8FF;
    border: 1px solid var(--accent-border);
    color: #0B0736;
}

.stage-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
    background: var(--accent);
}

.deal-responsible {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed var(--accent-border);
    font-family: Montserrat;
    font-weight: 500;
    font-size: 11.5px;
    color: #475569;
    min-width: 0;
}

.deal-responsible span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.deal-responsible iconify-icon {
    flex-shrink: 0;
    color: var(--accent);
    font-size: 14px;
}

.deal-open-icon {
    position: absolute;
    top: 50%;
    right: 12px;
    transform: translateY(-50%);
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #FFFFFF;
    border: 1px solid var(--accent-border);
    color: var(--accent);
    font-size: 14px;
    transition: transform 0.2s ease, background-color 0.2s ease, color 0.2s ease;
}

.lead-deal-card:hover .deal-open-icon {
    transform: translate(2px, -50%);
    background: var(--accent);
    color: #FFFFFF;
}

.cursor-pointer {
    cursor: pointer;
}

/* ---- Mobile bottom sheet ---- */
.lead-deals-backdrop--sheet {
    background-color: rgba(15, 23, 42, 0.45);
}

.lead-deals-dropdown--sheet {
    left: 0;
    right: 0;
    bottom: 0;
    top: auto;
    width: 100%;
    animation: sheetUp 0.25s ease-out;
}

.lead-deals-dropdown--sheet .lead-deals-modal-content {
    width: 100%;
    max-height: 75dvh;
    border-radius: 20px 20px 0 0;
    padding: 0 16px calc(12px + env(safe-area-inset-bottom, 0px));
    box-shadow: 0 -10px 30px rgba(15, 23, 42, 0.15);
}

.sheet-handle {
    width: 40px;
    height: 4px;
    border-radius: 2px;
    background: #d1d5db;
    margin: 8px auto 0;
    flex-shrink: 0;
}

@keyframes sheetUp {
    from { transform: translateY(100%); }
    to { transform: translateY(0); }
}

.modal-body-custom::-webkit-scrollbar {
    width: 8px;
}

.modal-body-custom::-webkit-scrollbar-track {
    background: #F3F4F6;
    border-radius: 4px;
}

.modal-body-custom::-webkit-scrollbar-thumb {
    background-color: #D1D5DB;
    border-radius: 4px;
}
</style>
