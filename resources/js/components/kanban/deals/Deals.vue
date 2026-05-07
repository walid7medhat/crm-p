<template>
  <div class="deals-tab-content deal-figma-ui" :class="{ 'deals-tab-content--mobile': kanbanIsMobile }">
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
    <div class="kanban-outer" :class="{ 'kanban-outer--mobile': kanbanIsMobile }">
      <div
        ref="kanbanContainerRef"
        class="kanban-container"
        :class="{ 'kanban-container--mobile': kanbanIsMobile }"
        @scroll="updateScrollArrows"
        @dragover.prevent="onContainerDragOver"
      >
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
          :disabled="kanbanIsMobile"
          :ghost-class="'ghost'" 
          :drag-class="'dragging'"
        >
          <template #item="{ element: column }">
            <div class="kanban-column radius-12 d-flex flex-column" :style="{ '--column-color': column.color }">
              <div class="p-0 overflow-hidden shadow-none border-0 bg-transparent h-100 d-flex flex-column">
                <div class="card-body p-0 d-flex flex-column h-100">
                  <!-- Column Header with editing capability -->
                  <div 
                    class="column-header d-flex align-items-center justify-content-between cursor-move flex-shrink-0" 
                    :style="{ backgroundColor: column.headerBg }"
                  >
                    <div class="d-flex align-items-center gap-2">
                      <!-- <div class="stage-circle">
                        <div class="stage-dot" :style="{ backgroundColor: column.dotColor }"></div>
                      </div> -->
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
                    <!-- <div class="dropdown">
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
                    </div> -->
                  </div>

                  <!-- Column Content: deal cards (empty columns stay minimal, like leads) -->
                  <div class="column-content column-content-scrollable p-8 flex-grow-1 d-flex flex-column">
                    <draggable 
                      v-model="column.deals" 
                      :group="'deals-' + activeTypeTab" 
                      item-key="id"
                      class="tasks-list flex-grow-1 min-height-cards" 
                      :disabled="kanbanIsMobile"
                      :ghost-class="'ghost'"
                      :drag-class="'dragging'"
                      @start="onDealDragStart"
                      @end="onDealDragEnd"
                      @move="onMoveDeal"
                      @change="(evt) => onDealDragChange(evt, column)"
                    >
                      <template #item="{ element: deal }">
                        <div
                          class="kanban-card kanban-card-figma bg-white radius-12 mb-10 cursor-pointer"
                          :class="{
                            'mobile-pressing': mobilePressState.dealId === deal.id && mobilePressState.isPressing,
                            'mobile-action-origin': mobileActionSheet.visible && mobileActionSheet.deal?.id === deal.id
                          }"
                          @click="onDealCardTap(deal, column)"
                          @touchstart.passive="onDealTouchStart($event, deal, column)"
                          @touchmove.passive="onDealTouchMove($event)"
                          @touchend="onDealTouchEnd"
                          @touchcancel="onDealTouchCancel"
                        >
                          <div class="kanban-card-top d-flex align-items-start">
                            <p class="task-title flex-grow-1 mb-0">{{ deal.deal_name || 'Untitled Deal' }}</p>
                          </div>

                          <div class="task-info">
                            <div class="info-item date-created-line mb-10">
                              <span class="date-created-label">Created :</span>
                              <span class="date-created-value">{{ formatDealCardCreated(deal.created_at) }}</span>
                            </div>

                            <div class="info-item mb-10">
                              <div class="info-label text-secondary-light text-xs">Buyer Name</div>
                              <div class="info-value">{{ deal.buyer_name || '—' }}</div>
                            </div>

                            <div class="info-item mb-0">
                              <div class="info-label text-secondary-light text-xs">Source</div>
                              <div class="info-value">{{ deal.source || '—' }}</div>
                            </div>

                            <!-- <hr class="kanban-card-divider my-10"> -->

                              <!-- Assigned By -->
                                          <div>
                                              <hr class="mb-2 border-neutral-200">
                                              <div class="mt-1 d-flex align-items-center justify-content-between assignedBy">
                                                  <div class="info-item">
                                                      <div class="info-label text-secondary-light text-xs mb-1">Assigned </div>
                                                      <div class="info-value">{{ formatDate(deal.assigned_at) }}</div>
                                                  </div>
                                                  <div
                                                      class="person-hover-anchor"
                                                      @mouseenter.stop="showPersonHoverCard(deal, 'assigned')"
                                                      @mouseleave.stop="hidePersonHoverCard"
                                                        @click.stop="openPersonProfile(deal, 'assigned', $event)"
                                                  >
                                                      <img v-if="deal?.parent?.avatar" :src="deal.parent.avatar"   alt="" class="avatar-sm rounded-circle" />
                                                      <div v-else class="avatar-sm rounded-circle bg-neutral-200 d-flex align-items-center justify-content-center">
                                                          <iconify-icon icon="solar:user-bold" class="text-neutral-600"></iconify-icon>
                                                      </div>
                                                      <transition name="person-hover-pop">
                                                          <div
                                                              v-if="isPersonHoverVisible(deal, 'assigned') && activePersonHover?.data"
                                                              class="person-hover-card person-hover-card-right"
                                                              @mouseenter.stop="cancelPersonHoverHide"
                                                              @mouseleave.stop="hidePersonHoverCard"
                                                                @click.stop="openPersonProfile(deal, 'assigned', $event)"
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
                          </div>
                        </div>
                      </template>
                        <template #footer>
                        <div v-if="column.hasMoreDeals || column.loadingMore" class="py-3 text-center">
                          <div v-if="column.loadingMore" class="d-flex justify-content-center align-items-center gap-2">
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                              <span class="visually-hidden">Loading...</span>
                            </div>
                            <span class="text-muted small">Loading more deals...</span>
                          </div>
                          <div v-else-if="column.hasMoreDeals" :id="`sentinel-${column.stage_id}`" class="sentinel-trigger" style="height: 10px;"></div>
                        </div>
                        <div v-if="!column.hasMoreDeals && column.deals.length > 0" class="text-center py-2">
                          <span class="text-muted small">No more deals to load</span>
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
    <ViewDealModal
      v-model="showViewDealModal"
      :deal="selectedDeal"
      @deal-updated="handleDealUpdatedFromModal"
      @stage-change-request="handleStageChangeFromModal"
        :auto-edit-section="autoEditSection"
        
    />

    <div
      v-if="mobileActionSheet.visible"
      class="mobile-action-sheet-overlay"
      @click="closeMobileActionSheet"
    >
      <div class="mobile-action-sheet" @click.stop>
        <div class="mobile-action-sheet-handle"></div>
        <div class="mobile-action-sheet-title">
          {{ mobileActionSheet.deal?.deal_name || 'Deal actions' }}
        </div>

        <button
          type="button"
          class="mobile-action-item"
          :class="{ active: mobileActionSheet.mode === 'assign' }"
          @click="mobileActionSheet.mode = 'assign'"
        >
          <iconify-icon icon="lucide:user-plus" />
          Assign
        </button>

        <button
          type="button"
          class="mobile-action-item"
          :class="{ active: mobileActionSheet.mode === 'stage' }"
          @click="mobileActionSheet.mode = 'stage'"
        >
          <iconify-icon icon="lucide:move-right" />
          Move to stage
        </button>

        <div v-if="mobileActionSheet.mode === 'assign'" class="mobile-action-panel">
          <div class="mobile-action-panel-title">Select responsible person</div>
          <div v-if="mobileResponsibleLoading" class="mobile-action-loading">Loading...</div>
          <button
            v-for="person in mobileResponsibleOptions"
            :key="person.id"
            type="button"
            class="mobile-list-item"
            @click="assignDealFromMobileSheet(person.id)"
          >
            <span class="text-truncate">{{ person.name }}</span>
          </button>
        </div>

        <div v-if="mobileActionSheet.mode === 'stage'" class="mobile-action-panel">
          <div class="mobile-action-panel-title">Select stage</div>
          <button
            v-for="stage in mobileStageOptions"
            :key="stage.stage_id"
            type="button"
            class="mobile-list-item"
            @click="moveDealFromMobileSheet(stage)"
          >
            <span class="text-truncate">{{ stage.title }}</span>
          </button>
        </div>
      </div>
    </div>
    
    <CompleteStageFieldsModal
      :show="showCompleteFieldsModal"
      :deal-id="pendingCompleteFields?.dealId"
      :deal-type="pendingCompleteFields?.dealData?.deal_type || activeTypeTab"
      :target-stage-id="pendingCompleteFields?.targetStageId"
      :target-stage-name="pendingCompleteFields?.targetStageName"
      :target-stage-order="pendingCompleteFields?.targetStageOrder"
      :missing-fields="pendingCompleteFields?.missingFields || []"
      :missing-fields-grouped="pendingCompleteFields?.missingFieldsGrouped || { sections: [] }"
      :missing-fields-grouped-by-stage="pendingCompleteFields?.missingFieldsGroupedByStage || { stages: [] }"
      :grouped-missing="pendingCompleteFields?.groupedMissing || { sections: [], by_stage: [] }"
      :deal="pendingCompleteFields?.dealData || null"
      @save="handleCompleteFieldsSave"
      @closed="clearPendingCompleteFields"
      @open-deal="openDealById"
    />
    
    <StageChangeReasonModal
      ref="stageChangeReasonModal"
      :dealId="pendingStageChange?.dealId"
      :targetStageId="pendingStageChange?.targetStageId"
      :targetStageName="pendingStageChange?.targetStageName"
      :targetStageOrder="pendingStageChange?.targetStageOrder"
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
     <ProfilePopup 
        v-model="showProfilePopup"
        :user-id="profileUserId"
        @update:model-value="closeProfilePopup"
    />
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch, inject } from 'vue'
import draggable from 'vuedraggable'
import axios from '@/plugins/axios'
import { useIntersectionObserver } from '@vueuse/core' 
import Swal from 'sweetalert2'
import ViewDealModal from './ViewDealModal.vue'
import StageChangeReasonModal from './StageChangeReasonModal.vue'
import CompleteStageFieldsModal from './CompleteStageFieldsModal.vue'
import { useStageTransition } from '@/composables/useStageTransition'
import ProfilePopup from '../shared/ProfilePopup.vue'

