<template>
  <AuthLandingShell>
    <div class="auth-glass-card auth-glass-card--signin">
      <h6 class="auth-glass-card__eyebrow auth-glass-card__eyebrow--desktop">Welcome To</h6>
      <h6 class="auth-glass-card__title auth-glass-card__title--desktop">OIA PROPERTIES</h6>
      <div class="auth-mobile-hero">
        <h6 class="auth-mobile-hero__title">Welcome Back</h6>
        <p class="auth-mobile-hero__lead">Sign in with your email to access your workspace.</p>
      </div>

      <form class="auth-glass-form" @submit.prevent="login">
        <div class="auth-glass-field">
          <label class="auth-glass-field__label" for="signin-username">Email</label>
          <div class="auth-glass-input-wrap">
            <input
              id="signin-username"
              v-model="email"
              type="email"
              class="auth-glass-input"
              placeholder="Email Address"
              autocomplete="username"
              inputmode="email"
              enterkeyhint="next"
              autocapitalize="off"
              spellcheck="false"
              required
            />
            <span class="auth-glass-input__icon" aria-hidden="true">
              <iconify-icon icon="mdi:email-outline" />
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
              placeholder="Password"
              autocomplete="current-password"
              enterkeyhint="go"
              required
            />
            <span class="auth-glass-input__icon auth-mobile-only" aria-hidden="true">
              <iconify-icon icon="lucide:lock" />
            </span>
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

        <Transition name="auth-alert">
          <div
            v-if="authNoticeContent && !errorMessage"
            class="auth-glass-error auth-alert auth-alert--location auth-alert--notice"
            role="status"
            aria-live="polite"
          >
            <span class="auth-alert__icon" aria-hidden="true">
              <iconify-icon :icon="authNoticeContent.icon" />
            </span>
            <span class="auth-alert__body">
              <strong class="auth-alert__title">{{ authNoticeContent.title }}</strong>
              <span class="auth-alert__text">{{ authNoticeContent.text }}</span>
            </span>
            <button type="button" class="auth-alert__close" aria-label="Dismiss" @click="authNotice = ''">
              <iconify-icon icon="lucide:x" />
            </button>
          </div>
        </Transition>

        <Transition name="auth-alert">
          <div
            v-if="errorMessage"
            :key="errorMessage"
            class="auth-glass-error auth-alert"
            :class="{ 'auth-alert--location': isLocationError }"
            role="alert"
            aria-live="assertive"
          >
            <span class="auth-alert__icon" aria-hidden="true">
              <iconify-icon :icon="isLocationError ? 'lucide:map-pin-off' : 'lucide:shield-alert'" />
            </span>
            <span class="auth-alert__body">
              <strong class="auth-alert__title">{{ isLocationError ? 'Location needed' : "Couldn't sign you in" }}</strong>
              <span class="auth-alert__text">{{ errorMessage }}</span>
            </span>
            <button type="button" class="auth-alert__close" aria-label="Dismiss" @click="errorMessage = ''">
              <iconify-icon icon="lucide:x" />
            </button>
          </div>
        </Transition>

        <div class="auth-glass-actions">
          <button type="button" class="auth-glass-btn auth-glass-btn--ghost" @click="clearForm">
            Clear
          </button>
          <button type="submit" class="auth-glass-btn auth-glass-btn--primary" :disabled="loading">
            {{ loading ? '...' : 'Log In' }}
          </button>
        </div>

        <div class="auth-glass-divider" role="separator">
          <span>or</span>
        </div>

        <AuthMobileStack />

        <router-link to="/sign-up" class="auth-glass-pill-link">
          Don't have an account? <b>Sign Up</b>
        </router-link>

        <router-link to="/privacy-policy" class="auth-glass-footer-link auth-glass-footer-link--policy">Privacy Policy</router-link>
      </form>
    </div>
  </AuthLandingShell>
</template>

<script>
import api, { setAuthToken, clearAuthToken } from '@/plugins/axios';
import { askLeadPushPermission, subscribeLeadPush } from '@/services/offerLeadPush';
// import { captureDeviceLocation } from '@/utils/deviceLocation.mjs';
import { useRouter } from 'vue-router';
import AuthLandingShell from './AuthLandingShell.vue';
import AuthMobileStack from './AuthMobileStack.vue';

