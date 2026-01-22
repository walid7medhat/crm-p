<template>
  <section class="login">
   

   
       <div class="login-card">
         <div class=" mx-auto w-100">
           <div class="logo">
              <router-link to="/" class="mb-40 max-w-290-px">
                  <img :src="logo" alt=""  class="main-logo">
                </router-link>
         
            </div>
       <div class="form">
        <!-- Success Message بعد التسجيل -->
        <div v-if="registrationSuccess" class="alert alert-success text-center">
          <h5 class="mb-3">🎉 Registration Successful!</h5>
          <p class="mb-3">Your account has been created successfully and is pending activation.</p>
          <p class="mb-3"><strong>Your supervisor will activate your account shortly.</strong></p>
          <p class="mb-0">You will receive an email notification once your account is activated.</p>
          <hr>
          <button @click="goToLogin" class="btn btn-primary btn-sm">
            Go to Sign In
          </button>
        </div>
         
        <!-- Registration Form -->
        <form v-else @submit.prevent="register">
           <h4 class="mb-12 titleH">Create your account</h4>
          <!-- <p class="mb-32 titleH2">Set up your profile to access the internal sales system</p> -->
          <div class="icon-field mb-16">
            <span class="icon top-50 translate-middle-y">
              <iconify-icon icon="f7:person"></iconify-icon>
            </span>
            <input
              type="text"
              class="form-control h-56-px bg-neutral-50 radius-12"
              :class="{ 'is-invalid': errors.name }"
              placeholder="Full Name"
              v-model="name"
              required
            />
            <div v-if="errors.name" class="invalid-feedback d-block">
              {{ errors.name[0] }}
            </div>
          </div>

          <div class="icon-field mb-16">
            <span class="icon top-50 translate-middle-y">
              <iconify-icon icon="mage:email"></iconify-icon>
            </span>
            <input
              type="email"
              class="form-control h-56-px bg-neutral-50 radius-12"
              :class="{ 'is-invalid': errors.email }"
              placeholder="Email"
              v-model="email"
              required
            />
            <div v-if="errors.email" class="invalid-feedback d-block">
              {{ errors.email[0] }}
            </div>
          </div>

          
          <div class="position-relative mb-20">
            <div class="icon-field">
              <span class="icon top-50 translate-middle-y">
                <iconify-icon icon="solar:lock-password-outline"></iconify-icon>
              </span>
              <input
                :type="showPassword ? 'text' : 'password'"
                class="form-control h-56-px bg-neutral-50 radius-12"
                id="your-password"
                placeholder="Password"
                v-model="password"
                required
              />
            </div>
            <span
              class="toggle-password ri-eye-line cursor-pointer position-absolute end-0 top-50 translate-middle-y me-16 text-secondary-light"
              :class="{ 'ri-eye-off-line': showPassword }"
              @click="togglePassword"
            ></span>
          </div>
             <span class=" passV text-white">Password must be at least 8 characters</span>
           <div v-if="errors.password" class="invalid-feedback d-block">
              {{ errors.password[0] }}
            </div>

          <!-- Supervisor Selection - جنب بعض -->
          <!-- <div class="mb-16">
            <label class="form-label fw-semibold mb-8">Select Your Supervisor</label>
            
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label small text-muted mb-2">Manager *</label>
                <select 
                  class="form-select bg-neutral-50 radius-12" 
                  :class="{ 'is-invalid': errors.manager_id || managerError }"
                  v-model="manager_id" 
                  :disabled="loadingParents"
                  @change="onManagerChange"
                  required
                >
                  <option value="" disabled selected>Choose manager</option>
                  <option v-for="manager in parents.managers" :key="'m-'+manager.id" :value="manager.id">
                    {{ manager.name }}
                  </option>
                </select>
                <div v-if="errors.manager_id" class="invalid-feedback d-block">
                  {{ errors.manager_id[0] }}
                </div>
                <div v-if="managerError" class="invalid-feedback d-block">
                  {{ managerError }}
                </div>
                <small class="text-muted" v-if="parents.managers.length === 0 && !loadingParents">
                  No managers available
                </small>
              </div>

              <div class="col-md-6">
                <label class="form-label small text-muted mb-2">Team Lead</label>
                <select 
                  class="form-select bg-neutral-50 radius-12" 
                  :class="{ 'is-invalid': errors.team_lead_id }"
                  v-model="team_lead_id" 
                  :disabled="loadingParents || !manager_id"
                >
                  <option value="" selected>Choose team lead</option>
                  <option v-for="teamLead in filteredTeamLeads" :key="'tl-'+teamLead.id" :value="teamLead.id">
                    {{ teamLead.name }}
                  </option>
                </select>
                <div v-if="errors.team_lead_id" class="invalid-feedback d-block">
                  {{ errors.team_lead_id[0] }}
                </div>
                <small class="text-muted" v-if="manager_id && filteredTeamLeads.length === 0">
                  No team leads under this manager
                </small>
                <small class="text-muted" v-else-if="!manager_id">
                  Select a manager first
                </small>
              </div>
            </div>
          </div> -->

          <!-- Selected Supervisor Info -->
          <!-- <div v-if="selectedSupervisor" class="mb-16 p-3 bg-light rounded">
            <h6 class="mb-2">Selected Supervisor:</h6>
            <div class="d-flex align-items-center">
              <div class="flex-grow-1">
                <strong>{{ selectedSupervisor.name }}</strong> - {{ selectedSupervisor.email }}
                <br>
                <small class="text-muted">{{ selectedSupervisor.role }}</small>
              </div>
              <span class="badge bg-success">{{ selectedSupervisor.type }}</span>
            </div>
          </div> -->

          <!-- Debug Information -->
          <!-- <div v-if="debugMode" class="mb-16 p-3 bg-warning rounded">
            <h6 class="mb-2">Debug Information:</h6>
            <p><strong>Manager ID:</strong> {{ manager_id }} (Type: {{ typeof manager_id }})</p>
            <p><strong>Team Lead ID:</strong> {{ team_lead_id }} (Type: {{ typeof team_lead_id }})</p>
            <p><strong>Parent ID:</strong> {{ parent_id }} (Type: {{ typeof parent_id }})</p>
            <p><strong>Managers:</strong> {{ parents.managers.length }}</p>
            <p><strong>Team Leads:</strong> {{ parents.teamLeads.length }}</p>
          </div> -->

          <button
            type="submit"
            class="btn btn-primary text-sm btn-sm px-12 py-16 w-100 radius-12 mt-32"
            :disabled="loading || loadingParents"
          >
            <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
            {{ loading ? 'Creating Account...' : 'Sign Up as Sales Agent' }}
          </button>

         
        </form>

         <div class="mt-32 text-center text-sm">
            <p class="mb-0 text-white">Already have an account? 
              <a href="/sign-in" class="text-white fw-semibold">Sign In</a>
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script>
import api from '@/plugins/axios';
import { useRouter } from 'vue-router';

