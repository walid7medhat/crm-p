<template>
    <div class="dashboard-main-body">
        <Breadcrumb title="My Orders" :breadcrumbs="[
            { name: 'My Orders' }
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
                    <div class="icon-field d-flex align-items-center" style="padding-bottom: 5px;">
                        <span class="me-13">Search:</span>
                        <div class="position-relative" style="width: 100%; max-width: 226px;">
                            <input type="text" class="form-control form-control-sm w-100 px-3 pe-5" placeholder="Search by name, email, property, status..." v-model="searchText"
                                style="border-radius: 10px; height: 2.5rem;" />
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
                                 <th @click="sortBy('reference_number')">Request Number</th>
                                <th v-if="hasShowAllColumn">Request From</th>
                                <th @click="sortBy('property_title')">Send To</th>
                                <th @click="sortBy('request_type')">Request Type</th>
                                <!-- <th @click="sortBy('status')">Status</th> -->
                                <th @click="sortBy('created_at')">Requested Date</th>
                                <th @click="sortBy('responded_at')">Response Date</th>
                                <th>Status</th>
                                <th>Review</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="order in paginatedOrders" :key="order.id">
                                 <td>{{order.reference_number}}</td>
                                <td v-if="hasShowAllColumn">
                                    <div class="d-flex align-items-center">
                                                    <img :src="avatarUrl(order.requested_by?.avatar)"
                                            :alt="getRequesterName(order)"
                                            class="w-40-px h-40-px rounded-circle flex-shrink-0 me-12 overflow-hidden"
                                            style="object-fit: cover;"
                                            @error="handleImageError"
                                        />
                                        <div class="flex-grow-1">
                                            
                                            <span class=" name text-md mb-0 fw-bolder text-primary-light d-block">{{ getRequesterName(order) }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                     <div class="d-flex align-items-center">
                                      <img :src="avatarUrl(order.listing?.agent_avatar)"
                                            :alt="order.listing?.agent || ''"
                                            class="w-40-px h-40-px rounded-circle flex-shrink-0 me-12 overflow-hidden"
                                            style="object-fit: cover;"
                                            @error="handleImageError"
                                        />
                                             <div class="d-flex flex-column">
                                              <span class="text-md mb-1 fw-bolder text-primary-light">
                                                  
                                                {{ order.listing.agent }}
                                              </span>
                                              <span class="text-sm text-secondary-light">
                                                {{ order.property_title }}
                                              </span>
                                            </div>
                                    </div>
                                
                                </td>
                                <td>
                                    <!--<span class="text-sm mb-0 fw-normal text-secondary-light d-block">{{ order.property_title }}</span>-->
                                    <span class="requestTypeClass(order.request_type)">
                                        {{ getRequestTypeLabel(order.request_type) }}
                                    </span>
                                    <small v-if="order?.request_type=='viewing'" class="request-time viewing">
                                      {{ order.formatted_date}}  at {{ order.formatted_time}}
                                    </small>
                                </td>
                                <!-- <td>
                                    <span :class="statusClass(order.status)" class="px-12 py-4 rounded fw-medium text-sm">
                                          {{ order.status === 'converted' ? 'sold out' : order.status }}
                                    </span>
                                </td> -->
                                <td>
                                    <span class="text-sm mb-0 fw-normal text-secondary-light d-block">{{ formatDate(order.created_at) }}</span>
                                </td>
                                <td>
                                    <span class="text-sm mb-0 fw-normal text-secondary-light d-block">
                                        {{ order.responded_at ? formatDate(order.responded_at) : 'Pending' }}
                                    </span>
                                </td>
                               <td>
                                    <div v-if="showActionDetails(order)" :class="getActionStatusClass(order.status)" class="px-16 py-8 rounded fw-medium status-reason text-sm border d-flex flex-column gap-2" style="min-width: 140px;">
                                        <!-- Status Text -->
                                        <div class="fw-bold">
                                            {{ getActionStatusText(order) }}
                                        </div>

                                        <div class="text-sm" v-if="getActionReasonText(order)">
                                            {{ getActionReasonText(order) }}
                                        </div>
                                        <span v-if="order.status === 'cancelled'"> {{ order.cancellation_reason }}</span>
                                    </div>

                                    <span v-else :class="getActionStatusClass(order.status)" class="px-12 py-4 rounded fw-medium text-sm">
                                        {{ getActionStatusText(order) }}
                                    </span>

                                    <button
                                        v-if="canCancelOrder(order)"
                                        class="btn btn-sm btn-outline-danger mt-2 cancel-order-btn"
                                        @click="cancelOrder(order)"
                                    >
                                        <i class="ri-close-circle-line me-1"></i> Cancel
                                    </button>
                                </td>
                                
                                 <td>
                                    <div class="debug-info d-none">
                                        User ID: {{ currentUserId }}, Request By: {{ order.request_by }}, 
                                        Can Review: {{ order.can_review }}, 
                                        Type: {{ order.request_type }}, Status: {{ order.status }}
                                    </div>
                                    
                                    <div v-if="order.request_type === 'viewing' && order.status === 'approved' && order.review && order.can_review">
                                        <div class="review-display">
                                            <div class="review-text mb-1">
                                                "{{ truncateText(order.review, 80) }}"
                                            </div>
                                            <small class="text-muted d-block">
                                                {{ formatDate(order.reviewed_at) }}
                                            </small>
                                            <button 
                                                class="btn btn-sm btn-link p-0 text-primary mt-1"
                                                @click="editReview(order)"
                                                title="Edit Review"
                                            >
                                                <i class="ri-edit-line me-1"></i> Edit
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div v-else-if="order.request_type === 'viewing' && order.status === 'approved' && order.can_review">
                                        <button class="btn btn-sm btn-outline-success" @click="addReview(order)">
                                            <i class="ri-add-line me-1"></i> Add Review
                                        </button>
                                    </div>
                                    
                                    <div v-else>
                                        <span class="text-muted">-</span>
                                        <small v-if="order.request_type === 'viewing' && order.status === 'approved'" 
                                               class="text-muted d-block">
                                            <template v-if="!order.can_review">
                                                Only the requester can review
                                            </template>
                                        </small>
                                    </div>
                                </td>
                                
                            </tr>
                        </tbody>
                    </table>

                    <!-- Empty State -->
                    <div v-if="!loading && filteredOrders.length === 0" class="text-center py-5">
                        <i class="ri-file-list-line" style="font-size: 64px; color: #6c757d;"></i>
                        <h5 class="mt-3">No Orders Found</h5>
                        <p class="text-muted">You haven't made any access requests yet.</p>
                        <router-link to="/properties" class="btn btn-primary mt-3">
                            <i class="ri-home-5-line me-2"></i>
                            Browse Properties
                        </router-link>
                    </div>

                    <!-- Loading State -->
                    <div v-if="loading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3">Loading orders...</p>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-24" v-if="!loading && filteredOrders.length > 0">
                        <span>
                            Showing {{ startIndex + 1 }} to {{ endIndex }} of {{ filteredOrders.length }} entries
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

        <!-- Order Details Modal -->
        <div v-if="showDetailsModal" class="modal-overlay" @click="showDetailsModal = false">
            <div class="modal-content" @click.stop style="max-width: 600px;">
                <div class="modal-header">
                    <h5 class="modal-title">Request Details</h5>
                    <button type="button" class="btn-close" @click="showDetailsModal = false"></button>
                </div>
                <div class="modal-body">
                    <div class="details-grid">
                        <div class="detail-item">
                            <label>Property:</label>
                            <span>{{ selectedOrder?.property_title }}</span>
                        </div>
                        <div class="detail-item">
                            <label>Request Type:</label>
                            <span>{{ getRequestTypeLabel(selectedOrder?.request_type) }}</span>
                        </div>
                        <div class="detail-item">
                            <label>Status:</label>
                            <span :class="statusClass(selectedOrder?.status)" class="px-8 py-2 rounded fw-medium text-sm">
                                {{ selectedOrder?.status }}
                            </span>
                        </div>
                        <div class="detail-item">
                            <label>Requested By:</label>
                            <span>{{ getRequesterName(selectedOrder) }}</span>
                        </div>
                        <div class="detail-item">
                            <label>Requested:</label>
                            <span>{{ formatDate(selectedOrder?.created_at) }}</span>
                        </div>
                        <div class="detail-item" v-if="selectedOrder?.responded_at">
                            <label>Responded:</label>
                            <span>{{ formatDate(selectedOrder?.responded_at) }}</span>
                        </div>
                        <div class="detail-item" v-if="selectedOrder?.owner_response">
                            <label>Owner Response:</label>
                            <span>{{ selectedOrder?.owner_response }}</span>
                        </div>
                        
                        <!-- Converted Data -->
                        <div v-if="selectedOrder?.status === 'converted'" class="converted-data-section">
                            <h6 class="section-title">Sold Out Information</h6>
                            <div class="converted-item">
                                <label>Sold Out By:</label>
                                <span>{{ selectedOrder?.converted_by || 'You' }}</span>
                            </div>
                            <div class="converted-item">
                                <label>Sold Out Date:</label>
                                <span>{{ formatDate(selectedOrder?.converted_at) }}</span>
                            </div>
                            <div class="converted-item" v-if="selectedOrder?.conversion_notes">
                                <label>Sold Out Notes:</label>
                                <span>{{ selectedOrder?.conversion_notes }}</span>
                            </div>
                        </div>
                        
                        <!-- Approved Data -->
                        <div v-if="selectedOrder?.status === 'approved'" class="approved-data-section">
                            <h6 class="section-title">Approved Information</h6>
                            
                            <div v-if="selectedOrder?.request_type === 'unit_number' && selectedOrder?.listing?.unit_number" class="approved-item">
                                <label>Unit Number:</label>
                                <span class="unit-number-value">{{ selectedOrder.listing.unit_number }}</span>
                            </div>
                            
                            <div v-if="selectedOrder?.request_type === 'owner_data' && selectedOrder?.owner" class="owner-details">
                                <div class="approved-item">
                                    <label>Owner Name:</label>
                                    <span>{{ selectedOrder.owner.name }}</span>
                                </div>
                                <div class="approved-item" v-if="selectedOrder.owner.phone">
                                    <label>Phone:</label>
                                    <span>{{ selectedOrder.owner.phone }}</span>
                                </div>
                                <div class="approved-item" v-if="selectedOrder.owner.email">
                                    <label>Email:</label>
                                    <span>{{ selectedOrder.owner.email }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="showDetailsModal = false">Close</button>
                    <button type="button" 
                            class="btn btn-success" 
                            @click="convertOrderFromModal(selectedOrder)"
                            v-if="selectedOrder?.status === 'approved'">
                   Sold Out
                    </button>
                    <button type="button" 
                            class="btn btn-primary" 
                            @click="viewPropertyFromModal(selectedOrder?.listing?.id)"
                            v-if="selectedOrder?.listing?.id">
                        View Property
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
                        By marking this request as Sold Out, you confirm that you have successfully used the provided information.
                    </div>
                    <div class="form-group">
                        <label class="form-label">Sold Out Notes (Optional)</label>
                        <textarea 
                            v-model="conversionNotes" 
                            class="form-control" 
                            rows="4" 
                            placeholder="Add any notes about the conversion outcome..."
                        ></textarea>
                        <small class="text-muted">Example: Contacted owner, scheduled meeting, etc.</small>
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
<!-- Review Modal -->
<div v-if="showReviewModal" class="modal-overlay" @click="closeReviewModal">
    <div class="modal-content" @click.stop style="max-width: 500px;">
        <div class="modal-header">
            <h5 class="modal-title">
                <i class="ri-star-line me-2"></i>
                {{ isEditingReview ? 'Edit Review' : 'Add Review' }}
            </h5>
            <button type="button" class="btn-close" @click="closeReviewModal"></button>
        </div>
        <div class="modal-body">
            <div v-if="selectedReviewOrder" class="review-summary mb-4 p-3 bg-light rounded">
                <h6 class="mb-2">Viewing Details:</h6>
                <div class="row">
                    <div class="col-6">
                        <small class="text-muted">Property:</small>
                        <p class="mb-0 fw-semibold">{{ selectedReviewOrder.property_title }}</p>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">Agent:</small>
                        <p class="mb-0 fw-semibold">{{ selectedReviewOrder.listing?.agent }}</p>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-6">
                        <small class="text-muted">Date:</small>
                        <p class="mb-0">{{ selectedReviewOrder.formatted_date }}</p>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">Time:</small>
                        <p class="mb-0">{{ selectedReviewOrder.formatted_time }}</p>
                    </div>
                </div>
            </div>
            
            <form @submit.prevent="submitReview">
                <div class="form-group">
                    <label class="form-label fw-semibold">Your Review *</label>
                    <textarea 
                        v-model="reviewText" 
                        class="form-control" 
                        rows="5" 
                        placeholder="Share your viewing experience..."
                        required
                        maxlength="500"
                        :class="{ 'is-invalid': reviewText.length > 500 }"
                    ></textarea>
                    <div class="d-flex justify-content-between mt-1">
                        <small class="text-muted">Maximum 500 characters</small>
                        <small :class="{'text-danger': reviewText.length > 500, 'text-muted': reviewText.length <= 500}">
                            {{ reviewText.length }}/500
                        </small>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="closeReviewModal">
                <i class="ri-close-line me-1"></i> Cancel
            </button>
            
            <button 
                type="button" 
                class="btn btn-primary" 
                @click="submitReview"
                :disabled="!reviewText.trim() || reviewText.length > 500"
            >
                <i class="ri-send-plane-line me-1"></i>
                {{ isEditingReview ? 'Update' : 'Submit' }}
            </button>
        </div>
    </div>
</div>

<!-- Review Notification Modal -->
<div v-if="showReviewNotification" class="modal-overlay notification-overlay" @click="closeReviewNotification">
    <div class="modal-content notification-content" @click.stop style="max-width: 500px;">
        <div class="modal-header bg-primary text-white">
            <h5 class="modal-title">
                <i class="ri-star-fill me-2"></i>
                Pending Reviews Required
            </h5>
            <button type="button" class="btn-close btn-close-white" @click="closeReviewNotification"></button>
        </div>
        <div class="modal-body">
            <div class="alert alert-info">
                <i class="ri-information-line me-2"></i>
                <strong>You have {{ pendingReviewOrders.length }} viewing request(s) that need your review!</strong>
            </div>
            
            <div class="review-list">
                <div v-for="(order, index) in pendingReviewOrders" :key="order.id" 
                     class="review-item p-3 mb-2 border rounded">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">{{ order.property_title }}</h6>
                            <small class="text-muted d-block">
                                <i class="ri-calendar-line me-1"></i>
                                {{ order.formatted_date }} at {{ order.formatted_time }}
                            </small>
                            <small class="text-muted d-block">
                                <i class="ri-user-line me-1"></i>
                                Agent: {{ order.listing?.agent }}
                            </small>
                            <small class="text-muted d-block">
                                <i class="ri-file-list-line me-1"></i>
                                Request #: {{ order.reference_number }}
                            </small>
                        </div>
                        <button class="btn btn-sm btn-success" @click="openReviewFromNotification(order)">
                            <i class="ri-pencil-line me-1"></i>
                            Add Review
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="mt-3 text-center">
                <button class="btn btn-link text-decoration-none" @click="remindMeLater">
                    <i class="ri-time-line me-1"></i>
                    Remind me later
                </button>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="closeReviewNotification">
                Close
            </button>
            <button type="button" class="btn btn-primary" @click="reviewAllNow" 
                    v-if="pendingReviewOrders.length > 1">
                <i class="ri-star-s-fill me-1"></i>
                Review All Now
            </button>
        </div>
    </div>
</div>
   </div>     
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import Swal from 'sweetalert2'
import api from '@/plugins/axios'

const defaultAvatar = '/assets/images/user.png'
const filtericon = '/assets/images/filter.png'

/** Same as UsersTable / AllRequests: Laravel asset() may use wrong host vs SPA for /storage/ URLs */
function resolveMediaUrl(url) {
    if (!url || typeof url !== 'string') return null
    try {
        const parsed = new URL(url, typeof window !== 'undefined' ? window.location.origin : undefined)
        const path = parsed.pathname + parsed.search
        if (!path.includes('/storage/')) {
            return parsed.href
        }
        const badLocal = /^(127\.0\.0\.1|localhost)$/i.test(parsed.hostname)
        const pageOrigin = typeof window !== 'undefined' ? window.location.origin : ''
        if (!pageOrigin) return parsed.href
        if (badLocal || parsed.origin !== pageOrigin) {
            return `${pageOrigin}${path}`
        }
        return parsed.href
    } catch {
        if (typeof window === 'undefined') return url
        if (url.startsWith('/')) return `${window.location.origin}${url}`
        return `${window.location.origin}/storage/${url.replace(/^\/+/, '')}`
    }
}

function avatarUrl(raw) {
    if (!raw) return defaultAvatar
    return resolveMediaUrl(raw) || defaultAvatar
}

function handleImageError(event) {
    const el = event.target
    if (el.dataset.avatarFallback === '1') return
    el.dataset.avatarFallback = '1'
    el.src = defaultAvatar
}

// Router
const router = useRouter()

// Data
const orders = ref([])
const loading = ref(true)
const selectedShow = ref(10)
const currentPage = ref(1)
const searchText = ref('')
const sortedBy = ref('')
const sortAsc = ref(true)
const activeFilter = ref('all')
const showDetailsModal = ref(false)
const selectedOrder = ref(null)
const showConvertModal = ref(false)
const conversionNotes = ref('')
const currentOrderId = ref(null)

// Review variables
const showReviewModal = ref(false)
const selectedReviewOrder = ref(null)
const reviewText = ref('')
const isEditingReview = ref(false)
const currentUserId = ref(null)

// Real-time updates
const echoListeners = ref([])
const pollingInterval = ref(null)


// Notification variables
const showReviewNotification = ref(false)
const pendingReviewOrders = ref([])
const notificationReminded = ref(false)


const filterTabs = computed(() => {
    const tabs = [
        { label: 'All', value: 'all' },
        { label: 'Pending', value: 'pending' },
        { label: 'Approved', value: 'approved' },
        { label: 'Sold Out', value: 'converted' },
        { label: 'Rejected', value: 'rejected' },
        { label: 'Cancelled', value: 'cancelled' }
    ]
    
    // إذا كان هناك طلبات تحتاج تقييم، أضف خاصية hasPending
    const pendingReviews = orders.value.filter(order => 
        order.request_type === 'viewing' && 
        order.status === 'approved' && 
        order.can_review && 
        !order.review
    ).length
    
    if (pendingReviews > 0) {
        tabs.forEach(tab => {
            if (tab.value === 'all' || tab.value === 'approved') {
                tab.hasPending = true
            }
        })
    }
    
    return tabs
})

// Computed
const hasShowAllColumn = computed(() => {
    return orders.value.some(order => order.show_all_column === true)
})

const filteredOrders = computed(() => {
    let filtered = orders.value
    
    // Apply status filter
    if (activeFilter.value !== 'all') {
        filtered = filtered.filter(order => order.status === activeFilter.value)
    }
    
    // Apply search filter
    const keyword = searchText.value.toLowerCase()
    if (keyword) {
        filtered = filtered.filter(order =>
            order.property_title?.toLowerCase().includes(keyword) ||
            order.request_type?.toLowerCase().includes(keyword) ||
            order.status?.toLowerCase().includes(keyword) ||
            getRequesterName(order).toLowerCase().includes(keyword) || 
            order.reference_number?.toLowerCase().includes(keyword)
        )
    }
    
    return filtered
})

const sortedOrders = computed(() => {
    if (!sortedBy.value) return filteredOrders.value
    
    return [...filteredOrders.value].sort((a, b) => {
        let valA, valB
        
        switch (sortedBy.value) {
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
            default:
                valA = ''
                valB = ''
        }
        
        return (valA > valB ? 1 : -1) * (sortAsc.value ? 1 : -1)
    })
})

const totalPages = computed(() =>
    Math.ceil(sortedOrders.value.length / selectedShow.value)
)

const paginatedOrders = computed(() => {
    const start = (currentPage.value - 1) * selectedShow.value
    return sortedOrders.value.slice(start, start + selectedShow.value)
})

const startIndex = computed(() => (currentPage.value - 1) * selectedShow.value)
const endIndex = computed(() =>
    Math.min(startIndex.value + selectedShow.value, filteredOrders.value.length)
)

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
            return 'bg-success-focus text-success-main'
        case 'rejected':
            return 'bg-danger-focus text-danger-main'
        case 'pending':
            return ' bg-info-focus text-secondary-main'
        case 'cancelled':
            return 'bg-warning-focus text-warning-main'
        case 'converted':
            return 'bg-success-focus text-success-main'
        default:
            return 'bg-gray-50 text-gray-600'
    }
}

