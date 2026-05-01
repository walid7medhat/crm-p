<template>
    <div class="lead-pool-wrapper">
        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
        
        <div v-else-if="leads.length === 0" class="text-center py-5 text-muted">
            No leads found in Lead Pool
        </div>
        
        <!-- نفس تصميم الكانبان -->
        <div v-else class="leads-grid">
            <div 
                v-for="lead in leads" 
                :key="lead.id" 
                class="lead-card"
                @click="viewLead(lead)"
            >
                <!-- نفس كارد الـ kanban-card بالضبط -->
                <div class="kanban-card bg-white p-12 radius-12 shadow-sm border-0 cursor-pointer">
                    <div class="task-header d-flex align-items-center justify-content-between gap-2 mb-12">
                        <p class="task-title flex-grow-1 mb-0">{{ lead.lead_name || lead.name || 'Untitled Lead' }}</p>
                        <span 
                            v-if="lead.has_service_duplicate"
                            class="service-dup-badge"
                        >
                            Provide
                        </span>
                        <div 
                            v-if="lead.duplicate_no > 0"
                            class="duplicate-badge position-relative cursor-pointer"
                            @click.stop="openDuplicateLeadsModal(lead.id, $event)"
                        >
                            <div class="duplicate-icon-wrapper">
                                <div class="duplicate-rectangle duplicate-rectangle-back"></div>
                                <div class="duplicate-rectangle duplicate-rectangle-front">
                                    <span class="duplicate-number">{{ lead.duplicate_no || 0 }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="task-info">
                        <!-- Created at -->
                        <div class="info-item date-info d-flex align-items-center gap-1 mb-8">
                            <span class="text-secondary-light text-xs">Created</span>
                            <span>{{ formatDate(lead.created_at) }}</span>
                        </div>
                        
                        <!-- Created By -->
                        <div v-if="lead.added_by_user?.name" class="info-item mb-8">
                            <div class="info-label text-secondary-light text-xs">Created By</div>
                            <div class="info-value">{{ lead.added_by_user.name }}</div>
                        </div>
                        
                        <!-- Name (Salutation + First Name) -->
                        <div v-if="lead.first_name" class="info-item mb-8">
                            <div class="info-label text-secondary-light text-xs">Name</div>
                            <div class="info-value">{{ lead.salutation || '' }} {{ lead.first_name }}</div>
                        </div>
                        
                        <!-- Source -->
                        <div v-if="lead.lead_source" class="info-item mb-8">
                            <div class="info-label text-secondary-light text-xs mb-1">Source</div>
                            <div class="info-value">{{ lead.lead_source }}</div>
                        </div>
                        
                        <!-- Lead Branch Source -->
                        <div v-if="lead.lead_branch_source" class="info-item mb-12">
                            <div class="info-label text-secondary-light text-xs mb-1">Lead Branch Source</div>
                            <div class="info-value">{{ lead.lead_branch_source }}</div>
                        </div>
                        
                        <!-- Phone -->
                        <div v-if="lead.work_phone" class="info-item mb-8">
                            <div class="info-label text-secondary-light text-xs">Phone</div>
                            <div class="info-value">{{ lead.work_phone.slice(0,8) + '....' }}</div>
                        </div>
                        
                        <!-- Email -->
                        <div v-if="lead.email" class="info-item mb-8">
                            <div class="info-label text-secondary-light text-xs">Email</div>
                            <div class="info-value">{{ formatMaskedEmail(lead.email) }}</div>
                        </div>
                        
                        <!-- WhatsApp -->
                        <div v-if="lead.whatsapp_number" class="info-item mb-8">
                            <div class="info-label text-secondary-light text-xs">WhatsApp</div>
                            <div class="info-value">{{ lead.whatsapp_number }}</div>
                        </div>
                        
                        <!-- More Information -->
                        <div v-if="lead.api_first_question" class="info-item mb-8">
                            <div class="info-label text-secondary-light text-xs">More Information</div>
                            <div class="info-value">{{ formatMaskedQuestion(lead.api_first_question) }}</div>
                        </div>
                        
                        <!-- Responsible Person -->
                        <div v-if="lead.responsible_person?.name" class="responsible-info d-flex align-items-center justify-content-between mb-12">
                            <div class="d-flex align-items-center gap-2">
                                <div class="person-hover-anchor">
                                    <img v-if="lead.responsible_person?.avatar" :src="lead.responsible_person.avatar" alt="" class="avatar-sm rounded-circle" />
                                    <div v-else class="avatar-sm rounded-circle bg-neutral-200 d-flex align-items-center justify-content-center">
                                        <iconify-icon icon="solar:user-bold" class="text-neutral-600"></iconify-icon>
                                    </div>
                                </div>
                                <div>
                                    <div class="info-value">{{ lead.responsible_person.name }}</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Assigned By -->
                        <div v-if="lead.assigned_at">
                            <hr class="mb-2 border-neutral-200">
                            <div class="mt-1 d-flex align-items-center justify-content-between assignedBy">
                                <div class="info-item">
                                    <div class="info-label text-secondary-light text-xs mb-1">Assigned</div>
                                    <div class="info-value">{{ formatDate(lead.assigned_at) }}</div>
                                </div>
                                <div class="person-hover-anchor">
                                    <img v-if="lead.parent?.avatar" :src="lead.parent.avatar" alt="" class="avatar-sm rounded-circle" />
                                    <div v-else class="avatar-sm rounded-circle bg-neutral-200 d-flex align-items-center justify-content-center">
                                        <iconify-icon icon="solar:user-bold" class="text-neutral-600"></iconify-icon>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <ViewLeadModal
        v-model="showViewModal"
        :leadId="selectedLeadId"
        @lead-updated="handleLeadUpdated"
    />
    
    <DuplicateLeadsModal 
        v-model="showDuplicateModal" 
        :leadId="selectedLeadForDuplicates"
        :triggerElement="currentTriggerElement"
        @view-lead="handleViewDuplicateLead"
    />
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'
import Swal from 'sweetalert2'
import ViewLeadModal from '../viewLead/ViewLeadModal.vue'
import DuplicateLeadsModal from './DuplicateLeadsModal.vue'

// Emits
const emit = defineEmits(['lead-clicked'])

// State
const leads = ref([])
const loading = ref(true)
const showViewModal = ref(false)
const selectedLeadId = ref(null)
const showDuplicateModal = ref(false)
const selectedLeadForDuplicates = ref(null)
const currentTriggerElement = ref(null)

// Fetch leads with stage_id = 10
const fetchLeadPool = async () => {
    loading.value = true
    try {
        const response = await api.get('/leads', {
            params: {
                stage_id: 10
            }
        })

        const data = response.data?.data || []
        
        if (data && data.length > 0 && data[0]?.leads) {
            leads.value = data[0].leads
        } else if (Array.isArray(data)) {
            leads.value = data
        } else {
            leads.value = []
        }
    } catch (error) {
        console.error('Error fetching lead pool:', error)
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to load Lead Pool',
            timer: 3000,
            showConfirmButton: false
        })
        leads.value = []
    } finally {
        loading.value = false
    }
}

