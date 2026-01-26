<template>
    <div class="dashboard-main-body">
        <Breadcrumb 
            title="Projects List" 
            :breadcrumbs="[
                { name: 'Dashboard', path: '/dashboard' },
                { name: 'Projects' }
            ]" 
        />

        <div class="card basic-data-table">
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
                    <button v-if="this.$hasPermission('projects-create')" 
                        class="btn btn-primary"
                        @click="addProject">
                        <iconify-icon icon="lucide:plus" class="me-2"></iconify-icon>
                        Add Project
                    </button>
                    
                    <div class="icon-field d-flex align-items-center" style="padding-bottom: 5px;">
                        <span class="me-13">Search:</span>
                        <div class="position-relative" style="width: 100%; max-width: 240px;">
                            <input type="text" class="form-control form-control-sm w-100 px-3 pe-5" v-model="searchText"
                                style="border-radius: 10px; height: 2.5rem;" placeholder="Search Projects..." />
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
                                <th scope="col">ID</th>
                                <th scope="col">Image
                                <button class="btn btn-sm btn-link p-0 ms-1" 
                                        @click="toggleImageSort"
                                        title="Sort by image status">
                                    <iconify-icon icon="lucide:arrow-up-down"></iconify-icon>
                                </button>
                                </th>
                               <th @click="sortBy('title')" class="sortable">
                                        Title
                                        <iconify-icon
                                            v-if="sortKey === 'title'"
                                            :icon="sortDirection === 'asc' ? 'lucide:arrow-up' : 'lucide:arrow-down'"
                                        />
                                    </th>
                                    
                                    <th @click="sortBy('developer')" class="sortable">Developer</th>
                                    <th @click="sortBy('area')" class="sortable">Area</th>
                                    <th @click="sortBy('status')" class="sortable">Status</th>
                                    <th @click="sortBy('count_listing')" class="sortable">Count of Listing</th>
                                    <th @click="sortBy('duplicated')" class="sortable">Duplicated</th>
                                    <th @click="sortBy('created_at')" class="sortable">Created Date</th>

                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="project in paginatedProjects" :key="project.id">
                                <td>{{ project.id }}</td>
                                 <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img v-if="project.main_image" 
                                             :src="project.main_image" 
                                             alt="Project Image"
                                             class="flex-shrink-0 me-2 radius-8"
                                             width="60"
                                             height="60"
                                             style="object-fit: cover;">
                                        <div v-else class="bg-light rounded d-flex align-items-center justify-content-center"
                                             style="width: 60px; height: 60px;">
                                            <iconify-icon icon="lucide:image" class="text-muted"></iconify-icon>
                                        </div>
                                        
                                        <!--<span class="badge" :class="project.has_image ? 'bg-success' : 'bg-danger'">-->
                                        <!--    {{ project.has_image ? 'Has Image' : 'No Image' }}-->
                                        <!--</span>-->
                                    </div>
                                </td>
                                <td>
                                    <h6 class="text-md mb-0 fw-medium">{{ project.title }}</h6>
                                    <!--<small class="text-muted">-->
                                    <!--    {{ project.from_sqft }} - {{ project.to_sqft }} sqft-->
                                    <!--</small>-->
                                </td>
                                <td>
                                    <div v-if="project.developer" class="d-flex align-items-center">
                                        <img v-if="project.developer.avatar"
                                             :src="project.developer.avatar"
                                             alt="Developer"
                                             class="rounded-circle me-2"
                                             width="24"
                                             height="24"
                                             style="object-fit: cover;">
                                        <span>{{ project.developer.name }}</span>
                                    </div>
                                    <span v-else class="text-muted">N/A</span>
                                </td>
                                <!--<td>-->
                                <!--    <span v-if="project.from_price || project.to_price">-->
                                <!--        Starting From {{ formatPrice(project.from_price) }}-->
                                <!--    </span>-->
                                <!--    <span v-else class="text-muted">N/A</span>-->
                                <!--</td>-->
                                <td>
                                    <span v-if="project.area">
                                        {{ project.area.name }}
                                    </span>
                                    <span v-else class="text-muted">N/A</span>
                                </td>
                                <td>
                                    <small class="badge text-white" :class="statusBadgeClass(project.status)">
                                        {{ project.status_label }}
                                    </small>
                                </td>
                                <td>{{project.listing_count}}</td>
                                <td> <router-link v-if="project.duplicated_project"
                                                 :to="`/projects/${project.duplicated_project.id}`"
                                                 class="text-danger text-decoration-underline">
                                      {{ project.duplicated_project.title }}
                                    </router-link>
                                    <span v-else class="text-muted">No</span>

                                </td>
                                 <td>
                                    <div class="completion-indicator">
                                        <div class="progress" style="height: 6px; width: 100px;">
                                            <div class="progress-bar" 
                                                 :class="completionClass(project.completion_percentage)"
                                                 :style="{ width: project.completion_percentage + '%' }">
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex align-items-center gap-2 mt-1">
                                            <span class="text-sm fw-medium">{{ project.completion_percentage }}%</span>
                                            <!--<span class="badge" :class="completionBadgeClass(project.completion_percentage)">-->
                                            <!--    {{ project.completion_status }}-->
                                            <!--</span>-->
                                        </div>
                                        
                                        <div class="completion-details mt-1">
                                            <small class="d-block text-muted" v-if="!project.has_image">❌ No Image</small>
                                            <small class="d-block text-muted" v-if="!project.description">❌ No Description</small>
                                            <small class="d-block text-muted" v-if="!project.developer">❌ No Developer</small>
                                            <small class="d-block text-muted" v-if="!project.area">❌ No Area</small>
                                            <small class="d-block text-muted" v-if="!project.features || project.features.length === 0">❌ No Features</small>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ formatDate(project.created_at) }}</td>
                                <td class="d-flex gap-2">
                                    <a v-if="this.$hasPermission('projects-list')" href="javascript:void(0)"
                                        class="w-32-px h-32-px bg-primary-light text-primary-600 rounded-circle d-inline-flex align-items-center justify-content-center"
                                        @click="viewProject(project.id)"
                                        title="View Project">
                                        <iconify-icon icon="iconamoon:eye-light"></iconify-icon>
                                    </a>

                                    <a v-if="this.$hasPermission('projects-edit')" href="javascript:void(0)"
                                        class="w-32-px h-32-px bg-success-focus text-success-main rounded-circle d-inline-flex align-items-center justify-content-center"
                                        @click="editProject(project.id)"
                                        title="Edit Project">
                                        <iconify-icon icon="lucide:edit"></iconify-icon>
                                    </a>

                                    <a v-if="this.$hasPermission('projects-delete')" href="javascript:void(0)"
                                        class="w-32-px h-32-px bg-danger-focus text-danger-main rounded-circle d-inline-flex align-items-center justify-content-center"
                                        @click="deleteProject(project)"
                                        title="Delete Project">
                                        <iconify-icon icon="mingcute:delete-2-line"></iconify-icon>
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Loading State -->
                <div v-if="loading" class="loading text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2">Loading Projects...</p>
                </div>

                <!-- No Data State -->
                <div v-if="!loading && projects.length === 0" class="no-data text-center py-4">
                    <iconify-icon icon="lucide:folder" class="text-muted mb-2" width="48"></iconify-icon>
                    <p>No Projects found</p>
                    <button v-if="this.$hasPermission('projects-create')" class="btn btn-primary mt-2" @click="addProject">
                        <iconify-icon icon="lucide:plus" class="me-2"></iconify-icon>
                        Add First Project
                    </button>
                </div>

                <!-- Show All Message -->
                <div v-if="!loading && projects.length > 0 && selectedShow === 'all'" 
                     class="d-flex justify-content-between align-items-center mt-24">
                    <span>
                        Showing all {{ totalEntries }} entries
                    </span>
                    <button class="btn btn-outline-secondary btn-sm" @click="selectedShow = 10">
                        Show Pagination
                    </button>
                </div>

                <!-- Pagination -->
                <div v-if="!loading && projects.length > 0 && selectedShow !== 'all' && totalPages > 1" 
                     class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-24">
                    <span>
                        Showing {{ startIndex + 1 }} to {{ endIndex }} of {{ totalEntries }} entries
                    </span>
                    <ul class="pagination d-flex flex-wrap align-items-center gap-2 justify-content-center">
                        <!-- First Page Button -->
                        <li class="page-item">
                            <a class="page-link text-secondary-light fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px bg-base"
                                href="javascript:void(0)" @click="goToPage(1)"
                                :class="{ disabled: currentPage === 1 }"
                                title="First Page">
                                <iconify-icon icon="ep:d-arrow-left" class="text-xl"></iconify-icon>
                            </a>
                        </li>
                        
                        <!-- Previous Page Button -->
                        <li class="page-item">
                            <a class="page-link text-secondary-light fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px bg-base"
                                href="javascript:void(0)" @click="goToPage(currentPage - 1)"
                                :class="{ disabled: currentPage === 1 }"
                                title="Previous Page">
                                <iconify-icon icon="ep:arrow-left"></iconify-icon>
                            </a>
                        </li>

                        <!-- First Page Number -->
                        <li class="page-item" v-if="currentPage > 3">
                            <a href="javascript:void(0)"
                                class="page-link fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px bg-primary-50 text-secondary-light"
                                @click="goToPage(1)">
                                1
                            </a>
                        </li>

                        <!-- First Dots -->
                        <li class="page-item" v-if="currentPage > 4">
                            <span class="page-link border-0 bg-transparent">...</span>
                        </li>

                        <!-- Previous Pages -->
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

                        <!-- Current Page -->
                        <li class="page-item">
                            <a href="javascript:void(0)"
                                class="page-link fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px bg-primary-600 text-white"
                                @click="goToPage(currentPage)">
                                {{ currentPage }}
                            </a>
                        </li>

                        <!-- Next Pages -->
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

                        <!-- Last Dots -->
                        <li class="page-item" v-if="currentPage < totalPages - 3">
                            <span class="page-link border-0 bg-transparent">...</span>
                        </li>

                        <!-- Last Page Number -->
                        <li class="page-item" v-if="currentPage < totalPages - 2">
                            <a href="javascript:void(0)"
                                class="page-link fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px bg-primary-50 text-secondary-light"
                                @click="goToPage(totalPages)">
                                {{ totalPages }}
                            </a>
                        </li>

                        <!-- Next Page Button -->
                        <li class="page-item">
                            <a class="page-link text-secondary-light fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px bg-base"
                                href="javascript:void(0)" @click="goToPage(currentPage + 1)"
                                :class="{ disabled: currentPage === totalPages }"
                                title="Next Page">
                                <iconify-icon icon="ep:arrow-right" class="text-xl"></iconify-icon>
                            </a>
                        </li>

                        <!-- Last Page Button -->
                        <li class="page-item">
                            <a class="page-link text-secondary-light fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px bg-base"
                                href="javascript:void(0)" @click="goToPage(totalPages)"
                                :class="{ disabled: currentPage === totalPages }"
                                title="Last Page">
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
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';

