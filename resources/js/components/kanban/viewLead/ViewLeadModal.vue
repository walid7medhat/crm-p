<template>
    <b-modal 
        id="view-lead-modal" 
        v-model="show"
        hide-header
        hide-footer
        size="xl"
        centered
        body-class="p-0 view-lead-modal"
        :z-index="zIndex"
        :no-focus="true"
        dialog-class="kanban-mobile-fullscreen-modal"
    >
        <div v-if="show" class="view-lead-modal-content p-3 pb-0">
            <!-- Header -->
            <div class="modal-header-custom d-flex align-items-center gap-2 px-1">
                <span class="modal-title">{{ lead?.lead_name }}</span>
                <button type="button" class="close-btn view-lead-close-btn" aria-label="Close lead" @click="show = false">
                    <iconify-icon icon="lucide:x"></iconify-icon>
                </button>
            </div>

            <!-- Stages Progress -->
            <StageSelector v-model="leadStageId"   
            :require-validation="true"
            :class="pt-0"
            @stage-change-request="handleStageChangeRequest"/>

            <!-- Tabs -->
            <div class="tabs-container mb- border-bottom">
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
            :interactionMode="pendingStageChange?.interactionMode || false"
            @submit="handleStageChangeWithReason"
            @closed="clearPendingStageChange"
        />
       
    </b-modal>
     <ConvertLeadModal
        ref="convertModalRef"
        :leadId="selectedLeadForConversion"
        :leadData="selectedLeadData"
        @converted="handleLeadConverted"
        @closed="selectedLeadForConversion = null"
    />
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted, computed, nextTick } from 'vue'
import { BModal, BDropdown } from 'bootstrap-vue-3'
import StageSelector from '../shared/StageSelector.vue'
import StageChangeReasonModal from '../leadList/StageChangeReasonModal.vue'
import ConvertLeadModal from '../leadList/ConvertLeadModal.vue'

import GeneralTab from './GeneralTab.vue'
import HistoryTab from './HistoryTab.vue'
import api from '@/plugins/axios'
import { shouldSuppressLeadUpdateNotification } from '@/utils/leadRealtimeNotifications.js'

