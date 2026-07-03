<?php

namespace App\Filament\Resources\ScheduledTasks;

use App\Filament\Resources\ScheduledTasks\Pages\ListScheduledTasks;
use App\Filament\Resources\ScheduledTasks\Pages\ViewScheduledTask;
use App\Filament\Resources\ScheduledTasks\Schemas\ScheduledTaskInfolist;
use App\Filament\Resources\ScheduledTasks\Tables\ScheduledTasksTable;
use App\Models\ScheduledTask;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ScheduledTaskResource extends Resource
{
    protected static ?string $model = ScheduledTask::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Execution';

    protected static ?int $navigationSort = 3;

    public static function infolist(Schema $schema): Schema
    {
        return ScheduledTaskInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ScheduledTasksTable::configure($table);
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
            'index' => ListScheduledTasks::route('/'),
            'view' => ViewScheduledTask::route('/{record}'),
        ];
    }
}
