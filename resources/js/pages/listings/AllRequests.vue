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
                            <SearchableSelect preset="perPage10_15_20" v-model="selectedShow" :clearable="false" inline class="w-auto me-10" :input-style="{ borderRadius: '10px', height: '2.4rem', minWidth: '5.5rem' }" />
                            <span>entries per page</span>
                        </div>
                    </div>
                    <div class="icon-field d-flex align-items-center" style="padding-bottom: 5px;">
                        <span class="me-13">Search:</span>
                        <div class="position-relative" style="width: 100%; max-width: 226px;">
                            <input type="text" class="form-control form-control-sm w-100 px-3 pe-5" placeholder="Search by name, email,reference_number, property, status..."  v-model="searchText"
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
                                    <div class="d-flex align-items-center"  style="cursor: pointer;" @click="goToUser(order.requested_by?.id)">
                                                    <img :src="avatarUrl(order.requested_by?.avatar)"
                                            :alt="getRequesterName(order)"
                                            class="w-40-px h-40-px rounded-circle flex-shrink-0 me-12 overflow-hidden"
                                            style="object-fit: cover;"
                                            @error="handleImageError"
                                        />
                                        <div class="d-flex flex-column">
                                 
                                            <span class=" name text-md mb-0 fw-bolder text-primary-light d-block">{{ getRequesterName(order) }}</span>
                                        </div>
                                    </div>
                                </td>
                               <td>
                                  <div class="d-flex align-items-center" >
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
                                    <span :class="requestTypeClass(order.request_type)" class="px-12 py-4 rounded fw-medium text-sm">
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
                                            <br/>
                                                <span v-if="order.status === 'cancelled'"> {{ order.cancellation_reason }}</span>
                                             
                                        </span>
                                        
                                </td>
                                      <td>
                                        <div v-if="order.review">
                                            <div class="review-display">
                                                <div class="review-text mb-1">
                                                    "{{ truncateText(order.review, 60) }}"
                                                </div>
                                                <small class="text-muted d-block">
                                                    By: {{ order.reviewed_by?.name || 'Requester' }}
                                                </small>
                                                <small class="text-muted d-block">
                                                    {{ formatDate(order.reviewed_at) }}
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <div v-else-if="order.request_type === 'viewing' && order.status === 'approved'">
                                            <span class="text-muted small">
                                                <i class="ri-star-line me-1"></i>
                                                No review yet
                                            </span>
                                        </div>
                                        
                                        <div v-else>
                                            <span class="text-muted">-</span>
                                        </div>
                                    </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Empty State -->
                    <div v-if="!loading && filteredOrders.length === 0" class="text-center py-5">
                        <i class="ri-file-list-line" style="font-size: 64px; color: #6c757d;"></i>
                        <h6 class="ui-h-mini mt-3">No Orders Found</h6>
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
                    <h6 class="ui-h-mini modal-title">Request Details</h6>
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
                    <h6 class="ui-h-mini modal-title">Mark as Sold Out</h6>
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
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import Swal from 'sweetalert2'
import api from '@/plugins/axios'

const defaultAvatar = '/assets/images/user.png'
const filtericon= '/assets/images/filter.png'

/** Same logic as UsersTable: Laravel asset() may use wrong host vs SPA origin for /storage/ URLs */
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
const hasShowAllColumn = computed(() => {
    return orders.value.some(order => order.show_all_column === true)
})

const echoListeners = ref([])
const pollingInterval = ref(null)

const filterTabs = [
    { label: 'All', value: 'all' },
    { label: 'Pending', value: 'pending' },
    { label: 'Approved', value: 'approved' },
    { label: 'Sold Out', value: 'converted' },
    { label: 'Rejected', value: 'rejected' },
    { label: 'Cancelled', value: 'cancelled' }
]

