<!-- resources/js/Pages/Auth/Register.vue -->
<template>
  <div class="register-container">
    <div class="card mx-auto" style="max-width: 500px;">
      <div class="card-header">
        <h6 class="ui-h-sub text-center">Complete Your Registration</h6>
      </div>
      <div class="card-body">
        
        <!-- Invitation Info -->
        <div v-if="invitation" class="alert alert-info">
          <h6>You're invited to join!</h6>
          <p><strong>Email:</strong> {{ invitation.email }}</p>
          <p><strong>Invited by:</strong> {{ invitation.inviter?.name || 'System Admin' }}</p>
        </div>

        <!-- Error Message -->
        <div v-if="error" class="alert alert-danger">
          {{ error }}
        </div>

        <!-- Registration Form -->
        <form @submit.prevent="register" v-if="invitation && !success">
          <div class="mb-3">
            <label for="name" class="form-label">Full Name</label>
            <input
              type="text"
              class="form-control"
              id="name"
              v-model="form.name"
              required
              :disabled="loading"
            >
          </div>

          <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <input
              type="email"
              class="form-control"
              id="email"
              :value="invitation.email"
              disabled
            >
            <small class="text-muted">This email was used for your invitation</small>
          </div>

          <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input
              type="password"
              class="form-control"
              id="password"
              v-model="form.password"
              required
              :disabled="loading"
              minlength="8"
            >
          </div>

          <div class="mb-3">
            <label for="password_confirmation" class="form-label">Confirm Password</label>
            <input
              type="password"
              class="form-control"
              id="password_confirmation"
              v-model="form.password_confirmation"
              required
              :disabled="loading"
            >
          </div>

          <button 
            type="submit" 
            class="btn btn-primary w-100"
            :disabled="loading"
          >
            <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
            {{ loading ? 'Creating Account...' : 'Create Account' }}
          </button>
        </form>

        <!-- Success Message -->
        <div v-if="success" class="alert alert-success text-center">
          <h6 class="ui-h-mini">🎉 Account Created Successfully!</h6>
          <p>You can now login with your new account.</p>
          <router-link to="/login" class="btn btn-success">Go to Login</router-link>
        </div>

      </div>
    </div>
  </div>
</template>

<script>
import api from '@/utils/axios';

export default {
  name: 'RegisterWithInvitation',
  data() {
    return {
      invitation: null,
      loading: false,
      error: '',
      success: false,
      form: {
        name: '',
        password: '',
        password_confirmation: '',
        token: this.$route.params.token
      }
    };
  },
  async mounted() {
    await this.validateInvitation();
  },
  methods: {
    async validateInvitation() {
      try {
        const response = await api.get(`/invitation/${this.form.token}`);
        this.invitation = response.data.invitation;
        
        if (!this.invitation.is_valid) {
          this.error = 'This invitation is no longer valid. Please request a new one.';
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Invalid invitation link';
      }
    },

    async register() {
      this.loading = true;
      this.error = '';

      try {
        const response = await api.post('/register', this.form);
        
        if (response.data.success) {
          this.success = true;
          this.invitation.used = true;
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Registration failed. Please try again.';
        
        if (error.response?.data?.errors) {
          const errors = error.response.data.errors;
          this.error = Object.values(errors).flat().join(', ');
        }
      } finally {
        this.loading = false;
      }
    }
  }
};
</script>

<style scoped>
.register-container {
  padding: 50px 20px;
  min-height: 100vh;
  background: #f8f9fa;
}
</style>