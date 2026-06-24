import { ref, computed, watch, onMounted } from 'vue'
import {
  fetchEmployees,
  fetchEmployeeStatistics,
  deleteEmployee,
  fetchDepartments,
  fetchDesignations,
  fetchBranches,
  fetchManagers,
} from '@/services/employeesApi'

const DEFAULT_FILTERS = () => ({
  department_id: '',
  designation_id: '',
  employment_status: '',
  status: '',
  salary_type: '',
  company_branch_id: '',
  parent_id: '',
  joining_date_from: '',
  joining_date_to: '',
})

export function useEmployeesManagement() {
  const employees = ref([])
  const statistics = ref(null)
  const loading = ref(false)
  const searching = ref(false)
  const loadingMore = ref(false)
  const error = ref('')
  const searchQuery = ref('')
  const filters = ref(DEFAULT_FILTERS())
  const currentPage = ref(1)
  const lastPage = ref(1)
  const total = ref(0)
  const perPage = ref(10)

  const departments = ref([])
  const designations = ref([])
  const branches = ref([])
  const managers = ref([])
  const filterOptionsLoading = ref(false)

  let searchTimer = null

  const activeFilterCount = computed(() => {
    return Object.values(filters.value).filter((v) => v !== '' && v != null).length
  })

  const hasActiveFilters = computed(() => activeFilterCount.value > 0 || searchQuery.value.trim().length > 0)

  const statsCards = computed(() => {
    const s = statistics.value || {}
    return [
      {
        key: 'total',
        label: 'Total Employees',
        value: s.total_employees ?? total.value ?? 0,
        icon: 'lucide:users',
        bgColor: 'rgba(115, 62, 135, 0.12)',
        iconColor: '#733E87',
      },
      {
        key: 'active',
        label: 'Active Employees',
        value: s.active_employees ?? s.by_employment_status?.active ?? 0,
        icon: 'lucide:user-check',
        bgColor: '#e8f8ed',
        iconColor: '#16a34a',
      },
      {
        key: 'new',
        label: 'New Employees',
        value: s.new_employees_30d ?? 0,
        icon: 'lucide:user-round-plus',
        bgColor: '#f4e8ff',
        iconColor: '#9333ea',
      },
      {
        key: 'leave',
        label: 'On Leave',
        value: s.employees_on_leave ?? s.by_employment_status?.on_leave ?? 0,
        icon: 'lucide:calendar-off',
        bgColor: '#fff7ed',
        iconColor: '#ea580c',
      },
      {
        key: 'departments',
        label: 'Departments',
        value: s.departments_count ?? 0,
        icon: 'lucide:building-2',
        bgColor: 'rgba(11, 7, 54, 0.08)',
        iconColor: '#0B0736',
      },
    ]
  })

  function buildParams(page = 1) {
    const params = { page, per_page: perPage.value }
    const q = searchQuery.value.trim()
    if (q) params.search = q
    Object.entries(filters.value).forEach(([key, value]) => {
      if (value !== '' && value != null) params[key] = value
    })
    return params
  }

  async function loadStatistics() {
    try {
      statistics.value = await fetchEmployeeStatistics()
    } catch {
      statistics.value = null
    }
  }

  async function loadFilterOptions() {
    filterOptionsLoading.value = true
    try {
      const [depts, desigs, brs, mgrs] = await Promise.all([
        fetchDepartments(),
        fetchDesignations(),
        fetchBranches(),
        fetchManagers(),
      ])
      departments.value = depts
      designations.value = desigs
      branches.value = brs
      managers.value = mgrs
    } catch {
      departments.value = []
      designations.value = []
      branches.value = []
      managers.value = []
    } finally {
      filterOptionsLoading.value = false
    }
  }

  async function loadEmployees(page = currentPage.value, options = {}) {
    const { silent = false } = options
    if (silent) {
      searching.value = true
    } else {
      loading.value = true
    }
    error.value = ''

    try {
      const result = await fetchEmployees(buildParams(page))
      employees.value = result.items
      currentPage.value = result.currentPage
      lastPage.value = result.lastPage
      total.value = result.total
    } catch (e) {
      error.value = e?.response?.data?.message || e?.message || 'Failed to load employees'
      if (!silent) employees.value = []
    } finally {
      loading.value = false
      searching.value = false
      loadingMore.value = false
    }
  }

  function goToPage(page) {
    const next = Math.max(1, Math.min(page, lastPage.value || 1))
    if (next === currentPage.value && employees.value.length) return
    currentPage.value = next
    return loadEmployees(next)
  }

  function setPerPage(value) {
    perPage.value = value
    currentPage.value = 1
    return loadEmployees(1)
  }

  function clearFilters() {
    filters.value = DEFAULT_FILTERS()
    searchQuery.value = ''
    loadEmployees(1)
  }

  function applyQuickFilter(key, value) {
    if (filters.value[key] === value) {
      filters.value[key] = ''
    } else {
      filters.value[key] = value
    }
    loadEmployees(1)
  }

  async function removeEmployee(id) {
    await deleteEmployee(id)
    employees.value = employees.value.filter((e) => e.id !== id)
    total.value = Math.max(0, total.value - 1)
    await loadStatistics()
  }

  watch(searchQuery, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
      currentPage.value = 1
      loadEmployees(1, { silent: employees.value.length > 0 })
    }, 500)
  })

  onMounted(async () => {
    await Promise.all([loadStatistics(), loadFilterOptions(), loadEmployees(1)])
  })

  return {
    employees,
    statistics,
    loading,
    searching,
    loadingMore,
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
    filterOptionsLoading,
    activeFilterCount,
    hasActiveFilters,
    statsCards,
    loadEmployees,
    goToPage,
    setPerPage,
    clearFilters,
    applyQuickFilter,
    removeEmployee,
    loadStatistics,
  }
}
