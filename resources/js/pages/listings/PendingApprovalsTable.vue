<template>
  <div class="pending-approvals-container">
    <!-- Stats Header -->
    <div class="stats-header mb-4">
      <div class="row">
        <div class="col-md-4">
          <div class="stat-card">
            <h4>{{ totalPending }}</h4>
            <p>Pending Approvals</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="stat-card">
            <h4>{{ approvedToday }}</h4>
            <p>Approved Today</p>
          </div>
        </div>
        <!--<div class="col-md-4">-->
        <!--  <div class="stat-card">-->
        <!--    <h4>{{ averageResponseTime }}</h4>-->
        <!--    <p>Avg Response Time</p>-->
        <!--  </div>-->
        <!--</div>-->
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar mb-4">
      <div class="row">
        <div class="col-md-4">
          <input 
            type="text" 
            v-model="filters.search" 
            @input="debouncedSearch"
            class="form-control"
            placeholder="Search by title, ref number..."
          />
        </div>
        <div class="col-md-3">
          <select v-model="filters.property_type_id" @change="fetchPendingApprovals" class="form-control">
            <option value="">All Property Types</option>
            <option v-for="type in propertyTypes" :key="type.id" :value="type.id">
              {{ type.name }}
            </option>
          </select>
        </div>
        <div class="col-md-3">
          <select v-model="filters.agent_id" @change="fetchPendingApprovals" class="form-control">
            <option value="">All Agents</option>
            <option v-for="agent in agents" :key="agent.id" :value="agent.id">
              {{ agent.name }}
            </option>
          </select>
        </div>
        <div class="col-md-2">
          <button @click="batchApprove" class="btn btn-success w-100" :disabled="selectedListings.length === 0">
            <i class="ri-checkbox-line"></i> Approve Selected ({{ selectedListings.length }})
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <p class="mt-3">Loading pending approvals...</p>
    </div>

    <!-- Table -->
    <div v-else class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th width="50">
              <input type="checkbox" v-model="selectAll" @change="toggleSelectAll" />
            </th>
            <th>Image</th>
            <th>Reference / Title</th>
            <th>Property Type</th>
            <th>Price</th>
            <th>Agent</th>
            <th>Added By</th>
            <th>Created At</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="listing in listings" :key="listing.id">
            <td>
              <input type="checkbox" v-model="selectedListings" :value="listing.id" />
            </td>
            <td>
              <img 
                :src="listing.main_image || '/default-image.jpg'" 
                class="listing-thumbnail"
                style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;"
              />
            </td>
            <td>
              <div><strong>{{ listing.reference_number || 'N/A' }}</strong></div>
              <div class="small text-muted">{{ listing.area || 'No area' }}</div>
            </td>
            <td>{{ listing.property_type || 'N/A' }}</td>
            <td>
              <strong>AED {{ formatPrice(listing.price) }}</strong>
              <div class="small">({{ listing.listing_status }})</div>
            </td>
            <td>
              <div v-if="listing.agent">
                <div>{{ listing.agent.name }}</div>
                <div class="small text-muted">{{ listing.agent.email }}</div>
              </div>
              <span v-else class="text-muted">Unassigned</span>
            </td>
            <td>
              <div>{{ listing.added_by?.name || 'N/A' }}</div>
              <div class="small text-muted">{{ formatDate(listing.created_at) }}</div>
            </td>
            <td>{{ formatDate(listing.created_at) }}</td>
            <td>
              <div class="btn-group">
                <button 
                  @click="viewListing(listing.id)" 
                  class="btn btn-sm btn-info"
                  title="View Details"
                >
                  <i class="ri-eye-line"></i>
                </button>
                <button 
                  @click="approveSingle(listing.id)" 
                  class="btn btn-sm btn-success"
                  title="Approve"
                  :disabled="approvingIds.includes(listing.id)"
                >
                  <i class="ri-check-line"></i>
                </button>
                <!--<button -->
                <!--  @click="openRejectModal(listing)" -->
                <!--  class="btn btn-sm btn-danger"-->
                <!--  title="Reject">-->
                <!--  <i class="ri-close-line"></i>-->
                <!--</button>-->
              </div>
            </td>
          </tr>
          <tr v-if="listings.length === 0 && !loading">
            <td colspan="9" class="text-center py-5">
              <i class="ri-inbox-line" style="font-size: 48px; color: #ccc;"></i>
              <p class="mt-3">No pending approvals found</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="pagination-wrapper mt-4" v-if="pagination && pagination.last_page > 1">
      <nav>
        <ul class="pagination justify-content-center">
          <li class="page-item" :class="{ disabled: pagination.current_page === 1 }">
            <button class="page-link" @click="changePage(pagination.current_page - 1)">Previous</button>
          </li>
          <li 
            v-for="page in visiblePages" 
            :key="page" 
            class="page-item" 
            :class="{ active: page === pagination.current_page }"
          >
            <button class="page-link" @click="changePage(page)">{{ page }}</button>
          </li>
          <li class="page-item" :class="{ disabled: pagination.current_page === pagination.last_page }">
            <button class="page-link" @click="changePage(pagination.current_page + 1)">Next</button>
          </li>
        </ul>
      </nav>
    </div>

    <!-- Reject Modal -->
    <div v-if="showRejectModal" class="modal-overlay" @click="showRejectModal = false">
      <div class="modal-content" @click.stop style="max-width: 500px;">
        <div class="modal-header">
          <h5>Reject Listing</h5>
          <button class="modal-close" @click="showRejectModal = false">×</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Reason for rejection <span class="text-danger">*</span></label>
            <textarea 
              v-model="rejectReason" 
              class="form-control" 
              rows="4"
              placeholder="Please provide a reason why this listing needs changes..."
            ></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showRejectModal = false">Cancel</button>
          <button 
            class="btn btn-danger" 
            @click="confirmReject"
            :disabled="!rejectReason.trim() || rejecting"
          >
            {{ rejecting ? 'Rejecting...' : 'Reject Listing' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';

const router = useRouter();

// State
const listings = ref([]);
const loading = ref(false);
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0
});
const filters = ref({
  search: '',
  property_type_id: '',
  agent_id: ''
});
const selectedListings = ref([]);
const selectAll = ref(false);
const approvingIds = ref([]);
const showRejectModal = ref(false);
const rejectReason = ref('');
const rejecting = ref(false);
const currentRejectListing = ref(null);

