<template>
    <b-modal 
        id="view-lead-modal" 
        v-model="show"
        hide-header
        hide-footer
        size="xl"
        centered
        body-class="p-0"
    >
        <div class="view-lead-modal-content p-3">
            <!-- Header -->
            <div class="modal-header-custom d-flex justify-content-between align-items-center px-1">
                <div class="d-flex align-items-center gap-3">
                    <span class="modal-title">{{ lead?.lead_name }}</span>
                </div>
                <button class="close-btn" @click="show = false">
                    <iconify-icon icon="lucide:x"></iconify-icon>
                </button>
            </div>

            <!-- Stages Progress -->
            <StageSelector v-model="leadStageId" />

            <!-- Tabs -->
            <div class="tabs-container mb-3 border-bottom">
                <div class="d-flex gap-4">
                    <button 
                        class="tab-item" 
                        :class="{ active: activeTab === 'general' }"
                        @click="switchTab('general')"
                    >
                        General
                    </button>
                    <button 
                        class="tab-item" 
                        :class="{ active: activeTab === 'history' }"
                        @click="switchTab('history')"
                    >
                        History
                    </button>
                </div>
            </div>

            <!-- Main Content -->
            <div class="modal-body-custom p-4">
                <!-- General Tab Content -->
                <GeneralTab v-show="activeTab === 'general'" :lead="lead" />

                <!-- History Tab Content -->
                <HistoryTab v-show="activeTab === 'history'" :lead="lead" />
            </div>
        </div>
    </b-modal>
</template>

<script setup>
import { ref, watch } from 'vue'
import { BModal, BDropdown } from 'bootstrap-vue-3'
import StageSelector from './StageSelector.vue'
import GeneralTab from './GeneralTab.vue'
import HistoryTab from './HistoryTab.vue'
import api from '@/plugins/axios'

const props = defineProps({
    modelValue: Boolean,
    leadId: {
        type: Number,
        default: null
    }
})
const lead = ref(null)
const emit = defineEmits(['update:modelValue', 'stage-updated'])

const show = ref(props.modelValue)
const leadStageId = ref(null)
const activeTab = ref('general')

const switchTab = (tab) => {
    activeTab.value = tab
}

// mounted(() => {
    // if (props.leadId) {
    //     fetchLead()
    // }
// })

const fetchLead = async () => {
    const response = await api.get(`/leads/${props.leadId}`)
    lead.value = response.data.data
}

watch(() => props.modelValue, (val) => {
    show.value = val
})

watch(show, (val) => {
    if (val) {
        if (props.leadId) {
            fetchLead()
        }
    }
    emit('update:modelValue', val)
})

// Watch for lead prop changes to update the stage
watch(lead, (newLead) => {
    if (lead.value && lead.value.stage_id) {
        leadStageId.value = lead.value.stage_id
    }
}, { immediate: true })

// Emit when stage is updated
watch(leadStageId, (newStageId) => {
    if (newStageId && props.lead) {
        emit('stage-updated', { leadId: props.lead.id, stageId: newStageId })
    }
})
</script>

<style scoped>
.view-lead-modal-content {
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    font-family: 'Montserrat', sans-serif;
}

.modal-header-custom {
    background: #fff;
}

.modal-title {
    font-size: 16px;
    font-weight: 600;
    color: #01062C;
}

.settings-btn, .close-btn, .notification-btn {
    background: none;
    border: none;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.close-btn {
    font-size: 20px;
    color: #000;
}

.custom-dropdown-pill :deep(.btn) {
    border-radius: 50px !important;
    border: 1px solid #E5E7EB !important;
    background-color: #fff !important;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.custom-dropdown-pill :deep(.btn:hover) {
    background-color: #F9FAFB !important;
    border-color: #D1D5DB !important;
}

.custom-dropdown-pill :deep(.btn:focus) {
    box-shadow: none !important;
}

.text-neutral-500 {
    color: #9CA3AF !important;
}

.tab-item {
    background: none;
    border: none;
    padding: 12px 10px;
    font-size: 13px;
    font-weight: 500;
    color: #64748B;
    position: relative;
    cursor: pointer;
}

.tab-item.active {
    color: #01062C;
}

.tab-item.active::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    width: 100%;
    height: 2px;
    background: #FAA300;
}

.bg-light-gray {
    background-color: #F8FAFC;
}

.radius-12 { border-radius: 12px; }
.radius-8 { border-radius: 8px; }
.radius-4 { border-radius: 4px; }
.radius-100 { border-radius: 100px; }

.section-title {
    font-size: 14px;
    font-weight: 600;
    color: #01062C;
}

.info-label {
    display: block;
    font-size: 12px;
    color: #64748B;
    margin-bottom: 2px;
}

.info-value {
    font-size: 13px;
    font-weight: 600;
    color: #01062C;
}

.info-group {
    margin-bottom: 12px;
}

.responsible-person-box {
    background: #fff;
    border: 1px solid #F3F3F3;
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.03);
}

.match-card {
    background: #fff;
    border: 1px solid #F3F3F3;
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.03);
}

.btn-toggle {
    background: none;
    border: none;
    font-size: 13px;
    font-weight: 600;
    color: #64748B;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-toggle.active {
    background: #01062C;
    color: #fff;
    box-shadow: 0px 4px 8px rgba(1, 6, 44, 0.2);
}

.comment-box {
    background: #fff;
    border: 1px solid #E2E8F0 !important;
}

.btn-primary {
    background: #01062C;
    border: none;
    font-weight: 500;
}

.btn-light {
    background: #F1F5F9;
    border: none;
    color: #475569;
    font-weight: 500;
}

.bg-info-soft {
    background-color: #E0F2FE;
}

.text-info {
    color: #0EA5E9;
}

.bg-success-soft {
    background-color: #D1FAE5;
}

.text-success {
    color: #10B981;
}

.bg-warning-soft {
    background-color: #FEF3C7;
}

.bg-primary-soft {
    background-color: #DBEAFE;
}

.text-primary {
    color: #3B82F6;
}

.h-fit-content {
    height: fit-content;
}

.history-content {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.avatar-sm {
    width: 32px;
    height: 32px;
    object-fit: cover;
}

.timeline-date {
    padding-left: 44px;
}
</style>
