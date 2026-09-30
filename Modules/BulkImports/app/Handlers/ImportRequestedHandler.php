<?php

namespace Modules\BulkImports\Handlers;

use App\Messaging\Contracts\MessageEnvelope;
use App\Messaging\Contracts\V1\ImportRequested;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\BulkImports\Jobs\ProcessImport;
use Modules\BulkImports\Models\BulkImportsInboxMessage;
use Modules\BulkImports\Models\ImportRun;

class ImportRequestedHandler
{
    public function handle(MessageEnvelope $envelope): void
    {
        if (! $envelope->message instanceof ImportRequested) {
            throw new InvalidArgumentException(
                "BulkImports handler cannot process message type [{$envelope->message->messageType()}].",
            );
        }

        $request = $envelope->message;

        $importRunId = DB::transaction(function () use ($envelope, $request): ?int {
            $now = now();

            BulkImportsInboxMessage::query()->firstOrCreate(
                ['message_id' => $envelope->messageId],
                [
                    'message_type' => $request->messageType(),
                    'correlation_id' => $envelope->correlationId,
                    'payload' => $envelope->toArray(),
                    'status' => 'received',
                    'received_at' => $now,
                ],
            );

            $importRun = ImportRun::query()->firstOrCreate(
                ['import_id' => $request->importId],
                [
                    'source_object_key' => $request->sourceObjectKey,
                    'requested_by' => $request->requestedBy,
                    'status' => 'queued',
                    'total_rows' => null,
                    'processed_rows' => 0,
                    'accepted_rows' => 0,
                    'rejected_rows' => 0,
                    'total_chunks' => null,
                    'processed_chunks' => 0,
                ],
            );

            return $importRun->status === 'queued'
                ? $importRun->id
                : null;
        });

        if ($importRunId !== null) {
            ProcessImport::dispatch($importRunId)
                ->onConnection('rabbitmq')
                ->onQueue('bulk-imports.process-imports');
        }

        BulkImportsInboxMessage::query()
            ->where('message_id', $envelope->messageId)
            ->firstOrFail()
            ->forceFill([
                'status' => 'processed',
                'processed_at' => now(),
            ])
            ->save();
    }
}
