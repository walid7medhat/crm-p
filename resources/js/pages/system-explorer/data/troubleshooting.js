export const troubleshooting = [
  {
    id: 'ts-page-blank',
    status: 'verified',
    title: { en: 'Page does not load / blank', ar: 'الصفحة لا تُحمَّل / فارغة' },
    order: [
      { en: 'Confirm route exists in resources/js/router.js and SPA catch-all in routes/web.php.', ar: 'تأكد من وجود الراوت في router.js والـ catch-all في web.php.' },
      { en: 'Check browser console for stale chunk errors (router.onError reloads once).', ar: 'افحص أخطاء الـ chunk في الكونسول.' },
      { en: 'Verify Vite build / npm run dev is serving assets (welcome.blade.php @vite).', ar: 'تحقق من Vite/@vite.' },
      { en: 'If redirected to /, check meta requiresSuperAdmin / permission vs localStorage user.', ar: 'إن حُوِّلت لـ / افحص meta والصلاحيات.' },
    ],
    files: ['resources/js/router.js', 'resources/views/welcome.blade.php', 'resources/js/main.js', 'routes/web.php'],
  },
  {
    id: 'ts-api-error',
    status: 'verified',
    title: { en: 'API request returns error', ar: 'طلب الـ API يرجع خطأ' },
    order: [
      { en: 'Network tab: status, URL under /api, Authorization header.', ar: 'Network: الحالة والـ URL والتوكن.' },
      { en: '401 → JWT expired (axios forceInactiveLogout / session_expired).', ar: '401 → JWT منتهي.' },
      { en: '403 → Spatie permission / controller gate.', ar: '403 → صلاحية Spatie أو فحص في الـ Controller.' },
      { en: '422 → Form Request validation messages.', ar: '422 → تحقق Form Request.' },
      { en: '500 → Laravel log; start at matching Controller in routes/api.php.', ar: '500 → لوج Laravel ثم الـ Controller.' },
    ],
    files: ['resources/js/plugins/axios.js', 'routes/api.php', 'storage/logs/laravel.log (local only)', 'app/Http/Middleware/JwtAuthMiddleware.php'],
  },
  {
    id: 'ts-hidden-feature',
    status: 'verified',
    title: { en: 'Feature unexpectedly hidden', ar: 'ميزة مخفية بشكل غير متوقع' },
    order: [
      { en: 'Sidebar conditions in header/index.vue (isSuperAdmin, hasPermission).', ar: 'شروط السايدبار في header/index.vue.' },
      { en: 'Route meta in router.js (requiresPermission + strictPermission).', ar: 'meta الراوت في router.js.' },
      { en: 'In-page v-if on roles — UI hide ≠ API allow.', ar: 'v-if داخل الصفحة — الإخفاء ≠ السماح في الـ API.' },
    ],
    files: ['resources/js/components/layout/header/index.vue', 'resources/js/router.js'],
  },
  {
    id: 'ts-record-missing',
    status: 'verified',
    title: { en: 'Record missing or wrong data', ar: 'سجل ناقص أو بيانات خاطئة' },
    order: [
      { en: 'Confirm soft-delete / archive scopes on Lead/Listing queries.', ar: 'تحقق من Soft delete/الأرشفة.' },
      { en: 'Branch / hierarchy scoping (Lead::isManagedBy, subordinateIds).', ar: 'نطاق الفرع/التسلسل الهرمي.' },
      { en: 'Stage visibility settings endpoints.', ar: 'إعدادات ظهور المراحل.' },
      { en: 'Integration sync lag (Bitrix jobs / queue).', ar: 'تأخر مزامنة التكامل.' },
    ],
    files: ['app/Models/Lead.php', 'app/Http/Controllers/Api/StageController.php', 'app/Jobs/'],
  },
  {
    id: 'ts-job-fail',
    status: 'verified',
    title: { en: 'Background job / schedule fails', ar: 'فشل Job أو جدولة' },
    order: [
      { en: 'Read schedule registrations in bootstrap/app.php.', ar: 'اقرأ الجدولة في bootstrap/app.php.' },
      { en: 'Ensure queue worker running for ShouldQueue jobs.', ar: 'تأكد من تشغيل queue worker.' },
      { en: 'Inspect failed_jobs / job class under app/Jobs.', ar: 'افحص failed_jobs وملفات Jobs.' },
      { en: 'For assignment: LeadAssignmentSetting + lead-assignment logs API.', ar: 'للتعيين: الإعدادات وسجلات التعيين.' },
    ],
    files: ['bootstrap/app.php', 'app/Jobs/', 'app/Console/Commands/', 'app/Services/LeadAssignmentService.php'],
  },
  {
    id: 'ts-ui-no-effect',
    status: 'verified',
    title: { en: 'UI action has no effect', ar: 'إجراء الواجهة بلا نتيجة' },
    order: [
      { en: 'Confirm click handler fires (Vue DevTools).', ar: 'تأكد أن الـ handler يعمل.' },
      { en: 'Watch Network for missing/blocked request.', ar: 'راقب Network.' },
      { en: 'Optimistic UI rolled back on error — read response body.', ar: 'تراجع الواجهة عند الخطأ — اقرأ الرد.' },
      { en: 'Echo/broadcast: check broadcasting/auth and channel auth in web.php.', ar: 'للبث: broadcasting/auth.' },
    ],
    files: ['resources/js/plugins/axios.js', 'routes/web.php', 'app/Events/'],
  },
]
