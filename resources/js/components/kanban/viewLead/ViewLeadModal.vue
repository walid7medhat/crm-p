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
         @hidden="handleClose"
    >
        <div v-if="show" class="view-lead-modal-content p-3 pb-0">
            <!-- Header -->
            <div class="modal-header-custom d-flex align-items-center gap-2 px-1" :class="{ 'is-above-requirement': qualifiedRequirementBlocking }">
                <!-- Lead name — inline edit, same UX as the deal title -->
                <template v-if="!isEditingName">
                    <div class="lead-title-read-row d-flex align-items-center gap-2 min-w-0">
                        <span
                            class="modal-title"
                            :class="{ 'lead-title-editable': canEditName }"
                            @click="canEditName && startEditName()"
                        >{{ lead?.lead_name }}</span>
                        <button
                            v-if="canEditName"
                            type="button"
                            class="lead-title-edit-btn"
                            aria-label="Edit lead name"
                            title="Edit lead name"
                            @click.stop="startEditName"
                        >
                            <span class="lead-title-edit-btn-inner">
                                <iconify-icon icon="lucide:pencil" class="lead-title-edit-icon" />
                            </span>
                        </button>
                    </div>
                </template>
                <div v-else class="lead-title-input-shell min-w-0">
                    <input
                        ref="leadNameInputRef"
                        v-model="leadNameInput"
                        type="text"
                        class="view-lead-title-input"
                        placeholder="Lead name"
                        maxlength="255"
                        :disabled="savingName"
                        @keyup.enter="saveLeadName"
                        @blur="onLeadNameBlur"
                        @keydown.esc.prevent="cancelEditName"
                    />
                </div>
                <button type="button" class="close-btn view-lead-close-btn" aria-label="Close lead" @click="show = false">
                    <iconify-icon icon="lucide:x"></iconify-icon>
                </button>
            </div>

            <!-- Stages Progress -->
            <StageSelector v-model="leadStageId"
            :require-validation="true"
            :disabled="disableStageChange"
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
                <BrandLoader v-if="isLoadingLead && !lead" variant="inline" label="Opening lead" />

                <!-- General Tab Content -->
                <GeneralTab 
                    v-else-if="activeTab === 'general' && lead" 
                    :lead="lead" 
                    :stage-id="leadStageId"
                    @update:lead="handleLeadUpdateFromTab"
                />

                <!-- History Tab Content -->
                <HistoryTab v-else-if="activeTab === 'history' && canViewHistory && lead"  :lead="lead" :is-active="activeTab === 'history'" />
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
            :mandatory="pendingStageChange?.requirementOnly === true"
            @submit="handleStageChangeWithReason"
            @closed="clearPendingStageChange"
            @close-lead="show = false"
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
import { useRoute, useRouter } from 'vue-router'
import { BModal, BDropdown } from 'bootstrap-vue-3'
import StageSelector from '../shared/StageSelector.vue'
import StageChangeReasonModal from '../leadList/StageChangeReasonModal.vue'
import ConvertLeadModal from '../leadList/ConvertLeadModal.vue'

import BrandLoader from '@/components/layout/BrandLoader.vue'
import GeneralTab from './GeneralTab.vue'
import HistoryTab from './HistoryTab.vue'
import api from '@/plugins/axios'
import { shouldSuppressLeadUpdateNotification } from '@/utils/leadRealtimeNotifications.js'



const route = useRoute()
const router = useRouter()

const props = defineProps({
    modelValue: Boolean,
    leadId: {
        type: [Number, String],
        default: null
    },
    /** Local card/lead snapshot so the modal can paint before GET finishes. */
    initialLead: {
        type: Object,
        default: null
    },
    /** Use a higher value when opening on top of another modal (e.g. view deal). */
    zIndex: {
        type: Number,
        default: 1040
    },
    /**
     * This component is mounted twice at once in the app — globally in App.vue, and
     * locally inside LeadPool.vue — and both instances would otherwise read/write the
     * SAME shared route.query.lead, stepping on each other's open/close state (one
     * instance's own show/leadId gets reset by the other reacting to a URL change it
     * didn't cause). Only one instance should own that query param; the other passes
     * syncUrl=false and manages its own persistence separately (see LeadPool.vue).
     */
    syncUrl: {
        type: Boolean,
        default: true
    },
    /** Lead Pool leads shouldn't be movable from inside this modal. */
    disableStageChange: {
        type: Boolean,
        default: false
    }
})

