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
        Schema::create('import_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_run_id')
                ->constrained('import_runs')
                ->cascadeOnDelete();

            $table->string('chunk_id', 64);
            $table->string('status', 24)->default('pending');
            $table->string('object_key');
            $table->unsignedBigInteger('row_start');
            $table->unsignedInteger('row_count');
            $table->unsignedSmallInteger('attempts')->default(0);

            $table->unsignedInteger('accepted_rows')->default(0);
            $table->unsignedInteger('rejected_rows')->default(0);
            $table->string('accepted_object_key')->nullable();
            $table->string('rejected_object_key')->nullable();

            $table->string('failure_code', 100)->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['import_run_id', 'chunk_id']);
            $table->index(['import_run_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_chunks');
    }
};
