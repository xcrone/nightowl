<?php

namespace App\Filament\Resources\JobRecords\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class JobRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Job')
                    ->columns(2)
                    ->components([
                        TextEntry::make('job_class')->columnSpanFull(),
                        TextEntry::make('queue'),
                        TextEntry::make('connection'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('attempts'),
                        TextEntry::make('duration')->label('Duration (ms)'),
                        TextEntry::make('job_id'),
                        TextEntry::make('attempt_id'),
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
