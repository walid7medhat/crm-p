<template>
    <div class="dashboard-main-body">
        <Breadcrumb title="My Requests" :breadcrumbs="[
            { name: 'My Requests' }
        ]" />
    
        <div class="card basic-data-table">
            <div class="card-body">
                <div class="table-toolbar d-flex flex-wrap align-items-center justify-content-between gap-3"
                    style="border-bottom: none; padding-bottom: 8px; padding-top: 1px;">
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <div class="d-flex align-items-center gap-2">
                            <select class="form-select form-select-lr w-auto rounded-3 me-10" v-model="selectedShow"
                                style="border-radius: 10px; height: 2.4rem;">
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                            </select>
                            <span>entries per page</span>
                        </div>
                    </div>
                       <div class="search-container">
                              <!--<button class="btn btn-warning" @click="openVacationModal">-->
                              <!--  <i class="ri-suitcase-line me-1"></i>-->
                              <!--  Vacation Mode-->
                              <!--</button>-->
                            <div class="icon-field d-flex align-items-center" style="padding-bottom: 5px;">
                                <span class="me-13">Search:</span>
                                <div class="position-relative" style="width: 100%; max-width: 300px;">
                                    <input 
                                        type="text" 
                                        class="form-control form-control-sm w-100 px-3 pe-5" 
                                        v-model="searchText"
                                        placeholder="Search by name, email, property, status..."
                                        style="border-radius: 10px; height: 2.5rem;" 
                                    />
                                    <i class="ri-search-line position-absolute end-0 top-50 translate-middle-y me-3" 
                                       style="color: #6c757d;"></i>
                                </div>
                            </div>
                        </div>
                </div>
                <!-- Filter Tabs -->
                <div class="filter-tabs mb-4">
                    <button 
                        v-for="tab in filterTabs" 
                        :key="tab.value"
                        class="tab-btn"
                        :class="{ active: activeFilter === tab.value }"
                        @click="activeFilter = tab.value"
                    >
                        {{ tab.label }}
                        <span class="tab-count" v-if="getTabCount(tab.value) > 0">
                            {{ getTabCount(tab.value) }}
                        </span>
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="table bordered-table mb-0 mx-0">
                        <thead>
                            <tr>
                                <th @click="sortBy('reference_number')">#</th>
                                <th @click="sortBy('requester_name')">Requested From</th>
                                <th @click="sortBy('property_title')"  v-if="hasShowAllColumn">Requested To</th>
                                <th @click="sortBy('property_title')"  v-if="!hasShowAllColumn">Property</th>
                                <th @click="sortBy('request_type')">Request Type</th>
                                <th @click="sortBy('status')">Status</th>
                                <th @click="sortBy('created_at')">Requested Date</th>
                                <th @click="sortBy('responded_at')">Response Date</th>

                                <!-- <th @click="sortBy('converted_at')">Sold Out Info</th> -->
                                <th>Review</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="request in paginatedRequests" :key="request.id">
                                  <td>{{request.reference_number}}</td>
                                <td>
                                    <div class="d-flex align-items-center" style="cursor: pointer;" @click="goToUser(request.requested_by?.id)">
                                        <img :src="request.requested_by?.avatar || defaultAvatar"  alt=""
                                            class="w-40-px h-40-px rounded-circle flex-shrink-0 me-12 overflow-hidden" />
                                        <div class="flex-grow-1">
                                            <span class="text-md mb-0 fw-bolder text-primary-light d-block">{{ request.requester_name }}</span>

                                        </div>
                                    </div>
                                </td>
                                <td  v-if="hasShowAllColumn">
                                    <div class="d-flex align-items-center">
                                         <img :src="request.listing?.agent_avatar|| defaultAvatar"  alt=""
                                            class="w-40-px h-40-px rounded-circle flex-shrink-0 me-12 overflow-hidden" />
                                          <div class="d-flex flex-column">
                                              <span class="text-md mb-1 fw-bolder text-primary-light">
                                                  
                                                {{ request.listing.agent }}
                                              </span>
                                              <span class="text-sm text-secondary-light">
                                                {{ request.property_title }}
                                              </span>
                                            </div>
                                    </div>
                                </td>
                                 <td  v-if="!hasShowAllColumn">

                                              <span class="text-sm text-secondary-light">
                                                {{ request.property_title }}
                                              </span>
                                </td>
                                
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-md mb-1 fw-bolder d-block">
                                            {{ getRequestTypeLabel(request.request_type) }}
                                        </span>
                                        
                                        <div v-if="request?.request_type=='viewing'" class="d-flex align-items-center gap-3">
                                            <small class="request-time viewing text-muted">
                                                <i class="ri-calendar-line me-1"></i>
                                                {{ request.formatted_date }} 
                                                <i class="ri-time-line ms-2 me-1"></i>
                                                {{ request.formatted_time }}
                                            </small>
                                            <button 
                                                v-if="request.permissions.can_approve"
                                                class="btn-time-edit ms-2"
                                                @click="editViewingTime(request)"
                                                title="Edit Viewing Time"
                                            >
                                                <iconify-icon icon="ri:edit-2-line" width="14"></iconify-icon>
                                            </button>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span :class="statusClass(request.status)" class="px-12 py-4 rounded fw-medium text-sm">
                                       {{ 
                                            request.status === 'converted' ? 'Sold Out' : 
                                            (request.status === 'in_progress' ? 'In Progress' : request.status) 
                                        }}
                                        <span v-if="request.status === 'pending'" class="pulse-dot"></span>
                                        <br/>
                                        <span v-if="request.status === 'cancelled'"> {{ request.cancellation_reason }}</span>
                                    </span>
                                     
                                </td>
                                <td>
                                    <span class="text-sm mb-0 fw-normal text-secondary-light d-block">{{ formatDate(request.created_at) }}</span>
                                </td>
                                       <td>
                                    <span class="text-sm mb-0 fw-normal text-secondary-light d-block">
                                        {{ request.responded_at ? formatDate(request.responded_at) : 'Pending' }}
                                    </span>
                                </td>
                                <!-- <td>
                                    <div v-if="request.status === 'converted'" class="converted-info">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="ri-checkbox-circle-fill text-success me-2"></i>
                                            <span class="text-sm fw-bold text-success">Sold Out</span>
                                        </div>
                                        <div class="converted-details">
                                            <span class="text-xs text-muted d-block">
                                                By: {{ request.converted_by || 'You' }}
                                            </span>
                                            <span class="text-xs text-muted d-block">
                                                {{ formatDate(request.converted_at) }}
                                            </span>
                                        </div>
                                        <div v-if="request.conversion_notes" class="mt-2 p-2 bg-light rounded">
                                            <span class="text-xs text-muted">Notes: {{ request.conversion_notes }}</span>
                                        </div>
                                    </div>
                                    <div v-else-if="request.status === 'approved'" class="text-center">
                                        <span class="text-sm text-warning">
                                            <i class="ri-time-line me-1"></i>
                                            Ready for Sold Out
                                        </span>
                                    </div>
                                    <div v-else class="text-center">
                                        <span class="text-sm text-muted">-</span>
                                    </div>
                                </td> -->
                                    <td>
                                        <div v-if="request.review">
                                            <div class="review-display">
                                                <div class="review-text mb-1">
                                                    "{{ truncateText(request.review, 60) }}"
                                                </div>
                                                <small class="text-muted d-block">
                                                    By: {{ request.reviewed_by?.name || 'Requester' }}
                                                </small>
                                                <small class="text-muted d-block">
                                                    {{ formatDate(request.reviewed_at) }}
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <div v-else-if="request.request_type === 'viewing' && request.status === 'approved'">
                                            <span class="text-muted small">
                                                <i class="ri-star-line me-1"></i>
                                                No review yet
                                            </span>
                                        </div>
                                        
                                        <div v-else>
                                            <span class="text-muted">-</span>
                                        </div>
                                    </td>
                                 <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                type="button" 
                                                data-bs-toggle="dropdown" 
                                                aria-expanded="false">
                                            <img :src="filtericon" width="20px" />
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li v-if="request.permissions.can_in_progress && request.request_type=='viewing'">
                                                <a class="dropdown-item " href="javascript:void(0)" @click="inProgressRequest(request.id)">
                                                    <iconify-icon icon="mdi:progress-clock" class="me-2 text-success"></iconify-icon>
                                                    In Progress 
                                                </a>
                                            </li>
                                            <!-- Approve Action -->
                                            <li v-if="request.permissions.can_approve">
                                                <a class="dropdown-item " href="javascript:void(0)" @click="approveRequest(request.id)">
                                                    <iconify-icon icon="ri:check-line" class="me-2 text-success"></iconify-icon>
                                                    Approve 
                                                </a>
                                            </li>
                                            <li v-if="request.permissions.can_approve && request.request_type=='viewing'">
                                                <a class="dropdown-item" href="javascript:void(0)" @click="editViewingTime(request)">
                                                    <iconify-icon icon="ri:time-line" class="me-2 text-info"></iconify-icon>
                                                    Edit Viewing Time
                                                </a>
                                            </li>
                                            <!-- Reject Action -->
                                            <li v-if="request.permissions.can_reject">
                                                <a class="dropdown-item " href="javascript:void(0)" @click="rejectRequest(request.id)">
                                                    <iconify-icon icon="ri:close-line" class="me-2 text-danger"></iconify-icon>
                                                    Reject 
                                                </a>
                                            </li>
                                            
                                            <!-- Mark as Sold Out -->
                                            <li v-if="request.permissions.can_convert">
                                                <a class="dropdown-item " href="javascript:void(0)" @click="markAsConverted(request)">
                                                    <iconify-icon icon="ri:checkbox-circle-line" class="me-2 text-primary"></iconify-icon>
                                                    Sold Out
                                                </a>
                                            </li>
                                            
                                            <!-- Sold Out Status Display -->
                                            <li v-if="request.status === 'converted'" class="dropdown-item disabled">
                                                <span class="">
                                                    <iconify-icon icon="ri:checkbox-circle-fill" class="me-2 text-success"></iconify-icon>
                                                    Sold Out
                                                </span>
                                                <br>
                                                <span class="text-muted small">
                                                    {{ formatDate(request.converted_at) }}
                                                </span>
                                            </li>
                                            
                                            <!-- View Property -->
                                            <li >
                                                <a class="dropdown-item " href="javascript:void(0)" 
                                                   @click="viewProperty(request.listing.id)">
                                                    <iconify-icon icon="ri:home-line" class="me-2 text-primary"></iconify-icon>
                                                    View Property
                                                </a>
                                            </li>
                                            
                                            <!-- Cancel Request -->
                                            <li v-if="request.permissions.can_cancel">
                                                <a class="dropdown-item " href="javascript:void(0)" @click="cancelRequest(request.listing?.id, request.request_type)">
                                                    <iconify-icon icon="ri:close-circle-line" class="me-2 text-warning"></iconify-icon>
                                                    Cancel Request
                                                </a>
                                            </li>
                                            
                                            <!-- No Actions Available -->
                                            <li v-if="!hasAvailableActions(request)">
                                                <span class="dropdown-item text-muted">No actions available</span>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Empty State -->
                    <div v-if="!loading && filteredRequests.length === 0" class="text-center py-5">
                        <i class="ri-inbox-line" style="font-size: 64px; color: #6c757d;"></i>
                        <h5 class="mt-3">No Requests Found</h5>
                        <p class="text-muted">You don't have any incoming access requests yet.</p>
                    </div>

                    <!-- Loading State -->
                    <div v-if="loading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3">Loading requests...</p>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-24" v-if="!loading && filteredRequests.length > 0">
                        <span>
                            Showing {{ startIndex + 1 }} to {{ endIndex }} of {{ filteredRequests.length }} entries
                        </span>
                        <ul class="pagination d-flex flex-wrap align-items-center gap-2 justify-content-center">
                            <li class="page-item">
                                <a class="page-link text-secondary-light fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px bg-base"
                                    href="javascript:void(0)" @click="goToPage(currentPage - 1)"
                                    :class="{ disabled: currentPage === 1 }">
                                    <iconify-icon icon="ep:d-arrow-left" class="text-xl"></iconify-icon>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link text-secondary-light fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px bg-base"
                                    href="javascript:void(0)" @click="goToPage(currentPage - 1)"
                                    :class="{ disabled: currentPage === 1 }">
                                    <iconify-icon icon="ep:arrow-left"></iconify-icon>
                                </a>
                            </li>

                            <li v-for="page in totalPages" :key="page" class="page-item">
                                <a href="javascript:void(0)"
                                    class="page-link fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px"
                                    :class="{
                                        'bg-primary-600 text-white': currentPage === page,
                                        'bg-primary-50 text-secondary-light': currentPage !== page
                                    }" @click="goToPage(page)">
                                    {{ page }}
                                </a>
                            </li>

                            <li class="page-item">
                                <a class="page-link text-secondary-light fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px bg-base"
                                    href="javascript:void(0)" @click="goToPage(currentPage + 1)"
                                    :class="{ disabled: currentPage === totalPages }">
                                    <iconify-icon icon="ep:arrow-right" class="text-xl"></iconify-icon>
                                </a>
                            </li>

                            <li class="page-item">
                                <a class="page-link text-secondary-light fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px bg-base"
                                    href="javascript:void(0)" @click="goToPage(currentPage + 1)"
                                    :class="{ disabled: currentPage === totalPages }">
                                    <iconify-icon icon="ep:d-arrow-right" class="text-xl"></iconify-icon>
                                </a>
                            </li>
                                
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Response Modal -->
        <div v-if="showResponseModal" class="modal-overlay" @click="showResponseModal = false">
            <div class="modal-content" @click.stop style="max-width: 500px;">
                <div class="modal-header">
                    <h5 class="modal-title">Respond to Request</h5>
                    <button type="button" class="btn-close" @click="showResponseModal = false"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Response Notes <span v-if="currentResponseType === 'rejected'" class="text-danger">*</span></label>
                        <textarea 
                            v-model="responseNotes" 
                            class="form-control" 
                            rows="4" 
                            placeholder="Add any notes for the requester..."
                            :class="{ 'is-invalid': currentResponseType === 'rejected' && !responseNotes.trim() }"
                        ></textarea>
                        <div v-if="currentResponseType === 'rejected' && !responseNotes.trim()" class="invalid-feedback">
                            Please provide a reason for rejection
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="showResponseModal = false">Cancel</button>
                    <button type="button" class="btn btn-primary" @click="confirmResponse" :disabled="currentResponseType === 'rejected' && !responseNotes.trim()">
                        Confirm 
                    </button>
                </div>
            </div>
        </div>

        <!-- Convert Modal -->
        <div v-if="showConvertModal" class="modal-overlay" @click="showConvertModal = false">
            <div class="modal-content" @click.stop style="max-width: 500px;">
                <div class="modal-header">
                    <h5 class="modal-title">Mark as Sold Out</h5>
                    <button type="button" class="btn-close" @click="showConvertModal = false"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="ri-information-line me-2"></i>
                        You are marking this request as Sold Out. This indicates the lead has been successfully processed.
                    </div>
                    <div class="form-group">
                        <label class="form-label">Sold Out Notes (Optional)</label>
                        <textarea 
                            v-model="conversionNotes" 
                            class="form-control" 
                            rows="4" 
                            placeholder="Add any notes about the conversion outcome..."
                        ></textarea>
                        <small class="text-muted">Example: Lead successfully Sold Out, deal closed, meeting scheduled, etc.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="showConvertModal = false">Cancel</button>
                    <button type="button" class="btn btn-success" @click="confirmConvert">
                        <i class="ri-check-line me-2"></i>
                        Mark as Sold Out
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- Time Edit Modal -->
<div v-if="showTimeEditModal" class="modal-overlay" @click="showTimeEditModal = false">
    <div class="modal-content" @click.stop style="max-width: 500px;">
        <div class="modal-header">
            <h5 class="modal-title">Edit Viewing Time</h5>
            <button type="button" class="btn-close" @click="showTimeEditModal = false"></button>
        </div>
        <div class="modal-body">
            <div class="alert alert-info">
                <i class="ri-information-line me-2"></i>
                Update the viewing appointment details
            </div>
            
            <div class="form-group mb-3">
                <label class="form-label">Date <span class="text-danger">*</span></label>
                <input 
                    type="date" 
                    v-model="timeEditData.date" 
                    class="form-control" 
                    :min="new Date().toISOString().split('T')[0]"
                    required
                />
            </div>
            
            <div class="form-group mb-3">
                <label class="form-label">Time <span class="text-danger">*</span></label>
                <input 
                    type="time" 
                    v-model="timeEditData.time" 
                    class="form-control" 
                    required
                />
            </div>
            
           
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showTimeEditModal = false">
                Cancel
            </button>
            <button type="button" class="btn btn-primary" @click="saveTimeEdit">
                <i class="ri-save-line me-2"></i>
                Save Changes
            </button>
        </div>
    </div>
