<?php

namespace App\Http\Requests\Deal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stage_id' => 'sometimes|exists:stages,id',
            'status' => 'sometimes|in:draft,pending_approval,approved,completed,cancelled',
            'listing_id' => 'nullable|exists:listings,id',
            'deal_total_amount' => 'nullable|numeric',
            'responsible_person_id' => 'nullable|exists:users,id',
            'source' => 'nullable|string',
            'deal_name' => 'nullable|string',
            'deal_commission' => 'nullable|numeric',
            'agent_share' => 'nullable|numeric',
            'company_share' => 'nullable|numeric',
            'currency' => 'nullable|string|in:AED,USD,EUR,GBP,SAR,QAR,KWD,BHD,OMR,EGP',
            'lost_reason' => 'nullable|string',
            
            // Multi properties
            'properties' => 'sometimes|array',
            'properties.*.unit_no' => 'nullable|string',
            'properties.*.property_type_id' => 'nullable|exists:property_types,id',
            'properties.*.bedrooms' => 'nullable|string',
            'properties.*.unit_size' => 'nullable|numeric',
            'properties.*.area_id' => 'nullable|exists:areas,id',
            'properties.*.developer_id' => 'nullable|exists:developers,id',
            'properties.*.developer_name' => 'nullable|string|max:255',
            'properties.*.developer_phone' => 'nullable|string|max:255',
            'properties.*.budget_from' => 'nullable|numeric|min:0',
            'properties.*.budget_to' => 'nullable|numeric|min:0',
            'properties.*.purchase_price' => 'nullable|numeric|min:0',
            'properties.*.rental_price' => 'nullable|numeric|min:0',
            'properties.*.commission' => 'nullable|numeric|min:0|max:100',
            
            // ✅ EOI Documents
            'properties.*.eoi_documents' => 'nullable|array',
            'properties.*.eoi_documents.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            
            // ✅ Booking Documents
            'properties.*.booking_documents' => 'nullable|array',
            'properties.*.booking_documents.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            
            // ✅ Payment Proof
            'properties.*.payment_proof' => 'nullable|array',
            'properties.*.payment_proof.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            
            // ✅ SPA Document
            'properties.*.spa_document' => 'nullable|array',
            'properties.*.spa_document.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            
            // ✅ Single property mode (direct fields)
            'eoi_documents' => 'nullable|array',
            'eoi_documents.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'booking_documents' => 'nullable|array',
            'booking_documents.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'payment_proof' => 'nullable|array',
            'payment_proof.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'spa_document' => 'nullable|array',
            'spa_document.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
                 'eoi_date' => 'nullable|date',
            'booking_date' => 'nullable|date',
            'spa_date' => 'nullable|date',
            'security_deposit_date' => 'nullable|date',
            'mou_date' => 'nullable|date',
            'noc_date' => 'nullable|date',
            'won_date' => 'nullable|date',
        ];
    }
    
    public function messages(): array
    {
        return [
            // File validation messages
            'properties.*.eoi_documents.*.file' => 'Each EOI document must be a valid file',
            'properties.*.eoi_documents.*.mimes' => 'EOI document must be a JPG, JPEG, PNG, or PDF file',
            'properties.*.eoi_documents.*.max' => 'EOI document cannot exceed 10MB',
            'properties.*.booking_documents.*.file' => 'Each booking document must be a valid file',
            'properties.*.booking_documents.*.mimes' => 'Booking document must be a JPG, JPEG, PNG, or PDF file',
            'properties.*.booking_documents.*.max' => 'Booking document cannot exceed 10MB',
            'properties.*.payment_proof.*.file' => 'Each payment proof must be a valid file',
            'properties.*.payment_proof.*.mimes' => 'Payment proof must be a JPG, JPEG, PNG, or PDF file',
            'properties.*.payment_proof.*.max' => 'Payment proof cannot exceed 10MB',
            'properties.*.spa_document.*.file' => 'Each SPA document must be a valid file',
            'properties.*.spa_document.*.mimes' => 'SPA document must be a JPG, JPEG, PNG, or PDF file',
            'properties.*.spa_document.*.max' => 'SPA document cannot exceed 10MB',
            'eoi_documents.*.file' => 'EOI document must be a valid file',
            'eoi_documents.*.mimes' => 'EOI document must be a JPG, JPEG, PNG, or PDF file',
            'eoi_documents.*.max' => 'EOI document cannot exceed 10MB',
            'booking_documents.*.file' => 'Booking document must be a valid file',
            'booking_documents.*.mimes' => 'Booking document must be a JPG, JPEG, PNG, or PDF file',
            'booking_documents.*.max' => 'Booking document cannot exceed 10MB',
            'payment_proof.*.file' => 'Payment proof must be a valid file',
            'payment_proof.*.mimes' => 'Payment proof must be a JPG, JPEG, PNG, or PDF file',
            'payment_proof.*.max' => 'Payment proof cannot exceed 10MB',
            'spa_document.*.file' => 'SPA document must be a valid file',
            'spa_document.*.mimes' => 'SPA document must be a JPG, JPEG, PNG, or PDF file',
            'spa_document.*.max' => 'SPA document cannot exceed 10MB',
        ];
    }
}