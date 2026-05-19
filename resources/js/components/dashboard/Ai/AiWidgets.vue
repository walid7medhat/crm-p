<template>
  <div class="row row-cols-xxxl-4 row-cols-lg-4 row-cols-sm-2 row-cols-1">
    <!-- Total Agents Card -->
    <router-link v-if="hasPermission('users-list')" :to="'/users'" class="col-6 col-xxl-3 col-xl-3 col-lg-3 text-decoration-none">
      <div class="card shadow-none border bg-gradient-start-1 h-100 hover-card">
        <div class="card-body p-20">
          <div class="d-flex flex-wrap align-items-top justify-content-between gap-3">
            <div class="order-2 order-sm-1">
              <p class="fw-medium text-primary-light mb-1">Total Agents</p>
              <h6 class="mb-0">{{ formatNumber(stats.total_agents) }}</h6>
            </div>
            <div class="w-50-px h-50-px bg-cyan rounded-circle d-flex justify-content-center align-items-center primary-background order-1 order-sm-2">
              <iconify-icon icon="flowbite:users-group-outline" class="text-white text-2xl mb-0"></iconify-icon>
            </div>
          </div>
        </div>
      </div>
    </router-link>

    <!-- Total Listings Card -->
    <router-link v-if="hasPermission('listings-list')" :to="getListingsRoute()" class="col-6 col-xxl-3 col-xl-3 col-lg-3 text-decoration-none">
      <div class="card shadow-none border bg-gradient-start-2 h-100 hover-card">
        <div class="card-body p-20">
          <div class="d-flex flex-wrap align-items-top justify-content-between gap-3">
            <div class="order-2 order-sm-1">
              <p class="fw-medium text-primary-light mb-1">Total Listings</p>
              <h6 class="mb-0">{{ formatNumber(stats.total_listings) }}</h6>
            </div>
            <div class="w-50-px h-50-px bg-purple rounded-circle d-flex justify-content-center align-items-center primary-background order-1 order-sm-2">
              <iconify-icon icon="mingcute:storage-line" class="text-white text-2xl mb-0"></iconify-icon>
            </div>
          </div>
        </div>
      </div>
    </router-link>

    <!-- Admin/Super Admin: Requests Card -->
      <router-link :to="'/my-requests'" v-if="hasPermission('listings-list') && isAdminUser" class="col-6 col-xxl-3 col-xl-3 col-lg-3 text-decoration-none">
        <div class="card shadow-none border bg-gradient-start-2 h-100 hover-card">
          <div class="card-body p-20">
            <div class="d-flex flex-wrap align-items-top justify-content-between gap-3">
              <div class="order-2 order-sm-1">
                <p class="fw-medium text-primary-light mb-1">Requests</p>
                <h6 class="mb-0">{{ formatNumber( stats.my_orders) }}</h6>
              </div>
              <div class="w-50-px h-50-px bg-purple rounded-circle d-flex justify-content-center align-items-center primary-background order-1 order-sm-2">
                <iconify-icon icon="lucide:shopping-cart" class="text-white text-xl mb-0"></iconify-icon>
              </div>
            </div>
          </div>
        </div>
      </router-link>

    <!-- Regular Users: Inbound/Outbound Card -->
    <div v-if="hasPermission('listings-list') && !isAdminUser" class="col-6 col-xxl-3 col-xl-3 col-lg-3">
      <div class="card shadow-none border bg-gradient-start-2 h-100 hover-card">
        <div class="card-body p-20">
          <div class="d-flex flex-wrap align-items-top justify-content-between gap-3">
            <div class="order-2 order-sm-1 inbound-outbound-text">
              <p class="fw-medium text-primary-light mb-1">Requests</p>
              <div class="d-flex flex-column gap-2">
                <!-- Inbound Link -->
                <router-link :to="'/my-requests'" class="text-decoration-none d-flex justify-content-between align-items-center">
                  <span class="fw-medium text-primary-light">
                    Inbound: 
                    <span class="fw-bold">{{ formatNumber(stats.my_requests) }}</span>
                  </span>
                </router-link>
                
                <!-- Outbound Link -->
                <router-link :to="'/my-orders'" class="text-decoration-none d-flex justify-content-between align-items-center">
                  <span class="fw-medium text-primary-light">
                    Outbound: 
                    <span class="fw-bold">{{ formatNumber(stats.my_orders) }}</span>
                  </span>
                </router-link>
              </div>
            </div>
            
            <div class="w-50-px h-50-px bg-purple rounded-circle d-flex justify-content-center align-items-center primary-background order-1 order-sm-2">
              <iconify-icon icon="lucide:shopping-cart" class="text-white text-xl mb-0"></iconify-icon>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Owners Card -->
    <router-link v-if="hasPermission('owners-list')" :to="'/owners'" class="col-6 col-xxl-3 col-xl-3 col-lg-3 text-decoration-none">
      <div class="card shadow-none border bg-gradient-start-5 h-100 hover-card">
        <div class="card-body p-20">
          <div class="d-flex flex-wrap align-items-top justify-content-between gap-3">
            <div class="order-2 order-sm-1">
              <p class="fw-medium text-primary-light mb-1">Owners</p>
              <h6 class="mb-0">{{ formatNumber(stats.owners) }}</h6>
            </div>
            <div class="w-50-px h-50-px bg-success-main rounded-circle d-flex justify-content-center align-items-center primary-background order-1 order-sm-2">
              <iconify-icon icon="lucide:users" class="text-white text-2xl mb-0"></iconify-icon>
            </div>
          </div>
        </div>
      </div>
    </router-link>
  </div>
