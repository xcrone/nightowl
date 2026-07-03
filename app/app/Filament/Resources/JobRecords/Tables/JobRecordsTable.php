<?php

namespace App\Filament\Resources\JobRecords\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JobRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('job_class')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('queue')
                    ->toggleable(),
                TextColumn::make('connection')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'failed' => 'danger',
                        'processed' => 'success',
                        'released' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('attempts')
                    ->toggleable(),
                TextColumn::make('duration')
                    ->label('Duration (ms)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('exceptions')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'queued' => 'Queued',
                        'processed' => 'Processed',
                        'released' => 'Released',
                        'failed' => 'Failed',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