const lead = ref(null)
const isLoadingLead = ref(false)
const emit = defineEmits(['update:modelValue', 'stage-updated', 'lead-updated', 'update:leadId'])

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
const leadPoolStageId = ref(null)
const leadPoolStage = ref(null)
const selectedLeadForConversion = ref(null)
const selectedLeadData = ref(null)
const convertModalRef = ref(null)

const QUALIFIED_REQUIREMENT_FIELDS = [
    'property_type_id',
    'area_id',
    'budget_from',
    'budget_to',
    'lead_type',
    'property_status',
    'purpose_buying',
    'bedrooms',
    'status_lead',
]
const QUAL_META_KIND = 'qualification_meta'

const qualifiedRequirementBlocking = computed(() =>
    showStageChangeModal.value && pendingStageChange.value?.requirementOnly === true
)

const normalizeRoleName = (role) => {
    if (!role) return ''
    const raw = typeof role === 'string' ? role : (role.name || role.role || '')
    return String(raw).trim().toLowerCase().replace(/[\s-]+/g, '_')
}

const isQualifiedRequirementRole = (currentUser) => {
    const roles = Array.isArray(currentUser?.roles) ? currentUser.roles.map(normalizeRoleName) : []
    if (currentUser?.role) roles.push(normalizeRoleName(currentUser.role))
    if (currentUser?.role_name) roles.push(normalizeRoleName(currentUser.role_name))
    return roles.some((role) => {
        if (!role) return false
        if (role === 'manager') return true
        if (role === 'team_lead' || role === 'team_leader') return true
        return role === 'sales' || role.includes('sales')
    })
}

const isQualifiedLeadStage = (currentLead) => {
    const name = String(currentLead?.stage?.name || currentLead?.stage_name || '').trim().toLowerCase()
    if (name) return name === 'qualified'
    const order = Number(currentLead?.stage?.order ?? currentLead?.stage_order)
    return order === 4
}

const requirementRowHasContent = (req) => {
    if (!req || req._kind === QUAL_META_KIND) return false
    const hasBudget = Number(req.budget_from) > 0 || Number(req.budget_to) > 0 || Number(req.budget) > 0
    return Boolean(
        req.area_label || req.area_id || req.area ||
        req.property_type_label || req.property_type_id || req.property_type ||
        req.lead_type || req.property_status ||
        (req.bedrooms !== null && req.bedrooms !== undefined && req.bedrooms !== '') ||
        hasBudget || req.purpose_buying
    )
}

const hasAtLeastOneClientRequirement = (currentLead) => {
    if (!currentLead) return false
    const extras = Array.isArray(currentLead.extra_client_requirements)
        ? currentLead.extra_client_requirements
        : []
    if (extras.some(requirementRowHasContent)) return true
    return requirementRowHasContent({
        area: currentLead.area,
        area_id: currentLead.area_id,
        property_type: currentLead.property_type,
        property_type_id: currentLead.property_type_id,
        lead_type: currentLead.lead_type,
        property_status: currentLead.property_status,
        bedrooms: currentLead.bedrooms,
        budget_from: currentLead.budget_from,
        budget_to: currentLead.budget_to,
        budget: currentLead.budget,
        purpose_buying: currentLead.purpose_buying,
    })
}

const blockQualifiedRequirementEscape = (event) => {
    if (!qualifiedRequirementBlocking.value) return
    if (event.key !== 'Escape') return
    event.preventDefault()
    event.stopPropagation()
    show.value = false
}

