<?php

namespace App\Console\Commands;

use App\Messaging\Contracts\MessageEnvelope;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use LogicException;
use Modules\Orders\Handlers\OrderChunkRequestedHandler;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

#[Signature('orders:consume-chunks')]
#[Description('Consume order chunk requests from RabbitMQ.')]
class ConsumeOrderChunks extends Command
{
    protected $signature = 'orders:consume-chunks';

    protected $description = 'Consume order chunk requests from RabbitMQ.';

    public function handle(
        RabbitMQConnector $connector,
        OrderChunkRequestedHandler $handler,
    ): int {
        $rabbitMq = $connector->connect(config('queue.connections.rabbitmq'));

        if (! $rabbitMq instanceof RabbitMQQueue) {
            throw new LogicException('The RabbitMQ connection did not return the expected queue driver.');
        }

        $channel = $rabbitMq->getChannel();

        try {
            // Process one delivery at a time per consumer.
            $channel->basic_qos(0, 1, false);

            $channel->basic_consume(
                'orders.order-chunks',
                '',
                false,
                false,
                false,
                false,
                function (AMQPMessage $message) use ($channel, $handler): void {
                    try {
                        $envelope = MessageEnvelope::fromJson($message->getBody());
                        $handler->handle($envelope);
                        $this->info("Processed {$message->getDeliveryTag()} ({$message->getRoutingKey()}).");
                    } catch (Throwable $exception) {
                        report($exception);

                        // Requeue this delivery. The quorum queue's delivery limit
                        // and dead-letter exchange handle repeated failures.
                        $channel->basic_reject($message->getDeliveryTag(), true);
                        $this->error("Failed to process {$message->getDeliveryTag()} ({$message->getRoutingKey()}): {$exception->getMessage()}");
                        return;
                    }

                    // Ack only after the handler's database transaction succeeds.
                    $channel->basic_ack($message->getDeliveryTag());
                },
            );

            $this->info('Listening for order chunk requests. Press Ctrl+C to stop.');

            while ($channel->is_consuming()) {
                $channel->wait();
            }
        } finally {
            $rabbitMq->close();
        }

        return self::SUCCESS;
    }
}