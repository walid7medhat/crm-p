<?php

namespace App\Http\Requests\Deal;

use Illuminate\Foundation\Http\FormRequest;

class ConvertLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            // Basic required fields
            'deal_type' => 'required|in:primary,secondary,rental',
            'stage_id' => 'required|exists:stages,id',
            'unit_no' => 'required|string',
            'source' => 'required|string',
            'deal_name' => 'required|string',
            'listing_id' => 'nullable|exists:listings,id',

            'property_type_id' => 'required|exists:property_types,id',
            'responsible_person_id' => 'required|exists:users,id',
            
            // Optional fields
            'bedrooms' => 'nullable|string',
            'unit_size' => 'nullable|numeric',
            'area_id' => 'nullable|exists:areas,id',
            'developer_name' => 'nullable|string|max:255',
            'developer_phone' => 'nullable|string|max:255',
            'deal_total_amount' => 'nullable|numeric',
            'deal_commission' => 'nullable|numeric',
            'agent_share' => 'nullable|numeric',
            'company_share' => 'nullable|numeric',
            'currency' => 'nullable|string|in:AED,USD,EUR,GBP,SAR,QAR,KWD,BHD,OMR,EGP',
            'property_link' => 'nullable|string|url',
            'property_reference' => 'nullable|string',
        ];

        // ✅ Check if listing_id exists to determine if we should skip seller/landlord
        $hasListingId = $this->filled('listing_id');
        $listing = null;
        
        if ($hasListingId) {
            $listing = \App\Models\Listing::find($this->listing_id);
        }

        // Primary Deal Rules
        if ($this->deal_type === 'primary') {
            $rules = array_merge($rules, [
                'buyer_first_name' => 'required|string',
                'buyer_last_name' => 'required|string',
                'buyer_dob' => 'required|date',
                'buyer_phone' => 'required|string',
                'buyer_email' => 'required|email',
                'buyer_nationality' => 'required|string',
                'buyer_residency_status' => 'required|string',
                'buyer_city' => 'required|string',
                'buyer_language' => 'required|string',
                'buyer_country' => 'nullable|string',
                'amount' => 'nullable|numeric',
                
                'secondary_first_name' => 'nullable|string',
                'secondary_last_name' => 'nullable|string',
                'secondary_phone' => 'nullable|string',
                'secondary_email' => 'nullable|email',
                'secondary_amount' => 'nullable|numeric',
                
                'buyer_documents' => 'sometimes|array',
                'buyer_documents.*.file' => 'sometimes|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:51200',
                'buyer_documents.*.document_type' => 'required_with:buyer_documents.*.file|in:national_id,passport,kyc,spa,payment_proof',
            ]);
        }

        // Secondary Deal Rules - ✅ جعل Seller اختياري إذا كان هناك listing_id
        if ($this->deal_type === 'secondary') {
            // ✅ تحديد ما إذا كان يجب جعل Seller مطلوباً أم لا
            $isSellerRequired = !($hasListingId && $listing && $listing->status === 'converted');
            
            $rules = array_merge($rules, [
                // Buyer required fields (دائماً مطلوبة)
                'buyer_first_name' => 'required|string',
                'buyer_last_name' => 'required|string',
                'buyer_dob' => 'required|date',
                'buyer_phone' => 'required|string',
                'buyer_email' => 'required|email',
                'buyer_nationality' => 'required|string',
                'buyer_residency_status' => 'required|string',
                'buyer_city' => 'required|string',
                'buyer_language' => 'required|string',
                'buyer_country' => 'nullable|string',
                
                // ✅ Seller fields - مطلوبة فقط إذا لم يكن هناك listing_id من نوع converted
                'seller_first_name' => $isSellerRequired ? 'required|string' : 'nullable|string',
                'seller_last_name' => $isSellerRequired ? 'required|string' : 'nullable|string',
                'seller_dob' => $isSellerRequired ? 'required|date' : 'nullable|date',
                'seller_phone' => $isSellerRequired ? 'required|string' : 'nullable|string',
                'seller_email' => $isSellerRequired ? 'required|email' : 'nullable|email',
                'seller_nationality' => $isSellerRequired ? 'required|string' : 'nullable|string',
                'seller_residency_status' => $isSellerRequired ? 'required|string' : 'nullable|string',
                'seller_city' => $isSellerRequired ? 'required|string' : 'nullable|string',
                'seller_language' => $isSellerRequired ? 'required|string' : 'nullable|string',
                'seller_country' => 'nullable|string',
                
                'amount' => 'nullable|numeric',
                
                'secondary_first_name' => 'nullable|string',
                'secondary_last_name' => 'nullable|string',
                'secondary_phone' => 'nullable|string',
                'secondary_email' => 'nullable|email',
                'secondary_amount' => 'nullable|numeric',
                
                'buyer_documents' => 'sometimes|array',
                'buyer_documents.*.file' => 'sometimes|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:51200',
                'buyer_documents.*.document_type' => 'required_with:buyer_documents.*.file|in:noc,national_id,passport,kyc,payment_proof,title_deed',
                
                'seller_documents' => 'sometimes|array',
                'seller_documents.*.file' => 'sometimes|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:51200',
                'seller_documents.*.document_type' => 'required_with:seller_documents.*.file|in:national_id,passport,title_deed',
            ]);
        }

        // Rental Deal Rules - ✅ جعل Landlord اختياري إذا كان هناك listing_id
        if ($this->deal_type === 'rental') {
            // ✅ تحديد ما إذا كان يجب جعل Landlord مطلوباً أم لا
            $isLandlordRequired = !($hasListingId && $listing && $listing->status === 'rented');
            
            $rules = array_merge($rules, [
                // Tenant required fields (دائماً مطلوبة)
                'tenant_first_name' => 'required|string',
                'tenant_last_name' => 'required|string',
                'tenant_dob' => 'nullable|date',
                'tenant_phone' => 'required|string',
                'tenant_email' => 'required|email',
                'tenant_nationality' => 'required|string',
                'tenant_residency_status' => 'required|string',
                'tenant_city' => 'required|string',
                'tenant_language' => 'required|string',
                'tenant_country' => 'nullable|string',
                
                // ✅ Landlord fields - مطلوبة فقط إذا لم يكن هناك listing_id من نوع rented
                'landlord_first_name' => $isLandlordRequired ? 'required|string' : 'nullable|string',
                'landlord_last_name' => $isLandlordRequired ? 'required|string' : 'nullable|string',
                'landlord_dob' => $isLandlordRequired ? 'required|date' : 'nullable|date',
                'landlord_phone' => $isLandlordRequired ? 'required|string' : 'nullable|string',
                'landlord_email' => $isLandlordRequired ? 'required|email' : 'nullable|email',
                'landlord_nationality' => $isLandlordRequired ? 'required|string' : 'nullable|string',
                'landlord_residency_status' => $isLandlordRequired ? 'required|string' : 'nullable|string',
                'landlord_city' => $isLandlordRequired ? 'required|string' : 'nullable|string',
                'landlord_language' => $isLandlordRequired ? 'required|string' : 'nullable|string',
                'landlord_country' => 'nullable|string',
                
                // Documents
                'tenant_documents' => 'sometimes|array',
                'tenant_documents.*.file' => 'sometimes|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:51200',
                'tenant_documents.*.document_type' => 'required_with:tenant_documents.*.file|in:national_id,passport,kyc,visa,payment_proof,ejari,tenancy_contract,move_in_form',
                
                'landlord_documents' => 'sometimes|array',
                'landlord_documents.*.file' => 'sometimes|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:51200',
                'landlord_documents.*.document_type' => 'required_with:landlord_documents.*.file|in:title_deed,passport,national_id,visa',
            ]);
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            // Basic fields
            'deal_type.required' => 'Deal type is required',
            'deal_type.in' => 'Invalid deal type selected',
            'stage_id.required' => 'Please select a stage for the deal',
            'stage_id.exists' => 'Selected stage is invalid',
            'unit_no.required' => 'Unit number is required',
            'source.required' => 'Source is required',
            'deal_name.required' => 'Deal name is required',
            'property_type_id.required' => 'Property type is required',
            'property_type_id.exists' => 'Selected property type is invalid',
            'responsible_person_id.required' => 'Responsible person is required',
            
            // Buyer fields
            'buyer_first_name.required' => 'Buyer first name is required',
            'buyer_last_name.required' => 'Buyer last name is required',
            'buyer_dob.required' => 'Buyer date of birth is required',
            'buyer_phone.required' => 'Buyer phone is required',
            'buyer_email.required' => 'Buyer email is required',
            'buyer_nationality.required' => 'Buyer nationality is required',
            'buyer_residency_status.required' => 'Buyer residency status is required',
            'buyer_city.required' => 'Buyer city is required',
            'buyer_language.required' => 'Buyer language is required',
            
            // Seller fields
            'seller_first_name.required' => 'Seller first name is required',
            'seller_last_name.required' => 'Seller last name is required',
            'seller_dob.required' => 'Seller date of birth is required',
            'seller_phone.required' => 'Seller phone is required',
            'seller_email.required' => 'Seller email is required',
            'seller_nationality.required' => 'Seller nationality is required',
            'seller_residency_status.required' => 'Seller residency status is required',
            'seller_city.required' => 'Seller city is required',
            'seller_language.required' => 'Seller language is required',
            
            // Tenant fields
            'tenant_first_name.required' => 'Tenant first name is required',
            'tenant_last_name.required' => 'Tenant last name is required',
            'tenant_phone.required' => 'Tenant phone is required',
            'tenant_email.required' => 'Tenant email is required',
            'tenant_nationality.required' => 'Tenant nationality is required',
            'tenant_residency_status.required' => 'Tenant residency status is required',
            'tenant_city.required' => 'Tenant city is required',
            'tenant_language.required' => 'Tenant language is required',
            
            // Landlord fields
            'landlord_first_name.required' => 'Landlord first name is required',
            'landlord_last_name.required' => 'Landlord last name is required',
            'landlord_dob.required' => 'Landlord date of birth is required',
            'landlord_phone.required' => 'Landlord phone is required',
            'landlord_email.required' => 'Landlord email is required',
            'landlord_nationality.required' => 'Landlord nationality is required',
            'landlord_residency_status.required' => 'Landlord residency status is required',
            'landlord_city.required' => 'Landlord city is required',
            'landlord_language.required' => 'Landlord language is required',
        ];
    }
}