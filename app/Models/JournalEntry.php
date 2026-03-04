<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class JournalEntry extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        'entry_date',
        'description',
        'source_type',
        'source_id',
        'created_by',
    ];

    public function lines()
    {
        return $this->hasMany(JournalLine::class, 'journal_entry_id');
        // ❌ ممنوع where('company_id', $this->company_id)
    }

    public function source()
    {
        return $this->morphTo();
    }
}
