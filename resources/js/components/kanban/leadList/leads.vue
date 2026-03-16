<template>
    <div class="kanban-outer">
        <div ref="kanbanContainerRef" class="kanban-container" @scroll="updateScrollArrows">
        <!-- Loading state -->
        <div v-if="loading && columns.length === 0" class="kanban-empty-state kanban-loading">
            <div class="kanban-empty-spinner"></div>
            <p class="kanban-empty-title">Loading stages…</p>
        </div>
        <!-- Error state -->
        <div v-if="error && columns.length === 0" class="kanban-empty-state kanban-error-state">
            <iconify-icon icon="lucide:alert-circle" class="kanban-empty-icon"></iconify-icon>
            <p class="kanban-empty-title">Could not load stages</p>
            <p class="kanban-empty-text">{{ error }}</p>
            <button type="button" class="kanban-empty-btn" @click="fetchLeads(true)">Try again</button>
        </div>
        <!-- No stages yet -->
        <div v-else-if="!loading && columns.length === 0" class="kanban-empty-state">
            <iconify-icon icon="lucide:columns-3" class="kanban-empty-icon"></iconify-icon>
            <p class="kanban-empty-title">No stages yet</p>
            <p class="kanban-empty-text">Use the menu above to add a new stage and start organizing your leads.</p>
        </div>
        <!-- Draggable Columns -->
        <draggable v-else v-model="columns" item-key="status" class="kanban-wrapper kanban-wrapper-tight d-flex h-100" :group="'columns'"
            handle=".column-header"
            :ghost-class="'ghost'" :drag-class="'dragging'">
            <template #item="{ element: column, index }">
                <div class="kanban-column radius-12 d-flex flex-column" :style="{ '--column-color': column.color }">
                    <div class=" p-0 overflow-hidden shadow-none border-0 bg-transparent h-100 d-flex flex-column">
                        <div class="card-body p-0 d-flex flex-column h-100">
                            <!-- Column Header -->
                            <div class="column-header d-flex align-items-center justify-content-between p-8 cursor-move flex-shrink-0" :style="{ backgroundColor: column.color }">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="stage-circle">
                                        <div class="stage-dot" :style="{ backgroundColor: column.color }"></div>
                                    </div>
                                    <div v-if="editingStageId !== column.status" class="header-title-wrapper" @click="startEditingStage(column)">
                                        <p class="header-title">{{ column.title }}</p>
                                         <small class="leads-count-badge" v-if="column.leads.length>0 && stagePagination[column.status] && stagePagination[column.status].total > column.leads.length ">
                                             {{ stagePagination[column.status]?.total || column.leads.length }}
                                        </small>
                                        <small class="leads-count-badge" v-else >
                                            {{ column.leads.length }}
                                        </small>
                                    </div>
                                    <input 
                                        v-else
                                        v-model="editingStageTitle"
                                        @keyup.enter="saveStageName(column)"
                                        @keyup.esc="cancelEditingStage"
                                        @blur="saveStageName(column)"
                                        class="header-title-input"
                                        ref="stageTitleInput"
                                        type="text"
                                    />
                                </div>
                                <div class="dropdown">
                                    <button type="button" data-bs-toggle="dropdown" aria-expanded="false" class="bg-transparent border-0 p-0 d-flex align-items-center">
                                        <iconify-icon icon="entypo:dots-three-vertical" class="text-xl text-white"></iconify-icon>
                                    </button>
                                     <ul class="dropdown-menu p-12 border bg-base shadow">
                                        <li>
                                            <a href="#" class="duplicate-button dropdown-item px-10 py-1 text-secondary-light bg-hover-neutral-200 text-hover-neutral-900 d-flex align-items-center gap-2" @click="editStage(column)">
                                                <iconify-icon class="text-xs" icon="lucide:edit"></iconify-icon>
                                                Edit Stage
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div
                                class="column-content column-content-scrollable p-8 flex-grow-1 d-flex flex-column"
                                @scroll="(e) => onColumnScroll(column, e)"
                            >
                                <!-- Tasks -->
                                <draggable v-model="column.leads" :group="'tasks'" item-key="id"
                                    class="tasks-list flex-grow-1" :ghost-class="'ghost'"
                                    :drag-class="'dragging'"
                                    @change="(evt) => onLeadDragChange(evt, column)">
                                    <template #item="{ element: task, index }">
                                            <div
                                                :key="task.id"
                                                class="kanban-card bg-white p-12 radius-12 mb-10 shadow-sm border-0 cursor-pointer"
                                                @click="viewLead(task)"
                                            >
                                                <!-- Task Header - Lead Name (دائماً ظاهر) -->
                                                <div class="task-header d-flex align-items-center justify-content-between gap-2 mb-12">
                                                    <p class="task-title flex-grow-1 mb-0">{{ task.lead_name }}</p>
                                                    <div 
                                                        v-if="isFieldEnabled('duplicate_count') && index === 0 && isAdminOrSuperAdmin"
                                                        class="duplicate-badge position-relative cursor-pointer"
                                                        @click.stop="openDuplicateLeadsModal(task.id, $event)"
                                                    >
                                                        <div class="duplicate-icon-wrapper">
                                                            <div class="duplicate-rectangle duplicate-rectangle-back"></div>
                                                            <div class="duplicate-rectangle duplicate-rectangle-front">
                                                                <span class="duplicate-number">{{ task.duplicate_no || 0 }}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                 <div class="task-info">
                                                        <!-- عرض الفيلدات حسب الإعدادات والترتيب -->
                                                        <template v-for="field in enabledFields" :key="field.key">
                                                            <!-- Created By / Date -->
                                                            <div v-if="field.key === 'created_by' || field.key === 'created_at'" 
                                                                 class="info-item date-info d-flex align-items-center gap-1 mb-8">
                                                                <span v-if="field.key === 'created_by'">Created By</span>
                                                                <span>{{ formatDate(task.created_at) }}</span>
                                                            </div>
                                                            
                                                            <!-- First Name -->
                                                            <div v-else-if="field.key === 'first_name'" class="info-item mb-8">
                                                                <div class="info-label text-secondary-light text-xs">Name</div>
                                                                <div class="info-value">{{ task.salutation }} {{ task.first_name }}</div>
                                                            </div>
                                                            
                                                            <!-- Source -->
                                                            <div v-else-if="field.key === 'lead_source'" class="info-item mb-8">
                                                                <div class="info-label text-secondary-light text-xs mb-1">Source</div>
                                                                <div class="info-value">{{ task.lead_source }}</div>
                                                            </div>
                                                            
                                                            <!-- Lead Branch Source -->
                                                            <div v-else-if="field.key === 'lead_branch_source' && task.lead_branch_source" class="info-item mb-12">
                                                                <div class="info-label text-secondary-light text-xs mb-1">Lead Branch Source</div>
                                                                <div class="info-value">{{ task.lead_branch_source }}</div>
                                                            </div>
                                                              
                                                            <!-- Work Phone -->
                                                            <div v-else-if="field.key === 'work_phone'" class="info-item mb-8">
                                                                <div class="info-label text-secondary-light text-xs">Phone</div>
                                                                <div class="info-value">{{ task.work_phone || task.whatsapp_number || '—' }}</div>
                                                            </div>
                                                            
                                                            <!-- Email -->
                                                            <div v-else-if="field.key === 'email'" class="info-item mb-8">
                                                                <div class="info-label text-secondary-light text-xs">Email</div>
                                                                <div class="info-value">{{ task.email || '—' }}</div>
                                                            </div>
                                                            
                                                            <!-- Bedrooms -->
                                                            <div v-else-if="field.key === 'bedrooms' && task.bedrooms" class="info-item mb-8">
                                                                <div class="info-label text-secondary-light text-xs">Bedrooms</div>
                                                                <div class="info-value">{{ task.bedrooms }}</div>
                                                            </div>
                                                            
                                                            <!-- Budget -->
                                                            <div v-else-if="field.key === 'budget' && task.budget" class="info-item mb-8">
                                                                <div class="info-label text-secondary-light text-xs">Budget</div>
                                                                <div class="info-value">{{ task.budget }} {{ task.currency || '' }}</div>
                                                            </div>
                                                            
                                                            <!-- WhatsApp -->
                                                            <div v-else-if="field.key === 'whatsapp_number' && task.whatsapp_number" class="info-item mb-8">
                                                                <div class="info-label text-secondary-light text-xs">WhatsApp</div>
                                                                <div class="info-value">{{ task.whatsapp_number }}</div>
                                                            </div>
                                                            
                                                            <!-- Responsible Person -->
                                                            <div v-else-if="field.key === 'responsible_person'" class="responsible-info d-flex align-items-center justify-content-between mb-12">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <img v-if="task.responsible_person?.avatar" :title="task.responsible_person?.name" :src="task.responsible_person.avatar" alt="" class="avatar-sm rounded-circle" />
                                                                    <div v-else class="avatar-sm rounded-circle bg-neutral-200 d-flex align-items-center justify-content-center">
                                                                        <iconify-icon icon="solar:user-bold" class="text-neutral-600"></iconify-icon>
                                                                    </div>
                                                                    <div>
                                                                        <div class="info-value">{{ task.responsible_person?.name }}</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- Assigned By -->
                                                            <div v-else-if="field.key === 'assigned_by'">
                                                                <hr class="mb-2 border-neutral-200">
                                                                <div class="mt-1 d-flex align-items-center justify-content-between assignedBy">
                                                                    <div class="info-item">
                                                                        <div class="info-label text-secondary-light text-xs mb-1">Assigned By</div>
                                                                        <div class="info-value">{{ formatDate(task.assigned_at) }}</div>
                                                                    </div>
                                                                    <img v-if="task?.parent?.avatar" :src="task.parent.avatar"  :title="task.parent.name" alt="" class="avatar-sm rounded-circle" />
                                                                    <div v-else class="avatar-sm rounded-circle bg-neutral-200 d-flex align-items-center justify-content-center">
                                                                        <iconify-icon icon="solar:user-bold" class="text-neutral-600"></iconify-icon>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                          
                                                        </template>
                                                    </div>
                                            </div>
                                        </template>
                                </draggable>
                                
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </draggable>
        </div>
        <template v-if="!loading && !error && columns.length > 0">
            <!-- Left: hide when at start -->
            <div
                v-show="showLeftZone"
                class="kanban-nav-zone kanban-nav-zone-left"
                title="Move left"
                aria-label="Move left"
                @mouseenter="startScrollLeft"
                @mouseleave="stopScroll"
            >
                <span class="kanban-nav-arrow kanban-nav-arrow-left">
                    <iconify-icon icon="lucide:chevron-left" class="kanban-nav-arrow-icon" />
                </span>
            </div>
            <!-- Right: hide when at end -->
            <div
                v-show="showRightZone"
                class="kanban-nav-zone kanban-nav-zone-right"
                title="Move the stages"
                aria-label="Move the stages"
                @mouseenter="startScrollRight"
                @mouseleave="stopScroll"
            >
                <span class="kanban-nav-arrow kanban-nav-arrow-right">
                    <iconify-icon icon="lucide:chevron-right" class="kanban-nav-arrow-icon" />
                </span>
            </div>
        </template>
    </div>

    <!-- View Lead Modal -->
    <ViewLeadModal
        v-model="showViewModal"
        :leadId="selectedLead"
        @lead-updated="handleLeadUpdatedFromModal"
    />

    <!-- Duplicate Leads Dropdown -->
    <DuplicateLeadsModal 
        v-model="showDuplicateModal" 
        :leadId="selectedLeadForDuplicates"
        :triggerElement="currentTriggerElement"
        @view-lead="handleViewDuplicateLead"
    />
     <StageChangeReasonModal
            ref="stageChangeReasonModal"
            :leadId="pendingStageChange?.leadId"
            :targetStageId="pendingStageChange?.targetStageId"
            :targetStageName="pendingStageChange?.targetStageName"
            @submit="handleStageChangeWithReason"
            @closed="clearPendingStageChange"
        />
    <ConvertLeadModal
        ref="convertModalRef"
        :leadId="selectedLeadForConversion"
        :leadData="selectedLeadData"
        @converted="handleLeadConverted"
        @closed="selectedLeadForConversion = null"
    />
    <!-- Add/Edit Task Modal -->
    <div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title text-xl mb-0" id="addTaskModalLabel">
                        {{ isEditing ? 'Edit Task' : 'Add New Task' }}
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="taskForm">
                        <input type="hidden" id="editTaskId" v-model="currentTask.id">
                        <div class="mb-3">
                            <label for="taskTitle"
                                class="form-label fw-semibold text-primary-light text-sm mb-8">Title</label>
                            <input type="text" class="form-control" v-model="currentTask.title"
                                placeholder="Enter Event Title" id="taskTitle" required>
                        </div>
                        <div class="mb-3">
                            <label for="taskName"
                                class="form-label fw-semibold text-primary-light text-sm mb-8">Name</label>
                            <input type="text" class="form-control" v-model="currentTask.name"
                                placeholder="Enter Name" id="taskName">
                        </div>
                        <div class="mb-3">
                            <label for="taskSource"
                                class="form-label fw-semibold text-primary-light text-sm mb-8">Source</label>
                            <input type="text" class="form-control" v-model="currentTask.source"
                                placeholder="Enter Source" id="taskSource">
                        </div>
                        <div class="mb-3">
                            <label for="taskBranch"
                                class="form-label fw-semibold text-primary-light text-sm mb-8">Branch Source</label>
                            <input type="text" class="form-control" v-model="currentTask.branchSource"
                                placeholder="Enter Branch Source" id="taskBranch">
                        </div>
                        <div class="mb-3">
                            <label for="taskDescription"
                                class="form-label fw-semibold text-primary-light text-sm mb-8">Description</label>
                            <textarea class="form-control" v-model="currentTask.description" id="taskDescription"
                                rows="3" placeholder="Write some text"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer justify-content-center gap-3">
                    <button type="button"
                        class="border border-danger-600 bg-hover-danger-200 text-danger-600 text-md px-50 py-11 radius-8"
                        data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-primary border border-primary-600 text-md px-28 py-12 radius-8"
                        @click="saveTask">
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div v-if="showStageModal" class="stage-modal-overlay">
    <div class="stage-modal">
            <h6 class="mb-3">
                {{ isEditingStage ? 'Edit Stage' : 'Create Stage' }}
            </h6>
    
             <!-- Stage Tittle -->
            <div class="form-group">
                <label class="form-label">Stage Title</label>
                <input
                    type="text"
                    v-model="stageForm.name"
                    class="form-control"
                />
                
            </div>
            <div class="form-group">
                <label class="form-label">Stage Color</label>
            
                <div class="color-field-wrapper">

                                <!-- hex input -->
                   
                    <input
                    placeholder="#000000"
                        type="color"
                        v-model="stageForm.color"
                        class="form-control"
                    />

                    <input
                        ref="colorInput"
                        type="color"
                        v-model="stageForm.color"
                        class="hidden-color-input"
                    />

                </div>
            
                
            </div>
    
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button class="btn btn-light" @click="closeStageModal">
                    Cancel
                </button>
                <button class="btn btn-primary" @click="saveStage">
                    Save
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import draggable from 'vuedraggable'
import avatar1 from '@/assets/images/users/user1.png'
import leadsIcon from '@/assets/images/kanban/leads-icon.png'
import avatar2 from '@/assets/images/users/user2.png'
import ViewLeadModal from '../viewLead/ViewLeadModal.vue'
import DuplicateLeadsModal from './DuplicateLeadsModal.vue'
import StageChangeReasonModal from './StageChangeReasonModal.vue'
import ConvertLeadModal from './ConvertLeadModal.vue'


