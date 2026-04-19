<template>
    <div class="card basic-data-table">
        <div class="card-header">
            <h6 class="ui-h-mini card-title mb-0">Developers List</h6>
        </div>
        <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3"
                style="border-bottom: none; padding-bottom: 0px;">

                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <SearchableSelect preset="perPage10_15_20" v-model="selectedShow" :clearable="false" inline class="w-auto me-10" :input-style="{ borderRadius: '10px', height: '2.4rem', minWidth: '5.5rem' }" />
                        <span>entries per page</span>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <!-- Add Developer Button -->
                    <button v-if="this.$hasPermission('developers-create')" 
                        class="btn btn-primary"
                        @click="addDeveloper">
                        <iconify-icon icon="lucide:plus" class="me-2"></iconify-icon>
                        Add Developer
                    </button>
                    
                    <div class="icon-field d-flex align-items-center" style="padding-bottom: 5px;">
                        <span class="me-13">Search:</span>
                              <div class="position-relative" style="width: 100%; max-width: 240px;">
                            <input type="text" class="form-control form-control-sm w-100 px-3 pe-5" v-model="searchText"
                                style="border-radius: 10px; height: 2.5rem;" placeholder="Search developers..." />
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
                                        <!-- <input class="form-check-input" type="checkbox" v-model="selectAll"
                                            @change="toggleSelectAll"> -->
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
                                    Name
                                    <span v-if="sortKey === 'name'">
                                        <iconify-icon :icon="sortAsc ? 'mdi:arrow-up' : 'mdi:arrow-down'"></iconify-icon>
                                    </span>
                                </th>
                                <th scope="col" @click="sortBy('added_by')" class="sortable">
                                    Added By
                                    <span v-if="sortKey === 'added_by'">
                                        <iconify-icon :icon="sortAsc ? 'mdi:arrow-up' : 'mdi:arrow-down'"></iconify-icon>
                                    </span>
                                </th>
                                <th scope="col" @click="sortBy('created_at')" class="sortable">
                                    Created Date
                                    <span v-if="sortKey === 'created_at'">
                                        <iconify-icon :icon="sortAsc ? 'mdi:arrow-up' : 'mdi:arrow-down'"></iconify-icon>
                                    </span>
                                </th>
                                <th scope="col">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="developer in paginatedDevelopers" :key="developer.id">
                                <td>
                                    <div class="form-check style-check d-flex align-items-center">
                                        <!-- <input class="form-check-input" type="checkbox" v-model="selectedIds"
                                            :value="developer.id"> -->
                                        <label class="form-check-label">{{ developer.id }}</label>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                    <img
                                            :src="developer.avatar || defaultAvatar"
                                            alt="Developer Avatar"
                                            class="flex-shrink-0 me-12 radius-8"
                                            width="40"
                                            height="40"
                                            />
                                        <h6 class="text-md mb-0 fw-medium flex-grow-1">{{ developer.name }}</h6>
                                    </div>
                                </td>
                                <td>{{ developer.added_by }}</td>
                                <td>{{ formatDate(developer.created_at) }}</td>
                                <td class="d-flex gap-2">
                                    <!-- View Button -->
                                    <a v-if="this.$hasPermission('developers-list')" href="javascript:void(0)"
                                        class="w-32-px h-32-px bg-primary-light text-primary-600 rounded-circle d-inline-flex align-items-center justify-content-center"
                                        @click="viewDeveloper(developer.id)"
                                        title="View Developer">
                                        <iconify-icon icon="iconamoon:eye-light"></iconify-icon>
                                    </a>

                                    <!-- Edit Button -->
                                    <a v-if="this.$hasPermission('developers-edit')" href="javascript:void(0)"
                                        class="w-32-px h-32-px bg-success-focus text-success-main rounded-circle d-inline-flex align-items-center justify-content-center"
                                        @click="editDeveloper(developer.id)"
                                        title="Edit Developer">
                                        <iconify-icon icon="lucide:edit"></iconify-icon>
                                    </a>

                                    <!-- Delete Button -->
                                    <a v-if="this.$hasPermission('developers-delete')" href="javascript:void(0)"
                                        class="w-32-px h-32-px bg-danger-focus text-danger-main rounded-circle d-inline-flex align-items-center justify-content-center"
                                        @click="deleteDeveloper(developer)"
                                        title="Delete Developer">
                                        <iconify-icon icon="mingcute:delete-2-line"></iconify-icon>
                                    </a>

                                    <!-- No Permissions Message -->
                                    <span v-if="!hasAnyDeveloperPermission()" class="text-muted text-sm">
                                        No actions
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                 </div>
                <!-- Loading State -->
                <div v-if="loading" class="loading text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2">Loading developers...</p>
                </div>

                <!-- No Data State -->
                <div v-if="!loading && developers.length === 0" class="no-data text-center py-4">
                    <iconify-icon icon="lucide:users" class="text-muted mb-2" width="48"></iconify-icon>
                    <p>No developers found</p>
                    <button v-if="this.$hasPermission('developers-create')" class="btn btn-primary mt-2" @click="addDeveloper">
                        <iconify-icon icon="lucide:plus" class="me-2"></iconify-icon>
                        Add First Developer
                    </button>
                </div>

                <!-- Pagination -->
                <div v-if="!loading && developers.length > 0" class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-24">
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
</template>