export default {
  name: 'SignUp',
  data() {
    return {
      name: '',
      email: '',
      password: '',
      manager_id: '',
      team_lead_id: '',
      showPassword: false,
      loading: false,
      loadingParents: false,
        logo: '/assets/images/LogoWhite.png',
      parents: {
        managers: [],
        teamLeads: []
      },
      // Errors handling
      errors: {},
      managerError: '',
      // Success state
      registrationSuccess: false,
      // Debug mode
      debugMode: process.env.NODE_ENV === 'development'
    };
  },
  setup() {
    const router = useRouter();
    return { router };
  },
  computed: {
    filteredTeamLeads() {
      if (!this.manager_id) return [];
      
      return this.parents.teamLeads.filter(teamLead => {
        const teamLeadManagerId = Number(teamLead.manager_id);
        const selectedManagerId = Number(this.manager_id);
        
        console.log('Team Lead:', teamLead.name, 'Manager ID:', teamLeadManagerId, 'Selected Manager:', selectedManagerId);
        return teamLeadManagerId === selectedManagerId;
      });
    },
    
    selectedSupervisor() {
      if (this.team_lead_id) {
        const teamLead = this.parents.teamLeads.find(tl => {
          return Number(tl.id) === Number(this.team_lead_id);
        });
        if (teamLead) {
          return {
            ...teamLead,
            role: 'Team Lead',
            type: 'Direct Supervisor'
          };
        }
      } else if (this.manager_id) {
        const manager = this.parents.managers.find(m => {
          return Number(m.id) === Number(this.manager_id);
        });
        if (manager) {
          return {
            ...manager,
            role: 'Manager',
            type: 'Direct Supervisor'
          };
        }
      }
      return null;
    },
    
    parent_id() {
      return this.team_lead_id || this.manager_id;
    }
  },
  watch: {
    manager_id(newManagerId, oldManagerId) {
      if (newManagerId !== oldManagerId) {
        this.team_lead_id = '';
        this.managerError = ''; 
      }
    }
  },
  async mounted() {
    await this.fetchParents();
  },
  methods: {
    togglePassword() {
      this.showPassword = !this.showPassword;
    },

    async fetchParents() {
      this.loadingParents = true;
      
      try {
        console.log('Fetching parents...');
        
        const [managersRes, teamLeadsRes] = await Promise.all([
          api.get('/auth/users/role/manager'),
          api.get('/auth/users/role/team_lead')
        ]);
        
        console.log('Managers raw response:', managersRes.data);
        console.log('Team Leads raw response:', teamLeadsRes.data);
        
        this.parents.managers = this.processManagersData(managersRes.data);
        this.parents.teamLeads = this.processTeamLeadsData(teamLeadsRes.data);
        
        console.log('Processed managers:', this.parents.managers);
        console.log('Processed team leads:', this.parents.teamLeads);
        
      } catch (error) {
        console.error('Error fetching parents:', error);
        this.$showNotification('Failed to load supervisors list', 'error');
      } finally {
        this.loadingParents = false;
      }
    },
    
    processManagersData(data) {
      if (data && data.success && data.data) {
        return data.data.map(item => ({
          id: item.id,
          name: item.name || 'Unknown Name',
          email: item.email || 'No Email',
          role: 'manager'
        }));
      }
      
      if (Array.isArray(data)) {
        return data.map(item => ({
          id: item.id,
          name: item.name || 'Unknown Name',
          email: item.email || 'No Email',
          role: 'manager'
        }));
      }
      
      console.warn('Unexpected data format for managers:', data);
      return [];
    },

    processTeamLeadsData(data) {
      if (data && data.success && data.data) {
        return data.data.map(item => ({
          id: item.id,
          name: item.name || 'Unknown Name',
          email: item.email || 'No Email',
          role: 'team_lead',
          manager_id: item.manager_id || item.parent_id || null
        }));
      }
      
      if (Array.isArray(data)) {
        return data.map(item => ({
          id: item.id,
          name: item.name || 'Unknown Name',
          email: item.email || 'No Email',
          role: 'team_lead',
          manager_id: item.manager_id || item.parent_id || null
        }));
      }
      
      console.warn('Unexpected data format for team leads:', data);
      return [];
    },

    onManagerChange() {
      this.team_lead_id = '';
      console.log('Manager changed to:', this.manager_id, 'Type:', typeof this.manager_id);
      console.log('Available team leads:', this.filteredTeamLeads);
    },

    clearErrors() {
      this.errors = {};
      this.managerError = '';
    },

    showValidationErrors() {
      for (const [field, messages] of Object.entries(this.errors)) {
        if (messages && messages.length > 0) {
          this.$showNotification(messages[0], 'error');
          break;
        }
      }
    },

    goToLogin() {
      this.router.push('/sign-in');
    },

    validateForm() {
      this.clearErrors();
      let isValid = true;

      // Frontend validation
      if (this.password.length < 8) {
        this.errors.password = ['Password must be at least 8 characters'];
        this.$showNotification('Password must be at least 8 characters', 'warning');
        isValid = false;
      }
      
      // if (!this.manager_id) {
      //   this.managerError = 'Please select your manager';
      //   this.$showNotification('Please select your manager', 'warning');
      //   isValid = false;
      // } else {
      //   const managerExists = this.parents.managers.some(manager => 
      //     Number(manager.id) === Number(this.manager_id)
      //   );
        
      //   if (!managerExists) {
      //     this.managerError = 'Selected manager is not valid';
      //     this.$showNotification('Selected manager is not valid', 'warning');
      //     isValid = false;
      //   }
      // }

      return isValid;
    },

    async register() {
      this.loading = true;
      
      // التحقق من صحة النموذج أولاً
      if (!this.validateForm()) {
        this.loading = false;
        return;
      }

      try {
        // تحويل القيم إلى الأرقام قبل الإرسال
        const payload = {
          name: this.name,
          email: this.email,
          password: this.password,
          parent_id: Number(this.parent_id) // تأكد من إرسال رقم
        };

        console.log('📤 Sending registration payload:', payload);

        const response = await api.post('/auth/register', payload);

        console.log('✅ Registration response:', response.data);

        // عرض رسالة النجاح بدلاً من تسجيل الدخول التلقائي
        this.registrationSuccess = true;
        
        this.$showNotification('Account created successfully! Pending activation by supervisor.', 'success');

      } catch (error) {
        console.log('❌ Registration error:', error);
        this.handleApiError(error);
      } finally {
        this.loading = false;
      }
    },

    handleApiError(error) {
      if (error.response && error.response.data) {
        const responseData = error.response.data;
        
        if (error.response.status === 422 && responseData.errors) {
          this.errors = responseData.errors;
          console.log('Validation errors:', this.errors);
          this.showValidationErrors();
        } 
        else if (responseData.message) {
          this.$showNotification(responseData.message, 'error');
        }
        else if (responseData.success === false && responseData.message) {
          this.$showNotification(responseData.message, 'error');
        }
        else {
          this.$showNotification('Registration failed. Please try again.', 'error');
        }
      } else if (error.request) {
        this.$showNotification('Network error. Please check your connection.', 'error');
      } else {
        this.$showNotification('An unexpected error occurred.', 'error');
      }
    }
  }
};
</script>