const maybeOpenQualifiedRequirementGate = () => {
    const currentLead = lead.value
    if (!show.value || !currentLead?.id) return
    if (qualifiedRequirementBlocking.value) return
    if (!isQualifiedRequirementRole(user.value)) return
    if (!isQualifiedLeadStage(currentLead)) return
    if (hasAtLeastOneClientRequirement(currentLead)) return

    pendingStageChange.value = {
        leadId: currentLead.id,
        targetStageId: currentLead.stage_id,
        targetStageName: currentLead?.stage?.name || 'Qualified',
        // Order 4 selects the existing Qualified field set (Hot/Warm/Cold). The lead stage is not changed.
        targetStageOrder: 4,
        originalStageId: currentLead.stage_id,
        leadData: { ...currentLead },
        isConversion: false,
        requirementOnly: true,
    }
    missingFieldsForLead.value = [...QUALIFIED_REQUIREMENT_FIELDS]
    showStageChangeModal.value = true
    scrollToCommentList()
}

/**
 * While the mandatory Client Requirement panel is docked on the left, bring the lead's
 * comments into view behind it. Comments load async, so wait (up to ~4s) for the list.
 */
const scrollToCommentList = (attempt = 0) => {
    if (!show.value || !qualifiedRequirementBlocking.value) return
    const list = document.querySelector('#view-lead-modal .lead-comment-list')
        || document.querySelector('.view-lead-modal .lead-comment-list')
    if (list) {
        list.scrollIntoView({ behavior: 'smooth', block: 'start' })
        return
    }
    if (attempt < 20) setTimeout(() => scrollToCommentList(attempt + 1), 200)
}

function handleLeadConverted(deal) {
    // Let the Kanban board move/remove this lead's card immediately (it just became
    // a deal) instead of relying on a websocket broadcast or a full board refetch.
    const updatedLead = deal?._lead
    if (updatedLead?.id) {
        lead.value = { ...lead.value, ...updatedLead }
        emit('lead-updated', lead.value)
    }
    selectedLeadForConversion.value = null
    selectedLeadData.value = null
    show.value = false
    emit('update:modelValue', false)
    window.dispatchEvent(new CustomEvent('kanban-open-converted-deal', { detail: deal }))
}

const canViewHistory = computed(() => {
    if (!user.value || !lead.value) return false

    // Mirrors the backend's history() gate (LeadController.php) — admin/super_admin
    // always, branch_admin for leads in their own branch (server enforces the actual
    // branch check; showing the tab here just offers it, same as it already does for
    // 'admin' without re-deriving admin's own scoping client-side).
    const isAdminUser =
        user.value.roles?.includes('super_admin') ||
        user.value.roles?.includes('admin') ||
        user.value.roles?.includes('branch_admin')

    const isResponsible =
        lead.value.responsible_person_id === user.value.id

    return isAdminUser || isResponsible
})

const switchTab = (tab) => {
    activeTab.value = tab
}

// ================= Inline lead-name edit (like the deal title) =================
const isEditingName = ref(false)
const leadNameInput = ref('')
const leadNameInputRef = ref(null)
const savingName = ref(false)
let leadNameBlurTimer = null

// Same gate as LeadController::updateName (leads-edit + canViewLead on the server);
// sales outside the listing team can't rename leads.
const canEditName = computed(() => {
    if (!lead.value?.id) return false
    const perms = user.value?.permissions || []
    const roles = user.value?.roles || []
    if (roles.includes('super_admin')) return true
    const isHigherRole = ['admin', 'branch_admin', 'manager', 'team_lead'].some((r) => roles.includes(r))
    if (roles.includes('sales') && !isHigherRole && !user.value?.is_listing_team) return false
    return perms.includes('leads-edit')
})

const startEditName = () => {
    if (!canEditName.value) return
    leadNameInput.value = lead.value?.lead_name || ''
    isEditingName.value = true
    nextTick(() => {
        leadNameInputRef.value?.focus()
        leadNameInputRef.value?.select()
    })
}

