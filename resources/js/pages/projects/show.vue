<template>
  <div class="dashboard-main-body-inner">
    <div class="row gy-4">
      <!-- Main Content -->
      <div class="col-lg-8">
        <div class="card card-main p-0 radius-12 overflow-hidden">
          <div class="card-body p-0">
            <!-- Images Section -->
            <div class="property-gallery" v-if="project">
              <div class="gallery-container">
                <div class="main-image-section" @click="openLightbox(0)">
                  <img :src="getMainImage()" alt="Project main image" class="main-image" />
                </div>
              </div>
            </div>

            <!-- Project Details Section -->
            <div class="property-content" v-if="project">
              
              <!-- Basic Info -->
              <div class="property-main-info mb-16">
                <div class="property-actions mb-16">
                  <button class="btn btn-primary">
                    {{ project.status_label || "Not specified" }}
                  </button>
                </div>
                
                <div class="price-main">
                  <!--<h3 class="property-price">{{ formatPrice(project.from_price) }} - {{ formatPrice(project.to_price) }}</h3>-->
                  <h4 class="property-title">{{ project.title || 'Project Title' }}</h4>
                </div>

                <div class="specs-grid-main">
                  <!--<div class="spec-main-item">-->
                  <!--  <div class="spec-main-info">-->
                  <!--    <span class="spec-main-value">-->
                  <!--      <img :src="areaIcon" class="imgicon"/>-->
                  <!--      {{ project.from_sqft }} - {{ project.to_sqft }} sqft-->
                  <!--    </span>-->
                  <!--  </div>-->
                  <!--</div>-->
                  
                  <div class="spec-main-item" v-if="project.developer">
                    <div class="spec-main-info">
                      <span class="spec-main-value">
                        <img :src=" project.developer.avatar " class="imgicon"/>
                        {{ project.developer.name }}
                      </span>
                    </div>
                  </div>
                  
                  <div class="spec-main-item" v-if="project.area">
                    <div class="spec-main-info">
                      <span class="spec-main-value">
                        <i class="ri-map-pin-line"></i>
                        {{ project.area.name || project.area.area_parents_title }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Project Details -->
              <div class="detailed-info-section mb-16">
                <div class="info-section">
                  <h3 class="section-title mb-20">Project Details</h3>
                  <div class="info-grid">
                    <div class="info-item">
                      <span class="info-label">Title</span>
                      <span class="info-value">{{ project.title || "Not specified" }}</span>
                    </div>
                    
                    <div class="info-item">
                      <span class="info-label">Status</span>
                      <span class="info-value">{{ project.status_label || "Not specified" }}</span>
                    </div>
                    
                    <!--<div class="info-item">-->
                    <!--  <span class="info-label">Price Range</span>-->
                    <!--  <span class="info-value">{{ formatPrice(project.from_price) }} - {{ formatPrice(project.to_price) }}</span>-->
                    <!--</div>-->
                    
                    <!--<div class="info-item">-->
                    <!--  <span class="info-label">Area Range</span>-->
                    <!--  <span class="info-value">{{ project.from_sqft }} - {{ project.to_sqft }} sqft</span>-->
                    <!--</div>-->
                    
                    <div class="info-item" v-if="project.developer">
                      <span class="info-label">Developer</span>
                      <span class="info-value">{{ project.developer.name }}</span>
                    </div>
                    
                    <div class="info-item" v-if="project.area">
                      <span class="info-label">Area</span>
                      <span class="info-value">{{ project.area.name || project.area.area_parents_title }}</span>
                    </div>
                    
                    <div class="info-item">
                      <span class="info-label">Created Date</span>
                      <span class="info-value">{{ formatDate(project.created_at) }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- About Project -->
              <div class="detailed-info-section mb-16" v-if="project.about && project.about.trim()">
                <div class="info-section">
                  <h3 class="section-title mb-20">About Project</h3>
                  <div class="description-content">
                    <p class="description-text">{{ project.about }}</p>
                  </div>
                </div>
              </div>

              <!-- Features -->
              <div class="detailed-info-section mb-16" v-if="project.features && project.features.length > 0">
                <div class="info-section">
                  <h3 class="section-title mb-20">Features</h3>
                  <div class="row g-3">
                    <div v-for="feature in project.features" 
                         :key="feature.id" 
                         class="col-md-3 col-sm-6">
                      <div class="d-flex align-items-center p-3 border rounded">
                        <img v-if="feature.img" 
                             :src="feature.img" 
                             alt="Feature Icon"
                             class="me-3"
                             width="24"
                             height="24"
                             style="object-fit: contain;">
                        <div>
                          <p class="mb-0" style="font-size: 13px;">{{ feature.name }}</p>
                          <small class="text-muted" style="font-size: 11px;">{{ feature.category }}</small>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

            </div>

            <!-- Loading State -->
            <div v-else-if="loading" class="property-content">
              <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                  <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3" style="font-size: 14px;">Loading project details...</p>
              </div>
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="property-content">
              <div class="text-center py-5">
                <i class="ri-error-warning-line text-danger mb-3" style="font-size: 48px;"></i>
                <h5>Failed to Load Project</h5>
                <p class="text-muted" style="font-size: 14px;">{{ error }}</p>
                <button class="btn btn-primary" @click="fetchProject">
                  <i class="ri-refresh-line me-2"></i>
                  Try Again
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="col-lg-4">
        <div class="sidebar-sticky-container">
          <div class="agent-sidebar-card">
            
            <!-- Developer Info -->
            <div class="agent-profile" v-if="project && project.developer">
              <img 
                :src="project.developer.avatar || 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'" 
                alt="Developer" 
                class="agent-sidebar-avatar" 
              />
              <div class="agent-sidebar-info">
                <h5 class="agent-sidebar-name">{{ project.developer.name || 'Developer Name' }}</h5>
                <small class="agent-sidebar-company">Developer</small>
              </div>
            </div>

            <!-- Area Info -->
            <div class="sidebar-section" v-if="project && project.area">
              <h6 class="sidebar-title">Area Information</h6>
              <div class="quick-info-grid">
                <div class="quick-info-item">
                  <i class="ri-map-pin-line"></i>
                  <div>
                    <span class="quick-info-label">Area</span>
                    <span class="quick-info-value">{{ project.area.name || project.area.area_parents_title }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Project Actions -->
            <div class="sidebar-section">
              <div class="property-actions-dropdown-wrapper">
                <div class="property-actions-dropdown">
                  <button 
                    class="dropdown-toggle"
                    @click="toggleActionsDropdown"
                  >
                    Project Actions
                  </button>
                  
                  <div class="dropdown-container" :class="{ expanded: showActionsDropdown }">
                    <div class="dropdown-menu" :class="{ show: showActionsDropdown }">
                      
                      <!-- Edit Project -->
                      <button 
                        v-if="canEditProject" 
                        class="dropdown-item"
                        @click="editProject"
                      >
                        <i class="ri-edit-line"></i>
                        Edit Project
                      </button>

                      <!-- Delete Project -->
                      <button 
                        v-if="canDeleteProject"
                        class="dropdown-item"
                        @click="deleteProject"
                      >
                        <i class="ri-delete-bin-line"></i>
                        Delete Project
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
      
    </div>

    <!-- Lightbox Modal -->
    <div v-if="showLightbox && project && project.images" class="lightbox-overlay" @click="closeLightbox">
      <div class="lightbox-content" @click.stop>
        <div class="lightbox-header">
          <div class="lightbox-header-right">
            <button class="lightbox-close" @click="closeLightbox">
              <i class="ri-close-line"></i>
            </button>
          </div>
        </div>

        <div class="lightbox-main">
          <div class="lightbox-image-container">
            <img 
              :src="getImageUrl(project.images[currentImageIndex]?.url || project.images[currentImageIndex])" 
              :alt="'Project image ' + (currentImageIndex + 1)" 
              class="lightbox-image" 
            />
          </div>
        </div>

        <div class="lightbox-thumbnails">
          <div 
            v-for="(image, index) in project.images" 
            :key="index" 
            class="lightbox-thumbnail"
            :class="{ active: currentImageIndex === index }"
            @click="setCurrentImage(index)"
          >
            <img :src="getImageUrl(image.url || image)" :alt="'Thumbnail ' + (index + 1)" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';

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
    const showActionsDropdown = ref(false);
    
    // Icons
    const areaIcon = '/assets/icons/area-size.svg';
    const buildingIcon = '/assets/icons/building-icon.svg';
    const developerIcon = '/assets/icons/developer-icon.svg';

    const canEditProject = ref(true);
    const canDeleteProject = ref(true);

    const fetchProject = async () => {
      try {
        loading.value = true;
        error.value = null;
        
        // طلب المشروع مع تضمين بيانات Area
        const response = await api.get(`/listings/projects/${route.params.id}?include=area,developer,features`);
        
        if (response.data.status) {
          project.value = response.data.data;
          console.log('Project data with area:', project.value);
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

    const getMainImage = () => {
      if (project.value?.main_image) {
        return getImageUrl(project.value.main_image);
      } else if (project.value?.images && project.value.images.length > 0) {
        return getImageUrl(project.value.images[0].url || project.value.images[0]);
      }
      return '/default-project.jpg';
    };

    const getImageUrl = (path) => {
      if (!path) return '/default-project.jpg';
      if (path.startsWith('http://') || path.startsWith('https://')) return path;
      if (path.includes('/storage/http://') || path.includes('/storage/https://')) {
        const urlParts = path.split('/storage/');
        return urlParts[1] || urlParts[0];
      }
      if (path.startsWith('/storage/')) return path;
      return `/storage/${path}`;
    };

    // Lightbox functions
    const openLightbox = (index) => {
      if (!project.value?.images || project.value.images.length === 0) {
        Swal.fire({
          title: 'No Images',
          text: 'No images available for this project.',
          icon: 'warning',
          confirmButtonColor: '#01062d'
        });
        return;
      }
      currentImageIndex.value = index;
      showLightbox.value = true;
      document.body.style.overflow = 'hidden';
    };

    const closeLightbox = () => {
      showLightbox.value = false;
      document.body.style.overflow = 'auto';
    };

    const nextImage = () => {
      if (project.value?.images && currentImageIndex.value < project.value.images.length - 1) {
        currentImageIndex.value++;
      }
    };

    const prevImage = () => {
      if (currentImageIndex.value > 0) {
        currentImageIndex.value--;
      }
    };

    const setCurrentImage = (index) => {
      currentImageIndex.value = index;
    };

    // Actions
    const toggleActionsDropdown = () => {
      showActionsDropdown.value = !showActionsDropdown.value;
    };

    const closeActionsDropdown = () => {
      showActionsDropdown.value = false;
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
            confirmButtonColor: '#01062d',
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
          confirmButtonColor: '#01062d'
        });
      }
    };

    const formatPrice = (price) => {
      if (!price) return 'N/A';
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'AED',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
      }).format(price);
    };

    const formatDate = (dateString) => {
      if (!dateString) return 'Not specified';
      return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    };

    const handleKeydown = (event) => {
      if (!showLightbox.value) return;
      switch(event.key) {
        case 'Escape': closeLightbox(); break;
        case 'ArrowLeft': prevImage(); break;
        case 'ArrowRight': nextImage(); break;
      }
    };

    onMounted(() => {
      fetchProject();
      document.addEventListener('keydown', handleKeydown);
      
      document.addEventListener('click', (e) => {
        if (!e.target.closest('.property-actions-dropdown')) {
          closeActionsDropdown();
        }
      });
    });

    const cleanup = () => {
      document.removeEventListener('keydown', handleKeydown);
    };

    return {
      project,
      loading,
      error,
      showLightbox,
      currentImageIndex,
      showActionsDropdown,
      areaIcon,
      buildingIcon,
      developerIcon,
      canEditProject,
      canDeleteProject,
      fetchProject,
      getMainImage,
      getImageUrl,
      openLightbox,
      closeLightbox,
      nextImage,
      prevImage,
      setCurrentImage,
      toggleActionsDropdown,
      closeActionsDropdown,
      editProject,
      deleteProject,
      formatPrice,
      formatDate,
      cleanup
    };
  },

  beforeUnmount() {
    this.cleanup();
  }
};
</script>

