<?php

namespace Modules\Dashboard\Services;

use App\Messaging\Contracts\MessageEnvelope;
use App\Messaging\Contracts\V1\ImportRequested;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Dashboard\Models\DashboardImport;
use Modules\Dashboard\Models\DashboardOutboxMessage;
use RuntimeException;

class StartDashboardImport
{
    public function start(
        string $sourceObjectKey,
        string $fileName,
        string $requestedBy,
    ): DashboardImport {
        $disk = Storage::disk('s3');

        if (! $disk->exists($sourceObjectKey)) {
            throw new RuntimeException('The uploaded CSV could not be found in S3 storage.');
        }

        $sourceChecksum = $this->checksum($sourceObjectKey);
        $fileSizeBytes = $disk->size($sourceObjectKey);
        $importId = (string) Str::uuid();

        $message = new ImportRequested(
            importId: $importId,
            sourceObjectKey: $sourceObjectKey,
            sourceChecksum: $sourceChecksum,
            requestedBy: $requestedBy,
        );

        $envelope = new MessageEnvelope(
            messageId: (string) Str::uuid(),
            correlationId: $importId,
            occurredAt: new DateTimeImmutable('now'),
            message: $message,
        );

        return DB::transaction(function () use (
            $envelope,
            $fileName,
            $fileSizeBytes,
            $importId,
            $message,
            $requestedBy,
            $sourceChecksum,
            $sourceObjectKey,
        ): DashboardImport {
            $import = DashboardImport::query()->create([
                'import_id' => $importId,
                'file_name' => $fileName,
                'source_object_key' => $sourceObjectKey,
                'source_checksum' => $sourceChecksum,
                'requested_by' => $requestedBy,
                'status' => 'queued',
                'file_size_bytes' => $fileSizeBytes,
            ]);

            DashboardOutboxMessage::query()->create([
                'message_id' => $envelope->messageId,
                'message_type' => $message->messageType(),
                'correlation_id' => $envelope->correlationId,
                'exchange_name' => 'bulk-processing.commands',
                'routing_key' => $message->messageType(),
                'payload' => $envelope->toArray(),
                'status' => 'pending',
                'attempts' => 0,
                'available_at' => now(),
            ]);

            return $import;
        });
    }

    private function checksum(string $sourceObjectKey): string
    {
        $stream = Storage::disk('s3')->readStream($sourceObjectKey);

        if (! is_resource($stream)) {
            throw new RuntimeException('The uploaded CSV could not be read from S3 storage.');
        }

        try {
            $context = hash_init('sha256');

            if (hash_update_stream($context, $stream) === false) {
                throw new RuntimeException('The uploaded CSV checksum could not be calculated.');
            }

            return 'sha256:'.hash_final($context);
        } finally {
            fclose($stream);
        }
    }
}
