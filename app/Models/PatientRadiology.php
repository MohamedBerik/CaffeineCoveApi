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

    protected $appends = ['file_url', 'file_extension']; // إضافة الامتداد لسهولة فحص الفرونت إند

    // ============ Accessors ============

    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }
        return asset($this->file_path);
    }

    public function getFileSizeAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        // إزالة البادئة لأن الديسك المخصص يقف على المجلد مباشرة
        $cleanPath = str_replace('radiology/', '', $this->file_path);

        if (!Storage::disk('radiology_public')->exists($cleanPath)) {
            return null;
        }

        $size = Storage::disk('radiology_public')->size($cleanPath);

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
        return strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION));
    }

    // ============ Helpers ============

    public function isPdf(): bool
    {
        return $this->file_extension === 'pdf';
    }

    public function isImage(): bool
    {
        $imageExtensions = ['jpeg', 'jpg', 'png', 'gif'];
        return in_array($this->file_extension, $imageExtensions);
    }

    // ============ Boot ============

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($radiology) {
            if ($radiology->file_path) {
                $cleanPath = str_replace('radiology/', '', $radiology->file_path);

                if (Storage::disk('radiology_public')->exists($cleanPath)) {
                    Storage::disk('radiology_public')->delete($cleanPath);
                    Log::info('Radiology file deleted from custom disk', [
                        'id' => $radiology->id,
                        'clean_path' => $cleanPath,
                    ]);
                }
            }
        });
    }
}
