<?php

namespace App\Filament\Resources\RequestRecords\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RequestRecordsTable
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
                TextColumn::make('route_name')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status_code')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 500 => 'danger',
                        $state >= 400 => 'warning',
                        default => 'success',
                    })
                    ->sortable(),
                TextColumn::make('duration')
                    ->label('Duration (ms)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('user_id')
                    ->toggleable(),
                TextColumn::make('exceptions')
                    ->label('Exc.')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('queries')
                    ->label('Queries')
                    ->toggleable(),
                TextColumn::make('peak_memory_usage')
                    ->label('Peak memory')
                    ->formatStateUsing(fn (?int $state): string => $state ? number_format($state / 1024 / 1024, 1).' MB' : '-')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status_code')
                    ->options([
                        200 => '200 OK',
                        302 => '302 Redirect',
                        404 => '404 Not Found',
                        500 => '500 Server Error',
                    ]),
                Filter::make('failed')
                    ->label('Status >= 500')
                    ->query(fn (Builder $query): Builder => $query->where('status_code', '>=', 500)),
                Filter::make('slow')
                    ->label('Slow (> 1000ms)')
                    ->query(fn (Builder $query): Builder => $query->where('duration', '>', 1000)),
                Filter::make('has_exceptions')
                    ->label('Has exceptions')
                    ->query(fn (Builder $query): Builder => $query->where('exceptions', '>', 0)),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
