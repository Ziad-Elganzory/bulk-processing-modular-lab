<?php

namespace Modules\BulkImports\Handlers;

use App\Messaging\Contracts\MessageEnvelope;
use App\Messaging\Contracts\V1\ImportProgressed;
use App\Messaging\Contracts\V1\OrdersChunkCommitted;
use App\Messaging\Contracts\V1\OrdersChunkFailed;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\BulkImports\Models\BulkImportsInboxMessage;
use Modules\BulkImports\Models\BulkImportsOutboxMessage;
use Modules\BulkImports\Models\ImportRun;
use Modules\BulkImports\Services\ImportRunFinalizer;
use RuntimeException;

class OrderChunkResultHandler
{
    public function __construct(
        private readonly ImportRunFinalizer $finalizer,
    ) {}

    public function handle(MessageEnvelope $envelope): void
    {
        $message = $envelope->message;

        if (! $message instanceof OrdersChunkCommitted
            && ! $message instanceof OrdersChunkFailed) {
            throw new InvalidArgumentException(
                "BulkImports cannot process message type [{$message->messageType()}].",
            );
        }

        DB::transaction(function () use ($envelope, $message): void {
            $now = now();

            BulkImportsInboxMessage::query()->firstOrCreate(
                ['message_id' => $envelope->messageId],
                [
                    'message_type' => $message->messageType(),
                    'correlation_id' => $envelope->correlationId,
                    'payload' => $envelope->toArray(),
                    'status' => 'received',
                    'received_at' => $now,
                ],
            );

            $inboxMessage = BulkImportsInboxMessage::query()
                ->where('message_id', $envelope->messageId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($inboxMessage->status === 'processed') {
                return;
            }

            $importRun = ImportRun::query()
                ->where('import_id', $message->importId)
                ->lockForUpdate()
                ->firstOrFail();

            $chunk = $importRun->chunks()
                ->where('chunk_id', $message->chunkId)
                ->lockForUpdate()
                ->firstOrFail();

            if (\in_array($chunk->status, ['committed', 'completed_with_errors', 'failed'], true)) {
                $inboxMessage->forceFill([
                    'status' => 'processed',
                    'processed_at' => $now,
                ])->save();

                return;
            }

            $processedRows = 0;
            $acceptedRows = 0;
            $rejectedRows = 0;

            if ($message instanceof OrdersChunkCommitted) {
                if ($message->acceptedRows + $message->rejectedRows !== $chunk->row_count) {
                    throw new RuntimeException(
                        'Orders result row counts do not match the stored chunk row count.',
                    );
                }

                $acceptedRows = $message->acceptedRows;
                $rejectedRows = $message->rejectedRows;
                $processedRows = $acceptedRows + $rejectedRows;

                $chunk->forceFill([
                    'status' => $rejectedRows > 0 ? 'completed_with_errors' : 'committed',
                    'accepted_rows' => $acceptedRows,
                    'rejected_rows' => $rejectedRows,
                    'accepted_object_key' => $message->acceptedObjectKey,
                    'rejected_object_key' => $message->rejectedObjectKey,
                    'finished_at' => $now,
                ])->save();
            } else {
                $chunk->forceFill([
                    'status' => 'failed',
                    'attempts' => $message->attempts,
                    'failure_code' => $message->failureCode,
                    'failure_message' => $message->failureMessage,
                    'finished_at' => $now,
                ])->save();
            }

            $importRun->forceFill([
                'processed_chunks' => $importRun->processed_chunks + 1,
                'processed_rows' => $importRun->processed_rows + $processedRows,
                'accepted_rows' => $importRun->accepted_rows + $acceptedRows,
                'rejected_rows' => $importRun->rejected_rows + $rejectedRows,
            ])->save();

            if ($importRun->total_rows === null || $importRun->total_chunks === null) {
                throw new RuntimeException('Import totals must be known before publishing progress.');
            }

            $progress = new ImportProgressed(
                importId: $importRun->import_id,
                processedChunks: $importRun->processed_chunks,
                totalChunks: $importRun->total_chunks,
                processedRows: $importRun->processed_rows,
                totalRows: $importRun->total_rows,
                acceptedRows: $importRun->accepted_rows,
                rejectedRows: $importRun->rejected_rows,
            );

            $progressMessageId = 'bulk-imports:progressed:'.hash(
                'sha256',
                $envelope->messageId,
            );

            $progressEnvelope = new MessageEnvelope(
                messageId: $progressMessageId,
                correlationId: $importRun->import_id,
                occurredAt: new DateTimeImmutable('now'),
                message: $progress,
            );

            BulkImportsOutboxMessage::query()->firstOrCreate(
                ['message_id' => $progressMessageId],
                [
                    'message_type' => $progress->messageType(),
                    'correlation_id' => $progressEnvelope->correlationId,
                    'exchange_name' => 'bulk-processing.events',
                    'routing_key' => $progress->messageType(),
                    'payload' => $progressEnvelope->toArray(),
                    'status' => 'pending',
                    'attempts' => 0,
                    'available_at' => $now,
                ],
            );

            $this->finalizer->finalizeIfReady($importRun);

            $inboxMessage->forceFill([
                'status' => 'processed',
                'processed_at' => $now,
            ])->save();
        });
    }
}
