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
                                    <h6 class="mb-0 text-dark card-title">
                                        <i class="fas fa-building me-2"></i>
                                        Project Information
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <!--<div class="col-md-12 mb-3">-->
                                        <!--    <label class="form-label fw-semibold">-->
                                        <!--        <i class="fas fa-heading me-1 text-primary"></i>-->
                                        <!--        Project Title-->
                                        <!--        <span class="text-danger">*</span>-->
                                        <!--    </label>-->
                                        <!--    <input type="text" class="form-control form-control-lg" -->
                                        <!--           v-model="projectForm.title" -->
                                        <!--           :class="{'is-invalid': errors.title}"-->
                                        <!--           placeholder="Enter project title"-->
                                        <!--           autofocus>-->
                                        <!--    <div class="invalid-feedback" v-if="errors.title">-->
                                        <!--        {{ errors.title[0] }}-->
                                        <!--    </div>-->
                                        <!--    <div class="form-text text-muted mt-1">-->
                                        <!--        <i class="fas fa-info-circle me-1"></i>-->
                                        <!--        Enter a descriptive title for the project-->
                                        <!--    </div>-->
                                        <!--</div>-->

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
                                                      @search="loadAreas"  @update:modelValue="updateTitleFromArea">
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
                                            
                                            <div v-if="selectedAreaName" class="mt-2 text-success">
                                                <i class="fas fa-check-circle me-1"></i>
                                                Project will be titled: <strong>{{ selectedAreaName }}</strong>
                                            </div>
                                            <div v-else class="form-text text-muted mt-1">
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
                                    <h6 class="mb-0 text-dark card-title">
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
                            <!--<div class="card mb-4">-->
                            <!--    <div class="card-header bg-light d-flex justify-content-between align-items-center">-->
                            <!--        <h6 class="mb-0 text-dark card-title">-->
                            <!--            <i class="fas fa-layer-group me-2"></i>-->
                            <!--            Floor Plan Images-->
                            <!--            <span v-if="totalFloorPlanImages > 0" class="badge bg-primary ms-2">-->
                            <!--                {{ totalFloorPlanImages }}-->
                            <!--            </span>-->
                            <!--        </h6>-->
                            <!--    </div>-->
                            <!--    <div class="card-body">-->
                                    <!-- Section with existing and new images together -->
                            <!--        <div class="all-images-section">-->
                                        <!-- Images Container -->
                            <!--            <div class="images-container" v-if="totalFloorPlanImages > 0">-->
                                            <!-- Images Grid -->
                            <!--                <div class="images-grid-combined">-->
                                                <!-- Existing Images -->
                            <!--                <div v-for="image in existingFloorPlanImages" -->
                            <!--                     :key="'existing-' + image.id" -->
                            <!--                     class="image-card combined"-->
                            <!--                     :class="{'marked-for-delete': floorPlanImagesToDelete.includes(image.id)}">-->
                            <!--                    <div class="image-wrapper">-->
                            <!--                        <img :src="image.image_url" -->
                            <!--                             alt="Floor Plan" -->
                            <!--                             class="preview-img">-->
                                                    
                            <!--                        <div class="image-overlay">-->
                            <!--                            <div class="overlay-buttons">-->
                            <!--                                <button type="button" -->
                            <!--                                        class="btn btn-sm btn-danger"-->
                            <!--                                        @click.stop="toggleDeleteImage(image.id)"-->
                            <!--                                        :title="floorPlanImagesToDelete.includes(image.id) ? 'Restore image' : 'Delete image'">-->
                            <!--                                    <i :class="floorPlanImagesToDelete.includes(image.id) ? 'fas fa-undo' : 'fas fa-trash'"></i>-->
                            <!--                                </button>-->
                                                            
                                                           
                            <!--                            </div>-->
                            <!--                        </div>-->
                                                    
                            <!--                        <span v-if="floorPlanImagesToDelete.includes(image.id)" -->
                            <!--                              class="image-status delete">-->
                            <!--                            <i class="fas fa-exclamation-triangle me-1"></i>-->
                            <!--                            Will delete-->
                            <!--                        </span>-->
                                                    
                                                    <!--<span class="image-badge existing">-->
                                                    <!--    <i class="fas fa-history me-1"></i>-->
                                                    <!--    Existing-->
                                                    <!--</span>-->
                            <!--                    </div>-->
                                                
                            <!--                    <div class="image-info mt-2">-->
                            <!--                        <div class="floor-plan-name mb-2">-->
                            <!--                            <div class="input-group input-group-sm">-->
                            <!--                                <input type="text" -->
                            <!--                                       class="form-control" -->
                            <!--                                       v-model="image.name"-->
                            <!--                                       placeholder="Floor plan name..."-->
                            <!--                                       @change="updateFloorPlanName(image)"-->
                            <!--                                       @blur="updateFloorPlanName(image)">-->
                                                            
                                                            
                            <!--                            </div>-->
                                                        
                            <!--                            <small v-if="image.nameError" class="text-danger">-->
                            <!--                                {{ image.nameError }}-->
                            <!--                            </small>-->
                            <!--                        </div>-->
                                                    
                            <!--                        <small class="text-muted d-block">-->
                            <!--                            <i class="fas fa-calendar me-1"></i>-->
                            <!--                            {{ formatDate(image.created_at) }}-->
                            <!--                        </small>-->
                                                    
                                                    <!-- حجم الصورة (إذا كان متوفراً) -->
                            <!--                        <small v-if="image.file_size" class="text-muted d-block">-->
                            <!--                            <i class="fas fa-file me-1"></i>-->
                            <!--                            {{ formatFileSize(image.file_size) }}-->
                            <!--                        </small>-->
                            <!--                    </div>-->
                            <!--                </div>-->
                            
                                                <!-- New Images -->
                            <!--                    <div v-for="(image, index) in floorPlanImages" -->
                            <!--                         :key="'new-' + index" -->
                            <!--                         class="image-card combined new">-->
                            <!--                        <div class="image-wrapper">-->
                            <!--                            <img :src="image.preview" -->
                            <!--                                 alt="Floor Plan" -->
                            <!--                                 class="preview-img">-->
                            <!--                            <div class="image-overlay">-->
                            <!--                                <div class="overlay-buttons">-->
                            <!--                                    <button type="button" -->
                            <!--                                            class="btn btn-sm btn-danger"-->
                            <!--                                            @click.stop="removeFloorPlanImage(index)">-->
                            <!--                                        <i class="fas fa-trash"></i>-->
                            <!--                                    </button>-->
                            <!--                                </div>-->
                            <!--                            </div>-->
                            <!--                            <span class="image-badge new">-->
                            <!--                                <i class="fas fa-plus me-1"></i>-->
                            <!--                                New-->
                            <!--                            </span>-->
                            <!--                            <span class="image-number">{{ totalExistingImages + index + 1 }}</span>-->
                            <!--                        </div>-->
                            <!--                        <div class="image-info mt-2">-->
                            <!--                            <div class="floor-plan-name mb-2">-->
                            <!--                                <input type="text" -->
                            <!--                                       class="form-control form-control-sm" -->
                            <!--                                       v-model="image.name"-->
                            <!--                                       placeholder="Enter floor plan name..."-->
                            <!--                                       @change="validateFloorPlanName(image, index)"-->
                            <!--                                       @blur="validateFloorPlanName(image, index)">-->
                            <!--                                <small v-if="image.nameError" class="text-danger">-->
                            <!--                                    {{ image.nameError }}-->
                            <!--                                </small>-->
                            <!--                            </div>-->
                            <!--                            <small class="text-muted d-block">-->
                            <!--                                <i class="fas fa-file me-1"></i>-->
                            <!--                                {{ formatFileSize(image.file.size) }}-->
                            <!--                            </small>-->
                            <!--                            <small class="text-success d-block mt-1">-->
                            <!--                                <i class="fas fa-check-circle me-1"></i>-->
                            <!--                                Ready to upload-->
                            <!--                            </small>-->
                            <!--                        </div>-->
                            <!--                    </div>-->
                            
                                                <!-- Add More Button -->
                            <!--                    <div class="add-more-card" @click="$refs.floorPlanImagesInput.click()">-->
                            <!--                        <div class="add-more-content">-->
                            <!--                            <i class="fas fa-plus-circle fa-3x text-primary mb-3"></i>-->
                            <!--                            <h6 class="text-primary mb-2">Add More Images</h6>-->
                            <!--                            <p class="text-muted small mb-0">Click or drag & drop</p>-->
                            <!--                        </div>-->
                            <!--                    </div>-->
                            <!--                </div>-->
                            <!--            </div>-->
                            
                                        <!-- Empty State -->
                            <!--            <div v-else class="empty-state text-center py-5">-->
                            <!--                <div class="empty-icon mb-4">-->
                            <!--                    <i class="fas fa-image fa-4x text-muted"></i>-->
                            <!--                </div>-->
                            <!--                <h5 class="mb-3 text-muted">No Floor Plan Images</h5>-->
                            <!--                <p class="text-muted mb-4">Add floor plan images to showcase different layouts</p>-->
                            <!--                <button type="button" -->
                            <!--                        class="btn btn-primary"-->
                            <!--                        @click="$refs.floorPlanImagesInput.click()">-->
                            <!--                    <i class="fas fa-cloud-upload-alt me-2"></i>-->
                            <!--                    Upload Images-->
                            <!--                </button>-->
                            <!--            </div>-->
                            
                                        <!-- Hidden File Input -->
                            <!--            <input ref="floorPlanImagesInput"-->
                            <!--                   type="file" -->
                            <!--                   class="d-none" -->
                            <!--                   @change="handleFloorPlanImages" -->
                            <!--                   multiple-->
                            <!--                   accept="image/*">-->
                            
                                        <!-- Drop Zone Overlay -->
                            <!--            <div class="drop-zone-overlay"-->
                            <!--                 :class="{'active': isDragOver}"-->
                            <!--                 @dragover.prevent="handleDragOver"-->
                            <!--                 @dragleave.prevent="handleDragLeave"-->
                            <!--                 @drop.prevent="handleFloorPlanDrop">-->
                            <!--                <div class="drop-zone-content" v-if="isDragOver">-->
                            <!--                    <i class="fas fa-cloud-upload-alt fa-4x text-primary mb-3"></i>-->
                            <!--                    <h5 class="text-primary mb-2">Drop to upload</h5>-->
                            <!--                    <p class="text-muted">Release to add images</p>-->
                            <!--                </div>-->
                            <!--            </div>-->
                            <!--        </div>-->
                            
                                    <!-- Deletion Warning -->
                            <!--        <div v-if="floorPlanImagesToDelete.length > 0" class="mt-4">-->
                            <!--            <div class="alert alert-warning">-->
                            <!--                <div class="d-flex align-items-center">-->
                            <!--                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>-->
                            <!--                    <div>-->
                            <!--                        <h6 class="alert-heading mb-1">-->
                            <!--                            {{ floorPlanImagesToDelete.length }} image(s) marked for deletion-->
                            <!--                        </h6>-->
                            <!--                        <p class="mb-2">These images will be permanently removed when you save changes.</p>-->
                            <!--                        <button type="button" -->
                            <!--                                class="btn btn-sm btn-outline-warning"-->
                            <!--                                @click="clearAllMarkedForDeletion">-->
                            <!--                            <i class="fas fa-times me-1"></i>-->
                            <!--                            Clear all deletion marks-->
                            <!--                        </button>-->
                            <!--                    </div>-->
                            <!--                </div>-->
                            <!--            </div>-->
                            <!--        </div>-->
                            <!--    </div>-->
                            <!--</div>-->
                        </div>

                        <!-- Right Column -->
                        <div class="col-md-4">
                            <!-- 🏗️ Developer Information Section -->
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0 text-dark card-title">
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
                                    <h6 class="mb-0 text-dark card-title">
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
                                    <h6 class="mb-0 text-dark card-title">
                                        <i class="fas fa-image me-2"></i>
                                        Project Main Image
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
                              <!-- 🖼️ Gallery Images Section (max 3, reorderable) -->
                            <div class="card mb-4">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 text-dark card-title">
                                    <i class="fas fa-images me-2"></i>
                                    Gallery Images
                                    <span class="badge bg-primary ms-2">
                                        {{ galleryCount }} / 3
                                    </span>
                                </h6>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    @click="$refs.galleryInput.click()"
                                    :disabled="galleryAtMax"
                                    :title="galleryAtMax ? 'Maximum of 3 images reached' : 'Add Images'"
                                >
                                    <i class="fas fa-plus"></i> Add Images
                                </button>
                            </div>
                            <div class="card-body">
                                <!-- Unified ordered gallery (drag & drop to reorder) -->
                                <draggable
                                    v-if="orderedGalleryItems.length > 0"
                                    v-model="orderedGalleryItemsModel"
                                    item-key="_key"
                                    tag="div"
                                    class="gallery-grid"
                                    ghost-class="gallery-ghost"
                                    chosen-class="gallery-chosen"
                                    drag-class="gallery-drag"
                                    :animation="180"
                                    filter=".no-drag"
                                    :prevent-on-filter="true"
                                >
                                    <template #item="{ element: item, index: idx }">
                                        <div
                                            class="gallery-item"
                                            :class="{ 'new': item._kind === 'new' }"
                                        >
                                            <div class="gallery-image-wrapper">
                                                <img
                                                    :src="item._kind === 'existing' ? item.image_url : item.preview"
                                                    :alt="'Gallery ' + (idx + 1)"
                                                    class="gallery-img"
                                                />

                                                <span class="order-badge">#{{ idx + 1 }}</span>
                                                <span v-if="item._kind === 'new'" class="new-badge">New</span>

                                                <span class="drag-hint" title="Drag to reorder">
                                                    <i class="fas fa-up-down-left-right"></i>
                                                </span>

                                                <div class="gallery-overlay">
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-danger no-drag"
                                                        @click="item._kind === 'existing' ? toggleDeleteGalleryImage(item.id) : removeGalleryImage(item._idx)"
                                                        title="Remove"
                                                    >
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </draggable>

                                <!-- Empty State -->
                                <div v-else class="empty-gallery text-center py-4">
                                    <i class="fas fa-images fa-3x text-muted mb-3"></i>
                                    <p class="text-muted mb-0">No gallery images added yet</p>
                                    <small class="text-muted">Click "Add Images" to upload (up to 3)</small>
                                </div>

                                <!-- Hidden File Input -->
                                <input ref="galleryInput" type="file" class="d-none" @change="handleGalleryImages"
                                    multiple accept="image/jpeg,image/png,image/jpg,image/gif">
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
import { ref, onMounted, computed, getCurrentInstance ,watch} from "vue";
import { useRoute, useRouter } from "vue-router";
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import draggable from 'vuedraggable';

