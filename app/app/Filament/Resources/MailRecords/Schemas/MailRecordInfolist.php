<?php

namespace App\Filament\Resources\MailRecords\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MailRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Mail')
                    ->columns(2)
                    ->components([
                        TextEntry::make('subject')->columnSpanFull(),
                        TextEntry::make('mailable'),
                        TextEntry::make('mailer'),
                        TextEntry::make('recipients')->columnSpanFull(),
                        TextEntry::make('cc'),
                        TextEntry::make('bcc'),
                        TextEntry::make('attachments'),
                        TextEntry::make('failed')->badge(),
                        TextEntry::make('queued')->badge(),
                        TextEntry::make('duration')->label('Duration (ms)'),
                        TextEntry::make('created_at')->dateTime(),
                    ]),
            ]);
    }
}
