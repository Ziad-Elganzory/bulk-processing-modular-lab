<?php

namespace Modules\Dashboard\Filament\Resources\DashboardImports\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Dashboard\Models\DashboardImport;

class DashboardImportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Import')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('import_id')
                            ->label('Import ID')
                            ->copyable(),

                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'queued' => 'gray',
                                'processing' => 'warning',
                                'completed' => 'success',
                                'completed_with_errors' => 'warning',
                                'failed' => 'danger',
                                default => 'gray',
                            }),

                        TextEntry::make('requested_by')
                            ->label('Requested by'),

                        TextEntry::make('file_name')
                            ->label('File'),

                        TextEntry::make('file_size_bytes')
                            ->label('File size in bytes')
                            ->numeric(decimalPlaces: 0),

                        TextEntry::make('source_object_key')
                            ->label('Stored file')
                            ->limit(70)
                            ->copyable(),
                    ]),

                Section::make('Progress')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('overall_progress')
                            ->label('Overall progress')
                            ->state(function (DashboardImport $record): string {
                                if (! $record->total_chunks) {
                                    return match ($record->status) {
                                        'queued' => 'Waiting to start',
                                        'failed' => 'Failed before chunk processing',
                                        default => 'Preparing chunks',
                                    };
                                }

                                $percentage = min(
                                    100,
                                    (int) floor(
                                        ($record->processed_chunks / $record->total_chunks) * 100
                                    )
                                );

                                return "{$percentage}% ({$record->processed_chunks}/{$record->total_chunks} chunks)";
                            }),

                        TextEntry::make('total_rows')
                            ->label('Total rows')
                            ->numeric(decimalPlaces: 0),

                        TextEntry::make('processed_rows')
                            ->label('Processed rows')
                            ->numeric(decimalPlaces: 0),

                        TextEntry::make('accepted_rows')
                            ->label('Accepted rows')
                            ->numeric(decimalPlaces: 0),

                        TextEntry::make('rejected_rows')
                            ->label('Rejected rows')
                            ->numeric(decimalPlaces: 0),

                        TextEntry::make('processed_chunks')
                            ->label('Processed chunks')
                            ->numeric(decimalPlaces: 0),

                        TextEntry::make('total_chunks')
                            ->label('Total chunks')
                            ->numeric(decimalPlaces: 0),

                        TextEntry::make('started_at')
                            ->dateTime(),

                        TextEntry::make('finished_at')
                            ->dateTime(),
                    ]),

                Section::make('Errors and rejected rows')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('failure_code')
                            ->placeholder('—'),

                        TextEntry::make('failure_message')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('rejected_report_object_key')
                            ->label('Rejected rows report')
                            ->placeholder('—')
                            ->copyable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}