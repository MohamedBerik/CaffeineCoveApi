<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class Employee extends Model
{
    use HasFactory;
    use BelongsToCompanyTrait;

    public static $hasBranchScope = true;

    protected $fillable = [
        'company_id',
        'branch_id',
        'user_id',
        'name',
        'email',
        'phone',
        'password',
        'salary',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'salary' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // ============ Relationships ============

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    // ============ Scopes ============

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        });
    }

    // ============ Accessors ============

    public function getSalaryFormattedAttribute(): string
    {
        return number_format($this->salary, 2);
    }

    public function getTotalSalesAttribute(): float
    {
        return $this->sales()->sum('price');
    }

    public function getSalesCountAttribute(): int
    {
        return $this->sales()->count();
    }
}
