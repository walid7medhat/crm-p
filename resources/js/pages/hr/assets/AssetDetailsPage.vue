<template>
  <div class="dashboard-main-body hr-screen ast-detail-page">
    <div class="ast-detail">
      <button type="button" class="emp-mgmt__toolbar-btn" style="margin-bottom:12px;" @click="goBack">
        <iconify-icon icon="lucide:arrow-left" />
        <span>Back to assets</span>
      </button>

      <div v-if="loading" class="emp-mgmt__grid ast-grid">
        <div v-for="n in 4" :key="n" class="emp-skeleton" style="min-height:100px;" />
      </div>

      <div v-else-if="error" class="emp-error">
        <h6>{{ error }}</h6>
        <button type="button" class="emp-mgmt__toolbar-btn emp-mgmt__toolbar-btn--primary" @click="load">Retry</button>
      </div>

      <template v-else-if="asset">
        <header class="ast-detail__hero">
          <div class="ast-detail__image">
            <iconify-icon :icon="asset.imageIcon || 'lucide:package'" />
          </div>
          <div class="ast-detail__hero-body">
            <h6>{{ asset.name }}</h6>
            <p>{{ asset.assetId }} · {{ asset.category }} · {{ asset.serialNumber }}</p>
            <div class="ast-detail__badges">
              <span class="ast-card__badge" :class="`ast-card__badge--${asset.status}`">{{ asset.statusLabel }}</span>
              <span class="ast-detail__warranty" :class="`ast-detail__warranty--${asset.warrantyStatus?.key}`">
                {{ asset.warrantyStatus?.label }}
              </span>
            </div>
          </div>
        </header>

        <div class="ast-detail__actions">
          <button type="button" @click="openAssign"><iconify-icon icon="lucide:user-plus" /> Assign</button>
          <button type="button" @click="openTransfer"><iconify-icon icon="lucide:arrow-right-left" /> Transfer</button>
          <button type="button" @click="onReturn"><iconify-icon icon="lucide:undo-2" /> Return</button>
          <button type="button" @click="openEdit"><iconify-icon icon="lucide:pencil" /> Edit</button>
          <button type="button" class="warn" @click="onMaintenance"><iconify-icon icon="lucide:wrench" /> Maintenance</button>
          <button type="button" class="danger" @click="onDelete"><iconify-icon icon="lucide:trash-2" /> Delete</button>
        </div>

        <div class="ast-detail__layout">
          <div class="ast-detail__main">
            <section class="ast-detail__section">
              <h6>Asset information</h6>
              <div class="ast-detail__grid">
                <div><label>Asset ID</label><span>{{ asset.assetId }}</span></div>
                <div><label>Category</label><span>{{ asset.category }}</span></div>
                <div><label>Assigned employee</label><span>{{ asset.assignedEmployee }}</span></div>
                <div><label>Department</label><span>{{ asset.department }}</span></div>
                <div><label>Branch</label><span>{{ asset.branch }}</span></div>
                <div><label>Condition</label><span>{{ asset.conditionLabel }}</span></div>
                <div><label>Purchase date</label><span>{{ formatDate(asset.purchaseDate) }}</span></div>
                <div><label>Supplier</label><span>{{ asset.supplierName }}</span></div>
                <div><label>Unit price</label><span>{{ asset.unitPrice ?? '—' }}</span></div>
                <div><label>Quantity</label><span>{{ asset.quantity }}</span></div>
              </div>
            </section>

            <section class="ast-detail__section">
              <h6>Warranty information</h6>
              <div class="ast-detail__grid">
                <div><label>Warranty end</label><span>{{ formatDate(asset.warrantyDate) }}</span></div>
                <div><label>Status</label><span>{{ asset.warrantyStatus?.label }}</span></div>
                <div><label>Handover date</label><span>{{ formatDate(asset.handoverDate) }}</span></div>
              </div>
            </section>

            <section class="ast-detail__section">
              <h6>Assignment history</h6>
              <div v-if="!assignments.length" class="ast-tracking__empty">No assignments recorded.</div>
              <div v-else class="ast-detail__history">
                <article v-for="item in assignments" :key="item.id">
                  <strong>{{ item.user?.name || `User #${item.user_id}` }}</strong>
                  <p>{{ formatDate(item.handover_date) }} → {{ item.return_date ? formatDate(item.return_date) : 'Present' }}</p>
                </article>
              </div>
            </section>

            <section class="ast-detail__section">
              <h6>Maintenance records</h6>
              <div v-if="!maintenanceRecords.length" class="ast-tracking__empty">No maintenance records.</div>
              <div v-else class="ast-detail__history">
                <article v-for="(item, idx) in maintenanceRecords" :key="`m-${idx}`">
                  <strong>Maintenance</strong>
                  <p>{{ item.details }}</p>
                  <small>{{ formatDateTime(item.created_at) }}</small>
                </article>
              </div>
            </section>

            <section class="ast-detail__section">
              <h6>Documents</h6>
              <div class="ast-detail__docs">
                <p v-if="asset.description"><strong>Description</strong><br>{{ asset.description }}</p>
                <p v-if="asset.remarks"><strong>Remarks</strong><br>{{ asset.remarks }}</p>
                <p v-if="!asset.description && !asset.remarks" class="ast-tracking__empty">No documents attached.</p>
              </div>
            </section>

            <section class="ast-detail__section">
              <h6>Activity timeline</h6>
              <div v-if="!timeline.length" class="ast-tracking__empty">No activity yet.</div>
              <div v-else class="ast-detail__timeline">
                <article v-for="item in timeline" :key="item.id">
                  <span>{{ item.title }}</span>
                  <p>{{ item.detail }}</p>
                  <small>{{ formatDateTime(item.date) }}</small>
                </article>
              </div>
            </section>
          </div>

          <aside class="ast-detail__side">
            <AssetTrackingPanel :asset="asset" :warranty-alerts="[asset].filter(a => ['expired','expiring_soon'].includes(a.warrantyStatus?.key)).map(a => ({ id:a.id, name:a.name, assetId:a.assetId, warrantyDate:a.warrantyDate, status:a.warrantyStatus }))" />
          </aside>
        </div>
      </template>
    </div>

    <Teleport to="body">
      <div v-if="showEditModal" class="ast-modal-overlay" @click.self="showEditModal = false">
        <div class="ast-modal ast-modal--wide">
          <h6>Edit Asset</h6>
          <div class="ast-form-grid">
            <label>Asset name *<input v-model="editForm.name" type="text" /></label>
            <label>Serial number<input v-model="editForm.serial_number" type="text" /></label>
            <label>Status
              <select v-model="editForm.status">
                <option value="available">Available</option>
                <option value="assigned">Assigned</option>
                <option value="maintenance">Under Maintenance</option>
                <option value="disposed">Lost / Disposed</option>
              </select>
            </label>
            <label>Condition
              <select v-model="editForm.condition">
                <option value="new">New</option>
                <option value="used">Used</option>
                <option value="working">Working</option>
                <option value="damaged">Damaged</option>
                <option value="maintenance">Maintenance</option>
              </select>
            </label>
            <label>Warranty date<input v-model="editForm.warranty_date" type="date" /></label>
            <label class="ast-form-full">Description<textarea v-model="editForm.description" rows="3" /></label>
          </div>
          <div class="ast-modal__actions">
            <button type="button" class="emp-filter-sheet__clear" @click="showEditModal = false">Cancel</button>
            <button type="button" class="emp-filter-sheet__apply" @click="saveEdit">Save</button>
          </div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="showAssignModal" class="ast-modal-overlay" @click.self="showAssignModal = false">
        <div class="ast-modal">
          <h6>{{ assignMode === 'transfer' ? 'Transfer Asset' : 'Assign Asset' }}</h6>
          <div class="ast-form-grid">
            <label>
              Employee
              <select v-model="assignForm.user_id">
                <option value="">Select employee</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
              </select>
            </label>
            <label>
              Handover date
              <input v-model="assignForm.handover_date" type="date" />
            </label>
            <label class="ast-form-full">
              Notes
              <textarea v-model="assignForm.notes" rows="2" />
            </label>
          </div>
          <div class="ast-modal__actions">
            <button type="button" class="emp-filter-sheet__clear" @click="showAssignModal = false">Cancel</button>
            <button type="button" class="emp-filter-sheet__apply" @click="confirmAssign">Confirm</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Swal from 'sweetalert2'
