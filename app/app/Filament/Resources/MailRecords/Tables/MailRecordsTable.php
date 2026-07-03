<?php

namespace App\Filament\Resources\MailRecords\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MailRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('subject')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('mailable')
                    ->toggleable(),
                TextColumn::make('recipients')
                    ->limit(40)
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
