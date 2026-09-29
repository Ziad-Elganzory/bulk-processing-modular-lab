<?php

namespace Modules\Orders\Services;

use App\Messaging\Contracts\V1\OrderChunkRequested;
use Generator;
use Illuminate\Support\Facades\Storage;
use Modules\Orders\Models\Order;
use RuntimeException;

class OrderChunkProcessor
{
    public function __construct(
        private readonly OrderRowValidator $rowValidator,
    ) {}

    /**
     * @return Generator<int, array{source_row_number: int, row: array<string, ?string>}>
     */
    private function readRows(OrderChunkRequested $chunk): Generator
    {
        $stream = Storage::disk(config('filesystems.default'))
            ->readStream($chunk->objectKey);

        if (! \is_resource($stream)) {
            throw new RuntimeException("Could not read chunk [{$chunk->objectKey}].");
        }

        try {
            $headers = fgetcsv($stream);

            if ($headers === false) {
                throw new RuntimeException('The chunk CSV is empty.');
            }

            $headers = array_map(
                static fn (?string $header): string => trim((string) $header),
                $headers,
            );

            if (isset($headers[0])) {
                $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]) ?? $headers[0];
            }

            $expectedHeaders = [
                'order_id',
                'customer_id',
                'amount',
                'currency',
                'order_date',
            ];

            if ($headers !== $expectedHeaders) {
                throw new RuntimeException('The chunk CSV headers do not match the expected format.');
            }

            $sourceRowNumber = $chunk->rowStart;
            $readRowCount = 0;

            while (($values = fgetcsv($stream)) !== false) {
                if (\count($values) !== \count($headers)) {
                    throw new RuntimeException(
                        "CSV row {$sourceRowNumber} has an unexpected number of columns.",
                    );
                }

                $row = array_combine($headers, $values);

                if ($row === false) {
                    throw new RuntimeException("Could not map CSV row {$sourceRowNumber}.");
                }

                yield [
                    'source_row_number' => $sourceRowNumber,
                    'row' => array_map(
                        static fn (?string $value): ?string => $value === null ? null : trim($value),
                        $row,
                    ),
                ];

                $sourceRowNumber++;
                $readRowCount++;
            }