const cancelEditName = () => {
    if (leadNameBlurTimer) {
        clearTimeout(leadNameBlurTimer)
        leadNameBlurTimer = null
    }
    leadNameInput.value = lead.value?.lead_name || ''
    isEditingName.value = false
}

const onLeadNameBlur = () => {
    if (leadNameBlurTimer) clearTimeout(leadNameBlurTimer)
    leadNameBlurTimer = setTimeout(() => {
        leadNameBlurTimer = null
        if (show.value && isEditingName.value) saveLeadName()
    }, 120)
}

const saveLeadName = async () => {
    if (leadNameBlurTimer) {
        clearTimeout(leadNameBlurTimer)
        leadNameBlurTimer = null
    }
    if (!lead.value?.id || savingName.value) return

    const newName = String(leadNameInput.value || '').trim()
    const oldName = lead.value.lead_name || ''

    if (!newName) {
        $showNotification('Lead name cannot be empty', 'error')
        cancelEditName()
        return
    }
    if (newName === oldName) {
        isEditingName.value = false
        return
    }

    savingName.value = true
    try {
        await api.patch(`/leads/${lead.value.id}/name`, { lead_name: newName })
        lead.value = { ...lead.value, lead_name: newName }
        emit('lead-updated', lead.value)
        $showNotification('Lead name updated', 'success')
    } catch (error) {
        console.error('Error updating lead name:', error)
        $showNotification(error.response?.data?.message || 'Failed to update lead name', 'error')
    } finally {
        savingName.value = false
        isEditingName.value = false
    }
}

// Fetch stage orders
const fetchStageOrders = async () => {
    try {
        const response = await api.get('/stages')
        let stages = []
        const payload = response.data?.data

        if (payload?.data && Array.isArray(payload.data)) {
            stages = payload.data
        } else if (Array.isArray(payload)) {
            stages = payload
        } else if (Array.isArray(response.data)) {
            stages = response.data
        }

        if (!Array.isArray(stages)) {
            stages = []
        }

        const map = {}
        const poolsByName = []
        const poolsByOrder = []
        stages.forEach(stage => {
            if (stage && stage.id) {
                map[stage.id] = stage.order || 0
                const name = String(stage.name || '').toLowerCase().replace(/[\s_-]+/g, '')
                if (name === 'leadpool') poolsByName.push(stage)
                if (Number(stage.order) === 9) poolsByOrder.push(stage)
            }
        })
        const preferOriginal = (list) =>
            [...list].sort((a, b) => Number(a.id) - Number(b.id))[0] || null
        stageOrderMap.value = map
        leadPoolStage.value = preferOriginal(poolsByName) || preferOriginal(poolsByOrder)
        leadPoolStageId.value = leadPoolStage.value?.id || null
        console.log('Stage order map loaded:', stageOrderMap.value)
    } catch (error) {
        console.error('Error fetching stage orders:', error)
        stageOrderMap.value = {}
    }
}

