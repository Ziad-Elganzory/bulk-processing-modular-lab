<?php

namespace App\Messaging\Contracts\V1;

use App\Messaging\Contracts\MessageContract;
use App\Messaging\Contracts\MessageData;
use InvalidArgumentException;

final readonly class ImportCompleted implements MessageContract
{
    public const TYPE = 'bulk-import.completed.v1';

    public function __construct(
        public string $importId,
        public string $status,
        public int $totalRows,
        public int $processedRows,
        public int $acceptedRows,
        public int $rejectedRows,
        public ?string $rejectedReportObjectKey,
    ) {
        MessageData::assertNonEmptyString($this->importId, 'import_id');

        if (! in_array($this->status, ['completed', 'completed_with_errors', 'failed', 'cancelled'], true)) {
            throw new InvalidArgumentException('The [status] field has an unsupported value.');
        }

        MessageData::assertInteger($this->totalRows, 'total_rows');
        MessageData::assertInteger($this->processedRows, 'processed_rows');
        MessageData::assertInteger($this->acceptedRows, 'accepted_rows');
        MessageData::assertInteger($this->rejectedRows, 'rejected_rows');
        MessageData::assertNullableString($this->rejectedReportObjectKey, 'rejected_report_object_key');

        if ($this->processedRows > $this->totalRows) {
            throw new InvalidArgumentException('Processed rows cannot exceed total rows.');
        }

        if ($this->acceptedRows + $this->rejectedRows !== $this->processedRows) {
            throw new InvalidArgumentException('Accepted and rejected rows must equal processed rows.');
        }

        if ($this->rejectedRows > 0 && $this->rejectedReportObjectKey === null) {
            throw new InvalidArgumentException('Rejected rows require a rejected report object key.');
        }
        if (
            in_array($this->status, ['completed', 'completed_with_errors'], true)
            && $this->processedRows !== $this->totalRows
        ) {
            throw new InvalidArgumentException('A completed import must process all rows.');
        }

        if ($this->status === 'completed' && $this->rejectedRows > 0) {
            throw new InvalidArgumentException('A completed import cannot have rejected rows.');
        }

        if ($this->status === 'completed_with_errors' && $this->rejectedRows === 0) {
            throw new InvalidArgumentException('An import completed with errors must have rejected rows.');
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
            'status' => $this->status,
            'total_rows' => $this->totalRows,
            'processed_rows' => $this->processedRows,
            'accepted_rows' => $this->acceptedRows,
            'rejected_rows' => $this->rejectedRows,
            'rejected_report_object_key' => $this->rejectedReportObjectKey,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromData(array $data): static
    {
        $status = MessageData::requiredString($data, 'status');

        return new self(
            importId: MessageData::requiredString($data, 'import_id'),
            status: $status,
            totalRows: MessageData::requiredInt($data, 'total_rows'),
            processedRows: MessageData::requiredInt($data, 'processed_rows'),
            acceptedRows: MessageData::requiredInt($data, 'accepted_rows'),
            rejectedRows: MessageData::requiredInt($data, 'rejected_rows'),
            rejectedReportObjectKey: MessageData::nullableString($data, 'rejected_report_object_key'),
        );
    }
}
