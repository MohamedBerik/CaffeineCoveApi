<?php
// app/Models/PatientRadiology.php

namespace App\Models;

use App\Models\Concerns\BelongsToCompanyTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PatientRadiology extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;


    protected $fillable = [
        'company_id',
        'customer_id',
        'dental_record_id',
        'title',
        'file_path',
        'file_name',
        'file_type',
        'tooth_number',
        'captured_at',
        'notes',
    ];

    protected $casts = [
        'captured_at' => 'date',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function dentalRecord()
    {
        return $this->belongsTo(DentalRecord::class, 'dental_record_id');
    }

    // Accessor for full image URL
    public function getFileUrlAttribute()
    {
        if (!$this->file_path) {
            return null;
        }
        return Storage::disk('public')->url($this->file_path);
    }

    // Boot method for deleting file when model is deleted
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($radiology) {
            if ($radiology->file_path && Storage::disk('public')->exists($radiology->file_path)) {
                Storage::disk('public')->delete($radiology->file_path);
            }
        });
    }
}