import {
  fetchAsset,
  updateAsset,
  assignAsset,
  transferAsset,
  returnAsset,
  markAssetMaintenance,
  deleteAsset,
  buildActivityTimeline,
  getMaintenanceRecords,
} from '@/services/assetsApi'
import { fetchAgentEmployees } from '@/services/hrApi'
import AssetTrackingPanel from '@/components/hr/assets/AssetTrackingPanel.vue'

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const error = ref('')
const asset = ref(null)
const employees = ref([])
const showAssignModal = ref(false)
const showEditModal = ref(false)
const assignMode = ref('assign')
const assignForm = ref({ user_id: '', handover_date: '', notes: '' })
const editForm = ref({ name: '', serial_number: '', status: '', condition: '', warranty_date: '', description: '' })

const assignments = computed(() => asset.value?.raw?.assignments || asset.value?.assignments || [])
const timeline = computed(() => (asset.value ? buildActivityTimeline(asset.value.raw || asset.value) : []))
const maintenanceRecords = computed(() => (asset.value ? getMaintenanceRecords(asset.value.raw || asset.value) : []))

async function load() {
  loading.value = true
  error.value = ''
  try {
    asset.value = await fetchAsset(route.params.id)
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || 'Asset not found'
  } finally {
    loading.value = false
  }
}

