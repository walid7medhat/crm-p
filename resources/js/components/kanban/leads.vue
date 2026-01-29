<template>
    <div class="kanban-container">
        <!-- Draggable Columns -->
        <draggable v-model="columns" item-key="status" class="kanban-wrapper d-flex gap-20 h-100" :group="'columns'"
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
                                            <p class="task-title">{{ task.lead_name }}</p>
                                            
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
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import draggable from 'vuedraggable'
import avatar1 from '@/assets/images/users/user1.png'
import leadsIcon from '@/assets/images/kanban/svg/leads-icon.png'
import avatar2 from '@/assets/images/users/user2.png'
import ViewLeadModal from './ViewLeadModal.vue'
import api from '@/plugins/axios'
import Swal from 'sweetalert2'

// Import Bootstrap
import * as bootstrap from 'bootstrap'

const columns = ref([])
const responsiblePersons = ref([])
const loading = ref(true)
const error = ref(null)

const echoListeners = ref([])
const pollingInterval = ref(null)

// Stage editing state
const editingStageId = ref(null)
const editingStageTitle = ref('')
const stageTitleInput = ref(null)

const colors = ['#7BD3EA', '#E3DA32', '#F2C934', '#8EC82F', '#00A74C']

function getColorByIndex(index) {
    return colors[index % colors.length]
}

const fetchLeads = async () => {
    console.log('📥 fetchLeads() called - Fetching latest leads from server...')
    try {
        const response = await api.get('/stages/kanban/stages-with-leads')
        const newData = response.data.data.map((stage, index) => ({
            title: stage.name,
            status: stage.id, // Use ID for stage changes
            color: stage.color || getColorByIndex(index),
            order: stage.order || index, // Include order field
            leads: stage.leads || []
        }))
        
        columns.value = newData
        loading.value = false
    } catch (error) {
        console.error('❌ Error fetching stages:', error)
        error.value = error.message || 'Failed to load data'
        loading.value = false
    }
}

// Fetch responsible persons
async function fetchResponsiblePersons() {
    try {
        console.log('Fetching responsible persons...')
        const response = await api.get('/available-responsible-persons')
        
        if (response.data && response.data.data) {
            responsiblePersons.value = response.data.data
            console.log('Responsible persons loaded:', responsiblePersons.value.length)
        } else {
            responsiblePersons.value = []
        }
    } catch (error) {
        console.error('Error fetching responsible persons:', error)
        // Don't throw error for this, we can still work without it
    }
}

