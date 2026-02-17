<template>
    <div class="kanban-container">
        <!-- Loading state -->
        <div v-if="loading && columns.length === 0" class="kanban-empty-state kanban-loading">
            <div class="kanban-empty-spinner"></div>
            <p class="kanban-empty-title">Loading stages…</p>
        </div>
        <!-- Error state -->
        <div v-else-if="error && columns.length === 0" class="kanban-empty-state kanban-error-state">
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
        <draggable v-else v-model="columns" item-key="status" class="kanban-wrapper d-flex gap-20 h-100" :group="'columns'"
            handle=".column-header"
            :ghost-class="'ghost'" :drag-class="'dragging'">
            <template #item="{ element: column, index }">
                <div class="kanban-column radius-12 d-flex flex-column" :style="{ '--column-color': column.color }">
                    <div class=" p-0 overflow-hidden shadow-none border-0 bg-transparent h-100 d-flex flex-column">
                        <div class="card-body p-0 d-flex flex-column h-100">
                            <!-- Column Header -->
                            <div class="column-header d-flex align-items-center justify-content-between p-11 cursor-move flex-shrink-0" :style="{ backgroundColor: column.color }">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="stage-circle">
                                        <div class="stage-dot" :style="{ backgroundColor: column.color }"></div>
                                    </div>
                                    <div v-if="editingStageId !== column.status" class="header-title-wrapper" @click="startEditingStage(column)">
                                        <p class="header-title">{{ column.title }} ({{ column.leads.length }})</p>
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
                                    <!-- <ul class="dropdown-menu p-12 border bg-base shadow">
                                        <li>
                                            <a href="#" class="duplicate-button dropdown-item px-10 py-1 text-secondary-light bg-hover-neutral-200 text-hover-neutral-900 d-flex align-items-center gap-2" @click="editStage(column)">
                                                <iconify-icon class="text-xs" icon="lucide:edit"></iconify-icon>
                                                Edit Stage
                                            </a>
                                        </li>
                                    </ul> -->
                                </div>
                            </div>

                            <div class="column-content p-10 overflow-y-auto scroll-sm flex-grow-1 d-flex flex-column">
                                <!-- Tasks -->
                                <draggable v-model="column.leads" :group="'tasks'" item-key="id"
                                    class="tasks-list flex-grow-1" :ghost-class="'ghost'"
                                    :drag-class="'dragging'"
                                    @change="(evt) => onLeadDragChange(evt, column)">
                                    <template #item="{ element: task }">
                                        <div :key="task.id" class="kanban-card bg-white p-16 radius-12 mb-16 shadow-sm border-0 cursor-pointer"
                                            @click="viewLead(task)">
                                            <div class="task-header d-flex align-items-center justify-content-between gap-2 mb-12">
                                                <p class="task-title flex-grow-1 mb-0">{{ task.lead_name }}</p>
                                                <div 
                                                    v-if="index === 0 && isAdminOrSuperAdmin"
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
                                                <div class="info-item date-info d-flex align-items-center gap-1 mb-8">
                                                    <span>Created By</span>
                                                    <span>{{ formatDate(task.created_at) }}</span>
                                                </div>
                                                
                                                <div class="info-item mb-8">
                                                    <div class="info-label text-secondary-light text-xs">Name</div>
                                                    <div class="info-value">{{ task.salutation }} {{ task.first_name}}</div>
                                                </div>
                                                
                                                <div class="info-item mb-8">
                                                    <div class="info-label text-secondary-light text-xs mb-1">Source</div>
                                                    <div class="info-value">{{ task.lead_source }}</div>
                                                </div>
                                                
                                                <div class="info-item mb-12">
                                                    <div class="info-label text-secondary-light text-xs mb-1">Lead Branch Source</div>
                                                    <div class="info-value">{{ task.lead_branch_source }}</div>
                                                </div>

                                                <div class="responsible-info d-flex align-items-center justify-content-between mb-12">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <img v-if="task.responsible_person?.avatar" :src="task.responsible_person.avatar" alt="" class="avatar-sm rounded-circle" />
                                                        <div v-else class="avatar-sm rounded-circle bg-neutral-200 d-flex align-items-center justify-content-center">
                                                            <iconify-icon icon="solar:user-bold" class="text-neutral-600"></iconify-icon>
                                                        </div>
                                                        <div>
                                                            <div class="info-label text-secondary-light text-xs">Responsible</div>
                                                            <div class="info-value">{{ task.responsible_person?.name }}</div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <hr class="my-12 border-neutral-200">

                                                <div class="d-flex align-items-center justify-content-between">
                                                    <div class="info-item">
                                                        <div class="info-label text-secondary-light text-xs mb-1">Assigned By</div>
                                                        <div class="info-value">{{ formatDate(task.responsible_person.created_at) }}</div>
                                                    </div>
                                                    <img v-if="task?.parent?.avatar" :src="task.parent.avatar" alt="" class="avatar-sm rounded-circle" />
                                                    <div v-else class="avatar-sm rounded-circle bg-neutral-200 d-flex align-items-center justify-content-center">
                                                        <iconify-icon icon="solar:user-bold" class="text-neutral-600"></iconify-icon>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="task-actions d-none">
                                                <button type="button" class="card-edit-button text-success-600"
                                                    @click.stop="openModal(task)">
                                                    <iconify-icon icon="lucide:edit"></iconify-icon>
                                                </button>
                                                <button type="button" class="card-delete-button text-danger-600"
                                                    @click.stop="deleteTask(task.id)">
                                                    <iconify-icon icon="fluent:delete-24-regular"></iconify-icon>
                                                </button>
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

    <!-- View Lead Modal -->
    <ViewLeadModal v-model="showViewModal" :leadId="selectedLead" />

    <!-- Duplicate Leads Dropdown -->
    <DuplicateLeadsModal 
        v-model="showDuplicateModal" 
        :leadId="selectedLeadForDuplicates"
        :triggerElement="currentTriggerElement"
        @view-lead="handleViewDuplicateLead"
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
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import draggable from 'vuedraggable'
import avatar1 from '@/assets/images/users/user1.png'
import leadsIcon from '@/assets/images/kanban/leads-icon.png'
import avatar2 from '@/assets/images/users/user2.png'
import ViewLeadModal from '../viewLead/ViewLeadModal.vue'
import DuplicateLeadsModal from './DuplicateLeadsModal.vue'
import api from '@/plugins/axios'
import Swal from 'sweetalert2'