function goBack() {
  router.push('/hr')
}

function openAssign() {
  assignMode.value = 'assign'
  assignForm.value = { user_id: '', handover_date: new Date().toISOString().slice(0, 10), notes: '' }
  showAssignModal.value = true
}

function openTransfer() {
  assignMode.value = 'transfer'
  assignForm.value = { user_id: '', handover_date: new Date().toISOString().slice(0, 10), notes: '' }
  showAssignModal.value = true
}

async function confirmAssign() {
  try {
    if (assignMode.value === 'transfer') await transferAsset(asset.value.id, assignForm.value)
    else await assignAsset(asset.value.id, assignForm.value)
    Swal.fire({ icon: 'success', title: 'Updated', timer: 1600, showConfirmButton: false, toast: true, position: 'top-end' })
    showAssignModal.value = false
    await load()
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed', text: e?.response?.data?.message || e?.message })
  }
}

async function onReturn() {
  const result = await Swal.fire({
    title: 'Return asset?',
    input: 'date',
    inputLabel: 'Return date',
    inputValue: new Date().toISOString().slice(0, 10),
    showCancelButton: true,
  })
  if (!result.isConfirmed) return
  try {
    await returnAsset(asset.value.id, { return_date: result.value, notes: '' })
    Swal.fire({ icon: 'success', title: 'Asset returned', timer: 1600, showConfirmButton: false, toast: true, position: 'top-end' })
    await load()
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed', text: e?.response?.data?.message || e?.message })
  }
}

function openEdit() {
  editForm.value = {
    name: asset.value.name,
    serial_number: asset.value.serialNumber === '—' ? '' : asset.value.serialNumber,
    status: asset.value.status,
    condition: asset.value.condition,
    warranty_date: asset.value.warrantyDate ? String(asset.value.warrantyDate).slice(0, 10) : '',
    description: asset.value.description || '',
  }
  showEditModal.value = true
}

async function saveEdit() {
  try {
    await updateAsset(asset.value.id, editForm.value)
    Swal.fire({ icon: 'success', title: 'Saved', timer: 1600, showConfirmButton: false, toast: true, position: 'top-end' })
    showEditModal.value = false
    await load()
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed', text: e?.response?.data?.message || e?.message })
  }
}

async function onMaintenance() {
  const result = await Swal.fire({
    title: 'Mark under maintenance?',
    input: 'textarea',
    showCancelButton: true,
    confirmButtonColor: '#ea580c',
  })
  if (!result.isConfirmed) return
  try {
    await markAssetMaintenance(asset.value.id, result.value || '')
    Swal.fire({ icon: 'success', title: 'Updated', timer: 1600, showConfirmButton: false, toast: true, position: 'top-end' })
    await load()
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed', text: e?.response?.data?.message || e?.message })
  }
}

async function onDelete() {
  const result = await Swal.fire({
    title: 'Delete asset?',
    text: asset.value.name,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
  })
  if (!result.isConfirmed) return
  try {
    await deleteAsset(asset.value.id)
    Swal.fire({ icon: 'success', title: 'Deleted', timer: 1600, showConfirmButton: false, toast: true, position: 'top-end' })
    goBack()
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed', text: e?.response?.data?.message || e?.message })
  }
}

function formatDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatDateTime(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

onMounted(async () => {
  employees.value = await fetchAgentEmployees().catch(() => [])
  await load()
})
</script>

<style>
@import '../../../../css/hr-employees.css';
@import '../../../../css/hr-assets.css';
</style>
