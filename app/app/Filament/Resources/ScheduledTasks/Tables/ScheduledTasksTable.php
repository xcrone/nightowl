<?php

namespace App\Filament\Resources\ScheduledTasks\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ScheduledTasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('command')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('expression')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'failed' => 'danger',
                        'success' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('exit_code')
                    ->toggleable(),
                TextColumn::make('duration')
                    ->label('Duration (ms)')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'success' => 'Success',
                        'failed' => 'Failed',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
