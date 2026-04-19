<template>
    <div class="card basic-data-table">
        <div class="card-header">
            <h6 class="ui-h-mini card-title mb-0">Areas Management</h6>
        </div>
        <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3"
                style="border-bottom: none; padding-bottom: 0px;">

                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <SearchableSelect preset="perPage10_15_20_all" v-model="selectedShow" :clearable="false" inline class="w-auto me-10" :input-style="{ borderRadius: '10px', height: '2.4rem', minWidth: '5.5rem' }" />
                        <span>entries per page</span>
                    </div>

                    <!-- Type Filter -->
                    <div class="d-flex align-items-center gap-2">
                        <SearchableSelect preset="areasType" v-model="selectedType" :clearable="false" inline class="w-auto" :input-style="{ borderRadius: '10px', height: '2.4rem', minWidth: '10rem' }" />
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <!-- Add Area Button -->
                    <button v-if="$hasPermission('areas-create')" 
                        class="btn btn-primary"
                        @click="addArea">
                        <iconify-icon icon="lucide:plus" class="me-2"></iconify-icon>
                        Add Area
                    </button>
                    
                    <div class="icon-field d-flex align-items-center" style="padding-bottom: 5px;">
                        <span class="me-13">Search:</span>
                        <div class="position-relative" style="width: 100%; max-width: 240px;">
                            <input type="text" class="form-control form-control-sm w-100 px-3 pe-5" v-model="searchText"
                                style="border-radius: 10px; height: 2.5rem;" placeholder="Search areas..." />
                            <span class="icon position-absolute end-0 top-50 translate-middle-y me-3 text-muted"
                                style="pointer-events: none;">
                                <iconify-icon icon="lucide:search"></iconify-icon>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tree View Toggle -->
            <div class="card-body border-bottom">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" v-model="showTreeView" id="treeViewSwitch">
                    <label class="form-check-label" for="treeViewSwitch">
                        Tree View
                    </label>
                </div>
            </div>

            <!-- Table View -->
            <div class="card-body " v-if="!showTreeView">
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
                                Name
                                <span v-if="sortKey === 'name'">
                                    <iconify-icon :icon="sortAsc ? 'mdi:arrow-up' : 'mdi:arrow-down'"></iconify-icon>
                                </span>
                            </th>
                            <th scope="col">Type</th>
                            <th scope="col">Parent</th>
                            <th scope="col">Children Count</th>
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
                        <tr v-for="area in paginatedAreas" :key="area.id">
                            <td>
                                <div class="form-check style-check d-flex align-items-center">
                                    <label class="form-check-label">{{ area.id }}</label>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0 me-12">
                                        <iconify-icon :icon="getTypeIcon(area.type)" class="text-primary" width="24"></iconify-icon>
                                    </div>
                                    <div>
                                        <h6 class="text-md mb-0 fw-medium">{{ area.name || 'N/A' }}</h6>
                                        <small class="text-muted">{{ area.area_parents_title || 'No parent title' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge" :class="getTypeBadgeClass(area.type)">
                                    {{ area.type || 'unknown' }}
                                </span>
                            </td>
                            <td>
                                <span v-if="area.parent_name" class="badge bg-light text-dark">
                                    {{ area.parent_name }}
                                </span>
                                <span v-else class="text-muted">-</span>
                            </td>
                            <td>
                                <span class="badge bg-info">
                                    {{ area.children_count || 0 }}
                                </span>
                            </td>
                            <td>{{ formatDate(area.created_at) }}</td>
                           
                            <td class="d-flex gap-2">
                              
                                <!-- Edit Button -->
                                <a v-if="$hasPermission('areas-edit')" href="javascript:void(0)"
                                    class="w-32-px h-32-px bg-success-focus text-success-main rounded-circle d-inline-flex align-items-center justify-content-center"
                                    @click="editArea(area.id)"
                                    title="Edit Area">
                                    <iconify-icon icon="lucide:edit"></iconify-icon>
                                </a>

                                <!-- Delete Button -->
                                <a v-if="$hasPermission('areas-delete')" href="javascript:void(0)"
                                    class="w-32-px h-32-px bg-danger-focus text-danger-main rounded-circle d-inline-flex align-items-center justify-content-center"
                                    @click="deleteArea(area)"
                                    title="Delete Area">
                                    <iconify-icon icon="mingcute:delete-2-line"></iconify-icon>
                                </a>

                                <!-- No Permissions Message -->
                                <span v-if="!hasAnyAreaPermission()" class="text-muted text-sm">
                                    No actions
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
               </div>
            </div>

            <!-- Tree View -->
            <div class="card-body" v-else>
                <div class="table-responsive">

                <div class="areas-tree">
                    <div v-for="country in areasTree" :key="country.id" class="tree-node">
                        <TreeNode 
                            :node="country" 
                            :level="0"
                            @edit="editArea"
                            @delete="deleteArea"
                            @view-children="viewChildren"
                            :permissions="{
                                edit: $hasPermission('areas-edit'),
                                delete: $hasPermission('areas-delete')
                            }" />
                    </div>
                    
                    <!-- No tree data message -->
                    <div v-if="areasTree.length === 0 && !loading" class="text-center text-muted py-4">
                        <iconify-icon icon="lucide:tree-pine" width="48" class="mb-2"></iconify-icon>
                        <p>No areas data available for tree view</p>
                    </div>
                </div>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="loading text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2">Loading areas...</p>
            </div>

            <!-- No Data State -->
            <div v-if="!loading && areas.length === 0" class="no-data text-center py-4">
                <iconify-icon icon="lucide:map-pin" class="text-muted mb-2" width="48"></iconify-icon>
                <p>No areas found</p>
                <button v-if="$hasPermission('areas-create')" class="btn btn-primary mt-2" @click="addArea">
                    <iconify-icon icon="lucide:plus" class="me-2"></iconify-icon>
                    Add First Area
                </button>
            </div>

        <!-- Pagination (Table View Only) -->
<div v-if="!showTreeView && !loading && areas.length > 0 && selectedShow !== 'all'" 
     class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-24">
    <span>
        Showing {{ startIndex + 1 }} to {{ endIndex }} of {{ totalEntries }} entries
    </span>
    <ul class="pagination d-flex flex-wrap align-items-center gap-2 justify-content-center">
        <li class="page-item">
            <a class="page-link text-secondary-light fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px bg-base"
                href="javascript:void(0)" @click="goToPage(1)"
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

        <!-- الصفحة الأولى -->
        <li class="page-item" v-if="currentPage > 3">
            <a href="javascript:void(0)"
                class="page-link fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px bg-primary-50 text-secondary-light"
                @click="goToPage(1)">1</a>
        </li>

        <!-- النقاط الأولى -->
        <li class="page-item" v-if="currentPage > 4">
            <span class="page-link border-0 bg-transparent">...</span>
        </li>

        <!-- الصفحات السابقة -->
        <li class="page-item" v-if="currentPage - 2 > 1">
            <a href="javascript:void(0)"
                class="page-link fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px bg-primary-50 text-secondary-light"
                @click="goToPage(currentPage - 2)">
                {{ currentPage - 2 }}
            </a>
        </li>
        <li class="page-item" v-if="currentPage - 1 > 1">
            <a href="javascript:void(0)"
                class="page-link fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px bg-primary-50 text-secondary-light"
                @click="goToPage(currentPage - 1)">
                {{ currentPage - 1 }}
            </a>
        </li>

        <!-- الصفحة الحالية -->
        <li class="page-item">
            <a href="javascript:void(0)"
                class="page-link fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px bg-primary-600 text-white"
                @click="goToPage(currentPage)">
                {{ currentPage }}
            </a>
        </li>

        <!-- الصفحات التالية -->
        <li class="page-item" v-if="currentPage + 1 < totalPages">
            <a href="javascript:void(0)"
                class="page-link fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px bg-primary-50 text-secondary-light"
                @click="goToPage(currentPage + 1)">
                {{ currentPage + 1 }}
            </a>
        </li>
        <li class="page-item" v-if="currentPage + 2 < totalPages">
            <a href="javascript:void(0)"
                class="page-link fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px bg-primary-50 text-secondary-light"
                @click="goToPage(currentPage + 2)">
                {{ currentPage + 2 }}
            </a>
        </li>

        <!-- النقاط الأخيرة -->
        <li class="page-item" v-if="currentPage < totalPages - 3">
            <span class="page-link border-0 bg-transparent">...</span>
        </li>

        <!-- الصفحة الأخيرة -->
        <li class="page-item" v-if="currentPage < totalPages - 2">
            <a href="javascript:void(0)"
                class="page-link fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px bg-primary-50 text-secondary-light"
                @click="goToPage(totalPages)">
                {{ totalPages }}
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
                href="javascript:void(0)" @click="goToPage(totalPages)"
                :class="{ disabled: currentPage === totalPages }">
                <iconify-icon icon="ep:d-arrow-right" class="text-xl"></iconify-icon>
            </a>
        </li>
    </ul>
</div>

            <!-- Show All Message -->
            <div v-if="!showTreeView && !loading && areas.length > 0 && selectedShow === 'all'" 
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
</template>

<script>
import { API_ENDPOINTS } from '../../config/api';
import TreeNode from './TreeNode.vue';

export default {
    name: 'AreasTable',
    components: {
        TreeNode
    },
    data() {
        return {
            loading: true,
            selectedShow: '10',
            selectedType: '',
            searchText: '',
            currentPage: 1,
            sortKey: '',
            sortAsc: true,
            areas: [],
            showTreeView: false,
            itemsPerPage: 10, // عدد العناصر في كل صفحة
          totalEntries:0
        };
    },
    computed: {
        entriesPerPage() {
            return this.selectedShow === 'all' ? this.filteredAreas.length : Number(this.selectedShow);
        },
 
        filteredAreas() {
            let result = [...this.areas];

            // Filter by type
            if (this.selectedType) {
                result = result.filter(area => area.type === this.selectedType);
            }

            // Filter by search text
            if (this.searchText) {
                const search = this.searchText.toLowerCase();
                result = result.filter(area => {
                    const name = area.name || '';
                    const parentTitle = area.area_parents_title || '';
                    const parentName = area.parent_name || '';
                    
                    return name.toLowerCase().includes(search) ||
                           parentTitle.toLowerCase().includes(search) ||
                           parentName.toLowerCase().includes(search);
                });
            }

            // Sorting
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
        paginatedAreas() {
            if (this.selectedShow === 'all' || this.showTreeView) {
                return this.filteredAreas;
            }
            return this.filteredAreas.slice(this.startIndex, this.endIndex);
        },
        totalEntries() {
            this.totalEntries= this.filteredAreas.length;
            return this.filteredAreas.length;
        },
       
         totalPages() {
            return Math.ceil(this.totalEntries / this.itemsPerPage);
        },
        startIndex() {
            return (this.currentPage - 1) * this.itemsPerPage;
        },
        endIndex() {
            const end = this.currentPage * this.itemsPerPage;
            return end > this.totalEntries ? this.totalEntries : end;
        },
        areasTree() {
            if (!this.areas.length) return [];

            // Build tree structure from flat list
            const areasMap = new Map();
            const roots = [];

            try {
                // Create map of all areas with safe defaults
                this.areas.forEach(area => {
                    areasMap.set(area.id, { 
                        ...area, 
                        children: [],
                        name: area.name || 'Unnamed',
                        type: area.type || 'unknown',
                        children_count: area.children_count || 0
                    });
                });

                // Build tree
                this.areas.forEach(area => {
                    const node = areasMap.get(area.id);
                    if (area.parent_id && areasMap.has(area.parent_id)) {
                        const parent = areasMap.get(area.parent_id);
                        if (parent && Array.isArray(parent.children)) {
                            parent.children.push(node);
                        }
                    } else {
                        roots.push(node);
                    }
                });

                return roots;
            } catch (error) {
                console.error('Error building areas tree:', error);
                return [];
            }
        }
    },
    watch: {
        selectedShow() {
            this.currentPage = 1;
        },
        searchText() {
            this.currentPage = 1;
        },
        selectedType() {
            this.currentPage = 1;
        }
    },
    mounted() {
        this.fetchAreas();
    },
    methods: {
          goToPage(page) {
            if (page < 1 || page > this.totalPages) return;
            this.currentPage = page;
            // هنا يمكنك إضافة كود جلب البيانات للصفحة الجديدة
        },
        hasAnyAreaPermission() {
            return this.$hasPermission('areas-edit') || 
                   this.$hasPermission('areas-delete');
        },

        getTypeIcon(type) {
            const icons = {
                country: 'lucide:globe',
                city: 'lucide:building',
                area: 'lucide:map-pin',
                community: 'lucide:users',
                sub_community: 'lucide:user-plus',
                cluster: 'lucide:group',
                building: 'lucide:home',
                phaces: 'lucide:layers'
            };
            return icons[type] || 'lucide:map-pin';
        },

        getTypeBadgeClass(type) {
            const classes = {
                country: 'bg-primary',
                city: 'bg-success',
                area: 'bg-info',
                community: 'bg-warning',
                sub_community: 'bg-secondary',
                cluster: 'bg-dark',
                building: 'bg-danger',
                phaces: 'bg-light text-dark'
            };
            return classes[type] || 'bg-light text-dark';
        },

        // API methods
        async fetchAreas() {
            try {
                this.loading = true;
                
                const token = localStorage.getItem('token');
                const response = await fetch(API_ENDPOINTS.AREAS, {
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
                
                console.log('API Response:', data);
                
                // Handle different response formats safely
                if (data && data.data) {
                    this.areas = Array.isArray(data.data) ? data.data : [];
                } else if (Array.isArray(data)) {
                    this.areas = data;
                } else {
                    this.areas = [];
                }

                // Ensure all areas have required properties
                this.areas = this.areas.map(area => ({
                    id: area.id || 0,
                    name: area.name || 'Unnamed',
                    type: area.type || 'unknown',
                    parent_id: area.parent_id || null,
                    parent_name: area.parent_name || null,
                    area_parents_title: area.area_parents_title || '',
                    children_count: area.children_count || 0,
                    created_at: area.created_at || null,
                    updated_at: area.updated_at || null,
                    added_by: area.added_by || null
                }));
                
                console.log('Areas loaded:', this.areas);
                
            } catch (error) {
                console.error('Error fetching areas:', error);
                this.areas = [];
                this.showNotification('Failed to load areas. Please try again.', 'error');
            } finally {
                this.loading = false;
            }
        },

        // Action methods
        addArea() {
            if (!this.$hasPermission('areas-create')) {
                this.showNotification('You do not have permission to create areas', 'warning');
                return;
            }
            this.$router.push('/add-area');
        },

        editArea(areaId) {
            if (!this.$hasPermission('areas-edit')) {
                this.showNotification('You do not have permission to edit areas', 'warning');
                return;
            }
            this.$router.push(`/areas/${areaId}/edit`);
        },

        viewChildren(area) {
            // Navigate to children view or expand in tree
            if (this.showTreeView) {
                // Handle tree expansion (implement in TreeNode component)
                console.log('View children for:', area);
            } else {
                this.$router.push(`/areas?parent_id=${area.id}`);
            }
        },

        async deleteArea(area) {
            if (!this.$hasPermission('areas-delete')) {
                this.showNotification('You do not have permission to delete areas', 'warning');
                return;
            }

            const confirmed = await this.showConfirm(
                'Are you sure?', 
                `You are about to delete "${area.name}". This action cannot be undone!`,
                'warning'
            );

            if (confirmed) {
                try {
                    this.loading = true;
                    const token = localStorage.getItem('token');
                    const response = await fetch(API_ENDPOINTS.AREA_BY_ID(area.id), {
                        method: 'DELETE',
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Content-Type': 'application/json'
                        }
                    });

                    if (response.ok) {
                        // Remove from local list
                        this.areas = this.areas.filter(a => a.id !== area.id);
                        
                        this.showNotification(`"${area.name}" has been deleted successfully.`, 'success');
                    } else {
                        const errorData = await response.json();
                        throw new Error(errorData.message || 'Failed to delete area');
                    }
                } catch (error) {
                    console.error('Error deleting area:', error);
                    this.showNotification(`Failed to delete area: ${error.message}`, 'error');
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

.areas-tree {
    padding: 1rem 0;
}
</style>