<?php

namespace Modules\BulkImports\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\BulkImports\Models\ImportRun;

class ImportRunSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ImportRun::query()->updateOrCreate(
            ['import_id' => 'demo-import-001'],
            [
                'source_object_key' => 'imports/demo-import-001/source.csv',
                'requested_by' => 'demo-user',
                'status' => 'queued',
                'total_rows' => null,
                'processed_rows' => 0,
                'accepted_rows' => 0,
                'rejected_rows' => 0,
                'total_chunks' => null,
                'processed_chunks' => 0,
            ],
        );
    }
}