</div>
    <!-- Vacation Mode Modal -->
 <div v-if="showVacationModal" class="modal-overlay" @click="showVacationModal=false">
  <div class="modal-content" @click.stop style="max-width:500px">
    <div class="modal-header">
      <h5 class="modal-title">Vacation Mode</h5>
      <button type="button" class="btn-close" @click="showVacationModal=false"></button>
    </div>
    <div class="modal-body">
        <div class="form-group">
          <label>Activate Vacation Mode</label>
          <select class="form-select" v-model="vacationData.delegate_id" :disabled="!vacationData.active">
            <option value="">Select Agent to handle requests</option>
            <option v-for="agent in agentsList" :key="agent.id" :value="agent.id">
              {{ agent.name }}
            </option>
          </select>
        </div>
        
        <div class="form-check mt-3">
          <input type="checkbox" class="form-check-input" v-model="vacationData.active" id="vacationActive">
          <label class="form-check-label" for="vacationActive">Activate Vacation Mode</label>
        </div>

    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" @click="showVacationModal=false">Cancel</button>
      <button class="btn btn-warning" @click="saveVacationMode">Save</button>
    </div>
  </div>
</div>

</template>
<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import Swal from 'sweetalert2'
import api from '@/plugins/axios'
import { useRouter } from 'vue-router'

