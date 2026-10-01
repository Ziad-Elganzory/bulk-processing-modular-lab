<?php

namespace Modules\Dashboard\Filament\Resources\DashboardImports;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Dashboard\Filament\Resources\DashboardImports\Pages\CreateDashboardImport;
use Modules\Dashboard\Filament\Resources\DashboardImports\Pages\ListDashboardImports;
use Modules\Dashboard\Filament\Resources\DashboardImports\Pages\ViewDashboardImport;
use Modules\Dashboard\Filament\Resources\DashboardImports\RelationManagers\ChunksRelationManager;
use Modules\Dashboard\Filament\Resources\DashboardImports\Schemas\DashboardImportForm;
use Modules\Dashboard\Filament\Resources\DashboardImports\Schemas\DashboardImportInfolist;
use Modules\Dashboard\Filament\Resources\DashboardImports\Tables\DashboardImportsTable;
use Modules\Dashboard\Models\DashboardImport;

class DashboardImportResource extends Resource
{
    protected static ?string $model = DashboardImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'import_id';

    public static function form(Schema $schema): Schema
    {
        return DashboardImportForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DashboardImportInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DashboardImportsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ChunksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDashboardImports::route('/'),
            'create' => CreateDashboardImport::route('/create'),
            'view' => ViewDashboardImport::route('/{record}'),
        ];
    }
}
