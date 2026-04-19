<template>
    <div class="dashboard-main-body">
        <Breadcrumb 
            :title="`Floor Plans - ${projectTitle}`" 
            :breadcrumbs="[
                { name: 'Projects', path: '/projects' },
                { name: 'Floor Plans' }
            ]" 
        />

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-layer-group me-2"></i>
                   {{projectTitle}} Floor Plan Images
                </h5>
                <button class="btn btn-outline-secondary" @click="goBack">
                    <iconify-icon icon="lucide:arrow-left" class="me-2"></iconify-icon>
                    Back
                </button>
            </div>
            
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-map-marker-alt me-1 text-primary"></i>
                            Select Building/Phases
                        </label>
                        <select class="form-select" v-model="selectedAreaId" @change="onAreaChange">
                            <option value="">-- Choose  --</option>
                            <option v-for="area in areas" :key="area.id" :value="area.id">
                                {{ area.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <div v-if="errorMessage" class="alert alert-danger">
                    {{ errorMessage }}
                </div>

                <div v-if="loading" class="text-center py-5">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-3">Loading...</p>
                </div>

                <div v-else-if="selectedAreaId">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6>
                            <span class="badge bg-primary">
                                {{ existingImages.length + newImages.length - imagesToDelete.length }} Images
                            </span>
                        </h6>
                        <button class="btn  btn-primary" @click="$refs.fileInput.click()">
                            <i class="fas fa-plus me-1"></i> Add Images
                        </button>
                    </div>

                    <input ref="fileInput" type="file" class="d-none" @change="handleImageUpload" multiple accept="image/*">

                    <div class="row">
                        <div v-for="img in existingImages" :key="img.id" class="col-md-3 col-6 mb-3">
                            <div class="image-card" :class="{'to-delete': imagesToDelete.includes(img.id)}">
                                <div class="position-relative">
                                    <img :src="img.image_url" class="img-fluid rounded" style="height: 150px; width: 100%; object-fit: cover;">
                                    <button class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1" 
                                            @click="toggleDelete(img.id)">
                                        <i :class="imagesToDelete.includes(img.id) ? 'fas fa-undo' : 'fas fa-trash'"></i>
                                    </button>
                                </div>
                                <div class="mt-2">
                                    <input type="text" class="form-control form-control-sm" 
                                           v-model="img.name" 
                                           placeholder="Image name"
                                           @change="updateName(img)">
                                    <small class="text-danger" v-if="img.nameError">{{ img.nameError }}</small>
                                </div>
                            </div>
                        </div>

                        <div v-for="(img, index) in newImages" :key="index" class="col-md-3 col-6 mb-3">
                            <div class="image-card new">
                                <div class="position-relative">
                                    <img :src="img.preview" class="img-fluid rounded" style="height: 150px; width: 100%; object-fit: cover;">
                                    <button class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1" 
                                            @click="removeNew(index)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <span class="badge bg-success position-absolute bottom-0 start-0 m-1">New</span>
                                </div>
                                <div class="mt-2">
                                    <input type="text" class="form-control form-control-sm" 
                                           v-model="img.name" 
                                           placeholder="Image name"
                                           @blur="validateNew(img, index)">
                                    <small class="text-danger" v-if="img.nameError">{{ img.nameError }}</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 text-end" v-if="hasChanges">
                        <button class="btn btn-primary" @click="saveChanges" :disabled="saving">
                            <i class="fas fa-spinner fa-spin me-1" v-if="saving"></i>
                            <i class="fas fa-save me-1" v-else></i>
                            {{ saving ? 'Saving...' : 'Save Changes' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted, computed } from "vue";

import { useRoute, useRouter } from "vue-router";
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';

export default {
    name: 'FloorPlans',
    components: { Breadcrumb },
    setup() {
        const route = useRoute();
        const router = useRouter();
        const projectId = route.params.id;

        const loading = ref(false);
        const saving = ref(false);
        const errorMessage = ref('');
        const projectTitle = ref('');
        const areas = ref([]);
        const selectedAreaId = ref('');
        const existingImages = ref([]);
        const newImages = ref([]);
        const imagesToDelete = ref([]);

        const hasChanges = computed(() => {
            return newImages.value.length > 0 || imagesToDelete.value.length > 0;
        });

        const fetchProject = async () => {
            try {
                const token = localStorage.getItem('token');
                console.log('Fetching project:', projectId);
                
                const res = await fetch(`/api/listings/projects/${projectId}`, {
                    headers: { 
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json'
                    }
                });
                
                console.log('Project response status:', res.status);
                
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                
                const data = await res.json();
                console.log('Project data:', data);
                
                projectTitle.value = data.data.title;
                
                await fetchProjectAreas();
            } catch (err) {
                console.error('Error fetching project:', err);
                errorMessage.value = 'Failed to load project: ' + err.message;
            }
        };

        const fetchProjectAreas = async () => {
            try {
                const token = localStorage.getItem('token');
                console.log('Fetching areas for project:', projectId);
                
                const res = await fetch(`/api/listings/projects/${projectId}/areas`, {
                    headers: { 
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json'
                    }
                });
                
                console.log('Areas response status:', res.status);
                
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                
                const data = await res.json();
                console.log('Areas data:', data);
                
                if (data.success) {
                    areas.value = data.data;
                    if (areas.value.length > 0) {
                        selectedAreaId.value = areas.value[0].id;
                        await fetchFloorPlans();
                    }
                }
            } catch (err) {
                console.error('Error fetching areas:', err);
                errorMessage.value = 'Failed to load areas: ' + err.message;
            }
        };

        const fetchFloorPlans = async () => {
            if (!selectedAreaId.value) return;
            
            loading.value = true;
            errorMessage.value = '';
            
            try {
                const token = localStorage.getItem('token');
                const url = `/api/listings/projects/${projectId}/floor-plans/${selectedAreaId.value}`;
                console.log('Fetching floor plans from:', url);
                
                const res = await fetch(url, {
                    headers: { 
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json'
                    }
                });
                
                console.log('Floor plans response status:', res.status);
                
                if (!res.ok) {
                    if (res.status === 404) {
                        console.log('Route not found - check API routes');
                        errorMessage.value = 'API route not found. Check backend routes.';
                    } else {
                        throw new Error(`HTTP error! status: ${res.status}`);
                    }
                    existingImages.value = [];
                } else {
                    const data = await res.json();
                    console.log('Floor plans data:', data);
                    
                    if (data.success) {
                        existingImages.value = data.data.map(img => ({
                            ...img,
                            name: img.name || '',
                            nameError: ''
                        }));
                    } else {
                        existingImages.value = [];
                    }
                }
            } catch (err) {
                console.error('Error fetching floor plans:', err);
                errorMessage.value = 'Failed to load floor plans: ' + err.message;
                existingImages.value = [];
            } finally {
                loading.value = false;
            }
        };

        const onAreaChange = () => {
            console.log('Area changed to:', selectedAreaId.value);
            if (selectedAreaId.value) {
                fetchFloorPlans();
            }
        };
        const showNotification = (message, type = 'info') => {
            console.log(`[${type}] ${message}`);
            // alert(message);
            
            if (window.$showNotification) {
                window.$showNotification(message, type);
            }
        };
        const handleImageUpload = (e) => {
            const files = Array.from(e.target.files);
            console.log('Uploading files:', files.length);
            
            files.forEach(file => {
                if (!file.type.startsWith('image/')) {
                    showNotification('Please upload image files only');
                    return;
                }
                
                if (file.size > 5 * 1024 * 1024) {
                    showNotification('File size must be less than 5MB');
                    return;
                }

                const reader = new FileReader();
                reader.onload = (e) => {
                    newImages.value.push({
                        file,
                        preview: e.target.result,
                        name: file.name.replace(/\.[^/.]+$/, ""),
                        nameError: ''
                    });
                    console.log('New images:', newImages.value.length);
                };
                reader.readAsDataURL(file);
            });
            
            e.target.value = '';
        };

        const validateNew = (img, index) => {
            if (!img.name || img.name.trim() === '') {
                img.nameError = 'Name is required';
                return false;
            }
            img.nameError = '';
            return true;
        };

        const updateName = async (img) => {
            if (!img.name || img.name.trim() === '') {
                img.nameError = 'Name is required';
                return;
            }

            try {
                const token = localStorage.getItem('token');
                const url = `/api/listings/projects/floor-plan-images/${img.id}/name`;
                console.log('Updating name at:', url, 'with name:', img.name);
                
                const res = await fetch(url, {
                    method: 'PUT',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ name: img.name.trim() })
                });

                console.log('Update name response status:', res.status);
                
                if (res.ok) {
                    img.nameError = '';
                    console.log('Name updated successfully');
                } else {
                    const data = await res.json();
                    img.nameError = data.message || 'Failed to update';
                    console.error('Update failed:', data);
                }
            } catch (err) {
                console.error('Error updating name:', err);
                img.nameError = 'Failed to update';
            }
        };

        const toggleDelete = (id) => {
            const index = imagesToDelete.value.indexOf(id);
            if (index === -1) {
                imagesToDelete.value.push(id);
                console.log('Marked for delete:', id);
            } else {
                imagesToDelete.value.splice(index, 1);
                console.log('Restored:', id);
            }
        };

        const removeNew = (index) => {
            newImages.value.splice(index, 1);
            console.log('Removed new image at index:', index);
        };

        const saveChanges = async () => {
            let isValid = true;
            newImages.value.forEach((img, index) => {
                if (!validateNew(img, index)) {
                    isValid = false;
                }
            });
        
            if (!isValid) {
                showNotification('Please fill all image names','error');
                return;
            }
        
            saving.value = true;
            errorMessage.value = '';
            
            const formData = new FormData();
            formData.append('area_id', selectedAreaId.value);
            
            if (imagesToDelete.value.length > 0) {
                imagesToDelete.value.forEach((id, i) => {
                    formData.append(`delete_floor_plan_images[${i}]`, id);
                });
                console.log('Images to delete:', imagesToDelete.value);
            }
            
            newImages.value.forEach((img, i) => {
                formData.append(`floor_plan_images[${i}][file]`, img.file);
                formData.append(`floor_plan_images[${i}][name]`, img.name);
            });
            console.log('New images count:', newImages.value.length);
            
            existingImages.value.forEach(img => {
                if (img.name && !img.nameError) {
                    formData.append(`floor_plan_names[${img.id}]`, img.name);
                }
            });
        
            try {
                const token = localStorage.getItem('token');
                const url = `/api/listings/projects/${projectId}/update/floor-plans`;
                console.log('Saving to:', url);
                
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json'
                    },
                    body: formData
                });
        
                console.log('Save response status:', res.status);
                
                const data = await res.json();
                console.log('Save response data:', data);
                
                if (res.ok && data.success) {
                    showNotification('Saved successfully');
                    await fetchFloorPlans();
                    newImages.value = [];
                    imagesToDelete.value = [];
                } else {
                    throw new Error(data.message || 'Error saving');
                }
            } catch (err) {
                console.error('Error saving:', err);
                errorMessage.value = 'Error saving: ' + err.message;
                // alert('Error saving: ' + err.message);
            } finally {
                saving.value = false;
            }
        };

        const goBack = () => {
            router.push('/projects');
        };

        onMounted(() => {
            console.log('FloorPlans component mounted, projectId:', projectId);
            fetchProject();
        });

        return {
            projectTitle,
            areas,
            selectedAreaId,
            existingImages,
            newImages,
            imagesToDelete,
            loading,
            saving,
            errorMessage,
            hasChanges,
            onAreaChange,
            handleImageUpload,
            validateNew,
            updateName,
            toggleDelete,
            removeNew,
            saveChanges,
            goBack,
            showNotification
        };
    }
};
</script>

<style scoped>
.image-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 10px;
    background: white;
    transition: all 0.2s;
}

.image-card:hover {
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.image-card.to-delete {
    opacity: 0.5;
    border-color: #dc3545;
    background: #fff5f5;
}

.image-card.new {
    border-color: #28a745;
    background: #f0fff0;
}

.form-control:focus {
    box-shadow: none;
    border-color: #0d6efd;
}

.badge {
    font-size: 0.7rem;
}

.btn-sm {
    width: 30px;
    height: 30px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>