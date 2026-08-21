<template>
    <div class="dashboard-main-body">
        <Breadcrumb 
            :title="isEditMode ? 'Edit User' : 'Add New User'" 
            :breadcrumbs="[
                { name: 'Users', path: '/users' },
                { name: isEditMode ? 'Edit' : 'Add New' }
            ]" 
        />

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ isEditMode ? 'Edit User' : 'Add New User' }}</h5>
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
                                <img :src="avatarPreview ||  'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'" 
                                     alt="Avatar" 
                                     class="rounded-circle border mb-3" 
                                     width="120" height="120"
                                     @error="handleImageError">
                                <div>
                                    <label class="btn btn-outline-primary btn-sm">
                                        <iconify-icon icon="lucide:upload" class="me-2"></iconify-icon>
                                        Upload Photo
                                        <input type="file" 
                                               class="d-none" 
                                               @change="handleAvatarUpload"
                                               accept=".jpeg,.png,.jpg,.gif"
                                               ref="avatarInput">
                                    </label>
                                    <button v-if="userForm.avatar ||   'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'" 
                                            type="button" 
                                            class="btn btn-outline-danger btn-sm ms-2"
                                            >
                                        <iconify-icon icon="lucide:x"></iconify-icon>
                                    </button>
                                </div>
                                <small class="text-muted d-block mt-1">JPEG, PNG, JPG, GIF up to 2MB</small>
                                <div v-if="errors.avatar" class="text-danger small mt-2">
                                    {{ errors.avatar[0] }}
                                </div>
                            </div>
                        </div>

                        <!-- Form Fields -->
                        <div class="col-md-9">
                            <div class="row">
                                <!-- Basic Information -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" v-model="userForm.name" 
                                               :class="{'is-invalid': errors.name}"
                                               placeholder="Enter full name">
                                        <div class="invalid-feedback" v-if="errors.name">
                                            {{ errors.name[0] }}
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" v-model="userForm.email" 
                                               :class="{'is-invalid': errors.email}"
                                               placeholder="Enter email address">
                                        <div class="invalid-feedback" v-if="errors.email">
                                            {{ errors.email[0] }}
                                        </div>
                                         <div class="invalid-feedback" v-else-if="userForm.email && !isEmailValid">
                                            {{ emailErrorMessage }}
                                        </div>
                                        <small class="text-muted" v-if="userForm.email && isEmailValid && !errors.email">
                                            ✓ Valid company email
                                        </small>
                                      
                                    </div>
                                </div>

                                <!-- Contact Information -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Phone</label>
                                        <input type="text" class="form-control" v-model="userForm.phone" 
                                               :class="{'is-invalid': errors.phone}"
                                               placeholder="Enter phone number">
                                        <div class="invalid-feedback" v-if="errors.phone">
                                            {{ errors.phone[0] }}
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Password <span class="text-danger" v-if="!isEditMode">*</span></label>
                                        <input type="password" class="form-control" v-model="userForm.password" 
                                               :class="{'is-invalid': errors.password}"
                                               :placeholder="isEditMode ? 'Leave empty to keep current' : 'Enter password'">
                                        <div class="invalid-feedback" v-if="errors.password">
                                            {{ errors.password[0] }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Organizational Structure -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Role <span class="text-danger">*</span></label>
                                     <v-select
                                         v-model="userForm.role_id"
                                        :options="roles"
                                        label="name"
                                        :reduce="(role) => role.id"
                                        placeholder="Select role..."
                                        :class="{'is-invalid': errors.role_id}"
                                    />
                                        <div class="invalid-feedback" v-if="errors.role_id">
                                            {{ errors.role_id[0] }}
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Agent Commission %</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" v-model.number="userForm.commission_percentage"
                                                   :class="{'is-invalid': errors.commission_percentage}"
                                                   min="0" max="100" step="0.01" placeholder="50">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <div class="invalid-feedback" v-if="errors.commission_percentage">
                                            {{ errors.commission_percentage[0] }}
                                        </div>
                                        <small class="text-muted">Share of each deal's commission this user keeps; the rest goes to the company.</small>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Parent / Manager</label>
                                        <v-select
                                            v-model="userForm.parent_id"
                                            :options="availableManagers"
                                            label="name"
                                            :reduce="(user) => user.id"
                                            placeholder="Select manager..."
                                            :class="{'is-invalid': errors.parent_id}"
                                        />
                                        <!-- <small class="text-muted">User will report to this manager</small> -->
                                        <div class="invalid-feedback" v-if="errors.parent_id">
                                            {{ errors.parent_id[0] }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Status -->
                                <!-- <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Status <span class="text-danger">*</span></label>
                                        <select v-model="userForm.status" class="form-select" 
                                                :class="{'is-invalid': errors.status}">
                                            <option value="active">Active</option>
                                            <option value="in_active">Inactive</option>
                                            <option value="blocked">Blocked</option>
                                        </select>
                                        <div class="invalid-feedback" v-if="errors.status">
                                            {{ errors.status[0] }}
                                        </div>
                                    </div>
                                </div> -->

                                <!-- Last Login (Display only in edit mode) -->
                                <div class="col-md-6" v-if="isEditMode && userForm.last_login_at">
                                    <div class="mb-3">
                                        <label class="form-label">Last Login</label>
                                        <input type="text" class="form-control" 
                                               :value="formatDate(userForm.last_login_at)" 
                                               disabled>
                                    </div>
                                </div>

                                <!-- Permissions Preview -->
                                <div class="col-12" v-if="selectedRolePermissions.length > 0">
                                    <div class="mb-3">
                                        <label class="form-label">Role Permissions</label>
                                        <div class="permissions-preview">
                                            <span v-for="permission in selectedRolePermissions" 
                                                  :key="permission"
                                                  class="badge bg-light text-dark me-2 mb-2">
                                                {{ permission }}
                                            </span>
                                        </div>
                                        <small class="text-muted">These permissions are inherited from the selected role</small>
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
                                    <span v-if="loading">{{ isEditMode ? 'Updating...' : 'Creating...' }}</span>
                                    <span v-else>{{ isEditMode ? 'Update User' : 'Create User' }}</span>
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
import { ref, onMounted, computed ,watch} from "vue";
import { useRoute, useRouter } from "vue-router";
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import userPlaceholder from '@/assets/images/avatar/avatar1.png';

export default {
    name: 'UserForm',
    components: {
        Breadcrumb,
        vSelect
    },
    setup() {
        const route = useRoute();
        const router = useRouter();

        const isEditMode = computed(() => route.name === 'edit-user');
        const userId = computed(() => route.params.id);

        // Refs
        const avatarInput = ref(null);
        const errors = ref({});
        const loading = ref(false);
        const avatarPreview = ref(null);
        const roles = ref([]);
        const availableManagers = ref([]);
        const selectedRolePermissions = ref([]);
        const validateEmailDomain = (email) => {
            if (!email) return true;
            return email.endsWith('@oiaproperties.com');
        };
        
        const getEmailValidationMessage = (email) => {
            if (!email) return '';
            if (!email.includes('@')) return 'Please enter a valid email address';
            if (!email.endsWith('@oiaproperties.com')) return 'Email must be from @oiaproperties.com domain';
            return '';
        };
        // User Form Data - Updated to match backend
        const userForm = ref({
            name: "",
            email: "",
            phone: "",
            password: "",
            role_id: null,
            parent_id: null,
            avatar: null,
            status: "active",
            notes: "",
            commission_percentage: 50,
            last_login_at: null
        });

        // Base API URL
        const API_BASE = '/api';
watch(roles, (newRoles) => {
    if (newRoles.length > 0 && isEditMode.value && userForm.value.role_id === null) {
        console.log('Roles loaded, now assigning user role...');
        fetchUserDataOnly();
    }
});
        // Handle avatar upload
        const handleAvatarUpload = (event) => {
            const file = event.target.files[0];
            if (file) {
                if (file.size > 2 * 1024 * 1024) {
                    showNotification("❌ Avatar image must be less than 2MB", "error");
                    event.target.value = '';
                    return;
                }

                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    showNotification("❌ Please upload a valid image (JPG, PNG, GIF)", "error");
                    event.target.value = '';
                    return;
                }

                userForm.value.avatar = file;
                
                const reader = new FileReader();
                reader.onload = (e) => {
                    avatarPreview.value = e.target.result;
                };
                reader.readAsDataURL(file);

                showNotification("✅ Avatar uploaded successfully", "success");
            }
        };

        // Remove avatar
        const removeAvatar = () => {
            userForm.value.avatar = null;
            avatarPreview.value = null;
            if (avatarInput.value) {
                avatarInput.value.value = '';
            }
            showNotification("🗑️ Avatar removed", "info");
        };

        // Handle image error
        const handleImageError = (event) => {
            console.log('Image failed to load, using placeholder');
            event.target.src = userPlaceholder;
            
            // نحاول نفحص ليه الصورة مش شغالة
            const currentSrc = event.target.src;
            console.log('Failed image source:', currentSrc);
            
            // إذا كان في مشكلة في الـ URL نحاول نصلحها
            if (currentSrc.includes('users/avatars/') && !currentSrc.includes('/storage/')) {
                const fixedUrl = `${window.location.origin}/storage/${currentSrc}`;
                console.log('Trying fixed URL:', fixedUrl);
                event.target.src = fixedUrl;
            }
        };

        // Fetch roles
        const fetchRoles = async () => {
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`${API_BASE}/roles`, {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    roles.value = data.data || data;
                    console.log('Roles loaded:', roles.value);
                } else {
                    throw new Error(`Failed to fetch roles: ${response.status}`);
                }
            } catch (error) {
                console.error("❌ Error fetching roles:", error);
                showNotification("❌ Failed to load roles.", "error");
            }
        };

        // Fetch available managers
        const fetchManagers = async () => {
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`${API_BASE}/users/managers/available`, {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    availableManagers.value = data.data || data;
                    console.log('Managers loaded:', availableManagers.value);
                } else {
                    console.warn('No managers available or access denied');
                    availableManagers.value = [];
                }
            } catch (error) {
                console.error("❌ Error fetching managers:", error);
                availableManagers.value = [];
            }
        };

        // Handle role change
        const handleRoleChange = async (roleId) => {
            if (roleId) {
                try {
                    const token = localStorage.getItem('token');
                    const response = await fetch(`${API_BASE}/roles/${roleId}/permissions`, {
                        method: 'GET',
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Content-Type': 'application/json'
                        }
                    });

                    if (response.ok) {
                        const data = await response.json();
                        selectedRolePermissions.value = data.data || data;
                        console.log('Role permissions:', selectedRolePermissions.value);
                    } else {
                        selectedRolePermissions.value = [];
                        console.warn('No permissions found for this role');
                    showNotification("ℹ️ No specific permissions for this role", "info");
                    }
                } catch (error) {
                    console.error("❌ Error fetching role permissions:", error);
                    selectedRolePermissions.value = [];
                }
            } else {
                selectedRolePermissions.value = [];
            }
        };

        // Fetch user data for editing
        const fetchUser = async () => {
            try {
                loading.value = true;
                const token = localStorage.getItem('token');
                
                const response = await fetch(`${API_BASE}/users/${userId.value}`, {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    const userData = data.data || data;
                    console.log('User data fetched:', userData);
                    
                    // Reset form first
                    userForm.value = {
                        name: userData.name || "",
                        email: userData.email || "",
                        phone: userData.phone || "",
                        password: "",
                       role_id: userData.role_id || null,
                        parent_id: userData.parent_id || null,
                        avatar: null,
                        status: userData.status || "active",
                        notes: userData.notes || "",
                        commission_percentage: userData.commission_percentage ?? 50,
                        last_login_at: userData.last_login_at || null
                    };

                   if (userForm.value.email && !validateEmailDomain(userForm.value.email)) {
                        showNotification("⚠️ Warning: This user has an email not from @oiaproperties.com domain", "warning");
                    }

                    // Set avatar preview if exists
                    if (userData.avatar) {
                        avatarPreview.value = userData.avatar;
                    }

                    showNotification("✅ User data loaded successfully", "success");
                } else {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Failed to fetch user data');
                }

            } catch (error) {
                console.error("❌ Error fetching user:", error);
                showNotification(`❌ Failed to load user data: ${error.message}`, "error");
                router.push('/users');
            } finally {
                loading.value = false;
            }
        };
        const isEmailValid = computed(() => {
            if (!userForm.value.email) return true;
            return validateEmailDomain(userForm.value.email);
        });
        
        const emailErrorMessage = computed(() => {
            return getEmailValidationMessage(userForm.value.email);
        });
        const selectedRole = computed({
            get: () => {
                if (!userForm.value.role_id) return null;
                return roles.value.find(role => role.id === userForm.value.role_id);
            },
            set: (newRole) => {
                userForm.value.role_id = newRole ? newRole.id : null;
                if (newRole) {
                    handleRoleChange(newRole.id);
                }
            }
        });

        // Submit user form
        const submitForm = async () => {
            try {
                loading.value = true;
                errors.value = {};

                // Basic validation
                if (!userForm.value.name || !userForm.value.email || !userForm.value.role_id) {
                    showNotification("❌ Please fill in all required fields", "error");
                    loading.value = false;
                    return;
                }
        
                // Email domain validation
                if (!validateEmailDomain(userForm.value.email)) {
                    errors.value.email = ['Email must be from @oiaproperties.com domain'];
                    showNotification("❌ Email must be from @oiaproperties.com domain", "error");
                    loading.value = false;
                    return;
                }


                const formData = new FormData();

                // Append all form data
                Object.keys(userForm.value).forEach(key => {
                    const value = userForm.value[key];
                    if (value instanceof File) {
                        formData.append(key, value);
                    } else if (value !== null && value !== "" && value !== undefined) {
                        formData.append(key, value);
                    }
                });

                // Remove password if empty in edit mode
                if (isEditMode.value && !userForm.value.password) {
                    formData.delete('password');
                }

                // Debug: Log form data
                for (let pair of formData.entries()) {
                    console.log(pair[0] + ': ' + pair[1]);
                }

                const token = localStorage.getItem('token');
                let response;
                let url;

                if (isEditMode.value) {
                    url = `${API_BASE}/users/${userId.value}`;
                    response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'X-HTTP-Method-Override': 'PUT'
                        },
                        body: formData
                    });
                } else {
                    url = `${API_BASE}/users`;
                    response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Authorization': 'Bearer ' + token
                        },
                        body: formData
                    });
                }

                console.log('Submit response status:', response.status);

                if (response.ok) {
                    const result = await response.json();
                    const successMessage = isEditMode.value ? "User updated successfully!" : "User created successfully!";
                    showNotification(`✅ ${successMessage}`, "success");

                    // Redirect to users list
                    setTimeout(() => {
                        router.push('/users');
                    }, 1500);
                } else {
                    const errorText = await response.text();
                    console.error('Error response:', errorText);
                    
                    let errorData;
                    try {
                        errorData = JSON.parse(errorText);
                    } catch (e) {
                        errorData = { message: errorText };
                    }

                    if (errorData.errors) {
                        errors.value = errorData.errors;
                        showNotification("❌ Please check the form for errors.", "error");
                    } else {
                        throw new Error(errorData.message || `Failed to save user: ${response.status}`);
                    }
                }

            } catch (error) {
                console.error("❌ Error saving user:", error);
                showNotification(`❌ Failed to save user: ${error.message}`, "error");
            } finally {
                loading.value = false;
            }
        };

        // Format date for display
        const formatDate = (dateString) => {
            if (!dateString) return 'Never';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        };
        const validateEmailOnBlur = () => {
            if (userForm.value.email && !validateEmailDomain(userForm.value.email)) {
                errors.value.email = ['Email must be from @oiaproperties.com domain'];
            } else if (errors.value.email) {
                delete errors.value.email;
            }
        };

        // Helper function for notifications
        const showNotification = (message, type = 'info') => {
            if (window.$showNotification) {
                window.$showNotification(message, type);
            } else {
                console.log(`${type}: ${message}`);
                // Fallback notification
                const alertClass = {
                    'success': 'alert-success',
                    'error': 'alert-danger',
                    'warning': 'alert-warning',
                    'info': 'alert-info'
                }[type] || 'alert-info';
                
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
                alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                alertDiv.innerHTML = `
                    <div class="d-flex align-items-center">
                        <span class="me-2">${message}</span>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                    </div>
                `;
                document.body.appendChild(alertDiv);
                
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.parentNode.removeChild(alertDiv);
                    }
                }, 5000);
            }
        };

        // Initialize component
        onMounted(async () => {
            console.log('UserForm mounted, isEditMode:', isEditMode.value);
            await fetchRoles();
            await fetchManagers();
            
            if (isEditMode.value) {
                await fetchUser();
            }
        });

        return {
            isEditMode,
            loading,
            avatarPreview,
            userPlaceholder,
            userForm,
            errors,
            roles,
            availableManagers,
            selectedRolePermissions,
            avatarInput,
            handleAvatarUpload,
            removeAvatar,
            handleImageError,
            handleRoleChange,
            submitForm,
            formatDate,
            selectedRole,
            isEmailValid,
            emailErrorMessage,
            validateEmailOnBlur,
            validateEmailDomain
        };
    }
};
</script>

<style scoped>
.permissions-preview {
    max-height: 120px;
    overflow-y: auto;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 12px;
    background-color: #f8f9fa;
}

.badge {
    font-size: 0.7rem;
    padding: 4px 8px;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.invalid-feedback {
    display: block;
}

.form-label {
    font-weight: 500;
    color: #495057;
}

.form-control, .form-select {
    border-radius: 8px;
    border: 1px solid #ced4da;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-control:focus, .form-select:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

.btn {
    border-radius: 8px;
    font-weight: 500;
}

.v-select {
    --vs-border-color: #ced4da;
    --vs-border-radius: 8px;
}

.v-select:focus-within {
    --vs-border-color: #86b7fe;
}

.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}
.text-warning {
    color: #ffc107;
}

.form-control.is-valid {
    border-color: #198754;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
    padding-right: calc(1.5em + 0.75rem);
}
</style>