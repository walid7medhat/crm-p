<template>
    <div class="lead-info-view">
        <div class="info-section">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="info-section-title mb-0">Lead Information</div>
                <button v-if="showEditIcon && canEdit" class="lead-edit-inline-btn" @click="emit('edit-request')">
                    <iconify-icon class="edit-icon-btn" color="#FAA300" icon="lucide:pencil"></iconify-icon>
                </button>
            </div>
            <div class="info-group">
                <label class="form-label-custom">Lead Name</label>
                <div class="info-value">{{ lead?.lead_name || '—' }}</div>
            </div>
            <div class="info-group">
                <label class="form-label-custom">Salutation</label>
                <div class="info-value">{{ lead?.salutation || '—' }}</div>
            </div>
            <div class="info-group">
                <label class="form-label-custom">First Name</label>
                <div class="info-value">{{ lead?.first_name || '—' }}</div>
            </div>
            <div class="info-group">
                <label class="form-label-custom">Last Name</label>
                <div class="info-value">{{ lead?.last_name || '—' }}</div>
            </div>
            <div class="info-group">
                <label class="form-label-custom">Primary Phone</label>
                <div class="info-value">
                    <span v-if="canView">{{ lead?.work_phone || '—' }}</span>
                    <span v-else>
                        {{ lead?.work_phone?.slice(0,3) || '' }}
                        <span class="blurred-stars">{{ maskValue(lead?.work_phone?.slice(3)) }}</span>
                    </span>
                </div>
            </div>
            <div class="info-group">
                <label class="form-label-custom">Primary Email</label>
                <div class="info-value">
                    <span v-if="canView">{{ lead?.email || '—' }}</span>
                    <span v-else>
                        {{ lead?.email?.slice(0,3) || '' }}
                        <span class="blurred-stars">{{ maskValue(lead?.email?.slice(3))}}</span>
                    </span>
                </div>
            </div>
            <div class="info-group">
                <label class="form-label-custom">Secondary Email</label>
                <div class="info-value">
                    <span v-if="canView">{{ lead?.secondary_email || '—' }}</span>
                    <span v-else>
                        {{ lead?.secondary_email?.slice(0,3) || '' }}
                        <span class="blurred-stars">{{ maskValue(lead?.secondary_email?.slice(3))}}</span>
                    </span>
                </div>
            </div>
            <div class="info-group">
                <label class="form-label-custom">Secondary Phone</label>
                <div class="info-value">
                    <span v-if="canView">{{ lead?.work_phone_2 || '—' }}</span>
                    <span v-else>
                        {{ lead?.work_phone_2?.slice(0,3) || '' }}
                        <span class="blurred-stars">{{ maskValue(lead?.work_phone_2?.slice(3)) }}</span>
                    </span>
                </div>
            </div>
        </div>

        <div class="info-section" v-if="hasAdditionalQuestions">
            <div class="info-section-title">More Information</div>
           
            <template v-if="hasAdditionalQuestions">
                <div class="info-group" v-for="(answer, question) in facebookQuestions" :key="question">
                    <label class="form-label-custom">{{ formatQuestion(question) }}</label>
                    <div class="info-value">
                        <a v-if="question === 'link' || question === 'Page_URL' || question ==='inbox_url'" :href="answer" target="_blank" class="facebook-link">
                            {{ answer }}
                        </a>
                        <span v-else>
                            {{ answer }}
                        </span>
                    </div>
                </div>
                <div class="info-group" v-for="(answer, question) in metaQuestions" :key="`meta-${question}`">
                    <label class="form-label-custom">{{ formatQuestion(question) }}</label>
                    <div class="info-value ">
                        <a v-if="question === 'link' || question === 'Page_URL' || question ==='inbox_url'" :href="answer" target="_blank" class="facebook-link">
                            {{ answer }}
                        </a>
                        <span v-else>
                            {{ answer }}
                        </span>
                    </div>
                </div>
            </template>
            <div v-else class="info-empty">
                No additional information
            </div>
        </div>

        <div class="info-section" v-if="hasClientRequiredInfo">
            <div class="info-section-title">Client Requirement</div>
            <template v-if="hasClientRequiredInfo">
                <div class="info-group" v-if="lead?.bedrooms">
                    <label class="form-label-custom">Bedrooms</label>
                    <div class="info-value">{{ lead?.bedrooms || '—' }}</div>
                </div>
                <div class="info-group" v-if="lead?.area">
                    <label class="form-label-custom">Location</label>
                    <div class="info-value">{{ lead?.area || '—' }}</div>
                </div>
                <div class="info-group" v-if="lead?.property_type">
                    <label class="form-label-custom">Property Type</label>
                    <div class="info-value">{{ lead?.property_type || '—' }}</div>
                </div>
                <div class="info-group" v-if="formatLeadBudgetRange(lead)">
                    <label class="form-label-custom">Budget</label>
                    <div class="info-value">{{ formatLeadBudgetRange(lead) }} {{ lead?.currency || 'AED' }}</div>
                </div>
                 <div class="info-group" v-if="lead?.branch != null">
                    <label class="form-label-custom">Shared Branch</label>
                    <div class="info-value">{{ formatWithMapping(lead?.branch, {
                                                'Abu Dhabi': 'Abu Dhabi',
                                                'Dubai': 'Dubai',
                                                'Sharjah': 'Sharjah'
                                            }) }}</div>
                </div>
                 <div class="info-group" v-if="lead?.available_date != null">
                    <label class="form-label-custom">Available Date</label>
                    <div class="info-value">{{ lead?.available_date != null ? lead.available_date : '—' }}</div>
                </div>
                   <div class="info-group" v-if="lead?.status_lead != null">
                    <label class="form-label-custom">Lead Status</label>
                    <div class="info-value">{{ formatLeadStatus(lead?.status_lead) }}</div>
                </div>
                <div class="info-group" v-if="lead?.source_information">
                    <label class="form-label-custom">Source Information</label>
                    <div class="info-value">{{ lead?.source_information || '—' }}</div>
                </div>
                 <div class="info-group" v-if="lead?.unqualified_status">
                    <label class="form-label-custom">Unqualified Status</label>
                    <div class="info-value">{{ lead?.unqualified_status || '—' }}</div>
                </div>
                 <div class="info-group" v-if="lead?.why_lost_lead">
                    <label class="form-label-custom">Lost For</label>
                    <div class="info-value">{{ formatLostReason(lead?.why_lost_lead) }}</div>
                </div>
                <div class="info-group" v-for="(value, key) in clientRequiredMetaFields" :key="`client-${key}`">
                    <label class="form-label-custom">{{ formatQuestion(key) }}</label>
                    <div class="info-value">{{ value }}</div>
                </div>
            </template>
            <div v-else class="info-empty">
                No additional information
            </div>
        </div>

        <MatchingPropertiesSection v-if="lead?.id" :lead="lead" />

        <div v-if="showResponsibleSection" class="info-section">
            <div class="info-section-title">Responsible Person</div>
            <div class="info-group">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <label class="form-label-custom">Responsible Person</label>
                <b-button 
                    variant="link" 
                    class="p-0 edit-person-btn"
                    @click="openPersonModal"
                    :disabled="isUpdatingPerson"
                >
                    <iconify-icon icon="lucide:edit" class="edit-icon"></iconify-icon>
                    <span>Change</span>
                </b-button>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-wrapper person-hover-anchor" @mouseenter="showPersonCard = true" @mouseleave="showPersonCard = false">
                    <img 
                        v-if="lead?.responsible_person?.avatar" 
                        :src="lead?.responsible_person?.avatar" 
                        class="avatar-md rounded-circle" 
                        
                    />
                    <div v-else class="avatar-placeholder">
                        <iconify-icon icon="lucide:user" class="avatar-icon"></iconify-icon>
                    </div>
                    <transition name="person-card-pop">
                        <div v-if="showPersonCard" class="person-hover-card">
                            <div class="person-hover-head">
                                <img
                                    v-if="lead?.responsible_person?.avatar"
                                    :src="lead?.responsible_person?.avatar"
                                    alt=""
                                    class="person-hover-avatar"
                                />
                                <div v-else class="person-hover-avatar person-hover-avatar-fallback">
                                    <iconify-icon icon="lucide:user" class="avatar-icon"></iconify-icon>
                                </div>
                                <div>
                                    <div class="person-hover-name">{{ lead?.responsible_person?.name || '—' }}</div>
                                    <div class="person-hover-role">{{ lead?.responsible_person?.position || lead?.responsible_person?.role_name || 'Team Member' }}</div>
                                </div>
                            </div>
                            <div class="person-hover-line">
                                <span>Reports To</span>
                                <b>{{ lead?.responsible_person?.manager_name || lead?.responsible_person?.team_lead_name || 'Not specified' }}</b>
                            </div>
                            <div class="person-hover-line">
                                <span>Branch</span>
                                <b>{{ lead?.responsible_person?.branch_name || lead?.lead_branch_source || 'Not specified' }}</b>
                            </div>
                        </div>
                    </transition>
                </div>
                <div class="flex-grow-1">
                    <div class="info-value">{{ lead?.responsible_person?.name || '—' }}</div>
                </div>
            </div>
            
            <!-- Person Update Modal -->
            <b-modal
                v-model="showPersonModal"
                title=" Responsible "
                hide-footer
                size="md"
                class="person-modal"
                @hidden="resetPersonModal"
            >
                <div class="person-modal-content">
                    <!-- Search Input -->
                    <div class="search-input-wrapper mb-3">
                        <b-form-input 
                            v-model="personSearchQuery" 
                            placeholder="Search Person by name or email" 
                            class="person-search-input"
                        />
                        <iconify-icon icon="lucide:search" class="search-icon"></iconify-icon>
                    </div>
                    
                    <!-- Loading State -->
                    <div v-if="isLoadingPersons" class="text-center py-4">
                        <b-spinner small variant="warning" label="Loading..."></b-spinner>
                        <p class="mt-2 text-muted">Loading persons...</p>
                    </div>
                    
                    <!-- Users List -->
                    <div v-else class="person-list-scroll">
                        <div 
                            v-for="user in filteredPersons" 
                            :key="user.id"
                            class="person-item d-flex align-items-center justify-content-between p-2"
                            @click="selectPerson(user)"
                            :class="{ 
                                'selected': selectedPersonId === user.id,
                                'current': lead?.responsible_person?.id === user.id
                            }"
                        >
                            <div class="d-flex align-items-center gap-2">
                                <img 
                                    :src="user.avatar || 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'" 
                                    class="person-item-avatar" 
                                />
                                <div class="person-item-info">
                                    <div class="person-item-name">
                                        <span class="user-item-name">{{ user.name }}</span>
                                           <span v-if="user.role_name" class="user-position-badge">
                                                    {{user.role_name }}
                                                </span>
                                    </div>
                                    <div class=" user-item-meta-line"> 
                                                <span class="meta-label">Parent:</span>
                                                <span class="meta-value">{{ user.parent_name }}</span>
                                                <span class="meta-divider" v-if="user.branch_name">|</span>
                                                <span class="meta-label" v-if="user.branch_name">Branch:</span>
                                                <span class="meta-value" v-if="user.branch_name">{{ user.branch_name}}</span></div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span v-if="lead?.responsible_person?.id === user.id" class="current-badge">
                                    Current
                                </span>
                                <iconify-icon 
                                    v-if="selectedPersonId === user.id" 
                                    icon="lucide:check" 
                                    class="text-warning"
                                ></iconify-icon>
                            </div>
                        </div>
                        
                        <div v-if="filteredPersons?.length === 0" class="text-center p-4 text-muted">
                            <iconify-icon icon="lucide:users" class="mb-2" width="40" height="40"></iconify-icon>
                            <p>No persons found matching "{{ personSearchQuery }}"</p>
                        </div>
                    </div>
                    
                    <!-- Person Update Error -->
                    <div v-if="personUpdateError" class="alert alert-danger mt-3 py-2">
                        <iconify-icon icon="lucide:alert-circle" class="me-1"></iconify-icon>
                        {{ personUpdateError }}
                    </div>
                    
                    <!-- Modal Footer -->
                    <div class="modal-footer-custom mt-3">
                        <b-button 
                            variant="light" 
                            @click="showPersonModal = false"
                            :disabled="isUpdatingPerson"
                        >
                            Cancel
                        </b-button>
                        <b-button 
                            variant="warning" 
                            @click="updateResponsiblePerson"
                            :disabled="!selectedPersonId || isUpdatingPerson || selectedPersonId === lead?.responsible_person?.id"
                        >
                            <b-spinner v-if="isUpdatingPerson" small></b-spinner>
                            <span v-else>Update Person</span>
                        </b-button>
                    </div>
                </div>
            </b-modal>
        </div>
        
        <!--<div class="info-group" v-if="lead?.lead_source">-->
        <!--    <label class="form-label-custom">lead source</label>-->
        <!--    <div class="info-value">{{ lead?.lead_source || '—' }}</div>-->
        <!--</div>-->
        
        <!--<div class="info-group" v-if="lead?.lead_source">-->
        <!--    <label class="form-label-custom">Lead Branch Source</label>-->
        <!--    <div class="info-value">{{ lead?.lead_branch_source || '—' }}</div>-->
        <!--</div>-->
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { BButton, BModal, BFormInput, BSpinner } from 'bootstrap-vue-3'
import api from '@/plugins/axios'
import { formatLeadBudgetRange } from '@/utils/budgetInput'
import MatchingPropertiesSection from './MatchingPropertiesSection.vue'

