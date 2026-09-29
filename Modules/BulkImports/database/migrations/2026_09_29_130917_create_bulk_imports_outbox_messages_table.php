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
        Schema::create('bulk_imports_outbox_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('message_id', 100)->unique();
            $table->string('message_type', 150);
            $table->string('correlation_id', 100)->index();
            $table->string('exchange_name', 150);
            $table->string('routing_key', 150);
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('available_at');
            $table->timestamp('published_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        
            $table->index(
                ['status', 'available_at'],
                'bulk_imports_outbox_dispatch_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bulk_imports_outbox_messages');
    }
};
