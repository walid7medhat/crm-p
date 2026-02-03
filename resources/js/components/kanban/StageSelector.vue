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
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const props = defineProps({
    modelValue: {
        type: Number,
        default: null
    }
})

const emit = defineEmits(['update:modelValue'])

const stages = ref([])

// Same color logic from leads.vue
const colors = ['#7BD3EA', '#E3DA32', '#F2C934', '#8EC82F', '#00A74C']

function getColorByIndex(index) {
    return colors[index % colors.length]
}

const fetchStages = async () => {
    try {
        const response = await api.get('/stages')
        
        // Handle response structure:
        // ApiResponse wraps: { status: true, message: '...', data: {...} }
        // StageCollection wraps: { data: [...], meta: {...} }
        // So stages array is at: response.data.data.data
        let stagesData = []
        
        if (response.data?.data?.data) {
            // StageCollection format
            stagesData = response.data.data.data
        } else if (response.data?.data && Array.isArray(response.data.data)) {
            // Direct array format
            stagesData = response.data.data
        } else if (Array.isArray(response.data)) {
            // Direct array response
            stagesData = response.data
        }
        
        stages.value = stagesData.map((stage, index) => ({
            id: stage.id,
            name: stage.name,
            color: stage.color || getColorByIndex(index),
            order: stage.order || index
        }))
    } catch (error) {
        console.error('Error fetching stages:', error)
        console.error('Error response:', error.response?.data)
    }
}

onMounted(() => {
    fetchStages()
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
