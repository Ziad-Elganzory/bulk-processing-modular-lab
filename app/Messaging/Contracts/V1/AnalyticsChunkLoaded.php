<?php

namespace App\Messaging\Contracts\V1;

use App\Messaging\Contracts\MessageContract;
use App\Messaging\Contracts\MessageData;

final readonly class AnalyticsChunkLoaded implements MessageContract
{
    public const TYPE = 'analytics.chunk.loaded.v1';

    public function __construct(
        public string $importId,
        public string $chunkId,
        public int $loadedRows,
    ) {
        MessageData::assertNonEmptyString($this->importId, 'import_id');
        MessageData::assertNonEmptyString($this->chunkId, 'chunk_id');
        MessageData::assertInteger($this->loadedRows, 'loaded_rows');
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
            'loaded_rows' => $this->loadedRows,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromData(array $data): static
    {
        return new self(
            importId: MessageData::requiredString($data, 'import_id'),
            chunkId: MessageData::requiredString($data, 'chunk_id'),
            loadedRows: MessageData::requiredInt($data, 'loaded_rows'),
        );
    }
}
