<?php

namespace Modules\BulkImports\Services;

use InvalidArgumentException;
use Modules\BulkImports\Models\BulkImportsOutboxMessage;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;
use Throwable;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class BulkImportsOutboxPublisher
{
    public function __construct(
        private readonly RabbitMQConnector $connector,
    ) {}

    public function publishPending(int $limit = 100): int
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('The publish limit must be at least 1.');
        }

        $messages = BulkImportsOutboxMessage::query()
            ->where('status', 'pending')
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($messages->isEmpty()) {
            return 0;
        }

        $rabbitMq = $this->connector->connect(config('queue.connections.rabbitmq'));

        if (! $rabbitMq instanceof RabbitMQQueue) {
            throw new RuntimeException(
                'The RabbitMQ connection did not return the expected queue driver.',
            );
        }

        $channel = $rabbitMq->getChannel();
        $channel->confirm_select();

        $channel->set_nack_handler(static function (AMQPMessage $message): void {
            throw new RuntimeException('RabbitMQ negatively acknowledged an outbox message.');
        });

        $channel->set_return_listener(
            static function (
                int $replyCode,
                string $replyText,
                string $exchange,
                string $routingKey,
                AMQPMessage $message,
            ): void {
                throw new RuntimeException(
                    "RabbitMQ returned an unroutable message for [{$exchange}:{$routingKey}].",
                );
            },
        );

        $publishedCount = 0;

        try {
            foreach ($messages as $outboxMessage) {
                $attempts = $outboxMessage->attempts + 1;

                $message = new AMQPMessage(
                    json_encode($outboxMessage->payload, JSON_THROW_ON_ERROR),
                    [
                        'content_type' => 'application/json',
                        'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                        'message_id' => $outboxMessage->message_id,
                        'correlation_id' => $outboxMessage->correlation_id,
                        'type' => $outboxMessage->message_type,
                    ],
                );

                try {
                    $channel->basic_publish(
                        $message,
                        $outboxMessage->exchange_name,
                        $outboxMessage->routing_key,
                        true,
                    );

                    $channel->wait_for_pending_acks_returns(5);

                    $outboxMessage->forceFill([
                        'status' => 'published',
                        'attempts' => $attempts,
                        'published_at' => now(),
                        'last_error' => null,
                    ])->save();

                    $publishedCount++;
                } catch (Throwable $exception) {
                    $delaySeconds = min(30 * (2 ** min($outboxMessage->attempts, 7)), 3600);

                    $outboxMessage->forceFill([
                        'status' => 'pending',
                        'attempts' => $attempts,
                        'available_at' => now()->addSeconds($delaySeconds),
                        'last_error' => substr($exception->getMessage(), 0, 60000),
                    ])->save();

                    report($exception);

                    // Reconnect on the next run; confirmation state may be uncertain.
                    break;
                }
            }
        } finally {
            $rabbitMq->close();
        }

        return $publishedCount;
    }
}