const props = defineProps({
    modelValue: Boolean,
    leadId: {
        type: [Number, String],
        default: null
    },
    /** Use a higher value when opening on top of another modal (e.g. view deal). */
    zIndex: {
        type: Number,
        default: 1040
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
const selectedLeadForConversion = ref(null)
const selectedLeadData = ref(null)
const convertModalRef = ref(null)

function handleLeadConverted(deal) {
    selectedLeadForConversion.value = null
    selectedLeadData.value = null
    show.value = false
    emit('update:modelValue', false)
    window.dispatchEvent(new CustomEvent('kanban-open-converted-deal', { detail: deal }))
}

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

    const normalizeStageName = (name) =>
        String(name || '').toLowerCase().replace(/[^a-z]/g, '')

    const sourceStageName = normalizeStageName(lead.value?.stage?.name || lead.value?.stage_name)
    const targetStageName = normalizeStageName(stageName)

    const isAssignToFollowUpOrContacted =
        (sourceStageName.includes('assign') || sourceStageName.includes('newlead')) &&
        (targetStageName.includes('followup') || targetStageName.includes('contacted'))

    const isMovingToContacted = targetStageName.includes('contacted')
    const isSalutationMissing = !lead.value?.salutation || lead.value?.salutation === '' || lead.value?.salutation === null

    console.log('🔍 Contacted check:', { 
        isMovingToContacted, 
        isSalutationMissing,
        currentSalutation: lead.value?.salutation,
        sourceStageName,
        targetStageName
    })

    // 🔥 Contacted logic (نفس الـ Kanban بالضبط)
    if (isMovingToContacted && isSalutationMissing) {
        console.log('📢 Moving to Contacted stage but salutation is missing. Showing modal.')
        
        pendingStageChange.value = {
            leadId: lead.value.id,
            targetStageId: stageId,
            targetStageName: stageName,
            targetStageOrder: targetStageOrder,
            originalStageId: lead.value.stage_id,
            leadData: { ...lead.value },
            isConversion: false,
            interactionMode: true
        }

        missingFieldsForLead.value = ['salutation']
        showStageChangeModal.value = true
        
        await nextTick()
        
        setTimeout(() => {
            const textarea = document.querySelector('.stage-change-modal textarea')
            if (textarea) {
                textarea.focus()
                textarea.click()
            }
            document.body.classList.remove('modal-open')
        }, 100)
        
        return
    }

    // 🔥 Assign → Follow up / Contacted (نفس الـ Kanban)
    if (isAssignToFollowUpOrContacted) {
        console.log('📢 Assign to FollowUp/Contacted')
        
        pendingStageChange.value = {
            leadId: lead.value.id,
            targetStageId: stageId,
            targetStageName: stageName,
            targetStageOrder: targetStageOrder,
            originalStageId: lead.value.stage_id,
            leadData: { ...lead.value },
            isConversion: false,
            interactionMode: true
        }

        missingFieldsForLead.value = []
        showStageChangeModal.value = true
        
        await nextTick()
        return
    }

    // 🔥 Conversion (stage order 6)
    if (targetStageOrder === 6) {
        const requiredFieldsForConversion = [
            'salutation',
            'property_type_id',
            'area_id',
            'budget_from',
            'budget_to',
            'lead_type',
            'property_status',
            'lead_source',
            'purpose_buying',
            'bedrooms',
            'status_lead',
            'deal_name'
        ]

        const missingFields = requiredFieldsForConversion.filter(field => {
            const value = lead.value[field]
            return !value || value === '' || value === null || value === undefined
        })

        console.log('Conversion - Missing fields:', missingFields)

        if (missingFields.length > 0) {
            console.log('Showing modal to complete missing fields for conversion')
            
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
            
            await nextTick()
            return
        }

        // All data complete, proceed with conversion
        console.log('All data complete, showing conversion modal')
        selectedLeadForConversion.value = lead.value.id
        selectedLeadData.value = lead.value

        await nextTick()
        if (convertModalRef.value) {
            
            convertModalRef.value.show()
        }
        return
    }

    const requiredFieldsMap = {
        3: ['salutation'],
        4: ['salutation','property_type_id','area_id','budget_from','budget_to','lead_type','property_status','purpose_buying','bedrooms','status_lead'],
        5: ['salutation','available_date'],
        7: ['salutation','branch'],
        8: ['why_lost_lead'],
        9: ['status_lead'],
        10: ['status_lead']
    }
    const alwaysRequiredFieldsMap = {
        9: ['status_lead'],
        10: ['status_lead']
    }
    const requiredFields = requiredFieldsMap[targetStageOrder] || []
    const alwaysFields = alwaysRequiredFieldsMap[targetStageOrder] || []
    
    const leadMissingFields = requiredFields.filter(f => !lead[f])
    
    const fieldsToShow = [...new Set([...leadMissingFields, ...alwaysFields])]
    if (targetStageOrder === 9 || targetStageOrder === 10) {
        if (!fieldsToShow.includes('status_lead')) {
            fieldsToShow.push('status_lead')
        }
    }
    if (fieldsToShow.length > 0 || [3,4,5,7,8,9,10].includes(targetStageOrder)) {
        console.log('Showing modal for stage order:', targetStageOrder, 'Missing fields:', leadMissingFields)
        
        pendingStageChange.value = {
            leadId: lead.value.id,
            targetStageId: stageId,
            targetStageName: stageName,
            targetStageOrder: targetStageOrder,
            originalStageId: lead.value.stage_id,
            leadData: { ...lead.value },
            isConversion: false
        }

        missingFieldsForLead.value = fieldsToShow
        showStageChangeModal.value = true
        
        await nextTick()
        return
    }

    // ✅ No missing fields, proceed with stage change
    await executeStageChange(stageId, lead.value.stage_id)
}

// Execute stage change API call (without modal)
const executeStageChange = async (newStageId, oldStageId) => {
    try {
        isUserAction.value = true
        const response = await api.post(`/leads/${lead.value.id}/change-stage`, {
            stage_id: newStageId
        })
        const updatedLeadData = response.data?.data || response.data
        if (updatedLeadData) {
            lead.value = { ...lead.value, ...updatedLeadData }
        }
        leadStageId.value = newStageId
        emit('lead-updated', lead.value)
        $showNotification('Stage updated successfully', 'success')
    } catch (error) {
        console.error('❌ Error updating stage:', error)
        $showNotification(error.response?.data?.message || 'Failed to update stage', 'error')
        leadStageId.value = oldStageId
    } finally {
        setTimeout(() => {
            isUserAction.value = false
        }, 500)
    }
}

// Handle stage change with reason from modal (نفس الـ Kanban بالضبط)
const handleStageChangeWithReason = async ({ leadId, targetStageId, reason, ...additionalData }) => {
    console.log('📝 handleStageChangeWithReason called:', { leadId, targetStageId, reason, additionalData })
    
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
        
        // ✅ إضافة salutation (الأهم)
        if (additionalData.salutation) {
            payload.salutation = additionalData.salutation
            console.log('✅ Adding salutation to payload:', additionalData.salutation)
        }
        
        // Add fields from modal
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
        if (additionalData.interaction_result) payload.interaction_result = additionalData.interaction_result
        if (additionalData.deal_name) payload.deal_name = additionalData.deal_name
        
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

        console.log('📤 Sending payload to backend:', JSON.stringify(payload, null, 2))

        // Send request
        const response = await api.post(`/leads/${leadId}/change-stage`, payload)
        
        console.log('✅ Backend response:', response.data)
        
        $showNotification(response.data?.message || 'Lead data updated successfully', 'success')
        
        // ✅ تحديث الـ lead data محلياً
        if (response.data?.data) {
            lead.value = { ...lead.value, ...response.data.data }
        } else {
            if (payload.salutation) lead.value.salutation = payload.salutation
            if (payload.stage_id) lead.value.stage_id = payload.stage_id
            if (payload.budget_from) lead.value.budget_from = payload.budget_from
            if (payload.budget_to) lead.value.budget_to = payload.budget_to
            if (payload.lead_type) lead.value.lead_type = payload.lead_type
            if (payload.property_status) lead.value.property_status = payload.property_status
            if (payload.area_id) lead.value.area_id = payload.area_id
            if (payload.property_type_id) lead.value.property_type_id = payload.property_type_id
            if (payload.bedrooms) lead.value.bedrooms = payload.bedrooms
            if (payload.purpose_buying) lead.value.purpose_buying = payload.purpose_buying
            if (payload.lead_source) lead.value.lead_source = payload.lead_source
            if (payload.available_date) lead.value.available_date = payload.available_date
            if (payload.branch) lead.value.branch = payload.branch
            if (payload.why_lost_lead) lead.value.why_lost_lead = payload.why_lost_lead
            
            if (payload.status_lead) lead.value.status_lead = payload.status_lead
            if (payload.status_lead_pool) lead.value.status_lead = payload.status_lead_pool
            if (payload.unqualified_status) lead.value.status_lead = payload.unqualified_status
            if (payload.deal_name) lead.value.deal_name = payload.deal_name
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
        
        // ✅ إعادة جلب البيانات للتأكد من التحديث
        await fetchLead()
        
        // If this was for conversion (stage 6), open conversion modal
        if (isConversion && targetStageOrder === 6) {
            console.log('Opening conversion modal')
            selectedLeadForConversion.value = leadId
            selectedLeadData.value = lead.value
            
            await nextTick()
            if (convertModalRef.value) {
                convertModalRef.value.show()
            }
        }
        
    } catch (error) {
        console.error('❌ Error in handleStageChangeWithReason:', error)
        const errorMessage = error.response?.data?.message || 
                            error.response?.data?.error || 
                            'Failed to update lead data'
        $showNotification(errorMessage, 'error')
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
        console.log('📝 Salutation value:', lead.value?.salutation)
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
        console.log('❌ ViewLeadModal: Real-time updates not available')
        return
    }

    try {
        const channel = window.Echo.private(`user.${user.id}`)
        
        echoListener.value = channel.listen('.lead.updated', (event) => {
            const leadData = event.lead?.data || event.lead
            if (leadData && leadData.id === props.leadId) {
                handleLeadUpdate(event, 'updated')
            }
        })
        
        echoAssignedListener.value = channel.listen('.lead.assigned', (event) => {
            const leadData = event.lead?.data || event.lead
            if (leadData && leadData.id === props.leadId) {
                handleLeadUpdate(event, 'assigned')
            }
        })
        
        console.log('✅ ViewLeadModal: Listeners initialized')
    } catch (error) {
        console.error('❌ ViewLeadModal: Failed to initialize Echo listeners:', error)
    }
}

const handleLeadUpdate = (event, eventType = 'unknown') => {
    const leadData = event.lead?.data || event.lead
    
    if (!leadData) return
    
    if (event.action_type === 'deleted') {
        $showNotification('This lead has been deleted', 'warning')
        show.value = false
    } else {
        lead.value = { ...lead.value, ...leadData }
        
        if (leadData.stage_id) {
            leadStageId.value = leadData.stage_id
        }
        
        emit('lead-updated', leadData)

        if (!shouldSuppressLeadUpdateNotification(event)) {
            const userName = event.user_name || 'Someone'
            const notificationMessage = eventType === 'assigned'
                ? `${userName} assigned this lead`
                : `${userName} updated this lead`

            $showNotification(notificationMessage, 'info')
        }
    }
}

const handleLeadUpdateFromTab = (updatedLeadData) => {
    console.log('📝 ViewLeadModal: Received lead update from GeneralTab:', updatedLeadData)
    lead.value = updatedLeadData
    
    if (updatedLeadData.stage_id) {
        leadStageId.value = updatedLeadData.stage_id
    } else if (updatedLeadData.stage?.id) {
        leadStageId.value = updatedLeadData.stage.id
    }
    
    emit('lead-updated', updatedLeadData)
}

const cleanup = () => {
    if (echoListener.value) {
        if (typeof echoListener.value.stopListening === 'function') {
            echoListener.value.stopListening('.lead.updated')
        }
        echoListener.value = null
    }
    
    if (echoAssignedListener.value) {
        if (typeof echoAssignedListener.value.stopListening === 'function') {
            echoAssignedListener.value.stopListening('.lead.assigned')
        }
        echoAssignedListener.value = null
    }
}

onMounted(() => {
    fetchStageOrders()
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
            initializeLeadListener()
        }
    } else {
        cleanup()
        activeTab.value = 'general'
    }
    emit('update:modelValue', val)
})