const props = defineProps({
  filters: {
    type: Object,
    default: () => ({})
  }
})
const viewDealModalRef = ref(null)
const autoEditSection = ref(null)
// تعريف emit مرة واحدة فقط مع جميع الأحداث
const emit = defineEmits(['update:deals', 'deal-moved', 'deal-type-change'])
const openDealModal = async (dealData) => {
    console.log('🎯 Opening deal modal with data:', dealData)
    
    // 1. تغيير التاب النشط إلى نوع الديل الجديد
    const newDealType = dealData.deal_type || 'primary'
    
    if (activeTypeTab.value !== newDealType) {
        console.log(`Switching from ${activeTypeTab.value} to ${newDealType}`)
        activeTypeTab.value = newDealType
        // انتظر تحميل البيانات
        await fetchDeals(true)
    }
    
    // 2. تجهيز بيانات الديل للمودال
    selectedDeal.value = {
        ...dealData,
        deal_type: newDealType,
        stageId: dealData.stage_id,
        stageTitle: dealData.stage?.name,
        stage: dealData.stage
    }
    
    console.log('Selected deal set, opening modal:', selectedDeal.value)
    
    // 3. فتح المودال (converted lead flow -> open directly in buyer edit)
    autoEditSection.value = 'buyer_details'
    await nextTick()
    showViewDealModal.value = true
    
    console.log('Modal should be open, showViewDealModal =', showViewDealModal.value)
    
}
const handleDealCreated = (createdDeal) => {
    console.log('Deal created event received in deals component:', createdDeal)
    openDealModal(createdDeal)
}
const {
  checkStageRequirements,
  changeStage,
  updateAndChangeStage,
  normalizeMissingFromError,
} = useStageTransition()

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
const kanbanIsMobile = inject('kanbanIsMobile', ref(false))
const LONG_PRESS_MS = 420
const TOUCH_MOVE_THRESHOLD = 10
const suppressTapUntil = ref(0)
const mobileResponsibleLoading = ref(false)
const mobileResponsibleOptions = ref([])
const mobilePressState = ref({
  timer: null,
  startX: 0,
  startY: 0,
  isPressing: false,
  isLongPress: false,
  dealId: null,
  deal: null,
  column: null,
})
const mobileActionSheet = ref({
  visible: false,
  mode: null, // assign | stage
  deal: null,
  column: null,
})

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

// Complete required fields before stage change
const showCompleteFieldsModal = ref(false)
const pendingCompleteFields = ref(null)


const showStageChangeModal = ref(false)


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

const blockedMove = ref(false)

function onMoveDeal(evt) {
  if (kanbanIsMobile.value) return true

  // إذا كان هناك مودال مفتوح، امنع الحركة
  if (showCompleteFieldsModal.value || pendingStageChange.value) {
    return false
  }

  // اسمح بالحركة دائماً، وسيتم التعامل مع التحقق في onDealDragChange
  return true
}
// Stage modal for editing
const showStageModal = ref(false)
const isEditingStage = ref(false)
const stageForm = ref({
  id: null,
  name: '',
  color: null
})

