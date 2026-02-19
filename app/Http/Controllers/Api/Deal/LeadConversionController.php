<?php
// app/Http/Controllers/Api/LeadConversionController.php

namespace App\Http\Controllers\Api\Deal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Deal\ConvertLeadRequest;
use App\Http\Resources\Deal\DealResource;
use App\Models\Lead;
use App\Models\Deal;
use App\Models\Stage;
use App\Models\DealParty;
use Illuminate\Support\Facades\DB;

class LeadConversionController extends Controller
{
    /**
     * تحويل Lead إلى Deal
     * 
     * POST /api/leads/{lead}/convert-to-deal
     */
    public function convert(ConvertLeadRequest $request, Lead $lead)
    {
        // ===== 1. التحقق من صلاحية المستخدم للـ Lead =====
        $user = auth()->user();
        
        // التحقق أن المستخدم يقدر يشوف الـ lead ده
        if (!$user->hasAnyRole(['super_admin'])) {
            $canAccess = false;
            
            if ($user->hasAnyRole(['manager', 'team_lead', 'admin'])) {
                $subordinatesIds = $user->getAllSubordinatesIds();
                $canAccess = in_array($lead->responsible_person_id, array_merge($subordinatesIds, [$user->id])) 
                        || in_array($lead->added_by, $subordinatesIds);
            } else {
                $canAccess = $lead->responsible_person_id == $user->id 
                        || $lead->added_by == $user->id;
            }
            
            if (!$canAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to convert this lead'
                ], 403);
            }
        }

        // ===== 2. التحقق من أن الـ Lead مش محول قبل كده =====
        if ($lead->converted_to_deal_id) {
            return response()->json([
                'success' => false,
                'message' => 'Lead already converted to deal',
                'deal_id' => $lead->converted_to_deal_id
            ], 400);
        }

        // ===== 3. التحقق من وجود مرحلة مناسبة للـ Deal =====
        $stage = Stage::where('stage_type', 'deal')
            ->where('deal_type', $request->deal_type)
            ->orderBy('order')
            ->first();

        if (!$stage) {
            return response()->json([
                'success' => false,
                'message' => 'No suitable stage found for this deal type'
            ], 400);
        }

        // ===== 4. بدأ المعاملة (Transaction) =====
        try {
            DB::beginTransaction();

            // ===== 5. إنشاء رقم الصفقة =====
            $dealNumber = $this->generateDealNumber($lead);

            // ===== 6. إنشاء الصفقة =====
            $deal = Deal::create([
                // الربط بالـ Lead
                'lead_id' => $lead->id,
                'deal_number' => $dealNumber,
                
                // نوع ومرحلة الصفقة
                'deal_type' => $request->deal_type,
                'stage_id' => $stage->id,
                
                // المعلومات الأساسية
                'source' => $request->source,
                'deal_name' => $request->deal_name,
                'status' => 'draft',
                
                // التفاصيل المالية
                'deal_total_amount' => $request->deal_total_amount,
                'currency' => $request->currency ?? 'AED',
                'deal_commission' => $request->deal_commission,
                'agent_share' => $request->agent_share,
                'company_share' => $request->company_share,
                
                // تفاصيل العقار (في نفس الجدول)
                'unit_no' => $request->unit_no,
                'property_type_id' => $request->property_type_id,
                'bedrooms' => $request->bedrooms,
                'unit_size' => $request->unit_size,
                'property_link' => $request->property_link,
                'property_reference' => $request->property_reference,
                
                // العلاقات
                'project_id' => $request->project_id,
                'area_id' => $request->area_id,
                'developer_id' => $request->developer_id,
                
                // المسؤول
                'responsible_person_id' => $request->responsible_person_id ?? $lead->responsible_person_id,
                
                // Tracking
                'created_by' => auth()->id(),
                
                // Metadata
                'metadata' => [
                    'converted_from_lead' => $lead->id,
                    'converted_at' => now()->toDateTimeString(),
                    'lead_data' => [
                        'id' => $lead->id,
                        'name' => $lead->lead_name,
                        'email' => $lead->email,
                        'phone' => $lead->phone
                    ]
                ]
            ]);

            // ===== 7. إنشاء أطراف الصفقة حسب النوع =====
            $this->createDealParties($deal, $request);

            // ===== 8. تحديث الـ Lead =====
            $lead->update([
                'converted_to_deal_id' => $deal->id,
                'converted_at' => now()
            ]);

            DB::commit();

            // ===== 9. Dispatch Event (اختياري) =====
            // event(new \App\Events\LeadConvertedToDeal($lead, $deal, auth()->user()));

            // ===== 10. إرجاع النتيجة =====
            return response()->json([
                'success' => true,
                'message' => 'Lead converted to deal successfully',
                'data' => new DealResource($deal->load([
                    'stage',
                    'propertyType',
                    'project',
                    'area',
                    'developer',
                    'parties',
                    'responsiblePerson'
                ]))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            dd($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to convert lead to deal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * إنشاء أطراف الصفقة حسب النوع
     */
    private function createDealParties($deal, $request)
    {
        switch ($request->deal_type) {
            
            case 'rental':
                // Client (العميل)
                if ($request->client_name) {
                    $nameParts = explode(' ', $request->client_name, 2);
                    DealParty::create([
                        'deal_id' => $deal->id,
                        'party_type' => 'client',
                        'party_role' => 'primary',
                        'first_name' => $nameParts[0] ?? '',
                        'last_name' => $nameParts[1] ?? '',
                        'phone' => $request->client_phone,
                        'email' => $request->client_email,
                    ]);
                }

                // Tenant (المستأجر)
                DealParty::create([
                    'deal_id' => $deal->id,
                    'party_type' => 'tenant',
                    'party_role' => 'primary',
                    'first_name' => $request->tenant_first_name,
                    'last_name' => $request->tenant_last_name,
                    'phone' => $request->tenant_phone,
                    'email' => $request->tenant_email,
                    'nationality' => $request->tenant_nationality,
                ]);

                // Landlord (المالك)
                DealParty::create([
                    'deal_id' => $deal->id,
                    'party_type' => 'landlord',
                    'party_role' => 'primary',
                    'first_name' => $request->landlord_first_name,
                    'last_name' => $request->landlord_last_name,
                    'phone' => $request->landlord_phone,
                    'email' => $request->landlord_email,
                    'nationality' => $request->landlord_nationality,
                    'residency_status' => $request->landlord_residency_status,
                    'country' => $request->landlord_country,
                    'city' => $request->landlord_city,
                ]);
                break;

            case 'primary':
                // Buyer (المشتري)
                DealParty::create([
                    'deal_id' => $deal->id,
                    'party_type' => 'buyer',
                    'party_role' => 'primary',
                    'first_name' => $request->buyer_first_name,
                    'last_name' => $request->buyer_last_name,
                    'date_of_birth' => $request->buyer_dob,
                    'phone' => $request->buyer_phone,
                    'email' => $request->buyer_email,
                    'nationality' => $request->buyer_nationality,
                    'residency_status' => $request->buyer_residency_status,
                    'city' => $request->buyer_city,
                    'country' => $request->buyer_country,
                    'language' => $request->buyer_language,
                    'amount' => $request->amount,
                ]);

                // Secondary Buyer (لو موجود)
                if ($request->has('secondary_buyer_first_name')) {
                    DealParty::create([
                        'deal_id' => $deal->id,
                        'party_type' => 'buyer',
                        'party_role' => 'secondary',
                        'first_name' => $request->secondary_buyer_first_name,
                        'last_name' => $request->secondary_buyer_last_name,
                        'phone' => $request->secondary_buyer_phone,
                        'email' => $request->secondary_buyer_email,
                        'amount' => $request->secondary_buyer_amount,
                    ]);
                }
                break;

            case 'secondary':
                // Buyer (المشتري)
                DealParty::create([
                    'deal_id' => $deal->id,
                    'party_type' => 'buyer',
                    'party_role' => 'primary',
                    'first_name' => $request->buyer_first_name,
                    'last_name' => $request->buyer_last_name,
                    'date_of_birth' => $request->buyer_dob,
                    'phone' => $request->buyer_phone,
                    'email' => $request->buyer_email,
                    'nationality' => $request->buyer_nationality,
                    'residency_status' => $request->buyer_residency_status,
                    'city' => $request->buyer_city,
                    'country' => $request->buyer_country,
                    'language' => $request->buyer_language,
                    'amount' => $request->amount,
                ]);

                // Seller (البائع)
                DealParty::create([
                    'deal_id' => $deal->id,
                    'party_type' => 'seller',
                    'party_role' => 'primary',
                    'first_name' => $request->seller_first_name,
                    'last_name' => $request->seller_last_name,
                    'date_of_birth' => $request->seller_dob,
                    'phone' => $request->seller_phone,
                    'email' => $request->seller_email,
                    'city' => $request->seller_city,
                    'language' => $request->seller_language,
                ]);

                // Secondary Buyer (لو موجود)
                if ($request->has('secondary_buyer_first_name')) {
                    DealParty::create([
                        'deal_id' => $deal->id,
                        'party_type' => 'buyer',
                        'party_role' => 'secondary',
                        'first_name' => $request->secondary_buyer_first_name,
                        'last_name' => $request->secondary_buyer_last_name,
                        'phone' => $request->secondary_buyer_phone,
                        'email' => $request->secondary_buyer_email,
                        'amount' => $request->secondary_buyer_amount,
                    ]);
                }
                break;
        }
    }

    /**
     * توليد رقم الصفقة
     */
    private function generateDealNumber($lead = null)
    {
        // لو الـ lead عنده رقم، استخدمه
        if ($lead && $lead->lead_number) {
            return $lead->lead_number;
        }

        // وإلا، أنشئ رقم جديد
        $prefix = 'DL';
        $year = date('Y');
        $month = date('m');
        
        $lastDeal = Deal::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastDeal && preg_match('/-(\d+)$/', $lastDeal->deal_number, $matches)) {
            $sequence = intval($matches[1]) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('%s-%s%s-%04d', $prefix, $year, $month, $sequence);
    }

    /**
     * [Optionally] التحقق من إمكانية تحويل الـ Lead
     * GET /api/leads/{lead}/can-convert
     */
    public function canConvert(Lead $lead)
    {
        // التحقق من الصلاحية
        $user = auth()->user();
        
        if (!$user->hasAnyRole(['super_admin'])) {
            $canAccess = false;
            
            if ($user->hasAnyRole(['manager', 'team_lead', 'admin'])) {
                $subordinatesIds = $user->getAllSubordinatesIds();
                $canAccess = in_array($lead->responsible_person_id, array_merge($subordinatesIds, [$user->id])) 
                        || in_array($lead->added_by, $subordinatesIds);
            } else {
                $canAccess = $lead->responsible_person_id == $user->id 
                        || $lead->added_by == $user->id;
            }
            
            if (!$canAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'can_convert' => is_null($lead->converted_to_deal_id),
                'is_converted' => !is_null($lead->converted_to_deal_id),
                'converted_to_deal_id' => $lead->converted_to_deal_id,
                'converted_at' => $lead->converted_at,
                'available_deal_types' => ['primary', 'secondary', 'rental']
            ]
        ]);
    }
}