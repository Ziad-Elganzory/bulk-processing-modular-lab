<?php

namespace App\Console\Commands;

use App\Messaging\Contracts\MessageEnvelope;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use LogicException;
use Modules\Orders\Handlers\DeadLetteredOrderChunkHandler;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

#[Signature('orders:consume-failed-chunks')]
#[Description('Consume failed order chunk requests from RabbitMQ.')]
class ConsumeFailedOrderChunks extends Command
{
    protected $signature = 'orders:consume-failed-chunks';

    protected $description = 'Consume failed order chunk requests from RabbitMQ.';

    public function handle(
        RabbitMQConnector $connector,
        DeadLetteredOrderChunkHandler $handler,
    ): int {
        $rabbitMq = $connector->connect(config('queue.connections.rabbitmq'));

        if (! $rabbitMq instanceof RabbitMQQueue) {
            throw new LogicException('The RabbitMQ connection did not return the expected queue driver.');
        }

        $topology = config('rabbitmq-topology');
        $deliveryLimit = (int) (
            $topology['queues']['orders.order-chunks']['arguments']['x-delivery-limit'] ?? 1
        );

        $channel = $rabbitMq->getChannel();

        try {
            $channel->basic_qos(0, 1, false);

            $channel->basic_consume(
                'orders.order-chunks.failed',
                '',
                false,
                false,
                false,
                false,
                function (AMQPMessage $message) use ($channel, $handler, $deliveryLimit): void {
                    try {
                        $envelope = MessageEnvelope::fromJson($message->getBody());
                        $attempts = $this->deliveryAttempts($message, $deliveryLimit);

                        $handler->handle($envelope, $attempts);
                        $this->info("Processed {$message->getDeliveryTag()} ({$message->getRoutingKey()}).");
                    } catch (Throwable $exception) {
                        report($exception);

                        $channel->basic_reject($message->getDeliveryTag(), true);
                        $this->error("Failed to process {$message->getDeliveryTag()} ({$message->getRoutingKey()}): {$exception->getMessage()}");

                        return;
                    }

                    $channel->basic_ack($message->getDeliveryTag());
                },
            );

            $this->info('Listening for dead-lettered order chunks. Press Ctrl+C to stop.');

            while ($channel->is_consuming()) {
                $channel->wait();
            }
        } finally {
            $rabbitMq->close();
        }

        return self::SUCCESS;
    }

    private function deliveryAttempts(AMQPMessage $message, int $fallback): int
    {
        $applicationHeaders = $message->get_properties()['application_headers'] ?? null;

        if (! $applicationHeaders instanceof AMQPTable) {
            return max($fallback, 1);
        }

        $headers = $applicationHeaders->getNativeData();
        $deliveryCount = $headers['x-delivery-count'] ?? null;

        if (! \is_numeric($deliveryCount)) {
            return max($fallback, 1);
        }

        return max((int) $deliveryCount, 1);
    }
}
