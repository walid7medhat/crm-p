<template>
    <b-modal 
        id="view-lead-modal" 
        v-model="show"
        hide-header
        hide-footer
        size="xl"
        centered
        body-class="p-0 view-lead-modal"
        :z-index="1040"
        :no-enforce-focus="true"
        :trap-focus="false"
    >
        <div v-if="show" class="view-lead-modal-content p-3">
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
            <StageSelector v-model="leadStageId"   
            :require-validation="true"
            @stage-change-request="handleStageChangeRequest"/>

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
                        v-if="canViewHistory"
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
                <GeneralTab 
                    v-if="activeTab === 'general'" 
                    :lead="lead" 
                    :stage-id="leadStageId"
                    @update:lead="handleLeadUpdateFromTab"
                />

                <!-- History Tab Content -->
                <HistoryTab v-if="activeTab === 'history' && canViewHistory"  :lead="lead" :is-active="activeTab === 'history'" />
            </div>
        </div>
           <!-- Stage Change Reason Modal -->
    <StageChangeReasonModal
        ref="stageChangeReasonModal"
        v-model="showStageChangeModal"
        :leadId="pendingStageChange?.leadId"
        :targetStageId="pendingStageChange?.targetStageId"
        :targetStageName="pendingStageChange?.targetStageName"
        :targetStageOrder="pendingStageChange?.targetStageOrder"
        :missingFields="missingFieldsForLead"
        :leadData="pendingStageChange?.leadData"
        :isConversion="pendingStageChange?.isConversion || false"
        @submit="handleStageChangeWithReason"
        @closed="clearPendingStageChange"
    />
    </b-modal>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted,computed } from 'vue'
import { BModal, BDropdown } from 'bootstrap-vue-3'
import StageSelector from '../shared/StageSelector.vue'
import StageChangeReasonModal from '../leadList/StageChangeReasonModal.vue'

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
const emit = defineEmits(['update:modelValue', 'stage-updated', 'lead-updated'])

const show = ref(props.modelValue)
const leadStageId = ref(null)
const activeTab = ref('general')
const echoListener = ref(null)
const echoAssignedListener = ref(null)
const user = ref(JSON.parse(localStorage.getItem('user')))
const isUserAction = ref(false)

// Stage Change Modal State
const stageChangeReasonModal = ref(null)
const showStageChangeModal = ref(false)
const pendingStageChange = ref(null)
const missingFieldsForLead = ref([])
const stageOrderMap = ref({})

const canViewHistory = computed(() => {
    if (!user.value || !lead.value) return false

    const isAdminUser =
        user.value.roles?.includes('super_admin') ||
        user.value.roles?.includes('admin')

    const isResponsible =
        lead.value.responsible_person_id === user.value.id

    return isAdminUser || isResponsible
})

const switchTab = (tab) => {
    activeTab.value = tab
}

// Fetch stage orders
const fetchStageOrders = async () => {
    try {
        const response = await api.get('/stages')
        let stages = []
        
        if (response.data && response.data.data) {
            stages = response.data.data
        } else if (response.data && Array.isArray(response.data)) {
            stages = response.data
        }
        
        if (!Array.isArray(stages)) {
            stages = []
        }
        
        const map = {}
        stages.forEach(stage => {
            if (stage && stage.id) {
                map[stage.id] = stage.order || 0
            }
        })
        stageOrderMap.value = map
        console.log('Stage order map loaded:', stageOrderMap.value)
    } catch (error) {
        console.error('Error fetching stage orders:', error)
        stageOrderMap.value = {}
    }
}

