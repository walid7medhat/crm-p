<template>
    <div class="stage-selector-wrapper py-3 pt-0 pb-0" :class="{ 'stage-selector-disabled': disabled, 'stage-selector--panel': appearance === 'panel' }" :title="disabled ? 'Stage cannot be changed from here' : undefined">
        <div v-if="appearance === 'panel' && isMobile" class="stage-current-bar">
            <div
                class="stage-current"
                :style="currentStageStyle"
            >
                <span class="stage-text">{{ currentStage?.name || 'Stage' }}</span>
            </div>
            <button
                v-if="!disabled"
                type="button"
                class="stage-change-btn"
                @click="openStagePicker"
            >
                Change stage
                <iconify-icon icon="lucide:chevron-right" aria-hidden="true" />
            </button>
        </div>
        <!-- Track = exact height of pills row so arrows align vertically in the middle -->
        <div v-else class="stage-selector-track">
            <div
                ref="scrollContainerRef"
                class="stage-container"
                @wheel.prevent="handleWheelScroll"
                @scroll.passive="updateScrollAffordance"
            >
                <template v-for="(stage, index) in stages" :key="stage.id">
                    <div
                        class="stage-pill"
                        :class="{ active: index <= selectedStageIndex, 'is-current': index === selectedStageIndex, 'stage-pill--blocked': isStageBlocked(stage) }"
                        :title="isStageBlocked(stage) ? 'Leads cannot be created in Lead Pool' : undefined"
                        :style="{
                            backgroundColor: index <= selectedStageIndex ? stage.color : (appearance === 'panel' ? '#ffffff' : 'transparent'),
                            borderColor: index <= selectedStageIndex ? stage.color : (appearance === 'panel' ? '#eadff0' : '#E2E8F0'),
                            zIndex: stages.length - index,
                        }"
                        @click="selectStage(index)"
                    >
                        <span class="stage-text" :title="stage.name">
                            {{ stage.name }}
                        </span>
                    </div>
                </template>
            </div>
            <button
                v-show="canScrollLeft"
                type="button"
                class="scroll-hover-edge scroll-hover-edge--left"
                aria-label="Scroll stages left"
                @mouseenter="startEdgeScroll('left')"
                @mouseleave="stopEdgeScroll"
                @click.prevent.stop="scrollArrowClick('left')"
            >
                <span class="scroll-edge-inner">
                    <iconify-icon icon="lucide:chevron-left" class="scroll-edge-icon" />
                </span>
            </button>
            <button
                v-show="canScrollRight"
                type="button"
                class="scroll-hover-edge scroll-hover-edge--right"
                aria-label="Scroll stages right"
                @mouseenter="startEdgeScroll('right')"
                @mouseleave="stopEdgeScroll"
                @click.prevent.stop="scrollArrowClick('right')"
            >
                <span class="scroll-edge-inner">
                    <iconify-icon icon="lucide:chevron-right" class="scroll-edge-icon" />
                </span>
            </button>
        </div>
    </div>
    <Teleport to="body">
        <div v-if="pickerOpen" class="stage-picker-overlay" @click.self="closeStagePicker">
            <div class="stage-picker" role="dialog" aria-modal="true" aria-label="Change stage">
                <div class="stage-picker__top">
                    <div class="stage-picker__head">
                        <span class="stage-picker__mark" aria-hidden="true"></span>
                        <p class="stage-picker__title">Change stage</p>
                    </div>
                    <button type="button" class="stage-picker__close" aria-label="Close" @click="closeStagePicker">
                        <iconify-icon icon="lucide:x" />
                    </button>
                </div>
                <p class="stage-picker__hint">Tap a stage to move this lead.</p>
                <div
                    v-if="currentStage"
                    class="stage-picker__now"
                    :style="{ backgroundColor: currentStage.color }"
                >
                    <span class="stage-picker__now-label">Active now</span>
                    <span class="stage-picker__now-name">{{ currentStage.name }}</span>
                </div>
                <div class="stage-picker__list">
                    <button
                        v-for="(stage, index) in stages"
                        :key="stage.id"
                        type="button"
                        class="stage-picker__option"
                        :class="{ 'is-current': index === selectedStageIndex, 'is-blocked': isStageBlocked(stage) }"
                        :disabled="isStageBlocked(stage)"
                        @click="pickStage(index)"
                    >
                        <span class="stage-picker__swatch" :style="{ backgroundColor: stage.color }" />
                        <span class="stage-picker__name">{{ stage.name }}</span>
                        <span v-if="index === selectedStageIndex" class="stage-picker__badge">Current</span>
                        <iconify-icon v-else icon="lucide:arrow-right" class="stage-picker__go" aria-hidden="true" />
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import api from '@/plugins/axios'

