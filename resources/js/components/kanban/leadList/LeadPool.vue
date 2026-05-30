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

        <template v-else>
        <div class="lead-pool-toolbar">
            <button
                v-if="!selectMode"
                type="button"
                class="lead-pool-toolbar__btn lead-pool-toolbar__btn--primary"
                @click="enterSelectMode"
            >
                <iconify-icon icon="lucide:check-square" />
                <span>Select leads</span>
            </button>

            <template v-else>
                <button
                    type="button"
                    class="lead-pool-toolbar__btn lead-pool-toolbar__btn--ghost"
                    @click="exitSelectMode"
                >
                    <iconify-icon icon="lucide:x" />
                    <span>Done</span>
                </button>

                <label class="lead-pool-toolbar__select-all">
                    <input
                        type="checkbox"
                        :checked="allOnPageSelected"
                        :indeterminate="someOnPageSelected && !allOnPageSelected"
                        @change="onSelectAllChange"
                    />
                    <span>All on page</span>
                </label>

                <span class="lead-pool-toolbar__count">{{ selection.count }} selected</span>

                <button
                    type="button"
                    class="lead-pool-toolbar__btn lead-pool-toolbar__btn--assign"
                    :disabled="!selection.hasSelection || isAssigning"
                    @click="handleBulkAssignToMe"
                >
                    <iconify-icon
                        :icon="isAssigning ? 'lucide:loader-2' : 'lucide:user-check'"
                        :class="{ 'lead-pool-toolbar__spin': isAssigning }"
                    />
                    <span>{{ isAssigning ? 'Assigning…' : 'Assign To Me' }}</span>
                </button>
            </template>
        </div>

        <p v-if="selectMode" class="lead-pool-toolbar__tip">
            Tap cards to select · Shift+click for range · Esc to cancel
        </p>

        <div class="leads-grid" :class="{ 'leads-grid--select-mode': selectMode }">
            <LeadPoolCard
                v-for="lead in leads"
                :key="lead.id"
                v-memo="[lead.id, selection.isSelected(lead.id), selectMode]"
                :selected="selection.isSelected(lead.id)"
                :select-mode="selectMode"
                @toggle="(e) => selection.handleCardClick(lead.id, e)"
                @open="viewLead(lead)"
            >
                <div class="kanban-card bg-white p-12 radius-12 shadow-sm border-0 cursor-pointer lead-card">
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
                        <template v-for="field in enabledFieldsForLead(lead)" :key="field.key">
                            <!-- Responsible Person -->
                            <div v-if="field.key === 'responsible_person' && hasResponsiblePerson(lead)" 
                                class="responsible-info d-flex align-items-center justify-content-between mb-12">
                                <div class="d-flex align-items-center gap-2">
                                    <div
                                        class="person-hover-anchor"
                                        @mouseenter.stop="showPersonHoverCard(lead, 'responsible')"
                                        @mouseleave.stop="hidePersonHoverCard"
                                        @click.stop="openPersonProfile(lead, 'responsible', $event)"
                                    >
                                        <img v-if="lead.responsible_person?.avatar" :src="lead.responsible_person.avatar" alt="" class="avatar-sm rounded-circle" />
                                        <div v-else class="avatar-sm rounded-circle bg-neutral-200 d-flex align-items-center justify-content-center">
                                            <iconify-icon icon="solar:user-bold" class="text-neutral-600"></iconify-icon>
                                        </div>
                                        <transition name="person-hover-pop">
                                            <div
                                                v-if="isPersonHoverVisible(lead, 'responsible') && activePersonHover?.data"
                                                class="person-hover-card"
                                                @mouseenter.stop="cancelPersonHoverHide"
                                                @mouseleave.stop="hidePersonHoverCard"
                                                @click.stop="openPersonProfile(lead, 'responsible', $event)"
                                            >
                                                <div class="person-hover-head">
                                                    <img
                                                        v-if="activePersonHover.data.avatar"
                                                        :src="activePersonHover.data.avatar"
                                                        alt=""
                                                        class="person-hover-avatar"
                                                    />
                                                    <div v-else class="person-hover-avatar person-hover-avatar-fallback d-flex align-items-center justify-content-center">
                                                        <iconify-icon icon="solar:user-bold" class="text-neutral-600" />
                                                    </div>
                                                    <div class="person-hover-head-text">
                                                        <div class="person-hover-name">{{ activePersonHover.data.name }}</div>
                                                        <div class="person-hover-role">{{ activePersonHover.data.position }}</div>
                                                    </div>
                                                </div>
                                                <div class="person-hover-line"><span>Reports To</span><b>{{ activePersonHover.data.manager }}</b></div>
                                                <div class="person-hover-line"><span>Branch</span><b>{{ activePersonHover.data.branch }}</b></div>
                                            </div>
                                        </transition>
                                    </div>
                                    <div>
                                        <div class="info-value" 
                                            @mouseenter.stop="showPersonHoverCard(lead, 'responsible')"
                                            @mouseleave.stop="hidePersonHoverCard"  
                                            @click.stop="openPersonProfile(lead, 'responsible', $event)">
                                            {{ lead.responsible_person?.name }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Assigned By -->
                            <div v-else-if="field.key === 'assigned_by' && hasAssignedBy(lead)">
                                <hr class="mb-2 border-neutral-200">
                                <div class="mt-1 d-flex align-items-center justify-content-between assignedBy">
                                    <div class="info-item">
                                        <div class="info-label text-secondary-light text-xs mb-1">Assigned</div>
                                        <div class="info-value">{{ formatDate(lead.assigned_at) }}</div>
                                    </div>
                                    <div
                                        class="person-hover-anchor"
                                        @mouseenter.stop="showPersonHoverCard(lead, 'assigned')"
                                        @mouseleave.stop="hidePersonHoverCard"
                                        @click.stop="openPersonProfile(lead, 'assigned', $event)"
                                    >
                                        <img v-if="lead.parent?.avatar" :src="lead.parent.avatar" alt="" class="avatar-sm rounded-circle" />
                                        <div v-else class="avatar-sm rounded-circle bg-neutral-200 d-flex align-items-center justify-content-center">
                                            <iconify-icon icon="solar:user-bold" class="text-neutral-600"></iconify-icon>
                                        </div>
                                        <transition name="person-hover-pop">
                                            <div
                                                v-if="isPersonHoverVisible(lead, 'assigned') && activePersonHover?.data"
                                                class="person-hover-card person-hover-card-right"
                                                @mouseenter.stop="cancelPersonHoverHide"
                                                @mouseleave.stop="hidePersonHoverCard"
                                                @click.stop="openPersonProfile(lead, 'assigned', $event)"
                                            >
                                                <div class="person-hover-head">
                                                    <img
                                                        v-if="activePersonHover.data.avatar"
                                                        :src="activePersonHover.data.avatar"
                                                        alt=""
                                                        class="person-hover-avatar"
                                                    />
                                                    <div v-else class="person-hover-avatar person-hover-avatar-fallback d-flex align-items-center justify-content-center">
                                                        <iconify-icon icon="solar:user-bold" class="text-neutral-600" />
                                                    </div>
                                                    <div class="person-hover-head-text">
                                                        <div class="person-hover-name">{{ activePersonHover.data.name }}</div>
                                                        <div class="person-hover-role">{{ activePersonHover.data.position }}</div>
                                                    </div>
                                                </div>
                                                <div class="person-hover-line"><span>Reports To</span><b>{{ activePersonHover.data.manager }}</b></div>
                                                <div class="person-hover-line"><span>Branch</span><b>{{ activePersonHover.data.branch }}</b></div>
                                            </div>
                                        </transition>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Other fields -->
                            <div v-else-if="field.key === 'created_at'" class="info-item date-info d-flex align-items-center gap-1 mb-8">
                                <span class="text-secondary-light text-xs">Created</span>
                                <span>{{ formatDate(lead.created_at) }}</span>
                            </div>
                            <div v-else-if="field.key === 'created_by'" class="info-item mb-8">
                                <div class="info-label text-secondary-light text-xs">Created By</div>
                                <div class="info-value">{{ lead.added_by_user?.name || lead.added_by || '—' }}</div>
                            </div>
                            <div v-else-if="field.key === 'first_name'" class="info-item mb-8">
                                <div class="info-label text-secondary-light text-xs">Name</div>
                                <div class="info-value">{{ lead.salutation || '' }} {{ lead.first_name }}</div>
                            </div>
                            <div v-else-if="field.key === 'lead_source' && lead.lead_source" class="info-item mb-8">
                                <div class="info-label text-secondary-light text-xs mb-1">Source</div>
                                <div class="info-value">{{ lead.lead_source }}</div>
                            </div>
                            <div v-else-if="field.key === 'lead_branch_source' && lead.lead_branch_source" class="info-item mb-12">
                                <div class="info-label text-secondary-light text-xs mb-1">Lead Branch Source</div>
                                <div class="info-value">{{ lead.lead_branch_source }}</div>
                            </div>
                            <div v-else-if="field.key === 'work_phone' && lead.work_phone" class="info-item mb-8">
                                <div class="info-label text-secondary-light text-xs">Phone</div>
                                <div class="info-value">{{ lead.work_phone.slice(0,8) + '....' }}</div>
                            </div>
                            <div v-else-if="field.key === 'email' && lead.email" class="info-item mb-8">
                                <div class="info-label text-secondary-light text-xs">Email</div>
                                <div class="info-value">{{ formatMaskedEmail(lead.email) }}</div>
                            </div>
                            <div v-else-if="field.key === 'whatsapp_number' && lead.whatsapp_number" class="info-item mb-8">
                                <div class="info-label text-secondary-light text-xs">WhatsApp</div>
                                <div class="info-value">{{ lead.whatsapp_number }}</div>
                            </div>
                            <div v-else-if="field.key === 'api_first_question' && lead.api_first_question" class="info-item mb-8">
                                <div class="info-label text-secondary-light text-xs">More Information</div>
                                <div class="info-value">{{ formatMaskedQuestion(lead.api_first_question) }}</div>
                            </div>
                            <div v-else-if="hasDynamicFieldValue(lead, field.key)" class="info-item mb-8">
                                <div class="info-label text-secondary-light text-xs">{{ field.label || field.key }}</div>
                                <div class="info-value">{{ getDynamicFieldDisplay(lead, field.key) }}</div>
                            </div>
                        </template>
                    </div>
                </div>
            </LeadPoolCard>
        </div>

        <!-- Pagination -->
        <div v-if="lastPage > 1" class="lead-pool-pagination d-flex align-items-center justify-content-center gap-2 mt-3">
            <button
                type="button"
                class="page-btn"
                :disabled="currentPage <= 1 || loading"
                @click="goToPage(currentPage - 1)"
            >
                <iconify-icon icon="lucide:chevron-left" />
            </button>
            <button
                v-for="p in visiblePages"
                :key="p.key"
                type="button"
                class="page-btn page-btn-num"
                :class="{ active: p.value === currentPage, ellipsis: p.value === null }"
                :disabled="p.value === null || loading"
                @click="p.value !== null && goToPage(p.value)"
            >
                {{ p.value === null ? '…' : p.value }}
            </button>
            <button
                type="button"
                class="page-btn"
                :disabled="currentPage >= lastPage || loading"
                @click="goToPage(currentPage + 1)"
            >
                <iconify-icon icon="lucide:chevron-right" />
            </button>
            <span class="page-summary text-secondary-light">
                Page {{ currentPage }} / {{ lastPage }} · {{ total }} total
            </span>
        </div>
        </template>
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
    
    <ProfilePopup
        v-if="showProfilePopup && profileUserId"
        v-model="showProfilePopup"
        :user-id="profileUserId"
        @update:model-value="closeProfilePopup"
    />
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import api from '@/plugins/axios'
import Swal from 'sweetalert2'
import ViewLeadModal from '../viewLead/ViewLeadModal.vue'
import DuplicateLeadsModal from './DuplicateLeadsModal.vue'
import ProfilePopup from '../shared/ProfilePopup.vue'
import LeadPoolCard from './LeadPoolCard.vue'
import { useLeadPoolSelection } from './composables/useLeadPoolSelection.js'
import { useLeadPoolBulkActions } from './composables/useLeadPoolBulkActions.js'

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
const showProfilePopup = ref(false)
const profileUserId = ref(null)
const activePersonHover = ref(null)
const personHoverHideTimer = ref(null)

const selectMode = ref(false)
const orderedLeadIds = computed(() => leads.value.map((l) => l.id))
const selection = useLeadPoolSelection(() => orderedLeadIds.value)
const { isAssigning, assignToMe } = useLeadPoolBulkActions()

// Role gating — comments/activities/"More Information" on lead-pool cards are super_admin-only.
const currentUserRoles = (() => {
    try {
        const raw = localStorage.getItem('user')
        const parsed = raw ? JSON.parse(raw) : null
        return Array.isArray(parsed?.roles) ? parsed.roles : []
    } catch {
        return []
    }
})()
const isSuperAdmin = currentUserRoles.includes('super_admin')
const isAdminOrSuper = isSuperAdmin || currentUserRoles.includes('admin')

function enterSelectMode() {
  selectMode.value = true
}

function exitSelectMode() {
  selectMode.value = false
  selection.clear()
}

const allOnPageSelected = computed(
  () => leads.value.length > 0 && leads.value.every((l) => selection.isSelected(l.id)),
)
const someOnPageSelected = computed(() => leads.value.some((l) => selection.isSelected(l.id)))

function onSelectAllChange(event) {
  if (event.target.checked) selection.selectAllOnPage()
  else selection.clear()
}

async function handleBulkAssignToMe() {
  if (isAssigning.value) return
  const ids = selection.selectedIds.value.map(Number).filter(Boolean)
  if (!ids.length) return

  try {
    const { ok, failed } = await assignToMe(ids)
    if (ok.length) {
      window.$showNotification?.(
        ok.length === 1
          ? '1 lead assigned to you and moved to Assigned'
          : `${ok.length} leads assigned to you and moved to Assigned`,
        'success',
      )
      exitSelectMode()
      await fetchLeadPool()
    }
    if (failed.length) {
      const msg =
        failed.length === 1
          ? failed[0].message
          : `${failed.length} leads could not be assigned`
      window.$showNotification?.(msg, failed.length === ids.length ? 'error' : 'warning')
      if (ok.length) await fetchLeadPool()
    }
  } catch (err) {
    window.$showNotification?.(err?.message || 'Assignment failed', 'error')
  }
}

function onLeadPoolKeydown(event) {
  const tag = event.target?.tagName?.toLowerCase()
  if (tag === 'input' || tag === 'textarea' || tag === 'select' || event.target?.isContentEditable) {
    return
  }
  if (event.key === 'Escape') {
    if (selectMode.value) exitSelectMode()
    else selection.clear()
    return
  }
  if (selectMode.value && (event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'a') {
    event.preventDefault()
    selection.selectAllOnPage()
  }
}

// Pagination
const currentPage = ref(1)
const perPage = ref(24)
const lastPage = ref(1)
const total = ref(0)

// Filters applied from the navbar search modal — merged into every fetch.
const currentQuery = ref({})

// Numeric pager with ellipsis: always show first/last + a window around current.
const visiblePages = computed(() => {
    const last = lastPage.value
    const curr = currentPage.value
    if (last <= 7) {
        return Array.from({ length: last }, (_, i) => ({ key: `p${i + 1}`, value: i + 1 }))
    }
    const pages = [{ key: 'p1', value: 1 }]
    const start = Math.max(2, curr - 1)
    const end = Math.min(last - 1, curr + 1)
    if (start > 2) pages.push({ key: 'gap-l', value: null })
    for (let i = start; i <= end; i++) pages.push({ key: `p${i}`, value: i })
    if (end < last - 1) pages.push({ key: 'gap-r', value: null })
    pages.push({ key: `p${last}`, value: last })
    return pages
})

// Card fields configuration (you can fetch from API or use default)
const cardFields = ref([
    { key: 'first_name', enabled: true, order: 1, label: 'Name' },
    { key: 'lead_source', enabled: true, order: 2, label: 'Source' },
    { key: 'lead_branch_source', enabled: true, order: 3, label: 'Lead Branch Source' },
    { key: 'work_phone', enabled: true, order: 4, label: 'Phone' },
    { key: 'email', enabled: true, order: 5, label: 'Email' },
    { key: 'whatsapp_number', enabled: true, order: 6, label: 'WhatsApp' },
    { key: 'api_first_question', enabled: true, order: 7, label: 'More Information' }
])

// Fetch leads with stage_id = 10 (Lead Pool), paginated and filtered.
const fetchLeadPool = async () => {
    loading.value = true
    try {
        const params = {
            stage_id: 10,
            paginate: 1,
            page: currentPage.value,
            per_page: perPage.value,
            ...(currentQuery.value || {}),
        }
        // stage_id is the lead-pool fixed scope — never overridden by the search query.
        params.stage_id = 10

        const response = await api.get('/leads', { params })
        const body = response.data || {}
        const data = body.data || []

        // Backend returns flat leads array + pagination meta in paginate mode; fall back to
        // the legacy grouped shape for safety.
        if (Array.isArray(data) && body.pagination) {
            leads.value = data
            lastPage.value = Number(body.pagination.last_page) || 1
            total.value = Number(body.pagination.total) || data.length
            perPage.value = Number(body.pagination.per_page) || perPage.value
            currentPage.value = Number(body.pagination.current_page) || currentPage.value
        } else if (data && data.length > 0 && data[0]?.leads) {
            leads.value = data[0].leads
            lastPage.value = 1
            total.value = leads.value.length
        } else if (Array.isArray(data)) {
            leads.value = data
            lastPage.value = 1
            total.value = data.length
        } else {
            leads.value = []
            lastPage.value = 1
            total.value = 0
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
        lastPage.value = 1
        total.value = 0
    } finally {
        loading.value = false
    }
}

const goToPage = (page) => {
    const target = Number(page)
    if (!Number.isFinite(target)) return
    if (target < 1 || target > lastPage.value) return
    if (target === currentPage.value) return
    currentPage.value = target
    fetchLeadPool()
}


// Get enabled fields for lead
const enabledFieldsForLead = (lead) => {
    return cardFields.value
        .filter(field => field.enabled)
        // "More Information" (api_first_question) is super_admin-only in the lead pool.
        .filter(field => field.key !== 'api_first_question' || isSuperAdmin)
        .filter(field => hasDynamicFieldValue(lead, field.key))
        .sort((a, b) => a.order - b.order)
}

// Helper functions for dynamic fields
const hasDynamicFieldValue = (lead, key) => {
    if (key === 'created_by') {
        return !!(lead?.added_by_user?.name || lead?.added_by)
    }
    const value = lead?.[key]
    if (value == null) return false
    if (typeof value === 'string') return value.trim().length > 0
    if (Array.isArray(value)) return value.length > 0
    if (typeof value === 'object') return Object.keys(value).length > 0
    return true
}

const getDynamicFieldDisplay = (lead, key) => {
    if (key === 'created_by') {
        return lead?.added_by_user?.name || lead?.added_by || '—'
    }
    const value = lead?.[key]
    if (value == null) return '—'
    if (key === 'email') return formatMaskedEmail(value)
    if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') return String(value)
    if (Array.isArray(value)) return value.map(v => (typeof v === 'object' ? (v?.name || v?.label || JSON.stringify(v)) : String(v))).join(', ')
    if (typeof value === 'object') return value?.name || value?.label || JSON.stringify(value)
    return String(value)
}

const hasResponsiblePerson = (lead) => {
    return !!(lead?.responsible_person?.name || lead?.responsible_person?.avatar)
}

const hasAssignedBy = (lead) => {
    return !!(lead?.assigned_at || lead?.parent?.name || lead?.parent?.avatar)
}

// Person hover card functions
const normalizePersonHoverData = (person, task = {}, type = 'responsible', fallbackName = 'Unknown') => {
    const name = person?.name || person?.full_name || fallbackName
    const position = person?.position || person?.designation || person?.job_title || person?.role_name || person?.role || 'Team Member'
    const manager = person?.manager_name ||
        person?.team_lead_name ||
        person?.reports_to_name ||
        person?.parent_name ||
        person?.manager?.name ||
        person?.team_lead?.name ||
        person?.parent?.name ||
        (type === 'responsible' ? (task?.parent?.name || task?.manager?.name || task?.team_lead?.name) : null) ||
        (type === 'assigned' ? (task?.parent?.manager_name || task?.parent?.manager?.name || task?.manager?.name) : null) ||
        'Not specified'
    const branch = person?.branch_name ||
        person?.branch?.name ||
        person?.office ||
        person?.team ||
        person?.department ||
        person?.location ||
        person?.team_name ||
        task?.lead_branch_source ||
        task?.branch_name ||
        task?.branch?.name ||
        task?.office_branch_name ||
        task?.office_branch ||
        'Not specified'
    const avatar = person?.avatar || person?.image || person?.photo || ''
    return { name, position, manager, branch, avatar }
}

const showPersonHoverCard = (lead, type) => {
    cancelPersonHoverHide()
    const person = type === 'assigned' ? lead?.parent : lead?.responsible_person
    const fallbackName = type === 'assigned' ? (lead?.parent?.name || 'Assigned By') : (lead?.responsible_person?.name || 'Responsible Person')
    activePersonHover.value = {
        leadId: lead?.id,
        type,
        data: normalizePersonHoverData(person, lead, type, fallbackName),
    }
}

const hidePersonHoverCard = () => {
    cancelPersonHoverHide()
    personHoverHideTimer.value = setTimeout(() => {
        activePersonHover.value = null
    }, 90)
}

const cancelPersonHoverHide = () => {
    if (personHoverHideTimer.value) {
        clearTimeout(personHoverHideTimer.value)
        personHoverHideTimer.value = null
    }
}

const isPersonHoverVisible = (lead, type) => {
    return activePersonHover.value?.leadId === lead?.id && activePersonHover.value?.type === type
}

const openPersonProfile = (lead, type, event) => {
    if (event) event.stopPropagation()
    
    const person = type === 'assigned' ? lead?.parent : lead?.responsible_person
    if (!person?.id) return
    
    profileUserId.value = person.id
    showProfilePopup.value = true
}

const closeProfilePopup = () => {
    showProfilePopup.value = false
    profileUserId.value = null
}

// View lead details
const viewLead = (lead) => {
    selectedLeadId.value = lead.id
    showViewModal.value = true
    if (lead?.id) {
        // Fire-and-forget: log a "view" history entry so admins see who opened the lead.
        api.get(`/leads/${lead.id}/history/view`).catch(() => {})
    }
}

// Handle lead update from modal
const handleLeadUpdated = (updatedLead) => {
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
    if (leadId) {
        api.get(`/leads/${leadId}/history/view`).catch(() => {})
    }
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
    window.addEventListener('keydown', onLeadPoolKeydown)
    fetchLeadPool()
})

onUnmounted(() => {
    window.removeEventListener('keydown', onLeadPoolKeydown)
})

// Expose refresh + a programmatic search-apply for the parent kanban if needed.
defineExpose({
    fetchLeadPool,
    setQuery(query) {
        currentQuery.value = query && typeof query === 'object' ? { ...query } : {}
        currentPage.value = 1
        fetchLeadPool()
    },
})
</script>

<style scoped>
.lead-pool-wrapper {
    padding: 16px 20px;
    min-height: calc(100vh - 72px);
}

.leads-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    grid-auto-rows: 1fr;
    gap: 10px;
    align-items: stretch;
}

.lead-pool-wrapper .lead-card {
    display: flex;
    height: 100%;
    width: 100%;
}

.lead-pool-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 12px;
    margin-bottom: 8px;
    padding: 10px 12px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.lead-pool-toolbar__btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 8px;
    border: 1px solid transparent;
    font-size: 13px;
    font-weight: 600;
    font-family: Montserrat, Inter, system-ui, sans-serif;
    cursor: pointer;
}

