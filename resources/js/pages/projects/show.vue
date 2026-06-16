<template>
  <div class="dashboard-main-body project-show-page">
    <div v-if="loading" class="state-box">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <p class="mb-0">Loading project details...</p>
    </div>

    <div v-else-if="error" class="state-box state-box--error">
      <i class="ri-error-warning-line"></i>
      <h5>Failed to Load Project</h5>
      <p class="mb-0">{{ error }}</p>
      <button class="project-btn" @click="fetchProject">
        <i class="ri-refresh-line"></i>
        Try Again
      </button>
    </div>

    <div v-else-if="project" class="project-shell">
      <header class="project-hero">
        <button class="hero-back-link" @click="router.push('/projects')">
          <i class="ri-arrow-left-s-line"></i>
          Back To Projects Lists
        </button>

        <div class="hero-title-row">
          <div>
            <div class="hero-name-wrap">
              <h1 class="hero-title">{{ project.title || 'Project Title' }}</h1>
              <span class="hero-status">{{ project.status_label || 'Not specified' }}</span>
            </div>
            <div class="hero-location" v-if="project.area">
              <i class="ri-map-pin-line"></i>
              {{ project.area.name || project.area.area_parents_title }}
            </div>
          </div>

          <div class="hero-actions">
            <button class="hero-action-btn" @click="shareProject">
              <i class="ri-share-line"></i>
              Share
            </button>
            <button class="hero-action-btn hero-action-btn--icon" v-if="canEditProject" @click="editProject">
              <i class="ri-pencil-line"></i>
            </button>
            <button class="hero-action-btn hero-action-btn--icon" v-if="canDeleteProject" @click="deleteProject">
              <i class="ri-delete-bin-line"></i>
            </button>
          </div>
        </div>

        <div class="hero-gallery-wrap" :class="{ 'hero-gallery-wrap--single': heroThumbTiles.length === 0 }">
          <div class="hero-main-image" @click="openImageLightbox(0)">
            <img :src="mainImage" alt="Project main image" @error="onImageError" />
            <!-- <button
              class="gallery-nav gallery-nav--prev"
              v-if="galleryImages.length > 1"
              @click.stop="prevHeroImage"
            >
              <i class="ri-arrow-left-s-line"></i>
            </button>
            <button
              class="gallery-nav gallery-nav--next"
              v-if="galleryImages.length > 1"
              @click.stop="nextHeroImage"
            >
              <i class="ri-arrow-right-s-line"></i>
            </button> -->
          </div>

          <div class="hero-thumbs" v-if="heroThumbTiles.length">
            <button
              v-for="(tile, index) in heroThumbTiles"
              :key="`gallery-${index}`"
              class="hero-thumb"
              :class="{ active: activeHeroIndex === tile.index }"
              @click="setHeroImage(tile.index)"
            >
              <img :src="tile.src" :alt="`Project image ${tile.index + 1}`" @error="onImageError" />
              <span v-if="tile.moreCount > 0" class="hero-thumb-more-overlay">+{{ tile.moreCount }}</span>
            </button>
          </div>
        </div>
      </header>

      <div class="project-content-grid">
        <div class="project-main-col">
          <section class="info-card">
            <h3 class="info-card-title" style="font-size:14px !important; line-height:1.25 !important;">Project Information</h3>
            <div class="info-divider"></div>

            <div class="project-meta-row">
              <div>
                <p class="meta-label" style="font-size:10px !important;">Title</p>
                <p class="meta-value" style="font-size:13px !important;">{{ project.title || 'Not specified' }}</p>
              </div>
              <div class="meta-date-pill" style="font-size:10px !important;">Created Date : {{ formatDate(project.created_at) }}</div>
            </div>

            <div class="project-meta-row project-meta-row--status">
              <div>
                <p class="meta-label" style="font-size:10px !important;">Status</p>
                <p class="meta-value" style="font-size:13px !important;">{{ project.status_label || 'Not specified' }}</p>
              </div>
            </div>

            <div class="text-block" v-if="project.about && project.about.trim()">
              <h4 style="font-size:13px !important;">Overview</h4>
              <p style="font-size:12px !important; line-height:1.5 !important;">{{ project.about }}</p>
            </div>

            <div class="text-block" v-if="project.features && project.features.length">
              <h4 style="font-size:13px !important;">Highlights</h4>
              <div class="highlight-chips">
                <span class="highlight-chip" style="font-size:11px !important;" v-for="feature in project.features" :key="feature.id || feature.name">
                  <img v-if="feature.img" :src="feature.img" alt="" />
                  <i v-else class="ri-award-line"></i>
                  {{ feature.name }}
                </span>
              </div>
            </div>
          </section>

         <section class="info-card floor-card" v-if="groupedFloorPlans.length">
                <h3 class="info-card-title" style="font-size:14px !important; line-height:1.25 !important;">
                  Floor Plans 
                </h3>
                <div class="info-divider"></div>

                <!-- Area Tabs -->
                <div class="floor-tabs area-tabs">
                  <button
                    v-for="tab in areaTabs"
                    :key="tab.areaId"
                    class="floor-tab area-tab"
                    :class="{ active: activeAreaTab === tab.areaId }"
                    @click="activeAreaTab = tab.areaId"
                    style="font-size:11px !important;"
                  >
                    <i class="ri-map-pin-line"></i>
                    {{ tab.label }}
                    <span class="tab-count">({{ tab.count }})</span>
                  </button>
                </div>

                <!-- Floor Plans Grid for Selected Area -->
                <div class="floor-plans-grid" v-if="currentAreaFloorPlans.length">
                  <div 
                    v-for="(floorPlan, index) in currentAreaFloorPlans" 
                    :key="floorPlan.id || index"
                    class="floor-plan-item"
                    @click="openFloorPlanLightbox(index)"
                  >
                    <div class="floor-plan-preview">
                      <div class="floor-plan-side" :class="{ 'floor-plan-side--fallback': !hasFloorCover(floorPlan) }">
                        <img
                          v-if="hasFloorCover(floorPlan)"
                          :src="getFloorCover(floorPlan, index)"
                          :alt="floorPlan.name || 'Floor Plan'"
                          @error="onImageError"
                        />
                        <div v-else class="floor-plan-side-fallback">
                          <span class="floor-plan-side-number">{{ getFloorBadge(floorPlan) }}</span>
                          <span class="floor-plan-side-label">{{ getFloorType(floorPlan) }}</span>
                        </div>
                      </div>
                      <img 
                        class="floor-plan-main" 
                        :src="getImageUrl(floorPlan.image_url)" 
                        :alt="floorPlan.name || 'Floor Plan'" 
                        @error="onImageError" 
                      />
                    </div>
                    <div class="floor-plan-footer">
                      <div class="floor-plan-info">
                        <span class="floor-plan-name">{{ floorPlan.name || 'Floor Plan' }}</span>
                        <span class="floor-plan-area" v-if="floorPlan.area_name">
                          <i class="ri-map-pin-2-line"></i>
                          {{ floorPlan.area_name }}
                        </span>
                      </div>
                      <small>
                        <i class="ri-calendar-line"></i> 
                        {{ formatDate(floorPlan.created_at) }}
                      </small>
                    </div>
                  </div>
                </div>

                <!-- Empty State -->
                <div v-else class="empty-floor-plans">
                  <i class="ri-building-4-line"></i>
                  <p>No floor plans available for this area</p>
                </div>
              </section>
        </div>

        <aside class="project-side-col">
          <section class="side-card" v-if="project.developer">
            <h3 class="info-card-title" style="font-size:14px !important; line-height:1.25 !important;">Developer Information</h3>
            <div class="info-divider"></div>
            <div class="developer-row">
              <img
                :src="project.developer.avatar || 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'"
                alt="Developer"
                @error="onImageError"
              />
              <div>
                <h4 style="font-size:13px !important;">{{ project.developer.name || 'Developer Name' }}</h4>
                <p style="font-size:10px !important;">Developer</p>
              </div>
            </div>
            <button class="side-action-btn" style="font-size:10px !important;">View Developer Details</button>
          </section>

          <section class="side-card" v-if="project.area">
            <h3 class="info-card-title" style="font-size:14px !important; line-height:1.25 !important;">Location Information</h3>
            <div class="info-divider"></div>
            <div class="location-icon">
              <i class="ri-map-pin-2-fill"></i>
            </div>
            <h4 class="location-title" style="font-size:13px !important;">{{ project.area.name || project.area.area_parents_title }}</h4>
            <p class="location-sub" style="font-size:10px !important;">Area</p>
          </section>
        </aside>
      </div>
    </div>

     <div v-if="showLightbox && lightboxImages.length" class="lightbox-overlay" @click="closeLightbox">
      <div class="lightbox-content" @click.stop>
        <div class="lightbox-header">
          <div class="lightbox-info">
            <span class="lightbox-title">
              <i class="ri-map-pin-2-line"></i>
              Floor Plans - {{ getCurrentAreaName() }}
            </span>
            <span class="lightbox-counter">
              {{ currentImageIndex + 1 }} / {{ lightboxImages.length }}
            </span>
          </div>
          <button class="lightbox-close" @click="closeLightbox">
            <i class="ri-close-line"></i>
          </button>
        </div>
        <div class="lightbox-main">
          <button class="lightbox-nav lightbox-nav--prev" @click="prevImage" :disabled="currentImageIndex === 0">
            <i class="ri-arrow-left-s-line"></i>
          </button>
          
          <div class="lightbox-image-wrapper">
            <img :src="lightboxImages[currentImageIndex]" class="lightbox-image" alt="" @error="onImageError" />
            
            <button 
              v-if="currentImageIndex > 0" 
              class="lightbox-image-nav lightbox-image-nav--prev"
              @click.stop="prevImage"
            >
              <i class="ri-arrow-left-s-line"></i>
            </button>
            <button 
              v-if="currentImageIndex < lightboxImages.length - 1" 
              class="lightbox-image-nav lightbox-image-nav--next"
              @click.stop="nextImage"
            >
              <i class="ri-arrow-right-s-line"></i>
            </button>
          </div>
          
          <button class="lightbox-nav lightbox-nav--next" @click="nextImage" :disabled="currentImageIndex >= lightboxImages.length - 1">
            <i class="ri-arrow-right-s-line"></i>
          </button>
        </div>
        
        <div class="lightbox-dots" v-if="lightboxImages.length > 1">
          <span 
            v-for="(_, index) in lightboxImages" 
            :key="index"
            class="lightbox-dot"
            :class="{ active: currentImageIndex === index }"
            @click="currentImageIndex = index"
          ></span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import { enablePageNaturalScroll, disablePageNaturalScroll } from '@/composables/usePageNaturalScroll.js';

