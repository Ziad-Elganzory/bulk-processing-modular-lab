<?php

namespace Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;

class OrdersInboxMessage extends Model
{
    protected $table = 'orders_inbox_messages';

    protected $fillable = [
        'message_id',
        'message_type',
        'correlation_id',
        'payload',
        'status',
        'received_at',
        'processed_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
