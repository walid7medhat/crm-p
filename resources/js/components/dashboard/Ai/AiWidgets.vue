<template>
  <div class="ai-widgets-row">
    <router-link
      v-if="hasPermission('users-list')"
      to="/users"
      class="ai-metric"
    >
      <div class="ai-metric__inner">
        <div>
          <p class="ai-metric__label">Total Agents</p>
          <p class="ai-metric__value">{{ formatNumber(stats.total_agents) }}</p>
        </div>
        <div class="ai-metric__icon ai-metric__icon--cyan">
          <iconify-icon icon="flowbite:users-group-outline" />
        </div>
      </div>
    </router-link>

    <router-link
      v-if="hasPermission('listings-list')"
      :to="getListingsRoute()"
      class="ai-metric"
    >
      <div class="ai-metric__inner">
        <div>
          <p class="ai-metric__label">Total Listings</p>
          <p class="ai-metric__value">{{ formatNumber(stats.total_listings) }}</p>
        </div>
        <div class="ai-metric__icon ai-metric__icon--purple">
          <iconify-icon icon="mingcute:storage-line" />
        </div>
      </div>
    </router-link>

    <router-link
      v-if="hasPermission('listings-list') && isAdminUser"
      to="/my-requests"
      class="ai-metric"
    >
      <div class="ai-metric__inner">
        <div>
          <p class="ai-metric__label">Requests</p>
          <p class="ai-metric__value">{{ formatNumber(stats.my_orders) }}</p>
        </div>
        <div class="ai-metric__icon ai-metric__icon--green">
          <iconify-icon icon="lucide:shopping-cart" />
        </div>
      </div>
    </router-link>

    <div v-else-if="hasPermission('listings-list')" class="ai-metric">
      <div class="ai-metric__inner">
        <div>
          <p class="ai-metric__label">Requests</p>
          <div class="ai-metric__io">
            <router-link to="/my-requests" class="text-decoration-none">
              <span>Inbound: <strong>{{ formatNumber(stats.my_requests) }}</strong></span>
            </router-link>
            <router-link to="/my-orders" class="text-decoration-none">
              <span>Outbound: <strong>{{ formatNumber(stats.my_orders) }}</strong></span>
            </router-link>
          </div>
        </div>
        <div class="ai-metric__icon ai-metric__icon--green">
          <iconify-icon icon="lucide:shopping-cart" />
        </div>
      </div>
    </div>

    <router-link
      v-if="hasPermission('owners-list')"
      to="/owners"
      class="ai-metric"
    >
      <div class="ai-metric__inner">
        <div>
          <p class="ai-metric__label">Owners</p>
          <p class="ai-metric__value">{{ formatNumber(stats.owners) }}</p>
        </div>
        <div class="ai-metric__icon ai-metric__icon--amber">
          <iconify-icon icon="lucide:user-plus" />
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
      total_listings: 0,
      my_orders: 0,
      my_requests: 0,
      owners: 0,
    })

    const fetchStats = async () => {
      try {
        const token = localStorage.getItem('token')
        const response = await axios.get('/api/dashboard/stats', {
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
          },
        })
        stats.value = response.data.data
      } catch (error) {
        console.error('Error fetching dashboard stats:', error)
        proxy?.$showNotification?.('Failed to load dashboard statistics', 'error')
      }
    }

    const formatNumber = (num) => new Intl.NumberFormat().format(num || 0)

    const hasPermission = (permission) => {
      try {
        const userData = JSON.parse(localStorage.getItem('user'))
        if (!userData?.permissions) return false
        return (
          userData.permissions.includes(permission) ||
          userData.permissions.includes('*') ||
          userData.role_name === 'admin' ||
          userData.role_name === 'super_admin'
        )
      } catch {
        return false
      }
    }

    const isAdminUser = computed(() => {
      try {
        const userData = JSON.parse(localStorage.getItem('user'))
        return userData?.role_name === 'admin' || userData?.role_name === 'super_admin'
      } catch {
        return false
      }
    })

    const getListingsRoute = () => '/alllisting'

    onMounted(fetchStats)

    return {
      stats,
      formatNumber,
      hasPermission,
      isAdminUser,
      getListingsRoute,
    }
  },
}
</script>

<style scoped>
.ai-widgets-row {
  display: contents;
}
</style>
