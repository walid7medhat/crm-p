<template>
    <div class="dashboard-main-body">
        <Breadcrumb 
            :title="isEditMode ? 'Edit Project' : 'Add Project'" 
            :breadcrumbs="[
                { name: 'Projects', path: '/projects' },
                { name: isEditMode ? 'Edit' : 'Add' }
            ]" 
        />

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ isEditMode ? 'Edit Project' : 'Add Project' }}</h5>
                <button class="btn btn-outline-secondary" @click="$router.back()">
                    <iconify-icon icon="lucide:arrow-left" class="me-2"></iconify-icon>
                    Back
                </button>
            </div>
            
            <div class="card-body">
                <form @submit.prevent="submitForm" enctype="multipart/form-data">
                    <div class="row">
                        <!-- Left Column -->
                        <div class="col-md-8">
                            <!-- 🏢 Basic Information Section -->
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0 text-dark">
                                        <i class="fas fa-building me-2"></i>
                                        Project Information
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label fw-semibold">
                                                <i class="fas fa-heading me-1 text-primary"></i>
                                                Project Title
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control form-control-lg" 
                                                   v-model="projectForm.title" 
                                                   :class="{'is-invalid': errors.title}"
                                                   placeholder="Enter project title"
                                                   autofocus>
                                            <div class="invalid-feedback" v-if="errors.title">
                                                {{ errors.title[0] }}
                                            </div>
                                            <div class="form-text text-muted mt-1">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Enter a descriptive title for the project
                                            </div>
                                        </div>

                                        <div class="col-md-12 mb-3">
                                            <label class="form-label fw-semibold">
                                                <i class="fas fa-map-marker-alt me-1 text-primary"></i>
                                                Location
                                                <span class="text-danger">*</span>
                                            </label>
                                            <v-select v-model="projectForm.area_id"
                                                      :options="areas"
                                                      :reduce="area => area.id"
                                                      label="name"
                                                      placeholder="Select project location"
                                                      :loading="areasLoading"
                                                      :class="{'is-invalid': errors.area_id}"
                                                      :filterable="true"
                                                      @search="loadAreas">
                                                <template #option="{ name, full_name, children_count }">
                                                    <div class="d-flex flex-column">
                                                        <strong>{{ name }}</strong>
                                                        <small v-if="full_name" class="text-muted">
                                                            <i class="fas fa-location-dot me-1"></i>
                                                            {{ full_name }}
                                                        </small>
                                                    </div>
                                                </template>
                                                <template #no-options>
                                                    <div class="text-muted text-center py-3">
                                                        <i class="fas fa-search me-2"></i>
                                                        {{ areasLoading ? 'Loading locations...' : 'Type to search for locations' }}
                                                    </div>
                                                </template>
                                            </v-select>
                                            <div class="invalid-feedback" v-if="errors.area_id">
                                                {{ errors.area_id[0] }}
                                            </div>
                                            <div class="form-text text-muted mt-1">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Select the main location of the project
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <label class="form-label fw-semibold">
                                                <i class="fas fa-file-alt me-1 text-primary"></i>
                                                Project Description
                                            </label>
                                            <textarea class="form-control" v-model="projectForm.about" 
                                                      rows="5" 
                                                      placeholder="Describe the project features, amenities, and key highlights..."></textarea>
                                            <div class="invalid-feedback" v-if="errors.about">
                                                {{ errors.about[0] }}
                                            </div>
                                            <div class="form-text text-muted mt-1">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Provide detailed information about the project
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 📋 Project Details Section -->
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0 text-dark">
                                        <i class="fas fa-clipboard-list me-2"></i>
                                        Project Details
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-semibold">
                                                <i class="fas fa-chart-line me-1 text-primary"></i>
                                                Project Status
                                                <span class="text-danger">*</span>
                                            </label>
                                            <v-select v-model="projectForm.status"
                                                      :options="statusOptions"
                                                      :reduce="status => status.value"
                                                      label="label"
                                                      placeholder="Select project status"
                                                      :class="{'is-invalid': errors.status}">
                                                <template #option="{ label, value, icon }">
                                                    <div class="d-flex align-items-center">
                                                        <!--<i :class="icon" class="me-2"></i>-->
                                                        <span>{{ label }}</span>
                                                    </div>
                                                </template>
                                                <template #selected-option="{ label, icon }">
                                                    <div class="d-flex align-items-center">
                                                        <!--<i :class="icon" class="me-2"></i>-->
                                                        <span>{{ label }}</span>
                                                    </div>
                                                </template>
                                            </v-select>
                                            <div class="invalid-feedback" v-if="errors.status">
                                                {{ errors.status[0] }}
                                            </div>
                                            <div class="form-text text-muted mt-1">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Current phase of the project
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                              <!-- 📐 Floor Plans Images Section -->
                                    <div class="card mb-4">
                                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0 text-dark">
                                                <i class="fas fa-layer-group me-2"></i>
                                                Floor Plan Images
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <!-- Upload New Images -->
                                            <div class="mb-4">
                                                <label class="form-label fw-semibold">
                                                    <i class="fas fa-upload me-1 text-primary"></i>
                                                    Upload Floor Plan Images
                                                </label>
                                                
                                                <div class="file-upload-area" 
                                                     :class="{'has-preview': floorPlanImages.length > 0}"
                                                     @click="$refs.floorPlanImagesInput.click()"
                                                     @dragover.prevent
                                                     @drop="handleFloorPlanDrop">
                                                    <input ref="floorPlanImagesInput"
                                                           type="file" 
                                                           class="d-none" 
                                                           @change="handleFloorPlanImages" 
                                                           multiple
                                                           accept="image/*">
                                                    
                                                    <div v-if="floorPlanImages.length === 0" class="upload-placeholder">
                                                        <div class="upload-icon">
                                                            <i class="fas fa-cloud-upload-alt fa-2x text-muted"></i>
                                                        </div>
                                                        <div class="upload-text mt-2">
                                                            <span class="text-primary fw-medium">Click to upload</span>
                                                            <span class="text-muted d-block">or drag and drop</span>
                                                        </div>
                                                        <div class="upload-hint mt-1">
                                                            <small class="text-muted">PNG, JPG, JPEG up to 5MB</small>
                                                        </div>
                                                    </div>
                                                    
                                                    <div v-else class="images-grid">
                                                        <div v-for="(image, index) in floorPlanImages" 
                                                             :key="index" 
                                                             class="image-thumbnail">
                                                            <img :src="image.preview" 
                                                                 alt="Floor Plan" 
                                                                 class="thumbnail-image">
                                                            <div class="thumbnail-overlay">
                                                                <button type="button" 
                                                                        class="btn btn-sm btn-danger"
                                                                        @click.stop="removeFloorPlanImage(index)">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </div>
                                                            <div class="thumbnail-badge">
                                                                {{ index + 1 }}
                                                            </div>
                                                        </div>
                                                        <div class="add-more-images" @click.stop="$refs.floorPlanImagesInput.click()">
                                                            <i class="fas fa-plus fa-2x text-primary"></i>
                                                            <small class="d-block mt-2">Add More</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="form-text text-muted mt-2">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Upload floor plan images. You can reorder by drag and drop.
                                                </div>
                                            </div>
                                
                                            <!-- Existing Images (for edit mode) -->
                                            <div v-if="existingFloorPlanImages.length > 0" class="existing-images-section">
                                                <h6 class="fw-semibold mb-3">
                                                    <i class="fas fa-images me-2"></i>
                                                    Existing Floor Plan Images
                                                </h6>
                                                
                                                <div class="existing-images-grid">
                                                    <div v-for="image in existingFloorPlanImages" 
                                                         :key="image.id" 
                                                         class="existing-image-item">
                                                        <img :src="image.image_url" 
                                                             alt="Floor Plan" 
                                                             class="existing-image">
                                                        <div class="existing-image-overlay">
                                                            <button type="button" 
                                                                    class="btn btn-sm btn-danger"
                                                                    @click="markFloorPlanImageForDeletion(image.id)">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div v-if="floorPlanImagesToDelete.length > 0" class="mt-3">
                                                    <div class="alert alert-warning py-2">
                                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                                        {{ floorPlanImagesToDelete.length }} image(s) marked for deletion
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                        </div>

                        <!-- Right Column -->
                        <div class="col-md-4">
                            <!-- 🏗️ Developer Information Section -->
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0 text-dark">
                                        <i class="fas fa-hard-hat me-2"></i>
                                        Developer Information
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-user-tie me-1 text-primary"></i>
                                            Select Developer
                                            <span class="text-danger">*</span>
                                        </label>
                                        <v-select v-model="projectForm.developer_id"
                                                  :options="developers"
                                                  :reduce="developer => developer.id"
                                                  label="name"
                                                  placeholder="Search developer..."
                                                  :filterable="false"
                                                  :loading="developersLoading">
                                            <template #option="{ name, avatar }">
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-container me-2">
                                                        <img v-if="avatar" :src="avatar" alt="Developer" class="rounded-circle" width="32" height="32">
                                                        <div v-else class="avatar-placeholder rounded-circle d-flex align-items-center justify-content-center"
                                                             style="width: 32px; height: 32px; background-color: #e9ecef; color: #6c757d;">
                                                            <i class="fas fa-building"></i>
                                                        </div>
                                                    </div>
                                                    <span>{{ name }}</span>
                                                </div>
                                            </template>
                                        </v-select>

                                        <div class="invalid-feedback" v-if="errors.developer_id">
                                            {{ errors.developer_id[0] }}
                                        </div>
                                        <div class="form-text text-muted mt-1">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Select the company developing this project
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ✨ Features & Amenities Section -->
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0 text-dark">
                                        <i class="fas fa-star me-2"></i>
                                        Features & Amenities
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-check-circle me-1 text-primary"></i>
                                            Select Features
                                        </label>
                                        
                                        <v-select v-model="projectForm.features"
                                                  :options="filteredFeatures"
                                                  :reduce="feature => feature.id"
                                                  label="name"
                                                  placeholder="Type to search features..."
                                                  multiple
                                                  :filterable="true"
                                                  @search="handleFeatureSearch"
                                                  :loading="featuresLoading"
                                                  :class="{'is-invalid': errors.features}">
                                            <template #option="{ name, avatar }">
                                                <div class="d-flex align-items-center">
                                                    <div class="feature-icon-container me-2">
                                                        <img v-if="avatar" 
                                                             :src="avatar" 
                                                             alt="Feature"
                                                             class="rounded"
                                                             width="24"
                                                             height="24"
                                                             style="object-fit: contain;">
                                                        <div v-else class="feature-placeholder rounded d-flex align-items-center justify-content-center"
                                                             style="width: 24px; height: 24px; background-color: #e9ecef; color: #6c757d;">
                                                            <i class="fas fa-check"></i>
                                                        </div>
                                                    </div>
                                                    <span>{{ name }}</span>
                                                </div>
                                            </template>
                                            <template #selected-option="{ name, img }">
                                                <div class="d-flex align-items-center">
                                                    <div class="feature-icon-container me-1">
                                                        <img v-if="img" 
                                                             :src="img" 
                                                             alt="Feature"
                                                             class="rounded"
                                                             width="16"
                                                             height="16"
                                                             style="object-fit: contain;">
                                                        <div v-else class="feature-placeholder rounded d-flex align-items-center justify-content-center"
                                                             style="width: 16px; height: 16px; background-color: #e9ecef; color: #6c757d;">
                                                            <i class="fas fa-check"></i>
                                                        </div>
                                                    </div>
                                                    <span class="text-truncate">{{ name }}</span>
                                                </div>
                                            </template>
                                            <template #no-options>
                                                <div class="text-muted text-center py-3">
                                                    <i class="fas fa-search me-2"></i>
                                                    {{ featuresLoading ? 'Loading features...' : 'Type to search for features' }}
                                                </div>
                                            </template>
                                        </v-select>
                                        
                                        <div v-if="projectForm.features.length > 0" class="mt-2 d-flex align-items-center">
                                            <span class="badge bg-primary rounded-pill me-2">
                                                {{ projectForm.features.length }}
                                            </span>
                                            <small class="text-muted">features selected</small>
                                        </div>
                                        <div class="form-text text-muted mt-1">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Choose amenities and features available in this project
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 🖼️ Project Image Section -->
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0 text-dark">
                                        <i class="fas fa-image me-2"></i>
                                        Project Image
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-upload me-1 text-primary"></i>
                                            Upload Image
                                            <span class="text-danger">*</span>
                                        </label>
                                        
                                        <!-- File Upload Area -->
                                        <div class="file-upload-area" 
                                             :class="{'has-preview': imagePreview || currentImage}"
                                             @click="$refs.fileInput.click()">
                                            <input ref="fileInput" 
                                                   type="file" 
                                                   class="d-none" 
                                                   @change="handleImageUpload" 
                                                   accept="image/*">
                                            
                                            <div v-if="!imagePreview && !currentImage" class="upload-placeholder">
                                                <div class="upload-icon">
                                                    <i class="fas fa-cloud-upload-alt fa-2x text-muted"></i>
                                                </div>
                                                <div class="upload-text mt-2">
                                                    <span class="text-primary fw-medium">Click to upload</span>
                                                    <span class="text-muted d-block">or drag and drop</span>
                                                </div>
                                                <div class="upload-hint mt-1">
                                                    <small class="text-muted">PNG, JPG, JPEG up to 5MB</small>
                                                </div>
                                            </div>
                                            
                                            <div v-else class="image-preview-container">
                                                <img :src="imagePreview || currentImage" 
                                                     alt="Project Image" 
                                                     class="preview-image">
                                                <div class="preview-overlay">
                                                    <button type="button" 
                                                            class="btn btn-sm btn-light"
                                                            @click.stop="$refs.fileInput.click()">
                                                        <i class="fas fa-sync me-1"></i> Change
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="invalid-feedback" v-if="errors.main_image">
                                            {{ errors.main_image[0] }}
                                        </div>
                                        <div class="form-text text-muted mt-2">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Upload the main image that represents this project
                                        </div>
                                    </div>
                                </div>
                            </div>

                          
                            
                        </div>
                        <div class="col-md-12 text-right">
                             <button type="submit" 
                                                class="btn btn-primary btn-lg m-2" 
                                                :disabled="loading">
                                            <span v-if="loading">
                                                <i class="fas fa-spinner fa-spin me-2"></i>
                                                {{ isEditMode ? 'Updating...' : 'Creating...' }}
                                            </span>
                                            <span v-else>
                                                <i class="fas fa-save me-2"></i>
                                                {{ isEditMode ? 'Update Project' : 'Create Project' }}
                                            </span>
                                        </button>
                                        
                                        <button type="button" 
                                                class="btn btn-outline-secondary btn-lg m-2" 
                                                @click="$router.back()" 
                                                :disabled="loading">
                                            <i class="fas fa-times me-2"></i>
                                            Cancel
                                        </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted, computed, getCurrentInstance } from "vue";