import api from '@/plugins/axios'
import Swal from 'sweetalert2'

// Import Bootstrap
import * as bootstrap from 'bootstrap'


const showConvertModal = ref(false)
const selectedLeadForConversion = ref(null)
const selectedLeadData = ref(null)
const convertModalRef = ref(null)
const CONVERTED_STAGE_ID = 8



// Get user from storage (same pattern as header/index.vue)
const getUserFromStorage = () => {
    try {
        const userData = localStorage.getItem('user')
        return userData ? JSON.parse(userData) : null
    } catch (error) {
        console.error('Error getting user from storage:', error)
        return null
    }
}

const user = ref(getUserFromStorage())

// Applied search params (from search modal, not from URL)
const appliedSearchParams = ref(null)

// Check if user is admin or super_admin (same pattern as header/index.vue)
const isAdminOrSuperAdmin = computed(() => {
    if (!user.value) return false
    
    const isAdminUser = user.value.roles?.includes('super_admin') || 
                       user.value.roles?.includes('admin')
    
    return isAdminUser
})

const columns = ref([])
const INITIAL_VISIBLE_LEADS_PER_STAGE = 20
const VISIBLE_LEADS_INCREMENT = 20
const visibleLeadCounts = ref({})
const KANBAN_LEADS_CACHE_KEY = 'kanban_leads_stages_cache_v1'
const KANBAN_LEADS_CACHE_TTL_MS =30000
const responsiblePersons = ref([])
const loading = ref(true)
const error = ref(null)
const kanbanContainerRef = ref(null)
const scrollInterval = ref(null)
const showLeftZone = ref(true)
const showRightZone = ref(true)
const stagePagination = ref({})          
const loadingMoreLeads = ref({})          
const leadsPerPage = ref(20)              
const SCROLL_SPEED = 10
const SCROLL_TICK_MS = 16