export default {
  components: {
    AuthLandingShell,
    AuthMobileStack,
  },
  data() {
    return {
      email: '',
      password: '',
      showPassword: false,
      loading: false,
      errorMessage: '',
      authNotice: '',
      locationAttempt: 0,
    };
  },
  setup() {
    const router = useRouter();
    return { router };
  },
  mounted() {
    // Start the location request now, so the user answers the prompt while typing
    // this.locationPromise = this.fetchLocationSafe();
    // Why the user was sent here (set by the 401 handler / expired-token guard). Read once.
    try {
      this.authNotice = sessionStorage.getItem('authNotice') || '';
      sessionStorage.removeItem('authNotice');
    } catch {
      this.authNotice = '';
    }
    // Fresh sign-in page: drop stale tokens so background API calls do not 401-loop.
    clearAuthToken();
    try {
      localStorage.removeItem('user');
      localStorage.removeItem('refreshToken');
    } catch {
      /* ignore */
    }
  },
  computed: {
    isLocationError() {
      return /location|geolocation|gps/i.test(this.errorMessage || '');
    },
    authNoticeContent() {
      if (this.authNotice === 'session_expired') {
        return {
          icon: 'lucide:clock-alert',
          title: 'Your session has ended',
          text: 'For your security, you were signed out. Please log in again to continue.',
        };
      }
      if (this.authNotice === 'account_inactive') {
        return {
          icon: 'lucide:user-x',
          title: 'Account inactive',
          text: 'Your account has been deactivated. Please contact your administrator.',
        };
      }
      return null;
    },
  },
  methods: {
    togglePassword() {
      this.showPassword = !this.showPassword;
    },
          getDeviceType() {
            const ua = navigator.userAgent.toLowerCase();

            if (/iphone|ipad|ipod/.test(ua)) return 'ios';
            if (/android/.test(ua)) return 'android';
            return 'desktop';
          },

          handleLocationError(error) {
            const type = this.getDeviceType();

            if (error?.code === 1) {
              // Permission denied
              if (type === 'ios') {
                return `Location is blocked on iPhone.

          Go to:
          Settings → Privacy & Security → Location Services → Safari Websites → While Using`;
              }

              if (type === 'android') {
                return `Location is blocked on Android.

          Go to:
          Settings → Location → App permissions → Enable browser access`;
              }

              return `Location permission is blocked in your browser.
          Please allow location access and try again.`;
            }

            // Other errors (timeout / unavailable)
            return `Could not detect your location.
          Please make sure location services are enabled and try again.`;
          },
    clearForm() {
      this.email = '';
      this.password = '';
      this.errorMessage = '';
    },

    /**
     * One device reading for this sign-in. High accuracy is preferred. A later
     * attempt invalidates an older in-flight result so it cannot be submitted.
     */
    async getCurrentLocation() {
      if (!navigator?.geolocation) {
        throw { code: 0, message: 'Your browser does not support location. Location is required to sign in.' };
      }
      if (window.isSecureContext === false) {
        throw { code: 0, message: 'Location needs a secure (HTTPS) connection.' };
      }

      return captureDeviceLocation(navigator.geolocation);
    },

    async fetchLocationSafe() {
      const attempt = ++this.locationAttempt;
      try {
        const coords = await this.getCurrentLocation();
        if (attempt !== this.locationAttempt) {
          return { coords: null, error: null, stale: true };
        }
        return { coords, error: null, stale: false };
      } catch (error) {
        if (attempt !== this.locationAttempt) {
          return { coords: null, error: null, stale: true };
        }
        const message = error?.code === 0 && error?.message
          ? error.message
          : this.handleLocationError(error);
        const wrapped = new Error(message);
        wrapped.permissionDenied = error?.code === 1;
        return { coords: null, error: wrapped, stale: false };
      }
    },

    async login() {
      this.loading = true;
      this.authNotice = '';
      this.errorMessage = '';

      try {
        // const isLocal = ['localhost', '127.0.0.1', '::1'].includes(window.location.hostname);

        // // Usually already resolved while the user was typing.
        // let result = await (this.locationPromise || this.fetchLocationSafe());
        // if (result?.stale) {
        //   result = await this.fetchLocationSafe();
        // }

        // // Retry only if the user denied before (they may have just enabled it).
        // // A timeout or unavailable reading is not retried and is not replaced
        // // with a network or IP position.
        // if (!result.coords && result.error?.permissionDenied === true) {
        //   result = await this.fetchLocationSafe();
        //   this.locationPromise = Promise.resolve(result);
        // }

        // // Block only on denied / unsupported. Timeout or unavailable continues
        // // without coordinates. The server leaves any previous device fix as-is.
        // const couldNotLocate = result.error?.permissionDenied === false;
        // if (!result.coords && !isLocal && !couldNotLocate) {
        //   this.errorMessage = result.error?.message || 'Location is required to sign in.';
        //   return; // finally{} resets loading
        // }

        // const coords = result.coords;

        const response = await api.post('/auth/login', {
          email: this.email,
          password: this.password,
          // latitude: coords?.latitude ?? null,
          // longitude: coords?.longitude ?? null,
          // accuracy: coords?.accuracy ?? null,
          // captured_at: coords?.capturedAt ?? null,
        });

        const token = response.data?.data?.token;
        const webPush = response.data?.data?.web_push;

        if (!token) {
          this.errorMessage = 'Token not found';
          return;
        }

        const notificationPermission = await askLeadPushPermission(webPush);

        setAuthToken(token);

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

        // Don't let a previous user's saved listing filters leak into this session
        localStorage.removeItem('listingSearchFilters');

        if (notificationPermission === 'granted' && webPush?.public_key) {
          try {
            await subscribeLeadPush(webPush.public_key);
          } catch (_) {
            /* Login still continues. The bell can retry the subscription. */
          }
        }

        const isAdminUser = userData.roles?.includes('only show listings');
        window.location.href = isAdminUser ? '/alllisting' : '/';
      } catch (error) {
        this.errorMessage = error.response?.data?.message || 'Server error';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style src="./auth-glass-shared.css"></style>
