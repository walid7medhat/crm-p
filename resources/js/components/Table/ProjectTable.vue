<template>
    <div class="projects-board" :class="{ 'is-paging': paging }">
        <div class="prj-toolbar">
            <label class="prj-search">
                <iconify-icon icon="lucide:search"></iconify-icon>
                <input
                    v-model="searchText"
                    type="search"
                    placeholder="Search Projects..."
                    aria-label="Search projects"
                >
            </label>

            <div class="prj-views" role="group" aria-label="Project layout">
                <button
                    type="button"
                    :class="{ active: viewMode === 'grid' }"
                    :aria-pressed="viewMode === 'grid'"
                    aria-label="Card view"
                    @click="setView('grid')"
                >
                    <iconify-icon icon="lucide:layout-grid"></iconify-icon>
                </button>
                <button
                    type="button"
                    :class="{ active: viewMode === 'list' }"
                    :aria-pressed="viewMode === 'list'"
                    aria-label="List view"
                    @click="setView('list')"
                >
                    <iconify-icon icon="lucide:list"></iconify-icon>
                </button>
            </div>
        </div>

        <div v-if="loading" class="prj-state">
            <div class="prj-spinner" role="status" aria-label="Loading projects"></div>
            <p>Loading Projects...</p>
        </div>

        <div v-else-if="projects.length === 0" class="prj-state">
            <template v-if="searchText.trim()">
                <iconify-icon icon="lucide:search-x" width="42"></iconify-icon>
                <p>No projects match your search</p>
            </template>
            <template v-else>
                <iconify-icon icon="lucide:folder" width="42"></iconify-icon>
                <p>No Projects found</p>
                <button
                    v-if="this.$hasPermission('projects-create')"
                    type="button"
                    class="prj-btn prj-btn--edit"
                    @click="addProject"
                >
                    <iconify-icon icon="lucide:plus"></iconify-icon>
                    Add First Project
                </button>
            </template>
        </div>

        <div v-else-if="viewMode === 'grid'" class="projects-grid">
            <article v-for="project in paginatedProjects" :key="project.id" class="prj-card">
                <div class="prj-media-wrap">
                    <button type="button" class="prj-card__media" @click="onMediaClick(project)">
                        <img v-if="project.main_image" :src="project.main_image" :alt="project.title">
                        <span v-else class="prj-media-empty">
                            <iconify-icon icon="lucide:image" width="28"></iconify-icon>
                        </span>
                    </button>
                    <button type="button" class="prj-share" aria-label="Share project" @click.stop="shareProject(project)">
                        <iconify-icon icon="lucide:share-2"></iconify-icon>
                    </button>
                </div>

                <div class="prj-card__body">
                    <div class="prj-pills">
                        <span class="prj-pill prj-pill--id">ID : {{ project.id }}</span>
                        <span class="prj-pill" :class="`prj-pill--${statusTone(project)}`">
                            {{ project.status_label || project.status || '—' }}
                        </span>
                    </div>

                    <h3 class="prj-title">
                        <button type="button" @click="viewProject(project.id)">{{ project.title }}</button>
                    </h3>

                    <div class="prj-card__lower">
                        <div class="prj-card__facts">
                            <p class="prj-meta">
                                <iconify-icon icon="lucide:map-pin"></iconify-icon>
                                <span>{{ project.area?.name || '—' }}</span>
                            </p>
                            <p class="prj-meta">
                                <iconify-icon icon="lucide:building-2"></iconify-icon>
                                <span>{{ project.developer?.name || '—' }}</span>
                            </p>
                            <p class="prj-meta prj-meta--date">
                                <iconify-icon icon="lucide:calendar"></iconify-icon>
                                <span>Created By : {{ formatDate(project.created_at) }}</span>
                            </p>
                        </div>
                        <img
                            v-if="developerLogo(project)"
                            :src="developerLogo(project)"
                            alt=""
                            class="prj-card__logo"
                            @error="markLogoBroken(project.id)"
                        >
                    </div>

                    <div class="prj-actions">
                        <button
                            v-if="this.$hasPermission('projects-edit')"
                            type="button"
                            class="prj-btn prj-btn--edit"
                            @click="editProject(project.id)"
                        >
                            <iconify-icon icon="lucide:pencil"></iconify-icon>
                            Edit
                        </button>
                        <button
                            v-if="this.$hasPermission('projects-edit')"
                            type="button"
                            class="prj-btn prj-btn--plans"
                            @click="projectFloorPlan(project.id)"
                        >
                            <iconify-icon icon="lucide:building-2"></iconify-icon>
                            Floorplans
                        </button>
                        <button
                            v-if="this.$hasPermission('projects-delete')"
                            type="button"
                            class="prj-btn prj-btn--delete"
                            @click="deleteProject(project)"
                        >
                            <iconify-icon icon="lucide:trash-2"></iconify-icon>
                            Delete
                        </button>
                    </div>
                </div>
            </article>
        </div>

        <div v-else class="projects-list">
            <article v-for="project in paginatedProjects" :key="`list-${project.id}`" class="prj-row">
                <button type="button" class="prj-row__media" @click="onMediaClick(project)">
                    <img v-if="project.main_image" :src="project.main_image" :alt="project.title">
                    <span v-else class="prj-media-empty">
                        <iconify-icon icon="lucide:image" width="28"></iconify-icon>
                    </span>
                </button>

                <div class="prj-row__main">
                    <div class="prj-pills">
                        <span class="prj-pill prj-pill--id">ID : {{ project.id }}</span>
                        <span class="prj-pill" :class="`prj-pill--${statusTone(project)}`">
                            {{ project.status_label || project.status || '—' }}
                        </span>
                    </div>

                    <h3 class="prj-title">
                        <button type="button" @click="viewProject(project.id)">{{ project.title }}</button>
                    </h3>

                    <p
                        v-if="plainAbout(project)"
                        class="prj-desc"
                        @click="openAboutModal(project)"
                    >{{ plainAbout(project) }}</p>

                    <p class="prj-meta">
                        <iconify-icon icon="lucide:map-pin"></iconify-icon>
                        <span>{{ project.area?.name || '—' }}</span>
                    </p>

                    <p class="prj-meta prj-meta--date">
                        <iconify-icon icon="lucide:calendar"></iconify-icon>
                        <span>Created By : {{ formatDate(project.created_at) }}</span>
                    </p>
                </div>

                <div class="prj-row__side">
                    <div class="prj-row__brand">
                        <img
                            v-if="developerLogo(project)"
                            :src="developerLogo(project)"
                            alt=""
                            class="prj-row__logo"
                            @error="markLogoBroken(project.id)"
                        >
                        <span v-else-if="project.developer?.name" class="prj-row__logo-text">
                            {{ project.developer.name }}
                        </span>
                        <button type="button" class="prj-share prj-share--inline" aria-label="Share project" @click.stop="shareProject(project)">
                            <iconify-icon icon="lucide:share-2"></iconify-icon>
                        </button>
                    </div>

                    <div class="prj-actions">
                        <button
                            v-if="this.$hasPermission('projects-edit')"
                            type="button"
                            class="prj-btn prj-btn--edit"
                            @click="editProject(project.id)"
                        >
                            <iconify-icon icon="lucide:pencil"></iconify-icon>
                            Edit
                        </button>
                        <button
                            v-if="this.$hasPermission('projects-edit')"
                            type="button"
                            class="prj-btn prj-btn--plans"
                            @click="projectFloorPlan(project.id)"
                        >
                            <iconify-icon icon="lucide:building-2"></iconify-icon>
                            Floorplans
                        </button>
                        <button
                            v-if="this.$hasPermission('projects-delete')"
                            type="button"
                            class="prj-btn prj-btn--delete"
                            @click="deleteProject(project)"
                        >
                            <iconify-icon icon="lucide:trash-2"></iconify-icon>
                            Delete
                        </button>
                    </div>
                </div>
            </article>
        </div>

        <div v-if="!loading && totalEntries > 0" class="prj-pager">
            <div class="prj-pager__meta">
                <span v-if="selectedShow === 'all'">Showing all {{ totalEntries }} Entries</span>
                <span v-else>Showing {{ startIndex + 1 }} to {{ endIndex }} of {{ totalEntries }} Entries</span>
                <div ref="perPage" class="prj-per-page">
                    <button
                        type="button"
                        aria-label="Entries per page"
                        :aria-expanded="perPageOpen"
                        @click="perPageOpen = !perPageOpen"
                    >
                        <iconify-icon icon="lucide:chevron-down"></iconify-icon>
                    </button>
                    <ul v-if="perPageOpen">
                        <li v-for="option in perPageOptions" :key="option.value">
                            <button
                                type="button"
                                :class="{ active: String(selectedShow) === String(option.value) }"
                                @click="setPerPage(option.value)"
                            >
                                {{ option.label }}
                            </button>
                        </li>
                    </ul>
                </div>
            </div>

            <div v-if="selectedShow !== 'all' && totalPages > 1" class="prj-pager__pages">
                <button type="button" class="prj-nav" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">
                    <iconify-icon icon="lucide:chevron-left"></iconify-icon>
                    Previous
                </button>

                <template v-for="(item, index) in pageItems" :key="`${item}-${index}`">
                    <span v-if="item === 'ellipsis'" class="prj-ellipsis">...</span>
                    <button
                        v-else
                        type="button"
                        class="prj-page"
                        :class="{ 'is-active': item === currentPage }"
                        @click="goToPage(item)"
                    >
                        {{ item }}
                    </button>
                </template>

                <button type="button" class="prj-nav" :disabled="currentPage === totalPages" @click="goToPage(currentPage + 1)">
                    Next
                    <iconify-icon icon="lucide:chevron-right"></iconify-icon>
                </button>
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

