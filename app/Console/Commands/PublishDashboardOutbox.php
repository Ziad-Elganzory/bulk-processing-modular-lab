<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Dashboard\Services\DashboardOutboxPublisher;

#[Signature('dashboard:outbox:publish')]
#[Description('Publish pending Dashboard outbox messages to RabbitMQ.')]
class PublishDashboardOutbox extends Command
{
    protected $signature = 'dashboard:outbox:publish';

    protected $description = 'Publish pending Dashboard outbox messages to RabbitMQ.';

    public function handle(DashboardOutboxPublisher $publisher): int
    {
        $publishedCount = $publisher->publishPending();

        $this->info("Published {$publishedCount} outbox message(s).");

        return self::SUCCESS;
    }
}