<?php

namespace App\Filament\Resources\CommandRecords;

use App\Filament\Resources\CommandRecords\Pages\ListCommandRecords;
use App\Filament\Resources\CommandRecords\Pages\ViewCommandRecord;
use App\Filament\Resources\CommandRecords\Schemas\CommandRecordInfolist;
use App\Filament\Resources\CommandRecords\Tables\CommandRecordsTable;
use App\Models\CommandRecord;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CommandRecordResource extends Resource
{
    protected static ?string $model = CommandRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static string|UnitEnum|null $navigationGroup = 'Execution';

    protected static ?int $navigationSort = 2;

    public static function infolist(Schema $schema): Schema
    {
        return CommandRecordInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommandRecordsTable::configure($table);
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
            'index' => ListCommandRecords::route('/'),
            'view' => ViewCommandRecord::route('/{record}'),
        ];
    }
}