export default {
    name: 'ProjectsTable',
    components: {
        Breadcrumb
    },
    data() {
        return {
            loading: true,
            selectedShow: 10,
            searchText: '',
            currentPage: 1,
            projects: [],
             sortByImage: 'all' ,
        sortKey: null,          // title | status | duplicated | created_at | ...
        sortDirection: 'asc'    // asc | desc
        };
    },
    computed: {
        filteredProjects() {
                let filtered = this.projects;
            
                // 🔍 search
                if (this.searchText) {
                    const search = this.searchText.toLowerCase();
                    filtered = filtered.filter(project =>
                        project.title.toLowerCase().includes(search) ||
                        (project.developer?.name && project.developer.name.toLowerCase().includes(search)) ||
                        (project.area?.name && project.area.name.toLowerCase().includes(search)) ||
                        project.status.toLowerCase().includes(search)
                    );
                }
            
                // 🔁 SORTING 
                if (this.sortKey) {
                    filtered = [...filtered].sort((a, b) => {
                        let aVal, bVal;
            
                        switch (this.sortKey) {
                            case 'title':
                                aVal = a.title?.toLowerCase();
                                bVal = b.title?.toLowerCase();
                                break;
            
                            case 'status':
                                aVal = a.status;
                                bVal = b.status;
                                break;
            
                            case 'area':
                                aVal = a.area?.name?.toLowerCase();
                                bVal = b.area?.name?.toLowerCase();
                                break;
            
                            case 'developer':
                                aVal = a.developer?.name?.toLowerCase();
                                bVal = b.developer?.name?.toLowerCase();
                                break;
            
                            case 'duplicated':
                                aVal = a.duplicated_project ? 1 : 0;
                                bVal = b.duplicated_project ? 1 : 0;
                                break;
                           case 'count_listing':
                                    aVal = Number(a.listing_count) || 0;
                                    bVal = Number(b.listing_count) || 0;
                                    break;

            
                            case 'created_at':
                                aVal = new Date(a.created_at);
                                bVal = new Date(b.created_at);
                                break;
            
                            default:
                                return 0;
                        }
            
                        if (aVal == null) return 1;
                        if (bVal == null) return -1;
            
                        if (aVal < bVal) return this.sortDirection === 'asc' ? -1 : 1;
                        if (aVal > bVal) return this.sortDirection === 'asc' ? 1 : -1;
                        return 0;
                    });
                }
            
                return filtered;
            },

        paginatedProjects() {
            if (this.selectedShow === 'all') {
                return this.filteredProjects;
            }
            const start = (this.currentPage - 1) * Number(this.selectedShow);
            const end = start + Number(this.selectedShow);
            return this.filteredProjects.slice(start, end);
        },
        totalEntries() {
            return this.filteredProjects.length;
        },
        totalPages() {
            if (this.selectedShow === 'all') return 1;
            return Math.ceil(this.totalEntries / Number(this.selectedShow));
        },
        startIndex() {
            if (this.selectedShow === 'all') return 0;
            return (this.currentPage - 1) * Number(this.selectedShow);
        },
        endIndex() {
            if (this.selectedShow === 'all') return this.totalEntries;
            return Math.min(this.startIndex + Number(this.selectedShow), this.totalEntries);
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
        this.fetchProjects();
    },
    methods: {
          sortBy(key) {
            if (this.sortKey === key) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortKey = key;
                this.sortDirection = 'asc';
            }
        },
          toggleImageSort() {
            const options = ['all', 'has-image', 'no-image'];
            const currentIndex = options.indexOf(this.sortByImage);
            this.sortByImage = options[(currentIndex + 1) % options.length];
        },
        
        async fetchProjects() {
            try {
                this.loading = true;
                const token = localStorage.getItem('token');
                const response = await fetch(API_ENDPOINTS.PROJECTS, {
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    }
                });

                if (!response.ok) throw new Error('Failed to fetch projects');
                
                const data = await response.json();
                this.projects = (data.data || []).map(project => ({
                    ...project,
                    has_image: !!project.main_image,
                    description: project.about,
                    completion_percentage: this.calculateCompletionPercentage(project),
                    completion_status: this.getCompletionStatus(project)
                }));
                
            } catch (error) {
                console.error('Error fetching projects:', error);
                this.projects = [];
                this.$showNotification('Failed to load projects', 'error');
            } finally {
                this.loading = false;
            }
        },
        
        calculateCompletionPercentage(project) {
            const fields = [
                { name: 'title', value: project.title, weight: 20 },
                { name: 'image', value: project.main_image, weight: 15 },
                { name: 'description', value: project.about, weight: 20 },
                { name: 'developer', value: project.developer, weight: 15 },
                { name: 'area', value: project.area, weight: 15 },
                { name: 'features', value: project.features && project.features.length > 0, weight: 15 }
            ];
            
            let totalScore = 0;
            let totalWeight = 0;
            
            fields.forEach(field => {
                totalWeight += field.weight;
                if (field.value) {
                    if (field.name === 'features') {
                        totalScore += field.value ? field.weight : 0;
                    } else if (field.value && (typeof field.value === 'string' ? field.value.trim() : true)) {
                        totalScore += field.weight;
                    }
                }
            });
            
            return totalWeight > 0 ? Math.round((totalScore / totalWeight) * 100) : 0;
        },
        
        getCompletionStatus(project) {
            const percentage = this.calculateCompletionPercentage(project);
            if (percentage === 100) return 'Complete';
            if (percentage >= 80) return 'Almost Complete';
            if (percentage >= 50) return 'Partially Complete';
            return 'Incomplete';
        },
        
        completionClass(percentage) {
            if (percentage === 100) return 'bg-success';
            if (percentage >= 80) return 'bg-primary';
            if (percentage >= 50) return 'bg-warning';
            return 'bg-danger';
        },
        
        completionBadgeClass(percentage) {
            if (percentage === 100) return 'bg-success';
            if (percentage >= 80) return 'bg-primary';
            if (percentage >= 50) return 'bg-warning';
            return 'bg-danger';
        },

        addProject() {
            if (!this.$hasPermission('projects-create')) {
                this.$showNotification('You do not have permission to create projects', 'warning');
                return;
            }
            this.$router.push('/add-projects');
        },

        viewProject(id) {
            if (!this.$hasPermission('projects-list')) {
                this.$showNotification('You do not have permission to view projects', 'warning');
                return;
            }
            this.$router.push(`/projects/${id}`);
        },

        editProject(id) {
            if (!this.$hasPermission('projects-edit')) {
                this.$showNotification('You do not have permission to edit projects', 'warning');
                return;
            }
            this.$router.push(`/projects/${id}/edit`);
        },

        async deleteProject(project) {
            if (!this.$hasPermission('projects-delete')) {
                this.$showNotification('You do not have permission to delete projects', 'warning');
                return;
            }

            const confirmed = await this.showConfirm(
                'Are you sure?', 
                `You are about to delete "${project.title}". This action cannot be undone!`,
                'warning'
            );
            
            if (confirmed) {
                try {
                    this.loading = true;
                    const token = localStorage.getItem('token');
                    const response = await fetch(API_ENDPOINTS.PROJECT_BY_ID(project.id), {
                        method: 'DELETE',
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Content-Type': 'application/json'
                        }
                    });

                    if (response.ok) {
                        this.projects = this.projects.filter(p => p.id !== project.id);
                        this.$showNotification(`${project.title} has been deleted successfully`, 'success');
                    } else {
                        throw new Error('Failed to delete project');
                    }
                } catch (error) {
                    console.error('Error deleting project:', error);
                    this.$showNotification('Failed to delete project', 'error');
                } finally {
                    this.loading = false;
                }
            }
        },

        showConfirm(title, text, type = 'warning') {
            return new Promise((resolve) => {
                const confirmDiv = document.createElement('div');
                const alertClass = this.getAlertClass(type);
                confirmDiv.className = `alert ${alertClass} position-fixed`;
                confirmDiv.style.cssText = 'top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 9999; min-width: 400px;';
                confirmDiv.innerHTML = `
                    <h5 class="alert-heading">${title}</h5>
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

        formatPrice(price) {
            if (!price) return 'N/A';
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'AED',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(price);
        },

        formatDate(dateString) {
            if (!dateString) return 'N/A';
            return new Date(dateString).toLocaleDateString('en-US');
        },

        statusBadgeClass(status) {
            const classes = {
                'upcoming': 'bg-warning',
                'ongoing': 'bg-info',
                'completed': 'bg-success'
            };
            return classes[status] || 'bg-secondary';
        },

        goToPage(page) {
            if (page >= 1 && page <= this.totalPages) {
                this.currentPage = page;
                // Scroll to top of table
                const tableElement = document.querySelector('.table-responsive');
                if (tableElement) {
                    tableElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
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

.badge {
    font-size: 0.75em;
    padding: 0.35em 0.65em;
    color: white !important;
}

.pagination .page-item .page-link {
    transition: all 0.3s ease;
}

.pagination .page-item .page-link:hover:not(.disabled) {
    background-color: #e9ecef;
    transform: translateY(-2px);
}

.pagination .page-item .page-link.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination .page-item .page-link.bg-primary-600 {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
.completion-indicator {
    min-width: 120px;
}

.progress {
    background-color: #e9ecef;
    border-radius: 3px;
}

.progress-bar {
    border-radius: 3px;
    transition: width 0.3s ease;
}

/* complete */
.completion-details {
    font-size: 0.75rem;
    line-height: 1.2;
}

.completion-details small {
    display: flex;
    align-items: center;
    gap: 4px;
}

.badge {
    font-size: 0.65rem;
    padding: 0.25em 0.5em;
}

.btn-link {
    color: #6c757d;
    text-decoration: none;
}

.btn-link:hover {
    color: #0d6efd;
}

.table td, .table th {
    padding: 0.75rem;
}

@media (max-width: 768px) {
    .completion-indicator {
        min-width: 100px;
    }
    
    .progress {
        width: 80px;
    }
}
.sortable {
    cursor: pointer;
    user-select: none;
}
.sortable:hover {
    text-decoration: underline;
}

</style>