<?php

namespace Modules\BulkImports\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\BulkImports\Database\Factories\ImportRunFactory;

class ImportRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_id',
        'source_object_key',
        'requested_by',
        'status',
        'total_rows',
        'processed_rows',
        'accepted_rows',
        'rejected_rows',
        'total_chunks',
        'processed_chunks',
        'failure_code',
        'failure_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'accepted_rows' => 'integer',
            'rejected_rows' => 'integer',
            'total_chunks' => 'integer',
            'processed_chunks' => 'integer',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(ImportChunk::class);
    }

    protected static function newFactory(): ImportRunFactory
    {
        return ImportRunFactory::new();
    }
}