<?php

namespace Modules\Orders\Services;

use App\Messaging\Contracts\MessageEnvelope;
use App\Messaging\Contracts\V1\OrderChunkRequested;
use App\Messaging\Contracts\V1\OrdersChunkCommitted;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderChunkRun;
use Modules\Orders\Models\OrdersInboxMessage;
use Modules\Orders\Models\OrdersOutboxMessage;
use RuntimeException;

class OrderChunkCommitter
{
    /**
     * @param array{
     *     accepted: list<array{source_row_number: int, attributes: array<string, mixed>}>,
     *     rejected: list<array{
     *         source_row_number: int,
     *         row: array<string, ?string>,
     *         errors: array<string, list<string>>
     *     }>
     * } $result
     * @param array{
     *     accepted_rows: int,
     *     rejected_rows: int,
     *     accepted_object_key: ?string,
     *     rejected_object_key: ?string
     * } $files
     */
    public function commit(
        MessageEnvelope $incomingEnvelope,
        OrderChunkRequested $chunk,
        array $result,
        array $files,
    ): void {
        DB::transaction(function () use ($incomingEnvelope, $chunk, $result, $files): void {
            $now = now();

            $inboxMessage = OrdersInboxMessage::query()->firstOrCreate(
                ['message_id' => $incomingEnvelope->messageId],
                [
                    'message_type' => $incomingEnvelope->message->messageType(),
                    'correlation_id' => $incomingEnvelope->correlationId,
                    'payload' => $incomingEnvelope->toArray(),
                    'status' => 'received',
                    'received_at' => $now,
                ],
            );

            if (! $inboxMessage->wasRecentlyCreated) {
                if ($inboxMessage->status === 'processed') {
                    return;
                }

                throw new RuntimeException(
                    "Inbox message [{$incomingEnvelope->messageId}] already exists but is not processed.",
                );
            }

            $chunkRun = OrderChunkRun::query()
                ->where('import_id', $chunk->importId)
                ->where('chunk_id', $chunk->chunkId)
                ->lockForUpdate()
                ->first();

            if ($chunkRun !== null && $chunkRun->status === 'committed') {
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

            if ($result['accepted'] !== []) {
                $orderRows = [];

                foreach ($result['accepted'] as $item) {
                    $orderRows[] = [
                        ...$item['attributes'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                Order::query()->insert($orderRows);
            }

            $chunkRun->forceFill([
                'status' => 'committed',
                'accepted_rows' => $files['accepted_rows'],
                'rejected_rows' => $files['rejected_rows'],
                'accepted_object_key' => $files['accepted_object_key'],
                'rejected_object_key' => $files['rejected_object_key'],
                'finished_at' => $now,
            ])->save();

            $event = new OrdersChunkCommitted(
                importId: $chunk->importId,
                chunkId: $chunk->chunkId,
                acceptedRows: $files['accepted_rows'],
                rejectedRows: $files['rejected_rows'],
                acceptedObjectKey: $files['accepted_object_key'],
                rejectedObjectKey: $files['rejected_object_key'],
            );

            $outgoingEnvelope = new MessageEnvelope(
                messageId: (string) Str::uuid(),
                correlationId: $chunk->importId,
                occurredAt: new DateTimeImmutable,
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
