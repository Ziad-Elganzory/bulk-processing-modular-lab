<?php

namespace Modules\BulkImports\Services;

use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Modules\BulkImports\Models\ImportChunk;
use Modules\BulkImports\Models\ImportRun;
use App\Messaging\Contracts\MessageEnvelope;
use App\Messaging\Contracts\V1\OrderChunkRequested;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Modules\BulkImports\Models\BulkImportsOutboxMessage;
use RuntimeException;

class ImportCsvChunker
{
    public function process(int $importRunId): void
    {
        $importRun = ImportRun::query()->findOrFail($importRunId);

        if (in_array($importRun->status, ['failed', 'completed', 'completed_with_errors'], true)) {
            return;
        }

        $importRun->forceFill([
            'status' => 'processing',
            'started_at' => $importRun->started_at ?? now(),
            'failure_code' => null,
            'failure_message' => null,
        ])->save();

        $expectedHeaders = config('bulkimports.csv_headers');
        $chunkSize = (int) config('bulkimports.chunk_size');

        if (! is_array($expectedHeaders) || $chunkSize < 1) {
            throw new RuntimeException('BulkImports CSV configuration is invalid.');
        }

        try {
            $totalRows = $this->validateSource(
                $importRun->source_object_key,
                $expectedHeaders,
            );
        } catch (InvalidArgumentException $exception) {
            $importRun->forceFill([
                'status' => 'failed',
                'failure_code' => 'invalid_source_csv',
                'failure_message' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();

            return;
        }

        if ($totalRows === 0) {
            $importRun->forceFill([
                'status' => 'failed',
                'failure_code' => 'invalid_source_csv',
                'failure_message' => 'The source CSV contains no data rows.',
                'finished_at' => now(),
            ])->save();

            return;
        }

        $totalChunks = $this->writeChunks(
            $importRun,
            $expectedHeaders,
            $chunkSize,
        );

        $importRun->forceFill([
            'total_rows' => $totalRows,
            'total_chunks' => $totalChunks,
        ])->save();
    }

    /**
     * @param list<string> $expectedHeaders
     */
    private function validateSource(string $objectKey, array $expectedHeaders): int
    {
        $stream = Storage::disk(config('filesystems.default'))->readStream($objectKey);

        if (! \is_resource($stream)) {
            throw new RuntimeException("Could not read source CSV [{$objectKey}].");
        }

        try {
            $headers = fgetcsv($stream);

            if ($headers === false) {
                throw new InvalidArgumentException('The source CSV is empty.');
            }

            $headers = $this->normalizeHeaders($headers);

            if ($headers !== $expectedHeaders) {
                throw new InvalidArgumentException(
                    'The source CSV headers do not match the expected format.',
                );
            }

            $rowCount = 0;

            while (($row = fgetcsv($stream)) !== false) {
                if (\count($row) !== \count($expectedHeaders)) {
                    $sourceRow = $rowCount + 2;

                    throw new InvalidArgumentException(
                        "Source CSV row {$sourceRow} has an unexpected number of columns.",
                    );
                }

                $rowCount++;
            }

            return $rowCount;
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param list<int|string|null> $headers
     * @return list<string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $headers = array_map(
            static fn (int|string|null $header): string => trim((string) $header),
            $headers,
        );

        if (isset($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]) ?? $headers[0];
        }

        return $headers;
    }

    /**
     * @param list<string> $headers
     */
    private function writeChunks(
        ImportRun $importRun,
        array $headers,
        int $chunkSize,
    ): int {
        $disk = Storage::disk(config('filesystems.default'));
        $sourceStream = $disk->readStream($importRun->source_object_key);

        if (! \is_resource($sourceStream)) {
            throw new RuntimeException(
                "Could not reopen source CSV [{$importRun->source_object_key}].",
            );
        }

        $chunkStream = null;

        try {
            $sourceHeaders = fgetcsv($sourceStream);

            if (
                $sourceHeaders === false
                || $this->normalizeHeaders($sourceHeaders) !== $headers
            ) {
                throw new RuntimeException('The source CSV header changed after validation.');
            }

            $chunkIndex = 1;
            $sourceRowNumber = 2;
            $chunkRowStart = 2;
            $chunkRowCount = 0;
            $chunkCount = 0;
            $chunkStream = $this->newChunkStream($headers);

            while (($row = fgetcsv($sourceStream)) !== false) {
                if (\count($row) !== \count($headers)) {
                    throw new RuntimeException(
                        "Source CSV row {$sourceRowNumber} changed after validation.",
                    );
                }

                if (fputcsv($chunkStream, $row, ',', '"', '') === false) {
                    throw new RuntimeException(
                        'Could not write a row to the temporary chunk stream.',
                    );
                }

                $chunkRowCount++;

                if ($chunkRowCount === $chunkSize) {
                    $this->storeChunk(
                        $importRun,
                        $chunkIndex,
                        $chunkRowStart,
                        $chunkRowCount,
                        $chunkStream,
                    );

                    $chunkCount++;
                    fclose($chunkStream);
                    $chunkStream = null;

                    $chunkIndex++;
                    $sourceRowNumber++;
                    $chunkRowStart = $sourceRowNumber;
                    $chunkRowCount = 0;
                    $chunkStream = $this->newChunkStream($headers);

                    continue;
                }

                $sourceRowNumber++;
            }

            if ($chunkRowCount > 0 && \is_resource($chunkStream)) {
                $this->storeChunk(
                    $importRun,
                    $chunkIndex,
                    $chunkRowStart,
                    $chunkRowCount,
                    $chunkStream,
                );

                $chunkCount++;
            }

            return $chunkCount;
        } finally {
            if (\is_resource($chunkStream)) {
                fclose($chunkStream);
            }

            fclose($sourceStream);
        }
    }

    /**
     * @param list<string> $headers
     * @return resource
     */
    private function newChunkStream(array $headers)
    {
        $stream = fopen('php://temp/maxmemory:5242880', 'w+b');

        if (! \is_resource($stream)) {
            throw new RuntimeException('Could not create a temporary chunk stream.');
        }

        if (fputcsv($stream, $headers, ',', '"', '') === false) {
            fclose($stream);

            throw new RuntimeException('Could not write the chunk CSV header.');
        }

        return $stream;
    }

    /**
     * @param resource $stream
     */
    private function storeChunk(
        ImportRun $importRun,
        int $chunkIndex,
        int $rowStart,
        int $rowCount,
        $stream,
    ): void {
        $chunkId = 'chunk-'.str_pad((string) $chunkIndex, 6, '0', STR_PAD_LEFT);
        $objectKey = "imports/{$importRun->import_id}/chunks/{$chunkId}.csv";

        rewind($stream);

        $stored = Storage::disk(config('filesystems.default'))->writeStream(
            $objectKey,
            $stream,
            ['visibility' => 'private'],
        );

        if (! $stored) {
            throw new RuntimeException("Could not store chunk object [{$objectKey}].");
        }

        DB::transaction(function () use (
            $importRun,
            $chunkId,
            $objectKey,
            $rowStart,
            $rowCount,
        ): void {
            ImportChunk::query()->firstOrCreate(
                [
                    'import_run_id' => $importRun->id,
                    'chunk_id' => $chunkId,
                ],
                [
                    'status' => 'pending',
                    'object_key' => $objectKey,
                    'row_start' => $rowStart,
                    'row_count' => $rowCount,
                    'attempts' => 0,
                ],
            );
        
            $message = new OrderChunkRequested(
                importId: $importRun->import_id,
                chunkId: $chunkId,
                objectKey: $objectKey,
                rowStart: $rowStart,
                rowCount: $rowCount,
            );
        
            $messageId = 'bulk-imports:orders-chunk:'.hash(
                'sha256',
                $importRun->import_id.':'.$chunkId,
            );
        
            $envelope = new MessageEnvelope(
                messageId: $messageId,
                correlationId: $importRun->import_id,
                occurredAt: new DateTimeImmutable(),
                message: $message,
            );
        
            BulkImportsOutboxMessage::query()->firstOrCreate(
                ['message_id' => $messageId],
                [
                    'message_type' => $message->messageType(),
                    'correlation_id' => $importRun->import_id,
                    'exchange_name' => 'bulk-processing.commands',
                    'routing_key' => $message->messageType(),
                    'payload' => $envelope->toArray(),
                    'status' => 'pending',
                    'attempts' => 0,
                    'available_at' => now(),
                ],
            );
        });
    }
}