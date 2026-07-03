<?php

namespace App\Filament\Resources\LogRecords\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LogRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('level')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'emergency', 'alert', 'critical', 'error' => 'danger',
                        'warning' => 'warning',
                        'notice', 'info' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('message')
                    ->limit(100)
                    ->searchable(),
                TextColumn::make('channel')
                    ->toggleable(),
                TextColumn::make('execution_source')
                    ->label('Source')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('level')
                    ->options([
                        'emergency' => 'Emergency',
                        'alert' => 'Alert',
                        'critical' => 'Critical',
                        'error' => 'Error',
                        'warning' => 'Warning',
                        'notice' => 'Notice',
                        'info' => 'Info',
                        'debug' => 'Debug',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
