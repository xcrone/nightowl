<?php

namespace App\Filament\Resources\ExceptionRecords\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExceptionRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('class')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('message')
                    ->limit(80)
                    ->searchable(),
                TextColumn::make('file')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('line')
                    ->toggleable(),
                IconColumn::make('handled')
                    ->boolean(),
                TextColumn::make('execution_source')
                    ->label('Source')
                    ->toggleable(),
                TextColumn::make('fingerprint')
                    ->limit(12)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('handled')
                    ->options([1 => 'Handled', 0 => 'Unhandled']),
                Filter::make('unhandled_only')
                    ->label('Unhandled only')
                    ->query(fn (Builder $query): Builder => $query->where('handled', false)),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
