<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompanyTrait;

class Account extends Model
{
    use BelongsToCompanyTrait;

    protected static function booted()
    {
        static::saving(function ($account) {

            if ($account->parent_id) {

                $parent = self::withoutGlobalScopes()
                    ->where('id', $account->parent_id)
                    ->first();

                if (! $parent) {
                    throw new \Exception('Parent account not found');
                }

                if ($parent->company_id !== $account->company_id) {
                    throw new \Exception('Parent account must belong to the same company');
                }
            }
        });
    }

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'type',
        'parent_id'
    ];
}
