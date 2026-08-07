<template>
  <div class="vp-page">
    <div class="vp-layout">
      <!-- Left sidebar -->
      <aside class="vp-sidebar">
        <div class="vp-identity">
          <img
            v-if="user.avatar"
            :src="user.avatar"
            :alt="user.name || 'Profile'"
            class="vp-identity__avatar"
          />
          <div v-else class="vp-identity__avatar vp-identity__avatar--placeholder">
            {{ userInitials }}
          </div>
          <div>
            <div class="vp-identity__name">{{ user.name || 'User' }}</div>
            <span class="vp-status" :class="{ 'is-active': user.can_login }">
              {{ user.can_login ? 'ACTIVE' : 'INACTIVE' }}
            </span>
          </div>
        </div>

        <section class="vp-overview">
          <div class="vp-overview__title">Personal Info</div>
          <ul class="vp-info-list">
            <li class="vp-info-list__item">
              <iconify-icon icon="lucide:user" class="vp-info-list__icon" />
              <div class="vp-info-list__body">
                <span class="vp-info-list__label">Full Name</span>
                <span class="vp-info-list__value">{{ user.name || '—' }}</span>
              </div>
            </li>
            <li class="vp-info-list__item">
              <iconify-icon icon="lucide:mail" class="vp-info-list__icon" />
              <div class="vp-info-list__body">
                <span class="vp-info-list__label">Email</span>
                <span class="vp-info-list__value">{{ user.email || '—' }}</span>
              </div>
            </li>
            <li class="vp-info-list__item">
              <iconify-icon icon="lucide:phone" class="vp-info-list__icon" />
              <div class="vp-info-list__body">
                <span class="vp-info-list__label">Phone Number</span>
                <span class="vp-info-list__value">{{ user.phone || '—' }}</span>
              </div>
            </li>
            <li class="vp-info-list__item">
              <iconify-icon icon="lucide:shield" class="vp-info-list__icon" />
              <div class="vp-info-list__body">
                <span class="vp-info-list__label">Role</span>
                <span class="vp-info-list__value">{{ user.role_name || 'User' }}</span>
              </div>
            </li>
            <li class="vp-info-list__item">
              <iconify-icon icon="lucide:activity" class="vp-info-list__icon" />
              <div class="vp-info-list__body">
                <span class="vp-info-list__label">Status</span>
                <span class="vp-info-list__value">{{ user.status || (user.can_login ? 'Active' : 'Inactive') }}</span>
              </div>
            </li>
            <li class="vp-info-list__item">
              <iconify-icon icon="lucide:calendar" class="vp-info-list__icon" />
              <div class="vp-info-list__body">
                <span class="vp-info-list__label">Member Since</span>
                <span class="vp-info-list__value">{{ user.created_at || '—' }}</span>
              </div>
            </li>
            <li v-if="user.parent_name" class="vp-info-list__item">
              <iconify-icon icon="lucide:user-check" class="vp-info-list__icon" />
              <div class="vp-info-list__body">
                <span class="vp-info-list__label">Supervisor</span>
                <span class="vp-info-list__value">{{ user.parent_name }}</span>
              </div>
            </li>
            <li v-if="user.admin_parent_name" class="vp-info-list__item">
              <iconify-icon icon="lucide:building-2" class="vp-info-list__icon" />
              <div class="vp-info-list__body">
                <span class="vp-info-list__label">Branch</span>
                <span class="vp-info-list__value">{{ user.admin_parent_name }}</span>
              </div>
            </li>
          </ul>

          <div v-if="isSuperAdmin && selectedDepartmentLabels.length" class="vp-dept-tags">
            <span v-for="label in selectedDepartmentLabels" :key="label" class="vp-dept-tag">{{ label }}</span>
          </div>
        </section>

        <section v-if="isSuperAdmin" class="vp-schedule">
          <svg class="vp-schedule__wave" viewBox="0 0 120 56" fill="none" aria-hidden="true">
            <path d="M0 40 Q30 10 60 35 T120 20 V56 H0 Z" fill="url(#vpWaveGrad)" />
            <defs>
              <linearGradient id="vpWaveGrad" x1="0" y1="0" x2="120" y2="56">
                <stop stop-color="#7c5cbf" />
                <stop offset="1" stop-color="#5b3d8f" stop-opacity="0.4" />
              </linearGradient>
            </defs>
          </svg>
          <div class="vp-schedule__head">
            <div class="vp-schedule__donut" aria-hidden="true" />
            <div class="vp-schedule__title">Attendance Schedule</div>
          </div>
          <div class="vp-schedule__field">
            <label class="vp-schedule__label">Day</label>
            <select
              v-model.number="attendanceSettings.day_of_week"
              class="vp-schedule__select"
              :disabled="attendanceSettingsLoading || attendanceSettingsSaving"
            >
              <option v-for="day in dayOptions" :key="day.value" :value="day.value">{{ day.label }}</option>
            </select>
          </div>
          <div class="vp-schedule__row vp-schedule__field">
            <div>
              <label class="vp-schedule__label">From</label>
              <input
                v-model="attendanceSettings.start_time"
                type="time"
                class="vp-schedule__input"
                :disabled="attendanceSettingsLoading || attendanceSettingsSaving"
              />
            </div>
            <div>
              <label class="vp-schedule__label">To</label>
              <input
                v-model="attendanceSettings.end_time"
                type="time"
                class="vp-schedule__input"
                :disabled="attendanceSettingsLoading || attendanceSettingsSaving"
              />
            </div>
          </div>
          <div class="vp-schedule__field">
            <label class="vp-schedule__label">Departments (required check-in)</label>
            <div
              class="vp-schedule__depts"
              :class="{ 'is-disabled': attendanceSettingsLoading || attendanceSettingsSaving }"
            >
              <div class="vp-schedule__chips">
                <template v-if="selectedDepartmentLabels.length">
                  <span v-for="label in selectedDepartmentLabels" :key="label" class="vp-dept-tag">{{ label }}</span>
                </template>
                <span v-else class="vp-schedule__hint" style="margin: 0">All departments selected</span>
              </div>
              <label
                v-for="dept in departmentOptions"
                :key="dept.value"
                class="vp-schedule__dept-item"
              >
                <input
                  type="checkbox"
                  :value="dept.value"
                  :checked="attendanceSettings.department_ids.includes(dept.value)"
                  :disabled="attendanceSettingsLoading || attendanceSettingsSaving"
                  @change="toggleDepartmentSelection(dept.value)"
                />
                <span>{{ dept.label }}</span>
              </label>
            </div>
            <p class="vp-schedule__hint">
              Select one or more departments. Leave empty to apply to all departments.
            </p>
            <button
              type="button"
              class="vp-btn-save-sm"
              :disabled="attendanceSettingsLoading || attendanceSettingsSaving"
              @click="saveAttendanceSettings"
            >
              <span v-if="attendanceSettingsSaving">Saving...</span>
              <span v-else>Save Schedule</span>
            </button>
          </div>
        </section>
      </aside>

      <!-- Right panel -->
      <main class="vp-main">
        <nav class="vp-tabs" role="tablist">
          <button
            type="button"
            class="vp-tab"
            :class="{ 'is-active': activeTab === 'edit-profile' }"
            role="tab"
            @click="activeTab = 'edit-profile'"
          >
            <iconify-icon icon="lucide:user-pen" />
            Edit Profile
          </button>
          <button
            type="button"
            class="vp-tab"
            :class="{ 'is-active': activeTab === 'change-password' }"
            role="tab"
            @click="activeTab = 'change-password'"
          >
            <iconify-icon icon="lucide:key-round" />
            Change Password
          </button>
          <button
            type="button"
            class="vp-tab"
            :class="{ 'is-active': activeTab === 'vacation' }"
            role="tab"
            @click="activeTab = 'vacation'"
          >
            <iconify-icon icon="lucide:palmtree" />
            Vacation
          </button>
          <button
            type="button"
            class="vp-tab"
            :class="{ 'is-active': activeTab === 'attendance' }"
            role="tab"
            @click="activeTab = 'attendance'"
          >
            <iconify-icon icon="lucide:calendar-check" />
            Attendance
          </button>
        </nav>

        <div class="vp-panel">
          <!-- Edit Profile -->
          <div v-if="activeTab === 'edit-profile'">
            <div class="vp-section-title">Profile Image</div>
            <div class="vp-avatar-block">
              <div id="imagePreview" class="vp-avatar-preview" :style="{ backgroundImage: 'url(' + profileImage + ')' }" />
              <div class="vp-avatar-actions">
                <input id="imageUpload" type="file" accept=".png, .jpg, .jpeg, .gif" hidden @change="onImageChange" />
                <label for="imageUpload" class="vp-btn-upload">
                  <iconify-icon icon="lucide:cloud-upload" />
                  upload photo
                </label>
                <span class="vp-avatar-hint">JPEG, PNG, JPG, GIF up to 2MB</span>
              </div>
            </div>

            <form @submit.prevent="updateProfile">
              <div class="vp-form-grid">
                <div class="vp-field">
                  <label class="vp-field__label">Full Name <span class="required">*</span></label>
                  <input
                    v-model="formData.name"
                    type="text"
                    class="vp-field__input"
                    placeholder="Enter Full Name"
                    required
                  />
                </div>
                <div class="vp-field">
                  <label class="vp-field__label">Email <span class="required">*</span></label>
                  <input
                    v-model="formData.email"
                    type="email"
                    class="vp-field__input"
                    placeholder="Enter email address"
                    required
                  />
                </div>
                <div class="vp-field">
                  <label class="vp-field__label">Phone</label>
                  <input
                    v-model="formData.phone"
                    type="text"
                    class="vp-field__input"
                    placeholder="Enter phone number"
                  />
                </div>
                <div class="vp-field vp-field--role">
                  <label class="vp-field__label">Role</label>
                  <input
                    type="text"
                    class="vp-field__input vp-field__input--locked"
                    :value="user.role_name"
                    readonly
                    disabled
                  />
                  <iconify-icon icon="lucide:lock" class="vp-field__lock-icon" />
                  <span class="vp-field__hint">
                    <iconify-icon icon="lucide:key-round" style="font-size: 12px; color: var(--vp-gold)" />
                    Role cannot be changed
                  </span>
                </div>
              </div>
              <div class="vp-form-actions">
                <button type="button" class="vp-btn-ghost" @click="resetForm">Cancel</button>
                <button type="submit" class="vp-btn-primary" :disabled="loading">
                  <span v-if="loading">Saving...</span>
                  <span v-else>Save Changes</span>
                </button>
              </div>
            </form>
          </div>

          <!-- Change Password -->
          <div v-if="activeTab === 'change-password'">
            <div class="vp-section-title">Change Password</div>
            <form class="vp-password-form" @submit.prevent="changePassword">
              <div class="vp-field">
                <label class="vp-field__label">Current Password <span class="required">*</span></label>
                <div class="vp-input-wrap">
                  <input
                    v-model="passwordData.current_password"
                    :type="currentPasswordVisible ? 'text' : 'password'"
                    class="vp-field__input"
                    placeholder="Enter Current Password"
                    required
                  />
                  <button
                    type="button"
                    class="vp-input-wrap__toggle"
                    :aria-label="currentPasswordVisible ? 'Hide password' : 'Show password'"
                    @click="currentPasswordVisible = !currentPasswordVisible"
                  >
                    <iconify-icon :icon="currentPasswordVisible ? 'lucide:eye-off' : 'lucide:eye'" />
                  </button>
                </div>
              </div>
              <div class="vp-field">
                <label class="vp-field__label">New Password <span class="required">*</span></label>
                <div class="vp-input-wrap">
                  <input
                    v-model="passwordData.new_password"
                    :type="newPasswordVisible ? 'text' : 'password'"
                    class="vp-field__input"
                    placeholder="Enter New Password"
                    required
                  />
                  <button
                    type="button"
                    class="vp-input-wrap__toggle"
                    @click="newPasswordVisible = !newPasswordVisible"
                  >
                    <iconify-icon :icon="newPasswordVisible ? 'lucide:eye-off' : 'lucide:eye'" />
                  </button>
                </div>
              </div>
              <div class="vp-field">
                <label class="vp-field__label">Confirm Password <span class="required">*</span></label>
                <div class="vp-input-wrap">
                  <input
                    v-model="passwordData.confirm_password"
                    :type="confirmPasswordVisible ? 'text' : 'password'"
                    class="vp-field__input"
                    placeholder="Confirm Password"
                    required
                  />
                  <button
                    type="button"
                    class="vp-input-wrap__toggle"
                    @click="confirmPasswordVisible = !confirmPasswordVisible"
                  >
                    <iconify-icon :icon="confirmPasswordVisible ? 'lucide:eye-off' : 'lucide:eye'" />
                  </button>
                </div>
              </div>
              <div class="vp-form-actions">
                <button type="submit" class="vp-btn-primary" :disabled="passwordLoading">
                  <span v-if="passwordLoading">Updating...</span>
                  <span v-else>Update Password</span>
                </button>
              </div>
            </form>
          </div>

          <!-- Vacation -->
          <div v-if="activeTab === 'vacation'">
            <div class="vp-section-title">Vacation Mode</div>
            <div class="vp-vacation-card">
              <div class="vp-vacation-toggle">
                <div>
                  <div class="vp-vacation-toggle__title">Activate Vacation Mode</div>
                  <p>When activated, new requests will be assigned to selected agent</p>
                </div>
                <label class="vp-switch">
                  <input
                    id="vacationSwitch"
                    v-model="vacationData.active"
                    type="checkbox"
                    :disabled="vacationLoading"
                  />
                  <span class="vp-switch__slider" />
                </label>
              </div>

              <div v-if="vacationData.active">
                <div class="vp-field">
                  <label class="vp-field__label">Select Agent to handle your requests</label>
                  <select
                    v-model="vacationData.delegate_id"
                    class="vp-field__input"
                    :disabled="vacationLoading"
                  >
                    <option value="">Choose an agent...</option>
                    <option v-for="agent in agentsList" :key="agent.id" :value="agent.id">
                      {{ agent.name }} ({{ agent.email }})
                    </option>
                  </select>
                  <span class="vp-field__hint">This agent will receive all new requests while you're on vacation</span>
                </div>
                <div class="vp-form-actions" style="margin-top: 16px">
                  <button
                    type="button"
                    class="vp-btn-gold"
                    :disabled="vacationLoading || !vacationData.delegate_id"
                    @click="saveVacationMode"
                  >
                    <span v-if="vacationLoading">Saving...</span>
                    <span v-else>Save Changes</span>
                  </button>
                </div>
              </div>

              <div v-else class="vp-vacation-empty">
                <iconify-icon icon="lucide:sun" />
                <p>Vacation mode is currently inactive. Turn it on to delegate your requests.</p>
              </div>
            </div>

            <div class="vp-status-card">
              <div class="vp-section-title vp-section-title--sm">Current Status</div>
              <div class="vp-status-row">
                <div class="vp-status-item">
                  <span class="vp-info-list__label">Mode</span>
                  <span :class="['vp-badge', vacationData.active ? 'vp-badge--active' : 'vp-badge--inactive']">
                    {{ vacationData.active ? 'On Vacation' : 'Active' }}
                  </span>
                </div>
                <div v-if="vacationData.active && currentDelegate" class="vp-status-item">
                  <span class="vp-info-list__label">Delegate</span>
                  <span class="vp-info-list__value">{{ currentDelegate.name }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Attendance -->
          <div v-if="activeTab === 'attendance'">
            <div class="vp-section-title">Attendance</div>
            <UserAttendanceCarousel />
          </div>
        </div>
      </main>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, reactive, getCurrentInstance, computed, watch } from 'vue';
