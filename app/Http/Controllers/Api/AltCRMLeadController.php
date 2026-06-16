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
     * Update more_information only
     * Endpoint: POST /api/altcrm/update-more-info
     */
    public function updateMoreInformation(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'lead_id' => 'required|integer|exists:leads,id',
                'more_information' => 'required|string', // استقبال JSON string
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

            // ✅ تحديث more_information مباشرة
            $lead->update([
                'more_information' => $request->more_information
            ]);

            Log::info('More information updated for lead', [
                'lead_id' => $lead->id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'More information updated successfully',
                'data' => [
                    'lead_id' => $lead->id,
                    'more_information' => $lead->more_information
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error updating more_information', [
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
}