import { useRoute, useRouter } from "vue-router";
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';

export default {
    name: 'ProjectForm',
    components: {
        Breadcrumb,
        vSelect  
    },
    setup() {
        const instance = getCurrentInstance();
        const route = useRoute();
        const router = useRouter();

        const isEditMode = computed(() => route.name === 'edit-project');
        const projectId = computed(() => route.params.id);

        // Refs
        const errors = ref({});
        const loading = ref(false);
        const developers = ref([]);
        const developersLoading = ref(false);
        const areas = ref([]);
        const areasLoading = ref(false);
        const features = ref([]);
        const featuresLoading = ref(false);
        const featureSearchTerm = ref('');
        const selectedImage = ref(null);
        const imagePreview = ref('');
        const currentImage = ref('');
        const floorPlanImages = ref([]);
        const existingFloorPlanImages = ref([]);
        const floorPlanImagesToDelete = ref([]);
        const floorPlanImagesInput = ref(null);


        // Status options with icons
        const statusOptions = ref([
            { value: 'Under Construction', label: 'Under Construction', icon: 'fas fa-hourglass-start text-warning' },
            { value: 'Ready', label: 'Ready', icon: 'fas fa-check-circle text-success' }
        ]);

        // Project Form Data (removed price and sqft fields)
        const projectForm = ref({
            title: "",
            developer_id: null,
            area_id: null,
            status: null,
            about: "",
            features: [],
            floor_plan_images: []
        });
        const handleFloorPlanImages = (event) => {
            const files = Array.from(event.target.files);
            
            if (files.length === 0) return;

            const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            const maxSize = 5 * 1024 * 1024; // 5MB
            
            files.forEach(file => {
                if (!validTypes.includes(file.type)) {
                    showNotification('Please upload valid image files (JPEG, PNG, JPG, GIF)', 'error');
                    return;
                }

                if (file.size > maxSize) {
                    showNotification('Image size should be less than 5MB', 'error');
                    return;
                }

                const reader = new FileReader();
                reader.onload = (e) => {
                    floorPlanImages.value.push({
                        file: file,
                        preview: e.target.result
                    });
                };
                reader.readAsDataURL(file);
            });

            event.target.value = '';
        };

        const handleFloorPlanDrop = (event) => {
            event.preventDefault();
            const files = Array.from(event.dataTransfer.files);
            
            if (files.length === 0) return;

            const validFiles = files.filter(file => 
                file.type.startsWith('image/') && file.size <= 5 * 1024 * 1024
            );

            if (validFiles.length === 0) {
                showNotification('Please drop valid image files (max 5MB each)', 'error');
                return;
            }

            validFiles.forEach(file => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    floorPlanImages.value.push({
                        file: file,
                        preview: e.target.result
                    });
                };
                reader.readAsDataURL(file);
            });
        };

        const removeFloorPlanImage = (index) => {
            floorPlanImages.value.splice(index, 1);
        };

        const markFloorPlanImageForDeletion = (imageId) => {
            if (!floorPlanImagesToDelete.value.includes(imageId)) {
                floorPlanImagesToDelete.value.push(imageId);
            }
        };


        // Computed: Filter features based on search
        const filteredFeatures = computed(() => {
            if (!featureSearchTerm.value) return features.value;
            
            const search = featureSearchTerm.value.toLowerCase();
            return features.value.filter(feature =>
                feature.name.toLowerCase().includes(search)
            );
        });

        // Helper function to show notifications
        const showNotification = (message, type = 'info') => {
            if (instance && instance.proxy && instance.proxy.$showNotification) {
                instance.proxy.$showNotification(message, type);
            } else if (typeof window !== 'undefined' && window.$showNotification) {
                window.$showNotification(message, type);
            } else {
                console.log(`${type}: ${message}`);
            }
        };

        // Fetch developers
        const loadDevelopers = async (search = '') => {
                    if (developers.value.length > 0 && !search) return; // لا تعيد الكتابة إذا القائمة موجودة
                
                    try {
                        developersLoading.value = true;
                        const token = localStorage.getItem('token');
                
                        let url = '/api/listings/developers';
                        if (search) url += `?search=${encodeURIComponent(search)}`;
                
                        const response = await fetch(url, {
                            headers: {
                                'Authorization': 'Bearer ' + token,
                                'Content-Type': 'application/json'
                            }
                        });
                
                        if (!response.ok) {
                            throw new Error(`Failed to fetch developers: ${response.status} ${response.statusText}`);
                        }
                
                        const data = await response.json();
                
                        if (search) {
                            developers.value = data.data || [];
                        } else {
                            developers.value = [...developers.value, ...(data.data || [])];
                        }
                
                    } catch (error) {
                        console.error('❌ Error fetching developers:', error);
                        showNotification('Failed to load developers: ' + error.message, 'error');
                    } finally {
                        developersLoading.value = false;
                    }
                };


        // Fetch areas
        const loadAreas = async (search = '') => {
            try {
                areasLoading.value = true;
                const token = localStorage.getItem('token');
                
                let url = '/api/listings/areas';
                if (search) {
                    url += `?search=${encodeURIComponent(search)}`;
                }
                
                const response = await fetch(url, {
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error(`Failed to fetch areas: ${response.status} ${response.statusText}`);
                }
                
                const data = await response.json();
                const areasData = data.data || data;

                areas.value = areasData
                    .map(area => ({
                        id: area.id,
                        name: area.name || area.title || area.area_parents_title || 'Unnamed Area',
                        children_count: area.children_count ?? 0,
                        full_name: area.area_parents_title || area.name || area.title || ''
                    }));
                
                console.log('📊 Loaded Areas:', areas.value);
                
            } catch (error) {
                console.error('❌ Error fetching areas:', error);
                showNotification('Failed to load areas: ' + error.message, 'error');
            } finally {
                areasLoading.value = false;
            }
        };

        // Fetch features
        const loadFeatures = async (search = '') => {
            try {
                featuresLoading.value = true;
                const token = localStorage.getItem('token');
                
                let url = '/api/listings/features';
                if (search) {
                    url += `?search=${encodeURIComponent(search)}`;
                }
                
                const response = await fetch(url, {
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    }
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`Failed to fetch features: ${response.status} ${response.statusText}`);
                }
                
                const data = await response.json();
                console.log('📊 Features response:', data);
                
                if (search) {
                    features.value = data.data || [];
                } else {
                    if (features.value.length === 0) {
                        features.value = data.data || [];
                    }
                }
                
            } catch (error) {
                console.error('❌ Error fetching features:', error);
                showNotification('Failed to load features: ' + error.message, 'error');
            } finally {
                featuresLoading.value = false;
            }
        };

        // Handle feature search in v-select
        const handleFeatureSearch = (search) => {
            featureSearchTerm.value = search;
            if (search) {
                loadFeatures(search);
            }
        };

        // Fetch project data for editing
            const fetchProject = async () => {
                    try {
                        loading.value = true;
                        const token = localStorage.getItem('token');
                        const response = await fetch(`/api/listings/projects/${projectId.value}`, {
                            headers: {
                                'Authorization': 'Bearer ' + token,
                                'Content-Type': 'application/json'
                            }
                        });
                
                        if (!response.ok) throw new Error('Failed to fetch project');
                
                        const data = await response.json();
                        const projectData = data.data;
                
                        // Load areas and features first
                        await Promise.all([
                            loadAreas(),
                            loadFeatures()
                        ]);
                
                        // Set project form basic info
                        projectForm.value.title = projectData.title || "";
                        projectForm.value.area_id = projectData.area?.id || null;
                        projectForm.value.status = projectData.status || null;
                        projectForm.value.about = projectData.about || "";
                        projectForm.value.features = projectData.features ? projectData.features.map(f => f.id) : [];
                
                        if (projectData.main_image) {
                            currentImage.value = projectData.main_image;
                        }
                        
                        // Set existing floor plan images
                        if (projectData.floor_plan_images) {
                            existingFloorPlanImages.value = projectData.floor_plan_images;
                        }
                
                        // Load developers separately and **ensure current developer is included**
                        developersLoading.value = true;
                        await loadDevelopers();
                
                        if (projectData.developer) {
                            const currentDeveloper = {
                                id: projectData.developer.id,
                                name: projectData.developer.name,
                                avatar: projectData.developer.avatar || null
                            };
                
                            // Add developer to the list if not exists
                            if (!developers.value.find(d => d.id === currentDeveloper.id)) {
                                developers.value.unshift(currentDeveloper);
                            }
                
                            // Assign developer_id **after developers array is ready**
                            projectForm.value.developer_id = currentDeveloper.id;
                        }
                
                    } catch (error) {
                        console.error(error);
                        showNotification('Failed to load project', 'error');
                        router.push('/projects');
                    } finally {
                        loading.value = false;
                        developersLoading.value = false;
                    }
                };



        // Handle image upload
        const handleImageUpload = (event) => {
            const file = event.target.files[0];
            
            if (!file) {
                selectedImage.value = null;
                imagePreview.value = '';
                return;
            }

            // Validate file type
            const validTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            if (!validTypes.includes(file.type)) {
                showNotification('Please upload a valid image file (JPEG, PNG, JPG)', 'error');
                event.target.value = '';
                return;
            }

            // Validate file size (max 5MB)
            const maxSize = 5 * 1024 * 1024; // 5MB
            if (file.size > maxSize) {
                showNotification('Image size should be less than 5MB', 'error');
                event.target.value = '';
                return;
            }

            selectedImage.value = file;
            
            // Create preview
            const reader = new FileReader();
            reader.onload = (e) => {
                imagePreview.value = e.target.result;
            };
            reader.readAsDataURL(file);
        };

        // Submit form
        const submitForm = async () => {
            try {
                loading.value = true;
                errors.value = {};

                // Validation
                if (!projectForm.value.title?.trim()) {
                    showNotification("Please enter project title", "error");
                    loading.value = false;
                    return;
                }

                // if (!projectForm.value.developer_id) {
                //     showNotification("Please select a developer", "error");
                //     loading.value = false;
                //     return;
                // }

                if (!projectForm.value.area_id) {
                    showNotification("Please select a location", "error");
                    loading.value = false;
                    return;
                }

                // if (!projectForm.value.status) {
                //     showNotification("Please select a status", "error");
                //     loading.value = false;
                //     return;
                // }

                // // Image validation (required for create, optional for edit)
                // if (!isEditMode.value && !selectedImage.value) {
                //     showNotification("Please upload a project image", "error");
                //     loading.value = false;
                //     return;
                // }

                // Prepare form data
                const formData = new FormData();
                
                // Add form fields
                const appendIfExists = (key, value) => {
                    if (value !== null && value !== undefined && value !== '') {
                        formData.append(key, value);
                    }
                };

                appendIfExists('title', projectForm.value.title);
                appendIfExists('developer_id', projectForm.value.developer_id);
                appendIfExists('area_id', projectForm.value.area_id);
                appendIfExists('status', projectForm.value.status);
                appendIfExists('about', projectForm.value.about);
                
                // Handle features array
                if (projectForm.value.features && projectForm.value.features.length > 0) {
                    projectForm.value.features.forEach(featureId => {
                        formData.append('features[]', featureId);
                    });
                }
                
                // Add single image
                if (selectedImage.value) {
                    formData.append('main_image', selectedImage.value);
                } else if (isEditMode.value && !selectedImage.value) {
                    formData.append('keep_current_image', 'true');
                }  
                floorPlanImages.value.forEach((image, index) => {
                    formData.append(`floor_plan_images[${index}]`, image.file);
                });

                if (floorPlanImagesToDelete.value.length > 0) {
                    floorPlanImagesToDelete.value.forEach((imageId, index) => {
                        formData.append(`delete_floor_plan_images[${index}]`, imageId);
                    });
                }

                const token = localStorage.getItem('token');
                let response;
                
                if (isEditMode.value) {
                    formData.append('_method', 'PUT');
                    response = await fetch(`/api/listings/projects/${projectId.value}`, {
                        method: 'POST',
                        headers: {
                            'Authorization': 'Bearer ' + token
                        },
                        body: formData
                    });
                } else {
                    response = await fetch('/api/listings/projects', {
                        method: 'POST',
                        headers: {
                            'Authorization': 'Bearer ' + token
                        },
                        body: formData
                    });
                }

                if (!response.ok) {
                    let errorData;
                    try {
                        errorData = await response.json();
                        console.error('❌ Error response:', errorData);
                    } catch (e) {
                        const errorText = await response.text();
                        console.error('❌ Error text:', errorText);
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    
                    if (errorData.errors) {
                        errors.value = errorData.errors;
                        throw new Error('Validation error');
                    } else {
                        throw new Error(errorData.message || 'Failed to save project');
                    }
                }

                const result = await response.json();
                console.log('✅ Success response:', result);

                const successMessage = isEditMode.value ? "Project updated successfully!" : "Project created successfully!";
                showNotification(successMessage, "success");

                setTimeout(() => {
                    router.push('/projects');
                }, 1000);

            } catch (error) {
                console.error("❌ Error saving project:", error);
                
                if (error.message === 'Validation error') {
                    showNotification("Please check the form for errors.", "error");
                } else {
                    showNotification(error.message || "Failed to save project.", "error");
                }
            } finally {
                loading.value = false;
            }
        };

        // Initialize component
        onMounted(() => {
            console.log('🚀 ProjectForm component mounted');
            loadDevelopers();
            loadAreas();
            loadFeatures();
            if (isEditMode.value && projectId.value) {
                fetchProject();
            }
        });

        return {
            isEditMode,
            loading,
            projectForm,
            errors,
            developers,
            developersLoading,
            areas,
            areasLoading,
            features,
            featuresLoading,
            filteredFeatures,
            statusOptions,
            imagePreview,
            currentImage,
            submitForm,
            handleImageUpload,
            handleFeatureSearch,
            loadDevelopers,
            loadAreas,
            loadFeatures,
            existingFloorPlanImages,
            floorPlanImagesToDelete,
            floorPlanImagesInput,
            handleFloorPlanImages,
            handleFloorPlanDrop,
            removeFloorPlanImage,
            markFloorPlanImageForDeletion,
            floorPlanImages 
        };
    }
};
</script>