<style scoped>
/* استيراد الأنماط الأساسية من مثال البروبرتي */
.property-gallery {
  position: relative;
  margin-bottom: 20px;
}

.gallery-container {
  display: grid;
  gap: 12px;
  height: 400px;
}

.main-image-section {
  border-radius: 12px;
  overflow: hidden;
  position: relative;
  cursor: pointer;
}

.main-image {
  width: 100%;
  height: 100%;
  transition: transform 0.3s ease;
}

.main-image-section:hover .main-image {
  transform: scale(1.05);
}

/* Property Content */
.property-content {
  /* background: white; */
}

.property-main-info {
  padding: 30px;
  margin-bottom: 20px;
  border-radius: 12px;
  border: 1px solid #e9ecef;
  background-color: #ffffff;
}

.price-main {
  margin-bottom: 8px;
}

.property-price {
  font-size: 24px !important;
  font-weight: 800;
  color: #01062d;
  margin: 0 0 15px 0 !important;
  line-height: 1;
}

.property-title {
  font-size: 16px !important;
  font-weight: 600;
  color: #6c757d;
  margin: 0 !important;
  line-height: 1.2;
}

.specs-grid-main {
  display: flex;
  gap: 8px;
  align-items: center;
  justify-content: flex-start;
  margin-top: 12px;
}