export default {
    name: 'ProjectForm',
    components: {
        Breadcrumb,
        vSelect,
        draggable
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
        const floorPlanNames = ref({});
        const existingFloorPlanImages = ref([]);
        const floorPlanImagesToDelete = ref([]);
        const floorPlanImagesInput = ref(null);
        const galleryImages = ref([]);
        const existingGalleryImages = ref([]); 
        const galleryImagesToDelete = ref([]);
        const selectedAreaName = ref('');

        // Status options with icons
        const statusOptions = ref([
            { value: 'Under Construction', label: 'Under Construction', icon: 'fas fa-hourglass-start text-warning' },
            { value: 'Ready', label: 'Ready', icon: 'fas fa-check-circle text-success' }
        ]);
        // Hard limit on the gallery (existing kept + new added)
        const GALLERY_MAX = 3;

        /** Count of gallery slots currently occupied (kept existing + new pending). */
        const galleryCount = computed(() => {
            const keptExisting = existingGalleryImages.value.filter(
                (img) => !galleryImagesToDelete.value.includes(img.id)
            ).length;
            return keptExisting + galleryImages.value.length;
        });

        const galleryAtMax = computed(() => galleryCount.value >= GALLERY_MAX);

        const nextSortOrder = () => {
            const maxExisting = existingGalleryImages.value.reduce(
                (m, x) => Math.max(m, Number(x.sort_order || 0)), 0
            );
            const maxNew = galleryImages.value.reduce(
                (m, x) => Math.max(m, Number(x.sort_order || 0)), 0
            );
            return Math.max(maxExisting, maxNew) + 1;
        };

        const handleGalleryImages = (event) => {
            const files = Array.from(event.target.files);

            if (files.length === 0) return;

            const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            const maxSize = 5 * 1024 * 1024; // 5MB

            const remainingSlots = GALLERY_MAX - galleryCount.value;
            if (remainingSlots <= 0) {
                showNotification(`You can upload up to ${GALLERY_MAX} images only`, 'warning');
                event.target.value = '';
                return;
            }

            const toAccept = files.slice(0, remainingSlots);
            if (files.length > toAccept.length) {
                showNotification(
                    `Only the first ${remainingSlots} image(s) will be added (limit is ${GALLERY_MAX})`,
                    'warning'
                );
            }

            toAccept.forEach((file) => {
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
                    galleryImages.value.push({
                        file: file,
                        preview: e.target.result,
                        sort_order: nextSortOrder(),
                    });
                };
                reader.readAsDataURL(file);
            });

            event.target.value = '';
        };

        // Remove new gallery image
        const removeGalleryImage = (index) => {
            galleryImages.value.splice(index, 1);
            // Resync orders so they stay 1..N for tidy submission
            resyncGalleryOrders();
        };

        // Toggle delete existing gallery image
        const toggleDeleteGalleryImage = (imageId) => {
            const index = galleryImagesToDelete.value.indexOf(imageId);
            if (index === -1) {
                galleryImagesToDelete.value.push(imageId);
                showNotification('Image marked for deletion', 'warning');
            } else {
                galleryImagesToDelete.value.splice(index, 1);
                showNotification('Image restored', 'success');
            }
            resyncGalleryOrders();
        };

        /** Unified ordered list of gallery items (kept existing + new), sorted by sort_order. */
        const orderedGalleryItems = computed(() => {
            const items = [];
            existingGalleryImages.value.forEach((img) => {
                if (galleryImagesToDelete.value.includes(img.id)) return;
                items.push({
                    _kind: 'existing',
                    _key: `e-${img.id}`,
                    id: img.id,
                    image_url: img.image_url,
                    name: img.name,
                    sort_order: Number(img.sort_order || 0),
                });
            });
            galleryImages.value.forEach((img, idx) => {
                items.push({
                    _kind: 'new',
                    _key: `n-${idx}`,
                    _idx: idx,
                    preview: img.preview,
                    sort_order: Number(img.sort_order || 0),
                });
            });
            items.sort((a, b) => a.sort_order - b.sort_order);
            return items;
        });

        /** Swap an item's sort_order with its neighbour in either direction. */
        const moveGalleryItem = (item, direction) => {
            const ordered = orderedGalleryItems.value;
            const pos = ordered.findIndex((i) => i._key === item._key);
            const swapPos = direction === 'up' ? pos - 1 : pos + 1;
            if (pos < 0 || swapPos < 0 || swapPos >= ordered.length) return;

            const a = ordered[pos];
            const b = ordered[swapPos];
            const aOrder = a.sort_order;
            const bOrder = b.sort_order;
            applySortOrder(a, bOrder);
            applySortOrder(b, aOrder);
        };

        /** Write sort_order back to whichever source array the item belongs to. */
        const applySortOrder = (item, newOrder) => {
            if (item._kind === 'existing') {
                const src = existingGalleryImages.value.find((x) => x.id === item.id);
                if (src) src.sort_order = newOrder;
            } else {
                const src = galleryImages.value[item._idx];
                if (src) src.sort_order = newOrder;
            }
        };

        /** Compact sort_order values to 1..N (after deletes / drops) to keep things tidy. */
        const resyncGalleryOrders = () => {
            const ordered = orderedGalleryItems.value;
            ordered.forEach((item, idx) => applySortOrder(item, idx + 1));
        };

        /** Writable model that <draggable> binds to. On drop, vuedraggable hands us
         *  the freshly reordered array — we write a new sort_order onto each underlying
         *  source item, which then re-derives `orderedGalleryItems` in the new order. */
        const orderedGalleryItemsModel = computed({
            get: () => orderedGalleryItems.value,
            set: (newOrder) => {
                newOrder.forEach((item, idx) => applySortOrder(item, idx + 1));
            },
        });

        // Project Form Data (removed price and sqft fields)
        const projectForm = ref({
            title: "",
            developer_id: null,
            area_id: null,
            status: null,
            about: "",
            features: [],
            floor_plan_images: [],
            gallery_images: []
        });
     const updateTitleFromArea = (selectedId) => {
            console.log('🟢 Selected ID:', selectedId);
            console.log('📋 Areas loaded:', areas.value);
            
            if (!selectedId) {
                projectForm.value.title = '';
                selectedAreaName.value = '';
                return;
            }
            
            if (!areas.value || areas.value.length === 0) {
                console.log('⏳ Areas not loaded yet, will try again after loading');
                return;
            }
            
            const fullArea = areas.value.find(area => area.id === selectedId);
            console.log('🔍 Found area:', fullArea);
            
            if (fullArea) {
                projectForm.value.title = fullArea.name;
                selectedAreaName.value = fullArea.name;
                console.log('✅ Title updated to:', fullArea.name);
            } else {
                console.log('⚠️ No area found with ID:', selectedId);
            }
        };
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
        
                const fileName = file.name.replace(/\.[^/.]+$/, ""); 
                const cleanFileName = fileName.replace(/[^\w\s-]/g, " ").trim(); 
                
                const reader = new FileReader();
                reader.onload = (e) => {
                    floorPlanImages.value.push({
                        file: file,
                        preview: e.target.result,
                        name: cleanFileName || `Floor Plan ${floorPlanImages.value.length + 1}`, 
                        originalName: cleanFileName, 
                        nameError: '', 
                        defaultName: cleanFileName || `Floor Plan ${floorPlanImages.value.length + 1}`
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
                // استخراج اسم الملف بدون الامتداد
                const fileName = file.name.replace(/\.[^/.]+$/, "");
                const cleanFileName = fileName.replace(/[^\w\s-]/g, " ").trim(); 
                
                const reader = new FileReader();
                reader.onload = (e) => {
                    floorPlanImages.value.push({
                        file: file,
                        preview: e.target.result,
                        name: cleanFileName || `Floor Plan ${floorPlanImages.value.length + 1}`, 
                        originalName: cleanFileName, 
                        nameError: '', 
                        defaultName: cleanFileName || `Floor Plan ${floorPlanImages.value.length + 1}`
                    });
                };
                reader.readAsDataURL(file);
            });
        };
        const validateFloorPlanName = (image, index) => {
                if (!image.name || image.name.trim() === '') {
                    image.nameError = 'Floor plan name is required';
                    return false;
                }
                
                if (image.name.length > 100) {
                    image.nameError = 'Name must be less than 100 characters';
                    return false;
                }
                
                const allNames = [
                    ...existingFloorPlanImages.value.map(img => img.name).filter(Boolean),
                    ...floorPlanImages.value.map(img => img.name).filter(Boolean)
                ];
                
                const duplicateCount = allNames.filter(name => 
                    name.toLowerCase() === image.name.toLowerCase()
                ).length;
                
                if (duplicateCount > 1) {
                    image.nameError = 'Floor plan name must be unique';
                    return false;
                }
                
                image.nameError = '';
                return true;
            };
            const toggleDeleteImage = (imageId) => {
                const index = floorPlanImagesToDelete.value.indexOf(imageId);
                
                if (index === -1) {
                    floorPlanImagesToDelete.value.push(imageId);
                    showNotification('Image marked for deletion', 'warning');
                } else {
                    floorPlanImagesToDelete.value.splice(index, 1);
                    showNotification('Image restored', 'success');
                }
            };
            const validateAllFloorPlanNames = () => {
                let isValid = true;
                
                floorPlanImages.value.forEach((image, index) => {
                    if (!validateFloorPlanName(image, index)) {
                        isValid = false;
                    }
                });
                
                existingFloorPlanImages.value.forEach((image) => {
                    if (!image.name || image.name.trim() === '') {
                        showNotification(`Please enter name for existing floor plan image`, 'error');
                        isValid = false;
                    }
                });
                
                return isValid;
            };
            
            const updateFloorPlanName = async (image) => {
                if (!image.name || image.name.trim() === '') {
                    image.nameError = 'Floor plan name is required';
                    return;
                }
                
                if (image.name.length > 100) {
                    image.nameError = 'Name must be less than 100 characters';
                    return;
                }
                
                try {
                    const token = localStorage.getItem('token');
                    const response = await fetch(`/api/listings/projects/floor-plan-images/${image.id}/name`, {
                        method: 'PUT',
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            name: image.name.trim()
                        })
                    });
                    
                    if (response.ok) {
                        image.nameError = '';
                        showNotification('Floor plan name updated', 'success');
                    } else {
                        const errorData = await response.json();
                        image.nameError = errorData.message || 'Failed to update name';
                        showNotification('Failed to update floor plan name', 'error');
                    }
                } catch (error) {
                    console.error('Error updating floor plan name:', error);
                    image.nameError = 'Failed to update name';
                    showNotification('Failed to update floor plan name', 'error');
                }
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
                        if (projectData.images) {
                            // Keep gallery (non-main) images, and ensure each has a sort_order
                            // so the unified ordered list works even on legacy rows.
                            existingGalleryImages.value = projectData.images
                                .filter(img => !img.is_main)
                                .map((img, idx) => ({
                                    ...img,
                                    sort_order: Number(img.sort_order ?? idx + 1),
                                }));
                        }
                    
                        // Set existing floor plan images
                        // if (projectData.floor_plan_images) {
                        //     existingFloorPlanImages.value = projectData.floor_plan_images;
                        // }
                         if (projectData.floor_plan_images) {
                            existingFloorPlanImages.value = projectData.floor_plan_images.map(img => ({
                                ...img,
                                name: img.name || '', 
                                nameError: '' 
                            }));
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
                        if (projectData.area && projectData.area.name) {
                            selectedAreaName.value = projectData.area.name;
                            projectForm.value.title = projectData.area.name;
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
                
                   if (!projectForm.value.area_id) {
                    showNotification("Please select a location", "error");
                    loading.value = false;
                    return;
                }

                if (!projectForm.value.title || projectForm.value.title.trim() === '') {
                    showNotification("Please select a valid location title", "error");
                    loading.value = false;
                    return;
                }
                // if (!projectForm.value.title?.trim()) {
                //     showNotification("Please enter project title", "error");
                //     loading.value = false;
                //     return;
                // }

                // if (!validateAllFloorPlanNames()) {
                //             showNotification("Please enter valid names for all floor plans", "error");
                //             loading.value = false;
                //             return;
                // }
                // if (!projectForm.value.developer_id) {
                //     showNotification("Please select a developer", "error");
                //     loading.value = false;
                //     return;
                // }

               

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
                // Send gallery in the user's chosen order. For each ordered slot:
                //  - existing images get an image_orders[id] entry so the backend can update sort_order
                //  - new images are appended in display order, with images_order[] holding the slot index
                resyncGalleryOrders();
                const ordered = orderedGalleryItems.value;
                let newImgIdx = 0;
                ordered.forEach((item, idx) => {
                    const sortOrder = idx + 1;
                    if (item._kind === 'existing') {
                        formData.append(`image_orders[${item.id}]`, sortOrder);
                    } else {
                        const src = galleryImages.value[item._idx];
                        if (src && src.file) {
                            formData.append('images[]', src.file);
                            formData.append(`images_order[${newImgIdx}]`, sortOrder);
                            newImgIdx += 1;
                        }
                    }
                });

                // Add gallery images to delete (if any)
                if (galleryImagesToDelete.value.length > 0) {
                    galleryImagesToDelete.value.forEach((imageId, index) => {
                        formData.append(`delete_images[${index}]`, imageId);
                    });
                }
               
                if (floorPlanImagesToDelete.value.length > 0) {
                    floorPlanImagesToDelete.value.forEach((imageId, index) => {
                        formData.append(`delete_floor_plan_images[${index}]`, imageId);
                    });
                }
                 floorPlanImages.value.forEach((image, index) => {
                    formData.append(`floor_plan_images[${index}][file]`, image.file);
                    formData.append(`floor_plan_images[${index}][name]`, 
                        image.name || `Floor Plan ${totalExistingImages.value + index + 1}`);
                });
        
                existingFloorPlanImages.value.forEach((image) => {
                    if (image.name && image.name.trim() !== '') {
                        formData.append(`floor_plan_names[${image.id}]`, image.name);
                    }
                });

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
   const totalExistingImages = computed(() => {
            return existingFloorPlanImages.value ? existingFloorPlanImages.value.length : 0;
        });

        const totalFloorPlanImages = computed(() => {
            const existing = totalExistingImages.value;
            const newImages = floorPlanImages.value ? floorPlanImages.value.length : 0;
            const markedForDelete = floorPlanImagesToDelete.value ? floorPlanImagesToDelete.value.length : 0;
            return existing + newImages - markedForDelete;
        });

        const previewImage = (imageUrl, identifier) => {
            const title = typeof identifier === 'number' 
                ? `New Image ${identifier + 1}` 
                : 'Existing Image';
            
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: title,
                    html: `<img src="${imageUrl}" style="max-width: 100%; max-height: 70vh; border-radius: 8px;" />`,
                    showCloseButton: true,
                    showConfirmButton: false,
                    width: '80%'
                });
            } else {
                window.open(imageUrl, '_blank');
            }
        };

        const moveImage = (index, direction) => {
            if (!floorPlanImages.value || floorPlanImages.value.length <= 1) return;
            
            const newIndex = direction === 'up' ? index - 1 : index + 1;
            
            if (newIndex >= 0 && newIndex < floorPlanImages.value.length) {
                const temp = floorPlanImages.value[index];
                floorPlanImages.value[index] = floorPlanImages.value[newIndex];
                floorPlanImages.value[newIndex] = temp;
                
                showNotification(`Image moved ${direction}`, 'info');
            }
        };

        const clearAllMarkedForDeletion = () => {
            floorPlanImagesToDelete.value = [];
            showNotification('All deletion marks cleared', 'info');
        };

        const formatFileSize = (bytes) => {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        };

        const formatDate = (dateString) => {
            if (!dateString) return 'Unknown date';
            try {
                return new Date(dateString).toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                });
            } catch {
                return 'Unknown date';
            }
        };
        // Watch for changes in area_id
        watch(() => projectForm.value.area_id, (newAreaId) => {
            console.log('🟢 Area ID changed:', newAreaId);
            
            if (newAreaId) {
                const fullArea = areas.value.find(area => area.id === newAreaId);
                console.log('🔍 Found area:', fullArea);
                
                if (fullArea) {
                    projectForm.value.title = fullArea.name;
                    selectedAreaName.value = fullArea.name;
                    console.log('✅ Title updated to:', fullArea.name);
                }
            } else {
                projectForm.value.title = '';
                selectedAreaName.value = '';
            }
        });
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
            totalExistingImages,
            totalFloorPlanImages,
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
            floorPlanImages ,
             previewImage,
            moveImage,
            clearAllMarkedForDeletion,
            formatFileSize,
            formatDate,
            toggleDeleteImage ,
            updateTitleFromArea ,
            galleryImages,
            existingGalleryImages,
            galleryImagesToDelete,
            handleGalleryImages,
            removeGalleryImage,
            toggleDeleteGalleryImage,
            galleryCount,
            galleryAtMax,
            orderedGalleryItems,
            orderedGalleryItemsModel
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
.all-images-section {
    position: relative;
    min-height: 200px;
}

