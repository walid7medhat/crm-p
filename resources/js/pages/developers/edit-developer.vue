<template>
    <div class="dashboard-main-body">
        <Breadcrumb :title="developer ? `Edit ${developer.name}` : 'Edit Developer'" :breadcrumbs="[
            { name: 'Developers', path: '/developers' },
            { name: developer ? developer.name : 'Loading...', path: developer ? `/developers/${developer.id}` : '#' },
            { name: 'Edit' }
        ]" />

        <div class="card" v-if="developer">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="ui-h-mini card-title mb-0">Edit Developer Information</h6>
                <button class="btn btn-outline-secondary" @click="$router.back()">
                    <iconify-icon icon="lucide:arrow-left" class="me-2"></iconify-icon>
                    Back
                </button>
            </div>
            
            <div class="card-body">
                <form @submit.prevent="submitForm">
                    <div class="row">
                        <!-- Profile Picture -->
                        <div class="col-md-3 text-center">
                            <div class="mb-4">
                                <img :src="avatarPreview || form.avatar || 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'" 
                                     alt="Avatar" 
                                     class="rounded-circle border mb-3" 
                                     width="120" height="120"
                                    >
                                <div>
                                    <label class="btn btn-outline-primary btn-sm">
                                        <iconify-icon icon="lucide:upload" class="me-2"></iconify-icon>
                                        Change Photo
                                        <input type="file" 
                                               class="d-none" 
                                               @change="handleImageUpload"
                                               accept=".jpeg,.png,.jpg,.gif,image/jpeg,image/png,image/jpg,image/gif"
                                               ref="fileInput">
                                    </label>
                                    <button v-if="avatarFile" 
                                            type="button" 
                                            class="btn btn-outline-danger btn-sm ms-2"
                                            @click="removeSelectedImage">
                                        <iconify-icon icon="lucide:x"></iconify-icon>
                                    </button>
                                </div>
                                <small class="text-muted d-block mt-1">JPEG, PNG, JPG, GIF up to 2MB</small>
                                <!-- Display avatar errors properly -->
                                <div v-if="errors.avatar" class="text-danger small mt-2">
                                    {{ errors.avatar[0] }}
                                </div>
                                <div v-if="!avatarFile && form.avatar" class="text-info small mt-1">
                                    Current photo will be kept
                                </div>
                            </div>
                        </div>

                        <!-- Form Fields -->
                        <div class="col-md-9">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" v-model="form.name" required
                                               :class="{'is-invalid': errors.name}"
                                               placeholder="Enter full name">
                                        <div class="invalid-feedback" v-if="errors.name">
                                            {{ errors.name[0] }}
                                        </div>
                                        <div class="form-text">The developer's full name</div>
                                    </div>
                                </div>
                                
                               
                                <!-- Read-only Information -->
                                <div class="col-12">
                                    <hr class="my-4">
                                    <h6 class="text-muted mb-3">System Information</h6>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted">Developer ID</label>
                                        <input type="text" class="form-control bg-light" :value="developer.id" readonly>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted">Added By</label>
                                        <input type="text" class="form-control bg-light" :value="developer.added_by" readonly>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted">Created Date</label>
                                        <input type="text" class="form-control bg-light" :value="formatDate(developer.created_at)" readonly>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted">Last Updated</label>
                                        <input type="text" class="form-control bg-light" :value="formatDate(developer.updated_at)" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="d-flex gap-2 justify-content-end border-top pt-4">
                                <button type="button" class="btn btn-outline-secondary" @click="$router.back()">
                                    <iconify-icon icon="lucide:x" class="me-2"></iconify-icon>
                                    Cancel
                                </button>
                              
                                <button type="submit" class="btn btn-primary" :disabled="loading">
                                    <iconify-icon icon="lucide:save" class="me-2"></iconify-icon>
                                    <span v-if="loading">Updating...</span>
                                    <span v-else>Update Developer</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Loading State -->
        <div v-else-if="loading" class="card">
            <div class="card-body text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <p>Loading developer information...</p>
            </div>
        </div>

        <!-- Error State -->
        <div v-else class="card">
            <div class="card-body text-center py-5">
                <iconify-icon icon="lucide:user-x" class="text-muted mb-3" width="48"></iconify-icon>
                <h6 class="ui-h-mini">Developer Not Found</h6>
                <p class="text-muted">The developer you're trying to edit doesn't exist.</p>
                <button class="btn btn-primary" @click="$router.push('/developers')">
                    Back to Developers List
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { API_ENDPOINTS } from '@/config/api';
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import userPlaceholder from '../../assets/images/avatar/avatar1.png';

