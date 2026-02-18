<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class JournalLine extends Model
{
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        'journal_entry_id',
        'account_id',
        'debit',
        'credit'
    ];

    public function entry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id')
            ->where('company_id', $this->company_id);
    }

    public function account()
    {
        return $this->belongsTo(Account::class)
            ->where('company_id', $this->company_id);
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
