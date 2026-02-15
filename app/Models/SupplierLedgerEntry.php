<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierLedgerEntry extends Model
{
    protected $fillable = [
        'company_id',
        'supplier_id',
        'purchase_order_id',
        'supplier_payment_id',
        'type',
        'debit',
        'credit',
        'entry_date',
        'description',
    ];
    protected $casts = [
        'entry_date' => 'date',
    ];
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
