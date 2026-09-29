<?php

namespace App\Console\Commands;

use App\Messaging\Contracts\MessageEnvelope;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use LogicException;
use Modules\BulkImports\Handlers\ImportRequestedHandler;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

#[Signature('bulk-imports:consume-import-requests')]
#[Description('Consume bulk import requests from RabbitMQ.')]
class ConsumeImportRequests extends Command
{
    protected $signature = 'bulk-imports:consume-import-requests';

    protected $description = 'Consume bulk import requests from RabbitMQ.';

    public function handle(
        RabbitMQConnector $connector,
        ImportRequestedHandler $handler,
    ): int {
        $rabbitMq = $connector->connect(config('queue.connections.rabbitmq'));

        if (! $rabbitMq instanceof RabbitMQQueue) {
            throw new LogicException('The RabbitMQ connection did not return the expected queue driver.');
        }

        $channel = $rabbitMq->getChannel();

        try {
            $channel->basic_qos(0, 1, false);

            $channel->basic_consume(
                'bulk-imports.import-requests',
                '',
                false,
                false,
                false,
                false,
                function (AMQPMessage $message) use ($channel, $handler): void {
                    try {
                        $envelope = MessageEnvelope::fromJson($message->getBody());
                        $handler->handle($envelope);

                        $this->info(
                            "Processed {$message->getDeliveryTag()} ({$message->getRoutingKey()}).",
                        );
                    } catch (Throwable $exception) {
                        report($exception);
                        $channel->basic_reject($message->getDeliveryTag(), true);
                        $this->error("Failed to process {$message->getDeliveryTag()} ({$message->getRoutingKey()}): {$exception->getMessage()}");
                        return;
                    }

                    $channel->basic_ack($message->getDeliveryTag());
                },
            );

            $this->info('Listening for bulk import requests. Press Ctrl+C to stop.');

            while ($channel->is_consuming()) {
                $channel->wait();
            }
        } finally {
            $rabbitMq->close();
        }

        return self::SUCCESS;
    }
}