<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal-content">
      <!-- Loading State -->
      <div v-if="loading" class="loading-state">
        <iconify-icon icon="lucide:loader-2" class="loading-spinner"></iconify-icon>
        <p>Loading user statistics...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="error-state">
        <iconify-icon icon="lucide:alert-circle" class="error-icon"></iconify-icon>
        <p>{{ error }}</p>
        <button @click="fetchUserStatistics" class="retry-btn">Retry</button>
      </div>

      <!-- Content -->
      <template v-else>
        <div class="modal-header">
          <div class="user-info">
            <img :src="user.avatar || '/assets/images/user.png'" class="modal-user-avatar" @error="handleImageError">
            <div>
              <h5 class="modal-title">{{ user.name }}</h5>
              <span class="modal-subtitle">{{ user.email }}</span>
            </div>
          </div>
          <button class="modal-close" @click="$emit('close')">
            <iconify-icon icon="lucide:x"></iconify-icon>
          </button>
        </div>

        <div class="modal-body">
          <!-- Summary Stats -->
          <div class="summary-stats" v-if="statistics">
            <div class="summary-item">
              <span class="summary-label">Total Assigned</span>
              <span class="summary-value">{{ statistics.total_assigned_leads || 0 }}</span>
            </div>
            <div class="summary-item">
              <span class="summary-label">Created</span>
              <span class="summary-value">{{ statistics.total_created_leads || 0 }}</span>
            </div>
            <div class="summary-item">
              <span class="summary-label">Converted</span>
              <span class="summary-value">{{ statistics.converted_leads || 0 }}</span>
            </div>
            <div class="summary-item">
              <span class="summary-label">Active</span>
              <span class="summary-value">{{ statistics.active_leads || 0 }}</span>
            </div>
          </div>

          <!-- Stage Distribution -->
          <div class="stats-section" v-if="statistics?.leads_by_stage?.length">
            <h6 class="section-title">Leads by Stage</h6>
            <div class="stage-stats">
              <div v-for="stage in statistics.leads_by_stage" :key="stage.stage_id" class="stage-stat-item">
                <div class="stage-stat-header">
                  <span class="stage-stat-name">{{ stage.stage_name }}</span>
                  <span class="stage-stat-count">{{ stage.count }}</span>
                </div>
                <div class="stage-stat-bar">
                  <div 
                    class="stage-stat-fill"
                    :style="{ 
                      width: statistics.total_assigned_leads > 0 ? (stage.count / statistics.total_assigned_leads * 100) + '%' : '0%',
                      backgroundColor: stage.stage_color || '#FAA300'
                    }"
                  ></div>
                </div>
              </div>
            </div>
          </div>

          <!-- No Stages Message -->
          <div v-else-if="statistics && statistics.total_assigned_leads === 0" class="empty-state">
            <iconify-icon icon="lucide:inbox" class="empty-icon"></iconify-icon>
            <p>No leads assigned to this user</p>
          </div>

          <!-- Recent Leads -->
          <div class="recent-section" v-if="statistics?.recent_leads?.length">
            <h6 class="section-title">Recent Leads</h6>
            <div class="recent-leads-list">
              <div v-for="lead in statistics.recent_leads" :key="lead.id" class="recent-lead-item">
                <div class="recent-lead-info">
                  <span class="recent-lead-name">{{ lead.lead_name || lead.first_name || 'Unnamed' }}</span>
                  <span class="recent-lead-stage" :style="{ color: lead.stage?.color }">
                    {{ lead.stage?.name }}
                  </span>
                </div>
                <span class="recent-lead-date">{{ formatDate(lead.created_at) }}</span>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  user: {
    type: Object,
    required: true
  },
  filters: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['close'])

const loading = ref(true)
const error = ref(null)
const statistics = ref(null)

const handleImageError = (e) => {
  e.target.src = '/assets/images/user.png'
}

