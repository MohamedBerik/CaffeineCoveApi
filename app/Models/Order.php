<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Invoice;
use App\Models\Concerns\BelongsToCompanyTrait;

/**
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\OrderItem[] $items
 */
class Order extends Model
{
    use BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        'customer_id',
        'status',
        'total',
        'created_by',
        'title_en',
        'title_ar',
        'description_en',
        'description_ar',
    ];

    protected $attributes = [
        'title_en' => 'ERP Order',
        'title_ar' => 'طلب ERP',
        'description_en' => '',
        'description_ar' => '',
    ];

    // Relations
    public function items()
    {
        return $this->hasMany(OrderItem::class)
            ->where('company_id', $this->company_id);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')
            ->where('company_id', $this->company_id);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')
            ->where('company_id', $this->company_id);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class)
            ->where('company_id', $this->company_id);
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
