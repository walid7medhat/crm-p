import { STACK } from './meta.js'

export const frontendLayer = {
  bootstrap: {
    en: 'main.js creates the Vue app, imports router, axios plugin, global CSS, and notification helpers.',
    ar: 'main.js ينشئ تطبيق Vue ويستورد الراوتر وaxios والـ CSS ومساعدات الإشعارات.',
    path: 'resources/js/main.js',
  },
  router: {
    en: 'Vue Router history mode; lazy page imports; beforeEach enforces JWT + role/permission meta.',
    ar: 'Vue Router مع lazy-load وحراسة JWT والأدوار/الصلاحيات.',
    path: 'resources/js/router.js',
  },
  layout: {
    en: 'App.vue wraps Header + Navbar + router-view + Footer unless meta.layout === false.',
    ar: 'App.vue يلف الهيدر والنافبار وrouter-view ما لم يُعطَّل الـ layout.',
    path: 'resources/js/App.vue',
  },
  apiClient: {
    en: 'Shared axios instance (window.axios) with token resolution and /api base URL.',
    ar: 'نسخة axios مشتركة مع التوكن وقاعدة /api.',
    path: 'resources/js/plugins/axios.js',
  },
  state: {
    en: 'Mostly component/localStorage state; composables under resources/js/composables/ (no mandatory Pinia store for core CRM).',
    ar: 'غالبًا حالة محلية/localStorage وcomposables — بدون Pinia إلزامي للنواة.',
    path: 'resources/js/composables/',
    status: 'partial',
  },
  relatedTools: [
    { path: '/system-overview', note: { en: 'Product/system map for admins (unchanged).', ar: 'خريطة المنتج للأدمن (بدون تغيير).' } },
    { path: '/project-map', note: { en: 'Dev file/route scanner for super_admin (unchanged).', ar: 'ماسح ملفات للمطورين (بدون تغيير).' } },
  ],
  stack: STACK,
}

export const backendLayer = {
  routing: {
    en: 'bootstrap/app.php loads web.php + api.php (prefix /api). SPA fallback in web.php.',
    ar: 'bootstrap/app.php يحمّل web وapi. الـ SPA عبر catch-all في web.php.',
    path: 'bootstrap/app.php, routes/api.php, routes/web.php',
  },
  auth: {
    en: 'Default API guard jwt; middleware alias jwt.auth → JwtAuthMiddleware; Spatie role/permission middleware.',
    ar: 'حارس api بـ jwt؛ وسيط jwt.auth؛ Spatie للأدوار/الصلاحيات.',
    path: 'config/auth.php, app/Http/Middleware/JwtAuthMiddleware.php',
  },
  controllers: {
    en: 'Almost all HTTP under app/Http/Controllers/Api/ (LeadController, Deal/*, Listing/*, Employee/*, …).',
    ar: 'معظم الـ HTTP تحت Controllers/Api/.',
    path: 'app/Http/Controllers/Api/',
  },
  services: {
    en: 'Business-heavy logic often in app/Services (e.g. LeadAssignmentService) — not every controller uses a service.',
    ar: 'منطق ثقيل في Services — ليس كل Controller يستخدم Service.',
    path: 'app/Services/',
  },
  models: {
    en: 'Eloquent models in app/Models (~100+). Relationships define the data graph used by APIs.',
    ar: 'نماذج Eloquent في app/Models.',
    path: 'app/Models/',
  },
  jobsEvents: {
    en: 'Jobs in app/Jobs; Events/Listeners for lead/deal updates; Notifications for user alerts.',
    ar: 'Jobs وEvents/Listeners وNotifications.',
    path: 'app/Jobs/, app/Events/, app/Listeners/, app/Notifications/',
  },
  schedule: {
    en: 'withSchedule in bootstrap/app.php (leads:auto-assign, scoring, attendance, AI recalculation, …).',
    ar: 'الجدولة في bootstrap/app.php.',
    path: 'bootstrap/app.php',
  },
}

export const architectureMermaid = `flowchart TB
  Browser[Browser Vue SPA]
  Router[vue-router]
  Axios[axios /api]
  MW[jwt.auth + Spatie]
  Ctrl[Api Controllers]
  Svc[Services / Jobs]
  Models[Eloquent Models]
  DB[(Database)]
  Ext[Webhooks Integrations]

  Browser --> Router
  Router --> Browser
  Browser --> Axios
  Axios --> MW
  MW --> Ctrl
  Ctrl --> Svc
  Ctrl --> Models
  Svc --> Models
  Models --> DB
  Ext --> Ctrl
  Svc --> Ext`
