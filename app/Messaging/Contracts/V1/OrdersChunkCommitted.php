<?php

namespace App\Messaging\Contracts\V1;

use App\Messaging\Contracts\MessageContract;
use App\Messaging\Contracts\MessageData;

final readonly class OrdersChunkCommitted implements MessageContract
{
    public const TYPE = 'orders.chunk.committed.v1';

    public function __construct(
        public string $importId,
        public string $chunkId,
        public int $acceptedRows,
        public int $rejectedRows,
        public ?string $acceptedObjectKey,
        public ?string $rejectedObjectKey,
    ) {
        MessageData::assertNonEmptyString($this->importId, 'import_id');
        MessageData::assertNonEmptyString($this->chunkId, 'chunk_id');
        MessageData::assertInteger($this->acceptedRows, 'accepted_rows');
        MessageData::assertInteger($this->rejectedRows, 'rejected_rows');
        MessageData::assertNullableString($this->acceptedObjectKey, 'accepted_object_key');
        MessageData::assertNullableString($this->rejectedObjectKey, 'rejected_object_key');

        if ($this->acceptedRows > 0 && $this->acceptedObjectKey === null) {
            throw new \InvalidArgumentException('Accepted rows require an accepted object key.');
        }

        if ($this->rejectedRows > 0 && $this->rejectedObjectKey === null) {
            throw new \InvalidArgumentException('Rejected rows require a rejected object key.');
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
            'chunk_id' => $this->chunkId,
            'accepted_rows' => $this->acceptedRows,
            'rejected_rows' => $this->rejectedRows,
            'accepted_object_key' => $this->acceptedObjectKey,
            'rejected_object_key' => $this->rejectedObjectKey,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromData(array $data): static
    {
        return new self(
            importId: MessageData::requiredString($data, 'import_id'),
            chunkId: MessageData::requiredString($data, 'chunk_id'),
            acceptedRows: MessageData::requiredInt($data, 'accepted_rows'),
            rejectedRows: MessageData::requiredInt($data, 'rejected_rows'),
            acceptedObjectKey: MessageData::nullableString($data, 'accepted_object_key'),
            rejectedObjectKey: MessageData::nullableString($data, 'rejected_object_key'),
        );
    }
}
