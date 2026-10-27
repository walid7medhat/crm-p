export const workflows = [
  {
    id: 'wf-lead-lifecycle',
    status: 'verified',
    name: { en: 'Lead lifecycle (board → work → convert)', ar: 'دورة حياة الـ Lead' },
    summary: {
      en: 'Lead enters system → appears on kanban stage → assigned → activities/comments → optional convert to deal.',
      ar: 'دخول الليد → ظهوره في مرحلة الكانبان → تعيين → أنشطة/تعليقات → تحويل اختياري لصفقة.',
    },
    steps: [
      { id: 'in', en: 'Create via UI, import, or integration webhook', ar: 'إنشاء من الواجهة أو استيراد أو webhook', paths: ['LeadController@store', 'IntegrationController', 'Bitrix24WebhookController'] },
      { id: 'board', en: 'Visible on /kanban via stages-with-leads', ar: 'يظهر على /kanban', paths: ['StageController@getStagesWithLeads'] },
      { id: 'assign', en: 'Manual assign or auto-assign engine', ar: 'تعيين يدوي أو تلقائي', paths: ['LeadController@assignResponsiblePerson', 'LeadAssignmentService'] },
      { id: 'engage', en: 'Comments & activities on lead', ar: 'تعليقات وأنشطة', paths: ['LeadComment', 'LeadActivity'] },
      { id: 'convert', en: 'POST leads/convert/to-deal when eligible', ar: 'تحويل لصفقة عند الأهلية', paths: ['LeadConversionController@convert'] },
      { id: 'deal', en: 'Continue on /kanban_deal', ar: 'متابعة على لوحة الصفقات', paths: ['kanban_deal.vue', 'DealController'] },
    ],
    mermaid: `flowchart LR
  A[Lead in] --> B[Kanban stage]
  B --> C[Assign]
  C --> D[Activities]
  D --> E{Convert?}
  E -->|yes| F[Deal board]
  E -->|no| D`,
  },
  {
    id: 'wf-request-lifecycle',
    status: 'verified',
    name: { en: 'Typical authenticated API request', ar: 'طلب API مصادق نموذجي' },
    summary: {
      en: 'Browser Vue → axios (/api) → jwt.auth → controller → model/DB → JSON → UI state.',
      ar: 'Vue → axios → jwt.auth → Controller → Model/DB → JSON → تحديث الواجهة.',
    },
    steps: [
      { id: 'ui', en: 'Component calls window.axios / api helper', ar: 'المكوّن يستدعي axios', paths: ['resources/js/plugins/axios.js'] },
      { id: 'http', en: 'HTTP to /api/...', ar: 'طلب HTTP إلى /api', paths: ['routes/api.php'] },
      { id: 'mw', en: 'jwt.auth (+ optional permission/throttle/log.usage)', ar: 'jwt.auth ووسائط أخرى', paths: ['JwtAuthMiddleware', 'bootstrap/app.php'] },
      { id: 'ctrl', en: 'Api controller method', ar: 'دالة الـ Controller', paths: ['app/Http/Controllers/Api/'] },
      { id: 'svc', en: 'Optional Service / FormRequest validation', ar: 'Service أو FormRequest اختياري', paths: ['app/Services/', 'app/Http/Requests/'] },
      { id: 'db', en: 'Eloquent model ↔ MySQL tables', ar: 'Eloquent ↔ الجداول', paths: ['app/Models/'] },
      { id: 'out', en: 'JsonResponse / API Resource → Vue updates', ar: 'استجابة JSON → تحديث Vue', paths: ['app/Http/Resources/'] },
    ],
    mermaid: `sequenceDiagram
  participant UI as Vue
  participant AX as axios
  participant MW as jwt.auth
  participant C as Controller
  participant DB as Database
  UI->>AX: API call
  AX->>MW: Bearer JWT
  MW->>C: authorized request
  C->>DB: Eloquent
  DB-->>C: rows
  C-->>UI: JSON`,
  },
  {
    id: 'wf-permission',
    status: 'verified',
    name: { en: 'Permission decision (UI + server)', ar: 'قرار الصلاحية (واجهة + سيرفر)' },
    summary: {
      en: 'Vue may hide links; server must still enforce. Route meta + Spatie middleware/policies.',
      ar: 'Vue قد تخفي الروابط؛ السيرفر يفرض فعليًا. meta الراوت + Spatie/Policies.',
    },
    steps: [
      { id: 'store', en: 'User roles/permissions in localStorage after login', ar: 'الأدوار/الصلاحيات في localStorage', paths: ['AuthController@login', 'sign-in.vue'] },
      { id: 'nav', en: 'Sidebar filters by isSuperAdmin / hasPermission', ar: 'السايدبار يفلتر حسب الدور', paths: ['header/index.vue'] },
      { id: 'route', en: 'router.beforeEach meta requiresPermission / requiresSuperAdmin', ar: 'حراسة الراوتر', paths: ['resources/js/router.js'] },
      { id: 'api', en: 'API jwt.auth + permission middleware / controller checks', ar: 'حماية الـ API', paths: ['routes/api.php', 'Spatie middleware'] },
    ],
    mermaid: `flowchart TD
  L[Login JWT + roles] --> N[Nav visibility]
  L --> R[Vue route guard]
  L --> A[API jwt.auth]
  A --> P[permission / policy]
  P --> OK[Controller runs]
  P --> X[403]`,
  },
]
