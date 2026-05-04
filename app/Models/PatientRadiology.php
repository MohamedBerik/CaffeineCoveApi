<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompanyTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PatientRadiology extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    // ✅ Performance fix
    // public static $hasCompanyColumn = true;

    // ✅ الثوابت
    const TYPE_XRAY = 'xray';
    const TYPE_PANORAMA = 'panorama';
    const TYPE_CBCT = 'cbct';
    const TYPE_CEPHALOMETRIC = 'cephalometric';
    const TYPE_REPORT = 'report';
    const TYPE_CONSENT = 'consent';
    const TYPE_OTHER = 'other';

    protected $fillable = [
        'company_id',
        'branch_id',

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
        'captured_at' => 'datetime',
    ];

    protected $appends = ['file_url'];

    // ============ Relationships ============

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

    // ============ Scopes ============

    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('file_type', $type);
    }

    public function scopeForTooth($query, string $toothNumber)
    {
        return $query->where('tooth_number', $toothNumber);
    }

    // ============ Accessors ============

    public function getFileUrlAttribute()
    {
        if (!$this->file_path) {
            return null;
        }

        if (!Storage::disk('public')->exists($this->file_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->file_path);
    }

    public function getFileSizeAttribute(): ?string
    {
        if (!$this->file_path || !Storage::disk('public')->exists($this->file_path)) {
            return null;
        }

        $size = Storage::disk('public')->size($this->file_path);

        if ($size < 1024) {
            return $size . ' B';
        } elseif ($size < 1048576) {
            return round($size / 1024, 2) . ' KB';
        } else {
            return round($size / 1048576, 2) . ' MB';
        }
    }

    public function getFileExtensionAttribute(): ?string
    {
        if (!$this->file_name) {
            return null;
        }
        return pathinfo($this->file_name, PATHINFO_EXTENSION);
    }

    // ============ Helpers ============

    public function isImage(): bool
    {
        $imageTypes = [self::TYPE_XRAY, self::TYPE_PANORAMA, self::TYPE_CBCT, self::TYPE_CEPHALOMETRIC];
        return in_array($this->file_type, $imageTypes);
    }

    public function isDocument(): bool
    {
        $docTypes = [self::TYPE_REPORT, self::TYPE_CONSENT, self::TYPE_OTHER];
        return in_array($this->file_type, $docTypes);
    }

    // ============ Boot ============

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($radiology) {
            if ($radiology->file_path && Storage::disk('public')->exists($radiology->file_path)) {
                Storage::disk('public')->delete($radiology->file_path);
                Log::info('Radiology file deleted', [
                    'id' => $radiology->id,
                    'file_path' => $radiology->file_path,
                ]);
            }
        });
    }
}
