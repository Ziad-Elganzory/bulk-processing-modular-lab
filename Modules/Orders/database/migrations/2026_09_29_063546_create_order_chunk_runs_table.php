<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_chunk_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('import_id', 64);
            $table->string('chunk_id', 64);
            $table->string('status', 20)->default('processing');
            $table->unsignedInteger('row_start');
            $table->unsignedInteger('row_count');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedInteger('accepted_rows')->default(0);
            $table->unsignedInteger('rejected_rows')->default(0);
            $table->string('accepted_object_key')->nullable();
            $table->string('rejected_object_key')->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['import_id', 'chunk_id'],
                'order_chunk_runs_import_chunk_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_chunk_runs');
    }
};
