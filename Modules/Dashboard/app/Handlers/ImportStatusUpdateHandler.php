<?php

namespace Modules\Dashboard\Handlers;

use App\Messaging\Contracts\MessageEnvelope;
use App\Messaging\Contracts\V1\ImportCompleted;
use App\Messaging\Contracts\V1\ImportProgressed;
use App\Messaging\Contracts\V1\ImportStarted;
use App\Messaging\Contracts\V1\OrderChunkRequested;
use App\Messaging\Contracts\V1\OrdersChunkCommitted;
use App\Messaging\Contracts\V1\OrdersChunkFailed;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Dashboard\Models\DashboardImport;
use Modules\Dashboard\Models\DashboardImportChunk;
use Modules\Dashboard\Models\DashboardInboxMessage;
use RuntimeException;

class ImportStatusUpdateHandler
{
    public function handle(MessageEnvelope $envelope): void
    {
        $message = $envelope->message;

        if (! $message instanceof ImportStarted
            && ! $message instanceof ImportProgressed
            && ! $message instanceof ImportCompleted
            && ! $message instanceof OrderChunkRequested
            && ! $message instanceof OrdersChunkCommitted
            && ! $message instanceof OrdersChunkFailed) {
            throw new InvalidArgumentException(
                "Dashboard cannot process message type [{$message->messageType()}].",
            );
        }

        DB::transaction(function () use ($envelope, $message): void {
            $now = now();

            DashboardInboxMessage::query()->firstOrCreate(
                ['message_id' => $envelope->messageId],
                [
                    'message_type' => $message->messageType(),
                    'correlation_id' => $envelope->correlationId,
                    'payload' => $envelope->toArray(),
                    'status' => 'received',
                    'received_at' => $now,
                ],
            );

            $inboxMessage = DashboardInboxMessage::query()
                ->where('message_id', $envelope->messageId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($inboxMessage->status === 'processed') {
                return;
            }

            $import = DashboardImport::query()
                ->where('import_id', $message->importId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($message instanceof ImportStarted) {
                if (! in_array($import->status, [
                    'completed',
                    'completed_with_errors',
                    'failed',
                    'cancelled',
                ], true)) {
                    $import->forceFill([
                        'status' => 'processing',
                        'started_at' => $import->started_at ?? $envelope->occurredAt,
                    ])->save();
                }
            } elseif ($message instanceof ImportProgressed) {
                if (! in_array($import->status, [
                    'completed',
                    'completed_with_errors',
                    'failed',
                    'cancelled',
                ], true)) {
                    $import->forceFill([
                        'status' => 'processing',
                        'total_rows' => $message->totalRows,
                        'processed_rows' => $message->processedRows,
                        'accepted_rows' => $message->acceptedRows,
                        'rejected_rows' => $message->rejectedRows,
                        'total_chunks' => $message->totalChunks,
                        'processed_chunks' => $message->processedChunks,
                        'started_at' => $import->started_at ?? $now,
                    ])->save();
                }
            } elseif ($message instanceof ImportCompleted) {
                $import->forceFill([
                    'status' => $message->status,
                    'total_rows' => $message->totalRows,
                    'processed_rows' => $message->processedRows,
                    'accepted_rows' => $message->acceptedRows,
                    'rejected_rows' => $message->rejectedRows,
                    'rejected_report_object_key' => $message->rejectedReportObjectKey,
                    'started_at' => $import->started_at ?? $now,
                    'finished_at' => $now,
                ])->save();
            } elseif ($message instanceof OrderChunkRequested) {
                DashboardImportChunk::query()->firstOrCreate(
                    [
                        'dashboard_import_id' => $import->id,
                        'chunk_id' => $message->chunkId,
                    ],
                    [
                        'status' => 'pending',
                        'object_key' => $message->objectKey,
                        'row_start' => $message->rowStart,
                        'row_count' => $message->rowCount,
                        'attempts' => 0,
                        'accepted_rows' => 0,
                        'rejected_rows' => 0,
                    ],
                );
            } else {
                $chunk = DashboardImportChunk::query()
                    ->where('dashboard_import_id', $import->id)
                    ->where('chunk_id', $message->chunkId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($message instanceof OrdersChunkCommitted) {
                    if ($message->acceptedRows + $message->rejectedRows !== $chunk->row_count) {
                        throw new RuntimeException(
                            'Orders result row counts do not match the Dashboard chunk row count.',
                        );
                    }

                    $chunk->forceFill([
                        'status' => $message->rejectedRows > 0
                            ? 'completed_with_errors'
                            : 'completed',
                        'accepted_rows' => $message->acceptedRows,
                        'rejected_rows' => $message->rejectedRows,
                        'accepted_object_key' => $message->acceptedObjectKey,
                        'rejected_object_key' => $message->rejectedObjectKey,
                        'failure_code' => null,
                        'failure_message' => null,
                        'finished_at' => $now,
                    ])->save();
                } elseif ($message instanceof OrdersChunkFailed) {
                    $chunk->forceFill([
                        'status' => 'failed',
                        'attempts' => $message->attempts,
                        'failure_code' => $message->failureCode,
                        'failure_message' => $message->failureMessage,
                        'finished_at' => $now,
                    ])->save();
                }
            }

            $inboxMessage->forceFill([
                'status' => 'processed',
                'processed_at' => $now,
            ])->save();
        });
    }
}
