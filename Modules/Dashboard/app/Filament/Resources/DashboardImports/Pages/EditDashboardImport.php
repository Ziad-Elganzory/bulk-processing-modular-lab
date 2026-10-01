<?php

namespace Modules\Dashboard\Filament\Resources\DashboardImports\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Dashboard\Filament\Resources\DashboardImports\DashboardImportResource;

class EditDashboardImport extends EditRecord
{
    protected static string $resource = DashboardImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
