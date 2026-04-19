<template>
    <div class="card basic-data-table">
        <div class="card-header">
            <h6 class="ui-h-mini card-title mb-0">Layout Types List</h6>
        </div>
        <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3"
                style="border-bottom: none; padding-bottom: 0px;">

                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <SearchableSelect preset="perPage10_15_20_all" v-model="selectedShow" :clearable="false" inline class="w-auto me-10" :input-style="{ borderRadius: '10px', height: '2.4rem', minWidth: '5.5rem' }" />
                        <span>entries per page</span>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <!-- Add Layout Type Button -->
                    <button v-if="this.$hasPermission('layout_types-create')" 
                        class="btn btn-primary"
                        @click="addPropertyType">
                        <iconify-icon icon="lucide:plus" class="me-2"></iconify-icon>
                        Add Layout Type
                    </button>
                    
                    <div class="icon-field d-flex align-items-center" style="padding-bottom: 5px;">
                        <span class="me-13">Search:</span>
                        <div class="position-relative" style="width: 100%; max-width: 240px;">
                            <input type="text" class="form-control form-control-sm w-100 px-3 pe-5" v-model="searchText"
                                style="border-radius: 10px; height: 2.5rem;" placeholder="Search Layout Types..." />
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
                            <tr v-for="propertyType in paginatedPropertyTypes" :key="propertyType.id">
                                <td>
                                    <div class="form-check style-check d-flex align-items-center">
                                        <!-- <input class="form-check-input" type="checkbox" v-model="selectedIds"
                                            :value="propertyType.id"> -->
                                        <label class="form-check-label">{{ propertyType.id }}</label>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0 me-12">
                                            <iconify-icon icon="lucide:layers" class="text-primary" width="24"></iconify-icon>
                                        </div>
                                        <div>
                                            <h6 class="text-md mb-0 fw-medium">{{ propertyType.name }}</h6>
                                        
                                        </div>
                                    </div>
                                </td>
                                <td>{{ formatDate(propertyType.created_at) }}</td>
                            
                                <td class="d-flex gap-2">
                                    <!-- Edit Button -->
                                    <a v-if="this.$hasPermission('layout_types-edit')" href="javascript:void(0)"
                                        class="w-32-px h-32-px bg-success-focus text-success-main rounded-circle d-inline-flex align-items-center justify-content-center"
                                        @click="editPropertyType(propertyType.id)"
                                        title="Edit Layout Type">
                                        <iconify-icon icon="lucide:edit"></iconify-icon>
                                    </a>

                                    <!-- Delete Button -->
                                    <a v-if="this.$hasPermission('layout_types-delete')" href="javascript:void(0)"
                                        class="w-32-px h-32-px bg-danger-focus text-danger-main rounded-circle d-inline-flex align-items-center justify-content-center"
                                        @click="deletePropertyType(propertyType)"
                                        title="Delete Layout Type">
                                        <iconify-icon icon="mingcute:delete-2-line"></iconify-icon>
                                    </a>

                                    <!-- No Permissions Message -->
                                    <span v-if="!hasAnyPropertyTypePermission()" class="text-muted text-sm">
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
                    <p class="mt-2">Loading Layout Types...</p>
                </div>

                <!-- No Data State -->
                <div v-if="!loading && propertyTypes.length === 0" class="no-data text-center py-4">
                    <iconify-icon icon="lucide:layers" class="text-muted mb-2" width="48"></iconify-icon>
                    <p>No Layout Types found</p>
                    <button v-if="this.$hasPermission('layout_types-create')" class="btn btn-primary mt-2" @click="addPropertyType">
                        <iconify-icon icon="lucide:plus" class="me-2"></iconify-icon>
                        Add First Layout Type
                    </button>
                </div>

                <!-- Pagination -->
                <div v-if="!loading && propertyTypes.length > 0 && selectedShow !== 'all'" 
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

                <!-- Show All Message -->
                <div v-if="!loading && propertyTypes.length > 0 && selectedShow === 'all'" 
                     class="d-flex justify-content-between align-items-center mt-24">
                    <span>
                        Showing all {{ totalEntries }} entries
                    </span>
                    <button class="btn btn-outline-secondary btn-sm" @click="selectedShow = '10'">
                        Show Pagination
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { API_ENDPOINTS } from '../../config/api';

