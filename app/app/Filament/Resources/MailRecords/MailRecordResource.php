<?php

namespace App\Filament\Resources\MailRecords;

use App\Filament\Resources\MailRecords\Pages\ListMailRecords;
use App\Filament\Resources\MailRecords\Pages\ViewMailRecord;
use App\Filament\Resources\MailRecords\Schemas\MailRecordInfolist;
use App\Filament\Resources\MailRecords\Tables\MailRecordsTable;
use App\Models\MailRecord;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MailRecordResource extends Resource
{
    protected static ?string $model = MailRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Communication';

    protected static ?int $navigationSort = 1;

    public static function infolist(Schema $schema): Schema
    {
        return MailRecordInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MailRecordsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMailRecords::route('/'),
            'view' => ViewMailRecord::route('/{record}'),
        ];
    }
}
