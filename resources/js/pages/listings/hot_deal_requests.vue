<template>
    <div class="dashboard-main-body">
        <Breadcrumb title="Hot Deal Requests" :breadcrumbs="[
            { name: 'Hot Deal Requests' }
        ]" />

        <div class="card basic-data-table">
            <div class="card-body">

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="table bordered-table mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Property</th>
                                <th>Requested By</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="req in hotDealRequests" :key="req.id">
                                <td>{{ req.id }}</td>

                               <td>
                                    <div class="d-flex flex-column">
                                        <span @click="viewProperty(req.listing?.id)"> {{ req.listing?.area?.title || '-' }}</span>
                                        <span @click="viewProperty(req.listing?.id)"> {{ formatPrice(req.listing?.price) || '-' }}</span>
                                        <span @click="viewProperty(req.listing?.id)" v-if="req.listing?.number_of_bedrooms"> {{ req.listing?.number_of_bedrooms  || '-' }} Beds</span>
                                    </div>
                                </td>
                                <td>
                                    {{ req.requester?.name }}
                                </td>

                                <td>
                                    {{ formatDate(req.created_at) }}
                                </td>

                                <td>
                                    <span class="badge bg-warning">
                                        {{ req.status }}
                                    </span>
                                </td>

                                <td >
                                    <button 
                                    v-if="req.status=='pending'"
                                        class="btn btn-success btn-sm me-2"
                                        @click="openAction(req.id, 'approve')"
                                    >
                                        Approve
                                    </button>

                                    <button 
                                       v-if="req.status=='pending'"
                                        class="btn btn-danger btn-sm"
                                        @click="openAction(req.id, 'reject')"
                                    >
                                        Reject
                                    </button>
                                      <button 
                                            class="btn btn-primary btn-sm ml-1"
                                            @click="viewProperty(req.listing?.id)"
                                        >
                                            View
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                        <!-- Pagination -->
                        <div v-if="pagination.last_page > 1" class="pagination-wrapper mt-4">
                            <nav>
                                <ul class="pagination justify-content-center">
                                    <li class="page-item" :class="{ disabled: pagination.current_page === 1 }">
                                        <button class="page-link" @click="changePage(pagination.current_page - 1)">
                                            Previous
                                        </button>
                                    </li>
                                    
                                    <li 
                                        v-for="page in visiblePages" 
                                        :key="page" 
                                        class="page-item" 
                                        :class="{ active: page === pagination.current_page }"
                                    >
                                        <button class="page-link" @click="changePage(page)">
                                            {{ page }}
                                        </button>
                                    </li>
                                    
                                    <li class="page-item" :class="{ disabled: pagination.current_page === pagination.last_page }">
                                        <button class="page-link" @click="changePage(pagination.current_page + 1)">
                                            Next
                                        </button>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        
                        <!-- Info -->
                        <div class="text-center text-muted mt-2 small" v-if="pagination.total > 0">
                            Showing {{ ((pagination.current_page - 1) * pagination.per_page) + 1 }} 
                            to {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} 
                            of {{ pagination.total }} requests
                        </div>
                    <!-- Empty -->
                    <div v-if="!loading && hotDealRequests.length === 0" class="text-center py-5">
                        <h5>No Requests</h5>
                    </div>

                    <!-- Loading -->
                    <div v-if="loading" class="text-center py-5">
                        <div class="spinner-border"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="selectedId" class="modal-overlay">
            <div class="modal-content" @click.stop>

                <div class="modal-header">
                    <h5>{{ actionType === 'approve' ? 'Approve' : 'Reject' }} Request</h5>
                    <button class="btn-close" @click="selectedId = null"></button>
                </div>

                <div class="modal-body">
                    <textarea 
                        v-model="comments"
                        class="form-control"
                        placeholder="Write comment..."
                    ></textarea>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="selectedId = null">
                        Cancel
                    </button>

                    <button class="btn btn-primary" @click="submitAction">
                        Confirm
                    </button>
                </div>

            </div>
        </div>

    </div>
</template>
<script setup>
import { ref, onMounted,computed } from 'vue'
import Swal from 'sweetalert2'
import api from '@/plugins/axios'

const hotDealRequests = ref([])
const loading = ref(false)

const selectedId = ref(null)
const actionType = ref(null)
const comments = ref('')

const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 50,
    total: 0
})
onMounted(() => {
    fetchRequests()
})
function viewProperty(id) {
    if (!id) return
    window.open(`/property-details/${id}`, '_blank')
}
function formatPrice(price) {
    return price ? new Intl.NumberFormat().format(price) + ' AED' : '-'
}
async function fetchRequests(page = 1) {
    try {
        loading.value = true

        const res = await api.get('/listings/hot-deal-requests/pending', {
            params: { page, per_page: 50 }
        })
        
        console.log('API Response:', res.data)

        if (res.data.status) {
            // ✅ التصحيح - البيانات موجودة في res.data.data.data
            if (res.data.data && Array.isArray(res.data.data.data)) {
                hotDealRequests.value = res.data.data.data
                // حفظ معلومات الباجنيشن
                pagination.value = {
                    current_page: res.data.data.current_page,
                    last_page: res.data.data.last_page,
                    per_page: res.data.data.per_page,
                    total: res.data.data.total
                }
            } 
            // Fallback إذا كانت المصفوفة مباشرة
            else if (Array.isArray(res.data.data)) {
                hotDealRequests.value = res.data.data
            }
            else {
                hotDealRequests.value = []
            }
        }

    } catch (e) {
        console.error('Error:', e)
        Swal.fire('Error', 'Failed to load requests', 'error')
    } finally {
        loading.value = false
    }
}

function openAction(id, type) {
    selectedId.value = id
    actionType.value = type
    comments.value = ''
}

async function submitAction() {
    try {
        await api.post(`/listings/hot-deal-requests/${selectedId.value}/process`, {
            action: actionType.value,
            comments: comments.value
        })

        Swal.fire('Success', 'Request processed', 'success')

        selectedId.value = null
        fetchRequests()

    } catch (e) {
        Swal.fire('Error', 'Failed to process', 'error')
    }
}

function formatDate(date) {
    return new Date(date).toLocaleString()
}
const visiblePages = computed(() => {
    const current = pagination.value.current_page
    const last = pagination.value.last_page
    const delta = 2
    const range = []
    
    for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
        range.push(i)
    }
    
    if (current - delta > 2) {
        range.unshift('...')
    }
    if (current + delta < last - 1) {
        range.push('...')
    }
    
    range.unshift(1)
    if (last !== 1) range.push(last)
    
    return range
})

function changePage(page) {
    if (page < 1 || page > pagination.value.last_page) return
    pagination.value.current_page = page
    fetchRequests(page)
}
</script>
<style scoped>
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.modal-content {
    background: white;
    border-radius: 8px;
    width: 400px;
    padding: 20px;
}
.modal-header h5{
    font-size:20px !important;
}
.modal-footer{
    margin-top:20px !important;
}
.btn{
    margin:5px !important;
}
</style>