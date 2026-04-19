<template>
    <div class="dashboard-main-body">
        <Breadcrumb 
            :title="isEditMode ? 'Edit Feature' : 'Add Feature'" 
            :breadcrumbs="[
                { name: 'Features', path: '/features' },
                { name: isEditMode ? 'Edit' : 'Add' }
            ]" 
        />

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ isEditMode ? 'Edit Feature' : 'Add Feature' }}</h5>
                <button class="btn btn-outline-secondary" @click="$router.back()">
                    <iconify-icon icon="lucide:arrow-left" class="me-2"></iconify-icon>
                    Back
                </button>
            </div>
            
            <div class="card-body">
                <form @submit.prevent="submitForm" enctype="multipart/form-data">
                    <div class="row justify-content-center">
                        <div class="col-md-6">
                            <!-- Name Field -->
                            <div class="mb-4">
                                <label class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" v-model="featureForm.name" 
                                       :class="{'is-invalid': errors.name}"
                                       placeholder="Enter feature name"
                                       autofocus>
                                <div class="invalid-feedback" v-if="errors.name">
                                    {{ errors.name[0] }}
                                </div>
                                <small class="text-muted">e.g., Swimming Pool, Gym, Parking, etc.</small>
                            </div>

                            <!-- Icon Field -->
                            <div class="mb-4">
                                <label class="form-label">
                                    Icon 
                                    <span v-if="!isEditMode" class="text-danger">*</span>
                                </label>
                                
                                <!-- Current Icon Preview -->
                                <div v-if="isEditMode && featureForm.avatar" class="mb-3">
                                    <p class="mb-2">Current Icon:</p>
                                    <img :src="featureForm.avatar" 
                                         alt="Current Icon" 
                                         class="img-thumbnail"
                                         style="width: 100px; height: 100px; object-fit: cover;">
                                </div>

                                <!-- File Input -->
                                <input type="file" 
                                       class="form-control" 
                                       @change="handleFileUpload"
                                       accept="image/*"
                                       :class="{'is-invalid': errors.avatar}">
                                
                                <div class="invalid-feedback" v-if="errors.avatar">
                                    {{ errors.avatar[0] }}
                                </div>
                                <small class="text-muted">Upload an icon/image for the feature (jpeg, png, jpg, gif)</small>

                                <!-- New Image Preview -->
                                <div v-if="previewImage" class="mt-3">
                                    <p class="mb-2">New Icon Preview:</p>
                                    <img :src="previewImage" 
                                         alt="New Icon Preview" 
                                         class="img-thumbnail"
                                         style="width: 100px; height: 100px; object-fit: cover;">
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-flex gap-2 justify-content-end border-top pt-4 mt-4">
                                <button type="button" class="btn btn-outline-secondary" @click="$router.back()" :disabled="loading">
                                    Cancel
                                </button>
                              
                                <button type="submit" class="btn btn-primary" :disabled="loading">
                                    <span v-if="loading">
                                        <iconify-icon icon="lucide:loader-2" class="me-2 spin"></iconify-icon>
                                        {{ isEditMode ? 'Updating...' : 'Creating...' }}
                                    </span>
                                    <span v-else>
                                        <iconify-icon icon="lucide:save" class="me-2"></iconify-icon>
                                        {{ isEditMode ? 'Update' : 'Create' }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "@/plugins/axios";
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';

export default {
    name: 'FeatureForm',
    components: {
        Breadcrumb
    },
    setup() {
        const route = useRoute();
        const router = useRouter();

        const isEditMode = computed(() => route.name === 'edit-layout_type');
        const featureId = computed(() => route.params.id);

        // Refs
        const errors = ref({});
        const loading = ref(false);
        const previewImage = ref(null);
        const selectedFile = ref(null);

        // Feature Form Data
        const featureForm = ref({
            name: "",
            avatar: null
        });

        // Handle file upload
        const handleFileUpload = (event) => {
            const file = event.target.files[0];
            if (file) {
                selectedFile.value = file;
                
                // Create preview
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewImage.value = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        };

        // Fetch Feature data for editing
        const fetchFeature = async () => {
            try {
                loading.value = true;
                console.log('Fetching feature data for ID:', featureId.value);
                
                const response = await api.get(`/listings/features/${featureId.value}`);
                const featureData = response.data.data || response.data;

                if (!featureData) {
                    throw new Error('Feature not found');
                }

                // Populate form with existing data
                featureForm.value = {
                    name: featureData.name || "",
                    avatar: featureData.avatar || null
                };

                console.log('Form populated with data:', featureForm.value);

            } catch (error) {
                console.error("Error fetching feature:", error);
                showNotification("Failed to load feature.", "error");
                router.push('/features');
            } finally {
                loading.value = false;
            }
        };

        // Submit Feature form
        const submitForm = async () => {
            try {
                loading.value = true;
                errors.value = {};

                // Validation
                if (!featureForm.value.name?.trim()) {
                    showNotification("Please enter feature name", "error");
                    loading.value = false;
                    return;
                }

                if (!isEditMode.value && !selectedFile.value) {
                    showNotification("Please select an icon", "error");
                    loading.value = false;
                    return;
                }

                // Prepare form data
                const formData = new FormData();
                formData.append('name', featureForm.value.name.trim());
                
                if (selectedFile.value) {
                    formData.append('avatar', selectedFile.value);
                }

                console.log('Submitting feature data...');

                let response;
                
                if (isEditMode.value) {
                    response = await api.post(`/listings/features/${featureId.value}?_method=PUT`, formData, {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    });
                } else {
                    response = await api.post("/listings/features", formData, {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    });
                }

                const successMessage = isEditMode.value ? "Feature updated successfully!" : "Feature created successfully!";
                showNotification(`${successMessage}`, "success");

                setTimeout(() => {
                    router.push('/features');
                }, 1000);

            } catch (error) {
                console.error("Error saving feature:", error);
                
                if (error.response?.data?.errors) {
                    errors.value = error.response.data.errors;
                    showNotification("Please check the form for errors.", "error");
                } else {
                    showNotification("Failed to save feature.", "error");
                }
            } finally {
                loading.value = false;
            }
        };

        // Helper function for notifications
        const showNotification = (message, type = 'info') => {
            console.log(`${type}: ${message}`);
            // يمكنك استخدام مكتبة الإشعارات هنا
        };

        // Initialize component
        onMounted(() => {
            if (isEditMode.value) {
                fetchFeature();
            }
        });

        return {
            isEditMode,
            loading,
            featureForm,
            errors,
            previewImage,
            submitForm,
            handleFileUpload
        };
    }
};
</script>

<style scoped>
.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.invalid-feedback {
    display: block;
}

.border-top {
    border-top: 1px solid #dee2e6 !important;
}

.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.text-muted {
    font-size: 0.875rem;
}

.img-thumbnail {
    padding: 0.25rem;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
}
</style>