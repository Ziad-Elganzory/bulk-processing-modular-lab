<?php

namespace Modules\BulkImports\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\BulkImports\Database\Factories\BulkImportsInboxMessageFactory;

class BulkImportsInboxMessage extends Model
{
    use HasFactory;

    protected $table = 'bulk_imports_inbox_messages';

    /**
     * The attributes that are mass assignable.
     */
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
