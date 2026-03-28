<template>
    <section class="login">
        <div class="login-card">
            <div class="mx-auto w-100">
                <div class="logo">
                    <router-link to="/" class="mb-40 max-w-290-px">
                        <img :src="logo" alt="" class="main-logo">
                    </router-link>
                </div>
                <div class="form">
                    <template v-if="!done">
                        <h4 class="mb-12 titleH">Set new password</h4>
                        <p class="mb-32 titleH2">
                            Choose a new password for <strong class="text-white">{{ emailDisplay }}</strong>.
                        </p>
                        <form @submit.prevent="submit">
                            <div class="position-relative mb-20">
                                <div class="icon-field">
                                    <span class="icon top-50 translate-middle-y">
                                        <iconify-icon icon="solar:lock-password-outline"></iconify-icon>
                                    </span>
                                    <input
                                        v-model="password"
                                        :type="showPassword ? 'text' : 'password'"
                                        class="form-control h-56-px bg-neutral-50 radius-12"
                                        placeholder="New password"
                                        required
                                        autocomplete="new-password"
                                        minlength="8"
                                    >
                                </div>
                                <span
                                    class="toggle-password ri-eye-line cursor-pointer position-absolute end-0 top-50 translate-middle-y me-16 text-secondary-light"
                                    :class="{ 'ri-eye-off-line': showPassword }"
                                    role="button"
                                    tabindex="0"
                                    @click="showPassword = !showPassword"
                                    @keydown.enter.prevent="showPassword = !showPassword"
                                />
                            </div>
                            <div class="icon-field mb-16">
                                <span class="icon top-50 translate-middle-y">
                                    <iconify-icon icon="solar:lock-password-outline"></iconify-icon>
                                </span>
                                <input
                                    v-model="passwordConfirmation"
                                    :type="showPassword ? 'text' : 'password'"
                                    class="form-control h-56-px bg-neutral-50 radius-12"
                                    placeholder="Confirm new password"
                                    required
                                    autocomplete="new-password"
                                    minlength="8"
                                >
                            </div>
                            <span class="pass-hint text-white d-block mb-8">Password must be at least 8 characters</span>
                            <p v-if="errorMessage" class="text-danger mt-2 mb-0 text-center small-message">
                                {{ errorMessage }}
                            </p>
                            <button
                                type="submit"
                                class="btn btn-primary text-sm btn-sm px-12 py-16 w-100 radius-12 mt-24"
                                :disabled="loading || !token || !email"
                            >
                                {{ loading ? 'Updating…' : 'Update password' }}
                            </button>
                        </form>
                        <div class="mt-32 text-center text-sm">
                            <router-link to="/sign-in" class="text-white fw-semibold text-decoration-none">Back to Sign In</router-link>
                        </div>
                    </template>
                    <template v-else>
                        <h4 class="mb-12 titleH">Password updated</h4>
                        <p class="mb-32 titleH2">{{ successMessage }}</p>
                        <router-link to="/sign-in" class="btn btn-primary text-sm btn-sm px-12 py-16 w-100 radius-12 text-center d-block text-decoration-none">
                            Sign In
                        </router-link>
                    </template>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/plugins/axios'

defineOptions({ name: 'ResetPassword' })

const route = useRoute()
const logo = '/assets/images/LogoWhite.png'

const token = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const showPassword = ref(false)
const loading = ref(false)
const done = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const emailDisplay = computed(() => email.value || 'your account')

function readQuery() {
    const t = route.query.token
    const e = route.query.email
    token.value = typeof t === 'string' ? t : Array.isArray(t) ? t[0] || '' : ''
    email.value = typeof e === 'string' ? decodeURIComponent(e) : Array.isArray(e) ? decodeURIComponent(e[0] || '') : ''
    if (!token.value || !email.value) {
        errorMessage.value = 'This reset link is invalid or incomplete. Request a new link from Forgot password.'
    }
}

onMounted(() => {
    readQuery()
})

async function submit() {
    errorMessage.value = ''
    if (password.value !== passwordConfirmation.value) {
        errorMessage.value = 'Passwords do not match.'
        return
    }
    loading.value = true
    try {
        const res = await api.post('/auth/reset-password', {
            token: token.value,
            email: email.value,
            password: password.value,
            password_confirmation: passwordConfirmation.value,
        })
        successMessage.value = res.data?.message || 'You can sign in with your new password.'
        done.value = true
    } catch (e) {
        const msg = e.response?.data?.message
        const errs = e.response?.data?.errors
        if (errs && typeof errs === 'object') {
            const first = Object.values(errs)[0]
            errorMessage.value = Array.isArray(first) ? first[0] : String(first)
        } else {
            errorMessage.value = msg || 'Could not reset password. The link may have expired.'
        }
    } finally {
        loading.value = false
    }
}
</script>

<style scoped>
.login {
    background-color: #01062c !important;
    height: 100vh;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.login-card {
    width: 100%;
    max-width: 586px;
    background: transparent;
}

.form {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.5));
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(40px);
    padding: 40px 32px;
    box-sizing: border-box;
}

.titleH {
    font-size: 28px;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 12px;
    text-align: center;
}

.titleH2 {
    font-size: 16px;
    font-weight: 400;
    color: #ffffff;
    margin-bottom: 32px;
    text-align: center;
    line-height: 1.5;
}

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
    color: #01062c !important;
    font-size: 14px;
}

.icon-field input::placeholder {
    color: #888;
}

.icon-field input:focus {
    border-color: #01062c !important;
    box-shadow: 0 0 0 3px rgba(1, 6, 44, 0.1) !important;
}

.toggle-password {
    color: #666;
    font-size: 18px;
    z-index: 3;
}

.toggle-password:hover {
    color: #01062c;
}

.pass-hint {
    font-weight: 400;
    font-size: 14px;
    opacity: 0.95;
}

.btn-primary {
    background-color: #01062c !important;
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

.text-danger,
.small-message.text-danger {
    background: rgba(220, 53, 69, 0.15);
    padding: 12px;
    border-radius: 8px;
    border-left: 4px solid #dc3545;
    color: #fff !important;
}

.logo {
    text-align: center;
    justify-content: center;
}

.main-logo {
    height: 60px;
    width: auto;
    display: block;
    margin-left: auto;
    margin-right: auto;
}

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
</style>
