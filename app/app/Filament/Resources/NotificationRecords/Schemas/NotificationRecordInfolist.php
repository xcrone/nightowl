<?php

namespace App\Filament\Resources\NotificationRecords\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NotificationRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Notification')
                    ->columns(2)
                    ->components([
                        TextEntry::make('notification')->columnSpanFull(),
                        TextEntry::make('channel')->badge(),
                        TextEntry::make('notifiable_type'),
                        TextEntry::make('notifiable_id'),
                        TextEntry::make('failed')->badge(),
                        TextEntry::make('queued')->badge(),
                        TextEntry::make('duration')->label('Duration (ms)'),
                        TextEntry::make('created_at')->dateTime(),
                    ]),
            ]);
    }
}
