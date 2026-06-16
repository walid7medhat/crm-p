<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AltCRMLeadController extends Controller
{
    /**
     * Save AltCRM webhook response to whatsapp_qualification
     * Endpoint: POST /api/altcrm/whatsapp-qualification
     */
    public function updateWhatsappQualification(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'lead_id' => 'required|integer|exists:leads,id',
                'response' => 'required|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $lead = Lead::find($request->lead_id);

            if (!$lead) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Lead not found'
                ], 404);
            }

            // ✅ تخزين الـ response كاملة بدون تنسيق ثابت
            $qualification = [
                'raw_response' => $request->response, // تخزين كامل
                'received_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ];

            // ✅ استخراج البيانات المتاحة ديناميكياً
            $this->extractDynamicData($qualification, $request->response);

            // ✅ تخزين في whatsapp_qualification
            $lead->update([
                'whatsapp_qualification' => $qualification
            ]);

            Log::info('WhatsApp Qualification updated from webhook', [
                'lead_id' => $lead->id,
                'qualification' => $qualification
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'WhatsApp Qualification updated successfully',
                'data' => [
                    'lead_id' => $lead->id,
                    'whatsapp_qualification' => $lead->whatsapp_qualification
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error updating WhatsApp Qualification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * استخراج البيانات ديناميكياً من أي هيكل
     */
    protected function extractDynamicData(&$qualification, array $data, $prefix = '')
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // إذا كانت قيمة array، نتعمق فيها
                $this->extractDynamicData($qualification, $value, $prefix . $key . '_');
            } else {
                // تخزين القيمة مع المفتاح
                $fieldKey = $prefix . $key;
                // تنظيف المفتاح
                $fieldKey = str_replace(['altcrm_', 'webhook_'], '', $fieldKey);
                $qualification[$fieldKey] = $value;
            }
        }
    }

    /**
     * Get WhatsApp Qualification
     * Endpoint: GET /api/altcrm/whatsapp-qualification/{lead_id}
     */
    public function getWhatsappQualification($lead_id)
    {
        try {
            $lead = Lead::find($lead_id);

            if (!$lead) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Lead not found'
                ], 404);
            }

            $qualification = $lead->whatsapp_qualification;
            if (is_string($qualification)) {
                $qualification = json_decode($qualification, true);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'lead_id' => $lead->id,
                    'whatsapp_qualification' => $qualification ?? []
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}