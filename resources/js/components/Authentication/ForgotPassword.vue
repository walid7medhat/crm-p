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
                    <template v-if="!success">
                        <h4 class="mb-12 titleH">Forgot password</h4>
                        <p class="mb-32 titleH2">
                            Enter the email for your account. We’ll send you a link to reset your password.
                        </p>
                        <form @submit.prevent="submit">
                            <div class="icon-field mb-16">
                                <span class="icon top-50 translate-middle-y">
                                    <iconify-icon icon="mage:email"></iconify-icon>
                                </span>
                                <input
                                    v-model="email"
                                    type="email"
                                    class="form-control h-56-px bg-neutral-50 radius-12"
                                    placeholder="Email"
                                    required
                                    autocomplete="email"
                                >
                            </div>
                            <p v-if="errorMessage" class="text-danger mt-2 mb-0 text-center small-message">
                                {{ errorMessage }}
                            </p>
                            <button
                                type="submit"
                                class="btn btn-primary text-sm btn-sm px-12 py-16 w-100 radius-12 mt-32"
                                :disabled="loading"
                            >
                                {{ loading ? 'Sending…' : 'Send reset link' }}
                            </button>
                        </form>
                        <div class="mt-32 text-center text-sm">
                            <router-link to="/sign-in" class="text-white fw-semibold text-decoration-none">Back to Sign In</router-link>
                        </div>
                        <div class="mt-24 text-center text-sm">
                            <p class="mb-0 text-white">
                                Don’t have an account?
                                <a href="/sign-up" class="text-white fw-semibold">Sign Up As Agent</a>
                            </p>
                        </div>
                    </template>

                    <template v-else>
                        <h4 class="mb-12 titleH">Check your email</h4>
                        <p class="mb-24 titleH2">
                            If an account exists for <strong class="text-white">{{ email }}</strong>, we sent a link to reset your password.
                        </p>
                        <button
                            type="button"
                            class="btn btn-primary text-sm btn-sm px-12 py-16 w-100 radius-12 mb-16"
                            @click="router.push('/sign-in')"
                        >
                            Back to Sign In
                        </button>
                        <p class="mb-0 text-center text-sm text-white">
                            Didn’t get an email?
                            <button
                                type="button"
                                class="btn btn-link text-white fw-semibold p-0 align-baseline text-decoration-none resend-link"
                                :disabled="loading"
                                @click="submit"
                            >
                                Resend
                            </button>
                        </p>
                    </template>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/plugins/axios'

defineOptions({ name: 'ForgotPassword' })

const router = useRouter()
const logo = '/assets/images/LogoWhite.png'

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

<style scoped>
.login {
    background-color: #0B0736 !important;
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

.max-w-464-px {
    max-width: 464px;
    margin: 0 auto;
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
    color: #0B0736 !important;
    font-size: 14px;
}

.icon-field input::placeholder {
    color: #888;
}

.icon-field input:focus {
    border-color: #0B0736 !important;
    box-shadow: 0 0 0 3px rgba(1, 6, 44, 0.1) !important;
}

.btn-primary {
    background-color: #0B0736 !important;
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

.resend-link:hover {
    text-decoration: underline !important;
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
