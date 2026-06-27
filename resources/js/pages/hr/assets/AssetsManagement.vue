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
      <div v-if="!filteredAssets && !filteredAssets.length" class="emp-empty">
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

    <!-- Create / Edit modal -->
    <Teleport to="body">
      <div v-if="showFormModal" class="ast-modal-overlay" @click.self="closeFormModal">
        <div class="ast-modal ast-modal--wide">
          <h6>{{ editingId ? 'Edit Asset' : 'Add New Asset' }}</h6>
          <div class="ast-form-grid">
            <label>
              Asset type *
              <select v-model="form.asset_type_id">
                <option value="">Select type</option>
                <option v-for="t in assetTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </label>
            <label>
              Asset name *
              <input v-model="form.name" type="text" placeholder="Asset name" />
            </label>
            <label>
              Serial number
              <input v-model="form.serial_number" type="text" />
            </label>
            <label>
              Model number
              <input v-model="form.model_number" type="text" />
            </label>
            <label>
              Department
              <select v-model="form.department_id">
                <option value="">Select department</option>
                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
              </select>
            </label>
            <label>
              Branch
              <select v-model="form.branch_id">
                <option value="">Select branch</option>
                <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </label>
            <label>
              Purchase date
              <input v-model="form.purchase_date" type="date" />
            </label>
            <label>
              Warranty date
              <input v-model="form.warranty_date" type="date" />
            </label>
            <label>
              Condition
              <select v-model="form.condition">
                <option value="new">New</option>
                <option value="used">Used</option>
                <option value="working">Working</option>
                <option value="damaged">Damaged</option>
                <option value="maintenance">Maintenance</option>
              </select>
            </label>
            <label>
              Status
              <select v-model="form.status">
                <option value="available">Available</option>
                <option value="assigned">Assigned</option>
                <option value="maintenance">Under Maintenance</option>
                <option value="disposed">Lost / Disposed</option>
              </select>
            </label>
            <label class="ast-form-full">
              Description
              <textarea v-model="form.description" rows="3" />
            </label>
          </div>
          <div class="ast-modal__actions">
            <button type="button" class="emp-filter-sheet__clear" @click="closeFormModal">Cancel</button>
            <button type="button" class="emp-filter-sheet__apply" :disabled="saving" @click="saveAsset">
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
          <p v-if="actionAsset">{{ actionAsset.name }} ({{ actionAsset.assetId }})</p>
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
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
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
} from '@/services/assetsApi'
import AssetCard from '@/components/hr/assets/AssetCard.vue'
import AssetsFilterFields from '@/components/hr/assets/AssetsFilterFields.vue'
import AssetTrackingPanel from '@/components/hr/assets/AssetTrackingPanel.vue'

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
  department_id: '',
  branch_id: '',
  purchase_date: '',
  warranty_date: '',
  condition: 'new',
  status: 'available',
  description: '',
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
}

function openEdit(asset) {
  editingId.value = asset.id
  form.value = {
    name: asset.name,
    asset_type_id: asset.assetTypeId || '',
    serial_number: asset.serialNumber === '—' ? '' : asset.serialNumber,
    model_number: asset.modelNumber === '—' ? '' : asset.modelNumber,
    department_id: asset.departmentId || '',
    branch_id: asset.branchId || '',
    purchase_date: asset.purchaseDate ? String(asset.purchaseDate).slice(0, 10) : '',
    warranty_date: asset.warrantyDate ? String(asset.warrantyDate).slice(0, 10) : '',
    condition: asset.condition || 'new',
    status: asset.status || 'available',
    description: asset.description || '',
  }
  showFormModal.value = true
}

function closeFormModal() {
  showFormModal.value = false
  editingId.value = null
}

async function saveAsset() {
  if (!form.value.name || !form.value.asset_type_id) {
    Swal.fire({ icon: 'warning', title: 'Required fields', text: 'Asset name and type are required.' })
    return
  }
  saving.value = true
  try {
    const payload = { ...form.value }
    if (editingId.value) await updateAsset(editingId.value, payload)
    else await createAsset(payload)
    Swal.fire({ icon: 'success', title: 'Saved', timer: 1600, showConfirmButton: false, toast: true, position: 'top-end' })
    closeFormModal()
    await loadAssets(true)
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed', text: e?.response?.data?.message || e?.message })
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
    Swal.fire({ icon: 'warning', title: 'Missing fields', text: 'Select employee and handover date.' })
    return
  }
  saving.value = true
  try {
    if (assignMode.value === 'transfer') {
      await transferAsset(actionAsset.value.id, assignForm.value)
    } else {
      await assignAsset(actionAsset.value.id, assignForm.value)
    }
    Swal.fire({ icon: 'success', title: 'Updated', timer: 1600, showConfirmButton: false, toast: true, position: 'top-end' })
    showAssignModal.value = false
    await loadAssets(true)
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed', text: e?.response?.data?.message || e?.message })
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
    Swal.fire({ icon: 'success', title: 'Marked under maintenance', timer: 1600, showConfirmButton: false, toast: true, position: 'top-end' })
    await loadAssets(true)
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed', text: e?.response?.data?.message || e?.message })
  }
}

function exportList() {
  if (!filteredAssets.value || !filteredAssets.value.length) {
    Swal.fire({ icon: 'warning', title: 'No data', text: 'There are no assets to export.' })
    return
  }
  exportAssetsCsv('assets.csv', filteredAssets.value, [
    { label: 'Asset ID', value: (r) => r.assetId },
    { label: 'Name', value: (r) => r.name },
    { label: 'Category', value: (r) => r.category },
    { label: 'Assigned To', value: (r) => r.assignedEmployee },
    { label: 'Status', value: (r) => r.statusLabel },
    { label: 'Purchase Date', value: (r) => r.purchaseDate },
    { label: 'Serial', value: (r) => r.serialNumber },
  ])
}

defineExpose({ openCreate })

onMounted(() => {
  syncMobile()
  window.addEventListener('resize', syncMobile)
  localFilters.value = { ...filters.value }
})

onUnmounted(() => {
  window.removeEventListener('resize', syncMobile)
})
</script>

<style>
@import '../../../../css/hr-employees.css';
@import '../../../../css/hr-assets.css';
</style>