.spec-main-item {
  display: flex;
  align-items: center;
  padding: 8px 12px;
  min-width: 100px;
}

.spec-main-info {
  display: flex;
  flex-direction: column;
  text-align: center;
  gap: 2px;
}

.spec-main-value {
  font-size: 16px;
  font-weight: 700;
  color: #01062d;
  line-height: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
}

.spec-main-value i {
  font-size: 14px;
  opacity: 0.8;
}

.spec-main-label {
  font-size: 11px;
  color: #6c757d;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.detailed-info-section {
  padding: 16px;
  margin-bottom: 16px;
  border-radius: 12px;
  border: 1px solid #e9ecef;
  background-color: #ffffff;
}

.info-section {
  margin-bottom: 20px;
}

.section-title {
  font-size: 20px !important;
  font-weight: 600;
  color: #01062d;
  padding: 10px 12px;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 12px 24px;
  margin-bottom: 16px;
}

.info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 12px;
  background: #f8f9fa;
  border-radius: 8px;
  border: 1px solid #e9ecef;
}

.info-label {
  font-weight: 600;
  color: #555;
  font-size: 13px;
}

.info-value {
  font-weight: 800;
  color: #01062d;
  font-size: 13px;
}

.description-content {
  background: #f8f9fa;
  padding: 16px;
  border-radius: 8px;
  border: 1px solid #e9ecef;
}