.images-container {
    margin-bottom: 2rem;
}

.images-grid-combined {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 1.5rem;
    align-items: start;
}

.image-card.combined {
    position: relative;
    transition: all 0.3s ease;
    border-radius: 12px;
    overflow: hidden;
    background: white;
    border: 1px solid #e9ecef;
    padding: 1rem;
}

.image-card.combined:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    border-color: #0d6efd;
}

.image-card.combined.marked-for-delete {
    opacity: 0.6;
    border-color: #dc3545;
    background: linear-gradient(45deg, transparent 90%, #dc354520 10%);
}

.image-card.combined.new {
    border-color: #28a745;
    background: linear-gradient(45deg, transparent 90%, #28a74510 10%);
}

.image-wrapper {
    position: relative;
    width: 100%;
    height: 150px;
    border-radius: 8px;
    overflow: hidden;
    background-color: #f8f9fa;
}

.preview-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.image-card:hover .preview-img {
    transform: scale(1.05);
}

.image-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(to bottom, 
                rgba(0, 0, 0, 0.8) 0%,
                rgba(0, 0, 0, 0.5) 30%,
                transparent 70%);
    display: flex;
    align-items: flex-start;
    justify-content: flex-end;
    opacity: 0;
    transition: opacity 0.3s ease;
    padding: 10px;
}

.image-wrapper:hover .image-overlay {
    opacity: 1;
}

.overlay-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
    justify-content: center;
}

