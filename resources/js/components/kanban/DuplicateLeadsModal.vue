<template>
    <Teleport to="body">
        <!-- Backdrop -->
        <div 
            v-if="show" 
            class="duplicate-leads-backdrop" 
            @click.stop="show = false"
        ></div>
        
        <!-- Dropdown Popup -->
        <div 
            v-if="show" 
            ref="popupRef"
            class="duplicate-leads-dropdown"
            :style="popupStyle"
            @click.stop
        >
            <div class="duplicate-leads-modal-content px-3">
                <!-- Header -->
                <div class="modal-header-custom d-flex justify-content-between align-items-center py-3 border-bottom">
                    <picture class="modal-title mb-0">Duplicate Leads ({{ duplicateLeads.length }})</picture>
                    <button class="close-btn" @click="show = false">
                        <iconify-icon icon="lucide:x"></iconify-icon>
                    </button>
                </div>

                <!-- Content -->
                <div class="modal-body-custom p-4" style="max-height: 70vh; overflow-y: auto;">
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

                    <div v-else class="duplicate-leads-list d-flex flex-column gap-3">
                        <div 
                            v-for="lead in duplicateLeads" 
                            :key="lead.id"
                            class="duplicate-lead-card bg-white radius-12 shadow-sm border-0 cursor-pointer"
                            @click="openLeadView(lead.id)"
                        >
                            <!-- Lead Title -->
                            <h6 class="lead-title">{{ lead.lead_name }}</h6>
                            
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
                                
                                <!-- Email -->
                                <div class="info-row">
                                    <span class="info-label">Email</span>
                                    <span class="info-value">{{ lead.email  || '----'}}</span>
                                </div>
                            </div>

                            <!-- Avatar -->
                            <div class="lead-avatar position-absolute">
                                <img 
                                    v-if="lead.responsible_person?.avatar" 
                                    :src="lead.responsible_person.avatar" 
                                    alt="" 
                                    class="avatar-sm rounded-circle" 
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
import { ref, watch, nextTick, onMounted, onUnmounted } from 'vue'
import api from '@/plugins/axios'

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

const emit = defineEmits(['update:modelValue', 'view-lead'])

const show = ref(props.modelValue)
const duplicateLeads = ref([])
const loading = ref(false)
const error = ref(null)
const popupRef = ref(null)
const popupStyle = ref({})

const calculatePosition = async () => {
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
        
        const popupWidth = 500 // Fixed width
        const estimatedHeight = Math.min(400, duplicateLeads.value.length * 120 + 100) // Estimate height
        
        let top = 0
        let left = 0
        
        // Position horizontally - align to right edge of trigger, adjust if needed
        const preferredLeft = rect.right - popupWidth
        if (preferredLeft < 16) {
            left = 16 // 16px margin from left edge
        } else if (preferredLeft + popupWidth > viewportWidth - 16) {
            left = viewportWidth - popupWidth - 16 // 16px margin from right edge
        } else {
            left = preferredLeft
        }
        
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
            width: '500px',
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
    return lead.phone_number || lead.work_phone || lead.work_phone_2 || lead.whatsapp_number || lead.phone || null
}

const getPhoneType = (lead) => {
    if (lead.work_phone || lead.phone_number) return 'Work Phone'
    if (lead.whatsapp_number) return 'WhatsApp'
    if (lead.work_phone_2) return 'Work Phone'
    return lead.phone_type || null
}

const openLeadView = (leadId) => {
    emit('view-lead', leadId)
    show.value = false
}

watch(() => props.modelValue, async (val) => {
    show.value = val
    if (val) {
        await fetchDuplicateLeads()
        await nextTick()
        calculatePosition()
    }
})

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
    background-color: #FFFFFF;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.modal-header-custom {
    background-color: #FFFFFF;
    border-bottom: 1px solid #EBECEF;
}

.modal-title {
    font-family: Montserrat;
    /* font-weight: 700; */
    font-size: 14px;
    line-height: 24px;
    color: #01062C;
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
    color: #01062C;
}

.modal-body-custom {
    /* background-color: #F5F6FA; */
}

.duplicate-lead-card {
    position: relative;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    padding: 16px;
    padding-bottom: 48px;
}

.duplicate-lead-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
}

.lead-title {
    font-family: Montserrat;
    font-weight: 700;
    font-size: 14px;
    line-height: 20px;
    color: #01062C;
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
</style>
