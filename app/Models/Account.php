<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class Account extends Model
{
    use BelongsToCompanyTrait;

    /**
     * Account types
     */
    const TYPE_ASSET = 'asset';
    const TYPE_LIABILITY = 'liability';
    const TYPE_EQUITY = 'equity';
    const TYPE_REVENUE = 'revenue';
    const TYPE_EXPENSE = 'expense';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'type',
        'parent_id',
    ];

    protected static function booted()
    {
        static::saving(function ($account) {
            if ($account->parent_id) {
                $parent = self::withoutGlobalScopes()
                    ->where('id', $account->parent_id)
                    ->first();

                if (!$parent) {
                    throw new \Exception('Parent account not found');
                }

                if ($parent->company_id !== $account->company_id) {
                    throw new \Exception('Parent account must belong to the same company');
                }
            }
        });
    }

    /**
     * Relationship: Parent account
     */
    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    /**
     * Relationship: Child accounts
     */
    public function children()
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    /**
     * Relationship: Company
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope: Only parent accounts (no parent_id)
     */
    public function scopeParents($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope: By type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Check if account is parent
     */
    public function isParent(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Check if account has children
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Get full account code with parent prefix
     */
    public function getFullCodeAttribute(): string
    {
        if ($this->parent) {
            return $this->parent->code . '-' . $this->code;
        }
        return $this->code;
    }
}
