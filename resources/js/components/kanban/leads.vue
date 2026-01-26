<template>
    <div class="overflow-hidden pb-8 d-flex gap-24 kanban-container">
        <!-- Draggable Columns -->
        <draggable v-model="columns" item-key="status" class="kanban-wrapper d-flex gap-20 overflow-x-auto scroll-sm h-100" :group="'columns'"
            handle=".column-header"
            :ghost-class="'ghost'" :drag-class="'dragging'">
            <template #item="{ element: column, index }">
                <div class="kanban-column radius-12 d-flex flex-column" :style="{ '--column-color': column.color }">
                    <div class="card p-0 radius-12 overflow-hidden shadow-none border-0 bg-transparent h-100 d-flex flex-column">
                        <div class="card-body p-0 d-flex flex-column h-100">
                            <!-- Column Header -->
                            <div class="column-header d-flex align-items-center justify-content-between p-11 cursor-move flex-shrink-0" :style="{ backgroundColor: column.color }">
                                <div class="d-flex align-items-center gap-2">
                                    <img :src="leadsIcon" alt="" class="leads-icon">
                                    <p class="header-title">{{ column.title }} ({{ column.leads.length }})</p>
                                </div>
                                <div class="dropdown">
                                    <button type="button" data-bs-toggle="dropdown" aria-expanded="false" class="bg-transparent border-0 p-0 d-flex align-items-center">
                                        <iconify-icon icon="entypo:dots-three-vertical" class="text-xl text-white"></iconify-icon>
                                    </button>
                                    <ul class="dropdown-menu p-12 border bg-base shadow">
                                        <li>
                                            <a href="#" class="duplicate-button dropdown-item px-16 py-8 rounded text-secondary-light bg-hover-neutral-200 text-hover-neutral-900 d-flex align-items-center gap-2" @click="duplicateColumn(column)">
                                                <iconify-icon class="text-xl" icon="humbleicons:duplicate"></iconify-icon>
                                                Duplicate
                                            </a>
                                        </li>
                                        <li>
                                            <a href="#" class="delete-button dropdown-item px-16 py-8 rounded text-secondary-light bg-hover-danger-200 text-hover-danger-900 d-flex align-items-center gap-2" @click="deleteColumn(index)">
                                                <iconify-icon class="text-xl" icon="mingcute:delete-2-line"></iconify-icon>
                                                Delete
                                            </a>
                                        </li>
                                    </ul>
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
                                                    <div class="info-value">{{ task.salutation + ' ' + task.first_name}}</div>
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
                                                        <img :src="task.responsible_person?.avatar" alt="" class="avatar-sm rounded-circle" />
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
                                                    <img :src="task?.parent?.avatar" alt="" class="avatar-sm rounded-circle" />
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
    <ViewLeadModal v-model="showViewModal" :lead="selectedLead" />

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
import { ref, onMounted } from 'vue'
import draggable from 'vuedraggable'
// import avatar1 from '@/assets/images/users/user1.png'
import leadsIcon from '@/assets/images/kanban/svg/leads-icon.png'
// import avatar2 from '@/assets/images/users/user2.png'
// import ViewLeadModal from './ViewLeadModal.vue'
import api from '@/plugins/axios'

// Import Bootstrap
import * as bootstrap from 'bootstrap'

const columns = ref([])

const colors = ['#7BD3EA', '#E3DA32', '#F2C934', '#8EC82F', '#00A74C']

function getColorByIndex(index) {
    return colors[index % colors.length]
}

onMounted(async () => {
    try {
        const response = await api.get('/stages/kanban/stages-with-leads')
        columns.value = response.data.data.map((stage, index) => ({
            title: stage.name,
            status: stage.id, // Use ID for stage changes
            color: stage.color || getColorByIndex(index),
            leads: stage.leads || []
        }))

    } catch (error) {
        console.error('Error fetching stages:', error)
    }
})

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
    selectedLead.value = task
    showViewModal.value = true
}

function openModal(task = null, status = '') {
    if (task) {
        currentTask.value = { ...task }
        isEditing.value = true
    } else {
        currentTask.value = {
            id: Date.now(),
            title: 'Compleate CRM From “Mamsha Gardens Plots”',
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

function duplicateColumn(column) {
    const duplicatedColumn = {
        ...column,
        title: column.title,
        leads: [...column.leads.map(task => ({ ...task, id: Date.now() + Math.random() }))],
    }
    columns.value.push(duplicatedColumn)
}

function deleteColumn(index) {
    columns.value.splice(index, 1)
}

async function onLeadDragChange(evt, column) {
    if (evt.added) {
        const lead = evt.added.element
        const newStageId = column.status // column.status is stage.slug || stage.id

        try {
            await api.post(`/leads/${lead.id}/change-stage`, {
                stage_id: newStageId
            })
        } catch (error) {
            console.error('Error changing lead stage:', error)
            // Optional: Revert the UI change if API fails
            // This would require more complex state management
        }
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
    overflow: hidden;
}

.kanban-wrapper {
    height: 100%;
}

.kanban-column {
    min-width: 247px;
    width: 247px;
    background-color: #E8EDFB;
    border-radius: 12px;
    height: 100%;
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
</style>