<style scoped>
/* Form Styling Enhancements */
.card {
    border: 1px solid #e0e0e0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    border-radius: 10px;
    transition: box-shadow 0.3s ease;
}

.card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.card-header {
    border-bottom: 1px solid #e0e0e0;
    padding: 1.25rem 1.5rem;
}

.card-header.bg-light {
    background-color: #f8f9fa !important;
    border-bottom: 2px solid #e9ecef;
}

.card-title {
    font-weight: 600;
    color: #2c3e50;
}

.form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
}

.form-label .fas {
    width: 20px;
    text-align: center;
}

.form-control {
    border: 1px solid #ced4da;
    border-radius: 8px;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
}

.form-control-lg {
    font-size: 1.125rem;
    padding: 1rem 1.25rem;
}

.form-text {
    font-size: 0.875rem;
    margin-top: 0.25rem;
}

/* File Upload Area */
.file-upload-area {
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background-color: #f8f9fa;
    position: relative;
    min-height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.file-upload-area:hover {
    border-color: #0d6efd;
    background-color: #f0f8ff;
}

.file-upload-area.has-preview {
    border-style: solid;
    border-color: #dee2e6;
    padding: 0;
    overflow: hidden;
}

.upload-placeholder {
    padding: 1rem;
}

.upload-icon {
    color: #adb5bd;
}

.upload-text {
    color: #495057;
}

.upload-hint {
    font-size: 0.875rem;
}

.image-preview-container {
    position: relative;
    width: 100%;
    height: 200px;
}

.preview-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 6px;
}

