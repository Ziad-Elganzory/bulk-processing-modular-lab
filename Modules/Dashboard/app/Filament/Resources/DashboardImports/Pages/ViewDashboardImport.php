<?php

namespace Modules\Dashboard\Filament\Resources\DashboardImports\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\Dashboard\Filament\Resources\DashboardImports\DashboardImportResource;

class ViewDashboardImport extends ViewRecord
{
    protected static string $resource = DashboardImportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