// Handle stage change request from StageSelector
const handleStageChangeRequest = async ({ stageId, stageName, stageOrder }) => {
    if (props.disableStageChange) return
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
            convertModalRef.value.show(lead.value.id, lead.value)
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

const saveQualifiedClientRequirement = async (form) => {
    const currentLead = lead.value
    if (!currentLead?.id) return false

    const id = `ecr-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`
    const now = new Date().toISOString()
    const row = {
        id,
        created_at: now,
        updated_at: now,
        area_id: form.area_id ?? null,
        area_label: form.area || '',
        property_type_id: form.property_type_id ?? null,
        property_type_label: form.property_type || '',
        lead_type: form.lead_type ?? null,
        property_status: form.property_status ?? null,
        bedrooms: form.bedrooms ?? null,
        status_lead: form.lead_status ?? null,
        budget_from: form.budget_from ?? null,
        budget_to: form.budget_to ?? null,
        purpose_buying: form.purpose_buying ?? null,
        selected_for_qualification: true,
    }

    const existing = Array.isArray(currentLead.extra_client_requirements)
        ? currentLead.extra_client_requirements.filter((item) => item?._kind !== QUAL_META_KIND)
        : []
    const persisted = [
        ...existing.map((item) => ({ ...item, selected_for_qualification: false })),
        row,
        { id: '__qualification_meta__', _kind: QUAL_META_KIND, source: id },
    ]

    try {
        const response = await api.put(`/leads/${currentLead.id}/extra-client-requirements`, {
            extra_client_requirements: persisted,
        })
        const savedLead = response?.data?.data
        lead.value = {
            ...currentLead,
            ...(savedLead && typeof savedLead === 'object' ? savedLead : {}),
            extra_client_requirements: savedLead?.extra_client_requirements || persisted,
        }
        emit('lead-updated', lead.value)
        $showNotification('Client requirement saved', 'success')
        return true
    } catch (error) {
        const message = error.response?.data?.message || 'Failed to save client requirement'
        $showNotification(message, 'error')
        return false
    }
}

const moveQualifiedLeadToPool = async (additionalData) => {
    const stageId = leadPoolStageId.value
    if (!stageId || !lead.value?.id) {
        $showNotification('Lead Pool stage was not found', 'error')
        return false
    }
    try {
        const response = await api.post(`/leads/${lead.value.id}/change-stage`, {
            stage_id: stageId,
            reason: additionalData.reason,
            status_lead_pool: additionalData.lead_status,
        })
        const savedLead = response.data?.data || response.data
        const poolStage = leadPoolStage.value
        if (savedLead && typeof savedLead === 'object') {
            lead.value = {
                ...lead.value,
                ...savedLead,
                stage_id: poolStage?.id || savedLead.stage_id,
                stage: poolStage
                    ? {
                        ...(lead.value?.stage || {}),
                        id: poolStage.id,
                        name: poolStage.name,
                        order: poolStage.order,
                        color: poolStage.color,
                    }
                    : lead.value?.stage,
            }
            if (lead.value.stage_id) leadStageId.value = lead.value.stage_id
        }
        emit('lead-updated', lead.value)
        $showNotification('Lead moved to Lead Pool', 'success')
        return true
    } catch (error) {
        const message = error.response?.data?.message || 'Failed to move lead to Lead Pool'
        $showNotification(message, 'error')
        return false
    }
}

// Handle stage change with reason from modal (نفس الـ Kanban بالضبط)
const handleStageChangeWithReason = async ({ leadId, targetStageId, reason, ...additionalData }) => {
    if (pendingStageChange.value?.requirementOnly) {
        const finish = additionalData.__requirementSaved
        const saved = additionalData.moveToLeadPool
            ? await moveQualifiedLeadToPool(additionalData)
            : await saveQualifiedClientRequirement(additionalData)
        if (typeof finish === 'function') finish(saved)
        return saved
    }
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

        // Apply optimistically and close modal immediately (same as Kanban).
        if (payload.salutation) lead.value.salutation = payload.salutation
        if (payload.stage_id) lead.value.stage_id = payload.stage_id
        if (payload.budget_from) lead.value.budget_from = payload.budget_from
        if (payload.budget_to) lead.value.budget_to = payload.budget_to
        if (payload.lead_type) lead.value.lead_type = payload.lead_type
        if (payload.property_status) lead.value.property_status = payload.property_status
        if (payload.area_id) lead.value.area_id = payload.area_id
        if (payload.property_type_id) lead.value.property_type_id = payload.property_type_id
        if (payload.bedrooms !== undefined && payload.bedrooms !== '') lead.value.bedrooms = payload.bedrooms
        if (payload.purpose_buying) lead.value.purpose_buying = payload.purpose_buying
        if (payload.lead_source) lead.value.lead_source = payload.lead_source
        if (payload.available_date) lead.value.available_date = payload.available_date
        if (payload.branch) lead.value.branch = payload.branch
        if (payload.why_lost_lead) lead.value.why_lost_lead = payload.why_lost_lead
        if (payload.status_lead) lead.value.status_lead = payload.status_lead
        if (payload.status_lead_pool) lead.value.status_lead = payload.status_lead_pool
        if (payload.unqualified_status) lead.value.status_lead = payload.unqualified_status
        if (payload.deal_name) lead.value.deal_name = payload.deal_name
        if (payload.interaction_result) lead.value.interaction_result = payload.interaction_result
        if (additionalData.area) lead.value.area = additionalData.area
        if (additionalData.property_type) lead.value.property_type = additionalData.property_type

        if (targetStageId) {
            leadStageId.value = targetStageId
        }

        showStageChangeModal.value = false
        $showNotification('Lead stage updated successfully', 'success')
        emit('lead-updated', lead.value)
        clearPendingStageChange()

        // Open convert picker immediately — don't wait for change-stage to finish.
        if (isConversion && targetStageOrder === 6) {
            selectedLeadForConversion.value = leadId
            selectedLeadData.value = lead.value
            nextTick(() => {
                if (convertModalRef.value) {
                    convertModalRef.value.show(leadId, lead.value)
                }
            })
        }

        try {
            const response = await api.post(`/leads/${leadId}/change-stage`, {
                ...payload,
                ...(additionalData.activity_title && { activity_title: additionalData.activity_title }),
                ...(additionalData.activity_reminder_date && { activity_reminder_date: additionalData.activity_reminder_date }),
                ...(additionalData.activity_reminders && { activity_reminders: additionalData.activity_reminders }),
            })

            if (response.data?.data) {
                lead.value = { ...lead.value, ...response.data.data }
                emit('lead-updated', lead.value)
            }

            // Background refresh — do not block the UI
            fetchLead({ silent: true }).catch(() => {})
        } catch (error) {
            console.error('❌ Error in handleStageChangeWithReason:', error)
            const errorMessage = error.response?.data?.message ||
                                error.response?.data?.error ||
                                'Failed to update lead data'
            $showNotification(errorMessage, 'error')
            // Refresh to restore authoritative state after failed optimistic update
            await fetchLead()
            throw error
        }
        
    } catch (error) {
        console.error('❌ Error preparing stage change:', error)
        throw error
    }
}