const defaultAvatar = 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'
const filtericon= '/assets/images/filter.png'
const router = useRouter()

// Data
const requests = ref([])
const loading = ref(true)
const selectedShow = ref(10)
const currentPage = ref(1)
const searchText = ref('') 
const sortedBy = ref('')
const sortAsc = ref(true)
const showResponseModal = ref(false)
const currentRequestId = ref(null)
const currentResponseType = ref('')
const responseNotes = ref('')
const activeFilter = ref('all')
const showConvertModal = ref(false)
const conversionNotes = ref('')
const currentConvertingRequest = ref(null)
const showVacationModal = ref(false)

// Vacation Mode Data
const vacationData = ref({
    active: false,
    delegate_id: ''
})
const agentsList = ref([]) // Load from API
const hasShowAllColumn = computed(() => {
    return requests.value.some(request => request.show_all_column === true)
})
const hasAvailableActions = (request) => {
    return request.permissions.can_approve || 
           request.permissions.can_reject || 
           request.permissions.can_convert || 
           request.permissions.can_cancel;
}
const showTimeEditModal = ref(false)
const timeEditData = ref({
    id: null,
    date: '',
    time: '',
})

function editViewingTime(request) {
    timeEditData.value = {
        id: request.id,
        date: request.viewing_date || '',
        time: request.viewing_time || '',
    }
    showTimeEditModal.value = true
}
async function cancelRequest(id, requestType) {
    const result = await Swal.fire({
        title: 'Are you sure?',
        text: "Do you really want to cancel this request?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#01062d',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, cancel it',
        cancelButtonText: 'No'
    })

    if (!result.isConfirmed) return

    try {
        const response = await api.post(`/listings/access-requests/${id}/cancel`, {
            request_type: requestType
        })

        if (response.data.status) {
            Swal.fire({
                title: 'Success!',
                text: 'Request cancelled successfully',
                icon: 'success',
                confirmButtonColor: '#01062d'
            })

            await fetchMyRequests()
        } else {
            throw new Error(response.data.message)
        }

    } catch (err) {
        Swal.fire({
            title: 'Error!',
            text: err.response?.data?.message || 'Failed to cancel request',
            icon: 'error',
            confirmButtonColor: '#01062d'
        })
    }
}
async function saveTimeEdit() {
    try {
        if (!timeEditData.value.date && !timeEditData.value.time) {
             showTimeEditModal.value = false
            Swal.fire('Error!', 'Please select both date or time', 'error')
            return
        }

        const response = await api.put(
            `/listings/access-requests/${timeEditData.value.id}/update-time`,
            {
                viewing_date: timeEditData.value.date,
                viewing_time: timeEditData.value.time,
                viewing_notes: timeEditData.value.notes
            }
        )

        if (response.data.status) {
             showTimeEditModal.value = false
            Swal.fire({
                title: 'Success!',
                text: 'Viewing time updated successfully',
                icon: 'success',
                confirmButtonColor: '#01062d'
            })
           
            await fetchMyRequests()
        } else {
            throw new Error(response.data.message || 'Failed to update time')
        }
    } catch (err) {
        console.error('Error updating viewing time:', err)
         showTimeEditModal.value = false
        Swal.fire({
            title: 'Error!',
            text: 'Failed to update viewing time',
            icon: 'error',
            confirmButtonColor: '#01062d'
        })
    }
}
const filterTabs = [
    { label: 'All', value: 'all' },
    { label: 'Pending', value: 'pending' },
    { label: 'Approved', value: 'approved' },
    { label: 'Sold Out', value: 'converted' },
    { label: 'Rejected', value: 'rejected' },
    { label: 'Cancelled', value: 'cancelled' }
]

