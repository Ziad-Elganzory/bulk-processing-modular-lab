<?php

namespace Modules\Dashboard\Filament\Resources\DashboardImports\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Dashboard\Filament\Resources\DashboardImports\DashboardImportResource;

class CreateDashboardImport extends CreateRecord
{
    protected static string $resource = DashboardImportResource::class;
}