watch(() => props.leadId, (newLeadId, oldLeadId) => {
    if (!show.value) return
    if (!newLeadId || newLeadId === oldLeadId) return
    fetchLead()
    initializeLeadListener()
})

watch(lead, (newLead) => {
    if (lead.value && lead.value.stage_id) {
        leadStageId.value = lead.value.stage_id
    }
}, { immediate: true })

watch(leadStageId, async (newStageId, oldStageId) => {
    if (!isUserAction.value && newStageId !== oldStageId) {
        console.log('External stage update detected:', { newStageId, oldStageId })
    }
}, { immediate: false })

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
    overflow: visible;
    font-family: 'Montserrat', sans-serif;
    position: relative;
}

.modal-header-custom {
    background: #fff;
    position: relative;
}

.modal-title {
    flex: 1 1 auto;
    min-width: 0;
    font-size: 16px;
    font-weight: 600;
    color: #0B0736;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
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
    position: absolute;
    top: 8px;
    right: -65px;
    width: 64px;
    height: 49px;
    border: 1px solid rgba(115, 62, 135, 0.75);
    border-radius: 999px;
    background: var(--gradient-crm, linear-gradient(135deg, #0b0736 0%, #733e87 100%));
    color: #ffffff;
    font-size: 18px;
    line-height: 1;
    padding: 0;
    box-shadow: 0 8px 16px rgba(15, 23, 42, 0.2);
    z-index: -1;
    display: flex;
    justify-content: center;
    align-items: center;
    transition: filter 0.2s ease;
}

.close-btn iconify-icon {
    width: 16px;
    height: 16px;
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
    padding: 10px;
    font-size: 13px;
    font-weight: 500;
    color: #64748B;
    position: relative;
    cursor: pointer;
}

.tab-item.active {
    color: #0B0736;
}

.tab-item.active::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    width: 100%;
    height: 2px;
    background: #733E87;
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
    color: #0B0736;
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
    color: #0B0736;
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
    background: #0B0736;
    color: #fff;
    box-shadow: 0px 4px 8px rgba(1, 6, 44, 0.2);
}

.comment-box {
    background: #fff;
    border: 1px solid #E2E8F0 !important;
}

.btn-primary {
    background: #0B0736;
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

:deep(.kanban-mobile-fullscreen-modal .modal-content),
:deep(.kanban-mobile-fullscreen-modal .modal-body),
.modal-body-custom {
    overflow-x: hidden !important;
}

:deep(.kanban-mobile-fullscreen-modal .modal-content) {
    overflow: visible !important;
}

@media (max-width: 768px) {
    .close-btn,
    .view-lead-close-btn {
        position: relative !important;
        top: auto !important;
        right: auto !important;
        left: auto !important;
        transform: none;
        width: 44px !important;
        height: 44px !important;
        min-width: 44px !important;
        min-height: 44px !important;
        margin-left: auto;
        padding: 0;
        display: flex !important;
        justify-content: center;
        align-items: center;
        flex-shrink: 0;
        box-shadow: 0 2px 8px rgba(11, 7, 54, 0.15);
        border-radius: 999px;
        border: none;
        background: linear-gradient(135deg, #0b0736 0%, #733e87 100%);
        color: #ffffff !important;
        z-index: 10 !important;
    }

    .close-btn iconify-icon,
    .view-lead-close-btn iconify-icon {
        width: 20px !important;
        height: 20px !important;
        color: #ffffff !important;
    }

    .modal-header-custom {
        position: sticky;
        top: 0;
        z-index: 10;
        flex-shrink: 0;
        align-items: center;
        gap: 10px;
        padding: 10px 12px !important;
        margin-bottom: 8px;
    }

    .modal-title {
        font-size: 15px;
        line-height: 1.25;
        padding-right: 4px;
    }

    :deep(.kanban-mobile-fullscreen-modal) {
        margin: 0 !important;
        max-width: 100% !important;
        width: 100% !important;
        height: 100dvh !important;
    }
    :deep(.kanban-mobile-fullscreen-modal .modal-content) {
        height: 100dvh !important;
        border-radius: 0 !important;
    }
    :deep(.kanban-mobile-fullscreen-modal .modal-body) {
        height: 100dvh !important;
        padding: 0 !important;
    }
    .view-lead-modal-content {
        height: 100dvh;
        max-height: 100dvh;
        border-radius: 0 !important;
        padding: calc(8px + env(safe-area-inset-top, 0px)) 10px 10px !important;
        display: flex;
        flex-direction: column;
        background: #f8fbff;
        overflow: hidden;
    }
    .modal-body-custom {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        padding: 8px 4px 16px !important;
    }
    .modal-header-custom,
    .stage-selector-wrapper,
    .tabs-container {
        background: #fff;
        border-radius: 14px;
        border: 1px solid #eef2f7;
        padding-left: 12px !important;
        padding-right: 12px !important;
    }
    :deep(.stage-selector-wrapper .stage-text) {
        font-size: 11px !important;
    }
    .tabs-container {
        margin-top: 8px;
    }
    .details-content,
    .timeline-content,
    .history-content {
        border-radius: 14px;
        border: 1px solid #eef2f7;
        background: #fff;
        padding: 10px !important;
    }
}
.modal {
    z-index: 2000 !important;
}

.modal-backdrop {
    z-index: 1999 !important;
}

:deep(.view-lead-modal) {
    padding: 0 !important;
    height: 98vh;
    max-height: 98vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* تعديل الـ modal-content */
:deep(.modal-content) {
    height: 92vh;
    max-height: 92vh;
    border-radius: 16px;
}

/* المحتوى الداخلي */
.view-lead-modal-content {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #fff;
    font-family: 'Montserrat', sans-serif;
}

/* الأجزاء الثابتة */
.modal-header-custom,
.stage-selector-wrapper,
.tabs-container {
    flex-shrink: 0;
}

/* الجزء القابل للسكرول */
.modal-body-custom {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
}

.modal-body-custom::-webkit-scrollbar {
    width: 6px;
}

.modal-body-custom::-webkit-scrollbar-track {
    background: #F1F5F9;
    border-radius: 10px;
}

.modal-body-custom::-webkit-scrollbar-thumb {
    background: #CBD5E1;
    border-radius: 10px;
}

.modal-body-custom::-webkit-scrollbar-thumb:hover {
    background: #94A3B8;
}

/* للشاشات الصغيرة */
@media (max-width: 768px) {
    :deep(.view-lead-modal),
    :deep(.modal-content) {
        height: 100dvh;
        max-height: 100dvh;
        border-radius: 0;
    }
}
</style>
<style>
.modal#view-lead-modal .modal-dialog {
    max-width: min(1200px, 95vw) !important;
    width: min(1200px, 95vw) !important;
    max-height: 98vh !important;
    margin: 1vh auto !important;
}

.view-lead-modal {
    padding: 0 !important;
    height: 98vh;
    max-height: 100vh;
    display: flex;
    flex-direction: column;
}

@media (max-width: 768px) {
    .view-lead-modal {
        height: 100dvh !important;
        max-height: 100dvh !important;
        overflow: hidden !important;
    }

    .modal#view-lead-modal .modal-dialog {
        margin: 0 !important;
        max-width: 100% !important;
        width: 100% !important;
        height: 100dvh !important;
        max-height: 100dvh !important;
    }

    .modal#view-lead-modal .modal-content {
        height: 100dvh !important;
        max-height: 100dvh !important;
        border-radius: 0 !important;
        overflow: hidden !important;
    }

    /* Teleported modal: ensure close button always visible on mobile */
    #view-lead-modal .view-lead-close-btn {
        position: relative !important;
        top: auto !important;
        right: auto !important;
        left: auto !important;
        width: 44px !important;
        height: 44px !important;
        min-width: 44px !important;
        flex-shrink: 0 !important;
        margin-left: auto !important;
        z-index: 20 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        border: none !important;
        border-radius: 999px !important;
        background: linear-gradient(135deg, #0b0736 0%, #733e87 100%) !important;
        color: #fff !important;
        box-shadow: 0 2px 10px rgba(11, 7, 54, 0.25) !important;
    }

    #view-lead-modal .view-lead-close-btn iconify-icon,
    #view-lead-modal .view-lead-close-btn svg {
        color: #fff !important;
        width: 20px !important;
        height: 20px !important;
    }

    #view-lead-modal .modal-header-custom {
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
        overflow: hidden !important;
        flex-shrink: 0 !important;
    }

    #view-lead-modal .modal-title {
        flex: 1 1 auto !important;
        min-width: 0 !important;
        max-width: calc(100% - 54px) !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
    }

    /* Hide chat FAB behind lead modal on mobile */
    body:has(#view-lead-modal.show) .chat-floating-btn {
        display: none !important;
    }
}
</style>