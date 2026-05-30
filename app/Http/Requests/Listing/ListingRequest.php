<?php

namespace App\Http\Requests\Listing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isDraft = $this->input('action') === 'draft';
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');
$listingId = $this->route('property');
// Add conditional rules based on action and method
        // Action-specific rules
        $propertyTypeId = $this->input('property_type_id');

        $isPlot = false;


        if ($propertyTypeId) {
            $plotTypes = [24,31,35,36];
            $isPlot = in_array($propertyTypeId, $plotTypes);
        }
        $rules = [
            // 'unit_number' => 'required|string|max:50',
            'unit_number' => [
            'required',
            'string',
            'max:50',
             Rule::unique('listings', 'unit_number')
                ->where(function ($query) {
                    return $query
                        ->where('listing_status', request()->listing_status)
                        ->where('area_id', request()->area_id);
                })
                ->ignore($listingId),
            ],
            'size_sqft' => 'nullable|numeric|min:1',
            'size_sqmt' => 'nullable|numeric|min:1',
            'number_of_bedrooms' => $isPlot?'nullable':'nullable|integer|min:0',
            'number_of_bathrooms' => $isPlot?'nullable':'nullable|integer|min:0',
            'price' => 'required|numeric|min:10000',
            'listing_status' => 'sometimes|string|max:50',
            'mortgage_status' => 'required|string|max:50',
            'mortgage_amount' => 'nullable|numeric|min:0',
            'mortgage_comment' => 'nullable|string|max:1000',
            'occupancy_status' => 'nullable|string|max:50',
            'occupancy_comment' => 'nullable|string|max:1000',
            'spa_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png,svg',
            'desk_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png,svg',
            'other_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png,svg',
            'comment' => 'nullable|string|max:2000',
            'property_type_id' => 'required|exists:property_types,id',
            'project_id'=>'required|exists:projects,id',
            'area_id' =>request()->has('project_id')?'nullable|exists:areas,id': 'required|exists:areas,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'agent_id' => 'required|exists:users,id',
            'owner_id' => 'required|exists:owners,id',
            'developer_id' => 'nullable|exists:developers,id',
            // TEMP: was after:today (rejects "today" and any past date). Relax until product confirms rules.
            'rent_expiry_date' => 'nullable|date',
            'rent_amount' => 'nullable|numeric|min:0',
            //   'rented_status' => 'nullable|in:Available,Rented',
            // 'rented_until' => 'nullable|date|after_or_equal:today',
            'payment_plan' => 'nullable|string',
            'original_price' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'noc_percentage' => 'nullable|integer|min:0|max:50',
            'handover_date' => 'nullable|date',
            'payment_breakdown' => 'nullable|array',
            'payment_breakdown.*.id' => 'nullable',
            'payment_breakdown.*.type' => 'nullable|string|in:percentage,amount',
            'payment_breakdown.*.value' => 'nullable|numeric',
            'payment_breakdown.*.date' => 'nullable|date',
            'assignment_expense_lines' => 'nullable|array',
            'assignment_expense_lines.*.id' => 'nullable',
            'assignment_expense_lines.*.label' => 'nullable|string|max:120',
            'assignment_expense_lines.*.calcType' => 'nullable|string|in:percentage,fixed',
            'assignment_expense_lines.*.base' => 'nullable|string|in:op,sp,premium',
            'assignment_expense_lines.*.value' => 'nullable|numeric',
            'assignment_expense_lines.*.vatEnabled' => 'nullable|boolean',
            'drive_link' => 'nullable|url|max:500',
            'is_hot_deal'=>'nullable|in:No,Yes',
            'project_floor_plan_ids' => 'nullable|array',
            'project_floor_plan_ids.*' => 'nullable|exists:floor_plan_images,id',
            
            'floor_plans_source' => 'nullable|array',
              'additional_features' => 'sometimes|array',
            'additional_features.maid' => 'sometimes|boolean',
            'additional_features.storage' => 'sometimes|boolean',
            'additional_features.study' => 'sometimes|boolean',
            'additional_features.store' => 'sometimes|boolean',
            'additional_features.laundry' => 'sometimes|boolean',
            'additional_features.driver' => 'sometimes|boolean',
        ];

        // File upload rules - always nullable for updates
        $rules['floor_plans'] = 'nullable|array';
        $rules['floor_plans.*'] = 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:10240';
        $rules['gallery'] = 'nullable|array';
        $rules['gallery.*'] = 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:10240';
        $rules['hero_image'] = 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:10240';
        $rules['additional_documents'] = 'nullable|array';
        $rules['additional_documents.*'] = 'nullable|file|mimes:pdf,jpg,jpeg,png,svg|max:10240';
        


            if ($isDraft) {
                $rules['completion_status'] = 'nullable|string|max:50';
            } else {
                $rules['completion_status'] = 'required|string|max:50';
            
                if ($this->input('action') === 'publish') {
                    if (!$isUpdate) {
                        if (!$isPlot) {
                            // Regular properties need 10+ gallery images
                            $rules['gallery'] = 'required|array|min:10|max:15';
                        } else {
                            // Plots only need 1+ gallery image
                            $rules['gallery'] = 'required|array|min:1|max:15';
                        }
                        $rules['floor_plans'] = 'nullable|array|min:1';
                    }
                }
            }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'unit_number.required' => 'Unit number is required',
            'property_type_id.required' => 'Property type is required',
            'area_id.required' => 'Area is required',
            'agent_id.required' => 'Agent is required',
            'owner_id.required' => 'Owner is required',
            'completion_status.required' => 'Completion status is required',
            'gallery.required' => 'At least 10 gallery images are required',
            'gallery.min' => 'At least 10 gallery images are required',
            'gallery.*.image' => 'Each gallery file must be an image',
            'gallery.*.mimes' => 'Gallery images must be jpeg, png, jpg, gif, or webp',
            'floor_plans.*.image' => 'Each floor plan must be an image',
            'floor_plans.*.mimes' => 'Floor plans must be jpeg, png, jpg, gif, webp, or svg',
            'hero_image.image' => 'Hero image must be an image',
            'hero_image.mimes' => 'Hero image must be jpeg, png, jpg, gif, webp, or svg',
        ];
    }

    public function attributes(): array
    {
        return [
            'floor_plans.*' => 'floor plan',
            'gallery.*' => 'gallery image',
            'floor_plan_names.*' => 'floor plan name',
        ];
    }

    /**
     * Configure the validator instance.
     * For updates, we need to check total gallery images count
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $isUpdate = $this->isMethod('put') || $this->isMethod('patch');
            $isPublish = $this->input('action') === 'publish';
            
            if ($isUpdate && $isPublish) {
                // Get the listing ID from route
                $listingId = $this->route('listing');
                
                if ($listingId) {
                    $listing = \App\Models\Listing::find($listingId);
                    if ($listing) {
                        $existingGalleryCount = $listing->galleryImages()->count();
                        $newGalleryCount = $this->hasFile('gallery') ? count($this->file('gallery')) : 0;
                        $totalGalleryCount = $existingGalleryCount + $newGalleryCount;
                        
                        if ($totalGalleryCount < 10) {
                            $validator->errors()->add(
                                'gallery', 
                                "Total gallery images must be at least 10. Currently you have {$totalGalleryCount} images."
                            );
                        }
                        
                        $hasHeroImage = $listing->hero_image_path || $this->hasFile('hero_image');
                        $hasGalleryImages = $totalGalleryCount > 0;
                        
                        if (!$hasHeroImage && !$hasGalleryImages) {
                            $validator->errors()->add(
                                'hero_image', 
                                "Hero image is required for publishing. You can upload a hero image or add gallery images (first image will be used as hero)."
                            );
                        }
                    }
                }
            }
        });
    }
     public function prepareForValidation()
    {
        if ($this->has('payment_plan') && is_array($this->payment_plan)) {
            $this->merge([
                'payment_plan' => json_encode($this->payment_plan)
            ]);
        }

        // FormData sends JSON-stringified arrays as strings; decode so array rules pass.
        foreach (['payment_breakdown', 'assignment_expense_lines'] as $jsonField) {
            if ($this->has($jsonField) && is_string($this->input($jsonField))) {
                $decoded = json_decode($this->input($jsonField), true);
                if (is_array($decoded)) {
                    $this->merge([$jsonField => $decoded]);
                } elseif ($this->input($jsonField) === '' || $this->input($jsonField) === '[]') {
                    $this->merge([$jsonField => []]);
                }
            }
        }

        // Frontend uses `selling_price` as the canonical alias for `price`. Keep `price` in sync so existing validation still passes.
        if ($this->has('selling_price') && !$this->filled('price')) {
            $this->merge(['price' => $this->input('selling_price')]);
        }
         $floorPlansSource = [
            'project_plans_count' => count($this->project_floor_plan_ids ?? []),
            'uploaded_plans_count' => $this->hasFile('floor_plans') ? count($this->file('floor_plans')) : 0,
            'total_plans' => 0,
            'selected_project_plans' => $this->project_floor_plan_ids ?? [],
        ];
        
        $floorPlansSource['total_plans'] = 
            $floorPlansSource['project_plans_count'] + 
            $floorPlansSource['uploaded_plans_count'];
        
        $this->merge([
            'floor_plans_source' => $floorPlansSource,
        ]);
    }
}