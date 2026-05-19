<template>
  <AuthLandingShell>
    <div class="auth-glass-card auth-glass-card--compact">
      <h6 class="auth-glass-card__eyebrow">Welcome To</h6>
      <h6 class="auth-glass-card__title">OIA PROPERTIES</h6>

      <div v-if="registrationSuccess" class="auth-glass-success">
        <h6>Registration Successful</h6>
        <span class="auth-glass-success__note">Pending supervisor activation.</span>
        <button type="button" class="auth-glass-btn auth-glass-btn--primary auth-glass-btn--block" @click="goToLogin">
          Sign In
        </button>
      </div>

      <form v-else class="auth-glass-form" @submit.prevent="register">
        <div class="auth-glass-field">
          <label class="auth-glass-field__label" for="signup-name">Name</label>
          <div class="auth-glass-input-wrap">
            <input
              id="signup-name"
              v-model="name"
              type="text"
              class="auth-glass-input"
              :class="{ 'is-invalid': errors.name }"
              placeholder="Enter your name"
              required
            />
            <span class="auth-glass-input__icon" aria-hidden="true">
              <iconify-icon icon="mdi:account-outline" />
            </span>
          </div>
          <span v-if="errors.name" class="auth-glass-field-error">{{ errors.name[0] }}</span>
        </div>

        <div class="auth-glass-field">
          <label class="auth-glass-field__label" for="signup-email">Email</label>
          <div class="auth-glass-input-wrap">
            <input
              id="signup-email"
              v-model="email"
              type="email"
              class="auth-glass-input"
              :class="{ 'is-invalid': errors.email }"
              placeholder="name@oiaproperties.com"
              required
              @input="validateEmailDomain"
            />
            <span class="auth-glass-input__icon" aria-hidden="true">
              <iconify-icon icon="mage:email" />
            </span>
          </div>
          <span v-if="errors.email" class="auth-glass-field-error">{{ errors.email[0] }}</span>
        </div>

        <div class="auth-glass-field">
          <label class="auth-glass-field__label" for="signup-password">Password</label>
          <div class="auth-glass-input-wrap">
            <input
              id="signup-password"
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              class="auth-glass-input"
              :class="{ 'is-invalid': errors.password }"
              placeholder="Enter Password"
              required
            />
            <button
              type="button"
              class="auth-glass-input__icon auth-glass-input__icon--clickable"
              :aria-label="showPassword ? 'Hide password' : 'Show password'"
              @click="togglePassword"
            >
              <iconify-icon
                :icon="showPassword ? 'mdi:eye-outline' : 'mdi:eye-off-outline'"
              />
            </button>
          </div>
          <span v-if="errors.password" class="auth-glass-field-error">{{ errors.password[0] }}</span>
        </div>

        <div class="auth-glass-actions">
          <button type="button" class="auth-glass-btn auth-glass-btn--ghost" @click="clearForm">
            Clear
          </button>
          <button
            type="submit"
            class="auth-glass-btn auth-glass-btn--primary"
            :disabled="loading || loadingParents"
          >
            <span v-if="loading" class="auth-glass-spinner" aria-hidden="true" />
            {{ loading ? '...' : 'Sign Up' }}
          </button>
        </div>

        <div class="auth-glass-divider" role="separator">
          <span>Or</span>
        </div>

        <router-link to="/sign-in" class="auth-glass-pill-link">
          already have account? <b>Sign In</b>
        </router-link>

        <router-link to="/privacy-policy" class="auth-glass-footer-link">Privacy Policy</router-link>
      </form>
    </div>
  </AuthLandingShell>
</template>

<script>
import api from '@/plugins/axios';
import { useRouter } from 'vue-router';
import AuthLandingShell from './AuthLandingShell.vue';

