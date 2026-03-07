<template>
  <div class="deals-tab-content">
    <!-- Top tabs: Primary/Off-Plan, Secondary, Rental (small like image) -->
    <div class="deals-type-tabs d-flex gap-2 mb-24">
      <button
        v-for="tab in typeTabs"
        :key="tab.id"
        class="deals-type-tab"
        :class="{ active: activeTypeTab === tab.id }"
        @click="activeTypeTab = tab.id"
      >
        <iconify-icon :icon="tab.icon" class="tab-icon"></iconify-icon>
        <span>{{ tab.name }}</span>
      </button>
    </div>

    <!-- Kanban board (same as Leads - no nav arrows) -->
    <div class="kanban-container">
      <div class="kanban-wrapper d-flex gap-20 h-100">
        <div
          v-for="column in columns"
          :key="column.id"
          class="kanban-column radius-12 d-flex flex-column"
        >
          <!-- Column header -->
          <div
            class="column-header d-flex align-items-center justify-content-between p-11 flex-shrink-0"
            :style="{ backgroundColor: column.headerBg }"
          >
            <div class="d-flex align-items-center gap-2">
              <div class="stage-circle">
                <div class="stage-dot" :style="{ backgroundColor: column.dotColor }"></div>
              </div>
              <p class="header-title mb-0">{{ column.title }} ({{ column.deals.length }})</p>
            </div>
            <button type="button" class="dropdown btn p-0 border-0 bg-transparent">
              <iconify-icon icon="entypo:dots-three-vertical" class="column-menu-icon"></iconify-icon>
            </button>
          </div>
          <!-- Column content: draggable cards like Leads -->
          <div class="column-content p-10 overflow-y-auto flex-grow-1 d-flex flex-column">
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
                  class="kanban-card bg-white p-16 radius-12 mb-16 shadow-sm border-0 cursor-pointer"
                  @click="viewDeal(deal, column)"
                >
                  <div class="task-header d-flex align-items-center justify-content-between gap-2 mb-12">
                    <p class="task-title flex-grow-1 mb-0">{{ deal.project }}</p>
                  </div>
                  <div class="task-info">
                    <div class="info-item date-info d-flex align-items-center gap-1 mb-8">
                      <span>Created By</span>
                      <span>{{ deal.createdBy }}</span>
                    </div>
                    <div class="info-item mb-8">
                      <div class="info-label text-secondary-light text-xs">Buyer Name</div>
                      <div class="info-value">{{ deal.buyerName }}</div>
                    </div>
                    <div class="info-item mb-8">
                      <div class="info-label text-secondary-light text-xs">Source</div>
                      <div class="info-value">{{ deal.source }}</div>
                    </div>
                    <hr class="my-12 border-neutral-200">
                    <div class="d-flex align-items-center justify-content-between">
                      <div class="info-item mb-0">
                        <div class="info-label text-secondary-light text-xs mb-1">Assigned By</div>
                        <div class="info-value">{{ deal.assignedBy }}</div>
                      </div>
                      <div class="avatar-sm rounded-circle bg-neutral-200 d-flex align-items-center justify-content-center overflow-hidden flex-shrink-0">
                        <iconify-icon icon="solar:user-bold" class="text-neutral-600"></iconify-icon>
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

    <ViewDealModal v-model="showViewDealModal" :deal="selectedDeal" />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import draggable from 'vuedraggable'
import ViewDealModal from './ViewDealModal.vue'

const activeTypeTab = ref('primary')
const showViewDealModal = ref(false)
const selectedDeal = ref(null)

const typeTabs = [
  { id: 'primary', name: 'Primary/Off-Plan', icon: 'lucide:layout-grid' },
  { id: 'secondary', name: 'Secondary', icon: 'lucide:calendar' },
  { id: 'rental', name: 'Rental', icon: 'lucide:building-2' }
]

// Same card data as in the image (reused for all tabs)
const dealCard = {
  project: 'Compleate CRM From "Mamsha Gardens Plots"',
  createdBy: 'Nov 21 | 9:26 PM',
  buyerName: 'Forwzan Riaz Mulla',
  source: 'Meta Ads - Lead Form',
  assignedBy: '21 Dec 2025 | 12.05 PM'
}

function makeDeal(id, overrides = {}) {
  return { id, ...dealCard, ...overrides }
}