export default {
    name: 'ProjectsTable',
    data() {
        return {
            loading: true,
            selectedShow: 10,
            searchText: '',
            currentPage: 1,
            projects: [],
            sortByImage: 'all',
            sortKey: null,
            sortDirection: 'asc',
            viewMode: 'grid',
            paging: false,
            fetchSeq: 0,
            searchTimer: null,
            serverTotal: 0,
            serverLastPage: 1,
            brokenLogos: {},
            perPageOpen: false,
            perPageOptions: [
                { value: 10, label: '10 per page' },
                { value: 15, label: '15 per page' },
                { value: 20, label: '20 per page' },
            ],
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

                if (this.searchText) {
                    const search = this.searchText.toLowerCase();
                    filtered = filtered.filter(project =>
                        (project.title || '').toLowerCase().includes(search) ||
                        (project.developer?.name && project.developer.name.toLowerCase().includes(search)) ||
                        (project.area?.name && project.area.name.toLowerCase().includes(search)) ||
                        (this.getAboutText(project) && this.getAboutText(project).toLowerCase().includes(search)) ||
                        (project.status || '').toLowerCase().includes(search)
                    );
                }

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
            return this.projects;
        },
        totalEntries() {
            return this.serverTotal;
        },
        totalPages() {
            return Math.max(1, this.serverLastPage);
        },
        startIndex() {
            if (!this.serverTotal) return 0;
            return (this.currentPage - 1) * Number(this.selectedShow);
        },
        endIndex() {
            return Math.min(this.startIndex + this.projects.length, this.serverTotal);
        },
        pageItems() {
            const total = this.totalPages;
            const current = this.currentPage;
            if (total <= 5) {
                return Array.from({ length: total }, (_, index) => index + 1);
            }
            if (current <= 3) {
                return [1, 2, 3, 'ellipsis', total];
            }
            if (current >= total - 2) {
                return [1, 'ellipsis', total - 2, total - 1, total];
            }
            return [1, 'ellipsis', current - 1, current, current + 1, 'ellipsis', total];
        },
    },
    watch: {
        searchText() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => {
                this.currentPage = 1;
                this.fetchProjects();
            }, 350);
        }
    },
    mounted() {
        const savedView = localStorage.getItem('projectsViewMode');
        if (savedView === 'grid' || savedView === 'list') {
            this.viewMode = savedView;
        }
        this.fetchProjects();
        window.addEventListener('keydown', this.onGalleryKeydown);
        document.addEventListener('pointerdown', this.onDocumentPointer);
    },
    beforeUnmount() {
        clearTimeout(this.searchTimer);
        window.removeEventListener('keydown', this.onGalleryKeydown);
        document.removeEventListener('pointerdown', this.onDocumentPointer);
        document.body.style.overflow = '';
    },
    methods: {
        setView(mode) {
            this.viewMode = mode;
            localStorage.setItem('projectsViewMode', mode);
        },
        setPerPage(value) {
            this.perPageOpen = false;
            if (String(this.selectedShow) === String(value)) return;
            this.selectedShow = Number(value);
            this.currentPage = 1;
            this.fetchProjects();
        },
        developerLogo(project) {
            const src = project?.developer?.avatar;
            if (!src || this.brokenLogos[project.id]) return '';
            return src;
        },
        markLogoBroken(id) {
            this.brokenLogos = { ...this.brokenLogos, [id]: true };
        },
        onDocumentPointer(event) {
            const menu = this.$refs.perPage;
            if (menu && !menu.contains(event.target)) {
                this.perPageOpen = false;
            }
        },
        statusTone(project) {
            const status = String(project?.status_label || project?.status || '').toLowerCase();
            if (status.includes('ready')) return 'ready';
            if (status.includes('construction')) return 'construction';
            return 'default';
        },
        developerInitials(project) {
            const parts = String(project?.developer?.name || '').trim().split(/\s+/).filter(Boolean);
            if (!parts.length) return '—';
            return parts.slice(0, 2).map((part) => part[0]).join('').toUpperCase();
        },
        plainAbout(project) {
            return this.getAboutText(project)
                .replace(/<[^>]*>/g, ' ')
                .replace(/&nbsp;/gi, ' ')
                .replace(/\s+/g, ' ')
                .trim();
        },
        async shareProject(project) {
            const url = `${window.location.origin}/projects/${project.id}`;
            if (navigator.share) {
                try {
                    await navigator.share({ title: project.title || 'Project', url });
                    return;
                } catch (error) {
                    if (error?.name === 'AbortError') return;
                }
            }
            try {
                await navigator.clipboard.writeText(url);
                this.$showNotification('Project link copied', 'success');
            } catch (error) {
                this.$showNotification('Could not share this project', 'error');
            }
        },
        onMediaClick(project) {
            if (project?.main_image || (Array.isArray(project?.images) && project.images.length)) {
                this.openProjectGallery(project);
                return;
            }
            this.viewProject(project.id);
        },
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
            const requestId = ++this.fetchSeq;
            const firstLoad = this.projects.length === 0;
            if (firstLoad) this.loading = true;
            else this.paging = true;

            try {
                const params = new URLSearchParams({
                    per_page: String(this.selectedShow || 10),
                    page: String(this.currentPage || 1),
                });
                const search = this.searchText.trim();
                if (search) params.set('search', search);

                const token = localStorage.getItem('token');
                const response = await fetch(`${API_ENDPOINTS.PROJECTS}?${params.toString()}`, {
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    }
                });

                if (!response.ok) throw new Error('Failed to fetch projects');

                const data = await response.json();
                if (requestId !== this.fetchSeq) return;

                const rows = Array.isArray(data.data) ? data.data : [];
                this.projects = rows.map(project => ({
                    ...project,
                    has_image: !!project.main_image,
                    description: project.about,
                }));
                const meta = data.meta || {};
                this.serverTotal = Number(meta.total ?? rows.length);
                this.serverLastPage = Math.max(1, Number(meta.last_page ?? 1));
            } catch (error) {
                if (requestId !== this.fetchSeq) return;
                console.error('Error fetching projects:', error);
                this.projects = [];
                this.serverTotal = 0;
                this.serverLastPage = 1;
                this.$showNotification('Failed to load projects', 'error');
            } finally {
                if (requestId === this.fetchSeq) {
                    this.loading = false;
                    this.paging = false;
                }
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
            const text = this.plainAbout(project);
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
                    const token = localStorage.getItem('token');
                    const response = await fetch(API_ENDPOINTS.PROJECT_BY_ID(project.id), {
                        method: 'DELETE',
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Content-Type': 'application/json'
                        }
                    });

                    if (response.ok) {
                        this.$showNotification(`${project.title} has been deleted successfully`, 'success');
                        if (this.projects.length === 1 && this.currentPage > 1) {
                            this.currentPage -= 1;
                        }
                        await this.fetchProjects();
                    } else {
                        throw new Error('Failed to delete project');
                    }
                } catch (error) {
                    console.error('Error deleting project:', error);
                    this.$showNotification('Failed to delete project', 'error');
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
            const date = new Date(String(dateString).replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) return 'N/A';
            const day = String(date.getDate()).padStart(2, '0');
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return `${day} ${months[date.getMonth()]} ${date.getFullYear()}`;
        },

        statusBadgeClass(status) {
            const classes = {
                'Under Construction': 'bg-info',
                'Ready': 'bg-success'
            };
            return classes[status] || 'bg-secondary';
        },

        goToPage(page) {
            if (page >= 1 && page <= this.totalPages && page !== this.currentPage) {
                this.currentPage = page;
                this.fetchProjects();
            }
        }
    }
};
</script>

