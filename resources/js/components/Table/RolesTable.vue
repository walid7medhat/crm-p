<template>
    <div class="card basic-data-table">
        <div class="card-header">
            <h5 class="card-title mb-0">Roles Management</h5>
        </div>
        <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3"
                style="border-bottom: none; padding-bottom: 0px;">

                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <select class="form-select form-select-lr w-auto rounded-3 me-10" v-model="selectedShow"
                            style="border-radius: 10px; height: 2.4rem;">
                            <option value="10">10</option>
                            <option value="15">15</option>
                            <option value="20">20</option>
                            <option value="all">All</option>
                        </select>
                        <span>entries per page</span>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <!-- Refresh Button -->
                    <button class="btn btn-outline-secondary" @click="refreshData" title="Refresh Data">
                        <iconify-icon icon="lucide:refresh-cw"></iconify-icon>
                    </button>

                    <!-- Add Role Button -->
                    <button v-if="$hasPermission('roles-create')" 
                        class="btn btn-primary"
                        @click="addRole">
                        <iconify-icon icon="lucide:plus" class="me-2"></iconify-icon>
                        Add New Role
                    </button>
                    
                    <div class="icon-field d-flex align-items-center" style="padding-bottom: 5px;">
                        <span class="me-13">Search:</span>
                        <div class="position-relative" style="width: 100%; max-width: 240px;">
                            <input type="text" class="form-control form-control-sm w-100 px-3 pe-5" v-model="searchText"
                                style="border-radius: 10px; height: 2.5rem;" placeholder="Search roles..." />
                            <span class="icon position-absolute end-0 top-50 translate-middle-y me-3 text-muted"
                                style="pointer-events: none;">
                                <iconify-icon icon="lucide:search"></iconify-icon>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Super Admin Notice -->
            <div v-if="isSuperAdmin" class="alert alert-info mx-3 mt-3 mb-0 d-flex align-items-center">
                <iconify-icon icon="lucide:shield-check" class="me-2"></iconify-icon>
                <div>
                    <strong>Super Admin Mode:</strong> You have full access to modify all system roles.
                </div>
            </div>

            <!-- Debug Panel (يمكن إخفاؤه لاحقاً) -->
            <!-- <div v-if="debugMode" class="alert alert-warning mx-3 mt-3">
                <h6>Debug Information:</h6>
                <p><strong>User:</strong> {{ currentUser?.name }} (ID: {{ currentUser?.id }})</p>
                <p><strong>Role:</strong> {{ currentUser?.role_name }}</p>
                <p><strong>Is Super Admin:</strong> {{ isSuperAdmin ? 'Yes' : 'No' }}</p>
                <p><strong>Roles Array:</strong> {{ currentUser?.roles }}</p>
                <button class="btn btn-sm btn-outline-secondary mt-2" @click="debugUserData">
                    Show Full Debug
                </button>
            </div> -->

            <!-- Table -->
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table bordered-table mb-0">
                        <thead>
                            <tr>
                                <th scope="col" @click="sortBy('id')" class="sortable">
                                    ID
                                    <span v-if="sortKey === 'id'">
                                        <iconify-icon :icon="sortAsc ? 'mdi:arrow-up' : 'mdi:arrow-down'"></iconify-icon>
                                    </span>
                                </th>
                                <th scope="col" @click="sortBy('name')" class="sortable">
                                    Role Name
                                    <span v-if="sortKey === 'name'">
                                        <iconify-icon :icon="sortAsc ? 'mdi:arrow-up' : 'mdi:arrow-down'"></iconify-icon>
                                    </span>
                                </th>
                                <th scope="col">Permissions Count</th>
                                <th scope="col">Role Type</th>
                                <th scope="col" @click="sortBy('created_at')" class="sortable">
                                    Created Date
                                    <span v-if="sortKey === 'created_at'">
                                        <iconify-icon :icon="sortAsc ? 'mdi:arrow-up' : 'mdi:arrow-down'"></iconify-icon>
                                    </span>
                                </th>
                                <th scope="col">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="role in paginatedRoles" :key="role.id">
                                <td>{{ role.id }}</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0 me-12">
                                            <iconify-icon 
                                                :icon="isProtectedRole(role.id) ? 'lucide:shield' : 'lucide:user'" 
                                                :class="isProtectedRole(role.id) ? 'text-warning' : 'text-primary'" 
                                                width="24">
                                            </iconify-icon>
                                        </div>
                                        <div>
                                            <h6 class="text-md mb-0 fw-medium">{{ role.name || 'N/A' }}</h6>
                                            <small class="text-muted">{{ role.guard_name || 'api' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        {{ role.permissions_count || role.permissions?.length || 0 }}
                                    </span>
                                </td>
                                <td>
                                    <span :class="roleTypeClass(role.id)" class="badge">
                                        {{ getRoleType(role.id) }}
                                    </span>
                                </td>
                                <td>{{ formatDate(role.created_at) }}</td>
                            
                                <td class="d-flex gap-2">
                                    <!-- View Details Button -->
                                    <a href="javascript:void(0)"
                                        class="w-32-px h-32-px bg-primary-focus text-primary-main rounded-circle d-inline-flex align-items-center justify-content-center"
                                        @click="viewDetails(role)"
                                        title="View Details">
                                        <iconify-icon icon="lucide:eye"></iconify-icon>
                                    </a>

                                    <!-- Edit Button -->
                                    <a v-if="canEditRole(role)" href="javascript:void(0)"
                                        class="w-32-px h-32-px bg-success-focus text-success-main rounded-circle d-inline-flex align-items-center justify-content-center"
                                        @click="editRole(role)"
                                        :title="getEditButtonTitle(role)">
                                        <iconify-icon icon="lucide:edit"></iconify-icon>
                                    </a>

                                    <!-- Delete Button -->
                                    <a v-if="role.id>5" href="javascript:void(0)"
                                        class="w-32-px h-32-px bg-danger-focus text-danger-main rounded-circle d-inline-flex align-items-center justify-content-center"
                                        @click="deleteRole(role)"
                                        :title="getDeleteButtonTitle(role)">
                                        <iconify-icon icon="mingcute:delete-2-line"></iconify-icon>
                                    </a>

                                    <!-- Protected Role Indicator -->
                                    <span v-if="isProtectedRole(role.id) && !isSuperAdmin" 
                                          class="text-muted text-sm d-flex align-items-center"
                                          title="System role - Only Super Admin can modify">
                                        <iconify-icon icon="lucide:lock" class="me-1"></iconify-icon>
                                        Locked
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Loading State -->
                <div v-if="loading" class="loading text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2">Loading roles...</p>
                </div>

                <!-- No Data State -->
                <div v-if="!loading && roles.length === 0" class="no-data text-center py-4">
                    <iconify-icon icon="lucide:shield" class="text-muted mb-2" width="48"></iconify-icon>
                    <p>No roles found</p>
                    <button v-if="$hasPermission('roles-create')" class="btn btn-primary mt-2" @click="addRole">
                        <iconify-icon icon="lucide:plus" class="me-2"></iconify-icon>
                        Add First Role
                    </button>
                </div>

                <!-- Pagination -->
                <div v-if="!loading && roles.length > 0 && selectedShow !== 'all'" 
                     class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-24">
                    <span>
                        Showing {{ startIndex + 1 }} to {{ endIndex }} of {{ totalEntries }} entries
                    </span>
                    <ul class="pagination d-flex flex-wrap align-items-center gap-2 justify-content-center">
                        <li class="page-item">
                            <a class="page-link text-secondary-light fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px bg-base"
                                href="javascript:void(0)" @click="goToPage(currentPage - 1)"
                                :class="{ disabled: currentPage === 1 }">
                                <iconify-icon icon="ep:d-arrow-left" class="text-xl"></iconify-icon>
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
                                <iconify-icon icon="ep:d-arrow-right" class="text-xl"></iconify-icon>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { API_ENDPOINTS } from '../../config/api';

export default {
    name: 'RolesTable',
    data() {
        return {
            loading: true,
            selectedShow: '10',
            searchText: '',
            currentPage: 1,
            sortKey: '',
            sortAsc: true,
            roles: [],
            currentUser: null,
            debugMode: true // يمكن تعيينها false لاحقاً لإخفاء الـ debug
        };
    },
    computed: {
        // Protected roles IDs (1-5)
        protectedRoles() {
            return [1, 2, 3, 4, 5];
        },

        // Check if current user is Super Admin مع معالجة أفضل للأخطاء
        isSuperAdmin() {
            if (!this.currentUser) {
                console.warn('⚠️ No current user data for Super Admin check');
                return false;
            }

            console.log('🔍 Checking Super Admin status for user:', this.currentUser);

            // طريقة 1: التحقق من role_name مباشرة
            if (this.currentUser.role_name === 'Super_admin') {
                console.log('✅ Super Admin confirmed via role_name');
                return true;
            }

            // طريقة 2: التحقق من مصفوفة roles
            if (this.currentUser.roles && Array.isArray(this.currentUser.roles)) {
                const isSuperAdmin = this.currentUser.roles.some(role => {
                    if (typeof role === 'string') {
                        return role === 'Super_admin';
                    } else if (role && typeof role === 'object') {
                        return role.name === 'Super_admin';
                    }
                    return false;
                });
                
                if (isSuperAdmin) {
                    console.log('✅ Super Admin confirmed via roles array');
                    return true;
                }
            }

            // طريقة 3: التحقق من ID المستخدم (إذا كان ID 1 غالباً Super Admin)
            if (this.currentUser.id === 1) {
                console.log('⚠️ Assuming Super Admin via user ID (fallback)');
                return true;
            }

            console.log('❌ User is not Super Admin');
            return false;
        },

        entriesPerPage() {
            return this.selectedShow === 'all' ? this.filteredRoles.length : Number(this.selectedShow);
        },
        filteredRoles() {
            let result = [...this.roles];

            if (this.searchText) {
                const search = this.searchText.toLowerCase();
                result = result.filter(role =>
                    (role.name && role.name.toLowerCase().includes(search))
                );
            }

            if (this.sortKey) {
                result.sort((a, b) => {
                    let valA = a[this.sortKey] || '';
                    let valB = b[this.sortKey] || '';

                    if (this.sortKey === 'created_at') {
                        valA = new Date(valA);
                        valB = new Date(valB);
                    } else {
                        valA = String(valA).toLowerCase();
                        valB = String(valB).toLowerCase();
                    }

                    return this.sortAsc ? valA > valB ? 1 : -1 : valA < valB ? 1 : -1;
                });
            }

            return result;
        },
        paginatedRoles() {
            if (this.selectedShow === 'all') {
                return this.filteredRoles;
            }
            return this.filteredRoles.slice(this.startIndex, this.endIndex);
        },
        totalEntries() {
            return this.filteredRoles.length;
        },
        totalPages() {
            return this.selectedShow === 'all' ? 1 : Math.ceil(this.totalEntries / this.entriesPerPage);
        },
        startIndex() {
            return (this.currentPage - 1) * this.entriesPerPage;
        },
        endIndex() {
            return Math.min(this.startIndex + this.entriesPerPage, this.totalEntries);
        }
    },
    watch: {
        selectedShow() {
            this.currentPage = 1;
        },
        searchText() {
            this.currentPage = 1;
        }
    },
    mounted() {
        console.log('🚀 RolesTable component mounted');
        console.log('📋 Initial localStorage user:', localStorage.getItem('user'));
        console.log('🔑 Token exists:', !!localStorage.getItem('token'));
        
        this.fetchCurrentUser();
        this.fetchRoles();
        
        // debug بعد تحميل البيانات
        setTimeout(() => {
            this.debugUserData();
        }, 2000);
    },
    methods: {
        // Check if role is protected (ID 1-5)
        isProtectedRole(roleId) {
            return this.protectedRoles.includes(parseInt(roleId));
        },

        // Get role type
        getRoleType(roleId) {
            return this.isProtectedRole(roleId) ? 'System' : 'Custom';
        },

        // Role type badge class
        roleTypeClass(roleId) {
            return this.isProtectedRole(roleId) ? 'bg-warning' : 'bg-success';
        },

        // Check if user can edit role
        canEditRole(role) {
            if (!this.$hasPermission('roles-edit')) return false;
            
            // Super Admin can edit all roles
            if (this.isSuperAdmin) return true;
            
            // Non-Super Admin can only edit non-protected roles (ID >= 6)
            return !this.isProtectedRole(role.id);
        },

        // Check if user can delete role
        canDeleteRole(role) {
            if (!this.$hasPermission('roles-delete')) return false;
            
            // Super Admin can delete all roles
            if (this.isSuperAdmin) return true;
            
            // Non-Super Admin can only delete non-protected roles (ID >= 6)
            return !this.isProtectedRole(role.id);
        },

        // Get button titles
        getEditButtonTitle(role) {
            if (this.isProtectedRole(role.id)) {
                return 'Edit System Role (Super Admin Only)';
            }
            return 'Edit Role';
        },

        getDeleteButtonTitle(role) {
            if (this.isProtectedRole(role.id)) {
                return 'Delete System Role (Super Admin Only)';
            }
            return 'Delete Role';
        },

        // Fetch current user data
        async fetchCurrentUser() {
            try {
                // أولاً: جرب استخدام البيانات من localStorage
                const storedUser = localStorage.getItem('user');
                if (storedUser) {
                    try {
                        this.currentUser = JSON.parse(storedUser);
                        console.log('✅ Using cached user data from localStorage:', this.currentUser);
                        
                        // إذا كانت البيانات كافية، لا نحتاج لطلب API
                        if (this.currentUser && this.currentUser.id) {
                            return;
                        }
                    } catch (e) {
                        console.error('❌ Error parsing stored user data:', e);
                    }
                }

                const token = localStorage.getItem('token');
                if (!token) {
                    console.warn('⚠️ No token found in localStorage');
                    return;
                }

                console.log('🔄 Fetching user data from API...');
                
                // جرب endpoints مختلفة
                const endpoints = [
                    '/api/user',
                    '/api/auth/user',
                    '/user',
                    '/auth/user',
                    '/api/me',
                    '/me'
                ];

                for (const endpoint of endpoints) {
                    try {
                        console.log(`🔍 Trying endpoint: ${endpoint}`);
                        const response = await fetch(endpoint, {
                            method: 'GET',
                            headers: {
                                'Authorization': 'Bearer ' + token,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            }
                        });

                        // تحقق إذا كان الرد HTML (خطأ)
                        const contentType = response.headers.get('content-type');
                        if (contentType && contentType.includes('text/html')) {
                            console.log(`❌ Endpoint ${endpoint} returned HTML instead of JSON`);
                            continue;
                        }

                        if (response.ok) {
                            const text = await response.text();
                            console.log(`📥 Raw response from ${endpoint}:`, text.substring(0, 200));
                            
                            if (!text) {
                                console.log(`❌ Empty response from ${endpoint}`);
                                continue;
                            }

                            let userData;
                            try {
                                userData = JSON.parse(text);
                            } catch (parseError) {
                                console.log(`❌ JSON parse error for ${endpoint}:`, parseError);
                                continue;
                            }

                            console.log('✅ Parsed user data:', userData);
                            
                            // معالجة الاستجابة المختلفة
                            this.currentUser = userData.data || userData.user || userData;
                            
                            if (this.currentUser) {
                                // تحديث localStorage بالبيانات الجديدة
                                const userToStore = {
                                    id: this.currentUser.id,
                                    name: this.currentUser.name,
                                    email: this.currentUser.email,
                                    avatar: this.currentUser.avatar,
                                    roles: this.currentUser.roles || [],
                                    permissions: this.currentUser.permissions || [],
                                    role_name: this.currentUser.role_name || this.currentUser.roles?.[0]?.name || 'User'
                                };
                                
                                localStorage.setItem('user', JSON.stringify(userToStore));
                                console.log('✅ User data saved to localStorage');
                            }
                            
                            break;
                        } else {
                            console.log(`❌ Endpoint ${endpoint} failed with status: ${response.status}`);
                        }
                    } catch (error) {
                        console.log(`❌ Network error for endpoint ${endpoint}:`, error.message);
                        continue;
                    }
                }

                // إذا فشلت جميع المحاولات، استخدم البيانات المخزنة
                if (!this.currentUser && storedUser) {
                    this.fallbackToStoredData();
                }

            } catch (error) {
                console.error('❌ Error in fetchCurrentUser:', error);
                this.fallbackToStoredData();
            }
        },

        // دالة احتياطية
        fallbackToStoredData() {
            const storedUser = localStorage.getItem('user');
            if (storedUser) {
                try {
                    this.currentUser = JSON.parse(storedUser);
                    console.log('🔄 Using fallback user data from localStorage');
                } catch (e) {
                    console.error('❌ Error in fallback:', e);
                }
            }
        },

        // API methods
        async fetchRoles() {
            try {
                this.loading = true;
                
                const token = localStorage.getItem('token');
                
                const endpointsToTry = [
                    '/api/roles',
                    '/roles'
                ];

                let responseData = null;
                
                for (const endpoint of endpointsToTry) {
                    try {
                        const response = await fetch(endpoint, {
                            method: 'GET',
                            headers: {
                                'Authorization': 'Bearer ' + token,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            }
                        });

                        if (response.ok) {
                            const text = await response.text();
                            if (text) {
                                responseData = JSON.parse(text);
                                console.log('✅ Success with endpoint:', endpoint);
                                break;
                            }
                        }
                    } catch (error) {
                        console.log('❌ Failed with endpoint:', endpoint, error.message);
                        continue;
                    }
                }

                if (!responseData) {
                    throw new Error('All API endpoints failed');
                }
                
                console.log('📥 API Roles Response:', responseData);
                
                if (responseData && responseData.data) {
                    this.roles = Array.isArray(responseData.data) ? responseData.data : [];
                } else if (Array.isArray(responseData)) {
                    this.roles = responseData;
                } else {
                    this.roles = [];
                }
                
                console.log('✅ Roles loaded:', this.roles);
                
            } catch (error) {
                console.error('❌ Error fetching roles:', error);
                this.roles = [];
                
                // Demo data
                this.roles = [
                    {
                        id: 1,
                        name: 'Super_admin',
                        guard_name: 'api',
                        permissions_count: 15,
                        created_at: '2024-01-15T10:30:00.000000Z',
                        description: 'Full system access with all permissions'
                    },
                    {
                        id: 2,
                        name: 'Admin',
                        guard_name: 'api',
                        permissions_count: 10,
                        created_at: '2024-01-16T14:20:00.000000Z',
                        description: 'Administrative access with most permissions'
                    },
                    {
                        id: 3,
                        name: 'Manager',
                        guard_name: 'api',
                        permissions_count: 8,
                        created_at: '2024-01-17T09:15:00.000000Z',
                        description: 'Management level access'
                    },
                    {
                        id: 4,
                        name: 'Team_lead',
                        guard_name: 'api',
                        permissions_count: 6,
                        created_at: '2024-01-18T11:45:00.000000Z',
                        description: 'Team leadership access'
                    },
                    {
                        id: 5,
                        name: 'Sales_agent',
                        guard_name: 'api',
                        permissions_count: 4,
                        created_at: '2024-01-19T16:30:00.000000Z',
                        description: 'Sales agent access'
                    },
                    {
                        id: 6,
                        name: 'Viewer',
                        guard_name: 'api',
                        permissions_count: 2,
                        created_at: '2024-01-20T16:30:00.000000Z',
                        description: 'Read-only access'
                    }
                ];
                
                this.showNotification('Using demo data. API connection failed.', 'warning');
            } finally {
                this.loading = false;
            }
        },

        // Action methods
        addRole() {
            if (!this.$hasPermission('roles-create')) {
                this.showNotification('You do not have permission to create roles', 'warning');
                return;
            }
            this.$router.push('/add-role');
        },

        viewDetails(role) {
            this.$router.push(`/roles/${role.id}`);
        },

        editRole(role) {
            if (!this.canEditRole(role)) {
                if (this.isProtectedRole(role.id)) {
                    this.showNotification('Only Super Admin can modify system roles', 'warning');
                } else {
                    this.showNotification('You do not have permission to edit roles', 'warning');
                }
                return;
            }
            this.$router.push(`/roles/${role.id}/edit`);
        },

        async deleteRole(role) {
            if (!this.canDeleteRole(role)) {
                if (this.isProtectedRole(role.id)) {
                    this.showNotification('Only Super Admin can delete system roles', 'warning');
                } else {
                    this.showNotification('You do not have permission to delete roles', 'warning');
                }
                return;
            }

            const roleType = this.isProtectedRole(role.id) ? 'system role' : 'role';
            const confirmed = await this.showConfirm(
                'Are you sure?', 
                `You are about to delete ${roleType} "${role.name}". This action cannot be undone!`,
                'warning'
            );

            if (confirmed) {
                try {
                    this.loading = true;
                    // Remove from local list
                    this.roles = this.roles.filter(r => r.id !== role.id);
                    this.showNotification(`${roleType.charAt(0).toUpperCase() + roleType.slice(1)} "${role.name}" has been deleted successfully.`, 'success');
                } catch (error) {
                    console.error('❌ Error deleting role:', error);
                    this.showNotification(`Failed to delete ${roleType}: ${error.message}`, 'error');
                } finally {
                    this.loading = false;
                }
            }
        },

        // Refresh data
        async refreshData() {
            console.log('🔄 Refreshing data...');
            this.loading = true;
            await this.fetchCurrentUser();
            await this.fetchRoles();
            this.loading = false;
            this.debugUserData();
            this.showNotification('Data refreshed successfully', 'success');
        },

        // دالة للمساعدة في تصحيح الأخطاء
        debugUserData() {
            console.log('=== USER DATA DEBUG ===');
            console.log('Current User Object:', this.currentUser);
            console.log('LocalStorage User:', localStorage.getItem('user'));
            console.log('Is Super Admin:', this.isSuperAdmin);
            console.log('User Roles:', this.currentUser?.roles);
            console.log('User Role Name:', this.currentUser?.role_name);
            console.log('User Permissions:', this.currentUser?.permissions);
            console.log('Roles Count:', this.roles.length);
            console.log('Protected Roles:', this.protectedRoles);
            console.log('========================');
        },

        // Helper methods
        showNotification(message, type = 'info') {
            if (this.$showNotification) {
                this.$showNotification(message, type);
            } else {
                alert(`${type.toUpperCase()}: ${message}`);
            }
        },

        showConfirm(title, text, type = 'warning') {
            return new Promise((resolve) => {
                const confirmed = confirm(`${title}\n\n${text}`);
                resolve(confirmed);
            });
        },

        // Other methods
        sortBy(key) {
            if (this.sortKey === key) {
                this.sortAsc = !this.sortAsc;
            } else {
                this.sortKey = key;
                this.sortAsc = true;
            }
        },

        goToPage(page) {
            if (page >= 1 && page <= this.totalPages) {
                this.currentPage = page;
            }
        },

        formatDate(dateString) {
            if (!dateString) return 'N/A';
            try {
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            } catch (error) {
                return 'Invalid Date';
            }
        }
    }
};
</script>

<style scoped>
.sortable {
    cursor: pointer;
    user-select: none;
}

.sortable:hover {
    background-color: #f8f9fa;
}

.loading, .no-data {
    color: #7f8c8d;
    font-size: 16px;
}

.table th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
}

.table td {
    vertical-align: middle;
}

.radius-8 {
    border-radius: 8px;
}

.w-32-px {
    width: 32px;
}

.h-32-px {
    height: 32px;
}

.bg-primary-focus {
    background-color: rgba(13, 110, 253, 0.1);
}

.bg-success-focus {
    background-color: rgba(25, 135, 84, 0.1);
}

.bg-danger-focus {
    background-color: rgba(220, 53, 69, 0.1);
}

.page-link.disabled {
    opacity: 0.5;
    pointer-events: none;
    cursor: not-allowed;
}

.form-select-lr {
    border-radius: 10px !important;
}

.badge {
    font-size: 0.75rem;
}

/* Super Admin notice */
.alert-info {
    background-color: #d1ecf1;
    border-color: #bee5eb;
    color: #0c5460;
}

/* Debug panel */
.alert-warning {
    background-color: #fff3cd;
    border-color: #ffeaa7;
    color: #856404;
}

/* Role type badges */
.bg-warning {
    background-color: #fff3cd !important;
    color: #856404 !important;
}

.bg-success {
    background-color: #d1e7dd !important;
    color: #0f5132 !important;
}

/* Responsive design */
@media (max-width: 768px) {
    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .d-flex.gap-2 {
        flex-wrap: wrap;
    }
    
    .icon-field {
        width: 100%;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
}
</style>