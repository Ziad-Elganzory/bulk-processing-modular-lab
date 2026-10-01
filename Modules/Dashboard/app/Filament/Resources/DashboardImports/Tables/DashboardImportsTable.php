<?php

namespace Modules\Dashboard\Filament\Resources\DashboardImports\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Dashboard\Models\DashboardImport;

class DashboardImportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('import_id')
                    ->label('Import ID')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('file_name')
                    ->label('File')
                    ->searchable()
                    ->limit(40),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'queued' => 'gray',
                        'processing' => 'warning',
                        'completed' => 'success',
                        'completed_with_errors' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('progress')
                    ->label('Chunk progress')
                    ->state(function (DashboardImport $record): string {
                        if (! $record->total_chunks) {
                            return $record->finished_at
                                ? 'Finished before chunking'
                                : 'Preparing CSV';
                        }

                        $percentage = min(
                            100,
                            (int) floor(
                                ($record->processed_chunks / $record->total_chunks) * 100
                            )
                        );

                        return "{$percentage}% ({$record->processed_chunks}/{$record->total_chunks} chunks)";
                    }),

                TextColumn::make('accepted_rows')
                    ->label('Accepted')
                    ->numeric(),

                TextColumn::make('rejected_rows')
                    ->label('Rejected')
                    ->numeric(),

                TextColumn::make('created_at')
                    ->label('Started')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}