.preview-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
    border-radius: 6px;
}

.image-preview-container:hover .preview-overlay {
    opacity: 1;
}

/* Buttons */
.btn {
    border-radius: 8px;
    font-weight: 500;
    padding: 0.75rem 1.5rem;
    transition: all 0.3s ease;
}

.btn-lg {
    padding: 1rem 1.5rem;
    font-size: 1.125rem;
}

.btn-primary {
    background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
    border: none;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #0a58ca 0%, #084298 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.25);
}

.btn-primary:disabled {
    opacity: 0.65;
    transform: none;
    box-shadow: none;
}

.btn-outline-secondary {
    border: 2px solid #6c757d;
}

.btn-outline-secondary:hover {
    background-color: #6c757d;
    color: white;
    border-color: #6c757d;
}

/* Badge */
.badge {
    font-size: 0.75rem;
    padding: 0.35em 0.65em;
    font-weight: 500;
}

/* Spinner Animation */
.fa-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* V-Select Custom Styling */
:deep(.vs__dropdown-toggle) {
    border: 2px solid #e0e0e0 !important;
    border-radius: 8px !important;
    padding: 0.75rem 1rem !important;
    min-height: 48px !important;
    background-color: white !important;
    transition: all 0.3s ease !important;
    height:auto !important;
}

