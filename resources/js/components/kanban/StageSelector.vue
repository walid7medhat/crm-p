<template>
    <!-- Stage Selector -->
    <div class="stage-selector-wrapper py-3 pb-0">
        <div class="stage-container">
            <template v-for="(stage, index) in stages" :key="stage.id">
                <div 
                    class="stage-pill"
                    :class="{ 'active': index <= selectedStageIndex }"
                    :style="{ 
                        backgroundColor: index <= selectedStageIndex ? stage.color : 'transparent',
                        borderColor: index <= selectedStageIndex ? stage.color : '#E2E8F0',
                        zIndex: stages.length - index,
                        marginLeft: index > 0 ? '-26px' : '0',
                    }"
                    @click="selectStage(index)"
                >
                    <!-- Separator at the end -->
                    <div v-if="index > 0" class="stage-separator">
                        <iconify-icon 
                            icon="lucide:chevrons-right" 
                            class="separator-icon" 
                            :class="{ 'active-separator': index <= selectedStageIndex }"
                        ></iconify-icon>
                    </div>
                    <div class="stage-circle">
                        <div class="stage-dot" :style="{ backgroundColor: stage.color }"></div>
                    </div>
                    <span class="stage-text" :class="{ 'active-text': index <= selectedStageIndex }">
                        {{ stage.name }}
                    </span>

                
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import api from '@/plugins/axios'

const props = defineProps({
    modelValue: {
        type: Number,
        default: null
    }
})

const emit = defineEmits(['update:modelValue'])

const stages = ref([])
const isFetching = ref(false)
const abortController = ref(null)
const fetchDebounceTimer = ref(null)
const hasMounted = ref(false)
const mountTime = ref(null)

// Global request tracker to prevent duplicate calls across component instances
if (!window.__fetchStagesTracker) {
    window.__fetchStagesTracker = {
        isFetching: false,
        abortController: null,
        lastFetchTime: 0,
        mountTimes: []
    }
}

// Same color logic from leads.vue
const colors = ['#7BD3EA', '#E3DA32', '#F2C934', '#8EC82F', '#00A74C']

function getColorByIndex(index) {
    return colors[index % colors.length]
}

const fetchStages = async (immediate = false) => {
    // Clear any pending debounce
    if (fetchDebounceTimer.value) {
        clearTimeout(fetchDebounceTimer.value)
        fetchDebounceTimer.value = null
    }
    
    // If not immediate, debounce rapid calls
    if (!immediate) {
        return new Promise((resolve) => {
            fetchDebounceTimer.value = setTimeout(async () => {
                await executeFetchStages()
                resolve()
            }, 300) // 300ms debounce
        })
    }
    
    return executeFetchStages()
}

const executeFetchStages = async () => {
    const tracker = window.__fetchStagesTracker
    const now = Date.now()
    
    // Prevent concurrent requests (both local and global)
    if (isFetching.value || tracker.isFetching) {
        // If another instance is fetching, wait a bit and check again
        if (tracker.isFetching && now - tracker.lastFetchTime < 2000) {
            return
        }
    }
    
    // Prevent rapid successive calls (within 500ms)
    if (now - tracker.lastFetchTime < 500) {
        return
    }
    
    // Cancel any pending request (both local and global)
    if (abortController.value) {
        abortController.value.abort()
    }
    if (tracker.abortController) {
        tracker.abortController.abort()
    }
    
    // Create new abort controller for this request
    abortController.value = new AbortController()
    tracker.abortController = abortController.value
    isFetching.value = true
    tracker.isFetching = true
    tracker.lastFetchTime = now
    
    try {
        const response = await api.get('/stages/kanban/stages-with-leads', {
            signal: abortController.value.signal
        })
        stages.value = response.data.data.map((stage, index) => ({
            id: stage.id,
            name: stage.name,
            color: stage.color || getColorByIndex(index)
        }))
    } catch (error) {
        // Don't log error if request was aborted
        if (error.name !== 'AbortError' && error.name !== 'CanceledError') {
            console.error('Error fetching stages:', error)
        }
    } finally {
        isFetching.value = false
        tracker.isFetching = false
        abortController.value = null
        tracker.abortController = null
    }
}

