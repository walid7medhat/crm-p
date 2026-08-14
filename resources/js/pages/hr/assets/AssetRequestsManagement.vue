<template>
  <div class="emp-mgmt ast-req-mgmt" :class="{ 'emp-mgmt--embedded': embedded }">
    <div v-if="loading" class="emp-directory-table emp-directory-table--loading">
      <div v-for="n in 6" :key="n" class="emp-directory-table__skeleton" />
    </div>
    <div v-else-if="error" class="emp-error">
      <div class="emp-error__icon"><iconify-icon icon="lucide:alert-circle" /></div>
      <h6>Could not load asset requests</h6>
      <p>{{ error }}</p>
      <button type="button" class="emp-mgmt__toolbar-btn emp-mgmt__toolbar-btn--primary" @click="loadAll">Try again</button>
    </div>
    <AssetRequestsTable
      v-else
      :requests="pagedRequests"
      v-model:page="tablePage"
      v-model:per-page="perPage"
      v-model:selected-ids="selectedIds"
      v-model:search-query="searchQuery"
      :filters="filters"
      :departments="departments"
      :total="filteredRequests.length"
      :total-pages="totalPages"
      :start-entry="startEntry"
      :end-entry="endEntry"
      :pagination-items="paginationItems"
      :has-active-filters="hasActiveFilters"
      @export="exportCurrent"
      @apply-filters="onPopupSearch"
      @clear-filters="onClearFilters"
      @edit="openEdit"
      @view="openDetails"
      @delete="confirmDelete"
      @approve="confirmApprove"
      @reject="confirmReject"
    />

    <Teleport to="body">
      <div v-if="showApplyModal" class="rec-create-job-overlay ast-req-overlay" @click.self="closeApply">
        <div class="rec-create-job-modal ast-req-modal">
          <div class="rec-create-job-modal__head">
            <h6>{{ editingId ? 'Edit Asset Request' : 'Apply Request For Assets' }}</h6>
            <button type="button" class="rec-create-job-modal__close" @click="closeApply">
              <iconify-icon icon="lucide:x" />
            </button>
          </div>

          <div class="rec-create-job-modal__body">
            <section class="rec-create-job-panel ast-req-form-panel">
              <div class="ast-req-form-grid">
                <div class="rec-create-job-field ast-req-field--full" ref="employeePickerRef">
                  <label>Employee <em>*</em></label>
                  <button type="button" class="ast-req-select-trigger" @click.stop="toggleEmployeePicker">
                    <span :class="{ 'is-placeholder': !selectedEmployee }">
                      {{ selectedEmployee?.name || 'Search Employee or ID' }}
                    </span>
                    <span class="ast-req-select-chevrons">
                      <iconify-icon icon="lucide:chevron-up" />
                      <iconify-icon icon="lucide:chevron-down" />
                    </span>
                  </button>
                  <div v-if="showEmployeePicker" class="ast-req-picker" @click.stop>
                    <div class="ast-req-picker__search">
                      <input v-model="employeeSearch" type="text" placeholder="Search Employee or ID" />
                      <iconify-icon icon="lucide:search" />
                    </div>
                    <div class="ast-req-picker__list">
                      <button
                        v-for="person in filteredEmployees"
                        :key="person.id"
                        type="button"
                        class="ast-req-picker__item"
                        :class="{ 'is-selected': Number(form.user_id) === Number(person.id) }"
                        @click="selectEmployee(person)"
                      >
                        <img :src="person.avatar" :alt="person.name" />
                        <div>
                          <div class="ast-req-picker__name">
                            <strong>{{ person.name }}</strong>
                            <span v-if="person.designation && person.designation !== '—'" class="ast-req-role">{{ person.designation }}</span>
                            <span v-else-if="person.role && person.role !== '—'" class="ast-req-role">{{ person.role }}</span>
                          </div>
                          <p>{{ person.branch || '—' }} | {{ person.department || '—' }}</p>
                        </div>
                        <iconify-icon v-if="Number(form.user_id) === Number(person.id)" icon="lucide:check" />
                      </button>
                      <p v-if="!filteredEmployees.length" class="ast-req-picker__empty">No employees found</p>
                    </div>
                  </div>
                </div>

                <div class="rec-create-job-field ast-req-field--full" ref="assetItemRef">
                  <label>Asset Item <em>*</em></label>
                  <input
                    v-model="form.asset_item"
                    type="text"
                    placeholder="Enter Asset Item name"
                    @focus="showAssetItems = true"
                    @input="showAssetItems = true"
                  />
                  <div v-if="showAssetItems && filteredAssetItems.length" class="ast-req-suggest">
                    <button
                      v-for="item in filteredAssetItems"
                      :key="item"
                      type="button"
                      :class="{ 'is-selected': form.asset_item === item }"
                      @click="selectAssetItem(item)"
                    >
                      <span>{{ item }}</span>
                      <iconify-icon v-if="form.asset_item === item" icon="lucide:check" />
                    </button>
                  </div>
                </div>

                <div class="rec-create-job-field ast-req-field--full">
                  <label>Company Name <em>*</em></label>
                  <SearchableSelect
                    v-model="form.company_name"
                    :options="companySelectOptions"
                    placeholder="Select Company Name"
                    :append-to-body="false"
                    :clearable="false"
                  />
                </div>

                <div class="rec-create-job-field ast-req-field--full">
                  <label>Branch <em>*</em></label>
                  <SearchableSelect
                    v-model="form.branch_id"
                    :options="branchSelectOptions"
                    placeholder="Select Branch"
                    :append-to-body="false"
                    :clearable="false"
                  />
                </div>

                <div class="rec-create-job-field">
                  <label>Department <em>*</em></label>
                  <SearchableSelect
                    v-model="form.department_id"
                    :options="departmentSelectOptions"
                    placeholder="Select Department"
                    :append-to-body="false"
                    :clearable="false"
                  />
                </div>

                <div class="rec-create-job-field">
                  <label>Qty <em>*</em></label>
                  <SearchableSelect
                    v-model="form.qty"
                    :options="qtyOptions"
                    placeholder="Select quantity"
                    :append-to-body="false"
                    :clearable="false"
                  />
                </div>

                <div class="rec-create-job-field ast-req-field--full">
                  <label>Description</label>
                  <textarea v-model="form.description" rows="4" placeholder="Enter Description" />
                </div>
              </div>
            </section>
          </div>

          <div class="rec-create-job-modal__footer">
            <button type="button" class="rec-create-job-clear ast-req-cancel" @click="closeApply">Cancel</button>
            <button type="button" class="rec-create-job-confirm" :disabled="saving" @click="saveRequest">
              {{ saving ? 'Saving…' : 'Apply' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="showDetails && detailsRow" class="rec-create-job-overlay ast-req-overlay" @click.self="showDetails = false">
        <div class="rec-create-job-modal ast-req-details">
          <div class="rec-create-job-modal__head">
            <h6>Asset Request Details</h6>
            <button type="button" class="rec-create-job-modal__close" @click="showDetails = false">
              <iconify-icon icon="lucide:x" />
            </button>
          </div>
          <div class="rec-create-job-modal__body">
            <div class="ast-req-detail-row">
              <span>Employee</span>
              <div class="ast-req-user">
                <img :src="detailsRow.avatar" :alt="detailsRow.userName" />
                <div>
                  <strong>{{ detailsRow.userName }}</strong>
                  <span>{{ formatEmpId(detailsRow.employeeCode) }}</span>
                </div>
              </div>
            </div>
            <div class="ast-req-detail-row">
              <span>Department</span>
              <strong>{{ detailsRow.department }}</strong>
            </div>
            <div class="ast-req-detail-stats">
              <div>
                <span>Applied Date</span>
                <strong>{{ formatDate(detailsRow.appliedAt) }}</strong>
              </div>
              <div>
                <span>Asset Item</span>
                <strong>{{ detailsRow.assetItem }}</strong>
              </div>
              <div>
                <span>Qty</span>
                <strong>{{ padQty(detailsRow.qty) }}</strong>
              </div>
              <div>
                <span>Status</span>
                <strong :class="`ast-req-status-text is-${detailsRow.status}`">{{ detailsRow.statusLabel }}</strong>
              </div>
            </div>
            <div class="ast-req-detail-meta">
              <p>
                <span>Company Name</span>
                <strong>{{ companyDisplay(detailsRow) }}</strong>
              </p>
              <p>
                <span>Branch</span>
                <strong>{{ detailsRow.branch || dash }}</strong>
              </p>
              <p>
                <span>Description</span>
                <strong>{{ detailsRow.description || dash }}</strong>
              </p>
              <p>
                <span>Approved By</span>
                <strong>{{ detailsRow.approvedBy || dash }}</strong>
              </p>
            </div>
          </div>
          <div v-if="detailsRow.status === 'pending'" class="ast-req-details__footer">
            <button type="button" class="ast-req-approve-btn" :disabled="saving" @click="confirmApprove(detailsRow)">
              Approve Asset Request
            </button>
            <button type="button" class="ast-req-reject-btn" :disabled="saving" @click="confirmReject(detailsRow)">
              Reject Asset Request
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import Swal from 'sweetalert2'
import SearchableSelect from '@/components/ui/SearchableSelect.vue'
import AssetRequestsTable from '@/components/hr/assets/AssetRequestsTable.vue'
import { useAssetRequestsManagement } from '@/composables/useAssetRequestsManagement'
import {
  createAssetRequest,
  updateAssetRequest,
  deleteAssetRequest,
  approveAssetRequest,
  rejectAssetRequest,
} from '@/services/assetsApi'
import { formatAttendanceDate } from '@/services/leaveAttendanceApi'
import { exportRecruitmentCsv } from '@/services/recruitmentApi'

defineProps({
  embedded: { type: Boolean, default: false },
})

const {
  loading,
  error,
  searchQuery,
  filters,
  departments,
  branches,
  employees,
  assetItemOptions,
  companyOptions,
  tablePage,
  perPage,
  selectedIds,
  hasActiveFilters,
  filteredRequests,
  pagedRequests,
  totalPages,
  startEntry,
  endEntry,
  paginationItems,
  applyFilters,
  clearFilters,
  loadAll,
} = useAssetRequestsManagement()

const dash = '--'
const showApplyModal = ref(false)
const showDetails = ref(false)
const detailsRow = ref(null)
const editingId = ref(null)
const saving = ref(false)
const showEmployeePicker = ref(false)
const showAssetItems = ref(false)
const employeeSearch = ref('')
const employeePickerRef = ref(null)
const assetItemRef = ref(null)

const emptyForm = () => ({
  user_id: '',
  asset_item: '',
  company_name: '',
  branch_id: '',
  department_id: '',
  qty: 1,
  description: '',
})

const form = reactive(emptyForm())

const selectedEmployee = computed(() =>
  employees.value.find((person) => Number(person.id) === Number(form.user_id)) || null
)

const filteredEmployees = computed(() => {
  const q = employeeSearch.value.trim().toLowerCase()
  if (!q) return employees.value
  return employees.value.filter((person) =>
    [person.name, person.employeeCode, person.department, person.role]
      .some((value) => String(value || '').toLowerCase().includes(q))
  )
})

const filteredAssetItems = computed(() => {
  const q = String(form.asset_item || '').trim().toLowerCase()
  if (!q) return assetItemOptions.value
  return assetItemOptions.value.filter((item) => item.toLowerCase().includes(q))
})

const companySelectOptions = computed(() =>
  companyOptions.value.map((name) => ({ value: name, label: name }))
)
const branchSelectOptions = computed(() =>
  branches.value.map((item) => ({ value: item.id, label: item.name }))
)
const departmentSelectOptions = computed(() =>
  departments.value.map((item) => ({ value: item.id, label: item.name }))
)
const qtyOptions = Array.from({ length: 20 }, (_, i) => ({
  value: i + 1,
  label: String(i + 1).padStart(2, '0'),
}))

function formatDate(value) {
  return formatAttendanceDate(value)
}

function formatEmpId(code) {
  if (!code || code === '—') return 'ID: —'
  const raw = String(code).replace(/^ID\s*:?\s*/i, '').replace(/^#/, '')
  return `ID: #${raw}`
}

function padQty(value) {
  return String(value ?? 1).padStart(2, '0')
}

function companyDisplay(row) {
  if (!row?.companyName || row.companyName === '—') return dash
  if (row.branch && row.branch !== '—' && !String(row.companyName).includes(row.branch)) {
    return `${row.companyName} (${row.branch})`
  }
  return row.companyName
}

function resetForm() {
  Object.assign(form, emptyForm())
  employeeSearch.value = ''
  showEmployeePicker.value = false
  showAssetItems.value = false
}

function openCreate() {
  editingId.value = null
  resetForm()
  if (!form.company_name && companyOptions.value[0]) form.company_name = companyOptions.value[0]
  showApplyModal.value = true
}

function openEdit(row) {
  if (row.status !== 'pending') {
    Swal.fire({ icon: 'info', title: 'Only pending requests can be edited' })
    return
  }
  editingId.value = row.id
  Object.assign(form, {
    user_id: row.userId,
    asset_item: row.assetItem === '—' ? '' : row.assetItem,
    company_name: row.companyName === '—' ? '' : row.companyName,
    branch_id: row.branchId || '',
    department_id: row.departmentId || '',
    qty: row.qty || 1,
    description: row.description || '',
  })
  showApplyModal.value = true
}

function closeApply() {
  showApplyModal.value = false
  editingId.value = null
  resetForm()
}

function toggleEmployeePicker() {
  showEmployeePicker.value = !showEmployeePicker.value
  showAssetItems.value = false
}

function selectEmployee(person) {
  form.user_id = person.id
  if (person.departmentId) {
    form.department_id = person.departmentId
  } else if (person.department && person.department !== '—') {
    const match = departments.value.find((item) => item.name === person.department)
    if (match) form.department_id = match.id
  }
  if (person.branchId) {
    form.branch_id = person.branchId
  } else if (person.branch && person.branch !== '—') {
    const match = branches.value.find((item) => item.name === person.branch)
    if (match) form.branch_id = match.id
  }
  showEmployeePicker.value = false
}

function selectAssetItem(item) {
  form.asset_item = item
  showAssetItems.value = false
}

function openDetails(row) {
  detailsRow.value = row
  showDetails.value = true
}

async function saveRequest() {
  if (!form.user_id) return Swal.fire({ icon: 'warning', title: 'Employee is required' })
  if (!String(form.asset_item || '').trim()) return Swal.fire({ icon: 'warning', title: 'Asset item is required' })
  if (!form.company_name) return Swal.fire({ icon: 'warning', title: 'Company name is required' })
  if (!form.branch_id) return Swal.fire({ icon: 'warning', title: 'Branch is required' })
  if (!form.department_id) return Swal.fire({ icon: 'warning', title: 'Department is required' })

  saving.value = true
  try {
    const payload = {
      user_id: form.user_id,
      asset_item: String(form.asset_item).trim(),
      company_name: form.company_name,
      branch_id: form.branch_id,
      department_id: form.department_id,
      qty: Number(form.qty || 1),
      description: form.description || null,
    }
    const isEdit = Boolean(editingId.value)
    if (isEdit) await updateAssetRequest(editingId.value, payload)
    else await createAssetRequest(payload)
    closeApply()
    await loadAll()
    Swal.fire({
      icon: 'success',
      title: isEdit ? 'Request updated' : 'Request applied',
      timer: 1600,
      showConfirmButton: false,
      toast: true,
      position: 'top-end',
    })
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed to save request', text: e?.response?.data?.message || e?.message })
  } finally {
    saving.value = false
  }
}

async function confirmDelete(row) {
  const result = await Swal.fire({
    icon: 'warning',
    title: 'Delete this request?',
    text: 'This action cannot be undone.',
    showCancelButton: true,
    confirmButtonText: 'Delete',
    confirmButtonColor: '#dc2626',
  })
  if (!result.isConfirmed) return
  try {
    await deleteAssetRequest(row.id)
    if (detailsRow.value?.id === row.id) showDetails.value = false
    await loadAll()
    Swal.fire({ icon: 'success', title: 'Request deleted', timer: 1600, showConfirmButton: false, toast: true, position: 'top-end' })
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed', text: e?.response?.data?.message || e?.message })
  }
}

async function confirmApprove(row) {
  const result = await Swal.fire({
    icon: 'question',
    title: 'Approve this asset request?',
    showCancelButton: true,
    confirmButtonText: 'Approve',
    confirmButtonColor: '#16a34a',
  })
  if (!result.isConfirmed) return
  saving.value = true
  try {
    const updated = await approveAssetRequest(row.id)
    if (detailsRow.value?.id === row.id) detailsRow.value = updated
    await loadAll()
    Swal.fire({ icon: 'success', title: 'Request approved', timer: 1600, showConfirmButton: false, toast: true, position: 'top-end' })
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed', text: e?.response?.data?.message || e?.message })
  } finally {
    saving.value = false
  }
}

async function confirmReject(row) {
  const result = await Swal.fire({
    icon: 'warning',
    title: 'Reject this asset request?',
    input: 'textarea',
    inputPlaceholder: 'Optional reason',
    showCancelButton: true,
    confirmButtonText: 'Reject',
    confirmButtonColor: '#dc2626',
  })
  if (!result.isConfirmed) return
  saving.value = true
  try {
    const updated = await rejectAssetRequest(row.id, result.value || '')
    if (detailsRow.value?.id === row.id) detailsRow.value = updated
    await loadAll()
    Swal.fire({ icon: 'success', title: 'Request rejected', timer: 1600, showConfirmButton: false, toast: true, position: 'top-end' })
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed', text: e?.response?.data?.message || e?.message })
  } finally {
    saving.value = false
  }
}

function onPopupSearch(payload) {
  applyFilters(payload)
}

function onClearFilters() {
  clearFilters()
}

function exportCurrent() {
  exportRecruitmentCsv('asset-requests.csv', filteredRequests.value, [
    { label: 'Applied Date', value: (r) => formatDate(r.appliedAt) },
    { label: 'User', value: (r) => r.userName },
    { label: 'Employee ID', value: (r) => r.employeeCode },
    { label: 'Department', value: (r) => r.department },
    { label: 'Asset Item', value: (r) => r.assetItem },
    { label: 'Qty', value: (r) => r.qty },
    { label: 'Status', value: (r) => r.statusLabel },
    { label: 'Company', value: (r) => r.companyName },
    { label: 'Branch', value: (r) => r.branch },
  ])
}

function onDocClick(event) {
  if (showEmployeePicker.value && !employeePickerRef.value?.contains(event.target)) {
    showEmployeePicker.value = false
  }
  if (showAssetItems.value && !assetItemRef.value?.contains(event.target)) {
    showAssetItems.value = false
  }
}

onMounted(() => document.addEventListener('click', onDocClick))
onUnmounted(() => document.removeEventListener('click', onDocClick))

defineExpose({ openCreate })
</script>

<style scoped>
@import '../../../../css/hr-employees.css';
@import '../../../../css/hr-recruitment.css';
@import '../../../../css/hr-assets.css';
</style>
