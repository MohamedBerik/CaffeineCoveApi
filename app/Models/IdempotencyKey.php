<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $table = 'idempotency_keys';

    protected $fillable = [
        'company_id',
        'key',
        'endpoint',
        'request_hash',
        'status_code',
        'response_body',
    ];

    protected $casts = [
        'response_body' => 'array',
    ];
}