// Primary/Off-Plan stages (EOI, Booking, SPA Signed, Deal Lost, Deal Won)
const primaryColumns = ref([
  { id: 'eoi', title: 'EOI', headerBg: '#DBEAFE', dotColor: '#3B82F6', deals: [makeDeal(1), makeDeal(2), makeDeal(3)] },
  { id: 'booking', title: 'Booking', headerBg: '#D1FAE5', dotColor: '#059669', deals: [makeDeal(4), makeDeal(5), makeDeal(6)] },
  { id: 'spa-signed', title: 'SPA Signed - Deal done', headerBg: '#D1FAE5', dotColor: '#059669', deals: [makeDeal(7), makeDeal(8), makeDeal(9)] },
  { id: 'deal-lost', title: 'Deal Lost', headerBg: '#FEE2E2', dotColor: '#DC2626', deals: [makeDeal(10), makeDeal(11)] },
  { id: 'deal-won', title: 'Deal Won', headerBg: '#D1FAE5', dotColor: '#059669', deals: [makeDeal(12), makeDeal(13)] }
])

// Secondary stages (counts per image: 2, 3, 0, 1, 2)
const secondaryColumns = ref([
  { id: 'security-deposit', title: 'Security Deposit', headerBg: '#D1FAE5', dotColor: '#059669', deals: [makeDeal(101), makeDeal(102)] },
  { id: 'mou-signed', title: 'MOU/Contract F Signed', headerBg: '#D1FAE5', dotColor: '#059669', deals: [makeDeal(103), makeDeal(104), makeDeal(105)] },
  { id: 'noc', title: 'NOC', headerBg: '#D1FAE5', dotColor: '#059669', deals: [] },
  { id: 'deal-lost-sec', title: 'Deal Lost', headerBg: '#FEE2E2', dotColor: '#DC2626', deals: [makeDeal(106)] },
  { id: 'deal-won-sec', title: 'Deal Won', headerBg: '#D1FAE5', dotColor: '#059669', deals: [makeDeal(107), makeDeal(108)] }
])

// Rental stages (counts per image: 2, 3, 0, 2, 2)
const rentalColumns = ref([
  { id: 'lease-off', title: 'Lease Off Latter', headerBg: '#DBEAFE', dotColor: '#3B82F6', deals: [makeDeal(201), makeDeal(202)] },
  { id: 'guarantee-letter', title: 'Guarantee Latter / Chequ...', headerBg: '#D1FAE5', dotColor: '#059669', deals: [makeDeal(203), makeDeal(204), makeDeal(205)] },
  { id: 'internal-contract', title: 'Internal Contract Signed', headerBg: '#DBEAFE', dotColor: '#3B82F6', deals: [] },
  { id: 'ejari', title: 'Ejari/Tawtheq Issued', headerBg: '#DBEAFE', dotColor: '#3B82F6', deals: [makeDeal(206), makeDeal(207)] },
  { id: 'tenant-moved', title: 'Tanant Moved in', headerBg: '#DBEAFE', dotColor: '#3B82F6', deals: [makeDeal(208), makeDeal(209)] }
])

// Show the right columns based on active tab
const columns = computed(() => {
  if (activeTypeTab.value === 'secondary') return secondaryColumns.value
  if (activeTypeTab.value === 'rental') return rentalColumns.value
  return primaryColumns.value
})

function viewDeal(deal, column) {
  selectedDeal.value = {
    ...deal,
    stageTitle: column?.title,
    stageId: column?.id,
    deal_type: activeTypeTab.value
  }
  showViewDealModal.value = true
}

function onDealDragChange(evt, column) {
  if (evt.added) {
    // Optional: call API to persist deal stage change when you have backend
    // e.g. api.post(`/deals/${deal.id}/change-stage`, { stage_id: column.id })
  }
}
</script>

<style scoped>
.deals-tab-content {
  padding: 24px;
  min-height: 500px;
  font-family: 'Montserrat', sans-serif;
}

.deals-type-tabs {
  margin-bottom: 20px;
}

/* Small tabs like image */
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

/* Same layout as Leads - full width kanban, no arrows */
.kanban-container {
  height: calc(100vh - 150px);
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
  color: #64748B;
}

.column-content {
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.column-content::-webkit-scrollbar {
  display: none;
}

/* Leads-style stage circle and dot */
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

/* Same font as Leads: header-title */
.header-title {
  font-family: 'Montserrat', sans-serif;
  font-weight: 600;
  font-size: 13px;
  color: #01062C;
}

/* Same font as Leads: task-title */
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

/* Same font as Leads: info-label, info-value, date-info */
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

.ghost {
  opacity: 0.5;
  background: #c8ebfb;
}

.dragging {
  cursor: grabbing;
}
</style>
