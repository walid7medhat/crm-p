/**
 * System Overview bilingual copy (EN UI chrome + AR Egyptian colloquial for module bodies & presentation).
 * English module reference text stays in @/data/systemOverviewModules.js — AR equivalents live under messages.ar.modules.
 */

export const SYSTEM_LANG_STORAGE_KEY = 'system_lang'

const en = {
  page: {
    accessDenied: 'You do not have access to this page.',
    backHome: 'Back to dashboard',
    sidebarProductMap: 'Product map',
    sidebarSystemMap: 'System map',
    sidebarCommercialOs: 'Commercial OS',
    sidebarArchitectureView: 'Architecture view',
    mapHint:
      'Investor-ready flow: pipeline, intelligence layer, and cross-module links.',
    viewLabel: 'View',
    viewModules: 'Modules',
    viewSystemMap: 'System map',
    demoMode: 'Demo mode',
    demoHint: 'Mock KPIs and highlights when demo is on.',
    demoBanner: 'Demo mode — illustrative KPIs & samples.',
    kicker: 'Super admin · Product narrative',
    heroTitleModules: 'Commercial platform map',
    heroTitleMap: 'Live architecture map',
    heroLeadModules:
      'How listings, leads, and deals connect — with measurable KPIs, flows, and the intelligence layer that automates decisions across the stack.',
    heroLeadMap:
      'Single canvas: inventory → pipeline → revenue, powered by scoring, routing, matching, and stage validation.',
    apiBase: 'API base:',
    accessOk: 'Access verified',
    accessPending: 'Server role check pending',
    footer: 'Internal product view · super admin',
    demoSnapshot: 'Demo snapshot',
    endpointNotePrefix: 'All paths are relative to',
    endpointNoteSuffix: '(same prefix as the SPA axios client).',
    productHighlights: 'Product highlights',
    langEn: 'EN',
    langAr: 'AR',
    navListings: 'Listings',
    navLeads: 'Leads',
    navDeals: 'Deals',
  },
  table: {
    field: 'Field / key',
    type: 'Type',
    desc: 'Description',
    method: 'Method',
    path: 'Path',
    notes: 'Notes',
  },
  sections: {
    overview: 'Overview',
    features: 'Features',
    workflows: 'Workflows',
    uiActions: 'UI actions',
    dataStructure: 'Data structure',
    apiEndpoints: 'API endpoints',
    specialLogic: 'Special logic',
    badgeDetail: 'Detail',
    badgeFlow: 'Flow',
    badgeUx: 'UX',
    badgeSchema: 'Schema',
    badgeRest: 'REST',
    badgeEngine: 'Engine',
  },
  mpc: {
    moduleFlow: 'Module flow',
    keyActions: 'Key actions',
    dependencies: 'Dependencies',
    depTargets: {
      listings: 'Listings',
      leads: 'Leads',
      deals: 'Deals',
      intelligence: 'Intelligence',
    },
  },
  presentation: {
    pipelineFoot: 'Revenue architecture',
    pipelinePath: 'Inventory → Pipeline → Close',
    intelligenceTitle: 'System intelligence layer',
    intelligenceSub: 'Automation & decisioning that connects inventory to revenue',
    intelligencePill: 'AI + Rules',
    crossTitle: 'Cross-module relationships',
    crossSub: 'How data and automation flow across the platform',
    crossHub: 'Intelligence',
    crossConnectionsAria: 'Connections',
    mapRibbonK: 'Architecture mode',
    mapRibbonH: 'Commercial operating system',
    mapRibbonP:
      'Inventory, pipeline, and revenue — unified by an intelligence layer that scores, routes, matches, and validates.',
    mapBand: 'System intelligence',
    mapMatrixTitle: 'Cross-module data flow',
    mapFootPill: 'Investor-ready map',
    mapFootNote: 'Mock KPIs and labels are illustrative when demo mode is on.',
  },
}

