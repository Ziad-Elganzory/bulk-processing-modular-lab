<?php

namespace Modules\Dashboard\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Dashboard\Database\Factories\DashboardInboxMessageFactory;

class DashboardInboxMessage extends Model
{
    use HasFactory;

    protected $table = 'dashboard_inbox_messages';

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

    protected static function newFactory(): DashboardInboxMessageFactory
    {
        return DashboardInboxMessageFactory::new();
    }
}