function updateScrollArrows() {
    const el = kanbanContainerRef.value
    if (!el) return
    const atStart = el.scrollLeft <= 2
    const atEnd = el.scrollWidth - el.clientWidth <= el.scrollLeft + 2
    showLeftZone.value = !atStart
    showRightZone.value = !atEnd
}

function startScrollLeft() {
    stopScroll()
    scrollInterval.value = setInterval(() => {
        const el = kanbanContainerRef.value
        if (!el) return
        el.scrollLeft -= SCROLL_SPEED
    }, SCROLL_TICK_MS)
}

function startScrollRight() {
    stopScroll()
    scrollInterval.value = setInterval(() => {
        const el = kanbanContainerRef.value
        if (!el) return
        el.scrollLeft += SCROLL_SPEED
    }, SCROLL_TICK_MS)
}

function stopScroll() {
    if (scrollInterval.value) {
        clearInterval(scrollInterval.value)
        scrollInterval.value = null
    }
}

const echoListeners = ref([])
const pollingInterval = ref(null)
const isFetching = ref(false)
const abortController = ref(null)
const fetchDebounceTimer = ref(null)

// Stage editing state
const editingStageId = ref(null)
const editingStageTitle = ref('')
const stageTitleInput = ref(null)

const cardFields = ref([])


const stageChangeReasonModal = ref(null)
const pendingStageChange = ref(null)


const showStageModal = ref(false)
const isEditingStage = ref(false)

const stageForm = ref({
    id: null,
    name: '',
    color: null
})

const colorInput = ref(null)

const openColorPicker = () => {
    colorInput.value?.click()
}


const colors = ['#7BD3EA', '#E3DA32', '#F2C934', '#8EC82F', '#00A74C']

function getColorByIndex(index) {
    return colors[index % colors.length]
}
const fetchCardSettings = async () => {
    try {
        const response = await api.get('/settings/kanban')
        const data = response.data.data
        cardFields.value = data.card_fields || []
    } catch (error) {
        console.error('Error fetching card settings:', error)
    }
}
const fetchLeads = async (immediate = false, queryOverride = undefined) => {
    if (queryOverride !== undefined) {
        appliedSearchParams.value = queryOverride && Object.keys(queryOverride).length ? queryOverride : null
    }
    // Clear any pending debounce
    if (fetchDebounceTimer.value) {
        clearTimeout(fetchDebounceTimer.value)
        fetchDebounceTimer.value = null
    }
    
    // If not immediate, debounce rapid calls
    if (!immediate) {
        return new Promise((resolve) => {
            fetchDebounceTimer.value = setTimeout(async () => {
                await executeFetchLeads()
                resolve()
            }, 300) // 300ms debounce
        })
    }
    
    return executeFetchLeads()
}
function closeStageModal() {
    showStageModal.value = false
    isEditingStage.value = false
    stageForm.value = { id: null, name: '', color: null }
}

async function saveStage() {
    if (!stageForm.value.name.trim()) {
        $showNotification('Stage name is required', 'warning')
        return
    }

    try {
        await api.put(`/stages/${stageForm.value.id}`, {
            name: stageForm.value.name,
            color: stageForm.value.color
        })

        // Update local column
        const column = columns.value.find(c => c.status === stageForm.value.id)
        if (column) {
            column.title = stageForm.value.name
            column.color = stageForm.value.color
        }

        $showNotification('Stage updated successfully', 'success')
        closeStageModal()
    } catch (error) {
        $showNotification('Failed to update stage', 'error')
    }
}