// Options
const propertyTypes = ref([]);
const agents = ref([]);

// Stats
const approvedToday = ref(0);
const averageResponseTime = ref('2.5h');

// Computed
const totalPending = computed(() => pagination.value?.total || 0);

const visiblePages = computed(() => {
  if (!pagination.value) return [1];
  
  const current = pagination.value.current_page || 1;
  const last = pagination.value.last_page || 1;
  const delta = 2;
  const range = [];
  
  for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
    range.push(i);
  }
  
  if (current - delta > 2) {
    range.unshift('...');
  }
  if (current + delta < last - 1) {
    range.push('...');
  }
  
  range.unshift(1);
  if (last !== 1) range.push(last);
  
  return range;
});

// Methods
const formatPrice = (price) => {
  if (!price) return '0';
  return new Intl.NumberFormat('en-US').format(price);
};

const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
};

const fetchPendingApprovals = async () => {
  try {
    loading.value = true;
    const response = await api.get('/listings/pending-approvals', {
      params: {
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
        search: filters.value.search || undefined,
        property_type_id: filters.value.property_type_id || undefined,
        agent_id: filters.value.agent_id || undefined
      }
    });
    
    if (response.data.status) {
      listings.value = response.data.data || [];
      pagination.value = response.data.pagination || {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0
      };
      
      // Reset selections
      selectedListings.value = [];
      selectAll.value = false;
    } else {
      console.error('API returned status false:', response.data.message);
      listings.value = [];
    }
  } catch (error) {
    console.error('Error fetching pending approvals:', error);
    listings.value = [];
    pagination.value = {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0
    };
    Swal.fire('Error', 'Failed to load pending approvals', 'error');
  } finally {
    loading.value = false;
  }
};

const fetchOptions = async () => {
  try {
    const [typesRes, agentsRes] = await Promise.all([
      api.get('/listings/property-types').catch(() => ({ data: { data: [] } })),
      api.get('/listings/agents').catch(() => ({ data: { data: [] } }))
    ]);
    
    propertyTypes.value = typesRes.data?.data || [];
    agents.value = agentsRes.data?.data || [];
  } catch (error) {
    console.error('Error fetching options:', error);
    propertyTypes.value = [];
    agents.value = [];
  }
};

