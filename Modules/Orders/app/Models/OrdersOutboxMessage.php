<?php

namespace Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;

class OrdersOutboxMessage extends Model
{
    protected $table = 'orders_outbox_messages';

    protected $fillable = [
        'message_id',
        'message_type',
        'correlation_id',
        'exchange_name',
        'routing_key',
        'payload',
        'status',
        'attempts',
        'available_at',
        'published_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'available_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }
}
