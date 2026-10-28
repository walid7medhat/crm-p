<?php

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;

class LeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Link typed without a scheme ("www.bayut.com/…") gets https:// so the url rule accepts it. */
    protected function prepareForValidation(): void
    {
        $link = trim((string) $this->input('website', ''));
        if ($link !== '' && ! preg_match('~^https?://~i', $link)) {
            $this->merge(['website' => 'https://' . ltrim($link, '/')]);
        } elseif ($this->has('website') && $link === '') {
            $this->merge(['website' => null]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'lead_name' => 'required|string|max:255',
            'lead_number' => 'string|unique:leads,lead_number',
            'stage_id' => 'required|exists:stages,id',
            
            // Contact Information
            'salutation' => 'nullable|string|max:10',
            'first_name' => 'required|string|max:255',
            'second_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            
            // Contact Details
            'whatsapp_number' => 'nullable|string|max:20',
            'whatsapp_username' => 'nullable|string|max:100',
            'work_phone' => [
                'required',
                'max:20',
                
            ],
            'work_phone_2' => [
                'nullable',
                'max:20',
               
            ],
            'secondary_email' => 'nullable|email',
            'email' => 'nullable|email',
            // "Link" on the create/edit lead form — saved on the lead, shown as a link chip.
            'website' => 'nullable|url|max:255',
            'messenger' => 'nullable|string|max:255',
            'facebook' => 'nullable|string|max:255',
            
            // Company Information
            'company_name' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            
            // Property Information
            'interested_in' => 'nullable|string|max:255',
            'bedrooms' => 'nullable|integer|min:0',
            'purpose_buying' => 'nullable|string|max:255',
            'nationality' => 'nullable|string|max:255',
            'citizenship_program' => 'nullable|string|max:255',
            
            // Lead Source
            'lead_source' => 'required|string|max:255',
            'source_information' => 'nullable|string|max:255',
            'lead_branch_source' => 'nullable|string|max:255',
            'ad_id' => 'nullable|string|max:255',
            'available_to_everyone' => 'boolean',
            
            // ========== الحقول الجديدة للعميل المحيل (Referral) ==========
            'source_client_name' => 'nullable|string|max:255|required_if:lead_source,referral',
            'source_client_phone' => 'nullable|string|max:20|required_if:lead_source,referral',
            'source_client_email' => 'nullable|email|max:255',
            'source_relation' => 'nullable|string|max:255',
            // ============================================================
            
            // Status
            'status_lead' => 'nullable|string|max:255',
            'status_unit' => 'nullable|string|max:255',
            'status_project' => 'nullable|string|max:255',
            'lists' => 'nullable|string|max:255',
            'unqualified_reason' => 'nullable|string|max:255',
            'why_lost_lead' => 'nullable|string|max:255',
            
            // Additional
            'address' => 'nullable|string',
            'comment' => 'nullable|string',
            'additional_services' => 'nullable|string',
            
            // Sales & Management
            'responsible_person_id' => 'nullable|exists:users,id',
            
            // Relationships
            'participants' => 'nullable|array',
            'participants.*.name' => 'required|string|max:255',
            'participants.*.phone' => 'nullable|string|max:20',
            'participants.*.email' => 'nullable|email',
            'participants.*.type' => 'required|in:contact,company',
            
            'observers' => 'nullable|array',
            'observers.*' => 'exists:users,id',
            
            'budget' => 'nullable|numeric|min:0|max:999999999.99',
            'budget_from' => 'nullable|numeric|min:0|max:999999999.99',
            'budget_to' => 'nullable|numeric|min:0|max:999999999.99',
            'lead_type' => 'nullable|string|in:sale,rent',
            'property_status' => 'nullable|string|in:ready,off_plan,both',
            'currency' => 'nullable',
             
            'area_id' => 'nullable|exists:areas,id',
            'property_type_id' => 'nullable|exists:property_types,id',

            'available_date' => 'nullable|date',
            'branch' => 'nullable|string|max:100',
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['lead_number'] = 'string|unique:leads,lead_number,' . $this->route('lead')->id;

            // Editing: client name and primary phone are optional for everyone.
            // (LeadController::update saves '' for the NOT NULL first_name column; who
            // may change the phone at all is still decided there.)
            $rules['first_name'] = 'nullable|string|max:255';
            $rules['work_phone'] = ['nullable', 'max:20'];
        }

        // Creating: branch_admin and listing-team sales may leave the client's first name
        // and primary phone empty (editing already allows it for everyone, above).
        // LeadController::store saves '' for the NOT NULL first_name column. Everyone
        // else keeps both required when creating.
        if ($this->mayCreateWithoutNameAndPhone()) {
            $rules['first_name'] = 'nullable|string|max:255';
            $rules['work_phone'] = ['nullable', 'max:20'];
        }

        return $rules;
    }

    private function mayCreateWithoutNameAndPhone(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        return $user->hasRole('super_admin') || $user->hasRole('admin') || $user->hasRole('branch_admin')
            || ($user->hasRole('sales') && $user->is_listing_team);
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $from = $this->input('budget_from');
            $to = $this->input('budget_to');
            if ($from !== null && $from !== '' && $to !== null && $to !== '' && (float) $to < (float) $from) {
                $validator->errors()->add('budget_to', 'The budget to must be greater than or equal to budget from.');
            }

            // New leads can't be created straight into the Lead Pool (same rule as
            // LeadController::isLeadPoolStage). Editing is unaffected.
            if ($this->isMethod('POST') && $this->filled('stage_id')) {
                $stage = \App\Models\Stage::find($this->input('stage_id'));
                $name = strtolower(trim((string) $stage?->name));
                if ($stage && ($name === 'lead pool' || $name === 'leadpool' || (int) $stage->order === 9)) {
                    $validator->errors()->add('stage_id', 'Leads cannot be created in the Lead Pool stage.');
                }

                if ($stage) {
                    $this->requireStageFields($validator, (int) $stage->order);
                }
            }

            if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
                $this->guardStageFieldsOnEdit($validator);
            }
        });
    }

    private static function isBlank($value): bool
    {
        return $value === null || $value === '';
    }

    /**
     * Creating straight into a stage needs that stage's fields — same sets the create form
     * (CreateLeadModal stageRequiredFields) and the stage-change modal ask for, by stage
     * order. The form check alone could be skipped (its stage list failed to load → no
     * stage order → nothing required).
     */
    private function requireStageFields($validator, int $order): void
    {
        foreach ($this->stageRequiredFields($order, fn ($field) => $this->input($field)) as $field => $message) {
            if (self::isBlank($this->input($field))) {
                $validator->errors()->add($field, $message);
            }
        }
    }

    /**
     * Editing (EditLead sends the whole form, even for a one-section edit):
     *  - moving to another stage here → that stage's fields must all be filled;
     *  - same stage → a field the stage requires can't be cleared. Older leads that were
     *    saved without it stay editable instead of every save bouncing on hidden fields.
     * Values not sent keep what the lead already has.
     */
    private function guardStageFieldsOnEdit($validator): void
    {
        $lead = $this->route('lead');
        if (! $lead instanceof \App\Models\Lead) {
            return;
        }

        $value = fn ($field) => $this->has($field) ? $this->input($field) : $lead->getAttribute($field);
        $stage = $this->filled('stage_id') ? \App\Models\Stage::find($this->input('stage_id')) : $lead->stage;
        if (! $stage) {
            return;
        }

        $stageChanged = (int) $stage->id !== (int) $lead->stage_id;
        foreach ($this->stageRequiredFields((int) $stage->order, $value) as $field => $message) {
            if (! self::isBlank($value($field))) {
                continue;
            }
            if ($stageChanged) {
                $validator->errors()->add($field, $message);
            } elseif (! self::isBlank($lead->getAttribute($field))) {
                $validator->errors()->add($field, "{$message} — it is required for the {$stage->name} stage and can't be cleared.");
            }
        }
    }

    /**
     * Required fields per stage order (field => message) — same lists and messages as the
     * stage-change popup (StageChangeReasonModal, fed by leads.vue requiredFieldsMap and the
     * conversion list). $value reads a field's effective value: rent leads skip property
     * status / purpose, plots/land skip bedrooms.
     *
     * @return array<string, string>
     */
    private function stageRequiredFields(int $order, callable $value): array
    {
        $isRent = strtolower((string) $value('lead_type')) === 'rent';
        $typeName = strtolower((string) \App\Models\PropertyType::whereKey($value('property_type_id'))->value('name'));
        $isPlotsOrLand = str_contains($typeName, 'plot') || str_contains($typeName, 'land');

        $salutation = ['salutation' => 'Please select salutation'];
        $property = [
            'property_type_id' => 'Please select property type',
            'area_id' => 'Please select area',
            'budget_from' => 'Please enter minimum budget',
            'budget_to' => 'Please enter maximum budget',
            'lead_type' => 'Please select lead type (Sale/Rent)',
            'property_status' => $isRent ? null : 'Please select property status',
            'purpose_buying' => $isRent ? null : 'Please select purpose',
            'bedrooms' => $isPlotsOrLand ? null : 'Please select bedrooms',
        ];

        $required = match ($order) {
            3 => $salutation,                                                            // Contacted
            4 => $salutation + $property + ['status_lead' => 'Please select lead status (cold/warm/hot)'], // Qualified
            5 => $salutation + ['available_date' => 'Please select available date'],
            // Converted: the property details too. Its status ("converted") is filled by the
            // conversion popup and isn't a field on the create form, so it isn't required here;
            // deal_name belongs to the deal, not the lead.
            6 => $salutation + $property + ['lead_source' => 'Please select lead source'],
            7 => $salutation + ['branch' => 'Please select branch'],                    // Shared Leads
            8 => ['why_lost_lead' => 'Please select lost reason'],                       // Lost Lead
            9 => ['status_lead' => 'Please select lead pool status'],                    // Lead Pool
            10 => ['status_lead' => 'Please select unqualified status'],                 // Unqualified
            default => [],
        };

        return array_filter($required);
    }

    public function attributes(): array
    {
        return [
            'work_phone' => 'Primary Phone',
            'work_phone_2' => 'Secondary Phone',
            'email' => 'Primary Email',
            'secondary_email' => 'Secondary Email',
            'website' => 'link',
            'whatsapp_username' => 'WhatsApp Username',
            'source_client_name' => 'Source Client Name',
            'source_client_phone' => 'Source Client Phone',
            'source_client_email' => 'Source Client Email',
            'source_relation' => 'Relation with Client',
        ];
    }

    public function messages(): array
    {
        return [
            'responsible_person_id.required' => 'The responsible manager is required.',
            'responsible_person_id.exists' => 'The selected responsible manager does not exist.',
            'work_phone.regex' => 'Primary Phone number must contain digits only.',
            'work_phone_2.regex' => 'Secondary Phone number must contain digits only.',
            // رسائل التحقق للحقول الجديدة
            'source_client_name.required_if' => 'Source client name is required when source is Referral',
            'source_client_phone.required_if' => 'Source client phone is required when source is Referral',
            'source_client_email.email' => 'Please enter a valid email address for the source client',
        ];
    }
}