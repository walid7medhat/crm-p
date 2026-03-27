<template>
    <div class="stage-selector-wrapper py-3 pb-0">
        <div ref="scrollContainerRef" class="stage-container" @wheel.prevent="handleWheelScroll">
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
    </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import api from '@/plugins/axios'

const props = defineProps({
    modelValue: {
        type: Number,
        default: null
    }
})

const emit = defineEmits(['update:modelValue'])

const stages = ref([])
const scrollContainerRef = ref(null)
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

const scrollSelectedIntoView = () => {
    const el = scrollContainerRef.value
    if (!el || selectedStageIndex.value < 0) return
    const pills = el.querySelectorAll('.stage-pill')
    const selected = pills[selectedStageIndex.value]
    if (selected?.scrollIntoView) {
        selected.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' })
    }
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
        currentStageId.value = stages.value[index].id
        emit('update:modelValue', currentStageId.value)
        console.log('User selected stage:', stages.value[index].name)
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
    })
})

onMounted(() => {
    console.log('StageSelector mounted')
    fetchStages()
    nextTick(scrollSelectedIntoView)
})
</script>

<style scoped>
/* باقي الستايلات كما هي */
</style>

<style scoped>
.stage-selector-wrapper {
    overflow: hidden;
}

.stage-container {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 4px 4px 8px;
    box-shadow: 1px 1px 5px 5px #00000005;
    width: 100%;
    min-width: 0;
    overflow-x: scroll;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
    scroll-behavior: smooth;
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
    padding: 4px 11px;
    border-radius: 30px;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
    border: 1px solid transparent;
    box-shadow: inset -1px 0 0 rgba(255, 255, 255, 0.55);
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
    color: #01062C;
    font-weight: 400;
}
</style>