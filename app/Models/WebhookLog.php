<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'gateway',
        'event_type',
        'payload',
        'order_id',
        'ip_address',
        'status',
        'error',
        'subscription_id',
        'invoice_id',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
