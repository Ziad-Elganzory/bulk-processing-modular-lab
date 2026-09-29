<?php

namespace Modules\BulkImports\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ImportRunFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\BulkImports\Models\ImportRun::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $importId = (string) Str::uuid();
    
        return [
            'import_id' => $importId,
            'source_object_key' => "imports/{$importId}/source.csv",
            'requested_by' => 'demo-user',
            'status' => 'queued',
            'total_rows' => null,
            'processed_rows' => 0,
            'accepted_rows' => 0,
            'rejected_rows' => 0,
            'total_chunks' => null,
            'processed_chunks' => 0,
        ];
    }
}

