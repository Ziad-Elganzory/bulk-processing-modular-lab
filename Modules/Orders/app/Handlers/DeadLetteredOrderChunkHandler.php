<?php

namespace Modules\Orders\Handlers;

use App\Messaging\Contracts\MessageEnvelope;
use App\Messaging\Contracts\V1\OrderChunkRequested;
use App\Messaging\Contracts\V1\OrdersChunkFailed;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Orders\Models\OrderChunkRun;
use Modules\Orders\Models\OrdersInboxMessage;
use Modules\Orders\Models\OrdersOutboxMessage;

class DeadLetteredOrderChunkHandler
{
    public function handle(MessageEnvelope $envelope, int $attempts): void
    {
        if (! $envelope->message instanceof OrderChunkRequested) {
            throw new InvalidArgumentException(
                "Orders failure handler cannot process message type [{$envelope->message->messageType()}].",
            );
        }

        $chunk = $envelope->message;
        $attempts = max($attempts, 1);

        DB::transaction(function () use ($envelope, $chunk, $attempts): void {
            $now = now();

            $inboxMessage = OrdersInboxMessage::query()->firstOrCreate(
                ['message_id' => $envelope->messageId],
                [
                    'message_type' => $envelope->message->messageType(),
                    'correlation_id' => $envelope->correlationId,
                    'payload' => $envelope->toArray(),
                    'status' => 'received',
                    'received_at' => $now,
                ],
            );

            $inboxMessage = OrdersInboxMessage::query()
                ->where('message_id', $envelope->messageId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($inboxMessage->status === 'processed') {
                return;
            }

            $chunkRun = OrderChunkRun::query()
                ->where('import_id', $chunk->importId)
                ->where('chunk_id', $chunk->chunkId)
                ->lockForUpdate()
                ->first();

            if ($chunkRun !== null && in_array($chunkRun->status, ['committed', 'failed'], true)) {
                $inboxMessage->forceFill([
                    'status' => 'processed',
                    'processed_at' => $now,
                ])->save();

                return;
            }

            if ($chunkRun === null) {
                $chunkRun = OrderChunkRun::query()->create([
                    'import_id' => $chunk->importId,
                    'chunk_id' => $chunk->chunkId,
                    'status' => 'processing',
                    'row_start' => $chunk->rowStart,
                    'row_count' => $chunk->rowCount,
                    'started_at' => $now,
                ]);
            }

            $failureCode = 'delivery_limit_exceeded';
            $failureMessage = 'The chunk request exceeded the RabbitMQ delivery limit.';

            $chunkRun->forceFill([
                'status' => 'failed',
                'attempts' => $attempts,
                'failure_code' => $failureCode,
                'failure_message' => $failureMessage,
                'finished_at' => $now,
            ])->save();

            $event = new OrdersChunkFailed(
                importId: $chunk->importId,
                chunkId: $chunk->chunkId,
                failureCode: $failureCode,
                failureMessage: $failureMessage,
                attempts: $attempts,
            );

            $outgoingEnvelope = new MessageEnvelope(
                messageId: (string) Str::uuid(),
                correlationId: $chunk->importId,
                occurredAt: new DateTimeImmutable('now'),
                message: $event,
            );

            OrdersOutboxMessage::query()->create([
                'message_id' => $outgoingEnvelope->messageId,
                'message_type' => $event->messageType(),
                'correlation_id' => $outgoingEnvelope->correlationId,
                'exchange_name' => 'bulk-processing.events',
                'routing_key' => $event->messageType(),
                'payload' => $outgoingEnvelope->toArray(),
                'status' => 'pending',
                'attempts' => 0,
                'available_at' => $now,
            ]);

            $inboxMessage->forceFill([
                'status' => 'processed',
                'processed_at' => $now,
            ])->save();
        });
    }
}
