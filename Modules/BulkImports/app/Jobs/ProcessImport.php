<?php

namespace Modules\BulkImports\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\BulkImports\Services\BeginImportProcessing;
use Modules\BulkImports\Services\ImportCsvChunker;

class ProcessImport implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $importRunId,
    ) {}

    public function handle(
        BeginImportProcessing $beginImportProcessing,
        ImportCsvChunker $chunker,
    ): void {
        if (! $beginImportProcessing->begin($this->importRunId)) {
            return;
        }

        $chunker->process($this->importRunId);
    }
}