const props = defineProps({
    modelValue: {
        type: Number,
        default: null
    },
       requireValidation: {
        type: Boolean,
        default: true
    },
    disabled: {
        type: Boolean,
        default: false
    },
    /** panel: compact stage rail used on the lead view popup. */
    appearance: {
        type: String,
        default: 'default'
    },
    // Create form: the Lead Pool stage can't be picked (LeadController::store rejects it).
    disableLeadPool: {
        type: Boolean,
        default: false
    }
})

// Same rule as LeadController::isLeadPoolStage.
const isLeadPoolStage = (stage) => {
    const name = String(stage?.name || '').trim().toLowerCase()
    return name === 'lead pool' || name === 'leadpool' || Number(stage?.order) === 9
}

const isStageBlocked = (stage) => props.disableLeadPool && isLeadPoolStage(stage)

const emit = defineEmits(['update:modelValue', 'stage-change-request'])

const stages = ref([])
const scrollContainerRef = ref(null)
const canScrollLeft = ref(false)
const canScrollRight = ref(false)
let edgeScrollRafId = null
/** Hover edge: slow continuous scroll */
const EDGE_SCROLL_SLOW_PX = 4

const colors = ['#7BD3EA', '#E3DA32', '#F2C934', '#8EC82F', '#00A74C']

const getUserFromStorage = () => {
    try {
        const userData = localStorage.getItem('user')
        return userData ? JSON.parse(userData) : null
    } catch (error) {
        console.error('Error getting user from storage:', error)
        return null
    }
}

const user = ref(getUserFromStorage())

// Check if user is admin or super_admin
const isSuperAdmin = computed(() => {
    if (!user.value) return false
    return user.value.roles?.includes('super_admin')
})

// Listing-team sales create their own leads straight into the first stage (New Lead);
// the auto-assign engine leaves those leads with them.
const isListingTeamSales = computed(() => {
    if (!user.value) return false
    return !!user.value.is_listing_team && !!user.value.roles?.includes('sales')
})

function getColorByIndex(index) {
    return colors[index % colors.length]
}

const updateScrollAffordance = () => {
    const el = scrollContainerRef.value
    if (!el) {
        canScrollLeft.value = false
        canScrollRight.value = false
        return
    }
    const { scrollLeft, scrollWidth, clientWidth } = el
    const max = Math.max(0, scrollWidth - clientWidth)
    const eps = 2
    canScrollLeft.value = scrollLeft > eps
    canScrollRight.value = max > eps && scrollLeft < max - eps
}

const stopEdgeScroll = () => {
    if (edgeScrollRafId != null) {
        cancelAnimationFrame(edgeScrollRafId)
        edgeScrollRafId = null
    }
}

const scrollArrowClick = (direction) => {
    const el = scrollContainerRef.value
    if (!el) return
    const jump = Math.max(160, Math.round(el.clientWidth * 0.4))
    el.scrollBy({
        left: direction === 'right' ? jump : -jump,
        behavior: 'auto',
    })
    nextTick(updateScrollAffordance)
}

const startEdgeScroll = (direction) => {
    stopEdgeScroll()
    const step = () => {
        const el = scrollContainerRef.value
        if (!el) {
            stopEdgeScroll()
            return
        }
        const max = el.scrollWidth - el.clientWidth
        if (direction === 'right') {
            if (el.scrollLeft >= max - 0.5) {
                stopEdgeScroll()
                updateScrollAffordance()
                return
            }
            el.scrollBy({ left: EDGE_SCROLL_SLOW_PX, behavior: 'auto' })
        } else {
            if (el.scrollLeft <= 0.5) {
                stopEdgeScroll()
                updateScrollAffordance()
                return
            }
            el.scrollBy({ left: -EDGE_SCROLL_SLOW_PX, behavior: 'auto' })
        }
        updateScrollAffordance()
        edgeScrollRafId = requestAnimationFrame(step)
    }
    edgeScrollRafId = requestAnimationFrame(step)
}

