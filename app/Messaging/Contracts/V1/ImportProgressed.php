<?php

namespace App\Messaging\Contracts\V1;

use App\Messaging\Contracts\MessageContract;
use App\Messaging\Contracts\MessageData;

final readonly class ImportProgressed implements MessageContract
{
    public const TYPE = 'bulk-import.progressed.v1';

    public function __construct(
        public string $importId,
        public int $processedChunks,
        public int $totalChunks,
        public int $processedRows,
        public int $totalRows,
        public int $acceptedRows,
        public int $rejectedRows,
    ) {
        MessageData::assertNonEmptyString($this->importId, 'import_id');
        MessageData::assertInteger($this->processedChunks, 'processed_chunks');
        MessageData::assertInteger($this->totalChunks, 'total_chunks');
        MessageData::assertInteger($this->processedRows, 'processed_rows');
        MessageData::assertInteger($this->totalRows, 'total_rows');
        MessageData::assertInteger($this->acceptedRows, 'accepted_rows');
        MessageData::assertInteger($this->rejectedRows, 'rejected_rows');

        if ($this->processedChunks > $this->totalChunks) {
            throw new \InvalidArgumentException('Processed chunks cannot exceed total chunks.');
        }

        if ($this->processedRows > $this->totalRows) {
            throw new \InvalidArgumentException('Processed rows cannot exceed total rows.');
        }

        if ($this->acceptedRows + $this->rejectedRows !== $this->processedRows) {
            throw new \InvalidArgumentException('Accepted and rejected rows must equal processed rows.');
        }
    }

    public function messageType(): string
    {
        return self::TYPE;
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return [
            'import_id' => $this->importId,
            'processed_chunks' => $this->processedChunks,
            'total_chunks' => $this->totalChunks,
            'processed_rows' => $this->processedRows,
            'total_rows' => $this->totalRows,
            'accepted_rows' => $this->acceptedRows,
            'rejected_rows' => $this->rejectedRows,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromData(array $data): static
    {
        return new self(
            importId: MessageData::requiredString($data, 'import_id'),
            processedChunks: MessageData::requiredInt($data, 'processed_chunks'),
            totalChunks: MessageData::requiredInt($data, 'total_chunks'),
            processedRows: MessageData::requiredInt($data, 'processed_rows'),
            totalRows: MessageData::requiredInt($data, 'total_rows'),
            acceptedRows: MessageData::requiredInt($data, 'accepted_rows'),
            rejectedRows: MessageData::requiredInt($data, 'rejected_rows'),
        );
    }
}