export default {
  name: 'SignUp',
  components: {
    AuthLandingShell,
  },
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
      parents: {
        managers: [],
        teamLeads: [],
      },
      errors: {},
      managerError: '',
      registrationSuccess: false,
      debugMode: process.env.NODE_ENV === 'development',
    };
  },
  setup() {
    const router = useRouter();
    return { router };
  },
  computed: {
    filteredTeamLeads() {
      if (!this.manager_id) return [];

      return this.parents.teamLeads.filter((teamLead) => {
        const teamLeadManagerId = Number(teamLead.manager_id);
        const selectedManagerId = Number(this.manager_id);
        return teamLeadManagerId === selectedManagerId;
      });
    },

    selectedSupervisor() {
      if (this.team_lead_id) {
        const teamLead = this.parents.teamLeads.find((tl) => {
          return Number(tl.id) === Number(this.team_lead_id);
        });
        if (teamLead) {
          return {
            ...teamLead,
            role: 'Team Lead',
            type: 'Direct Supervisor',
          };
        }
      } else if (this.manager_id) {
        const manager = this.parents.managers.find((m) => {
          return Number(m.id) === Number(this.manager_id);
        });
        if (manager) {
          return {
            ...manager,
            role: 'Manager',
            type: 'Direct Supervisor',
          };
        }
      }
      return null;
    },

    parent_id() {
      return this.team_lead_id || this.manager_id;
    },
  },
  watch: {
    manager_id(newManagerId, oldManagerId) {
      if (newManagerId !== oldManagerId) {
        this.team_lead_id = '';
        this.managerError = '';
      }
    },
  },
  async mounted() {
    await this.fetchParents();
  },
  methods: {
    togglePassword() {
      this.showPassword = !this.showPassword;
    },

    clearForm() {
      this.name = '';
      this.email = '';
      this.password = '';
      this.manager_id = '';
      this.team_lead_id = '';
      this.clearErrors();
    },

    async fetchParents() {
      this.loadingParents = true;

      try {
        const [managersRes, teamLeadsRes] = await Promise.all([
          api.get('/auth/users/role/manager'),
          api.get('/auth/users/role/team_lead'),
        ]);

        this.parents.managers = this.processManagersData(managersRes.data);
        this.parents.teamLeads = this.processTeamLeadsData(teamLeadsRes.data);
      } catch (error) {
        this.$showNotification('Failed to load supervisors list', 'error');
      } finally {
        this.loadingParents = false;
      }
    },

    processManagersData(data) {
      if (data && data.success && data.data) {
        return data.data.map((item) => ({
          id: item.id,
          name: item.name || 'Unknown Name',
          email: item.email || 'No Email',
          role: 'manager',
        }));
      }

      if (Array.isArray(data)) {
        return data.map((item) => ({
          id: item.id,
          name: item.name || 'Unknown Name',
          email: item.email || 'No Email',
          role: 'manager',
        }));
      }

      return [];
    },

    processTeamLeadsData(data) {
      if (data && data.success && data.data) {
        return data.data.map((item) => ({
          id: item.id,
          name: item.name || 'Unknown Name',
          email: item.email || 'No Email',
          role: 'team_lead',
          manager_id: item.manager_id || item.parent_id || null,
        }));
      }

      if (Array.isArray(data)) {
        return data.map((item) => ({
          id: item.id,
          name: item.name || 'Unknown Name',
          email: item.email || 'No Email',
          role: 'team_lead',
          manager_id: item.manager_id || item.parent_id || null,
        }));
      }

      return [];
    },

    onManagerChange() {
      this.team_lead_id = '';
    },

    validateEmailDomain() {
      if (this.email && !this.email.endsWith('@oiaproperties.com')) {
        this.errors.email = ['Email must be from @oiaproperties.com domain'];
        return false;
      }
      if (this.errors.email && this.email.endsWith('@oiaproperties.com')) {
        const { email, ...rest } = this.errors;
        this.errors = rest;
      }
      return true;
    },

    clearErrors() {
      this.errors = {};
      this.managerError = '';
    },

    goToLogin() {
      this.router.push('/sign-in');
    },

    showValidationErrors() {
      for (const [, messages] of Object.entries(this.errors)) {
        if (messages && messages.length > 0) {
          this.$showNotification(messages[0], 'error');
          break;
        }
      }
    },

    validateForm() {
      this.clearErrors();
      let isValid = true;

      if (!this.name?.trim()) {
        this.errors.name = ['Name is required'];
        this.$showNotification('Name is required', 'warning');
        isValid = false;
      }

      if (!this.email) {
        this.errors.email = ['Email is required'];
        this.$showNotification('Email is required', 'warning');
        isValid = false;
      } else if (!this.email.endsWith('@oiaproperties.com')) {
        this.errors.email = ['Email must be from @oiaproperties.com domain'];
        this.$showNotification('Email must be from @oiaproperties.com domain', 'warning');
        isValid = false;
      } else if (!this.email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
        this.errors.email = ['Please enter a valid email address'];
        this.$showNotification('Please enter a valid email address', 'warning');
        isValid = false;
      }

      if (this.password.length < 8) {
        this.errors.password = ['Password must be at least 8 characters'];
        this.$showNotification('Password must be at least 8 characters', 'warning');
        isValid = false;
      }

      return isValid;
    },

    async register() {
      this.loading = true;

      if (this.email && !this.email.endsWith('@oiaproperties.com')) {
        this.$showNotification('Email must be from @oiaproperties.com domain', 'warning');
        this.errors.email = ['Email must be from @oiaproperties.com domain'];
        this.loading = false;
        return;
      }

      if (!this.validateForm()) {
        this.loading = false;
        return;
      }

      try {
        const payload = {
          name: `${this.name}`.trim(),
          email: this.email,
          password: this.password,
          parent_id: Number(this.parent_id),
        };

        await api.post('/auth/register', payload);

        this.registrationSuccess = true;

        this.$showNotification(
          'Account created successfully! Pending activation by supervisor.',
          'success'
        );
      } catch (error) {
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
          this.showValidationErrors();
        } else if (responseData.message) {
          this.$showNotification(responseData.message, 'error');
        } else if (responseData.success === false && responseData.message) {
          this.$showNotification(responseData.message, 'error');
        } else {
          this.$showNotification('Registration failed. Please try again.', 'error');
        }
      } else if (error.request) {
        this.$showNotification('Network error. Please check your connection.', 'error');
      } else {
        this.$showNotification('An unexpected error occurred.', 'error');
      }
    },
  },
};
</script>

<style src="./auth-glass-shared.css"></style>

<style scoped>
.auth-glass-form {
  display: block;
}

.auth-glass-spinner {
  display: inline-block;
  width: 14px;
  height: 14px;
  margin-right: 6px;
  border: 2px solid rgba(43, 20, 88, 0.25);
  border-top-color: #2b1458;
  border-radius: 50%;
  animation: auth-glass-spin 0.7s linear infinite;
  vertical-align: -2px;
}

@keyframes auth-glass-spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
