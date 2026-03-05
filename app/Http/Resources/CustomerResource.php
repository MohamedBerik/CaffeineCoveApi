<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
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
            "id"            => $this->id,
            "company_id"    => $this->company_id,
            "patient_code"  => $this->patient_code,
            "name"          => $this->name,
            "email"         => $this->email,
            "phone"         => $this->phone,
            "date_of_birth" => $this->date_of_birth,
            "gender"        => $this->gender,
            "address"       => $this->address,
            "notes"         => $this->notes,
            "status"        => $this->status,
            "created_at"    => $this->created_at,
            "updated_at"    => $this->updated_at,
        ];
    }
}
