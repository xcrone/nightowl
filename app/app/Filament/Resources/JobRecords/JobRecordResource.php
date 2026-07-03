<?php

namespace App\Filament\Resources\JobRecords;

use App\Filament\Resources\JobRecords\Pages\ListJobRecords;
use App\Filament\Resources\JobRecords\Pages\ViewJobRecord;
use App\Filament\Resources\JobRecords\Schemas\JobRecordInfolist;
use App\Filament\Resources\JobRecords\Tables\JobRecordsTable;
use App\Models\JobRecord;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class JobRecordResource extends Resource
{
    protected static ?string $model = JobRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Execution';

    protected static ?int $navigationSort = 1;

    public static function infolist(Schema $schema): Schema
    {
        return JobRecordInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JobRecordsTable::configure($table);
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
            'index' => ListJobRecords::route('/'),
            'view' => ViewJobRecord::route('/{record}'),
        ];
    }
}
