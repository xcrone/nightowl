<?php

namespace App\Filament\Resources\OutgoingRequests;

use App\Filament\Resources\OutgoingRequests\Pages\ListOutgoingRequests;
use App\Filament\Resources\OutgoingRequests\Pages\ViewOutgoingRequest;
use App\Filament\Resources\OutgoingRequests\Schemas\OutgoingRequestInfolist;
use App\Filament\Resources\OutgoingRequests\Tables\OutgoingRequestsTable;
use App\Models\OutgoingRequest;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OutgoingRequestResource extends Resource
{
    protected static ?string $model = OutgoingRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static string|UnitEnum|null $navigationGroup = 'HTTP';

    protected static ?int $navigationSort = 2;

    public static function infolist(Schema $schema): Schema
    {
        return OutgoingRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OutgoingRequestsTable::configure($table);
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
            'index' => ListOutgoingRequests::route('/'),
            'view' => ViewOutgoingRequest::route('/{record}'),
        ];
    }
}