const filteredRequests = computed(() => {
    let filtered = requests.value
    
    if (activeFilter.value !== 'all') {
        filtered = filtered.filter(request => request.status === activeFilter.value)
    }
    
    if (searchText.value.trim()) {
        const searchTerm = searchText.value.toLowerCase().trim()
        
        filtered = filtered.filter(request => {
            const searchFields = [
                request.requester_name || '',
                request.requester_email || '',
                request.property_title || '',
                request.listing?.agent || '',
                request.request_type || '',
                request.status || '',
                request.reference_number ||'',
                formatDate(request.created_at) || '',
                formatDate(request.responded_at) || '',
                formatDate(request.converted_at) || ''
            ]
            
            return searchFields.some(field => 
                field.toLowerCase().includes(searchTerm)
            )
        })
    }
    
    return filtered
})

const sortedRequests = computed(() => {
    if (!sortedBy.value) return filteredRequests.value
    
    return [...filteredRequests.value].sort((a, b) => {
        let valA, valB
        
        switch (sortedBy.value) {
            case 'requester_name':
                valA = a.requester_name?.toLowerCase?.() || ''
                valB = b.requester_name?.toLowerCase?.() || ''
                break
            case 'property_title':
                valA = a.property_title?.toLowerCase?.() || ''
                valB = b.property_title?.toLowerCase?.() || ''
                break
            case 'request_type':
                valA = a.request_type?.toLowerCase?.() || ''
                valB = b.request_type?.toLowerCase?.() || ''
                break
            case 'status':
                valA = a.status?.toLowerCase?.() || ''
                valB = b.status?.toLowerCase?.() || ''
                break
            case 'created_at':
                valA = new Date(a.created_at).getTime()
                valB = new Date(b.created_at).getTime()
                break
            case 'responded_at':
                valA = a.responded_at ? new Date(a.responded_at).getTime() : 0
                valB = b.responded_at ? new Date(b.responded_at).getTime() : 0
                break
            case 'converted_at':
                valA = a.converted_at ? new Date(a.converted_at).getTime() : 0
                valB = b.converted_at ? new Date(b.converted_at).getTime() : 0
                break
            default:
                valA = ''
                valB = ''
        }
        
        return (valA > valB ? 1 : -1) * (sortAsc.value ? 1 : -1)
    })
})

