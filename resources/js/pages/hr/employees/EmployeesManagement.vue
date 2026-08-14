<template>
  <div class="emp-mgmt" :class="{ 'emp-mgmt--embedded': embedded }">
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

    <div v-if="loading" class="emp-directory-table emp-directory-table--loading">
      <div v-for="n in 6" :key="n" class="emp-directory-table__skeleton" />
    </div>

    <div v-else-if="error" class="emp-error">
      <div class="emp-error__icon"><iconify-icon icon="lucide:alert-circle" /></div>
      <h6>Could not load employees</h6>
      <p>{{ error }}</p>
      <button type="button" class="emp-mgmt__toolbar-btn emp-mgmt__toolbar-btn--primary" @click="loadEmployees(1)">Try again</button>
    </div>

    <div v-else-if="!employees.length && !hasActiveFilters && !searching" class="emp-empty">
      <div class="emp-empty__icon"><iconify-icon icon="lucide:users" /></div>
      <h6>No employees found</h6>
      <p>Add your first employee from the header to get started.</p>
    </div>

    <EmployeesTable
      v-else
      :employees="employees"
      v-model:page="tablePage"
      v-model:per-page="tablePerPage"
      v-model:selected-ids="selectedIds"
      v-model:search-query="searchQuery"
      :searching="searching"
      :has-active-filters="hasActiveFilters"
      :filters="filters"
      :departments="departments"
      :designations="designations"
      :total="total"
      :total-pages="lastPage"
      :start-entry="startEntry"
      :end-entry="endEntry"
      :pagination-items="paginationItems"
      @export="exportEmployees"
      @apply-filters="onApplyFilters"
      @clear-filters="clearFilters"
      @view="onView"
      @edit="onEdit"
      @attendance="onAttendance"
      @leave="onLeave"
      @delete="onDelete"
    />

    <button v-if="isMobile" type="button" class="emp-fab" aria-label="Add employee" @click="$emit('add')">
      <iconify-icon icon="lucide:plus" />
    </button>
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

defineProps({
  embedded: { type: Boolean, default: true },
})

const emit = defineEmits(['add', 'edit'])

const router = useRouter()
const isMobile = ref(false)
const selectedIds = ref([])
const tablePage = ref(1)
const tablePerPage = ref(10)

const {
  employees,
  loading,
  searching,
  error,
  searchQuery,
  filters,
  currentPage,
  lastPage,
  total,
  perPage,
  departments,
  designations,
  hasActiveFilters,
  statsCards,
  loadEmployees,
  goToPage,
  setPerPage,
  clearFilters,
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

function syncMobile() {
  isMobile.value = window.innerWidth <= MOBILE_LAYOUT_MAX_WIDTH
}

function onApplyFilters(payload) {
  if (payload.name != null) searchQuery.value = payload.name
  filters.value = {
    ...filters.value,
    department_id: payload.department_id || '',
    designation_id: payload.designation_id || '',
    joining_date: payload.joining_date || '',
    joining_date_from: '',
    joining_date_to: '',
    visa_validity: payload.visa_validity || '',
    status: payload.status || '',
    employment_status: '',
  }
  loadEmployees(1)
}

function onView(employee) {
  router.push(`/hr/employees/${employee.id}`)
}

function onEdit(employee) {
  emit('edit', employee)
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