export default {
  name: "ProjectDetails",
  setup() {
    const route = useRoute();
    const router = useRouter();
    
    const project = ref(null);
    const loading = ref(true);
    const error = ref(null);
    const showLightbox = ref(false);
    const currentImageIndex = ref(0);
    const canEditProject = ref(true);
    const canDeleteProject = ref(true);
    const activeHeroIndex = ref(0);
    const activeFloorTab = ref('1 Bedroom');
    const lightboxImages = ref([]);
    const activeAreaTab = ref(null);
    const FALLBACK_IMAGE = 'https://images.unsplash.com/photo-1460317442991-0ec209397118?auto=format&fit=crop&w=1600&q=80';

    const resolveMediaPath = (input) => {
      if (!input) return '';
      if (typeof input === 'string') return input;
      if (typeof input === 'object') {
        return input.url || input.image_url || input.path || input.src || '';
      }
      return String(input);
    };

    const getImageUrl = (pathLike) => {
      const path = resolveMediaPath(pathLike);
      if (!path) return FALLBACK_IMAGE;
      if (path.startsWith('http://') || path.startsWith('https://')) return path;
      if (path.includes('/storage/http://') || path.includes('/storage/https://')) {
        const urlParts = path.split('/storage/');
        return urlParts[1] || urlParts[0];
      }
      if (path.startsWith('/storage/')) return path;
      return `/storage/${path}`;
    };

    const galleryImages = computed(() => {
      const images = Array.isArray(project.value?.images) ? project.value.images : [];
      if (!images.length && project.value?.main_image) {
        return [getImageUrl(project.value.main_image)];
      }
      if (!images.length) {
        return [FALLBACK_IMAGE];
      }
      return images.map((img) => getImageUrl(img)).filter(Boolean);
    });

    const mainImage = computed(() => galleryImages.value[activeHeroIndex.value] || FALLBACK_IMAGE);
    const heroThumbTiles = computed(() => {
      const images = galleryImages.value.slice(1);
      if (!images.length) return [];
      const tiles = images.slice(0, 3).map((src, i) => ({
        index: i + 1,
        src,
        moreCount: 0,
      }));
      if (images.length > 3 && tiles.length) {
        tiles[tiles.length - 1].moreCount = images.length - 3;
      }
      return tiles;
    });

    const floorPlans = computed(() => (Array.isArray(project.value?.floor_plan_images) ? project.value.floor_plan_images : []));
    const floorTabs = computed(() => {
      const tabs = ['All'];
      floorPlans.value.forEach((item) => {
        const name = (item.name || '').toLowerCase();
        const match = name.match(/(\d+)\s*bed(room)?/);
        if (match) {
          const label = `${match[1]} Bedroom`;
          if (!tabs.includes(label)) tabs.push(label);
        }
      });
      if (tabs.length === 1) {
        tabs.push('1 Bedroom', '2 Bedroom', '3 Bedroom', '4 Bedroom', '5 Bedroom');
      }
      return tabs;
    });

    const filteredFloorPlans = computed(() => {
      if (activeFloorTab.value === 'All') return floorPlans.value;
      return floorPlans.value.filter((item) =>
        (item.name || '').toLowerCase().includes(activeFloorTab.value.toLowerCase())
      );
    });

    const activeFloorPlan = computed(() => filteredFloorPlans.value[0] || null);
    const groupedFloorPlans = computed(() => {
      const plans = floorPlans.value;
      if (!plans.length) return [];

      const grouped = plans.reduce((acc, plan) => {
        const areaId = plan.area_id || 'unassigned';
        if (!acc[areaId]) {
          acc[areaId] = {
            areaId: areaId,
            areaName: plan.area_name || plan.area || 'General',
            plans: []
          };
        }
        acc[areaId].plans.push(plan);
        return acc;
      }, {});

      return Object.values(grouped);
    });

    const areaTabs = computed(() => {
      return groupedFloorPlans.value.map(group => ({
        areaId: group.areaId,
        label: group.areaName,
        count: group.plans.length
      }));
    });

    const currentAreaFloorPlans = computed(() => {
      if (!activeAreaTab.value || activeAreaTab.value === 'unassigned') {
        return floorPlans.value.filter(p => !p.area_id);
      }
      
      const group = groupedFloorPlans.value.find(g => g.areaId === activeAreaTab.value);
      return group ? group.plans : [];
    });

    const getFloorType = (floorPlan) => {
      const name = String(floorPlan?.name || '').toLowerCase();
      if (name.includes('bedroom')) return 'BEDROOM';
      if (name.includes('studio')) return 'STUDIO';
      if (name.includes('duplex')) return 'DUPLEX';
      if (name.includes('penthouse')) return 'PENTHOUSE';
      return 'FLOOR';
    };

   
    const fetchProject = async () => {
      try {
        loading.value = true;
        error.value = null;

        const response = await api.get(`/listings/projects/${route.params.id}?include=area,developer,features`);

        if (response.data.status) {
          project.value = response.data.data;
          activeHeroIndex.value = 0;
          const tabs = floorTabs.value;
          activeFloorTab.value = tabs.includes('1 Bedroom') ? '1 Bedroom' : (tabs[0] || 'All');
        } else {
          throw new Error(response.data.message || 'Failed to fetch project');
        }
      } catch (err) {
        console.error('Error fetching project:', err);
        error.value = 'Failed to load project details. Please try again.';
      } finally {
        loading.value = false;
      }
    };

    const prevHeroImage = () => {
      if (!galleryImages.value.length) return;
      activeHeroIndex.value = activeHeroIndex.value > 0 ? activeHeroIndex.value - 1 : galleryImages.value.length - 1;
    };

    const nextHeroImage = () => {
      if (!galleryImages.value.length) return;
      activeHeroIndex.value = activeHeroIndex.value < galleryImages.value.length - 1 ? activeHeroIndex.value + 1 : 0;
    };

    const setHeroImage = (index) => {
      if (!galleryImages.value.length) return;
      if (index < 0 || index >= galleryImages.value.length) return;
      activeHeroIndex.value = index;
    };

   const getCurrentAreaName = () => {
      if (!activeAreaTab.value) return 'General';
      if (activeAreaTab.value === 'unassigned') return 'Unassigned';
      const tab = areaTabs.value.find(t => t.areaId === activeAreaTab.value);
      return tab ? tab.label : 'General';
    };

    // تحسين دالة openLightbox لإضافة ميزة الـ Swipe
    let touchStartX = 0;
    let touchEndX = 0;

    const handleTouchStart = (e) => {
      touchStartX = e.changedTouches[0].screenX;
    };

    const handleTouchEnd = (e) => {
      touchEndX = e.changedTouches[0].screenX;
      const diff = touchStartX - touchEndX;
      if (Math.abs(diff) > 50) { // الحد الأدنى للـ Swipe
        if (diff > 0) {
          nextImage();
        } else {
          prevImage();
        }
      }
    };

    const openLightbox = (images, index) => {
      if (!images.length) {
        Swal.fire({
          title: 'No Images',
          text: 'No images available.',
          icon: 'warning',
          confirmButtonColor: '#0B0736'
        });
        return;
      }
      lightboxImages.value = images;
      currentImageIndex.value = index;
      showLightbox.value = true;
      document.body.style.overflow = 'hidden';
    };

    const openFloorPlanLightbox = (index) => {
      const images = currentAreaFloorPlans.value.map((item) => getImageUrl(item.image_url));
      openLightbox(images, index);
    };


    const closeLightbox = () => {
      showLightbox.value = false;
      document.body.style.overflow = 'auto';
    };

    const nextImage = () => {
      if (currentImageIndex.value < lightboxImages.value.length - 1) {
        currentImageIndex.value++;
      }
    };

    const prevImage = () => {
      if (currentImageIndex.value > 0) {
        currentImageIndex.value--;
      }
    };

    const openImageLightbox = (index) => {
      openLightbox(galleryImages.value, index);
    };



    const editProject = () => {
      router.push(`/projects/${project.value.id}/edit`);
    };

    const deleteProject = async () => {
      const result = await Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
      });

      if (!result.isConfirmed) return;

      try {
        const response = await api.delete(`/listings/projects/${project.value.id}`);
        
        if (response.data.status) {
          await Swal.fire({
            title: 'Deleted!',
            text: 'Project has been deleted successfully.',
            icon: 'success',
            confirmButtonColor: '#0B0736',
            timer: 2000,
            showConfirmButton: false
          });
          router.push('/projects');
        }
      } catch (err) {
        console.error('Error deleting project:', err);
        Swal.fire({
          title: 'Error!',
          text: 'Failed to delete project.',
          icon: 'error',
          confirmButtonColor: '#0B0736'
        });
      }
    };

    const shareProject = async () => {
      const url = window.location.href;
      try {
        await navigator.clipboard.writeText(url);
        window.$showNotification?.('Project link copied', 'success');
      } catch (_e) {
        window.open(url, '_blank');
      }
    };

    const formatDate = (dateString) => {
      if (!dateString) return 'Not specified';
      return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    };

    const getFloorCover = (image, index) => {
      const fromMedia = getImageUrl(image.cover_url || image.thumbnail || image.image_url || '');
      if (fromMedia !== FALLBACK_IMAGE) return fromMedia;
      return galleryImages.value[index % Math.max(galleryImages.value.length, 1)] || FALLBACK_IMAGE;
    };

    const hasFloorCover = (image) => {
      const raw = resolveMediaPath(image?.cover_url || image?.thumbnail);
      return Boolean(raw);
    };

    const getFloorBadge = (image) => {
      const name = String(image?.name || '').toLowerCase();
      const match = name.match(/(\d+)/);
      return match ? match[1] : '1';
    };

    const onImageError = (event) => {
      if (!event?.target) return;
      if (event.target.src !== FALLBACK_IMAGE) {
        event.target.src = FALLBACK_IMAGE;
      }
    };

    const handleKeydown = (event) => {
      if (!showLightbox.value) return;
      switch (event.key) {
        case 'Escape': closeLightbox(); break;
        case 'ArrowLeft': prevImage(); break;
        case 'ArrowRight': nextImage(); break;
      }
    };

   
  onMounted(() => {
      enablePageNaturalScroll();
      fetchProject().then(() => {
        if (areaTabs.value.length) {
          activeAreaTab.value = areaTabs.value[0].areaId;
        }
      });
      document.addEventListener('keydown', handleKeydown);
    });
    const cleanup = () => {
      document.removeEventListener('keydown', handleKeydown);
      document.body.style.overflow = '';
    };

    onBeforeUnmount(() => {
      cleanup();
      disablePageNaturalScroll();
    });

    return {
      project,
      loading,
      error,
      showLightbox,
      currentImageIndex,
      lightboxImages,
      canEditProject,
      canDeleteProject,
      router,
      mainImage,
      galleryImages,
      heroThumbTiles,
      floorTabs,
      activeFloorTab,
      filteredFloorPlans,
      activeFloorPlan,
      fetchProject,
      getImageUrl,
      openImageLightbox,
      closeLightbox,
      nextImage,
      prevImage,
      prevHeroImage,
      nextHeroImage,
      setHeroImage,
      editProject,
      deleteProject,
      shareProject,
      formatDate,
      getFloorCover,
      hasFloorCover,
      getFloorBadge,
      onImageError,
      cleanup,
      openFloorPlanLightbox,
        groupedFloorPlans,
      areaTabs,
      activeAreaTab,
      currentAreaFloorPlans,
      getFloorType,
        getCurrentAreaName,
      handleTouchStart,
      handleTouchEnd,
    };
  },

};
</script>

