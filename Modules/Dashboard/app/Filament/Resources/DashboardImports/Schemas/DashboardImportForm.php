<?php

namespace Modules\Dashboard\Filament\Resources\DashboardImports\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;

class DashboardImportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('source_object_key')
                    ->label('Orders CSV file')
                    ->disk('s3')
                    ->directory('imports/source-files')
                    ->visibility('private')
                    ->acceptedFileTypes([
                        'text/csv',
                        'text/plain',
                        'application/vnd.ms-excel',
                    ])
                    ->rules(['extensions:csv'])
                    ->storeFileNamesIn('file_name')
                    ->maxSize(102400)
                    ->required(),
            ]);
    }
}
