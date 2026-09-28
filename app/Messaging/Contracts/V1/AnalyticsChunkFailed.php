<?php

namespace App\Messaging\Contracts\V1;

use App\Messaging\Contracts\MessageContract;
use App\Messaging\Contracts\MessageData;

final readonly class AnalyticsChunkFailed implements MessageContract
{
    public const TYPE = 'analytics.chunk.failed.v1';

    public function __construct(
        public string $importId,
        public string $chunkId,
        public string $failureCode,
        public string $failureMessage,
        public int $attempts,
    ) {
        MessageData::assertNonEmptyString($this->importId, 'import_id');
        MessageData::assertNonEmptyString($this->chunkId, 'chunk_id');
        MessageData::assertNonEmptyString($this->failureCode, 'failure_code');
        MessageData::assertNonEmptyString($this->failureMessage, 'failure_message');
        MessageData::assertInteger($this->attempts, 'attempts', minimum: 1);
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
            'failure_code' => $this->failureCode,
            'failure_message' => $this->failureMessage,
            'attempts' => $this->attempts,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromData(array $data): static
    {
        return new self(
            importId: MessageData::requiredString($data, 'import_id'),
            chunkId: MessageData::requiredString($data, 'chunk_id'),
            failureCode: MessageData::requiredString($data, 'failure_code'),
            failureMessage: MessageData::requiredString($data, 'failure_message'),
            attempts: MessageData::requiredInt($data, 'attempts', minimum: 1),
        );
    }
}
