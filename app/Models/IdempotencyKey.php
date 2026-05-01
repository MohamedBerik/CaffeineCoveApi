<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class IdempotencyKey extends Model
{
    use BelongsToCompanyTrait;
    public static $hasBranchColumn = true; // ✅ تفعيل BranchScope

    // ✅ Performance fix
    // public static $hasCompanyColumn = true;

    protected $table = 'idempotency_keys';

    protected $fillable = [
        'company_id',
        'key',
        'endpoint',
        'request_hash',
        'status_code',
        'response_body',
    ];

    protected $casts = [
        'response_body' => 'array',
        'status_code' => 'integer',
    ];

    // ============ Relationships ============

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // ============ Scopes ============

    public function scopeByKey($query, string $key)
    {
        return $query->where('key', $key);
    }

    public function scopeByEndpoint($query, string $endpoint)
    {
        return $query->where('endpoint', $endpoint);
    }

    public function scopePending($query)
    {
        return $query->whereNull('status_code');
    }

    public function scopeCompleted($query)
    {
        return $query->whereNotNull('status_code');
    }

    // ============ Helpers ============

    public function isPending(): bool
    {
        return is_null($this->status_code);
    }

    public function isCompleted(): bool
    {
        return !is_null($this->status_code);
    }

    public function isSuccessful(): bool
    {
        return $this->status_code >= 200 && $this->status_code < 300;
    }

    public function markAsCompleted(int $statusCode, array $responseBody): void
    {
        $this->update([
            'status_code' => $statusCode,
            'response_body' => $responseBody,
        ]);
    }
}
