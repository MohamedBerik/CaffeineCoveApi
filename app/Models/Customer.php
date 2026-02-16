<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'company_id',
        "name",
        "email",
        "password",
        "status",
    ];

    public function ledgerEntries()
    {
        return $this->hasMany(CustomerLedgerEntry::class);
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
