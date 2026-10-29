<template>
    <Teleport to="body">
        <!-- Backdrop -->
        <div
            v-if="show"
            class="duplicate-leads-backdrop"
            :class="{ 'duplicate-leads-backdrop--sheet': isSheet }"
            @click.stop="show = false"
        ></div>

        <!-- Dropdown Popup (bottom sheet on mobile) -->
        <div
            v-if="show"
            ref="popupRef"
            class="duplicate-leads-dropdown"
            :class="{ 'duplicate-leads-dropdown--sheet': isSheet }"
            :style="isSheet ? null : popupStyle"
            @click.stop
        >
            <div class="duplicate-leads-modal-content">
                <div v-if="isSheet" class="sheet-handle" aria-hidden="true"></div>
                <!-- Header -->
                <div class="modal-header-custom d-flex justify-content-between align-items-center py-3 border-bottom">
                    <picture class="modal-title mb-0">Duplicate Leads ({{ duplicateLeads.length }})</picture>
                    <div class="header-actions d-flex align-items-center gap-2">
                        <button
                            v-if="showMergeAction"
                            type="button"
                            class="merge-btn-3d"
                            @click.stop="startMerge"
                        >
                            <span class="merge-btn-3d__shine" aria-hidden="true"></span>
                            <iconify-icon icon="lucide:git-merge" class="merge-btn-3d__icon"></iconify-icon>
                            <span>Merge</span>
                        </button>
                        <button class="close-btn" @click="show = false" aria-label="Close">
                            <iconify-icon icon="lucide:x"></iconify-icon>
                        </button>
                    </div>
                </div>
            
                <!-- Content -->
                <div class="modal-body-custom">
                    <div v-if="loading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    
                    <div v-else-if="error" class="text-center py-5">
                        <p class="text-danger">{{ error }}</p>
                    </div>

                    <div v-else-if="duplicateLeads.length === 0" class="text-center py-5">
                        <p class="text-secondary">No duplicate leads found</p>
                    </div>

                    <div v-else class="duplicate-leads-list d-flex flex-column">
                        <div 
                            v-for="lead in duplicateLeads" 
                            :key="lead.id"
                            class="duplicate-lead-card bg-white radius-12 cursor-pointer"
                            :class="{ 'duplicate-lead-card--locked': lead.can_view === false }"
                            @click="openLeadView(lead)"
                        >
                            <!-- Lead Title -->
                            <p class="lead-title">{{ lead.lead_name }}</p>

                            <div v-if="lead.can_view === false" class="other-branch-badge">
                                <iconify-icon icon="lucide:building-2"></iconify-icon>
                                With another branch<span v-if="lead.branch_name"> ({{ lead.branch_name }})</span>
                            </div>

                            <!-- Stage + merge eligibility -->
                            <div class="dup-labels">
                                <div class="info-row">
                                    <span class="info-label">Stage</span>
                                    <span class="info-value"><span class="stage-chip">{{ lead.stage_name || '—' }}</span></span>
                                </div>
                                <div v-if="lead.match_by?.length" class="info-row dup-match">
                                    <MatchByChips :match-by="lead.match_by" />
                                </div>
                            </div>
                            <div class="stage-line">
                                <span v-if="lead.merge_blocked_reason" class="blocked-reason">
                                    <iconify-icon icon="lucide:ban"></iconify-icon>
                                    {{ lead.merge_blocked_reason }}
                                </span>
                                <span v-else-if="lead.can_merge" class="ok-reason">
                                    <iconify-icon icon="lucide:check"></iconify-icon>
                                    Can be merged
                                </span>
                            </div>
                           
                            
                            <!-- Contact Information -->
                            <div class="lead-info d-flex flex-column gap-2">
                                <!-- Phone Number -->
                                <div v-if="getPhoneNumber(lead)" class="info-row">
                                    <span class="info-label">Phone Number</span>
                                    <span class="info-value">
                                        {{ getPhoneNumber(lead) || '----' }}
                                        <span v-if="getPhoneType(lead)" class="phone-type">({{ getPhoneType(lead) }})</span>
                                    </span>
                                </div>
                                
                            </div>

                            <!-- Avatar -->
                            <div class="lead-avatar position-absolute">
                                <img 
                                    v-if="lead.responsible_person?.avatar" 
                                    :src="lead.responsible_person.avatar" 
                                    alt="" 
                                    class="avatar-sm rounded-circle" 
                                    :title="lead.responsible_person.name"
                                />
                                <div v-else class="avatar-sm rounded-circle bg-neutral-200 d-flex align-items-center justify-content-center">
                                    <iconify-icon icon="solar:user-bold" class="text-neutral-600"></iconify-icon>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref,computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import api from '@/plugins/axios'