.lead-pool-toolbar__btn--primary {
    color: #fff;
    background: rgba(0, 167, 250, 0.35);
    border-color: rgba(0, 167, 250, 0.5);
}

.lead-pool-toolbar__btn--ghost {
    color: rgba(255, 255, 255, 0.9);
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.15);
}

.lead-pool-toolbar__btn--assign {
    margin-left: auto;
    color: #fff;
    background: linear-gradient(135deg, #00a7fa 0%, #733e87 100%);
}

.lead-pool-toolbar__btn--assign:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.lead-pool-toolbar__spin {
    animation: lead-pool-spin 0.8s linear infinite;
}

@keyframes lead-pool-spin {
    to { transform: rotate(360deg); }
}

.lead-pool-toolbar__select-all {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin: 0;
    font-size: 12px;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.85);
    cursor: pointer;
}

.lead-pool-toolbar__count {
    font-size: 12px;
    font-weight: 700;
    color: #7dd3fc;
}

.lead-pool-toolbar__tip {
    margin: 0 0 10px;
    font-size: 11px;
    color: rgba(255, 255, 255, 0.55);
}

.leads-grid--select-mode {
    padding: 4px;
    border-radius: 12px;
    outline: 1px dashed rgba(0, 167, 250, 0.25);
}

.lead-pool-wrapper .kanban-card {
    padding: 10px !important;
    border-radius: 11px !important;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    color: #1e293b;
    border: 1px solid #e5e7eb !important;
    width: 100%;
    display: flex;
    flex-direction: column;
    min-height: 180px;
}