// View lead details
const viewLead = (lead) => {
    selectedLeadId.value = lead.id
    showViewModal.value = true
}

// Handle lead update from modal
const handleLeadUpdated = (updatedLead) => {
    // Refresh the list
    fetchLeadPool()
}

// Open duplicate leads modal
const openDuplicateLeadsModal = (leadId, event) => {
    selectedLeadForDuplicates.value = leadId
    if (event && event.currentTarget) {
        currentTriggerElement.value = event.currentTarget
    }
    showDuplicateModal.value = true
}

// Handle view duplicate lead
const handleViewDuplicateLead = (leadId) => {
    selectedLeadId.value = leadId
    showViewModal.value = true
}

// Helper functions
const formatDate = (dateString) => {
    if (!dateString) return ''
    const date = new Date(dateString)
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    })
}

const formatMaskedEmail = (email) => {
    const raw = String(email || '').trim()
    if (!raw) return ''
    if (raw.length <= 8) return `${raw}....`
    return `${raw.slice(0, 8)}....`
}

const formatMaskedQuestion = (questionData) => {
    if (!questionData) return '—'
    if (typeof questionData === 'string') {
        let questionText = questionData.replace(/_/g, ' ')
        questionText = questionText.replace(/\b\w/g, l => l.toUpperCase())
        if (questionText.length > 30) {
            questionText = questionText.substring(0, 30) + '...'
        }
        return questionText
    }
    return '—'
}

onMounted(() => {
    fetchLeadPool()
})

// Expose refresh method
defineExpose({
    fetchLeadPool
})
</script>

<style scoped>
.lead-pool-wrapper {
    padding: 20px;
    /* background: #f8f9fa; */
    min-height: calc(100vh - 72px);
}

.leads-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: 10px;
}

/* نفس ستايل الكانبان كارد */
.kanban-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    color: #1e293b;
    border: 1px solid #e5e7eb !important;
}

.kanban-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1) !important;
}

.task-title {
    font-family: Montserrat;
    font-weight: 700;
    font-size: 12px;
    line-height: 19px;
    letter-spacing: -0.25px;
    color: #01062C;
}

.task-header {
    align-items: flex-start;
}

.info-label {
    color: #979797;
    font-weight: 500;
    font-size: 11px;
}

.info-value {
    font-weight: 500;
    font-size: 11px;
    line-height: 12px;
    color: #353535;
}

.avatar-sm {
    width: 32px;
    height: 32px;
    object-fit: cover;
}

.assignedBy .avatar-sm {
    width: 28px;
    height: 28px;
}

.date-info {
    font-family: Montserrat;
    font-weight: 500;
    font-size: 10px;
    line-height: 9px;
    color: #64748b;
}

.date-info span {
    color: #1e293b;
}

.border-neutral-200 {
    border-width: 1px;
    border-color: #e5e7eb;
}

/* Duplicate badge */
.duplicate-badge {
    flex-shrink: 0;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity 0.2s ease;
}

.duplicate-badge:hover {
    opacity: 0.7;
}

.duplicate-badge.cursor-pointer {
    cursor: pointer;
}

.duplicate-icon-wrapper {
    position: relative;
    width: 24px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.duplicate-rectangle {
    position: absolute;
    width: 20px;
    height: 24px;
    background-color: #FFFFFF;
    border: 1px solid #D1D5DB;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.duplicate-rectangle-back {
    top: 4px;
    left: 4px;
    z-index: 1;
}

.duplicate-rectangle-front {
    top: 0;
    left: 0;
    z-index: 2;
}

.duplicate-number {
    font-family: Montserrat;
    font-weight: 600;
    font-size: 11px;
    line-height: 1;
    color: #01062C;
}

.service-dup-badge {
    background: #ff4d4f;
    color: white;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 6px;
    margin-left: 6px;
}

.person-hover-anchor {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.bg-neutral-200 {
    background-color: #e5e7eb;
}

.radius-12 {
    border-radius: 12px;
}

.p-12 {
    padding: 12px;
}

.mb-12 {
    margin-bottom: 12px;
}

.mb-8 {
    margin-bottom: 8px;
}

.gap-2 {
    gap: 8px;
}

.cursor-pointer {
    cursor: pointer;
}
</style>