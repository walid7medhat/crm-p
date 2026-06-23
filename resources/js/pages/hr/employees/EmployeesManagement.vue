<template>
  <div class="emp-mgmt" :class="{ 'emp-mgmt--embedded': embedded }">
    <!-- Stats -->
    <section class="emp-mgmt__stats">
      <div class="emp-mgmt__stats-grid">
        <article v-for="stat in statsCards" :key="stat.key" class="emp-stat-card">
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

    <!-- Sticky toolbar -->
    <div class="emp-mgmt__toolbar" ref="toolbarRef">
      <div class="emp-mgmt__search-row">
        <button type="button" class="emp-mgmt__toolbar-btn" @click="showFilters = !showFilters">
          <iconify-icon icon="lucide:sliders-horizontal" />
          <span>Filters{{ activeFilterCount ? ` (${activeFilterCount})` : '' }}</span>
        </button>
        <button v-if="!isMobile" type="button" class="emp-mgmt__toolbar-btn emp-mgmt__toolbar-btn--primary" @click="$emit('add')">
          <iconify-icon icon="lucide:plus" />
          <span>Add Employee</span>
        </button>
      </div>

      <!-- Quick chips -->
      <div v-if="hasActiveFilters || quickChips.length" class="emp-mgmt__chips">
        <button
          v-for="chip in quickChips"
          :key="chip.key + chip.value"
          type="button"
          class="emp-mgmt__chip"
          :class="{ 'is-active': filters[chip.key] === chip.value }"
          @click="applyQuickFilter(chip.key, chip.value)"
        >
          {{ chip.label }}
        </button>
        <button v-if="hasActiveFilters" type="button" class="emp-mgmt__chip emp-mgmt__chip--clear" @click="clearFilters">
          Clear all
        </button>
      </div>

      <!-- Desktop filters panel -->
      <div v-if="showFilters && !isMobile" class="emp-filter-desktop">
        <EmployeesFilterFields
          v-model="localFilters"
          :departments="departments"
          :designations="designations"
          :branches="branches"
          :managers="managers"
        />
        <div style="grid-column: 1 / -1; display:flex; gap:10px; justify-content:flex-end;">
          <button type="button" class="emp-filter-sheet__clear" style="min-height:40px;padding:0 16px;border-radius:10px;" @click="onClearFilters">Clear</button>
          <button type="button" class="emp-filter-sheet__apply" style="min-height:40px;padding:0 20px;border-radius:10px;border:none;" @click="onApplyFilters">Apply filters</button>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="emp-directory-table emp-directory-table--loading">
      <div v-for="n in 6" :key="n" class="emp-directory-table__skeleton" />
    </div>

    <!-- Error -->
    <div v-else-if="error" class="emp-error">
      <div class="emp-error__icon"><iconify-icon icon="lucide:alert-circle" /></div>
      <h6>Could not load employees</h6>
      <p>{{ error }}</p>
      <button type="button" class="emp-mgmt__toolbar-btn emp-mgmt__toolbar-btn--primary" @click="loadEmployees(1)">Try again</button>
    </div>

    <!-- Empty -->
    <div v-else-if="!employees.length" class="emp-empty">
      <div class="emp-empty__icon"><iconify-icon icon="lucide:users" /></div>
      <h6>No employees found</h6>
      <p>{{ hasActiveFilters ? 'Try adjusting your search or filters.' : 'Add your first employee to get started.' }}</p>
      <button v-if="hasActiveFilters" type="button" class="emp-mgmt__toolbar-btn" @click="clearFilters">Clear filters</button>
      <button v-else type="button" class="emp-mgmt__toolbar-btn emp-mgmt__toolbar-btn--primary" @click="$emit('add')">Add Employee</button>
    </div>

    <!-- Table -->
    <EmployeesTable
      v-else
      :employees="employees"
      v-model:page="tablePage"
      v-model:per-page="tablePerPage"
      v-model:selected-ids="selectedIds"
      v-model:search-query="searchQuery"
      :total="total"
      :total-pages="lastPage"
      :start-entry="startEntry"
      :end-entry="endEntry"
      :pagination-items="paginationItems"
      @export="exportEmployees"
      @view="onView"
      @edit="onEdit"
      @assets="onAssets"
      @attendance="onAttendance"
      @leave="onLeave"
      @delete="onDelete"
    />

    <!-- Mobile FAB -->
    <button v-if="isMobile" type="button" class="emp-fab" aria-label="Add employee" @click="$emit('add')">
      <iconify-icon icon="lucide:plus" />
    </button>

    <!-- Mobile filter sheet -->
    <Teleport to="body">
      <div v-if="showFilters && isMobile" class="emp-filter-sheet" @click.self="showFilters = false">
        <div class="emp-filter-sheet__backdrop" @click="showFilters = false" />
        <div class="emp-filter-sheet__panel">
          <div class="emp-filter-sheet__handle" />
          <div class="emp-filter-sheet__head">
            <h6>Filter employees</h6>
            <button type="button" class="emp-mgmt__toolbar-btn" @click="showFilters = false">
              <iconify-icon icon="lucide:x" />
            </button>
          </div>
          <EmployeesFilterFields
            v-model="localFilters"
            :departments="departments"
            :designations="designations"
            :branches="branches"
            :managers="managers"
          />
          <div class="emp-filter-sheet__actions">
            <button type="button" class="emp-filter-sheet__clear" @click="onClearFilters">Clear all</button>
            <button type="button" class="emp-filter-sheet__apply" @click="onApplyFilters">Apply</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import Swal from 'sweetalert2'
