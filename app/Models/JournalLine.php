<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class JournalLine extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    // ✅ Performance fix

    protected $fillable = [
        'company_id',
        'journal_entry_id',
        'account_id',
        'debit',
        'credit',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    // ============ Relationships ============

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function entry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    // ============ Helpers ============

    public function getNetAmountAttribute(): float
    {
        return $this->debit - $this->credit;
    }

    public function isDebit(): bool
    {
        return $this->debit > 0;
    }

    public function isCredit(): bool
    {
        return $this->credit > 0;
    }

    public function getAmountAttribute(): float
    {
        return $this->debit > 0 ? $this->debit : $this->credit;
    }
}
