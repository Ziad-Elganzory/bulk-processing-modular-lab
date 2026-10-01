<?php

namespace Modules\Dashboard\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Dashboard\Database\Factories\DashboardImportChunkFactory;

class DashboardImportChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'dashboard_import_id',
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
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'dashboard_import_id' => 'integer',
            'row_start' => 'integer',
            'row_count' => 'integer',
            'attempts' => 'integer',
            'accepted_rows' => 'integer',
            'rejected_rows' => 'integer',
            'finished_at' => 'immutable_datetime',
        ];
    }

    public function dashboardImport(): BelongsTo
    {
        return $this->belongsTo(DashboardImport::class);
    }

    protected static function newFactory(): DashboardImportChunkFactory
    {
        return DashboardImportChunkFactory::new();
    }
}
