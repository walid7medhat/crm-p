<template>
    <div class="tab-content">
        <p class="section-title">CRM Entities</p>
        
        <!-- Entity Cards -->
        <div class="entity-cards-container">
            <div 
                v-for="entity in entities" 
                :key="entity.id"
                class="entity-card"
                :class="{ selected: selectedEntity === entity.id }"
                @click="selectedEntity = entity.id"
            >
                <div class="entity-icon-wrapper">
                    <iconify-icon :icon="entity.icon" class="entity-icon"></iconify-icon>
                </div>
                <span class="entity-label">{{ entity.label }}</span>
            </div>
        </div>

        <!-- Expert Mode Checkbox -->
        <div class="expert-mode-wrapper mt-3" @click="expertMode = !expertMode">
            <input 
                type="checkbox" 
                id="expert-mode"
                v-model="expertMode"
                class="expert-mode-checkbox"
                @click.stop
            />
            <label for="expert-mode" class="expert-mode-label" @click.stop>Expert Mode</label>
            <iconify-icon icon="lucide:chevrons-up-down" class="input-icon"></iconify-icon>
        </div>

        <!-- Duplicate Handling Options -->
        <div v-if="expertMode" class="duplicate-handling-wrapper mt-3">
            <p class="duplicate-handling-question">How do you want to handle duplicates ?</p>
            <div class="duplicate-options">
                <div class="duplicate-option" @click="duplicateHandling = 'allow'">
                    <input 
                        type="checkbox" 
                        id="allow-duplicates"
                        :checked="duplicateHandling === 'allow'"
                        class="duplicate-checkbox"
                        @click.stop="duplicateHandling = 'allow'"
                    />
                    <label for="allow-duplicates" class="duplicate-label" @click.stop>Allow Duplicates</label>
                </div>
                <div class="duplicate-option" @click="duplicateHandling = 'replace'">
                    <input 
                        type="checkbox" 
                        id="replace-duplicates"
                        :checked="duplicateHandling === 'replace'"
                        class="duplicate-checkbox"
                        @click.stop="duplicateHandling = 'replace'"
                    />
                    <label for="replace-duplicates" class="duplicate-label" @click.stop>Replace Duplicates</label>
                </div>
                <div class="duplicate-option" @click="duplicateHandling = 'merge'">
                    <input 
                        type="checkbox" 
                        id="merge-duplicates"
                        :checked="duplicateHandling === 'merge'"
                        class="duplicate-checkbox"
                        @click.stop="duplicateHandling = 'merge'"
                    />
                    <label for="merge-duplicates" class="duplicate-label" @click.stop>Merge Duplicates</label>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'

// الأسماء هنا لازم تطابق الأسماء في v-model بالمودال الرئيسي
const props = defineProps({
    selectedEntity: {  // كان modelSelectedEntity
        type: String,
        default: null
    },
    expertMode: {      // كان modelExpertMode
        type: Boolean,
        default: false
    },
    duplicateHandling: { // كان modelDuplicateHandling
        type: String,
        default: 'merge'
    }
})

const emit = defineEmits([
    'update:selectedEntity',    // كان update:model-selected-entity
    'update:expertMode',        // كان update:model-expert-mode
    'update:duplicateHandling'  // كان update:model-duplicate-handling
])

const selectedEntity = ref(props.selectedEntity)
const expertMode = ref(props.expertMode)
const duplicateHandling = ref(props.duplicateHandling)

const entities = [
    { 
        id: 'lead', 
        label: 'Lead', 
        icon: 'lucide:user-search' 
    },
    { 
        id: 'customer', 
        label: 'Customer', 
        icon: 'lucide:users' 
    },
    { 
        id: 'invoices', 
        label: 'Invoices', 
        icon: 'lucide:file-text' 
    }
]

// Watch props
watch(() => props.selectedEntity, (newVal) => {
    selectedEntity.value = newVal
})

watch(() => props.expertMode, (newVal) => {
    expertMode.value = newVal
})

watch(() => props.duplicateHandling, (newVal) => {
    duplicateHandling.value = newVal
})

// Emit changes
watch(selectedEntity, (newVal) => {
    emit('update:selectedEntity', newVal)
})

