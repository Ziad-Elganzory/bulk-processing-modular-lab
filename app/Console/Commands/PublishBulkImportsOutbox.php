<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\BulkImports\Services\BulkImportsOutboxPublisher;

#[Signature('bulk-imports:outbox:publish')]
#[Description('Publish pending BulkImports outbox messages to RabbitMQ.')]
class PublishBulkImportsOutbox extends Command
{
    protected $signature = 'bulk-imports:outbox:publish';

    protected $description = 'Publish pending BulkImports outbox messages to RabbitMQ.';

    public function handle(BulkImportsOutboxPublisher $publisher): int
    {
        $publishedCount = $publisher->publishPending();

        $this->info("Published {$publishedCount} outbox message(s).");

        return self::SUCCESS;
    }
}