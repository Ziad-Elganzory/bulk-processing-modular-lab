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
        Schema::create('dashboard_import_chunks', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('dashboard_import_id')
                ->constrained('dashboard_imports')
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

            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['dashboard_import_id', 'chunk_id'],
                'dashboard_import_chunks_import_chunk_unique',
            );
            $table->index(
                ['dashboard_import_id', 'status'],
                'dashboard_import_chunks_import_status_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_import_chunks');
    }
};