.description-text {
  line-height: 1.6;
  color: #555;
  font-size: 14px;
  margin: 0;
}

/* Sidebar */
.sidebar-sticky-container {
  position: sticky;
  top: 90px;
  height: fit-content;
  border-radius: 20px;
}

.agent-sidebar-card {
  background: rgba(255, 255, 255, 0.05);
  backdrop-filter: blur(40px);
  border-radius: 20px;
  padding: 16px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  border: 1px solid #e9ecef;
  position: sticky;
  top: 100px;
}

.agent-sidebar-card::before {
  content: "";
  position: absolute;
  inset: 0;
  z-index: -1;
  background: linear-gradient(180deg, rgb(255 255 255) 0%, rgb(20 30 80 / 79%) 0%, rgba(5, 10, 40, 0.95) 100%);
  border-radius: 20px;
}

.agent-profile {
  display: flex;
  align-items: start;
  gap: 12px;
  margin-bottom: 16px;
  padding-bottom: 16px;
  border-bottom: 1px solid #e9ecef;
  text-align: left;
}

.agent-sidebar-avatar {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid #01062d;
}

.agent-sidebar-info {
  width: 100%;
}

.agent-sidebar-name {
  font-size: 16px;
  font-weight: 700;
  color: #ffffff;
  margin: 0 0 6px 0;
  line-height: 1.3;
}

.agent-sidebar-company {
  font-size: 13px;
  color: #ffffff;
  margin: 0 0 10px 0;
  line-height: 1.4;
}

/* Sidebar Sections */
.sidebar-section {
  margin-bottom: 16px;
  padding-bottom: 12px;
}

.sidebar-title {
  font-size: 15px;
  font-weight: 700;
  color: #ffffff;
  margin-bottom: 10px;
  padding-bottom: 6px;
}

/* Quick Info */
.quick-info-grid {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.quick-info-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.quick-info-item i {
  font-size: 18px;
  color: #ffffff;
  width: 24px;
}

.quick-info-label {
  display: block;
  font-size: 11px;
  color: rgba(255, 255, 255, 0.7);
  margin-bottom: 2px;
}

.quick-info-value {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: #ffffff;
}

/* Dropdown Actions */
.property-actions-dropdown-wrapper {
  position: relative;
  margin-bottom: 0;
}

.property-actions-dropdown {
  position: relative;
  display: block;
}

.dropdown-toggle {
  width: 100%;
  align-items: center;
  justify-content: space-between;
  padding: 8px;
  background: #FAA300;
  color: white;
  border: none;
  border-radius: 6px;
  font-weight: 500;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.3s ease;
  text-align: center;
}

.dropdown-toggle:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(1, 6, 45, 0.3);
}

.dropdown-container {
  position: relative;
}

.dropdown-menu {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  opacity: 0;
  visibility: hidden;
  max-height: 0;
  overflow: hidden;
  transition: all 0.3s ease;
  margin: 0;
  border: none;
  box-shadow: none;
  transform: none;
  z-index: 1000;
  background: white;
  border-radius: 8px;
  border: 1px solid #e9ecef;
}

