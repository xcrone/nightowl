<?php

namespace App\Filament\Resources\LogRecords\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LogRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Log')
                    ->columns(2)
                    ->components([
                        TextEntry::make('level')->badge(),
                        TextEntry::make('channel'),
                        TextEntry::make('message')->columnSpanFull(),
                        TextEntry::make('execution_source')->label('Source'),
                        TextEntry::make('created_at')->dateTime(),
                    ]),
                Section::make('Context')
                    ->collapsed()
                    ->components([
                        TextEntry::make('context')->columnSpanFull(),
                        TextEntry::make('extra')->columnSpanFull(),
                    ]),
            ]);
    }
}
