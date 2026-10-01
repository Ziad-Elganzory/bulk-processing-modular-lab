<?php

namespace Modules\Dashboard\Filament\Resources\DashboardImports\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChunksRelationManager extends RelationManager
{
    protected static string $relationship = 'chunks';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('chunk_id')
            ->columns([
                TextColumn::make('chunk_id')
                    ->label('Chunk')
                    ->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'processing' => 'warning',
                        'completed' => 'success',
                        'completed_with_errors' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('row_start')
                    ->label('Starting row')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('row_count')
                    ->label('Rows')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('accepted_rows')
                    ->label('Accepted')
                    ->numeric(),

                TextColumn::make('rejected_rows')
                    ->label('Rejected')
                    ->numeric(),

                TextColumn::make('attempts')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('failure_code')
                    ->searchable(),

                TextColumn::make('failure_message')
                    ->limit(80),

                TextColumn::make('finished_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('chunk_id')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}