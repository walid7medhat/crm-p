<template>
  <div class="deals-tab-content">
    <!-- Top tabs: Primary/Off-Plan, Secondary, Rental -->
    <div class="deals-type-tabs d-flex gap-2 mb-24">
      <button
        v-for="tab in typeTabs"
        :key="tab.id"
        class="deals-type-tab"
        :class="{ active: activeTypeTab === tab.id }"
        @click="switchTab(tab.id)"
      >
        <iconify-icon :icon="tab.icon" class="tab-icon"></iconify-icon>
        <span>{{ tab.name }}</span>
      </button>
    </div>

    <!-- Kanban board with navigation arrows -->
    <div class="kanban-outer">
      <div ref="kanbanContainerRef" class="kanban-container" @scroll="updateScrollArrows">
        <!-- Loading state -->
        <div v-if="loading && columns.length === 0" class="kanban-empty-state kanban-loading">
          <div class="kanban-empty-spinner"></div>
          <p class="kanban-empty-title">Loading deals...</p>
        </div>
        
        <!-- Error state -->
        <div v-else-if="error && columns.length === 0" class="kanban-empty-state kanban-error-state">
          <iconify-icon icon="lucide:alert-circle" class="kanban-empty-icon"></iconify-icon>
          <p class="kanban-empty-title">Could not load stages</p>
          <p class="kanban-empty-text">{{ error }}</p>
          <button type="button" class="kanban-empty-btn" @click="fetchDeals(true)">Try again</button>
        </div>
        
        <!-- No stages yet -->
        <div v-else-if="!loading && columns.length === 0" class="kanban-empty-state">
          <iconify-icon icon="lucide:columns-3" class="kanban-empty-icon"></iconify-icon>
          <p class="kanban-empty-title">No stages yet</p>
          <p class="kanban-empty-text">Configure deal stages in settings.</p>
        </div>
        
        <!-- Draggable Columns -->
        <draggable 
          v-else 
          v-model="columns" 
          item-key="stage_id" 
          class="kanban-wrapper kanban-wrapper-tight d-flex h-100" 
          :group="'deals-columns'"
          handle=".column-header"
          :ghost-class="'ghost'" 
          :drag-class="'dragging'"
        >
          <template #item="{ element: column, index }">
            <div class="kanban-column radius-12 d-flex flex-column" :style="{ '--column-color': column.color }">
              <div class="p-0 overflow-hidden shadow-none border-0 bg-transparent h-100 d-flex flex-column">
                <div class="card-body p-0 d-flex flex-column h-100">
                  <!-- Column Header with editing capability -->
                  <div 
                    class="column-header d-flex align-items-center justify-content-between p-8 cursor-move flex-shrink-0" 
                    :style="{ backgroundColor: column.headerBg }"
                  >
                    <div class="d-flex align-items-center gap-2">
                      <div class="stage-circle">
                        <div class="stage-dot" :style="{ backgroundColor: column.dotColor }"></div>
                      </div>
                      <div v-if="editingStageId !== column.stage_id" class="header-title-wrapper" @click="startEditingStage(column)">
                        <p class="header-title">{{ column.title }} ({{ column.deals_count }})</p>
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
                        <iconify-icon icon="entypo:dots-three-vertical" class="column-menu-icon"></iconify-icon>
                      </button>
                      <ul class="dropdown-menu p-12 border bg-base shadow">
                        <li  @click="editStage(column)">
                          <a href="#" class="dropdown-item px-10 py-1 text-secondary-light bg-hover-neutral-200 text-hover-neutral-900 d-flex align-items-center gap-2">
                            <iconify-icon class="text-xs" icon="lucide:edit"></iconify-icon>
                            Edit Stage
                          </a>
                        </li>
                      </ul>
                    </div>
                  </div>

                  <!-- Column Content -->
                  <div class="column-content column-content-scrollable p-8 flex-grow-1 d-flex flex-column">
                    <draggable 
                      v-model="column.deals" 
                      :group="'deals-' + activeTypeTab" 
                      item-key="id"
                      class="tasks-list flex-grow-1" 
                      :ghost-class="'ghost'"
                      :drag-class="'dragging'"
                      @change="(evt) => onDealDragChange(evt, column)"
                    >
                      <template #item="{ element: deal }">
                        <div
                          class="kanban-card bg-white p-12 radius-12 mb-10 shadow-sm border-0 cursor-pointer"
                          @click="viewDeal(deal, column)"
                        >
                          <div class="task-header d-flex align-items-center justify-content-between gap-2 mb-12">
                            <p class="task-title flex-grow-1 mb-0">{{ deal.deal_name || 'Untitled Deal' }}</p>
                          </div>
                          
                          <div class="task-info">
                            <div class="info-item date-info d-flex align-items-center gap-1 mb-8">
                              <span>Created</span>
                              <span>{{ formatDate(deal.created_at) }}</span>
                            </div>
                            
                            <div class="info-item mb-8">
                              <div class="info-label text-secondary-light text-xs">Buyer Name</div>
                              <div class="info-value">{{deal.buyer_name || 'No Buyer' }}</div>
                            </div>
                            
                            <div class="info-item mb-8">
                              <div class="info-label text-secondary-light text-xs">Source</div>
                              <div class="info-value">{{ deal.source || 'N/A' }}</div>
                            </div>
                            
                            <!--<div class="info-item mb-8" v-if="deal.property_type">-->
                            <!--  <div class="info-label text-secondary-light text-xs">Property Type</div>-->
                            <!--  <div class="info-value">{{ deal.property_type.name }}</div>-->
                            <!--</div>-->

                            <hr class="my-12 border-neutral-200">

                            <div class="d-flex align-items-center justify-content-between">
                              <div class="info-item mb-0">
                                <div class="info-label text-secondary-light text-xs mb-1">Assigned To</div>
                                <div class="info-value">{{ deal.responsible_person?.name || 'Unassigned' }}</div>
                              </div>
                              <div class="avatar-sm rounded-circle bg-neutral-200 d-flex align-items-center justify-content-center overflow-hidden flex-shrink-0">
                                <img 
                                  v-if="deal.responsible_person?.avatar" 
                                  :src="getAvatarUrl(deal.responsible_person.avatar)" 
                                  class="w-100 h-100 object-fit-cover"
                                  alt="Avatar"
                                >
                                <iconify-icon v-else icon="solar:user-bold" class="text-neutral-600"></iconify-icon>
                              </div>
                            </div>
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

      <!-- Navigation arrows -->
      <template v-if="!loading && !error && columns.length > 0">
        <div
          v-show="showLeftZone"
          class="kanban-nav-zone kanban-nav-zone-left"
          title="Move left"
          @mouseenter="startScrollLeft"
          @mouseleave="stopScroll"
        >
          <span class="kanban-nav-arrow kanban-nav-arrow-left">
            <iconify-icon icon="lucide:chevron-left" class="kanban-nav-arrow-icon" />
          </span>
        </div>
        <div
          v-show="showRightZone"
          class="kanban-nav-zone kanban-nav-zone-right"
          title="Move right"
          @mouseenter="startScrollRight"
          @mouseleave="stopScroll"
        >
          <span class="kanban-nav-arrow kanban-nav-arrow-right">
            <iconify-icon icon="lucide:chevron-right" class="kanban-nav-arrow-icon" />
          </span>
        </div>
      </template>
    </div>

    <!-- Modals -->
    <ViewDealModal v-model="showViewDealModal" :deal="selectedDeal" @deal-updated="handleDealUpdatedFromModal" />
    
    <StageChangeReasonModal
      ref="stageChangeReasonModal"
      :dealId="pendingStageChange?.dealId"
      :targetStageId="pendingStageChange?.targetStageId"
      :targetStageName="pendingStageChange?.targetStageName"
      @submit="handleStageChangeWithReason"
      @closed="clearPendingStageChange"
    />

    <!-- Stage Edit Modal -->
    <div v-if="showStageModal" class="stage-modal-overlay" @click.self="closeStageModal">
      <div class="stage-modal">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="mb-0">
            {{ isEditingStage ? 'Edit Stage' : 'Create Stage' }}
          </h6>
          <button type="button" class="btn-close" @click="closeStageModal"></button>
        </div>

        <!-- Stage Title -->
        <div class="form-group mb-3">
          <label class="form-label fw-semibold mb-1">Stage Title</label>
          <input
            type="text"
            v-model="stageForm.name"
            class="form-control"
            placeholder="Enter stage name"
          />
        </div>
        
        <!-- Stage Color -->
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

        <div class="d-flex justify-content-end gap-2">
          <button class="btn btn-light" @click="closeStageModal">
            Cancel
          </button>
          <button class="btn btn-primary" @click="saveStage" :disabled="!stageForm.name.trim()">
            Save
          </button>
        </div>
      </div>
    </div>
    
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import draggable from 'vuedraggable'
import axios from '@/plugins/axios'

