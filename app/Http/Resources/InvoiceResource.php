<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray($request): array
    {
        // بما أن الخدمة تُرجع مصفوفة كاملة، يمكننا ببساطة إرجاعها كمصفوفة
        return $this->resource;
    }
}
