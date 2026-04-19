<template>
    <div class="dashboard-main-body">
        <Breadcrumb 
            :title="`Role Details - ${role.name}`" 
            :breadcrumbs="[
                { name: 'Roles', path: '/roles' },
                { name: 'Details' }
            ]" 
        />

        <div class="row">
            <!-- Role Information -->
            <div class="col-md-9">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="ui-h-mini card-title mb-0">Role Information</h6>
                        <div class="d-flex gap-2">
                            <button v-if="role.id >= 5 && $hasPermission('roles-edit')" 
                                class="btn btn-success btn-sm"
                                @click="editRole">
                                <iconify-icon icon="lucide:edit" class="me-2"></iconify-icon>
                                Edit Role
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" @click="$router.back()">
                                <iconify-icon icon="lucide:arrow-left" class="me-2"></iconify-icon>
                                Back
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-item mb-3">
                                    <label class="form-label fw-semibold text-muted">Role ID</label>
                                    <p class="fs-6 mb-0">{{ role.id }}</p>
                                </div>
                                <div class="info-item mb-3">
                                    <label class="form-label fw-semibold text-muted">Role Name</label>
                                    <p class="fs-6 mb-0">{{ role.name }}</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-item mb-3">
                                    <label class="form-label fw-semibold text-muted">Guard Name</label>
                                    <p class="fs-6 mb-0">
                                        <span class="badge bg-secondary">{{ role.guard_name }}</span>
                                    </p>
                                </div>
                                <div class="info-item mb-3">
                                    <label class="form-label fw-semibold text-muted">Created Date</label>
                                    <p class="fs-6 mb-0">{{ formatDate(role.created_at) }}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="info-item mb-3" v-if="role.description">
                            <label class="form-label fw-semibold text-muted">Description</label>
                            <p class="fs-6 mb-0 text-muted">{{ role.description }}</p>
                        </div>
                    </div>
                </div>

                <!-- Permissions Section -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="ui-h-mini card-title mb-0">Assigned Permissions</h6>
                    </div>
                    <div class="card-body">
                        <div v-if="role.permissions && role.permissions.length > 0" class="permissions-grid">
                            <div v-for="permission in role.permissions" :key="permission.id" 
                                 class="permission-card">
                                <div class="d-flex align-items-center">
                                    <iconify-icon icon="lucide:key" class="text-warning me-2"></iconify-icon>
                                    <span class="fw-medium">{{ permission }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center text-muted py-4">
                            <iconify-icon icon="lucide:key" width="48" class="mb-2"></iconify-icon>
                            <p>No permissions assigned to this role</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Sidebar -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header">
                        <h6 class="ui-h-mini card-title mb-0">Role Statistics</h6>
                    </div>
                    <div class="card-body">
                        <div class="stat-item text-center mb-4">
                            <div class="stat-value text-primary">{{ role.permissions_count || role.permissions?.length || 0 }}</div>
                            <div class="stat-label text-muted">Total Permissions</div>
                        </div>
                        
                        <div class="stat-item text-center mb-4">
                            <div class="stat-value text-success">{{ calculateUsersCount() }}</div>
                            <div class="stat-label text-muted">Assigned Users</div>
                        </div>
                        
                        <div class="stat-item text-center">
                            <div class="stat-value text-info">{{ getRoleLevel() }}</div>
                            <div class="stat-label text-muted">Access Level</div>
                        </div>
                    </div>
                </div>

               
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "@/plugins/axios";
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';

export default {
    name: 'RoleDetails',
    components: {
        Breadcrumb
    },
    setup() {
        const route = useRoute();
        const router = useRouter();
        const roleId = route.params.id;
        
        const role = ref({
            id: '',
            name: '',
            guard_name: '',
            permissions_count: 0,
            created_at: '',
            permissions: []
        });
        
        const loading = ref(true);

        const fetchRoleDetails = async () => {
            try {
                loading.value = true;
                const response = await api.get(`/roles/${roleId}`);
                const roleData = response.data.data || response.data;
                
                if (roleData) {
                    role.value = {
                        ...roleData,
                        permissions: roleData.permissions || []
                    };
                }
            } catch (error) {
                console.error('Error fetching role details:', error);
                // Demo data for testing
                role.value = {
                    id: roleId,
                    name: 'Admin',
                    guard_name: 'api',
                    permissions_count: 10,
                    created_at: '2024-01-15T10:30:00.000000Z',
                    description: 'Administrative access with most permissions',
                    permissions: [
                        { id: 1, name: 'users-create', guard_name: 'api' },
                        { id: 2, name: 'users-edit', guard_name: 'api' },
                        { id: 3, name: 'users-delete', guard_name: 'api' },
                        { id: 4, name: 'roles-create', guard_name: 'api' },
                        { id: 5, name: 'roles-edit', guard_name: 'api' }
                    ]
                };
            } finally {
                loading.value = false;
            }
        };

        const editRole = () => {
            router.push(`/roles/${roleId}/edit`);
        };

        const assignPermissions = () => {
            router.push(`/roles/${roleId}/permissions`);
        };

        const deleteRole = async () => {
            if (confirm(`Are you sure you want to delete role "${role.value.name}"?`)) {
                try {
                    await api.delete(`/roles/${roleId}`);
                    router.push('/roles');
                } catch (error) {
                    console.error('Error deleting role:', error);
                    alert('Failed to delete role');
                }
            }
        };

        const duplicateRole = () => {
            // Implementation for duplicating role
            console.log('Duplicate role:', role.value.name);
        };

        const calculateUsersCount = () => {
            // This would typically come from API
            return Math.floor(Math.random() * 50) + 1;
        };

        const getRoleLevel = () => {
            const count = role.value.permissions_count || role.value.permissions?.length || 0;
            if (count > 10) return 'High';
            if (count > 5) return 'Medium';
            return 'Low';
        };

        const formatDate = (dateString) => {
            if (!dateString) return 'N/A';
            try {
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } catch (error) {
                return 'Invalid Date';
            }
        };

        onMounted(() => {
            fetchRoleDetails();
        });

        return {
            role,
            loading,
            editRole,
            assignPermissions,
            deleteRole,
            duplicateRole,
            calculateUsersCount,
            getRoleLevel,
            formatDate
        };
    }
};
</script>

<style scoped>
.info-item {
    padding: 0.5rem 0;
    border-bottom: 1px solid #f8f9fa;
}

.info-item:last-child {
    border-bottom: none;
}

.permissions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
}

.permission-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 1rem;
    transition: all 0.3s ease;
}

.permission-card:hover {
    background: #e9ecef;
    transform: translateY(-2px);
}

.stat-value {
    font-size: 2.5rem;
    font-weight: bold;
    line-height: 1;
}

.stat-label {
    font-size: 0.875rem;
    margin-top: 0.5rem;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.btn {
    border-radius: 6px;
}
</style>