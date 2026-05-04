<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class JournalEntry extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    // ✅ Performance fix
    // public static $hasCompanyColumn = true;

    protected $fillable = [
        'company_id',
        'branch_id',

        'entry_date',
        'description',
        'source_type',
        'source_id',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    // ============ Relationships ============

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function lines()
    {
        return $this->hasMany(JournalLine::class, 'journal_entry_id');
    }

    public function source()
    {
        return $this->morphTo();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ============ Scopes ============

    public function scopeBetweenDates($query, $from, $to)
    {
        return $query->whereBetween('entry_date', [$from, $to]);
    }

    public function scopeBySource($query, string $type, int $id)
    {
        return $query->where('source_type', $type)->where('source_id', $id);
    }

    // ============ Helpers ============

    public function getTotalDebitAttribute(): float
    {
        return $this->lines->sum('debit');
    }

    public function getTotalCreditAttribute(): float
    {
        return $this->lines->sum('credit');
    }

    public function isBalanced(): bool
    {
        return $this->total_debit === $this->total_credit;
    }

    public function getDifferenceAttribute(): float
    {
        return abs($this->total_debit - $this->total_credit);
    }
}