// Handle stage change request from StageSelector
const handleStageChangeRequest = async ({ stageId, stageName, stageOrder }) => {
    console.log('🎯 handleStageChangeRequest called:', { stageId, stageName, stageOrder })
    
    const targetStageOrder = stageOrderMap.value[stageId] || stageOrder || 0
    
    // Define required fields based on stage order
    const requiredFieldsMap = {
        3: ['salutation'],
        4: ['salutation', 'property_type_id', 'area_id', 'budget_from','budget_to','property_status','lead_type', 'purpose_buying', 'bedrooms', 'status_lead'],
        5: ['salutation', 'available_date'],
        7: ['salutation', 'branch'],
        8: ['why_lost_lead'],
        9: ['status_lead'],
        10: ['status_lead']
    }
    
    // For conversion (stage 6), check all required fields
    if (targetStageOrder === 6) {
        const requiredFieldsForConversion = [
            'salutation', 'property_type_id', 'area_id', 'budget', 'budget_from','budget_to','property_status','lead_type',
            'lead_source', 'purpose_buying', 'bedrooms', 'status_lead',
            
        ]
        
        const missingFields = requiredFieldsForConversion.filter(field => {
            const value = lead.value[field]
            return !value || value === '' || value === null || value === undefined
        })
        
        if (missingFields.length > 0) {
            console.log('Missing fields for conversion:', missingFields)
            
            pendingStageChange.value = {
                leadId: lead.value.id,
                targetStageId: stageId,
                targetStageName: stageName,
                targetStageOrder: targetStageOrder,
                originalStageId: lead.value.stage_id,
                leadData: { ...lead.value },
                isConversion: true
            }
            
            missingFieldsForLead.value = missingFields
            showStageChangeModal.value = true
            setTimeout(() => {
                const textarea = document.querySelector('.stage-change-modal textarea')

                if (textarea) {
                    textarea.focus()
                    
                    // 💣 دي اللي هتحل المشكلة
                    textarea.click()
                }

                // 💣 دي أهم سطر
                document.body.classList.remove('modal-open')
            }, 100)
            return
        }
        
        // All data complete, proceed with stage change
        await executeStageChange(stageId, lead.value.stage_id)
        return
    }
    
    // For other stages
    const requiredFields = requiredFieldsMap[targetStageOrder] || []
    const leadMissingFields = requiredFields.filter(field => {
        const value = lead.value[field]
        return !value || value === '' || value === null || value === undefined
    })
    
    if (leadMissingFields.length > 0 || [3,4,5,7,8,9,10].includes(targetStageOrder)) {
        console.log('Missing fields for stage:', targetStageOrder, leadMissingFields)
        
        pendingStageChange.value = {
            leadId: lead.value.id,
            targetStageId: stageId,
            targetStageName: stageName,
            targetStageOrder: targetStageOrder,
            originalStageId: lead.value.stage_id,
            leadData: { ...lead.value },
            isConversion: false
        }
        
        missingFieldsForLead.value = leadMissingFields
        showStageChangeModal.value = true
       setTimeout(() => {
            const textarea = document.querySelector('.stage-change-modal textarea')

            if (textarea) {
                textarea.focus()
                
                // 💣 دي اللي هتحل المشكلة
                textarea.click()
            }

            // 💣 دي أهم سطر
            document.body.classList.remove('modal-open')
        }, 100)
        return
    }
    
    // No missing fields, proceed with stage change
    await executeStageChange(stageId, lead.value.stage_id)
}
// Execute stage change API call
const executeStageChange = async (newStageId, oldStageId) => {
    try {
        isUserAction.value = true  // Mark that this is a user action
        const response = await api.post(`/leads/${lead.value.id}/change-stage`, {
            stage_id: newStageId
        })
        const updatedLeadData = response.data?.data || response.data
        if (updatedLeadData) {
            lead.value = { ...lead.value, ...updatedLeadData }
        }
        // Update the stage selector value
        leadStageId.value = newStageId
        emit('lead-updated', lead.value)
        $showNotification('Stage updated successfully', 'success')
    } catch (error) {
        console.error('❌ Error updating stage:', error)
        $showNotification(error.response?.data?.message || 'Failed to update stage', 'error')
        // Revert the stage selector
        leadStageId.value = oldStageId
    } finally {
        setTimeout(() => {
            isUserAction.value = false
        }, 500)
    }
}

