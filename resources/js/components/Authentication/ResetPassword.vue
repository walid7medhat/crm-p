<template>
  <section class="login">
    <div class="login-card">
      <div class="mx-auto w-100">

        <!-- Logo -->
        <div class="logo">
          <img :src="logo" class="main-logo" />
        </div>

        <div class="form">
          <form @submit.prevent="resetPassword">

            <h4 class="mb-12 titleH">Reset Password</h4>
            <p class="mb-32 titleH2">
              Enter your new password
            </p>

            <!-- Password -->
            <div class="position-relative mb-20">
              <div class="icon-field">
                <span class="icon top-50 translate-middle-y">
                  <iconify-icon icon="solar:lock-password-outline"></iconify-icon>
                </span>
                <input
                  :type="showPassword ? 'text' : 'password'"
                  class="form-control h-56-px bg-neutral-50 radius-12"
                  placeholder="New Password"
                  v-model="password"
                  required
                />
              </div>
            </div>

            <!-- Confirm -->
            <div class="position-relative mb-20">
              <div class="icon-field">
                <span class="icon top-50 translate-middle-y">
                  <iconify-icon icon="solar:lock-password-outline"></iconify-icon>
                </span>
                <input
                  :type="showPassword ? 'text' : 'password'"
                  class="form-control h-56-px bg-neutral-50 radius-12"
                  placeholder="Confirm Password"
                  v-model="password_confirmation"
                  required
                />
              </div>
            </div>

            <!-- Button -->
            <button
              type="submit"
              class="btn btn-primary text-sm btn-sm px-12 py-16 w-100 radius-12 mt-32"
            >
              Reset Password
            </button>

            <!-- Message -->
            <p v-if="message" class="text-danger mt-3 text-center">
              {{ message }}
            </p>

          </form>

          <!-- Back -->
          <div class="mt-32 text-center text-sm">
            <p class="mb-0 text-white">
              Back to
              <router-link to="/login" class="text-white fw-semibold">
                Sign In
              </router-link>
            </p>
          </div>

        </div>
      </div>
    </div>
  </section>
</template>

<script>
import api from '@/plugins/axios';

export default {
  data() {
    return {
      email: '',
      password: '',
      password_confirmation: '',
      token: '',
      message: '',
      logo: '/assets/images/LogoWhite.png'
    };
  },
  mounted() {
    this.token = this.$route.query.token;
    this.email = this.$route.query.email;
  },
  methods: {
    async resetPassword() {
      try {
        const res = await api.post('/auth/reset-password', {
          email: this.email,
          password: this.password,
          password_confirmation: this.password_confirmation,
          token: this.token
        });

        this.message = res.data.message;
      } catch (e) {
        this.message = e.response?.data?.message || 'Error';
      }
    }
  }
};
</script>
<style scoped>
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
</style>