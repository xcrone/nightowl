<?php

namespace App\Filament\Resources\NotificationRecords;

use App\Filament\Resources\NotificationRecords\Pages\ListNotificationRecords;
use App\Filament\Resources\NotificationRecords\Pages\ViewNotificationRecord;
use App\Filament\Resources\NotificationRecords\Schemas\NotificationRecordInfolist;
use App\Filament\Resources\NotificationRecords\Tables\NotificationRecordsTable;
use App\Models\NotificationRecord;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class NotificationRecordResource extends Resource
{
    protected static ?string $model = NotificationRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static string|UnitEnum|null $navigationGroup = 'Communication';

    protected static ?int $navigationSort = 2;

    public static function infolist(Schema $schema): Schema
    {
        return NotificationRecordInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NotificationRecordsTable::configure($table);
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
            'index' => ListNotificationRecords::route('/'),
            'view' => ViewNotificationRecord::route('/{record}'),
        ];
    }
}