const filteredOrders = computed(() => {
    let filtered = orders.value
    
    if (activeFilter.value !== 'all') {
        filtered = filtered.filter(order => order.status === activeFilter.value)
    }
    
    const keyword = searchText.value.toLowerCase()
    if (keyword) {
        filtered = filtered.filter(order =>
            order.property_title?.toLowerCase().includes(keyword) ||
            order.request_type?.toLowerCase().includes(keyword) ||
            order.status?.toLowerCase().includes(keyword) ||
            getRequesterName(order).toLowerCase().includes(keyword) || order.reference_number?.toLowerCase().includes(keyword) 
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
function truncateText(text, maxLength) {
    if (!text) return ''
    if (text.length <= maxLength) return text
    return text.substring(0, maxLength) + '...'
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

function viewProperty(propertyId) {
    if (propertyId) {
        router.push(`/property-details/${propertyId}`)
    } else {
        Swal.fire({
            title: 'Error!',
            text: 'Property not found',
            icon: 'error',
            confirmButtonColor: '#01062d'
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
            confirmButtonColor: '#01062d'
        })
    }
}
function  goToUser(userId) {
        if (userId) {
            router.push(`/users/${userId}`);
        }
    }
async function fetchMyOrders() {
    try {
        loading.value = true
        const response = await api.get('/listings/access-requests/my-orders')
        
        console.log('📊 API Response:', response.data)
        
        if (response.data.status) {
            orders.value = response.data.data.map(order => ({
                ...order,
                property_title: order.listing?.title || 'Unknown Property',
                requester_name: order.requested_by?.name || 'Unknown',
                requester_avatar: order.requested_by?.avatar || '' 
            }))
            console.log('✅ MyOrders loaded:', orders.value.length, 'orders')
        } else {
            throw new Error(response.data.message || 'Failed to fetch orders')
        }
    } catch (err) {
        console.error('Error fetching orders:', err)
        Swal.fire({
            title: 'Error!',
            text: 'Failed to load orders',
            icon: 'error',
            confirmButtonColor: '#01062d'
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

async function cancelOrder(orderId,request_type) {
    const result = await Swal.fire({
        title: 'Cancel Request?',
        text: 'Are you sure you want to cancel this request?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, cancel it!',
        cancelButtonText: 'Keep Request'
    })

    if (!result.isConfirmed) return

    try {
        const response = await api.post(`/listings/access-requests/${orderId}/cancel` ,{
    request_type: request_type
})
        
        if (response.data.status) {
            Swal.fire({
                title: 'Cancelled!',
                text: 'Request cancelled successfully',
                icon: 'success',
                confirmButtonColor: '#01062d'
            })
            await fetchMyOrders() 
        } else {
            throw new Error(response.data.message || 'Failed to cancel request')
        }
    } catch (err) {
        console.error('Error cancelling order:', err)
        Swal.fire({
            title: 'Error!',
            text: 'Failed to cancel request',
            icon: 'error',
            confirmButtonColor: '#01062d'
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
                confirmButtonColor: '#01062d'
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
            confirmButtonColor: '#01062d'
        })
    }
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
        const listener = window.Echo.private(`user.${user.id}`)
            .listen('.access.request.updated', (event) => {
                console.log('🎉 MyOrders: Real-time update received:', event)
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
    if (event.action_type === 'responded' && event.requested_by === getCurrentUserId()) {
        console.log('🔄 My request was responded to via real-time')
        fetchMyOrders() 
        showResponseNotification(event)
    }
    
    if (event.action_type === 'cancelled' && event.requested_by === getCurrentUserId()) {
        console.log('❌ My request was cancelled via real-time')
        fetchMyOrders() 
        showCancelledNotification(event)
    }

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
function getActionStatusText(order) {
    switch(order.status) {
        case 'approved':
            return order.owner_response ? 'Approved ' : 'Approved';
        
        case 'rejected':
            return order.owner_response ? 'Rejected ' : 'Declined';
        case 'pending':
            return 'Pending';
        case 'in_progress':
            return 'In Progress';
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
           order.status === 'approved' ||
           order.status === 'rejected' || order.status === 'in_progress';
}
onMounted(() => {
    fetchMyOrders()
    setTimeout(() => {
        initializeRealTimeUpdates()
    }, 1000)
})

onUnmounted(() => {
    cleanup()
})
function  hasActions(order) {
        return (
            (order.status === 'pending' && order.can_cancel) ||
            (order.status === 'approved') ||
            (order.status === 'approved' || order.status === 'converted') ||
            (order.listing?.id)
        );
    }
</script>

<style scoped>
/* Tab count badges */
.tab-count {
    background: #01062d;
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
    background: #01062d;
    color: white;
}

.tab-btn:hover:not(.active) {
    background: #f8f9fa;
}

.btn-outline-primary {
    border: 1px solid #01062d;
    color: #01062d;
    background: transparent;
}

.btn-outline-primary:hover:not(:disabled) {
    background: #01062d;
    color: white;
}

.btn-outline-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

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
    color: #01062d;
    font-size: 1.1rem;
}

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
    max-width: 200px;
    background: #f8f9fa;
    padding: 8px 12px;
    border-radius: 6px;
    border-left: 3px solid #28a745;
}

.review-text {
    font-style: italic;
    color: #495057;
    font-size: 12px;
    line-height: 1.3;
    word-break: break-word;
    margin-bottom: 4px;
}

/* Review Modal for full view */
.review-modal .modal-content {
    max-width: 600px;
}

.review-modal .review-full-text {
    font-size: 14px;
    line-height: 1.6;
    color: #333;
    background: #f8f9fa;
    padding: 16px;
    border-radius: 8px;
    border-left: 4px solid #28a745;
}

/* Badge for no review */
.text-muted.small i {
    color: #ffc107;
}

/* Responsive */
@media (max-width: 768px) {
    .review-display {
        max-width: 150px;
    }
    
    .review-text {
        font-size: 11px;
    }
}
.card.basic-data-table tbody tr td span:last-child {
    font-weight: 500 !important;
    font-size: 15px !important;
    color: #000 !important;
}
</style>