const scrollSelectedIntoView = () => {
    const el = scrollContainerRef.value
    if (!el || selectedStageIndex.value < 0) return
    const pills = el.querySelectorAll('.stage-pill')
    const selected = pills[selectedStageIndex.value]
    if (selected?.scrollIntoView) {
        selected.scrollIntoView({ behavior: 'auto', inline: 'center', block: 'nearest' })
    }
    nextTick(updateScrollAffordance)
}

// Function to auto-select stage based on user role
const autoSelectStage = () => {
    console.log('=== autoSelectStage called ===')
    console.log('stages.length:', stages.value.length)
    console.log('props.modelValue:', props.modelValue)
    console.log('currentStageId.value:', currentStageId.value)
    
    if (!stages.value.length) {
        console.log('No stages available, returning')
        return false
    }
    
    // IMPORTANT: Only auto-select if there's NO modelValue from parent
    // AND currentStageId is not set
    if (props.modelValue === null && !currentStageId.value) {
        let stageToSelectId
        
        if (isSuperAdmin.value || isListingTeamSales.value) {
            // Super admin / listing-team sales: select first stage (New Lead)
            stageToSelectId = stages.value[0].id
            console.log('Admin auto-selecting first stage:', stageToSelectId, stages.value[0].name)
        } else {
            // Regular user: select second stage if exists, otherwise first
            if (stages.value.length > 1) {
                stageToSelectId = stages.value[1].id
                console.log('Regular user auto-selecting second stage:', stageToSelectId, stages.value[1].name)
            } else {
                stageToSelectId = stages.value[0].id
                console.log('Regular user auto-selecting first stage:', stageToSelectId, stages.value[0].name)
            }
        }
        
        currentStageId.value = stageToSelectId
        emit('update:modelValue', stageToSelectId)
        console.log('Auto-selected stage, emitted value:', stageToSelectId)
        
        nextTick(() => {
            scrollSelectedIntoView()
        })
        return true
    } else {
        console.log('Skipping auto-select because there is already a value:', props.modelValue || currentStageId.value)
        return false
    }
}

const fetchStages = async () => {
    try {
        console.log('Fetching stages...')
        const response = await api.get('/stages')
        let stagesData = []

        if (response.data?.data?.data) {
            stagesData = response.data.data.data
        } else if (response.data?.data && Array.isArray(response.data.data)) {
            stagesData = response.data.data
        } else if (Array.isArray(response.data)) {
            stagesData = response.data
        }

        stages.value = stagesData.map((stage, index) => ({
            id: stage.id,
            name: stage.name,
            color: stage.color || getColorByIndex(index),
            order: stage.order || index
        }))

        console.log('Stages loaded successfully:', stages.value)
        
        // Auto-select after stages are loaded ONLY if no value is set
        autoSelectStage()

        nextTick(() => {
            updateScrollAffordance()
        })

    } catch (error) {
        console.error('Error fetching stages:', error)
        console.error('Error response:', error.response?.data)
    }
}

const currentStageId = ref(props.modelValue)

// Watch for changes in props.modelValue
watch(() => props.modelValue, (newVal, oldVal) => {
    console.log('modelValue changed from', oldVal, 'to', newVal)
    currentStageId.value = newVal
    
    // Only auto-select if modelValue becomes null AND there's no current value
    // AND this is not a case where we want to preserve the value
    if (newVal === null && stages.value.length > 0 && !currentStageId.value) {
        console.log('modelValue is null and no stage selected, checking auto-select...')
        autoSelectStage()
    }
})

// Watch for changes in stages array
watch(stages, (newStages) => {
    console.log('Stages array changed, length:', newStages.length)
    // Only auto-select if there's no stage selected and no value from parent
    if (newStages.length > 0 && !currentStageId.value && props.modelValue === null) {
        console.log('Stages loaded, no stage selected, and no parent value, auto-selecting...')
        autoSelectStage()
    }
}, { deep: true })

const selectedStageIndex = computed(() => {
    if (!currentStageId.value || stages.value.length === 0) return -1
    const index = stages.value.findIndex(stage => stage.id === currentStageId.value)
    console.log('Selected stage index:', index, 'for stage ID:', currentStageId.value)
    return index >= 0 ? index : -1
})

const currentStage = computed(() => {
    if (selectedStageIndex.value < 0) return null
    return stages.value[selectedStageIndex.value] || null
})

