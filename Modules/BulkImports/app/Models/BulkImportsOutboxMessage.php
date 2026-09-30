<?php

namespace Modules\BulkImports\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\BulkImports\Database\Factories\BulkImportsOutboxMessageFactory;

class BulkImportsOutboxMessage extends Model
{
    use HasFactory;

    protected $table = 'bulk_imports_outbox_messages';

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