import MatchByChips from './MatchByChips.vue'



const props = defineProps({
    modelValue: Boolean,
    leadId: {
        type: Number,
        default: null
    },
    triggerElement: {
        type: HTMLElement,
        default: null
    }
})

const emit = defineEmits(['update:modelValue', 'view-lead', 'merge'])

const show = ref(props.modelValue)
const duplicateLeads = ref([])
const loading = ref(false)
const error = ref(null)
const popupRef = ref(null)
const popupStyle = ref({})

const MERGE_ROLES = ['super_admin', 'admin', 'branch_admin']

function currentUserRoles() {
    try {
        const raw = localStorage.getItem('user')
        const parsed = raw ? JSON.parse(raw) : null
        return Array.isArray(parsed?.roles) ? parsed.roles : []
    } catch {
        return []
    }
}

const canMergeByRole = computed(() =>
    currentUserRoles().some((r) => MERGE_ROLES.includes(r))
)

const mergeableGroup = computed(
    () => duplicateLeads.value.some((l) => l.group_can_merge === true)
)

const showMergeAction = computed(
    () => canMergeByRole.value && mergeableGroup.value
)

const startMerge = () => {
    emit('merge', props.leadId)
    show.value = false
}

// Phones: a positioned dropdown doesn't fit (it was placed as if 500px wide and ended up
// off-screen), so show a full-width bottom sheet instead.
const MOBILE_MAX = 768
const isSheet = ref(typeof window !== 'undefined' && window.innerWidth <= MOBILE_MAX)

const calculatePosition = async () => {
    isSheet.value = window.innerWidth <= MOBILE_MAX
    if (isSheet.value) return

    if (!props.triggerElement) {
        // Fallback: center on screen if no trigger element
        popupStyle.value = {
            position: 'fixed',
            top: '50%',
            left: '50%',
            transform: 'translate(-50%, -50%)',
            width: '500px',
            maxWidth: 'calc(100vw - 32px)',
            zIndex: 1050
        }
        return
    }
    
    await nextTick()
    
    if (!popupRef.value) return
    
    const trigger = props.triggerElement
    const popup = popupRef.value
    const rect = trigger.getBoundingClientRect()
    
    // Use requestAnimationFrame to ensure DOM is fully rendered
    requestAnimationFrame(() => {
        const popupRect = popup.getBoundingClientRect()
        const viewportHeight = window.innerHeight
        const viewportWidth = window.innerWidth
        const spaceBelow = viewportHeight - rect.bottom
        const spaceAbove = rect.top
        
        // Real panel width (content is 416px), never wider than the viewport.
        const popupWidth = Math.min(416, viewportWidth - 32)
        const estimatedHeight = Math.min(400, duplicateLeads.value.length * 120 + 100) // Estimate height
        
        let top = 0
        let left = 0
        
        // Position horizontally - open on the right side of the icon
        const gap = 8 // 8px gap from the trigger element
        const preferredLeft = rect.right + gap
        
        // Check if there's enough space on the right side
        if (preferredLeft + popupWidth > viewportWidth - 16) {
            // Not enough space on right, try left side
            const leftSidePosition = rect.left - popupWidth - gap
            if (leftSidePosition >= 16) {
                left = leftSidePosition
            } else {
                // Not enough space on either side, align to viewport edge
                left = viewportWidth - popupWidth - 16
            }
        } else {
            left = preferredLeft
        }
        left = Math.max(16, left)

        // Position vertically - prefer below, but show above if not enough space
        if (spaceBelow >= estimatedHeight + 8 || spaceBelow >= spaceAbove) {
            top = rect.bottom + 8 // 8px gap below trigger
        } else {
            top = rect.top - estimatedHeight - 8 // 8px gap above trigger
        }
        
        // Ensure popup doesn't go off-screen
        if (top < 16) top = 16
        const maxTop = viewportHeight - estimatedHeight - 16
        if (top > maxTop) top = maxTop
        
        popupStyle.value = {
            position: 'fixed',
            top: `${top}px`,
            left: `${left}px`,
            width: `${popupWidth}px`,
            maxWidth: 'calc(100vw - 32px)',
            zIndex: 1050
        }
    })
}
const handleClickOutside = (event) => {
    if (!show.value) return
    
    // Don't close if clicking inside the popup
    if (popupRef.value && popupRef.value.contains(event.target)) {
        return
    }
    
    // Don't close if clicking on the trigger element
    if (props.triggerElement && props.triggerElement.contains(event.target)) {
        return
    }
    
    // Close if clicking outside
    show.value = false
}

