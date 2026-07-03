<?php

namespace App\Filament\Resources\OutgoingRequests\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OutgoingRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('method')
                    ->badge(),
                TextColumn::make('url')
                    ->limit(60)
                    ->searchable(),
                TextColumn::make('host')
                    ->toggleable(),
                TextColumn::make('status_code')
                    ->badge()
                    ->color(fn (?int $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 500 => 'danger',
                        $state >= 400 => 'warning',
                        default => 'success',
                    })
                    ->sortable(),
                TextColumn::make('duration')
                    ->label('Duration (ms)')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('failed')
                    ->label('Status >= 400')
                    ->query(fn (Builder $query): Builder => $query->where('status_code', '>=', 400)),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
