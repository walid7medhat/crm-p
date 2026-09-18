<!-- components/Deals/ConvertLeadModal.vue -->
<template>
    <Teleport to="body">
        <div
            v-if="visible"
            class="convert-lead-overlay"
            role="dialog"
            aria-modal="true"
            aria-label="Convert Lead to Deal"
            @click.self="hide"
        >
            <div class="convert-lead-dialog">
                <div class="convert-lead-content">
                    <div class="convert-lead-header">
                        <div class="convert-lead-heading">
                            <div class="convert-lead-heading-icon" aria-hidden="true">
                                <iconify-icon icon="lucide:handshake" />
                            </div>
                            <div class="convert-lead-heading-text">
                                <h6 class="convert-lead-title">Convert Lead to Deal</h6>
                                <p class="convert-lead-subtitle">
                                    Choose the type of deal you want to create for this lead
                                </p>
                            </div>
                        </div>
                        <button type="button" class="convert-lead-close" aria-label="Close" @click="hide">
                            <iconify-icon icon="lucide:x" />
                        </button>
                    </div>

                    <div class="convert-lead-body">
                        <aside class="convert-lead-aside" aria-hidden="true">
                            <div class="aside-skyline"></div>
                            <div class="aside-content">
                                <span class="aside-eyebrow">Turn opportunities into deals</span>
                                <h6 class="aside-title">
                                    Create a new deal and
                                    <span class="aside-accent">move forward</span>.
                                </h6>
                                <p class="aside-copy">
                                    Select the deal type that best matches this lead’s interest.
                                </p>
                            </div>
                        </aside>

                        <div class="options-row">
                            <button
                                type="button"
                                class="deal-type-option"
                                :class="{ selected: form.deal_type === 'primary' }"
                                @click.stop="selectDealType('primary')"
                            >
                                <div class="option-icon">
                                    <iconify-icon icon="lucide:home" />
                                </div>
                                <div class="option-text">
                                    <span class="deal-type-label">Primary</span>
                                    <span class="deal-type-desc">
                                        Standard property deal with direct ownership from the developer or owner.
                                    </span>
                                </div>
                                <span
                                    v-if="form.deal_type === 'primary'"
                                    class="most-common-badge"
                                >
                                    <iconify-icon icon="lucide:star" />
                                    Most common
                                </span>
                                <span class="option-arrow" aria-hidden="true">
                                    <iconify-icon icon="lucide:arrow-right" />
                                </span>
                            </button>

                            <button
                                type="button"
                                class="deal-type-option"
                                :class="{ selected: form.deal_type === 'secondary' }"
                                @click.stop="selectDealType('secondary')"
                            >
                                <div class="option-icon">
                                    <iconify-icon icon="lucide:building-2" />
                                </div>
                                <div class="option-text">
                                    <span class="deal-type-label">Secondary</span>
                                    <span class="deal-type-desc">
                                        Resale property deal from an existing owner (not directly from developer).
                                    </span>
                                </div>
                                <span class="option-arrow" aria-hidden="true">
                                    <iconify-icon icon="lucide:arrow-right" />
                                </span>
                            </button>

                            <button
                                type="button"
                                class="deal-type-option"
                                :class="{ selected: form.deal_type === 'rental' }"
                                @click.stop="selectDealType('rental')"
                            >
                                <div class="option-icon">
                                    <iconify-icon icon="lucide:key-round" />
                                </div>
                                <div class="option-text">
                                    <span class="deal-type-label">Rental</span>
                                    <span class="deal-type-desc">
                                        Rental property deal for long-term or short-term leasing.
                                    </span>
                                </div>
                                <span class="option-arrow" aria-hidden="true">
                                    <iconify-icon icon="lucide:arrow-right" />
                                </span>
                            </button>
                        </div>
                    </div>

                    <div class="convert-lead-footer">
                        <p class="convert-lead-tip">
                            <iconify-icon icon="lucide:info" />
                            <span>You can always change the deal details later.</span>
                        </p>
                        <div class="convert-lead-actions">
                            <button type="button" class="btn-cancel" @click="hide">Cancel</button>
                            <button
                                type="button"
                                class="btn-add-deal"
                                @click.stop="submitConversion"
                                :disabled="!form.deal_type || loading"
                            >
                                <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
                                <span>Continue</span>
                                <iconify-icon icon="lucide:arrow-right" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue'
