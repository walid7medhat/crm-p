<template>
  <div class="report-container">
    <!-- Header with Filters -->
    <div class="report-header bg-white p-4 radius-12 shadow-sm mb-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="report-title">Users Performance Report</h4>
        <button class="btn-export" @click="exportReport">
          <iconify-icon icon="lucide:download" class="me-2"></iconify-icon>
          Export Report
        </button>
      </div>

      <!-- Filters -->
      <div class="filters-section d-flex gap-3 align-items-end">
        <div class="filter-group">
          <label class="filter-label">Filter by Month</label>
          <select v-model="filters.month" class="filter-select" @change="fetchReport">
            <option value="">All Months</option>
            <option v-for="month in months" :key="month.value" :value="month.value">
              {{ month.label }}
            </option>
          </select>
        </div>

        <div class="filter-group">
          <label class="filter-label">Filter by Year</label>
          <select v-model="filters.year" class="filter-select" @change="fetchReport">
            <option value="">All Years</option>
            <option v-for="year in years" :key="year.value" :value="year.value">
              {{ year.label }}
            </option>
          </select>
        </div>

        <div class="filter-group">
          <label class="filter-label">Custom Date Range</label>
          <div class="d-flex gap-2">
            <input 
              type="date" 
              v-model="filters.date_from" 
              class="filter-input"
              placeholder="From"
            >
            <input 
              type="date" 
              v-model="filters.date_to" 
              class="filter-input"
              placeholder="To"
            >
          </div>
        </div>

        <button class="btn-apply" @click="fetchReport">
          <iconify-icon icon="lucide:search" class="me-2"></iconify-icon>
          Apply
        </button>

        <button class="btn-reset-filter" @click="resetFilters">
          <iconify-icon icon="lucide:x" class="me-2"></iconify-icon>
          Reset
        </button>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-cards row g-3 mb-4" v-if="summary">
      <div class="col-md-3">
        <div class="summary-card bg-white p-3 radius-12 shadow-sm">
          <div class="card-icon bg-primary-light">
            <iconify-icon icon="lucide:users" class="text-primary"></iconify-icon>
          </div>
          <div class="card-content">
            <span class="card-label">Total Users</span>
            <span class="card-value">{{ usersReport.length }}</span>
          </div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="summary-card bg-white p-3 radius-12 shadow-sm">
          <div class="card-icon bg-success-light">
            <iconify-icon icon="lucide:trending-up" class="text-success"></iconify-icon>
          </div>
          <div class="card-content">
            <span class="card-label">Total Leads</span>
            <span class="card-value">{{ summary.total_leads }}</span>
          </div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="summary-card bg-white p-3 radius-12 shadow-sm">
          <div class="card-icon bg-warning-light">
            <iconify-icon icon="lucide:check-circle" class="text-warning"></iconify-icon>
          </div>
          <div class="card-content">
            <span class="card-label">Converted Leads</span>
            <span class="card-value">{{ summary.total_converted_leads }}</span>
          </div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="summary-card bg-white p-3 radius-12 shadow-sm">
          <div class="card-icon bg-info-light">
            <iconify-icon icon="lucide:percent" class="text-info"></iconify-icon>
          </div>
          <div class="card-content">
            <span class="card-label">Conversion Rate</span>
            <span class="card-value">{{ summary.conversion_rate }}%</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Users Report Table -->
    <div class="report-table bg-white p-4 radius-12 shadow-sm">
      <div class="table-header d-flex justify-content-between align-items-center mb-3">
        <h5 class="table-title">Users Performance Details</h5>
        <div class="table-search">
          <input 
            type="text" 
            v-model="searchQuery" 
            class="search-input"
            placeholder="Search user..."
          >
          <iconify-icon icon="lucide:search" class="search-icon"></iconify-icon>
        </div>
      </div>

      <div class="table-responsive">
        <table class="report-data-table">
          <thead>
            <tr>
              <th>User</th>
              <th>Role</th>
              <th>Assigned Leads</th>
              <th>Created Leads</th>
              <th>Active Leads</th>
              <th>Converted</th>
              <th>Conversion Rate</th>
              <th>By Stage</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in filteredUsers" :key="item.user.id">
              <td>
                <div class="user-cell">
                  <img 
                    :src="item.user.avatar || '/default-avatar.png'" 
                    class="user-avatar"
                      @error="handleImageError"
                  >
                  <div class="user-info">
                    <div class="user-name">{{ item.user.name }}</div>
                    <div class="user-email">{{ item.user.email }}</div>
                  </div>
                </div>
              </td>
              <td>
                <span class="role-badge">{{ item.user.role_name || 'No Role' }}</span>
              </td>
              <td class="text-center">{{ item.statistics.total_assigned_leads }}</td>
              <td class="text-center">{{ item.statistics.total_created_leads }}</td>
              <td class="text-center">{{ item.statistics.active_leads }}</td>
              <td class="text-center">{{ item.statistics.converted_leads }}</td>
              <td class="text-center">
                {{ calculateConversionRate(item.statistics) }}%
              </td>
              <td>
                <div class="stage-progress">
                  <div 
                    v-for="stage in item.statistics.leads_by_stage" 
                    :key="stage.stage_id"
                    class="stage-bar"
                    :style="{ 
                      width: (stage.count / item.statistics.total_assigned_leads * 100) + '%',
                      backgroundColor: stage.stage_color || '#FAA300'
                    }"
                    :title="`${stage.stage_name}: ${stage.count}`"
                  ></div>
                </div>
              </td>
              <td>
                <button class="btn-view" @click="viewUserDetails(item.user)">
                  <iconify-icon icon="lucide:eye"></iconify-icon>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="text-center p-5">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
      </div>
    </div>

    <!-- Leads by Stage Chart (Overall) -->
    <div class="chart-section bg-white p-4 radius-12 shadow-sm mt-4" v-if="summary">
      <h5 class="chart-title mb-3">Leads Distribution by Stage</h5>
      <div class="stage-chart">
        <div 
          v-for="(stage, index) in summary.leads_by_stage_overall" 
          :key="index"
          class="stage-chart-item"
        >
          <div class="stage-label">{{ stage.stage_name }}</div>
          <div class="stage-bar-container">
            <div 
              class="stage-bar-fill"
              :style="{ 
                width: (stage.count / summary.total_leads * 100) + '%',
                backgroundColor: stage.stage_color
              }"
            ></div>
            <span class="stage-count">{{ stage.count }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- User Details Modal -->
    <UserDetailsModal
      v-if="showUserModal"
      :user="selectedUser"
      :filters="filters"
      @close="showUserModal = false"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'
import UserDetailsModal from './UserDetailsModal.vue'

const loading = ref(false)
const usersReport = ref([])
const summary = ref(null)
const months = ref([])
const years = ref([])
const searchQuery = ref('')
const showUserModal = ref(false)
const selectedUser = ref(null)

const filters = ref({
  month: '',
  year: '',
  date_from: '',
  date_to: ''
})

// Filtered users based on search
const filteredUsers = computed(() => {
  if (!searchQuery.value) return usersReport.value
  
  return usersReport.value.filter(item => 
    item.user.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
    item.user.email.toLowerCase().includes(searchQuery.value.toLowerCase())
  )
})
const handleImageError = (e) => {
  e.target.src = '/assets/images/user.png' // صورة افتراضية احتياطية
}
// Fetch report data
const fetchReport = async () => {
  loading.value = true
  try {
    const params = {}
    
    if (filters.value.month) params.month = filters.value.month
    if (filters.value.year) params.year = filters.value.year
    if (filters.value.date_from) params.date_from = filters.value.date_from
    if (filters.value.date_to) params.date_to = filters.value.date_to
    
    const response = await api.get('/leads/reports/users', { params })
    usersReport.value = response.data.data.users_report || []
    summary.value = response.data.data.summary
  } catch (error) {
    console.error('Error fetching report:', error)
    window.$showNotification?.(error.response?.data?.message || 'Failed to load report', 'error')
  } finally {
    loading.value = false
  }
}

// Fetch filter options
const fetchFilterOptions = async () => {
  try {
    const [monthsRes, yearsRes] = await Promise.all([
      api.get('/leads/reports/months'),
      api.get('/leads/reports/years')
    ])
    months.value = monthsRes.data || []
    years.value = yearsRes.data || []
  } catch (error) {
    console.error('Error fetching filter options:', error)
  }
}

// Calculate conversion rate
const calculateConversionRate = (statistics) => {
  if (!statistics.total_assigned_leads) return 0
  return ((statistics.converted_leads / statistics.total_assigned_leads) * 100).toFixed(1)
}

// Get stage color
const getStageColor = (index) => {
  const colors = ['#FAA300', '#3B82F6', '#10B981', '#EF4444', '#8B5CF6', '#EC4899']
  return colors[index % colors.length]
}

// Reset filters
const resetFilters = () => {
  filters.value = {
    month: '',
    year: '',
    date_from: '',
    date_to: ''
  }
  fetchReport()
}

// View user details
const viewUserDetails = (user) => {
  selectedUser.value = user
  showUserModal.value = true
}

// Export report
const exportReport = () => {
  // Create CSV content
  let csv = 'User,Role,Assigned Leads,Created Leads,Active Leads,Converted,Conversion Rate\n'
  
  usersReport.value.forEach(item => {
    const conversionRate = calculateConversionRate(item.statistics)
    csv += `${item.user.name},${item.user.roles?.[0]?.name || 'No Role'},${item.statistics.total_assigned_leads},${item.statistics.total_created_leads},${item.statistics.active_leads},${item.statistics.converted_leads},${conversionRate}%\n`
  })
  
  // Download CSV
  const blob = new Blob([csv], { type: 'text/csv' })
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `user_report_${new Date().toISOString().split('T')[0]}.csv`
  a.click()
}

onMounted(() => {
  fetchFilterOptions()
  fetchReport()
})
</script>

<style scoped>
.report-container {
  padding: 24px;
}

.report-title {
  font-size: 20px !important;
  font-weight: 600;
  color: #01062C;
  margin: 0;
}

.btn-export {
  background: #01062C;
  border: none;
  border-radius: 8px;
  padding: 8px 16px;
  color: #fff;
  font-size: 14px !important;
  font-weight: 500 !important;
  display: flex;
  align-items: center;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-export:hover {
  background: #060a2b;
}

.filters-section {
  background: #F8FAFC;
  padding: 16px;
  border-radius: 8px;
  flex-wrap: wrap;
}

.filter-group {
  min-width: 150px;
}

.filter-label {
  display: block;
  font-size: 12px !important;
  font-weight: 500  !important;
  color: #64748B;
  margin-bottom: 4px;
}

.filter-select,
.filter-input {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #E2E8F0;
  border-radius: 6px;
  font-size: 13px  !important;
  color: #1E293B;
  background: #fff;
}

.filter-input {
  width: 140px;
}

.btn-apply {
  background: #01062C;
  border: none;
  border-radius: 6px;
  padding: 8px 16px;
  color: #fff;
  font-size: 13px  !important;
  font-weight: 500  !important;
  cursor: pointer;
  height: 36px;
}

.btn-reset-filter {
  background: #F1F5F9;
  border: 1px solid #E2E8F0;
  border-radius: 6px;
  padding: 8px 16px;
  color: #64748B;
  font-size: 13px !important;
  font-weight: 500 !important;
  cursor: pointer;
  height: 36px;
}

.summary-card {
  display: flex;
  align-items: center;
  gap: 12px;
  height: 100%;
}

.card-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px !important;
}

.bg-primary-light {
  background: rgba(1, 6, 44, 0.1);
}

.bg-success-light {
  background: rgba(16, 185, 129, 0.1);
}

.bg-warning-light {
  background: rgba(250, 163, 0, 0.1);
}

.bg-info-light {
  background: rgba(59, 130, 246, 0.1);
}

.card-content {
  display: flex;
  flex-direction: column;
}

.card-label {
  font-size: 12px !important;
  color: #64748B;
}

.card-value {
  font-size: 24px !important;
  font-weight: 600 !important;
  color: #01062C;
}

.text-primary { color: #01062C; }
.text-success { color: #10B981; }
.text-warning { color: #FAA300; }
.text-info { color: #3B82F6; }

.table-header {
  margin-bottom: 20px;
}

.table-title {
  font-size: 16px !important;
  font-weight: 600;
  color: #01062C;
  margin: 0;
}

.table-search {
  position: relative;
}

.search-input {
  padding: 8px 12px 8px 36px;
  border: 1px solid #E2E8F0;
  border-radius: 8px;
  font-size: 13px !important;
  width: 250px;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #94A3B8;
  font-size: 16px !important;
}

.report-data-table {
  width: 100%;
  border-collapse: collapse;
}

.report-data-table th {
  background: #F8FAFC;
  padding: 12px 16px;
  font-size: 13px !important;
  font-weight: 600;
  color: #475569;
  text-align: left;
  border-bottom: 1px solid #E2E8F0;
}

.report-data-table td {
  padding: 16px;
  font-size: 13px !important;
  color: #1E293B;
  border-bottom: 1px solid #F1F5F9;
}

.user-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
}

.user-info {
  display: flex;
  flex-direction: column;
}

.user-name {
  font-weight: 600;
  color: #01062C;
}

.user-email {
  font-size: 12px !important;
  color: #64748B;
}

.role-badge {
  display: inline-block;
  padding: 4px 8px;
  background: #F1F5F9;
  border-radius: 4px;
  font-size: 12px !important;
  color: #475569;
}

.stage-progress {
  display: flex;
  height: 8px;
  background: #F1F5F9;
  border-radius: 4px;
  overflow: hidden;
  min-width: 120px;
}

.stage-bar {
  height: 100%;
  transition: all 0.2s;
}

.btn-view {
  background: none;
  border: 1px solid #E2E8F0;
  border-radius: 6px;
  padding: 6px 10px;
  color: #64748B;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-view:hover {
  background: #F1F5F9;
  color: #01062C;
}

.chart-section {
  margin-top: 24px;
}

.chart-title {
  font-size: 16px !important;
  font-weight: 600;
  color: #01062C;
}

.stage-chart {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.stage-chart-item {
  display: flex;
  align-items: center;
  gap: 12px;
}

.stage-label {
  width: 150px;
  font-size: 13px !important;
  font-weight: 500 !important;
  color: #475569;
}

.stage-bar-container {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 12px;
  background: #F1F5F9;
  border-radius: 4px;
  height: 32px;
  position: relative;
}

.stage-bar-fill {
  height: 100%;
  border-radius: 4px;
  transition: all 0.2s;
}

.stage-count {
  position: absolute;
  right: 12px;
  font-size: 12px !important;
  font-weight: 500;
  color: #1E293B;
}

/* Responsive */
@media (max-width: 768px) {
  .filters-section {
    flex-direction: column;
    align-items: stretch;
  }
  
  .filter-group {
    width: 100%;
  }
  
  .btn-apply,
  .btn-reset-filter {
    width: 100%;
  }
}
</style>