<style scoped>
.project-show-page {
  padding: 8px 6px 24px;
  min-height: 0;
}

.project-shell {
  border-radius: 16px;
  overflow: visible;
  border: 1px solid rgba(255, 255, 255, 0.35);
  background:
    linear-gradient(180deg, rgba(92, 86, 176, 0.9), rgba(100, 83, 170, 0.95)),
    url("https://images.unsplash.com/photo-1545239351-1141bd82e8a6?auto=format&fit=crop&w=1400&q=80") center / cover;
}

.project-hero {
  padding: 8px 10px 10px;
}

.hero-back-link {
  border: none;
  background: transparent;
  color: #ede9fe;
  font-size: 11px;
  display: inline-flex;
  gap: 4px;
  align-items: center;
  margin-bottom: 6px;
  opacity: 0.9;
}

.hero-title-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 10px;
}

.hero-name-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
}

.hero-title {
  color: #e5e7eb;
  font-size: 26px !important;
  font-weight: 700 !important;
  margin: 0;
  line-height: 1.05;
  letter-spacing: -0.02em;
}

.hero-status {
  background: #f8b133;
  border-radius: 999px;
  font-size: 11px;
  padding: 4px 11px;
  color: #201a2a;
  font-weight: 700;
}

.hero-location {
  margin-top: 5px;
  color: #d1d5db;
  font-size: 12px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.hero-actions {
  display: flex;
  gap: 8px;
}

.hero-action-btn {
  border: none;
  border-radius: 999px;
  background: #fff;
  min-height: 28px;
  padding: 0 10px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-weight: 600;
  font-size: 10px;
}

.hero-action-btn--icon {
  width: 28px;
  justify-content: center;
  padding: 0;
}

.hero-gallery-wrap {
  display: grid;
  grid-template-columns: minmax(0, 0.78fr) minmax(210px, 0.22fr);
  gap: 10px;
}

.hero-gallery-wrap--single {
  grid-template-columns: minmax(0, 1fr);
}

.hero-main-image {
  border-radius: 12px;
  overflow: hidden;
  position: relative;
  height: 360px;
  min-height: 360px;
  cursor: pointer;
}

.hero-main-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.gallery-nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 30px;
  height: 30px;
  border-radius: 50%;
  border: none;
  background: #fff;
}