/** Egyptian Arabic (spoken / عامية) — module & presentation AR strings */
const ar = {
  page: {
    accessDenied: 'مش مسموح لك تشوف الصفحة دي.',
    backHome: 'ارجع للداشبورد',
    sidebarProductMap: 'خريطة المنتج',
    sidebarSystemMap: 'خريطة النظام',
    sidebarCommercialOs: 'نظام الشغل التجاري',
    sidebarArchitectureView: 'عرض المعمارية',
    mapHint:
      'عرض جاهز للعرض: خط السير، طبقة الذكاء، وروابط ما بين الموديولات.',
    viewLabel: 'العرض',
    viewModules: 'موديولات',
    viewSystemMap: 'خريطة النظام',
    demoMode: 'وضع الديمو',
    demoHint: 'كـ KPIs وهمية وتظليلات لما الديمو يكون شغال.',
    demoBanner: 'ديمو — أرقام وعينات للتوضيح بس.',
    kicker: 'سوبر أدمن · سرد المنتج',
    heroTitleModules: 'خريطة المنصة التجارية',
    heroTitleMap: 'خريطة المعمارية الحية',
    heroLeadModules:
      'إزاي الليستنجز والليدز والديلز متوصلة — مع KPIs، مسارات الشغل، وطبقة الذكاء اللي بتاخد قرارات على السيستم.',
    heroLeadMap:
      'شاشة واحدة: المخزون → الفانل → الإيراد، بالسكورينج، التوزيع، الماتشينج، وفاليديشن المراحل.',
    apiBase: 'الـ API:',
    accessOk: 'الدخول متأكد',
    accessPending: 'لسه بنتأكد الصلاحية من السيرفر',
    footer: 'عرض داخلي للمنتج · سوبر أدمن',
    demoSnapshot: 'لقطة ديمو',
    endpointNotePrefix: 'كل المسارات تحت',
    endpointNoteSuffix: '(نفس البريفكس اللي axios في السبا بيستخدمه).',
    productHighlights: 'مميزات المنتج',
    langEn: 'إنجليزي',
    langAr: 'عربي',
    navListings: 'العروض',
    navLeads: 'الليدز',
    navDeals: 'الصفقات',
  },
  table: {
    field: 'الحقل / المفتاح',
    type: 'النوع',
    desc: 'الوصف',
    method: 'الميثود',
    path: 'المسار',
    notes: 'ملاحظات',
  },
  sections: {
    overview: 'نظرة عامة',
    features: 'المميزات',
    workflows: 'سيناريوهات الشغل',
    uiActions: 'إجراءات الواجهة',
    dataStructure: 'الداتا',
    apiEndpoints: 'نقاط الـ API',
    specialLogic: 'لوجيك خاص',
    badgeDetail: 'تفاصيل',
    badgeFlow: 'مسار',
    badgeUx: 'واجهة',
    badgeSchema: 'سكيما',
    badgeRest: 'REST',
    badgeEngine: 'محرك',
  },
  mpc: {
    moduleFlow: 'مسار الموديول',
    keyActions: 'أهم الإجراءات',
    dependencies: 'مع مين متوصل',
    depTargets: {
      listings: 'العروض',
      leads: 'الليدز',
      deals: 'الصفقات',
      intelligence: 'الذكاء',
    },
  },
  presentation: {
    pipelineFoot: 'معمارية الإيراد',
    pipelinePath: 'مخزون → فانل → إغلاق',
    intelligenceTitle: 'طبقة ذكاء النظام',
    intelligenceSub: 'أتمتة وقرارات بين المخزون والإيراد',
    intelligencePill: 'ذكاء + قواعد',
    crossTitle: 'العلاقات بين الموديولات',
    crossSub: 'إزاي الداتا والأوتوميشن بيمشوا في المنصة',
    crossHub: 'الذكاء',
    crossConnectionsAria: 'الروابط',
    mapRibbonK: 'وضع المعمارية',
    mapRibbonH: 'نظام التشغيل التجاري',
    mapRibbonP:
      'مخزون، فانل، وإيراد — موحّدين بطبقة ذكاء بتعمل سكورينج، توجيه، ماتشينج، وفاليديشن.',
    mapBand: 'ذكاء النظام',
    mapMatrixTitle: 'تدفق الداتا بين الموديولات',
    mapFootPill: 'خريطة جاهزة للعرض',
    mapFootNote: 'الـ KPIs والليبلز وهمية لما الديمو يكون شغال.',
  },
  modules: {
    listings: {
      title: 'العروض',
      shortTitle: 'موديول العروض',
      badges: ['موافقات', 'أوتوميشن', 'خرايط'],
      overview:
        'ده المكان اللي فيه كل العقارات في السيستم (بيع / إيجار): وحدات مربوطة بمشاريع، مناطق، ملاك، ووكلاء. بيشغّل الديسكفري (بحث، خريطة)، الجونفرنس (مسودة / نشر / موافقة)، والتعاون (طلبات وصول، هوت ديل، كومنتات).',
      features: [
        'تعمل CRUD للعروض مع صورة رئيسية وجاليري (١٠+ لما تنشر لو مش أرض)، ومخططات من المشروع أو رفع، ومستندات.',
        'رقم وحدة فريد في المنطقة + حالة العرض؛ رقم مرجع أوتوماتيك؛ الأراضي فيها قواعد أوفر للغرف والجاليري.',
        'ظهور الكتالوج للجمهور متوقف على active، موافقة، أرشيف، الحالة — غير الأدمن يشوفوا اللي اتعمل لهم أبلوف بس.',
        'سكوب “عروضي” للوكلاء مع سلسلة تقارير مستويين.',
        'خريطة العقار: شجرة المناطق + المشاريع؛ إحداثيات مع ListingMapCoordinateResolver (Nominatim لو احتاجنا، بحد أمان).',
        'ماتشينج ذكي: مرونة في السعر والفلاتر عشان العرض يقابل الليد.',
        'طلبات وصول: بيانات المالك، رقم الوحدة، معاينة — إشعارات للوكيل، صلاحيات، رد، تحويل، مراجعة.',
        'طلب هوت ديل: فريق العروض ممكن يحتاج موافقة؛ المدير يوافق → علامة هوت ديل + طوابع.',
        'تنبيهات بحث: فلاتر محفوظة + جوب يبعت إيميل أول ما يظهر عرض جديد.',
        'إحصائيات، أبلوف كِتش، تتبع مباع/مأجور/محوّل، وجيل عروض على العقار.',
      ],
      workflows: [
        {
          title: 'إنشاء ونشر',
          body:
            'مسودة أو نشر؛ النشر يفاليدي الجاليري والمخططات؛ الجديد يبدأ مش متأكد لحد ما المدير يوافق للقائمة العامة.',
        },
        {
          title: 'موافقة',
          body:
            'مدير فريق العروض أو سوبر أدمن يوافق → يبان في اللستة الافتراضية؛ إشعار للي عمل والوكيل.',
        },
        {
          title: 'طلب وصول',
          body:
            'الوكيل يطلب تليفون المالك أو الداتا الكاملة أو المعاينة؛ المسؤول يوافق؛ الطالب يشوف الحقول المسموحة.',
        },
        {
          title: 'تعليم مباع / مأجور',
          body:
            'الحالة تتحول لمباع/مأجور؛ ممكن شركة خارجية + تواصل وكيل.',
        },
      ],
      uiActions: [
        'إنشاء / تعديل / حذف (صلاحيات + ملكية).',
        'تعيين وكيل، أرشفة، صورة رئيسية، رفع وحذف جاليري ومخططات ومستندات.',
        'تحريك على الخريطة، بحث محفوظ، تنبيه “بلغني”، طلب هوت ديل، رد على طلبات الوصول.',
      ],
      dataFields: [
        { field: 'reference_number', type: 'string', desc: 'رقم مرجع أوتوماتيك فريد' },
        { field: 'title, unit_number', type: 'string', desc: 'الوحدة + العنوان المعروض' },
        { field: 'listing_status', type: 'sale | rent', desc: 'بيع vs إيجار' },
        { field: 'status', type: 'string', desc: 'مسودة، منشور، محوّل، …' },
        { field: 'approved', type: 'bool', desc: 'بوابة موافقة المدير' },
        { field: 'price, area_id, project_id', type: 'money / FK', desc: 'سعر وجغرافيا' },
        { field: 'agent_id, owner_id', type: 'FK', desc: 'أطراف تجارية' },
        { field: 'is_hot_deal', type: 'Yes | No', desc: 'علامة ترويج + أثر موافقة' },
      ],
      endpointNotes: [
        'إندكس بالصفحات (فلاتر، كاش)',
        'بنز الخريطة + الإحداثيات',
        'إنشاء (multipart)',
        'تحديث',
        'ماتش ذكي في كونتكست الليد',
        'بحث محفوظ → إشعار',
        'موافقة المدير',
        'طلب وصول مالك / معاينة',
      ],
      specialLogic: [
        'ستاك ظهور العرض لغير الأدمن: active + متأكد + مش أرشيف + استبعاد مسودة/محوّل/مأجور حسب الإعداد.',
        'فلتر additional_features JSON في استعلام العروض.',
        'HotDealNotifiable لإشعار الموافقين.',
      ],
      highlights: [
        'طابور موافقة المدير',
        'خريطة OSM Nominatim كبديل',
        'إخفاء البائع المرتبط بالعرض في فاليديشن الديل',
      ],
      kpis: [
        { label: 'وحدات شغالة', value: '2.4k', delta: '+12%', up: true },
        { label: 'طابور الموافقة', value: '48', delta: '−6%', up: false },
        { label: 'تغطية الخريطة', value: '94%', delta: '+2%', up: true },
      ],
      microFlow: [
        { key: 'capture', label: 'تجميع' },
        { key: 'approve', label: 'موافقة' },
        { key: 'publish', label: 'نشر' },
        { key: 'match', label: 'ماتش' },
      ],
      actions: ['إنشاء', 'تعديل', 'موافقة', 'أرشيف', 'هوت ديل'],
      dependencies: [
        { target: 'leads', relation: 'بيطعم الماتشينج وتنبيهات البحث' },
        { target: 'deals', relation: 'listing_id على الصفقة' },
      ],
    },
    leads: {
      title: 'الليدز',
      shortTitle: 'موديول الليدز',
      badges: ['ذكاء اصطناعي', 'أوتوميشن', 'كانبان'],
      overview:
        'أول الفانل في الـ CRM: جهات اتصال مربوطة بمراحل كانبان ومسؤول عن الليد. في هيراركية للعرض، سكورينج ذكاء، توزيع أوتوماتيك، أنشطة/كومنت، وتحويل لديل.',
      features: [
        'مراحل كانبان؛ تغيير مرحلة مع تاريخ وبث.',
        'تليفون إجباري بفورمات؛ مصدر الإحالة يفتح حقول التزكية.',
        'جوب ذكاء الليد: سكور موزون، أولوية سخن/دافئ/بارد، نوايا، خطوة جاية، تفاصيل السكور.',
        'توزيع أوتوماتيك: أوضاع realtime/simple/mجدولة، لوجز، أوزان حضور وأداء.',
        'API للدوبلكيت بنفس الموبايل كإشارة ضعيفة.',
        'مشاركين ومتابعين على الليد.',
        'الإندكس: سوبر أدمن الكل؛ المدير فرع الفريق؛ السيلز اللي يخصهم واللي ضافوه.',
        'رجوع لمرحلة ١ بعد ساعات معينة في ترتيب المرحلة ٢ (KanbanSetting).',
      ],
      workflows: [
        {
          title: 'إنشاء ليد',
          body:
            'ستور متحقق → جوب ذكاء + جوب توزيع لو شغالين → بث LeadUpdated.',
        },
        {
          title: 'تحريك الكارت',
          body:
            'changeStage يحدّث آخر تغيير مرحلة؛ تاريخ + Pusher.',
        },
        {
          title: 'تعيين',
          body:
            'المدير يعيّن مسؤول ضمن الشجرة؛ يمسح ماركر الرجوع حيث مطبّق.',
        },
        {
          title: 'تحويل',
          body:
            'POST تحويل لديل ينشئ Deal وأطراف؛ الليد ياخد converted_to_deal_id.',
        },
      ],
      uiActions: [
        'إنشاء / تعديل / حذف (صلاحيات leads-*).',
        'سحب مراحل، تعيين مسؤول، كومنت وأنشطة وتذكيرات.',
        'استيراد شيت، مودال دوبلكيت، تقارير ليد (داش سوبر أدمن).',
      ],
      dataFields: [
        { field: 'lead_number', type: 'string', desc: 'تعريف فريد' },
        { field: 'stage_id', type: 'FK', desc: 'عمود كانبان' },
        { field: 'responsible_person_id', type: 'FK', desc: 'وكيل/مدير مسؤول' },
        { field: 'lead_source', type: 'string', desc: 'قناة + إضافات إحالة' },
        { field: 'score, priority, intent', type: 'int / string', desc: 'مخرجات الذكاء' },
        { field: 'converted_to_deal_id', type: 'FK nullable', desc: 'بعد التحويل' },
      ],
      endpointNotes: [
        'إندكس مجمّع بالمراحل في الريسبونس',
        'إنشاء',
        'تحريك الكارت',
        'تحويل → ديل',
        'إعدادات محرك التوزيع',
        'تشغيل توزيع يدوي',
      ],
      specialLogic: [
        'ProcessLeadIntelligenceJob يحدّ السكورинг عبر last_scored_at.',
        'ProcessLeadAutoAssignmentJob يحترم LeadAssignmentSetting.',
        'canViewLead يحمي العرض/التعديل؛ المتابعين مش في canViewCle — فجوة منتج.',
      ],
      highlights: [
        'نوايا OpenAI لو في API key',
        'SLA وتعلم ذاتي في محرك التوزيع',
      ],
      kpis: [
        { label: 'ليدز مفتوحة', value: '1.1k', delta: '+8%', up: true },
        { label: 'متوسط السكور', value: '72', delta: '+4pts', up: true },
        { label: 'متوجّهين أوتوماتيك', value: '61%', delta: '+5%', up: true },
      ],
      microFlow: [
        { key: 'ingest', label: 'إدخال' },
        { key: 'score', label: 'سكور' },
        { key: 'assign', label: 'توزيع' },
        { key: 'convert', label: 'تحويل' },
      ],
      actions: ['إنشاء', 'تعديل', 'نقل', 'تعيين', 'تحويل'],
      dependencies: [
        { target: 'listings', relation: 'API ماتش العقار' },
        { target: 'deals', relation: 'تحويل اتجاه واحد' },
        { target: 'intelligence', relation: 'سكورينج وتوزيع' },
      ],
    },
    deals: {
      title: 'الصفقات',
      shortTitle: 'موديول الصفقات',
      badges: ['بوابات مراحل', 'امتثال'],
      overview:
        'فانل بعد التأهيل لصفقات أولية وثانوية وإيجار: مالية، ربط بالعرض (وفيها listing_id)، أطراف، مستندات، وفاليديشن صارم قبل أي خطوة.',
      features: [
        'أنواع ديل: أولي | ثانوي | إيجار مع مراحل منفصلة.',
        'إنشاء من تحويل ليد؛ ستور غني مع مستندات multipart.',
        'DealStageValidator: حقول مطلوبة لكل مرحلة؛ listing_id يشيل اشتراطات بائع/مؤجر لو الداتا في العرض.',
        'أطراف بدور أساسي لكل نوع؛ مستندات مصنّفة.',
        'أنشطة وكومنت مع مرفقات ومنشن؛ تاريخ موحّد مع lead_histories.',
        'Visibility visibleFor — المدير يشوف صفقات الفرع؛ السيلز صفقاته؛ استثناءات يوزر قديمة.',
      ],
      workflows: [
        {
          title: 'تحويل من ليد',
          body:
            'تشييك صلاحية → مرحلة ديل → طرف مشتري/مستأجر → تاريخ + بث.',
        },
        {
          title: 'تقديم المرحلة',
          body:
            'check-stage-requirements → change-stage أو تحديث مع تعبئة KYC/مالية.',
        },
        {
          title: 'تعيين',
          body:
            'المدير يعيّن مسؤول؛ حدث DealUpdated.',
        },
        {
          title: 'خسارة',
          body:
            'سبب الخسارة على التحديثات.',
        },
      ],
      uiActions: [
        'سحب كانبان مع مودال الحقول المطلوبة.',
        'رفع مستندات (ضغط WebP للصور)، حفظ جزئي لكل الأطراف.',
        'بحث صفقات (استعلام كامل يشمل الأطراف والليد المربوط).',
      ],
      dataFields: [
        { field: 'deal_number', type: 'string', desc: 'غالبًا من الليد' },
        { field: 'deal_type', type: 'enum', desc: 'أولي | ثانوي | إيجار' },
        { field: 'stage_id', type: 'FK', desc: 'فانل الصفقة' },
        { field: 'status', type: 'enum', desc: 'مسودة … ملغاة' },
        { field: 'listing_id', type: 'FK nullable', desc: 'ربط بالعرض' },
        { field: 'deal_total_amount', type: 'decimal', desc: 'قيمة المعاملة' },
        { field: 'responsible_person_id', type: 'FK', desc: 'مالك الصفقة' },
      ],
      endpointNotes: [
        'صفقات + فلاتر',
        'كانبان مجمّع بالمراحل',
        'فحص متطلبات المرحلة',
        'تغيير مرحلة متحقق',
        'تحديث كامل + مستندات',
        'تحويل من ليد + أطراف',
      ],
      specialLogic: [
        'Soft delete على الصفقات.',
        'assignResponsiblePerson و last_stage_change_at — اتأكد من fillable.',
        'التاريخ يدمج صفوف الليد للتايم لاين الموحّد.',
      ],
      highlights: [
        'فاليديشن واعي بالعرض',
        'تايم لاين موحّد (ليد + ديل)',
      ],
      kpis: [
        { label: 'قيمة الفانل', value: 'AED 420M', delta: '+14%', up: true },
        { label: 'قيد التنفيذ', value: '312', delta: '+3%', up: true },
        { label: 'نسبة تجاوز المراحل', value: '88%', delta: '±0%', up: null },
      ],
      microFlow: [
        { key: 'open', label: 'فتح' },
        { key: 'kyc', label: 'KYC' },
        { key: 'validate', label: 'فاليديت' },
        { key: 'close', label: 'إغلاق' },
      ],
      actions: ['تعديل', 'مرحلة', 'تعيين', 'مستندات', 'ربح/خسارة'],
      dependencies: [
        { target: 'leads', relation: 'lead_id / دمج تاريخ' },
        { target: 'listings', relation: 'ربط مخزون وفاليديشن' },
        { target: 'intelligence', relation: 'بوابات المراحل' },
      ],
    },
  },
  presentation: {
    pipeline: {
      listings: { label: 'العروض', tagline: 'مخزون واكتشاف' },
      leads: { label: 'الليدز', tagline: 'فانل وتأهيل' },
      deals: { label: 'الصفقات', tagline: 'معاملات' },
    },
    intelligenceEngines: [
      {
        title: 'سكورينج الليد بالذكاء',
        subtitle: 'أولوية ونية',
        summary:
          'سكور موزون، سخن/دافئ/بارد، نوايا OpenAI لو موجودة، وأحسن خطوة جاية.',
      },
      {
        title: 'توزيع أوتوماتيك',
        subtitle: 'محرك التوجيه',
        summary:
          'أوضاع لحظية وبسيطة ومجدولة؛ أوزان حضور وأداء؛ لوجز توزيع.',
      },
      {
        title: 'محرك الماتشينج',
        subtitle: 'عرض ↔ ليد',
        summary:
          'ماتش ذكي لمرونة السعر؛ تنبيهات لما يظهر عرض جديد.',
      },
      {
        title: 'فاليديشن المراحل',
        subtitle: 'بوابات امتثال',
        summary:
          'حقول ومستندات مطلوبة لكل مرحلة صفقة؛ قواعد واعية بـ listing_id.',
      },
    ],
    intelligenceFeeds: {
      listings: 'عروض',
      leads: 'ليدز',
      deals: 'صفقات',
    },
    crossEdges: [
      'سكور وتوزيع',
      'فاليديت المراحل',
      'ماتش وتنبيهات',
      'تحويل',
      'listing_id',
    ],
    mapEngineTitles: [
      'سكورينج الليد بالذكاء',
      'توزيع أوتوماتيك',
      'محرك الماتشينج',
      'فاليديشن المراحل',
    ],
  },
}

export const messages = { en, ar }
