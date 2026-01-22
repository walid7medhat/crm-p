import { createRouter, createWebHistory } from 'vue-router'

// Views
import Dashboard from '@/views/Dashboard.vue'
import SignIn from '@/views/SignIn.vue'
import PropertyForm from '@/components/pages/Table/PropertyForm.vue' 

const routes = [
  { path: '/', name: 'Dashboard', component: Dashboard, meta: { requiresAuth: true } },
  { path: '/property/create', name: 'PropertyCreate', component: PropertyForm, meta: { requiresAuth: true } },
  { path: '/sign-in', name: 'SignIn', component: SignIn, meta: { layout: false, requiresAuth: false } },
]

const router = createRouter({
  history: createWebHistory(),
  routes,

  // ⬅️ أهم جزء — يخلي أي صفحة تفتح من أولها
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition
    } else {
      return { top: 0, left: 0 }
    }
  }
})

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
