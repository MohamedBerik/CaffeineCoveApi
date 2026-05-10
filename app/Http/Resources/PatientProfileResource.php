<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'patient' => [
                'id' => $this['patient']->id,
                'name' => $this['patient']->name,
                'email' => $this['patient']->email,
                'phone' => $this['patient']->phone,
                'patient_code' => $this['patient']->patient_code,
                'date_of_birth' => $this['patient']->date_of_birth,
                'gender' => $this['patient']->gender,
                'address' => $this['patient']->address,
                'notes' => $this['patient']->notes,
                'status' => $this['patient']->status,
                'created_at' => $this['patient']->created_at,
                'updated_at' => $this['patient']->updated_at,
            ],

            'procedures' => $this['procedures'],

            'appointments' => $this['appointments'],

            'dental_records' => $this['dental_records'],

            'treatment_plans' => $this['treatment_plans'],

            'invoices' => $this['invoices'],

            'financial_summary' => $this['financial_summary'],
        ];
    }
}