const typeTabs = [
  { id: 'primary', name: 'Primary', icon: 'lucide:layout-grid' },
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
const isDealDragging = ref(false)
const dragPointerX = ref(null)
let dragAutoScrollRaf = null
const DRAG_SCROLL_EDGE_THRESHOLD = 220
const DRAG_SCROLL_MAX_SPEED = 10

const showProfilePopup = ref(false)
const profileUserId = ref(null)
const profileTriggerType = ref(null)
const activePersonHover = ref(null)
const personHoverHideTimer = ref(null)

const openPersonProfile = (task, type, event) => {
    if (event) event.stopPropagation()
    
    const person = type === 'assigned' ? task?.parent : task?.responsible_person
    if (!person?.id) return
    
    profileUserId.value = person.id
    profileTriggerType.value = type
    showProfilePopup.value = true
}


const normalizePersonHoverData = (person, task = {}, type = 'responsible', fallbackName = 'Unknown') => {
    const name = person?.name || person?.full_name || fallbackName
    const position = person?.position || person?.designation || person?.job_title || person?.role_name || person?.role || 'Team Member'
    const manager =
        person?.manager_name ||
        person?.team_lead_name ||
        person?.reports_to_name ||
        person?.parent_name ||
        person?.manager?.name ||
        person?.team_lead?.name ||
        person?.parent?.name ||
        (type === 'responsible' ? (task?.parent?.name || task?.manager?.name || task?.team_lead?.name) : null) ||
        (type === 'assigned' ? (task?.parent?.manager_name || task?.parent?.manager?.name || task?.manager?.name) : null) ||
        'Not specified'
    const branch =
        person?.branch_name ||
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
const showPersonHoverCard = (task, type) => {
    cancelPersonHoverHide()
    const person = type === 'assigned' ? task?.parent : task?.responsible_person
    const fallbackName = type === 'assigned' ? (task?.parent?.name || 'Assigned By') : (task?.responsible_person?.name || 'Responsible Person')
    activePersonHover.value = {
        leadId: task?.id,
        type,
        data: normalizePersonHoverData(person, task, type, fallbackName),
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

const isPersonHoverVisible = (task, type) => {
    return activePersonHover.value?.leadId === task?.id && activePersonHover.value?.type === type
}

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

/** Figma card: "Nov 21 | 9:26 PM" */
function formatDealCardCreated(dateString) {
  if (!dateString) return '—'
  const date = new Date(dateString)
  if (Number.isNaN(date.getTime())) return '—'
  const datePart = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
  const timePart = date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })
  return `${datePart} | ${timePart}`
}

/** Figma card footer: "21 Dec 2025 | 12:05 PM" (uses best available timestamp from API) */
function getAssignedTimestamp(deal) {
  if (!deal) return null
  return deal.converted_at || deal.updated_at || deal.created_at || null
}

function formatDealCardAssigned(dateString) {
  if (!dateString) return '—'
  const date = new Date(dateString)
  if (Number.isNaN(date.getTime())) return '—'
  const datePart = date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
  const timePart = date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })
  return `${datePart} | ${timePart}`
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

function onGlobalPointerMove(event) {
  const x = event?.touches?.[0]?.clientX ?? event?.clientX
  if (typeof x === 'number') {
    dragPointerX.value = x
  }
}

function onContainerDragOver(event) {
  if (!isDealDragging.value) return
  const x = event?.clientX
  if (typeof x === 'number') {
    dragPointerX.value = x
  }
}

function onGlobalDragOver(event) {
  if (!isDealDragging.value) return
  const x = event?.clientX
  if (typeof x === 'number') {
    dragPointerX.value = x
  }
}

function stepDragAutoScroll() {
  if (!isDealDragging.value) {
    dragAutoScrollRaf = null
    return
  }

  const container = kanbanContainerRef.value
  if (container && typeof dragPointerX.value === 'number') {
    const rect = container.getBoundingClientRect()
    const threshold = DRAG_SCROLL_EDGE_THRESHOLD
    const maxSpeed = DRAG_SCROLL_MAX_SPEED
    let delta = 0

    if (dragPointerX.value < rect.left + threshold) {
      const ratio = Math.min(1, (rect.left + threshold - dragPointerX.value) / threshold)
      delta = -Math.ceil(maxSpeed * ratio)
    } else if (dragPointerX.value > rect.right - threshold) {
      const ratio = Math.min(1, (dragPointerX.value - (rect.right - threshold)) / threshold)
      delta = Math.ceil(maxSpeed * ratio)
    }

    if (delta !== 0) {
      container.scrollLeft += delta
      updateScrollArrows()
    }
  }

  dragAutoScrollRaf = requestAnimationFrame(stepDragAutoScroll)
}

function onDealDragStart(event) {
  if (kanbanIsMobile.value) return
  isDealDragging.value = true
  onGlobalPointerMove(event?.originalEvent || event)

  document.addEventListener('pointermove', onGlobalPointerMove, { passive: true })
  document.addEventListener('mousemove', onGlobalPointerMove, { passive: true })
  document.addEventListener('touchmove', onGlobalPointerMove, { passive: true })
  document.addEventListener('dragover', onGlobalDragOver)

  if (!dragAutoScrollRaf) {
    dragAutoScrollRaf = requestAnimationFrame(stepDragAutoScroll)
  }
}

function onDealDragEnd() {
  isDealDragging.value = false
  dragPointerX.value = null
  stopScroll()

  document.removeEventListener('pointermove', onGlobalPointerMove)
  document.removeEventListener('mousemove', onGlobalPointerMove)
  document.removeEventListener('touchmove', onGlobalPointerMove)
  document.removeEventListener('dragover', onGlobalDragOver)

  if (dragAutoScrollRaf) {
    cancelAnimationFrame(dragAutoScrollRaf)
    dragAutoScrollRaf = null
  }
}

// Fetch deals from API
const runtimeFilters = ref({})

async function fetchDeals(immediate = false, externalFilters = null) {
  if (externalFilters && typeof externalFilters === 'object') {
    runtimeFilters.value = { ...externalFilters }
  } else if (externalFilters === null) {
    runtimeFilters.value = {}
  }

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
const loadMoreDealsForColumn = async (column, reset = false) => {
  if (column.loadingMore) return; // منع الطلبات المتكررة
  
  const nextPage = reset ? 1 : (column.currentPage + 1);
  
  // إذا لم يكن هناك المزيد ولا نريد إعادة تعيين، نخرج
  if (!reset && !column.hasMoreDeals) return;

  column.loadingMore = true;
  
  try {
    const response = await axios.get('/deals/get-more/by-stage', { 
      params: {
        stage_id: column.stage_id,
        deal_type: activeTypeTab.value,
        page: nextPage,
        per_page: 10, // عدد الصفقات لكل تحميلة
        ...props.filters,
        ...runtimeFilters.value
      }
    });
    
    const newDeals = response.data.data || [];
    const total = response.data.total || 0;
    
    if (reset) {
      // أول تحميل: استبدال الصفقات
      column.deals = newDeals;
      column.currentPage = 1;
    } else {
      // تحميل إضافي: دمج الصفقات الجديدة مع القديمة
      // نمنع تكرار الـ deals عن طريق الـ ID
      const existingIds = new Set(column.deals.map(d => d.id));
      const uniqueNewDeals = newDeals.filter(d => !existingIds.has(d.id));
      column.deals.push(...uniqueNewDeals);
      column.currentPage = nextPage;
    }
    
    // تحديث عدد الصفقات الكلي
    column.deals_count = response.data.total || column.deals.length;
    // تحديد إذا كان هناك المزيد من الصفقات للتحميل
    column.hasMoreDeals = column.deals.length < total;
    
  } catch (error) {
    console.error(`Error loading deals for stage ${column.stage_id}:`, error);
    showNotification('Failed to load more deals', 'error');
  } finally {
    column.loadingMore = false;
  }
};

async function executeFetchDeals() {
  if (abortController.value) {
    abortController.value.abort();
  }
  
  abortController.value = new AbortController();
  isFetching.value = true;
  loading.value = true;
  
  try {
    // هذه الجلب الآن يجلب فقط الأعمدة (بدون صفقات) أو الصفقات الأولى للعرض الأولي
    const response = await axios.get('/deals/grouped-by-stage', { 
      params: {
        deal_type: activeTypeTab.value,
        ...props.filters,
        ...runtimeFilters.value,
        per_page: 10 
      }
    });
    
    if (response.data.success) {
      stagesData.value = response.data.data.map(stage => ({
        stage_id: stage.stage_id,
         order: stage.order,
        title: stage.stage_name,
        headerBg: stage.stage_color,
        dotColor: stage.stage_color || '#3B82F6',
        color: stage.stage_color || '#3B82F6',
        deals_count: stage.deals_count,
        deals: stage.deals || [], // أول 10 صفقات
        currentPage: 1,
        hasMoreDeals: (stage.deals?.length || 0) < (stage.total_count || stage.deals_count || 0),
        loadingMore: false,
        total_count: stage.total_count || stage.deals_count || 0
      }));
      error.value = null;
    } else {
      throw new Error('Failed to fetch deals');
    }
  } catch (err) {
    if (err.name !== 'AbortError' && err.name !== 'CanceledError') {
      console.error('Error fetching deals:', err);
      error.value = err.message || 'Failed to load deals. Please try again.';
    }
  } finally {
    loading.value = false;
    isFetching.value = false;
    abortController.value = null;
  }
}


const setupInfiniteScroll = () => {
  nextTick(() => {
    if (window._infiniteObservers) {
      window._infiniteObservers.forEach(observer => observer.disconnect());
    }
    
    const observers = [];
    
    columns.value.forEach((column) => {
      const sentinelId = `sentinel-${column.stage_id}`;
      const sentinel = document.getElementById(sentinelId);
      
      if (sentinel && column.hasMoreDeals) {
        const observer = new IntersectionObserver((entries) => {
          entries.forEach(entry => {
            if (entry.isIntersecting && column.hasMoreDeals && !column.loadingMore && !loading.value) {
              loadMoreDealsForColumn(column, false);
            }
          });
        }, { 
          threshold: 0.1,
          rootMargin: '0px 0px 100px 0px' // يبدأ التحميل قبل الوصول للنهاية بـ 100px
        });
        
        observer.observe(sentinel);
        observers.push(observer);
      }
    });
    
    window._infiniteObservers = observers;
  });
};

// Columns computed from stagesData
const columns = computed({
  get: () => stagesData.value,
  set: (value) => {
    stagesData.value = value
  }
})

const mobileStageOptions = computed(() => {
  if (!mobileActionSheet.value.deal) return []
  const currentStageId = mobileActionSheet.value.deal.stage_id || mobileActionSheet.value.column?.stage_id
  return columns.value.filter((c) => String(c.stage_id) !== String(currentStageId))
})

// Switch between tabs
async function switchTab(tabId) {
  showCompleteFieldsModal.value = false;
  pendingCompleteFields.value = null;
  pendingStageChange.value = null;
  activeTypeTab.value = tabId;
  
  if (window._infiniteObservers) {
    window._infiniteObservers.forEach(observer => observer.disconnect());
    window._infiniteObservers = [];
  }
  
  await fetchDeals(true);
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
  if (!deal || !deal.id) return;
  const stageId = deal.stage_id || deal.stage?.id;
  if (!stageId) return;
  
  const column = columns.value.find(col => col.stage_id === stageId);
  if (column) {
    // نمنع التكرار
    const existingIndex = column.deals.findIndex(d => d.id === deal.id);
    if (existingIndex === -1) {
      column.deals.unshift(deal);
      column.deals_count = column.deals.length;
    } else {
      column.deals[existingIndex] = deal;
    }
  }
};

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
    if (selectedDeal.value?.id === updatedDeal.id) {
      selectedDeal.value = {
        ...selectedDeal.value,
        ...updatedDeal,
        stageId: updatedDeal.stage_id ?? updatedDeal.stage?.id ?? selectedDeal.value.stageId,
        deal_type: updatedDeal.deal_type ?? selectedDeal.value.deal_type
      }
    }
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
          column.deals[index] =  { ...deal }
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
            
            columns.value[newColumnIndex].deals.unshift({ ...deal })
            columns.value[newColumnIndex].deals_count = columns.value[newColumnIndex].deals.length
          }
        }
        break
      }
    }
  }
}
// أضف هذه الدالة في DealsKanban.vue
async function fetchDealDetails(dealId) {
  try {
    const response = await axios.get(`/deals/${dealId}`)
    return response.data?.data || response.data
  } catch (error) {
    console.error('Error fetching deal details:', error)
    return null
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
  stageForm.value = {
    id: column.stage_id,  
    name: column.title,
    color: column.dotColor || column.color
  }
  
  isEditingStage.value = true
  showStageModal.value = true
}

function closeStageModal() {
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
    const response = await axios.put(`/stages/${stageForm.value.id}`, {
      name: stageForm.value.name,
      color: stageForm.value.color
    })

    const column = columns.value.find(c => c.stage_id === stageForm.value.id)
    if (column) {
      column.title = stageForm.value.name
      column.dotColor = stageForm.value.color
      column.color = stageForm.value.color
      column.headerBg = stageForm.value.color
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
  if (showCompleteFieldsModal.value || pendingStageChange.value) {
    const deal = evt.added?.element
    if (deal) {
      revertDealDrag(deal, targetColumn, deal.stage_id)
    }
    return
  }

  const added = evt.added
  if (!added?.element) return

  const deal = added.element
  const newStageId = targetColumn.stage_id
  const oldStageId = deal.stage_id

  if (oldStageId === newStageId) return

  const originalDeal = { ...deal }
  const sourceColumn = columns.value.find(c => c.stage_id === oldStageId)




  let fullDealData = deal
  if (!fullDealData?.properties) {
    fullDealData = await fetchDealDetails(deal.id)
    if (!fullDealData) {
      revertDealDrag(deal, targetColumn, oldStageId)
      showNotification('Failed to load deal details', 'error')
      return
    }
  }

  try {
    const res = await checkStageRequirements({
      dealId: deal.id,
      targetStageId: newStageId,
      dealType: activeTypeTab.value,
       dealData: fullDealData
    })

    const valid = res.valid
    const missingFields = res.missingFields || []

    // ❌ case 1: missing fields
    if (!valid && missingFields.length > 0) {
      // تراجع فوري في UI قبل فتح المودال
      revertDealDrag(deal, targetColumn, oldStageId)

      pendingCompleteFields.value = {
        dealId: deal.id,
        targetStageId: newStageId,
        targetStageName: targetColumn.title,
        targetStageOrder: targetColumn.order,
        originalStageId: oldStageId,
        dealData: {
          ...originalDeal,
          stage: fullDealData?.stage ?? originalDeal.stage ?? null,
        },
        missingFields,
        missingFieldsGrouped: res.missingFieldsGrouped,
        missingFieldsGroupedByStage: res.missingFieldsGroupedByStage,
        groupedMissing: res.groupedMissing,
        canProceedWithoutFields: false,
      }

      showCompleteFieldsModal.value = true
      return
    }

    // ❌ case 2: reason required
    const reasonRequired = Boolean(
      targetColumn?.reason_required ||
      targetColumn?.requires_reason ||
      targetColumn?.require_reason
    )

    if (reasonRequired) {
      // تراجع فوري في UI قبل فتح المودال
      revertDealDrag(deal, targetColumn, oldStageId)

      pendingStageChange.value = {
        dealId: deal.id,
        targetStageId: newStageId,
        targetStageName: targetColumn.title,
        targetStageOrder: targetColumn.order,
        originalStageId: oldStageId,
        originalStageName:
          columns.value.find(c => c.stage_id === oldStageId)?.title || 'Previous Stage',
        dealData: originalDeal,
      }
      return
    }

    // ✅ case 3: allowed move - لا نحتاج لحفظ الحالة هنا
    await moveDealDirectly(deal, newStageId, targetColumn, oldStageId)

  } catch (err) {
    console.error('Stage check error', err)
    revertDealDrag(deal, targetColumn, oldStageId)
    showNotification(
      err.response?.data?.message || 'Failed to validate stage change',
      'error'
    )
  }
}
// استبدل دالة moveDealDirectly بهذه:
async function moveDealDirectly(deal, newStageId, targetColumn, oldStageId) {
  try {
    const sourceColumn = columns.value.find(c => c.stage_id === oldStageId)

    if (sourceColumn) {
      sourceColumn.deals = sourceColumn.deals.filter(d => d.id !== deal.id)
      sourceColumn.deals_count = sourceColumn.deals.length
    }

    const updatedDeal = {
      ...deal,
      stage_id: newStageId
    }

    // اصلاح الخطأ: التحقق من وجود الـ deal قبل الإضافة
    const existingIndex = targetColumn.deals.findIndex(d => d.id === deal.id)
    if (existingIndex !== -1) {
      targetColumn.deals[existingIndex] = { ...updatedDeal }
    } else {
      targetColumn.deals.push({ ...updatedDeal })
    }
    targetColumn.deals_count = targetColumn.deals.length

    await changeStage({ dealId: deal.id, stageId: newStageId })

    showNotification('Deal moved successfully', 'success')

  } catch (error) {
    revertDealDrag(deal, targetColumn, oldStageId)
    showNotification('Failed to move deal', 'error')
  }
}

function findColumnByStageId(stageId) {
  return columns.value.find(c => String(c.stage_id) === String(stageId))
}

function revertDealDrag(deal, targetColumn, originalStageId) {
  const safeTargetColumn = targetColumn || findColumnByStageId(deal?.stage_id)
  const sourceColumn = findColumnByStageId(originalStageId)

  if (safeTargetColumn?.deals) {
    safeTargetColumn.deals = safeTargetColumn.deals.filter(d => d.id !== deal.id)
    safeTargetColumn.deals_count = safeTargetColumn.deals.length
  }

  if (sourceColumn?.deals) {
    const existing = sourceColumn.deals.find(d => d.id === deal.id)
    if (!existing) {
      sourceColumn.deals.push({ ...deal, stage_id: originalStageId })
      sourceColumn.deals_count = sourceColumn.deals.length
    }
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

// متغير لتخزين حالة السحب المؤقتة
const tempDragState = ref({
  isActive: false,
  sourceColumn: null,
  targetColumn: null,
  draggedDeal: null,
  originalIndex: -1
})

// دالة لحفظ حالة السحب قبل المودال
function saveDragStateBeforeModal(sourceColumn, targetColumn, draggedDeal) {
  tempDragState.value = {
    isActive: true,
    sourceColumn: sourceColumn,
    targetColumn: targetColumn,
    draggedDeal: { ...draggedDeal }, // نسخة عميقة
    originalIndex: sourceColumn?.deals.findIndex(d => d.id === draggedDeal.id) || -1
  }
}

// دالة لاستعادة الكارد عند الإلغاء
function restoreDealFromDragState() {
  if (!tempDragState.value.isActive) return false
  
  const { sourceColumn, targetColumn, draggedDeal, originalIndex } = tempDragState.value
  
  // 1. إزالة الكارد من العمود الهدف (إذا كان موجوداً)
  if (targetColumn) {
    const existingIndex = targetColumn.deals.findIndex(d => d.id === draggedDeal.id)
    if (existingIndex !== -1) {
      targetColumn.deals.splice(existingIndex, 1)
      targetColumn.deals_count = targetColumn.deals.length
    }
  }
  
  // 2. إعادة الكارد إلى العمود المصدر في مكانه الأصلي
  if (sourceColumn) {
    // تأكد من عدم وجوده مسبقاً
    const alreadyExists = sourceColumn.deals.find(d => d.id === draggedDeal.id)
    if (!alreadyExists) {
      if (originalIndex >= 0 && originalIndex <= sourceColumn.deals.length) {
        sourceColumn.deals.splice(originalIndex, 0, draggedDeal)
      } else {
        sourceColumn.deals.push(draggedDeal)
      }
      sourceColumn.deals_count = sourceColumn.deals.length
    }
  }
  
  // 3. تنظيف الحالة
  tempDragState.value.isActive = false
  
  return true
}

// استبدل دالة clearPendingCompleteFields الحالية بهذه
async function clearPendingCompleteFields() {
  const pending = pendingCompleteFields.value
  
  if (pending && pending.dealData && pending.originalStageId && pending.targetStageId) {
    const sourceColumn = findColumnByStageId(pending.originalStageId)
    const targetColumn = findColumnByStageId(pending.targetStageId)
    
    if (sourceColumn && targetColumn) {
      targetColumn.deals = targetColumn.deals.filter(d => d.id !== pending.dealData.id)
      targetColumn.deals_count = targetColumn.deals.length
      
      if (!sourceColumn.deals.find(d => d.id === pending.dealData.id)) {
        sourceColumn.deals.push(pending.dealData)
        sourceColumn.deals_count = sourceColumn.deals.length
      }
    }
  }
  
  showCompleteFieldsModal.value = false
  pendingCompleteFields.value = null

  // Final guard: resync board from backend so card never stays hidden/stuck.
  // This keeps UI consistent even if drag state became out-of-sync.
  if (pending) {
    await fetchDeals(true)
  }
}

// استبدل دالة clearPendingStageChange الحالية بهذه
async function clearPendingStageChange() {
  const pending = pendingStageChange.value
  
  if (pending && pending.dealData && pending.originalStageId && pending.targetStageId) {
    const sourceColumn = findColumnByStageId(pending.originalStageId)
    const targetColumn = findColumnByStageId(pending.targetStageId)
    if (sourceColumn && targetColumn) {
      targetColumn.deals = targetColumn.deals.filter(d => d.id !== pending.dealData.id)
      targetColumn.deals_count = targetColumn.deals.length
      if (!sourceColumn.deals.find(d => d.id === pending.dealData.id)) {
        sourceColumn.deals.push({ ...pending.dealData, stage_id: pending.originalStageId })
        sourceColumn.deals_count = sourceColumn.deals.length
      }
    }
  }
  
  pendingStageChange.value = null
  showStageChangeModal.value = false

  // Final guard: resync board from backend after cancel/close.
  if (pending) {
    await fetchDeals(true)
  }
}
/// في DealsKanban.vue
// ✅ الطريقة الصحيحة - في DealsKanban.vue
async function handleCompleteFieldsSave({ payload, documents, stage_id }) {
  const pending = pendingCompleteFields.value
  if (!pending || !pending.dealId) return

  const dealId = pending.dealId
  const targetStageId = stage_id || pending.targetStageId

  try {
    const response = await updateAndChangeStage({
      dealId,
      payload,
      documents,
      stageId: targetStageId,
    })

    showNotification('Deal updated and stage changed successfully', 'success')
    clearPendingCompleteFields()
    
    // 7. تحديث الـ UI
    await fetchDeals(true)

  } catch (err) {
    console.error('Error in handleCompleteFieldsSave:', err)
    console.error('Error response:', err.response?.data)
    
    const normalizedMissing = normalizeMissingFromError(err)
    if (normalizedMissing) {
      pendingCompleteFields.value = {
        ...pending,
        missingFields: normalizedMissing.missingFields,
        missingFieldsGrouped: normalizedMissing.missingFieldsGrouped,
        missingFieldsGroupedByStage: normalizedMissing.missingFieldsGroupedByStage,
        groupedMissing: normalizedMissing.groupedMissing,
      }
      showCompleteFieldsModal.value = true
      return
    }
    
    showNotification(err.response?.data?.message || 'Failed to update deal', 'error')
    return
  }
}
function openDealById(dealId) {
  const deal = pendingCompleteFields.value?.dealData || columns.value.flatMap(c => c.deals || []).find(d => d.id === dealId)
  if (deal) {
    selectedDeal.value = {
      ...deal,
      stageTitle: columns.value.find(c => c.stage_id === deal.stage_id)?.title,
      stageId: deal.stage_id,
      deal_type: activeTypeTab.value
    }
    // Normal card/open flow should never auto-enter edit mode.
    autoEditSection.value = null
    showViewDealModal.value = true
  }
  clearPendingCompleteFields()
}

async function handleStageChangeWithReason({ dealId, targetStageId, reason }) {
  try {
    const pending = pendingStageChange.value
    const deal = pending?.dealData
    if (!deal) return

    await changeStage({ dealId, stageId: targetStageId, reason })
    moveDealBetweenColumns(deal, pending.originalStageId, targetStageId)
    
    showNotification('Deal moved successfully', 'success')
    pendingStageChange.value = null
  } catch (error) {
    showNotification(error.response?.data?.message || 'Failed to move deal', 'error')
    throw error
  }
}

function moveDealBetweenColumns(deal, fromStageId, toStageId) {
  const fromColumn = columns.value.find(c => c.stage_id === fromStageId)
  const toColumn = columns.value.find(c => c.stage_id === toStageId)
  if (!toColumn) return

  if (fromColumn) {
    fromColumn.deals = fromColumn.deals.filter(d => d.id !== deal.id)
    fromColumn.deals_count = fromColumn.deals.length
  }

  const movedDeal = { ...deal, stage_id: toStageId }
  if (!toColumn.deals.find(d => d.id === deal.id)) {
    toColumn.deals.push(movedDeal)
    toColumn.deals_count = toColumn.deals.length
  }
}

function normalizeStageName(value) {
  return String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim()
}

async function handleStageChangeFromModal({ dealId, originalStageId, targetStageId, targetStageName,targetStageOrder, dealData }) {
  if (!dealId || targetStageId == null) return
  if (String(originalStageId) === String(targetStageId)) return
    let fullDealData = dealData
  if (!fullDealData?.properties) {
    fullDealData = await fetchDealDetails(dealId)
    if (!fullDealData) {
      showNotification('Failed to load deal details', 'error')
      return
    }
  }
  const targetColumn =
    columns.value.find((c) => String(c.stage_id) === String(targetStageId)) ||
    columns.value.find((c) => normalizeStageName(c.title) === normalizeStageName(targetStageName))

  if (!targetColumn) {
    showNotification('Target stage was not found in Kanban columns', 'error')
    return
  }

  try {
    const normalized = await checkStageRequirements({
      dealId,
      targetStageId: targetColumn.stage_id,
      dealType: activeTypeTab.value,
    })

    const valid = normalized.valid
    const missingFields = normalized.missingFields || []

    if (valid || missingFields.length === 0) {
      const reasonRequired = Boolean(
        targetColumn?.reason_required || targetColumn?.requires_reason || targetColumn?.require_reason,
      )

      if (reasonRequired) {
        pendingStageChange.value = {
          dealId,
          targetStageId: targetColumn.stage_id,
          targetStageName: targetColumn.title,
          targetStageOrder: targetColumn.Order,
          originalStageId,
          originalStageName: columns.value.find((c) => String(c.stage_id) === String(originalStageId))?.title || 'Previous Stage',
           dealData: fullDealData 
        }
        return
      }

      await changeStage({ dealId, stageId: targetColumn.stage_id })
      moveDealBetweenColumns(dealData || selectedDeal.value || { id: dealId }, originalStageId, targetColumn.stage_id)
      selectedDeal.value = {
        ...(selectedDeal.value || {}),
        stage_id: targetColumn.stage_id,
        stageId: targetColumn.stage_id,
        stageTitle: targetColumn.title,
      }
      showNotification('Deal moved successfully', 'success')
      return
    }

    pendingCompleteFields.value = {
      dealId,
      targetStageId: targetColumn.stage_id,
      targetStageName: targetColumn.title,
      targetStageOrder: targetColumn.order,
      originalStageId,
      dealData: {
        ...(dealData || selectedDeal.value || {}),
        stage: fullDealData?.stage ?? (dealData || selectedDeal.value)?.stage ?? null,
      },
      missingFields,
      missingFieldsGrouped: normalized.missingFieldsGrouped,
      missingFieldsGroupedByStage: normalized.missingFieldsGroupedByStage,
      groupedMissing: normalized.groupedMissing,
      canProceedWithoutFields: valid,
    }
    showCompleteFieldsModal.value = true
  } catch (err) {
    console.error('Modal stage validation error', err)
    showNotification(err.response?.data?.message || 'Failed to validate stage change', 'error')
  }
}

function resetMobilePressState() {
  if (mobilePressState.value.timer) {
    clearTimeout(mobilePressState.value.timer)
    mobilePressState.value.timer = null
  }
  mobilePressState.value.isPressing = false
  mobilePressState.value.isLongPress = false
  mobilePressState.value.dealId = null
  mobilePressState.value.deal = null
  mobilePressState.value.column = null
}

function openMobileActionSheet(deal, column) {
  mobileActionSheet.value = {
    visible: true,
    mode: null,
    deal,
    column,
  }
}

function closeMobileActionSheet() {
  mobileActionSheet.value = {
    visible: false,
    mode: null,
    deal: null,
    column: null,
  }
}

async function ensureMobileResponsibleOptions() {
  if (mobileResponsibleOptions.value.length > 0) return
  mobileResponsibleLoading.value = true
  try {
    const response = await axios.get('/available-responsible-persons')
    const data = response?.data?.data || response?.data || []
    mobileResponsibleOptions.value = Array.isArray(data) ? data : []
  } catch (error) {
    console.error('Failed to load responsible persons', error)
    showNotification('Failed to load responsible persons', 'error')
  } finally {
    mobileResponsibleLoading.value = false
  }
}

function onDealTouchStart(event, deal, column) {
  if (!kanbanIsMobile.value) return
  if (mobileActionSheet.value.visible) return
  const touch = event.touches?.[0]
  if (!touch) return

  resetMobilePressState()
  mobilePressState.value.isPressing = true
  mobilePressState.value.dealId = deal.id
  mobilePressState.value.deal = deal
  mobilePressState.value.column = column
  mobilePressState.value.startX = touch.clientX
  mobilePressState.value.startY = touch.clientY
  mobilePressState.value.timer = setTimeout(async () => {
    mobilePressState.value.isLongPress = true
    mobilePressState.value.isPressing = false
    suppressTapUntil.value = Date.now() + 500
    openMobileActionSheet(deal, column)
    await ensureMobileResponsibleOptions()
  }, LONG_PRESS_MS)
}

function onDealTouchMove(event) {
  if (!kanbanIsMobile.value || !mobilePressState.value.isPressing) return
  const touch = event.touches?.[0]
  if (!touch) return
  const dx = Math.abs(touch.clientX - mobilePressState.value.startX)
  const dy = Math.abs(touch.clientY - mobilePressState.value.startY)
  if (dx > TOUCH_MOVE_THRESHOLD || dy > TOUCH_MOVE_THRESHOLD) {
    resetMobilePressState()
  }
}

function onDealTouchEnd() {
  if (!kanbanIsMobile.value) return
  if (mobilePressState.value.timer) {
    clearTimeout(mobilePressState.value.timer)
    mobilePressState.value.timer = null
  }
  mobilePressState.value.isPressing = false
}

function onDealTouchCancel() {
  if (!kanbanIsMobile.value) return
  resetMobilePressState()
}

function onDealCardTap(deal, column) {
  if (!kanbanIsMobile.value) {
    viewDeal(deal, column)
    return
  }
  if (mobileActionSheet.value.visible) return
  if (Date.now() < suppressTapUntil.value) return
  viewDeal(deal, column)
}

async function assignDealFromMobileSheet(responsiblePersonId) {
  const deal = mobileActionSheet.value.deal
  const column = mobileActionSheet.value.column
  if (!deal?.id) return
  const stageId = deal.stage_id || column?.stage_id
  if (!stageId) return

  try {
    const res = await updateAndChangeStage({
      dealId: deal.id,
      payload: { responsible_person_id: responsiblePersonId },
      documents: [],
      stageId,
    })
    const updatedDeal = res?.data?.data ?? res?.data ?? { ...deal, responsible_person_id: responsiblePersonId }
    const selectedResponsible = mobileResponsibleOptions.value.find((p) => String(p.id) === String(responsiblePersonId))
    if (selectedResponsible) {
      updatedDeal.responsible_person = {
        id: selectedResponsible.id,
        name: selectedResponsible.name,
        avatar: selectedResponsible.avatar || selectedResponsible.profile_image || null,
      }
    }
    handleUpdatedDeal(updatedDeal)
    closeMobileActionSheet()
    showNotification('Responsible person updated successfully', 'success')
  } catch (error) {
    console.error('Assign from mobile sheet failed', error)
    showNotification(error?.response?.data?.message || 'Failed to assign deal', 'error')
  }
}

async function moveDealFromMobileSheet(stage) {
  const deal = mobileActionSheet.value.deal
  const column = mobileActionSheet.value.column
  if (!deal?.id || !stage?.stage_id) return

  closeMobileActionSheet()
  await handleStageChangeFromModal({
    dealId: deal.id,
    originalStageId: deal.stage_id || column?.stage_id,
    targetStageId: stage.stage_id,
    targetStageName: stage.title,
    dealData: { ...deal },
  })
}

// View deal
function viewDeal(deal, column) {
  selectedDeal.value = {
    ...deal,
    stageTitle: column?.title,
    stageId: column?.stage_id,
    deal_type: activeTypeTab.value
  }
  // Normal card open -> view mode only.
  autoEditSection.value = null
  showViewDealModal.value = true
}

watch(showViewDealModal, (isOpen) => {
  if (!isOpen) autoEditSection.value = null
})

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
watch(() => columns.value, () => {
  setupInfiniteScroll();
}, { deep: true, flush: 'post' });

// Lifecycle hooks
onMounted(async () => {
  await fetchDeals(true);
  nextTick(() => {
    updateScrollArrows();
    setupInfiniteScroll();
  });
  window.addEventListener('resize', updateScrollArrows);
  setTimeout(() => {
    initializeDealUpdates();
  }, 1000);
});

onUnmounted(() => {
  resetMobilePressState();
  closeMobileActionSheet();
  onDealDragEnd();
  stopScroll();
  window.removeEventListener('resize', updateScrollArrows);
  cleanup();
  if (window._infiniteObservers) {
    window._infiniteObservers.forEach(observer => observer.disconnect());
  }
});

// Watch for filter changes
watch(() => props.filters, () => {
  fetchDeals(true)
}, { deep: true })

// Expose methods
defineExpose({
  fetchDeals,
  currentDealType: activeTypeTab,
    openDealModal,      
    handleDealCreated  
})
</script>

<style scoped>
.deals-tab-content {
  padding: 24px;
  min-height: 500px;
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
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
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
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
  background-color: transparent;
  border-radius: 12px;
  border: none;
  border-left: 1px dashed rgba(148, 163, 184, 0.45);
  height: 100%;
  flex-shrink: 0;
  overflow: visible;
}
.kanban-column:first-child {
  border-left: none;
}
.column-content {
  background: transparent;
  border-radius: 0;
}

.column-header {
  min-height: 36px;
  padding: 3px 8px 3px 10px !important;
  border-top-left-radius: 12px;
  border-top-right-radius: 12px;
  border-bottom-right-radius: 12px;
  border: none;
  box-shadow: none;
  position: relative;
  z-index: 1;
  overflow: visible;
  clip-path: polygon(0 0, calc(100% - 7px) 0, 100% 50%, calc(100% - 7px) 100%, 0 100%);
}

.column-header .header-title {
font-weight: 600;
    font-style: SemiBold;
    font-size: 11px;
    line-height: 1.1;
    color: #01062c;
    margin: 0;
}

.column-header .stage-circle {
  width: 18px;
  height: 18px;
  min-width: 18px;
  border-radius: 50%;
  border: 1px solid rgba(1, 6, 44, 0.12);
  background: rgba(255, 255, 255, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
}
.column-header::before {
  content: "";
  display: none;
}
.column-menu-icon {
  font-size: 18px;
  color: rgba(1, 6, 44, 0.5);
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
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
  font-weight: 600;
  font-size: 11px;
  line-height: 1.1;
  color: #01062c;
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

/* Kanban card — Figma deal card */
.kanban-card-figma {
  padding: 10px 10px 8px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  box-shadow: none;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.kanban-card-top {
  margin-bottom: 8px;
}

.kanban-card-divider {
  border: 0;
  border-top: 1px solid #e8ecf4;
  opacity: 1;
}

.date-created-line {
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
  font-size: 11px;
  line-height: 1.3;
  color: #8a8f98;
}

.date-created-label {
  font-weight: 500;
  margin-right: 6px;
}

.date-created-value {
  font-weight: 500;
  color: #777e89;
}

.assigned-by-line {
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
  font-size: 11px;
  font-weight: 500;
  color: #4b5563;
}

.kanban-card-footer-avatar {
  width: 26px;
  height: 26px;
}

/* Kanban Card */
.task-title {
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
  font-weight: 700;
  font-size: 12px;
  line-height: 1.25;
  letter-spacing: -0.02em;
  color: #01062c;
}

.task-header {
  align-items: flex-start;
}

.info-label {
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
  color: #8b9099;
  font-weight: 500;
  font-size: 11px;
  margin-bottom: 3px;
}

.info-value {
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
  font-weight: 500;
  font-size: 12px;
  line-height: 1.25;
  color: #343a40;
}

.date-info {
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
  font-weight: 500;
  font-size: 10px;
  line-height: 9px;
  color: #64748B;
}

.kanban-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(2, 6, 23, 0.08);
}

.mobile-pressing {
  transform: scale(0.985);
  box-shadow: 0 4px 18px rgba(2, 6, 23, 0.18);
  transition: transform 0.12s ease, box-shadow 0.12s ease;
}

.mobile-action-origin {
  border-color: #faa300 !important;
  box-shadow: 0 0 0 2px rgba(250, 163, 0, 0.25);
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
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
}

.min-height-cards {
  min-height: 40px;
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
  background: #ffffff5c;
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

.mobile-action-sheet-overlay {
  position: fixed;
  inset: 0;
  background: rgba(9, 14, 34, 0.35);
  z-index: 2500;
  display: flex;
  align-items: flex-end;
}

.mobile-action-sheet {
  width: 100%;
  background: #fff;
  border-radius: 20px 20px 0 0;
  padding: 10px 14px calc(14px + env(safe-area-inset-bottom, 0px));
  box-shadow: 0 -8px 24px rgba(15, 23, 42, 0.18);
}

.mobile-action-sheet-handle {
  width: 42px;
  height: 4px;
  background: #d1d5db;
  border-radius: 999px;
  margin: 0 auto 8px;
}

.mobile-action-sheet-title {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 8px;
}

.mobile-action-item {
  width: 100%;
  border: 1px solid #e5e7eb;
  background: #fff;
  border-radius: 12px;
  min-height: 44px;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 12px;
  color: #111827;
  font-size: 14px;
  font-weight: 500;
}

.mobile-action-item.active {
  border-color: #faa300;
  background: #fff8eb;
}

.mobile-action-panel {
  border: 1px solid #eef2f7;
  border-radius: 12px;
  padding: 10px;
  max-height: 42vh;
  overflow-y: auto;
}

.mobile-action-panel-title {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 8px;
}

.mobile-action-loading {
  font-size: 13px;
  color: #6b7280;
  padding: 8px 2px;
}

.mobile-list-item {
  width: 100%;
  border: 1px solid #e5e7eb;
  background: #fff;
  border-radius: 10px;
  min-height: 40px;
  padding: 0 10px;
  text-align: left;
  color: #111827;
  font-size: 13px;
  margin-bottom: 6px;
}

.mobile-list-item:active {
  background: #f8fafc;
}

@media (max-width: 768px) {
  .deals-tab-content--mobile {
    padding: 0 6px;
  }

  .deals-tab-content--mobile .deals-type-tabs {
    overflow-x: auto;
    flex-wrap: nowrap;
    padding-bottom: 2px;
    margin-bottom: 10px !important;
    scrollbar-width: none;
  }

  .deals-tab-content--mobile .deals-type-tabs::-webkit-scrollbar {
    display: none;
  }

  .deals-tab-content--mobile .deals-type-tab {
    height: 30px;
    font-size: 11px;
    flex-shrink: 0;
    white-space: nowrap;
    padding: 0 10px;
  }

  .kanban-outer--mobile {
    height: calc(100vh - 190px);
  }

  .kanban-container--mobile {
    overflow-x: hidden !important;
    overflow-y: auto !important;
    padding-right: 2px;
  }

  .kanban-container--mobile .kanban-wrapper {
    width: 100% !important;
    min-width: 100% !important;
    display: flex !important;
    flex-direction: column;
    gap: 10px;
    height: auto !important;
  }

  .kanban-container--mobile .kanban-column {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 100% !important;
    height: auto !important;
    border-left: none;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 0;
  }

  .kanban-container--mobile .column-header {
    clip-path: none;
    border-radius: 10px 10px 0 0;
    min-height: 34px;
  }

  .kanban-container--mobile .column-content-scrollable {
    overflow: visible;
  }

  .kanban-container--mobile .tasks-list {
    min-height: 0;
  }

  .kanban-container--mobile .kanban-card-figma {
    margin-bottom: 8px !important;
  }

  .kanban-nav-zone {
    display: none !important;
  }
}


.assignedBy .avatar-sm{
      width: 28px;
    height: 28px;
}

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

.person-hover-avatar {
    width: 34px;
    height: 34px;
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

</style>