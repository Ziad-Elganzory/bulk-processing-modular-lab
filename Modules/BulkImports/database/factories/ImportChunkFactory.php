<?php

namespace Modules\BulkImports\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BulkImports\Models\ImportRun;

class ImportChunkFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\BulkImports\Models\ImportChunk::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $chunkId = 'chunk-'.fake()->unique()->numerify('######');
    
        return [
            'import_run_id' => ImportRun::factory(),
            'chunk_id' => $chunkId,
            'status' => 'pending',
            'object_key' => "imports/factory/chunks/{$chunkId}.csv",
            'row_start' => 2,
            'row_count' => 1000,
            'attempts' => 0,
            'accepted_rows' => 0,
            'rejected_rows' => 0,
        ];
    }
}

