<template>
    <div class="row gy-4 mt-4">
      <div class="col-lg-4">
        <div class="user-grid-card position-relative border radius-16 overflow-hidden bg-base h-100">
          <!-- تم إزالة صورة الخلفية هنا -->
          <div class="p-24">
            <div class="text-center border-bottom pb-24">
              <img 
                :src="user.avatar || defaultAvatar" 
                alt="User Avatar"
                class=" w-200-px h-200-px object-fit-cover border-avatar"
              />
              <!--<h6 class="mb-0 mt-16">{{ user.name || 'Jacob Jones' }}</h6>-->
              <!--<span class="text-secondary-light mb-16">{{ user.email || 'ifrandom@gmail.com' }}</span>-->
              <div class="mt-8">
                <span class="badge bg-success" v-if="user.can_login">Active</span>
                <span class="badge bg-info" v-else>Inactive</span>
              </div>
            </div>
            <div class="mt-24">
              <h6 class="text-xl mb-16">Personal Info</h6>
              <ul>
                <li class="d-flex align-items-center gap-1">
                  <span class="w-40 text-md fw-semibold text-primary-light">Full Name</span>
                  <span class="w-60 text-secondary-light fw-medium">: {{ user.name || 'Will Jonto' }}</span>
                </li>
                <li class="d-flex align-items-center gap-1">
                  <span class="w-40 text-md fw-semibold text-primary-light">Email</span>
                  <span class="w-60 text-secondary-light fw-medium">: {{ user.email || 'willjontoax@gmail.com' }}</span>
                </li>
                <li class="d-flex align-items-center gap-1">
                  <span class="w-40 text-md fw-semibold text-primary-light">Phone Number</span>
                  <span class="w-60 text-secondary-light fw-medium">: {{ user.phone || '(1) 2536 2561 2365' }}</span>
                </li>
                <li class="d-flex align-items-center gap-1">
                  <span class="w-40 text-md fw-semibold text-primary-light">Role</span>
                  <span class="w-60 text-secondary-light fw-medium">: {{ user.role_name || 'User' }}</span>
                </li>
                <li class="d-flex align-items-center gap-1">
                  <span class="w-40 text-md fw-semibold text-primary-light">Status</span>
                  <span class="w-60 text-secondary-light fw-medium">: {{ user.status || 'Active' }}</span>
                </li>
                <li class="d-flex align-items-center gap-1">
                  <span class="w-40 text-md fw-semibold text-primary-light">Member Since</span>
                  <span class="w-60 text-secondary-light fw-medium">: {{ user.created_at || '2024-01-01' }}</span>
                </li>
                <li class="d-flex align-items-center gap-1" v-if="user.parent_name">
                  <span class="w-40 text-md fw-semibold text-primary-light">Supervisor</span>
                  <span class="w-60 text-secondary-light fw-medium">: {{ user.parent_name }}</span>
                </li>
                 <li class="d-flex align-items-center gap-1" v-if="user.admin_parent_name">
                  <span class="w-40 text-md fw-semibold text-primary-light"> Branch</span>
                  <span class="w-60 text-secondary-light fw-medium">: {{ user.admin_parent_name }}</span>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
  
      <!-- Right Panel -->
      <div class="col-lg-8">
        <div class="card h-100">
          <div class="card-body p-24">
            <ul class="nav border-gradient-tab nav-pills mb-20 d-inline-flex" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link d-flex align-items-center px-24" 
                        :class="{ active: activeTab === 'edit-profile' }" 
                        @click="activeTab = 'edit-profile'">
                  Edit Profile
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link d-flex align-items-center px-24" 
                        :class="{ active: activeTab === 'change-password' }" 
                        @click="activeTab = 'change-password'">
                  Change Password
                </button>
              </li>
                <li class="nav-item" role="presentation">
                <button class="nav-link d-flex align-items-center px-24" 
                        :class="{ active: activeTab === 'vacation' }" 
                        @click="activeTab = 'vacation'">
                 Vacation 
                </button>
              </li>
            </ul>
  
            <div class="tab-content">
              <!-- Edit Profile Tab -->
              <div v-if="activeTab === 'edit-profile'">
                <h6 class="text-md text-primary-light mb-16 mb-3">Profile Image</h6>
                <div class="mb-24 mt-16">
                  <div class="avatar-upload">
                     <div class="avatar-preview">
                      <div id="imagePreview" :style="{ backgroundImage: 'url(' + profileImage + ')' }"></div>
                    </div>

                    <div class="avatar-edit  cursor-pointer">
                      <input type="file" id="imageUpload" accept=".png, .jpg, .jpeg" @change="onImageChange" hidden />
                      <label for="imageUpload" class="btn btn-primary">
                        <!-- <iconify-icon icon="solar:camera-outline" class="icon"></iconify-icon> -->
                            <i class="ri-upload-2-line me-1"></i>
                          upload photo
                      </label>
                      <span class="info-text">JPEG, PNG, JPG, GIF up to 2MB</span>
                    </div>
                   
                  </div>
                </div>
  
                <form @submit.prevent="updateProfile">
                  <div class="row">
                    <div class="col-sm-6 mb-20">
                      <label class="form-label fw-semibold text-primary-light text-sm mb-8">
                        Full Name <span class="text-danger-600">*</span>
                      </label>
                      <input type="text" class="form-control radius-8" 
                             v-model="formData.name" 
                             placeholder="Enter Full Name" 
                             required />
                    </div>
                    <div class="col-sm-6 mb-20">
                      <label class="form-label fw-semibold text-primary-light text-sm mb-8">
                        Email <span class="text-danger-600">*</span>
                      </label>
                      <input type="email" class="form-control radius-8" 
                             v-model="formData.email" 
                             placeholder="Enter email address" 
                             required />
                    </div>
                    <div class="col-sm-6 mb-20">
                      <label class="form-label fw-semibold text-primary-light text-sm mb-8">Phone</label>
                      <input type="text" class="form-control radius-8" 
                             v-model="formData.phone" 
                             placeholder="Enter phone number" />
                    </div>
                    <div class="col-sm-6 mb-20">
                      <label class="form-label fw-semibold text-primary-light text-sm mb-8">Role</label>
                      <input type="text" class="form-control radius-8" 
                             :value="user.role_name" 
                             readonly 
                             disabled />
                      <small class="text-muted">You cannot change your role</small>
                    </div>
                  </div>
  
                  <div class="d-flex align-items-right justify-content-right gap-3">
                    <button type="button" class=" btn btn-info text-danger-600 text-md px-12 py-6 radius-8"
                            @click="resetForm">
                      Cancel
                    </button>
                    <button type="submit" class="btn btn-primary  text-md px-12 py-6 radius-8"
                            :disabled="loading">
                      <span v-if="loading">Saving...</span>
                      <span v-else>Save Changes</span>
                    </button>
                  </div>
                </form>
              </div>
  
              <!-- Change Password Tab -->
              <div v-if="activeTab === 'change-password'">
                <form @submit.prevent="changePassword">
                  <div class="mb-20">
                    <label class="form-label fw-semibold text-primary-light text-sm mb-8">
                      Current Password <span class="text-danger-600">*</span>
                    </label>
                    <div class="position-relative">
                      <input :type="currentPasswordVisible ? 'text' : 'password'" 
                             class="form-control radius-8" 
                             v-model="passwordData.current_password"
                             placeholder="Enter Current Password" 
                             required />
                      <span @click="currentPasswordVisible = !currentPasswordVisible" 
                            class="ri-eye-line cursor-pointer position-absolute end-0 top-50 translate-middle-y me-16 text-secondary-light">
                      </span>
                    </div>
                  </div>
                  <div class="mb-20">
                    <label class="form-label fw-semibold text-primary-light text-sm mb-8">
                      New Password <span class="text-danger-600">*</span>
                    </label>
                    <div class="position-relative">
                      <input :type="newPasswordVisible ? 'text' : 'password'" 
                             class="form-control radius-8" 
                             v-model="passwordData.new_password"
                             placeholder="Enter New Password" 
                             required />
                      <span @click="newPasswordVisible = !newPasswordVisible" 
                            class="ri-eye-line cursor-pointer position-absolute end-0 top-50 translate-middle-y me-16 text-secondary-light">
                      </span>
                    </div>
                  </div>
                  <div class="mb-20">
                    <label class="form-label fw-semibold text-primary-light text-sm mb-8">
                      Confirm Password <span class="text-danger-600">*</span>
                    </label>
                    <div class="position-relative">
                      <input :type="confirmPasswordVisible ? 'text' : 'password'" 
                             class="form-control radius-8" 
                             v-model="passwordData.confirm_password"
                             placeholder="Confirm Password" 
                             required />
                      <span @click="confirmPasswordVisible = !confirmPasswordVisible" 
                            class="ri-eye-line cursor-pointer position-absolute end-0 top-50 translate-middle-y me-16 text-secondary-light">
                      </span>
                    </div>
                  </div>
                  
                  <div class="d-flex align-items-center justify-content-center gap-3">
                    <button type="submit" class="btn btn-primary border border-primary-600 text-md px-56 py-12 radius-8"
                            :disabled="passwordLoading">
                      <span v-if="passwordLoading">Updating...</span>
                      <span v-else>Update Password</span>
                    </button>
                  </div>
                </form>
              </div>
               <!-- Change Password Tab -->
              
              
              
               <!-- Vacation Mode Tab -->
            <div v-if="activeTab === 'vacation'">
              <div class="vacation-mode-container">
                <h6 class="text-md text-primary-light mb-16">Vacation Mode</h6>
                
                <!-- Vacation Mode Card -->
                <div class="card mb-20">
                  <div class="card-body">
                    <!-- Toggle Switch -->
                    <div class="d-flex justify-content-between align-items-center mb-20">
                      <div>
                        <h6 class="text-primary-light mb-2">Activate Vacation Mode</h6>
                        <p class="text-secondary-light mb-0">
                          When activated, new requests will be assigned to selected agent
                        </p>
                      </div>
                      <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" 
                               v-model="vacationData.active" 
                               :disabled="vacationLoading"
                               id="vacationSwitch">
                        <label class="form-check-label" for="vacationSwitch">
                          {{ vacationData.active ? 'Active' : 'Inactive' }}
                        </label>
                      </div>
                    </div>
                    
                    <div v-if="vacationData.active" class="delegate-section">
                      <label class="form-label fw-semibold text-primary-light text-sm mb-8">
                        Select Agent to handle your requests
                      </label>
                      <select class="form-select radius-8 mb-3" 
                              v-model="vacationData.delegate_id"
                              :disabled="vacationLoading">
                        <option value="">Choose an agent...</option>
                        <option v-for="agent in agentsList" 
                                :key="agent.id" 
                                :value="agent.id">
                          {{ agent.name }} ({{ agent.email }})
                        </option>
                      </select>
                      <small class="text-muted">This agent will receive all new requests while you're on vacation</small>
                      
                     
                    </div>
                 
                    <!-- Message when vacation is inactive -->
                    <div v-else class="text-center py-16">
                      <i class="ri-sun-line text-warning fs-1 mb-3"></i>
                      <p class="text-secondary-light mb-0">
                        Vacation mode is currently inactive. Turn it on to delegate your requests.
                      </p>
                    </div>
                     <!-- Save Button -->
                      <div class="d-flex justify-content-end mt-20">
                        <button type="button" class="btn btn-warning"
                                @click="saveVacationMode"
                                :disabled="vacationLoading || !vacationData.delegate_id">
                          <span v-if="vacationLoading">Saving...</span>
                          <span v-else>Save Changes</span>
                        </button>
                      </div>
                  </div>
                     
                </div>
                
                <!-- Current Status -->
                <div class="card">
                  <div class="card-body">
                    <h6 class="text-primary-light mb-16">Current Status</h6>
                    <div class="row">
                      <div class="col-md-6 mb-3">
                        <div class="status-item">
                          <span class="text-secondary-light">Mode:</span>
                          <span :class="['status-badge ms-2', vacationData.active ? 'active' : 'inactive']">
                            {{ vacationData.active ? 'On Vacation' : 'Active' }}
                          </span>
                        </div>
                      </div>
                      <div class="col-md-6 mb-3" v-if="vacationData.active && currentDelegate">
                        <div class="status-item">
                          <span class="text-secondary-light">Delegate:</span>
                          <span class="text-primary-light ms-2">{{ currentDelegate.name }}</span>
                        </div>
                      </div>
                    
                    </div>
                  </div>
                 </div>
             </div>
             </div>
             <!--end vacation tab-->
              
              
              
              
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>
  
