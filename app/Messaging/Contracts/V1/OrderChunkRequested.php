<?php

namespace App\Messaging\Contracts\V1;

use App\Messaging\Contracts\MessageContract;
use App\Messaging\Contracts\MessageData;

final readonly class OrderChunkRequested implements MessageContract
{
    public const TYPE = 'orders.chunk.requested.v1';

    public function __construct(
        public string $importId,
        public string $chunkId,
        public string $objectKey,
        public int $rowStart,
        public int $rowCount,
        public string $checksum,
    ) {
        MessageData::assertNonEmptyString($this->importId, 'import_id');
        MessageData::assertNonEmptyString($this->chunkId, 'chunk_id');
        MessageData::assertNonEmptyString($this->objectKey, 'object_key');
        MessageData::assertInteger($this->rowStart, 'row_start', minimum: 1);
        MessageData::assertInteger($this->rowCount, 'row_count', minimum: 1);
        MessageData::assertNonEmptyString($this->checksum, 'checksum');
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
            'object_key' => $this->objectKey,
            'row_start' => $this->rowStart,
            'row_count' => $this->rowCount,
            'checksum' => $this->checksum,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromData(array $data): static
    {
        return new self(
            importId: MessageData::requiredString($data, 'import_id'),
            chunkId: MessageData::requiredString($data, 'chunk_id'),
            objectKey: MessageData::requiredString($data, 'object_key'),
            rowStart: MessageData::requiredInt($data, 'row_start', minimum: 1),
            rowCount: MessageData::requiredInt($data, 'row_count', minimum: 1),
            checksum: MessageData::requiredString($data, 'checksum'),
        );
    }
}
