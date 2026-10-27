export const glossary = [
  {
    id: 'frontend',
    term: { en: 'Frontend', ar: 'الواجهة الأمامية' },
    def: {
      en: 'Vue 3 app in the browser (pages, components, router). Talks to the backend only via HTTP/API (axios). Example: resources/js/pages/kanban.vue.',
      ar: 'تطبيق Vue 3 في المتصفح. يتواصل مع السيرفر عبر HTTP/API فقط. مثال: resources/js/pages/kanban.vue.',
    },
  },
  {
    id: 'backend',
    term: { en: 'Backend', ar: 'الخلفية' },
    def: {
      en: 'Laravel PHP API: routes/api.php → Controllers → Services/Models. Example: LeadController.',
      ar: 'واجهة Laravel PHP: routes/api.php → Controllers → Services/Models. مثال: LeadController.',
    },
  },
  {
    id: 'database',
    term: { en: 'Database', ar: 'قاعدة البيانات' },
    def: {
      en: 'Persistent tables (MySQL). Eloquent models map to tables. Example: app/Models/Lead.php → leads.',
      ar: 'جداول دائمة. نماذج Eloquent تربط الجداول. مثال: Lead.php → leads.',
    },
  },
  {
    id: 'jwt',
    term: { en: 'JWT', ar: 'JWT' },
    def: {
      en: 'JSON Web Token stored after login; sent as Bearer token. Middleware: jwt.auth.',
      ar: 'رمز بعد تسجيل الدخول يُرسل كـ Bearer. الوسيط: jwt.auth.',
    },
  },
  {
    id: 'spatie',
    term: { en: 'Spatie Permission', ar: 'صلاحيات Spatie' },
    def: {
      en: 'Roles & permissions package (roles, permissions tables). Used with middleware permission/role.',
      ar: 'حزمة أدوار وصلاحيات مع middleware permission/role.',
    },
  },
  {
    id: 'eloquent',
    term: { en: 'Eloquent', ar: 'Eloquent' },
    def: {
      en: 'Laravel ORM: PHP classes representing tables and relationships (belongsTo, hasMany…).',
      ar: 'ORM لارافيل: كلاسات تمثل الجداول والعلاقات.',
    },
  },
  {
    id: 'job',
    term: { en: 'Job / Queue', ar: 'Job / الطابور' },
    def: {
      en: 'Background work class under app/Jobs, often queued so HTTP requests stay fast.',
      ar: 'عمل خلفي تحت app/Jobs غالبًا عبر Queue.',
    },
  },
  {
    id: 'schedule',
    term: { en: 'Scheduler', ar: 'الجدولة' },
    def: {
      en: 'Artisan commands timed in bootstrap/app.php withSchedule (cron).',
      ar: 'أوامر Artisan مجدولة في bootstrap/app.php.',
    },
  },
]
