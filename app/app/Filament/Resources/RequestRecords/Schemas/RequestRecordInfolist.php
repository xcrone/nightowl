<?php

namespace App\Filament\Resources\RequestRecords\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RequestRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request')
                    ->columns(2)
                    ->components([
                        TextEntry::make('method'),
                        TextEntry::make('status_code')->badge(),
                        TextEntry::make('url')->columnSpanFull(),
                        TextEntry::make('route_name'),
                        TextEntry::make('route_action'),
                        TextEntry::make('ip'),
                        TextEntry::make('user_id'),
                        TextEntry::make('duration')->label('Duration (ms)'),
                        TextEntry::make('created_at')->dateTime(),
                    ]),
                Section::make('Timing breakdown (microseconds)')
                    ->columns(4)
                    ->components([
                        TextEntry::make('bootstrap'),
                        TextEntry::make('before_middleware'),
                        TextEntry::make('action'),
                        TextEntry::make('render'),
                        TextEntry::make('after_middleware'),
                        TextEntry::make('sending'),
                        TextEntry::make('terminating'),
                    ]),
                Section::make('Child events')
                    ->columns(4)
                    ->components([
                        TextEntry::make('queries'),
                        TextEntry::make('exceptions'),
                        TextEntry::make('logs'),
                        TextEntry::make('cache_events'),
                        TextEntry::make('jobs_queued'),
                        TextEntry::make('mail'),
                        TextEntry::make('notifications'),
                        TextEntry::make('outgoing_requests'),
                    ]),
                Section::make('Payload & context')
                    ->collapsed()
                    ->components([
                        TextEntry::make('headers')->columnSpanFull(),
                        TextEntry::make('payload')->columnSpanFull(),
                        TextEntry::make('context')->columnSpanFull(),
                        TextEntry::make('exception_preview')->columnSpanFull(),
                    ]),
            ]);
    }
}