<script>
import { API_ENDPOINTS } from '../../config/api';
import defaultAvatar from "@/assets/images/user.png";
export default {
    name: 'DevelopersTable',
    data() {
        return {
            loading: true,
            selectedShow: 10,
            searchText: '',
            selectAll: false,
            selectedIds: [],
            currentPage: 1,
            sortKey: '',
            sortAsc: true,
            developers: [],
            defaultAvatar
        };
    },
    computed: {
        entriesPerPage() {
            return Number(this.selectedShow);
        },
        filteredDevelopers() {
            let result = [...this.developers];

            if (this.searchText) {
                const search = this.searchText.toLowerCase();
                result = result.filter(dev =>
                    dev.name.toLowerCase().includes(search) 
                   
                );
            }

            // Sorting
            if (this.sortKey) {
                result.sort((a, b) => {
                    let valA = a[this.sortKey];
                    let valB = b[this.sortKey];

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
        paginatedDevelopers() {
            return this.filteredDevelopers.slice(this.startIndex, this.endIndex);
        },
        totalEntries() {
            return this.filteredDevelopers.length;
        },
        totalPages() {
            return Math.ceil(this.totalEntries / this.entriesPerPage);
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
        this.fetchDevelopers();
    },
    methods: {
        

        hasAnyDeveloperPermission() {
            return this.$hasPermission('developers-list') || 
                   this.$hasPermission('developers-edit') || 
                   this.$hasPermission('developers-delete');
        },

        // API methods
        async fetchDevelopers() {
            try {
                this.loading = true;
                
                const token = localStorage.getItem('token');
                const response = await fetch(API_ENDPOINTS.DEVELOPERS, {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error('Failed to fetch developers');
                }

                const data = await response.json();
                this.developers = data.data || [];
                
            } catch (error) {
                console.error('Error fetching developers:', error);
                this.developers = [];
                
                // Show error message
                this.showAlert('Error', 'Failed to load developers. Please try again.', 'error');
            } finally {
                this.loading = false;
            }
        },

        // Action methods
        addDeveloper() {
            if (!this.$hasPermission('developers-create')) {
                this.showAlert('Permission Denied', 'You do not have permission to create developers', 'warning');
                return;
            }
            this.$router.push('/add-developer');
        },

        viewDeveloper(developerId) {
            if (!this.$hasPermission('developers-list')) {
                this.showAlert('Permission Denied', 'You do not have permission to view developers', 'warning');
                return;
            }
            this.$router.push(`/developers/${developerId}`);
        },

        editDeveloper(developerId) {
            if (!this.$hasPermission('developers-edit')) {
                this.showAlert('Permission Denied', 'You do not have permission to edit developers', 'warning');
                return;
            }
            this.$router.push(`/developers/${developerId}/edit`);
        },

        async deleteDeveloper(developer) {
            if (!this.$hasPermission('developers-delete')) {
                this.showAlert('Permission Denied', 'You do not have permission to delete developers', 'warning');
                return;
            }

            const confirmed = await this.showConfirm(
                'Are you sure?', 
                `You are about to delete "${developer.name}". This action cannot be undone!`,
                'warning'
            );

            if (confirmed) {
                try {
                    this.loading = true;
                    const token = localStorage.getItem('token');
                    const response = await fetch(API_ENDPOINTS.DEVELOPER_BY_ID(developer.id), {
                        method: 'DELETE',
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Content-Type': 'application/json'
                        }
                    });

                    if (response.ok) {
                        // Remove from local list
                        this.developers = this.developers.filter(dev => dev.id !== developer.id);
                        
                        this.showAlert('Deleted!', `${developer.name} has been deleted successfully.`, 'success');
                        
                        // Refresh the list
                        this.fetchDevelopers();
                    } else {
                        const errorData = await response.json();
                        throw new Error(errorData.message || 'Failed to delete developer');
                    }
                } catch (error) {
                    console.error('Error deleting developer:', error);
                    this.showAlert('Error!', `Failed to delete developer: ${error.message}`, 'error');
                } finally {
                    this.loading = false;
                }
            }
        },

        showAlert(title, text, type = 'info') {
            // Create custom alert
            const alertDiv = document.createElement('div');
            const alertClass = this.getAlertClass(type);
            alertDiv.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                <strong>${title}</strong> ${text}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
            }, 5000);
        },

        showConfirm(title, text, type = 'warning') {
            return new Promise((resolve) => {
                const confirmDiv = document.createElement('div');
                const alertClass = this.getAlertClass(type);
                confirmDiv.className = `alert ${alertClass} position-fixed`;
                confirmDiv.style.cssText = 'top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 9999; min-width: 400px;';
                confirmDiv.innerHTML = `
                    <h6 class="ui-h-mini alert-heading">${title}</h6>
                    <p class="mb-3">${text}</p>
                    <div class="d-flex gap-2 justify-content-end">
                        <button class="btn btn-secondary" id="confirmCancel">Cancel</button>
                        <button class="btn btn-danger" id="confirmOk">Delete</button>
                    </div>
                `;
                
                // Add overlay
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

                // Close on overlay click
                overlay.onclick = () => {
                    document.body.removeChild(overlay);
                    document.body.removeChild(confirmDiv);
                    resolve(false);
                };
            });
        },

        getAlertClass(type) {
            const classes = {
                'success': 'alert-success',
                'error': 'alert-danger', 
                'warning': 'alert-warning',
                'info': 'alert-info'
            };
            return classes[type] || 'alert-info';
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

        toggleSelectAll() {
            if (this.selectAll) {
                this.selectedIds = this.filteredDevelopers.map(dev => dev.id);
            } else {
                this.selectedIds = [];
            }
        },

        goToPage(page) {
            if (page >= 1 && page <= this.totalPages) {
                this.currentPage = page;
            }
        },

        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US');
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
    text-decoration: underline;
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
</style>