<template>
    <div class="stage-selector-wrapper py-3 pt-0 pb-0">
        <!-- Track = exact height of pills row so arrows align vertically in the middle -->
        <div class="stage-selector-track">
            <div
                ref="scrollContainerRef"
                class="stage-container"
                @wheel.prevent="handleWheelScroll"
                @scroll.passive="updateScrollAffordance"
            >
                <template v-for="(stage, index) in stages" :key="stage.id">
                    <div
                        class="stage-pill"
                        :class="{ active: index <= selectedStageIndex }"
                        :style="{
                            backgroundColor: index <= selectedStageIndex ? stage.color : 'transparent',
                            borderColor: index <= selectedStageIndex ? stage.color : '#E2E8F0',
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
    }
})

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
        
        if (isSuperAdmin.value) {
            // Admin: select first stage
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

const selectStage = (index) => {
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
    fetchStages()
    nextTick(() => {
        scrollSelectedIntoView()
        updateScrollAffordance()
    })
    window.addEventListener('resize', updateScrollAffordance)
})

onUnmounted(() => {
    stopEdgeScroll()
    window.removeEventListener('resize', updateScrollAffordance)
})
</script>

<style scoped>
/* باقي الستايلات كما هي */
</style>

<style scoped>
.stage-selector-wrapper {
    overflow: hidden;
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
    padding: 2px 10px;
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

@media (max-width: 768px) {
    .stage-pill {
        min-width: 104px;
        max-width: 138px;
        padding: 1px 8px;
    }

    .stage-text {
        font-size: 11px;
        font-weight: 500;
    }
}
</style>