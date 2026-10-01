<?php

namespace Modules\Dashboard\Filament\Resources\DashboardImports\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Dashboard\Filament\Resources\DashboardImports\DashboardImportResource;

class ListDashboardImports extends ListRecords
{
    protected static string $resource = DashboardImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
