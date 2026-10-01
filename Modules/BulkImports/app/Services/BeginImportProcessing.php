<?php

namespace Modules\BulkImports\Services;

use App\Messaging\Contracts\MessageEnvelope;
use App\Messaging\Contracts\V1\ImportStarted;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Modules\BulkImports\Models\BulkImportsOutboxMessage;
use Modules\BulkImports\Models\ImportRun;

class BeginImportProcessing
{
    public function begin(int $importRunId): bool
    {
        return DB::transaction(function () use ($importRunId): bool {
            $importRun = ImportRun::query()
                ->whereKey($importRunId)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($importRun->status, [
                'failed',
                'completed',
                'completed_with_errors',
                'cancelled',
            ], true)) {
                return false;
            }

            $now = now();

            $importRun->forceFill([
                'status' => 'processing',
                'started_at' => $importRun->started_at ?? $now,
                'failure_code' => null,
                'failure_message' => null,
            ])->save();

            $message = new ImportStarted(importId: $importRun->import_id);
            $messageId = 'bulk-imports:started:'.hash('sha256', $importRun->import_id);

            $envelope = new MessageEnvelope(
                messageId: $messageId,
                correlationId: $importRun->import_id,
                occurredAt: new DateTimeImmutable('now'),
                message: $message,
            );

            BulkImportsOutboxMessage::query()->firstOrCreate(
                ['message_id' => $messageId],
                [
                    'message_type' => $message->messageType(),
                    'correlation_id' => $envelope->correlationId,
                    'exchange_name' => 'bulk-processing.events',
                    'routing_key' => $message->messageType(),
                    'payload' => $envelope->toArray(),
                    'status' => 'pending',
                    'attempts' => 0,
                    'available_at' => $now,
                ],
            );

            return true;
        });
    }
}
