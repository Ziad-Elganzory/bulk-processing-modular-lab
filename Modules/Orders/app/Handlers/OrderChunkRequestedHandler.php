<?php

namespace Modules\Orders\Handlers;

use App\Messaging\Contracts\MessageEnvelope;
use App\Messaging\Contracts\V1\OrderChunkRequested;
use InvalidArgumentException;
use Modules\Orders\Services\OrderChunkCommitter;
use Modules\Orders\Services\OrderChunkProcessor;

class OrderChunkRequestedHandler
{
    public function __construct(
        private readonly OrderChunkProcessor $processor,
        private readonly OrderChunkCommitter $committer,
    ) {}

    public function handle(MessageEnvelope $envelope): void
    {
        if (! $envelope->message instanceof OrderChunkRequested) {
            throw new InvalidArgumentException(
                "Orders handler cannot process message type [{$envelope->message->messageType()}].",
            );
        }

        $chunk = $envelope->message;
        $result = $this->processor->validateRows($chunk);
        $files = $this->processor->writeResultFiles($chunk, $result);

        $this->committer->commit(
            incomingEnvelope: $envelope,
            chunk: $chunk,
            result: $result,
            files: $files,
        );
    }
}
