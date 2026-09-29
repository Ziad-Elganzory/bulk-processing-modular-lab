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
        Schema::create('bulk_imports_inbox_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('message_id', 100)->unique();
            $table->string('message_type', 150);
            $table->string('correlation_id', 100)->index();
            $table->json('payload');
            $table->string('status', 20)->default('received');
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bulk_imports_inbox_messages');
    }
};