<style scoped>
.projects-board {
    --prj-purple: #6d28d9;
    --prj-purple-soft: #f3e8ff;
    --prj-ink: #1e1b4b;
    --prj-muted: #64748b;
    --prj-line: #ece7f5;
    display: flex;
    flex-direction: column;
    gap: 16px;
    min-width: 0;
}

.prj-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.prj-search {
    display: flex;
    align-items: center;
    gap: 8px;
    height: 42px;
    padding: 0 14px;
    min-width: min(340px, 100%);
    background: #fff;
    border: 1px solid var(--prj-line);
    border-radius: 999px;
    color: #94a3b8;
    box-shadow: 0 8px 24px rgba(76, 29, 149, 0.05);
}

.prj-search input {
    width: 100%;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--prj-ink);
    font-size: 14px;
}

.prj-search input::placeholder {
    color: #94a3b8;
}

.prj-views {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px;
    background: #fff;
    border: 1px solid var(--prj-line);
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(76, 29, 149, 0.05);
}

.prj-views button {
    width: 36px;
    height: 32px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: #94a3b8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.prj-views button.active {
    background: var(--prj-purple);
    color: #fff;
}

.projects-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
}

.projects-board.is-paging .projects-grid,
.projects-board.is-paging .projects-list {
    opacity: 0.55;
    pointer-events: none;
}