.gallery-nav--prev {
  left: 10px;
}

.gallery-nav--next {
  right: 10px;
}

.hero-thumbs {
  display: grid;
  grid-template-rows: repeat(3, 113px);
  gap: 10px;
  align-content: start;
}

.hero-thumb {
  border: none;
  border-radius: 14px;
  overflow: hidden;
  position: relative;
  padding: 0;
  background: #d1d5db;
}

.hero-thumb.active {
  outline: 2px solid rgba(255, 255, 255, 0.8);
}

.hero-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.hero-thumb--more {
  background: rgba(0, 0, 0, 0.35);
  color: #fff;
  font-weight: 700;
  font-size: 20px;
}

.hero-thumb-more-overlay {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  background: rgba(17, 24, 39, 0.45);
  color: #fff;
  font-size: 18px;
  font-weight: 700;
}

.project-content-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 300px;
  gap: 8px;
  padding: 0 10px 16px;
  align-items: start;
}

.info-card,
.side-card {
  background: #fff;
  border-radius: 12px;
  padding: 10px;
  border: 1px solid rgba(100, 116, 139, 0.2);
}

.info-card-title {
  font-size: 14px;
  margin: 0;
  color: #0f172a;
  line-height: 1.2;
}

.info-divider {
  height: 1px;
  background: #e2e8f0;
  margin: 10px 0;
}

