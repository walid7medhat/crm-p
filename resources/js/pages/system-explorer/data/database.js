/**
 * Important tables inferred from Eloquent models (not live DB introspection).
 * FK notes distinguish model relations vs confirmed migration constraints.
 */
export const tables = [
  {
    id: 'leads',
    status: 'verified',
    purpose: { en: 'Core sales lead records.', ar: 'سجلات العملاء المحتملين.' },
    model: 'app/Models/Lead.php',
    pk: 'id',
    relations: [
      { to: 'stages', via: 'stage_id', type: 'belongsTo', evidence: 'Lead::stage()' },
      { to: 'users', via: 'responsible_person_id', type: 'belongsTo', evidence: 'Lead::responsiblePerson()' },
      { to: 'users', via: 'added_by', type: 'belongsTo', evidence: 'Lead::addedBy()' },
      { to: 'deals', via: 'converted_to_deal_id', type: 'belongsTo', evidence: 'Lead::convertedToDeal()' },
      { to: 'integrations', via: 'integration_id', type: 'belongsTo', evidence: 'Lead::integration()' },
      { to: 'areas', via: 'area_id', type: 'belongsTo', evidence: 'Lead::area()' },
      { to: 'lead_activities', via: 'lead_id', type: 'hasMany', evidence: 'Lead::activities()' },
      { to: 'lead_comments', via: 'lead_id', type: 'hasMany', evidence: 'Lead::comments()' },
      { to: 'lead_assignment_logs', via: 'lead_id', type: 'hasMany', evidence: 'Lead::assignmentLogs()' },
    ],
    notes: { en: 'Soft-delete / archive paths exist (archived endpoints).', ar: 'يوجد مسار أرشفة/حذف ناعم.' },
  },
  {
    id: 'deals',
    status: 'verified',
    purpose: { en: 'Deal pipeline records, often created from leads.', ar: 'سجلات الصفقات، غالبًا من تحويل الليدز.' },
    model: 'app/Models/Deal.php',
    pk: 'id',
    relations: [
      { to: 'leads', via: 'lead_id', type: 'belongsTo', evidence: 'Deal::lead()' },
      { to: 'stages', via: 'stage_id', type: 'belongsTo', evidence: 'Deal::stage()' },
      { to: 'listings', via: 'listing_id', type: 'belongsTo', evidence: 'Deal::listing()' },
      { to: 'users', via: 'responsible_person_id', type: 'belongsTo', evidence: 'Deal::responsiblePerson()' },
      { to: 'deal_parties', via: 'deal_id', type: 'hasMany', evidence: 'Deal::parties()' },
      { to: 'deal_documents', via: 'deal_id', type: 'hasMany', evidence: 'Deal::documents()' },
      { to: 'deal_properties', via: 'deal_id', type: 'hasMany', evidence: 'Deal::properties()' },
    ],
  },
  {
    id: 'listings',
    status: 'verified',
    purpose: { en: 'Property inventory / marketing listings.', ar: 'مخزون العقارات والإعلانات.' },
    model: 'app/Models/Listing.php',
    pk: 'id',
    relations: [
      { to: 'areas', via: 'area_id', type: 'belongsTo', evidence: 'Listing::area()' },
      { to: 'projects', via: 'project_id', type: 'belongsTo', evidence: 'Listing::project()' },
      { to: 'owners', via: 'owner_id', type: 'belongsTo', evidence: 'Listing::owner()' },
      { to: 'developers', via: 'developer_id', type: 'belongsTo', evidence: 'Listing::developer()' },
      { to: 'users', via: 'agent_id', type: 'belongsTo', evidence: 'Listing::agent()' },
      { to: 'gallery_images', via: 'morph', type: 'morphMany', evidence: 'Listing::galleryImages()' },
    ],
  },
  {
    id: 'stages',
    status: 'verified',
    purpose: { en: 'Pipeline stages for leads/deals kanban.', ar: 'مراحل المسار لليدز/الصفقات.' },
    model: 'app/Models/Stage.php',
    pk: 'id',
    relations: [
      { to: 'leads', via: 'stage_id', type: 'hasMany', evidence: 'used by Lead::stage()' },
      { to: 'deals', via: 'stage_id', type: 'hasMany', evidence: 'used by Deal::stage()' },
    ],
  },
  {
    id: 'users',
    status: 'verified',
    purpose: { en: 'CRM users (agents, admins, HR…).', ar: 'مستخدمو النظام.' },
    model: 'app/Models/User.php',
    pk: 'id',
    relations: [
      { to: 'roles', via: 'model_has_roles', type: 'spatie', evidence: 'spatie/laravel-permission' },
      { to: 'permissions', via: 'model_has_permissions', type: 'spatie', evidence: 'spatie/laravel-permission' },
    ],
  },
  {
    id: 'lead_assignment_logs',
    status: 'verified',
    purpose: { en: 'Audit trail for assignment decisions.', ar: 'سجل قرارات التعيين.' },
    model: 'app/Models/LeadAssignmentLog.php',
    pk: 'id',
    relations: [{ to: 'leads', via: 'lead_id', type: 'belongsTo', evidence: 'Lead::assignmentLogs()' }],
  },
  {
    id: 'integrations',
    status: 'verified',
    purpose: { en: 'External integration source metadata for leads.', ar: 'بيانات مصدر التكامل للليدز.' },
    model: 'app/Models/Integration.php',
    pk: 'id',
    relations: [{ to: 'leads', via: 'integration_id', type: 'hasMany', evidence: 'Lead::integration()' }],
  },
]

export const erMermaid = `erDiagram
  users ||--o{ leads : responsible_person
  stages ||--o{ leads : stage
  integrations ||--o{ leads : source
  leads ||--o| deals : converted_to
  leads ||--o{ lead_assignment_logs : logs
  stages ||--o{ deals : stage
  listings ||--o{ deals : listing
  deals ||--o{ deal_parties : parties
  deals ||--o{ deal_properties : properties
  areas ||--o{ listings : area
  owners ||--o{ listings : owner
  developers ||--o{ listings : developer
  users ||--o{ listings : agent`