const totalPages = computed(() =>
    Math.ceil(sortedRequests.value.length / selectedShow.value)
)

const paginatedRequests = computed(() => {
    const start = (currentPage.value - 1) * selectedShow.value
    return sortedRequests.value.slice(start, start + selectedShow.value)
})

const startIndex = computed(() => (currentPage.value - 1) * selectedShow.value)
const endIndex = computed(() =>
    Math.min(startIndex.value + selectedShow.value, filteredRequests.value.length)
)

const getTabCount = (status) => {
    if (status === 'all') return requests.value.length
    return requests.value.filter(request => request.status === status).length
}

// Methods
function sortBy(key) {
    if (sortedBy.value === key) {
        sortAsc.value = !sortAsc.value
    } else {
        sortedBy.value = key
        sortAsc.value = true
    }
}

function goToPage(page) {
    if (page < 1 || page > totalPages.value) return
    currentPage.value = page
}

function statusClass(status) {
    switch (status) {
        case 'approved':
            return 'bg-success-50 text-success-700 border border-success-200'
        case 'rejected':
            return 'bg-danger-50 text-danger-700 border border-danger-200'
        case 'pending':
            return 'bg-warning-50 text-warning-700 border border-warning-200'
        case 'cancelled':
            return 'bg-secondary-50 text-secondary-700 border border-secondary-200'
        case 'converted':
            return 'bg-success-50 text-success-700 border border-success-200'
        default:
            return 'bg-gray-50 text-gray-700 border border-gray-200'
    }
}

