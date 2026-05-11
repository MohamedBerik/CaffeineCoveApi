<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TreatmentPlanResource extends JsonResource
{
    public function toArray($request): array
    {
        // الخدمة تُرجع بالفعل مصفوفة بالشكل المطلوب، لذا نكتفي بإرجاعها
        return $this->resource;
    }
}