export default {
    name: 'PropertyTypesTable',
    data() {
        return {
            loading: true,
            selectedShow: '10',
            searchText: '',
            selectAll: false,
            selectedIds: [],
            currentPage: 1,
            sortKey: '',
            sortAsc: true,
            propertyTypes: []
        };
    },
    computed: {
        entriesPerPage() {
            return this.selectedShow === 'all' ? this.filteredPropertyTypes.length : Number(this.selectedShow);
        },
        filteredPropertyTypes() {
            let result = [...this.propertyTypes];

            if (this.searchText) {
                const search = this.searchText.toLowerCase();
                result = result.filter(type =>
                    (type.name && type.name.toLowerCase().includes(search)) ||
                    (type.description && type.description.toLowerCase().includes(search)) ||
                    (type.parent && type.parent.name && type.parent.name.toLowerCase().includes(search))
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
                        valA = String(valA || '').toLowerCase();
                        valB = String(valB || '').toLowerCase();
                    }

                    return this.sortAsc ? valA > valB ? 1 : -1 : valA < valB ? 1 : -1;
                });
            }

            return result;
        },
        paginatedPropertyTypes() {
            if (this.selectedShow === 'all') {
                return this.filteredPropertyTypes;
            }
            return this.filteredPropertyTypes.slice(this.startIndex, this.endIndex);
        },
        totalEntries() {
            return this.filteredPropertyTypes.length;
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
        this.fetchPropertyTypes();
    },
    methods: {
        hasAnyPropertyTypePermission() {
            return this.$hasPermission('layout_types-edit') || 
                   this.$hasPermission('layout_types-delete');
        },

        // API methods
        async fetchPropertyTypes() {
            try {
                this.loading = true;
                
                const token = localStorage.getItem('token');
                const response = await fetch(API_ENDPOINTS.LAYOUT_TYPES, {
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
                    this.propertyTypes = data.data;
                } else if (Array.isArray(data)) {
                    this.propertyTypes = data;
                } else {
                    this.propertyTypes = [];
                }
                
                console.log('Layout Types loaded:', this.propertyTypes);
                
            } catch (error) {
                console.error('Error fetching Layout Types:', error);
                this.propertyTypes = [];
                
                this.showNotification('Failed to load Layout Types. Please try again.', 'error');
            } finally {
                this.loading = false;
            }
        },

        // Action methods
        addPropertyType() {
            if (!this.$hasPermission('layout_types-create')) {
                this.showNotification('You do not have permission to create Layout Types', 'warning');
                return;
            }
            this.$router.push('/add-layout_type');
        },

        editPropertyType(typeId) {
            if (!this.$hasPermission('layout_types-edit')) {
                this.showNotification('You do not have permission to edit Layout Types', 'warning');
                return;
            }
            this.$router.push(`/layout_types/${typeId}/edit`);
        },

        async deletePropertyType(propertyType) {
            if (!this.$hasPermission('layout_types-delete')) {
                this.showNotification('You do not have permission to delete Layout Types', 'warning');
                return;
            }

            const confirmed = await this.showConfirm(
                'Are you sure?', 
                `You are about to delete "${propertyType.name}". This action cannot be undone!`,
                'warning'
            );

            if (confirmed) {
                try {
                    this.loading = true;
                    const token = localStorage.getItem('token');
                    const response = await fetch(API_ENDPOINTS.LAYOUT_TYPE_BY_ID(propertyType.id), {
                        method: 'DELETE',
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Content-Type': 'application/json'
                        }
                    });

                    if (response.ok) {
                        // Remove from local list
                        this.propertyTypes = this.propertyTypes.filter(type => type.id !== propertyType.id);
                        
                        this.showNotification(`"${propertyType.name}" has been deleted successfully.`, 'success');
                    } else {
                        const errorData = await response.json();
                        throw new Error(errorData.message || 'Failed to delete Layout Type');
                    }
                } catch (error) {
                    console.error('Error deleting Layout Type:', error);
                    this.showNotification(`Failed to delete Layout Type: ${error.message}`, 'error');
                } finally {
                    this.loading = false;
                }
            }
        },

        // Helper methods
        showNotification(message, type = 'info') {
            if (this.$showNotification) {
                this.$showNotification(message, type);
            } else {
                this.showAlert('Notification', message, type);
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
                this.selectedIds = this.filteredPropertyTypes.map(type => type.id);
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
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
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
</style>