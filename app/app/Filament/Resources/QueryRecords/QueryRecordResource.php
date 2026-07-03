<?php

namespace App\Filament\Resources\QueryRecords;

use App\Filament\Resources\QueryRecords\Pages\ListQueryRecords;
use App\Filament\Resources\QueryRecords\Pages\ViewQueryRecord;
use App\Filament\Resources\QueryRecords\Schemas\QueryRecordInfolist;
use App\Filament\Resources\QueryRecords\Tables\QueryRecordsTable;
use App\Models\QueryRecord;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class QueryRecordResource extends Resource
{
    protected static ?string $model = QueryRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Data';

    protected static ?int $navigationSort = 1;

    public static function infolist(Schema $schema): Schema
    {
        return QueryRecordInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QueryRecordsTable::configure($table);
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
            'index' => ListQueryRecords::route('/'),
            'view' => ViewQueryRecord::route('/{record}'),
        ];
    }
}
