<?php

namespace Modules\BulkImports\Services;

use App\Messaging\Contracts\MessageEnvelope;
use App\Messaging\Contracts\V1\ImportCompleted;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Modules\BulkImports\Models\BulkImportsOutboxMessage;
use Modules\BulkImports\Models\ImportRun;
use RuntimeException;

class ImportRunFinalizer
{
    public function __construct(
        private readonly RejectedRowsReportBuilder $reportBuilder,
    ) {}

    public function finalizeIfReady(ImportRun $importRun): void
    {
        if (in_array($importRun->status, [
            'completed',
            'completed_with_errors',
            'failed',
            'cancelled',
        ], true)) {
            return;
        }

        if ($importRun->total_rows === null || $importRun->total_chunks === null) {
            throw new RuntimeException('Import totals must be known before finalization.');
        }

        if ($importRun->processed_chunks < $importRun->total_chunks) {
            return;
        }

        if ($importRun->processed_chunks > $importRun->total_chunks) {
            throw new RuntimeException('Processed chunks cannot exceed the import total.');
        }

        $hasFailedChunks = $importRun->chunks()
            ->where('status', 'failed')
            ->exists();

        $status = $hasFailedChunks
            ? 'failed'
            : ($importRun->rejected_rows > 0 ? 'completed_with_errors' : 'completed');

        $rejectedReportObjectKey = $importRun->rejected_rows > 0
            ? $this->reportBuilder->build($importRun)
            : null;

        if ($importRun->rejected_rows > 0 && $rejectedReportObjectKey === null) {
            throw new RuntimeException('Rejected rows exist, but no rejected-row report was created.');
        }

        $failureCode = $hasFailedChunks ? 'chunk_processing_failed' : null;
        $failureMessage = $hasFailedChunks
            ? 'One or more order chunks failed after retries.'
            : null;

        $now = now();

        $importRun->forceFill([
            'status' => $status,
            'failure_code' => $failureCode,
            'failure_message' => $failureMessage,
            'finished_at' => $now,
        ])->save();

        $this->recordCompletionOutbox(
            $importRun,
            $status,
            $rejectedReportObjectKey,
        );
    }

    private function recordCompletionOutbox(
        ImportRun $importRun,
        string $status,
        ?string $rejectedReportObjectKey,
    ): void {
        $completed = new ImportCompleted(
            importId: $importRun->import_id,
            status: $status,
            totalRows: $importRun->total_rows,
            processedRows: $importRun->processed_rows,
            acceptedRows: $importRun->accepted_rows,
            rejectedRows: $importRun->rejected_rows,
            rejectedReportObjectKey: $rejectedReportObjectKey,
        );

        $messageId = 'bulk-imports:completed:'.hash('sha256', $importRun->import_id);

        $envelope = new MessageEnvelope(
            messageId: $messageId,
            correlationId: $importRun->import_id,
            occurredAt: new DateTimeImmutable('now'),
            message: $completed,
        );

        BulkImportsOutboxMessage::query()->firstOrCreate(
            ['message_id' => $messageId],
            [
                'message_type' => $completed->messageType(),
                'correlation_id' => $envelope->correlationId,
                'exchange_name' => 'bulk-processing.events',
                'routing_key' => $completed->messageType(),
                'payload' => $envelope->toArray(),
                'status' => 'pending',
                'attempts' => 0,
                'available_at' => now(),
            ],
        );
    }

    public function failSource(
        ImportRun $importRun,
        string $failureCode,
        string $failureMessage,
    ): void {
        DB::transaction(function () use ($importRun, $failureCode, $failureMessage): void {
            $lockedImportRun = ImportRun::query()
                ->whereKey($importRun->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($lockedImportRun->status, [
                'completed',
                'completed_with_errors',
                'failed',
                'cancelled',
            ], true)) {
                return;
            }

            $lockedImportRun->forceFill([
                'status' => 'failed',
                'failure_code' => $failureCode,
                'failure_message' => $failureMessage,
                'finished_at' => now(),
            ])->save();

            $this->recordCompletionOutbox(
                $lockedImportRun,
                'failed',
                null,
            );
        });
    }
}
