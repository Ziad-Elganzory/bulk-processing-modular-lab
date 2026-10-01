<?php

namespace Modules\Dashboard\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Dashboard\Models\DashboardImport;

/**
 * @extends Factory<DashboardImport>
 */
class DashboardImportFactory extends Factory
{
    protected $model = DashboardImport::class;

    public function definition(): array
    {
        $importId = (string) Str::uuid();

        return [
            'import_id' => $importId,
            'file_name' => 'orders.csv',
            'source_object_key' => "imports/{$importId}/source.csv",
            'source_checksum' => 'sha256:'.str_repeat('a', 64),
            'requested_by' => 'demo-user',
            'status' => 'queued',
            'file_size_bytes' => 1024,
            'total_rows' => null,
            'processed_rows' => 0,
            'accepted_rows' => 0,
            'rejected_rows' => 0,
            'total_chunks' => null,
            'processed_chunks' => 0,
            'rejected_report_object_key' => null,
            'failure_code' => null,
            'failure_message' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }
}