:deep(.vs__dropdown-toggle:hover) {
    border-color: #86b7fe !important;
}

:deep(.vs--open .vs__dropdown-toggle) {
    border-color: #86b7fe !important;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15) !important;
}

/*:deep(.vs__selected) {*/
/*    margin: 4px !important;*/
/*    background-color: #e3f2fd !important;*/
/*    border: 1px solid #bbdefb !important;*/
/*    border-radius: 6px !important;*/
/*    padding: 0.5rem 0.75rem !important;*/
/*    color: #1565c0 !important;*/
/*}*/

:deep(.vs__search) {
    margin: 0 !important;
    padding: 0 !important;
    font-size: 1rem !important;
}

:deep(.vs__dropdown-menu) {
    border: 1px solid #e0e0e0 !important;
    border-radius: 8px !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
    margin-top: 4px !important;
}

:deep(.vs__dropdown-option) {
    padding: 0.75rem 1rem !important;
    font-size: 1rem !important;
    transition: all 0.2s ease !important;
}

:deep(.vs__dropdown-option:hover) {
    background-color: #f8f9fa !important;
}

:deep(.vs__dropdown-option--highlight) {
    background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%) !important;
    color: white !important;
}

:deep(.vs__clear) {
    fill: #6c757d !important;
}

:deep(.vs__open-indicator) {
    fill: #6c757d !important;
}

