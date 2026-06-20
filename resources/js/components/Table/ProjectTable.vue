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
                    <!--<button v-if="this.$hasPermission('projects-create')" -->
                    <!--    class="btn btn-primary"-->
                    <!--    @click="addProject">-->
                    <!--    <iconify-icon icon="lucide:plus" class="me-2"></iconify-icon>-->
                    <!--    Add Project-->
                    <!--</button>-->
                    
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
                                    <th scope="col">About</th>
                                    
                                    <th @click="sortBy('developer')" class="sortable">Developer</th>
                                    <th @click="sortBy('area')" class="sortable">Area</th>
                                    <th @click="sortBy('status')" class="sortable">Status</th>
                                    <th @click="sortBy('count_listing')" class="sortable">Count of Listing</th>
                                    <th @click="sortBy('duplicated')" class="sortable">Duplicated</th>
                                    <th @click="sortBy('completed')" class="sortable">
                                        Completed
                                        <iconify-icon
                                            v-if="sortKey === 'completed'"
                                            :icon="sortDirection === 'asc' ? 'lucide:arrow-up' : 'lucide:arrow-down'"
                                        />
                                    </th>
                                    <th @click="sortBy('created_at')" class="sortable">Created Date</th>

                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="project in paginatedProjects" :key="project.id">
                                <td>{{ project.id }}</td>
                                 <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <button
                                            v-if="project.main_image"
                                            type="button"
                                            class="project-thumb-btn flex-shrink-0 me-2 radius-8"
                                            :title="`View ${project.title} gallery`"
                                            @click="openProjectGallery(project)"
                                        >
                                            <img
                                                :src="project.main_image"
                                                alt="Project Image"
                                                width="60"
                                                height="60"
                                                style="object-fit: cover;"
                                            >
                                        </button>
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
                                <td class="project-about-cell">
                                    <template v-if="getAboutText(project)">
                                        <p class="project-about-preview mb-1">{{ truncateAbout(getAboutText(project)) }}</p>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary project-about-view-btn"
                                            @click="openAboutModal(project)"
                                        >
                                            View
                                        </button>
                                    </template>
                                    <span v-else class="text-muted">N/A</span>
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
                                    <a v-if="this.$hasPermission('projects-edit')" href="javascript:void(0)"
                                        class="w-32-px h-32-px bg-success-focus text-success-main rounded-circle d-inline-flex align-items-center justify-content-center"
                                        @click="projectFloorPlan(project.id)"
                                        title="Project Floor Plans">
                                        <i class="fas fa-layer-group me-1"></i>
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

    <Teleport to="body">
        <div
            v-if="showGallery && galleryImages.length"
            class="project-gallery-overlay"
            @click="closeProjectGallery"
        >
            <div class="project-gallery-content" @click.stop>
                <div class="project-gallery-header">
                    <div class="project-gallery-info">
                        <span class="project-gallery-title">{{ galleryTitle }}</span>
                        <span class="project-gallery-counter">
                            {{ currentGalleryIndex + 1 }} / {{ galleryImages.length }}
                        </span>
                    </div>
                    <button type="button" class="project-gallery-close" @click="closeProjectGallery" aria-label="Close gallery">
                        <iconify-icon icon="lucide:x"></iconify-icon>
                    </button>
                </div>
                <div class="project-gallery-main">
                    <button
                        type="button"
                        class="project-gallery-nav project-gallery-nav--prev"
                        :disabled="currentGalleryIndex === 0"
                        @click="prevGalleryImage"
                        aria-label="Previous image"
                    >
                        <iconify-icon icon="lucide:chevron-left"></iconify-icon>
                    </button>
                    <div class="project-gallery-image-wrap">
                        <img
                            :src="galleryImages[currentGalleryIndex]"
                            class="project-gallery-image"
                            :alt="galleryTitle"
                        >
                    </div>
                    <button
                        type="button"
                        class="project-gallery-nav project-gallery-nav--next"
                        :disabled="currentGalleryIndex >= galleryImages.length - 1"
                        @click="nextGalleryImage"
                        aria-label="Next image"
                    >
                        <iconify-icon icon="lucide:chevron-right"></iconify-icon>
                    </button>
                </div>
                <div v-if="galleryImages.length > 1" class="project-gallery-dots">
                    <button
                        v-for="(_, index) in galleryImages"
                        :key="index"
                        type="button"
                        class="project-gallery-dot"
                        :class="{ active: currentGalleryIndex === index }"
                        :aria-label="`Go to image ${index + 1}`"
                        @click="currentGalleryIndex = index"
                    ></button>
                </div>
            </div>
        </div>
    </Teleport>

    <Teleport to="body">
        <div
            v-if="showAboutModal"
            class="project-about-overlay"
            @click="closeAboutModal"
        >
            <div class="project-about-modal" @click.stop>
                <div class="project-about-modal__header">
                    <div>
                        <h6 class="project-about-modal__title">{{ aboutModalTitle }}</h6>
                        <span class="project-about-modal__subtitle">About</span>
                    </div>
                    <button type="button" class="project-about-modal__close" @click="closeAboutModal" aria-label="Close">
                        <iconify-icon icon="lucide:x"></iconify-icon>
                    </button>
                </div>
                <div class="project-about-modal__body">
                    <p class="project-about-modal__text">{{ aboutModalText }}</p>
                </div>
            </div>
        </div>
    </Teleport>
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
        sortDirection: 'asc',    // asc | desc
            showGallery: false,
            galleryImages: [],
            currentGalleryIndex: 0,
            galleryTitle: '',
            showAboutModal: false,
            aboutModalTitle: '',
            aboutModalText: '',
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
                        (this.getAboutText(project) && this.getAboutText(project).toLowerCase().includes(search)) ||
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

                            case 'completed':
                                aVal = Number(a.completion_percentage) || 0;
                                bVal = Number(b.completion_percentage) || 0;
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
        window.addEventListener('keydown', this.onGalleryKeydown);
    },
    beforeUnmount() {
        window.removeEventListener('keydown', this.onGalleryKeydown);
        document.body.style.overflow = '';
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

        getProjectGalleryImages(project) {
            const urls = [];
            if (Array.isArray(project?.images) && project.images.length) {
                project.images.forEach((img) => {
                    const url = img?.image_url || img?.url || (typeof img === 'string' ? img : null);
                    if (url && !urls.includes(url)) urls.push(url);
                });
            }
            if (project?.main_image && !urls.includes(project.main_image)) {
                urls.unshift(project.main_image);
            }
            return urls;
        },

        async openProjectGallery(project) {
            let images = this.getProjectGalleryImages(project);

            if (!images.length && project?.id) {
                try {
                    const token = localStorage.getItem('token');
                    const response = await fetch(API_ENDPOINTS.PROJECT_BY_ID(project.id), {
                        headers: {
                            Authorization: 'Bearer ' + token,
                            'Content-Type': 'application/json',
                        },
                    });
                    if (response.ok) {
                        const payload = await response.json();
                        const fullProject = payload.data || payload;
                        images = this.getProjectGalleryImages(fullProject);
                    }
                } catch (error) {
                    console.error('Error loading project gallery:', error);
                }
            }

            if (!images.length) {
                this.$showNotification('No images available for this project', 'warning');
                return;
            }

            let startIndex = 0;
            if (project?.main_image) {
                const mainIndex = images.findIndex((url) => url === project.main_image);
                if (mainIndex >= 0) startIndex = mainIndex;
            }

            this.galleryImages = images;
            this.currentGalleryIndex = startIndex;
            this.galleryTitle = project?.title || 'Project Gallery';
            this.showGallery = true;
            document.body.style.overflow = 'hidden';
        },

        closeProjectGallery() {
            this.showGallery = false;
            this.galleryImages = [];
            this.currentGalleryIndex = 0;
            this.galleryTitle = '';
            if (!this.showAboutModal) {
                document.body.style.overflow = '';
            }
        },

        nextGalleryImage() {
            if (this.currentGalleryIndex < this.galleryImages.length - 1) {
                this.currentGalleryIndex += 1;
            }
        },

        prevGalleryImage() {
            if (this.currentGalleryIndex > 0) {
                this.currentGalleryIndex -= 1;
            }
        },

        onGalleryKeydown(event) {
            if (event.key === 'Escape') {
                if (this.showGallery) this.closeProjectGallery();
                if (this.showAboutModal) this.closeAboutModal();
                return;
            }
            if (!this.showGallery) return;
            if (event.key === 'ArrowRight') {
                this.nextGalleryImage();
            } else if (event.key === 'ArrowLeft') {
                this.prevGalleryImage();
            }
        },

        getAboutText(project) {
            const text = project?.about || project?.description || '';
            return typeof text === 'string' ? text.trim() : '';
        },

        truncateAbout(text, maxLength = 90) {
            if (!text) return '';
            if (text.length <= maxLength) return text;
            return `${text.slice(0, maxLength).trim()}…`;
        },

        openAboutModal(project) {
            const text = this.getAboutText(project);
            if (!text) return;
            this.aboutModalTitle = project?.title || 'Project';
            this.aboutModalText = text;
            this.showAboutModal = true;
            document.body.style.overflow = 'hidden';
        },

        closeAboutModal() {
            this.showAboutModal = false;
            this.aboutModalTitle = '';
            this.aboutModalText = '';
            if (!this.showGallery) {
                document.body.style.overflow = '';
            }
        },

        editProject(id) {
            if (!this.$hasPermission('projects-edit')) {
                this.$showNotification('You do not have permission to edit projects', 'warning');
                return;
            }
            this.$router.push(`/projects/${id}/edit`);
        },
        
        projectFloorPlan(id) {
            if (!this.$hasPermission('projects-edit')) {
                this.$showNotification('You do not have permission to edit projects floor-plans', 'warning');
                return;
            }
            this.$router.push(`/projects/${id}/floor-plans`);
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
                'Under Construction': 'bg-info',
                'Ready': 'bg-success'
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

.project-thumb-btn {
    display: block;
    padding: 0;
    border: none;
    background: transparent;
    cursor: zoom-in;
    overflow: hidden;
    line-height: 0;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.project-thumb-btn:hover {
    transform: scale(1.04);
    box-shadow: 0 4px 12px rgba(11, 7, 54, 0.18);
}

.project-gallery-overlay {
    position: fixed;
    inset: 0;
    z-index: 100500;
    background: rgba(0, 0, 0, 0.88);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
}

.project-gallery-content {
    width: min(960px, 100%);
    max-height: calc(100vh - 32px);
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.project-gallery-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    color: #fff;
}

.project-gallery-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}

.project-gallery-title {
    font-size: 16px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.project-gallery-counter {
    font-size: 13px;
    opacity: 0.75;
}

.project-gallery-close {
    width: 40px;
    height: 40px;
    border: none;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
}

.project-gallery-close:hover {
    background: rgba(255, 255, 255, 0.2);
}

.project-gallery-main {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 0;
}

.project-gallery-image-wrap {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    max-height: calc(100vh - 180px);
}

.project-gallery-image {
    max-width: 100%;
    max-height: calc(100vh - 180px);
    object-fit: contain;
    border-radius: 8px;
    background: #111;
}

.project-gallery-nav {
    width: 44px;
    height: 44px;
    border: none;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
}

.project-gallery-nav:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.22);
}

.project-gallery-nav:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

.project-gallery-dots {
    display: flex;
    justify-content: center;
    gap: 8px;
    flex-wrap: wrap;
}

.project-gallery-dot {
    width: 8px;
    height: 8px;
    border: none;
    border-radius: 50%;
    padding: 0;
    background: rgba(255, 255, 255, 0.35);
    cursor: pointer;
}

.project-gallery-dot.active {
    background: #fff;
    transform: scale(1.15);
}

@media (max-width: 768px) {
    .project-gallery-main {
        gap: 8px;
    }

    .project-gallery-nav {
        width: 36px;
        height: 36px;
    }

    .project-gallery-title {
        font-size: 14px;
    }
}

.project-about-cell {
    max-width: 220px;
    min-width: 160px;
}

.project-about-preview {
    font-size: 12px;
    line-height: 1.45;
    color: #64748b;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.project-about-view-btn {
    padding: 2px 10px;
    font-size: 11px;
    line-height: 1.4;
    border-radius: 999px;
}

.project-about-overlay {
    position: fixed;
    inset: 0;
    z-index: 100500;
    background: rgba(0, 0, 0, 0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
}

.project-about-modal {
    width: min(640px, 100%);
    max-height: calc(100vh - 32px);
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.25);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.project-about-modal__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 18px 20px 12px;
    border-bottom: 1px solid #eef2f7;
}

.project-about-modal__title {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #0f172a;
}

.project-about-modal__subtitle {
    font-size: 12px;
    color: #64748b;
}

.project-about-modal__close {
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 50%;
    background: #f1f5f9;
    color: #334155;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
}

.project-about-modal__close:hover {
    background: #e2e8f0;
}

.project-about-modal__body {
    padding: 16px 20px 20px;
    overflow-y: auto;
}

.project-about-modal__text {
    margin: 0;
    font-size: 14px;
    line-height: 1.65;
    color: #334155;
    white-space: pre-wrap;
    word-break: break-word;
}

</style>