.project-meta-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.project-meta-row--status {
  margin-top: 8px;
}

.meta-label {
  margin: 0;
  font-size: 10px;
  color: #94a3b8;
}

.meta-value {
  margin: 2px 0 0;
  font-size: 13px;
  font-weight: 600;
  color: #111827;
}

.meta-date-pill {
  margin-top: 10px;
  background: #f3f4f6;
  border-radius: 999px;
  height: fit-content;
  padding: 3px 8px;
  font-size: 10px;
  color: #111827;
}

.text-block {
  margin-top: 14px;
}

.text-block h4 {
  margin: 0 0 6px;
  font-size: 13px;
  font-weight: 700;
  color: #0f172a;
}

.text-block p {
  margin: 0;
  color: #1f2937;
  font-size: 12px;
  line-height: 1.5;
}

.highlight-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.highlight-chip {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  padding: 4px 8px;
  font-size: 11px;
}

.highlight-chip img {
  width: 11px;
  height: 11px;
  object-fit: contain;
}

.floor-card {
  margin-top: 12px;
}

.floor-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  margin-bottom: 8px;
}

.floor-tab {
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  background: #f8fafc;
  padding: 5px 10px;
  font-size: 11px;
}

.floor-tab.active {
  background: #02054e;
  color: #fff;
}

