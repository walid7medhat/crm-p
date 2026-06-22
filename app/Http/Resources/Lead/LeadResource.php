<?php

namespace App\Http\Resources\Lead;

use App\Http\Resources\Lead\Concerns\ResolvesLeadLastActivity;
use App\Models\Integration;
use App\Models\Lead;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    use ResolvesLeadLastActivity;
    public function toArray($request): array
    {
         $rawMetaData = is_string($this->raw_meta_data) 
            ? json_decode($this->raw_meta_data, true) 
            : $this->raw_meta_data;
$facebookFields = [];

if (!empty($rawMetaData['field_data']) && is_array($rawMetaData['field_data'])) {
    foreach ($rawMetaData['field_data'] as $field) {
        if (isset($field['name']) && isset($field['values'][0])) {
            $facebookFields[$field['name']] = $field['values'][0];
        }
    }
}
        $assignmentHistory = $this->histories()
            ->where('changes->action', 'assigned')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($assignmentHistory && $assignmentHistory->user) {
            $assignedBy = $assignmentHistory->user;
        } else {
            $assignedBy = $this->addedBy;
        }

        // "Last activity" — Bitrix24-style "who last touched this lead and when".
        // Prefers B24's LAST_ACTIVITY_TIME / LAST_ACTIVITY_BY mirror columns;
        // falls back to the most recent history row for non-B24 leads.
        [$lastActivityAt, $lastActivityUser] = $this->resolveLastActivity(includeHistoryFallback: true);
        $finalLastActivityUser = $lastActivityUser;
        $finalLastActivityAt = $lastActivityAt;

        if (empty($finalLastActivityUser) && ($assignmentHistory && $assignmentHistory->user)) {
            // استخدام الـ assigned_by كبديل
            $finalLastActivityUser = $assignmentHistory->user;
            $finalLastActivityAt = $assignmentHistory->created_at;
        } elseif (empty($finalLastActivityUser) && $assignedBy) {
            // استخدام الـ added_by كبديل أخير
            $finalLastActivityUser = $assignedBy;
            $finalLastActivityAt = $this->created_at;
        }
        return [
            'id' => $this->id,
            'added_by' => $this->added_by,
            'bitrix24_id '=>$this->bitrix24_id ,
            // Basic Information
            'lead_name' => $this->lead_name,
            'deal_name' => $this->deal_name,
            'lead_number' => $this->lead_number,
            'stage_id' => $this->stage_id,
            
            // Contact Information
            'salutation' => $this->salutation,
            'first_name' => $this->first_name,
            'second_name' => $this->second_name,
            'last_name' => $this->last_name,
            'date_of_birth' => $this->date_of_birth,
            
            // Contact Details
            'whatsapp_number' => $this->whatsapp_number,
            'work_phone' => $this->work_phone,
            'work_phone_2' => $this->work_phone_2,
            'email' => $this->email,
            'secondary_email' => $this->secondary_email,
            'website' => $this->website,
            'messenger' => $this->messenger,
            'facebook' => $this->facebook,
            
             'source_client_name' => $this->source_client_name,
            'source_client_phone' => $this->source_client_phone,
            'source_client_email' => $this->source_client_email,
            'source_relation' => $this->source_relation,
            // Company Information
            'company_name' => $this->company_name,
            'position' => $this->position,
            
            // Property Information
            'interested_in' => $this->interested_in,
            'bedrooms' => $this->bedrooms===0 || $this->bedrooms==='0'?'studio':$this->bedrooms,
            'purpose_buying' => $this->purpose_buying,
            'extra_client_requirements' => $this->extra_client_requirements ?? [],
            'nationality' => $this->nationality,
            'citizenship_program' => $this->citizenship_program,
            
            // Lead Source
            'lead_source' => $this->lead_source,
            'integration_id' => $this->integration_id,
            /** Listing scope: project linked on the lead (e.g. Meta / integration flow). */
            'project_id' => $this->project_id,
            /** Project for matching listings: integration project, then DB lookup, then lead.project_id. */
            'integration_project_id' => $this->resolveIntegrationProjectId(),
            'source_information' => $this->source_information,
                'office_branch' =>
            $this->responsiblePerson?->admin_parent?->name,
             'lead_branch_source' => $this->lead_branch_source ,
            'ad_id' => $this->ad_id,
            'available_to_everyone' => $this->available_to_everyone,
            
            // Status
            'status_lead' => $this->status_lead,
            'interaction_result' => $this->interaction_result,
            'available_date'=>$this->available_date,
            'branch'=>$this->branch,
            'status_unit' => $this->status_unit,
            'status_project' => $this->status_project,
            'lists' => $this->lists,
            'unqualified_reason' => $this->unqualified_reason,
            'why_lost_lead' => $this->why_lost_lead,
            
            // Additional
            'address' => $this->address,
            'comment' => $this->comment,
            'more_information' => $this->more_information,
            'additional_services' => $this->additional_services,
            
            'responsible_person_id' => $this->responsible_person_id,
            'last_stage_change_at' => $this->last_stage_change_at,
            
            // Relationships
            'stage' => new \App\Http\Resources\Stage\MainStageResource($this->whenLoaded('stage')),
            'added_by_user' => new \App\Http\Resources\User\UserResource($this->whenLoaded('addedBy')),
            'responsible_person' => new \App\Http\Resources\User\UserResource($this->responsiblePerson),
            'participants' => LeadParticipantResource::collection($this->whenLoaded('participants')),
            'observers' => LeadObserverResource::collection($this->whenLoaded('observers')),
            
            'budget' =>  (int) $this->budget,
            'budget_from' => (int)$this->budget_from,
            'budget_to' => (int) $this->budget_to,
            'property_status' => $this->property_status,
            'lead_type' => $this->lead_type,
            'currency' => $this->currency,
            
            'created_at' => $this->created_at->setTimezone(config('app.timezone')),
            'updated_at' => $this->updated_at,
            'duplicate_no'=>$this->duplicate_leads->count(),
            'duplicate_ids'=>$this->duplicate_leads->pluck('id')->toArray(),
            'is_reverted'=>!is_null($this->revert),
            'can_edit'=>auth()->check() && (auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('admin') || $this->responsible_person_id==auth()->user()->id),
            'can_edit_phone_email'=>auth()->check() && (auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('admin')),
            'can_delete'=>auth()->check() && (auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('admin') ),
             'raw_meta_data' => $rawMetaData, 
                        'facebook_questions_answers' =>$facebookFields,
            'parent'=>new \App\Http\Resources\User\UserResource($assignedBy),
            'assigned_at'=>$assignmentHistory?$assignmentHistory->created_at:$this->created_at,
            // Activity-tracker (Bitrix24-style). Frontend lead card now reads
            // these instead of `assigned_at` / `parent` for the "who changed it
            // last and when" tile. `formatActivityUser` returns a flat dict
            // (id is non-null only for real local users; B24-only users get
            // a stub with id=null so the avatar/name still render but the
            // click won't try to open a non-existent profile).
            'last_activity_at'   => $lastActivityAt,
            'last_activity_user' => $this->formatActivityUser($finalLastActivityUser), 

            // Bitrix24 ID of the user who did the last activity, in case the
            // local mapping (by email) didn't resolve.
            'bitrix24_last_activity_by_id' => $this->bitrix24_last_activity_by_id,
            'bitrix24_last_activity_at'    => $this->bitrix24_last_activity_at,
            'bitrix24_moved_at'            => $this->bitrix24_moved_at,
            'property_type'=>$this->propertyType?->name,
            'area'=>$this->area?->title,
            'property_type_id'=>$this->propertyType?->id,
            'area_id'=>$this->area?->id,
            'score' => $this->score,
            'priority' => $this->priority,
            'intent' => $this->intent,
            'next_action' => $this->next_action,
            'risk' => ($this->priority === 'hot' && $this->updated_at && $this->updated_at->lt(now()->subDays(2)))
                ? 'cooling down'
                : '',
            'original_name' => data_get($this->createdHistory, 'changes.name', $this->name),

            'original_branch' => data_get($this->createdHistory, 'changes.lead_branch_source'),
           'api_first_question' => $this->getFirstApiQuestion(),
            'has_service_duplicate' => $this->hasServiceDuplicate(),
              'whatsapp_qualification' => $this->when(
                !empty($this->whatsapp_qualification),
                $this->formatWhatsappQualification($this->whatsapp_qualification)
            ),

        ];
    }

    /**
     * Resolves the project id used to scope property listings (integration config or lead column).
     */
    protected function resolveIntegrationProjectId(): ?int
    {
        /** @var \App\Models\Lead $lead */
        $lead = $this->resource;

        if ($lead->relationLoaded('integration')) {
            $pid = $lead->integration?->project_id;
            if ($pid !== null && $pid !== '') {
                return (int) $pid;
            }
        }

        if ($lead->integration_id) {
            $pid = Integration::query()->whereKey($lead->integration_id)->value('project_id');
            if ($pid !== null && $pid !== '') {
                return (int) $pid;
            }
        }

        if ($lead->project_id !== null && $lead->project_id !== '') {
            return (int) $lead->project_id;
        }

        return null;
    }

    protected function getFirstApiQuestion()
{
    $rawMetaData = is_string($this->raw_meta_data) 
        ? json_decode($this->raw_meta_data, true) 
        : $this->raw_meta_data;
    
    $basicFields = ['email', 'phone', 'full_name', 'name', 'work_phone', 'work_phone_number', 'phone_number', 'full name', 'first_name', 'last_name','Date','Time','inbox_url', 'Page_Name', 'form_name', 'form_id', 'No_Label_name', 'No_Label_email', 'No_Label_phone'];
    
    $facebookFields = [];
    
    if (!empty($rawMetaData['field_data']) && is_array($rawMetaData['field_data'])) {
        foreach ($rawMetaData['field_data'] as $field) {
            if (isset($field['name']) && isset($field['values'][0])) {
                $fieldName = $field['name'];
                $fieldValue = $field['values'][0];
                
                if (!in_array($fieldName, $basicFields)) {
                    $facebookFields[$fieldName] = $fieldValue;
                }
            }
        }
    }
    
    if (!empty($facebookFields)) {
        $firstQuestionKey = array_key_first($facebookFields);
        return $firstQuestionKey ." : " . $facebookFields[$firstQuestionKey];
          
    }
    
    return null;
}
    protected function hasServiceDuplicate(): bool
{
    return Lead::query()
        ->where('id', '!=', $this->id)
        ->where('status_lead', 'blacklist')
        ->where(function ($q) {
            if ($this->work_phone) {
                $q->orWhere('work_phone', $this->work_phone);
            }
            if ($this->email) {
                $q->orWhere('email', $this->email);
            }
        })
        ->exists();
}
 /**
     * تنسيق WhatsApp Qualification ديناميكياً
     */
    protected function formatWhatsappQualification($qualification): array
    {
        if (empty($qualification)) {
            return [];
        }

        if (is_string($qualification)) {
            $qualification = json_decode($qualification, true);
        }

        if (!is_array($qualification)) {
            return [];
        }

        $qaList = [];
        
        // ✅ عرض كل البيانات الموجودة
        foreach ($qualification as $key => $value) {
            // تخطي القيم الفارغة
            if ($value === null || $value === '') {
                continue;
            }
            if (
                $key === 'raw_response' ||
                $key === 'updated_at' ||
                $key === 'received_at' ||
                $key === 'response_success' || // 👈 الجديد
                $key === 'success' // 👈 لو موجود كمان
            ) {
                continue;
            }
            // تخطي الـ raw_response (نعرضها في مكان منفصل)
            if ($key === 'raw_response') {
                continue;
            }
            
            // تخطي الـ updated_at و received_at (نعرضهم في النهاية)
            if ($key === 'updated_at' || $key === 'received_at') {
                continue;
            }
            
            // تنسيق المفتاح ليكون سؤال
            $question = $this->formatQuestion($key);
            
            $qaList[] = [
                'question' => $question,
                'answer' => $this->formatAnswer($value),
                'key' => $key,
            ];
        }
        
        // إضافة وقت الاستلام والتحديث في النهاية
        if (isset($qualification['received_at'])) {
            $qaList[] = [
                'question' => 'Received At',
                'answer' => $this->formatDate($qualification['received_at']),
                'key' => 'received_at',
            ];
        }
        
        if (isset($qualification['updated_at'])) {
            $qaList[] = [
                'question' => 'Updated At',
                'answer' => $this->formatDate($qualification['updated_at']),
                'key' => 'updated_at',
            ];
        }
        
        return $qaList;
    }

    /**
     * تنسيق المفتاح ليكون سؤال
     */
    protected function formatQuestion(string $key): string
    {
        // تعريف أسماء جميلة لبعض المفاتيح المعروفة
        $mapping = [
            'success' => 'Success',
            'source' => 'Source',
            'source_label' => 'Source Label',
            'lead_id' => 'Lead ID',
            'name' => 'Name',
            'phone' => 'Phone',
            'email' => 'Email',
            'external_id' => 'External ID',
            'campaign' => 'Campaign',
            'budget' => 'Budget',
            'status' => 'Status',
            'sent_at' => 'Sent At',
            'property_type' => 'Property Type',
            'temperature' => 'Temperature',
            'score' => 'Score',
            'timeline' => 'Timeline',
            'purpose' => 'Purpose',
            'best_call_time' => 'Best Call Time',
            'summary' => 'Summary',
            'notes' => 'Notes',
            'project' => 'Project',
            'project_id' => 'Project ID',
            'property_type_id' => 'Property Type ID',
            'payload_phone' => 'Payload Phone',
            'payload_name' => 'Payload Name',
            'response_status' => 'Response Status',
            'response_success' => 'Response Success',
            'response_source' => 'Response Source',
            'response_source_label' => 'Response Source Label',
            'response_lead_id' => 'Response Lead ID',
            'response_name' => 'Response Name',
            'response_phone' => 'Response Phone',
        ];
        
        // إذا كان المفتاح في الـ mapping
        if (isset($mapping[$key])) {
            return $mapping[$key];
        }
        
        // تنسيق المفتاح العام
        return ucwords(str_replace(['_', '-'], ' ', $key));
    }

    /**
     * تنسيق القيمة
     */
   protected function formatAnswer($value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        
        // إذا كانت القيمة boolean
        if (is_bool($value)) {
            return $value ? '✅ Yes' : '❌ No';
        }
        
        // إذا كانت القيمة array أو object
        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT);
        }
        
        // ✅ تنسيق الأرقام الكبيرة (باستثناء أرقام التليفون)
        if (is_numeric($value) && $value > 1000) {
            // التحقق من أن الرقم ليس تليفون (10-15 رقم)
            $stringValue = (string) $value;
            $length = strlen($stringValue);
            
            // إذا كان الرقم طوله بين 10 و 15 رقم، اعتبره تليفون
            if ($length >= 10 && $length <= 15) {
                return (string) $value;
            }
            
            return number_format($value, 0, '.', ',');
        }
        
        // تنسيق التواريخ
        if (is_string($value) && strtotime($value) !== false) {
            return $this->formatDate($value);
        }
        
        return (string) $value;
    }

    protected function formatDate($date): string
    {
        if (empty($date)) return '—';
        
        try {
            $timestamp = strtotime($date);
            if ($timestamp === false) return $date;
            return date('d M Y, h:i A', $timestamp);
        } catch (\Exception $e) {
            return $date;
        }
    }
    
}