.dropdown-menu.show {
  opacity: 1;
  visibility: visible;
  max-height: 400px;
  margin-top: 8px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.dropdown-item {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: none;
  border: none;
  text-align: left;
  font-size: 13px;
  font-weight: 500;
  color: #333;
  cursor: pointer;
  transition: all 0.2s ease;
  border-bottom: 1px solid #f8f9fa;
}

.dropdown-item:last-child {
  border-bottom: none;
}

.dropdown-item:hover {
  background: rgba(59, 130, 246, 0.1);
  color: #3b82f6;
}

.dropdown-item i {
  font-size: 16px;
  width: 20px;
  text-align: center;
}

/* Lightbox */
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
  z-index: 9999;
  padding: 20px;
}

.lightbox-content {
  background: none;
  border-radius: 12px;
  width: 100%;
  max-width: 1200px;
  height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.lightbox-header {
  display: flex;
  justify-content: end;
  align-items: center;
  padding: 16px 24px;
  background: none;
}

.lightbox-close {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #ffffff;
  padding: 8px;
  border-radius: 6px;
  transition: all 0.3s ease;
}

.lightbox-close:hover {
  background: #e9ecef;
  color: #01062d;
}

.lightbox-main {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px;
  position: relative;
  height: 400px;
}

.lightbox-nav {
  background: rgba(255, 255, 255, 0.9);
  color: #01062d;
  border: none;
  width: 60px;
  height: 60px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 30px;
  cursor: pointer;
  transition: all 0.3s ease;
  z-index: 2;
  font-weight: 700;
}

.lightbox-nav:hover:not(:disabled) {
  background: #01062d;
  color: white;
}

.lightbox-nav:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.lightbox-image-container {
  flex: 1;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 20px;
}

.lightbox-image {
  max-width: 100%;
  max-height: 100%;
  border-radius: 8px;
}

.lightbox-thumbnails {
  display: none;
  gap: 8px;
  padding: 16px 24px;
  overflow-x: auto;
}

.lightbox-thumbnail {
  width: 80px;
  height: 60px;
  border-radius: 6px;
  overflow: hidden;
  cursor: pointer;
  border: 2px solid transparent;
  transition: all 0.3s ease;
  flex-shrink: 0;
}

.lightbox-thumbnail.active {
  border-color: #01062d;
}

.lightbox-thumbnail:hover {
  transform: scale(1.05);
}

.lightbox-thumbnail img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* Property Actions */
.property-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 16px;
  justify-content: space-between;
}

.btn {
  font-weight: 500;
}

/* Responsive */
@media (max-width: 768px) {
  .property-content {
    padding: 0px !important;
  }
  
  .gallery-container {
    height: 300px;
  }
  
  .property-main-info {
    padding: 20px;
  }
  
  .property-price {
    font-size: 20px !important;
  }
  
  .property-title {
    font-size: 14px !important;
  }
  
  .specs-grid-main {
    gap: 6px;
  }
  
  .spec-main-item {
    padding: 6px 8px;
    min-width: 80px;
  }
  
  .spec-main-value {
    font-size: 14px;
  }
  
  .detailed-info-section {
    padding: 12px;
  }
  
  .section-title {
    font-size: 18px !important;
  }
  
  .info-grid {
    grid-template-columns: 1fr;
  }
  
  .agent-sidebar-card {
    position: relative;
    top: 0;
    margin-bottom: 16px;
  }
  
  .lightbox-main {
    padding: 10px;
  }
  
  .lightbox-nav {
    width: 40px;
    height: 40px;
    font-size: 20px;
  }
}

@media (max-width: 576px) {
  .gallery-container {
    height: 250px;
  }
  
  .property-price {
    font-size: 18px !important;
  }
  
  .specs-grid-main {
    flex-wrap: wrap;
    justify-content: center;
  }
  
  .spec-main-item {
    min-width: 70px;
  }
  
  .spec-main-value {
    font-size: 13px;
  }
}

@media (min-width: 992px) {
  .info-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

.imgicon {
  width: 20px;
  height: 20px;
  object-fit: contain;
}

.card-main {
  background: none !important;
}

.btn-primary {
  background-color: #01062d;
  border-color: #01062d;
}

.btn-primary:hover, .btn-primary:active, .btn-primary:focus {
  background-color: #FAA300 !important;
  border-color: #FAA300 !important;
}
</style>