<?php

namespace Modules\Dashboard\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Dashboard\Models\DashboardImport;
use Modules\Dashboard\Models\DashboardImportChunk;

/**
 * @extends Factory<DashboardImportChunk>
 */
class DashboardImportChunkFactory extends Factory
{
    protected $model = DashboardImportChunk::class;

    public function definition(): array
    {
        return [
            'dashboard_import_id' => DashboardImport::factory(),
            'chunk_id' => 'chunk-000001',
            'status' => 'pending',
            'object_key' => 'imports/demo-import/chunks/chunk-000001.csv',
            'row_start' => 2,
            'row_count' => 1000,
            'attempts' => 0,
            'accepted_rows' => 0,
            'rejected_rows' => 0,
            'accepted_object_key' => null,
            'rejected_object_key' => null,
            'failure_code' => null,
            'failure_message' => null,
            'finished_at' => null,
        ];
    }
}
