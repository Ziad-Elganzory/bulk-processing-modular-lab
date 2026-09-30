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
        Schema::create('import_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('import_id', 64)->unique();
            $table->string('source_object_key');
            $table->string('requested_by', 100);
            $table->string('status', 32)->default('queued');

            $table->unsignedBigInteger('total_rows')->nullable();
            $table->unsignedBigInteger('processed_rows')->default(0);
            $table->unsignedBigInteger('accepted_rows')->default(0);
            $table->unsignedBigInteger('rejected_rows')->default(0);

            $table->unsignedInteger('total_chunks')->nullable();
            $table->unsignedInteger('processed_chunks')->default(0);

            $table->string('failure_code', 100)->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_runs');
    }
};