function requestTypeClass(type) {
    switch (type) {
        case 'unit_number':
            return 'bg-info-50 text-info-700 border border-info-200'
        case 'owner_data':
            return 'bg-purple-50 text-purple-700 border border-purple-200'
        default:
            return 'bg-gray-50 text-gray-700 border border-gray-200'
    }
}
function truncateText(text, maxLength) {
    if (!text) return ''
    if (text.length <= maxLength) return text
    return text.substring(0, maxLength) + '...'
}

function getRequestTypeLabel(type) {
    const types = {
        'unit_number': 'Unit Number',
        'owner_data': 'Owner Information'
    }
    return types[type] || type
}

function formatDate(dateString) {
    if (!dateString) return 'N/A'
    const date = new Date(dateString)
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}
function  goToUser(userId) {
        if (userId) {
            router.push(`/users/${userId}`);
        }
    }
watch(searchText, () => {
    currentPage.value = 1
})

watch(activeFilter, () => {
    currentPage.value = 1
})

async function fetchMyRequests() {
    try {
        loading.value = true
        const response = await api.get('/listings/access-requests/my-requests')
        
        if (response.data.status) {
            requests.value = response.data.data.map(request => ({
                ...request,
                requester_name: request.requested_by?.name || 'Unknown',
                requester_email: request.requested_by?.email || 'Unknown',
                property_title: request.listing?.title || 'Unknown Property',
                converted_by_name: request.converted_by?.name || null
            }))
            console.log('✅ MyRequests loaded:', requests.value.length, 'requests')
            console.log('🔍 Approved requests:', requests.value.filter(r => r.status === 'approved'))
        } else {
            throw new Error(response.data.message || 'Failed to fetch requests')
        }
    } catch (err) {
        console.error('Error fetching requests:', err)
        Swal.fire({
            title: 'Error!',
            text: 'Failed to load requests',
            icon: 'error',
            confirmButtonColor: '#01062d'
        })
    } finally {
        loading.value = false
    }
}

async function refreshRequests() {
    await fetchMyRequests()
    Swal.fire({
        title: 'Refreshed!',
        text: 'Requests list updated',
        icon: 'success',
        timer: 1500,
        showConfirmButton: false
    })
}

function approveRequest(requestId) {
    currentRequestId.value = requestId
    currentResponseType.value = 'approved'
    responseNotes.value = ''
    showResponseModal.value = true
}
function inProgressRequest(requestId){
    currentRequestId.value = requestId
    currentResponseType.value = 'in_progress'
    responseNotes.value = ''
    showResponseModal.value = true
}
function rejectRequest(requestId) {
    currentRequestId.value = requestId
    currentResponseType.value = 'rejected'
    responseNotes.value = ''
    showResponseModal.value = true
}

async function confirmResponse() {
    if (currentResponseType.value === 'rejected' && !responseNotes.value.trim()) {
        return
    }

    try {
        const response = await api.put(
            `/listings/access-requests/${currentRequestId.value}/respond`,
            null,
            {
                params: {
                    status: currentResponseType.value,
                    response: responseNotes.value
                }
            }
        )

        if (response.data.status) {
            Swal.fire({
                title: 'Success!',
                text: `Request ${currentResponseType.value} successfully`,
                icon: 'success',
                confirmButtonColor: '#01062d'
            })
            showResponseModal.value = false
            await fetchMyRequests()
        } else {
            throw new Error(response.data.message || 'Failed to respond to request')
        }
    }
    catch (err) {
        console.error('Error responding to request:', err)
        showResponseModal.value = false
        await fetchMyRequests()
    
        // اقرأ رسالة السيرفر من Axios
        let errorMessage = 'Failed to respond to request'
    
        if (err.response && err.response.data) {
            errorMessage = err.response.data.message || errorMessage
        }
    
        Swal.fire({
            title: 'Error!',
            text: errorMessage,
            icon: 'error',
            confirmButtonColor: '#01062d'
        })
    }
}

function markAsConverted(request) {
    currentConvertingRequest.value = request
    conversionNotes.value = ''
    showConvertModal.value = true
}

async function confirmConvert() {
    try {
        const response = await api.put(
            `/listings/access-requests/${currentConvertingRequest.value.id}/convert`,
            {
                conversion_notes: conversionNotes.value
            }
        )

        if (response.data.status) {
            Swal.fire({
                title: 'Success!',
                text: 'Request marked as Sold Out successfully',
                icon: 'success',
                confirmButtonColor: '#01062d'
            })
            showConvertModal.value = false
            await fetchMyRequests()
        } else {
            throw new Error(response.data.message || 'Failed to mark as Sold Out')
        }
    } catch (err) {
        console.error('Error converting request:', err)
        Swal.fire({
            title: 'Error!',
            text: 'Failed to mark as Sold Out',
            icon: 'error',
            confirmButtonColor: '#01062d'
        })
    }
}