onMounted(async () => {
    await Promise.all([
        fetchLeads(),
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
        console.log('❌ Real-time updates not available, using polling...')
        console.log('User:', user ? '✅' : '❌')
        console.log('Echo:', window.Echo ? '✅' : '❌')
        startPolling()
        return
    }

    console.log('🔔 Leads Kanban: Initializing real-time updates for user:', user.id)
    console.log('📡 Subscribing to channel:', `user.${user.id}`)
    console.log('📡 Listening for event:', '.lead.updated')

    try {
        const channel = window.Echo.private(`user.${user.id}`)
        
        // Log subscription success
        channel.subscribed(() => {
            console.log('✅ Successfully subscribed to private channel:', `user.${user.id}`)
        })
        
        // Log subscription error
        channel.error((error) => {
            console.error('❌ Channel subscription error:', error)
            console.error('Channel:', `user.${user.id}`)
            console.error('This usually means authorization failed. Check:')
            console.error('1. Token is valid:', !!localStorage.getItem('token'))
            console.error('2. Backend broadcasting/auth route is working')
            console.error('3. User is authenticated')
            startPolling()
        })
        
        // Listen for lead updates
        channel.listen('.lead.updated', (event) => {
            console.log('🎉 Leads Kanban: Real-time update received!')
            console.log('Action type:', event.action_type)
            console.log('Event data:', event)
            handleLeadUpdate(event)
        })

        echoListeners.value.push(channel)
    } catch (error) {
        console.error('❌ Failed to initialize Echo:', error)
        startPolling()
    }
}

const handleLeadUpdate = (event) => {
    console.log('📊 Handling lead update:', event.action_type)
    console.log('📦 Event data:', event)
    
    const leadData = event.lead?.data || event.lead
    
    if (!leadData || !leadData.id) {
        console.error('❌ Invalid lead data:', event.lead)
        return
    }
    
    switch (event.action_type) {
        case 'created':
            handleNewLead(leadData)
            break
        case 'updated':
        case 'assigned':
            handleUpdatedLead(leadData)
            break
        case 'deleted':
            handleDeletedLead(leadData)
            break
        case 'stage_changed':
            handleStageChanged(leadData, event.changes)
            break
    }
    
    showLeadNotification(event)
}

const handleNewLead = (lead) => {
    if (!lead || !lead.id || !lead.stage_id) {
        console.error('❌ Invalid lead data in handleNewLead:', lead)
        return
    }
    
    const columnIndex = columns.value.findIndex(col => col.status === lead.stage_id)
    if (columnIndex !== -1) {
        if (!columns.value[columnIndex].leads) {
            columns.value[columnIndex].leads = []
        }
        
        const existingIndex = columns.value[columnIndex].leads.findIndex(l => l && l.id === lead.id)
        if (existingIndex === -1) {
            columns.value[columnIndex].leads.unshift(lead)
        }
    }
}

const handleDeletedLead = (lead) => {
    const leadId = lead?.data?.id || lead?.id
    if (!leadId) {
        console.error('❌ Invalid lead data in handleDeletedLead:', lead)
        return
    }
    
    for (const column of columns.value) {
        if (column.leads) {
            const index = column.leads.findIndex(l => l && l.id === leadId)
            if (index !== -1) {
                column.leads.splice(index, 1)
                break
            }
        }
    }
}

const handleUpdatedLead = (lead) => {
    for (const column of columns.value) {
        if (column.leads) {
            const index = column.leads.findIndex(l => l && l.id === lead.id)
            if (index !== -1) {
                if (column.status !== lead.stage_id) {
                    // Lead moved to different stage
                    column.leads.splice(index, 1)
                    const newColumnIndex = columns.value.findIndex(c => c.status === lead.stage_id)
                    if (newColumnIndex !== -1) {
                        if (!columns.value[newColumnIndex].leads) {
                            columns.value[newColumnIndex].leads = []
                        }
                        columns.value[newColumnIndex].leads.unshift(lead)
                    }
                } else {
                    // Update in same stage
                    column.leads[index] = lead
                }
                break
            }
        }
    }
}

const handleStageChanged = (lead, changes) => {
    console.log('🔄 Stage changed:', lead, changes)
    
    const leadId = lead?.data?.id || lead?.id
    const leadStageId = lead?.data?.stage_id || lead?.stage_id
    
    if (!leadId || !leadStageId) {
        console.error('❌ Lead ID or Stage ID is missing:', lead)
        return
    }
    
    for (const column of columns.value) {
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
                        columns.value[newColumnIndex].leads.unshift(lead.data || lead)
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
    console.log('🔄 Leads Kanban: Starting polling every 15 seconds')
    pollingInterval.value = setInterval(async () => {
        await fetchLeads()
    }, 15000)
}

const cleanup = () => {
    console.log('🧹 Cleaning up Echo listeners and polling...')
    
    echoListeners.value.forEach(channel => {
        if (channel) {
            try {
                // Stop listening to specific events
                channel.stopListening('.lead.updated')
                console.log('✅ Stopped listening to .lead.updated')
            } catch (error) {
                console.error('Error stopping listener:', error)
            }
        }
    })
    echoListeners.value = []

    if (pollingInterval.value) {
        clearInterval(pollingInterval.value)
        pollingInterval.value = null
        console.log('✅ Polling cleared')
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
    console.log(stage)
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
        
        // Show success notification
        $showNotification('Stage name updated successfully', 'success')
        
        // Cancel editing
        cancelEditingStage()
    } catch (error) {
        console.error('❌ Error updating stage name:', error)
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
            console.log('✅ Lead stage changed successfully')
        } catch (error) {
            console.error('❌ Error changing lead stage:', error)
            // Revert the UI change if API fails
            await fetchLeads()
            $showNotification('Failed to move lead', 'error')
        }
    }
}

// Notification helper
const $showNotification = (message, type = 'info') => {
    if (window.$showNotification) {
        window.$showNotification(message, type)
    } else {
        console.log(`${type}: ${message}`)
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
