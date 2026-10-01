<?php

namespace Modules\Dashboard\Filament\Resources\DashboardImports\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Dashboard\Filament\Resources\DashboardImports\DashboardImportResource;
use Modules\Dashboard\Services\StartDashboardImport;
use RuntimeException;

class CreateDashboardImport extends CreateRecord
{
    protected static string $resource = DashboardImportResource::class;

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        $requestedBy = auth()->id();

        if ($requestedBy === null) {
            throw new RuntimeException('An authenticated user is required to start an import.');
        }

        return app(StartDashboardImport::class)->start(
            sourceObjectKey: $data['source_object_key'],
            fileName: $data['file_name'],
            requestedBy: (string) $requestedBy,
        );
    }
}