const props = defineProps({
    lead: Object,
    showResponsibleSection: {
        type: Boolean,
        default: true,
    },
    canEdit: {
        type: Boolean,
        default: false,
    },
    showEditIcon: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['person-updated','edit-request'])

const user = ref(JSON.parse(localStorage.getItem('user') || '{}'))

// Person modal state
const showPersonModal = ref(false)
const isLoadingPersons = ref(false)
const isUpdatingPerson = ref(false)
const personSearchQuery = ref('')
const personsList = ref([])
const selectedPersonId = ref(null)
const personUpdateError = ref('')
const showPersonCard = ref(false)

// Computed property for permission
const canView = computed(() => {
    if (!user.value?.roles) return false
    const isAdmin = user.value.roles.includes('super_admin') || user.value.roles.includes('admin')
    const isResponsible = props.lead?.responsible_person_id === user.value.id
    return isAdmin || isResponsible
})

// Filter persons based on search query
const filteredPersons = computed(() => {
    if (!personSearchQuery.value) return personsList.value
    
    const query = personSearchQuery.value.toLowerCase()
    return personsList.value.filter(person => 
        person.name.toLowerCase().includes(query) ||
        person.email.toLowerCase().includes(query)
    )
})

// Fetch available persons
const fetchAvailablePersons = async () => {
    isLoadingPersons.value = true
    personUpdateError.value = ''
    
    try {
        const response = await api.get('/available-responsible-persons')
        personsList.value = response.data.data || response.data || []
    } catch (error) {
        console.error('Error fetching persons:', error)
        personUpdateError.value = 'Failed to load persons list'
    } finally {
        isLoadingPersons.value = false
    }
}

// Open person modal
const openPersonModal = () => {
    selectedPersonId.value = props.lead?.responsible_person?.id || null
    personSearchQuery.value = ''
    personUpdateError.value = ''
    fetchAvailablePersons()
    showPersonModal.value = true
}

// Select person
const selectPerson = (person) => {
    selectedPersonId.value = person.id
}

// Reset modal
const resetPersonModal = () => {
    selectedPersonId.value = null
    personSearchQuery.value = ''
    personsList.value = []
    personUpdateError.value = ''
}

// Update responsible person
const updateResponsiblePerson = async () => {
    if (!selectedPersonId.value) return
    
    isUpdatingPerson.value = true
    personUpdateError.value = ''
    
    try {
        const response = await api.post(`/leads/${props.lead.id}/assign-responsible-person`, {
            responsible_person_id: selectedPersonId.value
        })
        
        // Find the selected person details
        const selectedPerson = personsList.value.find(p => p.id === selectedPersonId.value)
        
        // Emit the updated person data
        emit('person-updated', {
            id: selectedPersonId.value,
            name: selectedPerson?.name,
            avatar: selectedPerson?.avatar
        })
        
        // Show success notification
        if (window.$showNotification) {
            window.$showNotification('Responsible person updated successfully!', 'success')
        }
        
        // Close modal
        showPersonModal.value = false
        
    } catch (error) {
        console.error('Error updating responsible person:', error)
        
        if (error.response?.status === 422) {
            // Validation errors
            const errors = error.response.data.errors || error.response.data
            personUpdateError.value = Object.values(errors)[0]?.[0] || 'Validation error'
        } else {
            personUpdateError.value = error.response?.data?.message || 'Failed to update responsible person'
        }
        
        if (window.$showNotification) {
            window.$showNotification(personUpdateError.value, 'error')
        }
    } finally {
        isUpdatingPerson.value = false
    }
}

// Mask value function
const maskValue = (value) => {
    if (!value) return ''
    return '★'.repeat(value.length)
}

// Format question function
const formatQuestion = (question) => {
    if (!question) return ''
    return question
        .replace(/_/g, ' ')
        .replace(/\b\w/g, l => l.toUpperCase())
}
// دالة لتنسيق lost reason
const formatLostReason = (reason) => {
    if (!reason) return '—'
    const mapping = {
        'lost_by_other_company': 'Lost by Other Company',
        'lost_by_our_company': 'Lost by Our Company'
    }
    return mapping[reason] || formatText(reason)
}

// دالة لتنسيق lead status حسب النوع
const formatLeadStatus = (status, type = 'qualified') => {
    if (!status) return '—'
    
    const mapping = {
        // Qualified
        'cold': 'Cold Lead',
        'warm': 'Warm Lead',
        'hot': 'Hot Lead',
        // Lead Pool
        'no_answer': 'Lead Pool - No Answer',
        'canceled': 'Lead Pool - Canceled',
        // Unqualified
        'unqualified_not_interested': 'Unqualified - Not Interested',
        'unqualified_wrong_contact': 'Unqualified - Wrong Contact Details',
        'unqualified_job_seeker': 'Unqualified - Job Seeker',
        'unqualified_other': 'Unqualified - Other'
    }
    
    return mapping[status] || formatText(status)
}

// دالة عامة مع إمكانية إضافة mapping مخصص
const formatWithMapping = (value, mapping = {}) => {
    if (!value) return '—'
    return mapping[value] || formatText(value)
}
// Basic fields for Facebook questions
const basicFields = ['email', 'phone', 'full_name', 'name', 'work_phone','work_phone_number','phone_number','full name', 'first_name', 'last_name','Date','Time','Page_Name','inbox_url','form_name','form_id','No_Label_name','No_Label_email','No_Label_phone']

// Facebook questions computed
const facebookQuestions = computed(() => {
    if (!props.lead?.facebook_questions_answers) {
        return {}
    }
    
    const fields = {}
    Object.keys(props.lead.facebook_questions_answers).forEach(key => {
        if (!basicFields.includes(key) && props.lead.facebook_questions_answers[key]) {
            fields[key] = props.lead.facebook_questions_answers[key]
        }
    })
    
    return fields
})

const hasAdditionalFacebookQuestions = computed(() => {
    return Object.keys(facebookQuestions.value).length > 0
})

const metaQuestions = computed(() => {
    const meta = props.lead?.meta
    if (!meta || typeof meta !== 'object') return {}
    const result = {}
    Object.keys(meta).forEach((key) => {
        const value = meta[key]
        if (
            value == null ||
            value === '' ||
            typeof value === 'object' ||
            basicFields.includes(key) ||
            ['client_required_info', 'additional_fields', 'questions_answers'].includes(key)
        ) return
        result[key] = value
    })
    return result
})

const clientRequiredMetaFields = computed(() => {
    const meta = props.lead?.meta
    const source =
        props.lead?.client_required_info ||
        props.lead?.additional_fields ||
        meta?.client_required_info ||
        meta?.additional_fields
    if (!source || typeof source !== 'object') return {}
    return Object.fromEntries(
        Object.entries(source).filter(([, value]) => value !== null && value !== '')
    )
})

const hasAdditionalQuestions = computed(() => {
    return hasAdditionalFacebookQuestions.value || Object.keys(metaQuestions.value).length > 0
})

const hasClientRequiredInfo = computed(() => {
    return Boolean(
        props.lead?.bedrooms ||
        props.lead?.area ||
        props.lead?.property_type ||
        props.lead?.source_information ||
        props.lead?.budget != null ||
        props.lead?.budget_from != null ||
        props.lead?.budget_to != null ||
        props.lead?.branch != null ||
        props.lead?.available_date != null ||
        props.lead?.status_lead != null ||
        props.lead?.unqualified_status != null ||
        props.lead?.why_lost_lead != null ||
        Object.keys(clientRequiredMetaFields.value).length > 0
    )
})
</script>

<style scoped>
.lead-info-view .info-group {
    margin-bottom: 1rem;
}

.lead-info-view {
    overflow: visible;
}

.info-section {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 14px;
    margin-bottom: 18px;
    background: #ffffff;
    overflow: visible;
}

.info-section-title {
    font-size: 12px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 12px;
    padding-bottom: 0;
    border-bottom: none;
}


.lead-edit-inline-btn {
    border: none;
    background: transparent;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.lead-edit-inline-btn:hover {
    opacity: 0.85;
}

.edit-icon-btn {
    font-size: 18px;
}
.info-empty {
    font-size: 12px;
    color: #94a3b8;
}

.lead-info-view .form-label-custom {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: #64748B;
    margin-bottom: 6px;
}

.lead-info-view .info-value,
.lead-info-view .info-value a {
    word-break: break-word;
    overflow-wrap: anywhere;
}
.lead-info-view .info-value {
    font-size: 14px;
    color: #1E293B;

    word-break: break-word;
    overflow-wrap: anywhere;
}

.lead-info-view .info-value-block {
    white-space: pre-wrap;
}

.blurred-stars {
    filter: blur(3px);
    user-select: none;
}

.facebook-link {
    color: #2563eb;
    text-decoration: underline;
    text-decoration-color: #2563eb;
}

.facebook-link:hover {
    color: #1d4ed8;
    text-decoration: none;
}

/* Avatar Styles */
.avatar-wrapper {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
}

.person-hover-anchor {
    position: relative;
    overflow: visible;
}

.person-hover-card {
    position: absolute;
    top: 50%;
    left: calc(100% + 10px);
    transform: translateY(-50%);
    width: 200px;
    z-index: 1200;
    border-radius: 12px;
    border: 1px solid #dbe3ef;
    background: rgba(255, 255, 255, 0.97);
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.2);
    backdrop-filter: blur(8px);
    padding: 10px;
}

.person-hover-head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.person-hover-avatar {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    object-fit: cover;
    border: 1px solid #e2e8f0;
}

.person-hover-avatar-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
}