.floor-plan-item {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  overflow: hidden;
  max-width: 720px;
}

.floor-plan-preview {
  background: #f8fafc;
  display: grid;
  grid-template-columns: 72px minmax(0, 1fr);
  min-height: 180px;
  height: auto;
  cursor: pointer;
}

.floor-plan-side {
  width: 100%;
  height: 100%;
  background: #0b1f4d;
}

.floor-plan-side img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.floor-plan-side--fallback {
  display: grid;
  place-items: center;
  background: linear-gradient(180deg, #06265d 0%, #041437 100%);
}

.floor-plan-side-fallback {
  display: flex;
  flex-direction: column;
  align-items: center;
  color: #fff;
  line-height: 1;
}

.floor-plan-side-number {
  font-size: 24px;
  font-weight: 700;
}

.floor-plan-side-label {
  margin-top: 4px;
  font-size: 9px;
  letter-spacing: 0.12em;
}

.floor-plan-main {
  width: 100%;
  max-width: 100%;
  min-height: 180px;
  max-height: 360px;
  height: auto;
  object-fit: contain;
  padding: 8px 10px;
  box-sizing: border-box;
}

.floor-plan-footer {
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  padding: 7px 10px;
  font-size: 11px;
}

.floor-plan-footer small {
  color: #9ca3af;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 10px;
}

.project-side-col {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.developer-row {
  display: flex;
  gap: 10px;
  align-items: center;
}

.developer-row img {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  object-fit: cover;
  border: 1px solid #d1d5db;
}

.developer-row h4 {
  margin: 0;
  font-size: 13px;
}

.developer-row p {
  margin: 3px 0 0;
  color: #9ca3af;
  font-size: 10px;
}

.side-action-btn {
  width: 100%;
  margin-top: 8px;
  border: none;
  border-radius: 999px;
  background: #f3f4f6;
  padding: 7px 10px;
  font-size: 10px;
}

.location-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  margin: 8px auto 10px;
  border: 1px solid #d8b4fe;
  color: #7e22ce;
  font-size: 18px;
  background: #faf5ff;
}

.location-title {
  text-align: center;
  margin: 0;
  font-size: 13px;
  color: #111827;
}

.location-sub {
  margin: 4px 0 0;
  text-align: center;
  color: #9ca3af;
  font-size: 10px;
}

.lightbox-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.9);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 5000;
  padding: 20px;
}