.prj-card,
.prj-row {
    background: #fff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 18px;
    box-shadow: 0 10px 28px rgba(76, 29, 149, 0.06);
}

.prj-card {
    display: flex;
    flex-direction: column;
    min-width: 0;
    padding: 10px;
}

.prj-media-wrap {
    position: relative;
}

.prj-card__media,
.prj-row__media {
    display: block;
    width: 100%;
    padding: 0;
    border: 0;
    overflow: hidden;
    background: #f8fafc;
    cursor: pointer;
}

.prj-card__media {
    height: 158px;
    border-radius: 14px;
}

.prj-card__media img,
.prj-row__media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.prj-media-empty {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #cbd5e1;
}

.prj-share {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 32px;
    height: 32px;
    border: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.92);
    color: #334155;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12);
}

.prj-share:hover,
.prj-btn:hover,
.prj-nav:hover:not(:disabled),
.prj-page:hover:not(.is-active) {
    filter: brightness(0.97);
}

.prj-card__body {
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex: 1;
    padding: 12px 6px 6px;
    min-width: 0;
}

.prj-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.prj-pill {
    display: inline-flex;
    align-items: center;
    height: 24px;
    padding: 0 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    line-height: 1;
    white-space: nowrap;
}

.prj-pill--id {
    background: #ede9fe;
    color: #6d28d9;
}

