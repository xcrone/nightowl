<?php

namespace App\Filament\Resources\RequestRecords;

use App\Filament\Resources\RequestRecords\Pages\ListRequestRecords;
use App\Filament\Resources\RequestRecords\Pages\ViewRequestRecord;
use App\Filament\Resources\RequestRecords\Schemas\RequestRecordInfolist;
use App\Filament\Resources\RequestRecords\Tables\RequestRecordsTable;
use App\Models\RequestRecord;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RequestRecordResource extends Resource
{
    protected static ?string $model = RequestRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = 'HTTP';

    protected static ?int $navigationSort = 1;

    public static function infolist(Schema $schema): Schema
    {
        return RequestRecordInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RequestRecordsTable::configure($table);
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
            'index' => ListRequestRecords::route('/'),
            'view' => ViewRequestRecord::route('/{record}'),
        ];
    }
}