.overlay-buttons .btn {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border-radius: 50%;
}

.image-badge {
    position: absolute;
    top: 8px;
    left: 8px;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    display: flex;
    align-items: center;
}

.image-badge.existing {
    background: rgba(13, 110, 253, 0.9);
    color: white;
}

.image-badge.new {
    background: rgba(40, 167, 69, 0.9);
    color: white;
}

.image-status {
    position: absolute;
    bottom: 8px;
    left: 8px;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    display: flex;
    align-items: center;
}

.image-status.delete {
    background: rgba(220, 53, 69, 0.9);
    color: white;
}

.image-number {
    position: absolute;
    bottom: 8px;
    right: 8px;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.875rem;
}

.image-info {
    text-align: center;
    padding: 0.5rem 0;
}

.image-info small {
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Add More Card */
.add-more-card {
    border: 2px dashed #dee2e6;
    border-radius: 12px;
    background-color: #f8fafc;
    min-height: 220px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.add-more-card:hover {
    border-color: #0d6efd;
    background-color: #f0f8ff;
    transform: translateY(-2px);
}

.add-more-content {
    text-align: center;
    padding: 1rem;
}
.add-more-content h6{
   font-size: 14px !important;
}

.add-more-content i {
    transition: transform 0.3s ease;
}

.add-more-card:hover .add-more-content i {
    transform: scale(1.1);
}

/* Empty State */
.empty-state {
    border: 2px dashed #dee2e6;
    border-radius: 12px;
    background-color: #f8fafc;
    margin: 2rem 0;
}

.empty-icon {
    opacity: 0.5;
}

/* Drop Zone Overlay */
.drop-zone-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(13, 110, 253, 0.9);
    border: 3px dashed white;
    border-radius: 12px;
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

.drop-zone-overlay.active {
    display: flex;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 0.9; }
    50% { opacity: 1; }
}

.drop-zone-content {
    text-align: center;
    color: white;
    padding: 2rem;
}

/* Upload Stats */
.upload-stats {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid #e9ecef;
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    font-size: 2rem;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: bold;
    margin: 0;
    color: #2c3e50;
}

.stat-label {
    color: #6c757d;
    margin: 0;
    font-size: 0.875rem;
}



/* Responsive Design */
@media (max-width: 992px) {
    .images-grid-combined {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 1rem;
    }
}

@media (max-width: 768px) {
    .images-grid-combined {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .image-wrapper {
        height: 140px;
    }
    
    .overlay-buttons {
        gap: 3px;
    }
    
    .overlay-buttons .btn {
        width: 32px;
        height: 32px;
        font-size: 0.75rem;
    }
}

@media (max-width: 576px) {
    .images-grid-combined {
        grid-template-columns: 1fr;
    }
    
    .upload-stats .row {
        flex-direction: column;
        gap: 1rem;
    }
    
 
}

/* Scrollbar Styling */
.images-grid-combined {
    max-height: 500px;
    overflow-y: auto;
    padding-right: 10px;
}

.images-grid-combined::-webkit-scrollbar {
    width: 6px;
}

.images-grid-combined::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.images-grid-combined::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.images-grid-combined::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
.floor-plan-name input {
    font-size: 0.875rem;
    padding: 0.25rem 0.5rem;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    width: 100%;
    text-align: center;
}

.floor-plan-name input:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
    outline: none;
}

.image-card .floor-plan-name {
    padding: 0.25rem 0;
    margin-bottom: 0.5rem;
}

.new .floor-plan-name input {
    border-color: #28a745;
}

.marked-for-delete .floor-plan-name input {
    border-color: #dc3545;
    background-color: #f8f9fa;
}

.text-danger {
    font-size: 0.75rem;
    margin-top: 0.25rem;
}

.floor-plan-name small.text-danger {
    display: block;
    font-size: 0.7rem;
    margin-top: 2px;
}
/* Gallery Images Styling */
.gallery-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}

