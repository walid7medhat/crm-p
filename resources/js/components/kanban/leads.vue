<template>
    <div class="overflow-x-auto scroll-sm pb-8 d-flex gap-24 kanban-container">
        <!-- Draggable Columns -->
        <draggable v-model="columns" item-key="status" class="kanban-wrapper d-flex gap-20" :group="'columns'"
            :ghost-class="'ghost'" :drag-class="'dragging'">
            <template #item="{ element: column, index }">
                <div class="kanban-column radius-12" :style="{ '--column-color': column.color }">
                    <div class="card p-0 radius-12 overflow-hidden shadow-none border-0 bg-transparent">
                        <div class="card-body p-0">
                            <!-- Column Header -->
                            <div class="column-header d-flex align-items-center justify-content-between p-11" :style="{ backgroundColor: column.color }">
                                <div class="d-flex align-items-center gap-2">
                                    <img :src="leadsIcon" alt="" class="leads-icon">
                                    <p class="header-title">{{ column.title }} ({{ column.tasks.length }})</p>
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

                            <div class="column-content p-10">
                                <!-- Tasks -->
                                <draggable v-model="column.tasks" :group="'tasks'" item-key="id"
                                    class="tasks-list" :ghost-class="'ghost'"
                                    :drag-class="'dragging'">
                                    <template #item="{ element: task }">
                                        <div :key="task.id" class="kanban-card bg-white p-16 radius-12 mb-16 shadow-sm border-0">
                                            <p class="task-title">{{ task.title }}</p>
                                            
                                            <div class="task-info">
                                                <div class="info-item date-info d-flex align-items-center gap-1 mb-8">
                                                    <span>Created By</span>
                                                    <span>{{ task.createdAt }}</span>
                                                </div>
                                                
                                                <div class="info-item mb-8">
                                                    <div class="info-label text-secondary-light text-xs">Name</div>
                                                    <div class="info-value">{{ task.name }}</div>
                                                </div>
                                                
                                                <div class="info-item mb-8">
                                                    <div class="info-label text-secondary-light text-xs mb-1">Source</div>
                                                    <div class="info-value">{{ task.source }}</div>
                                                </div>
                                                
                                                <div class="info-item mb-12">
                                                    <div class="info-label text-secondary-light text-xs mb-1">Lead Branch Source</div>
                                                    <div class="info-value">{{ task.branchSource }}</div>
                                                </div>

                                                <div class="responsible-info d-flex align-items-center justify-content-between mb-12">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <img :src="task.responsible.avatar" alt="" class="avatar-sm rounded-circle" />
                                                        <div>
                                                            <div class="info-label text-secondary-light text-xs">Responsible</div>
                                                            <div class="info-value">{{ task.responsible.name }}</div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <hr class="my-12 border-neutral-200">

                                                <div class="d-flex align-items-center justify-content-between">
                                                    <div class="info-item">
                                                        <div class="info-label text-secondary-light text-xs mb-1">Assigned By</div>
                                                        <div class="info-value">{{ task.assignedBy.date }}</div>
                                                    </div>
                                                    <img :src="task.assignedBy.avatar" alt="" class="avatar-sm rounded-circle" />
                                                </div>
                                            </div>

                                            <div class="task-actions d-none">
                                                <button type="button" class="card-edit-button text-success-600"
                                                    @click="openModal(task)">
                                                    <iconify-icon icon="lucide:edit"></iconify-icon>
                                                </button>
                                                <button type="button" class="card-delete-button text-danger-600"
                                                    @click="deleteTask(task.id)">
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
import { ref } from 'vue'
import draggable from 'vuedraggable'
import avatar1 from '@/assets/images/users/user1.png'
import leadsIcon from '@/assets/images/kanban/svg/leads-icon.png'
import avatar2 from '@/assets/images/users/user2.png'
import avatar3 from '@/assets/images/users/user3.png'

// Import Bootstrap
import * as bootstrap from 'bootstrap'