            if ($readRowCount !== $chunk->rowCount) {
                throw new RuntimeException(
                    "Expected {$chunk->rowCount} data rows, but read {$readRowCount}.",
                );
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return array{
     *     accepted: list<array{source_row_number: int, attributes: array<string, mixed>}>,
     *     rejected: list<array{
     *         source_row_number: int,
     *         row: array<string, ?string>,
     *         errors: array<string, list<string>>
     *     }>
     * }
     */
    public function validateRows(OrderChunkRequested $chunk): array
    {
        $accepted = [];
        $rejected = [];
        $candidates = [];
        $candidateOrderIds = [];
        $seenOrderIds = [];

        foreach ($this->readRows($chunk) as $record) {
            $result = $this->rowValidator->validate($record['row']);

            if (! $result['valid']) {
                $rejected[] = [
                    'source_row_number' => $record['source_row_number'],
                    'row' => $record['row'],
                    'errors' => $result['errors'],
                ];

                continue;
            }

            $orderId = $result['attributes']['order_id'];

            if (isset($seenOrderIds[$orderId])) {
                $rejected[] = [
                    'source_row_number' => $record['source_row_number'],
                    'row' => $record['row'],
                    'errors' => [
                        'order_id' => ['This order_id appears more than once in the chunk.'],
                    ],
                ];

                continue;
            }

            $seenOrderIds[$orderId] = true;
            $candidateOrderIds[] = $orderId;
            $candidates[] = [
                'source_row_number' => $record['source_row_number'],
                'row' => $record['row'],
                'attributes' => $result['attributes'],
            ];
        }

        $existingOrderIds = $candidateOrderIds === []
            ? []
            : Order::query()
                ->whereIn('order_id', $candidateOrderIds)
                ->pluck('order_id')
                ->all();

        $existingOrderIdSet = array_fill_keys($existingOrderIds, true);

        foreach ($candidates as $candidate) {
            $orderId = $candidate['attributes']['order_id'];

            if (isset($existingOrderIdSet[$orderId])) {
                $rejected[] = [
                    'source_row_number' => $candidate['source_row_number'],
                    'row' => $candidate['row'],
                    'errors' => [
                        'order_id' => ['This order_id has already been imported.'],
                    ],
                ];

                continue;
            }

            $accepted[] = [
                'source_row_number' => $candidate['source_row_number'],
                'attributes' => $candidate['attributes'],
            ];
        }

        return [
            'accepted' => $accepted,
            'rejected' => $rejected,
        ];
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<list<int|string|null>>  $rows
     */
    private function writeCsvObject(string $objectKey, array $headers, iterable $rows): void
    {
        $stream = fopen('php://temp/maxmemory:5242880', 'w+b');

        if (! \is_resource($stream)) {
            throw new RuntimeException('Could not create a temporary CSV stream.');
        }

        try {
            if (fputcsv($stream, $headers, ',', '"', '') === false) {
                throw new RuntimeException('Could not write the CSV header.');
            }

            foreach ($rows as $row) {
                if (fputcsv($stream, $row, ',', '"', '') === false) {
                    throw new RuntimeException('Could not write a CSV row.');
                }
            }

            rewind($stream);

            $stored = Storage::disk(config('filesystems.default'))->writeStream(
                $objectKey,
                $stream,
                ['visibility' => 'private'],
            );

            if (! $stored) {
                throw new RuntimeException("Could not store result object [{$objectKey}].");
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param array{
     *     accepted: list<array{source_row_number: int, attributes: array<string, mixed>}>,
     *     rejected: list<array{
     *         source_row_number: int,
     *         row: array<string, ?string>,
     *         errors: array<string, list<string>>
     *     }>
     * } $result
     * @return array{
     *     accepted_rows: int,
     *     rejected_rows: int,
     *     accepted_object_key: ?string,
     *     rejected_object_key: ?string
     * }
     */
    public function writeResultFiles(OrderChunkRequested $chunk, array $result): array
    {
        $acceptedObjectKey = $result['accepted'] === []
            ? null
            : "imports/{$chunk->importId}/accepted/{$chunk->chunkId}.csv";

        $rejectedObjectKey = $result['rejected'] === []
            ? null
            : "imports/{$chunk->importId}/rejected/{$chunk->chunkId}.csv";

        if ($acceptedObjectKey !== null) {
            $acceptedRows = [];

            foreach ($result['accepted'] as $item) {
                $attributes = $item['attributes'];

                $acceptedRows[] = [
                    $chunk->importId,
                    $chunk->chunkId,
                    $item['source_row_number'],
                    $attributes['order_id'],
                    $attributes['customer_id'],
                    $attributes['amount'],
                    $attributes['currency'],
                    $attributes['order_date'],
                ];
            }

            $this->writeCsvObject(
                $acceptedObjectKey,
                [
                    'import_id',
                    'chunk_id',
                    'source_row_number',
                    'order_id',
                    'customer_id',
                    'amount',
                    'currency',
                    'order_date',
                ],
                $acceptedRows,
            );
        }

        if ($rejectedObjectKey !== null) {
            $rejectedRows = [];

            foreach ($result['rejected'] as $item) {
                $row = $item['row'];

                $rejectedRows[] = [
                    $item['source_row_number'],
                    $row['order_id'] ?? null,
                    $row['customer_id'] ?? null,
                    $row['amount'] ?? null,
                    $row['currency'] ?? null,
                    $row['order_date'] ?? null,
                    json_encode($item['errors'], JSON_THROW_ON_ERROR),
                ];
            }

            $this->writeCsvObject(
                $rejectedObjectKey,
                [
                    'source_row_number',
                    'order_id',
                    'customer_id',
                    'amount',
                    'currency',
                    'order_date',
                    'errors',
                ],
                $rejectedRows,
            );
        }

        return [
            'accepted_rows' => \count($result['accepted']),
            'rejected_rows' => \count($result['rejected']),
            'accepted_object_key' => $acceptedObjectKey,
            'rejected_object_key' => $rejectedObjectKey,
        ];
    }
}
