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
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { BModal, BDropdown } from 'bootstrap-vue-3'
import StageSelector from './StageSelector.vue'
import GeneralTab from './GeneralTab.vue'
import HistoryTab from './HistoryTab.vue'
import api from '@/plugins/axios'
import Swal from 'sweetalert2'

const props = defineProps({
    modelValue: Boolean,
    leadId: {
        type: Number,
        default: null
    }
})
const lead = ref(null)
const emit = defineEmits(['update:modelValue', 'stage-updated', 'lead-updated'])

const show = ref(props.modelValue)
const leadStageId = ref(null)
const activeTab = ref('general')
const echoListener = ref(null)

const switchTab = (tab) => {
    activeTab.value = tab
}

const fetchLead = async () => {
    try {
        const response = await api.get(`/leads/${props.leadId}`)
        lead.value = response.data.data
        console.log('✅ Lead fetched:', lead.value)
    } catch (error) {
        console.error('❌ Error fetching lead:', error)
        $showNotification('Failed to load lead details', 'error')
    }
}

// Initialize real-time updates for this specific lead
const initializeLeadListener = () => {
    if (!props.leadId) return
    
    const user = JSON.parse(localStorage.getItem('user'))
    if (!user || !window.Echo) {
        console.log('❌ Real-time updates not available for lead modal')
        return
    }

    console.log('🔔 ViewLeadModal: Listening for lead updates:', props.leadId)

    try {
        echoListener.value = window.Echo.private(`user.${user.id}`)
            .listen('.lead.updated', (event) => {
                const leadData = event.lead?.data || event.lead
                
                // Only handle updates for this specific lead
                if (leadData && leadData.id === props.leadId) {
                    console.log('🎉 ViewLeadModal: Lead update received for current lead')
                    handleLeadUpdate(event)
                }
            })
    } catch (error) {
        console.error('❌ Failed to initialize Echo for lead modal:', error)
    }
}

const handleLeadUpdate = (event) => {
    const leadData = event.lead?.data || event.lead
    
    if (!leadData) return
    
    // Update the local lead data
    if (event.action_type === 'deleted') {
        $showNotification('This lead has been deleted', 'warning')
        show.value = false
    } else {
        lead.value = { ...lead.value, ...leadData }
        
        if (leadData.stage_id) {
            leadStageId.value = leadData.stage_id
        }
        
        emit('lead-updated', leadData)
        
        const userName = event.user_name || 'Someone'
        $showNotification(`${userName} updated this lead`, 'info')
    }
}

const cleanup = () => {
    if (echoListener.value && typeof echoListener.value.stopListening === 'function') {
        echoListener.value.stopListening('.lead.updated')
        echoListener.value = null
    }
}

onMounted(() => {
    if (show.value && props.leadId) {
        fetchLead()
        setTimeout(() => {
            initializeLeadListener()
        }, 500)
    }
})

onUnmounted(() => {
    cleanup()
})

watch(() => props.modelValue, (val) => {
    show.value = val
})

watch(show, (val) => {
    if (val) {
        if (props.leadId) {
            fetchLead()
            setTimeout(() => {
                initializeLeadListener()
            }, 500)
        }
    } else {
        cleanup()
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
watch(leadStageId, async (newStageId, oldStageId) => {
    if (newStageId && oldStageId && newStageId !== oldStageId && lead.value) {
        try {
            // Update the stage via API
            await api.post(`/leads/${lead.value.id}/change-stage`, {
                stage_id: newStageId
            })
            
            emit('stage-updated', { leadId: lead.value.id, stageId: newStageId })
            $showNotification('Lead stage updated successfully', 'success')
        } catch (error) {
            console.error('❌ Error updating stage:', error)
            $showNotification('Failed to update lead stage', 'error')
            // Revert the stage change
            leadStageId.value = oldStageId
        }
    }
})

// Notification helper
const $showNotification = (message, type = 'info') => {
    if (window.$showNotification) {
        window.$showNotification(message, type)
    } else {
        console.log(`${type}: ${message}`)
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        })
        
        const iconMap = {
            'success': 'success',
            'error': 'error',
            'warning': 'warning',
            'info': 'info'
        }
        
        Toast.fire({
            icon: iconMap[type] || 'info',
            title: message
        })
    }
}
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
