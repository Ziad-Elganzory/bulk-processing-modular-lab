<?php

namespace Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;

class OrderChunkRun extends Model
{
    protected $table = 'order_chunk_runs';

    protected $fillable = [
        'import_id',
        'chunk_id',
        'status',
        'row_start',
        'row_count',
        'attempts',
        'accepted_rows',
        'rejected_rows',
        'accepted_object_key',
        'rejected_object_key',
        'failure_code',
        'failure_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'row_start' => 'integer',
            'row_count' => 'integer',
            'attempts' => 'integer',
            'accepted_rows' => 'integer',
            'rejected_rows' => 'integer',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }
}