const executeFetchLeads = async () => {
    if (isFetching.value) return
    
    if (abortController.value) {
        abortController.value.abort()
    }
    
    abortController.value = new AbortController()
    isFetching.value = true
    loading.value = true
    
    try {
        const q = appliedSearchParams.value || {}
        
        const params = {
            per_page: leadsPerPage.value,
            ...(q.search && { search: q.search }),
            ...(q.lead_name && { lead_name: q.lead_name }),
            ...(q.first_name && { first_name: q.first_name }),
            ...(q.responsible_person_id != null && q.responsible_person_id !== '' && { responsible_person_id: q.responsible_person_id }),
            ...(q.created_at && { created_at: q.created_at }),
            ...(q.created_from && { created_from: q.created_from }),
            ...(q.created_to && { created_to: q.created_to }),
            ...(q.source && { source: q.source }),
            ...(q.lead_branch_source && { lead_branch_source: q.lead_branch_source }),
            ...(q.stage_id != null && q.stage_id !== '' && { stage_id: q.stage_id }),
            ...(q.closed !== undefined && q.closed !== null && q.closed !== '' && { closed: q.closed }),
            ...(q.work_phone && { work_phone: q.work_phone }),
            ...(q.email && { email: q.email }),
            ...(q.bedrooms !== undefined && q.bedrooms !== null && q.bedrooms !== '' && { bedrooms: q.bedrooms }),
            ...(q.team_id != null && q.team_id !== '' && { team_id: q.team_id })
        }
        
        const response = await api.get('/stages/kanban/stages-with-leads', {
            params,
            signal: abortController.value.signal
        })
        
        const responseData = response?.data?.data
        const stagesData = responseData?.stages || []
        
        // تحويل البيانات
        const newData = stagesData.map((stage, index) => ({
            title: stage.name,
            status: stage.id,
            color: stage.color || getColorByIndex(index),
            order: stage.order ?? index,
            leads: stage.leads || [],
            pagination: stage.pagination || {
                current_page: 1,
                last_page: 1,
                per_page: leadsPerPage.value,
                total: stage.lead_count || 0,
                has_more_pages: false
            }
        }))
        
        columns.value = newData
        
        // تحديث visibleLeadCounts (العدد المرئي)
        const nextCounts = {}
        columns.value.forEach(col => {
            const total = Array.isArray(col.leads) ? col.leads.length : 0
            nextCounts[col.status] = Math.min(INITIAL_VISIBLE_LEADS_PER_STAGE, total)
        })
        visibleLeadCounts.value = nextCounts
        
        // تخزين pagination info
        const newStagePagination = {}
        stagesData.forEach(stage => {
            newStagePagination[stage.id] = {
                currentPage: stage.pagination?.current_page || 1,
                lastPage: stage.pagination?.last_page || 1,
                perPage: stage.pagination?.per_page || leadsPerPage.value,
                total: stage.pagination?.total || stage.lead_count || 0,
                hasMorePages: stage.pagination?.has_more_pages || false
            }
        })
        stagePagination.value = newStagePagination
        
        error.value = null
        saveColumnsToCache()
        
    } catch (err) {
        if (err.name !== 'AbortError' && err.name !== 'CanceledError') {
            error.value = err.message || 'Failed to load data'
        }
    } finally {
        isFetching.value = false
        loading.value = false
        abortController.value = null
    }
}

function saveColumnsToCache() {
    try {
        const snapshot = Array.isArray(columns.value)
            ? columns.value.map(col => ({
                  // keep only what we need for fast first paint
                  title: col.title,
                  status: col.status,
                  color: col.color,
                  order: col.order,
                  // cap number of cached leads per stage to keep localStorage small
                  leads: Array.isArray(col.leads) ? col.leads.slice(0, 100) : []
              }))
            : []

        const payload = {
            cachedAt: Date.now(),
            columns: snapshot
        }
        localStorage.setItem(KANBAN_LEADS_CACHE_KEY, JSON.stringify(payload))
    } catch (e) {
        // ignore cache errors
    }
}

function loadCachedColumns() {
    try {
        const raw = localStorage.getItem(KANBAN_LEADS_CACHE_KEY)
        if (!raw) return
        const parsed = JSON.parse(raw)
        if (!parsed || !Array.isArray(parsed.columns)) return

        const now = Date.now()
        if (parsed.cachedAt && now - parsed.cachedAt > KANBAN_LEADS_CACHE_TTL_MS) {
            return
        }

        columns.value = parsed.columns

        // Initialize visible counts based on cached data
        const nextCounts = {}
        columns.value.forEach(col => {
            const total = Array.isArray(col.leads) ? col.leads.length : 0
            nextCounts[col.status] = Math.min(INITIAL_VISIBLE_LEADS_PER_STAGE, total)
        })
        visibleLeadCounts.value = nextCounts

        loading.value = false
        error.value = null
    } catch (e) {
        // ignore cache errors
    }
}

function getVisibleLeadCount(stageId) {
    const current = visibleLeadCounts.value[stageId]
    if (current == null) {
        return INITIAL_VISIBLE_LEADS_PER_STAGE
    }
    return current
}


function loadMoreLeads(stageId) {
    const current = getVisibleLeadCount(stageId)
    visibleLeadCounts.value = {
        ...visibleLeadCounts.value,
        [stageId]: current + VISIBLE_LEADS_INCREMENT
    }
}
async function fetchMoreLeadsFromApi(stageId) {
    // لو بتحمل حالياً، متعملش حاجة
    if (loadingMoreLeads.value[stageId]) return
    
    // لوصلت لآخر صفحة، متعملش حاجة
    const stage = columns.value.find(c => c.status === stageId)
    if (!stage || !stage.pagination?.has_more_pages) return
    
    loadingMoreLeads.value = {
        ...loadingMoreLeads.value,
        [stageId]: true
    }
    
    try {
        const nextPage = (stage.pagination?.current_page || 1) + 1
        
        // جمع معاملات الفلترة الحالية
        const q = appliedSearchParams.value || {}
        
        const params = {
            page: nextPage,
            per_page: leadsPerPage.value,
            ...(q.search && { search: q.search }),
            ...(q.lead_name && { lead_name: q.lead_name }),
            ...(q.first_name && { first_name: q.first_name }),
            ...(q.responsible_person_id != null && q.responsible_person_id !== '' && { responsible_person_id: q.responsible_person_id }),
            ...(q.created_at && { created_at: q.created_at }),
            ...(q.created_from && { created_from: q.created_from }),
            ...(q.created_to && { created_to: q.created_to }),
            ...(q.source && { source: q.source }),
            ...(q.lead_branch_source && { lead_branch_source: q.lead_branch_source }),
            ...(q.stage_id != null && q.stage_id !== '' && { stage_id: q.stage_id }),
            ...(q.closed !== undefined && q.closed !== null && q.closed !== '' && { closed: q.closed }),
            ...(q.work_phone && { work_phone: q.work_phone }),
            ...(q.email && { email: q.email }),
            ...(q.bedrooms !== undefined && q.bedrooms !== null && q.bedrooms !== '' && { bedrooms: q.bedrooms }),
            ...(q.team_id != null && q.team_id !== '' && { team_id: q.team_id })
        }
        
        const response = await api.get(`/stages/kanban/stage/${stageId}/more-leads`, {
            params
        })
        
        const responseData = response?.data?.data
        const newLeads = responseData?.leads || []
        const newPagination = responseData?.pagination || {}
        
        // إضافة الـ leads الجديدة للـ column
        const columnIndex = columns.value.findIndex(c => c.status === stageId)
        if (columnIndex !== -1) {
            // ضيف الـ leads الجديدة تحت القديمة
            columns.value[columnIndex].leads = [
                ...columns.value[columnIndex].leads,
                ...newLeads
            ]
            
            // تحديث الـ pagination
            columns.value[columnIndex].pagination = {
                current_page: newPagination.current_page,
                last_page: newPagination.last_page,
                per_page: newPagination.per_page,
                total: newPagination.total,
                has_more_pages: newPagination.has_more_pages
            }
        }
        
        // تحديث stagePagination
        stagePagination.value = {
            ...stagePagination.value,
            [stageId]: {
                currentPage: newPagination.current_page,
                lastPage: newPagination.last_page,
                perPage: newPagination.per_page,
                total: newPagination.total,
                hasMorePages: newPagination.has_more_pages
            }
        }
        
        // بعد ما تجيب الليدا الجديدة، زود العدد المرئي عشان تظهر
        // لكن هنا أحنا ضفناها بالفعل للـ leads array، فمحتاجين نحدث visibleLeadCounts
        const totalLeadsNow = columns.value[columnIndex].leads.length
        visibleLeadCounts.value = {
            ...visibleLeadCounts.value,
            [stageId]: totalLeadsNow // خلي الكل مرئي
        }
        
    } catch (error) {
        console.error('Error loading more leads:', error)
        $showNotification('Failed to load more leads', 'error')
    } finally {
        loadingMoreLeads.value = {
            ...loadingMoreLeads.value,
            [stageId]: false
        }
    }
}
function onColumnScroll(column, event) {
    const el = event?.target
    if (!el) return
    
    const threshold = 100
    const reachedBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - threshold
    
    if (reachedBottom) {
        const stageId = column.status
        const pagination = stagePagination.value[stageId]
        const isLoading = loadingMoreLeads.value[stageId]
        
        // لو فيه صفحات تانية ومش بنحمل دلوقتي
        if (pagination?.hasMorePages && !isLoading) {
            console.log(`Loading more leads for stage ${stageId} - Page ${pagination.currentPage + 1}`)
            fetchMoreLeadsFromApi(stageId)
        }
    }
}