/* Invalid state styling */
:deep(.is-invalid .vs__dropdown-toggle) {
    border-color: #dc3545 !important;
}

:deep(.is-invalid .vs__dropdown-toggle:hover) {
    border-color: #dc3545 !important;
}

:deep(.is-invalid .vs--open .vs__dropdown-toggle) {
    border-color: #dc3545 !important;
    box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.15) !important;
}

/* Responsive Design */
@media (max-width: 768px) {
    .card-body {
        padding: 1rem;
    }
    
    .file-upload-area {
        min-height: 150px;
        padding: 1rem;
    }
    
    .image-preview-container {
        height: 150px;
    }
    
    .btn-lg {
        padding: 0.75rem 1rem;
        font-size: 1rem;
    }
}

/* Icons Container */
.avatar-container, .feature-icon-container {
    flex-shrink: 0;
}

.avatar-placeholder, .feature-placeholder {
    font-size: 0.875rem;
}

/* Form Sections Spacing */
.mb-4:last-child {
    margin-bottom: 0 !important;
}

/* Required Field Indicator */
.text-danger {
    color: #dc3545 !important;
}

/* Loading States */
.text-muted {
    color: #6c757d !important;
}

/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
:deep(.vs__dropdown-option:hover small) {
    color: white !important;
}

