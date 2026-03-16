<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KanbanSetting;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;

class KanbanSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:super_admin');
    }
    
    public function getSettings()
    {
        try {
            $cardFields = KanbanSetting::getCardFields();
            $revertHours = KanbanSetting::getRevertHours();
            
            return ApiResponse::success([
                'card_fields' => $cardFields,
                'revert_hours' => $revertHours,
                'all_fields' => $this->getAllAvailableFields()
            ], 'Settings retrieved successfully');
            
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }
    
    public function updateCardFields(Request $request)
    {
        try {
            $request->validate([
                'fields' => 'required|array',
                'fields.*.key' => 'required|string',
                'fields.*.label' => 'required|string',
                'fields.*.enabled' => 'required|boolean',
                'fields.*.order' => 'required|integer'
            ]);
            
            KanbanSetting::updateOrCreate(
                ['key' => 'card_fields'],
                ['value' => $request->fields]
            );
            
            return ApiResponse::success(null, 'Card fields updated successfully');
            
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }
    
    public function updateRevertHours(Request $request)
    {
        try {
            $request->validate([
                'hours' => 'required|integer|min:1|max:720'
            ]);
            
            KanbanSetting::updateOrCreate(
                ['key' => 'revert_hours'],
                ['value' => $request->hours]
            );
            
            return ApiResponse::success(null, 'Revert hours updated successfully');
            
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }
    
    private function getAllAvailableFields()
    {
        return [
            ['key' => 'lead_name', 'label' => 'Lead Name', 'group' => 'basic'],
            ['key' => 'created_by', 'label' => 'Created By', 'group' => 'basic'],
            ['key' => 'created_at', 'label' => 'Date', 'group' => 'basic'],
            ['key' => 'responsible_person', 'label' => 'Responsible', 'group' => 'people'],
            ['key' => 'assigned_by', 'label' => 'Assigned By', 'group' => 'people'],
            ['key' => 'lead_source', 'label' => 'Source', 'group' => 'source'],
            ['key' => 'lead_branch_source', 'label' => 'Branch Source', 'group' => 'source'],
            ['key' => 'first_name', 'label' => 'First Name', 'group' => 'contact'],
            ['key' => 'last_name', 'label' => 'Last Name', 'group' => 'contact'],
            ['key' => 'work_phone', 'label' => 'Phone', 'group' => 'contact'],
            ['key' => 'email', 'label' => 'Email', 'group' => 'contact'],
            ['key' => 'duplicate_count', 'label' => 'Duplicates', 'group' => 'system'],
            ['key' => 'bedrooms', 'label' => 'Bedrooms', 'group' => 'property'],
            ['key' => 'budget', 'label' => 'Budget', 'group' => 'property'],
            ['key' => 'whatsapp_number', 'label' => 'WhatsApp', 'group' => 'contact'],
            // ['key' => 'company_name', 'label' => 'Company', 'group' => 'business'],
            // ['key' => 'interested_in', 'label' => 'Interested In', 'group' => 'property'],
            // ['key' => 'nationality', 'label' => 'Nationality', 'group' => 'personal'],
        ];
    }
}