.prj-pill--construction {
    background: #fef9c3;
    color: #ca8a04;
}

.prj-pill--ready {
    background: #dcfce7;
    color: #15803d;
}

.prj-pill--default {
    background: #f1f5f9;
    color: #475569;
}

.prj-title {
    margin: 0;
    min-width: 0;
}

.prj-title button {
    padding: 0;
    border: 0;
    background: transparent;
    color: var(--prj-ink);
    font-size: 15px;
    font-weight: 700;
    line-height: 1.35;
    text-align: left;
    cursor: pointer;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.prj-title button:hover {
    color: var(--prj-purple);
}

.prj-meta,
.prj-dev {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0;
    min-width: 0;
    color: #475569;
    font-size: 12.5px;
    line-height: 1.3;
}

.prj-meta span,
.prj-dev span:last-child {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.prj-meta iconify-icon {
    flex-shrink: 0;
    color: #7c3aed;
    font-size: 14px;
}

.prj-meta--date iconify-icon {
    color: #94a3b8;
}

.prj-card__lower {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-width: 0;
}

.prj-card__facts {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
    flex: 1;
}

.prj-card__logo {
    width: 72px;
    height: 52px;
    object-fit: contain;
    flex-shrink: 0;
    margin-left: auto;
}

.prj-actions {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 6px;
    margin-top: auto;
    padding-top: 4px;
}

.prj-btn {
    height: 30px;
    padding: 0 10px;
    border-radius: 999px;
    border: 1px solid transparent;
    background: #fff;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    cursor: pointer;
    white-space: nowrap;
    line-height: 1;
    flex: 0 0 auto;
}

.prj-card .prj-btn {
    padding: 0 8px;
    gap: 4px;
}

.prj-btn--edit {
    color: #7c3aed;
    border-color: #c4b5fd;
}

.prj-btn--plans {
    color: #d97706;
    border-color: #fbbf24;
}

.prj-btn--delete {
    color: #e11d48;
    border-color: #fda4af;
}

.projects-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.prj-row {
    display: grid;
    grid-template-columns: 196px minmax(0, 1fr) auto;
    gap: 18px;
    align-items: stretch;
    padding: 14px;
}

.prj-row__media {
    height: 148px;
    border-radius: 14px;
}

.prj-row__main {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 0;
    padding: 2px 0;
}

.prj-desc {
    margin: 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    cursor: pointer;
}

.prj-desc:hover {
    color: #334155;
}

.prj-row__side {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
    min-width: 230px;
}

.prj-row__brand {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.prj-row__logo {
    width: 72px;
    max-height: 52px;
    object-fit: contain;
}

.prj-row__logo-text {
    max-width: 140px;
    text-align: right;
    font-size: 12px;
    font-weight: 700;
    color: var(--prj-ink);
    line-height: 1.3;
}

.prj-share--inline {
    position: static;
    flex-shrink: 0;
}

.prj-pager {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    min-height: 44px;
}

.prj-pager__meta {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 36px;
    color: #64748b;
    font-size: 13px;
    line-height: 1;
}

.prj-per-page {
    position: relative;
}

.prj-per-page > button {
    width: 28px;
    height: 28px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.prj-per-page ul {
    position: absolute;
    z-index: 20;
    left: 0;
    bottom: calc(100% + 6px);
    margin: 0;
    padding: 6px;
    list-style: none;
    min-width: 140px;
    background: #fff;
    border: 1px solid var(--prj-line);
    border-radius: 12px;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
}

.prj-per-page li button {
    width: 100%;
    border: 0;
    background: transparent;
    border-radius: 8px;
    padding: 8px 10px;
    text-align: left;
    color: var(--prj-ink);
    font-size: 13px;
    cursor: pointer;
}

.prj-per-page li button.active,
.prj-per-page li button:hover {
    background: #f5f3ff;
    color: var(--prj-purple);
}

.prj-pager__pages {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.prj-nav,
.prj-page {
    border: 0;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
}

.prj-nav {
    height: 36px;
    padding: 0 14px;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    gap: 2px;
}

.prj-nav:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.prj-page {
    width: 32px;
    height: 32px;
    padding: 0;
    border-radius: 50%;
    background: transparent;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

.prj-page.is-active {
    background: var(--prj-purple);
    color: #fff;
}

.prj-ellipsis {
    color: #94a3b8;
    padding: 0 2px;
}

.prj-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    min-height: 240px;
    color: var(--prj-muted);
    background: #fff;
    border: 1px solid var(--prj-line);
    border-radius: 18px;
}

.prj-spinner {
    width: 32px;
    height: 32px;
    border: 3px solid #ede9fe;
    border-top-color: var(--prj-purple);
    border-radius: 50%;
    animation: prj-spin 0.7s linear infinite;
}

@keyframes prj-spin {
    to { transform: rotate(360deg); }
}

.projects-board button:focus-visible,
.projects-board input:focus-visible {
    outline: 2px solid #7c3aed;
    outline-offset: 2px;
}

@media (max-width: 1280px) {
    .projects-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 980px) {
    .prj-row {
        grid-template-columns: 160px minmax(0, 1fr);
    }

    .prj-row__side {
        grid-column: 1 / -1;
        flex-direction: row;
        align-items: center;
        min-width: 0;
    }
}

@media (max-width: 900px) {
    .projects-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 680px) {
    .projects-grid {
        grid-template-columns: 1fr;
    }

    .prj-row {
        grid-template-columns: 1fr;
    }

    .prj-row__media {
        height: 180px;
    }

    .prj-row__side {
        flex-direction: column;
        align-items: flex-start;
    }

    .prj-card .prj-actions,
    .prj-row .prj-actions {
        flex-wrap: wrap;
    }

    .prj-pager {
        flex-wrap: wrap;
        justify-content: center;
    }
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

.project-gallery-close,
.project-gallery-nav {
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

.project-gallery-close {
    width: 40px;
    height: 40px;
}

.project-gallery-close:hover,
.project-gallery-nav:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.22);
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
