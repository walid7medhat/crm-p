<template>
  <div class="col-xxl-8 col-xl-12">
    <div class="card h-100">
      <div class="card-body p-24">
        <div class="d-flex flex-wrap align-items-center gap-1 justify-content-between mb-16">
          <ul class="nav border-gradient-tab nav-pills mb-0 order-2 order-sm-1" id="pills-tab" role="tablist">
            <li v-if="isAdmin " class="nav-item" role="presentation">
              <button
                class="nav-link d-flex align-items-center active"
                id="pills-all-requests-tab"
                data-bs-toggle="pill"
                data-bs-target="#pills-all-requests"
                type="button"
                role="tab"
                aria-controls="pills-all-requests"
                aria-selected="true"
              >
                All Requests
                <span
                  class="text-sm fw-semibold py-6 px-12 bg-neutral-500 rounded-pill text-white line-height-1 ms-12 notification-alert"
                >
                  {{ allRequestsCount }}
                </span>
              </button>
            </li>
            
            <template v-else>
              <li class="nav-item" role="presentation">
                <button
                  class="nav-link d-flex align-items-center active"
                  id="pills-inbound-tab"
                  data-bs-toggle="pill"
                  data-bs-target="#pills-inbound"
                  type="button"
                  role="tab"
                  aria-controls="pills-inbound"
                  aria-selected="true"
                >
                  Inbound 
                  <span
                    class="text-sm fw-semibold py-6 px-12 bg-neutral-500 rounded-pill text-white line-height-1 ms-12 notification-alert"
                  >
                    {{ inboundCount }}
                  </span>
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button
                  class="nav-link d-flex align-items-center"
                  id="pills-outbound-tab"
                  data-bs-toggle="pill"
                  data-bs-target="#pills-outbound"
                  type="button"
                  role="tab"
                  aria-controls="pills-outbound"
                  aria-selected="false"
                  tabindex="-1"
                >
                  Outbound
                  <span
                    class="text-sm fw-semibold py-6 px-12 bg-neutral-500 rounded-pill text-white line-height-1 ms-12 notification-alert"
                  >
                    {{ outboundCount }}
                  </span>
                </button>
              </li>
            </template>
          </ul>
           <router-link 
            :to="getViewAllLink()" 
            class="text-primary-600 hover-text-primary d-flex align-items-center gap-1 order-1 order-sm-2"
          >
            View All
            <iconify-icon icon="solar:alt-arrow-right-linear" class="icon"></iconify-icon>
          </router-link>
        </div>

        <div class="tab-content" id="pills-tabContent">
          <div
            v-if="isAdmin"
            class="tab-pane fade show active"
            id="pills-all-requests"
            role="tabpanel"
            aria-labelledby="pills-all-requests-tab"
            tabindex="0"
          >
            <div class="table-responsive scroll-sm">
              <table class="table bordered-table sm-table mb-0">
                <thead>
                  <tr>
                    <th scope="col">Request From</th>
                    <th scope="col">Request To</th>
                    <th scope="col">Property</th>
                    <th scope="col" class="text-center">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="request in allRequests.slice(0, limit)" :key="request.id">
                    <td>
                      <div class="d-flex align-items-center">
                               <img :src="request?.request_from?.avatar || defaultAvatar"  @error="handleImageError"  alt=""
                                            class="w-40-px h-40-px rounded-circle flex-shrink-0 me-12 overflow-hidden" />
                        <div class="d-flex flex-column">
                       
                          <h6 class="text-md mb-0 fw-medium">{{ request.request_from.name }}</h6>
                        </div>
                      </div>
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                              <img :src="request?.request_to?.avatar || defaultAvatar"  @error="handleImageError"  alt=""
                                            class="w-40-px h-40-px rounded-circle flex-shrink-0 me-12 overflow-hidden" />
                              <div class="d-flex flex-column">
                            
                                <span class="fw-medium">{{ request.request_to.name }}</span>
                              </div>
                        </div>
                    </td>
                    <td>
                      <div class="d-flex flex-column">
                        <span class="fw-medium">{{ request.listing.area.name || 'N/A' }}</span>
                        <span class="text-sm text-secondary-light">{{ request.listing?.property_type?.name || 'N/A' }}</span>
                      </div>
                    </td>
                    <td class="text-center">
                      <span :class="`${getStatusClass(request.status)} px-16 py-4 rounded-pill fw-medium text-sm`">
                        {{ getStatusLabel(request.status) }}
                      </span>
                    </td>
                  </tr>
                  <tr v-if="allRequests.length === 0">
                    <td colspan="4" class="text-center py-4 text-muted">
                      No requests found
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <template v-else>
            <div
              class="tab-pane fade show active"
              id="pills-inbound"
              role="tabpanel"
              aria-labelledby="pills-inbound-tab"
              tabindex="0"
            >
              <div class="table-responsive scroll-sm">
                <table class="table bordered-table sm-table mb-0">
                  <thead>
                    <tr>
                      <th scope="col">Request From</th>
                      <th scope="col">Requested Date</th>
                      <th scope="col" class="text-center">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="request in inboundRequests.slice(0, limit)" :key="request.id">
                      <td>
                        <div class="d-flex align-items-center">
                          <div class="w-40-px h-40-px rounded-circle flex-shrink-0 me-12 overflow-hidden  d-flex align-items-center justify-content-center">
                             <img :src="request.request_from.avatar || defaultAvatar"  alt=""   @error="handleImageError"

                                            class="w-40-px h-40-px rounded-circle flex-shrink-0 me-12 overflow-hidden" />
                          </div>
                          <div class="d-flex flex-column">
                            
                            <h6 class="text-md mb-0 fw-medium">{{ request.request_from.name }}</h6>
                          </div>
                        </div>
                      </td>
                      <td>{{ formatDate(request.created_at) }}</td>
                      <td class="text-center">
                        <span :class="`${getStatusClass(request.status)} px-16 py-4 rounded-pill fw-medium text-sm`">
                          {{ getStatusLabel(request.status) }}
                        </span>
                      </td>
                    </tr>
                    <tr v-if="inboundRequests.length === 0">
                      <td colspan="4" class="text-center py-4 text-muted">
                        No inbound requests found
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Outbound Tab (طلبات مرسلة من الـ user) -->
            <div
              class="tab-pane fade"
              id="pills-outbound"
              role="tabpanel"
              aria-labelledby="pills-outbound-tab"
              tabindex="0"
            >
              <div class="table-responsive scroll-sm">
  <table class="table bordered-table sm-table mb-0">
  <thead>
    <tr>
      <th scope="col">Send To</th>
      <th scope="col">Requested Date</th>
      <th scope="col" class="text-center">Status</th>
    </tr>
  </thead>
  <tbody>
    <tr v-for="order in outboundOrders.slice(0, limit)" :key="order.id">
      <!-- Send To -->
      <td>
        <div class="d-flex align-items-center">
          <div class="w-40-px h-40-px rounded-circle flex-shrink-0 me-12 overflow-hidden d-flex align-items-center justify-content-center">
            <!--<iconify-icon icon="lucide:user" class="text-white-600"></iconify-icon>-->
               <img :src="order.listing?.agent?.avatar || defaultAvatar"   @error="handleImageError" alt=""
                                            class="w-40-px h-40-px rounded-circle flex-shrink-0 me-12 overflow-hidden" />
          </div>
          <div class="d-flex flex-column">
             
            <h6 class="text-md mb-0 fw-medium">{{ order.listing?.agent?.name || 'Agent' }}</h6>
          </div>
        </div>
      </td>


      <!-- Requested Date -->
      <td>{{ formatDate(order.created_at) }}</td>

      <!-- Status -->
      <td class="text-center">
        <span :class="`${getStatusClass(order.status)} px-16 py-4 rounded-pill fw-medium text-sm`">
          {{ getStatusLabel(order.status) }}
        </span>
      </td>
    </tr>

    <tr v-if="outboundOrders.length === 0">
      <td colspan="5" class="text-center py-4 text-muted">
        No outbound orders found
      </td>
    </tr>
  </tbody>