// Real-time Updates Methods
const echoListeners = ref([])
const pollingInterval = ref(null)

const initializeRealTimeUpdates = () => {
    const user = JSON.parse(localStorage.getItem('user'))
    if (!user || !window.Echo) {
        console.log('❌ Real-time updates not available, using polling...')
        startPolling()
        return
    }

    console.log('🔔 MyRequests: Initializing real-time updates for user:', user.id)

    try {
        const listener = window.Echo.private(`user.${user.id}`)
            .listen('.access.request.updated', (event) => {
                console.log('🎉 MyRequests: Real-time update received:', event)
                handleRealTimeUpdate(event)
            })
            .error((error) => {
                console.error('❌ Echo error:', error)
                startPolling()
            })

        echoListeners.value.push(listener)
    } catch (error) {
        console.error('❌ Failed to initialize Echo:', error)
        startPolling()
    }
}

const handleRealTimeUpdate = (event) => {
    if (event.action_type === 'requested') {
        console.log('🆕 New request received via real-time')
        fetchMyRequests()
        showNewRequestNotification(event)
    }
    
    if (event.action_type === 'cancelled') {
        console.log('❌ Request cancelled via real-time')
        fetchMyRequests()
        showCancelledNotification(event)
    }

    if (event.action_type === 'converted') {
        console.log('✅ Request Sold Out via real-time')
        fetchMyRequests()
        showConvertedNotification(event)
    }
}

const startPolling = () => {
    console.log('🔄 MyRequests: Starting polling every 10 seconds')
    pollingInterval.value = setInterval(async () => {
        await fetchMyRequests()
    }, 10000)
}

const showNewRequestNotification = (event) => {
    Swal.fire({
        title: 'New Request!',
        text: `New ${event.request_type} request received`,
        icon: 'info',
        timer: 3000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    })
}

const showCancelledNotification = (event) => {
    Swal.fire({
        title: 'Request Cancelled',
        text: `A ${event.request_type} request was cancelled`,
        icon: 'warning',
        timer: 3000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    })
}

const showConvertedNotification = (event) => {
    Swal.fire({
        title: 'Request Converted!',
        text: `A ${event.request_type} request was marked as Sold Out`,
        icon: 'success',
        timer: 3000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    })
}

const getCurrentUserId = () => {
    const user = JSON.parse(localStorage.getItem('user'))
    return user?.id
}

const cleanup = () => {
    echoListeners.value.forEach(listener => {
        if (listener && typeof listener.stopListening === 'function') {
            listener.stopListening('.access.request.updated')
        }
    })
    echoListeners.value = []

    if (pollingInterval.value) {
        clearInterval(pollingInterval.value)
        pollingInterval.value = null
    }
}
async function fetchAgents(){
  try{
    const res = await api.get('/listings/agents/?role=sales')
    if(res.data.status) agentsList.value=res.data.data
  }catch(e){ console.error(e) }
}

async function saveVacationMode(){
  try{
    const res = await api.post('/listings/agent/vacation', vacationData.value)
    if(res.data.status){
      Swal.fire('Success','Vacation mode saved!','success')
      showVacationModal.value=false
    }
  }catch(e){ Swal.fire('Error','Failed to save vacation mode','error') }
}
async function fetchVacationMode() {
    try {
        const { data } = await api.get('/listings/agent/vacation-mode')
        console.log(data);
        vacationData.value.active = data.data.on_vacation
        vacationData.value.delegate_id = data.data.delegate_agent_id || '' 
        console.log('Vacation Data Loaded:', vacationData.value)
    } catch (e) {
        console.error('Failed to fetch vacation mode:', e)
    }
}

function openVacationModal() {
    showVacationModal.value = true
    fetchVacationMode() 
}


// Lifecycle
onMounted(() => {
    fetchMyRequests(),
    fetchAgents(),
    setTimeout(() => {
        initializeRealTimeUpdates()
    }, 1000)
})

onUnmounted(() => {
    cleanup()
})

function viewProperty(propertyId) {
    window.open(`/property-details/${propertyId}`, '_blank')
}
</script>

<style scoped>
/* Pulse dot for pending requests */
.pulse-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    background: #f59e0b;
    border-radius: 50%;
    margin-left: 6px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7);
    }
    
    70% {
        transform: scale(1);
        box-shadow: 0 0 0 6px rgba(245, 158, 11, 0);
    }
    
    100% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(245, 158, 11, 0);
    }
}

