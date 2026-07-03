<?php

namespace App\Filament\Resources\NotificationRecords\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NotificationRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('notification')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('channel')
                    ->badge(),
                TextColumn::make('notifiable_type')
                    ->toggleable(),
                TextColumn::make('notifiable_id')
                    ->toggleable(),
                IconColumn::make('failed')
                    ->boolean()
                    ->color(fn (bool $state): string => $state ? 'danger' : 'success'),
                IconColumn::make('queued')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('duration')
                    ->label('Duration (ms)')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