// Handle stage change with reason from modal
const handleStageChangeWithReason = async ({ leadId, targetStageId, reason, ...additionalData }) => {
    console.log('handleStageChangeWithReason called:', { leadId, targetStageId, reason, additionalData })
    
    try {
        const leadData = pendingStageChange.value?.leadData
        if (!leadData) {
            console.error('No lead data found')
            return
        }

        const isConversion = pendingStageChange.value?.isConversion || false
        const targetStageOrder = pendingStageChange.value?.targetStageOrder || 0

        // Prepare payload
        const payload = {
            stage_id: targetStageId,
            reason: reason || null,
        }
        
        // Add fields from modal
        if (additionalData.salutation) payload.salutation = additionalData.salutation
        if (additionalData.budget_from) payload.budget_from = additionalData.budget_from
        if (additionalData.budget_to) payload.budget_to = additionalData.budget_to
        if (additionalData.lead_type) payload.lead_type = additionalData.lead_type
        if (additionalData.property_status) payload.property_status = additionalData.property_status
        if (additionalData.area_id) payload.area_id = additionalData.area_id
        if (additionalData.property_type_id) payload.property_type_id = additionalData.property_type_id
        
        // Handle bedrooms conversion
        let bedroomsValue = additionalData.bedrooms
        if (bedroomsValue === 'Studio' || bedroomsValue === 'studio') {
            bedroomsValue = 0
        }
        if (bedroomsValue !== undefined && bedroomsValue !== '') payload.bedrooms = bedroomsValue
        
        if (additionalData.purpose_buying) payload.purpose_buying = additionalData.purpose_buying
        if (additionalData.lead_source) payload.lead_source = additionalData.lead_source
        if (additionalData.available_date) payload.available_date = additionalData.available_date
        if (additionalData.branch) payload.branch = additionalData.branch
        if (additionalData.lost_reason) payload.why_lost_lead = additionalData.lost_reason
        
        // Handle lead status based on stage
        if (additionalData.lead_status) {
            if (targetStageOrder === 4 || (isConversion && targetStageOrder === 6)) {
                payload.status_lead = additionalData.lead_status
            } else if (targetStageOrder === 9) {
                payload.status_lead_pool = additionalData.lead_status
            } else if (targetStageOrder === 10) {
                payload.unqualified_status = additionalData.lead_status
            }
        }

        console.log('Sending payload:', payload)

        // Send request
        const response = await api.post(`/leads/${leadId}/change-stage`, payload)
        
        $showNotification(response.data?.message || 'Stage updated successfully', 'success')
        
        // Update local lead data
        if (response.data?.data) {
            lead.value = { ...lead.value, ...response.data.data }
        }
        
        // Update stage selector
        if (targetStageId) {
            leadStageId.value = targetStageId
        }
        
        // Close modal
        showStageChangeModal.value = false
        
        // Emit update to parent
        emit('lead-updated', lead.value)
        
        clearPendingStageChange()
        
    } catch (error) {
        console.error('Error:', error)
        $showNotification(error.response?.data?.message || 'Failed to update stage', 'error')
        throw error
    }
}