.person-hover-name {
    font-size: 12px;
    font-weight: 700;
    color: #0f172a;
}

.person-hover-role {
    margin-top: 1px;
    font-size: 11px;
    color: #64748b;
}

.person-hover-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    font-size: 11px;
    padding: 4px 0;
    border-top: 1px dashed #e2e8f0;
}

.person-hover-line span {
    color: #64748b;
}

.person-hover-line b {
    color: #0f172a;
    font-weight: 700;
    text-align: right;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.person-card-pop-enter-active,
.person-card-pop-leave-active {
    transition: opacity 0.14s ease, transform 0.14s ease;
}

.person-card-pop-enter-from,
.person-card-pop-leave-to {
    opacity: 0;
    transform: translateY(-50%) translateX(4px) scale(0.98);
}

.avatar-md {
    width: 48px;
    height: 48px;
    object-fit: cover;
}

.avatar-placeholder {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #F3F4F6;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #E5E7EB;
}

.avatar-icon {
    font-size: 24px;
    color: #9CA3AF;
}

/* Edit Person Button */
.edit-person-btn {
    text-decoration: none;
    color: #FAA300;
    font-size: 12px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 4px;
}

.edit-person-btn:hover {
    color: #E89200;
}

.edit-person-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.edit-icon {
    font-size: 14px;
}

/* Modal Styles */
:deep(.person-modal .modal-content) {
    border-radius: 12px !important;
    border: none !important;
}

:deep(.person-modal .modal-header) {
    border-bottom: 1px solid #E2E8F0 !important;
    padding: 1rem 1.5rem 1important;
}

:deep(.person-modal .modal-title) {
    font-size: 24px !important;
    font-weight: 600 !important;
    color: #1E293B;
}
.person-modal .modal-title {
    font-size: 24px !important;
    font-weight: 600 !important;
    color: #1E293B;
}
:deep(.person-modal .modal-body) {
    padding: 1.5rem;
}

.person-modal-content {
    padding: 0.5rem 0 !important;
}

/* Search Input */
.search-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.person-search-input {
    height: 45px !important;
    border-radius: 25px !important;
    padding-left: 20px !important;
    padding-right: 45px !important;
    border: 1px solid #E2E8F0 !important;
    font-size: 14px !important;
}

.person-search-input::placeholder {
    font-size: 15px !important;
}

.search-icon {
    position: absolute;
    right: 15px;
    color: #FAA300;
    font-size: 20px;
}

/* Person List */
.person-list-scroll {
    max-height: 350px;
    overflow-y: auto;
    padding-right: 5px;
}

/* Custom Scrollbar */
.person-list-scroll::-webkit-scrollbar {
    width: 4px;
}

.person-list-scroll::-webkit-scrollbar-track {
    background: #F1F5F9;
    border-radius: 10px;
}

.person-list-scroll::-webkit-scrollbar-thumb {
    background: #CBD5E1;
    border-radius: 10px;
}

.person-item {
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.2s;
    margin-bottom: 4px;
    border: 1px solid transparent;
}

.person-item:hover {
    background: #F8FAFC;
    border-color: #FAA300;
}

.person-item.selected {
    background: #FFFBEB;
    border-color: #FAA300;
}

.person-item.current {
    background: #F0F9FF;
}

.person-item-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}

