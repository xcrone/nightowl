<?php

namespace App\Filament\Resources\LogRecords;

use App\Filament\Resources\LogRecords\Pages\ListLogRecords;
use App\Filament\Resources\LogRecords\Pages\ViewLogRecord;
use App\Filament\Resources\LogRecords\Schemas\LogRecordInfolist;
use App\Filament\Resources\LogRecords\Tables\LogRecordsTable;
use App\Models\LogRecord;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LogRecordResource extends Resource
{
    protected static ?string $model = LogRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Logs';

    protected static ?int $navigationSort = 1;

    public static function infolist(Schema $schema): Schema
    {
        return LogRecordInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LogRecordsTable::configure($table);
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
            'index' => ListLogRecords::route('/'),
            'view' => ViewLogRecord::route('/{record}'),
        ];
    }
}