import api from '@/plugins/axios'
import Swal from 'sweetalert2'

const props = defineProps({
    leadId: {
        type: [Number, String],
        default: null
    },
    leadData: {
        type: Object,
        default: null
    }
})

const emit = defineEmits(['converted', 'closed'])

const visible = ref(false)
const loading = ref(false)

const form = ref({
    lead_id: props.leadId,
    deal_type: ''
})

watch(() => props.leadId, (newId) => {
    form.value.lead_id = newId
})

function selectDealType(type) {
    form.value.deal_type = type
}

function onEscapeKey(event) {
    if (event.key === 'Escape' && visible.value) {
        hide()
    }
}

const resolveLeadId = (explicitLeadId = null, explicitLeadData = null) => {
    const candidate =
        explicitLeadId
        ?? explicitLeadData?.id
        ?? explicitLeadData?.lead_id
        ?? explicitLeadData?.lead?.id
        ?? explicitLeadData?.lead?.lead_id
        ?? props.leadId
        ?? form.value.lead_id
        ?? props.leadData?.id
        ?? props.leadData?.lead_id
        ?? props.leadData?.lead?.id
        ?? props.leadData?.lead?.lead_id
        ?? null

    const numeric = Number(candidate)
    if (!Number.isNaN(numeric) && numeric > 0) return numeric
    return candidate
}

function cleanupBootstrapBackdrops() {
    document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove())
    document.body.classList.remove('modal-open')
    document.body.style.removeProperty('overflow')
    document.body.style.removeProperty('padding-right')
}

const show = (leadId = null, leadData = null) => {
    cleanupBootstrapBackdrops()
    form.value.lead_id = resolveLeadId(leadId, leadData)
    form.value.deal_type = 'primary'
    visible.value = true
    document.body.style.overflow = 'hidden'
}

const hide = () => {
    if (!visible.value) return
    visible.value = false
    document.body.style.overflow = ''
    emit('closed')
    form.value.deal_type = ''
}

onMounted(() => {
    document.addEventListener('keydown', onEscapeKey)
})

onUnmounted(() => {
    document.removeEventListener('keydown', onEscapeKey)
    document.body.style.overflow = ''
})

const submitConversion = async () => {
    if (!form.value.deal_type) {
        Swal.fire({
            icon: 'warning',
            title: 'Please select a deal type',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        })
        return
    }

    if (loading.value) return
    loading.value = true

    const resolvedLeadId = resolveLeadId()
    if (!resolvedLeadId) {
        Swal.fire({
            icon: 'error',
            title: 'Conversion failed',
            text: 'Lead ID is missing. Please reopen the convert modal and try again.',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500
        })
        loading.value = false
        return
    }

    const dealType = form.value.deal_type
    const leadDataSnapshot = props.leadData || null

    // Close immediately so Add Deal feels instant; API runs in the background.
    hide()

    try {
        const response = await api.post('/leads/convert/to-deal', {
            lead_id: resolvedLeadId,
            leadId: resolvedLeadId,
            id: resolvedLeadId,
            deal_type: dealType
        })

        if (response.data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Lead converted successfully!',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            })

            const createdDeal = {
                ...(response.data.data || {}),
                deal_type: response.data.data?.deal_type ?? dealType,
                _lead: response.data.lead || null,
                _sourceLead: leadDataSnapshot,
            }
            emit('converted', createdDeal)
        }
    } catch (error) {
        const alreadyConvertedId = error.response?.data?.deal_id
        // Treat "already converted" as success and open the existing deal.
        if (error.response?.status === 400 && alreadyConvertedId) {
            emit('converted', {
                id: alreadyConvertedId,
                deal_id: alreadyConvertedId,
                deal_type: dealType,
                _lead: {
                    id: resolvedLeadId,
                    converted_to_deal_id: alreadyConvertedId,
                },
                _sourceLead: leadDataSnapshot,
            })
            return
        }

        // Re-open so the user can retry without dragging the lead again.
        show(resolvedLeadId, leadDataSnapshot)
        form.value.deal_type = dealType

        const backendDebug = error?.response?.data?.debug?.payload
            ? ` | payload: ${JSON.stringify(error.response.data.debug.payload)}`
            : ''
        Swal.fire({
            icon: 'error',
            title: 'Conversion failed',
            text: (error.response?.data?.message || 'An error occurred') + backendDebug,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        })
    } finally {
        loading.value = false
    }
}