.file-upload-area {
    min-height: 200px;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background-color: #f8f9fa;
}

.file-upload-area:hover {
    border-color: #0d6efd;
    background-color: #f0f8ff;
}

.file-upload-area.has-preview {
    padding: 1rem;
}

.images-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 10px;
    width:100%;
}

.image-thumbnail {
    position: relative;
    width: 120px;
    height: 120px;
    border-radius: 6px;
    overflow: hidden;
    border: 2px solid #e9ecef;
}

.thumbnail-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.thumbnail-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.image-thumbnail:hover .thumbnail-overlay {
    opacity: 1;
}

.thumbnail-badge {
    position: absolute;
    top: 5px;
    left: 5px;
    background: #0d6efd;
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
}

.add-more-images {
    width: 120px;
    height: 120px;
    border: 2px dashed #0d6efd;
    border-radius: 6px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background-color: rgba(13, 110, 253, 0.05);
}

.add-more-images:hover {
    background-color: rgba(13, 110, 253, 0.1);
    border-color: #0a58ca;
}

.existing-images-section {
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 1px solid #e9ecef;
}

.existing-images-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
}

.existing-image-item {
    position: relative;
    width: 150px;
    height: 150px;
    border-radius: 8px;
    overflow: hidden;
    border: 2px solid #e9ecef;
}

.existing-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.existing-image-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.existing-image-item:hover .existing-image-overlay {
    opacity: 1;
}

.alert-warning {
    font-size: 0.875rem;
    padding: 0.5rem 1rem;
}

</style>