// جلب إحصائيات المستخدم من API
const fetchUserStatistics = async () => {
  loading.value = true
  error.value = null
  
  try {
    // بناء query params من الفلاتر
    const params = new URLSearchParams()
    if (props.filters.month) params.append('month', props.filters.month)
    if (props.filters.year) params.append('year', props.filters.year)
    if (props.filters.date_from) params.append('date_from', props.filters.date_from)
    if (props.filters.date_to) params.append('date_to', props.filters.date_to)
    
    const response = await axios.get(`/api/leads/reports/users/${props.user.id}`, { params })
    
    console.log('Full API Response:', response.data) // للتحقق من البيانات
    
    // التحقق من نجاح الاستجابة - المفتاح هو response.data.data.statistics
    if (response.data && response.data.status === true) {
      // الوصول إلى statistics من خلال response.data.data.statistics
      statistics.value = response.data.data.statistics
      console.log('Statistics loaded:', statistics.value) // للتحقق
    } else {
      throw new Error(response.data?.message || 'Failed to load statistics')
    }
  } catch (err) {
    console.error('Error fetching user statistics:', err)
    error.value = err.response?.data?.message || 'Failed to load user statistics'
  } finally {
    loading.value = false
  }
}

const formatDate = (date) => {
  if (!date) return ''
  const d = new Date(date)
  return d.toLocaleDateString('en-US', { 
    month: 'short', 
    day: 'numeric',
    year: 'numeric'
  })
}

// جلب البيانات عند فتح المودال
onMounted(() => {
  fetchUserStatistics()
})
</script>

<style scoped>
/* نفس الـ styles الموجودة */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1100;
}

.modal-content {
  background: #fff;
  border-radius: 12px;
  width: 500px;
  max-width: 90vw;
  max-height: 80vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.modal-header {
  padding: 20px;
  border-bottom: 1px solid #F1F5F9;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: sticky;
  top: 0;
  background: white;
  z-index: 10;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.modal-user-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  object-fit: cover;
}

.modal-title {
  font-size: 16px;
  font-weight: 600;
  color: #01062C;
  margin: 0;
}

.modal-subtitle {
  font-size: 12px;
  color: #64748B;
}

.modal-close {
  background: none;
  border: none;
  font-size: 20px;
  color: #64748B;
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  transition: all 0.2s;
}

.modal-close:hover {
  background: #F1F5F9;
  color: #01062C;
}

.modal-body {
  padding: 20px;
}

/* Loading State */
.loading-state,
.error-state,
.empty-state {
  padding: 40px 20px;
  text-align: center;
  color: #64748B;
}

.loading-spinner {
  font-size: 32px;
  animation: spin 1s linear infinite;
  color: #FAA300;
  margin-bottom: 12px;
}

.error-icon {
  font-size: 32px;
  color: #EF4444;
  margin-bottom: 12px;
}

.empty-icon {
  font-size: 32px;
  color: #94A3B8;
  margin-bottom: 12px;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.retry-btn {
  margin-top: 12px;
  padding: 8px 16px;
  background: #FAA300;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.retry-btn:hover {
  background: #e69500;
}

/* Summary Stats */
.summary-stats {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 24px;
  padding: 16px;
  background: #F8FAFC;
  border-radius: 10px;
}

.summary-item {
  text-align: center;
}

.summary-label {
  display: block;
  font-size: 11px;
  color: #64748B;
  margin-bottom: 4px;
}

.summary-value {
  display: block;
  font-size: 18px;
  font-weight: 600;
  color: #01062C;
}

.section-title {
  font-size: 14px;
  font-weight: 600;
  color: #01062C;
  margin-bottom: 12px;
}

.stats-section {
  margin-bottom: 24px;
}

.stage-stats {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.stage-stat-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.stage-stat-header {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
}

.stage-stat-name {
  color: #475569;
}

.stage-stat-count {
  font-weight: 600;
  color: #01062C;
}

.stage-stat-bar {
  height: 8px;
  background: #F1F5F9;
  border-radius: 4px;
  overflow: hidden;
}

.stage-stat-fill {
  height: 100%;
  transition: all 0.2s;
}

.recent-section {
  margin-top: 24px;
}

.recent-leads-list {
  max-height: 300px;
  overflow-y: auto;
  border: 1px solid #F1F5F9;
  border-radius: 8px;
}

.recent-lead-item {
  padding: 12px;
  border-bottom: 1px solid #F1F5F9;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.recent-lead-item:last-child {
  border-bottom: none;
}

.recent-lead-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.recent-lead-name {
  font-size: 13px;
  font-weight: 500;
  color: #01062C;
}

.recent-lead-stage {
  font-size: 12px;
  color: #64748B;
}

.recent-lead-date {
  font-size: 12px;
  color: #94A3B8;
}
</style>