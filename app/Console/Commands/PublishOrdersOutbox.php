<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Orders\Services\OrdersOutboxPublisher;

#[Signature('orders:outbox:publish')]
#[Description('Publish pending Orders outbox messages to RabbitMQ.')]
class PublishOrdersOutbox extends Command
{
    protected $signature = 'orders:outbox:publish';

    protected $description = 'Publish pending Orders outbox messages to RabbitMQ.';

    public function handle(OrdersOutboxPublisher $publisher): int
    {
        $publishedCount = $publisher->publishPending();

        $this->info("Published {$publishedCount} outbox message(s).");

        return self::SUCCESS;
    }
}