<style scoped>
.is-invalid {
  border-color: #dc3545 !important;
}

.invalid-feedback {
  display: block;
  width: 100%;
  margin-top: 0.25rem;
  font-size: 0.875em;
  color: #dc3545;
}



/* تصميم رسالة النجاح */
.alert-success {
  border-radius: 12px;
  border: 1px solid #d1e7dd;
  background-color: #d1e7dd;
  color: #0f5132;
  padding: 20px;
}

.alert-success h5 {
  color: #0f5132;
  font-weight: bold;
}

.alert-success hr {
  border-color: #0f5132;
  opacity: 0.3;
}


/* Debug information styling */
.bg-warning {
  background-color: #fff3cd !important;
  border: 1px solid #ffeaa7;
}
.titleSignup{
  font-size: 28px !important;
}
.login {
  background-color: #01062C !important;
  height: 100vh;
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.login-card {
  width: 100%;
  max-width: 586px; /* كما في الصورة */
  background: transparent;
}

.max-w-464-px {
  max-width: 464px;
  margin: 0 auto;
}

.form {
  /* التدرج اللوني - تم تعديله لتطابق الصورة */
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.5));
  
  /* الحدود */
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  
  /* الظلال والتأثيرات */
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
  backdrop-filter: blur(40px);
  
  /* محاذاة داخلية */
  padding: 40px 32px;
  
  /* تحسينات إضافية */
  box-sizing: border-box;
}

