<template>
    <div class="card basic-data-table">
        <div class="card-header">
            <div class="row">
                <div class="col-md-6 text-start">
                    <h5 class="card-title mb-0">Users List</h5>
                </div>
                <div class="col-md-6 text-end">
                    <router-link to="/team-tree" class="btn btn-primary">
                        <iconify-icon icon="lucide:network" class="me-2"></iconify-icon>
                        Open Team Tree View
                    </router-link>
                </div>
            </div>
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
                        </select>
                        <span>entries per page</span>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <!-- Add User Button -->
                    <button v-if="this.$hasPermission('users-create')" 
                        class="btn btn-primary"
                        @click="addUser">
                        <iconify-icon icon="lucide:plus" class="me-2"></iconify-icon>
                        Add User
                    </button>
                    
                    <div class="icon-field d-flex align-items-center" style="padding-bottom: 5px;">
                        <span class="me-13">Search:</span>
                        <div class="position-relative" style="width: 100%; max-width: 240px;">
                            <input type="text" class="form-control form-control-sm w-100 px-3 pe-5" v-model="searchText"
                                style="border-radius: 10px; height: 2.5rem;" placeholder="Search users..." />
                            <span class="icon position-absolute end-0 top-50 translate-middle-y me-3 text-muted"
                                style="pointer-events: none;">
                                <iconify-icon icon="lucide:search"></iconify-icon>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table bordered-table mb-0">
                        <thead>
                            <tr>
                                <th scope="col" @click="sortBy('id')" class="sortable">
                                    <div class="form-check style-check d-flex align-items-center">
                                        <label class="form-check-label d-flex align-items-center">
                                            ID
                                            <span v-if="sortKey === 'id'">
                                                <iconify-icon
                                                    :icon="sortAsc ? 'mdi:arrow-up' : 'mdi:arrow-down'"></iconify-icon>
                                            </span>
                                        </label>
                                    </div>
                                </th>
                                <th scope="col" @click="sortBy('name')" class="sortable">
                                    User
                                    <span v-if="sortKey === 'name'">
                                        <iconify-icon :icon="sortAsc ? 'mdi:arrow-up' : 'mdi:arrow-down'"></iconify-icon>
                                    </span>
                                </th>
                                <th scope="col">Email</th>
                                <th scope="col">Role</th>
                                <th scope="col">Manager</th>
                                <th scope="col">Active/Inactive</th>
                                <th scope="col" @click="sortBy('last_login_at')" class="sortable">
                                    Last Login
                                    <span v-if="sortKey === 'last_login_at'">
                                        <iconify-icon :icon="sortAsc ? 'mdi:arrow-up' : 'mdi:arrow-down'"></iconify-icon>
                                    </span>
                                </th>
                                
                                <th scope="col">Actions</th>
                                <th scope="col" @click="sortBy('created_at')" class="sortable">
                                    Created Date
                                    <span v-if="sortKey === 'created_at'">
                                        <iconify-icon :icon="sortAsc ? 'mdi:arrow-up' : 'mdi:arrow-down'"></iconify-icon>
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in paginatedUsers" :key="user.id">
                                <td>
                                    <div class="form-check style-check d-flex align-items-center">
                                        <label class="form-check-label">{{ user.id }}</label>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="position-relative">
                                            <img
                                                :src="avatarUrl(user)"
                                                :alt="user.name || ''"
                                                class="flex-shrink-0 me-12 radius-8"
                                                width="40"
                                                height="40"
                                                style="object-fit: cover;"
                                                @error="handleImageError"
                                            />
                                            <span v-if="isUserOnline(user)" 
                                                  class="position-absolute bottom-0 end-0 bg-success rounded-circle border border-white"
                                                  style="width: 10px; height: 10px;"></span>
                                        </div>
                                        <div>
                                            <h6 class="text-md mb-0 fw-medium">{{ user.name }}</h6>
                                        </div>
                                    </div>
                                </td>
                                <td>{{user.email}}</td>
                                <td>
                                    <span class="">{{ user.role_name.replace(/_/g, ' ') }}</span>
                                </td>
                                <td>
                                    <p class="text-md mb-0 fw-medium" v-if="user.parent_name">{{ user.parent_name }}</p>
                                    <p v-else class="text-muted">-</p>
                                    <span class="text-muted" v-if="user.admin_parent_name && user.parent_id != user.admin_parent_id">{{ user.admin_parent_name }}</span>
                                </td>
                             
                             <td>
                                    <div class="status-toggle" v-if="(hasAdminRole() || hasSuperAdminRole()) && user.id != 1">
                                        <label class="toggle-switch">
                                            <input 
                                                type="checkbox" 
                                                :checked="user.status === 'active'"
                                                @change="confirmStatusChange(user, user.status === 'active' ? 'in_active' : 'active')"
                                                :disabled="statusLoading === user.id"
                                                class="toggle-input">
                                            <span class="toggle-slider"></span>
                                            <span class="toggle-labels">
                                                <!-- <span class="active-label">Active</span>
                                                <span class="inactive-label">Inactive</span> -->
                                            </span>
                                        </label>
                                        <div v-if="statusLoading === user.id" class="loading-spinner">
                                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                        </div>
                                    </div>
                                    <span v-else class="text-muted">No permission</span>
                                </td>
                                <td>
                                    <span v-if="user.last_login_at" class="text-sm">
                                        {{ formatLastLogin (user.last_login_at) }}
                                    </span>
                                    <span v-else class="text-muted text-sm">Never</span>
                                </td>
                                    <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                type="button" 
                                                data-bs-toggle="dropdown" 
                                                aria-expanded="false"
                                                :disabled="!hasAnyUserPermission()">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu">
                                            <!-- View Action -->
                                            <li v-if="this.$hasPermission('users-list')">
                                                <a class="dropdown-item" href="javascript:void(0)" @click="viewUser(user.id)">
                                                    <iconify-icon icon="iconamoon:eye-light" class="me-2"></iconify-icon>
                                                    View User
                                                </a>
                                            </li>
                                            
                                            <!-- Edit Action -->
                                            <li v-if="this.$hasPermission('users-edit') && user.id != 1">
                                                <a class="dropdown-item" href="javascript:void(0)" @click="editUser(user.id)">
                                                    <iconify-icon icon="lucide:edit" class="me-2"></iconify-icon>
                                                    Edit User
                                                </a>
                                            </li>
                                            
                                            <!-- Delete Action -->
                                            <li v-if="this.$hasPermission('users-delete') && user.id != 1">
                                                <a class="dropdown-item text-danger" href="javascript:void(0)" @click="deleteUser(user)">
                                                    <iconify-icon icon="mingcute:delete-2-line" class="me-2"></iconify-icon>
                                                    Delete User
                                                </a>
                                            </li>
                                            
                                            <!-- No Actions Available -->
                                            <li v-if="!hasAnyUserPermission()">
                                                <span class="dropdown-item text-muted">No actions available</span>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                                <td>{{ formatDate(user.created_at) }}</td>
                            
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Loading State -->
                <div v-if="loading" class="loading text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2">Loading users...</p>
                </div>

                <!-- No Data State -->
                <div v-if="!loading && users.length === 0" class="no-data text-center py-4">
                    <iconify-icon icon="lucide:users" class="text-muted mb-2" width="48"></iconify-icon>
                    <p>No users found</p>
                    <button v-if="this.$hasPermission('users-create')" class="btn btn-primary mt-2" @click="addUser">
                        <iconify-icon icon="lucide:plus" class="me-2"></iconify-icon>
                        Add First User
                    </button>
                </div>

                <!-- Pagination -->
                <div v-if="!loading && users.length > 0" class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-24">
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
                        <li class="page-item">
                            <a class="page-link text-secondary-light fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px bg-base"
                                href="javascript:void(0)" @click="goToPage(currentPage - 1)"
                                :class="{ disabled: currentPage === 1 }">
                                <iconify-icon icon="ep:arrow-left"></iconify-icon>
                            </a>
                        </li>

                        <li v-for="page in displayedPages" :key="page" class="page-item">
                            <a v-if="page !== '...'" href="javascript:void(0)"
                                class="page-link fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px"
                                :class="{
                                    'bg-primary-600 text-white': currentPage === page,
                                    'bg-primary-50 text-secondary-light': currentPage !== page
                                }" @click="goToPage(page)">
                                {{ page }}
                            </a>
                            <span v-else class="page-link bg-transparent border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px">
                                ...
                            </span>
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
</template>

