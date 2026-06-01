<template>
  <div class="ai-panel ai-panel--agents">
    <div class="ai-panel__body">
      <div class="ai-panel__head ai-agents__head">
        <h2 class="ai-panel__title">Top Agent Performance</h2>
        <router-link to="/users" class="ai-agents__view-link">
          View Agents
          <iconify-icon icon="lucide:chevron-right" width="14" height="14" />
        </router-link>
      </div>

      <div v-if="loading" class="ai-skeleton ai-agents__skeleton" />

      <div v-else-if="topAgents.length === 0" class="ai-empty">No agents data available</div>

      <ul v-else class="ai-agents__list">
        <li
          v-for="agent in topAgents"
          :key="agent.id"
          class="ai-agents__item"
          :class="{ 'ai-agents__item--highlight': agent.is_current_user }"
        >
          <img
            :src="agent.avatar || defaultAvatar"
            alt=""
            class="ai-agents__avatar"
            @error="handleImageError"
          />
          <div class="ai-agents__info">
            <p class="ai-agents__name">{{ agent.name }}</p>
            <p class="ai-agents__meta">
              <button
                type="button"
                class="ai-agents__meta-btn"
                @click="goToAgentListings(agent)"
              >
                {{ agent.listings_count }} Listing{{ agent.listings_count === 1 ? '' : 's' }}
              </button>
              <span class="ai-agents__meta-sep">|</span>
              <span class="ai-agents__approved">{{ agent.approved_requests }} Approved</span>
            </p>
          </div>
          <span class="ai-agents__role-badge">{{ formatRole(agent.role) }}</span>
          <button type="button" class="ai-agents__menu" aria-label="Agent options">
            <iconify-icon icon="lucide:ellipsis-vertical" width="18" height="18" />
          </button>
        </li>
      </ul>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'TopAgentPerformance',
  data() {
    return {
      topAgents: [],
      defaultAvatar: '/assets/images/user.png',
      loading: false,
    }
  },
  methods: {
    formatRole(role) {
      const r = (role || 'sales').replace(/_/g, ' ')
      return r.charAt(0).toUpperCase() + r.slice(1).toLowerCase()
    },
    handleImageError(e) {
      e.target.src = this.defaultAvatar
    },
    async fetchTopAgents() {
      this.loading = true
      try {
        const token = localStorage.getItem('token')
        const response = await axios.get('/api/dashboard/top-agent-performance', {
          headers: { Authorization: `Bearer ${token}` },
        })
        this.topAgents = response.data.data || []
      } catch (error) {
        console.error('Error fetching top agents:', error)
      } finally {
        this.loading = false
      }
    },
    goToAgentListings(agent) {
      this.$router.push({
        path: '/alllisting',
        query: { agent_id: agent.id, agent_name: agent.name || agent.email },
      })
    },
  },
  mounted() {
    this.fetchTopAgents()
  },
}
</script>
