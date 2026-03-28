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
        <!-- form -->
        <form @submit.prevent="login">
             <h4 class="mb-12 titleH">Sign In to your Account</h4>
          <p class="mb-32 titleH2">Welcome Back! Please enter your details</p>
          <div class="icon-field mb-16">
            <span class="icon top-50 translate-middle-y">
              <iconify-icon icon="mage:email"></iconify-icon>
            </span>
            <input
              type="email"
              class="form-control h-56-px bg-neutral-50 radius-12"
              placeholder="Email"
              v-model="email"
              required
            />
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
         

          <div class="d-flex justify-content-between gap-2">
            <div class="form-check style-check d-flex align-items-center">
              <input
                class="form-check-input border border-neutral-300 "
                type="checkbox"
                id="remember"
                v-model="remember"
              />
              <label class="form-check-label text-white" for="remember">Remember me</label>
            </div>
             <router-link to="/forgot-password" class="text-white small">
              Forgot Password?
            </router-link>
          
          </div>

          <button
            type="submit"
            class="btn btn-primary text-sm btn-sm px-12 py-16 w-100 radius-12 mt-32"
            :disabled="loading"
          >
            {{ loading ? 'Signing in...' : 'Sign In' }}
          </button>
           
          <p v-if="errorMessage" class="text-danger mt-3 text-center">
            {{ errorMessage }}
          </p>

       
        </form>
          <div class="mt-32 text-center text-sm">
            <p class="mb-0 text-white">don't have account?
              <a href="/sign-up" class="text-white fw-semibold">Sign Up As Agent</a>
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
  data() {
    return {
      email: '',
      password: '',
      showPassword: false,
      remember: false,
      loading: false,
      errorMessage: '',
         profilePicture: '/assets/images/OiaLogo-ProfilePictureWhite.jpg',
      logo: '/assets/images/LogoWhite.png'
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

    async login() {
  this.loading = true;
  this.errorMessage = '';

try {
  const response = await api.post('/auth/login', {
    email: this.email,
    password: this.password,
  });

  console.log('✅ API response:', response.data);

  const token = response.data.data.token;

  if (token) {
    localStorage.setItem('token', token);
    console.log("✅ Token saved:", token);

    const userData = response.data.data.user;
    
    localStorage.setItem('user', JSON.stringify({
      id: userData.id,
      name: userData.name,
      email: userData.email,
      phone: userData.phone,
      avatar: userData.avatar,
      roles: userData.roles,
      permissions: userData.permissions,
      role_name:userData.role_name,
      is_listing_team:userData.is_listing_team
    }));
    
    console.log("✅ User data saved:", userData);
    
    window.location.href = '/';
    
    // this.router.push('/');
             
  } else {
    console.error("❌ Token not found in response:", response.data);
    this.errorMessage = 'Token not found';
  }

} catch (error) {
  console.log('❌ API error:', error);
  if (error.response && error.response.data.message) {
    this.errorMessage = error.response.data.message;
  } else {
    this.errorMessage = 'Server error';
  }
} finally {
  this.loading = false;
}
}

  },
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