import { useEmployeesManagement } from '@/composables/useEmployeesManagement'
import { MOBILE_LAYOUT_MAX_WIDTH } from '@/composables/useMobileNavigation'
import { exportCsv } from '@/services/leaveAttendanceApi'
import EmployeesTable from '@/components/hr/employees/EmployeesTable.vue'
import EmployeesFilterFields from '@/components/hr/employees/EmployeesFilterFields.vue'

defineProps({
  embedded: { type: Boolean, default: true },
})

const emit = defineEmits(['add', 'edit'])

const router = useRouter()
const toolbarRef = ref(null)
const showFilters = ref(false)
const isMobile = ref(false)
const localFilters = ref({})
const selectedIds = ref([])
const tablePage = ref(1)
const tablePerPage = ref(10)

const {
  employees,
  loading,
  error,
  searchQuery,
  filters,
  currentPage,
  lastPage,
  total,
  perPage,
  departments,
  designations,
  branches,
  managers,
  activeFilterCount,
  hasActiveFilters,
  statsCards,
  loadEmployees,
  goToPage,
  setPerPage,
  clearFilters,
  applyQuickFilter,
  removeEmployee,
} = useEmployeesManagement()

const startEntry = computed(() => (total.value ? (currentPage.value - 1) * perPage.value + 1 : 0))
const endEntry = computed(() => Math.min(currentPage.value * perPage.value, total.value))

const paginationItems = computed(() => {
  const pages = lastPage.value
  const current = currentPage.value
  if (pages <= 1) return [{ type: 'page', n: 1 }]
  if (pages <= 7) {
    return Array.from({ length: pages }, (_, i) => ({ type: 'page', n: i + 1 }))
  }
  const items = []
  const pushDots = () => {
    if (items.length && items[items.length - 1].type === 'dots') return
    items.push({ type: 'dots' })
  }
  items.push({ type: 'page', n: 1 })
  const left = Math.max(2, current - 1)
  const right = Math.min(pages - 1, current + 1)
  if (left > 2) pushDots()
  for (let i = left; i <= right; i += 1) items.push({ type: 'page', n: i })
  if (right < pages - 1) pushDots()
  items.push({ type: 'page', n: pages })
  return items
})

watch(tablePage, (page) => {
  if (page !== currentPage.value) goToPage(page)
})

watch(currentPage, (page) => {
  if (page !== tablePage.value) tablePage.value = page
  selectedIds.value = []
})

watch(tablePerPage, (value) => {
  if (value !== perPage.value) setPerPage(value)
})

watch(perPage, (value) => {
  if (value !== tablePerPage.value) tablePerPage.value = value
})

const quickChips = [
  { key: 'employment_status', value: 'active', label: 'Active' },
  { key: 'employment_status', value: 'on_leave', label: 'On Leave' },
  { key: 'status', value: 'active', label: 'Account Active' },
]

function syncMobile() {
  isMobile.value = window.innerWidth <= MOBILE_LAYOUT_MAX_WIDTH
}

function onApplyFilters() {
  filters.value = { ...localFilters.value }
  showFilters.value = false
  loadEmployees(1)
}

function onClearFilters() {
  localFilters.value = {
    department_id: '',
    designation_id: '',
    employment_status: '',
    status: '',
    salary_type: '',
    company_branch_id: '',
    parent_id: '',
    joining_date_from: '',
    joining_date_to: '',
  }
  clearFilters()
  showFilters.value = false
}

function onView(employee) {
  router.push(`/hr/employees/${employee.id}`)
}

function onEdit(employee) {
  emit('edit', employee)
}

function onAssets(employee) {
  router.push({ path: `/hr/employees/${employee.id}`, query: { tab: 'assets' } })
}

function onAttendance(employee) {
  router.push({ path: '/hr', query: { tab: 'Leave / Attendance', mode: 'attendance', employee: employee.id } })
}

function onLeave(employee) {
  router.push({ path: `/hr/employees/${employee.id}`, query: { tab: 'leave' } })
}

async function onDelete(employee) {
  const result = await Swal.fire({
    title: 'Delete employee?',
    text: `${employee.name} will be removed from the directory.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    confirmButtonText: 'Delete',
    cancelButtonText: 'Cancel',
  })
  if (!result.isConfirmed) return
  try {
    await removeEmployee(employee.id)
    Swal.fire({ icon: 'success', title: 'Deleted', timer: 1800, showConfirmButton: false, toast: true, position: 'top-end' })
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Delete failed', text: e?.response?.data?.message || e?.message })
  }
}

function exportEmployees() {
  exportCsv('employees.csv', employees.value, [
    { label: 'ID', value: (r) => r.employeeCode },
    { label: 'Name', value: (r) => r.name },
    { label: 'Designation', value: (r) => r.designation },
    { label: 'Email', value: (r) => r.email },
    { label: 'Department', value: (r) => r.department },
    { label: 'Phone', value: (r) => r.phone },
    { label: 'Branch', value: (r) => r.branch },
    { label: 'Manager', value: (r) => r.manager },
    { label: 'Status', value: (r) => r.employmentStatus },
  ])
}

onMounted(() => {
  localFilters.value = { ...filters.value }
  syncMobile()
  window.addEventListener('resize', syncMobile, { passive: true })
})

onUnmounted(() => {
  window.removeEventListener('resize', syncMobile)
})
</script>

<style>
@import '../../../../css/hr-employees.css';
</style>
