<template>
    <div class="dashboard-main-body">
        <Breadcrumb 
            title="Add New Developer" 
            :breadcrumbs="[
                { name: 'Developers', path: '/developers' },
                { name: 'Add New' }
            ]" 
        />

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Add New Developer</h5>
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
                                <img :src="userPlaceholder" 
                                     alt="Avatar" 
                                     class="rounded-circle border mb-3" 
                                     width="120" height="120"
                                    >
                                <div>
                                    <label class="btn btn-outline-primary btn-sm">
                                        <iconify-icon icon="lucide:upload" class="me-2"></iconify-icon>
                                        Upload Photo
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
                                <!-- Display avatar errors -->
                                <div v-if="errors.avatar" class="text-danger small mt-2">
                                    {{ errors.avatar[0] }}
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
                                    <span v-if="loading">Creating...</span>
                                    <span v-else>Create Developer</span>
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
import { API_ENDPOINTS } from '@/config/api';
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
// import userPlaceholder from '../../assets/images/avatar/avatar1.png';

export default {
    name: 'CreateDeveloper',
    components: {
        Breadcrumb
    },
    data() {
        return {
            loading: false,
            avatarFile: null,
            avatarPreview: null,
             userPlaceholder: 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png',
            form: {
                name: '',
                avatar: ''
            },
            errors: {}
        };
    },
    methods: {
        async submitForm() {
            if (!this.$hasPermission('developers-create')) {
                this.$showNotification('You do not have permission to create developers', 'error');
                return;
            }

            if (!this.form.name) {
                this.$showNotification('Name is required field', 'error');
                return;
            }

            try {
                this.loading = true;
                this.errors = {};

                const token = localStorage.getItem('token');

            

                let requestData;
                let headers = {
                    'Authorization': 'Bearer ' + token,
                    'Accept': 'application/json'
                };

                if (this.avatarFile) {
                    if (!this.validateImageFile(this.avatarFile)) {
                        this.$showNotification('Please select a valid image file', 'error');
                        this.loading = false;
                        return;
                    }

                    requestData = new FormData();
                    requestData.append('name', this.form.name);
                
                    requestData.append('avatar', this.avatarFile);

                    console.log('Using FormData for create');
                    console.log('FormData entries:');
                    for (let pair of requestData.entries()) {
                        console.log(pair[0] + ': ', pair[1]);
                    }
                } else {
                    requestData = JSON.stringify({
                        name: this.form.name,
                        
                    });
                    headers['Content-Type'] = 'application/json';
                    console.log('Using JSON for create');
                }

                console.log('Request headers:', headers);

                const response = await fetch(API_ENDPOINTS.DEVELOPERS, {
                    method: 'POST',
                    headers: headers,
                    body: requestData
                });

                console.log('Response status:', response.status);

                const responseData = await response.json();
                console.log('Server response:', responseData);

                if (response.ok) {
                    this.$showNotification('Developer created successfully!', 'success');
                    this.$router.push('/developers');
                } else {
                    if (response.status === 422 && responseData.errors) {
                        this.errors = responseData.errors;
                        const errorMessage = this.formatValidationErrors(responseData.errors);
                        
                        if (this.errors.avatar) {
                            this.$showNotification('Invalid image file. Please select a valid JPEG, PNG, JPG, or GIF file under 2MB.', 'error');
                        } else {
                            this.$showNotification(`Validation failed: ${errorMessage}`, 'error');
                        }
                        
                        throw new Error(`Validation failed: ${errorMessage}`);
                    }
                    
                    const errorMsg = responseData.message || `Failed to create developer. Status: ${response.status}`;
                    this.$showNotification(errorMsg, 'error');
                    throw new Error(errorMsg);
                }

            } catch (error) {
                console.error('Error creating developer:', error);
                
                if (!error.message.includes('Validation failed') && !this.errors.avatar) {
                    this.$showNotification('Error creating developer: ' + error.message, 'error');
                }
            } finally {
                this.loading = false;
            }
        },

        validateImageFile(file) {
            if (!file) return false;

            const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            const fileExtension = file.name.split('.').pop().toLowerCase();
            const allowedExtensions = ['jpeg', 'png', 'jpg', 'gif'];
            
            if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(fileExtension)) {
                console.error('Invalid file type:', file.type, fileExtension);
                return false;
            }

            const maxSize = 2 * 1024 * 1024;
            if (file.size > maxSize) {
                console.error('File too large:', file.size);
                return false;
            }

            if (file.size === 0) {
                console.error('File is empty');
                return false;
            }

            return true;
        },

        handleImageUpload(event) {
            const file = event.target.files[0];
            
            if (!file) return;

            console.log('Selected file:', file.name, file.type, file.size);

            if (!this.validateImageFile(file)) {
                this.$showNotification('Please select a valid image file (JPEG, PNG, JPG, GIF) under 2MB', 'error');
                event.target.value = '';
                return;
            }

            this.avatarFile = file;

            const reader = new FileReader();
            reader.onload = (e) => {
                this.avatarPreview = e.target.result;
            };
            reader.onerror = (e) => {
                console.error('Error reading file:', e);
                this.$showNotification('Error reading image file', 'error');
                this.removeSelectedImage();
            };
            reader.readAsDataURL(file);

            if (this.errors.avatar) {
                this.errors.avatar = null;
            }

            this.$showNotification('Image selected successfully', 'success');
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
            this.$showNotification('Image selection removed', 'info');
        },

        handleImageError(event) {
            console.log('Image error, using placeholder');
            event.target.src = this.userPlaceholder;
        },

        formatValidationErrors(errors) {
            if (typeof errors === 'object') {
                return Object.values(errors).flat().join(', ');
            }
            return errors || 'Please check the form for errors';
        }
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