/* Stats Cards */
.stat-card {
    background: white;
    border-radius: 8px;
    padding: 16px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border: 2px solid #e9ecef;
    cursor: pointer;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.stat-card.active {
    border-color: #01062d;
    background: #f8f9ff;
}

.stat-number {
    font-size: 24px;
    font-weight: 700;
    color: #01062d;
}

.stat-label {
    font-size: 12px;
    color: #6c757d;
    margin-top: 4px;
    font-weight: 600;
}

/* Filter Tabs */
.filter-tabs {
    display: flex;
    gap: 8px;
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 16px;
    flex-wrap: wrap;
}

.tab-btn {
    padding: 8px 16px;
    border: none;
    background: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    color: #6c757d;
    font-size: 14px;
    display: flex;
    align-items: center;
    border: 1px solid transparent;
}

.tab-btn.active {
    background: #01062d;
    color: white;
    border-color: #01062d;
}

.tab-btn:hover:not(.active) {
    background: #f8f9fa;
    border-color: #dee2e6;
}

.tab-count {
    background:#ffffff;
    color: inherit;
    border-radius: 12px;
    padding: 2px 8px;
    font-size: 11px;
    margin-left: 6px;
    font-weight: 600;
}

.tab-btn.active .tab-count {
    background: rgba(255, 255, 255, 0.3);
}

/* Converted info styles */
.converted-info {
    padding: 8px;
    background: #f0fdf4;
    border-radius: 6px;
    border: 1px solid #dcfce7;
}

.converted-details {
    margin-left: 24px;
}

/* Modal Styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    padding: 20px;
}

.modal-content {
    background: white;
    border-radius: 12px;
    width: 100%;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #e9ecef;
}

.modal-header h5 {
    margin: 0;
    color: #01062d;
    font-weight: 600;
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    display: flex;
    gap: 12px;
    padding: 20px 24px;
    border-top: 1px solid #e9ecef;
}

.btn {
    padding: 10px 16px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
}

.btn-primary {
    background: #01062d;
    color: white;
}

.btn-primary:hover:not(:disabled) {
    background: #020a4a;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-1px);
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-success:hover:not(:disabled) {
    background: #218838;
    transform: translateY(-1px);
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.form-group {
    margin-bottom: 16px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: white;
}

.form-control:focus {
    outline: none;
    border-color: #01062d;
    box-shadow: 0 0 0 3px rgba(1, 6, 45, 0.1);
}

.form-control.is-invalid {
    border-color: #dc3545;
    box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
}

.invalid-feedback {
    display: block;
    width: 100%;
    margin-top: 4px;
    font-size: 12px;
    color: #dc3545;
}

.text-center {
    text-align: center;
}

.spinner-border {
    width: 3rem;
    height: 3rem;
    border-width: 3px;
}

.visually-hidden {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

/* Refresh button styles */
.btn-outline-primary {
    border: 1px solid #01062d;
    color: #01062d;
    background: transparent;
}

.btn-outline-primary:hover:not(:disabled) {
    background: #01062d;
    color: white;
    transform: translateY(-1px);
}

.btn-outline-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

/* Table styles */
.table th {
    cursor: pointer;
    user-select: none;
    background: #f8f9fa;
    font-weight: 600;
    color: #374151;
    border-bottom: 2px solid #e9ecef;
}

.table th:hover {
    background: #e9ecef;
}

/* Alert styles */
.alert {
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 16px;
    border: 1px solid transparent;
}

.alert-info {
    background: #e7f3ff;
    border-color: #b3d9ff;
    color: #0066cc;
}

/* Responsive */
@media (max-width: 768px) {
    .stat-card {
        padding: 12px;
    }
    
    .stat-number {
        font-size: 20px;
    }
    
    .filter-tabs {
        gap: 4px;
    }
    
    .tab-btn {
        padding: 6px 12px;
        font-size: 12px;
    }
    
    .converted-info {
        font-size: 0.75rem;
    }
    
    .modal-content {
        margin: 20px;
        width: calc(100% - 40px);
    }
    
    .btn {
        padding: 8px 12px;
        font-size: 12px;
    }
}
.btn{
        max-width: 235px;
}
/* Dropdown styles */
.dropdown .btn{
    border: 1px solid #6c757d;
}
.dropdown-toggle::after {
    margin-left: 0.5em;
}

.dropdown-menu {
    border-radius: 8px;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.dropdown-item {
    padding: 0.5rem 1rem;
    display: flex;
    align-items: center;
    transition: background-color 0.2s;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
}

.dropdown-item.text-danger:hover {
    background-color: #f8d7da;
}
.dropdown-toggle::after{
    content: '' !important;
    display: none !important;
}
.tab-btn{
    background-color: #eeeeee;
}
.dropdown-menu{
    padding: 0px !important;
}
.request-time.viewing {
  display: block;
  font-size: 12px;
  color: #666;
  margin-top: 2px;
}

.review-display {
    max-width: 250px;
}

.review-text {
    font-style: italic;
    color: #495057;
    background: #f8f9fa;
    padding: 8px 12px;
    border-radius: 6px;
    border-left: 3px solid #28a745;
    font-size: 13px;
    line-height: 1.4;
    word-break: break-word;
}

/* زر Add Review */
.btn-outline-success {
    border: 1px solid #28a745;
    color: #28a745;
    font-size: 13px;
    padding: 4px 12px;
    transition: all 0.3s ease;
}

.btn-outline-success:hover {
    background: #28a745;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(40, 167, 69, 0.2);
}

.btn-outline-success:active {
    transform: translateY(0);
}
.card.basic-data-table tbody tr td span:last-child {
    font-weight: 500 !important;
    font-size: 15px !important;
    color: #000 !important;
        display: block;

}
</style>