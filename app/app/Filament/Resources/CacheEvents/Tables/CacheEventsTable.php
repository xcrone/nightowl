<?php

namespace App\Filament\Resources\CacheEvents\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CacheEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('event_type')
                    ->badge(),
                TextColumn::make('key')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('store')
                    ->toggleable(),
                TextColumn::make('ttl')
                    ->toggleable(),
                TextColumn::make('duration')
                    ->label('Duration (ms)')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('event_type')
                    ->options([
                        'hit' => 'Hit',
                        'missed' => 'Missed',
                        'write' => 'Write',
                        'forget' => 'Forget',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