// Import Bootstrap
import * as bootstrap from 'bootstrap'

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
const responsiblePersons = ref([])
const loading = ref(true)
const error = ref(null)

const echoListeners = ref([])
const pollingInterval = ref(null)
const isFetching = ref(false)
const abortController = ref(null)
const fetchDebounceTimer = ref(null)

// Stage editing state
const editingStageId = ref(null)
const editingStageTitle = ref('')
const stageTitleInput = ref(null)

const colors = ['#7BD3EA', '#E3DA32', '#F2C934', '#8EC82F', '#00A74C']

function getColorByIndex(index) {
    return colors[index % colors.length]
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

const executeFetchLeads = async () => {
    // Prevent concurrent requests
    if (isFetching.value) {
        return
    }
    
    // Cancel any pending request
    if (abortController.value) {
        abortController.value.abort()
    }
    
    // Create new abort controller for this request
    abortController.value = new AbortController()
    isFetching.value = true
    
    try {
        const q = appliedSearchParams.value || {}
        const params = {}
        if (q.search) params.search = q.search
        if (q.lead_name) params.lead_name = q.lead_name
        if (q.first_name) params.first_name = q.first_name
        if (q.responsible_person_id != null && q.responsible_person_id !== '') params.responsible_person_id = q.responsible_person_id
        if (q.created_at) params.created_at = q.created_at
        if (q.created_from) params.created_from = q.created_from
        if (q.created_to) params.created_to = q.created_to
        if (q.source) params.source = q.source
        if (q.lead_branch_source) params.lead_branch_source = q.lead_branch_source
        if (q.stage_id != null && q.stage_id !== '') params.stage_id = q.stage_id
        if (q.closed !== undefined && q.closed !== null && q.closed !== '') params.closed = q.closed
        if (q.work_phone) params.work_phone = q.work_phone
        if (q.email) params.email = q.email
        if (q.bedrooms !== undefined && q.bedrooms !== null && q.bedrooms !== '') params.bedrooms = q.bedrooms
        const response = await api.get('/stages/kanban/stages-with-leads', {
            params,
            signal: abortController.value.signal
        })
        const raw = response?.data?.data
        const list = Array.isArray(raw) ? raw : []
        const newData = list.map((stage, index) => ({
            title: stage.name,
            status: stage.id, // Use ID for stage changes
            color: stage.color || getColorByIndex(index),
            order: stage.order ?? index,
            leads: stage.leads || []
        }))
        columns.value = newData
        error.value = null
        loading.value = false
    } catch (err) {
        // Don't set error if request was aborted
        if (err.name !== 'AbortError' && err.name !== 'CanceledError') {
            error.value = err.message || 'Failed to load data'
            loading.value = false
        }
    } finally {
        isFetching.value = false
        abortController.value = null
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

onMounted(async () => {
    await Promise.all([
        fetchLeads(true), // Immediate on mount
        fetchResponsiblePersons()
    ])
    
    setTimeout(() => {
        initializeLeadUpdates()
    }, 1000)
})

onUnmounted(() => {
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
            handleUpdatedLead(leadData, 'assigned')
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
    
    if (!leadId || !leadStageId) {
        return
    }
    if (!isAdminOrSuperAdmin.value  && lead.is_reverted) {
        removeLeadFromColumns(lead.id)
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
    // Edit stage functionality
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
        const newStageId = column.status // column.status is stage.slug || stage.id

        try {
            await api.post(`/leads/${lead.id}/change-stage`, {
                stage_id: newStageId
            })
            // Don't refetch - real-time updates will handle the UI update
        } catch (error) {
            // Revert the UI change if API fails - only refetch if not already fetching
            if (!isFetching.value) {
                await fetchLeads(true) // Immediate refetch on error
            }
            $showNotification('Failed to move lead', 'error')
        }
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
.column-content {
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE and Edge */
}

.column-content::-webkit-scrollbar {
    display: none; /* Chrome, Safari, and Opera */
}

.kanban-container {
    /* background-color: transparent; Use background from Index.vue */
    padding: 24px;
    height: calc(100vh - 150px); /* Adjust based on your header height */
    overflow-x: auto;
    overflow-y: hidden;
    width: 100%;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
    position: relative;
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

.kanban-column {
    min-width: 247px;
    width: 247px;
    max-width: 247px;
    background-color: #E8EDFB;
    border-radius: 12px;
    height: 100%;
    flex-shrink: 0;
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
}

.kanban-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1) !important;
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

.info-label {
    color: #979797;
    font-weight: 500;
    font-style: Medium;
    font-size: 11px;

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
    min-height: 100%;
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
</style>