// Listen for stage update events
const handleStageUpdate = (event) => {
    // Ignore events fired immediately after any component mount (within 1 second)
    const tracker = window.__fetchStagesTracker
    const now = Date.now()
    
    // Check if any component mounted recently
    const recentMount = tracker.mountTimes.some(mountTime => now - mountTime < 1000)
    if (recentMount) {
        // Event fired too soon after mount, ignore it
        return
    }
    
    // Use debounced version for event-driven updates
    fetchStages(false)
}

onMounted(() => {
    // Prevent multiple mounts from calling fetchStages
    if (hasMounted.value) {
        return
    }
    
    const tracker = window.__fetchStagesTracker
    const now = Date.now()
    mountTime.value = now
    
    // Track this mount time globally
    if (!tracker.mountTimes) {
        tracker.mountTimes = []
    }
    tracker.mountTimes.push(now)
    
    // Clean up old mount times (older than 2 seconds)
    tracker.mountTimes = tracker.mountTimes.filter(time => now - time < 2000)
    
    // Only fetch if no recent fetch (within last 2 seconds)
    const timeSinceLastFetch = now - (tracker.lastFetchTime || 0)
    if (timeSinceLastFetch < 2000 && tracker.lastFetchTime > 0) {
        // Another instance just fetched, skip this one
        hasMounted.value = true
        window.addEventListener('stage-updated', handleStageUpdate)
        return
    }
    
    // Immediate fetch on mount
    fetchStages(true)
    hasMounted.value = true
    
    // Listen for custom event when stages are updated
    window.addEventListener('stage-updated', handleStageUpdate)
})

onUnmounted(() => {
    // Cancel any pending request
    if (abortController.value) {
        abortController.value.abort()
        abortController.value = null
    }
    
    // Clear global tracker if this is the last instance
    const tracker = window.__fetchStagesTracker
    if (tracker.abortController === abortController.value) {
        tracker.abortController = null
        tracker.isFetching = false
    }
    
    // Remove this component's mount time from tracker
    if (mountTime.value && tracker.mountTimes) {
        tracker.mountTimes = tracker.mountTimes.filter(time => time !== mountTime.value)
    }
    
    // Clear debounce timer
    if (fetchDebounceTimer.value) {
        clearTimeout(fetchDebounceTimer.value)
        fetchDebounceTimer.value = null
    }
    
    // Clean up event listener
    window.removeEventListener('stage-updated', handleStageUpdate)
})

// Expose refresh method for manual refresh if needed
defineExpose({
    refresh: (immediate = true) => fetchStages(immediate)
})

// Compute selected stage index based on stage ID
const selectedStageIndex = computed(() => {
    if (!props.modelValue || stages.value.length === 0) return -1
    const index = stages.value.findIndex(stage => stage.id === props.modelValue)
    return index >= 0 ? index : -1
})

const selectStage = (index) => {
    if (stages.value[index]) {
        emit('update:modelValue', stages.value[index].id)
    }
}
</script>

<style scoped>
/* Stage Selector Styles */
.stage-selector-wrapper {
    /* border-bottom: 1px solid #F4F4F4; */
    overflow-x: auto;
    scrollbar-width: none;
}

.stage-selector-wrapper::-webkit-scrollbar {
    display: none;
}

.stage-container {
    display: flex;
    align-items: center;
    padding: 4px;
    border: 1px solid #E5E7EB; /* Blue outline as seen in image */
    border-radius: 50px;
    box-shadow: 1px 1px 5px 5px #00000005;
    width: fit-content;
    min-width: 100%;
}

.stage-pill {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 15px;
    border-radius: 30px;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
    position: relative;
}

.stage-pill:not(:first-child) {
    padding-left: 25px;
}

.stage-pill:not(.active) {
    color: #94A3B8;
}

.stage-circle {
    width: 15px;
    height: 15px;
    border-radius: 50%;
    border: 1px solid #E2E8F0;
    background: #FFFFFF;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.stage-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
}

.stage-text {
    font-family: Montserrat;
    font-weight: 400;
    font-size: 13px;
    color: #979797;
}

.stage-pill.active .stage-text {
    color: #01062C;
    font-weight: 400;
}

.stage-separator {
    display: flex;
    align-items: center;
    color: #01062C;
    flex-shrink: 0;
    /* margin-left: 8px; */
    /* margin-right: -12px; */
    z-index: 2;
}

.active-separator {
    color: #FFFF !important;
    font-weight: 400 !important;
}

.separator-icon {
    font-size: 20px;
    font-weight: 400 !important;
    color: #D9D9D9;
}
</style>
