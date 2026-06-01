<template>
  <div class="ai-panel">
    <div class="ai-panel__body">
      <div class="ai-panel__head">
        <div class="ai-tabs">
          <button
            v-if="isAdmin"
            type="button"
            class="ai-tab"
            :class="{ 'ai-tab--active': activeTab === 'all-requests' }"
            @click="activeTab = 'all-requests'"
          >
            All Requests
            <span class="ai-tab__count">{{ allRequestsCount }}</span>
          </button>
          <template v-else>
            <button
              type="button"
              class="ai-tab"
              :class="{ 'ai-tab--active': activeTab === 'inbound' }"
              @click="activeTab = 'inbound'"
            >
              Inbound
              <span class="ai-tab__count">{{ inboundCount }}</span>
            </button>
            <button
              type="button"
              class="ai-tab"
              :class="{ 'ai-tab--active': activeTab === 'outbound' }"
              @click="activeTab = 'outbound'"
            >
              Outbound
              <span class="ai-tab__count">{{ outboundCount }}</span>
            </button>
          </template>
        </div>
        <router-link :to="getViewAllLink()" class="ai-panel__link">
          View All
          <iconify-icon icon="lucide:chevron-right" width="14" height="14" />
        </router-link>
      </div>

      <!-- Admin: all requests -->
      <div v-if="isAdmin && activeTab === 'all-requests'" class="ai-table-wrap">
        <table class="ai-table">
          <thead>
            <tr>
              <th>Request From</th>
              <th>Request To</th>
              <th>Property</th>
              <th class="text-center">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="request in allRequests.slice(0, limit)" :key="request.id">
              <td>
                <div class="ai-table-user">
                  <img
                    :src="request?.request_from?.avatar || defaultAvatar"
                    alt=""
                    @error="handleImageError"
                  />
                  <span class="ai-table-user__name">{{ request.request_from?.name }}</span>
                </div>
              </td>
              <td>
                <div class="ai-table-user">
                  <img
                    :src="request?.request_to?.avatar || defaultAvatar"
                    alt=""
                    @error="handleImageError"
                  />
                  <span class="ai-table-user__name">{{ request.request_to?.name }}</span>
                </div>
              </td>
              <td>
                <span class="ai-table-property__title">{{ request.listing?.area?.name || 'N/A' }}</span>
                <span class="ai-table-property__sub d-block">{{ request.listing?.property_type?.name || 'N/A' }}</span>
              </td>
              <td class="text-center">
                <span class="ai-badge" :class="badgeClass(request.status)">
                  {{ getStatusLabel(request.status) }}
                </span>
              </td>
            </tr>
            <tr v-if="allRequests.length === 0">
              <td colspan="4" class="ai-empty">No requests found</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- User: inbound -->
      <div v-if="!isAdmin && activeTab === 'inbound'" class="ai-table-wrap">
        <table class="ai-table">
          <thead>
            <tr>
              <th>Request From</th>
              <th>Requested Date</th>
              <th class="text-center">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="request in inboundRequests.slice(0, limit)" :key="request.id">
              <td>
                <div class="ai-table-user">
                  <img
                    :src="request.request_from?.avatar || defaultAvatar"
                    alt=""
                    @error="handleImageError"
                  />
                  <span class="ai-table-user__name">{{ request.request_from?.name }}</span>
                </div>
              </td>
              <td>{{ formatDate(request.created_at) }}</td>
              <td class="text-center">
                <span class="ai-badge" :class="badgeClass(request.status)">
                  {{ getStatusLabel(request.status) }}
                </span>
              </td>
            </tr>
            <tr v-if="inboundRequests.length === 0">
              <td colspan="3" class="ai-empty">No inbound requests found</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- User: outbound -->
      <div v-if="!isAdmin && activeTab === 'outbound'" class="ai-table-wrap">
        <table class="ai-table">
          <thead>
            <tr>
              <th>Send To</th>
              <th>Requested Date</th>
              <th class="text-center">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in outboundOrders.slice(0, limit)" :key="order.id">
              <td>
                <div class="ai-table-user">
                  <img
                    :src="order.listing?.agent?.avatar || defaultAvatar"
                    alt=""
                    @error="handleImageError"
                  />
                  <span class="ai-table-user__name">{{ order.listing?.agent?.name || 'Agent' }}</span>
                </div>
              </td>
              <td>{{ formatDate(order.created_at) }}</td>
              <td class="text-center">
                <span class="ai-badge" :class="badgeClass(order.status)">
                  {{ getStatusLabel(order.status) }}
                </span>
              </td>
            </tr>
            <tr v-if="outboundOrders.length === 0">
              <td colspan="3" class="ai-empty">No outbound orders found</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'LatestOrdersRequests',
  data() {
    return {
      limit: 5,
      isAdmin: false,
      inboundCount: 0,
      outboundCount: 0,
      inboundRequests: [],
      outboundOrders: [],
      activeTab: 'inbound',
      allRequestsCount: 0,
      allRequests: [],
      defaultAvatar: '/assets/images/user.png',
    }
  },
  created() {
    this.checkUserRole()
    this.activeTab = this.isAdmin ? 'all-requests' : 'inbound'
  },
  methods: {
    getViewAllLink() {
      if (this.isAdmin) return '/all-requests'
      if (this.activeTab === 'outbound') return '/my-orders'
      return '/my-requests'
    },
    checkUserRole() {
      try {
        const user = JSON.parse(localStorage.getItem('user') || '{}')
        const roles = user.roles || []
        const roleName = user.role_name || ''
        this.isAdmin =
          roles.includes('super_admin') ||
          roles.includes('admin') ||
          roleName === 'super_admin' ||
          roleName === 'admin'
      } catch {
        this.isAdmin = false
      }
    },
    async fetchLatestData() {
      const token = localStorage.getItem('token')
      if (this.isAdmin) {
        await this.fetchAllRequestsForAdmin(token)
      } else {
        await this.fetchUserSpecificData(token)
      }
    },
    async fetchAllRequestsForAdmin(token) {
      try {
        const response = await axios.get('/api/dashboard/admin/latest-requests', {
          headers: { Authorization: `Bearer ${token}` },
        })
        this.allRequests = response.data.data || []
        this.allRequestsCount = this.allRequests.length
      } catch {
        this.allRequests = []
        this.allRequestsCount = 0
      }
    },
    async fetchUserSpecificData(token) {
      try {
        const inboundResponse = await axios.get('/api/dashboard/my-latest-requests', {
          headers: { Authorization: `Bearer ${token}` },
        })
        this.inboundRequests = inboundResponse.data.data || []
        this.inboundCount = this.inboundRequests.length

        const outboundResponse = await axios.get('/api/dashboard/my-latest-orders', {
          headers: { Authorization: `Bearer ${token}` },
        })
        this.outboundOrders = outboundResponse.data.data || []
        this.outboundCount = this.outboundOrders.length
      } catch {
        this.inboundRequests = []
        this.outboundOrders = []
        this.inboundCount = 0
        this.outboundCount = 0
      }
    },
    formatDate(dateString) {
      if (!dateString) return 'N/A'
      return new Date(dateString).toLocaleDateString('en-US', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
      })
    },
    handleImageError(event) {
      event.target.src = this.defaultAvatar
    },
    getStatusLabel(status) {
      const labels = {
        pending: 'Pending',
        approved: 'Approved',
        rejected: 'Rejected',
        completed: 'Completed',
        in_progress: 'In Progress',
        in_review: 'In Review',
        cancelled: 'Canceled',
        expired: 'Expired',
        converted: 'Sold Out',
      }
      return labels[status] || status
    },
    badgeClass(status) {
      const s = (status || '').toLowerCase()
      if (s === 'pending' || s === 'in_progress' || s === 'in_review') return 'ai-badge--pending'
      if (s === 'approved' || s === 'completed') return 'ai-badge--approved'
      if (s === 'rejected' || s === 'cancelled') return 'ai-badge--rejected'
      if (s === 'canceled') return 'ai-badge--canceled'
      return 'ai-badge--default'
    },
  },
  mounted() {
    this.fetchLatestData()
  },
}
</script>
