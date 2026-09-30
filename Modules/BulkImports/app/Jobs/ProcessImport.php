<?php

namespace Modules\BulkImports\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\BulkImports\Services\ImportCsvChunker;

class ProcessImport implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $importRunId,
    ) {}

    public function handle(ImportCsvChunker $chunker): void
    {
        $chunker->process($this->importRunId);
    }
}