// Fetch responsible persons
async function fetchResponsiblePersons() {
    try {
        const response = await api.get('/available-responsible-persons')
        
        if (response.data && response.data.data) {
            responsiblePersons.value = response.data.data
        } else {
            responsiblePersons.value = []
        }
    } catch (error) {
        // Don't throw error for this, we can still work without it
    }
}

function openConvertLeadModal(lead) {
    selectedLeadForConversion.value = lead.id
    selectedLeadData.value = lead
    
    nextTick(() => {
        if (convertModalRef.value) {
            convertModalRef.value.show()
        }
    })
}

function handleLeadConverted(deal) {

   

    $showNotification('Lead converted to deal successfully', 'success')
    fetchLeads(true)

    selectedLeadForConversion.value = null
    selectedLeadData.value = null
}


watch(() => columns.value?.length, () => {
    nextTick(() => updateScrollArrows())
})
const enabledFields = computed(() => {
    return cardFields.value
        .filter(field => field.enabled)
        .sort((a, b) => a.order - b.order)
})
const isFieldEnabled = (fieldKey) => {
    return cardFields.value.some(field => field.key === fieldKey && field.enabled)
}
watch(cardFields, () => {
    console.log('Card fields updated:', cardFields.value)
}, { deep: true })
onMounted(async () => {
    // Try to show cached stages/leads immediately while fresh data loads
    // loadCachedColumns()

    await Promise.all([
        fetchLeads(true), // Immediate on mount
        fetchResponsiblePersons(),
         fetchCardSettings() 
    ])
    nextTick(() => updateScrollArrows())
    window.addEventListener('resize', updateScrollArrows)
    setTimeout(() => {
        initializeLeadUpdates()
    }, 1000)
})

onUnmounted(() => {
    stopScroll()
    window.removeEventListener('resize', updateScrollArrows)
    cleanup()
})

// Expose fetchLeads so parent can call it
defineExpose({
    fetchLeads
})

// Initialize real-time updates with Echo/Pusher
const initializeLeadUpdates = () => {
    const user = JSON.parse(localStorage.getItem('user'))
    if (!user || !window.Echo) {
        startPolling()
        return
    }

    try {
        const channel = window.Echo.private(`user.${user.id}`)
        
        channel.error((error) => {
            startPolling()
        })
        
        channel.listen('.lead.updated', (event) => {
            handleLeadUpdate(event, 'updated')
        })
        
        channel.listen('.lead.assigned', (event) => {
            handleLeadUpdate(event, 'assigned')
        })


        echoListeners.value.push(channel)
    } catch (error) {
        startPolling()
    }
}

const handleLeadUpdate = (event, eventType = 'unknown') => {
    // Extract lead data - handle different possible structures
    let leadData = event.lead
    
    // If lead is wrapped in a data property
    if (leadData?.data) {
        leadData = leadData.data
    }
    
    // If lead is an object with nested structure
    if (!leadData && event.lead) {
        leadData = event.lead
    }
    
    if (!leadData || !leadData.id) {
        return
    }
    
    switch (event.action_type) {
        case 'created':
            handleNewLead(leadData)
            break
        case 'updated':
            handleUpdatedLead(leadData, 'updated')
            break
        case 'assigned':
            handleAssignedLead(leadData, event.changes)

            break
        case 'deleted':
            handleDeletedLead(leadData)
            break
        case 'stage_changed':
            handleStageChanged(leadData, event.changes)
            break
        case 'revert':
            console.log("revert");
            handleStageChanged(leadData, event.changes)
            break
        default:
            // For unknown action types, try to handle as update
            handleUpdatedLead(leadData, eventType)
    }
    
    showLeadNotification(event)
}
const handleAssignedLead = (lead, changes) => {
    const user = JSON.parse(localStorage.getItem('user'))
    const currentUserId = user?.id

    if (!lead || !lead.id) return

    const oldPersonId = changes?.old_person_id ?? null
    const newPersonId = lead.responsible_person_id

    // لو أنا الشخص القديم → امسح
    if (oldPersonId && oldPersonId === currentUserId && oldPersonId != newPersonId) {
        removeLeadFromColumns(lead.id)
        return
    }
      console.log(newPersonId == currentUserId);
    // لو أنا الشخص الجديد → أضف أو حدّث
    if (newPersonId == currentUserId) {
        handleUpdatedLead(lead, 'assigned')
        return
    }
     handleUpdatedLead(lead, 'assigned')
        return
}

const handleNewLead = (lead) => {
    if (!lead || !lead.id) {
        return
    }
    
    // Extract stage_id from different possible locations
    const stageId = lead.stage_id || lead.stage?.id || null
    
    if (!stageId) {
        // If no stage_id, try to add to first column as fallback
        if (columns.value.length > 0 && columns.value[0].status) {
            const firstStageId = columns.value[0].status
            const leadWithStage = { ...lead, stage_id: firstStageId }
            handleNewLead(leadWithStage)
            return
        } else {
            return
        }
    }
    
    const columnIndex = columns.value.findIndex(col => col.status === stageId)
    
    if (columnIndex !== -1) {
        if (!columns.value[columnIndex].leads) {
            columns.value[columnIndex].leads = []
        }
        
        const existingIndex = columns.value[columnIndex].leads.findIndex(l => l && l.id === lead.id)
        if (existingIndex === -1) {
            // Ensure lead has stage_id set
            const leadToAdd = { ...lead, stage_id: stageId }
            columns.value[columnIndex].leads.unshift(leadToAdd)
        } else {
            columns.value[columnIndex].leads[existingIndex] = { ...lead, stage_id: stageId }
        }
    }
}

const handleDeletedLead = (lead) => {
    const leadId = lead?.data?.id || lead?.id
    
    if (!leadId) {
        return
    }
    
    for (let i = 0; i < columns.value.length; i++) {
        const column = columns.value[i]
        if (column.leads) {
            const index = column.leads.findIndex(l => l && l.id === leadId)
            if (index !== -1) {
                column.leads.splice(index, 1)
                break
            }
        }
    }
}

const handleLeadUpdatedFromModal = (updatedLead) => {
    if (updatedLead?.id) {
        handleUpdatedLead(updatedLead, 'updated')
    }
}

