<?php

namespace Modules\BulkImports\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\BulkImports\Models\ImportRun;

class ImportChunkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $run = ImportRun::query()
            ->where('import_id', 'demo-import-001')
            ->firstOrFail();
    
        $run->chunks()->updateOrCreate(
            ['chunk_id' => 'chunk-001'],
            [
                'status' => 'pending',
                'object_key' => 'imports/demo-import-001/chunks/chunk-001.csv',
                'row_start' => 2,
                'row_count' => 500,
                'attempts' => 0,
            ],
        );
    
        $run->chunks()->updateOrCreate(
            ['chunk_id' => 'chunk-002'],
            [
                'status' => 'pending',
                'object_key' => 'imports/demo-import-001/chunks/chunk-002.csv',
                'row_start' => 502,
                'row_count' => 500,
                'attempts' => 0,
            ],
        );
    }
}