const handleResize = () => {
    if (show.value) {
        calculatePosition()
    }
}

const fetchDuplicateLeads = async () => {
    if (!props.leadId) {
        error.value = 'No lead ID provided'
        return
    }

    loading.value = true
    error.value = null
    
    try {
        const response = await api.get(`/leads/get/duplicate/${props.leadId}`)
        duplicateLeads.value = response.data.data || []
        console.log('✅ Duplicate leads fetched:', duplicateLeads.value)
    } catch (err) {
        console.error('❌ Error fetching duplicate leads:', err)
        error.value = err.response?.data?.message || 'Failed to load duplicate leads'
        duplicateLeads.value = []
    } finally {
        loading.value = false
    }
}

const getPhoneNumber = (lead) => {
    return lead.work_phone || lead.work_phone_2 || null
}

const getPhoneType = (lead) => {
    if (lead.work_phone) return null
    if (lead.work_phone_2) return 'Secondary'
    return lead.phone_type || null
}

const openLeadView = (lead) => {
    if (lead.can_view === false) return
    emit('view-lead', lead.id)
    show.value = false
}

watch(() => props.modelValue, async (val) => {
    show.value = val
    if (val) {
        await fetchDuplicateLeads()
        await nextTick()
        calculatePosition()
    }
}, { immediate: true })

watch(show, (val) => {
    emit('update:modelValue', val)
    if (val) {
        nextTick(() => {
            calculatePosition()
            document.addEventListener('click', handleClickOutside)
            window.addEventListener('resize', handleResize)
            window.addEventListener('scroll', handleResize, true)
        })
    } else {
        document.removeEventListener('click', handleClickOutside)
        window.removeEventListener('resize', handleResize)
        window.removeEventListener('scroll', handleResize, true)
    }
})

onMounted(() => {
    if (show.value) {
        nextTick(() => {
            calculatePosition()
            document.addEventListener('click', handleClickOutside)
            window.addEventListener('resize', handleResize)
            window.addEventListener('scroll', handleResize, true)
        })
    }
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
    window.removeEventListener('resize', handleResize)
    window.removeEventListener('scroll', handleResize, true)
})
</script>

<style scoped>
.duplicate-leads-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1040;
    background-color: transparent;
}

.duplicate-leads-dropdown {
    position: fixed;
    z-index: 1050;
    animation: fadeInDown 0.2s ease-out;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.duplicate-leads-modal-content {
    padding: 0px 18px;
    background-color: #FFFFFF;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    height: 444px;
    width: 416px;
    display: flex;
    flex-direction: column;
}

.modal-header-custom {
    background-color: #FFFFFF;
    border-bottom: 1px solid #EBECEF;
    flex-shrink: 0;
}

.modal-title {
    font-family: Montserrat;
    /* font-weight: 700; */
    font-size: 14px !important;
    line-height: 24px;
    color: #0B0736;
}

.close-btn {
    background: none;
    border: none;
    padding: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6B7280;
    transition: color 0.2s;
}

.close-btn:hover {
    color: #0B0736;
}

.modal-body-custom {
    /* background-color: #F5F6FA; */
    flex: 1;
    overflow-y: auto;
    min-height: 0;
}

.duplicate-lead-card {
    position: relative;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid rgba(244, 244, 244, 1);
    margin: 12px 0px;
    padding: 12px;
}

.duplicate-lead-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
}

.lead-title {
    font-family: Montserrat;
    font-weight: 600;
    font-size: 12px !important;
    line-height: 20px;
    color: #0B0736;
    margin: 0 0 12px 0;
}

.lead-info {
    margin-bottom: 8px;
}

