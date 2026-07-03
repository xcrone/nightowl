<?php

namespace App\Filament\Resources\ScheduledTasks\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ScheduledTaskInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Scheduled task')
                    ->columns(2)
                    ->components([
                        TextEntry::make('command')->columnSpanFull(),
                        TextEntry::make('expression'),
                        TextEntry::make('timezone'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('exit_code'),
                        TextEntry::make('duration')->label('Duration (ms)'),
                        TextEntry::make('without_overlapping')->badge(),
                        TextEntry::make('on_one_server')->badge(),
                        TextEntry::make('run_in_background')->badge(),
                        TextEntry::make('created_at')->dateTime(),
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