watch(expertMode, (newVal) => {
    emit('update:expertMode', newVal)
})

watch(duplicateHandling, (newVal) => {
    emit('update:duplicateHandling', newVal)
})
</script>



<style scoped>
.section-title {
    font-family: 'Montserrat', sans-serif;
    font-size: 16px;
    font-weight: 600;
    color: #0B0736;
    margin: 0 0 24px 0;
}

/* Entity Cards Container */
.entity-cards-container {
    display: flex;
    gap: 16px;
    margin-bottom: 10px;
}

.entity-card {
    flex: 1;
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 8px 0px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 1px 1px 5px 5px #00000005;
}

.entity-card:hover {
    border-color: #CBD5E1;
    box-shadow: 0px 2px 6px rgba(0, 0, 0, 0.1);
}

.entity-card.selected {
    border-color: #0B0736;
    background: #0B0736;
    box-shadow: 0px 2px 8px rgba(1, 6, 44, 0.15);
}

.entity-icon-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
}

.entity-icon {
    font-size: 36px;
    color: #979797;
}

.entity-card.selected .entity-icon {
    color: #733E87;
}

.entity-label {
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    font-weight: 400;
    color: #1E293B;
    text-align: center;
}

.entity-card.selected .entity-label {
    color: #FFFFFF;
    font-weight: 400;
}

/* Expert Mode Wrapper */
.expert-mode-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 24px;
    width: 100%;
    height: 44px;
    padding: 0 40px 0 16px;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    color: #1E293B;
    background: #FFFFFF;
    transition: all 0.2s;
    cursor: pointer;
    position: relative;
    box-shadow: 1px 1px 5px 5px #00000005;
}

.expert-mode-wrapper:hover {
    border-color: #CBD5E1;
    background: #F8FAFC;
    box-shadow: 1px 1px 5px 5px #00000005;
}

.expert-mode-checkbox {
    width: 18px;
    height: 18px;
    border: 2px solid #CBD5E1;
    border-radius: 4px;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    position: relative;
    background-color: #FFFFFF;
    transition: all 0.2s ease;
    margin: 0;
    flex-shrink: 0;
}

.expert-mode-checkbox:checked {
    background-color: #733E87;
    border-color: #733E87;
}

.expert-mode-checkbox:checked::after {
    content: "";
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%) rotate(45deg);
    width: 4px;
    height: 8px;
    border: solid white;
    border-width: 0 2px 2px 0;
    border-radius: 0;
}

.expert-mode-checkbox:hover {
    border-color: #733E87;
}

.expert-mode-label {
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 500;
    color: #1E293B;
    cursor: pointer;
    user-select: none;
    flex: 1;
}

.expert-mode-wrapper .input-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 18px;
    color: #94A3B8;
    pointer-events: none;
    z-index: 0;
}

.expert-mode-wrapper .input-icon * {
    pointer-events: none;
}

/* Duplicate Handling Wrapper */
.duplicate-handling-wrapper {
    width: 100%;
    padding: 16px;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    background: #FFFFFF;
    box-shadow: 1px 1px 5px 5px #00000005;
    transition: all 0.2s;
}

.duplicate-handling-question {
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 400;
    color: #94A3B8;
    margin: 0 0 12px 0;
    line-height: 1.5;
}

.duplicate-options {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.duplicate-option {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    transition: all 0.2s;
}

.duplicate-checkbox {
    width: 18px;
    height: 18px;
    border: 2px solid #CBD5E1;
    border-radius: 4px;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    position: relative;
    background-color: #FFFFFF;
    transition: all 0.2s ease;
    margin: 0;
    flex-shrink: 0;
}

.duplicate-checkbox:checked {
    background-color: #733E87;
    border-color: #733E87;
}

.duplicate-checkbox:checked::after {
    content: "";
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%) rotate(45deg);
    width: 4px;
    height: 8px;
    border: solid white;
    border-width: 0 2px 2px 0;
    border-radius: 0;
}

.duplicate-checkbox:hover {
    border-color: #733E87;
}

.duplicate-label {
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 400;
    color: #1E293B;
    cursor: pointer;
    user-select: none;
    margin: 0;
    line-height: 1.5;
}
</style>
