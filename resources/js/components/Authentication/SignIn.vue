<template>
  <AuthLandingShell>
    <div class="auth-glass-card auth-glass-card--signin">
      <h6 class="auth-glass-card__eyebrow">Welcome To</h6>
      <h6 class="auth-glass-card__title">OIA PROPERTIES</h6>

      <form class="auth-glass-form" @submit.prevent="login">
        <div class="auth-glass-field">
          <label class="auth-glass-field__label" for="signin-username">Username</label>
          <div class="auth-glass-input-wrap">
            <input
              id="signin-username"
              v-model="email"
              type="email"
              class="auth-glass-input"
              placeholder="Enter username"
              autocomplete="username"
              required
            />
            <span class="auth-glass-input__icon" aria-hidden="true">
              <iconify-icon icon="mdi:account-outline" />
            </span>
          </div>
        </div>

        <div class="auth-glass-field">
          <label class="auth-glass-field__label" for="signin-password">Password</label>
          <div class="auth-glass-input-wrap">
            <input
              id="signin-password"
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              class="auth-glass-input"
              placeholder="Enter Password"
              autocomplete="current-password"
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
          <router-link to="/forgot-password" class="auth-glass-link">Forgot Password ?</router-link>
        </div>

        <div v-if="errorMessage" class="auth-glass-error" role="alert">
          {{ errorMessage }}
        </div>

        <div class="auth-glass-actions">
          <button type="button" class="auth-glass-btn auth-glass-btn--ghost" @click="clearForm">
            Clear
          </button>
          <button type="submit" class="auth-glass-btn auth-glass-btn--primary" :disabled="loading">
            {{ loading ? '...' : 'Login' }}
          </button>
        </div>

        <div class="auth-glass-divider" role="separator">
          <span>Or</span>
        </div>

        <router-link to="/sign-up" class="auth-glass-pill-link">
          don't have account? <b>Sign Up</b>
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
  components: {
    AuthLandingShell,
  },
  data() {
    return {
      email: '',
      password: '',
      showPassword: false,
      loading: false,
      errorMessage: '',
    };
  },
  setup() {
    const router = useRouter();
    return { router };
  },
  methods: {
    togglePassword() {
      this.showPassword = !this.showPassword;
    },

    clearForm() {
      this.email = '';
      this.password = '';
      this.errorMessage = '';
    },

    /**
     * Run a single geolocation attempt with the given options, wrapped in a
     * promise. Rejects with the raw GeolocationPositionError so the caller can
     * decide whether to retry or surface a message.
     */
    requestPosition(options) {
      return new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(resolve, reject, options);
      });
    },

    /**
     * Ask the browser for the user's location. Resolves to { latitude, longitude },
     * or rejects with a user-facing message. Tries a fast low-accuracy fix first
     * (works on desktops without GPS), then a high-accuracy pass. Login is gated
     * on this succeeding.
     */
    async getCurrentLocation() {
      if (!('geolocation' in navigator)) {
        throw new Error('Your browser does not support location. Location is required to sign in.');
      }

      // Geolocation only works on https:// or localhost. Fail early with a clear
      // message instead of a confusing timeout when served over plain http.
      if (typeof window !== 'undefined' && window.isSecureContext === false) {
        throw new Error('Location needs a secure (HTTPS) connection. Open the site over https:// to sign in.');
      }

      const attempts = [
        { enableHighAccuracy: false, timeout: 15000, maximumAge: 60000 },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
      ];

      let lastError = null;
      for (const options of attempts) {
        try {
          const position = await this.requestPosition(options);
          return {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
          };
        } catch (error) {
          lastError = error;
          // Don't bother retrying if the user actively denied permission.
          if (error && error.code === error.PERMISSION_DENIED) {
            break;
          }
        }
      }

      if (lastError && lastError.code === lastError.PERMISSION_DENIED) {
        throw new Error('Location access is required to sign in. Please allow location and try again.');
      }
      throw new Error(
        'Could not determine your location. Check that your device location service is turned on, then try again.'
      );
    },

    async login() {
      this.loading = true;
      this.errorMessage = '';

      try {
        // Location is mandatory — if the user denies it, stop before logging in.
        let coords;
        try {
          coords = await this.getCurrentLocation();
        } catch (locationError) {
          this.errorMessage = locationError.message;
          this.loading = false;
          return;
        }

        const response = await api.post('/auth/login', {
          email: this.email,
          password: this.password,
          latitude: coords.latitude,
          longitude: coords.longitude,
        });

        const token = response.data.data.token;

        if (token) {
          localStorage.setItem('token', token);

          const userData = response.data.data.user;

          localStorage.setItem(
            'user',
            JSON.stringify({
              id: userData.id,
              name: userData.name,
              email: userData.email,
              phone: userData.phone,
              avatar: userData.avatar,
              roles: userData.roles,
              permissions: userData.permissions,
              role_name: userData.role_name,
              is_listing_team: userData.is_listing_team,
              admin_parent_name: userData.admin_parent_name,
            })
          );

          const isAdminUser = userData.roles?.includes('only show listings');
          if (!isAdminUser) {
            window.location.href = '/';
          } else {
            window.location.href = '/alllisting';
          }
        } else {
          this.errorMessage = 'Token not found';
        }
      } catch (error) {
        if (error.response && error.response.data.message) {
          this.errorMessage = error.response.data.message;
        } else {
          this.errorMessage = 'Server error';
        }
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style src="./auth-glass-shared.css"></style>