.person-item-name {
    font-weight: 600;
    font-size: 14px;
    color: #1E293B;
    font-family: 'Montserrat';
}

.person-item-email {
    font-size: 12px;
    color: #64748B;
    font-family: 'Montserrat';
}

.user-position-badge {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #475569;
    font-size: 11px;
    font-weight: 600;
    line-height: 1;
    padding: 4px 8px;
    border-radius: 999px;
        margin-left: 10px;

}
.user-item-name{
    text-transform: capitalize;
}
.user-item-meta-line {
    margin-top: 2px;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    line-height: 1.3;
    color: #64748b;
    font-family: 'Montserrat';
}

.meta-label {
    font-weight: 600;
    color: #64748b;
}

.meta-value {
    font-weight: 500;
    color: #334155;
}

.meta-divider {
    color: #cbd5e1;
}
.current-badge {
    background: #E2E8F0;
    color: #475569;
    font-size: 11px;
    font-weight: 500;
    padding: 2px 8px;
    border-radius: 12px;
}

.text-warning {
    color: #FAA300 !important;
}

/* Modal Footer */
.modal-footer-custom {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    border-top: 1px solid #E2E8F0;
    padding-top: 1.5rem;
}

.modal-footer-custom .btn {
    padding: 0.5rem 1.5rem;
    border-radius: 100px;
    font-size: 14px;
    font-weight: 500;
}

.modal-footer-custom .btn-light {
    background: #F4F4F4;
    border: none;
    color: #01062C;
}

.modal-footer-custom .btn-light:hover {
    background: #E2E8F0;
}

.modal-footer-custom .btn-warning {
    background: #FAA300;
    border: none;
    color: #fff;
}

.modal-footer-custom .btn-warning:hover:not(:disabled) {
    background: #E89200;
}

.modal-footer-custom .btn-warning:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Alert Styles */
.alert-danger {
    background-color: #FEF2F2;
    border: 1px solid #FEE2E2;
    color: #DC2626;
    border-radius: 8px;
    font-size: 13px;
}
</style>