const clearPendingStageChange = () => {
    pendingStageChange.value = null
    missingFieldsForLead.value = []
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
    if (!props.leadId) {
        console.log('⚠️ ViewLeadModal: No leadId provided, skipping listener initialization')
        return
    }
    
    const user = JSON.parse(localStorage.getItem('user'))
    if (!user || !window.Echo) {
        console.log('❌ ViewLeadModal: Real-time updates not available - User:', !!user, 'Echo:', !!window.Echo)
        return
    }

    console.log('🔔 ViewLeadModal: Initializing listeners for lead:', props.leadId)
    console.log('   - User ID:', user.id)
    console.log('   - Channel: user.' + user.id)

    try {
        const channel = window.Echo.private(`user.${user.id}`)
        
        // Listen for lead.updated events
        console.log('📡 ViewLeadModal: Setting up .lead.updated listener...')
        echoListener.value = channel.listen('.lead.updated', (event) => {
            console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━')
            console.log('🔔 ViewLeadModal: [UPDATED EVENT] Received .lead.updated event')
            console.log('   📦 Full Event Object:', JSON.stringify(event, null, 2))
            console.log('   🆔 Event Lead ID:', event.lead?.data?.id || event.lead?.id || 'N/A')
            console.log('   🎯 Current Modal Lead ID:', props.leadId)
            console.log('   📋 Action Type:', event.action_type || 'NOT PROVIDED')
            console.log('   👤 User Name:', event.user_name || 'NOT PROVIDED')
            console.log('   👤 User ID:', event.user_id || 'NOT PROVIDED')
            console.log('   ⏰ Timestamp:', new Date().toISOString())
            
            const leadData = event.lead?.data || event.lead
            
            console.log('   📊 Lead Data Structure:', {
                hasLeadData: !!leadData,
                leadId: leadData?.id,
                leadName: leadData?.lead_name,
                stageId: leadData?.stage_id,
                responsiblePersonId: leadData?.responsible_person_id,
                responsiblePerson: leadData?.responsible_person,
                hasStage: !!leadData?.stage,
                allKeys: leadData ? Object.keys(leadData) : []
            })
            
            // Only handle updates for this specific lead
            if (leadData && leadData.id === props.leadId) {
                console.log('   ✅ MATCH: Event is for current lead, processing update...')
                handleLeadUpdate(event, 'updated')
            } else {
                console.log('   ⏭️  SKIP: Event is not for current lead')
                if (leadData) {
                    console.log('      - Event Lead ID:', leadData.id, 'vs Current:', props.leadId)
                } else {
                    console.log('      - No lead data found in event')
                }
            }
            console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━')
        })
        
        // Listen for lead.assigned events
        console.log('📡 ViewLeadModal: Setting up .lead.assigned listener...')
        echoAssignedListener.value = channel.listen('.lead.assigned', (event) => {
            console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━')
            console.log('🔔 ViewLeadModal: [ASSIGNED EVENT] Received .lead.assigned event')
            console.log('   📦 Full Event Object:', JSON.stringify(event, null, 2))
            console.log('   🆔 Event Lead ID:', event.lead?.data?.id || event.lead?.id || 'N/A')
            console.log('   🎯 Current Modal Lead ID:', props.leadId)
            console.log('   📋 Action Type:', event.action_type || 'NOT PROVIDED')
            console.log('   👤 User Name:', event.user_name || 'NOT PROVIDED')
            console.log('   👤 User ID:', event.user_id || 'NOT PROVIDED')
            console.log('   👤 Assigned To:', event.assigned_to?.name || event.assigned_to_id || 'NOT PROVIDED')
            console.log('   ⏰ Timestamp:', new Date().toISOString())
            
            const leadData = event.lead?.data || event.lead
            
            console.log('   📊 Lead Data Structure:', {
                hasLeadData: !!leadData,
                leadId: leadData?.id,
                leadName: leadData?.lead_name,
                stageId: leadData?.stage_id,
                responsiblePersonId: leadData?.responsible_person_id,
                responsiblePerson: leadData?.responsible_person,
                hasStage: !!leadData?.stage,
                allKeys: leadData ? Object.keys(leadData) : []
            })
            
            // Only handle assignments for this specific lead
            if (leadData && leadData.id === props.leadId) {
                console.log('   ✅ MATCH: Event is for current lead, processing assignment...')
                handleLeadUpdate(event, 'assigned')
            } else {
                console.log('   ⏭️  SKIP: Event is not for current lead')
                if (leadData) {
                    console.log('      - Event Lead ID:', leadData.id, 'vs Current:', props.leadId)
                } else {
                    console.log('      - No lead data found in event')
                }
            }
            console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━')
        })
        
        console.log('✅ ViewLeadModal: Both listeners initialized successfully')
    } catch (error) {
        console.error('❌ ViewLeadModal: Failed to initialize Echo listeners:', error)
        console.error('   Error Details:', {
            message: error.message,
            stack: error.stack,
            name: error.name
        })
    }
}

