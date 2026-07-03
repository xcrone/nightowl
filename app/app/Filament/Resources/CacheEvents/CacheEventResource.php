<?php

namespace App\Filament\Resources\CacheEvents;

use App\Filament\Resources\CacheEvents\Pages\ListCacheEvents;
use App\Filament\Resources\CacheEvents\Pages\ViewCacheEvent;
use App\Filament\Resources\CacheEvents\Schemas\CacheEventInfolist;
use App\Filament\Resources\CacheEvents\Tables\CacheEventsTable;
use App\Models\CacheEvent;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CacheEventResource extends Resource
{
    protected static ?string $model = CacheEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = 'Data';

    protected static ?int $navigationSort = 2;

    public static function infolist(Schema $schema): Schema
    {
        return CacheEventInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CacheEventsTable::configure($table);
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
            'index' => ListCacheEvents::route('/'),
            'view' => ViewCacheEvent::route('/{record}'),
        ];
    }
}
