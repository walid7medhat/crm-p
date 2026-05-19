<template>
    <div class="col-xxl-4 col-xl-12">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
            <h6 class="mb-2 fw-bold text-lg mb-0">Top Agent Performance</h6>
          </div>
  
          <div class="mt-32">
            <div
              v-for="(agent, index) in topAgents"
              :key="agent.id"
              class="d-flex align-items-center justify-content-between gap-3" 
              :class="{'mb-24': index !== topAgents.length - 1, 'mb-0': index === topAgents.length -1 }"
            >
              <div class="d-flex align-items-center">
                <img :src="agent.avatar || defaultAvatar" alt="" class="w-40-px h-40-px rounded-circle flex-shrink-0 me-12 overflow-hidden" />
                <div class="flex-grow-1">
                  <h6 class="text-md mb-0 fw-medium newsm">{{ agent.name }}</h6>
                    <span class="text-sm text-secondary-light fw-medium newsm">
                      {{ agent.role.replace(/_/g, ' ') }}
                    </span>
                </div>
              </div>
              <div class="text-end">
                <span 
                  class="text-primary-light text-md fw-medium d-block count clickable-listing-count" 
                  @click="goToAgentListings(agent)"
                >
                  {{ agent.listings_count }} listings
                </span>
                <span class="text-success-main text-sm count">{{ agent.approved_requests }} approved</span>
              </div>
            </div>
            
            <div v-if="topAgents.length === 0 && !loading" class="text-center py-4 text-muted">
              No agents data available
            </div>

            <div v-if="loading" class="text-center py-4">
              <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
              </div>
            </div>
          </div>
  
        </div>
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
        defaultAvatar: "/assets/images/user.png",
        loading: false
      };
    },
    methods: {
      async fetchTopAgents() {
        this.loading = true;
        try {
          const token = localStorage.getItem('token');
          const response = await axios.get('/api/dashboard/top-agent-performance', {
            headers: { 'Authorization': `Bearer ${token}` }
          });
          
          this.topAgents = response.data.data;
        } catch (error) {
          console.error('Error fetching top agents:', error);
        } finally {
          this.loading = false;
        }
      },
      
      goToAgentListings(agent) {
        this.$router.push({
          path: '/alllisting',
          query: {
            agent_id: agent.id,
            agent_name: agent.name || agent.email
          }
        });
      }
    },
    mounted() {
      this.fetchTopAgents();
    }
  };
  </script>
  
  <style scoped>
  .newsm{
    font-size: 13px !important;
  }    
  .count{
    font-size: 11px !important;
  }    
  .clickable-listing-count {
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-block;
    padding: 2px 4px;
    border-radius: 4px;
  }
  .clickable-listing-count:hover {
    background-color: rgba(11, 7, 54, 0.1);
    color: #0B0736;
    text-decoration: underline;
  }
  </style>