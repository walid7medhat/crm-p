<template>
  <div class="container-fluid py-4">
    <!-- Loading State -->
    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <p class="mt-2 text-muted">Loading Leads...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="alert alert-danger mx-3" role="alert">
      <div class="d-flex align-items-center">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <div>
          <strong>Error:</strong> {{ error }}
        </div>
      </div>
      <div class="mt-2">
        <button @click="retryLoading" class="btn btn-sm btn-outline-danger">
          <i class="bi bi-arrow-clockwise me-1"></i>Retry
        </button>
      </div>
    </div>

    <!-- Main Content -->
    <div v-else>
      <!-- Header -->
      <div class="d-flex justify-content-between align-items-center mb-4 px-3">
        <div>
          <h2 class="h3 mb-1 text-dark">Leads  Board</h2>
          <p class="text-muted mb-0">Drag and drop leads between stages</p>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-primary" @click="openStageModal(null)">
            <i class="bi bi-plus-circle me-2"></i>Add Stage
          </button>
          <button class="btn btn-success" @click="openLeadModal(null)">
            <i class="bi bi-person-plus me-2"></i>Add Lead
          </button>
        </div>
      </div>

      <!-- Kanban Board -->
      <div class="kanban-board px-3">
        <draggable
          v-model="stages"
          item-key="id"
          group="stages"
          @end="reorderStages"
          class="d-flex gap-3 overflow-auto pb-3"
          :animation="200"
          handle=".stage-handle"
          v-if="stages.length > 0"
        >
          <template #item="{ element: stage, index }">
            <div class="kanban-stage card border-0 shadow-sm">
              <!-- Stage Header -->
              <div class="card-header bg-light d-flex justify-content-between align-items-center py-3 stage-handle">
                <div class="d-flex align-items-center gap-2">
                  <i class="bi bi-grip-vertical text-muted"></i>
                  <h6 class="mb-0 fw-semibold text-dark">{{ stage.name }}</h6>
                  <span class="badge bg-primary rounded-pill">{{ stage.leads?.length || 0 }}</span>
                </div>
                <div class="dropdown">
                  <button class="btn btn-sm btn-link text-muted p-0" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-three-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <a class="dropdown-item" href="#" @click.prevent="openStageModal(stage)">
                        <i class="bi bi-pencil me-2"></i>Edit
                      </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                      <a class="dropdown-item text-danger" href="#" @click.prevent="deleteStage(stage)">
                        <i class="bi bi-trash me-2"></i>Delete
                      </a>
                    </li>
                  </ul>
                </div>
              </div>

              <!-- Stage Body - Leads List -->
              <div class="card-body p-3" style="min-height: 400px; max-height: 600px; overflow-y: auto;">
                    <draggable
                        v-model="stage.leads"
                        :group="{ name: 'leads', pull: true, put: true }"
                        item-key="id"
                        @end="(evt) => onLeadDragEnd(evt, stage)"
                        :animation="150"
                        class="leads-container"
                        >
                  <template #item="{ element: lead }">
                    <div class="lead-card card mb-3 border" @click="viewLeadDetails(lead)">
                      <div class="card-body p-3">
                        <!-- Lead Header -->
                        <div class="d-flex justify-content-between align-items-start mb-2">
                          <div>
                            <h6 class="mb-1 fw-semibold text-truncate" style="max-width: 180px;">
                              {{ lead.lead_name || 'Unnamed Lead' }}
                            </h6>
                            <small class="text-muted">#{{ lead.lead_number || 'N/A' }}</small>
                          </div>
                          <span class="badge bg-info-subtle text-info border border-info-subtle">
                            {{ lead.purpose_buying || 'Not Specified' }}
                          </span>
                        </div>

                        <!-- Lead Details -->
                        <div class="mb-2">
                          <div class="d-flex align-items-center gap-1 mb-1">
                            <i class="bi bi-person text-muted" style="font-size: 0.875rem;"></i>
                            <small class="text-truncate" style="max-width: 200px;">
                              {{ lead.first_name || '' }} {{ lead.last_name || '' }}
                            </small>
                          </div>
                          <div class="d-flex align-items-center gap-1 mb-1">
                            <i class="bi bi-telephone text-muted" style="font-size: 0.875rem;"></i>
                            <small>{{ lead.whatsapp_number || lead.lead_number || 'No Phone' }}</small>
                          </div>
                          <div v-if="lead.email" class="d-flex align-items-center gap-1 mb-1">
                            <i class="bi bi-envelope text-muted" style="font-size: 0.875rem;"></i>
                            <small class="text-truncate" style="max-width: 200px;">{{ lead.email }}</small>
                          </div>
                        </div>

                        <!-- Responsible Person -->
                        <div class="d-flex justify-content-between align-items-center border-top pt-2">
                          <div class="d-flex align-items-center gap-1">
                            <i class="bi bi-person-badge text-primary" style="font-size: 0.875rem;"></i>
                            <small class="text-muted text-truncate" style="max-width: 120px;">
                              {{ lead.responsible_person?.name  }}
                            </small>
                          </div>
                          <div class="text-end">
                            <small class="text-muted">{{ formatDate(lead.created_at) }}</small>
                          </div>
                        </div>
                      </div>
                    </div>
                  </template>

                  <!-- Empty State -->
                  <template #footer>
                    <div v-if="!stage.leads || stage.leads.length === 0" class="text-center py-4">
                      <i class="bi bi-inbox display-6 text-muted mb-2"></i>
                      <p class="text-muted mb-0">No leads in this stage</p>
                      <small class="text-muted">Drag leads here or click "Add Lead"</small>
                    </div>
                  </template>
                </draggable>
              </div>

              <!-- Add Lead Button -->
              <div class="card-footer bg-transparent border-top-0 pt-0">
                <button class="btn btn-outline-primary btn-sm w-100" @click="openLeadModal(null, stage)">
                  <i class="bi bi-plus me-1"></i>Add Lead
                </button>
              </div>
            </div>
          </template>
        </draggable>

        <!-- No Stages State -->
        <div v-else class="text-center py-5">
          <i class="bi bi-kanban display-1 text-muted mb-3"></i>
          <h5 class="text-muted mb-2">No stages available</h5>
          <p class="text-muted mb-4">Start by creating your first stage</p>
          <button class="btn btn-primary" @click="openStageModal(null)">
            <i class="bi bi-plus-circle me-2"></i>Create First Stage
          </button>
        </div>
      </div>
    </div>

    <!-- Stage Modal -->
    <div class="modal fade" id="stageModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ editingStage ? 'Edit Stage' : 'Add New Stage' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="saveStage">
              <div class="mb-3">
                <label class="form-label">Stage Name *</label>
                <input v-model="stageForm.name" type="text" class="form-control" required 
                       placeholder="e.g., New, Negotiation, Follow-up">
                <div class="form-text">Enter a descriptive name for this stage</div>
              </div>
              <div class="mb-3">
                <label class="form-label">Order Position</label>
                <input v-model="stageForm.order" type="number" class="form-control" min="0" 
                       placeholder="Will be auto-assigned">
                <div class="form-text">Lower numbers appear first (0, 1, 2...)</div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" @click="saveStage" :disabled="savingStage">
              <span v-if="savingStage" class="spinner-border spinner-border-sm me-1"></span>
              {{ editingStage ? 'Update' : 'Create' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Lead Modal -->
    <div class="modal fade" id="leadModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ editingLead ? 'Edit Lead' : 'Add New Lead' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div v-if="loadingLead" class="text-center py-3">
              <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
              </div>
            </div>
            <form v-else @submit.prevent="saveLead">
              <!-- Basic Information -->
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label">Lead Name *</label>
                  <input v-model="leadForm.lead_name" type="text" class="form-control" required
                         placeholder="Enter lead name">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Lead Number *</label>
                  <input v-model="leadForm.lead_number" type="text" class="form-control" required
                         :placeholder="editingLead ? leadForm.lead_number : 'Auto-generated'"
                         :readonly="editingLead">
                </div>
              </div>

              <!-- Personal Information -->
              <div class="row mb-3">
                <div class="col-md-3">
                  <label class="form-label">Salutation</label>
                  <select v-model="leadForm.salutation" class="form-select">
                    <option value="">Select</option>
                    <option value="Mr.">Mr.</option>
                    <option value="Mrs.">Mrs.</option>
                    <option value="Ms.">Ms.</option>
                    <option value="Dr.">Dr.</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">First Name *</label>
                  <input v-model="leadForm.first_name" type="text" class="form-control" required>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Second Name</label>
                  <input v-model="leadForm.second_name" type="text" class="form-control">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Last Name</label>
                  <input v-model="leadForm.last_name" type="text" class="form-control">
                </div>
              </div>

              <!-- Contact Information -->
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input v-model="leadForm.email" type="email" class="form-control" placeholder="email@example.com">
                </div>
                <div class="col-md-6">
                  <label class="form-label">WhatsApp Number</label>
                  <input v-model="leadForm.whatsapp_number" type="text" class="form-control" placeholder="+1234567890">
                </div>
              </div>

              <!-- Stage and Responsible Person -->
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label">Stage *</label>
                  <select v-model="leadForm.stage_id" class="form-select" required>
                    <option value="">Select a stage</option>
                    <option v-for="stage in stages" :key="stage.id" :value="stage.id">
                      {{ stage.name }}
                    </option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Responsible Person *</label>
                  <select v-model="leadForm.responsible_person_id" class="form-select" required>
                    <option value="">Select responsible person</option>
                    <option v-for="person in responsiblePersons" :key="person.id" :value="person.id">
                      {{ person.name }} ({{ person.email }})
                    </option>
                  </select>
                </div>
              </div>

              <!-- Additional Information -->
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label">Company Name</label>
                  <input v-model="leadForm.company_name" type="text" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Position</label>
                  <input v-model="leadForm.position" type="text" class="form-control">
                </div>
              </div>

              <!-- Property Information -->
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label">Interested In</label>
                  <input v-model="leadForm.interested_in" type="text" class="form-control" placeholder="e.g., Villa, Apartment">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Bedrooms</label>
                  <input v-model="leadForm.bedrooms" type="number" class="form-control" min="0">
                </div>
              </div>

              <!-- Comments -->
              <div class="mb-3">
                <label class="form-label">Comments</label>
                <textarea v-model="leadForm.comment" class="form-control" rows="3" 
                          placeholder="Add any additional notes or comments..."></textarea>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" @click="saveLead" :disabled="savingLead">
              <span v-if="savingLead" class="spinner-border spinner-border-sm me-1"></span>
              {{ editingLead ? 'Update' : 'Save' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Lead Details Modal -->
    <div class="modal fade" id="leadDetailsModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Lead Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <!-- Lead details content here -->
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted ,nextTick } from 'vue'
import draggable from 'vuedraggable'
import axios from 'axios'
import { Modal } from 'bootstrap'
import Swal from 'sweetalert2'

// Refs
const loading = ref(true)
const error = ref(null)
const stages = ref([])
const responsiblePersons = ref([])
const savingStage = ref(false)
const savingLead = ref(false)
const loadingLead = ref(false)

const echoListeners = ref([])
const pollingInterval = ref(null)

// Modal instances
let stageModal = null
let leadModal = null
let leadDetailsModal = null

// Forms
const stageForm = ref({
  id: null,
  name: '',
  order: 0
})

const leadForm = ref({
  id: null,
  lead_name: '',
  lead_number: '',
  stage_id: '',
  salutation: '',
  first_name: '',
  second_name: '',
  last_name: '',
  whatsapp_number: '',
  email: '',
  company_name: '',
  position: '',
  interested_in: '',
  bedrooms: null,
  comment: '',
  responsible_person_id: ''
})

const editingStage = ref(false)
const editingLead = ref(false)
const selectedStageForLead = ref(null)

onMounted(async () => {
  await initializeModals()
  await loadData()
  
  setTimeout(() => {
    initializeLeadUpdates()
  }, 1000)
})

onUnmounted(() => {
  cleanup()
})

async function initializeModals() {
  await nextTick()
  stageModal = new Modal(document.getElementById('stageModal'))
  leadModal = new Modal(document.getElementById('leadModal'))
  leadDetailsModal = new Modal(document.getElementById('leadDetailsModal'))
}

async function loadData() {
  try {
    loading.value = true
    error.value = null
    
    await Promise.all([
      fetchStages(),
      fetchResponsiblePersons()
    ])
    
    loading.value = false
  } catch (err) {
    console.error('Failed to load data:', err)
    error.value = err.message || 'Failed to load data. Please try again.'
    loading.value = false
  }
}

async function retryLoading() {
  error.value = null
  await loadData()
}

// Fetch stages with leads
async function fetchStages() {
  try {
    console.log('Fetching stages...')
    const response = await axios.get('/api/stages')
    
    if (response.data && response.data.data) {
      stages.value = response.data.data.data.map(stage => ({
        ...stage,
        leads: stage.leads || []
      }))
      console.log('Stages loaded:', stages.value.length)
    } else {
      stages.value = []
    }
  } catch (error) {
    console.error('Error fetching stages:', error)
    throw error
  }
}

// Fetch responsible persons
async function fetchResponsiblePersons() {
  try {
    console.log('Fetching responsible persons...')
    const response = await axios.get('/api/available-responsible-persons')
    
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

const initializeLeadUpdates = () => {
    const user = JSON.parse(localStorage.getItem('user'))
    if (!user || !window.Echo) {
        console.log('❌ Real-time updates not available, using polling...')
        startPolling()
        return
    }

    console.log('🔔 Leads Kanban: Initializing real-time updates for user:', user.id)

    try {
        const listener = window.Echo.private(`user.${user.id}`)
            .listen('.lead.updated', (event) => {
                console.log('🎉 Leads Kanban: Real-time update received:', event)
                handleLeadUpdate(event)
            })
            .error((error) => {
                console.error('❌ Echo error:', error)
                startPolling()
            })

        echoListeners.value.push(listener)
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
    
    const stageIndex = stages.value.findIndex(stage => stage.id === lead.stage_id)
    if (stageIndex !== -1) {
        if (!stages.value[stageIndex].leads) {
            stages.value[stageIndex].leads = []
        }
        
        const existingIndex = stages.value[stageIndex].leads.findIndex(l => l && l.id === lead.id)
        if (existingIndex === -1) {
            stages.value[stageIndex].leads.unshift(lead)
        }
    }
}


const handleDeletedLead = (lead) => {
    const leadId = lead?.data?.id || lead?.id
    if (!leadId) {
        console.error('❌ Invalid lead data in handleDeletedLead:', lead)
        return
    }
    
    for (const stage of stages.value) {
        if (stage.leads) {
            const index = stage.leads.findIndex(l => l && l.id === leadId)
            if (index !== -1) {
                stage.leads.splice(index, 1)
                break
            }
        }
    }
}

const handleUpdatedLead = (lead) => {
    for (const stage of stages.value) {
        if (stage.leads) {
           const index = stage.leads.findIndex(l => l && l.id === lead.id)
            if (index !== -1) {
                if (stage.id !== lead.stage_id) {
                    stage.leads.splice(index, 1)
                    const newStageIndex = stages.value.findIndex(s => s.id === lead.stage_id)
                    if (newStageIndex !== -1) {
                        if (!stages.value[newStageIndex].leads) {
                            stages.value[newStageIndex].leads = []
                        }
                        stages.value[newStageIndex].leads.unshift(lead)
                    }
                } else {
                    stage.leads[index] = lead
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
    
    for (const stage of stages.value) {
        if (stage.leads) {
            const index = stage.leads.findIndex(l => l && l.id === leadId)
            if (index !== -1) {
                if (stage.id !== leadStageId) {
                    stage.leads.splice(index, 1)
                    
                    const newStageIndex = stages.value.findIndex(s => s.id === leadStageId)
                    if (newStageIndex !== -1) {
                        if (!stages.value[newStageIndex].leads) {
                            stages.value[newStageIndex].leads = []
                        }
                        stages.value[newStageIndex].leads.unshift(lead.data || lead)
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
        await fetchStages()
    }, 15000)
}

const cleanup = () => {
    echoListeners.value.forEach(listener => {
        if (listener && typeof listener.stopListening === 'function') {
            listener.stopListening('.lead.updated')
        }
    })
    echoListeners.value = []

    if (pollingInterval.value) {
        clearInterval(pollingInterval.value)
        pollingInterval.value = null
    }
}

// Stage Modal
function openStageModal(stage = null) {
    const isEdit = !!stage
  editingStage.value = isEdit
  stageForm.value = stage ? { ...stage } : { 
    id: null, 
    name: '', 
    order: stages.value.length 
  }
  stageModal.show()
}

async function saveStage() {
  try {
    savingStage.value = true
    
    const url = stageForm.value.id 
      ? `/api/stages/${stageForm.value.id}` 
      : '/api/stages'
    
    const method = stageForm.value.id? 'put' : 'post'
    const data = { ...stageForm.value }
    
    // If no order specified, use next available
    if (!data.order && data.order !== 0) {
      data.order = stages.value.length
    }
    
    await axios({ method, url, data })
    
    stageModal.hide()
    await fetchStages()
    
    if (editingStage.value) {
      $showNotification('Stage updated successfully!', 'success')
    } else {
      $showNotification('Stage created successfully!', 'success')
    }
  } catch (error) {
    console.error('Error saving stage:', error)
    const message = error.response?.data?.message || 'Failed to save stage'
    $showNotification(message, 'error')
  } finally {
    savingStage.value = false
  }
}

async function deleteStage(stage) {
  if (!confirm(`Are you sure you want to delete "${stage.name}"? This action cannot be undone.`)) {
    return
  }

  try {
    await axios.delete(`/api/stages/${stage.id}`)
    await fetchStages()
    $showNotification('Stage deleted successfully!', 'success')
  } catch (error) {
    console.error('Error deleting stage:', error)
    const message = error.response?.data?.message || 'Failed to delete stage'
    $showNotification(message, 'error')
  }
}

// Lead Modal
function openLeadModal(lead = null, stage = null) {
  editingLead.value = !!lead
  selectedStageForLead.value = stage
  
  if (lead) {
    // Populate form with existing lead data
    leadForm.value = {
      id: lead.id,
      lead_name: lead.lead_name || '',
      lead_number: lead.lead_number || '',
      stage_id: lead.stage_id || (stages.value[0]?.id || ''),
      salutation: lead.salutation || '',
      first_name: lead.first_name || '',
      second_name: lead.second_name || '',
      last_name: lead.last_name || '',
      whatsapp_number: lead.whatsapp_number || '',
      email: lead.email || '',
      company_name: lead.company_name || '',
      position: lead.position || '',
      interested_in: lead.interested_in || '',
      bedrooms: lead.bedrooms || null,
      comment: lead.comment || '',
      responsible_person_id: lead.responsible_person_id || ''
    }
  } else {
    // Reset form for new lead
    leadForm.value = {
      id: null,
      lead_name: '',
      lead_number: `LEAD-${Date.now()}`,
      stage_id: stage ? stage.id : (stages.value[0]?.id || ''),
      salutation: '',
      first_name: '',
      second_name: '',
      last_name: '',
      whatsapp_number: '',
      email: '',
      company_name: '',
      position: '',
      interested_in: '',
      bedrooms: null,
      comment: '',
      responsible_person_id: ''
    }
  }
  
  leadModal.show()
}

async function saveLead() {
  try {
    savingLead.value = true
    
    // Validate required fields
    if (!leadForm.value.lead_name || !leadForm.value.first_name || !leadForm.value.stage_id) {
      $showNotification('Please fill all required fields', 'error')
      return
    }
    
    const url = editingLead.value 
      ? `/api/leads/${leadForm.value.id}` 
      : '/api/leads'
    
    const method = editingLead.value ? 'put' : 'post'
    
    await axios({ method, url, data: leadForm.value })
    
    leadModal.hide()
    await fetchStages()
    
    if (editingLead.value) {
      $showNotification('Lead updated successfully!', 'success')
    } else {
      $showNotification('Lead created successfully!', 'success')
    }
  } catch (error) {
    console.error('Error saving lead:', error)
    const message = error.response?.data?.message || 'Failed to save lead'
    $showNotification(message, 'error')
  } finally {
    savingLead.value = false
  }
}

function viewLeadDetails(lead) {
  console.log('View lead details:', lead)
  openLeadModal(lead)
}

// Drag & Drop
async function reorderStages() {
  try {
    const stagesData = stages.value.map((stage, index) => ({
      id: stage.id,
      order: index
    }))
    
    await axios.post('/api/stages/reorder', { stages: stagesData })
    $showNotification('Stages reordered successfully!', 'success')
  } catch (error) {
    console.error('Error reordering stages:', error)
    // Revert to original order
    await fetchStages()
    const message = error.response?.data?.message || 'Failed to reorder stages'
    $showNotification(message, 'error')
  }
}

async function onLeadDragEnd(evt, targetStage) {
  if (!evt.item || !evt.item._underlying_vm_) return;
  
  const lead = evt.item._underlying_vm_;
  console.log(`🎯 Lead "${lead.lead_name}" dropped in ${targetStage.name}`);
  
  try {
    let originalStage = null;
    
    for (const stage of stages.value) {
      if (stage.id === targetStage.id) continue; 
      
      if (stage.leads?.some(l => l.id === lead.id)) {
        originalStage = stage;
        break;
      }
    }
    
    if (!originalStage) {
      console.log('Lead not found in other stages - likely reordering');
      return;
    }
    
    if (originalStage.id === targetStage.id) {
      console.log('Same stage - reordering only');
      return;
    }
    
    console.log(`🔄 Moving from ${originalStage.name} to ${targetStage.name}`);
    
    const response = await axios.post(`/api/leads/${lead.id}/change-stage`, {
      stage_id: originalStage.id 
    });
    
    console.log('✅ Database updated:', response.data);
    
     
    
  } catch (error) {
    console.error('❌ Error:', error);
    
    await fetchStages();
    
    const message = error.response?.data?.message || 'Failed to move lead';
    $showNotification(`❌ ${message}`, 'error');
  }
}

// Utility functions
function formatDate(dateString) {
  if (!dateString) return 'N/A'
  
  try {
    const date = new Date(dateString)
    return date.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    })
  } catch (error) {
    return 'Invalid date'
  }
}

// Access global properties
const $showNotification = (message, type = 'info') => {
    if (window.$showNotification) {
        window.$showNotification(message, type);
    } else {
        console.log(`${type}: ${message}`);
        // Fallback notification
        const alertClass = {
            'success': 'alert-success',
            'error': 'alert-danger',
            'warning': 'alert-warning',
            'info': 'alert-info'
        }[type] || 'alert-info';
        
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alertDiv.innerHTML = `
            <div class="d-flex align-items-center">
                <span class="me-2">${message}</span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        `;
        document.body.appendChild(alertDiv);
        
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.parentNode.removeChild(alertDiv);
            }
        }, 5000);
    }
};
</script>
<style scoped>
.kanban-board {
  min-height: calc(100vh - 200px);
}

.kanban-stage {
  min-width: 320px;
  max-width: 320px;
  height: fit-content;
  max-height: 700px;
  transition: all 0.2s ease;
}

.kanban-stage:hover {
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

.leads-container {
  min-height: 100px;
}

.lead-card {
  cursor: pointer;
  transition: all 0.2s ease;
  border: 1px solid #e9ecef;
}

.lead-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  border-color: #0d6efd;
}

.ghost-lead {
  opacity: 0.4;
  background: #f8f9fa;
  border: 2px dashed #6c757d;
}

.dragging-lead {
  opacity: 0.8;
  transform: rotate(2deg);
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
}

/* Custom scrollbar */
.card-body::-webkit-scrollbar {
  width: 6px;
}

.card-body::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 10px;
}

.card-body::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 10px;
}

.card-body::-webkit-scrollbar-thumb:hover {
  background: #a1a1a1;
}

/* Drag handle cursor */
.stage-handle {
  cursor: move;
}

.stage-handle:hover {
  background-color: #e9ecef;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .kanban-stage {
    min-width: 280px;
  }
  
  .container-fluid {
    padding-left: 10px;
    padding-right: 10px;
  }
}
</style>