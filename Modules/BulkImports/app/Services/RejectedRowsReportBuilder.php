<?php

namespace Modules\BulkImports\Services;

use Illuminate\Support\Facades\Storage;
use Modules\BulkImports\Models\ImportRun;
use RuntimeException;

class RejectedRowsReportBuilder
{
    public function build(ImportRun $importRun): ?string
    {
        $chunks = $importRun->chunks()
            ->where('rejected_rows', '>', 0)
            ->orderBy('row_start')
            ->get(['rejected_object_key']);

        if ($chunks->isEmpty()) {
            return null;
        }

        $disk = Storage::disk(config('filesystems.default'));
        $reportStream = fopen('php://temp/maxmemory:5242880', 'w+b');

        if (! \is_resource($reportStream)) {
            throw new RuntimeException('Could not create the rejected-row report stream.');
        }

        $reportHeaders = null;

        try {
            foreach ($chunks as $chunk) {
                if (! is_string($chunk->rejected_object_key) || $chunk->rejected_object_key === '') {
                    throw new RuntimeException('A rejected chunk is missing its report object key.');
                }

                $chunkStream = $disk->readStream($chunk->rejected_object_key);

                if (! \is_resource($chunkStream)) {
                    throw new RuntimeException(
                        "Could not read rejected-row object [{$chunk->rejected_object_key}].",
                    );
                }

                try {
                    $chunkHeaders = fgetcsv($chunkStream);

                    if ($chunkHeaders === false) {
                        throw new RuntimeException(
                            "Rejected-row object [{$chunk->rejected_object_key}] is empty.",
                        );
                    }

                    if ($reportHeaders === null) {
                        $reportHeaders = $chunkHeaders;

                        if (fputcsv($reportStream, $reportHeaders, ',', '"', '') === false) {
                            throw new RuntimeException('Could not write the rejected-row report header.');
                        }
                    } elseif ($chunkHeaders !== $reportHeaders) {
                        throw new RuntimeException('Rejected chunk reports have inconsistent headers.');
                    }

                    while (($row = fgetcsv($chunkStream)) !== false) {
                        if (count($row) !== count($reportHeaders)) {
                            throw new RuntimeException(
                                "Rejected-row object [{$chunk->rejected_object_key}] has an invalid row.",
                            );
                        }

                        if (fputcsv($reportStream, $row, ',', '"', '') === false) {
                            throw new RuntimeException('Could not write a rejected row to the report.');
                        }
                    }
                } finally {
                    fclose($chunkStream);
                }
            }

            rewind($reportStream);

            $reportObjectKey = "imports/{$importRun->import_id}/reports/rejected.csv";
            $stored = $disk->writeStream(
                $reportObjectKey,
                $reportStream,
                ['visibility' => 'private'],
            );

            if (! $stored) {
                throw new RuntimeException(
                    "Could not store rejected-row report [{$reportObjectKey}].",
                );
            }

            return $reportObjectKey;
        } finally {
            fclose($reportStream);
        }
    }
}
