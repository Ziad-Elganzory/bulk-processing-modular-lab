<?php

namespace App\Messaging\Contracts\V1;

use App\Messaging\Contracts\MessageContract;
use App\Messaging\Contracts\MessageData;

final readonly class ImportRequested implements MessageContract
{
    public const TYPE = 'bulk-import.requested.v1';

    public function __construct(
        public string $importId,
        public string $sourceObjectKey,
        public string $sourceChecksum,
        public string $requestedBy,
    ) {
        MessageData::assertNonEmptyString($this->importId, 'import_id');
        MessageData::assertNonEmptyString($this->sourceObjectKey, 'source_object_key');
        MessageData::assertNonEmptyString($this->sourceChecksum, 'source_checksum');
        MessageData::assertNonEmptyString($this->requestedBy, 'requested_by');
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
            'source_object_key' => $this->sourceObjectKey,
            'source_checksum' => $this->sourceChecksum,
            'requested_by' => $this->requestedBy,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromData(array $data): static
    {
        return new self(
            importId: MessageData::requiredString($data, 'import_id'),
            sourceObjectKey: MessageData::requiredString($data, 'source_object_key'),
            sourceChecksum: MessageData::requiredString($data, 'source_checksum'),
            requestedBy: MessageData::requiredString($data, 'requested_by'),
        );
    }
}
