<?php

namespace App\Filament\Resources\OutgoingRequests\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OutgoingRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Outgoing request')
                    ->columns(2)
                    ->components([
                        TextEntry::make('method'),
                        TextEntry::make('status_code')->badge(),
                        TextEntry::make('url')->columnSpanFull(),
                        TextEntry::make('host'),
                        TextEntry::make('duration')->label('Duration (ms)'),
                        TextEntry::make('request_size'),
                        TextEntry::make('response_size'),
                        TextEntry::make('created_at')->dateTime(),
                    ]),
                Section::make('Request headers')
                    ->collapsed()
                    ->components([
                        TextEntry::make('request_headers')->columnSpanFull(),
                    ]),
            ]);
    }
}
