<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
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
        return $this->hasMany(JournalLine::class);
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
