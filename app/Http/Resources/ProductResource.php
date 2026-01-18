<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            "id" => $this->id,
            "image"=>$this->product_image,
            'image_url' => $this->product_image ? url('img/product/' . $this->product_image) : null,
            "title"=>$this->title_en,
            "title_ar"=>$this->title_ar,
            "description"=>$this->description_en,
            "description_ar"=>$this->description_ar,
            "price"=>$this->price,
            "quantity"=>$this->quantity,
            "category_id"=>$this->category_id,
        ];
    }
}