import api from '@/plugins/axios';
import defaultAvatar from "@/assets/images/user-grid/user-grid-img14.png";
import user1 from "@/assets/images/user-grid/user-grid-img13.png";
import UserAttendanceCarousel from '@/components/Users/UserAttendanceCarousel.vue';

export default {
  name: 'UserProfile',
  components: {
    UserAttendanceCarousel,
  },
  setup() {
    const instance = getCurrentInstance();
    const user = ref({});
    const activeTab = ref('edit-profile');
    const profileImage = ref(user1);
    const defaultAvatarImg = ref(defaultAvatar);

    const readStoredUser = () => {
      try {
        const raw = localStorage.getItem('user');
        if (!raw) return null;
        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' ? parsed : null;
      } catch (_) {
        return null;
      }
    };
    
    const vacationLoading = ref(false);
    const attendanceSettingsLoading = ref(false);
    const attendanceSettingsSaving = ref(false);
    const attendanceSettings = reactive({
      day_of_week: 6,
      start_time: '09:00',
      end_time: '10:00',
      department_ids: [],
    });
    const attendanceStatus = reactive({
      is_active_day: false,
      is_department_active: true,
      is_within_time_window: false,
      already_checked_in: false,
      status: 'Closed',
      window_label: 'Not configured',
      check_in_at:'',
      today_code: '',
    });
    const departmentOptions = ref([]);
    const checkinSubmitting = ref(false);
    const checkinCode = ref('');
    const dayOptions = [
      { value: 0, label: 'Sunday' },
      { value: 1, label: 'Monday' },
      { value: 2, label: 'Tuesday' },
      { value: 3, label: 'Wednesday' },
      { value: 4, label: 'Thursday' },
      { value: 5, label: 'Friday' },
      { value: 6, label: 'Saturday' },
    ];
    const agentsList = ref([]);
    const vacationData = reactive({
      active: false,
      delegate_id: '',
      last_updated: ''
    });
    
    const loadAgents = async () => {
      try {
        const agentsResponse = await api.get('/listings/agents/', {
          params: {
            role: 'sales',
            active: true
          }
        });

        if (agentsResponse.data.status || agentsResponse.data.success) {
          agentsList.value = agentsResponse.data.data || [];
        }

      } catch (error) {
        console.error('Error loading agents:', error);
      }
    };

    const loadVacationData = async () => {
      vacationLoading.value = true;
      try {
        const vacationResponse = await api.get('/listings/agent/vacation-mode');

        if (vacationResponse.data.status || vacationResponse.data.success) {
          const data = vacationResponse.data.data || {};
          vacationData.active = data.on_vacation || false;
          vacationData.delegate_id = data.delegate_agent_id || '';
          vacationData.last_updated = data.updated_at || '';
        }

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
        const response = await api.post('/listings/agent/vacation', {
          active: vacationData.active,
          delegate_id: vacationData.active ? vacationData.delegate_id : null
        });

        if (response.data.status || response.data.success) {
          showNotification('Vacation mode updated successfully!', 'success');
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
    const isSuperAdmin = computed(() => {
      const roleName = String(user.value?.role_name || '').toLowerCase();
      if (roleName === 'super_admin' || roleName === 'super admin') return true;
      const roles = Array.isArray(user.value?.roles) ? user.value.roles : [];
      return roles.some((role) => {
        const name = String(role?.name || role || '').toLowerCase();
        return name === 'super_admin' || name === 'super admin';
      });
    });
    const userInitials = computed(() => {
      const name = String(user.value?.name || 'U').trim();
      const parts = name.split(/\s+/).filter(Boolean);
      if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
      return name.slice(0, 2).toUpperCase();
    });
    const isCheckinCodeComplete = computed(() => String(checkinCode.value || '').trim().length === 4);
    const selectedDepartmentLabels = computed(() => {
      const selectedIds = attendanceSettings.department_ids.map((id) => Number(id));
      return departmentOptions.value
        .filter((dept) => selectedIds.includes(Number(dept.value)))
        .map((dept) => dept.label);
    });
    const checkinBadgeClass = computed(() => {
      if (attendanceStatus.status === 'Checked In') return 'bg-success text-white';
      if (attendanceStatus.status === 'Not Checked In') return 'bg-warning text-dark';
      return 'bg-secondary text-white';
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
    
    const applyUserToForm = (profile) => {
      if (!profile || typeof profile !== 'object') return;
      // Unwrap accidental nested { data: user } payloads
      const data = profile.data && typeof profile.data === 'object' && !profile.id
        ? profile.data
        : profile;

      user.value = data;
      formData.name = data.name || '';
      formData.email = data.email || '';
      formData.phone = data.phone || '';

      if (data.avatar) {
        profileImage.value = data.avatar;
      }

      try {
        localStorage.setItem('user', JSON.stringify(data));
      } catch (_) {
        // ignore storage failures
      }
    };

    const loadUserData = async () => {
      // Show cached profile immediately while the API loads
      const cached = readStoredUser();
      if (cached?.id || cached?.email || cached?.name) {
        applyUserToForm(cached);
      }

      try {
        const response = await api.get('/profile');
        const payload = response.data || {};
        const ok = payload.success !== false && payload.status !== false;
        const profile = payload.data ?? payload;

        if (ok && profile && (profile.id || profile.email || profile.name)) {
          applyUserToForm(profile);
        } else if (!user.value?.id) {
          showNotification(payload.message || 'Failed to load profile data', 'error');
        }
      } catch (error) {
        console.error('Error loading user data:', error);
        if (!user.value?.id) {
          showNotification(
            error?.response?.data?.message || 'Failed to load profile data',
            'error'
          );
        }
      }
    };

    const showNotification = (message, type = 'info') => {
      if (instance && instance.appContext.config.globalProperties.$showNotification) {
        instance.appContext.config.globalProperties.$showNotification(message, type);
      } else {
        alert(message);
      }
    };

    const updateProfile = async () => {
      loading.value = true;
      try {
        const response = await api.put('/profile', formData);

        if (response.data.success || response.data.status) {
          applyUserToForm(response.data.data || response.data);
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
          showNotification(error?.response?.data?.message || 'Failed to update profile', 'error');
        }
      } finally {
        loading.value = false;
      }
    };
    
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
        const response = await api.post('/profile/change-password', {
          current_password: passwordData.current_password,
          new_password: passwordData.new_password,
          new_password_confirmation: passwordData.confirm_password
        });

        if (response.data.success || response.data.status) {
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
          showNotification(error?.response?.data?.message || 'Failed to change password', 'error');
        }
      } finally {
        passwordLoading.value = false;
      }
    };

    const updateAvatar = async (file) => {
      try {
        const formDataObj = new FormData();
        formDataObj.append('avatar', file);

        const response = await api.post('/profile/avatar', formDataObj, {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        });

        if (response.data.success || response.data.status) {
          applyUserToForm(response.data.data || response.data);
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
          showNotification(error?.response?.data?.message || 'Failed to update avatar', 'error');
        }
      }
    };
    
    const onImageChange = (event) => {
      const file = event.target.files[0];
      if (file) {
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!validTypes.includes(file.type)) {
          showNotification('Please select a valid image file (JPEG, JPG, PNG, GIF)', 'warning');
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
    
    const resetForm = () => {
      loadUserData();
      showNotification('Form reset to original values', 'info');
    };

    const loadAttendanceSettings = async () => {
      if (!isSuperAdmin.value) return;
      attendanceSettingsLoading.value = true;
      try {
        const response = await api.get('/attendance/settings');

        attendanceSettings.day_of_week = Number.isInteger(response.data?.day_of_week) ? response.data.day_of_week : 6;
        attendanceSettings.start_time = String(response.data?.start_time || '09:00:00').slice(0, 5);
        attendanceSettings.end_time = String(response.data?.end_time || '10:00:00').slice(0, 5);
        attendanceSettings.department_ids = Array.isArray(response.data?.department_ids)
          ? response.data.department_ids.map((id) => Number(id)).filter((id) => Number.isInteger(id))
          : [];
      } catch (error) {
        console.error('Error loading attendance settings:', error);
      } finally {
        attendanceSettingsLoading.value = false;
      }
    };

    const loadDepartmentOptions = async () => {
      if (!isSuperAdmin.value) return;
      try {
        const response = await api.get('/attendance/departments');
        const rows = Array.isArray(response.data) ? response.data : (response.data?.data || []);

        departmentOptions.value = rows
          .map((dept) => ({
            value: Number(dept?.id),
            label: String(dept?.name || `Department #${dept?.id || ''}`)
          }))
          .filter((dept) => Number.isInteger(dept.value));
      } catch (error) {
        console.error('Error loading department options:', error);
        departmentOptions.value = [];
      }
    };

    const loadAttendanceStatus = async () => {
      try {
        const response = await api.get('/attendance/status');

        attendanceStatus.is_active_day = !!response.data?.is_active_day;
        attendanceStatus.is_department_active = response.data?.is_department_active !== false;
        attendanceStatus.is_within_time_window = !!response.data?.is_within_time_window;
        attendanceStatus.already_checked_in = !!response.data?.already_checked_in;
        attendanceStatus.check_in_at = response.data?.check_in_at;
        attendanceStatus.status = response.data?.status || 'Closed';
        attendanceStatus.window_label = response.data?.window_label || 'Not configured';
        attendanceStatus.today_code = response.data?.today_code || '';
      } catch (error) {
        console.error('Error loading attendance status:', error);
      }
    };

    const saveAttendanceSettings = async () => {
      if (!isSuperAdmin.value) return;
      attendanceSettingsSaving.value = true;
      try {
        const response = await api.put('/attendance/settings', {
          day_of_week: Number(attendanceSettings.day_of_week),
          start_time: attendanceSettings.start_time,
          end_time: attendanceSettings.end_time,
          department_ids: attendanceSettings.department_ids.map((id) => Number(id)).filter((id) => Number.isInteger(id)),
        });

        if (response.data?.success || response.data?.status) {
          showNotification('Attendance settings saved successfully!', 'success');
          await loadAttendanceStatus();
        }
      } catch (error) {
        showNotification(error?.response?.data?.message || 'Failed to save attendance settings', 'error');
      } finally {
        attendanceSettingsSaving.value = false;
      }
    };

    const toggleDepartmentSelection = (departmentId) => {
      const id = Number(departmentId);
      if (!Number.isInteger(id)) return;

      const current = attendanceSettings.department_ids.map((item) => Number(item)).filter((item) => Number.isInteger(item));
      const exists = current.includes(id);
      attendanceSettings.department_ids = exists
        ? current.filter((item) => item !== id)
        : [...current, id];
    };

    const submitCheckin = async () => {
      if (checkinSubmitting.value || !isCheckinCodeComplete.value) return;
      checkinSubmitting.value = true;
      try {
        const response = await api.post('/attendance/check-in', {
          code: String(checkinCode.value || '').trim().toUpperCase(),
        });

        if (response.data?.success || response.data?.status) {
          showNotification(response.data?.message || 'Checked in successfully', 'success');
          checkinCode.value = '';
          await loadAttendanceStatus();
        } else {
          showNotification(response.data?.message || 'Check-in failed', 'error');
        }
      } catch (error) {
        showNotification(error?.response?.data?.message || 'Check-in failed', 'error');
      } finally {
        checkinSubmitting.value = false;
      }
    };
    
    onMounted(() => {
      loadUserData();
      loadVacationData();
      loadAttendanceStatus();
    });

    watch(isSuperAdmin, (value) => {
      if (value) {
        loadDepartmentOptions();
        loadAttendanceSettings();
      }
    }, { immediate: true });
    
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
      attendanceSettingsLoading,
      attendanceSettingsSaving,
      attendanceSettings,
      attendanceStatus,
      departmentOptions,
      checkinSubmitting,
      checkinCode,
      dayOptions,
      isSuperAdmin,
      userInitials,
      isCheckinCodeComplete,
      selectedDepartmentLabels,
      checkinBadgeClass,
      currentPasswordVisible,
      newPasswordVisible,
      confirmPasswordVisible,
      updateProfile,
      changePassword,
      resetForm,
      onImageChange,
      loadVacationData,
      saveVacationMode,
      saveAttendanceSettings,
      toggleDepartmentSelection,
      submitCheckin,
      currentDelegate,
      formatDate
    };
  }
};
</script>