const approveSingle = async (listingId) => {
  const result = await Swal.fire({
    title: 'Approve Listing?',
    text: 'This listing will be visible to all users once approved.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#28a745',
    confirmButtonText: 'Yes, Approve',
    cancelButtonText: 'Cancel'
  });
  
  if (!result.isConfirmed) return;
  
  try {
    approvingIds.value.push(listingId);
    const response = await api.patch(`/listings/properties/${listingId}/approve`);
    
    if (response.data.status) {
      Swal.fire('Approved!', 'Listing has been approved successfully.', 'success');
      fetchPendingApprovals();
      
      // Remove from selected
      const index = selectedListings.value.indexOf(listingId);
      if (index > -1) selectedListings.value.splice(index, 1);
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Failed to approve listing', 'error');
  } finally {
    approvingIds.value = approvingIds.value.filter(id => id !== listingId);
  }
};

const openRejectModal = (listing) => {
  currentRejectListing.value = listing;
  rejectReason.value = '';
  showRejectModal.value = true;
};

const confirmReject = async () => {
  if (!rejectReason.value.trim()) {
    Swal.fire('Warning', 'Please provide a reason for rejection', 'warning');
    return;
  }
  
  try {
    rejecting.value = true;
    const response = await api.patch(`/listings/properties/${currentRejectListing.value.id}/reject`, {
      reason: rejectReason.value
    });
    
    if (response.data.status) {
      Swal.fire('Rejected', 'Listing has been rejected and the agent has been notified.', 'success');
      showRejectModal.value = false;
      fetchPendingApprovals();
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Failed to reject listing', 'error');
  } finally {
    rejecting.value = false;
    currentRejectListing.value = null;
  }
};

const batchApprove = async () => {
  if (selectedListings.value.length === 0) {
    Swal.fire('Warning', 'Please select at least one listing to approve', 'warning');
    return;
  }
  
  const result = await Swal.fire({
    title: `Approve ${selectedListings.value.length} Listings?`,
    text: 'All selected listings will be approved and become visible to users.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#28a745',
    confirmButtonText: 'Yes, Approve All',
    cancelButtonText: 'Cancel'
  });
  
  if (!result.isConfirmed) return;
  
  try {
    const response = await api.post('/listings/batch-approve', {
      listing_ids: selectedListings.value,
      action: 'approve'
    });
    
    if (response.data.status) {
      Swal.fire('Success', response.data.message, 'success');
      selectedListings.value = [];
      selectAll.value = false;
      fetchPendingApprovals();
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Failed to approve listings', 'error');
  }
};

const toggleSelectAll = () => {
  if (selectAll.value) {
    selectedListings.value = listings.value.map(l => l.id);
  } else {
    selectedListings.value = [];
  }
};

const viewListing = (listingId) => {
  router.push(`/property-details/${listingId}`);
};

const changePage = (page) => {
  if (page < 1 || page > pagination.value.last_page) return;
  pagination.value.current_page = page;
  fetchPendingApprovals();
};

// Debounced search
let searchTimeout;
const debouncedSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    pagination.value.current_page = 1;
    fetchPendingApprovals();
  }, 500);
};

// Watch for filter changes
watch(() => [filters.value.property_type_id, filters.value.agent_id], () => {
  pagination.value.current_page = 1;
  fetchPendingApprovals();
});

onMounted(() => {
  fetchPendingApprovals();
  fetchOptions();
});

onUnmounted(() => {
  if (searchTimeout) clearTimeout(searchTimeout);
});
</script>

<style scoped>
.pending-approvals-container {
  padding: 20px;
}

.stat-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  text-align: center;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.stat-card h4 {
  font-size: 28px;
  font-weight: bold;
  margin-bottom: 8px;
  color: #01062d;
}

.stat-card p {
  margin: 0;
  color: #666;
  font-size: 14px;
}

.filters-bar {
  background: white;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.table {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.table thead th {
  background: #f8f9fa;
  border-bottom: 2px solid #dee2e6;
  padding: 12px;
}

.listing-thumbnail {
  width: 60px;
  height: 60px;
  object-fit: cover;
  border-radius: 8px;
}

.btn-group {
  gap: 5px;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: 12px;
  max-width: 500px;
  width: 90%;
}

.modal-header {
  padding: 20px;
  border-bottom: 1px solid #dee2e6;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-body {
  padding: 20px;
}

.modal-footer {
  padding: 20px;
  border-top: 1px solid #dee2e6;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.modal-close {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
}
</style>