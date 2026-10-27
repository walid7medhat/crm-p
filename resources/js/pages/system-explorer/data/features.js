/**
 * Feature catalog with How-It-Works traces (verified from source).
 */
export const features = [
  {
    id: 'feat-lead-board',
    moduleId: 'leads',
    status: 'verified',
    name: { en: 'Open Leads Kanban Board', ar: 'فتح لوحة كانبان الليدز' },
    who: { en: 'Users with show-leads (or admin/super_admin bypass).', ar: 'من لديهم صلاحية show-leads (أو تجاوز admin/super_admin).' },
    simple: {
      en: 'User opens /kanban → Vue loads the board → API returns stages with leads → UI renders columns.',
      ar: 'المستخدم يفتح /kanban → Vue تحمّل اللوحة → الـ API ترجع المراحل مع الليدز → الواجهة ترسم الأعمدة.',
    },
    howItWorks: [
      { layer: 'UI', en: 'Navigate to /kanban (router meta requiresPermission: show-leads).', ar: 'الانتقال إلى /kanban (meta: show-leads).', path: 'resources/js/router.js' },
      { layer: 'UI', en: 'kanban.vue mounts; lead board components load; optional prefetchLeadBoard().', ar: 'kanban.vue يعمل؛ تحميل مكوّنات اللوحة؛ prefetch اختياري.', path: 'resources/js/pages/kanban.vue, resources/js/composables/leadBoardPrefetch.js' },
      { layer: 'API', en: 'GET /api/stages/kanban/stages-with-leads (jwt.auth, log.usage).', ar: 'GET /api/stages/kanban/stages-with-leads', path: 'routes/api.php → StageController@getStagesWithLeads' },
      { layer: 'Backend', en: 'StageController loads stages + visible leads for the user.', ar: 'StageController يحمّل المراحل والليدز المرئية للمستخدم.', path: 'app/Http/Controllers/Api/StageController.php' },
      { layer: 'DB', en: 'Reads stages, leads (+ relations as coded).', ar: 'قراءة stages و leads.', path: 'app/Models/Lead.php, app/Models/Stage.php' },
      { layer: 'UI', en: 'Board state updates; columns render.', ar: 'تحديث حالة اللوحة وعرض الأعمدة.', path: 'resources/js/components/kanban/leadList/leads.vue' },
    ],
    filesFirst: [
      'resources/js/pages/kanban.vue',
      'resources/js/components/kanban/leadList/leads.vue',
      'app/Http/Controllers/Api/StageController.php',
      'app/Http/Controllers/Api/LeadController.php',
    ],
    debug: {
      en: 'Check JWT token, show-leads permission in localStorage user, Network tab for stages-with-leads, server logs for StageController.',
      ar: 'تحقق من التوكن وصلاحية show-leads، تبويب Network لـ stages-with-leads، وسجلات StageController.',
    },
  },
  {
    id: 'feat-assign-responsible',
    moduleId: 'lead-assignment',
    status: 'verified',
    name: { en: 'Assign Responsible Person (manual)', ar: 'تعيين المسؤول يدويًا' },
    who: { en: 'Authorized lead managers (server-side checks in controller/request).', ar: 'مدراء الليدز المصرح لهم (فحوصات على السيرفر).' },
    simple: {
      en: 'From lead UI, assign a user → POST/PUT assign endpoint → Lead.responsible_person_id updates → history/events may fire.',
      ar: 'من واجهة الليد يتم التعيين → طلب API → تحديث responsible_person_id → قد تُسجَّل تاريخ/أحداث.',
    },
    howItWorks: [
      { layer: 'UI', en: 'Lead detail / board action triggers assign.', ar: 'إجراء من تفاصيل الليد أو اللوحة.', path: 'resources/js/components/kanban/ (lead UI)' },
      { layer: 'API', en: 'LeadController@assignResponsiblePerson with AssignResponsiblePersonRequest.', ar: 'LeadController@assignResponsiblePerson', path: 'app/Http/Controllers/Api/LeadController.php' },
      { layer: 'Backend', en: 'Validates request; updates lead; may write history.', ar: 'تحقق ثم تحديث الليد والتاريخ.', path: 'app/Http/Requests/…/AssignResponsiblePersonRequest' },
      { layer: 'DB', en: 'UPDATE leads.responsible_person_id (+ logs/history tables as coded).', ar: 'تحديث leads وجدول السجلات.', path: 'app/Models/Lead.php' },
      { layer: 'Side effects', en: 'Notifications/events possible (LeadUpdated listeners) — confirm per code path.', ar: 'إشعارات/أحداث محتملة — تحقق من المسار.', path: 'app/Listeners/, app/Notifications/', statusNote: 'partial' },
    ],
    filesFirst: [
      'app/Http/Controllers/Api/LeadController.php',
      'app/Services/LeadAssignmentService.php',
      'app/Models/LeadAssignmentLog.php',
    ],
    debug: {
      en: 'Inspect assign payload, 403/422 responses, lead_assignment_logs / lead_histories rows.',
      ar: 'افحص الـ payload وردود 403/422 وصفوف السجلات.',
    },
  },
  {
    id: 'feat-auto-assign',
    moduleId: 'lead-assignment',
    status: 'verified',
    name: { en: 'Automatic Lead Assignment (scheduler/job)', ar: 'التعيين التلقائي (جدولة/Job)' },
    who: { en: 'System scheduler / admin runNow.', ar: 'الجدولة أو تشغيل يدوي من الأدمن.' },
    simple: {
      en: 'Cron runs leads:auto-assign / realtime-assign → jobs/service score candidates → assign → log.',
      ar: 'الكرون يشغّل أوامر التعيين → الخدمة تقيّم المرشحين → تعيين → تسجيل.',
    },
    howItWorks: [
      { layer: 'Schedule', en: 'bootstrap/app.php schedules leads:auto-assign and leads:realtime-assign.', ar: 'جدولة الأوامر في bootstrap/app.php', path: 'bootstrap/app.php' },
      { layer: 'Job/Cmd', en: 'ProcessLeadAutoAssignmentJob / artisan commands invoke LeadAssignmentService.', ar: 'Job/أوامر تستدعي LeadAssignmentService', path: 'app/Jobs/ProcessLeadAutoAssignmentJob.php' },
      { layer: 'Service', en: 'LeadAssignmentService selects eligible sales, applies learning/performance weights.', ar: 'اختيار المبيعات المؤهلين وتطبيق الأوزان.', path: 'app/Services/LeadAssignmentService.php' },
      { layer: 'DB', en: 'Updates lead assignee; writes LeadAssignmentLog / patterns as implemented.', ar: 'تحديث الليد وكتابة السجلات.', path: 'app/Models/LeadAssignmentLog.php' },
      { layer: 'API (manual)', en: 'POST /api/lead-assignment/run for on-demand.', ar: 'POST /api/lead-assignment/run', path: 'app/Http/Controllers/Api/LeadAssignmentController.php' },
    ],
    filesFirst: [
      'bootstrap/app.php',
      'app/Services/LeadAssignmentService.php',
      'app/Http/Controllers/Api/LeadAssignmentController.php',
    ],
    debug: {
      en: 'Check queue workers, schedule:list, lead-assignment settings, assignment logs API.',
      ar: 'تحقق من الـ queue والجدولة وإعدادات التعيين وسجلات API.',
    },
  },
  {
    id: 'feat-convert-deal',
    moduleId: 'deals',
    status: 'verified',
    name: { en: 'Convert Lead to Deal', ar: 'تحويل Lead إلى Deal' },
    who: { en: 'Authenticated users allowed by LeadConversionController rules.', ar: 'مستخدمون مصرح لهم حسب قواعد LeadConversionController.' },
    simple: {
      en: 'UI checks can-convert → POST convert → creates Deal, links lead.converted_to_deal_id.',
      ar: 'الواجهة تتحقق can-convert → POST convert → إنشاء Deal وربط converted_to_deal_id.',
    },
    howItWorks: [
      { layer: 'UI', en: 'Deal/lead UI calls conversion APIs.', ar: 'واجهة الليد/الصفقة تستدعي التحويل.', path: 'resources/js/components/kanban/ (conversion UI)' },
      { layer: 'API', en: 'GET /api/leads/{lead}/can-convert then POST /api/leads/convert/to-deal.', ar: 'can-convert ثم convert', path: 'routes/api.php' },
      { layer: 'Backend', en: 'LeadConversionController@canConvert / @convert.', ar: 'LeadConversionController', path: 'app/Http/Controllers/Api/Deal/LeadConversionController.php' },
      { layer: 'DB', en: 'Inserts deals (+ parties/properties as coded); updates leads.converted_to_deal_id.', ar: 'إدراج deals وتحديث الليد.', path: 'app/Models/Deal.php, app/Models/Lead.php' },
      { layer: 'UI', en: 'User can open /kanban_deal for the deal pipeline.', ar: 'فتح /kanban_deal لمسار الصفقات.', path: 'resources/js/pages/kanban_deal.vue' },
    ],
    filesFirst: [
      'app/Http/Controllers/Api/Deal/LeadConversionController.php',
      'app/Models/Deal.php',
      'app/Models/Lead.php',
    ],
    debug: {
      en: 'Call can-convert response reasons; check validation errors inside convert(); verify converted_to_deal_id.',
      ar: 'افحص أسباب can-convert وأخطاء التحقق وconverted_to_deal_id.',
    },
  },
  {
    id: 'feat-listing-create',
    moduleId: 'listings',
    status: 'verified',
    name: { en: 'Create / Edit Listing', ar: 'إنشاء / تعديل عقار' },
    who: { en: 'Authenticated agents/admins per Listing model canEdit / policies.', ar: 'وكلاء/أدمن حسب canEdit والسياسات.' },
    simple: {
      en: 'Property form page → API Listing controllers → listings table + media morphs.',
      ar: 'نموذج العقار → Controllers الـ Listing → جدول listings والوسائط.',
    },
    howItWorks: [
      { layer: 'UI', en: '/property-form or /properties/:id/edit.', ar: 'صفحات النموذج.', path: 'resources/js/pages/listings/property-form.vue' },
      { layer: 'API', en: 'Listing API under Controllers/Api/Listing.', ar: 'واجهات Listing API', path: 'app/Http/Controllers/Api/Listing/' },
      { layer: 'Model', en: 'Listing relationships: area, project, owner, developer, galleryImages…', ar: 'علاقات Listing', path: 'app/Models/Listing.php' },
      { layer: 'AuthZ', en: 'Listing::canEdit / PropertyPolicy where applied.', ar: 'canEdit / PropertyPolicy', path: 'app/Policies/PropertyPolicy.php' },
    ],
    filesFirst: [
      'resources/js/pages/listings/property-form.vue',
      'app/Models/Listing.php',
      'app/Http/Controllers/Api/Listing/',
    ],
    debug: {
      en: '422 validation from Form Requests; permission/ownership failures; media upload paths.',
      ar: 'أخطاء التحقق 422 وفشل الملكية ومسارات الرفع.',
    },
  },
  {
    id: 'feat-login',
    moduleId: 'auth-permissions',
    status: 'verified',
    name: { en: 'Sign In (JWT)', ar: 'تسجيل الدخول (JWT)' },
    who: { en: 'Any valid user account.', ar: 'أي حساب مستخدم صالح.' },
    simple: {
      en: 'Sign-in form → POST /api/auth/login → JWT stored → axios attaches token → guarded routes open.',
      ar: 'نموذج الدخول → POST login → تخزين JWT → axios يرسل التوكن → فتح الراوتات المحمية.',
    },
    howItWorks: [
      { layer: 'UI', en: '/sign-in → sign-in.vue (layout: false).', ar: 'صفحة الدخول', path: 'resources/js/pages/authentication/sign-in.vue' },
      { layer: 'API', en: 'POST /api/auth/login → AuthController@login.', ar: 'AuthController@login', path: 'app/Http/Controllers/Api/AuthController.php' },
      { layer: 'Client', en: 'Token in localStorage; plugins/axios.js resolveAuthToken / jwt.auth on API.', ar: 'التوكن في localStorage وaxios', path: 'resources/js/plugins/axios.js' },
      { layer: 'Guard', en: 'router.beforeEach checks token + role/permission meta.', ar: 'حراسة الراوتر', path: 'resources/js/router.js' },
    ],
    filesFirst: [
      'resources/js/pages/authentication/sign-in.vue',
      'app/Http/Controllers/Api/AuthController.php',
      'resources/js/plugins/axios.js',
      'app/Http/Middleware/JwtAuthMiddleware.php',
    ],
    debug: {
      en: 'Invalid credentials, expired JWT (session_expired notice), CORS/base URL via getApiBaseUrl().',
      ar: 'بيانات خاطئة، JWT منتهي، أو خطأ base URL.',
    },
  },
  {
    id: 'feat-bitrix-webhook',
    moduleId: 'integrations',
    status: 'verified',
    name: { en: 'Bitrix24 Webhook → Lead', ar: 'Webhook بيتريكس → Lead' },
    who: { en: 'External Bitrix system (public webhook route).', ar: 'نظام Bitrix الخارجي.' },
    simple: {
      en: 'Bitrix POSTs webhook → controller → job/service maps payload → lead created/updated.',
      ar: 'Bitrix يرسل POST → Controller → Job/Service → إنشاء/تحديث Lead.',
    },
    howItWorks: [
      { layer: 'External', en: 'POST /api/bitrix24/webhook', ar: 'POST /api/bitrix24/webhook', path: 'routes/api.php' },
      { layer: 'Backend', en: 'Bitrix24WebhookController@handle', ar: 'Bitrix24WebhookController', path: 'app/Http/Controllers/Api/Bitrix24WebhookController.php' },
      { layer: 'Job', en: 'ProcessBitrixLeadJob (async processing path).', ar: 'ProcessBitrixLeadJob', path: 'app/Jobs/ProcessBitrixLeadJob.php' },
      { layer: 'DB', en: 'leads + integrations / bitrix sync state tables.', ar: 'جداول الليدز والمزامنة', path: 'app/Models/Lead.php, app/Models/BitrixSyncState.php' },
    ],
    filesFirst: [
      'app/Http/Controllers/Api/Bitrix24WebhookController.php',
      'app/Jobs/ProcessBitrixLeadJob.php',
      'app/Services/Bitrix24/',
    ],
    debug: {
      en: 'Webhook auth/signature if any; failed jobs; bitrix sync progress endpoints.',
      ar: 'مصادقة الـ webhook والـ jobs الفاشلة وتقدّم المزامنة.',
    },
  },
]
