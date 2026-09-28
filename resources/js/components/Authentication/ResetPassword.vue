<template>
  <AuthLandingShell>
    <div class="auth-glass-card auth-glass-card--compact">
      <h6 class="auth-glass-card__eyebrow">Welcome To</h6>
      <h6 class="auth-glass-card__title">OIA PROPERTIES</h6>

      <div class="auth-reset-heading">
        <h6 class="auth-reset-heading__title">Reset Password</h6>
        <p class="auth-reset-heading__subtitle">Enter your new password</p>
      </div>

      <form class="auth-glass-form" @submit.prevent="resetPassword">
        <div class="auth-glass-field">
          <label class="auth-glass-field__label" for="reset-password">New Password</label>
          <div class="auth-glass-input-wrap">
            <input
              id="reset-password"
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              class="auth-glass-input"
              placeholder="New Password"
              autocomplete="new-password"
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
        </div>

        <div class="auth-glass-field">
          <label class="auth-glass-field__label" for="reset-password-confirm">Confirm Password</label>
          <div class="auth-glass-input-wrap">
            <input
              id="reset-password-confirm"
              v-model="password_confirmation"
              :type="showPassword ? 'text' : 'password'"
              class="auth-glass-input"
              placeholder="Confirm Password"
              autocomplete="new-password"
              required
            />
          </div>
        </div>

        <div v-if="message" class="auth-glass-error" role="alert">
          {{ message }}
        </div>

        <div class="auth-glass-actions">
          <button type="submit" class="auth-glass-btn auth-glass-btn--primary auth-glass-btn--block">
            Reset Password
          </button>
        </div>

        <router-link to="/sign-in" class="auth-glass-pill-link">
          Back to <b>Sign In</b>
        </router-link>
      </form>
    </div>
  </AuthLandingShell>
</template>

<script>
import api from '@/plugins/axios';
import AuthLandingShell from './AuthLandingShell.vue';

export default {
  components: {
    AuthLandingShell,
  },
  data() {
    return {
      email: '',
      password: '',
      password_confirmation: '',
      token: '',
      message: '',
      showPassword: false,
    };
  },
  mounted() {
    this.token = this.$route.query.token;
    this.email = this.$route.query.email;
  },
  methods: {
    togglePassword() {
      this.showPassword = !this.showPassword;
    },
    async resetPassword() {
      // Validate passwords match
      if (this.password !== this.password_confirmation) {
        this.message = 'Passwords do not match';
        return;
      }

      // Validate password length
      if (this.password.length < 6) {
        this.message = 'Password must be at least 6 characters';
        return;
      }

      try {
        const res = await api.post('/auth/reset-password', {
          email: this.email,
          password: this.password,
          password_confirmation: this.password_confirmation,
          token: this.token,
        });

        this.message = res.data.message;

        // Optional: redirect to login after successful reset
        if (res.data.success) {
          setTimeout(() => {
            this.$router.push('/sign-in');
          }, 2000);
        }
      } catch (e) {
        this.message = e.response?.data?.message || 'Error resetting password';
      }
    },
  },
};
</script>

<style src="./auth-glass-shared.css"></style>
<style scoped>
.auth-reset-heading {
  margin-bottom: clamp(16px, 2.5vh, 22px);
  text-align: center;
}

.auth-reset-heading__title {
  margin: 0 0 6px;
  font-size: 1.1rem;
  font-weight: 700;
  color: #0f172a;
}

.auth-reset-heading__subtitle {
  margin: 0;
  font-size: 0.85rem;
  color: #64748b;
}

@media (max-width: 768px) {
  .auth-reset-heading__title {
    color: #fff;
  }

  .auth-reset-heading__subtitle {
    color: rgba(255, 255, 255, 0.7);
  }
}
</style>
