<?php

namespace App\Filament\Resources\Issues\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IssueInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Issue')
                    ->columns(2)
                    ->components([
                        TextEntry::make('type'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('priority')->badge(),
                        TextEntry::make('assigned_to'),
                        TextEntry::make('exception_class')->columnSpanFull(),
                        TextEntry::make('exception_message')->columnSpanFull(),
                        TextEntry::make('occurrences_count')->label('Occurrences'),
                        TextEntry::make('users_count')->label('Users affected'),
                        TextEntry::make('first_seen_at')->dateTime(),
                        TextEntry::make('last_seen_at')->dateTime(),
                        TextEntry::make('group_hash'),
                    ]),
            ]);
    }
}
