<?php

namespace App\Filament\Resources\ExceptionRecords;

use App\Filament\Resources\ExceptionRecords\Pages\ListExceptionRecords;
use App\Filament\Resources\ExceptionRecords\Pages\ViewExceptionRecord;
use App\Filament\Resources\ExceptionRecords\Schemas\ExceptionRecordInfolist;
use App\Filament\Resources\ExceptionRecords\Tables\ExceptionRecordsTable;
use App\Models\ExceptionRecord;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ExceptionRecordResource extends Resource
{
    protected static ?string $model = ExceptionRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Overview';

    protected static ?int $navigationSort = 2;

    public static function infolist(Schema $schema): Schema
    {
        return ExceptionRecordInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExceptionRecordsTable::configure($table);
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
            'index' => ListExceptionRecords::route('/'),
            'view' => ViewExceptionRecord::route('/{record}'),
        ];
    }
}