function textOnStageColor(hex) {
    const raw = String(hex || '').trim().replace('#', '')
    if (!/^[0-9a-fA-F]{6}$/.test(raw)) return '#ffffff'
    const r = parseInt(raw.slice(0, 2), 16)
    const g = parseInt(raw.slice(2, 4), 16)
    const b = parseInt(raw.slice(4, 6), 16)
    const yiq = (r * 299 + g * 587 + b * 114) / 1000
    return yiq >= 170 ? '#1c1424' : '#ffffff'
}

const currentStageStyle = computed(() => {
    const color = currentStage.value?.color
    if (!color) return undefined
    return {
        '--stage-color': color,
        backgroundColor: color,
        borderColor: color,
        color: textOnStageColor(color),
    }
})

const pickerOpen = ref(false)
const isMobile = ref(typeof window !== 'undefined' && window.matchMedia('(max-width: 768px)').matches)
let mobileQuery = null

const syncMobile = () => {
    isMobile.value = window.matchMedia('(max-width: 768px)').matches
}

const openStagePicker = () => {
    if (props.disabled || !isMobile.value) return
    pickerOpen.value = true
}

const closeStagePicker = () => {
    pickerOpen.value = false
}

watch(pickerOpen, (open) => {
    document.body.classList.toggle('stage-picker-open', open)
})

watch(isMobile, (mobile) => {
    if (!mobile) closeStagePicker()
})

const pickStage = (index) => {
    if (index === selectedStageIndex.value) {
        closeStagePicker()
        return
    }
    selectStage(index)
    closeStagePicker()
}

const onPickerKeydown = (event) => {
    if (event.key === 'Escape' && pickerOpen.value) closeStagePicker()
}

const selectStage = (index) => {
    if (props.disabled) return
    if (stages.value[index] && isStageBlocked(stages.value[index])) return
    if (stages.value[index]) {
        const selectedStage = stages.value[index]
        const newStageId = selectedStage.id
        
        console.log('User clicked stage:', selectedStage.name, 'ID:', newStageId)
        
        // If validation is required, emit a custom event to let parent handle validation
        if (props.requireValidation) {
            // Emit a request to change stage with validation
            emit('stage-change-request', {
                stageId: newStageId,
                stageName: selectedStage.name,
                stageOrder: selectedStage.order
            })
        } else {
            // Direct update without validation
            currentStageId.value = newStageId
            emit('update:modelValue', newStageId)
            console.log('Direct stage update (no validation):', selectedStage.name)
        }
    }
}

const handleWheelScroll = (event) => {
    const el = scrollContainerRef.value
    if (!el) return
    const delta = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.deltaY
    el.scrollLeft += delta
}

watch(selectedStageIndex, () => {
    nextTick(() => {
        scrollSelectedIntoView()
        updateScrollAffordance()
    })
})

watch(() => stages.value.length, () => {
    nextTick(updateScrollAffordance)
})

onMounted(() => {
    console.log('StageSelector mounted')
    mobileQuery = window.matchMedia('(max-width: 768px)')
    isMobile.value = mobileQuery.matches
    mobileQuery.addEventListener('change', syncMobile)
    window.addEventListener('keydown', onPickerKeydown)
    fetchStages()
    nextTick(() => {
        scrollSelectedIntoView()
        updateScrollAffordance()
    })
    window.addEventListener('resize', onViewportChange)
})

const onViewportChange = () => {
    syncMobile()
    updateScrollAffordance()
}

onUnmounted(() => {
    stopEdgeScroll()
    mobileQuery?.removeEventListener('change', syncMobile)
    document.body.classList.remove('stage-picker-open')
    window.removeEventListener('resize', onViewportChange)
    window.removeEventListener('keydown', onPickerKeydown)
})
</script>

<style scoped>
/* باقي الستايلات كما هي */
</style>

<style scoped>
.stage-selector-wrapper {
    overflow-x: clip;
    overflow-y: visible;
}

.stage-selector-disabled .stage-pill {
    cursor: not-allowed;
    opacity: 0.6;
    pointer-events: none;
}

.stage-pill.stage-pill--blocked {
    cursor: not-allowed;
    opacity: 0.45;
}

.stage-selector-track {
    position: relative;
    isolation: isolate;
}

.stage-container {
    position: relative;
    z-index: 0;
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 4px 4px 8px;
    box-shadow: 1px 1px 5px 5px #00000005;
    width: 100%;
    min-width: 0;
    overflow-x: auto;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
    scroll-behavior: auto;
}

/* Full row height + flex center = vertically aligned with stage pills */
.scroll-hover-edge {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 48px;
    z-index: 40;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    margin: 0;
    border: none;
    cursor: pointer;
    background: transparent;
    color: #334155;
}