.gallery-item {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #e9ecef;
    background: #f8f9fa;
}

.gallery-image-wrapper {
    position: relative;
    padding-bottom: 100%; /* 1:1 Aspect Ratio */
    height: 0;
    overflow: hidden;
}

.gallery-img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.gallery-item:hover .gallery-img {
    transform: scale(1.05);
}

.gallery-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.gallery-image-wrapper:hover .gallery-overlay {
    opacity: 1;
}

.gallery-overlay .btn {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}

/* Order indicator on each card (top-left) */
.order-badge {
    position: absolute;
    top: 8px;
    left: 8px;
    background: #0B0736;
    color: #fff;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 999px;
    z-index: 2;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.18);
}

/* Drag & drop cues */
.gallery-item {
    cursor: grab;
    user-select: none;
}
.gallery-item:active {
    cursor: grabbing;
}
.drag-hint {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(255, 255, 255, 0.85);
    color: #0B0736;
    font-size: 0.7rem;
    padding: 4px 6px;
    border-radius: 6px;
    z-index: 2;
    opacity: 0;
    transition: opacity 0.15s ease;
    pointer-events: none;
}
.gallery-item:hover .drag-hint {
    opacity: 1;
}
.gallery-ghost {
    opacity: 0.45;
    background: #f1f5f9;
    border: 2px dashed #94a3b8 !important;
}
.gallery-chosen {
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.25);
}
.gallery-drag {
    transform: rotate(2deg);
}

.gallery-item.marked-for-delete {
    opacity: 0.6;
    filter: grayscale(0.3);
}

.delete-badge, .new-badge {
    position: absolute;
    bottom: 8px;
    left: 8px;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
    z-index: 2;
}

.delete-badge {
    background: rgba(220, 53, 69, 0.9);
    color: white;
}

.new-badge {
    background: rgba(40, 167, 69, 0.9);
    color: white;
}

.empty-gallery {
    border: 1px dashed #dee2e6;
    border-radius: 8px;
    background: #f8f9fa;
}

@media (max-width: 768px) {
    .gallery-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.5rem;
    }
}
</style>