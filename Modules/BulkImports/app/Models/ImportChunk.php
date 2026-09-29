<?php

namespace Modules\BulkImports\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\BulkImports\Database\Factories\ImportChunkFactory;

class ImportChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_run_id',
        'chunk_id',
        'status',
        'object_key',
        'row_start',
        'row_count',
        'attempts',
        'accepted_rows',
        'rejected_rows',
        'accepted_object_key',
        'rejected_object_key',
        'failure_code',
        'failure_message',
        'dispatched_at',
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
            'dispatched_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    public function importRun(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class);
    }

    protected static function newFactory(): ImportChunkFactory
    {
        return ImportChunkFactory::new();
    }
}