const columns = ref([
    {
        title: 'New Leads',
        status: 'new-leads',
        color: '#7BD3EA',
        tasks: [
            {
                id: 1,
                title: 'Compleate CRM From “Mamsha Gardens Plots”',
                createdAt: 'Nov 21 | 9:26 PM',
                name: 'Forwzan Riaz Mulla',
                source: 'Meta Ads - Lead Form',
                branchSource: 'Abu Dhabi',
                responsible: {
                    name: 'Ahmad al mahfouz',
                    avatar: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRGAAyASQFyBM_Ro1XAzGSZF5w8fC5IFMkMOA&s'
                },
                assignedBy: {
                    date: '21 Dec 2025 | 12.05 PM',
                    avatar: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'
                }
            },
            {
                id: 2,
                title: 'Compleate CRM From “Mamsha Gardens Plots”',
                createdAt: 'Nov 21 | 9:26 PM',
                name: 'Forwzan Riaz Mulla',
                source: 'Meta Ads - Lead Form',
                branchSource: 'Abu Dhabi',
                responsible: {
                    name: 'Ahmad al mahfouz',
                    avatar: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRGAAyASQFyBM_Ro1XAzGSZF5w8fC5IFMkMOA&s'
                },
                assignedBy: {
                    date: '21 Dec 2025 | 12.05 PM',
                    avatar: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'
                }
            }
        ],
    },
    {
        title: 'Assigned',
        status: 'assigned',
        color: '#E3DA32',
        tasks: [
            {
                id: 1,
                title: 'Compleate CRM From “Mamsha Gardens Plots”',
                createdAt: 'Nov 21 | 9:26 PM',
                name: 'Forwzan Riaz Mulla',
                source: 'Meta Ads - Lead Form',
                branchSource: 'Abu Dhabi',
                responsible: {
                    name: 'Ahmad al mahfouz',
                    avatar: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRGAAyASQFyBM_Ro1XAzGSZF5w8fC5IFMkMOA&s'
                },
                assignedBy: {
                    date: '21 Dec 2025 | 12.05 PM',
                    avatar: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'
                }
            },
            {
                id: 2,
                title: 'Compleate CRM From “Mamsha Gardens Plots”',
                createdAt: 'Nov 21 | 9:26 PM',
                name: 'Forwzan Riaz Mulla',
                source: 'Meta Ads - Lead Form',
                branchSource: 'Abu Dhabi',
                responsible: {
                    name: 'Ahmad al mahfouz',
                    avatar: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRGAAyASQFyBM_Ro1XAzGSZF5w8fC5IFMkMOA&s'
                },
                assignedBy: {
                    date: '21 Dec 2025 | 12.05 PM',
                    avatar: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'
                }
            }
        ],
    },
    {
        title: 'Follow-up / Contacted',
        status: 'follow-up-contacted',
        color: '#F2C934',
        tasks: [
            {
                id: 1,
                title: 'Compleate CRM From “Mamsha Gardens Plots”',
                createdAt: 'Nov 21 | 9:26 PM',
                name: 'Forwzan Riaz Mulla',
                source: 'Meta Ads - Lead Form',
                branchSource: 'Abu Dhabi',
                responsible: {
                    name: 'Ahmad al mahfouz',
                    avatar: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRGAAyASQFyBM_Ro1XAzGSZF5w8fC5IFMkMOA&s'
                },
                assignedBy: {
                    date: '21 Dec 2025 | 12.05 PM',
                    avatar: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'
                }
            },
            {
                id: 2,
                title: 'Compleate CRM From “Mamsha Gardens Plots”',
                createdAt: 'Nov 21 | 9:26 PM',
                name: 'Forwzan Riaz Mulla',
                source: 'Meta Ads - Lead Form',
                branchSource: 'Abu Dhabi',
                responsible: {
                    name: 'Ahmad al mahfouz',
                    avatar: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRGAAyASQFyBM_Ro1XAzGSZF5w8fC5IFMkMOA&s'
                },
                assignedBy: {
                    date: '21 Dec 2025 | 12.05 PM',
                    avatar: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'
                }
            }
        ],
    },
    {
        title: 'Qualified',
        status: 'qualified',
        color: '#8EC82F',
        tasks: [
            {
                id: 1,
                title: 'Compleate CRM From “Mamsha Gardens Plots”',
                createdAt: 'Nov 21 | 9:26 PM',
                name: 'Forwzan Riaz Mulla',
                source: 'Meta Ads - Lead Form',
                branchSource: 'Abu Dhabi',
                responsible: {
                    name: 'Ahmad al mahfouz',
                    avatar: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRGAAyASQFyBM_Ro1XAzGSZF5w8fC5IFMkMOA&s'
                },
                assignedBy: {
                    date: '21 Dec 2025 | 12.05 PM',
                    avatar: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'
                }
            },
            {
                id: 2,
                title: 'Compleate CRM From “Mamsha Gardens Plots”',
                createdAt: 'Nov 21 | 9:26 PM',
                name: 'Forwzan Riaz Mulla',
                source: 'Meta Ads - Lead Form',
                branchSource: 'Abu Dhabi',
                responsible: {
                    name: 'Ahmad al mahfouz',
                    avatar: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRGAAyASQFyBM_Ro1XAzGSZF5w8fC5IFMkMOA&s'
                },
                assignedBy: {
                    date: '21 Dec 2025 | 12.05 PM',
                    avatar: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'
                }
            }
        ],
    },
    {
        title: 'Future Prospected',
        status: 'future-prospected',
        color: '#00A74C',
        tasks: [
            {
                id: 3,
                title: 'Compleate CRM From “Mamsha Gardens Plots”',
                createdAt: 'Nov 21 | 9:26 PM',
                name: 'Forwzan Riaz Mulla',
                source: 'Meta Ads - Lead Form',
                branchSource: 'Abu Dhabi',
                responsible: {
                    name: 'Ahmad al mahfouz',
                    avatar: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRGAAyASQFyBM_Ro1XAzGSZF5w8fC5IFMkMOA&s'
                },
                assignedBy: {
                    date: '21 Dec 2025 | 12.05 PM',
                    avatar: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'
                }
            }
        ],
    }
])

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
            responsible: { name: 'Ahmad al mahfouz', avatar: avatar1 },
            assignedBy: { date: new Date().toLocaleString(), avatar: avatar2 },
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
        const index = column.tasks.findIndex(t => t.id === currentTask.value.id)
        column.tasks[index] = { ...currentTask.value }
    } else {
        column.tasks.push({ ...currentTask.value })
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
        const idx = column.tasks.findIndex(t => t.id === taskId)
        if (idx !== -1) {
            column.tasks.splice(idx, 1)
            break
        }
    }
}

function duplicateColumn(column) {
    const duplicatedColumn = {
        ...column,
        title: column.title,
        tasks: [...column.tasks.map(task => ({ ...task, id: Date.now() + Math.random() }))],
    }
    columns.value.push(duplicatedColumn)
}

function deleteColumn(index) {
    columns.value.splice(index, 1)
}
</script>


<style scoped>
.kanban-container {
    /* background-color: transparent; Use background from Index.vue */
    padding: 24px;
    min-height: calc(100vh - 72px);
}

.kanban-column {
    min-width: 247px;
    width: 247px;
    background-color: #E8EDFB;
    border-radius: 12px;
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
    cursor: grab;
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
    min-height: 100px;
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