const handleUpdatedLead = (lead, updateType = 'updated') => {
    if (!lead || !lead.id) {
        return
    }
    if (!isAdminOrSuperAdmin.value  && lead.is_reverted) {
        removeLeadFromColumns(lead.id)
        return
    }
    // Extract stage_id from different possible locations
    const stageId = lead.stage_id || lead.stage?.id || null
    
    if (!stageId) {
        // If no stage_id, try to add to first column as fallback
        if (columns.value.length > 0 && columns.value[0].status) {
            const firstStageId = columns.value[0].status
            // Create a lead copy with the first stage_id
            const leadWithStage = { ...lead, stage_id: firstStageId }
            handleUpdatedLead(leadWithStage, updateType)
            return
        } else {
            return
        }
    }
    
    let leadFound = false
    
    // First, try to find and update existing lead
    for (let i = 0; i < columns.value.length; i++) {
        const column = columns.value[i]
        if (column.leads) {
            const index = column.leads.findIndex(l => l && l.id === lead.id)
            if (index !== -1) {
                leadFound = true
                
                if (column.status !== stageId) {
                    // Lead moved to different stage
                    column.leads.splice(index, 1)
                    
                    const newColumnIndex = columns.value.findIndex(c => c.status === stageId)
                    if (newColumnIndex !== -1) {
                        if (!columns.value[newColumnIndex].leads) {
                            columns.value[newColumnIndex].leads = []
                        }
                        
                        // Check if lead already exists in new column to avoid duplicates
                        const existingIndex = columns.value[newColumnIndex].leads.findIndex(l => l && l.id === lead.id)
                        if (existingIndex === -1) {
                            columns.value[newColumnIndex].leads.unshift(lead)
                        } else {
                            columns.value[newColumnIndex].leads[existingIndex] = lead
                        }
                    }
                } else {
                    column.leads[index] = lead
                }
                break
            }
        }
    }
    
    // If lead not found in any column, add it to the appropriate column (newly assigned lead)
    if (!leadFound) {
        const columnIndex = columns.value.findIndex(col => col.status === stageId)
        if (columnIndex !== -1) {
            if (!columns.value[columnIndex].leads) {
                columns.value[columnIndex].leads = []
            }
            
            // Check if lead already exists to avoid duplicates
            const existingIndex = columns.value[columnIndex].leads.findIndex(l => l && l.id === lead.id)
            if (existingIndex === -1) {
                // Ensure lead has stage_id set
                const leadToAdd = { ...lead, stage_id: stageId }
                columns.value[columnIndex].leads.unshift(leadToAdd)
            } else {
                // Update existing lead
                columns.value[columnIndex].leads[existingIndex] = { ...lead, stage_id: stageId }
            }
        } else {
            // Try to add to first available column as fallback
            if (columns.value.length > 0) {
                const firstColumn = columns.value[0]
                
                if (!firstColumn.leads) {
                    firstColumn.leads = []
                }
                
                const existingIndex = firstColumn.leads.findIndex(l => l && l.id === lead.id)
                if (existingIndex === -1) {
                    const leadToAdd = { ...lead, stage_id: firstColumn.status }
                    firstColumn.leads.unshift(leadToAdd)
                }
            }
        }
    }
}
const removeLeadFromColumns = (leadId) => {
    for (let i = 0; i < columns.value.length; i++) {
        const column = columns.value[i]
        if (column.leads) {
            const index = column.leads.findIndex(l => l && l.id === leadId)
            if (index !== -1) {
                column.leads.splice(index, 1)
                break
            }
        }
    }
}
const handleStageChanged = (lead, changes) => {
    const leadId = lead?.data?.id || lead?.id
    const leadStageId = lead?.data?.stage_id || lead?.stage_id

    if (!leadId || !leadStageId) return

    const existingLead = columns.value
        .flatMap(c => c.leads)
        .find(l => l.id === leadId)

    if (existingLead && existingLead.stage_id === leadStageId) {
        return
    }
    
    for (let i = 0; i < columns.value.length; i++) {
        const column = columns.value[i]
        if (column.leads) {
            const index = column.leads.findIndex(l => l && l.id === leadId)
            if (index !== -1) {
                if (column.status !== leadStageId) {
                    column.leads.splice(index, 1)
                    
                    const newColumnIndex = columns.value.findIndex(c => c.status === leadStageId)
                    if (newColumnIndex !== -1) {
                        if (!columns.value[newColumnIndex].leads) {
                            columns.value[newColumnIndex].leads = []
                        }
                        
                        const leadToAdd = lead.data || lead
                        columns.value[newColumnIndex].leads.unshift(leadToAdd)
                    }
                }
                break
            }
        }
    }
}

const showLeadNotification = (event) => {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    })

    const leadData = event.lead?.data || event.lead
    const leadName = leadData?.lead_name || leadData?.lead_number || 'Unknown Lead'
    const leadNumber = leadData?.lead_number ? `#${leadData.lead_number}` : ''
    
    const userName = event.user_name || 'Someone'

    let title = ''
    let icon = 'info'

    switch (event.action_type) {
        case 'created':
            title = `📝 New Lead: ${leadName} ${leadNumber}`
            icon = 'success'
            break
        case 'updated':
            title = `✏️ ${userName} updated: ${leadName} ${leadNumber}`
            icon = 'info'
            break
        case 'assigned':
            title = `👤 ${userName} assigned: ${leadName} ${leadNumber}`
            icon = 'warning'
            break
        case 'stage_changed':
            title = `🔄 ${userName} moved: ${leadName} ${leadNumber}`
            icon = 'info'
            break
        case 'deleted':
            title = `🗑️ ${userName} deleted: ${leadName} ${leadNumber}`
            icon = 'error'
            break
        default:
            title = `📊 Lead updated: ${leadName} ${leadNumber}`
    }

    Toast.fire({
        icon: icon,
        title: title,
        text: event.message || 'Lead has been updated'
    })
}

const startPolling = () => {
    // Only start polling if not already polling and Echo is not available
    if (pollingInterval.value) {
        return
    }
    
    pollingInterval.value = setInterval(() => {
        // Only poll if not currently fetching
        // Use immediate=false to allow debouncing (though polling shouldn't need it)
        if (!isFetching.value) {
            fetchLeads(false)
        }
    }, 15000)
}

const cleanup = () => {
    // Cancel any pending request
    if (abortController.value) {
        abortController.value.abort()
        abortController.value = null
    }
    
    // Clear debounce timer
    if (fetchDebounceTimer.value) {
        clearTimeout(fetchDebounceTimer.value)
        fetchDebounceTimer.value = null
    }
    
    echoListeners.value.forEach((channel) => {
        if (channel) {
            try {
                // Stop listening to specific events
                channel.stopListening('.lead.updated')
                channel.stopListening('.lead.assigned')
            } catch (error) {
                // Silently handle errors
            }
        }
    })
    echoListeners.value = []

    if (pollingInterval.value) {
        clearInterval(pollingInterval.value)
        pollingInterval.value = null
    }
}

const currentTask = ref({
    id: null,
    title: '',
    description: '',
    name: '',
    source: '',
    branchSource: '',
    responsible: { name: '', avatar: '' },
    assignedBy: { date: '', avatar: '' },
    createdAt: '',
    image: ''
})

const isEditing = ref(false)
const showViewModal = ref(false)
const selectedLead = ref(null)
const showDuplicateModal = ref(false)
const selectedLeadForDuplicates = ref(null)
const currentTriggerElement = ref(null)

function formatDate(dateString) {
    if (!dateString) return ''
    const date = new Date(dateString)
    const options = { month: 'short', day: 'numeric', year: 'numeric' }
    const formattedDate = date.toLocaleDateString('en-US', options)
    const formattedTime = date.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    })
    return `${formattedDate}  |  ${formattedTime}`
}

function viewLead(task) {
    selectedLead.value = task?.id
    showViewModal.value = true
}

function openDuplicateLeadsModal(leadId, event) {
    selectedLeadForDuplicates.value = leadId
    // Get the trigger element from event target
    if (event && event.currentTarget) {
        currentTriggerElement.value = event.currentTarget
    }
    showDuplicateModal.value = true
}

