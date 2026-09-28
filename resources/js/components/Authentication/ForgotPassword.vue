<template>
  <AuthLandingShell>
    <div class="auth-glass-card auth-glass-card--compact">
      <h6 class="auth-glass-card__eyebrow">Welcome To</h6>
      <h6 class="auth-glass-card__title">OIA PROPERTIES</h6>

      <template v-if="!success">
        <div class="auth-reset-heading">
          <h6 class="auth-reset-heading__title">Forgot Password</h6>
          <p class="auth-reset-heading__subtitle">
            Enter the email for your account. We'll send you a link to reset your password.
          </p>
        </div>

        <form class="auth-glass-form" @submit.prevent="submit">
          <div class="auth-glass-field">
            <label class="auth-glass-field__label" for="forgot-email">Email</label>
            <div class="auth-glass-input-wrap">
              <input
                id="forgot-email"
                v-model="email"
                type="email"
                class="auth-glass-input"
                placeholder="Enter Email"
                autocomplete="email"
                required
              />
              <span class="auth-glass-input__icon" aria-hidden="true">
                <iconify-icon icon="mage:email" />
              </span>
            </div>
          </div>

          <div v-if="errorMessage" class="auth-glass-error" role="alert">
            {{ errorMessage }}
          </div>

          <div class="auth-glass-actions">
            <button
              type="submit"
              class="auth-glass-btn auth-glass-btn--primary auth-glass-btn--block"
              :disabled="loading"
            >
              {{ loading ? 'Sending…' : 'Send reset link' }}
            </button>
          </div>

          <router-link to="/sign-in" class="auth-glass-pill-link">
            Back to <b>Sign In</b>
          </router-link>

          <router-link to="/sign-up" class="auth-glass-footer-link">
            Don't have an account? Sign Up As Agent
          </router-link>
        </form>
      </template>

      <template v-else>
        <div class="auth-glass-success">
          <h6>Check your email</h6>
          <span class="auth-glass-success__note">
            If an account exists for <strong>{{ email }}</strong>, we sent a link to reset your password.
          </span>
          <button
            type="button"
            class="auth-glass-btn auth-glass-btn--primary auth-glass-btn--block"
            @click="router.push('/sign-in')"
          >
            Back to Sign In
          </button>
          <p class="auth-resend-note">
            Didn't get an email?
            <button
              type="button"
              class="auth-resend-link"
              :disabled="loading"
              @click="submit"
            >
              Resend
            </button>
          </p>
        </div>
      </template>
    </div>
  </AuthLandingShell>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/plugins/axios'
import AuthLandingShell from './AuthLandingShell.vue'

defineOptions({ name: 'ForgotPassword' })

const router = useRouter()

const email = ref('')
const loading = ref(false)
const success = ref(false)
const errorMessage = ref('')

async function submit() {
    errorMessage.value = ''
    loading.value = true
    try {
        await api.post('/auth/forgot-password', { email: email.value.trim() })
        success.value = true
    } catch (e) {
        errorMessage.value = e.response?.data?.message || 'Could not send reset email. Try again later.'
    } finally {
        loading.value = false
    }
}
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
  line-height: 1.5;
}

.auth-resend-note {
  margin: 14px 0 0;
  text-align: center;
  font-size: 0.85rem;
  color: #64748b;
}

.auth-resend-link {
  border: none;
  background: transparent;
  padding: 0;
  margin-left: 4px;
  font-weight: 600;
  color: #7c3aed;
  cursor: pointer;
}

.auth-resend-link:hover {
  text-decoration: underline;
}

.auth-resend-link:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .auth-reset-heading__title {
    color: #fff;
  }

  .auth-reset-heading__subtitle {
    color: rgba(255, 255, 255, 0.7);
  }

  .auth-resend-note {
    color: rgba(255, 255, 255, 0.7);
  }

  .auth-resend-link {
    color: #fff;
  }
}
</style>
