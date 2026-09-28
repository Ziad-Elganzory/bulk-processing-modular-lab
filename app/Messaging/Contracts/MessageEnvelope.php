<?php

namespace App\Messaging\Contracts;

use App\Messaging\Contracts\V1\AnalyticsChunkFailed;
use App\Messaging\Contracts\V1\AnalyticsChunkLoaded;
use App\Messaging\Contracts\V1\ImportCompleted;
use App\Messaging\Contracts\V1\ImportProgressed;
use App\Messaging\Contracts\V1\ImportRequested;
use App\Messaging\Contracts\V1\OrderChunkRequested;
use App\Messaging\Contracts\V1\OrdersChunkCommitted;
use App\Messaging\Contracts\V1\OrdersChunkFailed;
use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;

final readonly class MessageEnvelope
{
    public function __construct(
        public string $messageId,
        public string $correlationId,
        public DateTimeImmutable $occurredAt,
        public MessageContract $message,
    ) {
        MessageData::assertNonEmptyString($this->messageId, 'message_id');
        MessageData::assertNonEmptyString($this->correlationId, 'correlation_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message_id' => $this->messageId,
            'message_type' => $this->message->messageType(),
            'correlation_id' => $this->correlationId,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
            'data' => $this->message->data(),
        ];
    }

    /**
     * @throws JsonException
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * @throws JsonException
     */
    public static function fromJson(string $json): self
    {
        $envelope = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($envelope) || array_is_list($envelope)) {
            throw new InvalidArgumentException('The message envelope must be a JSON object.');
        }

        $data = $envelope['data'] ?? null;

        if (! is_array($data) || array_is_list($data)) {
            throw new InvalidArgumentException('The message data must be a JSON object.');
        }

        $messageType = MessageData::requiredString($envelope, 'message_type');

        $occurredAtValue = MessageData::requiredString($envelope, 'occurred_at');
        $occurredAt = DateTimeImmutable::createFromFormat('!'.DATE_ATOM, $occurredAtValue);
        $dateErrors = DateTimeImmutable::getLastErrors();

        if (
            $occurredAt === false
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $occurredAt->format(DATE_ATOM) !== $occurredAtValue
        ) {
            throw new InvalidArgumentException('The [occurred_at] field must use the DATE_ATOM format.');
        }

        $message = match ($messageType) {
            ImportRequested::TYPE => ImportRequested::fromData($data),
            OrderChunkRequested::TYPE => OrderChunkRequested::fromData($data),
            OrdersChunkCommitted::TYPE => OrdersChunkCommitted::fromData($data),
            ImportProgressed::TYPE => ImportProgressed::fromData($data),
            ImportCompleted::TYPE => ImportCompleted::fromData($data),
            AnalyticsChunkLoaded::TYPE => AnalyticsChunkLoaded::fromData($data),
            OrdersChunkFailed::TYPE => OrdersChunkFailed::fromData($data),
            AnalyticsChunkFailed::TYPE => AnalyticsChunkFailed::fromData($data),
            default => throw new InvalidArgumentException(
                "Unsupported message type [{$messageType}].",
            ),
        };

        return new self(
            messageId: MessageData::requiredString($envelope, 'message_id'),
            correlationId: MessageData::requiredString($envelope, 'correlation_id'),
            occurredAt: $occurredAt,
            message: $message,
        );
    }
}