</template>

<script>
import { ref, onMounted, getCurrentInstance, computed } from 'vue'
import axios from 'axios'

export default {
  name: 'AiWidgets',
  setup() {
    const { proxy } = getCurrentInstance()
    
    const stats = ref({
      total_agents: 0,
      agents_change: 0,
      total_listings: 0,
      listings_change: 0,
      my_orders: 0,
      orders_change: 0,
      my_requests: 0,
      requests_change: 0,
      developers: 0,
      owners: 0,
      property_types: 0,
      unit_views: 0,
      areas: 0,
      layout_types: 0
    })

    const loading = ref(true)

    const fetchStats = async () => {
      try {
        const token = localStorage.getItem('token')
        const response = await axios.get('/api/dashboard/stats', {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
          }
        })
        stats.value = response.data.data
      } catch (error) {
        console.error('Error fetching dashboard stats:', error)
        proxy.$showNotification('Failed to load dashboard statistics', 'error')
      } finally {
        loading.value = false
      }
    }

    const formatNumber = (num) => {
      return new Intl.NumberFormat().format(num)
    }

    const hasPermission = (permission) => {
      try {
        const userData = JSON.parse(localStorage.getItem('user'))
        if (!userData || !userData.permissions) return false
        
        // Check if user has the specific permission
        return userData.permissions.includes(permission) || 
               userData.permissions.includes('*') || 
               userData.role_name === 'admin' || 
               userData.role_name === 'super_admin'
      } catch (error) {
        console.error('Error checking permission:', error)
        return false
      }
    }

    // حساب إذا كان المستخدم أدمن أو سوبر أدمن
    const isAdminUser = computed(() => {
      try {
        const userData = JSON.parse(localStorage.getItem('user'))
        if (!userData || !userData.role_name) return false
        
        return userData.role_name === 'admin' || userData.role_name === 'super_admin'
      } catch (error) {
        console.error('Error checking admin status:', error)
        return false
      }
    })

    const getListingsRoute = () => {
      try {
        const userData = JSON.parse(localStorage.getItem('user'))
        if (!userData || !userData.role_name) return '/alllisting'
        
        // Check if user is admin or super_admin
        if (userData.role_name === 'admin' || userData.role_name === 'super_admin') {
          return '/alllisting'
        }
        return '/alllisting'
      } catch (error) {
        console.error('Error determining listings route:', error)
        return '/alllisting'
      }
    }

    onMounted(() => {
      fetchStats()
    })

    return {
      stats,
      loading,
      formatNumber,
      hasPermission,
      isAdminUser,
      getListingsRoute
    }
  }
}
</script>

<style scoped>
.primary-background {
  background-color: #733E87 !important;
}

.hover-card {
  transition: all 0.3s ease;
  cursor: pointer;
}

.hover-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1) !important;
}

.text-decoration-none {
  text-decoration: none !important;
  color: inherit;
}

.inbound-outbound-text a:hover {
  color: #0B0736;
  text-decoration: underline;
}

/* Ensure router-link doesn't add extra styles */
a.router-link-active {
  color: inherit;
}

/* Admin Requests Card specific styling */
.card .order-2 h6 {
  color: #0B0736;
  font-size: 1.5rem;
}

.card .order-2 .fw-medium {
  color: #666;
}
</style>