function requestTypeClass(type) {
    switch (type) {
        case 'unit_number':
            return 'bg-info-focus text-info-main'
        case 'owner_data':
            return 'bg-purple-50 text-purple-600'
        default:
            return 'bg-gray-50 text-gray-600'
    }
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
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

function getTabCount(status) {
    if (status === 'all') return orders.value.length
    return orders.value.filter(order => order.status === status).length
}

function getRequesterName(order) {
    return order.requested_by?.name || 'You'
}

function getRequesterEmail(order) {
    return order.requested_by?.email || 'N/A'
}

function getRequesterAvatar(order) {
    return avatarUrl(order.requested_by?.avatar)
}

function truncateText(text, maxLength) {
    if (!text) return ''
    if (text.length <= maxLength) return text
    return text.substring(0, maxLength) + '...'
}

// Review Methods
function addReview(order) {
    selectedReviewOrder.value = order
    reviewText.value = ''
    isEditingReview.value = false
    showReviewModal.value = true
}

function editReview(order) {
    selectedReviewOrder.value = order
    reviewText.value = order.review || ''
    isEditingReview.value = true
    showReviewModal.value = true
}

function closeReviewModal() {
    showReviewModal.value = false
    selectedReviewOrder.value = null
    reviewText.value = ''
    isEditingReview.value = false
}
// Notification Methods
function openReviewFromNotification(order) {
     showReviewNotification.value = false
    addReview(order)
   
}

function closeReviewNotification() {
    showReviewNotification.value = false
    notificationReminded.value = true
}

function remindMeLater() {
    showReviewNotification.value = false
    notificationReminded.value = true
    
    // إعادة الإشعار بعد ساعة
    setTimeout(() => {
        notificationReminded.value = false
        checkPendingReviews()
    }, 60 * 60 * 1000) // 60 دقيقة
    
    Swal.fire({
        title: 'Reminder Set!',
        text: 'We\'ll remind you in 1 hour',
        icon: 'info',
        timer: 2000,
        showConfirmButton: false
    })
}

function reviewAllNow() {
    showReviewNotification.value = false
    
    if (pendingReviewOrders.value.length > 0) {
        addReview(pendingReviewOrders.value[0])
    }
}

async function submitReview() {
    if (!reviewText.value.trim()) {
        Swal.fire({
            title: 'Missing Review',
            text: 'Please provide your review text',
            icon: 'warning',
            confirmButtonColor: '#0B0736'
        })
        return
    }

    if (reviewText.value.length > 500) {
        Swal.fire({
            title: 'Review Too Long',
            text: 'Review cannot exceed 500 characters',
            icon: 'warning',
            confirmButtonColor: '#0B0736'
        })
        return
    }

    try {
        const endpoint = isEditingReview.value 
            ? `/listings/access-requests/${selectedReviewOrder.value.id}/review`
            : `/listings/access-requests/${selectedReviewOrder.value.id}/review`
        
        const response = await api.post(endpoint, {
            review: reviewText.value.trim()
        })
        
        if (response.data.status) {
            Swal.fire({
                title: 'Success!',
                text: isEditingReview.value ? 'Review updated successfully' : 'Review submitted successfully',
                icon: 'success',
                confirmButtonColor: '#0B0736',
                timer: 1500,
                showConfirmButton: false
            })
            
            closeReviewModal()
            await fetchMyOrders()
            
            checkPendingReviews()
        } else {
            throw new Error(response.data.message || 'Failed to submit review')
        }
    } catch (err) {
        console.error('Error submitting review:', err)
        Swal.fire({
            title: 'Error!',
            text: err.response?.data?.message || 'Failed to submit review',
            icon: 'error',
            confirmButtonColor: '#0B0736'
        })
    }
}

async function deleteReview() {
    const result = await Swal.fire({
        title: 'Delete Review?',
        text: 'Are you sure you want to delete this review?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    })

    if (!result.isConfirmed) return

    try {
        const response = await api.delete(`/listings/access-requests/${selectedReviewOrder.value.id}/review`)
        
        if (response.data.status) {
            Swal.fire({
                title: 'Deleted!',
                text: 'Review deleted successfully',
                icon: 'success',
                confirmButtonColor: '#0B0736',
                timer: 1500,
                showConfirmButton: false
            })
            
            closeReviewModal()
            await fetchMyOrders()
        }
    } catch (err) {
        console.error('Error deleting review:', err)
        Swal.fire({
            title: 'Error!',
            text: 'Failed to delete review',
            icon: 'error',
            confirmButtonColor: '#0B0736'
        })
    }
}

// Navigation Methods
function viewProperty(propertyId) {
    if (propertyId) {
        router.push(`/property-details/${propertyId}`)
    } else {
        Swal.fire({
            title: 'Error!',
            text: 'Property not found',
            icon: 'error',
            confirmButtonColor: '#0B0736'
        })
    }
}

function viewPropertyFromModal(propertyId) {
    if (propertyId) {
        showDetailsModal.value = false
        router.push(`/property-details/${propertyId}`)
    } else {
        Swal.fire({
            title: 'Error!',
            text: 'Property not found',
            icon: 'error',
            confirmButtonColor: '#0B0736'
        })
    }
}

// API Methods
async function fetchMyOrders() {
    try {
        loading.value = true
        const response = await api.get('/listings/access-requests/my-orders')
        
        console.log('📊 API Response:', response.data)
        
        if (response.data.status) {
            orders.value = response.data.data.map(order => ({
                ...order,
                property_title: order.listing?.title || 'Unknown Property'
            }))
            console.log('✅ MyOrders loaded:', orders.value.length, 'orders')
               checkPendingReviews()
        } else {
            throw new Error(response.data.message || 'Failed to fetch orders')
        }
    } catch (err) {
        console.error('Error fetching orders:', err)
        Swal.fire({
            title: 'Error!',
            text: 'Failed to load orders',
            icon: 'error',
            confirmButtonColor: '#0B0736'
        })
    } finally {
        loading.value = false
    }
}

async function refreshOrders() {
    await fetchMyOrders()
    Swal.fire({
        title: 'Refreshed!',
        text: 'Orders list updated',
        icon: 'success',
        timer: 1500,
        showConfirmButton: false
    })
}

/** Current user can act as privileged canceller of approved viewings. */
function isPrivilegedCanceller() {
    try {
        const user = JSON.parse(localStorage.getItem('user') || 'null')
        if (!user) return false
        const roles = user.roles || []
        if (roles.includes('super_admin') || roles.includes('admin')) return true
        if (roles.includes('manager') && Number(user.listing_team) === 1) return true
        return false
    } catch {
        return false
    }
}

/** Whether the cancel button should appear for this row.
 *  - viewing + pending/in_progress → requester (anyone) can cancel
 *  - viewing + approved            → only manager(listing_team=1) / admin / super_admin
 *  - owner_data / unit_number      → only pending / in_progress
 */
function canCancelOrder(order) {
    if (!order) return false
    if (order.request_type === 'viewing') {
        if (order.status === 'approved') return isPrivilegedCanceller()
        return ['pending', 'in_progress'].includes(order.status)
    }
    return ['pending', 'in_progress'].includes(order.status)
}

async function cancelOrder(order) {
    if (!order) return
    // Backend route /listings/access-requests/{id}/cancel resolves the id as listing_id.
    const listingId = order.listing?.id || order.listing_id
    if (!listingId) {
        Swal.fire({
            title: 'Error!',
            text: 'Cannot cancel: listing not found on this request.',
            icon: 'error',
            confirmButtonColor: '#01062d',
        })
        return
    }

    const result = await Swal.fire({
        title: 'Cancel Request?',
        text: order.request_type === 'viewing'
            ? 'Are you sure you want to cancel this viewing?'
            : 'Are you sure you want to cancel this request?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, cancel it!',
        cancelButtonText: 'Keep Request',
        input: 'textarea',
        inputLabel: 'Reason (optional)',
        inputPlaceholder: 'Why are you cancelling?',
    })

    if (!result.isConfirmed) return

    try {
        const response = await api.post(`/listings/access-requests/${listingId}/cancel`, {
            request_type: order.request_type,
            cancellation_reason: result.value || undefined,
        })

        if (response.data.status) {
            Swal.fire({
                title: 'Cancelled!',
                text: 'Request cancelled successfully',
                icon: 'success',
                confirmButtonColor: '#01062d',
                timer: 1500,
                showConfirmButton: false,
            })
            await fetchMyOrders()
        } else {
            throw new Error(response.data.message || 'Failed to cancel request')
        }
    } catch (err) {
        console.error('Error cancelling order:', err)
        Swal.fire({
            title: 'Error!',
            text: err?.response?.data?.message || 'Failed to cancel request',
            icon: 'error',
            confirmButtonColor: '#0B0736'
        })
    }
}

function viewOrderDetails(order) {
    selectedOrder.value = order
    showDetailsModal.value = true
}

function convertOrder(order) {
    currentOrderId.value = order.id
    conversionNotes.value = ''
    showConvertModal.value = true
}

function convertOrderFromModal(order) {
    showDetailsModal.value = false
    currentOrderId.value = order.id
    conversionNotes.value = ''
    showConvertModal.value = true
}

async function confirmConvert() {
    try {
        const response = await api.put(
            `/listings/access-requests/${currentOrderId.value}/convert`,
            {
                conversion_notes: conversionNotes.value
            }
        )

        if (response.data.status) {
            Swal.fire({
                title: 'Success!',
                text: 'Request marked as Sold Out successfully',
                icon: 'success',
                confirmButtonColor: '#0B0736'
            })
            showConvertModal.value = false
            await fetchMyOrders()
        } else {
            throw new Error(response.data.message || 'Failed to mark as Sold Out')
        }
    } catch (err) {
        console.error('Error converting order:', err)
        Swal.fire({
            title: 'Error!',
            text: 'Failed to mark as Sold Out',
            icon: 'error',
            confirmButtonColor: '#0B0736'
        })
    }
}

// Status Display Methods
function getActionStatusText(order) {
    switch(order.status) {
        case 'approved':
            return order.owner_response ? 'Approved' : 'Approved';
        case 'rejected':
            return order.owner_response ? 'Rejected' : 'Declined';
        case 'pending':
            return 'Pending';
        case 'in_progress':
            return 'In Progress'
        case 'converted':
            return 'Sold Out';
        default:
            return order.status.charAt(0).toUpperCase() + order.status.slice(1);
    }
}

function getActionReasonText(order) {
    if (order.owner_response && order.owner_response !== null && order.owner_response !== 'NULL') {
        return order.owner_response;
    }
    
    if (order.reason) {
        return order.reason;
    }
    
    if (order.status === 'converted' && order.conversion_notes) {
        return order.conversion_notes;
    }
    
    switch(order.status) {
        case 'pending':
            return 'Pending final review';
        case 'in_progress':
            return 'Request is in progress';
        case 'approved':
            return 'Request has been approved';
        case 'rejected':
            return 'Request has been declined';
        case 'converted':
            return 'Unit has been sold';
        default:
            return null;
    }
}

function getActionStatusClass(status) {
    switch(status) {
        case 'approved':
            return 'bg-success-light text-success border-success';
        case 'rejected':
        case 'declined':
            return 'bg-danger-light text-danger border-danger';
        case 'pending':
            return 'bg-warning-light text-warning border-warning';
        case 'in_progress':
            return 'bg-info-light text-info border-info';
        case 'converted':
            return 'bg-info-light text-info border-info';
        default:
            return 'bg-secondary-light text-secondary border-secondary';
    }
}

function showActionDetails(order) {
    return (order.owner_response && order.owner_response !== null && order.owner_response !== 'NULL') ||
           order.reason ||
           (order.status === 'converted' && order.conversion_notes) ||
           order.status === 'approved' || order.status === 'in_progress'||
           order.status === 'rejected';
}

function hasActions(order) {
    return (
        (order.status === 'pending' && order.can_cancel) ||
        (order.status === 'approved') ||
        (order.status === 'approved' || order.status === 'converted') ||
        (order.listing?.id)
    );
}

// Real-time Updates Methods
const initializeRealTimeUpdates = () => {
    const user = JSON.parse(localStorage.getItem('user'))
    if (!user || !window.Echo) {
        console.log('❌ Real-time updates not available, using polling...')
        startPolling()
        return
    }

    console.log('🔔 MyOrders: Initializing real-time updates for user:', user.id)

    try {
        // Listen for order updates (when owner responds to my requests)
        const listener = window.Echo.private(`user.${user.id}`)
            .listen('.access.request.updated', (event) => {
                console.log('🎉 MyOrders: Real-time update received:', event)
                handleRealTimeUpdate(event)
            })
            .error((error) => {
                console.error('❌ Echo error:', error)
                startPolling() // Fallback to polling
            })

        echoListeners.value.push(listener)
    } catch (error) {
        console.error('❌ Failed to initialize Echo:', error)
        startPolling() // Fallback to polling
    }
}

const handleRealTimeUpdate = (event) => {
    // If it's a response to my request
    if (event.action_type === 'responded' && event.requested_by === getCurrentUserId()) {
        console.log('🔄 My request was responded to via real-time')
        fetchMyOrders()
        showResponseNotification(event)
    }
    
    // If my request was cancelled
    if (event.action_type === 'cancelled' && event.requested_by === getCurrentUserId()) {
        console.log('❌ My request was cancelled via real-time')
        fetchMyOrders()
        showCancelledNotification(event)
    }

    // If my request was converted
    if (event.action_type === 'converted' && event.requested_by === getCurrentUserId()) {
        console.log('✅ My request was Sold Out via real-time')
        fetchMyOrders()
        showConvertedNotification(event)
    }
}

const startPolling = () => {
    console.log('🔄 MyOrders: Starting polling every 10 seconds')
    pollingInterval.value = setInterval(async () => {
        await fetchMyOrders()
    }, 10000)
}

const showResponseNotification = (event) => {
    const message = event.status === 'approved' 
        ? `Your ${event.request_type} request was approved! 🎉` 
        : `Your ${event.request_type} request was rejected`

    const icon = event.status === 'approved' ? 'success' : 'warning'

    Swal.fire({
        title: 'Request Updated!',
        text: message,
        icon: icon,
        timer: 4000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    })
}

const showCancelledNotification = (event) => {
    Swal.fire({
        title: 'Request Cancelled',
        text: `Your ${event.request_type} request was cancelled`,
        icon: 'info',
        timer: 3000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    })
}

const showConvertedNotification = (event) => {
    Swal.fire({
        title: 'Request Sold Out!',
        text: `Your ${event.request_type} request was marked as Sold Out`,
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
    // Cleanup Echo listeners
    echoListeners.value.forEach(listener => {
        if (listener && typeof listener.stopListening === 'function') {
            listener.stopListening('.access.request.updated')
        }
    })
    echoListeners.value = []

    // Cleanup polling
    if (pollingInterval.value) {
        clearInterval(pollingInterval.value)
        pollingInterval.value = null
    }
}
function checkPendingReviews() {
    const pending = orders.value.filter(order => 
        order.request_type === 'viewing' && 
        order.status === 'approved' && 
        order.can_review && 
        !order.review
    )
    
    pendingReviewOrders.value = pending
    
    if (pending.length > 0 && !notificationReminded.value) {
        showReviewNotification.value = true
    }
}
// Lifecycle
onMounted(() => {
    // Get current user ID
    const user = JSON.parse(localStorage.getItem('user'))
    currentUserId.value = user?.id
    
    // Fetch orders
    fetchMyOrders()
    
    // Initialize real-time updates
    setTimeout(() => {
        initializeRealTimeUpdates()
    }, 1000)
     setTimeout(() => {
        checkPendingReviews()
    }, 2000)
})

onUnmounted(() => {
    cleanup()
})
</script>

<style scoped>
/* Tab count badges */
.tab-count {
    background: #0B0736;
    color: white;
    border-radius: 12px;
    padding: 2px 8px;
    font-size: 11px;
    margin-left: 6px;
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
}

.tab-btn.active {
    background: #0B0736;
    color: white;
}

.tab-btn:hover:not(.active) {
    background: #f8f9fa;
}

/* Refresh button styles */
.btn-outline-primary {
    border: 1px solid #0B0736;
    color: #0B0736;
    background: transparent;
}

.btn-outline-primary:hover:not(:disabled) {
    background: #0B0736;
    color: white;
}

.btn-outline-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Modal styles */
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
    z-index: 1050;
}

.modal-content {
    background: white;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.modal-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: between;
    align-items: center;
}

.modal-title {
    margin: 0;
    font-size: 1.25rem;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid #dee2e6;
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

.details-grid {
    display: grid;
    gap: 1rem;
}

.detail-item {
    display: flex;
    justify-content: between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f8f9fa;
}

.detail-item label {
    font-weight: 600;
    min-width: 120px;
    color: #495057;
}

.detail-item span {
    flex: 1;
    text-align: right;
}

.converted-data-section {
    margin-top: 1rem;
    padding: 1rem;
    background: #f8fff8;
    border-radius: 6px;
    border-left: 3px solid #28a745;
}

.converted-item {
    display: flex;
    justify-content: between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.converted-item label {
    font-weight: 500;
    min-width: 120px;
    color: #28a745;
}

.approved-data-section {
    margin-top: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 6px;
}

.section-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
    color: #495057;
}

.approved-item {
    display: flex;
    justify-content: between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.approved-item label {
    font-weight: 500;
    min-width: 100px;
    color: #6c757d;
}

.unit-number-value {
    font-weight: 600;
    color: #0B0736;
    font-size: 1.1rem;
}

/* Alert styles */
.alert {
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 16px;
}

.alert-info {
    background: #e7f3ff;
    border: 1px solid #b3d9ff;
    color: #0066cc;
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
.bg-success-light { background-color: rgba(40, 167, 69, 0.1); }
.bg-danger-light { background-color: rgba(220, 53, 69, 0.1); }
.bg-warning-light { background-color: rgba(255, 193, 7, 0.1); }
.bg-info-light { background-color: rgba(23, 162, 184, 0.1); }
.bg-secondary-light { background-color: rgba(108, 117, 125, 0.1); }

.text-success { color: #28a745; }
.text-danger { color: #dc3545; }
.text-warning { color: #ffc107; }
.text-info { color: #17a2b8; }
.text-secondary { color: #6c757d; }

.border-success { border: 1px solid #28a745; }
.border-danger { border: 1px solid #dc3545; }
.border-warning { border: 1px solid #ffc107; }
.border-info { border: 1px solid #17a2b8; }
.border-secondary { border: 1px solid #6c757d; }

.status-reason{
    background: none !important;
}
.name{
        font-size: 14px !important;
}
.request-time.viewing {
  display: block;
  font-size: 10px;
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

/* Review Modal */
.review-summary {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
}

.review-summary h6 {
    color: #0B0736;
    font-size: 14px;
    font-weight: 600;
}

.review-summary p {
    font-size: 13px;
    margin-bottom: 0;
}

/* Textarea focus */
.form-control:focus {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

.form-control.is-invalid {
    border-color: #dc3545;
}

/* Character counter */
.text-danger {
    font-weight: 500;
}

/* Modal responsive */
@media (max-width: 768px) {
    .review-display {
        max-width: 200px;
    }
    
    .review-text {
        font-size: 12px;
        padding: 6px 10px;
    }
}

/* Table cell alignment */
td {
    vertical-align: middle !important;
}
/* Notification Modal Styles */
.notification-overlay {
    background: rgba(0, 0, 0, 0.7);
    z-index: 1060;
}
.notification-overlay .modal-header{
        background-color: #111827 !important;
    color: #fff !important;
}
.notification-overlay .modal-header h5{
    color: #fff !important;
    font-size:25px !important;
}
.notification-content {
    animation: slideInDown 0.3s ease-out;
}

@keyframes slideInDown {
    from {
        transform: translateY(-30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.review-item {
    background: #f8f9fa;
    transition: all 0.3s ease;
}

.review-item:hover {
    background: #e9ecef;
    transform: translateX(5px);
}

/* Badge for pending reviews in tab */
.tab-btn.has-pending .tab-count {
    background: #dc3545;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
    }
}

.tab-btn.all.has-pending {
    position: relative;
}

.tab-btn.all.has-pending::after {
    content: '';
    position: absolute;
    top: 5px;
    right: 5px;
    width: 8px;
    height: 8px;
    background: #dc3545;
    border-radius: 50%;
    animation: blink 1s infinite;
}

@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
.card.basic-data-table tbody tr td span:last-child {
    font-weight: 500 !important;
    font-size: 15px !important;
    color: #000 !important;
}

/* Cancel button in the status column */
.cancel-order-btn {
    border: 1px solid #dc3545;
    color: #dc3545;
    background: transparent;
    font-size: 12px;
    padding: 4px 10px;
    border-radius: 6px;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
}
.cancel-order-btn:hover {
    background: #dc3545;
    color: #fff;
}
.swal2-input-label{
    margin: 0 !important;
}
</style>