const handleLeadUpdate = (event, eventType = 'unknown') => {
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━')
    console.log(`🔄 ViewLeadModal: handleLeadUpdate called [${eventType.toUpperCase()}]`)
    console.log('   📋 Event Type:', eventType)
    console.log('   📋 Action Type:', event.action_type || 'NOT PROVIDED')
    console.log('   📦 Event Structure:', {
        hasLead: !!event.lead,
        hasLeadData: !!event.lead?.data,
        hasActionType: !!event.action_type,
        hasUserName: !!event.user_name,
        hasUserId: !!event.user_id,
        allEventKeys: Object.keys(event)
    })
    
    const leadData = event.lead?.data || event.lead
    
    console.log('   📊 Lead Data Check:', {
        hasLeadData: !!leadData,
        leadId: leadData?.id,
        leadName: leadData?.lead_name
    })
    
    if (!leadData) {
        console.log('   ❌ ERROR: No lead data found in event, aborting update')
        console.log('   📦 Event object:', event)
        console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━')
        return
    }
    
    console.log('   📝 Current Lead State (Before Update):', {
        id: lead.value?.id,
        name: lead.value?.lead_name,
        stageId: lead.value?.stage_id,
        responsiblePersonId: lead.value?.responsible_person_id,
        responsiblePersonName: lead.value?.responsible_person?.name
    })
    
    console.log('   📝 New Lead Data (From Event):', {
        id: leadData.id,
        name: leadData.lead_name,
        stageId: leadData.stage_id,
        responsiblePersonId: leadData.responsible_person_id,
        responsiblePersonName: leadData.responsible_person?.name,
        allKeys: Object.keys(leadData)
    })
    
    // Update the local lead data
    if (event.action_type === 'deleted') {
        console.log('   🗑️  ACTION: Lead deleted, closing modal')
        console.log('   ✅ TRIGGER: show.value = false')
        $showNotification('This lead has been deleted', 'warning')
        show.value = false
        console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━')
    } else {
        console.log('   🔄 ACTION: Updating lead data...')
        
        const previousLead = { ...lead.value }
        lead.value = { ...lead.value, ...leadData }
        
        console.log('   ✅ TRIGGER: lead.value updated')
        console.log('   📊 Changes Detected:', {
            nameChanged: previousLead?.lead_name !== lead.value?.lead_name,
            stageChanged: previousLead?.stage_id !== lead.value?.stage_id,
            responsiblePersonChanged: previousLead?.responsible_person_id !== lead.value?.responsible_person_id,
            previousStageId: previousLead?.stage_id,
            newStageId: lead.value?.stage_id,
            previousResponsiblePersonId: previousLead?.responsible_person_id,
            newResponsiblePersonId: lead.value?.responsible_person_id
        })
        
        if (leadData.stage_id) {
            const previousStageId = leadStageId.value
            leadStageId.value = leadData.stage_id
            console.log('   ✅ TRIGGER: leadStageId.value updated')
            console.log('      - Previous Stage ID:', previousStageId)
            console.log('      - New Stage ID:', leadStageId.value)
        } else {
            console.log('   ⚠️  WARNING: No stage_id in leadData, stage selector not updated')
        }
        
        console.log('   ✅ TRIGGER: Emitting lead-updated event to parent')
        console.log('      - Emit Data:', {
            id: leadData.id,
            name: leadData.lead_name,
            stageId: leadData.stage_id,
            responsiblePersonId: leadData.responsible_person_id
        })
        emit('lead-updated', leadData)
        
        const userName = event.user_name || 'Someone'
        const notificationMessage = eventType === 'assigned' 
            ? `${userName} assigned this lead` 
            : `${userName} updated this lead`
        
        console.log('   ✅ TRIGGER: Showing notification')
        console.log('      - Message:', notificationMessage)
        $showNotification(notificationMessage, 'info')
        
        console.log('   📝 Final Lead State (After Update):', {
            id: lead.value?.id,
            name: lead.value?.lead_name,
            stageId: lead.value?.stage_id,
            leadStageIdValue: leadStageId.value,
            responsiblePersonId: lead.value?.responsible_person_id,
            responsiblePersonName: lead.value?.responsible_person?.name
        })
        
        console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━')
    }
}

const handleLeadUpdateFromTab = (updatedLeadData) => {
    console.log('📝 ViewLeadModal: Received lead update from GeneralTab:', updatedLeadData)
    
    // Completely replace the lead data with the latest response from API
    // This ensures all fields are updated, including nested objects like responsible_person, stage, etc.
    lead.value = updatedLeadData
    
    // Update stage selector with the latest stage
    if (updatedLeadData.stage_id) {
        leadStageId.value = updatedLeadData.stage_id
    } else if (updatedLeadData.stage?.id) {
        leadStageId.value = updatedLeadData.stage.id
    }
    
    console.log('✅ ViewLeadModal: Lead data updated successfully')
    console.log('   - Lead ID:', lead.value.id)
    console.log('   - Lead Name:', lead.value.lead_name)
    console.log('   - Stage ID:', leadStageId.value)
    console.log('   - Responsible Person:', lead.value.responsible_person?.name)
    
    // Emit to parent to refresh the kanban board with the latest data
    emit('lead-updated', updatedLeadData)
}

