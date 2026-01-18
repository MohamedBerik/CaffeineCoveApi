<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            "image"=>$this->cate_image,
            'image_url' => $this->cate_image ? url('img/category/' . $this->cate_image) : null,
            "title"=>$this->title_en,
            "title_ar"=>$this->title_ar,
            "description"=>$this->description_en,
            "description_ar"=>$this->description_ar,
        ];
    }
}
