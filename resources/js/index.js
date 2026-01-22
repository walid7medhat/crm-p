import { createRouter, createWebHistory } from 'vue-router'

// Views
import Dashboard from '@/views/Dashboard.vue'
import SignIn from '@/views/SignIn.vue'

// Property Form
import PropertyForm from '@/components/PropertyForm.vue.vue' // عدّل المسار حسب مكان الملف

const routes = [
  // Dashboard
  { path: '/', name: 'Dashboard', component: Dashboard, meta: { requiresAuth: true } },

  // Property Form
  { path: '/property/create', name: 'PropertyCreate', component: PropertyForm.vue, meta: { requiresAuth: true } },

  // Authentication
  { path: '/sign-in', name: 'SignIn', component: SignIn, meta: { layout: false, requiresAuth: false } },
]
const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    return { top: 0 }
  }
})

const router = createRouter({
  history: createWebHistory(),
  routes,
})

// حماية الصفحات حسب التوكن
router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token')
  if (to.meta.requiresAuth && !token) {
    next('/sign-in')
  } else if (to.path === '/sign-in' && token) {
    next('/')
  } else {
    next()
  }
})

export default router
