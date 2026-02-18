<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class Account extends Model
{
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'type',
        'parent_id'
    ];

    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Account::class, 'parent_id');
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
