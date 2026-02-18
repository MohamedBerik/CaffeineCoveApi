<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class Customer extends Model
{
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        "name",
        "email",
        "password",
        "status",
    ];

    public function ledgerEntries()
    {
        return $this->hasMany(CustomerLedgerEntry::class)
            ->where('company_id', $this->company_id);
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
