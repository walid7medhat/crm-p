<template>
    <div class="dashboard-main-body">
        <Breadcrumb :title="developer ? developer.name : 'Developer Details'" :breadcrumbs="[
            { name: 'Developers', path: '/developers' },
            { name: developer ? developer.name : 'Loading...' }
        ]" />

        <div class="card" v-if="developer">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="ui-h-mini card-title mb-0">Developer Information</h6>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary" @click="$router.back()">
                        <iconify-icon icon="lucide:arrow-left" class="me-2"></iconify-icon>
                        Back
                    </button>
                    <button v-if="$hasPermission('developers-edit')" class="btn btn-primary" 
                            @click="$router.push(`/developers/${developer.id}/edit`)">
                        <iconify-icon icon="lucide:edit" class="me-2"></iconify-icon>
                        Edit Developer
                    </button>
                </div>
            </div>
            
            <div class="card-body">
                <div class="row">
                    <!-- Profile Section -->
                    <div class="col-md-4">
                        <div class="text-center">
                            <div class="position-relative d-inline-block">
                                <img :src="developer.avatar || userPlaceholder" alt="Avatar" 
                                     class="rounded-circle border" 
                                     width="150" height="150"
                                 >
                                <div class="position-absolute bottom-0 end-0 bg-success rounded-circle p-1 border">
                                    <iconify-icon icon="lucide:check" class="text-white" width="16"></iconify-icon>
                                </div>
                            </div>
                            <h6 class="ui-h-mini mt-3 mb-1">{{ developer.name }}</h6>
                            <div class="badge bg-primary fs-6">{{ developer.added_by }}</div>
                        </div>
                    </div>

                    <!-- Details Section -->
                    <div class="col-md-8">
                        <div class="row">
                            <div class="col-12">
                                <h6 class="section-title mb-3">Personal Information</h6>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-muted small">Developer ID</label>
                                <div class="fs-6">{{ developer.id }}</div>
                            </div>
                            
                          
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-muted small">Added By</label>
                                <div class="fs-6">{{ developer.added_by }}</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-muted small">Created Date</label>
                                <div class="fs-6">{{ formatDate(developer.created_at) }}</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-muted small">Last Updated</label>
                                <div class="fs-6">{{ formatDate(developer.updated_at) }}</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold text-muted small">Status</label>
                                <div>
                                    <span class="badge bg-success">Active</span>
                                </div>
                            </div>
                        </div>

               
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-else-if="loading" class="card">
            <div class="card-body text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <p>Loading developer information...</p>
            </div>
        </div>

        <!-- Error State -->
        <div v-else class="card">
            <div class="card-body text-center py-5">
                <iconify-icon icon="lucide:user-x" class="text-muted mb-3" width="48"></iconify-icon>
                <h6 class="ui-h-mini">Developer Not Found</h6>
                <p class="text-muted">The developer you're looking for doesn't exist.</p>
                <button class="btn btn-primary" @click="$router.push('/developers')">
                    Back to Developers List
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { API_ENDPOINTS } from '@/config/api';
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import userPlaceholder from '../../assets/images/avatar/avatar1.png';

export default {
    name: 'ViewDeveloper',
    components: {
        Breadcrumb
    },
    data() {
        return {
            loading: false,
            developer: null,
            error: null,
            userPlaceholder
        };
    },
    mounted() {
        this.fetchDeveloper();
    },
    methods: {
        async fetchDeveloper() {
            try {
                this.loading = true;
                this.error = null;
                
                const token = localStorage.getItem('token');
                const developerId = this.$route.params.id;
                
                const response = await fetch(API_ENDPOINTS.DEVELOPER_BY_ID(developerId), {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    this.developer = data.data;
                } else if (response.status === 404) {
                    this.error = 'Developer not found';
                } else {
                    throw new Error('Failed to fetch developer');
                }
            } catch (error) {
                console.error('Error fetching developer:', error);
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },

        formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },

        handleImageError(event) {
            event.target.src = '/images/avatars/default-avatar.jpg';
        },

        $hasPermission(permission) {
            try {
                const userData = localStorage.getItem('user');
                if (!userData) return false;
                
                const user = JSON.parse(userData);
                const permissions = user.permissions || [];
                const roles = user.roles || [];
                
                if (roles.includes('admin')) return true;
                return permissions.includes(permission);
            } catch (error) {
                return false;
            }
        }
    }
};
</script>

<style scoped>
.section-title {
    color: #6c757d;
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge {
    font-size: 0.75rem;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}
</style>