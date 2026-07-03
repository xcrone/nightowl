<?php

namespace App\Filament\Resources\CommandRecords\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommandRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('command')
                    ->searchable(),
                TextColumn::make('exit_code')
                    ->badge()
                    ->color(fn (?int $state): string => $state === 0 ? 'success' : 'danger'),
                TextColumn::make('duration')
                    ->label('Duration (ms)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('exceptions')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('queries')
                    ->toggleable(),
                TextColumn::make('class')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
