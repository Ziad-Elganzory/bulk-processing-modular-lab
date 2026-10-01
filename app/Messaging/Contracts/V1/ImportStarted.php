<?php

namespace App\Messaging\Contracts\V1;

use App\Messaging\Contracts\MessageContract;
use App\Messaging\Contracts\MessageData;

final readonly class ImportStarted implements MessageContract
{
    public const TYPE = 'bulk-import.started.v1';

    public function __construct(
        public string $importId,
    ) {
        MessageData::assertNonEmptyString($this->importId, 'import_id');
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
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromData(array $data): static
    {
        return new self(
            importId: MessageData::requiredString($data, 'import_id'),
        );
    }
}