<script>
import { ref, onMounted, reactive, getCurrentInstance, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import defaultAvatar from "@/assets/images/user-grid/user-grid-img14.png";
import user1 from "@/assets/images/user-grid/user-grid-img13.png";

export default {
  name: 'UserProfile',
  setup() {
    const instance = getCurrentInstance();
    const router = useRouter();
    const user = ref({});
    const activeTab = ref('edit-profile');
    const profileImage = ref(user1);
    const defaultAvatarImg = ref(defaultAvatar);
    
    const vacationLoading = ref(false);
    const agentsList = ref([]);
    const vacationData = reactive({
      active: false,
      delegate_id: '',
      last_updated: ''
    });
    
    // دالة لتحميل الوكلاء
    const loadAgents = async () => {
      try {
        const token = localStorage.getItem('token') || sessionStorage.getItem('token');
        
        const agentsResponse = await axios.get('/api/listings/agents/', {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
          },
          params: {
            role: 'sales',
            active: true
          }
        });
        
        console.log('Agents API Response:', agentsResponse.data);
        
        if (agentsResponse.data.status) {
          agentsList.value = agentsResponse.data.data;
          console.log('Agents loaded:', agentsList.value);
        } else {
          console.error('API returned status false:', agentsResponse.data);
        }
        
      } catch (error) {
        console.error('Error loading agents:', error);
        console.error('Error details:', error.response ? error.response.data : error.message);
      }
    };
    
    // دالة لتحميل بيانات الفاكيشن
    const loadVacationData = async () => {
      vacationLoading.value = true;
      try {
        const token = localStorage.getItem('token') || sessionStorage.getItem('token');
        
        // تحميل إعدادات الفاكيشن
        const vacationResponse = await axios.get('/api/listings/agent/vacation-mode', {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
          }
        });
        
        console.log('Vacation API Response:', vacationResponse.data);
        
        if (vacationResponse.data.status) {
          const data = vacationResponse.data.data;
          vacationData.active = data.on_vacation || false;
          vacationData.delegate_id = data.delegate_agent_id || '';
          vacationData.last_updated = data.updated_at || '';
          console.log('Vacation data loaded:', vacationData);
        }
        
        // تحميل قائمة الوكلاء
        await loadAgents();
        
      } catch (error) {
        console.error('Error loading vacation data:', error);
        showNotification('Failed to load vacation data', 'error');
      } finally {
        vacationLoading.value = false;
      }
    };
    
    const saveVacationMode = async () => {
      vacationLoading.value = true;
      try {
        const token = localStorage.getItem('token') || sessionStorage.getItem('token');
        
        const response = await axios.post('/api/listings/agent/vacation', {
          active: vacationData.active,
          delegate_id: vacationData.active ? vacationData.delegate_id : null
        }, {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          }
        });
        
        console.log('Save vacation response:', response.data);
        
        if (response.data.status) {
          showNotification('Vacation mode updated successfully!', 'success');
          // تحديث البيانات المحلية
          if (response.data.data) {
            Object.assign(vacationData, response.data.data);
            vacationData.active = response.data.data.on_vacation || false;
            vacationData.delegate_id = response.data.data.delegate_agent_id || '';
          }
        } else {
          showNotification(response.data.message || 'Failed to update vacation mode', 'error');
        }
      } catch (error) {
        console.error('Error saving vacation mode:', error);
        if (error.response?.data?.errors) {
          const errors = Object.values(error.response.data.errors).flat();
          showNotification(errors[0] || 'Failed to update vacation mode', 'error');
        } else if (error.response?.data?.message) {
          showNotification(error.response.data.message, 'error');
        } else {
          showNotification('Failed to update vacation mode', 'error');
        }
      } finally {
        vacationLoading.value = false;
      }
    };
    
    const formatDate = (dateString) => {
      if (!dateString) return 'N/A';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    };
    
    const currentDelegate = computed(() => {
      if (!vacationData.delegate_id) return null;
      return agentsList.value.find(agent => agent.id == vacationData.delegate_id);
    });
    
    const currentPasswordVisible = ref(false);
    const newPasswordVisible = ref(false);
    const confirmPasswordVisible = ref(false);
    
    const loading = ref(false);
    const passwordLoading = ref(false);
    
    const formData = reactive({
      name: '',
      email: '',
      phone: '',
    });
    
    const passwordData = reactive({
      current_password: '',
      new_password: '',
      confirm_password: ''
    });
    
    // Load user data
    const loadUserData = async () => {
      try {
        const token = localStorage.getItem('token') || sessionStorage.getItem('token');
        const response = await axios.get('/api/profile', {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
          }
        });
        
        if (response.data.success) {
          user.value = response.data.data;
          formData.name = user.value.name || '';
          formData.email = user.value.email || '';
          formData.phone = user.value.phone || '';
          
          if (user.value.avatar) {
            profileImage.value = user.value.avatar;
          }
          
          localStorage.setItem('user', JSON.stringify(user.value));
        }
      } catch (error) {
        console.error('Error loading user data:', error);
        showNotification('Failed to load profile data', 'error');
      }
    };
    
    // Show notification
    const showNotification = (message, type = 'info') => {
      if (instance && instance.appContext.config.globalProperties.$showNotification) {
        instance.appContext.config.globalProperties.$showNotification(message, type);
      } else {
        alert(message);
      }
    };
    
    // Update profile
    const updateProfile = async () => {
      loading.value = true;
      try {
        const token = localStorage.getItem('token') || sessionStorage.getItem('token');
        const response = await axios.put('/api/profile', formData, {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          }
        });
        
        if (response.data.success) {
          user.value = response.data.data;
          localStorage.setItem('user', JSON.stringify(user.value));
          showNotification('Profile updated successfully!', 'success');
        } else {
          showNotification(response.data.message || 'Failed to update profile', 'error');
        }
      } catch (error) {
        console.error('Error updating profile:', error);
        if (error.response?.data?.errors) {
          const errors = Object.values(error.response.data.errors).flat();
          showNotification(errors[0] || 'Failed to update profile', 'error');
        } else {
          showNotification('Failed to update profile', 'error');
        }
      } finally {
        loading.value = false;
      }
    };
    
    // Change password
    const changePassword = async () => {
      if (passwordData.new_password !== passwordData.confirm_password) {
        showNotification('New password and confirmation do not match!', 'warning');
        return;
      }
      
      if (passwordData.new_password.length < 6) {
        showNotification('Password must be at least 6 characters long!', 'warning');
        return;
      }
      
      passwordLoading.value = true;
      try {
        const token = localStorage.getItem('token') || sessionStorage.getItem('token');
        const response = await axios.post('/api/profile/change-password', {
          current_password: passwordData.current_password,
          new_password: passwordData.new_password,
          new_password_confirmation: passwordData.confirm_password
        }, {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          }
        });
        
        if (response.data.success) {
          passwordData.current_password = '';
          passwordData.new_password = '';
          passwordData.confirm_password = '';
          showNotification('Password changed successfully!', 'success');
        } else {
          showNotification(response.data.message || 'Failed to change password', 'error');
        }
      } catch (error) {
        console.error('Error changing password:', error);
        if (error.response?.data?.errors) {
          const errors = Object.values(error.response.data.errors).flat();
          showNotification(errors[0] || 'Failed to change password', 'error');
        } else {
          showNotification('Failed to change password', 'error');
        }
      } finally {
        passwordLoading.value = false;
      }
    };
    
    // Update avatar
    const updateAvatar = async (file) => {
      try {
        const formDataObj = new FormData();
        formDataObj.append('avatar', file);
        
        const token = localStorage.getItem('token') || sessionStorage.getItem('token');
        const response = await axios.post('/api/profile/avatar', formDataObj, {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'multipart/form-data'
          }
        });
        
        if (response.data.success) {
          user.value = response.data.data;
          profileImage.value = user.value.avatar;
          localStorage.setItem('user', JSON.stringify(user.value));
          showNotification('Profile image updated successfully!', 'success');
        } else {
          showNotification(response.data.message || 'Failed to update avatar', 'error');
        }
      } catch (error) {
        console.error('Error updating avatar:', error);
        if (error.response?.data?.errors) {
          const errors = Object.values(error.response.data.errors).flat();
          showNotification(errors[0] || 'Failed to update avatar', 'error');
        } else {
          showNotification('Failed to update avatar', 'error');
        }
      }
    };
    
    // Handle image change
    const onImageChange = (event) => {
      const file = event.target.files[0];
      if (file) {
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        if (!validTypes.includes(file.type)) {
          showNotification('Please select a valid image file (JPEG, JPG, PNG)', 'warning');
          return;
        }
        
        if (file.size > 2 * 1024 * 1024) {
          showNotification('Image size should be less than 2MB', 'warning');
          return;
        }
        
        const reader = new FileReader();
        reader.onload = e => {
          profileImage.value = e.target.result;
          updateAvatar(file);
        };
        reader.readAsDataURL(file);
      }
    };
    
    // Reset form
    const resetForm = () => {
      loadUserData();
      showNotification('Form reset to original values', 'info');
    };
    
    onMounted(() => {
      loadUserData();
      loadVacationData();
    });
    
    watch(() => vacationData.active, (newVal) => {
      if (newVal && agentsList.value.length === 0) {
        loadAgents();
      }
    });
    
    return {
      user,
      activeTab,
      profileImage,
      defaultAvatar: defaultAvatarImg,
      formData,
      passwordData,
      vacationData,
      agentsList,
      loading,
      passwordLoading,
      vacationLoading,
      currentPasswordVisible,
      newPasswordVisible,
      confirmPasswordVisible,
      updateProfile,
      changePassword,
      resetForm,
      onImageChange,
      loadVacationData,
      saveVacationMode,
      currentDelegate,
      formatDate
    };
  }
};
</script>
  
  <style scoped>
  .avatar-upload {
    position: relative;
    /* max-width: 205px; */
    /* margin: 0 auto; */
    display: flex;
    width: 100%;
    justify-content: space-between;
  }
  
  .avatar-edit {
    /* position: absolute; */
    right: 12px;
    z-index: 1;
    top: 10px;
    margin: 10% auto;
  }
  
  .avatar-preview {
    width: 192px;
    height: 192px;
    /* position: relative; */
    border-radius: 100%;
    border: 6px solid #F8F8F8;
    box-shadow: 0px 2px 4px 0px rgba(0, 0, 0, 0.1);
    margin-right: 100px ;
  }
  
  .avatar-preview > div {
    width: 100%;
    height: 100%;
    border-radius: 100%;
    background-size: cover;
    background-repeat: no-repeat;
    background-position: center;
  }
  
  .form-control:disabled {
    background-color: #f8f9fa;
    opacity: 0.7;
    cursor: not-allowed;
  }
  .badge{
      border-radius:5px;
  }
  .bg-success{
    background-color:#FAA300 !important;
  }
   .bg-info{
    background-color:#B8B8B8 !important;
  }
  .border-gradient-tab .nav-link::before{
    display: none !important;
  }
 .border-gradient-tab .nav-link{
    background-color: #B8B8B8 !important;
    color: #000000 !important;
    border-radius: 5px;
    margin: 10px;
    border: none;
  }
  .border-gradient-tab .nav-link.active{
    background-color: #0D1237 !important;
    color: #fff !important;
    border-radius: 5px;
    margin: 10px;
    border: none;
  }
  .btn-info{
    background-color: #B8B8B8 !important;
    color: #fff !important;
    border:none
  }
    .btn-primary{
    background-color: #FAA300 !important;
    color: #fff !important;
    border:none
  }
  .justify-content-right {
    justify-content: end;
    gap: 100px;
  }
  .info-text{
    margin-top: 10px;
    color: #A9A9A9;
    display: block;
  }
  .border-gradient-tab{
      border:none !important;
  }
  .border-avatar{
      border-radius:20px;
  }
  </style>