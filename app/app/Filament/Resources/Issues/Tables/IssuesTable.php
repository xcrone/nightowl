<?php

namespace App\Filament\Resources\Issues\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class IssuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'danger',
                        'resolved' => 'success',
                        'ignored' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('priority')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('exception_class')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('exception_message')
                    ->limit(80)
                    ->searchable(),
                TextColumn::make('occurrences_count')
                    ->label('Occurrences')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('users_count')
                    ->label('Users affected')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('first_seen_at')
                    ->dateTime()
                    ->toggleable(),
                TextColumn::make('last_seen_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('assigned_to')
                    ->toggleable(),
            ])
            ->defaultSort('last_seen_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'resolved' => 'Resolved',
                        'ignored' => 'Ignored',
                    ]),
                SelectFilter::make('type')
                    ->options([
                        'exception' => 'Exception',
                        'performance' => 'Performance',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
