<?php

namespace App\Filament\Resources\CommandRecords\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CommandRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Command')
                    ->columns(2)
                    ->components([
                        TextEntry::make('command')->columnSpanFull(),
                        TextEntry::make('class'),
                        TextEntry::make('name'),
                        TextEntry::make('exit_code'),
                        TextEntry::make('duration')->label('Duration (ms)'),
                        TextEntry::make('created_at')->dateTime(),
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
                    ]),
                Section::make('Context')
                    ->collapsed()
                    ->components([
                        TextEntry::make('context')->columnSpanFull(),
                        TextEntry::make('exception_preview')->columnSpanFull(),
                    ]),
            ]);
    }
}