function handleViewDuplicateLead(leadId) {
    selectedLead.value = leadId
    showViewModal.value = true
}

function openModal(task = null, status = '') {
    if (task) {
        currentTask.value = { ...task }
        isEditing.value = true
    } else {
        currentTask.value = {
            id: Date.now(),
            title: 'Compleate CRM From "Mamsha Gardens Plots"',
            name: '',
            source: '',
            branchSource: '',
            responsible: { name: 'Ahmad al mahfouz', avatar: '' },
            assignedBy: { date: new Date().toLocaleString(), avatar: '' },
            createdAt: new Date().toLocaleString(),
            status: status
        }
        isEditing.value = false
    }
    const modal = new bootstrap.Modal(document.getElementById('addTaskModal'))
    modal.show()
}

function saveTask() {
    const column = columns.value.find(c => c.status === currentTask.value.status)
    if (isEditing.value) {
        const index = column.leads.findIndex(t => t.id === currentTask.value.id)
        column.leads[index] = { ...currentTask.value }
    } else {
        column.leads.push({ ...currentTask.value })
    }
    const modal = bootstrap.Modal.getInstance(document.getElementById('addTaskModal'))
    modal.hide()
}

function handleFileChange(event) {
    const file = event.target.files[0]
    if (file) {
        const reader = new FileReader()
        reader.onload = () => {
            currentTask.value.image = reader.result
        }
        reader.readAsDataURL(file)
    }
}

function deleteTask(taskId) {
    for (const column of columns.value) {
        const idx = column.leads.findIndex(t => t.id === taskId)
        if (idx !== -1) {
            column.leads.splice(idx, 1)
            break
        }
    }
}
function editStage(stage) {
    stageForm.value = {
        id: stage.status,
        name: stage.title,
        color: stage.color
    }

    isEditingStage.value = true
    showStageModal.value = true
}


async function startEditingStage(column) {
    editingStageId.value = column.status
    editingStageTitle.value = column.title
    // Focus the input after it's rendered
    await nextTick()
    if (stageTitleInput.value) {
        stageTitleInput.value.focus()
        stageTitleInput.value.select()
    }
}

function cancelEditingStage() {
    editingStageId.value = null
    editingStageTitle.value = ''
}

async function saveStageName(column) {
    const newTitle = editingStageTitle.value.trim()
    
    // If title is empty or unchanged, cancel editing
    if (!newTitle || newTitle === column.title) {
        cancelEditingStage()
        return
    }
    
    try {
        // Update stage name via API
        // Ensure order is included and is a valid number
        const orderValue = column.order !== undefined && column.order !== null ? column.order : 0
        
        await api.put(`/stages/${column.status}`, {
            name: newTitle,
            order: orderValue
        })
        
        // Update local state
        column.title = newTitle
        
        // Dispatch custom event to notify StageSelector components to refresh
        window.dispatchEvent(new CustomEvent('stage-updated', {
            detail: { stageId: column.status, newName: newTitle }
        }))
        
        // Show success notification
        $showNotification('Stage name updated successfully', 'success')
        
        // Cancel editing
        cancelEditingStage()
    } catch (error) {
        $showNotification('Failed to update stage name', 'error')
        // Revert to original title on error
        editingStageTitle.value = column.title
    }
}

async function onLeadDragChange(evt, column) {
    if (evt.added) {
        const lead = evt.added.element
        const newStageId = column.status

        // Check if this is the converted stage (ID 8)
        if (newStageId === CONVERTED_STAGE_ID) {
            // Store the lead and target stage for the modal
            selectedLeadForConversion.value = lead.id
            selectedLeadData.value = lead
            
            // Remove the lead from the converted stage (revert UI)
            const targetColumnIndex = columns.value.findIndex(c => c.status === newStageId)
            columns.value[targetColumnIndex].leads = 
                columns.value[targetColumnIndex].leads.filter(l => l.id !== lead.id)
            
            // Show conversion modal
            await nextTick()
            if (convertModalRef.value) {
                convertModalRef.value.show()
            }
            return // Don't proceed with stage change
        }

        const targetColumnIndex = columns.value.findIndex(c => c.status === newStageId)

        if (targetColumnIndex === 0 || targetColumnIndex === 1) {
            await moveLeadWithStageChange(lead, newStageId)
            return
        }

        if (!isAdminOrSuperAdmin.value && lead.stage_id !== newStageId) {
            pendingStageChange.value = {
                leadId: lead.id,
                targetStageId: newStageId,
                targetStageName: column.title,
                originalStageId: lead.stage_id,
                leadData: lead
            }

            await nextTick()
            stageChangeReasonModal.value?.show()

            // revert UI
            const sourceColumn = columns.value.find(c => c.status === lead.stage_id)
            if (sourceColumn) {
                columns.value[targetColumnIndex].leads =
                    columns.value[targetColumnIndex].leads.filter(l => l.id !== lead.id)

                if (!sourceColumn.leads.find(l => l.id === lead.id)) {
                    sourceColumn.leads.push(lead)
                }
            }
            return
        }

        await moveLeadWithStageChange(lead, newStageId)
    }
}
async function moveLeadWithStageChange(lead, newStageId) {
    try {
        await api.post(`/leads/${lead.id}/change-stage`, {
            stage_id: newStageId
        })
          lead.stage_id = newStageId
        // Don't refetch - real-time updates will handle the UI update
    } catch (error) {
        // Revert the UI change if API fails - only refetch if not already fetching
        if (!isFetching.value) {
            await fetchLeads(true) // Immediate refetch on error
        }
        $showNotification('Failed to move lead', 'error')
    }
}

function clearPendingStageChange() {
    pendingStageChange.value = null
}


async function handleStageChangeWithReason({ leadId, targetStageId, reason }) {
    try {
        const lead = pendingStageChange.value?.leadData
        if (!lead) return

        await api.post(`/leads/${leadId}/change-stage`, {
            stage_id: targetStageId,
            reason: reason // Send reason to backend
        })
        
        // Success - real-time updates will handle UI
        $showNotification('Lead moved successfully', 'success')
    } catch (error) {
        $showNotification(error.response?.data?.message || 'Failed to move lead', 'error')
        throw error // Re-throw to show error in modal
    }
}