const clearPendingStageChange = () => {
    pendingStageChange.value = null
    missingFieldsForLead.value = []
}

let fetchLeadInFlight = null
let fetchLeadInFlightId = null
let fetchLeadGeneration = 0

const fetchLead = async ({ silent = false } = {}) => {
    console.log('[ViewLeadModal] fetchLead called', { propsLeadId: props.leadId })
    if (!props.leadId) return
    const leadIdNum = Number(props.leadId)

    if (fetchLeadInFlight && fetchLeadInFlightId === leadIdNum) {
        return fetchLeadInFlight
    }

    const requestGeneration = ++fetchLeadGeneration
    fetchLeadInFlightId = leadIdNum

    const seed = props.initialLead
    const seedMatches = seed && Number(seed.id) === leadIdNum
    // Paint from local card data synchronously so the modal body appears on the same frame as open.
    if (seedMatches) {
        if (!lead.value || Number(lead.value.id) !== leadIdNum) {
            lead.value = { ...seed }
        } else {
            lead.value = { ...lead.value, ...seed }
        }
        if (seed.stage_id) leadStageId.value = seed.stage_id
    } else if (!lead.value || Number(lead.value.id) !== leadIdNum) {
        lead.value = null
    }

    if (!silent) {
        isLoadingLead.value = !lead.value
    }

    fetchLeadInFlight = (async () => {
        if (requestGeneration !== fetchLeadGeneration || Number(props.leadId) !== leadIdNum) {
            return
        }

        try {
            const response = await api.get(`/leads/${leadIdNum}`)
            if (requestGeneration !== fetchLeadGeneration || Number(props.leadId) !== leadIdNum) {
                return
            }
            const fresh = response.data.data
            if (fresh) {
                lead.value = lead.value ? { ...lead.value, ...fresh } : fresh
                if (fresh.stage_id) leadStageId.value = fresh.stage_id
                maybeOpenQualifiedRequirementGate()
            }
        } catch (error) {
            if (requestGeneration !== fetchLeadGeneration || Number(props.leadId) !== leadIdNum) {
                return
            }
            console.error('❌ Error fetching lead:', error)
            if (error?.response?.status === 403) {
                // Never leave a half-populated modal showing (e.g. seeded from a kanban
                // card thumbnail) for a lead this user isn't actually authorized to view.
                lead.value = null
                $showNotification(
                    error.response?.data?.message || 'You do not have permission to view this lead',
                    'error'
                )
                show.value = false
            } else if (!lead.value) {
                $showNotification('Failed to load lead details', 'error')
            }
        } finally {
            if (requestGeneration === fetchLeadGeneration && Number(props.leadId) === leadIdNum) {
                isLoadingLead.value = false
            }
        }
    })()

    try {
        await fetchLeadInFlight
    } finally {
        if (fetchLeadInFlightId === leadIdNum) {
            fetchLeadInFlight = null
            fetchLeadInFlightId = null
        }
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
const checkUrlForLead = () => {
  console.log('[ViewLeadModal] checkUrlForLead', { syncUrl: props.syncUrl, routeQueryLead: route.query.lead, showValue: show.value, routePath: route.path })
  if (!props.syncUrl) return
  const leadIdFromUrl = route.query.lead
  if (leadIdFromUrl && !show.value) {
    const numericId = Number(leadIdFromUrl)
    if (!isNaN(numericId) && numericId > 0) {
      if (props.leadId !== numericId) {
        emit('update:leadId', numericId)
      }
      show.value = true
      console.log('[ViewLeadModal] checkUrlForLead -> show set true', { numericId })
    }
  }
}

onMounted(() => {
    fetchStageOrders()
    checkUrlForLead()
    document.addEventListener('keydown', blockQualifiedRequirementEscape, true)
})

onUnmounted(() => {
    document.removeEventListener('keydown', blockQualifiedRequirementEscape, true)
    cleanup()
})

const handleClose = () => {
  show.value = false
  if (props.syncUrl && route.query.lead) {
    // No `path` — this modal is embedded on more than one page (Kanban, Lead Pool inside
    // kanban_deal, …), so pushing a query-only location keeps whichever page it's actually
    // on instead of always redirecting to /kanban.
    router.push({
      query: {}
    }).catch(() => {})
  }
}
const showWithLeadId = (leadId) => {
  if (!leadId) return

  const numericId = Number(leadId)
  if (isNaN(numericId) || numericId <= 0) return

  emit('update:leadId', numericId)

  if (props.syncUrl) {
    router.push({
      query: { lead: numericId }
    }).catch(() => {})
  }

  show.value = true
}

const hideModal = () => {
  show.value = false

  if (props.syncUrl && route.query.lead) {
    router.push({
      query: {}
    }).catch(() => {})
  }
}

watch(() => route.query.lead, (newLeadId) => {
  console.log('[ViewLeadModal] watch route.query.lead', { syncUrl: props.syncUrl, newLeadId, showValue: show.value, propsLeadId: props.leadId })
  if (!props.syncUrl) return
  if (newLeadId && !show.value) {
    const numericId = Number(newLeadId)
    if (!isNaN(numericId) && numericId > 0) {
      // إذا كان leadId مختلف، قم بتحديثه
      if (props.leadId !== numericId) {
        emit('update:leadId', numericId)
      }
      show.value = true
      console.log('[ViewLeadModal] watch route.query.lead -> show set true', { numericId })
    }
  }
}, { immediate: true })

watch(() => props.modelValue, (val) => {
    show.value = val
})

// This modal is embedded on more than one page (Kanban, Lead Pool inside kanban_deal, …).
// Capture whichever path it's actually opened on so the "navigated away entirely" guard
// below compares against reality instead of an assumed fixed host page.
const modalHostPath = ref(route.path)

// Navigating away entirely (e.g. "More properties" / a matching listing card
// inside GeneralTab) should just close the modal — not fight the pending
// navigation with the redirect below (route.query.lead is already gone on the
// new route by the time this fires, so that guard no-ops).
watch(() => route.path, (newPath) => {
    if (show.value && newPath !== modalHostPath.value) {
        show.value = false
    }
})


watch(show, (val, oldVal) => {
  console.log('[ViewLeadModal] watch show', { val, oldVal, propsLeadId: props.leadId, syncUrl: props.syncUrl, routePath: route.path })
  if (val) {
    modalHostPath.value = route.path
    if (props.leadId) {
      fetchLead()
      initializeLeadListener()
    } else {
      console.log('[ViewLeadModal] watch show -> val true but props.leadId falsy, fetchLead NOT called')
    }
  } else if (oldVal) {
    if (pendingStageChange.value?.requirementOnly) {
      showStageChangeModal.value = false
      clearPendingStageChange()
    }
    fetchLeadGeneration++
    cleanup()
    cancelEditName()
    activeTab.value = 'general'
    if (props.syncUrl && route.query.lead) {
      router.push({
        query: {}
      }).catch(() => {})
    }
  }
  emit('update:modelValue', val)
}, { immediate: true })


watch(() => props.leadId, (newLeadId, oldLeadId) => {
    if (!show.value) return
    if (!newLeadId || newLeadId === oldLeadId) return
    if (props.syncUrl) {
      // تحديث الرابط عند تغيير leadId
      router.push({
          query: { lead: newLeadId }
      }).catch(() => {})
    }
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
defineExpose({
  show: showWithLeadId,
  hide: hideModal,
  showWithLeadId,
  hideModal,
  open: showWithLeadId,
  close: hideModal
})
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

.modal-header-custom.is-above-requirement {
    z-index: 13000;
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

/* Inline lead-name edit — mirrors the deal title styles in ViewDealModal */
.lead-title-read-row {
    flex: 1 1 auto;
    min-width: 0;
    padding: 2px 0;
}

.lead-title-read-row .modal-title {
    flex: 0 1 auto;
}

.lead-title-editable {
    cursor: text;
}

.lead-title-edit-btn {
    flex-shrink: 0;
    border: none;
    padding: 0;
    background: transparent;
    cursor: pointer;
    border-radius: 11px;
    line-height: 0;
}

.lead-title-edit-btn-inner {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 11px;
    border: 1px solid #c7d2fe;
    background: linear-gradient(155deg, #eef2ff 0%, #e0e7ff 48%, #c7d2fe 100%);
    color: #312e81;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06), inset 0 1px 0 rgba(255, 255, 255, 0.85);
    transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.lead-title-edit-btn:hover .lead-title-edit-btn-inner {
    color: #3730a3;
    box-shadow: 0 6px 16px rgba(99, 102, 241, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.9);
    transform: translateY(-1px);
}

.lead-title-edit-icon {
    font-size: 18px;
}

.lead-title-input-shell {
    flex: 0 1 auto;
    padding: 2px 0;
    width: min(440px, calc(100vw - 210px));
    max-width: 100%;
}

.view-lead-title-input {
    display: block;
    width: 100%;
    box-sizing: border-box;
    font-size: 16px;
    font-weight: 600;
    font-family: 'Montserrat', sans-serif;
    color: #0B0736;
    line-height: 1.35;
    padding: 6px 14px;
    border-radius: 11px;
    border: 1px solid #e2e8f0;
    background: #fff;
    outline: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.view-lead-title-input:focus {
    border-color: #a5b4fc;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
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
    background: var(--gradient-crm, linear-gradient(135deg, #6b21a8 0%, #733e87 100%));
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
    background: #6b21a8;
    color: #fff;
    box-shadow: 0px 4px 8px rgba(1, 6, 44, 0.2);
}

.comment-box {
    background: #fff;
    border: 1px solid #E2E8F0 !important;
}

.btn-primary {
    background: #6b21a8;
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
        background: linear-gradient(135deg, #6b21a8 0%, #733e87 100%);
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
        background: linear-gradient(135deg, #6b21a8 0%, #733e87 100%) !important;
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