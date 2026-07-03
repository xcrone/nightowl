<?php

namespace App\Filament\Resources\JobRecords\Pages;

use App\Filament\Resources\JobRecords\JobRecordResource;
use Filament\Resources\Pages\ListRecords;

class ListJobRecords extends ListRecords
{
    protected static string $resource = JobRecordResource::class;
}
