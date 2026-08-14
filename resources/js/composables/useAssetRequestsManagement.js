import { computed, onMounted, ref, watch } from 'vue'
import {
  fetchAssetRequests,
  fetchAssetTypes,
} from '@/services/assetsApi'
import { fetchDepartments, fetchBranches, fetchEmployees } from '@/services/employeesApi'
import { fetchAgentEmployees } from '@/services/hrApi'

const DEFAULT_FILTERS = () => ({
  status: '',
  department_id: '',
  user_id: '',
  asset_item: '',
  applied_date: '',
})

export const DEFAULT_ASSET_ITEMS = [
  'Dell Laptop',
  'Company Badge',
  'HP Laptop',
  'Laptop Charger Dell',
  'Company SIM',
  'Company Phone',
  'Car',
  'Desk Top',
]

export const DEFAULT_COMPANIES = ['OIA PROPERTIES L.L.C']

export function useAssetRequestsManagement() {
  const loading = ref(false)
  const error = ref('')
  const requests = ref([])
  const searchQuery = ref('')
  const filters = ref(DEFAULT_FILTERS())
  const departments = ref([])
  const branches = ref([])
  const employees = ref([])
  const assetTypes = ref([])
  const tablePage = ref(1)
  const perPage = ref(10)
  const selectedIds = ref([])

  let searchTimer = null

  const hasActiveFilters = computed(
    () => Object.values(filters.value).some((v) => v !== '' && v != null) || searchQuery.value.trim().length > 0
  )

  function matchesSearch(texts) {
    const q = searchQuery.value.trim().toLowerCase()
    if (!q) return true
    return texts.some((t) => String(t || '').toLowerCase().includes(q))
  }

  const filteredRequests = computed(() =>
    requests.value.filter((row) => {
      if (!matchesSearch([row.userName, row.employeeCode, row.department, row.assetItem, row.companyName, row.branch])) return false
      if (filters.value.status && row.status !== filters.value.status) return false
      if (filters.value.department_id && String(row.departmentId) !== String(filters.value.department_id)) return false
      if (filters.value.user_id && String(row.userId) !== String(filters.value.user_id)) return false
      if (filters.value.asset_item && !String(row.assetItem || '').toLowerCase().includes(String(filters.value.asset_item).toLowerCase())) return false
      if (filters.value.applied_date) {
        const applied = String(row.appliedAt || '').slice(0, 10)
        if (applied !== String(filters.value.applied_date).slice(0, 10)) return false
      }
      return true
    })
  )

  const totalPages = computed(() => Math.max(1, Math.ceil(filteredRequests.value.length / perPage.value)))
  const pagedRequests = computed(() => {
    const start = (tablePage.value - 1) * perPage.value
    return filteredRequests.value.slice(start, start + perPage.value)
  })
  const startEntry = computed(() => (filteredRequests.value.length ? (tablePage.value - 1) * perPage.value + 1 : 0))
  const endEntry = computed(() => Math.min(tablePage.value * perPage.value, filteredRequests.value.length))
  const paginationItems = computed(() => {
    const total = totalPages.value
    const current = tablePage.value
    if (total <= 1) return [{ type: 'page', n: 1 }]
    if (total <= 7) return Array.from({ length: total }, (_, i) => ({ type: 'page', n: i + 1 }))
    const items = []
    const pushDots = () => {
      if (items.length && items[items.length - 1].type === 'dots') return
      items.push({ type: 'dots' })
    }
    items.push({ type: 'page', n: 1 })
    const left = Math.max(2, current - 1)
    const right = Math.min(total - 1, current + 1)
    if (left > 2) pushDots()
    for (let i = left; i <= right; i += 1) items.push({ type: 'page', n: i })
    if (right < total - 1) pushDots()
    items.push({ type: 'page', n: total })
    return items
  })

  const assetItemOptions = computed(() => {
    const fromTypes = assetTypes.value.map((t) => t.name).filter(Boolean)
    const fromRows = requests.value.map((r) => r.assetItem).filter((name) => name && name !== '—')
    return [...new Set([...DEFAULT_ASSET_ITEMS, ...fromTypes, ...fromRows])]
  })

  const companyOptions = computed(() => {
    const fromRows = requests.value.map((r) => r.companyName).filter((name) => name && name !== '—')
    return [...new Set([...DEFAULT_COMPANIES, ...fromRows])]
  })

  function applyFilters(payload = {}) {
    searchQuery.value = payload.search ?? payload.name ?? searchQuery.value
    filters.value = {
      ...DEFAULT_FILTERS(),
      status: payload.status || '',
      department_id: payload.department_id || '',
      user_id: payload.user_id || '',
      asset_item: payload.asset_item || '',
      applied_date: payload.applied_date || '',
    }
    tablePage.value = 1
  }

  function clearFilters() {
    searchQuery.value = ''
    filters.value = DEFAULT_FILTERS()
    tablePage.value = 1
  }

  async function loadOptions() {
    const [depts, brs, emps, types, agents] = await Promise.all([
      fetchDepartments().catch(() => []),
      fetchBranches().catch(() => []),
      fetchEmployees({ per_page: 500 }).catch(() => ({ items: [] })),
      fetchAssetTypes().catch(() => []),
      fetchAgentEmployees().catch(() => []),
    ])
    departments.value = Array.isArray(depts) ? depts : []
    branches.value = Array.isArray(brs) ? brs : []
    assetTypes.value = Array.isArray(types) ? types : []

    const mappedAgents = (Array.isArray(agents) ? agents : []).map((row) => ({
      id: row.id,
      name: row.name || '—',
      avatar: row.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(row.name || 'E')}&background=0b0736&color=fff`,
      employeeCode: row.employee_code || row.id,
      department: row.department || '—',
      departmentId: row.department_id || null,
      branch: row.branch || row.office_name || '—',
      branchId: row.branch_id || row.company_branch_id || null,
      role: row.role_name || row.role?.name || row.position || '—',
      designation: row.position || row.role_name || '—',
    }))
    const byId = new Map()
    mappedAgents.forEach((row) => byId.set(Number(row.id), row))
    ;(emps?.items || []).forEach((row) => byId.set(Number(row.id), row))
    employees.value = [...byId.values()]
  }

  async function loadAll() {
    loading.value = true
    error.value = ''
    try {
      const page = await fetchAssetRequests({ per_page: 200 })
      requests.value = page.items
      if (tablePage.value > totalPages.value) tablePage.value = 1
    } catch (e) {
      error.value = e?.response?.data?.message || e?.message || 'Failed to load asset requests'
    } finally {
      loading.value = false
    }
  }

  watch(perPage, () => {
    tablePage.value = 1
  })

  watch(searchQuery, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
      tablePage.value = 1
    }, 200)
  })

  onMounted(async () => {
    await loadOptions()
    await loadAll()
  })

  return {
    loading,
    error,
    requests,
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
  }
}