.lightbox-content {
  background: #0f172a;
  border-radius: 12px;
  width: 100%;
  max-width: 1100px;
}

.lightbox-header {
  padding: 10px;
  text-align: right;
}

.lightbox-close {
  background: #1e293b;
  border: none;
  color: #fff;
  border-radius: 8px;
  padding: 8px;
}

.lightbox-main {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  min-height: 70vh;
  padding: 16px;
}

.lightbox-image {
  max-width: calc(100% - 140px);
  max-height: 78vh;
  border-radius: 8px;
  object-fit: contain;
}

.lightbox-nav {
  border: none;
  width: 42px;
  height: 42px;
  border-radius: 999px;
  background: #fff;
}

.state-box {
  min-height: 220px;
  border-radius: 14px;
  background: #fff;
  display: grid;
  place-items: center;
  gap: 8px;
  text-align: center;
  border: 1px solid #e2e8f0;
}

.state-box--error i {
  font-size: 42px;
  color: #dc2626;
}

.project-btn {
  border: none;
  border-radius: 8px;
  background: #1d4ed8;
  color: #fff;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
}

@media (max-width: 1399.98px) {
  .hero-title {
    font-size: 23px !important;
  }

  .hero-location {
    font-size: 11px;
  }
}

@media (max-width: 1199.98px) {
  .project-content-grid {
    grid-template-columns: 1fr;
  }

  .project-side-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 991.98px) {
  .hero-title-row {
    flex-direction: column;
  }

  .hero-gallery-wrap {
    grid-template-columns: 1fr;
  }

  .hero-thumbs {
    grid-template-columns: repeat(4, minmax(0, 1fr));
    grid-template-rows: unset;
  }

  .hero-main-image {
    height: 260px;
    min-height: 260px;
  }

  .hero-gallery-wrap {
    gap: 6px;
  }

  .hero-thumb {
    border-radius: 10px;
  }

  .hero-title {
    font-size: 20px !important;
  }

  .hero-location {
    font-size: 10px;
  }

  .project-side-col {
    grid-template-columns: 1fr;
  }
}
.area-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 12px;
}

.area-tab {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: 20px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  font-size: 11px;
  transition: all 0.2s ease;
}

.area-tab:hover {
  background: #eef2ff;
  border-color: #6366f1;
}

.area-tab.active {
  background: #02054e;
  color: #fff;
  border-color: #02054e;
}

.area-tab .tab-count {
  font-size: 9px;
  opacity: 0.7;
  margin-left: 2px;
}

.area-tab.active .tab-count {
  opacity: 1;
}

/* Grid Layout للفلور بلان */
.floor-plans-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
  margin-top: 8px;
}

.floor-plan-item {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  background: #fff;
}

.floor-plan-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.floor-plan-preview {
  display: grid;
  grid-template-columns: 60px minmax(0, 1fr);
  min-height: 150px;
  background: #f8fafc;
}

.floor-plan-side {
  width: 100%;
  height: 100%;
  background: #0b1f4d;
}