.titleH {
  font-size: 28px;
  font-weight: 700;
  color: #FFFFFF;
  margin-bottom: 12px;
  text-align: center;
}

.titleH2 {
  font-size: 16px;
  font-weight: 400;
  color: #FFFFFF;
  margin-bottom: 32px;
  text-align: center;
}

/* تحسين حقول الإدخال */
.icon-field {
  position: relative;
}

.icon-field .icon {
  position: absolute;
  left: 16px;
  color: #666;
  z-index: 2;
}

.icon-field input {
  padding-left: 48px !important;
  background-color: rgba(255, 255, 255, 0.3);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px !important;
  color: #01062C !important;
  font-size: 14px;
}

.icon-field input::placeholder {
  color: #888;
}

.icon-field input:focus {
  border-color: #01062C !important;
  box-shadow: 0 0 0 3px rgba(1, 6, 44, 0.1) !important;
}

/* زر تسجيل الدخول */
.btn-primary {
  background-color: #01062C !important;
  border: none !important;
  border-radius: 8px !important;
  font-weight: 600;
  font-size: 16px;
  padding: 16px !important;
  transition: all 0.3s ease;
}

.btn-primary:hover {
  background-color: #020a4a !important;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(1, 6, 44, 0.2);
}

.btn-primary:disabled {
  background-color: #666 !important;
  opacity: 0.7;
}

/* Remember me checkbox */
.form-check-input:checked {
  background-color: #01062C;
  border-color: #01062C;
}

.form-check-label {
  color: #333;
  font-size: 14px;
}

/* الروابط */
.text-primary-600 {
  color: #01062C !important;
  font-weight: 600;
  text-decoration: none;
}

.text-primary-600:hover {
  text-decoration: underline;
}

/* رسالة الخطأ */
.text-danger {
  background: rgba(220, 53, 69, 0.1);
  padding: 12px;
  border-radius: 8px;
  border-left: 4px solid #dc3545;
}

/* زر إظهار/إخفاء كلمة المرور */
.toggle-password {
  color: #666;
  font-size: 18px;
}

.toggle-password:hover {
  color: #01062C;
}

/* الشعار */
.logo{
    text-align: center;
  justify-content: center;
}
.main-logo {
  height: 60px;
  width: auto;
  display: block;
  margin-left: auto;
  margin-right: auto;
  text-align: center;
  justify-content: center;
}

/* التجاوب */
@media (max-width: 768px) {
  .form {
    padding: 32px 24px;
  }
  
  .login-card {
    margin: 20px;
  }
  
  .titleH {
    font-size: 24px;
  }
  
  .titleH2 {
    font-size: 14px;
  }
}

@media (max-width: 480px) {
  .form {
    padding: 24px 16px;
  }
  
  .login {
    padding: 16px;
  }
}
.passV{
  font-weight: 400;
  font-size: 16px;
}
</style>