<script>
import { API_ENDPOINTS } from '../../config/api';
import api from '@/plugins/axios';
import { useRouter } from 'vue-router';
export default {
    name: 'UsersTable',
    data() {
        return {
            loading: true,
            selectedShow: 10,
            searchText: '',
            currentPage: 1,
            sortKey: '',
            sortAsc: true,
            users: [],
            statusLoading: null,
                  defaultAvatar: '/assets/images/user.png'

        };
    },
    computed: {
        entriesPerPage() {
            return Number(this.selectedShow);
        },
        filteredUsers() {
            let result = [...this.users];

            if (this.searchText) {
                const search = this.searchText.toLowerCase();
                result = result.filter(user =>
                    (user.name && user.name.toLowerCase().includes(search)) ||
                    (user.email && user.email.toLowerCase().includes(search)) ||
                    (user.role_name && user.role_name.toLowerCase().includes(search)) ||
                    (user.parent_name && user.parent_name.toLowerCase().includes(search)) ||
                    (user.phone && user.phone.includes(search))
                );
            }

            // Sorting
            if (this.sortKey) {
                result.sort((a, b) => {
                    let valA = a[this.sortKey];
                    let valB = b[this.sortKey];

                    if (this.sortKey === 'created_at' || this.sortKey === 'last_login_at') {
                        valA = new Date(valA || 0);
                        valB = new Date(valB || 0);
                    } else if (this.sortKey === 'status') {
                        // Sort by status order: active > in_active > blocked
                        const statusOrder = { 'active': 1, 'in_active': 2, 'blocked': 3 };
                        valA = statusOrder[a.status] || 4;
                        valB = statusOrder[b.status] || 4;
                    } else {
                        valA = String(valA || '').toLowerCase();
                        valB = String(valB || '').toLowerCase();
                    }

                    return this.sortAsc ? valA > valB ? 1 : -1 : valA < valB ? 1 : -1;
                });
            }

            return result;
        },
        paginatedUsers() {
            return this.filteredUsers.slice(this.startIndex, this.endIndex);
        },
        totalEntries() {
            return this.filteredUsers.length;
        },
        totalPages() {
            return Math.ceil(this.totalEntries / this.entriesPerPage);
        },
        startIndex() {
            return (this.currentPage - 1) * this.entriesPerPage;
        },
        endIndex() {
            return Math.min(this.startIndex + this.entriesPerPage, this.totalEntries);
        },
        displayedPages() {
            const pages = [];
            const total = this.totalPages;
            const current = this.currentPage;
            
            if (total <= 7) {
                for (let i = 1; i <= total; i++) {
                    pages.push(i);
                }
            } else {
                if (current <= 4) {
                    for (let i = 1; i <= 5; i++) {
                        pages.push(i);
                    }
                    pages.push('...');
                    pages.push(total);
                } else if (current >= total - 3) {
                    pages.push(1);
                    pages.push('...');
                    for (let i = total - 4; i <= total; i++) {
                        pages.push(i);
                    }
                } else {
                    pages.push(1);
                    pages.push('...');
                    for (let i = current - 1; i <= current + 1; i++) {
                        pages.push(i);
                    }
                    pages.push('...');
                    pages.push(total);
                }
            }
            
            return pages;
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
        this.fetchUsers();
    },
    methods: {
        hasAnyUserPermission() {
            return this.$hasPermission('users-list') || 
                   this.$hasPermission('users-edit') || 
                   this.$hasPermission('users-delete');
        },
        
        hasAdminRole() {
            const userData = JSON.parse(localStorage.getItem('user') || '{}');
            return userData.roles && userData.roles.includes('admin');
        },

        hasSuperAdminRole() {
            const userData = JSON.parse(localStorage.getItem('user') || '{}');
            return userData.roles && userData.roles.includes('super_admin');
        },

        // New method to confirm status change
        async confirmStatusChange(user, newStatus) {
            // Check permissions
            if (!this.hasAdminRole() && !this.hasSuperAdminRole()) {
                this.showNotification('Only administrators can update user status', 'warning');
                // Reset toggle to original state
                this.$nextTick(() => {
                    user.status = user.status;
                });
                return;
            }

            // If already in the desired status, do nothing
            if (user.status === newStatus) {
                return;
            }

            const action = newStatus === 'active' ? 'activate' : 'deactivate';
            const actionText = newStatus === 'active' ? 'activate' : 'deactivate';
            const actionTitle = newStatus === 'active' ? 'Activate User' : 'Deactivate User';
            const actionMessage = newStatus === 'active' 
                ? `Are you sure you want to activate user "${user.name}"? This will allow them to access the system.`
                : `Are you sure you want to deactivate user "${user.name}"? This will prevent them from accessing the system.`;

            const confirmed = await this.showConfirm(
                actionTitle,
                actionMessage,'info'
                // newStatus === 'active' ? 'info' : 'warning'
            );

            if (confirmed) {
                await this.updateUserStatus(user, newStatus);
            } else {
                // Reset toggle to original state if user cancels
                this.$nextTick(() => {
                    user.status = user.status;
                });
            }
        },

        // Main function to handle status update
        async updateUserStatus(user, newStatus) {
            const actionText = newStatus === 'active' ? 'activate' : 'deactivate';

            try {
                this.statusLoading = user.id;

                // Call API to update status
                const updatedUser = await this.updateUserStatusAPI(user.id, newStatus);
                
                // Update local data
                const userIndex = this.users.findIndex(u => u.id === user.id);
                if (userIndex !== -1) {
                    this.users[userIndex].status = newStatus;
                }

                this.showNotification(`User "${user.name}" has been ${actionText}d successfully.`, 'success');
                
            } catch (error) {
                console.error(`Error ${actionText}ing user:`, error);
                
                // Reset to original status on error
                this.$nextTick(() => {
                    user.status = user.status;
                });
                
                this.showNotification(`Failed to ${actionText} user: ${error.message}`, 'error');
            } finally {
                this.statusLoading = null;
            }
        },

        // API methods
        async fetchUsers() {
            try {
                this.loading = true;
                
                const token = localStorage.getItem('token');
                const response = await fetch(API_ENDPOINTS.USERS, {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                
                // Handle different response formats
                if (data.data) {
                    this.users = data.data;
                } else if (Array.isArray(data)) {
                    this.users = data;
                } else {
                    this.users = [];
                }
                
                console.log('Users loaded:', this.users);
                
            } catch (error) {
                console.error('Error fetching users:', error);
                this.users = [];
                this.showNotification('Failed to load users. Please try again.', 'error');
            } finally {
                this.loading = false;
            }
        },

        // API Function to update user status
        async updateUserStatusAPI(userId, status) {
            const token = localStorage.getItem('token');
            
            const response = await fetch(API_ENDPOINTS.USER_STATUS(userId), {
                method: 'PUT',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    status: status
                })
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || `Failed to update user status`);
            }

            const result = await response.json();
            return result.data;
        },

        // Existing action methods
        addUser() {
            if (!this.$hasPermission('users-create')) {
                this.showNotification('You do not have permission to create users', 'warning');
                return;
            }
            this.$router.push('/add-user');
        },

        viewUser(userId) {
            if (!this.$hasPermission('users-list')) {
                this.showNotification('You do not have permission to view users', 'warning');
                return;
            }
            this.$router.push(`/users/${userId}`);
        },

        editUser(userId) {
            if (!this.$hasPermission('users-edit')) {
                this.showNotification('You do not have permission to edit users', 'warning');
                return;
            }
            this.$router.push(`/users/${userId}/edit`);
        },

        async deleteUser(user) {
            if (!this.$hasPermission('users-delete')) {
                this.showNotification('You do not have permission to delete users', 'warning');
                return;
            }

            const confirmed = await this.showConfirm(
                'Are you sure?', 
                `You are about to delete user "${user.name}". This action cannot be undone!`,
                'warning'
            );

            if (confirmed) {
                try {
                    this.loading = true;
                    const token = localStorage.getItem('token');
                    const response = await fetch(API_ENDPOINTS.USER_BY_ID(user.id), {
                        method: 'DELETE',
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Content-Type': 'application/json'
                        }
                    });

                    if (response.ok) {
                        this.users = this.users.filter(u => u.id !== user.id);
                        this.showNotification(`User "${user.name}" has been deleted successfully.`, 'success');
                    } else {
                        const errorData = await response.json();
                        throw new Error(errorData.message || 'Failed to delete user');
                    }
                } catch (error) {
                    console.error('Error deleting user:', error);
                    this.showNotification(`Failed to delete user: ${error.message}`, 'error');
                } finally {
                    this.loading = false;
                }
            }
        },

        // Helper methods
        showNotification(message, type = 'info') {
            if (window.$showNotification) {
                window.$showNotification(message, type);
            } else {
                console.log(`${type}: ${message}`);
                // Fallback notification
                const alertClass = {
                    'success': 'alert-success',
                    'error': 'alert-danger',
                    'warning': 'alert-warning',
                    'info': 'alert-info'
                }[type] || 'alert-info';
                
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
                alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                alertDiv.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.body.appendChild(alertDiv);
                
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.parentNode.removeChild(alertDiv);
                    }
                }, 5000);
            }
        },

        showConfirm(title, text, type = 'warning') {
            return new Promise((resolve) => {
                const confirmDiv = document.createElement('div');
                const alertClass = {
                    'success': 'alert-success',
                    'error': 'alert-danger', 
                    'warning': 'alert-warning',
                    'info': 'alert-info'
                }[type] || 'alert-warning';
                
                confirmDiv.className = `alert ${alertClass} position-fixed`;
                confirmDiv.style.cssText = 'top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 9999; min-width: 400px;';
                confirmDiv.innerHTML = `
                    <h5 class="alert-heading">${title}</h5>
                    <p class="mb-3">${text}</p>
                    <div class="d-flex gap-2 justify-content-end">
                        <button class="btn btn-secondary" id="confirmCancel">Cancel</button>
                        <button class="btn ${type === 'warning' ? 'btn-danger' : 'btn-primary'}" id="confirmOk">
                            ${type === 'warning' ? 'Delete' : 'Confirm'}
                        </button>
                    </div>
                `;
                
                const overlay = document.createElement('div');
                overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9998;';
                
                document.body.appendChild(overlay);
                document.body.appendChild(confirmDiv);

                document.getElementById('confirmOk').onclick = () => {
                    document.body.removeChild(overlay);
                    document.body.removeChild(confirmDiv);
                    resolve(true);
                };
                
                document.getElementById('confirmCancel').onclick = () => {
                    document.body.removeChild(overlay);
                    document.body.removeChild(confirmDiv);
                    resolve(false);
                };

                overlay.onclick = () => {
                    document.body.removeChild(overlay);
                    document.body.removeChild(confirmDiv);
                    resolve(false);
                };
            });
        },

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
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        },

        formatLastLogin(timestamp) {
            if (!timestamp) return 'Never';
            
            const now = new Date();
            const loginTime = new Date(timestamp);
            const diffMs = now - loginTime;
            const diffMins = Math.floor(diffMs / 60000);
            
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins}m ago`;
            
            const diffHours = Math.floor(diffMins / 60);
            if (diffHours < 24) return `${diffHours}h ago`;
            
            const diffDays = Math.floor(diffHours / 24);
            if (diffDays < 7) return `${diffDays}d ago`;
            
            return loginTime.toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric'
            });
        },

        isUserOnline(user) {
            if (!user.last_login_at) return false;
            const lastLogin = new Date(user.last_login_at);
            const now = new Date();
            const diffMinutes = (now - lastLogin) / (1000 * 60);
            return diffMinutes <= 15; // Online if logged in within last 15 minutes
        },

        /**
         * Laravel UserResource uses asset('storage/...'), which often points at APP_URL
         * (e.g. http://127.0.0.1:8001) while the SPA runs on another host. Use the same
         * origin as the page for /storage/ URLs so images load in production.
         */
        resolveMediaUrl(url) {
            if (!url || typeof url !== 'string') return null;
            try {
                const parsed = new URL(url, typeof window !== 'undefined' ? window.location.origin : undefined);
                const path = parsed.pathname + parsed.search;
                if (!path.includes('/storage/')) {
                    return parsed.href;
                }
                const badLocal = /^(127\.0\.0\.1|localhost)$/i.test(parsed.hostname);
                const pageOrigin = typeof window !== 'undefined' ? window.location.origin : '';
                if (!pageOrigin) return parsed.href;
                if (badLocal || parsed.origin !== pageOrigin) {
                    return `${pageOrigin}${path}`;
                }
                return parsed.href;
            } catch {
                if (typeof window === 'undefined') return url;
                if (url.startsWith('/')) return `${window.location.origin}${url}`;
                return `${window.location.origin}/storage/${url.replace(/^\/+/, '')}`;
            }
        },

        avatarUrl(user) {
            const raw = user?.avatar;
            if (!raw) return this.defaultAvatar;
            return this.resolveMediaUrl(raw) || this.defaultAvatar;
        },

        handleImageError(event) {
            const el = event.target;
            if (el.dataset.avatarFallback === '1') return;
            el.dataset.avatarFallback = '1';
            el.src = this.defaultAvatar;
        }
    }
};
</script>

<style scoped>
.status-toggle {
    position: relative;
    display: inline-block;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 40px;
    height: 24px;
    cursor: pointer;
}

.toggle-input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #E6E6E6;
    transition: .4s;
    border-radius: 24px;
    border: 2px solid #E6E6E6;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 14px;
    width: 14px;
    left: 2px;
    bottom: 4px;
    background-color: #01062C;
    transition: .4s;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

.toggle-input:checked + .toggle-slider {
    background-color: #01062C;
    border-color: #01062C;
}

.toggle-input:checked + .toggle-slider:before {
    transform: translateX(22px);
     background-color: white;
}

.toggle-input:disabled + .toggle-slider {
    opacity: 0.6;
    cursor: not-allowed;
}

.toggle-labels {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 6px;
    font-size: 9px;
    font-weight: 600;
    pointer-events: none;
}

.active-label {
    color: white;
    opacity: 0;
    transition: opacity 0.3s;
}

.inactive-label {
    color: white;
    opacity: 1;
    transition: opacity 0.3s;
}

.toggle-input:checked + .toggle-slider .active-label {
    opacity: 1;
}

.toggle-input:checked + .toggle-slider .inactive-label {
    opacity: 0;
}

.loading-spinner {
    position: absolute;
    top: 50%;
    right: -25px;
    transform: translateY(-50%);
}

.sortable {
    cursor: pointer;
    user-select: none;
    transition: background-color 0.2s;
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
    font-weight: 600;
    color: #495057;
}

.table td {
    vertical-align: middle;
    padding: 12px 8px;
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

.text-sm {
    font-size: 0.875rem;
}

.badge {
    font-size: 0.75rem;
    font-weight: 500;
}

.page-link {
    transition: all 0.2s;
}

.page-link:hover {
    transform: translateY(-1px);
}

.page-link.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.form-select, .form-control {
    border-radius: 8px;
}

.btn {
    border-radius: 8px;
    font-weight: 500;
}

/* Dropdown styles */
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

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .d-flex.gap-2 {
        flex-wrap: wrap;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .pagination {
        flex-wrap: wrap;
    }
    
    .dropdown {
        margin-bottom: 0.5rem;
    }
}

</style>