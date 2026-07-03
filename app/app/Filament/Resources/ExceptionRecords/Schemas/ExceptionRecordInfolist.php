<?php

namespace App\Filament\Resources\ExceptionRecords\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExceptionRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Exception')
                    ->columns(2)
                    ->components([
                        TextEntry::make('class')->columnSpanFull(),
                        TextEntry::make('message')->columnSpanFull(),
                        TextEntry::make('file'),
                        TextEntry::make('line'),
                        TextEntry::make('handled')->badge(),
                        TextEntry::make('code'),
                        TextEntry::make('execution_source')->label('Source'),
                        TextEntry::make('execution_stage')->label('Stage'),
                        TextEntry::make('user_id'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('fingerprint'),
                        TextEntry::make('php_version'),
                        TextEntry::make('laravel_version'),
                    ]),
                Section::make('Stack trace')
                    ->components([
                        TextEntry::make('trace')
                            ->columnSpanFull()
                            ->prose()
                            ->extraAttributes(['class' => 'font-mono text-xs whitespace-pre-wrap']),
                    ]),
            ]);
    }
}