.info-row {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.info-label {
    font-family: Montserrat;
    font-weight: 500;
    font-size: 11px;
    line-height: 14px;
    color: #979797;
}

.info-value {
    font-family: Montserrat;
    font-weight: 500;
    font-size: 12px;
    line-height: 16px;
    color: #353535;
    display: inline-block;
}

.phone-type {
    color: #6B7280;
    font-size: 11px;
    font-weight: 500;
    line-height: 16px;
}

.lead-avatar {
    bottom: 16px;
    right: 16px;
}

.avatar-sm {
    width: 32px;
    height: 32px;
    object-fit: cover;
}

.avatar-sm img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-sm iconify-icon {
    font-size: 18px;
}

.cursor-pointer {
    cursor: pointer;
}

/* ---- Mobile bottom sheet (above the mobile tab bar, z-index 12060) ---- */
.duplicate-leads-backdrop--sheet {
    z-index: 12090;
    background-color: rgba(15, 23, 42, 0.45);
}

.duplicate-leads-dropdown--sheet {
    left: 0;
    right: 0;
    bottom: 0;
    top: auto;
    width: 100%;
    z-index: 12100;
    animation: sheetUp 0.25s ease-out;
}

.duplicate-leads-dropdown--sheet .duplicate-leads-modal-content {
    width: 100%;
    height: auto;
    max-height: 75dvh;
    border-radius: 20px 20px 0 0;
    padding: 0 16px calc(12px + env(safe-area-inset-bottom, 0px));
    box-shadow: 0 -10px 30px rgba(15, 23, 42, 0.15);
}

.duplicate-leads-dropdown--sheet .modal-body-custom {
    -webkit-overflow-scrolling: touch;
}

.sheet-handle {
    width: 40px;
    height: 4px;
    border-radius: 2px;
    background: #d1d5db;
    margin: 8px auto 0;
    flex-shrink: 0;
}

@keyframes sheetUp {
    from {
        transform: translateY(100%);
    }
    to {
        transform: translateY(0);
    }
}

/* Scrollbar styling */
.modal-body-custom::-webkit-scrollbar {
    width: 8px;
}

.modal-body-custom::-webkit-scrollbar-track {
    background: #F3F4F6;
    border-radius: 4px;
}

.modal-body-custom::-webkit-scrollbar-thumb {
    background-color: #D1D5DB;
    border-radius: 4px;
}

.modal-body-custom::-webkit-scrollbar-thumb:hover {
    background-color: #9CA3AF;
}
.merge-btn-3d {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 0;
    border-radius: 10px;
    padding: 7px 14px;
    background: var(--gradient-primary, linear-gradient(135deg, #5b21b6 0%, #4c1d95 55%, #312e81 100%));
    color: #fff;
    font-family: Montserrat, sans-serif;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.01em;
    cursor: pointer;
    overflow: hidden;
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.22) inset,
        0 -2px 0 rgba(15, 23, 42, 0.22) inset,
        0 4px 10px rgba(49, 46, 129, 0.28),
        0 1px 2px rgba(15, 23, 42, 0.12);
    transform: translateY(0);
    transition:
        transform 0.16s ease,
        box-shadow 0.16s ease,
        filter 0.16s ease;
}
.merge-btn-3d__shine {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        115deg,
        transparent 0%,
        rgba(255, 255, 255, 0.16) 42%,
        transparent 58%
    );
    pointer-events: none;
}
.merge-btn-3d__icon {
    font-size: 14px;
    line-height: 1;
}
.merge-btn-3d:hover {
    filter: brightness(1.05);
    transform: translateY(-1px);
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.28) inset,
        0 -2px 0 rgba(15, 23, 42, 0.2) inset,
        0 6px 14px rgba(49, 46, 129, 0.34),
        0 2px 4px rgba(15, 23, 42, 0.14);
}
.merge-btn-3d:active {
    transform: translateY(1px);
    filter: brightness(0.97);
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.12) inset,
        0 2px 0 rgba(15, 23, 42, 0.28) inset,
        0 2px 4px rgba(49, 46, 129, 0.2);
}
.merge-btn-3d:focus-visible {
    outline: 2px solid #c4b5fd;
    outline-offset: 2px;
}
.merge-btn-3d:disabled {
    opacity: 0.45;
    cursor: not-allowed;
    transform: none;
    filter: none;
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.1) inset,
        0 1px 3px rgba(15, 23, 42, 0.12);
}
.duplicate-lead-card--locked { opacity: .75; cursor: not-allowed; }
.duplicate-lead-card--locked:hover { transform: none; box-shadow: none !important; }
.other-branch-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 10px; border-radius: 999px;
    background: #FEF3C7; color: #92400E;
    font-size: 11px; font-weight: 600;
}
.dup-labels { display: flex; flex-wrap: wrap; gap: 8px 20px; margin-bottom: 8px; }
.dup-match { justify-content: flex-end; }
.stage-line:empty { display: none; }
.stage-line { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; margin-bottom: 8px; }
.stage-chip {
    display: inline-block; padding: 2px 10px; border-radius: 999px;
    background: #F1F5F9; color: #334155; font-size: 11px; font-weight: 700;
}
.blocked-reason, .ok-reason {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 11px; font-weight: 600; line-height: 1.3;
}
.blocked-reason { color: #B45309; }
.ok-reason { color: #15803D; }
</style>