// Notification helper
const $showNotification = (message, type = 'info') => {
    if (window.$showNotification) {
        window.$showNotification(message, type)
    } else {
        // Fallback notification using Swal
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
/* Column content: visible when not scrollable (horizontal board scroll) */
.column-content {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.column-content::-webkit-scrollbar {
    display: none;
}

/* Vertical scroll inside each stage – scrollbar visible only on column hover */
.column-content-scrollable {
    overflow-y: auto;
    overflow-x: hidden;
    min-height: 0;
    scrollbar-width: none;
    transition: scrollbar-color 0.2s ease;
}

.column-content-scrollable::-webkit-scrollbar {
    width: 0;
    transition: width 0.2s ease;
}

.column-content-scrollable::-webkit-scrollbar-track {
    background: transparent;
    border-radius: 3px;
}

.column-content-scrollable::-webkit-scrollbar-thumb {
    background: transparent;
    border-radius: 3px;
}

/* Show scrollbar when hovering the stage column */
.kanban-column:hover .column-content-scrollable {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 #f1f5f9;
}

.kanban-column:hover .column-content-scrollable::-webkit-scrollbar {
    width: 6px;
}

.kanban-column:hover .column-content-scrollable::-webkit-scrollbar-track {
    background: #f1f5f9;
}

.kanban-column:hover .column-content-scrollable::-webkit-scrollbar-thumb {
    background: #cbd5e1;
}

.kanban-column:hover .column-content-scrollable::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.kanban-outer {
    position: relative;
    width: 100%;
    height: calc(100vh - 150px);
}

.kanban-container {
    padding: 12px 10px;
    height: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    width: 100%;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
    position: relative;
}

/* Full-height hover zones: hover anywhere on the line to scroll */
.kanban-nav-zone {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
    cursor: pointer;
}
.kanban-nav-zone-left {
    left: 0;
}
.kanban-nav-zone-right {
    right: 0;
}

/* Arrow style same as before: small semi-circular pill */
.kanban-nav-arrow {
    width: 36px;
    height: 72px;
    background: #ffffff;
    box-shadow: 2px 0 12px rgba(0, 0, 0, 0.08), 0 2px 8px rgba(0, 0, 0, 0.06);
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: box-shadow 0.2s ease, background 0.2s ease;
    pointer-events: none;
}
.kanban-nav-zone:hover .kanban-nav-arrow {
    box-shadow: 3px 0 16px rgba(0, 0, 0, 0.1), 0 3px 12px rgba(0, 0, 0, 0.08);
}
.kanban-nav-arrow-icon {
    font-size: 24px;
    font-weight: 600;
    color: #0f172a;
}
.kanban-nav-arrow-left {
    border-radius: 0 36px 36px 0;
    padding-left: 4px;
}
.kanban-nav-arrow-right {
    border-radius: 36px 0 0 36px;
    padding-right: 4px;
}

/* Empty / loading / error states */
.kanban-empty-state {
    position: absolute;
    inset: 24px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    color: #64748B;
    text-align: center;
    padding: 24px;
}
.kanban-empty-icon {
    font-size: 48px;
    color: #94a3b8;
}
.kanban-empty-title {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #334155;
}
.kanban-empty-text {
    margin: 0;
    font-size: 14px;
    max-width: 360px;
}
.kanban-empty-btn {
    margin-top: 8px;
    padding: 8px 16px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #334155;
    font-size: 14px;
    cursor: pointer;
}
.kanban-empty-btn:hover {
    background: #f8fafc;
}
.kanban-loading .kanban-empty-title {
    color: #64748B;
}
.kanban-empty-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid #e2e8f0;
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: kanban-spin 0.8s linear infinite;
}
@keyframes kanban-spin {
    to { transform: rotate(360deg); }
}
.kanban-error-state .kanban-empty-icon {
    color: #ef4444;
}

.kanban-container::-webkit-scrollbar {
    height: 8px;
}

.kanban-container::-webkit-scrollbar-track {
    background: transparent;
}

.kanban-container::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 4px;
}

.kanban-container::-webkit-scrollbar-thumb:hover {
    background-color: #94a3b8;
}

.kanban-wrapper {
    height: 100%;
    width: max-content;
    min-width: 100%;
    display: flex !important;
    flex-wrap: nowrap !important;
    flex-shrink: 0;
}

.kanban-wrapper-tight {
    gap: 8px;
}

/* Stages: no solid box; vertical dashed line between columns (first column has no left border) */
.kanban-column {
    min-width: 247px;
    width: 247px;
    max-width: 247px;
    background-color: transparent;
    border-radius: 12px;
    border: none;
    border-left: 1px dashed rgba(255, 255, 255, 0.55);
    height: 100%;
    flex-shrink: 0;
}

.kanban-column:first-child {
    border-left: none;
}

.column-header {
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
}

.leads-icon {
    width: 11px;
    height: 11px;
    object-fit: contain;
}

.add-new-btn {
    height: 36px;
    transition: all 0.3s ease;
    border: 1px solid #E5E7EB !important;
    gap: 10px;
}

.add-new-btn .btn-text {
    width: 61px;
    height: 16px;
    font-family: 'Montserrat', sans-serif;
    font-weight: 500;
    font-size: 13px;
    line-height: 12px;
    letter-spacing: 0%;
    color: #01062C;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.add-new-btn:hover {
    background-color: #f8f9fa !important;
    border-color: #d1d5db !important;
}

.kanban-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    color: #1e293b;
}

.kanban-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1) !important;
}

/* Ensure all card text is visible on white background (override parent/theme) */
.kanban-card .task-title,
.kanban-card .info-item,
.kanban-card .info-item span,
.kanban-card .date-info,
.kanban-card .date-info span,
.kanban-card .info-label,
.kanban-card .info-value {
    color: #1e293b !important;
}

.kanban-card .info-label {
    color: #64748b !important;
}

.cursor-pointer {
    cursor: pointer;
}

.cursor-move {
    cursor: move;
}

.avatar-sm {
    width: 32px;
    height: 32px;
    object-fit: cover;
}
.assignedBy .avatar-sm{
      width: 28px;
    height: 28px;
}

.info-label {
    color: #979797;
    font-weight: 500;
    font-style: Medium;
    font-size: 11px !important;

}

.info-value {
    font-weight: 500;
    font-size: 11px;
    line-height: 12px;
    color: #353535;
}

.border-neutral-200 {
    top: 233px;
    left: 12px;
    opacity: 1;
    border-width: 1px;

}

.tasks-list {
    min-height: 0;
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    gap: 8px;
    font-family: Montserrat;
}

/* Draggable styles */
.ghost {
    opacity: 0.5;
    background: #c8ebfb;
}

.stage-circle {
    width: 15px;
    height: 15px;
    border-radius: 50%;
    border: 1px solid #E2E8F0;
    background: #FFFFFF;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.stage-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
}

.dragging {
    cursor: grabbing;
}

.task-title {
    font-family: Montserrat;
    font-weight: 700;
    font-style: Bold;
    font-size: 12px;
    line-height: 19px;
    letter-spacing: -0.25px;
    color: #01062C;

    }

.task-header {
    align-items: flex-start;
}

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
    display: flex;
    align-items: center;
    justify-content: center;
}

.date-info {
    font-family: Montserrat;
    font-weight: 500;
    font-style: Medium;
    font-size: 10px;
    line-height: 9px;
    letter-spacing: 0%;
    color: #64748b;
}

.date-info span {
    color: #1e293b;
}

.header-title {
    font-weight: 600;
    font-style: SemiBold;
    font-size: 13px;
    color: #01062C;
    margin: 0;
}

.header-title-wrapper {
    cursor: pointer;
    flex: 1;
    display:flex;
}

.header-title-wrapper:hover .header-title {
    text-decoration: underline;
}

.header-title-input {
    font-weight: 600;
    font-style: SemiBold;
    font-size: 13px;
    color: #01062C;
    /* background: rgba(255, 255, 255, 0.2); */
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 4px;
    padding: 2px 6px;
    outline: none;
    flex: 1;
    min-width: 0;
}

.header-title-input:focus {
    /* background: rgba(255, 255, 255, 0.3); */
    border-color: rgba(255, 255, 255, 0.6);
}

.stage-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1050;
}

.stage-modal {
    background: white;
    padding: 24px;
    border-radius: 12px;
    width: 400px;
}
.leads-count-badge {
    /*font-size: 11px !important;*/
    color: rgba(255, 255, 255, 0.9);
    /*background: rgba(0, 0, 0, 0.2);*/
    /*padding: 2px 8px;*/
    /*border-radius: 12px;*/
    margin-left: 8px;
    font-weight: 600 !important;
}
</style>