</table>

              </div>
            </div>
          </template>
        </div>
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
        defaultAvatar: '/assets/images/user.png'


    };
  },
  created() {
    this.checkUserRole();
        this.activeTab = this.isAdmin ? 'all-requests' : 'inbound';

  },
  methods: {
       getViewAllLink() {
      if (this.isAdmin) {
        return '/all-requests';
      } else {
        // للمستخدمين العاديين: تغيير الـ link بناءً على التبويب النشط
        if (this.activeTab === 'inbound') {
          return '/my-requests';
        } else if (this.activeTab === 'outbound') {
          return '/my-orders';
        }
        return '/my-requests'; // القيمة الافتراضية
      }
    },
    
    // تحديث عند تغيير التبويب
    setActiveTab(tabName) {
      this.activeTab = tabName;
    },
    checkUserRole() {
      try {
        const userData = localStorage.getItem('user');
        if (userData) {
          const user = JSON.parse(userData);
          const roles = user.roles || [];
          const roleName = user.role_name || '';
          
           this.isAdmin = roles.includes('super_admin') || 
                     roles.includes('admin') || 
                    
                     roleName === 'super_admin' || 
                     roleName === 'admin' 
                    ;
        }
      } catch (error) {
        console.error('Error checking user role:', error);
        this.isAdmin = false;
      }
    },
    
    async fetchLatestData() {
      const token = localStorage.getItem('token');

      if (this.isAdmin) {
        await this.fetchAllRequestsForAdmin(token);
      } else {
        await this.fetchUserSpecificData(token);
      }
    },

    async fetchAllRequestsForAdmin(token) {
      try {
        // استخدام API الخاص بالأدمن كما هو
        const response = await axios.get('/api/dashboard/admin/latest-requests', {
          headers: { 'Authorization': `Bearer ${token}` }
        });
        
        this.allRequests = response.data.data || [];
        this.allRequestsCount = this.allRequests.length;
        
      } catch (error) {
        console.error('Error fetching admin data:', error);
        this.allRequests = [];
        this.allRequestsCount = 0;
      }
    },

    async fetchUserSpecificData(token) {
      try {
        // جلب inbound requests (طلبات واصلة للـ user)
        const inboundResponse = await axios.get('/api/dashboard/my-latest-requests', {
          headers: { 'Authorization': `Bearer ${token}` }
        });
        this.inboundRequests = inboundResponse.data.data || [];
        this.inboundCount = this.inboundRequests.length;

        // جلب outbound orders (طلبات مرسلة من الـ user)
        const outboundResponse = await axios.get('/api/dashboard/my-latest-orders', {
          headers: { 'Authorization': `Bearer ${token}` }
        });
        this.outboundOrders = outboundResponse.data.data || [];
        this.outboundCount = this.outboundOrders.length;

      } catch (error) {
        console.error('Error fetching user specific data:', error);
        this.inboundRequests = [];
        this.outboundOrders = [];
        this.inboundCount = 0;
        this.outboundCount = 0;
      }
    },
    
    formatDate(dateString) {
      if (!dateString) return 'N/A';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-US', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
      });
    },
    handleImageError(event) {
      event.target.src = this.defaultAvatar
    },
    getStatusLabel(status) {
      const labels = {
        'pending': 'Pending',
        'approved': 'Approved',
        'rejected': 'Rejected',
        'completed': 'Completed',
        'in_progress': 'In Progress',
        'in_review': 'In Review',
        'cancelled': 'Cancelled',
        'expired': 'Expired'
      };
      return labels[status] || status;
    },
    
    getStatusClass(status) {
      const statusClasses = {
        'pending': 'bg-warning-focus text-warning-main',
        'approved': 'bg-success-focus text-success-main',
        'completed': 'bg-success-focus text-success-main',
        'in_progress': 'bg-info-focus text-info-main',
        'in_review': 'bg-info-focus text-info-main',
        'rejected': 'bg-danger-focus text-danger-main',
        'cancelled': 'bg-danger-focus text-danger-main',
        'expired': 'bg-secondary-focus text-secondary-main'
      };
      return statusClasses[status?.toLowerCase()] || 'bg-neutral-500 text-white';
    }
  },
   mounted() {
    this.fetchLatestData();
    
    // إضافة event listener لتتبع تغيير التبويب
    const pillsTab = document.getElementById('pills-tab');
    if (pillsTab) {
      pillsTab.addEventListener('show.bs.tab', (event) => {
        const tabId = event.target.id;
        if (tabId.includes('inbound')) {
          this.setActiveTab('inbound');
        } else if (tabId.includes('outbound')) {
          this.setActiveTab('outbound');
        } else if (tabId.includes('all-requests')) {
          this.setActiveTab('all-requests');
        }
      });
    }
  },
  
  // مراقب لتحديث الـ link عند تغيير activeTab
  watch: {
    activeTab(newTab, oldTab) {
      console.log('Active tab changed from', oldTab, 'to', newTab);
      console.log('View All link:', this.getViewAllLink());
    }
  }
};
</script>

<style scoped>
.bg-primary-100{
  background-color: #FAA300 !important;
  color: #ffff !important;
}
 
.border-gradient-tab .nav-link.active{
  background-color:#FAA300 !important ;
  color: #ffff !important;
  border-color:#FAA300 !important ;
}
 
.border-gradient-tab{
  border: none !important;
}
 
.border-gradient-tab .nav-link.active .notification-alert{
  color: #FAA300 !important;
  background-color: #ffff !important;
}
 
.border-gradient-tab .nav-link::before{
  display: none;
}
 
.text-secondary-light {
  color: #6c757d !important;
}
 
.notification-alert {
  min-width: 24px;
  height: 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
}
 
.w-40-px {
  width: 40px;
}
 
.h-40-px {
  height: 40px;
}
 
.scroll-sm {
  overflow-x: auto;
}
 
@media (max-width: 768px) {
  .card-body {
    padding: 1rem !important;
  }
   
  .bordered-table {
    font-size: 0.875rem;
  }
   
  .bordered-table th, 
  .bordered-table td {
    padding: 0.5rem;
  }
}
</style>