defineExpose({
    show,
    hide
})
</script>

<style scoped>
/* Exact design proportions: ~1020 × ~540 — light shell, purple accents */
.convert-lead-overlay {
    position: fixed;
    inset: 0;
    z-index: 101800;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background:
        radial-gradient(ellipse at 15% 10%, rgba(124, 58, 237, 0.12), transparent 42%),
        radial-gradient(ellipse at 90% 90%, rgba(168, 85, 247, 0.1), transparent 40%),
        rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    pointer-events: auto;
    animation: convertLeadFadeIn 0.2s ease;
}

@keyframes convertLeadFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.convert-lead-dialog {
    width: 1020px;
    max-width: 100%;
    height: 540px;
    max-height: calc(100vh - 40px);
    pointer-events: auto;
    animation: convertLeadSlideIn 0.22s cubic-bezier(0.22, 1, 0.36, 1);
}

@keyframes convertLeadSlideIn {
    from {
        opacity: 0;
        transform: translateY(12px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.convert-lead-content {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    border-radius: 28px;
    border: 1px solid #e9e5f5;
    overflow: hidden;
    background: #ffffff;
    box-shadow:
        0 28px 70px rgba(15, 23, 42, 0.18),
        0 0 0 1px rgba(124, 58, 237, 0.04);
    pointer-events: auto;
    color: #0f172a;
}

.convert-lead-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-shrink: 0;
    padding: 24px 28px 12px;
}

.convert-lead-heading {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    min-width: 0;
}

.convert-lead-heading-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: #fff;
    background: linear-gradient(135deg, #a855f7 0%, #7c3aed 100%);
    border: 1px solid rgba(124, 58, 237, 0.25);
    box-shadow: 0 8px 18px rgba(124, 58, 237, 0.28);
}

.convert-lead-heading-text {
    min-width: 0;
    padding-top: 1px;
}

.convert-lead-title {
    margin: 0;
    font-size: 22px !important;
    font-weight: 700;
    line-height: 1.2;
    color: #0f172a;
    letter-spacing: -0.02em;
}

.convert-lead-subtitle {
    margin: 5px 0 0;
    font-size: 13px;
    line-height: 1.4;
    color: #64748b;
}

.convert-lead-close {
    width: 34px;
    height: 34px;
    border: 1px solid #e2e8f0;
    border-radius: 50%;
    background: #f8fafc;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}

.convert-lead-close:hover {
    background: #f3e8ff;
    border-color: #d8b4fe;
    color: #7c3aed;
}

/* Sidebar ~32% | cards 68% — same height as design */
.convert-lead-body {
    flex: 1;
    min-height: 0;
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 16px;
    padding: 8px 28px 16px;
    pointer-events: auto;
}

.convert-lead-aside {
    position: relative;
    overflow: hidden;
    border-radius: 20px;
    height: 100%;
    min-height: 0;
    border: 1px solid rgba(167, 139, 250, 0.35);
    background:
        linear-gradient(180deg, #7c3aed 0%, #6d28d9 42%, #4c1d95 100%),
        radial-gradient(ellipse at 70% 20%, rgba(244, 114, 182, 0.35), transparent 50%);
}

.aside-skyline {
    position: absolute;
    inset: 0;
    pointer-events: none;
    background:
        linear-gradient(180deg, rgba(76, 29, 149, 0.1) 0%, rgba(76, 29, 149, 0.15) 40%, rgba(49, 16, 100, 0.45) 100%),
        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 320 360' fill='none'%3E%3Cpath d='M18 360V230h16v130H18zm22 0V200h12v160H40zm20 0V245h10v115H60zm18 0V175h9v185H78zm16 0V210h14v150H94zm22 0V120h11v240h-11zm20 0V190h16v170h-16zm24 0V145h9v215h-9zm17 0V220h12v140h-12zm20 0V165h10v195h-10zm18 0V110h8v250h-8zm16 0V200h13v160h-13zm22 0V150h11v210h-11zm19 0V230h14v130h-14z' fill='%23ffffff' fill-opacity='0.22'/%3E%3Cpath d='M148 360V72l10-12 10 12v288h-20z' fill='%23f5d0fe' fill-opacity='0.45'/%3E%3Cpath d='M20 150c45-40 95-50 150-22s100 12 150-28' stroke='%23fce7f3' stroke-opacity='0.65' stroke-width='1.6' fill='none'/%3E%3Cpath d='M8 200c48-30 100-22 150 4s110 6 160-32' stroke='%23e9d5ff' stroke-opacity='0.5' stroke-width='1.1' fill='none'/%3E%3Ccircle cx='250' cy='80' r='28' fill='%23ffffff' fill-opacity='0.14'/%3E%3C/svg%3E")
        center bottom / cover no-repeat;
    opacity: 0.95;
}

.aside-content {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    height: 100%;
    padding: 22px 20px;
}

.aside-eyebrow {
    font-size: 10px;
    font-weight: 650;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #f5d0fe;
    margin-bottom: 12px;
}

.aside-title {
    margin: 0;
    font-size: 28px;
    line-height: 1.18;
    font-weight: 700;
    color: #fff;
    letter-spacing: -0.03em;
}

.aside-accent {
    background: linear-gradient(90deg, #fce7f3, #f0abfc 40%, #e9d5ff);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.aside-copy {
    margin: 14px 0 0;
    font-size: 12.5px;
    line-height: 1.45;
    color: rgba(255, 255, 255, 0.82);
    max-width: 26ch;
}

.options-row {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    height: 100%;
    min-height: 0;
}

.deal-type-option {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
    height: 100%;
    min-height: 0;
    padding: 18px 16px 48px;
    border-radius: 18px;
    border: 1px solid #e8e4f2;
    background: #faf9fc;
    color: inherit;
    cursor: pointer;
    text-align: left;
    appearance: none;
    -webkit-appearance: none;
    font: inherit;
    transition: border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
    pointer-events: auto;
    touch-action: manipulation;
    -webkit-tap-highlight-color: transparent;
}

.deal-type-option:not(.selected):hover {
    border-color: #d8b4fe;
    background: #f8f5ff;
    box-shadow: 0 8px 20px rgba(124, 58, 237, 0.08);
}

.deal-type-option.selected {
    border-color: #a855f7;
    background: linear-gradient(180deg, #faf5ff 0%, #f3e8ff 100%);
    box-shadow:
        0 0 0 1px rgba(168, 85, 247, 0.35),
        0 12px 28px rgba(124, 58, 237, 0.18);
}

.option-icon {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: #7c3aed;
    background: #f3e8ff;
    border: 1px solid #e9d5ff;
    pointer-events: none;
    flex-shrink: 0;
}

.deal-type-option.selected .option-icon {
    color: #fff;
    background: linear-gradient(135deg, #a855f7, #7c3aed);
    border-color: transparent;
    box-shadow: 0 6px 14px rgba(124, 58, 237, 0.3);
}

.option-text {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 0;
    pointer-events: none;
}

.deal-type-label {
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
}

.deal-type-desc {
    font-size: 12px;
    line-height: 1.45;
    color: #64748b;
}

.most-common-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: auto;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    color: #6b21a8;
    background: #f3e8ff;
    border: 1px solid #e9d5ff;
    pointer-events: none;
}

.most-common-badge iconify-icon {
    font-size: 11px;
    color: #a855f7;
}

.option-arrow {
    position: absolute;
    right: 14px;
    bottom: 14px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    color: #94a3b8;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    pointer-events: none;
    transition: background 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, color 0.2s ease;
}

.deal-type-option.selected .option-arrow {
    color: #fff;
    background: linear-gradient(135deg, #c026d3, #7c3aed);
    border-color: transparent;
    box-shadow: 0 8px 16px rgba(124, 58, 237, 0.35);
}

.convert-lead-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-shrink: 0;
    padding: 14px 28px 22px;
    border-top: 1px solid #f1f5f9;
    pointer-events: auto;
}

.convert-lead-tip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    font-size: 12.5px;
    color: #64748b;
}

.convert-lead-tip iconify-icon {
    font-size: 15px;
    color: #a855f7;
    flex-shrink: 0;
}

.convert-lead-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.btn-cancel,
.btn-add-deal {
    min-width: 108px;
    height: 42px;
    border-radius: 12px;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    font-size: 13.5px;
    font-weight: 600;
    padding: 0 18px;
    cursor: pointer;
    transition: all 0.2s ease;
    pointer-events: auto;
}

.btn-cancel {
    background: #f8fafc;
    color: #475569;
    border: 1px solid #e2e8f0;
}

.btn-cancel:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.btn-add-deal {
    background: linear-gradient(135deg, #d946ef 0%, #a855f7 45%, #7c3aed 100%);
    color: #fff;
    box-shadow: 0 10px 24px rgba(168, 85, 247, 0.32);
}

.btn-add-deal:hover:not(:disabled) {
    filter: brightness(1.06);
}

.btn-add-deal:disabled {
    opacity: 0.45;
    cursor: not-allowed;
    box-shadow: none;
}

.btn-add-deal iconify-icon {
    font-size: 15px;
}

/* Large tablets / small laptops */
@media (max-width: 1060px) {
    .convert-lead-dialog {
        width: 920px;
        height: 500px;
    }

    .convert-lead-body {
        grid-template-columns: 260px 1fr;
        padding-left: 22px;
        padding-right: 22px;
    }

    .aside-title {
        font-size: 24px;
    }
}

/* Tablet: stack sidebar above cards, keep 3 cards in a row */
@media (max-width: 860px) {
    .convert-lead-dialog {
        width: min(680px, 100%);
        height: auto;
        max-height: calc(100vh - 32px);
    }

    .convert-lead-content {
        height: auto;
        max-height: calc(100vh - 32px);
        overflow: auto;
    }

    .convert-lead-body {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .convert-lead-aside {
        height: 150px;
    }

    .aside-title {
        font-size: 20px;
    }

    .aside-copy {
        display: none;
    }

    .options-row {
        height: auto;
        min-height: 200px;
    }

    .deal-type-option {
        height: 200px;
    }
}

/* Mobile */
@media (max-width: 640px) {
    .convert-lead-overlay {
        align-items: flex-end;
        padding: 0;
    }

    .convert-lead-dialog {
        width: 100%;
        height: auto;
        max-height: 92vh;
    }

    .convert-lead-content {
        border-radius: 22px 22px 0 0;
        max-height: 92vh;
    }

    .convert-lead-header,
    .convert-lead-body,
    .convert-lead-footer {
        padding-left: 16px;
        padding-right: 16px;
    }

    .convert-lead-header {
        padding-top: 16px;
        padding-bottom: 8px;
    }

    .convert-lead-title {
        font-size: 18px !important;
    }

    .convert-lead-aside {
        height: 130px;
    }

    .aside-title {
        font-size: 18px;
    }

    .options-row {
        grid-template-columns: 1fr;
        gap: 8px;
        min-height: 0;
    }

    .deal-type-option {
        height: auto;
        min-height: 0;
        flex-direction: row;
        align-items: flex-start;
        gap: 12px;
        padding: 14px 48px 14px 14px;
    }

    .most-common-badge {
        display: none;
    }

    .option-arrow {
        top: 50%;
        bottom: auto;
        transform: translateY(-50%);
    }

    .convert-lead-footer {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
        padding-bottom: 18px;
    }

    .convert-lead-actions {
        width: 100%;
    }

    .btn-cancel,
    .btn-add-deal {
        flex: 1;
        min-width: 0;
    }
}
</style>