.scroll-hover-edge:hover .scroll-edge-inner {
    border-color: rgba(250, 163, 0, 0.55);
    background: rgba(255, 255, 255, 0.5);
    box-shadow: 0 4px 18px rgba(1, 6, 44, 0.08);
    color: #0B0736;
}

.scroll-hover-edge:active .scroll-edge-inner {
    transform: scale(0.94);
}

.scroll-hover-edge--left {
    left: 0;
    justify-content: flex-start;
    padding-left: 2px;
}

.scroll-hover-edge--right {
    right: 0;
    justify-content: flex-end;
    padding-right: 2px;
}

/* Glass pill: transparent fill + crisp border */
.scroll-edge-inner {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1.5px solid rgba(226, 232, 240, 0.95);
    background: rgba(255, 255, 255, 0.22);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    box-shadow: 0 2px 14px rgba(15, 23, 42, 0.06);
    transition: border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease, color 0.2s ease, transform 0.15s ease;
}

.scroll-edge-icon {
    font-size: 20px;
    flex-shrink: 0;
}

.stage-container::-webkit-scrollbar-button {
    display: none;
    width: 0;
    height: 0;
    background: transparent;
}

.stage-container::-webkit-scrollbar {
    height: 6px;
}

.stage-container::-webkit-scrollbar-track {
    background: transparent;
    border-radius: 999px;
    display: none;
}

.stage-container::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 999px;
}

.stage-container:hover::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.stage-pill {
    display: flex;
    align-items: center;
    min-width: 140px;
    max-width: 170px;
    min-height: 32px;
    padding: 4px 10px;
    /*border-radius: 30px;*/
    cursor: pointer;
    transition: background-color 0.1s ease, border-color 0.1s ease, color 0.1s ease;
    position: relative;
    overflow: hidden;
    /*border: 1px solid transparent;*/
    box-shadow: inset -1px 0 0 rgba(255, 255, 255, 0.55);
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
    border-bottom-right-radius: 12px;
    clip-path: polygon(0 0, calc(100% - 7px) 0, 100% 50%, calc(100% - 7px) 100%, 0 100%);
}

.stage-pill:not(.active) {
    color: #94A3B8;
}

.stage-text {
    font-family: Montserrat;
    font-weight: 400;
    font-size: 13px;
    color: #979797;
    display: block;
    width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.stage-pill.active .stage-text {
    color: #0B0736;
    font-weight: 400;
}

.stage-selector--panel {
    padding: 0 !important;
    background: transparent;
}

.stage-current-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    padding: 6px;
    border: 1px solid #efe4f3;
    border-radius: 14px;
    background: #faf6fb;
}

.stage-current {
    display: inline-flex;
    align-items: center;
    min-width: 0;
    max-width: 52%;
    min-height: 32px;
    padding: 5px 14px;
    border-radius: 999px;
    border: 1px solid transparent;
    background-color: var(--stage-color) !important;
    border-color: var(--stage-color) !important;
    box-shadow: 0 0 0 2px #fff, 0 0 0 3px var(--stage-color, rgba(var(--color-primary-rgb), 0.55));
}

.stage-current .stage-text {
    width: auto;
    min-width: 0;
    font-size: 11px;
    font-weight: 700;
    color: inherit !important;
    -webkit-text-fill-color: currentColor !important;
    text-align: center;
}

.stage-change-btn {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    gap: 2px;
    flex: 0 0 auto;
    height: 32px;
    padding: 0 12px;
    border: 1px solid #eadff0;
    border-radius: 999px;
    background: #fff;
    color: var(--color-primary);
    font-family: Montserrat, system-ui, sans-serif;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
}

.stage-change-btn:active {
    background: rgba(var(--color-primary-rgb), 0.08);
}

:global(body.stage-picker-open) {
    overflow: hidden;
}

.stage-picker-overlay {
    position: fixed;
    inset: 0;
    z-index: 20000;
    display: flex;
    background: #f6f1f8;
}

.stage-picker {
    width: 100%;
    height: 100%;
    min-height: 100%;
    display: flex;
    flex-direction: column;
    padding: 0;
    border-radius: 0;
    background:
        radial-gradient(90% 28% at 100% 0%, rgba(var(--color-primary-rgb), 0.18), transparent 60%),
        linear-gradient(180deg, #fff 0%, #f8f3fb 22%, #f4eef8 100%);
    font-family: Montserrat, system-ui, sans-serif;
    animation: stage-page-in 0.28s ease;
}

@keyframes stage-page-in {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: none; }
}