const cleanup = () => {
    console.log('🧹 ViewLeadModal: Cleaning up Echo listeners...')
    
    if (echoListener.value) {
        console.log('   🗑️  Removing .lead.updated listener')
        if (typeof echoListener.value.stopListening === 'function') {
            echoListener.value.stopListening('.lead.updated')
            console.log('   ✅ .lead.updated listener stopped')
        } else {
            console.log('   ⚠️  stopListening method not available on listener')
        }
        echoListener.value = null
    } else {
        console.log('   ℹ️  No .lead.updated listener to clean up')
    }
    
    if (echoAssignedListener.value) {
        console.log('   🗑️  Removing .lead.assigned listener')
        if (typeof echoAssignedListener.value.stopListening === 'function') {
            echoAssignedListener.value.stopListening('.lead.assigned')
            console.log('   ✅ .lead.assigned listener stopped')
        } else {
            console.log('   ⚠️  stopListening method not available on assigned listener')
        }
        echoAssignedListener.value = null
    } else {
        console.log('   ℹ️  No .lead.assigned listener to clean up')
    }
    
    console.log('✅ ViewLeadModal: Cleanup completed')
}

onMounted(() => {
    console.log('🚀 ViewLeadModal: Component mounted')
    console.log('   - show.value:', show.value)
    console.log('   - props.leadId:', props.leadId)
    fetchStageOrders()
    if (show.value && props.leadId) {
        console.log('   ✅ Initializing lead data and listeners...')
        fetchLead()
        setTimeout(() => {
            console.log('   ⏰ Timeout completed, initializing listeners...')
            initializeLeadListener()
        }, 500)
    } else {
        console.log('   ⏭️  Skipping initialization - modal not shown or no leadId')
    }
})

onUnmounted(() => {
    console.log('💀 ViewLeadModal: Component unmounting, cleaning up...')
    cleanup()
    console.log('✅ ViewLeadModal: Component unmounted')
})

watch(() => props.modelValue, (val) => {
    show.value = val
})

watch(show, (val) => {
    console.log('👀 ViewLeadModal: show watcher triggered')
    console.log('   - New value:', val)
    console.log('   - props.leadId:', props.leadId)
    
    if (val) {
        console.log('   ✅ Modal opened, initializing...')
        if (props.leadId) {
            console.log('   📥 Fetching lead data...')
            fetchLead()
            setTimeout(() => {
                console.log('   ⏰ Timeout completed, initializing listeners...')
                initializeLeadListener()
            }, 500)
        } else {
            console.log('   ⚠️  No leadId provided, cannot fetch or listen')
        }
    } else {
        console.log('   ❌ Modal closed, cleaning up listeners...')
        cleanup()
                activeTab.value = 'general'
                

    }
    console.log('   📤 Emitting update:modelValue:', val)
    emit('update:modelValue', val)
})

// Watch for lead prop changes to update the stage
watch(lead, (newLead) => {
    if (lead.value && lead.value.stage_id) {
        leadStageId.value = lead.value.stage_id
    }
}, { immediate: true })

// When user clicks a stage in StageSelector, save immediately and show in activity timeline
watch(leadStageId, async (newStageId, oldStageId) => {
    if (!isUserAction.value && newStageId !== oldStageId) {
        console.log('External stage update detected:', { newStageId, oldStageId })
    }
}, { immediate: false })

// Notification helper – use global deferred to avoid SweetAlert2 stack overflow
const $showNotification = (message, type = 'info') => {
    if (window.$showNotification) window.$showNotification(message, type)
    else console.log(`${type}: ${message}`)
}
</script>

<style scoped>
.view-lead-modal{
        z-index: 1000 !important;
}
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

.modal-backdrop {
    z-index: 1040 !important;
}

.modal {
    z-index: 1050 !important;
}

.stage-change-modal-overlay {
    z-index: 9999 !important;
    pointer-events: auto !important;
}




.stage-change-modal-overlay * {
    pointer-events: auto !important;
    user-select: text !important;
}

textarea, input, select {
    pointer-events: auto !important;
    user-select: text !important;
}
</style>
