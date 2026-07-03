<?php

namespace App\Filament\Resources\CacheEvents\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CacheEventInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cache event')
                    ->columns(2)
                    ->components([
                        TextEntry::make('event_type')->badge(),
                        TextEntry::make('key')->columnSpanFull(),
                        TextEntry::make('store'),
                        TextEntry::make('ttl'),
                        TextEntry::make('duration')->label('Duration (ms)'),
                        TextEntry::make('execution_source')->label('Source'),
                        TextEntry::make('created_at')->dateTime(),
                    ]),
            ]);
    }
}