import Swal from 'sweetalert2'
import ViewDealModal from './ViewDealModal.vue'
import StageChangeReasonModal from './StageChangeReasonModal.vue'

const props = defineProps({
  filters: {
    type: Object,
    default: () => ({})
  }
})

// تعريف emit مرة واحدة فقط مع جميع الأحداث
const emit = defineEmits(['update:deals', 'deal-moved', 'deal-type-change'])

const activeTypeTab = ref('primary')
const showViewDealModal = ref(false)
const selectedDeal = ref(null)
const loading = ref(false)
const error = ref(null)
const stagesData = ref([])
const kanbanContainerRef = ref(null)
const scrollInterval = ref(null)
const showLeftZone = ref(true)
const showRightZone = ref(true)

// Real-time updates
const echoListeners = ref([])
const pollingInterval = ref(null)
const isFetching = ref(false)
const abortController = ref(null)
const fetchDebounceTimer = ref(null)

// Stage editing
const editingStageId = ref(null)
const editingStageTitle = ref('')
const stageTitleInput = ref(null)

// Stage change with reason
const stageChangeReasonModal = ref(null)
const pendingStageChange = ref(null)

// Stage modal for editing
const showStageModal = ref(false)
const isEditingStage = ref(false)
const stageForm = ref({
  id: null,
  name: '',
  color: null
})

