<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            // Basic Info
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id'  => $this->branch_id,

            // Images
            'image' => $this->cate_image,
            'image_url' => $this->cate_image ? asset('img/category/' . $this->cate_image) : null,

            // Localized Fields
            'title' => $this->getTitleAttribute(),
            'title_en' => $this->title_en,
            'title_ar' => $this->title_ar,

            'description' => $this->getDescriptionAttribute(),
            'description_en' => $this->description_en,
            'description_ar' => $this->description_ar,

            // Metadata
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,

            // Relationships (when loaded)
            'products_count' => $this->whenLoaded('products', function () {
                return $this->products->count();
            }),
            'products' => ProductResource::collection($this->whenLoaded('products')),
        ];
    }

    public function with($request): array
    {
        return [
            'status' => 'success',
        ];
    }
}
