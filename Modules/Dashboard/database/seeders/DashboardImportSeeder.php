<?php

namespace Modules\Dashboard\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Dashboard\Models\DashboardImport;

class DashboardImportSeeder extends Seeder
{
    public function run(): void
    {
        $importId = 'demo-dashboard-import-001';
        $now = now();

        $import = DashboardImport::query()->firstOrCreate(
            ['import_id' => $importId],
            [
                'file_name' => 'orders-demo.csv',
                'source_object_key' => "imports/{$importId}/source.csv",
                'source_checksum' => 'sha256:'.str_repeat('a', 64),
                'requested_by' => 'demo-user',
                'status' => 'processing',
                'file_size_bytes' => 48000,
                'total_rows' => 3000,
                'processed_rows' => 1000,
                'accepted_rows' => 980,
                'rejected_rows' => 20,
                'total_chunks' => 3,
                'processed_chunks' => 2,
                'rejected_report_object_key' => "imports/{$importId}/reports/rejected.csv",
                'started_at' => $now->copy()->subMinutes(5),
            ],
        );

        $import->chunks()->firstOrCreate(
            ['chunk_id' => 'chunk-000001'],
            [
                'status' => 'completed_with_errors',
                'object_key' => "imports/{$importId}/chunks/chunk-000001.csv",
                'row_start' => 2,
                'row_count' => 1000,
                'attempts' => 1,
                'accepted_rows' => 980,
                'rejected_rows' => 20,
                'accepted_object_key' => "imports/{$importId}/accepted/chunk-000001.csv",
                'rejected_object_key' => "imports/{$importId}/rejected/chunk-000001.csv",
                'finished_at' => $now->copy()->subMinutes(3),
            ],
        );

        $import->chunks()->firstOrCreate(
            ['chunk_id' => 'chunk-000002'],
            [
                'status' => 'failed',
                'object_key' => "imports/{$importId}/chunks/chunk-000002.csv",
                'row_start' => 1002,
                'row_count' => 1000,
                'attempts' => 4,
                'accepted_rows' => 0,
                'rejected_rows' => 0,
                'failure_code' => 'delivery_limit_exceeded',
                'failure_message' => 'The chunk request exceeded the RabbitMQ delivery limit.',
                'finished_at' => $now->copy()->subMinute(),
            ],
        );

        $import->chunks()->firstOrCreate(
            ['chunk_id' => 'chunk-000003'],
            [
                'status' => 'pending',
                'object_key' => "imports/{$importId}/chunks/chunk-000003.csv",
                'row_start' => 2002,
                'row_count' => 1000,
                'attempts' => 0,
            ],
        );
    }
}