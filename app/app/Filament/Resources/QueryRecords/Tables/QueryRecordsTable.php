<?php

namespace App\Filament\Resources\QueryRecords\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QueryRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('sql_query')
                    ->label('SQL')
                    ->limit(80)
                    ->searchable()
                    ->fontFamily('mono'),
                TextColumn::make('connection')
                    ->toggleable(),
                TextColumn::make('duration')
                    ->label('Duration (ms)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('file')
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('line')
                    ->toggleable(),
                TextColumn::make('execution_source')
                    ->label('Source')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('slow')
                    ->label('Slow (> 100ms)')
                    ->query(fn (Builder $query): Builder => $query->where('duration', '>', 100)),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
