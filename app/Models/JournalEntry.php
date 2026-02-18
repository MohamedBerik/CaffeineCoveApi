<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class JournalEntry extends Model
{
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        'entry_date',
        'source_type',
        'source_id',
        'description',
        'created_by',
    ];

    public function lines()
    {
        return $this->hasMany(JournalLine::class)
            ->where('company_id', $this->company_id);
    }

    public function source()
    {
        return $this->morphTo();
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
