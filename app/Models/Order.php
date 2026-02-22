<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

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

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
