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

function getColorByIndex(index) {
    return colors[index % colors.length]
}

const selectedStageIndex = computed(() => {
    if (!props.modelValue || stages.value.length === 0) return -1
    const index = stages.value.findIndex(stage => stage.id === props.modelValue)
    return index >= 0 ? index : -1
})

const scrollSelectedIntoView = () => {
    const el = scrollContainerRef.value
    if (!el || selectedStageIndex.value < 0) return
    const pills = el.querySelectorAll('.stage-pill')
    const selected = pills[selectedStageIndex.value]
    if (selected?.scrollIntoView) {
        selected.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' })
    }
}

const fetchStages = async () => {
    try {
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

        nextTick(() => {
            scrollSelectedIntoView()
        })
    } catch (error) {
        console.error('Error fetching stages:', error)
        console.error('Error response:', error.response?.data)
    }
}

const selectStage = (index) => {
    if (stages.value[index]) {
        emit('update:modelValue', stages.value[index].id)
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
    fetchStages()
    nextTick(scrollSelectedIntoView)
})
</script>

<style scoped>
.stage-selector-wrapper {
    overflow: hidden;
}

.stage-container {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 4px 4px 8px;
    border: 1px solid #E5E7EB;
    border-radius: 50px;
    box-shadow: 1px 1px 5px 5px #00000005;
    width: 100%;
    min-width: 0;
    overflow-x: scroll;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 #f1f5f9;
}

.stage-container::-webkit-scrollbar {
    height: 6px;
}

.stage-container::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 999px;
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
