<?php

namespace App\Http\Resources\Listing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListingGridResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->project?->title,
            // collect([
            //     $this->project?->title,
            //     $this->area?->name,
            // ])->filter()->implode(', '),

            'is_active'=>(bool)$this->is_active,
            'is_archived'=>(bool)$this->is_archived,
            'is_hot_deal'=>$this->is_hot_deal =='Yes' && $this->hot_deal_approved_by && $this->hot_deal_approved_at ? $this->is_hot_deal :'No',
            'sold_by'=>$this->sold_by,
            'occupancy_status'=>$this->occupancy_status,
             'reference_number'=>$this->reference_number,
            'status' => $this->status,
            'unit_number' => $this->unit_number,
            'size_sqft' => $this->size_sqft,
            'size_sqmt' => $this->size_sqmt,
            'number_of_bedrooms' => $this->number_of_bedrooms,
            'number_of_bathrooms' => $this->number_of_bathrooms,
            'price' => $this->price,
            'furnished_status' => $this->furnished_status,
            'listing_status' => $this->listing_status,
            'completion_status' => $this->completion_status,
            
            // 'main_image' => $this->galleryImages->first()?->image_url,
            'main_image'=>$this->hero_image_path ? asset('storage/' . $this->hero_image_path) : null,
            'total_images' => $this->galleryImages->count()+1,
            
            'property_type' => $this->propertyType?->name,
            'area' => $this->area? $this->area->title: $this->old_area?->title,
          'agent' => $this->whenLoaded('agent', function () {
                return [
                    'id' => $this->agent->id,
                    'name' => $this->agent->name,
                    'email' => $this->agent->email,
                'avatar' => $this->avatar ?  $this->avatar : null,
                ];
            }),
            
            'created_at' => $this->created_at?->format('M d, Y'),
        ];
    }
}