.floor-plan-side img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.floor-plan-side--fallback {
  display: grid;
  place-items: center;
  background: linear-gradient(180deg, #06265d 0%, #041437 100%);
}

.floor-plan-side-fallback {
  display: flex;
  flex-direction: column;
  align-items: center;
  color: #fff;
  line-height: 1;
}

.floor-plan-side-number {
  font-size: 20px;
  font-weight: 700;
}

.floor-plan-side-label {
  margin-top: 4px;
  font-size: 8px;
  letter-spacing: 0.12em;
}

.floor-plan-main {
  width: 100%;
  max-width: 100%;
  min-height: 150px;
  max-height: 300px;
  height: auto;
  object-fit: contain;
  padding: 8px 10px;
  box-sizing: border-box;
}

.floor-plan-footer {
  border-top: 1px solid #e2e8f0;
  padding: 8px 12px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.floor-plan-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
}

.floor-plan-name {
  font-size: 12px;
  font-weight: 600;
  color: #111827;
}

.floor-plan-area {
  font-size: 10px;
  color: #6b7280;
  display: flex;
  align-items: center;
  gap: 3px;
}

.floor-plan-footer small {
  color: #9ca3af;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 9px;
  white-space: nowrap;
}

/* Empty State */
.empty-floor-plans {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 30px 20px;
  color: #9ca3af;
}

.empty-floor-plans i {
  font-size: 32px;
  margin-bottom: 8px;
}

.empty-floor-plans p {
  margin: 0;
  font-size: 12px;
}

/* Responsive */
@media (max-width: 991.98px) {
  .floor-plans-grid {
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 12px;
  }

  .area-tabs {
    gap: 4px;
  }

  .area-tab {
    padding: 4px 10px;
    font-size: 10px;
  }
}

@media (max-width: 575.98px) {
  .floor-plans-grid {
    grid-template-columns: 1fr;
  }

  .floor-plan-preview {
    grid-template-columns: 50px minmax(0, 1fr);
    min-height: 120px;
  }

  .floor-plan-main {
    min-height: 120px;
    max-height: 200px;
  }

  .floor-plan-info {
    gap: 1px;
  }

  .floor-plan-name {
    font-size: 11px;
  }
}
.lightbox-content {
  background: #0f172a;
  border-radius: 16px;
  width: 100%;
  max-width: 1200px;
  max-height: 95vh;
  display: flex;
  flex-direction: column;
  position: relative;
}

.lightbox-header {
  padding: 16px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  flex-shrink: 0;
}

.lightbox-info {
  display: flex;
  align-items: center;
  gap: 16px;
  color: #e5e7eb;
}

.lightbox-title {
  font-size: 14px;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 8px;
}

.lightbox-title i {
  color: #818cf8;
}

.lightbox-counter {
  font-size: 12px;
  color: #9ca3af;
  background: rgba(255, 255, 255, 0.1);
  padding: 2px 12px;
  border-radius: 12px;
}

.lightbox-close {
  background: rgba(255, 255, 255, 0.1);
  border: none;
  color: #fff;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  font-size: 20px;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.lightbox-close:hover {
  background: rgba(255, 255, 255, 0.2);
  transform: rotate(90deg);
}

.lightbox-main {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 20px;
  flex: 1;
  min-height: 60vh;
  position: relative;
}

.lightbox-image-wrapper {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  max-width: calc(100% - 120px);
  height: 100%;
  min-height: 50vh;
}

.lightbox-image {
  max-width: 100%;
  max-height: 70vh;
  border-radius: 8px;
  object-fit: contain;
  user-select: none;
  -webkit-user-drag: none;
}

.lightbox-nav {
  border: none;
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.15);
  color: #fff;
  font-size: 24px;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.lightbox-nav:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.25);
  transform: scale(1.05);
}

.lightbox-nav:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

/* أزرار التنقل على الصورة نفسها (للتجربة الأفضل) */
.lightbox-image-nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  background: rgba(0, 0, 0, 0.5);
  border: none;
  color: #fff;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  font-size: 20px;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(4px);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.lightbox-image-nav:hover {
  background: rgba(0, 0, 0, 0.7);
  transform: translateY(-50%) scale(1.05);
}

.lightbox-image-nav--prev {
  left: 16px;
}

.lightbox-image-nav--next {
  right: 16px;
}

/* النقاط السفلية */
.lightbox-dots {
  display: flex;
  justify-content: center;
  gap: 8px;
  padding: 16px;
  flex-shrink: 0;
  border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.lightbox-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.2);
  cursor: pointer;
  transition: all 0.3s ease;
}

.lightbox-dot.active {
  background: #818cf8;
  width: 24px;
  border-radius: 4px;
}

.lightbox-dot:hover {
  background: rgba(255, 255, 255, 0.4);
}

.lightbox-dot.active:hover {
  background: #6366f1;
}

/* تحسينات للموبايل */
@media (max-width: 767.98px) {
  .lightbox-main {
    padding: 12px;
    gap: 8px;
    min-height: 50vh;
  }

  .lightbox-image-wrapper {
    max-width: calc(100% - 80px);
    min-height: 40vh;
  }

  .lightbox-image {
    max-height: 55vh;
  }

  .lightbox-nav {
    width: 36px;
    height: 36px;
    font-size: 18px;
  }

  .lightbox-image-nav {
    width: 32px;
    height: 32px;
    font-size: 14px;
  }

  .lightbox-image-nav--prev {
    left: 4px;
  }

  .lightbox-image-nav--next {
    right: 4px;
  }

  .lightbox-header {
    padding: 12px 16px;
  }

  .lightbox-title {
    font-size: 12px;
  }

  .lightbox-counter {
    font-size: 10px;
    padding: 2px 8px;
  }

  .lightbox-close {
    width: 32px;
    height: 32px;
    font-size: 16px;
  }

  .lightbox-dots {
    padding: 12px;
    gap: 6px;
  }

  .lightbox-dot {
    width: 6px;
    height: 6px;
  }

  .lightbox-dot.active {
    width: 18px;
  }
}

@media (max-width: 575.98px) {
  .lightbox-info {
    gap: 8px;
  }

  .lightbox-title {
    font-size: 10px;
  }

  .lightbox-title i {
    display: none;
  }

  .lightbox-counter {
    font-size: 9px;
    padding: 1px 6px;
  }

  .lightbox-image-wrapper {
    max-width: calc(100% - 60px);
  }

  .lightbox-nav {
    width: 30px;
    height: 30px;
    font-size: 14px;
  }
}
</style>