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

                    <!-- Empty -->
                    <div v-if="!loading && hotDealRequests.length === 0" class="text-center py-5">
                        <h6 class="ui-h-mini">No Requests</h6>
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
                    <h6 class="ui-h-mini">{{ actionType === 'approve' ? 'Approve' : 'Reject' }} Request</h6>
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
import { ref, onMounted } from 'vue'
import Swal from 'sweetalert2'
import api from '@/plugins/axios'

const hotDealRequests = ref([])
const loading = ref(false)

const selectedId = ref(null)
const actionType = ref(null)
const comments = ref('')

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
async function fetchRequests() {
    try {
        loading.value = true

        const res = await api.get('/listings/hot-deal-requests/pending')

        if (res.data.status) {
            hotDealRequests.value = res.data.data.data
        }

    } catch (e) {
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
</style>