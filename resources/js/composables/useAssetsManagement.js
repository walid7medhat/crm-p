import { ref, computed, watch, onMounted } from 'vue'
import {
  fetchAssetStatistics,
  fetchAssets,
  fetchAssetTypes,
  resolveWarrantyStatus,
} from '@/services/assetsApi'
import { fetchDepartments, fetchBranches } from '@/services/employeesApi'
import { fetchAgentEmployees } from '@/services/hrApi'

const DEFAULT_FILTERS = () => ({
  asset_type_id: '',
  status: '',
  department_id: '',
  user_id: '',
  purchase_date_from: '',
  purchase_date_to: '',
  warranty_status: '',
})

export function useAssetsManagement() {
  const loading = ref(false)
  const loadingMore = ref(false)
  const error = ref('')
  const searchQuery = ref('')
  const filters = ref(DEFAULT_FILTERS())
  const statistics = ref({})
  const assets = ref([])
  const assetTypes = ref([])
  const departments = ref([])
  const branches = ref([])
  const employees = ref([])
  const currentPage = ref(1)
  const lastPage = ref(1)
  const total = ref(0)

  let searchTimer = null

  const activeFilterCount = computed(() =>
    Object.values(filters.value).filter((v) => v !== '' && v != null).length
  )

  const hasActiveFilters = computed(
    () => activeFilterCount.value > 0 || searchQuery.value.trim().length > 0
  )

  const kpiCards = computed(() => {
    const s = statistics.value
    return [
      { key: 'total', label: 'Total Assets', value: s.total_assets ?? 0, icon: 'lucide:package', bgColor: 'rgba(115, 62, 135, 0.12)', iconColor: '#733E87' },
      { key: 'assigned', label: 'Assigned Assets', value: s.assigned ?? 0, icon: 'lucide:user-check', bgColor: '#e0f2fe', iconColor: '#0284c7' },
      { key: 'available', label: 'Available Assets', value: s.available ?? 0, icon: 'lucide:box', bgColor: '#e8f8ed', iconColor: '#16a34a' },
      { key: 'maintenance', label: 'Under Maintenance', value: s.maintenance ?? 0, icon: 'lucide:wrench', bgColor: '#fff7ed', iconColor: '#ea580c' },
      { key: 'lost', label: 'Lost Assets', value: s.lost_assets ?? s.disposed ?? 0, icon: 'lucide:alert-triangle', bgColor: '#fef2f2', iconColor: '#dc2626' },
    ]
  })

  const filteredAssets = computed(() => {
    const q = searchQuery.value.trim().toLowerCase()
    const list = Array.isArray(assets.value) ? assets.value : []
    return list.filter((asset) => {
      if (q) {
        const haystack = [
          asset.name,
          asset.assetId,
          asset.serialNumber,
          asset.assignedEmployee,
          asset.category,
        ].map((t) => String(t || '').toLowerCase())
        if (!haystack.some((t) => t.includes(q))) return false
      }
      if (filters.value.warranty_status && asset.warrantyStatus?.key !== filters.value.warranty_status) {
        return false
      }
      return true
    })
  })

  const warrantyAlerts = computed(() => {
    const list = Array.isArray(assets.value) ? assets.value : []
    return list.filter((a) => ['expiring_soon', 'expired'].includes(a.warrantyStatus?.key))
  })

  async function loadOptions() {
    try {
      const [types, depts, branchList, users] = await Promise.all([
        fetchAssetTypes(),
        fetchDepartments(),
        fetchBranches(),
        fetchAgentEmployees(),
      ])
      assetTypes.value = types
      departments.value = depts
      branches.value = branchList
      employees.value = users
    } catch {
      assetTypes.value = []
      departments.value = []
      branches.value = []
      employees.value = []
    }
  }

  async function loadStatistics() {
    try {
      statistics.value = await fetchAssetStatistics()
    } catch {
      statistics.value = {}
    }
  }

  function buildParams(page = 1) {
    const params = { page, per_page: 50 }
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim()
    Object.entries(filters.value).forEach(([key, value]) => {
      if (value !== '' && value != null) params[key] = value
    })
    return params
  }

  async function loadAssets(reset = true) {
    if (reset) {
      loading.value = true
      currentPage.value = 1
    } else {
      loadingMore.value = true
    }
    error.value = ''
    try {
      const result = await fetchAssets(buildParams(currentPage.value))
      const items = Array.isArray(result?.items) ? result.items : []
      if (reset) {
        assets.value = items
      } else {
        assets.value = [...(Array.isArray(assets.value) ? assets.value : []), ...items]
      }
      currentPage.value = result?.currentPage ?? 1
      lastPage.value = result?.lastPage ?? 1
      total.value = result?.total ?? items.length
      await loadStatistics()
    } catch (e) {
      error.value = e?.response?.data?.message || e?.message || 'Failed to load assets'
      if (reset) assets.value = []
    } finally {
      loading.value = false
      loadingMore.value = false
    }
  }

  async function loadMore() {
    if (currentPage.value >= lastPage.value || loadingMore.value) return
    currentPage.value += 1
    await loadAssets(false)
  }

  function clearFilters() {
    filters.value = DEFAULT_FILTERS()
    searchQuery.value = ''
    loadAssets(true)
  }

  watch(searchQuery, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => loadAssets(true), 300)
  })

  watch(
    () => ({ ...filters.value }),
    () => {},
    { deep: true }
  )

  onMounted(async () => {
    await loadOptions()
    await loadAssets(true)
  })

  return {
    loading,
    loadingMore,
    error,
    searchQuery,
    filters,
    statistics,
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
    loadStatistics,
  }
}