const typeTabs = [
  { id: 'primary', name: 'Primary/Off-Plan', icon: 'lucide:layout-grid' },
  { id: 'secondary', name: 'Secondary', icon: 'lucide:calendar' },
  { id: 'rental', name: 'Rental', icon: 'lucide:building-2' }
]

// Get user from storage
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

// Check if user is admin or super_admin
const isAdminOrSuperAdmin = computed(() => {
  if (!user.value) return false
  return user.value.roles?.includes('super_admin') || user.value.roles?.includes('admin')
})

const SCROLL_SPEED = 10
const SCROLL_TICK_MS = 16

// Map stage colors to header backgrounds
function getHeaderBg(color) {
  if (!color) return '#DBEAFE'
  return color + '20' // Add 20% opacity
}

// Get full avatar URL
function getAvatarUrl(path) {
  if (!path) return ''
  if (path.startsWith('http')) return path
  return `/storage/${path}`
}

// Format date
function formatDate(dateString) {
  if (!dateString) return 'N/A'
  const date = new Date(dateString)
  return date.toLocaleDateString('en-US', { 
    month: 'short', 
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Scroll functions
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

// Fetch deals from API
async function fetchDeals(immediate = false) {
  // Prevent concurrent requests
  if (isFetching.value) return
  
  // Clear any pending debounce
  if (fetchDebounceTimer.value) {
    clearTimeout(fetchDebounceTimer.value)
    fetchDebounceTimer.value = null
  }
  
  // If not immediate, debounce rapid calls
  if (!immediate) {
    return new Promise((resolve) => {
      fetchDebounceTimer.value = setTimeout(async () => {
        await executeFetchDeals()
        resolve()
      }, 300)
    })
  }
  
  return executeFetchDeals()
}

async function executeFetchDeals() {
  // Cancel any pending request
  if (abortController.value) {
    abortController.value.abort()
  }
  
  // Create new abort controller
  abortController.value = new AbortController()
  isFetching.value = true
  loading.value = true
  
  try {
    const response = await axios.get('/deals/grouped-by-stage', { 
      params: {
        deal_type: activeTypeTab.value,
        ...props.filters
      }
    })
    
    if (response.data.success) {
      stagesData.value = response.data.data.map(stage => ({
        stage_id: stage.stage_id,
        title: stage.stage_name,
        headerBg: stage.stage_color,
        
        dotColor: stage.stage_color || '#3B82F6',
        color: stage.stage_color || '#3B82F6',
        deals_count: stage.deals_count,
        deals: stage.deals || []
      }))
      error.value = null
    } else {
      throw new Error('Failed to fetch deals')
    }
  } catch (err) {
    if (err.name !== 'AbortError' && err.name !== 'CanceledError') {
      console.error('Error fetching deals:', err)
      error.value = err.message || 'Failed to load deals. Please try again.'
    }
  } finally {
    loading.value = false
    isFetching.value = false
    abortController.value = null
  }
}

// Columns computed from stagesData
const columns = computed({
  get: () => stagesData.value,
  set: (value) => {
    stagesData.value = value
  }
})

// Switch between tabs
async function switchTab(tabId) {
  activeTypeTab.value = tabId
  emit('deal-type-change', tabId)
  await fetchDeals(true)
}

// Initialize real-time updates with Echo/Pusher
const initializeDealUpdates = () => {
  const user = JSON.parse(localStorage.getItem('user'))
  if (!user || !window.Echo) {
    startPolling()
    return
  }

  try {
    const channel = window.Echo.private(`user.${user.id}`)
    
    channel.error((error) => {
      console.error('Echo error:', error)
      startPolling()
    })
    
    channel.listen('.deal.updated', (event) => {
      handleDealUpdate(event)
    })
    
    echoListeners.value.push(channel)
  } catch (error) {
    console.error('Failed to initialize Echo:', error)
    startPolling()
  }
}

const handleDealUpdate = (event) => {
  let dealData = event.deal
  
  // Handle different data structures
  if (dealData?.data) {
    dealData = dealData.data
  }
  
  if (!dealData || !dealData.id) return
  
  switch (event.action_type) {
    case 'created':
      handleNewDeal(dealData)
      break
    case 'updated':
      handleUpdatedDeal(dealData)
      break
    case 'stage_changed':
    case 'revert':
      handleStageChanged(dealData, event.changes)
      break
    case 'deleted':
      handleDeletedDeal(dealData)
      break
  }
  
  showDealNotification(event)
}

const handleNewDeal = (deal) => {
  if (!deal || !deal.id) return
  
  const stageId = deal.stage_id || deal.stage?.id
  
  if (!stageId) {
    if (columns.value.length > 0) {
      const firstStageId = columns.value[0].stage_id
      const dealWithStage = { ...deal, stage_id: firstStageId }
      handleNewDeal(dealWithStage)
    }
    return
  }
  
  const columnIndex = columns.value.findIndex(col => col.stage_id === stageId)
  
  if (columnIndex !== -1) {
    if (!columns.value[columnIndex].deals) {
      columns.value[columnIndex].deals = []
    }
    
    const existingIndex = columns.value[columnIndex].deals.findIndex(d => d.id === deal.id)
    if (existingIndex === -1) {
      columns.value[columnIndex].deals.unshift(deal)
      columns.value[columnIndex].deals_count = columns.value[columnIndex].deals.length
    }
  }
}

const handleDeletedDeal = (deal) => {
  const dealId = deal?.id
  
  if (!dealId) return
  
  for (let i = 0; i < columns.value.length; i++) {
    const column = columns.value[i]
    if (column.deals) {
      const index = column.deals.findIndex(d => d.id === dealId)
      if (index !== -1) {
        column.deals.splice(index, 1)
        column.deals_count = column.deals.length
        break
      }
    }
  }
}

const handleDealUpdatedFromModal = (updatedDeal) => {
  if (updatedDeal?.id) {
    handleUpdatedDeal(updatedDeal)
  }
}

const handleUpdatedDeal = (deal) => {
  if (!deal || !deal.id) return
  
  const stageId = deal.stage_id || deal.stage?.id
  
  if (!stageId) {
    if (columns.value.length > 0) {
      const firstStageId = columns.value[0].stage_id
      const dealWithStage = { ...deal, stage_id: firstStageId }
      handleUpdatedDeal(dealWithStage)
    }
    return
  }
  
  let dealFound = false
  
  // Find and update existing deal
  for (let i = 0; i < columns.value.length; i++) {
    const column = columns.value[i]
    if (column.deals) {
      const index = column.deals.findIndex(d => d.id === deal.id)
      if (index !== -1) {
        dealFound = true
        
        if (column.stage_id !== stageId) {
          // Deal moved to different stage
          column.deals.splice(index, 1)
          
          const newColumnIndex = columns.value.findIndex(c => c.stage_id === stageId)
          if (newColumnIndex !== -1) {
            if (!columns.value[newColumnIndex].deals) {
              columns.value[newColumnIndex].deals = []
            }
            
            const existingInNew = columns.value[newColumnIndex].deals.findIndex(d => d.id === deal.id)
            if (existingInNew === -1) {
              columns.value[newColumnIndex].deals.unshift(deal)
            } else {
              columns.value[newColumnIndex].deals[existingInNew] = deal
            }
            columns.value[newColumnIndex].deals_count = columns.value[newColumnIndex].deals.length
          }
        } else {
          column.deals[index] = deal
        }
        column.deals_count = column.deals.length
        break
      }
    }
  }
  
  // If not found, add to appropriate column
  if (!dealFound) {
    const columnIndex = columns.value.findIndex(col => col.stage_id === stageId)
    if (columnIndex !== -1) {
      if (!columns.value[columnIndex].deals) {
        columns.value[columnIndex].deals = []
      }
      
      const existingIndex = columns.value[columnIndex].deals.findIndex(d => d.id === deal.id)
      if (existingIndex === -1) {
        columns.value[columnIndex].deals.unshift(deal)
      } else {
        columns.value[columnIndex].deals[existingIndex] = deal
      }
      columns.value[columnIndex].deals_count = columns.value[columnIndex].deals.length
    }
  }
}

const handleStageChanged = (deal, changes) => {
  const dealId = deal?.id
  const dealStageId = deal?.stage_id
  
  if (!dealId || !dealStageId) return
  
  for (let i = 0; i < columns.value.length; i++) {
    const column = columns.value[i]
    if (column.deals) {
      const index = column.deals.findIndex(d => d && d.id === dealId)
      if (index !== -1) {
        if (column.stage_id !== dealStageId) {
          column.deals.splice(index, 1)
          column.deals_count = column.deals.length
          
          const newColumnIndex = columns.value.findIndex(c => c.stage_id === dealStageId)
          if (newColumnIndex !== -1) {
            if (!columns.value[newColumnIndex].deals) {
              columns.value[newColumnIndex].deals = []
            }
            
            columns.value[newColumnIndex].deals.unshift(deal)
            columns.value[newColumnIndex].deals_count = columns.value[newColumnIndex].deals.length
          }
        }
        break
      }
    }
  }
}

const showDealNotification = (event) => {
  const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
  })

  const dealData = event.deal?.data || event.deal
  const dealName = dealData?.deal_name || dealData?.deal_number || 'Unknown Deal'
  const dealNumber = dealData?.deal_number ? `#${dealData.deal_number}` : ''
  
  const userName = event.user_name || 'Someone'

  let title = ''
  let icon = 'info'

  switch (event.action_type) {
    case 'created':
      title = `📝 New Deal: ${dealName} ${dealNumber}`
      icon = 'success'
      break
    case 'updated':
      title = `✏️ ${userName} updated: ${dealName} ${dealNumber}`
      icon = 'info'
      break
    case 'stage_changed':
      title = `🔄 ${userName} moved: ${dealName} ${dealNumber}`
      icon = 'info'
      break
    case 'revert':
      title = `↩️ ${userName} reverted: ${dealName} ${dealNumber}`
      icon = 'warning'
      break
    case 'deleted':
      title = `🗑️ ${userName} deleted: ${dealName} ${dealNumber}`
      icon = 'error'
      break
    default:
      title = `📊 Deal updated: ${dealName} ${dealNumber}`
  }

  Toast.fire({
    icon: icon,
    title: title,
    text: event.message || 'Deal has been updated'
  })
}

const startPolling = () => {
  if (pollingInterval.value) return
  
  pollingInterval.value = setInterval(() => {
    if (!isFetching.value) {
      fetchDeals(false)
    }
  }, 15000)
}

const cleanup = () => {
  if (abortController.value) {
    abortController.value.abort()
    abortController.value = null
  }
  
  if (fetchDebounceTimer.value) {
    clearTimeout(fetchDebounceTimer.value)
    fetchDebounceTimer.value = null
  }
  
  echoListeners.value.forEach((channel) => {
    if (channel) {
      try {
        channel.stopListening('.deal.updated')
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

// Stage editing functions
async function startEditingStage(column) {
  editingStageId.value = column.stage_id
  editingStageTitle.value = column.title
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
  
  if (!newTitle || newTitle === column.title) {
    cancelEditingStage()
    return
  }
  
  try {
              const orderValue = column.order !== undefined && column.order !== null ? column.order : 0

    await axios.put(`/stages/${column.stage_id}`, {
      name: newTitle,
      order:orderValue
    })
    
    column.title = newTitle
    
    window.dispatchEvent(new CustomEvent('stage-updated', {
      detail: { stageId: column.stage_id, newName: newTitle }
    }))
    
    showNotification('Stage name updated successfully', 'success')
    cancelEditingStage()
  } catch (error) {
    console.error('Error updating stage:', error)
    showNotification('Failed to update stage name', 'error')
    editingStageTitle.value = column.title
  }
}

function editStage(column) {
  console.log('Editing stage:', column)
  
  stageForm.value = {
    id: column.stage_id,  
    name: column.title,
    color: column.dotColor || column.color
  }
  
  isEditingStage.value = true
  showStageModal.value = true
    console.log('showStageModal after:', showStageModal.value)

}

function closeStageModal() {
  console.log('Closing stage modal')
  showStageModal.value = false
  isEditingStage.value = false
  stageForm.value = { 
    id: null, 
    name: '', 
    color: null 
  }
}

async function saveStage() {
  if (!stageForm.value.name.trim()) {
    showNotification('Stage name is required', 'warning')
    return
  }

  if (!stageForm.value.id) {
    showNotification('Stage ID is missing', 'error')
    return
  }

  try {
    console.log('Saving stage:', stageForm.value)
    
    const response = await axios.put(`/stages/${stageForm.value.id}`, {
      name: stageForm.value.name,
      color: stageForm.value.color
    })

    console.log('API Response:', response.data)

    const column = columns.value.find(c => c.stage_id === stageForm.value.id)
    if (column) {
      column.title = stageForm.value.name
      column.dotColor = stageForm.value.color
      column.color = stageForm.value.color
      column.headerBg = stageForm.value.color
      
      console.log('Updated column:', column) 
    } else {
      console.log('Column not found with stage_id:', stageForm.value.id)
    }

    showNotification('Stage updated successfully', 'success')
    closeStageModal()
    
    // ✅ Force refresh للـ columns
    stagesData.value = [...stagesData.value]
    
  } catch (error) {
    console.error('Error saving stage:', error)
    const errorMessage = error.response?.data?.message || error.message || 'Failed to update stage'
    showNotification(errorMessage, 'error')
  }
}

// Drag and drop handlers
async function onDealDragChange(evt, targetColumn) {
  if (evt.added) {
    const deal = evt.added.element
    const newStageId = targetColumn.stage_id
    const oldStageId = deal.stage_id

    // Check if stage change requires reason (you can define which stages need reason)
    const targetColumnIndex = columns.value.findIndex(c => c.stage_id === newStageId)
    
    if (targetColumnIndex === 0 || targetColumnIndex === 1) {
      await moveDealWithStageChange(deal, newStageId)
      return
    }

    if (!isAdminOrSuperAdmin.value && oldStageId !== newStageId) {
      pendingStageChange.value = {
        dealId: deal.id,
        targetStageId: newStageId,
        targetStageName: targetColumn.title,
        originalStageId: oldStageId,
        dealData: deal
      }

      await nextTick()
      stageChangeReasonModal.value?.show()

      // Revert UI
      const sourceColumn = columns.value.find(c => c.stage_id === oldStageId)
      if (sourceColumn) {
        targetColumn.deals = targetColumn.deals.filter(d => d.id !== deal.id)
        if (!sourceColumn.deals.find(d => d.id === deal.id)) {
          sourceColumn.deals.push(deal)
        }
        sourceColumn.deals_count = sourceColumn.deals.length
        targetColumn.deals_count = targetColumn.deals.length
      }
      return
    }

    await moveDealWithStageChange(deal, newStageId)
  }
}

async function moveDealWithStageChange(deal, newStageId) {
  try {
    await axios.post(`/deals/${deal.id}/change-stage`, {
      stage_id: newStageId
    })
    emit('deal-moved', { deal, newStageId })
    // Real-time updates will handle UI
  } catch (error) {
    if (!isFetching.value) {
      await fetchDeals(true)
    }
    showNotification('Failed to move deal', 'error')
  }
}

function clearPendingStageChange() {
  pendingStageChange.value = null
}

async function handleStageChangeWithReason({ dealId, targetStageId, reason }) {
  try {
    const deal = pendingStageChange.value?.dealData
    if (!deal) return

    await axios.post(`/deals/${dealId}/change-stage`, {
      stage_id: targetStageId,
      reason: reason
    })
    
    showNotification('Deal moved successfully', 'success')
  } catch (error) {
    showNotification(error.response?.data?.message || 'Failed to move deal', 'error')
    throw error
  }
}

// View deal
function viewDeal(deal, column) {
  selectedDeal.value = {
    ...deal,
    stageTitle: column?.title,
    stageId: column?.stage_id,
    deal_type: activeTypeTab.value
  }
  showViewDealModal.value = true
}

// Notification helper
const showNotification = (message, type = 'info') => {
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

// Lifecycle hooks
onMounted(async () => {
  await fetchDeals(true)
  nextTick(() => updateScrollArrows())
  window.addEventListener('resize', updateScrollArrows)
  setTimeout(() => {
    initializeDealUpdates()
  }, 1000)
})

onUnmounted(() => {
  stopScroll()
  window.removeEventListener('resize', updateScrollArrows)
  cleanup()
})

// Watch for filter changes
watch(() => props.filters, () => {
  fetchDeals(true)
}, { deep: true })

// Expose methods
defineExpose({
  fetchDeals,
  currentDealType: activeTypeTab
})
</script>

<style scoped>
.deals-tab-content {
  padding: 24px;
  min-height: 500px;
  font-family: 'Montserrat', sans-serif;
  position: relative;
}

.deals-type-tabs {
  margin-bottom: 20px;
}

.deals-type-tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 100px;
  border: none;
  font-family: 'Montserrat', sans-serif;
  font-size: 12px;
  font-weight: 500;
  color: #64748B;
  background: #F1F5F9;
  cursor: pointer;
  transition: all 0.2s;
}

.deals-type-tab:hover {
  color: #1E293B;
  background: #E2E8F0;
}

.deals-type-tab.active {
  background: #0F172A;
  color: #fff;
}

.deals-type-tab .tab-icon {
  font-size: 14px;
}

/* Kanban Outer Container */
.kanban-outer {
  position: relative;
  width: 100%;
  height: calc(100vh - 200px);
}

.kanban-container {
  height: 100%;
  overflow-x: auto;
  overflow-y: hidden;
  width: 100%;
  scrollbar-width: thin;
  scrollbar-color: #cbd5e1 transparent;
  position: relative;
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
  gap: 8px;
}


.kanban-column {
  min-width: 247px;
  width: 247px;
  max-width: 247px;
  background-color: #E8EDFB;
  border-radius: 12px;
  backdrop-filter: none !important;
  -webkit-backdrop-filter: none !important;
  height: 100%;
  flex-shrink: 0;
}
.column-header {
  border-top-left-radius: 12px;
  border-top-right-radius: 12px;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-bottom: none;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.25);
  position: relative;
  padding-left: 12px !important;
  color: #01062C;
}
.column-header::before {
  content: "";
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  border-radius: 4px 0 0 0;
  background: rgba(0, 0, 0, 0.2);
}
.column-menu-icon {
  font-size: 18px;
  color: #fff;
}

/* Column content scroll */
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

/* Stage circle and dot */
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

.header-title {
  font-family: 'Montserrat', sans-serif;
  font-weight: 600;
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
  font-size: 13px;
  color: #01062C;
  background: rgba(255, 255, 255, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.4);
  border-radius: 4px;
  padding: 2px 6px;
  outline: none;
  flex: 1;
  min-width: 0;
}

.header-title-input:focus {
  background: rgba(255, 255, 255, 0.3);
  border-color: rgba(255, 255, 255, 0.6);
}

/* Kanban Card */
.task-title {
  font-family: 'Montserrat', sans-serif;
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
  font-family: 'Montserrat', sans-serif;
  color: #979797;
  font-weight: 500;
  font-size: 11px;
  margin-bottom: 2px;
}

.info-value {
  font-family: 'Montserrat', sans-serif;
  font-weight: 500;
  font-size: 11px;
  line-height: 12px;
  color: #353535;
}

.date-info {
  font-family: 'Montserrat', sans-serif;
  font-weight: 500;
  font-size: 10px;
  line-height: 9px;
  color: #64748B;
}

.kanban-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.kanban-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1) !important;
}

.avatar-sm {
  width: 32px;
  height: 32px;
  object-fit: cover;
}

.border-neutral-200 {
  border-color: #E2E8F0;
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

.cursor-pointer {
  cursor: pointer;
}

.cursor-move {
  cursor: move;
}

/* Navigation arrows */
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

/* Empty states */
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

.object-fit-cover {
  object-fit: cover;
}

.w-100 {
  width: 100%;
}

.h-100 {
  height: 100%;
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
</style>