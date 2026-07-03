<?php

namespace App\Filament\Resources\Issues;

use App\Filament\Resources\Issues\Pages\ListIssues;
use App\Filament\Resources\Issues\Pages\ViewIssue;
use App\Filament\Resources\Issues\Schemas\IssueInfolist;
use App\Filament\Resources\Issues\Tables\IssuesTable;
use App\Models\Issue;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class IssueResource extends Resource
{
    protected static ?string $model = Issue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFire;

    protected static string|UnitEnum|null $navigationGroup = 'Overview';

    protected static ?int $navigationSort = 1;

    public static function infolist(Schema $schema): Schema
    {
        return IssueInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IssuesTable::configure($table);
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
            'index' => ListIssues::route('/'),
            'view' => ViewIssue::route('/{record}'),
        ];
    }
}