export default {
    name: 'EditDeveloper',
    components: {
        Breadcrumb
    },
    data() {
        return {
            loading: false,
            developer: null,
            avatarFile: null,
            avatarPreview: null,
            form: {
                name: '',
                avatar: ''
            },
            errors: {} ,
            userPlaceholder
        };
    },
    mounted() {
        this.fetchDeveloper();
    },
    methods: {
        async fetchDeveloper() {
            try {
                this.loading = true;
                const token = localStorage.getItem('token');
                const developerId = this.$route.params.id;
                
                const response = await fetch(API_ENDPOINTS.DEVELOPER_BY_ID(developerId), {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    this.developer = data.data;
                    this.form = { ...this.developer };
                    this.errors = {};
                } else {
                    throw new Error('Failed to fetch developer');
                }
            } catch (error) {
                console.error('Error fetching developer:', error);
                this.$showNotification('Error loading developer data', 'error');
            } finally {
                this.loading = false;
            }
        },

     async submitForm() {
            if (!this.$hasPermission('developers-edit')) {
                this.$showNotification('You do not have permission to edit developers', 'error');
                return;
            }

            if (!this.form.name) {
                this.$showNotification('Name is required fields', 'error');
                return;
            }

            try {
                this.loading = true;
                this.errors = {};

                const token = localStorage.getItem('token');
                const developerId = this.$route.params.id;

             

                let requestData;
                let headers = {
                    'Authorization': 'Bearer ' + token
                };

                let method = 'PUT';
                let url = API_ENDPOINTS.DEVELOPER_BY_ID(developerId);

                if (this.avatarFile) {
                    requestData = new FormData();
                    requestData.append('name', this.form.name);
                    requestData.append('avatar', this.avatarFile);

                    requestData.append('_method', 'PUT');
                    method = 'POST'; 

                    console.log('Using FormData (POST + _method=PUT)');
                } else {
                    requestData = JSON.stringify({
                        name: this.form.name,
                    });
                    headers['Content-Type'] = 'application/json';
                    console.log('Using JSON (PUT)');
                }

          

                const response = await fetch(url, {
                    method,
                    headers,
                    body: requestData
                });

                console.log('Response status:', response.status);

                const responseData = await response.json();
                console.log('Server response:', responseData);

                if (response.ok) {
                    this.$showNotification('Developer updated successfully!', 'success');
                    this.$router.push(`/developers/${developerId}`);
                } else {
                    if (response.status === 422 && responseData.errors) {
                        this.errors = responseData.errors;
                        const errorMessage = this.formatValidationErrors(responseData.errors);
                        throw new Error(`Validation failed: ${errorMessage}`);
                    }
                    throw new Error(responseData.message || `Failed to update developer. Status: ${response.status}`);
                }

            } catch (error) {
                console.error('Error updating developer:', error);
                this.$showNotification('Error updating developer: ' + error.message, 'error');

                if (error.message.includes('avatar') || this.errors.avatar) {
                    this.$showNotification('Please select a valid image file for the avatar', 'error');
                }
            } finally {
                this.loading = false;
            }
        },


        handleImageUpload(event) {
            const file = event.target.files[0];
            
            if (!file) return;

            console.log('Selected file:', file.name, file.type, file.size);

            const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            const fileExtension = file.name.split('.').pop().toLowerCase();
            const allowedExtensions = ['jpeg', 'png', 'jpg', 'gif'];
            
            if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(fileExtension)) {
                this.$showNotification('Please select a valid image file (JPEG, PNG, JPG, GIF)', 'error');
                event.target.value = '';
                return;
            }

            const maxSize = 2 * 1024 * 1024;
            if (file.size > maxSize) {
                this.$showNotification('Image size must be less than 2MB', 'error');
                event.target.value = '';
                return;
            }

            this.avatarFile = file;

            const reader = new FileReader();
            reader.onload = (e) => {
                this.avatarPreview = e.target.result;
            };
            reader.readAsDataURL(file);

            if (this.errors.avatar) {
                this.errors.avatar = null;
            }

            this.$showNotification('Image selected successfully. Click Update to save changes.', 'success');
        },

        removeSelectedImage() {
            this.avatarFile = null;
            this.avatarPreview = null;
            if (this.$refs.fileInput) {
                this.$refs.fileInput.value = '';
            }
            if (this.errors.avatar) {
                this.errors.avatar = null;
            }
            this.$showNotification('Image selection removed. Current photo will be kept.', 'info');
        },

       

        formatValidationErrors(errors) {
            if (typeof errors === 'object') {
                return Object.values(errors).flat().join(', ');
            }
            return errors || 'Please check the form for errors';
        },



        formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US');
        },

       
    }
};
</script>

<style scoped>
.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.form-text {
    font-size: 0.875rem;
}

.bg-light {
    background-color: #f8f9fa !important;
}

.invalid-feedback {
    display: block;
}

.text-danger {
    font-size: 0.875rem;
}

.text-info {
    font-size: 0.875rem;
}
</style>