<?php

namespace App\Http\Resources\Listing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Project;
class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
    //     $isDuplicated = Project::where('title', $this->title)
    // ->where('area_id', $this->area_id)
    // ->where('developer_id', $this->developer_id)
    // ->where('id', '!=', $this->id)
    // ->exists();
$duplicatedProject = Project::whereRaw(
        'LOWER(TRIM(title)) = ?',
        [strtolower(trim($this->title))]
    )->where('area_id',$this->area_id)
    ->where('id', '!=', $this->id)
    ->first();


        return [
            'id' => $this->id,
            'title' => $this->title,
            'name' => $this->title.', '.$this->area->title,
            'area_id' => $this->area_id,
            // Developer data
            'developer' => $this->developer ? [
                'id' => $this->developer->id,
                'name' => $this->developer->name,
                'avatar' => $this->developer->avatar_path ? asset('storage/' . $this->developer->avatar_path) : null
            ] : null,
            
            // Area data - 
            'area' => $this->area ? [
                'id' => $this->area->id,
                'name' => $this->area->name,
                'area_parents_title' => $this->area->area_parents_title,
                'children_count' => $this->area->children_count,
                
              
            ] : null,
            
            'from_price' => $this->from_price,
            'to_price' => $this->to_price,
            'from_sqft' => $this->from_sqft,
            'to_sqft' => $this->to_sqft,
            'status' => $this->status,
            'status_label' => ucfirst($this->status),
            'about' => $this->about,
            
            // Features
            'features' => $this->features->map(function ($feature) {
                return [
                    'id' => $feature->id,
                    'name' => $feature->name,
                    'img' => $feature->img ? asset('storage/' . $feature->img) : null,
                    'category' => $feature->category
                ];
            }),
            
            // Images
            'images' => $this->images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'url' => asset('storage/' . $image->image_path),
                    'is_main' => $image->is_main,
                    'sort_order' => $image->sort_order
                ];
            }),
            
            'main_image' => $this->mainImage ? asset('storage/' . $this->mainImage->image_path) : null,
            'added_by' => $this->addedBy?->name,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'listing_count'=>$this->listings->count(),
            'duplicated_project' => $duplicatedProject ? [
                    'id' => $duplicatedProject->id,
                    'title' => $duplicatedProject->title
                ] : null,
            'floor_plan_images' => FloorPlanImageResource::collection($this->whenLoaded('floorPlanImages')),

        ];
    }
}