<?php

namespace App\Filament\Resources\QueryRecords\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class QueryRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Query')
                    ->columns(2)
                    ->components([
                        TextEntry::make('sql_query')
                            ->label('SQL')
                            ->columnSpanFull()
                            ->fontFamily('mono'),
                        TextEntry::make('connection'),
                        TextEntry::make('connection_type'),
                        TextEntry::make('duration')->label('Duration (ms)'),
                        TextEntry::make('file'),
                        TextEntry::make('line'),
                        TextEntry::make('execution_source')->label('Source'),
                        TextEntry::make('user_id'),
                        TextEntry::make('created_at')->dateTime(),
                    ]),
            ]);
    }
}
