<?php

namespace Modules\Dashboard\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Dashboard\Database\Factories\DashboardImportFactory;

class DashboardImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_id',
        'file_name',
        'source_object_key',
        'source_checksum',
        'requested_by',
        'status',
        'file_size_bytes',
        'total_rows',
        'processed_rows',
        'accepted_rows',
        'rejected_rows',
        'total_chunks',
        'processed_chunks',
        'rejected_report_object_key',
        'failure_code',
        'failure_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
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
        return $this->hasMany(DashboardImportChunk::class);
    }

    protected static function newFactory(): DashboardImportFactory
    {
        return DashboardImportFactory::new();
    }
}
