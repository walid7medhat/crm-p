<template>
  <div class="emp-mgmt ast-mgmt" :class="{ 'emp-mgmt--embedded': embedded }">
    <section class="emp-mgmt__stats">
      <div class="emp-mgmt__stats-grid ast-stats-grid">
        <article v-for="stat in kpiCards" :key="stat.key" class="emp-stat-card">
          <div>
            <p class="emp-stat-card__value">{{ stat.value }}</p>
            <p class="emp-stat-card__label">{{ stat.label }}</p>
          </div>
          <span class="emp-stat-card__icon" :style="{ background: stat.bgColor, color: stat.iconColor }">
            <iconify-icon :icon="stat.icon" />
          </span>
        </article>
      </div>
    </section>

    <div class="ast-view-tabs">
      <button
        v-for="tab in viewTabs"
        :key="tab.id"
        type="button"
        class="ast-view-tab"
        :class="{ 'is-active': activeView === tab.id }"
        @click="activeView = tab.id"
      >
        <iconify-icon :icon="tab.icon" />
        {{ tab.label }}
      </button>
    </div>

    <div class="emp-mgmt__toolbar">
      <div class="emp-mgmt__search-row">
        <div class="emp-mgmt__search">
          <iconify-icon icon="lucide:search" class="emp-mgmt__search-icon" />
          <input
            v-model="searchQuery"
            type="search"
            placeholder="Search asset name, ID, serial, employee, category…"
            autocomplete="off"
          />
        </div>
        <button type="button" class="emp-mgmt__toolbar-btn" @click="showFilters = !showFilters">
          <iconify-icon icon="lucide:sliders-horizontal" />
          <span v-if="!isMobile">Filters{{ activeFilterCount ? ` (${activeFilterCount})` : '' }}</span>
        </button>
        <button v-if="!isMobile" type="button" class="emp-mgmt__toolbar-btn" @click="exportList">
          <iconify-icon icon="lucide:download" />
          <span>Export</span>
        </button>
        <button v-if="!isMobile" type="button" class="emp-mgmt__toolbar-btn emp-mgmt__toolbar-btn--primary" @click="openCreate">
          <iconify-icon icon="lucide:plus" />
          <span>Add Asset</span>
        </button>
      </div>

      <div class="emp-mgmt__chips">
        <button
          v-for="chip in quickChips"
          :key="chip.key + chip.value"
          type="button"
          class="emp-mgmt__chip"
          :class="{ 'is-active': filters[chip.key] === chip.value }"
          @click="toggleChip(chip.key, chip.value)"
        >
          {{ chip.label }}
        </button>
        <button v-if="hasActiveFilters" type="button" class="emp-mgmt__chip emp-mgmt__chip--clear" @click="onClearFilters">
          Clear all
        </button>
      </div>

      <div v-if="showFilters && !isMobile" class="emp-filter-desktop">
        <AssetsFilterFields
          v-model="localFilters"
          :asset-types="assetTypes"
          :departments="departments"
          :employees="employees"
        />
        <div style="grid-column:1/-1;display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" class="emp-filter-sheet__clear" style="min-height:40px;padding:0 16px;border-radius:10px;" @click="onClearFilters">Clear</button>
          <button type="button" class="emp-filter-sheet__apply" style="min-height:40px;padding:0 20px;border-radius:10px;border:none;" @click="onApplyFilters">Apply</button>
        </div>
      </div>
    </div>

    <div v-if="loading" class="emp-mgmt__grid ast-grid">
      <div v-for="n in 6" :key="n" class="emp-skeleton" />
    </div>

    <div v-else-if="error" class="emp-error">
      <div class="emp-error__icon"><iconify-icon icon="lucide:alert-circle" /></div>
      <h6>Could not load assets</h6>
      <p>{{ error }}</p>
      <button type="button" class="emp-mgmt__toolbar-btn emp-mgmt__toolbar-btn--primary" @click="loadAssets(true)">Try again</button>
    </div>

    <template v-else-if="activeView === 'inventory'">
      <div v-if="!filteredAssets.length" class="emp-empty">
        <div class="emp-empty__icon"><iconify-icon icon="lucide:package" /></div>
        <h6>No assets found</h6>
        <p>{{ hasActiveFilters ? 'Try adjusting your search or filters.' : 'Add your first asset to get started.' }}</p>
        <button v-if="!hasActiveFilters" type="button" class="emp-mgmt__toolbar-btn emp-mgmt__toolbar-btn--primary" @click="openCreate">Add Asset</button>
      </div>
      <div v-else class="emp-mgmt__grid ast-grid">
        <AssetCard
          v-for="asset in filteredAssets"
          :key="asset.id"
          :asset="asset"
          @view="onView"
          @assign="openAssign"
          @maintenance="onMaintenance"
          @edit="openEdit"
        />
      </div>
      <div v-if="currentPage < lastPage" class="emp-load-more">
        <button type="button" :disabled="loadingMore" @click="loadMore">
          {{ loadingMore ? 'Loading…' : `Load more (${assets.length} of ${total})` }}
        </button>
      </div>
    </template>

    <template v-else>
      <AssetTrackingPanel :assets="filteredAssets" :warranty-alerts="warrantyAlerts" />
    </template>

    <button v-if="isMobile" type="button" class="emp-fab" aria-label="Add asset" @click="openCreate">
      <iconify-icon icon="lucide:plus" />
    </button>

    <Teleport to="body">
      <div v-if="showFilters && isMobile" class="emp-filter-sheet" @click.self="showFilters = false">
        <div class="emp-filter-sheet__backdrop" @click="showFilters = false" />
        <div class="emp-filter-sheet__panel">
          <div class="emp-filter-sheet__handle" />
          <div class="emp-filter-sheet__head">
            <h6>Filter assets</h6>
            <button type="button" class="emp-mgmt__toolbar-btn" @click="showFilters = false">
              <iconify-icon icon="lucide:x" />
            </button>
          </div>
          <AssetsFilterFields
            v-model="localFilters"
            :asset-types="assetTypes"
            :departments="departments"
            :employees="employees"
          />
          <div class="emp-filter-sheet__actions">
            <button type="button" class="emp-filter-sheet__clear" @click="onClearFilters">Clear all</button>
            <button type="button" class="emp-filter-sheet__apply" @click="onApplyFilters">Apply</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ===== MODIFIED: Create/Edit Modal with full fields matching the external modal ===== -->
    <Teleport to="body">
      <div v-if="showFormModal" class="edit-overlay add-employee-overlay" @click.self="closeFormModal">
        <div class="add-employee-modal asset-create-modal">
          <div class="add-employee-head">
            <h6>{{ editingId ? 'Edit Asset' : 'Create New Asset' }}</h6>
            <button type="button" class="add-employee-close" @click="closeFormModal">
              <iconify-icon icon="lucide:x" />
            </button>
          </div>

          <div class="add-employee-body">
            <!-- Asset Details Section -->
            <section class="add-employee-section">
              <h6>Asset Details</h6>
              <div class="add-grid-two">
                <div class="add-field">
                  <label>Asset Type *</label>
                  <SearchableSelect 
                    v-model="form.asset_type_id" 
                    :options="assetTypeOptions" 
                    placeholder="Not Selected" 
                  />
                </div>
                <div class="add-field">
                  <label>Asset Name *</label>
                  <input v-model="form.name" type="text" placeholder="Enter Asset Name" />
                </div>
                <div class="add-field">
                  <label>Serial Number</label>
                  <input v-model="form.serial_number" type="text" placeholder="Enter Serial Number" />
                </div>
                <div class="add-field">
                  <label>Model Number</label>
                  <input v-model="form.model_number" type="text" placeholder="Enter Model Number" />
                </div>
                <div class="add-field">
                  <label>RDP Number</label>
                  <input v-model="form.rdp_number" type="text" placeholder="Enter reference number" />
                </div>
                <div class="add-field">
                  <label>Remarks</label>
                  <input v-model="form.remarks" type="text" placeholder="Enter Remarks" />
                </div>
                <div class="add-field add-field-full">
                  <label>Description</label>
                  <textarea v-model="form.description" placeholder="Enter Description"></textarea>
                </div>
              </div>
            </section>

            <!-- User Details Section -->
            <section class="add-employee-section">
              <h6>User Details</h6>
              <div class="add-grid-two">
                <div ref="assetUserPickerRef" class="add-field asset-user-picker-field">
                  <label>Asset User</label>
                  <button type="button" class="asset-user-trigger" @click.stop="toggleAssetUserPicker">
                    <span>{{ selectedAssetResponsiblePerson?.name || 'Not Selected' }}</span>
                    <iconify-icon icon="lucide:chevron-down" />
                  </button>
                  <div v-if="showAssetUserPicker" class="asset-user-dropdown" @click.stop>
                    <div class="asset-user-dropdown-head">
                      <span>Person</span>
                      <button type="button" class="asset-user-close-btn" @click="closeAssetUserPicker">
                        <iconify-icon icon="lucide:x" />
                      </button>
                    </div>
                    <div class="search-input-wrapper mb-2">
                      <input v-model="assetUserSearchQuery" type="text" class="asset-user-search-input" placeholder="Search Responsible Person" />
                      <iconify-icon icon="lucide:search" class="search-icon" />
                    </div>
                    <div class="asset-user-list-scroll">
                      <button
                        v-for="person in filteredAssetResponsiblePersons"
                        :key="person.id"
                        type="button"
                        class="asset-user-item"
                        :class="{ selected: Number(form.asset_user_id) === Number(person.id) }"
                        @click="selectAssetResponsiblePerson(person)"
                      >
                        <img :src="person.avatar || defaultPersonAvatar" class="asset-user-avatar" alt="user avatar" />
                        <div class="asset-user-info">
                          <div class="asset-user-head">
                            <span class="asset-user-name">{{ person.name }}</span>
                            <span v-if="person.role_name" class="user-position-badge">{{ person.role_name }}</span>
                          </div>
                          <div class="user-item-meta-line">
                            <span class="meta-value">{{ person.parent_name || person.team_lead_name || '—' }}</span>
                            <span class="meta-divider">|</span>
                            <span class="meta-value">{{ person.branch_name || person.office_name || '—' }}</span>
                          </div>
                        </div>
                      </button>
                      <div v-if="!filteredAssetResponsiblePersons.length" class="text-center text-muted py-2">No persons found</div>
                    </div>
                  </div>
                </div>
                <div class="add-field">
                  <label>Date Of Handover</label>
                  <input :value="formatDateDisplay(form.handover_date)" type="text" placeholder="-- / -- / --" readonly @click="openDatePicker('form.handover_date')" />
                </div>
                <div class="add-field">
                  <label>Branch Location</label>
                  <SearchableSelect 
                    v-model="form.branch_id" 
                    :options="branchOptions" 
                    placeholder="Not Selected" 
                  />
                </div>
                <div class="add-field">
                  <label>Department</label>
                  <SearchableSelect 
                    v-model="form.department_id" 
                    :options="departmentOptions" 
                    placeholder="Not Selected" 
                  />
                </div>
                <div class="add-field">
                  <label>Status *</label>
                  <SearchableSelect 
                    v-model="form.status" 
                    :options="statusOptions" 
                    placeholder="Not Selected" 
                  />
                </div>
                <div class="add-field">
                  <label>Date Of Return</label>
                  <input :value="formatDateDisplay(form.return_date)" type="text" placeholder="-- / -- / --" readonly @click="openDatePicker('form.return_date')" />
                </div>
              </div>
            </section>

            <!-- Purchase Details Section -->
            <section class="add-employee-section">
              <h6>Purchase Details</h6>
              <div class="add-grid-two">
                <div class="add-field">
                  <label>Purchase Date *</label>
                  <input :value="formatDateDisplay(form.purchase_date)" type="text" placeholder="-- / -- / --" readonly @click="openDatePicker('form.purchase_date')" />
                </div>
                <div class="add-field">
                  <label>Supplier Name</label>
                  <input v-model="form.supplier_name" type="text" placeholder="Enter Supplier Name" />
                </div>
                <div class="add-field">
                  <label>Warranty Date</label>
                  <input :value="formatDateDisplay(form.warranty_date)" type="text" placeholder="-- / -- / --" readonly @click="openDatePicker('form.warranty_date')" />
                </div>
                <div class="add-field">
                  <label>Condition *</label>
                  <SearchableSelect 
                    v-model="form.condition" 
                    :options="conditionOptions" 
                    placeholder="Not Selected" 
                  />
                </div>
                <div class="add-field">
                  <label>Unit Price</label>
                  <div class="asset-price-group">
                    <input v-model="form.unit_price" type="text" placeholder="Enter Amount" />
                    <select v-model="form.currency">
                      <option value="UAE Dirham">UAE Dirham</option>
                      <option value="USD">USD</option>
                      <option value="EUR">EUR</option>
                    </select>
                  </div>
                </div>
                <div class="add-field">
                  <label>QTY *</label>
                  <div class="asset-qty-group">
                    <input v-model.number="form.quantity" type="number" min="1" placeholder="Enter item quantity" />
                    <button type="button" class="asset-qty-btn" @click="decrementQty">-</button>
                    <button type="button" class="asset-qty-btn" @click="incrementQty">+</button>
                  </div>
                </div>
              </div>
            </section>
          </div>

          <div class="add-employee-footer">
            <button type="button" class="add-employee-clear-btn" @click="closeFormModal">Cancel</button>
            <button type="button" class="add-employee-save-btn" :disabled="saving" @click="saveAsset">
              {{ saving ? 'Saving…' : 'Save' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Assign / Transfer modal -->
    <Teleport to="body">
      <div v-if="showAssignModal" class="ast-modal-overlay" @click.self="showAssignModal = false">
        <div class="ast-modal">
          <h6>{{ assignMode === 'transfer' ? 'Transfer Asset' : 'Assign Asset' }}</h6>
          <p v-if="actionAsset">{{ actionAsset.name }} ({{ actionAsset.assetCode }})</p>
          <div class="ast-form-grid">
            <label>
              Employee *
              <select v-model="assignForm.user_id">
                <option value="">Select employee</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
              </select>
            </label>
            <label>
              Handover date *
              <input v-model="assignForm.handover_date" type="date" />
            </label>
            <label class="ast-form-full">
              Notes
              <textarea v-model="assignForm.notes" rows="2" />
            </label>
          </div>
          <div class="ast-modal__actions">
            <button type="button" class="emp-filter-sheet__clear" @click="showAssignModal = false">Cancel</button>
            <button type="button" class="emp-filter-sheet__apply" :disabled="saving" @click="confirmAssign">
              {{ saving ? 'Saving…' : 'Confirm' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Date Picker -->
    <DateTimePicker
      :show="showUnifiedDatePicker"
      :model-value="datePickerValue"
      :date-only="true"
      @update:show="showUnifiedDatePicker = $event"
      @apply="handleDatePickerApply"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import Swal from 'sweetalert2'
import { useAssetsManagement } from '@/composables/useAssetsManagement'
import { MOBILE_LAYOUT_MAX_WIDTH } from '@/composables/useMobileNavigation'
import {
  createAsset,
  updateAsset,
  deleteAsset,
  assignAsset,
  returnAsset,
  transferAsset,
  markAssetMaintenance,
  exportAssetsCsv,
  fetchAssetTypes,
  fetchResponsiblePersons,
  fetchAssetStatistics,
  updateAssetAssignment
} from '@/services/assetsApi'
import { fetchDepartments, fetchBranches } from '@/services/employeesApi'
import AssetCard from '@/components/hr/assets/AssetCard.vue'
import AssetsFilterFields from '@/components/hr/assets/AssetsFilterFields.vue'
import AssetTrackingPanel from '@/components/hr/assets/AssetTrackingPanel.vue'
import SearchableSelect from '@/components/ui/SearchableSelect.vue'
import DateTimePicker from '@/components/kanban/shared/DateTimePicker.vue'

defineProps({
  embedded: { type: Boolean, default: true },
})

const router = useRouter()
const isMobile = ref(false)
const showFilters = ref(false)
const activeView = ref('inventory')
const localFilters = ref({})
const showFormModal = ref(false)
const showAssignModal = ref(false)
const editingId = ref(null)
const actionAsset = ref(null)
const assignMode = ref('assign')
const saving = ref(false)

// ===== Asset User Picker =====
const showAssetUserPicker = ref(false)
const assetUserSearchQuery = ref('')
const assetUserPickerRef = ref(null)
const assetResponsiblePersons = ref([])
const defaultPersonAvatar = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'

// ===== Date Picker =====
const showUnifiedDatePicker = ref(false)
const datePickerValue = ref(null)
const activeDateField = ref('')

// ===== Options =====
const assetTypeOptions = ref([])
const departmentOptions = ref([])
const branchOptions = ref([])
const statusOptions = [
  { value: 'available', label: 'Available' },
  { value: 'assigned', label: 'Assigned' },
  { value: 'maintenance', label: 'Under Maintenance' },
  { value: 'disposed', label: 'Lost / Disposed' },
]
const conditionOptions = [
  { value: 'new', label: 'New' },
  { value: 'used', label: 'Used' },
  { value: 'working', label: 'Working' },
  { value: 'damaged', label: 'Damaged' },
  { value: 'maintenance', label: 'Maintenance' },
]

const viewTabs = [
  { id: 'inventory', label: 'Inventory', icon: 'lucide:layout-grid' },
  { id: 'tracking', label: 'Tracking', icon: 'lucide:radar' },
]

const quickChips = [
  { key: 'status', value: 'available', label: 'Available' },
  { key: 'status', value: 'assigned', label: 'Assigned' },
  { key: 'warranty_status', value: 'expiring_soon', label: 'Warranty expiring' },
]

const defaultForm = () => ({
  name: '',
  asset_type_id: '',
  serial_number: '',
  model_number: '',
  rdp_number: '',
  remarks: '',
  description: '',
  asset_user_id: null,
  handover_date: '',
  return_date: '',
  branch_id: '',
  department_id: '',
  status: 'available',
  purchase_date: '',
  supplier_name: '',
  warranty_date: '',
  condition: 'new',
  unit_price: '',
  currency: 'UAE Dirham',
  quantity: 1,
})

const form = ref(defaultForm())
const assignForm = ref({ user_id: '', handover_date: '', notes: '' })

const {
  loading,
  loadingMore,
  error,
  searchQuery,
  filters,
  assets,
  filteredAssets,
  assetTypes,
  departments,
  branches,
  employees,
  currentPage,
  lastPage,
  total,
  activeFilterCount,
  hasActiveFilters,
  kpiCards,
  warrantyAlerts,
  loadAssets,
  loadMore,
  clearFilters,
} = useAssetsManagement()

// ===== Computed =====
const selectedAssetResponsiblePerson = computed(() =>
  assetResponsiblePersons.value.find((person) => Number(person.id) === Number(form.value.asset_user_id)) || null,
)

const filteredAssetResponsiblePersons = computed(() => {
  if (!Array.isArray(assetResponsiblePersons.value) || assetResponsiblePersons.value.length === 0) {
    return []
  }
  const query = assetUserSearchQuery.value.trim().toLowerCase()
  if (!query) return assetResponsiblePersons.value
  return assetResponsiblePersons.value.filter((person) =>
    String(person.name || '').toLowerCase().includes(query) ||
    String(person.email || '').toLowerCase().includes(query)
  )
})

// ===== Helper Functions =====
function toDateValue(value) {
  if (!value) return null

  if (value instanceof Date) return value

  if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
    return new Date(`${value}T12:00:00`)
  }

  const parsed = new Date(value)
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

function toIsoDate(value) {
  if (!(value instanceof Date) || Number.isNaN(value.getTime())) {
    return ''
  }

  const y = value.getFullYear()
  const m = String(value.getMonth() + 1).padStart(2, '0')
  const d = String(value.getDate()).padStart(2, '0')

  return `${y}-${m}-${d}`
}

function formatDateDisplay(value) {
    if (!value) return ''

    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        const [y, m, d] = value.split('-')
        return `${d}/${m}/${y}`
    }

    const dt = toDateValue(value)

    if (!dt) return ''

    const d = String(dt.getDate()).padStart(2, '0')
    const m = String(dt.getMonth() + 1).padStart(2, '0')
    const y = dt.getFullYear()

    return `${d}/${m}/${y}`
}

function getFieldValueByPath(path) {
  if (path === 'form.handover_date') return form.value.handover_date
  if (path === 'form.return_date') return form.value.return_date
  if (path === 'form.purchase_date') return form.value.purchase_date
  if (path === 'form.warranty_date') return form.value.warranty_date
  return ''
}

function setFieldValueByPath(path, value) {
  if (path === 'form.handover_date') form.value.handover_date = value
  else if (path === 'form.return_date') form.value.return_date = value
  else if (path === 'form.purchase_date') form.value.purchase_date = value
  else if (path === 'form.warranty_date') form.value.warranty_date = value
}

function openDatePicker(path) {
  activeDateField.value = path
  datePickerValue.value = toDateValue(getFieldValueByPath(path))
  showUnifiedDatePicker.value = true
}

function handleDatePickerApply(date) {
  const targetPath = activeDateField.value
  if (!targetPath) return
  setFieldValueByPath(targetPath, toIsoDate(date))
}

function syncMobile() {
  isMobile.value = window.innerWidth <= MOBILE_LAYOUT_MAX_WIDTH
}

function toggleChip(key, value) {
  filters.value[key] = filters.value[key] === value ? '' : value
  loadAssets(true)
}

function onApplyFilters() {
  filters.value = { ...localFilters.value }
  showFilters.value = false
  loadAssets(true)
}

function onClearFilters() {
  localFilters.value = {
    asset_type_id: '',
    status: '',
    department_id: '',
    user_id: '',
    purchase_date_from: '',
    purchase_date_to: '',
    warranty_status: '',
  }
  clearFilters()
  showFilters.value = false
}

function onView(asset) {
  router.push(`/hr/assets/${asset.id}`)
}

function openCreate() {
  editingId.value = null
  form.value = defaultForm()
  showFormModal.value = true
  fetchAssetResponsiblePersons()
}

function openEdit(asset) {
   console.log('EDIT ASSET:', asset)
    console.log('RDP:', asset.rdp_number, asset.rdpNumber)
    editingId.value = asset.id

    const currentUserId =
        asset.currentAssignment?.user_id ||
        asset.current_assignment?.user_id ||
        asset.current_user?.id ||
        asset.currentUser?.id ||
        asset.assignedUserId ||
        asset.assetUserId ||
        null

    form.value = {
        name: asset.name || '',
        asset_type_id: asset.assetTypeId || asset.asset_type_id || '',

        serial_number:
            asset.serialNumber === '—'
                ? ''
                : asset.serialNumber || asset.serial_number || '',

        model_number:
            asset.modelNumber === '—'
                ? ''
                : asset.modelNumber || asset.model_number || '',

        rdp_number:
            asset.rdp_number ||
            asset.rdpNumber ||
            '',

        remarks: asset.remarks || '',
        description: asset.description || '',

        asset_user_id: currentUserId,

        handover_date:
            asset.handoverDate
                ? String(asset.handoverDate).slice(0, 10)
                : asset.current_assignment?.handover_date || '',

        return_date:
            asset.current_assignment?.return_date ||
            asset.currentAssignment?.return_date ||
            '',

        branch_id:
            asset.branchId ||
            asset.branch_id ||
            '',

        department_id:
            asset.departmentId ||
            asset.department_id ||
            '',

        status: asset.status || 'available',

        purchase_date:
            asset.purchaseDate
                ? String(asset.purchaseDate).slice(0, 10)
                : asset.purchase_date || '',

        supplier_name:
            asset.supplierName ||
            asset.supplier_name ||
            '',

        warranty_date:
            asset.warrantyDate
                ? String(asset.warrantyDate).slice(0, 10)
                : asset.warranty_date || '',

        condition: asset.condition || 'new',

        unit_price:
            asset.unitPrice ||
            asset.unit_price ||
            '',

        currency: asset.currency || 'UAE Dirham',

        quantity: asset.quantity || 1,
    }

    // مهم جدًا: نخزن الـ asset الحالي عشان نعرف الـ current user
    actionAsset.value = asset

    showFormModal.value = true
    fetchAssetResponsiblePersons()
}

function closeFormModal() {
  showFormModal.value = false
  editingId.value = null
  closeAssetUserPicker()
}

async function saveAsset() {

    // ================================
    // VALIDATION
    // ================================

    if (!form.value.name || !form.value.asset_type_id) {
        Swal.fire({
            icon: 'warning',
            title: 'Required fields',
            text: 'Asset name and type are required.'
        })
        return
    }

    if (
        form.value.status === 'assigned' &&
        !form.value.asset_user_id
    ) {
        Swal.fire({
            icon: 'warning',
            title: 'User required',
            text: 'Please select an employee to assign this asset.'
        })
        return
    }

    saving.value = true

    try {

        // ==========================================
        // 1. SAVE ASSET
        // ==========================================

        const payload = {
            name: form.value.name,

            asset_type_id:
                Number(form.value.asset_type_id),

            serial_number:
                form.value.serial_number || null,

            model_number:
                form.value.model_number || null,

            rdp_number:
                form.value.rdp_number || null,

            description:
                form.value.description || null,

            remarks:
                form.value.remarks || null,

            purchase_date:
                form.value.purchase_date || null,

            warranty_date:
                form.value.warranty_date || null,

            unit_price:
                form.value.unit_price || null,

            supplier_name:
                form.value.supplier_name || null,

            quantity:
                Number(form.value.quantity) || 1,

            condition:
                form.value.condition || 'new',

            status:
                form.value.status || 'available',

            branch_id:
                form.value.branch_id || null,

            department_id:
                form.value.department_id || null,
        }

        let result

        if (editingId.value) {

            // EDIT ASSET
            result = await updateAsset(
                editingId.value,
                payload
            )

        } else {

            // CREATE ASSET
            result = await createAsset(payload)
        }


        // ==========================================
        // 2. ASSIGNMENT
        // ==========================================

        if (form.value.asset_user_id) {

            const selectedUserId =
                Number(form.value.asset_user_id)

            const currentAssignment =
                actionAsset.value?.current_assignment ||
                actionAsset.value?.currentAssignment ||
                null

           
              const currentUserId =
                actionAsset.value?.currentAssignment?.user_id ||
                actionAsset.value?.current_assignment?.user_id ||
                actionAsset.value?.current_user?.id ||
                actionAsset.value?.currentUser?.id ||
                actionAsset.value?.assignedUserId ||
                actionAsset.value?.assetUserId ||
                null


            // ==========================================
            // DATES
            // ==========================================

            const handoverDate =
                form.value.handover_date ||
                new Date().toISOString().slice(0, 10)

            const returnDate =
                form.value.return_date || null


            // ==========================================
            // CASE 1: CREATE NEW ASSET
            // ==========================================

            if (!editingId.value) {

                await assignAsset(result.id, {
                    user_id: selectedUserId,

                    handover_date: handoverDate,

                    return_date: returnDate,

                    notes:
                        form.value.remarks ||
                        'Assigned during creation',
                })
            }


            // ==========================================
            // CASE 2: EDIT
            // ASSET WAS NOT ASSIGNED BEFORE
            // ==========================================

            else if (!currentUserId) {

                await assignAsset(editingId.value, {
                    user_id: selectedUserId,

                    handover_date: handoverDate,

                    return_date: returnDate,

                    notes:
                        form.value.remarks ||
                        'Assigned during edit',
                })
            }


            // ==========================================
            // CASE 3: EDIT
            // USER CHANGED
            // ==========================================

            else if (
                Number(currentUserId) !== selectedUserId
            ) {

                await transferAsset(editingId.value, {
                    user_id: selectedUserId,

                    handover_date: handoverDate,

                    return_date: returnDate,

                    notes:
                        form.value.remarks ||
                        'Transferred during edit',
                })
            }


            // ==========================================
            // CASE 4: EDIT
            // SAME USER
            // ==========================================

            else {

                if (currentAssignment?.id) {

                    await updateAssetAssignment(
                        currentAssignment.id,
                        {
                            handover_date:
                                handoverDate,

                            return_date:
                                returnDate,

                            notes:
                                form.value.remarks ||
                                null,
                        }
                    )
                }
            }
        }


        // ==========================================
        // SUCCESS
        // ==========================================

        await Swal.fire({
            icon: 'success',
            title: 'Saved',
            timer: 1600,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        })

        closeFormModal()

        await loadAssets(true)

    } catch (e) {

        console.error(
            'SAVE ASSET ERROR:',
            e
        )

        const errorMessage =
            e?.response?.data?.message ||
            e?.message ||
            'Failed to save asset'

        Swal.fire({
            icon: 'error',
            title: 'Failed',
            text: errorMessage
        })

    } finally {
        saving.value = false
    }
}
function openAssign(asset, mode = 'assign') {
  actionAsset.value = asset
  assignMode.value = mode
  assignForm.value = {
    user_id: '',
    handover_date: new Date().toISOString().slice(0, 10),
    notes: '',
  }
  showAssignModal.value = true
}

async function confirmAssign() {
  if (!actionAsset.value || !assignForm.value.user_id || !assignForm.value.handover_date) {
    Swal.fire({ 
      icon: 'warning', 
      title: 'Missing fields', 
      text: 'Select employee and handover date.' 
    })
    return
  }
  
  saving.value = true
  try {
    if (assignMode.value === 'transfer') {
      await transferAsset(actionAsset.value.id, {
        user_id: Number(assignForm.value.user_id),
        handover_date: assignForm.value.handover_date,
        notes: assignForm.value.notes,
      })
    } else {
      await assignAsset(actionAsset.value.id, {
        user_id: Number(assignForm.value.user_id),
        handover_date: assignForm.value.handover_date,
        notes: assignForm.value.notes,
      })
    }
    
    Swal.fire({ 
      icon: 'success', 
      title: 'Updated', 
      timer: 1600, 
      showConfirmButton: false, 
      toast: true, 
      position: 'top-end' 
    })
    
    showAssignModal.value = false
    await loadAssets(true)
  } catch (e) {
    Swal.fire({ 
      icon: 'error', 
      title: 'Failed', 
      text: e?.response?.data?.message || e?.message 
    })
  } finally {
    saving.value = false
  }
}

async function onMaintenance(asset) {
  const result = await Swal.fire({
    title: 'Mark under maintenance?',
    text: asset.name,
    input: 'textarea',
    inputPlaceholder: 'Notes (optional)',
    showCancelButton: true,
    confirmButtonColor: '#ea580c',
  })
  
  if (!result.isConfirmed) return
  
  try {
    await markAssetMaintenance(asset.id, result.value || '')
    Swal.fire({ 
      icon: 'success', 
      title: 'Marked under maintenance', 
      timer: 1600, 
      showConfirmButton: false, 
      toast: true, 
      position: 'top-end' 
    })
    await loadAssets(true)
  } catch (e) {
    Swal.fire({ 
      icon: 'error', 
      title: 'Failed', 
      text: e?.response?.data?.message || e?.message 
    })
  }
}

function exportList() {
  if (!filteredAssets.value || !filteredAssets.value.length) {
    Swal.fire({ 
      icon: 'warning', 
      title: 'No data', 
      text: 'There are no assets to export.' 
    })
    return
  }
  
  exportAssetsCsv('assets.csv', filteredAssets.value, [
    { label: 'Asset ID', value: (r) => r.assetCode || r.assetId },
    { label: 'Name', value: (r) => r.name },
    { label: 'Category', value: (r) => r.assetType?.name || r.category },
    { label: 'Assigned To', value: (r) => r.current_user?.name || r.assignedEmployee },
    { label: 'Status', value: (r) => r.statusLabel || r.status },
    { label: 'Purchase Date', value: (r) => r.purchase_date || r.purchaseDate },
    { label: 'Serial', value: (r) => r.serial_number || r.serialNumber },
  ])
}

// ===== Asset User Picker Functions =====
async function fetchAssetResponsiblePersons() {
  if (assetResponsiblePersons.value.length) return
  try {
    const persons = await fetchResponsiblePersons()
    assetResponsiblePersons.value = Array.isArray(persons) ? persons : []
    console.log('✅ Responsible persons loaded:', assetResponsiblePersons.value.length)
  } catch (error) {
    console.error('❌ Failed to load responsible persons:', error)
    assetResponsiblePersons.value = []
  }
}

function closeAssetUserPicker() {
  showAssetUserPicker.value = false
  assetUserSearchQuery.value = ''
}

async function toggleAssetUserPicker() {
  if (!showAssetUserPicker.value) {
    await fetchAssetResponsiblePersons()
  }
  showAssetUserPicker.value = !showAssetUserPicker.value
}

function selectAssetResponsiblePerson(person) {
  form.value.asset_user_id = Number(person.id)
  
  // Auto-fill department if not set
  if (!form.value.department_id && person.department_name) {
    const dept = departmentOptions.value.find(d => d.label === person.department_name)
    if (dept) form.value.department_id = dept.value
  }
  
  // Auto-fill branch if not set
  if (!form.value.branch_id) {
    const branchName = person.branch_name || person.office_name || ''
    const branch = branchOptions.value.find(b => b.label === branchName)
    if (branch) form.value.branch_id = branch.value
  }
  
  closeAssetUserPicker()
}

// ===== QTY Functions =====
function decrementQty() {
  const current = Number(form.value.quantity) || 1
  form.value.quantity = Math.max(1, current - 1)
}

function incrementQty() {
  const current = Number(form.value.quantity) || 1
  form.value.quantity = current + 1
}

// ===== Load Options =====
async function loadOptions() {
  try {
    // Load asset types
    const types = await fetchAssetTypes()
    assetTypeOptions.value = Array.isArray(types) ? types.map(t => ({
      value: t.id,
      label: t.name || t.label || t
    })) : []
    console.log('✅ Asset types loaded:', assetTypeOptions.value.length)

    // Load departments
    const depts = await fetchDepartments()
    departmentOptions.value = Array.isArray(depts) ? depts.map(d => ({
      value: d.id,
      label: d.name || d
    })) : []
    console.log('✅ Departments loaded:', departmentOptions.value.length)

    // Load branches
    const brs = await fetchBranches()
    branchOptions.value = Array.isArray(brs) ? brs.map(b => ({
      value: b.id,
      label: b.name || b
    })) : []
    console.log('✅ Branches loaded:', branchOptions.value.length)
  } catch (error) {
    console.error('❌ Failed to load options:', error)
  }
}

// ===== Expose for parent component =====
defineExpose({ 
  openCreate,
  openEdit,
  loadAssets,
  refresh: loadAssets 
})

// ===== Lifecycle =====
onMounted(async () => {
  syncMobile()
  window.addEventListener('resize', syncMobile)
  
  await loadOptions()
  await fetchAssetResponsiblePersons()
  await loadAssets(true)
})

onUnmounted(() => {
  window.removeEventListener('resize', syncMobile)
})
</script>

<style>
@import '../../../../css/hr-employees.css';
@import '../../../../css/hr-assets.css';

/* ===== Asset Modal Styles ===== */
.asset-create-modal {
  width: min(1320px, 96vw);
  max-height: calc(100vh - 32px);
}
.asset-create-modal .add-employee-body {
  max-height: calc(100vh - 200px);
  overflow-y: auto;
}
.asset-create-modal .add-employee-section h6 {
  font-size: 15px !important;
  font-weight: 600;
  color: #111827;
}
.asset-create-modal textarea {
  width: 100%;
  min-height: 96px;
  border: 1px solid #d9dee7;
  border-radius: 8px;
  padding: 10px 12px;
  font-size: 12px;
  color: #4b5563;
  resize: vertical;
}
.asset-create-modal .add-field-full {
  grid-column: 1 / -1;
}
.asset-create-modal .add-field :deep(.vs__dropdown-toggle) {
  height: 38px;
  min-height: 38px;
  border-radius: 8px;
}
.asset-create-modal .add-field :deep(.vs__actions) {
  height: 100%;
  min-height: 100%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.asset-create-modal .add-field :deep(.vs__open-indicator) {
  position: static !important;
  top: auto !important;
  margin: 0 !important;
  transform: none !important;
  width: 12px;
  height: 12px;
  line-height: 1;
  color: #9ca3af;
}
.asset-create-modal .asset-user-picker-field {
  position: relative;
}
.asset-create-modal .asset-user-trigger {
  width: 100%;
  height: 38px;
  border: 1px solid #d9dee7;
  border-radius: 8px;
  background: #fff;
  padding: 0 10px 0 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12px;
  color: #4b5563;
}
.asset-create-modal .asset-user-dropdown {
  position: absolute;
  left: 0;
  right: 0;
  top: calc(100% + 6px);
  z-index: 40;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  padding: 10px;
}
.asset-create-modal .asset-user-dropdown-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 8px;
  margin-bottom: 8px;
}
.asset-create-modal .asset-user-close-btn {
  background: transparent;
  border: none;
  color: #0f172a;
  font-size: 18px;
}
.asset-create-modal .asset-user-search-input {
  width: 100%;
  height: 38px;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
  padding: 0 38px 0 14px;
  font-size: 12px;
}
.asset-create-modal .search-input-wrapper {
  position: relative;
}
.asset-create-modal .search-input-wrapper .search-icon {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
}
.asset-create-modal .asset-user-list-scroll {
  max-height: 280px;
  overflow-y: auto;
  padding-right: 4px;
}
.asset-create-modal .asset-user-item {
  width: 100%;
  border: none;
  background: transparent;
  display: flex;
  align-items: center;
  gap: 10px;
  text-align: left;
  border-radius: 8px;
  padding: 8px;
}
.asset-create-modal .asset-user-item:hover {
  background: #f8fafc;
}
.asset-create-modal .asset-user-item.selected {
  background: #fff7e6;
}
.asset-create-modal .asset-user-avatar {
  width: 38px;
  height: 38px;
  border-radius: 999px;
  object-fit: cover;
}
.asset-create-modal .asset-user-info {
  min-width: 0;
}
.asset-create-modal .asset-user-head {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.asset-create-modal .asset-user-name {
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
}
.asset-create-modal .user-position-badge {
  font-size: 10px;
  background: #eef2ff;
  color: #4338ca;
  padding: 2px 8px;
  border-radius: 999px;
}
.asset-create-modal .user-item-meta-line {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #64748b;
}
.asset-create-modal .user-item-meta-line .meta-divider {
  color: #cbd5e1;
}
.asset-create-modal .asset-price-group {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 140px;
}
.asset-create-modal .asset-price-group input {
  border-radius: 8px 0 0 8px;
}
.asset-create-modal .asset-price-group select {
  border: 1px solid #d9dee7;
  border-left: none;
  border-radius: 0 8px 8px 0;
  padding: 0 10px;
  font-size: 12px;
  color: #4b5563;
  background: #fff;
}
.asset-create-modal .asset-qty-group {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 24px 24px;
  gap: 4px;
  align-items: center;
}
.asset-create-modal .asset-qty-btn {
  width: 24px;
  height: 24px;
  border: 1px solid #d9dee7;
  border-radius: 6px;
  background: #fff;
  color: #6b7280;
  line-height: 1;
  font-size: 14px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.asset-create-modal .asset-qty-btn:hover {
  background: #f1f5f9;
}

/* ===== Edit Overlay ===== */
.edit-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  backdrop-filter: blur(4px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
}
.add-employee-overlay {
  align-items: flex-start;
  padding: 16px 0;
  overflow-y: auto;
}
.add-employee-modal {
  width: min(1320px, 96vw);
  max-height: calc(100vh - 32px);
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e6eaf2;
  display: flex;
  flex-direction: column;
}
.add-employee-head {
  padding: 12px 18px;
  border-bottom: 1px solid #edf1f6;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
}
.add-employee-head h6 {
  margin: 0;
  font-size: 18px !important;
  font-weight: 600;
  color: #111827;
}
.add-employee-close {
  border: none;
  background: transparent;
  color: #6b7280;
  font-size: 20px;
}
.add-employee-body {
  padding: 12px 18px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  overflow-y: auto;
  flex: 1;
}
.add-employee-section {
  border: 1px solid #edf1f6;
  border-radius: 10px;
  padding: 12px 16px;
}
.add-employee-section h6 {
  margin: 0 0 10px;
  font-size: 14px !important;
  font-weight: 600;
  color: #111827;
}
.add-grid-two {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px 14px;
}
.add-field label {
  display: block;
  margin: 0 0 5px;
  font-size: 12px;
  font-weight: 600;
  color: #1f2937;
}
.add-field input,
.add-field textarea {
  width: 100%;
  border: 1px solid #d9dee7;
  border-radius: 8px;
  padding: 0 12px;
  font-size: 12px;
  color: #4b5563;
}
.add-field input {
  height: 38px;
}
.add-field textarea {
  min-height: 80px;
  padding: 10px 12px;
  resize: vertical;
}
.add-field input::placeholder,
.add-field textarea::placeholder {
  color: #9ca3af;
}
.add-field :deep(.vs__dropdown-toggle) {
  height: 38px;
  min-height: 38px;
  border: 1px solid #d9dee7;
  border-radius: 8px;
  padding: 0 8px 0 10px;
  display: flex;
  align-items: center;
}
.add-field :deep(.vs__selected-options) {
  display: inline-flex;
  align-items: center;
  min-height: 100%;
}
.add-field :deep(.vs__actions) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  padding-right: 4px;
}
.add-field :deep(.vs__clear) {
  display: none !important;
}
.add-field :deep(.vs__open-indicator) {
  margin-top: 0;
  transform: none;
  color: #9ca3af;
}
.add-field :deep(.vs__dropdown-menu) {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  box-shadow: 0 10px 22px rgba(15, 23, 42, 0.14);
  padding: 6px;
  margin-top: 4px;
  max-height: 180px;
}
.add-field :deep(.vs__dropdown-option) {
  border-radius: 8px;
  padding: 8px 10px;
  color: #4b5563;
  font-size: 12px;
}
.add-field :deep(.vs__dropdown-option--highlight) {
  background: #f3f4f6;
  color: #111827;
}
.add-field :deep(.vs__dropdown-option--selected) {
  background: #ffffff;
  color: #111827;
  font-weight: 600;
}
.add-employee-footer {
  padding: 12px 18px 14px;
  border-top: 1px solid #edf1f6;
  display: flex;
  justify-content: center;
  gap: 12px;
  flex-shrink: 0;
}
.add-employee-clear-btn,
.add-employee-save-btn {
  min-width: 90px;
  height: 38px;
  border-radius: 999px;
  border: none;
  font-size: 13px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
  text-align: center;
  padding: 0 16px;
}
.add-employee-clear-btn {
  background: #f3f4f6;
  color: #111827;
}
.add-employee-save-btn {
  background: #02014f;
  color: #fff;
}
.add-employee-save-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* ===== Mobile Responsive ===== */
@media (max-width: 768px) {
  .add-grid-two {
    grid-template-columns: 1fr;
  }
  .asset-create-modal .asset-price-group {
    grid-template-columns: 1fr;
  }
  .asset-create-modal .asset-price-group input {
    border-radius: 8px 8px 0 0;
  }
  .asset-create-modal .asset-price-group select {
    border-left: 1px solid #d9dee7;
    border-radius: 0 0 8px 8px;
    height: 38px;
  }
}
</style>