.lead-pool-wrapper .kanban-card .task-info {
    flex: 1 1 auto;
}

.lead-pool-wrapper .kanban-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 6px rgba(0, 0, 0, 0.08) !important;
}

.lead-pool-wrapper .task-title {
    font-family: Montserrat;
    font-weight: 700;
    font-size: 12px;
    line-height: 1.4;
    letter-spacing: -0.25px;
    color: #0B0736;
}

.lead-pool-wrapper .mb-12 {
    margin-bottom: 8px !important;
}

.lead-pool-wrapper .mb-8 {
    margin-bottom: 6px !important;
}

.lead-pool-wrapper .task-info hr {
    margin: 6px 0 !important;
}

.task-header {
    align-items: flex-start;
}

.lead-pool-wrapper .info-label {
    color: #979797;
    font-weight: 500;
    font-size: 11px;
}

.lead-pool-wrapper .info-value {
    font-weight: 500;
    font-size: 11px;
    line-height: 1.3;
    color: #353535;
}

.lead-pool-wrapper .avatar-sm {
    width: 28px;
    height: 28px;
    object-fit: cover;
}

.lead-pool-wrapper .assignedBy .avatar-sm {
    width: 26px;
    height: 26px;
}

.lead-pool-wrapper .date-info {
    font-family: Montserrat;
    font-weight: 500;
    font-size: 10px;
    line-height: 1.2;
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

.lead-pool-wrapper .duplicate-icon-wrapper {
    position: relative;
    width: 22px;
    height: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.lead-pool-wrapper .duplicate-rectangle {
    position: absolute;
    width: 18px;
    height: 22px;
    background-color: #FFFFFF;
    border: 1px solid #D1D5DB;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.lead-pool-wrapper .duplicate-rectangle-back {
    top: 3px;
    left: 3px;
    z-index: 1;
}

.lead-pool-wrapper .duplicate-rectangle-front {
    top: 0;
    left: 0;
    z-index: 2;
}

.lead-pool-wrapper .duplicate-number {
    font-family: Montserrat;
    font-weight: 600;
    font-size: 10px;
    line-height: 1;
    color: #0B0736;
}

.lead-pool-wrapper .service-dup-badge {
    background: #ff4d4f;
    color: white;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 5px;
    margin-left: 5px;
}

/* Person hover card styles */
.person-hover-anchor {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.person-hover-card {
    position: absolute;
    top: calc(100% + 8px);
    left: -10px;
    width: 200px;
    z-index: 60;
    border-radius: 12px;
    border: 1px solid #dbe3ef;
    background: rgba(255, 255, 255, 0.97);
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.2);
    backdrop-filter: blur(8px);
    padding: 10px;
}

.person-hover-card-right {
    right: -10px;
    left: auto;
}

.person-hover-head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.lead-pool-wrapper .person-hover-avatar {
    width: 32px;
    height: 32px;
    border-radius: 999px;
    object-fit: cover;
    border: 1px solid #e2e8f0;
}

.person-hover-avatar-fallback {
    background: #f1f5f9;
}

.person-hover-name {
    font-size: 12px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
}

.person-hover-role {
    margin-top: 1px;
    font-size: 11px;
    color: #64748b;
    line-height: 1.2;
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
    max-width: 130px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.person-hover-pop-enter-active,
.person-hover-pop-leave-active {
    transition: opacity 0.14s ease, transform 0.14s ease;
}

.person-hover-pop-enter-from,
.person-hover-pop-leave-to {
    opacity: 0;
    transform: translateY(4px) scale(0.98);
}

.bg-neutral-200 {
    background-color: #e5e7eb;
}

.radius-12 {
    border-radius: 12px;
}

.lead-pool-wrapper .gap-2 {
    gap: 8px;
}

.cursor-pointer {
    cursor: pointer;
}

/* Pagination */
.lead-pool-pagination {
    flex-wrap: wrap;
    padding: 8px 4px 16px;
}

.lead-pool-pagination .page-btn {
    min-width: 32px;
    height: 32px;
    padding: 0 8px;
    border: 1px solid #e5e7eb;
    background: #fff;
    color: #334155;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.lead-pool-pagination .page-btn:hover:not(:disabled):not(.ellipsis) {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.lead-pool-pagination .page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.lead-pool-pagination .page-btn.active {
    background: #0B0736;
    border-color: #0B0736;
    color: #fff;
}

.lead-pool-pagination .page-btn.ellipsis {
    border-color: transparent;
    background: transparent;
    cursor: default;
}

.lead-pool-pagination .page-summary {
    margin-left: 8px;
    font-size: 12px;
    color: #64748b;
}
</style>