.stage-picker__top {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: calc(16px + env(safe-area-inset-top)) 16px 0;
}

.stage-picker__head {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.stage-picker__mark {
    width: 7px;
    height: 7px;
    flex: 0 0 auto;
    border-radius: 2px;
    transform: rotate(45deg);
    background: var(--gradient-primary, var(--color-primary));
}

.stage-picker__title {
    margin: 0;
    color: var(--color-primary);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.stage-picker__close {
    margin-left: auto;
    display: grid;
    place-items: center;
    width: 32px;
    height: 32px;
    border: 0;
    border-radius: 999px;
    background: #fff;
    color: #3b3558;
    box-shadow: 0 4px 14px rgba(28, 20, 36, 0.08);
    cursor: pointer;
}

.stage-picker__hint {
    margin: 6px 16px 0;
    color: #6b6580;
    font-size: 11px;
    line-height: 1.35;
}

.stage-picker__now {
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin: 14px 16px 0;
    padding: 12px 14px;
    border-radius: 16px;
    box-shadow: 0 8px 20px rgba(28, 20, 36, 0.08);
}

.stage-picker__now-label {
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #3b3558;
}

.stage-picker__now-name {
    font-size: 13px;
    font-weight: 700;
    color: #1c1424;
}

.stage-picker__list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-height: 0;
    margin-top: 14px;
    padding: 0 16px calc(18px + env(safe-area-inset-bottom));
    overflow: auto;
}

.stage-picker__option {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    min-height: 44px;
    padding: 8px 12px;
    border: 1px solid #efe8f4;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.92);
    text-align: left;
    cursor: pointer;
    box-shadow: 0 6px 16px rgba(28, 20, 36, 0.04);
}

.stage-picker__option:hover {
    border-color: rgba(var(--color-primary-rgb), 0.45);
    background: #fbf8fc;
}

.stage-picker__option.is-current {
    border-color: var(--color-primary);
    background: #faf5ff;
}

.stage-picker__option.is-blocked {
    opacity: 0.45;
    cursor: not-allowed;
}

.stage-picker__swatch {
    width: 12px;
    height: 12px;
    flex: 0 0 auto;
    border-radius: 999px;
}

.stage-picker__name {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #1c1424;
    font-size: 12px;
    font-weight: 700;
}

.stage-picker__badge {
    margin-left: auto;
    padding: 2px 6px;
    border-radius: 999px;
    background: var(--gradient-primary);
    color: #fff;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.02em;
}

.stage-picker__go {
    margin-left: auto;
    color: #b7a8c4;
}

.stage-selector--panel .stage-container {
    gap: 6px;
    padding: 6px;
    border: 1px solid #efe4f3;
    border-radius: 14px;
    background: #faf6fb;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
}

.stage-selector--panel .stage-pill {
    min-width: 112px;
    max-width: 168px;
    min-height: 32px;
    padding: 5px 14px;
    border-radius: 999px;
    clip-path: none;
    border: 1px solid #eadff0;
    box-shadow: none;
}

.stage-selector--panel .stage-pill:not(.active) {
    background: #fff;
}

.stage-selector--panel .stage-pill.active {
    border-color: transparent;
    box-shadow: 0 1px 2px rgba(28, 25, 38, 0.06);
}

.stage-selector--panel .stage-pill.is-current {
    box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--color-primary);
}

.stage-selector--panel .stage-text {
    font-size: 11px;
    font-weight: 600;
    text-align: center;
    color: #5c5366;
}

.stage-selector--panel .stage-pill.active .stage-text {
    color: #1c1424;
    font-weight: 700;
}

.stage-selector--panel .scroll-edge-inner {
    width: 28px;
    height: 28px;
    border-color: #eadff0;
    background: #fff;
    color: var(--color-primary);
    box-shadow: 0 4px 12px rgba(var(--color-primary-rgb), 0.12);
}

.stage-selector--panel .scroll-hover-edge:hover .scroll-edge-inner {
    border-color: var(--color-primary);
    background: #fff;
    color: var(--color-primary);
}

@media (max-width: 768px) {
    .stage-pill {
        min-width: 104px;
        max-width: 138px;
        min-height: 34px;
        padding: 4px 8px;
    }

    .stage-text {
        font-size: 11px;
        font-weight: 500;
    }
}
</style>