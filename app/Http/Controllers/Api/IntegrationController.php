<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Integration;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Stage;
use App\Models\Lead;
use Carbon\Carbon;
use App\Helpers\LeadHistoryHelper;
use App\Models\LeadHistory;
use App\Events\LeadUpdated;

class IntegrationController extends Controller
{
    private const META_GRAPH_VERSION = 'v18.0';
    private const META_GRAPH_BASE = 'https://graph.facebook.com';

    /**
     * List authenticated user's integrations.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return ApiResponse::error('Unauthorized', 401);
            }
            $integrations = Integration::where('user_id', $user->id)
                ->with(['project', 'responsiblePerson'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn (Integration $i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'crm_entity' => $i->crm_entity,
                    'expert_mode' => $i->expert_mode,
                    'duplicate_handling' => $i->duplicate_handling,
                    'project' => $i->project ? ['id' => $i->project->id, 'name' => $i->project->name] : null,
                    'lead_source' => $i->lead_source,
                    'page_id' => $i->page_id,
                    'facebook_form_id' => $i->facebook_form_id,
                    'facebook_form_name' => $i->facebook_form_name,
                    'responsible_person' => $i->responsiblePerson ? ['id' => $i->responsiblePerson->id, 'name' => $i->responsiblePerson->name] : null,
                    'status' => $i->status,
                    'created_at' => $i->created_at?->toIso8601String(),
                    'leads_count'=>$i->leads->count(),
                    'track_enabled' => $i->track_enabled,
                    'track_keyword' => $i->track_keyword,
                ]);

            return ApiResponse::success($integrations, 'Integrations retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('Integration index error: ' . $e->getMessage());
            return ApiResponse::error('Failed to list integrations', 500);
        }
    }

    /**
     * Fetch Facebook Pages using server-side token.
     */
    public function fetchMetaPages(): JsonResponse
    {
        $accessToken = env('META_ACCESS_TOKEN');
        
        if (!$accessToken) {
            Log::error('META_ACCESS_TOKEN not set in environment');
            return ApiResponse::error('Meta access token not configured', 500);
        }

        $url = self::META_GRAPH_BASE . '/' . self::META_GRAPH_VERSION . '/me/accounts';
        $params = [
            'access_token' => $accessToken,
            'fields' => 'id,name,access_token',
            'limit' => 100
        ];

        try {
            $response = Http::timeout(15)->get($url, $params);

            if (!$response->successful()) {
                $body = $response->json() ?? [];
                $metaMessage = $body['error']['message'] ?? $body['error']['error_user_msg'] ?? 'Unknown error';
                Log::warning('Meta API pages error', ['status' => $response->status(), 'body' => $body]);
                return ApiResponse::error('Failed to fetch pages from Meta: ' . $metaMessage, 400);
            }

            $data = $response->json();
            $pages = $data['data'] ?? [];
            
            $list = array_map(fn ($p) => [
                'id' => $p['id'] ?? null, 
                'name' => $p['name'] ?? 'Unnamed',
                   'access_token' => $p['access_token'] ?? null
            ], $pages);

            return ApiResponse::success(['pages' => $list], 'Pages retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('Fetch Meta pages error: ' . $e->getMessage());
            return ApiResponse::error('Failed to fetch pages: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Fetch Meta forms for a specific page using Page Access Token
     */
    public function fetchMetaForms(Request $request): JsonResponse
    {
        $request->validate([
            'page_id' => 'required|string',
            'page_access_token' => 'required|string', // بناخد التوكن بتاع الصفحة
            'cursor' => 'nullable|string',
        ]);
    
        $pageId = $request->input('page_id');
        $pageAccessToken = $request->input('page_access_token');
        $cursor = $request->input('cursor');
    
        $url = self::META_GRAPH_BASE . '/' . self::META_GRAPH_VERSION . '/' . $pageId . '/leadgen_forms';
        $params = [
            'access_token' => $pageAccessToken, // استخدام Page Access Token
            'fields' => 'id,name,status,leads_count,created_time',
            'limit' => 50,
        ];
        
        if ($cursor) {
            $params['after'] = $cursor;
        }
    
        try {
            $response = Http::timeout(15)->get($url, $params);
    
            if (!$response->successful()) {
                $body = $response->json() ?? [];
                $metaMessage = $body['error']['message'] ?? $body['error']['error_user_msg'] ?? 'Unknown error';
                Log::warning('Meta API forms error', ['status' => $response->status(), 'body' => $body]);
                return ApiResponse::error('Failed to fetch forms from Meta: ' . $metaMessage, 400);
            }
    
            $data = $response->json();
            $forms = $data['data'] ?? [];
            $paging = $data['paging'] ?? [];
            
            $nextCursor = $paging['cursors']['after'] ?? null;
            $hasNext = isset($paging['next']);
    
            $list = array_map(function ($form) {
                return [
                    'id' => $form['id'] ?? null,
                    'name' => $form['name'] ?? 'Unnamed',
                    'status' => $form['status'] ?? null,
                    'leads_count' => $form['leads_count'] ?? 0,
                    'created_time' => $form['created_time'] ?? null,
                ];
            }, $forms);
    
            return ApiResponse::success([
                'forms' => $list,
                'next_cursor' => $nextCursor,
                'has_next' => $hasNext,
            ], 'Forms retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('Fetch Meta forms error: ' . $e->getMessage());
            return ApiResponse::error('Failed to fetch forms: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Store a new integration.
     */
    public function store(Request $request): JsonResponse
    {
        // dd($request->field_mappings);
        $request->validate([
            'name' => 'required|string|max:255',
            'crm_entity' => 'required|string',
            'expert_mode' => 'boolean',
            'duplicate_handling' => 'required|in:allow,replace,merge',
            'project_id' => 'nullable|exists:projects,id',
            'lead_source' => 'nullable|string',
            'page_id' => 'nullable|string',
            'facebook_form_id' => 'nullable|string',
            'facebook_form_name' => 'nullable|string|max:500',
            'field_mappings' => 'nullable|array',
            'responsible_person_id' => 'nullable|exists:users,id',
            'dont_make_responsible_if_not_clocked_in' => 'boolean',
            'status' => 'in:active,inactive',
            'track_enabled' => 'boolean',
            'track_keyword' => 'nullable|string|max:255|required_if:track_enabled,true',
        ]);

        $user = auth()->user();

        try {
            $integration = Integration::create([
                'user_id' => $user->id,
                'name' => $request->input('name'),
                'crm_entity' => $request->input('crm_entity'),
                'expert_mode' => $request->input('expert_mode', false),
                'duplicate_handling' => $request->input('duplicate_handling', 'merge'),
                'project_id' => $request->input('project_id'),
                'lead_source' => $request->input('lead_source'),
                'page_id' => $request->input('page_id'),
                'facebook_form_id' => $request->input('facebook_form_id'),
                'facebook_form_name' => $request->input('facebook_form_name'),
                'field_mappings' => $request->input('field_mappings', []),
                'responsible_person_id' => $request->input('responsible_person_id'),
                'dont_make_responsible_if_not_clocked_in' => $request->input('dont_make_responsible_if_not_clocked_in', true),
                'status' => $request->input('status', 'active'),
                'track_enabled' => $request->input('track_enabled', false),
                'track_keyword' => $request->input('track_keyword'),
            ]);

            return ApiResponse::success([
                'id' => $integration->id,
                'name' => $integration->name,
                'facebook_form_id' => $integration->facebook_form_id,
                'facebook_form_name' => $integration->facebook_form_name,
            ], 'Integration created successfully');
        } catch (\Throwable $e) {
            Log::error('Store integration error: ' . $e->getMessage());
            return ApiResponse::error('Failed to save integration: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get single integration.
     */
    public function show(Integration $integration): JsonResponse
    {
        if ($integration->user_id !== auth()->id()) {
            return ApiResponse::error('Unauthorized', 403);
        }

        return ApiResponse::success([
            'id' => $integration->id,
            'name' => $integration->name,
            'crm_entity' => $integration->crm_entity,
            'expert_mode' => $integration->expert_mode,
            'duplicate_handling' => $integration->duplicate_handling,
            'project_id' => $integration->project_id,
            'lead_source' => $integration->lead_source,
            'page_id' => $integration->page_id,
            'facebook_form_id' => $integration->facebook_form_id,
            'facebook_form_name' => $integration->facebook_form_name,
            'field_mappings' => $integration->field_mappings,
            'responsible_person_id' => $integration->responsible_person_id,
            'dont_make_responsible_if_not_clocked_in' => $integration->dont_make_responsible_if_not_clocked_in,
            'status' => $integration->status,
            'created_at' => $integration->created_at?->toIso8601String(),
            'track_enabled' => $integration->track_enabled,
            'track_keyword' => $integration->track_keyword,
        ], 'Integration retrieved successfully');
    }

    /**
     * Update an integration.
     */
    public function update(Request $request, Integration $integration): JsonResponse
    {
        if ($integration->user_id !== auth()->id()) {
            return ApiResponse::error('Unauthorized', 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'crm_entity' => 'sometimes|string',
            'expert_mode' => 'sometimes|boolean',
            'duplicate_handling' => 'sometimes|in:allow,replace,merge',
            'project_id' => 'nullable|exists:projects,id',
            'lead_source' => 'nullable|string',
            'page_id' => 'nullable|string',
            'facebook_form_id' => 'nullable|string',
            'facebook_form_name' => 'nullable|string|max:500',
            'field_mappings' => 'nullable|array',
            'responsible_person_id' => 'nullable|exists:users,id',
            // 'dont_make_responsible_if_not_clocked_in' => 'sometimes|boolean',
            'status' => 'sometimes|in:active,inactive',
            'track_enabled' => 'boolean',
            'track_keyword' => 'nullable|string|max:255|required_if:track_enabled,true',
        ]);

        try {
            $integration->update($request->all());

            return ApiResponse::success([
                'id' => $integration->id,
                'name' => $integration->name,
            ], 'Integration updated successfully');
        } catch (\Throwable $e) {
            Log::error('Update integration error: ' . $e->getMessage());
            return ApiResponse::error('Failed to update integration', 500);
        }
    }

    /**
     * Delete an integration.
     */
    public function destroy(Integration $integration): JsonResponse
    {
        if ($integration->user_id !== auth()->id()) {
            return ApiResponse::error('Unauthorized', 403);
        }

        try {
            $integration->delete();
            return ApiResponse::success(null, 'Integration deleted successfully');
        } catch (\Throwable $e) {
            Log::error('Delete integration error: ' . $e->getMessage());
            return ApiResponse::error('Failed to delete integration', 500);
        }
    }
    public function fetchMetaLeads(Request $request,$form_id)
        {
            // $request->validate([
            //     'form_id' => 'required|string',
            // ]);
            
        
            // $integration = Integration::where(
            //     'facebook_form_id',
            //     $form_id
            // )->firstOrFail();
        
            // $token = 'EAAW4JN9suLQBQ0NOcfe2ZBmpoGOSvCus7R5NQJoSqnpoiV3u2NGZBz1QoG6yDBXcfa3YNftZBSOsmKJ3ZCm6oEPGIFLcUKXdO67VhXCsXPuWDkVNbouRjtxGTdiiRu9chW3yT3Kf3b6HyPxuzpsEBEGaZC0LfyT2SQuoD4sZBL6JqaOdasXOIG04xlhB2hikZAaWuDh8E0ZD';
        $token=env('META_ACCESS_TOKEN');
            $url = self::META_GRAPH_BASE . '/' .
                self::META_GRAPH_VERSION .
                "/{$form_id}/leads";
        
            $response = Http::get($url, [
                'access_token' => $token,
                'fields' => 'created_time,ad_id,field_data'
            ]);
        
            $leads = $response->json()['data'] ?? [];
        
            $stage = Stage::where('stage_type', 'lead')
                ->orderBy('order')
                ->first();
        
            foreach ($leads as $metaLead) {
        
                $fields = $this->mapMetaFields(
                    $metaLead['field_data']
                );
        
                Lead::create([
                    'lead_name' =>
                        'suhoor event - aldar-fab',
        
                    'first_name' =>
                        $this->extractName($fields),
        
                    'email' =>
                        $fields['email'] ?? null,
        
                    'work_phone' =>
                        $fields['phone_number']
                        ?? $fields['phone']
                        ?? null,
        
                    'stage_id' => $stage?->id,
        
                    'lead_source' => 'Social Media-Facebook',
        
                    'ad_id' =>
                        $metaLead['ad_id'] ?? null,
        
                    'added_by' =>1,
                    'responsible_person_id'=>1,
        //             'created_at' =>
        // isset($metaLead['created_time'])
        //     ? Carbon::parse($metaLead['created_time'])
        //     : null,
                ]);
            }
        
            return ApiResponse::success(
                null,
                'Leads imported successfully'
            );
        }
          /**
     * Verify Facebook Webhook (GET request)
     */
    public function verifyWebhook(Request $request)
    {
        Log::info('🔍 Webhook verification request', $request->all());

        $verifyToken = env('META_WEBHOOK_VERIFY_TOKEN', 'oia777');
        
        $mode = $request->input('hub_mode');
        $token = $request->input('hub_verify_token');
        $challenge = $request->input('hub_challenge');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::info('✅ Webhook verified successfully');
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('❌ Webhook verification failed', [
            'mode' => $mode,
            'token' => $token
        ]);

        return response('Forbidden', 403);
    }

    /**
     * Handle Facebook Webhook (POST request)
     */
    public function handleWebhook(Request $request)
    {
        Log::info('📥 Facebook Webhook received', $request->all());

        try {
            $data = $request->all();

            // التأكد إنها مش challenge request
            if (isset($data['hub_challenge'])) {
                return response($data['hub_challenge'], 200);
            }

            $entries = $data['entry'] ?? [];

            foreach ($entries as $entry) {
                $pageId = $entry['id'] ?? null;
                $changes = $entry['changes'] ?? [];

                foreach ($changes as $change) {
                    if (($change['field'] ?? '') === 'leadgen') {
                        $value = $change['value'] ?? [];
                        
                        $formId = $value['form_id'] ?? null;
                        $leadgenId = $value['leadgen_id'] ?? null;
                        $createdTime = $value['created_time'] ?? null;

                        Log::info('📋 New lead detected', [
                            'page_id' => $pageId,
                            'form_id' => $formId,
                            'leadgen_id' => $leadgenId,
                        ]);

                        $integration = Integration::where('page_id', $pageId)
                            ->where('facebook_form_id', $formId)
                            ->where('status', 'active')
                            ->first();

                        if (!$integration) {
                            Log::warning('⚠️ No active integration found', [
                                'page_id' => $pageId,
                                'form_id' => $formId
                            ]);
                            continue;
                        }

                        // جلب تفاصيل الليد من Meta API
                        $this->fetchAndStoreLead($leadgenId, $integration);
                    }
                }
            }

            return response()->json(['status' => 'success'], 200);

        } catch (\Exception $e) {
            Log::error('❌ Webhook error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * جلب تفاصيل الليد من Meta API
     */
   private function fetchAndStoreLead($leadgenId, Integration $integration)
{
    try {
        // نجيب الـ token
        $token = env('META_ACCESS_TOKEN');
        
        // التحقق من عدم وجود الليد مكرر
        $existingLead = Lead::where('meta_lead_id', $leadgenId)->first();
        
        if ($existingLead) {
            Log::info('Lead already exists', ['meta_lead_id' => $leadgenId]);
            return;
        }
        
        $url = self::META_GRAPH_BASE . '/' . self::META_GRAPH_VERSION . "/{$leadgenId}";
        
        $response = Http::get($url, [
            'access_token' => $token,
            'fields' => 'created_time,ad_id,field_data'
        ]);

        if (!$response->successful()) {
            Log::error('Failed to fetch lead details', [
                'leadgen_id' => $leadgenId,
                'response' => $response->json()
            ]);
            return;
        }

        $leadData = $response->json();
        $fields = $this->mapMetaFields($leadData['field_data'] ?? []);
        
        // جلب المرحلة المناسبة
        $stage = Stage::where('stage_type', 'lead')
            ->orderBy('order')
            ->first();

        // ============= التعديل هنا =============
        // تجهيز البيانات الأساسية
        $leadDataArray = [
                'lead_name' => $integration->name ?? 'Facebook Lead',
            'integration_id' => $integration->id,
            'meta_lead_id' => $leadgenId,
            'stage_id' => $stage?->id,
            'lead_source' => $integration->lead_source ?? 'Social Media - Facebook',
            'project_id' => $integration->project_id,
            'ad_id' => $leadData['ad_id'] ?? null,
            'added_by' => $integration->user_id,
            'responsible_person_id' => $integration->responsible_person_id ?? 1,
            'raw_meta_data' => json_encode($leadData, JSON_UNESCAPED_UNICODE),
            'created_at' => isset($leadData['created_time']) 
                ? Carbon::parse($leadData['created_time']) 
                : now(),
        ];

        // تجهيز field mappings
        $fieldMappingsData = [];
        $mappings = $integration->field_mappings ?? [];

        // الحقول المعروفة في جدول leads
        $knownColumns = [
            'lead_name', 'first_name', 'last_name', 'email', 
            'work_phone', 'whatsapp_number', 'secondary_email',
            'work_phone_2', 'date_of_birth', 'source_information'
        ];

        foreach ($mappings as $mapping) {
            $metaField = $mapping['meta_field'] ?? null;
            $crmField = $mapping['crm_field'] ?? null;

            if ($metaField && $crmField && isset($fields[$metaField])) {
                $value = $fields[$metaField];
                
                // لو الحقل ده column معروف في جدول leads
                if (in_array($crmField, $knownColumns)) {
                    $leadDataArray[$crmField] = $value;
                } else {
                    // لو مش معروف، يحطه في field_mappings_data
                    $fieldMappingsData[$crmField] = $value;
                }
            }
        }

        // تعبئة البيانات الأساسية لو مش موجودة في المابينج
        if (!isset($leadDataArray['first_name']) || empty($leadDataArray['first_name'])) {
            $leadDataArray['first_name'] = $this->extractName($fields);
        }

        if (!isset($leadDataArray['last_name']) && !empty($fields['last_name'] ?? $fields['surname'] ?? null)) {
            $leadDataArray['last_name'] = $fields['last_name'] ?? $fields['surname'] ?? null;
        }

        if (!isset($leadDataArray['email']) && !empty($fields['email'] ?? null)) {
            $leadDataArray['email'] = $fields['email'];
        }

        if (!isset($leadDataArray['work_phone'])) {
            $leadDataArray['work_phone'] = $fields['phone_number'] ?? 
                                           $fields['phone'] ?? 
                                           $fields['work_phone'] ?? 
                                           $fields['work_phone_number'] ?? 
                                           $fields['mobile'] ?? 
                                           $fields['cell'] ?? 
                                           null;
        }

        // بناء lead_name
        if (!isset($leadDataArray['lead_name']) || empty($leadDataArray['lead_name'])) {
            $firstName = $leadDataArray['first_name'] ?? '';
            $lastName = $leadDataArray['last_name'] ?? '';
            $fullName = trim($firstName . ' ' . $lastName);
            $leadDataArray['lead_name'] = !empty($fullName) ? $fullName : 'Facebook Lead';
        }

        // إضافة field_mappings_data
        $leadDataArray['field_mappings_data'] = json_encode($fieldMappingsData, JSON_UNESCAPED_UNICODE);

        // إنشاء الليد
        $lead = Lead::create($leadDataArray);
        // ======================================
         $lead->lead_branch_source=$lead->responsiblePerson?->admin_parent?->name;
          $lead->save();
        LeadHistoryHelper::log(
            $lead->id,
            ['action' => 'created', 'source' => 'facebook_webhook','name'=>$lead->lead_name,'lead_branch_source'=>$lead->responsiblePerson?->admin_parent?->name]
        );
        
        broadcast(new LeadUpdated($lead, 'created'));
        
        Log::info('✅ Lead stored successfully', [
            'lead_id' => $lead->id,
            'meta_lead_id' => $leadgenId,
            'lead_name' => $lead->lead_name
        ]);

    } catch (\Exception $e) {
        Log::error('❌ Error fetching/storing lead: ' . $e->getMessage(), [
            'leadgen_id' => $leadgenId,
            'integration_id' => $integration->id,
            'trace' => $e->getTraceAsString()
        ]);
    }
}
    
        private function mapMetaFields(array $fields): array
        {
            $mapped = [];
        
            foreach ($fields as $field) {
                $mapped[$field['name']] =
                    $field['values'][0] ?? null;
            }
        
            return $mapped;
        }
        /**
         * Extract lead name safely
         */
        private function extractName(array $fields): string
        {
            return
                $fields['full_name']
                ?? $fields['name']
                ?? $fields['first_name']
                ?? $fields['full name'] ?? 'Facebook Lead';
        }
private function applyFieldMappings(array $fields, array $mappings): array
{
    $result = [];

    foreach ($mappings as $mapping) {
        $metaField = $mapping['meta_field'] ?? null;
        $crmField = $mapping['crm_field'] ?? null;

        if ($metaField && $crmField && isset($fields[$metaField])) {
            // Check if column exists in 'leads' table
            if (\Schema::hasColumn('leads', $crmField)) {
                $result[$crmField] = $fields[$metaField];
            } else {
                // Store in JSON field if column not exists
                $result['field_mappings_data'][$crmField] = $fields[$metaField];
            }
        }
    }

    return $result;
}
public function store_website(Request $request)
{
    $data = $request->all();
$fieldData = [];

foreach ($data as $key => $value) {

    if (in_array($key, ['responsible_person_id','lead_name','source'])) {
        continue;
    }

    if ($value !== null && $value !== '') {
        $fieldData[] = [
            'name' => $key,
            'values' => [(string) $value]
        ];
    }
}
$fieldMappings = [
    'field_data' => $fieldData
];
    $stage = Stage::where('stage_type', 'lead')
        ->orderBy('order')
        ->first();
\Log::info($data['responsible_person_id']);
    $lead = Lead::create([
        'integration_id' => null,
        'meta_lead_id' => null,
        'lead_name' => $data['lead_name'] ?html_entity_decode($data['lead_name'], ENT_QUOTES, 'UTF-8') : 'Website Lead',
        'first_name' => $data['name'] ?? null,
        'last_name' => $data['last_name'] ?? null,
        'email' => $data['email'] ?? null,
        'work_phone' => $data['phone'] ?? null,
        'stage_id' => $stage?->id,
        'lead_source' => 'Oiaproperties.com',
        'ad_id' => null,
        'added_by' => 1,
        'responsible_person_id' =>(int) $data['responsible_person_id'] ?? 1,
        'field_mappings_data' => json_encode($data),
        'raw_meta_data' => json_encode($fieldMappings),
    ]);
     $lead->lead_branch_source=$lead->responsiblePerson?->admin_parent?->name;
          $lead->save();

    LeadHistoryHelper::log($lead->id, ['action' => 'created','name'=>$lead->lead_name,'lead_branch_source'=>$lead->responsiblePerson?->admin_parent?->name]);
    broadcast(new LeadUpdated($lead, 'created'));

    return response()->json([
        'status' => 'success'
    ]);
}

public function store_wordpress(Request $request)
{
    $data = $request->all();
    \Log::info($data);
$fieldData = [];
    if($data['responsible_person']==2911){
                    $data['responsible_person_id']=59;
                }elseif($data['responsible_person']==2909){
                    $data['responsible_person_id']=25;
                }else{
                     $data['responsible_person_id']=25;
                }
foreach ($data as $key => $value) {

    if (in_array($key, ['responsible_person_id','responsible_person','lead_name','source'])) {
        continue;
    }

    if ($value !== null && $value !== '') {
        $fieldData[] = [
            'name' => $key,
            'values' => [(string) $value]
        ];
    }
}
$fieldMappings = [
    'field_data' => $fieldData
];
    $stage = Stage::where('stage_type', 'lead')
        ->orderBy('order')
        ->first();
\Log::info($data['responsible_person_id']);
    $lead = Lead::create([
        'integration_id' => null,
        'meta_lead_id' => null,
        'lead_name' => $data['Page_Name'] ?html_entity_decode($data['Page_Name'], ENT_QUOTES, 'UTF-8') :'Wordpress Lead',
        'first_name' => $data['No_Label_name'] ?? 'wordpress',
        'last_name' => $data['last_name'] ?? null,
        'email' => $data['No_Label_email'] ?? null,
        'work_phone' => $data['No_Label_phone'] ?? null,
        'stage_id' => $stage?->id,
        'lead_source' =>$data['source']?? 'Allproperties.ae',
        'ad_id' => null,
        'added_by' => 1,
        'responsible_person_id' =>(int) $data['responsible_person_id'] ?? 1,
        'field_mappings_data' => json_encode($data),
        'raw_meta_data' => json_encode($fieldMappings),
    ]);
 $lead->lead_branch_source=$lead->responsiblePerson?->admin_parent?->name;
          $lead->save();
    LeadHistoryHelper::log($lead->id, ['action' => 'created','name'=>$lead->lead_name,'lead_branch_source'=>$lead->responsiblePerson?->admin_parent?->name]);
    broadcast(new LeadUpdated($lead, 'created'));

    return response()->json([
        'status' => 'success'
    ]);
}
public function getFormFields($formId)
{
    $token = env('META_ACCESS_TOKEN');

    $url = "https://graph.facebook.com/v18.0/{$formId}";

    $response = Http::get($url, [
        'access_token' => $token,
        'fields' => 'questions'
    ]);

    $questions = $response->json()['questions'] ?? [];

    $fields = array_map(function ($q) {
        return $q['key'];
    }, $questions);

    return response()->json([
        'fields' => $fields
    ]);
}
}