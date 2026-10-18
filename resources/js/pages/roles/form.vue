<template>
    <div class="dashboard-main-body">
        <Breadcrumb 
            :title="isEditMode ? 'Edit Role' : 'Add New Role'" 
            :breadcrumbs="[
                { name: 'Roles', path: '/roles' },
                { name: isEditMode ? 'Edit' : 'Add' }
            ]" 
        />

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ isEditMode ? 'Edit Role' : 'Add New Role' }}</h5>
                <button class="btn btn-outline-secondary" @click="$router.back()">
                    <iconify-icon icon="lucide:arrow-left" class="me-2"></iconify-icon>
                    Back
                </button>
            </div>
            
            <div class="card-body">
                <form @submit.prevent="submitForm">
                    <!-- Basic Information Card -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="card-title mb-0">
                                <iconify-icon icon="lucide:info" class="me-2 text-primary"></iconify-icon>
                                Basic Information
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Role Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" v-model="roleForm.name" 
                                               :class="{'is-invalid': errors.name}"
                                               placeholder="Enter role name"
                                               autofocus>
                                        <div class="invalid-feedback" v-if="errors.name">
                                            {{ errors.name[0] }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Permissions Selection - Cards Layout -->
                    <div class="card mb-4" v-if="availablePermissions.length > 0 && permissionsLoaded">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">
                                <iconify-icon icon="lucide:key" class="me-2 text-warning"></iconify-icon>
                                Permissions 
                                <span class="badge bg-primary ms-2">{{ availablePermissions.length }} permissions available</span>
                            </h6>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" v-model="selectAllPermissions" 
                                       @change="toggleAllPermissions">
                                <label class="form-check-label">Select All</label>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Search Permissions -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <iconify-icon icon="lucide:search"></iconify-icon>
                                        </span>
                                        <input type="text" class="form-control" v-model="permissionSearch" 
                                               placeholder="Search permissions...">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <span class="badge bg-primary">
                                            Selected: {{ selectedPermissionsCount }}
                                        </span>
                                        <span class="badge bg-secondary">
                                            Total: {{ filteredPermissions.length }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Permissions Cards Grid -->
                            <div class="permissions-cards-grid">
                                <div v-for="category in groupedPermissions" :key="category.name"
                                     class="permission-category-card">
                                    <div class="category-header">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   :checked="isCategorySelected(category.name)"
                                                   @change="toggleCategory(category.name)">
                                            <label class="form-check-label fw-bold">
                                                {{ formatCategoryName(category.name) }}
                                            </label>
                                        </div>
                                        <iconify-icon :icon="getCategoryIcon(category.name)" 
                                                     :class="`category-icon text-${getCategoryColor(category.name)}`"></iconify-icon>
                                    </div>
                                    <div class="category-permissions">
                                        <div v-for="permission in category.permissions" 
                                             :key="permission.name"
                                             class="permission-item">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       :value="permission.name"
                                                       v-model="roleForm.permissions"
                                                       :id="`perm-${permission.name}`">
                                                <label class="form-check-label" :for="`perm-${permission.name}`">
                                                    {{ formatPermissionName(permission.name) }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div class="card mb-4" v-if="!permissionsLoaded">
                        <div class="card-body text-center py-5">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="mt-2">Loading permissions and role data...</p>
                        </div>
                    </div>

                    <!-- No Permissions State -->
                    <div class="card mb-4" v-if="permissionsLoaded && availablePermissions.length === 0">
                        <div class="card-body text-center py-5">
                            <iconify-icon icon="lucide:key" class="text-muted mb-2" width="48"></iconify-icon>
                            <p class="text-muted">No permissions available</p>
                            <button type="button" class="btn btn-outline-primary" @click="fetchPermissions">
                                <iconify-icon icon="lucide:refresh-cw" class="me-2"></iconify-icon>
                                Retry
                            </button>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex gap-2 justify-content-end">
                                <button type="button" class="btn btn-outline-secondary" @click="$router.back()" :disabled="loading">
                                    Cancel
                                </button>
                              
                                <button type="submit" class="btn btn-primary" :disabled="loading || !permissionsLoaded">
                                    <span v-if="loading">
                                        <iconify-icon icon="lucide:loader-2" class="me-2 spin"></iconify-icon>
                                        {{ isEditMode ? 'Updating...' : 'Creating...' }}
                                    </span>
                                    <span v-else>
                                        <iconify-icon icon="lucide:save" class="me-2"></iconify-icon>
                                        {{ isEditMode ? 'Update Role' : 'Create Role' }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted, computed, watch, nextTick } from "vue";
import { useRoute, useRouter } from "vue-router";

import api from "@/plugins/axios";
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';

export default {
    name: 'RoleForm',
    components: {
        Breadcrumb
    },
    setup() {
        const route = useRoute();
        const router = useRouter();

        const isEditMode = computed(() => route.name === 'edit-role');
        const roleId = computed(() => route.params.id);

        // Refs
        const errors = ref({});
        const loading = ref(false);
        const loadingPermissions = ref(false);
        const availablePermissions = ref([]);
        const permissionSearch = ref('');
        const selectAllPermissions = ref(false);
        const permissionsLoaded = ref(false);

        // Role Form Data
        const roleForm = ref({
            name: "",
            permissions: []
        });

        // Computed Properties
        const filteredPermissions = computed(() => {
            if (!permissionSearch.value) {
                return availablePermissions.value;
            }
            const search = permissionSearch.value.toLowerCase();
            return availablePermissions.value.filter(perm => 
                perm.name.toLowerCase().includes(search) ||
                formatPermissionName(perm.name).toLowerCase().includes(search)
            );
        });

        const selectedPermissionsCount = computed(() => {
            return roleForm.value.permissions.length;
        });

        const groupedPermissions = computed(() => {
            const groups = {};
            
            filteredPermissions.value.forEach(permission => {
                const category = permission.name.split('-')[0];
                
                if (!groups[category]) {
                    groups[category] = {
                        name: category,
                        permissions: []
                    };
                }
                
                groups[category].permissions.push(permission);
            });

            return Object.values(groups).sort((a, b) => a.name.localeCompare(b.name));
        });

        // Methods
        const getCategoryIcon = (category) => {
            const icons = {
                'users': 'lucide:users',
                'roles': 'lucide:shield',
                'listings': 'lucide:building',
                'areas': 'lucide:map-pin',
                'stages': 'lucide:layers',
                'leads': 'lucide:trending-up',
                'properties': 'lucide:home',
                'permissions': 'lucide:key',
                'settings': 'lucide:settings',
                'reports': 'lucide:bar-chart',
                'dashboard': 'lucide:layout-dashboard',
                'developers': 'lucide:code',
                'layout_types': 'lucide:layout'
            };
            return icons[category] || 'lucide:circle';
        };

        const getCategoryColor = (category) => {
            const colors = {
                'users': 'primary',
                'roles': 'success',
                'listings': 'info',
                'areas': 'warning',
                'stages': 'danger',
                'leads': 'success',
                'properties': 'info',
                'permissions': 'warning',
                'settings': 'secondary',
                'reports': 'primary',
                'dashboard': 'info',
                'developers': 'success',
                'layout_types': 'info'
            };
            return colors[category] || 'secondary';
        };

        const formatCategoryName = (category) => {
            return category.charAt(0).toUpperCase() + category.slice(1).replace('_', ' ') ;
        };

        const formatPermissionName = (permissionName) => {
            const parts = permissionName.split('-');
            if (parts.length === 2) {
                const action = parts[1].charAt(0).toUpperCase() + parts[1].slice(1);
                const resource = parts[0].charAt(0).toUpperCase() + parts[0].slice(1).replace('_', ' ');
                return `${action} ${resource}`;
            }
            return permissionName
                .split('-')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1).replace('_', ' '))
                .join(' ');
        };

        const isCategorySelected = (category) => {
            const categoryPerms = groupedPermissions.value.find(cat => cat.name === category)?.permissions || [];
            if (categoryPerms.length === 0) return false;
            return categoryPerms.every(perm => 
                roleForm.value.permissions.includes(perm.name)
            );
        };

        const toggleCategory = (category) => {
            const categoryData = groupedPermissions.value.find(cat => cat.name === category);
            if (!categoryData) return;

            const categoryPerms = categoryData.permissions;
            const allSelected = isCategorySelected(category);
            
            if (allSelected) {
                roleForm.value.permissions = roleForm.value.permissions.filter(
                    perm => !categoryPerms.some(cp => cp.name === perm)
                );
            } else {
                const newPerms = categoryPerms.map(cp => cp.name).filter(
                    perm => !roleForm.value.permissions.includes(perm)
                );
                roleForm.value.permissions.push(...newPerms);
            }
        };

        const toggleAllPermissions = () => {
            if (selectAllPermissions.value) {
                roleForm.value.permissions = filteredPermissions.value.map(perm => perm.name);
            } else {
                roleForm.value.permissions = [];
            }
        };

      // Helper function for notifications
  // Helper function for notifications - FINAL SOLUTION
const showNotification = (message, type = 'info') => {
    console.log(`🔔 Notification [${type}]:`, message);
    
    // محاولة استخدام window.$showNotification
    if (typeof window !== 'undefined' && window.$showNotification) {
        try {
            window.$showNotification(message, type);
            return;
        } catch (error) {
            console.warn('window.$showNotification failed, using fallback:', error);
        }
    }
    
    // Fallback: إنشاء إشعار مخصص
    if (typeof document !== 'undefined') {
        createFallbackNotification(message, type);
    }
};

const createFallbackNotification = (message, type) => {
    const notification = document.createElement('div');
    const typeConfig = {
        success: { bg: '#10b981', icon: '✅', title: 'Success' },
        error: { bg: '#ef4444', icon: '❌', title: 'Error' },
        warning: { bg: '#f59e0b', icon: '⚠️', title: 'Warning' },
        info: { bg: '#3b82f6', icon: 'ℹ️', title: 'Info' }
    };
    
    const config = typeConfig[type] || typeConfig.info;
    
    notification.innerHTML = `
        <div style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: ${config.bg}; color: white; border-radius: 8px; min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
            <span style="font-size: 18px;">${config.icon}</span>
            <div>
                <div style="font-weight: 600; font-size: 14px;">${config.title}</div>
                <div style="font-size: 13px;">${message}</div>
            </div>
        </div>
    `;
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        animation: slideIn 0.3s ease-out;
    `;
    
    // إضافة animation styles إذا لم تكن موجودة
    if (!document.querySelector('#notification-styles')) {
        const style = document.createElement('style');
        style.id = 'notification-styles';
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        document.head.appendChild(style);
    }
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideIn 0.3s ease-out reverse';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }
    }, 4000);
};


        // Watch for changes in filtered permissions to update select all state
        watch([() => roleForm.value.permissions, filteredPermissions], () => {
            if (filteredPermissions.value.length === 0) {
                selectAllPermissions.value = false;
                return;
            }
            
            const allSelected = filteredPermissions.value.every(perm => 
                roleForm.value.permissions.includes(perm.name)
            );
            selectAllPermissions.value = allSelected;
        });

        // Fetch available permissions
        const fetchPermissions = async () => {
            try {
                loadingPermissions.value = true;
                const response = await api.get('/permissions');
                
                let permissionsData = response.data;
                
                if (permissionsData && permissionsData.data) {
                    permissionsData = permissionsData.data;
                }
                
                if (!Array.isArray(permissionsData)) {
                    console.error('Permissions data is not an array:', permissionsData);
                    availablePermissions.value = [];
                    return;
                }
                
                availablePermissions.value = permissionsData.map(perm => ({
                    id: perm.id,
                    name: perm.name,
                    guard_name: perm.guard_name
                }));
                
                console.log('✅ Permissions loaded:', availablePermissions.value.length);
                
            } catch (error) {
                console.error("❌ Error fetching permissions:", error);
                availablePermissions.value = [];
                showNotification('Failed to load permissions', 'error');
            } finally {
                loadingPermissions.value = false;
            }
        };

        // Fetch role data for editing
        const fetchRole = async () => {
            try {
                loading.value = true;
                console.log('🔍 Fetching role data for ID:', roleId.value);

                const response = await api.get(`/roles/${roleId.value}`);
                const roleData = response.data.data || response.data;

                if (!roleData) {
                    throw new Error('Role not found');
                }

                console.log('📋 Role data received:', roleData);

                // تأكد من أن الصلاحيات قد تم تحميلها
                if (availablePermissions.value.length === 0) {
                    console.log('⏳ Waiting for permissions to load...');
                    await new Promise(resolve => setTimeout(resolve, 300));
                }

                const validPermissions = [];
                if (roleData.permissions && Array.isArray(roleData.permissions)) {
                    roleData.permissions.forEach(perm => {
                        const permissionName = perm.name || perm;
                        const permissionExists = availablePermissions.value.some(
                            availablePerm => availablePerm.name === permissionName
                        );
                        if (permissionExists) {
                            validPermissions.push(permissionName);
                            console.log(`✅ Found permission: ${permissionName}`);
                        }
                    });
                }

                // تعيين البيانات مع إعادة التفاعلية
                roleForm.value = {
                    name: roleData.name || "",
                    permissions: [...validPermissions] // استخدام spread لضمان reactivity
                };

                console.log('📝 Final form data:', roleForm.value);
                console.log('📝 Permissions count:', roleForm.value.permissions.length);

                // انتظر دورة التحديث التالية
                await nextTick();
                
                showNotification(`Role loaded with ${validPermissions.length} permissions`, 'success');

            } catch (error) {
                console.error("❌ Error fetching role:", error);
                showNotification('Failed to load role data', 'error');
                router.push('/roles');
            } finally {
                loading.value = false;
            }
        };

        // Submit role form
        const submitForm = async () => {
            try {
                loading.value = true;
                errors.value = {};

                if (!roleForm.value.name?.trim()) {
                    showNotification("Please enter role name", "error");
                    loading.value = false;
                    return;
                }

                const submitData = {
                    name: roleForm.value.name.trim(),
                    permissions: roleForm.value.permissions
                };

                console.log('🚀 Submitting data:', submitData);

                let response;
                
                if (isEditMode.value) {
                    response = await api.put(`/roles/${roleId.value}`, submitData);
                } else {
                    response = await api.post("/roles", submitData);
                }

                const successMessage = isEditMode.value ? "Role updated successfully!" : "Role created successfully!";
                showNotification(successMessage, "success");

                setTimeout(() => {
                    router.push('/roles');
                }, 1500);

            } catch (error) {
                console.error("❌ Error saving role:", error);
                
                if (error.response?.data?.errors) {
                    errors.value = error.response.data.errors;
                    const errorMessage = Object.values(error.response.data.errors).flat().join(', ');
                    showNotification(`Form errors: ${errorMessage}`, "error");
                } else if (error.response?.data?.message) {
                    showNotification(error.response.data.message, "error");
                } else {
                    showNotification("Failed to save role", "error");
                }
            } finally {
                loading.value = false;
            }
        };

        // Initialize component
        onMounted(async () => {
            try {
                // حمل الصلاحيات أولاً
                await fetchPermissions();
                
                // إذا كان في وضع التعديل، حمل بيانات الدور
                if (isEditMode.value) {
                    await fetchRole();
                }
                
                permissionsLoaded.value = true;
            } catch (error) {
                console.error('Error initializing component:', error);
                showNotification('Failed to load component data', 'error');
            }
        });

        return {
            isEditMode,
            loading,
            loadingPermissions,
            permissionsLoaded,
            roleForm,
            errors,
            availablePermissions,
            permissionSearch,
            selectAllPermissions,
            filteredPermissions,
            groupedPermissions,
            selectedPermissionsCount,
            getCategoryIcon,
            getCategoryColor,
            formatCategoryName,
            formatPermissionName,
            isCategorySelected,
            toggleCategory,
            toggleAllPermissions,
            submitForm
        };
    }
};
</script>

<style scoped>
.permissions-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
}

.permission-category-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 1.25rem;
    transition: all 0.3s ease;
}

.permission-category-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.category-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #e9ecef;
}

.category-header .form-check {
    display: flex;
    align-items: center;
    margin-bottom: 0;
    flex: 1;
}

.category-header .form-check-label {
    margin-left: 0.5rem;
    margin-bottom: 0;
    font-size: 1rem;
    font-weight: 600;
}

.category-icon {
    font-size: 1.5rem;
    margin-left: 1rem;
}

.category-permissions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.permission-item {
    padding: 0.5rem;
    border-radius: 6px;
    transition: background-color 0.2s ease;
}

.permission-item:hover {
    background-color: #e9ecef;
}

.permission-item .form-check {
    display: flex;
    align-items: center;
    margin-bottom: 0;
}

.permission-item .form-check-label {
    font-size: 0.9rem;
    cursor: pointer;
    margin-left: 0.5rem;
    margin-bottom: 0;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.card-header.bg-light {
    background-color: #f8f9fa !important;
    border-bottom: 1px solid #e9ecef;
}

.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.badge {
    font-size: 0.75rem;
}

.input-group-text {
    background-color: #f8f9fa;
    border-color: #dee2e6;
}

.form-check-input {
    margin-right: 0.5rem;
}

/* Bootstrap color utilities for category icons */
.text-primary { color: #0d6efd !important; }
.text-success { color: #198754 !important; }
.text-info { color: #0dcaf0 !important; }
.text-warning { color: #ffc107 !important; }
.text-danger { color: #dc3545 !important; }
.text-secondary { color: #6c757d !important; }

/* Responsive design */
@media (max-width: 768px) {
    .permissions-cards-grid {
        grid-template-columns: 1fr;
    }
    
    .category-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .category-icon {
        margin-left: 0;
        margin-top: 0.5rem;
    }
}
</style>