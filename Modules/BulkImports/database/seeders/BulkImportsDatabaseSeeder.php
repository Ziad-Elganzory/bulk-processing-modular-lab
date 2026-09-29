<?php

namespace Modules\BulkImports\Database\Seeders;

use Illuminate\Database\Seeder;

class BulkImportsDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            ImportRunSeeder::class,
            ImportChunkSeeder::class,
        ]);
    }
}
